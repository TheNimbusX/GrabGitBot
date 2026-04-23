<?php

namespace App\Services\FitBot;

use App\Models\DailyCheck;
use App\Models\User;
use App\Services\RatingService;
use Carbon\Carbon;

/**
 * Тексты бота: живой тон, честное зеркало, без канцелярита и «ботских» списков ради списка.
 */
final class FitBotMessaging
{
    public static function onboardingDisciplineIntro(): string
    {
        return "Ок, база на месте.\n\n"
            ."Дальше по вечерам — четыре честных отметки: еда, сон, движение, вода. Не о том, «какой ты молодец», а о том, <b>держишься ли ты в реальности</b>.\n\n"
            .'Как освободишься — загляни в <b>Чек-ин</b> или набери /check. Сегодня как раз хороший день начать.';
    }

    public static function onboardingFreeVsProHint(): string
    {
        return 'Сейчас всё бесплатно: чек-ины, рейтинг, напоминания. Этого хватит, чтобы увидеть правду о себе — без подписки и без пиара.'
            ."\n\n"
            .'Потом будет 💎 PRO: пожёстче контроль, AI под тренировки, нормальная аналитика. Подключим — я сам скажу, когда это реально пригодится.';
    }

    public static function onboardingAfterPlanFooter(): string
    {
        return 'План выше — ориентир на день. Фиксировать факты: <b>Чек-ин</b> или <b>/check</b>.';
    }

    /** Один раз после пары шагов анкеты: зачем вообще вопросы. */
    public static function onboardingValueBridge(): string
    {
        return 'Кстати, зачем я всё это спрашиваю. По ответам потом считается не «красивый отчёт», а <b>реальный прогресс</b> — день к дню.'
            ."\n\n"
            .'Без цифр ты всё равно гадаешь, нормально ли живёшь. С ними — уже видно.';
    }

    /** После самого первого завершённого чек-ина. */
    public static function firstEverCheckClosing(): string
    {
        return 'Это был первый чек-ин. Дальше интереснее: через несколько дней станет видно, <b>держишь ли ты ритм</b> или только веришь, что держишь.'
            ."\n\n"
            .'Вечером могу напомнить — если не отмахнёшься, увидишь картину честнее, чем в голове.';
    }

    public static function welcomeContinueButtonLabel(): string
    {
        return 'Давай, проверим';
    }

    public static function eveningReminderSoft(RatingService $rating, User $user, Carbon $today): string
    {
        $dayNum = self::dayNumberInBot($user, $today);
        $lines = [];

        if ($dayNum === 2) {
            $lines[] = 'Второй день — самый удобный, чтобы «забить». Если правда хочешь форму — не бери отмазку.';
            $lines[] = '';
        } elseif ($dayNum >= 3 && $dayNum <= 5) {
            $lines[] = 'Ты уже '.$dayNum.'‑й день здесь. Обычно в этот момент либо закрепляются, либо тихо сливаются. Как будет у тебя — покажет вечер.';
            $lines[] = '';
        }

        $lines[] = '🔔 Сегодня ты ещё <b>не закрыл день</b> в чек-ине.';
        $lines[] = '';
        $lines[] = 'Пара минут — и ты хотя бы знаешь, где ты, а не додумываешь.';

        $yesterday = $today->copy()->subDay();
        $missedYesterday = ! $rating->hasCompletedCheckOnDate($user, $yesterday);

        if ($missedYesterday) {
            $lines[] = '';
            $lines[] = 'Вчера ты не отметился — это уже <b>минус день</b> к дисциплине и минус к прогрессу, даже если «вроде норм жил».';
            $lines[] = 'Сегодня можно не усугублять — зайди в /check.';

            $twoAgo = $today->copy()->subDays(2);
            if (! $rating->hasCompletedCheckOnDate($user, $twoAgo)) {
                $lines[] = '';
                $lines[] = 'И позавчера тоже пусто. Так обычно начинают отваливаться — пока ещё не поздно развернуть.';
            }
        }

        $streak = $rating->checkInStreakDays($user, $today);
        if ($streak >= 3) {
            $lines[] = '';
            $lines[] = 'Серия идёт. Сегодня не отметишь — <b>сгорит</b>. Обидно будет самому себе.';
        }

        return implode("\n", $lines);
    }

