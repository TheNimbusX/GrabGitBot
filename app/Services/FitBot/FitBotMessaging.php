<?php

namespace App\Services\FitBot;

use App\Models\DailyCheck;
use App\Models\User;
use App\Services\RatingService;
use Carbon\Carbon;

/**
 * Тексты бота для Telegram (HTML): короткие фразы, без «нейросетевых» длинных тире.
 */
final class FitBotMessaging
{
    public static function onboardingAfterPlanFooter(): string
    {
        return 'План выше - ориентир на день. Фиксировать факты: <b>Чек-ин</b> или <b>/check</b>.';
    }

    /** Один раз после пары шагов анкеты: зачем вообще вопросы. */
    public static function onboardingValueBridge(): string
    {
        return 'По ответам потом считаю <b>реальный прогресс</b>, а не красивый отчёт. Без цифр всё равно гадаешь.';
    }

    /** После самого первого завершённого чек-ина. */
    public static function firstEverCheckClosing(): string
    {
        return 'Первый чек-ин есть. Через пару дней будет яснее, ритм или самообман 🙂 Вечером могу пнуть, если сам не зайдёшь.';
    }

    public static function welcomeContinueButtonLabel(): string
    {
        return '▶️ Продолжить';
    }

    public static function eveningReminderSoft(RatingService $rating, User $user, Carbon $today): string
    {
        $dayNum = self::dayNumberInBot($user, $today);
        $lines = [];

        if ($dayNum === 2) {
            $lines[] = 'Второй день удобно «забить». Если хочешь форму - не бери отмазку.';
            $lines[] = '';
        } elseif ($dayNum >= 3 && $dayNum <= 5) {
            $lines[] = 'Ты уже '.$dayNum.'‑й день здесь. Обычно тут либо закрепляются, либо тихо сливаются. Вечер покажет.';
            $lines[] = '';
        }

        $lines[] = '🔔 Сегодня ты ещё <b>не закрыл день</b> в чек-ине.';
        $lines[] = '';
        $lines[] = 'Пара минут - и ты хотя бы знаешь, где ты, а не додумываешь.';

        $yesterday = $today->copy()->subDay();
        $missedYesterday = ! $rating->hasCompletedCheckOnDate($user, $yesterday);

        if ($missedYesterday) {
            $lines[] = '';
            $lines[] = 'Вчера не отметился - это уже <b>минус день</b> к дисциплине и к прогрессу, даже если «вроде норм жил».';
            $lines[] = 'Сегодня не усугубляй - зайди в /check.';

            $twoAgo = $today->copy()->subDays(2);
            if (! $rating->hasCompletedCheckOnDate($user, $twoAgo)) {
                $lines[] = '';
                $lines[] = 'И позавчера пусто. Так часто начинают отваливаться - пока не поздно развернуть.';
            }
        }

        $streak = $rating->checkInStreakDays($user, $today);
        if ($streak >= 3) {
            $lines[] = '';
            $lines[] = 'Серия идёт. Сегодня не отметишь - <b>сгорит</b>. Самому себе обиднее всего.';
        }

        return implode("\n", $lines);
    }

    public static function eveningReminderStrict(): string
    {
        return "Ты снова игноришь чек-ин.\n\n"
            .'Так обычно и срываются - не разом, а маленькими «завтра».';
    }

    public static function churnAfterTwoDays(): string
    {
        return "Ты выпал из процесса.\n\n"
            .'Форма так не делается.';
    }

    public static function churnAfterFourDays(): string
    {
        return "Ты остановился.\n\n"
            .'Вернуться сложнее, чем продолжать.';
    }

    public static function comebackHead(): string
    {
        return "Окей. Вернулся.\n\n"
            .'Начинай заново.'
            ."\n"
            .'Без иллюзий.';
    }

    public static function weekPhotoEncouragement(): string
    {
        return "Сделай новое фото.\n\n"
            .'Ты можешь не видеть изменений.'
            ."\n"
            .'Но они уже есть.';
    }

