<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $device_name Name of the device
 * @property string|null $ip_address IP address of the device
 * @property int $is_active Indicates if the device is active
 * @property int|null $department_id Foreign key to Departments
 * @property int|null $created_by Foreign key to Users for record creator
 * @property int|null $updated_by Foreign key to Users for last updater
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Department|null $department
 * @property-read \App\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereDeviceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class Device extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'device_id',
        'device_name',
        'mac_address',
        'ip_address',
        'last_seen_at',
        'last_ip',
        'last_mac',
        'firmware_version',
        'last_wakeup_at',
        'is_active',
        'department_id',
        'created_by',
        'updated_by',
    ];

    /**
     * The department to which the device belongs.
     */
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the user who created the device.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the device.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
