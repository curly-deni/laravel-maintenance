<?php

namespace Aesis\Maintenance\Http\Middleware;

use Aesis\Maintenance\Enums\MaintenancePhase;
use Aesis\Maintenance\Services\MaintenancePolicy;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RejectNewWorkDuringMaintenance
{
    public function __construct(private readonly MaintenancePolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->policy->state();
        if (($state['mode'] ?? MaintenancePhase::NORMAL->value) !== MaintenancePhase::DRAINING->value) {
            return $next($request);
        }

        $retryAfter = $state['expected_end_at'] ?? null
            ? max(60, now('UTC')->diffInSeconds($state['expected_end_at'], false))
            : 1800;

        return new JsonResponse([
            'code' => 'maintenance_draining_new_work_disabled',
            'message' => 'Новые операции временно недоступны.',
        ], 503, ['Retry-After' => (string) $retryAfter]);
    }
}
