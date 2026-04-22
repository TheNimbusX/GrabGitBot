<?php

namespace App\Services;

use App\Enums\FitnessGoal;
use App\Models\User;

class PlanGeneratorService
{
    /**
     * Грубая оценка калорий и БЖУ + цель по воде. Без персонализации под возраст (MVP).
     */
    public function applyBasePlan(User $user): void
    {
        $weight = (float) $user->weight_kg;
        $goal = FitnessGoal::tryFrom((string) $user->goal) ?? FitnessGoal::Maintain;

        $maintenance = (int) round($weight * 24);

        $targetKcal = match ($goal) {
            FitnessGoal::Bulk => $maintenance + 300,
            FitnessGoal::Cut => max(1200, $maintenance - 450),
            FitnessGoal::Maintain => $maintenance,
        };

        $proteinPerKg = match ($goal) {
            FitnessGoal::Cut => 2.0,
            FitnessGoal::Bulk => 1.8,
            FitnessGoal::Maintain => 1.6,
        };

        $proteinG = (int) round($weight * $proteinPerKg);
        $fatG = (int) round($weight * 0.9);
        $proteinKcal = $proteinG * 4;
        $fatKcal = $fatG * 9;
        $carbsG = (int) max(0, round(($targetKcal - $proteinKcal - $fatKcal) / 4));

        $user->daily_calories_target = $targetKcal;
        $user->protein_g = $proteinG;
        $user->fat_g = $fatG;
        $user->carbs_g = $carbsG;

        $user->water_goal_ml = $this->waterGoalMl($user->telegram_id);

        $user->save();
    }

    public function buildPlanMessage(User $user): string
    {
        $menu = $this->exampleDayMenu($user);

        $sleep = $user->sleep_target_hours;
        $waterL = round(($user->water_goal_ml ?? 2500) / 1000, 1);

        $workout = <<<'TXT'
<b>Тренировки (база PPL, без персонализации)</b>

<b>Push</b> — грудь, плечи, трицепс:
• Жим штанги лёжа 3×8–12
• Жим гантелей на наклонной 3×10–12
• Разведения 3×12–15
• Жим стоя / арнольд 3×10–12
• Разведения в стороны 3×12–15
• Французский жим 3×10–12

<b>Pull</b> — спина, бицепс:
• Подтягивания / тяга верхнего блока 3×8–12
• Тяга штанги в наклоне 3×8–12
• Тяга одной рукой 3×10–12
• Подъём штанги на бицепс 3×10–12
• Молотки 3×10–12

<b>Legs</b> — ноги:
• Приседания 3×8–10
• Жим ногами 3×10–12
• Румынская тяга 3×8–10
• Разгибания / сгибания ног 3×12–15
• Икры 4×15–20

Чередуй дни: Push → Pull → Legs → отдых (или сразу новый цикл).
TXT;

        return implode("\n", [
            '<b>Твой стартовый план FitBot</b>',
            '',
            '<b>Питание</b>',
            'Калории (оценка): <b>'.$user->daily_calories_target.'</b> ккал/день',
            'БЖУ: белки <b>'.$user->protein_g.'</b> г, жиры <b>'.$user->fat_g.'</b> г, углеводы <b>'.$user->carbs_g.'</b> г',
            '',
            '<b>Пример дня</b>',
            $menu,
            '',
            '<b>Вода</b>',
            'Цель: <b>'.$waterL.'</b> л в день (ориентир 2.5–3 л).',
            '',
            '<b>Сон</b>',
            'Ориентир: <b>'.$sleep.'</b> ч (как ты указал).',
            trim($workout),
        ]);
    }

    private function waterGoalMl(int $telegramId): int
    {
        $base = 2500;
        $spread = 500;

        return $base + ($telegramId % ($spread + 1));
    }

    private function exampleDayMenu(User $user): string
    {
        $kcal = (int) $user->daily_calories_target;
        $p = (int) $user->protein_g;
        $f = (int) $user->fat_g;
        $c = (int) $user->carbs_g;

        return implode("\n", [
            'Завтрак: овсянка 60 г сухой + молоко/йогурт, яйца 2 шт., фрукт.',
            'Перекус: творог 150–200 г или протеин.',
            'Обед: куриная грудка/индейка 180–220 г, гарнир (рис/греча) 60–80 г сухого, салат с маслом.',
            'Перекус: орехи 20–30 г + овощи.',
            'Ужин: рыба/мясо 150–200 г, овощи, немного углеводов по остатку.',
            '',
            '<i>Ориентир по калориям: ~'.$kcal.' ккал; БЖУ ~'.$p.'/'.$f.'/'.$c.' г.</i>',
        ]);
    }
}
