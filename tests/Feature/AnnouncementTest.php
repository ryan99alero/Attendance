<?php

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Credential;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Services\AnnouncementService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->department = Department::factory()->create();
    $this->employee = Employee::factory()->create([
        'department_id' => $this->department->id,
        'is_active' => true,
    ]);
    $this->user = User::factory()->admin()->create([
        'employee_id' => $this->employee->id,
    ]);
    $this->credential = Credential::factory()->create([
        'employee_id' => $this->employee->id,
        'kind' => 'rfid',
        'is_active' => true,
    ]);
    $this->device = Device::factory()->create([
        'registration_status' => 'approved',
        'is_active' => true,
    ]);
});

describe('Announcement Model', function () {
    it('has correct constants', function () {
        expect(Announcement::TARGET_ALL)->toBe('all')
            ->and(Announcement::TARGET_DEPARTMENT)->toBe('department')
            ->and(Announcement::TARGET_EMPLOYEE)->toBe('employee')
            ->and(Announcement::AUDIO_NONE)->toBe('none')
            ->and(Announcement::AUDIO_BUZZ)->toBe('buzz')
            ->and(Announcement::AUDIO_TTS)->toBe('tts')
            ->and(Announcement::AUDIO_READ_ALOUD)->toBe('read_aloud')
            ->and(Announcement::PRIORITY_LOW)->toBe('low')
            ->and(Announcement::PRIORITY_NORMAL)->toBe('normal')
            ->and(Announcement::PRIORITY_HIGH)->toBe('high')
            ->and(Announcement::PRIORITY_URGENT)->toBe('urgent');
    });

    it('has static option methods', function () {
        expect(Announcement::getTargetTypeOptions())->toBeArray()
            ->and(Announcement::getAudioTypeOptions())->toBeArray()
            ->and(Announcement::getPriorityOptions())->toBeArray();
    });

    it('belongs to department', function () {
        $announcement = Announcement::factory()->forDepartment($this->department)->create();

        expect($announcement->department->id)->toBe($this->department->id);
    });

    it('belongs to employee', function () {
        $announcement = Announcement::factory()->forEmployee($this->employee)->create();

        expect($announcement->employee->id)->toBe($this->employee->id);
    });

    it('belongs to creator', function () {
        $announcement = Announcement::factory()->create([
            'created_by' => $this->user->id,
        ]);

        expect($announcement->creator->id)->toBe($this->user->id);
    });

    it('has reads relationship', function () {
        $announcement = Announcement::factory()->create();
        $announcement->markAsReadBy($this->employee);

        expect($announcement->reads)->toHaveCount(1);
    });

    it('scopes active announcements correctly', function () {
        Announcement::factory()->create(['is_active' => true]);
        Announcement::factory()->inactive()->create();

        expect(Announcement::active()->count())->toBe(1);
    });

    it('scopes expired announcements correctly', function () {
        Announcement::factory()->create(['expires_at' => now()->addDays(1)]);
        Announcement::factory()->expired()->create();

        expect(Announcement::active()->count())->toBe(1);
    });

    it('scopes scheduled announcements correctly', function () {
        Announcement::factory()->create(['starts_at' => now()->subDay()]);
        Announcement::factory()->scheduled()->create();

        expect(Announcement::active()->count())->toBe(1);
    });

    it('scopes forEmployee with target all', function () {
        Announcement::factory()->create(['target_type' => 'all']);

        expect(Announcement::forEmployee($this->employee)->count())->toBe(1);
    });

    it('scopes forEmployee with target department', function () {
        Announcement::factory()->forDepartment($this->department)->create();

        expect(Announcement::forEmployee($this->employee)->count())->toBe(1);
    });

    it('scopes forEmployee with target employee', function () {
        Announcement::factory()->forEmployee($this->employee)->create();

        expect(Announcement::forEmployee($this->employee)->count())->toBe(1);
    });

    it('marks announcement as read', function () {
        $announcement = Announcement::factory()->create();
        $announcement->markAsReadBy($this->employee);

        expect($announcement->isReadBy($this->employee))->toBeTrue();
    });

    it('acknowledges announcement', function () {
        $announcement = Announcement::factory()->create(['require_acknowledgment' => true]);
        $announcement->acknowledgeBy($this->employee, 'timeclock');

        expect($announcement->isAcknowledgedBy($this->employee))->toBeTrue();
    });

    it('does not duplicate read entries', function () {
        $announcement = Announcement::factory()->create();
        $announcement->markAsReadBy($this->employee);
        $announcement->markAsReadBy($this->employee);

        expect($announcement->reads()->count())->toBe(1);
    });
});

