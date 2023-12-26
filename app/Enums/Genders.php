<?php

namespace App\Enums;

enum Genders :string
{
    case MALE = 'Мужчина';
    case FEMALE = 'Женщина';
    case NON_BINARY = 'Не бинарный';
}
