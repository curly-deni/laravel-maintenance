<?php

namespace Aesis\Maintenance\Enums;

enum MaintenancePhase: string
{
    case NORMAL = 'normal';
    case SCHEDULED = 'scheduled';
    case DRAINING = 'draining';
    case MAINTENANCE = 'maintenance';
}
