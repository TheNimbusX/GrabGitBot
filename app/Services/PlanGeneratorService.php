<?php

namespace App\Services;

use App\Enums\ActivityLevel;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Models\User;

class PlanGeneratorService
{
    /**
     * Калории (Mifflin-St Jeor + активность) и БЖУ с учётом пола, возраста и активности.
     */
    public function applyBasePlan(User $user): void
    {
        $weight = (float) $user->weight_kg;
        $height = (int) $user->height_cm;
        $goal = FitnessGoal::tryFrom((string) $user->goal) ?? FitnessGoal::Maintain;
        $gender = Gender::tryFrom((string) $user->gender) ?? Gender::Male;
        $activity = ActivityLevel::tryFrom((string) $user->activity_level) ?? ActivityLevel::Medium;
        $age = (int) ($user->age ?? 28);
        $age = max(14, min(100, $age));

        $bmr = $this->mifflinStJeorBmr($weight, $height, $gender, $age);
        $activityFactor = match ($activity) {
            ActivityLevel::Low => 1.2,
            ActivityLevel::Medium => 1.375,
            ActivityLevel::High => 1.55,
        };
        $tdee = (int) round($bmr * $activityFactor);

        $targetKcal = match ($goal) {
            FitnessGoal::Bulk => $tdee + 300,
            FitnessGoal::Cut => max(1200, $tdee - 450),
            FitnessGoal::Maintain => $tdee,
        };

        $proteinPerKg = match ([$goal, $gender]) {
            [FitnessGoal::Cut, Gender::Male] => 2.0,
            [FitnessGoal::Cut, Gender::Female] => 1.85,
            [FitnessGoal::Bulk, Gender::Male] => 1.8,
            [FitnessGoal::Bulk, Gender::Female] => 1.65,
            [FitnessGoal::Maintain, Gender::Male] => 1.6,
            [FitnessGoal::Maintain, Gender::Female] => 1.5,
        };

        $activityProteinBoost = match ($activity) {
            ActivityLevel::Low => 0.0,
            ActivityLevel::Medium => 0.05,
            ActivityLevel::High => 0.1,
        };
        $proteinPerKg = min(2.2, $proteinPerKg + $activityProteinBoost);

        $proteinG = (int) round($weight * $proteinPerKg);
        $fatPerKg = $gender === Gender::Female ? 0.8 : 0.9;
        $fatG = (int) round($weight * $fatPerKg);
        $proteinKcal = $proteinG * 4;
        $fatKcal = $fatG * 9;
        $carbsG = (int) max(0, round(($targetKcal - $proteinKcal - $fatKcal) / 4));

        $user->daily_calories_target = $targetKcal;
        $user->protein_g = $proteinG;
        $user->fat_g = $fatG;
        $user->carbs_g = $carbsG;
        $user->water_goal_ml = 3000;

        $user->save();
    }

    public function buildPlanMessage(User $user): string
    {
        if ($user->isDisciplineOnlyMode()) {
            $lines = [
                '📋 <b>Режим «свой план»</b>',
                '',
                'Ты выбрал бота для <b>дисциплины и чек-инов</b> - без расчёта калорий и меню от FitBot.',
                '',
                '💧 <b>Вода</b>: цель <b>'.(int) $user->water_goal_ml.'</b> мл/день',
                '😴 <b>Сон</b>: цель <b>'.$user->sleep_target_hours.'</b> ч',
            ];
            if ($user->height_cm && $user->weight_kg) {
                $lines[] = '';
                $lines[] = '📌 В профиле: <b>'.(int) $user->height_cm.'</b> см, <b>'.$user->weight_kg.'</b> кг';
            }

            return implode("\n", $lines);
        }

        $menu = $this->exampleDayMenu($user);
        $sleep = $user->sleep_target_hours;
        $workout = $this->buildWorkoutBlock($user);

        return implode("\n", [
            '📋 <b>Твой стартовый план FitBot</b>',
            '',
            '🍽 <b>Питание</b>',
            'Калории (оценка): <b>'.$user->daily_calories_target.'</b> ккал/день',
            'БЖУ: белки <b>'.$user->protein_g.'</b> г, жиры <b>'.$user->fat_g.'</b> г, углеводы <b>'.$user->carbs_g.'</b> г',
            '',
            '🥗 <b>Пример дня</b>',
            $menu,
            '',
            '💧 <b>Вода</b>',
            'Ориентир: <b>'.(int) $user->water_goal_ml.'</b> мл/день (можно округлить «на глаз»).',
            '',
            '😴 <b>Сон</b>',
            'Ориентир: <b>'.$sleep.'</b> ч (как ты указал).',
            trim($workout),
        ]);
    }

