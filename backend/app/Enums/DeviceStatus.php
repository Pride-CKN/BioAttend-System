<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case Online = 'ONLINE';
    case Offline = 'OFFLINE';
    case Error = 'ERROR';
}