describe('AnnouncementService', function () {
    it('sends announcement to all employees', function () {
        Notification::fake();

        $announcement = Announcement::factory()->create(['target_type' => 'all']);
        $service = app(AnnouncementService::class);

        $count = $service->sendAnnouncement($announcement);

        expect($count)->toBe(1);
        Notification::assertSentTo($this->user, AnnouncementNotification::class);
    });

    it('sends announcement to department employees', function () {
        Notification::fake();

        $otherDept = Department::factory()->create();
        $otherEmployee = Employee::factory()->create([
            'department_id' => $otherDept->id,
            'is_active' => true,
        ]);
        User::factory()->create(['employee_id' => $otherEmployee->id]);

        $announcement = Announcement::factory()->forDepartment($this->department)->create();
        $service = app(AnnouncementService::class);

        $count = $service->sendAnnouncement($announcement);

        expect($count)->toBe(1);
        Notification::assertSentTo($this->user, AnnouncementNotification::class);
    });

    it('sends announcement to specific employee', function () {
        Notification::fake();

        $announcement = Announcement::factory()->forEmployee($this->employee)->create();
        $service = app(AnnouncementService::class);

        $count = $service->sendAnnouncement($announcement);

        expect($count)->toBe(1);
        Notification::assertSentTo($this->user, AnnouncementNotification::class);
    });

    it('gets unread announcements for employee', function () {
        $announcement = Announcement::factory()->create(['target_type' => 'all']);
        $service = app(AnnouncementService::class);

        expect($service->getUnreadAnnouncementsForEmployee($this->employee))->toHaveCount(1);

        $service->markAsRead($announcement, $this->employee);

        expect($service->getUnreadAnnouncementsForEmployee($this->employee))->toHaveCount(0);
    });

    it('gets time clock announcements', function () {
        $announcement = Announcement::factory()->create([
            'target_type' => 'all',
            'require_acknowledgment' => true,
        ]);
        $service = app(AnnouncementService::class);

        $announcements = $service->getTimeClockAnnouncements($this->employee);

        expect($announcements)->toHaveCount(1);
    });

    it('returns correct unread count', function () {
        Announcement::factory()->count(3)->create(['target_type' => 'all']);
        $service = app(AnnouncementService::class);

        expect($service->getUnreadCount($this->employee))->toBe(3);
    });

    it('marks announcement as read via service', function () {
        $announcement = Announcement::factory()->create();
        $service = app(AnnouncementService::class);

        $service->markAsRead($announcement, $this->employee, 'portal');

        expect($announcement->isReadBy($this->employee))->toBeTrue();
    });

    it('acknowledges announcement via service', function () {
        $announcement = Announcement::factory()->create(['require_acknowledgment' => true]);
        $service = app(AnnouncementService::class);

        $service->acknowledge($announcement, $this->employee, 'timeclock');

        expect($announcement->isAcknowledgedBy($this->employee))->toBeTrue();
    });
});

describe('Announcement API Endpoints', function () {
    it('returns announcements for employee via credential', function () {
        $announcement = Announcement::factory()->create([
            'target_type' => 'all',
            'audio_type' => 'tts',
            'priority' => 'high',
        ]);

        $response = $this->getJson('/api/v1/timeclock/announcements/'.$this->credential->identifier.'?kind=rfid');

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee_id', $this->employee->id)
            ->assertJsonPath('data.announcements.0.id', $announcement->id)
            ->assertJsonPath('data.announcements.0.audio_type', 'tts')
            ->assertJsonPath('data.announcements.0.priority', 'high');
    });

    it('returns 404 for unknown credential', function () {
        $response = $this->getJson('/api/v1/timeclock/announcements/UNKNOWN123?kind=rfid');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    });

    it('marks announcement as read via API', function () {
        $announcement = Announcement::factory()->create();

        $response = $this->postJson('/api/v1/timeclock/announcements/'.$announcement->id.'/read', [
            'credential_value' => $this->credential->identifier,
            'credential_kind' => 'rfid',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.announcement_id', $announcement->id);

        expect($announcement->isReadBy($this->employee))->toBeTrue();
    });

    it('acknowledges announcement via API', function () {
        $announcement = Announcement::factory()->create(['require_acknowledgment' => true]);

        $response = $this->postJson('/api/v1/timeclock/announcements/'.$announcement->id.'/acknowledge', [
            'credential_value' => $this->credential->identifier,
            'credential_kind' => 'rfid',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.announcement_id', $announcement->id);

        expect($announcement->isAcknowledgedBy($this->employee))->toBeTrue();
    });

    it('returns error when acknowledging non-acknowledgment announcement', function () {
        $announcement = Announcement::factory()->create(['require_acknowledgment' => false]);

        $response = $this->postJson('/api/v1/timeclock/announcements/'.$announcement->id.'/acknowledge', [
            'credential_value' => $this->credential->identifier,
            'credential_kind' => 'rfid',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Announcement does not require acknowledgment');
    });
});

describe('AnnouncementResource Configuration', function () {
    it('has correct navigation configuration', function () {
        expect(AnnouncementResource::getNavigationGroup())->toBe('Employee Management')
            ->and(AnnouncementResource::getModelLabel())->toBe('announcement');
    });

    it('resource has correct pages', function () {
        $pages = AnnouncementResource::getPages();

        expect($pages)->toHaveKey('index')
            ->and($pages)->toHaveKey('create')
            ->and($pages)->toHaveKey('edit');
    });
});

describe('AnnouncementNotification', function () {
    it('creates notification with correct structure', function () {
        $announcement = Announcement::factory()->create([
            'title' => 'Test Notification',
            'body' => 'Notification body',
            'priority' => 'high',
        ]);

        $notification = new AnnouncementNotification($announcement);
        $array = $notification->toArray($this->user);

        expect($array['announcement_id'])->toBe($announcement->id)
            ->and($array['title'])->toBe('Test Notification')
            ->and($array['priority'])->toBe('high');
    });

    it('uses database channel', function () {
        $announcement = Announcement::factory()->create();
        $notification = new AnnouncementNotification($announcement);

        expect($notification->via($this->user))->toContain('database');
    });
});
