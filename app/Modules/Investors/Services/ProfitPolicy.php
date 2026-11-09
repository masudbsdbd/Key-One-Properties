<?php

namespace App\Modules\Investors\Services;

use App\Support\Settings;

/**
 * Client decisions that gate profit approval. Until both are set, profit stays provisional.
 */
class ProfitPolicy
{
    public const RECOGNITION = [
        'sale_approved' => 'After sale approval, when admin approves',
        'registered' => 'Registration completed',
        'handed_over' => 'Handover completed',
        'fully_collected' => 'Fully collected',
    ];

    public const COST_POLICIES = [
        'area_shared' => 'Unit-tagged cost is direct; other project cost is shared by saleable area',
    ];

    public function __construct(private Settings $settings) {}

    public function revenueRecognition(): ?string
    {
        $value = $this->settings->get('investors.revenue_recognition');

        return array_key_exists((string) $value, self::RECOGNITION) ? $value : null;
    }

    public function costPolicy(): ?string
    {
        $value = $this->settings->get('investors.cost_policy');

        return array_key_exists((string) $value, self::COST_POLICIES) ? $value : null;
    }

    public function blockingReason(): ?string
    {
        return match (true) {
            $this->revenueRecognition() === null => 'Revenue recognition policy is not set',
            $this->costPolicy() === null => 'Cost policy is not set',
            default => null,
        };
    }
}
