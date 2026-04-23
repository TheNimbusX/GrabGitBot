<?php

namespace App\Services\FitBot;

use App\Models\DailyCheck;
use App\Models\User;
use App\Services\RatingService;
use Carbon\Carbon;

/**
 * Тексты уведомлений: честное зеркало, без лишнего «мотиватора».
 */
final class FitBotMessaging
{
    public static function onboardingDisciplineIntro(): string
    {
        return "Ты настроен.\n\n"
            ."Теперь каждый день ты будешь отмечать:\n"
            ."— питание\n"
            ."— сон\n"
            ."— тренировки\n"
            ."— воду\n\n"
            .'Я буду считать твой <b>рейтинг дисциплины</b>.'."\n\n"
            .'Начнём сегодня.';
    }

    public static function onboardingFreeVsProHint(): string
    {
        return '🆓 <b>Сейчас всё бесплатно:</b> чек-ин, базовый рейтинг, напоминания.'
            ."\n\n"
            .'💎 <b>PRO позже</b> (когда подключим): режим честного давления, AI-тренировки под тебя, серии и коллажи прогресса, расширенная аналитика.';
    }

    public static function onboardingAfterPlanFooter(): string
    {
        return 'План выше — ориентир на каждый день. Запись дня: <b>«Чек-ин»</b> или <b>/check</b>.';
    }

    public static function eveningReminderSoft(RatingService $rating, User $user, Carbon $today): string
    {
        $lines = ['🔔 <b>Ты сегодня ещё не отметил день.</b>', '', '2 минуты — и ты понимаешь, где ты сейчас.'];

        $yesterday = $today->copy()->subDay();
        $missedYesterday = ! $rating->hasCompletedCheckOnDate($user, $yesterday);

        if ($missedYesterday) {
            $lines[] = '';
            $lines[] = 'Ты пропустил вчера.';
            $lines[] = 'Минус день дисциплины.';
            $lines[] = 'Минус шаг к форме.';

            $twoAgo = $today->copy()->subDays(2);
            if (! $rating->hasCompletedCheckOnDate($user, $twoAgo)) {
                $lines[] = '';
                $lines[] = 'Ты начал сливать.';
                $lines[] = 'Обычно на этом этапе люди бросают.';
            }
        }

        $streak = $rating->checkInStreakDays($user, $today);
        if ($streak >= 3) {
            $lines[] = '';
            $lines[] = 'Сегодня пропустишь — <b>серия сгорит</b>.';
            $lines[] = 'Решай.';
        }

        return implode("\n", $lines);
    }