    public static function eveningReminderStrict(): string
    {
        return "Ты снова игноришь чек-ин.\n\n"
            .'Так обычно и срываются — не разом, а маленькими «завтра».';
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
            3 => "🔥 Три дня подряд — уже не «случайно получилось», а зачаток привычки.\n\n"
                .'Дальше обычно развилка: закрепляют или договариваются с собой, что «и так сойдёт». Второе — дорога назад.',
            5 => "🔥 Пять дней подряд.\n\n"
                .'По опыту ты уже не в хвосте тех, кто «скачал и забыл». Ты реально в игре.'
                ."\n\n"
                .'Пропустишь сегодня вечером — серия обнулится. Мелочь? Нет, обидно именно потому, что ты уже вложился.'
                ."\n\n"
                .'Когда подключим 💎 PRO — можно будет включить режим, где я не дам спокойно слиться. Пока держись на этом — ты уже молодец.',
            7 => "🔥 Неделя подряд без дырок в чек-ине.\n\n"
                .'Это редкость. Дальше либо закрепляют как систему, либо сливают на ровном месте. Ты как хочешь?',
            10 => "🔥 Десять дней подряд — это уже не настроение, а привычка.\n\n"
                .'Не обесценивай: большинство до сюда не доходит.',
            14 => "🔥 Две недели в ритме.\n\n"
                .'Тело и голова уже начинают привыкать — не останавливайся на полпути.',
            21 => "🔥 Три недели.\n\n"
                .'Так держат те, кому результат важнее отмазок.',
            30 => "🔥 Месяц без срыва по учёту.\n\n"
                .'Это уже уважение к себе. Продолжай.',
            default => null,
        };
    }

    public static function completedCheckClosing(DailyCheck $check, RatingService $rating): string
    {
        $score = (int) $check->total_score;
        $max = RatingService::MAX_DAILY_POINTS;
        $weak = $rating->weakestDimensionLabelForCheck($check);
        $head = 'Сегодня вышло <b>'.$score.' из '.$max.'</b>.';

        if ($score >= $max) {
            return $head."\n\n"
                .'День, когда всё сошлось. Такое бывает не каждый вечер — запомни ощущение и не требуй от себя «всегда идеал» завтра.';
        }

        if ($score <= 3) {
            $tail = 'С такой картой ты не подходишь к цели — ты от неё отползаешь. Без драмы, по факту.';
            if ($weak !== null) {
                $tail .= "\n\n".'Больше всего сегодня просело <b>'.$weak.'</b> — туда и смотри завтра в первую очередь.';
            }

            return $head."\n\n"
                .'Ты сам выбрал, куда идти. Сегодня по цифрам это больше похоже на отход, чем на движение.'
                ."\n\n"
                .$tail;
        }

        if ($score >= 7) {
            if ($weak === null) {
                return $head."\n\n"
                    .'В целом ровный день. Именно из таких скучных вечеров и складывается форма — не из «вдохновения».';
            }

            return $head."\n\n"
                .'Со стороны может казаться, что «всё ок», но <b>'.$weak.'</b> сегодня провисло. Обычно форму ломают не разовым срывом, а такими мелочами по накопительной.';
        }

        if ($score >= 5) {
            if ($weak !== null) {
                return $head."\n\n"
                    .'Неплохо, жить можно. Но <b>'.$weak.'</b> тянет вниз — если неделю подряд так, прогресс встанет, хоть ты и будешь верить, что «стараешься».';
            }

            return $head."\n\n"
                .'Нормальный день, без восторга. Завтра можно чуть плотнее закрыть слабое место — пока оно само не стало привычкой.';
        }

        if ($weak !== null) {
            return $head."\n\n"
                .'Так себе день. Особенно <b>'.$weak.'</b> — там сегодня явный провал по сравнению с остальным.'
                ."\n\n"
                .'Так форма не делается — не потому что «плохой человек», а потому что тело считает то, что ты ему даёшь.';
        }

        return $head."\n\n"
            .'Ниже среднего. Завтра можно начать с одного пункта, который точно потянешь — но начать, а не «подумаю».';
    }

    /** День «жизни» в боте с момента регистрации: 1 = день регистрации. */
    public static function dayNumberInBot(User $user, Carbon $now): int
    {
        return (int) $user->created_at->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()) + 1;
    }

    public static function morningDay7(): string
    {
        return "Неделя с ботом — это уже не «попробовал».\n\n"
            .'Ты хоть раз увидел свою дисциплину в цифрах — дальше либо привыкаешь к честности, либо снова уходишь в «вроде норм».'
            ."\n\n"
            .'💎 Когда подключим PRO — можно будет усилить контроль и разбор. Пока просто не бросай вечерний чек-ин: ты уже не на старте.';
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
            '☀️ День только начался. Вечером не обязан быть героем — достаточно честно закрыть <b>/check</b>, без выдумок.',
            '☀️ Первые дни — это не про идеал, про то, чтобы не пропадать. Вечером зайди, отметь как есть.',
            '☀️ Я не буду тебя хвалить за воздух. Зато если вечером заполнишь чек-ин — хотя бы сам себе не врёшь.',
            '☀️ Выбери один якорь на сегодня — сон, вода, что угодно одно — и вечером честно отрази в чек-ине. Маленькими шагами и выходит ритм.',
            '☀️ Мотивация выветривается. Остаётся привычка закрывать день. Вечером две минуты — и ты снова в курсе, где ты.',
            '☀️ Пока ты не отметил день, ты всё равно гадаешь. Вечером можно перестать гадать.',
            '☀️ Ничего страшного, если день обычный. Страшнее — когда обычный день заканчивается молчанием в боте.',
            '☀️ Не обещай себе идеал. Обещай себе возврат вечером — открыл чек-ин, записал факты, закрыл.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function morningHookPool(): array
    {
        return [
            '☀️ Ты уже несколько дней в процессе. Тут обычно развилка: кто-то закрепляет, кто-то тихо перестаёт открывать бота. Кем будешь ты — покажет вечер.',
            '☀️ Чек-ин — это не отчёт начальству. Это чтобы самому не врать. Вечером либо цифры, либо снова «ну вроде норм».',
            '☀️ Дисциплина не орёт в stories. Она копится в таких скучных вечерах, когда ты всё равно зашёл в <b>/check</b>.',
            '☀️ «Потом» любит повторяться каждый день. Сегодня можно один раз заменить его на «сделал чек-ин».',
            '☀️ Форма — это не про вдохновение. Про то, чтобы не пропадать из учёта. Не пропади сегодня.',
            '☀️ Ты уже потратил время на настройку. Обидно будет, если всё сольётся из‑за ленивого вечера.',
            '☀️ На этом этапе выигрывает не самый мотивированный, а тот, кто вечером всё равно возвращается.',
            '☀️ Три–пять дней подряд — как раз зона, где многие сливаются незаметно. Ты можешь просто не быть из них.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function morningLongRunPool(): array
    {
        return [
            '☀️ Очередной день. Вечером как обычно: <b>/check</b> — не ради галочки, ради того, чтобы не терять нить.',
            '☀️ Серия держится на таких скучных вечерах, когда ты всё равно зашёл и честно отметил.',
            '☀️ Идеал не обязателен. Обязателен возврат — даже если день был так себе.',
            '☀️ Пропустил вчера — не приговор. Пропустил неделю — уже другая история. Сегодня можно остаться в первой.',
            '☀️ Еда, сон, вода, движение — не звучит как мечта. Зато по факту это и есть форма.',
            '☀️ Каждый закрытый вечер — кирпичик. Не обесценивай кирпичики.',
            '☀️ Если вечером не зафиксируешь день — завтра снова будешь строить догадки вместо картины.',
        ];
    }

    // ——— UI / чек-ин / настройки (HTML для Telegram) ———

    public static function unknownCommand(): string
    {
        return '🤔 Неизвестная команда. Доступны: /start, /check, /cancel, /rating, /plan, /analytics, /settings';
    }

    public static function proAiMenuHint(): string
    {
        return '💎 <b>PRO / AI-тренировки</b> — позже, с оплатой. Сейчас пользуйся планом и чек-инами бесплатно.';
    }

    public static function proAiCallbackHint(): string
    {
        return '💎 <b>PRO</b> (когда подключим): AI-тренировки под твой уровень и цель, режим давления, серии, коллажи прогресса, аналитика.'
            ."\n\n"
            .'Сейчас всё основное уже в бесплатной версии — продолжай чек-ины и план.';
    }

    public static function weeklyFocusSavedAfterText(): string
    {
        return 'Записал <b>фокус недели</b> — строка в конце <b>/rating</b> и после чек-ина.';
    }

    public static function mainMenuFallback(): string
    {
        return '👇 Кнопки внизу или команды: /check, /cancel, /rating, /plan, /analytics, /settings';
    }

    public static function startWelcomeBack(): string
    {
        return '👋 Снова привет! Я <b>FitBot</b> — держим питание 😋, сон 😴, тренировки 💪 и воду 💧 под контролем.';
    }

    public static function onboardingContinueHint(): string
    {
        return 'Продолжим настройку — следуй подсказкам выше.';
    }

    public static function onboardingNeedButton(): string
    {
        return '👆 Чтобы продолжить, нажми кнопку под предыдущим сообщением.';
    }

    public static function onboardingFirstStepNoBack(): string
    {
        return 'Это первый шаг — назад некуда.';
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
            ."✏️ <b>Сменить анкету</b> — заново пройти вопросы (пол, возраст, вес…). История чек-инов сохранится, цели пересчитаются после завершения.\n\n"
            .'🗑 <b>Удалить аккаунт</b> — сотрутся все чек-ины, фото и цели. Потом снова /start.';
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
     * @param  array{morning: bool, evening: bool, churn: bool, quiet: bool, weekly_focus: bool}  $flags
     */
    public static function notificationsPanelText(array $flags, string $quietStart, string $quietEnd): string
    {
        $on = fn (bool $v) => $v ? '✅' : '⛔️';

        return '🔔 <b>Уведомления</b>'."\n\n"
            .'Что включено:'."\n"
            .$on($flags['morning']).' Утро (мотивация по расписанию)'."\n"
            .$on($flags['evening']).' Вечер (напоминание про чек-ин)'."\n"
            .$on($flags['churn']).' Возврат после паузы'."\n"
            .$on($flags['weekly_focus']).' Напоминание про фокус недели (раз в неделю)'."\n"
            .$on($flags['quiet']).' Тихие часы: не беспокоить'."\n\n"
            .'Окно тишины: <b>'.e($quietStart).' — '.e($quietEnd).'</b> (локаль сервера)'."\n\n"
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
            .'<i>Отмена — другое меню или подожди ~20 мин.</i>';
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
        return '📌 <b>Новая неделя — обнови фокус.</b>'."\n\n"
            .'Одна строка, что важно сейчас: сон, вода, питание, тренировки…'."\n"
            .'Настройки → <b>Фокус недели</b> или пресеты там же.'."\n\n"
            .'<i>Отключить напоминание: Настройки → Уведомления.</i>';
    }

    public static function checkCannotStepBack(): string
    {
        return '◀️ На шаге «питание» назад некуда — можно только <b>/cancel</b> и начать заново.';
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
        return 'Не разобрал объём. Нужно примерно <b>100–20 000</b> мл. Примеры: <code>2000</code>, <code>1.5 л</code>.';
    }

    public static function checkQuestionDiet(): string
    {
        return '🍽 <b>Питание сегодня?</b> Как получилось держать план?'."\n\n"
            .'<i>/cancel или кнопка внизу — прервать чек.</i>';
    }

    public static function checkQuestionSleep(float|int|string $targetHours): string
    {
        $t = e((string) $targetHours);

        return '😴 <b>Сон прошлой ночью</b>'."\n\n"
            .'Сколько часов реально спал? Напиши <b>одно число</b> в чат (например <b>7.5</b>).'."\n"
            .'🎯 Цель из анкеты: <b>'.$t.'</b> ч.'."\n"
            .'Диапазон: <b>0–16</b> ч. Можно запятой: <code>7,5</code>'."\n\n"
            .'<i>Застрял — /cancel или кнопка ниже.</i>';
    }

    public static function checkQuestionWorkout(): string
    {
        return '💪 <b>Движение сегодня</b>'."\n\n".'<i>Отмена чек-ина — кнопка внизу.</i>';
    }

    public static function checkQuestionWater(int $goalMl): string
    {
        $g = (string) $goalMl;

        return '💧 <b>Вода за день</b>'."\n\n"
            .'Сколько примерно выпил? Если не считал — оцени на глаз.'."\n"
            .'Примеры: <code>2000</code>, <code>1.5 л</code>, <code>2500 мл</code>'."\n"
            .'🎯 Цель: <b>'.$g.'</b> мл · допустимо примерно <b>100–20 000</b> мл'."\n\n"
            .'<i>/cancel — выйти из чек-ина.</i>';
    }

    public static function welcomeScreenText(): string
    {
        return '👋 Я FitBot. Без пафоса: я показываю, <b>держишь ли ты дисциплину на деле</b>, а не в голове.'
            ."\n\n"
            .'За первую неделю обычно уже видно: ты реально в ритме или только думаешь, что в ритме. Дальше — ещё честнее.'
            ."\n\n"
            .'Если хочешь проверить это на себе — жми кнопку ниже. Не хочешь — норм, просто тогда и не жди от меня сюсюканья.';
    }

    public static function planChoiceText(): string
    {
        return 'С чего начнём?'."\n\n"
            .'• <b>План от FitBot</b> — посчитаю калории, БЖУ, накидаю пример дня. Анкета чуть длиннее, зато меньше гадания с цифрами.'
            ."\n"
            .'• <b>Свой план</b> — только дисциплина: сон, вода, чек-ины. Меню и калории — на тебе, я не лезу.';
    }

    public static function onboardingGenderIntroDiscipline(): string
    {
        return 'Начнём с <b>пола</b> — так подсказки будут точнее.';
    }

    public static function onboardingGenderIntroFull(): string
    {
        return 'Начнём с <b>пола</b> — от него зависят расчёт калорий, БЖУ и пример тренировок.';
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
        return 'Нужно реалистичное значение веса в кг (30–300). Попробуй ещё раз.';
    }

    public static function onboardingHeightInvalid(): string
    {
        return 'Укажи рост в см целым числом (120–230).';
    }

    public static function onboardingSleepInvalid(): string
    {
        return 'Введи часы сна числом (например 7.5), обычно 4–12.';
    }

    public static function onboardingWaterInvalid(): string
    {
        return '💧 Укажи объём в мл или литрах (например 2500 или 2 л), в диапазоне примерно 400–12000 мл.';
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
        return '💧 Сколько <b>мл воды в день</b> для твоей цели? (например <b>2500</b> или <b>2.5 л</b>) — от этого считается балл за воду в /check.';
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
        return 'Сколько тебе <b>полных лет</b>? (число, например 27) — учту в расчёте калорий.';
    }

    public static function onboardingSleepTargetDiscipline(): string
    {
        return 'Сколько часов <b>сна в сутки</b> хочешь держать в цель? Введи число (например <b>7.5</b>) — для чек-ина.';
    }

    public static function onboardingSleepTargetFull(): string
    {
        return 'Сколько часов сна в цель? Введи число (например <b>7.5</b>).';
    }

    public static function finishFullPlanFooter(): string
    {
        return '🎉 Анкета готова, план выше — это твой ориентир на день.'
            ."\n\n"
            .'<b>Сегодня вечером</b> сделай первый чек-ин — кнопка «Чек-ин» или /check. Именно с него начинается честный учёт, а не с плана на бумаге.'
            ."\n\n"
            .'Если забудешь — напомню. Не злись: это как раз про то, чтобы не смыло первый же вечер.'
            ."\n\n"
            .'Раз в несколько недель могу пнуть про фото прогресса — если не против.';
    }

    public static function disciplineDoneFooter(): string
    {
        return '🎉 Всё, база настроена.'
            ."\n\n"
            .'<b>Сегодня вечером</b> зайди в чек-ин — первый раз задаёт планку. Потом легче продолжить, чем снова начинать с нуля.'
            ."\n\n"
            .'Напоминание кину, если размечтаешься. Калории и меню — на тебе, я слежу за дисциплиной учёта.'
            ."\n\n"
            .'Фото прогресса — реже, но тоже могу напомнить.';
    }

    public static function progressPhotoReminder(): string
    {
        return 'Пора обновить <b>фото прогресса</b> (раз в 30 дней). Пришли одно фото сообщением.'
            ."\n\n"
            .'Фото не спорит — даже когда голова ищет отмазку.';
    }

    public static function progressPhotoSaved(): string
    {
        return 'Фото прогресса сохранено. Следующее напоминание через 30 дней.';
    }
}
