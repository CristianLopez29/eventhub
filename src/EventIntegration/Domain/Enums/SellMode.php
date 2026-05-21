<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Enums;

enum SellMode: string
{
    case ONLINE = 'online';
    case OFFLINE = 'offline';
}
