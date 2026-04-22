<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case AskWeight = 'ask_weight';
    case AskHeight = 'ask_height';
    case AskGoal = 'ask_goal';
    case AskExperience = 'ask_experience';
    case AskSleep = 'ask_sleep';
    case AskBeforePhoto = 'ask_before_photo';
}
