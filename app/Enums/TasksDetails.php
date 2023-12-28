<?php
declare(strict_types=1);

namespace App\Enums;

enum TasksDetails: string
{
    case UZBEKISTAN_WORK_TIME_START = '9:00';

    case UZBEKISTAN_WORK_TIME_END = '18:00';

    case UZBEKISTAN_TIMEZONE = 'Asia/Tashkent';
}