    public static function streakCelebrationLine(int $streak): ?string
    {
        return match ($streak) {
            3 => "🔥 Три дня подряд. Уже не случайность, а зачаток привычки.\n\n"
                .'Дальше либо закрепляют, либо договариваются «и так сойдёт». Второе - дорога назад.',
            5 => "🔥 Пять дней подряд 🔥\n\n"
                .'Ты уже не из тех, кто скачал и забыл. Пропустишь вечером - серия обнулится, обидно будет.'
                ."\n\n"
                .'💎 PRO потом - жёстче контроль. Пока просто не сливай то, что настроил.',
            7 => "🔥 Неделя без дыр в чек-ине.\n\n"
                .'Редкость. Дальше либо система, либо слив на ровном месте.',
            10 => "🔥 10 дней подряд.\n\n"
                .'Уже привычка, не вдохновение. Большинство сюда не доходит.',
            14 => "🔥 Две недели в ритме.\n\n"
                .'Не останавливайся на полпути.',
            21 => "🔥 Три недели.\n\n"
                .'Так делают те, кому результат важнее отмазок.',
            30 => "🔥 Месяц подряд по учёту.\n\n"
                .'Уважение к себе. Дальше больше того же.',
            default => null,
        };
    }

    public static function completedCheckClosing(DailyCheck $check, RatingService $rating): string
    {
        $score = (int) $check->total_score;
        $max = RatingService::MAX_DAILY_POINTS;
        $weak = $rating->weakestDimensionLabelForCheck($check);
        $head = 'Сегодня <b>'.$score.' из '.$max.'</b>.';

        if ($score >= $max) {
            return $head."\n\n"
                .'Редкий день, когда всё сошлось. Запомни ощущение, но не жди идеала каждый вечер.';
        }

        if ($score <= 3) {
            $tail = 'По факту ты от цели отползаешь, не подходишь.';
            if ($weak !== null) {
                $tail .= ' Слабое место сегодня: <b>'.$weak.'</b>.';
            }

            return $head."\n\n"
                .'Так себе день по цифрам.'
                ."\n\n"
                .$tail;
        }

        if ($score >= 7) {
            if ($weak === null) {
                return $head."\n\n"
                    .'Ровный день. Форма складывается как раз из таких вечеров.';
            }

            return $head."\n\n"
                .'В целом ок, но <b>'.$weak.'</b> провисло. Мелочи копятся.';
        }

        if ($score >= 5) {
            if ($weak !== null) {
                return $head."\n\n"
                    .'Норм, но <b>'.$weak.'</b> тянет вниз. Неделя так - и прогресс встанет.';
            }

            return $head."\n\n"
                .'Неплохо. Завтра можно подтянуть слабое место.';
        }

        if ($weak !== null) {
            return $head."\n\n"
                .'Так себе. Особенно <b>'.$weak.'</b>.'
                ."\n\n"
                .'Тело считает то, что ты ему даёшь.';
        }

        return $head."\n\n"
            .'Ниже среднего. Завтра возьми один пункт и сделай, без «подумаю».';
    }

