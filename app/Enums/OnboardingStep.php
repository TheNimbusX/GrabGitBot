<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case AskGender = 'ask_gender';
    case AskAge = 'ask_age';
    case AskWeight = 'ask_weight';
    case AskHeight = 'ask_height';
    case AskActivity = 'ask_activity';
    case AskGoal = 'ask_goal';
    case AskExperience = 'ask_experience';
    case AskSleep = 'ask_sleep';
    case AskBeforePhoto = 'ask_before_photo';
}
