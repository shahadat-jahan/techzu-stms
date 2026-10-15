<?php

namespace App\Services;

use App\Enums\Priority;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;

class SlaService
{
    public function __construct(
        private readonly BusinessHoursCalculatorService $businessHoursCalculator,
    ) {}

    public function calculateDue(Priority $priority, CarbonInterface $start): CarbonImmutable
    {
        return $this->businessHoursCalculator->addBusinessMinutes($start, $this->resolutionMinutes($priority));
    }

    public function calculateWarning(Priority $priority, CarbonInterface $start): CarbonImmutable
    {
        return $this->businessHoursCalculator->addBusinessMinutes($start, $this->warningMinutes($priority));
    }

    /**
     * Recompute a ticket's deadlines from its original creation time so that
     * raising or lowering the priority cannot reset the resolution window.
     */
    public function applyTo(Ticket $ticket): void
    {
        $ticket->due_at = $this->calculateDue($ticket->priority, $ticket->created_at);
        $ticket->sla_warning_at = $this->calculateWarning($ticket->priority, $ticket->created_at);
    }

    private function resolutionMinutes(Priority $priority): int
    {
        return $this->policyFor($priority)['resolution_minutes'];
    }

    private function warningMinutes(Priority $priority): int
    {
        return $this->policyFor($priority)['warning_minutes'];
    }

    /**
     * @return array{resolution_minutes: int, warning_minutes: int}
     */
    private function policyFor(Priority $priority): array
    {
        $policy = SlaPolicy::where('priority', $priority)
            ->where('is_active', true)
            ->first();

        if ($policy instanceof SlaPolicy) {
            return [
                'resolution_minutes' => $policy->resolution_minutes,
                'warning_minutes' => $policy->warning_minutes,
            ];
        }

        $defaultResolutionMinutes = (int) Config::get('support.default_sla_minutes', 1440);
        $warningPercent = (int) Config::get('support.warning_percent', 80);

        return [
            'resolution_minutes' => $defaultResolutionMinutes,
            'warning_minutes' => (int) round($defaultResolutionMinutes * ($warningPercent / 100)),
        ];
    }
}