    /** День «жизни» в боте с момента регистрации: 1 = день регистрации. */
    public static function dayNumberInBot(User $user, Carbon $now): int
    {
        return (int) $user->created_at->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()) + 1;
    }

    public static function morningDay7(): string
    {
        return "Неделя с ботом. Уже не «попробовал».\n\n"
            .'Ты видел цифры - дальше либо привыкаешь к честности, либо снова «вроде норм».'
            ."\n\n"
            .'💎 PRO потом - жёстче контроль. Пока просто не бросай вечерний чек-ин.';
    }

    /**
     * @param  list<string>  $lines
     */
    public static function pickStable(string $dateKey, int $telegramId, array $lines): string
    {
        if ($lines === []) {
            return '';
        }
        $idx = crc32((string) $telegramId.'|'.$dateKey) % count($lines);

        return $lines[$idx];
    }

    /**
     * @return list<string>
     */
    public static function morningSoftPool(): array
    {
        return [
            '☀️ День только начался. Вечером не обязан быть героем - достаточно честно закрыть <b>/check</b>, без выдумок.',
            '☀️ Первые дни - это не про идеал, про то, чтобы не пропадать. Вечером зайди, отметь как есть.',
            '☀️ Я не буду тебя хвалить за воздух. Зато если вечером заполнишь чек-ин - хотя бы сам себе не врёшь.',
            '☀️ Выбери один якорь на сегодня - сон, вода, что угодно одно - и вечером честно отрази в чек-ине. Маленькими шагами и выходит ритм.',
            '☀️ Мотивация выветривается. Остаётся привычка закрывать день. Вечером две минуты - и ты снова в курсе, где ты.',
            '☀️ Пока ты не отметил день, ты всё равно гадаешь. Вечером можно перестать гадать.',
            '☀️ Ничего страшного, если день обычный. Страшнее - когда обычный день заканчивается молчанием в боте.',
            '☀️ Не обещай себе идеал. Обещай себе возврат вечером - открыл чек-ин, записал факты, закрыл.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function morningHookPool(): array
    {
        return [
            '☀️ Ты уже несколько дней в процессе. Тут обычно развилка: кто-то закрепляет, кто-то тихо перестаёт открывать бота. Кем будешь ты - покажет вечер.',
            '☀️ Чек-ин - это не отчёт начальству. Это чтобы самому не врать. Вечером либо цифры, либо снова «ну вроде норм».',
            '☀️ Дисциплина не орёт в stories. Она копится в таких скучных вечерах, когда ты всё равно зашёл в <b>/check</b>.',
            '☀️ «Потом» любит повторяться каждый день. Сегодня можно один раз заменить его на «сделал чек-ин».',
            '☀️ Форма - это не про вдохновение. Про то, чтобы не пропадать из учёта. Не пропади сегодня.',
            '☀️ Ты уже потратил время на настройку. Обидно будет, если всё сольётся из‑за ленивого вечера.',
            '☀️ На этом этапе выигрывает не самый мотивированный, а тот, кто вечером всё равно возвращается.',
            '☀️ Три-пять дней подряд - как раз зона, где многие сливаются незаметно. Ты можешь просто не быть из них.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function morningLongRunPool(): array
    {
        return [
            '☀️ Очередной день. Вечером как обычно: <b>/check</b> - не ради галочки, ради того, чтобы не терять нить.',
            '☀️ Серия держится на таких скучных вечерах, когда ты всё равно зашёл и честно отметил.',
            '☀️ Идеал не обязателен. Обязателен возврат - даже если день был так себе.',
            '☀️ Пропустил вчера - не приговор. Пропустил неделю - уже другая история. Сегодня можно остаться в первой.',
            '☀️ Еда, сон, вода, движение - не звучит как мечта. Зато по факту это и есть форма.',
            '☀️ Каждый закрытый вечер - кирпичик. Не обесценивай кирпичики.',
            '☀️ Если вечером не зафиксируешь день - завтра снова будешь строить догадки вместо картины.',
        ];
    }

    // --- UI / чек-ин / настройки (HTML для Telegram) ---

    public static function unknownCommand(): string
    {
        return '🤔 Неизвестная команда. Доступны: /start, /check, /cancel, /rating, /plan, /analytics, /settings';
    }

    public static function proAiMenuHint(): string
    {
        return '💎 <b>PRO / AI-тренировки</b> - позже, с оплатой. Сейчас пользуйся планом и чек-инами бесплатно.';
    }

    public static function proAiCallbackHint(): string
    {
        return '💎 <b>PRO</b> (когда подключим): AI-тренировки под твой уровень и цель, режим давления, серии, коллажи прогресса, аналитика.'
            ."\n\n"
            .'Сейчас всё основное уже в бесплатной версии - продолжай чек-ины и план.';
    }

    public static function weeklyFocusSavedAfterText(): string
    {
        return 'Записал <b>фокус недели</b> - строка в конце <b>/rating</b> и после чек-ина.';
    }

    public static function mainMenuFallback(): string
    {
        return '👇 Кнопки внизу или команды: /check, /cancel, /rating, /plan, /analytics, /settings';
    }

    public static function startWelcomeBack(): string
    {
        return '👋 Снова привет! Я <b>FitBot</b> - держим питание 😋, сон 😴, тренировки 💪 и воду 💧 под контролем.';
    }

    public static function onboardingContinueHint(): string
    {
        return 'Продолжим настройку - следуй подсказкам выше.';
    }

    public static function onboardingNeedButton(): string
    {
        return '👆 Чтобы продолжить, нажми кнопку под предыдущим сообщением.';
    }

    public static function onboardingFirstStepNoBack(): string
    {
        return 'Это первый шаг - назад некуда.';
    }

    public static function cmdNeedOnboarding(): string
    {
        return 'Сначала завершите онбординг: /start';
    }

    public static function cmdPlanNeedOnboarding(): string
    {
        return '⏳ Сначала заверши онбординг: /start';
    }

    public static function checkAlreadyDoneToday(): string
    {
        return 'Сегодняшний чек-ин уже заполнен. Завтра снова жду /check';
    }

    public static function settingsIntro(): string
    {
        return "⚙️ <b>Настройки</b>\n\n"
            ."✏️ <b>Сменить анкету</b> - заново пройти вопросы (пол, возраст, вес…). История чек-инов сохранится, цели пересчитаются после завершения.\n\n"
            .'🗑 <b>Удалить аккаунт</b> - сотрутся все чек-ины, фото и цели. Потом снова /start.';
    }

    public static function checkNoDraftToday(): string
    {
        return 'Незавершённого чек-ина за сегодня нет. Открыть заново: <b>/check</b>.';
    }

    public static function checkCancelled(): string
    {
        return '❌ <b>Чек-ин отменён.</b>'."\n\n"
            .'Начать снова: кнопка <b>Чек-ин</b> или <b>/check</b>.'."\n"
            .'<i>/cancel</i> всегда сбрасывает незавершённый чек за сегодня.';
    }

    public static function settingsHomeHint(): string
    {
        return 'Ок, ниже снова основные кнопки. Незавершённый чек-ин за сегодня можно сбросить: <b>/cancel</b>.';
    }

    public static function settingsReonboardingStart(): string
    {
        return 'Ок, начинаем заново: снова описание бота и выбор режима. История чек-инов остаётся; для режима «план FitBot» после анкеты снова пересчитаю калории и БЖУ.';
    }

    public static function settingsDeleteCancelled(): string
    {
        return 'Удаление отменено.';
    }

    public static function settingsDeleteSessionExpired(): string
    {
        return 'Сессия подтверждения истекла или не начата. Нажми «Удалить аккаунт» в /settings ещё раз.';
    }

    public static function settingsDeleteFinal(): string
    {
        return 'Аккаунт удалён. Чтобы начать с чистого листа, отправь /start.';
    }

    public static function settingsTitleShort(): string
    {
        return '⚙️ <b>Настройки</b>';
    }

    /**
     * @param  array{morning: bool, evening: bool, churn: bool, quiet: bool, weekly_focus: bool, weekly_weight: bool}  $flags
     */
    public static function notificationsPanelText(array $flags, string $quietStart, string $quietEnd): string
    {
        $on = fn (bool $v) => $v ? '✅' : '⛔️';

        return '🔔 <b>Уведомления</b>'."\n\n"
            .'Что включено:'."\n"
            .$on($flags['morning']).' Утро (мотивация по расписанию)'."\n"
            .$on($flags['evening']).' Вечер (напоминание про чек-ин)'."\n"
            .$on($flags['churn']).' Возврат после паузы'."\n"
            .$on($flags['weekly_focus']).' Фокус недели (раз в неделю)'."\n"
            .$on($flags['weekly_weight']).' Вес: напоминание обновить (раз в неделю)'."\n"
            .$on($flags['quiet']).' Тихие часы: не беспокоить'."\n\n"
            .'Окно тишины: <b>'.e($quietStart).' - '.e($quietEnd).'</b> (локаль сервера)'."\n\n"
            .'<i>Пресеты меняют только интервал «не беспокоить».</i>';
    }

    public static function weeklyFocusMenuTitle(): string
    {
        return '📌 <b>Фокус недели</b>'."\n\n"
            .'Выбери пресет или свой текст. Строка показывается в <b>/rating</b> и после чек-ина.';
    }

    /** Одна строка для ответа бота: актуальный фокус без открытия /rating. */
    public static function weeklyFocusUiNowLine(RatingService $rating, User $user): string
    {
        return '📌 <b>Сейчас:</b> '.$rating->weeklyFocusContentHtml($user);
    }

    public static function weeklyFocusAskCustom(): string
    {
        return '📌 <b>Свой фокус</b>'."\n\n"
            .'Напиши <b>одну короткую строку</b> (до 255 символов). Пример: «меньше сладкого», «8 ч сна», «3 тренировки».'."\n\n"
            .'<i>Отмена - другое меню или подожди ~20 мин.</i>';
    }

    public static function weeklyFocusCleared(): string
    {
        return '🗑 <b>Фокус недели сброшен.</b> Снова будет авто-подсказка по чек-инам или задай новый в настройках.';
    }

    public static function weeklyFocusPresetApplied(string $label): string
    {
        return 'Записал пресет: <b>'.e($label).'</b>. Можно сменить в любой момент в настройках.';
    }

    /** @return array<string, string> id кнопки => текст в профиль */
    public static function weeklyFocusPresetNotes(): array
    {
        return [
            'sleep' => 'Сон: держать режим и часы',
            'water' => 'Вода: не недопивать',
            'diet' => 'Питание без срывов по плану',
            'move' => 'Движение: 3+ тренировки / активность',
            'sugar' => 'Меньше сладкого и лишних перекусов',
        ];
    }

    public static function weeklyFocusPresetButtonLabel(string $id): string
    {
        return match ($id) {
            'sleep' => '😴 Сон',
            'water' => '💧 Вода',
            'diet' => '🍽 Питание',
            'move' => '💪 Движение',
            'sugar' => '🚫 Сладкое',
            default => $id,
        };
    }

    public static function weeklyFocusReminderNudge(): string
    {
        return '📌 <b>Новая неделя - обнови фокус.</b>'."\n\n"
            .'Одна строка, что важно сейчас: сон, вода, питание, тренировки…'."\n"
            .'Настройки → <b>Фокус недели</b> или пресеты там же.'."\n\n"
            .'<i>Отключить напоминание: Настройки → Уведомления.</i>';
    }

    public static function weeklyWeightReminderNudge(): string
    {
        return '⚖️ <b>Раз в неделю: обнови вес</b>'."\n\n"
            .'Так в <b>/analytics</b> будет нормальная динамика: с чего начинал и что сейчас.'
            ."\n\n"
            .'Нажми кнопку ниже и напиши вес в кг одним числом (например <b>75.5</b>).';
    }

    public static function weightUpdateAskKg(): string
    {
        return 'Ок. Напиши <b>текущий вес в кг</b> одним числом (например 75 или 75.5).'
            ."\n\n"
            .'<i>Не сейчас - просто игнорируй, запрос сгорит сам.</i>';
    }

    public static function weightUpdateInvalid(): string
    {
        return 'Нужно число в кг, как в анкете: обычно <b>30–300</b>. Попробуй ещё раз.';
    }

    public static function weightUpdatedSaved(float $kg): string
    {
        return '⚖️ Записал: <b>'.round($kg, 1).'</b> кг. Дальше смотри динамику в /analytics.';
    }

    public static function weightRecalcPlanQuestion(): string
    {
        return '📋 У тебя план калорий от FitBot. <b>Пересчитать</b> его под новый вес?'
            ."\n\n"
            .'Или оставь как есть - ккал не трогну.';
    }

    public static function weightRecalcDone(): string
    {
        return '📋 План пересчитан под новый вес. Смотри /plan';
    }

    public static function weightRecalcSkipped(): string
    {
        return 'Ок, цели по ккал не менял. Вес в профиле уже обновлён.';
    }

    public static function checkCannotStepBack(): string
    {
        return '◀️ На шаге «питание» назад некуда - можно только <b>/cancel</b> и начать заново.';
    }

    public static function checkErrorNeedDietButtons(): string
    {
        return 'Сначала выбери <b>питание</b> кнопками ниже (или <b>/cancel</b>).';
    }

    public static function checkErrorNeedWorkoutButtons(): string
    {
        return 'Выбери <b>движение</b> кнопками ниже (или <b>/cancel</b>).';
    }

    public static function checkErrorSleepHoursRange(): string
    {
        return 'Напиши часы сна числом от <b>0</b> до <b>16</b> (например <b>7.5</b>).';
    }

    public static function checkErrorWaterVolume(): string
    {
        return 'Не разобрал объём. Нужно примерно <b>100-20 000</b> мл. Примеры: <code>2000</code>, <code>1.5 л</code>.';
    }

    public static function checkQuestionDiet(): string
    {
        return '🍽 <b>Питание сегодня?</b> Как получилось держать план?'."\n\n"
            .'<i>/cancel или кнопка внизу - прервать чек.</i>';
    }

    public static function checkQuestionSleep(float|int|string $targetHours): string
    {
        $t = e((string) $targetHours);

        return '😴 <b>Сон прошлой ночью</b>'."\n\n"
            .'Сколько часов реально спал? Напиши <b>одно число</b> в чат (например <b>7.5</b>).'."\n"
            .'🎯 Цель из анкеты: <b>'.$t.'</b> ч.'."\n"
            .'Диапазон: <b>0-16</b> ч. Можно запятой: <code>7,5</code>'."\n\n"
            .'<i>Застрял - /cancel или кнопка ниже.</i>';
    }

    public static function checkQuestionWorkout(): string
    {
        return '💪 <b>Движение сегодня</b>'."\n\n".'<i>Отмена чек-ина - кнопка внизу.</i>';
    }

    public static function checkQuestionWater(int $goalMl): string
    {
        $g = (string) $goalMl;

        return '💧 <b>Вода за день</b>'."\n\n"
            .'Сколько примерно выпил? Если не считал - оцени на глаз.'."\n"
            .'Примеры: <code>2000</code>, <code>1.5 л</code>, <code>2500 мл</code>'."\n"
            .'🎯 Цель: <b>'.$g.'</b> мл · допустимо примерно <b>100-20 000</b> мл'."\n\n"
            .'<i>/cancel - выйти из чек-ина.</i>';
    }

    public static function welcomeScreenText(): string
    {
        return '👋 <b>Привет! Я FitBot</b>'."\n\n"
            .'Помогаю не терять нить дисциплины:'."\n\n"
            .'📝 <b>Чек-ин</b> - питание, сон, движение, вода (с баллами)'."\n"
            .'📊 <b>Рейтинг</b> - серия дней и подсказки'."\n"
            .'📋 <b>План</b> - цели по сну/воде или полный расчёт от меня'."\n"
            .'⏰ <b>Напоминания</b> - утром и вечером'."\n\n"
            .'Жми <b>«Продолжить»</b> - выбери режим и пройди короткую настройку.';
    }

    public static function planChoiceText(): string
    {
        return '🎯 <b>Какой режим?</b>'."\n\n"
            .'• <b>План от FitBot</b> - калории, БЖУ, пример дня (анкета дольше, зато цифры мои).'."\n"
            .'• <b>Свой план</b> - только дисциплина: сон, вода, чек-ины. Меню и ккал - на тебе.';
    }

    public static function onboardingGenderIntroDiscipline(): string
    {
        return 'Начнём с <b>пола</b> - так подсказки будут точнее.';
    }

    public static function onboardingGenderIntroFull(): string
    {
        return 'Начнём с <b>пола</b> - от него калории, БЖУ и пример тренировок.';
    }

    public static function onboardingActivityIntro(): string
    {
        return 'Какая у тебя <b>повседневная активность</b>? Это влияет на калории.';
    }

    public static function onboardingGoalAsk(): string
    {
        return 'Какая <b>цель</b>?';
    }

    public static function onboardingExperienceAsk(): string
    {
        return 'Твой <b>опыт</b> тренировок?';
    }

    public static function onboardingWeightPrompt(): string
    {
        return 'Введи <b>вес в кг</b> (например 75.5).';
    }

    public static function onboardingHeightPrompt(): string
    {
        return 'Введи <b>рост в см</b> (например 180).';
    }

    public static function onboardingAgeInvalid(): string
    {
        return 'Укажи возраст числом <b>от 14 до 100</b> лет.';
    }

    public static function onboardingWeightInvalid(): string
    {
        return 'Нужно реалистичное значение веса в кг (30-300). Попробуй ещё раз.';
    }

    public static function onboardingHeightInvalid(): string
    {
        return 'Укажи рост в см целым числом (120-230).';
    }

    public static function onboardingSleepInvalid(): string
    {
        return 'Введи часы сна числом (например 7.5), обычно 4-12.';
    }

    public static function onboardingWaterInvalid(): string
    {
        return '💧 Укажи объём в мл или литрах (например 2500 или 2 л), в диапазоне примерно 400-12000 мл.';
    }

    public static function onboardingAfterWeight(): string
    {
        return 'Отлично. Теперь <b>рост в см</b> (например 180).';
    }

    public static function onboardingAfterAge(): string
    {
        return 'Принято. Теперь <b>вес в кг</b> (например 75.5).';
    }

    public static function onboardingWaterGoalPrompt(): string
    {
        return '💧 Сколько <b>мл воды в день</b> для твоей цели? (например <b>2500</b> или <b>2.5 л</b>) - от этого считается балл за воду в /check.';
    }

    public static function onboardingPhotoPrompt(): string
    {
        return 'Пришли фото «до» или нажми «Пропустить».';
    }

    public static function onboardingPhotoPromptFull(): string
    {
        return 'Загрузи фото «до» (одно сообщение с фото) или нажми «Пропустить».';
    }

    public static function onboardingAgePromptDiscipline(): string
    {
        return 'Сколько тебе <b>полных лет</b>? Напиши число (например 27).';
    }

    public static function onboardingAgePromptFull(): string
    {
        return 'Сколько тебе <b>полных лет</b>? (число, например 27) - учту в расчёте калорий.';
    }

    public static function onboardingSleepTargetDiscipline(): string
    {
        return 'Сколько часов <b>сна в сутки</b> хочешь держать в цель? Введи число (например <b>7.5</b>) - для чек-ина.';
    }

    public static function onboardingSleepTargetFull(): string
    {
        return 'Сколько часов сна в цель? Введи число (например <b>7.5</b>).';
    }

    public static function finishFullPlanFooter(): string
    {
        return '🎉 <b>Готово!</b> План выше 👆'."\n\n"
            .'Сегодня вечером - первый чек-ин: кнопка или /check. Напомню, если забудешь 🙂'."\n\n"
            .'📷 Фото раз в пару недель могу напомнить.';
    }

    public static function disciplineDoneFooter(): string
    {
        return '🎉 <b>Настроили!</b>'."\n\n"
            .'Вечером загляни в чек-ин - первый самый важный. Напомню 📲'."\n\n"
            .'Ккал и меню - на тебе, я про учёт.';
    }

    public static function progressPhotoReminder(): string
    {
        return '📷 Пора <b>фото прогресса</b> (~раз в 30 дней). Одним сообщением.'."\n\n"
            .'Фото не врёт - даже когда голова ищет отмазку.';
    }

    public static function progressPhotoSaved(): string
    {
        return 'Фото прогресса сохранено. Следующее напоминание через 30 дней.';
    }
}
