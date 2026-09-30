<?php

namespace Aesis\Maintenance\Enums;

enum MaintenanceWindowStatus: string
{
    case SCHEDULED = 'scheduled';
    case DRAINING = 'draining';
    case STARTED = 'started';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
}
