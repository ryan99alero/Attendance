<?php

namespace Tests\Unit;

use App\Models\RoundingRule;
use App\Services\AttendanceProcessing\RoundingRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundingRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RoundingRuleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RoundingRuleService();
    }

    /** @test */
    public function it_can_find_a_matching_rounding_rule()
    {
        // Arrange
        RoundingRule::factory()->create([
            'round_group_id' => 1,
            'minute_min' => 0,
            'minute_max' => 5,
            'new_minute' => 0,
        ]);

        // Act
        $result = $this->service->findRule(3, 1);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(0, $result->new_minute);
    }

    /** @test */
    public function it_returns_original_minutes_if_no_rule_is_found()
    {
        // Act
        $result = $this->service->applyRoundingRule(7, 1);

        // Assert
        $this->assertEquals(7, $result);
    }

    /** @test */
    public function it_applies_a_rounding_rule_correctly()
    {
        // Arrange
        RoundingRule::factory()->create([
            'round_group_id' => 1,
            'minute_min' => 0,
            'minute_max' => 5,
            'new_minute' => 0,
        ]);

        // Act
        $result = $this->service->applyRoundingRule(4, 1);

        // Assert
        $this->assertEquals(0, $result);
    }

    /** @test */
    public function it_validates_non_overlapping_rules()
    {
        // Arrange
        RoundingRule::factory()->create([
            'round_group_id' => 1,
            'minute_min' => 0,
            'minute_max' => 5,
        ]);

        RoundingRule::factory()->create([
            'round_group_id' => 1,
            'minute_min' => 6,
            'minute_max' => 10,
        ]);

        // Act
        $isValid = $this->service->validateRules(1);

        // Assert
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_detects_overlapping_rules()
    {
        // Arrange
        RoundingRule::factory()->create([
            'round_group_id' => 1,
            'minute_min' => 0,
            'minute_max' => 5,
        ]);

        RoundingRule::factory()->create([
            'round_group_id' => 1,
            'minute_min' => 4,
            'minute_max' => 10,
        ]);

        // Act
        $isValid = $this->service->validateRules(1);

        // Assert
        $this->assertFalse($isValid);
    }
}
