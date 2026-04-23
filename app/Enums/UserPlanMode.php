<?php

namespace App\Enums;

enum UserPlanMode: string
{
    /** План калорий/БЖУ и пример дня от бота. */
    case Full = 'full';
    /** Свой план снаружи — бот для чек-инов и дисциплины. */
    case Discipline = 'discipline';
}