    public static function eveningReminderStrict(): string
    {
        return "Ты пропускаешь чек-ин.\n\n"
            .'Это первый шаг к срыву.';
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
            3 => "🔥 3 дня подряд — привычка цепляется.\n\nНе бросай на полпути.",
            5 => "🔥 5 дней подряд\n\nТы уже лучше, чем большинство.\n\nНе слей это.",
            7 => "🔥 Неделя без срыва.\n\nДальше либо закрепляют, либо сливаются. Ты выбираешь.",
            10 => "🔥 10 дней подряд.\n\nЭто уже система, не удача.",
            14 => "🔥 Две недели в ритме.\n\nЭто уже не случайность.",
            21 => "🔥 Три недели.\n\nТак держат те, кому правда нужен результат.",
            30 => "🔥 Месяц дисциплины.\n\nРедкость. Продолжай.",
            default => null,
        };
    }

    public static function completedCheckClosing(DailyCheck $check, RatingService $rating): string
    {
        $score = (int) $check->total_score;
        $max = RatingService::MAX_DAILY_POINTS;

        if ($score >= $max) {
            return '🔥 <b>'.$score.'/'.$max.'</b>'."\n\n"
                .'Вот так и делается форма.'."\n\n"
                .'Продолжай.';
        }

        if ($score <= 3) {
            return '🔴 <b>'.$score.'/'.$max.'</b>'."\n\n"
                .'Ты сам выбрал цель.'."\n\n"
                .'Сейчас ты от неё отходишь.';
        }

        $weak = $rating->weakestDimensionLabelForCheck($check);

        if ($score >= 7) {
            if ($weak === null) {
                return '📊 <b>Сегодня: '.$score.'/'.$max.'</b>'."\n\n"
                    .'Ровно и честно — так и строится дисциплина.';
            }

            return '📊 <b>Сегодня: '.$score.'/'.$max.'</b>'."\n\n"
                .'Очень неплохо.'
                ."\n"
                .'Но '.$weak.' — зона, где чаще всего срывают.';
        }

        if ($score >= 5) {
            $weakLine = $weak !== null
                ? 'Нормально.'."\n".'Но '.$weak.' проседает.'."\n\n".'Если так продолжишь — прогресс встанет.'
                : 'Нормально.'."\n\n".'Завтра можно сделать ровнее — без отмазок.';

            return '📊 <b>Сегодня: '.$score.'/'.$max.'</b>'."\n\n".$weakLine;
        }

        $weakLine = $weak !== null
            ? 'Так себе день.'."\n".'Особенно '.$weak.'.'."\n\n".'Завтра без «завтра».'
            : 'Так себе день.'."\n\n".'Завтра можно начать с чистого листа — но только делом.';

        return '📊 <b>Сегодня: '.$score.'/'.$max.'</b>'."\n\n".$weakLine;
    }

    /** День «жизни» в боте с момента регистрации: 1 = день регистрации. */
    public static function dayNumberInBot(User $user, Carbon $now): int
    {
        return (int) $user->created_at->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()) + 1;
    }

    public static function morningDay7(): string
    {
        return "Ты прошёл 7 дней.\n\n"
            .'Теперь ты видишь свою дисциплину.'
            ."\n\n"
            .'Хочешь идти дальше:'
            ."\n"
            .'— без сливов'
            ."\n"
            .'— с персональным контролем'
            ."\n\n"
            .'💎 <b>PRO</b> мы подключим позже — пока всё доступно бесплатно. Продолжай отмечать дни.';
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
            '☀️ Новый день — чистый лист. Зайди вечером в <b>/check</b>, без давления, просто по факту.',
            '☀️ Привычка начинается с малого: вода, еда, сон, движение. Отметишь вечером — уже шаг.',
            '☀️ Я не тренер и не друг — зеркало. Честно запиши день, и станет яснее, где ты.',
            '☀️ Не нужно идеала. Нужна регулярность. Сегодня можно просто не пропустить вечерний чек-ин.',
            '☀️ Тишина в боте = слепая зона. Две минуты вечером — и ты снова в курсе своей дисциплины.',
            '☀️ День только начался. Думай про вечер: спокойно пройти <b>/check</b> — уже победа над хаосом.',
            '☀️ Первые дни — про то, чтобы просто возвращаться. Вернёшься вечером — молодец.',
            '☀️ Мягко: выбери один якорь на сегодня (сон или вода) и вечером честно отметь, как вышло.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function morningHookPool(): array
    {
        return [
            '☀️ Ты уже держишься несколько дней. Обычно дальше люди либо закрепляют, либо сливаются.',
            '☀️ Паттерн прост: чек-ин → ясность. Пропуск → самообман. Вечером покажи, на чьей ты стороне.',
            '☀️ Дисциплина не кричит. Она считается. Вечером — цифры, не оправдания.',
            '☀️ Если вчера было «потом», сегодня «потом» снова придёт. Вечером лучше закрыть день в <b>/check</b>.',
            '☀️ Форма — не про мотивацию. Про то, чтобы не исчезать из процесса. Не исчезай сегодня.',
            '☀️ Ты уже вложил время в бота. Не превращай это в пустую регистрацию — вечером доведи до чек-ина.',
            '☀️ На этом этапе решает не вдохновение, а возврат. Вернись вечером с честными отметками.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function morningLongRunPool(): array
    {
        return [
            '☀️ Ещё один день — ещё одна честная отметка. Вечером <b>/check</b>, без драмы.',
            '☀️ Серия держится только пока ты не пропускаешь вечер. Сегодня — как обычно: закрой день.',
            '☀️ Не гонись за идеалом. Гонись за тем, чтобы не выпадать из ритма.',
            '☀️ Маленький срыв лечится возвратом. Большой — молчанием. Выбирай возврат.',
            '☀️ Вода, сон, еда, движение — скучно, зато работает. Вечером сверим факты.',
            '☀️ Ты строишь данные о себе. Пропуск дня — дыра в картине. Заполни вечером.',
            '☀️ Честное зеркало: если вечером не зафиксируешь день, завтра снова гадаешь вслепую.',
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
        return "👋 <b>Привет! Я FitBot</b>\n\n"
            ."Я помогаю не терять нить дисциплины:\n"
            ."• 📝 <b>Чек-ин</b> — питание, сон, движение, вода за день (с баллами)\n"
            ."• 📊 <b>Рейтинг</b> — серия дней и подсказки\n"
            ."• 📋 <b>План</b> — твои цели по сну и воде (или полный план от меня)\n"
            ."• ⏰ Напоминания утром и вечером\n\n"
            .'Нажми <b>«Продолжить»</b>, чтобы выбрать режим и пройти короткую настройку.';
    }

    public static function planChoiceText(): string
    {
        return '🎯 <b>Какой режим тебе подходит?</b>'."\n\n"
            .'• <b>План от FitBot</b> — рассчитаю калории, БЖУ, пример дня и блок про тренировки (дольше анкета).'."\n"
            .'• <b>Свой план</b> — только чек-ины и дисциплина: спрошу сон, воду и базовые данные, без меню и ккал от меня.';
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
        return '🎉 '.self::onboardingAfterPlanFooter()
            ."\n\n".'Раз в ~30 дней напомню про фото прогресса 📷';
    }

    public static function disciplineDoneFooter(): string
    {
        return '🎉 <b>Готово!</b> Режим без плана FitBot: каждый день <b>/check</b> ✍️, статистика <b>/rating</b> 📊. Калории и меню ведёшь сам — я помогаю не сбиться с дисциплины.'
            ."\n".'Раз в ~30 дней могу напомнить про фото прогресса 📷';
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