    private function mifflinStJeorBmr(float $weightKg, int $heightCm, Gender $gender, int $age): float
    {
        $base = 10 * $weightKg + 6.25 * $heightCm - 5 * $age;

        return $gender === Gender::Male ? $base + 5 : $base - 161;
    }

    private function buildWorkoutBlock(User $user): string
    {
        $gender = Gender::tryFrom((string) $user->gender) ?? Gender::Male;

        if ($gender === Gender::Female) {
            return <<<'TXT'

<b>Тренировки (PPL, акцент на ноги и ягодицы)</b>

<b>Push</b> - грудь, плечи, трицепс:
• Жим гантелей лёжа / в наклоне 3×10-12
• Отжимания / брусья с акцентом на грудь 3×8-12
• Жим сидя гантелями 3×10-12
• Разведения в стороны 3×12-15
• Разгибания на трицепс 3×12-15

<b>Pull</b> - спина, бицепс:
• Тяга верхнего блока / подтягивания 3×8-12
• Тяга одной рукой в наклоне 3×10-12
• Тяга к поясу в наклоне 3×10-12
• Сгибания на бицепс 3×10-12
• Молотки 3×10-12

<b>Legs</b> - ноги, ягодицы:
• Присед / гоблет 3×10-12
• Ягодичный мост / хип-траст 3×12-15
• Выпады / болгарские 3×10 с каждой
• Румынская тяга 3×8-10
• Разгибания ног + ягодичный мостик на мяче 3×15

Чередуй дни: Push → Pull → отдых → Legs → отдых.
TXT;
        }

        return <<<'TXT'

<b>Тренировки (PPL, база)</b>

<b>Push</b> - грудь, плечи, трицепс:
• Жим штанги лёжа 3×8-12
• Жим гантелей на наклонной 3×10-12
• Разведения 3×12-15
• Жим стоя / арнольд 3×10-12
• Разведения в стороны 3×12-15
• Французский жим 3×10-12

<b>Pull</b> - спина, бицепс:
• Подтягивания / тяга верхнего блока 3×8-12
• Тяга штанги в наклоне 3×8-12
• Тяга одной рукой 3×10-12
• Подъём штанги на бицепс 3×10-12
• Молотки 3×10-12

<b>Legs</b> - ноги:
• Приседания 3×8-10
• Жим ногами 3×10-12
• Румынская тяга 3×8-10
• Разгибания / сгибания ног 3×12-15
• Икры 4×15-20

Чередуй дни: Push → Pull → отдых → Legs → отдых.
TXT;
    }

    private function exampleDayMenu(User $user): string
    {
        $kcal = (int) $user->daily_calories_target;
        $p = (int) $user->protein_g;
        $f = (int) $user->fat_g;
        $c = (int) $user->carbs_g;

        return implode("\n", [
            'Завтрак: овсянка 60 г сухой + молоко/йогурт, яйца 2 шт., фрукт.',
            'Перекус: творог 150-200 г или протеин.',
            'Обед: куриная грудка/индейка 180-220 г, гарнир (рис/греча) 60-80 г сухого, салат с маслом.',
            'Перекус: орехи 20-30 г + овощи.',
            'Ужин: рыба/мясо 150-200 г, овощи, немного углеводов по остатку.',
            '',
            '<i>Ориентир по калориям: ~'.$kcal.' ккал; БЖУ ~'.$p.'/'.$f.'/'.$c.' г.</i>',
        ]);
    }
}
