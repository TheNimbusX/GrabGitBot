<?php

namespace App\Services\FitBot;

use App\Enums\ActivityLevel;
use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\StrikeStatusTier;
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

    public static function pluralRuDays(int $n): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return 'дней';
        }
        if ($n1 === 1) {
            return 'день';
        }
        if ($n1 >= 2 && $n1 <= 4) {
            return 'дня';
        }

        return 'дней';
    }

    /**
     * Ядро продукта: серия закрытых чек-инов + риск срыва сегодня.
     *
     * @param  bool  $todayCheckCompleted  Уже есть завершённый чек-ин за сегодня
     */
    public static function streakCoreBanner(int $streak, bool $todayCheckCompleted): ?string
    {
        if ($streak < 1) {
            return null;
        }
        $w = self::pluralRuDays($streak);
        if (! $todayCheckCompleted) {
            return '🔥 <b>'.$streak.' '.$w.' подряд</b> в закрытых чек-инах.'
                ."\n".'Если день сложный или болеешь - всё равно можно закрыть чек-ин в режиме восстановления.';
        }

        return '🔥 <b>'.$streak.' '.$w.' подряд</b>. Держим привычку спокойно: без идеала, но по фактам.';
    }

    /** После чек-ина для «обычных» дней без milestone-текста. */
    public static function streakCarryCompactLine(int $streak): ?string
    {
        if ($streak < 2) {
            return null;
        }
        if (in_array($streak, [3, 5, 7, 10, 14, 21, 30], true)) {
            return null;
        }
        $w = self::pluralRuDays($streak);

        return '🔥 Уже <b>'.$streak.' '.$w.' подряд</b>. Завтра просто отметь день как есть: обычный, отдых или восстановление.';
    }

    /** Персонализация: цепочка дней с «прогулял тренировку». */
    public static function workoutSkippedStreakNudge(int $consecutiveSkippedDays): ?string
    {
        if ($consecutiveSkippedDays < 3) {
            return null;
        }
        $w = self::pluralRuDays($consecutiveSkippedDays);

        return '🏋️ Уже <b>'.$consecutiveSkippedDays.' '.$w.' подряд</b> в чек-ине отмечаешь пропуск движения.'
            ."\n".'Если это болезнь или восстановление - выбирай этот вариант в чек-ине, я не буду давить тренировками.';
    }

    public static function eveningReminderSoft(RatingService $rating, User $user, Carbon $today): string
    {
        $dayNum = self::dayNumberInBot($user, $today);
        $lines = [];

        if ($dayNum === 2) {
            $lines[] = 'Второй день - самое время спокойно закрепить привычку отмечаться.';
            $lines[] = '';
        } elseif ($dayNum >= 3 && $dayNum <= 5) {
            $lines[] = 'Ты уже '.$dayNum.'‑й день здесь. Достаточно коротко отметить факты дня.';
            $lines[] = '';
        }

        $lines[] = '🔔 Сегодня ты ещё <b>не закрыл день</b> в чек-ине.';
        $lines[] = '';
        $lines[] = 'Пара минут - и картина дня сохранена. Если болеешь, выбери восстановление в шаге «Движение».';

        $yesterday = $today->copy()->subDay();
        $couldCheckYesterday = self::userWasRegisteredOnOrBeforeCalendarDay($user, $yesterday);
        $missedYesterday = $couldCheckYesterday
            && ! $rating->hasCompletedCheckOnDate($user, $yesterday);

        if ($missedYesterday) {
            $lines[] = '';
            $lines[] = 'Вчера чек-ин не закрылся. Ничего, сегодня можно вернуться коротко и без самобичевания.';
            $lines[] = 'Зайди в /check и отметь реальность дня.';

            $twoAgo = $today->copy()->subDays(2);
            if (
                self::userWasRegisteredOnOrBeforeCalendarDay($user, $twoAgo)
                && ! $rating->hasCompletedCheckOnDate($user, $twoAgo)
            ) {
                $lines[] = '';
                $lines[] = 'И позавчера пусто. Вернуться проще через один спокойный чек-ин, а не через идеальный день.';
            }
        }

        $streak = $rating->checkInStreakDays($user, $today);
        $streakLine = self::streakCoreBanner($streak, false);
        if ($streakLine !== null) {
            $lines[] = '';
            $lines[] = $streakLine;
        }

        $skipRun = $rating->consecutiveSkippedWorkoutDays($user, $today);
        $gymNudge = self::workoutSkippedStreakNudge($skipRun);
        if ($gymNudge !== null) {
            $lines[] = '';
            $lines[] = $gymNudge;
        }

        return implode("\n", $lines);
    }

    public static function eveningReminderStrict(): string
    {
        return "Напоминание про чек-ин.\n\n"
            .'Если день обычный - отметь питание, сон, воду и движение. Если болеешь или восстанавливаешься - выбери этот вариант, без давления на тренировки.';
    }

    /** Второе напоминание вечером: в первый день в боте без «снова». */
    public static function eveningReminderStrictFirstDayInBot(): string
    {
        return "Сегодня чек-ин ещё не закрыт.\n\n"
            .'Первый день как раз про то, чтобы просто отметиться. Зайди в /check.';
    }

    public static function churnAfterTwoDays(): string
    {
        return "Давно не было чек-ина.\n\n"
            .'Можно вернуться коротко: отметить день как есть. Если болеешь - включи режим восстановления в настройках.';
    }

    public static function churnAfterFourDays(): string
    {
        return "Похоже, ты поставил учёт на паузу.\n\n"
            .'Если это болезнь или завал - окей. Включи режим восстановления, и я не буду присылать мотивационные напоминания.';
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
                .'Дальше закрепляем спокойно: короткий чек-ин лучше, чем идеальный план в голове.',
            5 => "🔥 Пять дней подряд 🔥\n\n"
                .'Ты уже не из тех, кто скачал и забыл. Продолжай отмечаться без героизма.'
                ."\n\n"
                .'🏁 В клубе из таких чек-инов собираем weekly-отчёт и план на неделю. Пока просто продолжай отмечаться.',
            7 => "🔥 Неделя без дыр в чек-ине.\n\n"
                .'Хороший ритм. Дальше держим систему без лишнего давления.',
            10 => "🔥 10 дней подряд.\n\n"
                .'Уже привычка, не вдохновение. Большинство сюда не доходит.',
            14 => "🔥 Две недели в ритме.\n\n"
                .'Не останавливайся на полпути.',
            21 => "🔥 Три недели.\n\n"
                .'Это уже устойчивый учёт. Продолжай фиксировать реальность.',
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
            $tail = 'День слабый по цифрам, но он хотя бы зафиксирован.';
            if ($weak !== null) {
                $tail .= ' Слабое место сегодня: <b>'.$weak.'</b>.';
            }

            return $head."\n\n"
                .'Не лучший день по цифрам.'
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
                    .'Норм, но <b>'.$weak.'</b> тянет вниз. Завтра можно подтянуть именно это.';
            }

            return $head."\n\n"
                .'Неплохо. Завтра можно подтянуть слабое место.';
        }

        if ($weak !== null) {
            return $head."\n\n"
                .'Ниже среднего. Особенно <b>'.$weak.'</b>.'
                ."\n\n"
                .'Выбери один пункт на завтра и сделай его проще.';
        }

        return $head."\n\n"
            .'Ниже среднего. Завтра достаточно улучшить один пункт.';
    }

    /** День «жизни» в боте с момента регистрации: 1 = день регистрации. */
    public static function dayNumberInBot(User $user, Carbon $now): int
    {
        return (int) $user->created_at->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()) + 1;
    }

    /**
     * Пользователь уже был зарегистрирован к началу этого календарного дня
     * (иначе нельзя честно говорить «вчера не отметился» / «позавчера пусто»).
     */
    public static function userWasRegisteredOnOrBeforeCalendarDay(User $user, Carbon $calendarDay): bool
    {
        return $user->created_at->copy()->startOfDay()->lte($calendarDay->copy()->startOfDay());
    }

    public static function morningDay7(): string
    {
        return "Неделя с ботом.\n\n"
            .'Уже есть первые цифры: по ним проще видеть ритм, чем вспоминать на глаз.'
            ."\n\n"
            .'🏁 Если нужен контроль сильнее - открой <b>Клуб 30 дней</b>. Пока главное: не бросай вечерний чек-ин.';
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
            '☀️ День только начался. Вечером не нужен героизм - достаточно честно закрыть <b>/check</b>.',
            '☀️ Первые дни - это не про идеал, про то, чтобы не пропадать. Вечером зайди, отметь как есть.',
            '☀️ Вечером заполни чек-ин - будет понятнее, что реально получилось за день.',
            '☀️ Выбери один якорь на сегодня - сон, вода, что угодно одно - и вечером честно отрази в чек-ине. Маленькими шагами и выходит ритм.',
            '☀️ Мотивация выветривается. Остаётся привычка закрывать день. Вечером две минуты - и ты снова в курсе, где ты.',
            '☀️ Приходи вечером и отмечай, как прошел твой день.',
            '☀️ Ничего страшного, если день обычный. Обычные дни тоже полезно фиксировать.',
            '☀️ Не обещай себе идеал. Обещай себе дисциплину вечером - открыл чек-ин, записал факты, закрыл.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function morningHookPool(): array
    {
        return [
            '☀️ Ты уже несколько дней в процессе. Сегодня достаточно вечером открыть чек-ин и отметить факты.',
            '☀️ Чек-ин - это не отчёт начальству. Это короткая запись, чтобы видеть картину без догадок.',
            '☀️ Дисциплина не орёт в stories. Она копится в таких скучных вечерах, когда ты всё равно зашёл в <b>/check</b>.',
            '☀️ «Потом» любит повторяться каждый день. Сегодня можно один раз заменить его на «сделал чек-ин».',
            '☀️ Форма - это не про вдохновение. Про то, чтобы не пропадать из учёта. Не пропади сегодня.',
            '☀️ Ты уже потратил время на настройку. Вечером просто обнови картину дня.',
            '☀️ На этом этапе выигрывает не самый мотивированный, а тот, кто вечером всё равно возвращается.',
            '☀️ Три-пять дней подряд - зона, где привычка только собирается. Один короткий чек-ин вечером поможет.',
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
            '☀️ Пропустил вчера - не приговор. Сегодня можно спокойно вернуться одним чек-ином.',
            '☀️ Еда, сон, вода, движение - не звучит как мечта. Зато по факту это и есть форма.',
            '☀️ Каждый закрытый вечер - кирпичик. Не обесценивай кирпичики.',
            '☀️ Если вечером зафиксируешь день - завтра будет не догадка, а понятная картина.',
        ];
    }

    // --- UI / чек-ин / настройки (HTML для Telegram) ---

    public static function unknownCommand(): string
    {
        return '🤔 Неизвестная команда. Доступны: /start, /check, /cancel, /rating, /plan, /analytics, /club, /settings, /profile';
    }

    public static function proAiMenuHint(): string
    {
        return self::fitbotClubOffer();
    }

    public static function proAiCallbackHint(): string
    {
        return self::fitbotClubOffer();
    }

    public static function fitbotClubOffer(?User $user = null): string
    {
        $active = $user?->isFitbotClubActive() ?? false;
        if ($active) {
            return '🏁 <b>FitBot Club активен</b>'."\n\n"
                .'Ты в режиме 30 дней контроля: чек-ины, вес, weekly-отчёт, полная аналитика и закрытый чат.'
                ."\n\n"
                .'Открывай weekly-отчёт раз в неделю и смотри, что реально мешает похудению.';
        }

        return '🏁 <b>FitBot Club: 30 дней контроля похудения</b>'."\n\n"
            .'Для новичков, которые уже много раз начинали и срывались на 3-7 день.'
            ."\n\n"
            .'В бесплатной версии остаются чек-ин, рейтинг, план, вес и базовые напоминания.'
            ."\n\n"
            .'В клубе добавляем то, за что реально платят:'
            ."\n"
            .'• закрытый чат и короткие задания каждый день'."\n"
            .'• weekly-отчёт: вес, чек-ины, слабое место недели и план на следующую'."\n"
            .'• полная аналитика за 30 дней'."\n"
            .'• разбор типичных ошибок новичков'."\n"
            .'• поддержка, если был срыв, болезнь или откат'
            ."\n\n"
            .'Beta-набор: <b>первые 30 мест</b>. Founder price: <b>490-790 ₽ за 30 дней</b>.';
    }

    public static function fitbotClubPaywall(string $feature): string
    {
        $featureLabel = match ($feature) {
            'weekly' => 'weekly-отчёт клуба',
            'analytics' => 'полная аналитика за 30 дней',
            default => 'эта функция',
        };

        return '🏁 <b>'.$featureLabel.'</b> - часть FitBot Club.'."\n\n"
            .'Бесплатно бот помогает держать привычку: чек-ин, рейтинг, план, вес и базовые напоминания.'
            ."\n\n"
            .'Клуб - это контроль, чат и отчёты, чтобы не пропасть после пары плохих дней.';
    }

    public static function fitbotClubJoinRequested(): string
    {
        return '✅ Заявка в beta-набор FitBot Club отправлена.'."\n\n"
            .'Админ увидит её в поддержке и напишет условия входа. Пока продолжай чек-ины: это база, на которой строится весь контроль.';
    }

    public static function weeklyFocusSavedAfterText(): string
    {
        return 'Записал <b>фокус недели</b> - строка в конце <b>/rating</b> и после чек-ина.';
    }

    public static function mainMenuFallback(): string
    {
        return '👇 Кнопки внизу или команды: /check, /cancel, /rating, /plan, /analytics, /club, /settings, /profile. '
            .'Анкета и статус — <b>👤 Профиль</b>. Баг или идея — <b>✉️ Написать в поддержку</b>.';
    }

    public static function supportIntroPrompt(): string
    {
        return '✉️ <b>Поддержка</b>'."\n\n"
            .'Опиши баг или предложение одним сообщением (от 5 символов). Ответ придёт, когда подключим ответы из бота.'."\n\n"
            .'Сейчас твоё сообщение увидит администратор в панели.'."\n\n"
            .'<i>Отменить: напиши «отмена» или отправь /cancel</i>';
    }

    public static function supportThanksAfterSend(): string
    {
        return '✅ <b>Спасибо!</b> Сообщение передано. Если нужно ещё — снова нажми «Написать в поддержку».';
    }

    public static function supportCancelled(): string
    {
        return 'Ок, не отправляю. Если передумаешь — кнопка <b>✉️ Написать в поддержку</b>.';
    }

    public static function supportTooShort(): string
    {
        return 'Слишком коротко — распиши чуть подробнее (минимум 5 символов) или отмени: <i>отмена</i>.';
    }

    public static function supportPhotoNotAccepted(): string
    {
        return 'Пока прими только <b>текстом</b>. Опиши проблему или идею словами — или нажми «отмена».';
    }

    public static function strikeStatusLegend(): string
    {
        $rows = [];
        foreach ([
            StrikeStatusTier::Novice,
            StrikeStatusTier::Snowdrop,
            StrikeStatusTier::Amateur,
            StrikeStatusTier::Experienced,
            StrikeStatusTier::Boss,
        ] as $t) {
            $rows[] = $t->emoji().' <b>'.$t->labelRu().'</b> — '.$t->criteriaRu().'.';
        }

        return '🎖 <b>Статусы FitBot</b>'."\n\n"
            .'<b>Серия</b> — сколько календарных дней <b>подряд</b> у тебя закрыт чек-ин. Пропустил день — цепочка обрывается, статус может снизиться.'."\n\n"
            .implode("\n", $rows)
            ."\n\n".'Чем дольше держишь дисциплину без дыр, тем выше уровень. Открой <b>👤 Профиль</b>, чтобы увидеть актуальные данные.';
    }

    public static function profilePhotoCaption(StrikeStatusTier $tier, int $streak): string
    {
        $w = self::pluralRuDays($streak);

        return '📸 <b>Последнее фото в базе</b>'."\n"
            .$tier->emoji().' '.$tier->labelRu().' · 🔥 <b>'.$streak.'</b> '.$w.' подряд';
    }

    public static function profileMessage(User $user, RatingService $rating, int $streak, StrikeStatusTier $tier): string
    {
        $lines = [
            '━━━━━ <b>Твой профиль</b> ━━━━━',
            '',
            $tier->emoji().' <b>'.$tier->labelRu().'</b> · 🔥 серия <b>'.$streak.'</b> '.self::pluralRuDays($streak),
        ];
        $hint = self::strikeProgressHint($tier, $streak);
        if ($hint !== '') {
            $lines[] = '<i>'.$hint.'</i>';
        }
        $lines[] = '';
        $lines[] = '📋 <b>Анкета</b>';
        $lines = array_merge($lines, self::profileQuestionnaireLines($user));
        if ($user->isRecoveryModeActive()) {
            $lines[] = '';
            $lines[] = '🤒 <b>Режим восстановления</b> активен до '.$user->recovery_mode_until?->format('d.m H:i').'. Давящие напоминания выключены.';
        }
        if ($user->isFitbotClubActive()) {
            $lines[] = '';
            $lines[] = '🏁 <b>FitBot Club</b> активен до '.$user->fitbot_club_until?->format('d.m').'. Weekly-отчёт и полная аналитика открыты.';
        }
        $s = $rating->summary($user);
        $lines[] = '';
        $lines[] = '📊 <b>Баллы</b>: сегодня '.$s['day'].', неделя '.$s['week'].', месяц '.$s['month'];
        $lines[] = '';
        $lines[] = '<i>Нажми «Как устроены статусы» ниже, если нужна расшифровка уровней.</i>';

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private static function profileQuestionnaireLines(User $user): array
    {
        $name = trim((string) ($user->first_name ?? ''));
        if ($name === '') {
            $name = '—';
        }
        $un = $user->username ? '@'.$user->username : '—';
        $g = Gender::tryFrom((string) $user->gender);
        $age = $user->age !== null ? (string) $user->age : '—';
        $weight = $user->weight_kg !== null ? (string) $user->weight_kg.' кг' : '—';
        $height = $user->height_cm !== null ? (string) $user->height_cm.' см' : '—';
        $act = ActivityLevel::tryFrom((string) $user->activity_level);
        $goal = FitnessGoal::tryFrom((string) $user->goal) ?? FitnessGoal::Maintain;
        $exp = ExperienceLevel::tryFrom((string) $user->experience);
        $sleep = $user->sleep_target_hours !== null ? (string) $user->sleep_target_hours.' ч' : '—';
        $water = $user->water_goal_ml !== null ? (string) $user->water_goal_ml.' мл' : '—';

        $out = [
            'Имя: <b>'.e($name).'</b> · TG: <b>'.$un.'</b>',
            'Пол: <b>'.e($g?->labelRu() ?? '—').'</b> · возраст: <b>'.$age.'</b>',
            'Вес: <b>'.$weight.'</b> · рост: <b>'.$height.'</b>',
            'Активность: <b>'.e($act?->labelRu() ?? '—').'</b>',
            'Цель: <b>'.e($goal->labelRu()).'</b> · опыт в зале: <b>'.e($exp?->labelRu() ?? '—').'</b>',
            'Сон (цель): <b>'.$sleep.'</b> · вода (цель): <b>'.$water.'</b>',
        ];
        if ($user->usesGeneratedNutritionPlan()) {
            $kcal = $user->daily_calories_target !== null ? (string) $user->daily_calories_target : '—';
            $out[] = 'План FitBot: <b>'.$kcal.' ккал</b> / БЖУ '
                .(int) ($user->protein_g ?? 0).' / '.(int) ($user->fat_g ?? 0).' / '.(int) ($user->carbs_g ?? 0).' г';
        } elseif ($user->isDisciplineOnlyMode()) {
            $out[] = 'Режим: <b>свой план</b> (без расчёта ккал в боте)';
        } else {
            $out[] = 'План: <b>—</b>';
        }

        return $out;
    }

    private static function strikeProgressHint(StrikeStatusTier $tier, int $streak): string
    {
        $left = $tier->daysUntilNext($streak);
        if ($left === null) {
            return 'Ты на максимальном статусе — не теряй серию.';
        }
        if ($left === 0) {
            $next = $tier->next();
            $nl = $next !== null ? $next->labelRu() : '';

            return 'Остался один закрытый день до статуса «'.$nl.'».';
        }
        $next = $tier->next();
        if ($next === null) {
            return '';
        }

        return 'До «'.$next->labelRu().'»: ещё <b>'.$left.'</b> '.self::pluralRuDays($left).' серии.';
    }

    public static function strikeStatusRankUpLine(int $streak, StrikeStatusTier $tier): ?string
    {
        if (! in_array($streak, [8, 15, 31, 61], true)) {
            return null;
        }

        return '🎉 <b>Новый статус: '.$tier->emoji().' '.$tier->labelRu().'</b>'."\n"
            .'Серия <b>'.$streak.'</b> '.self::pluralRuDays($streak).' подряд.';
    }

    public static function strikeStatusMondayLine(int $streak, StrikeStatusTier $tier): ?string
    {
        if ($streak < 1) {
            return null;
        }
        $w = self::pluralRuDays($streak);
        $line = '🎖 Неделя стартует: <b>'.$tier->labelRu().'</b> · серия <b>'.$streak.'</b> '.$w.'.';
        $hint = self::strikeProgressHint($tier, $streak);
        if ($hint !== '') {
            $line .= "\n".'<i>'.$hint.'</i>';
        }

        return $line;
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
            ."⚖️ <b>Обновить вес</b> - быстро поменять текущий вес без полной анкеты.\n\n"
            ."🤒 <b>Болею / восстановление</b> - пауза на пуши и нормальный вариант в чек-ине без давления на тренировки.\n\n"
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
    public static function notificationsPanelText(array $flags, string $quietStart, string $quietEnd, ?Carbon $recoveryUntil = null): string
    {
        $on = fn (bool $v) => $v ? '✅' : '⛔️';
        $recoveryLine = $recoveryUntil !== null && $recoveryUntil->isFuture()
            ? "\n\n".'🤒 <b>Восстановление активно до '.$recoveryUntil->format('d.m H:i').'</b>. Пока оно активно, плановые пуши не отправляются.'
            : '';

        return '🔔 <b>Уведомления</b>'."\n\n"
            .'Что включено:'."\n"
            .$on($flags['morning']).' Утро (мотивация по расписанию)'."\n"
            .$on($flags['evening']).' Вечер (напоминание про чек-ин)'."\n"
            .$on($flags['churn']).' Возврат после паузы'."\n"
            .$on($flags['weekly_focus']).' Фокус недели (раз в неделю)'."\n"
            .$on($flags['weekly_weight']).' Вес: напоминание обновить (раз в неделю)'."\n"
            .$on($flags['quiet']).' Тихие часы: не беспокоить'."\n\n"
            .'Окно тишины: <b>'.e($quietStart).' - '.e($quietEnd).'</b> (локаль сервера)'."\n\n"
            .'<i>Пресеты меняют только интервал «не беспокоить».</i>'
            .$recoveryLine;
    }

    public static function recoveryModeMenuTitle(?Carbon $until): string
    {
        $status = $until !== null && $until->isFuture()
            ? 'Сейчас активно до <b>'.$until->format('d.m H:i').'</b>.'
            : 'Сейчас выключено.';

        return '🤒 <b>Болею / восстановление</b>'."\n\n"
            .$status."\n\n"
            .'На время режима я не шлю утренние, вечерние, возвратные и недельные напоминания. В чек-ине можно выбрать «Болею / восстановление» вместо тренировки.';
    }

    public static function recoveryModeEnabled(Carbon $until): string
    {
        return '🤒 Ок, включил режим восстановления до <b>'.$until->format('d.m H:i').'</b>.'."\n\n"
            .'Плановые пуши на это время замолчат. Чек-ин можно закрывать в мягком режиме, без давления на тренировки.';
    }

    public static function recoveryModeDisabled(): string
    {
        return 'Режим восстановления выключен. Плановые уведомления снова работают по твоим настройкам.';
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
            .'<i>Не сейчас - просто не отвечай, запрос сам истечёт.</i>';
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

    public static function recoveryCheckClosing(): string
    {
        return '🤒 Восстановление отмечено. Такой день тоже считается: задача сейчас - не геройствовать, а спокойно вернуться в ритм, когда станет легче.';
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
        return '💪 <b>Движение сегодня</b>'."\n\n"
            .'Если болеешь или восстанавливаешься - выбери этот вариант, тренировки требовать не буду.'."\n\n"
            .'<i>Отмена чек-ина - кнопка внизу.</i>';
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
        return 'Сколько часов сна в день нужно? Введи число (например <b>7.5</b>).';
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
            .'Фото помогает увидеть изменения, которые в зеркале легко не заметить.';
    }

    public static function progressPhotoSaved(): string
    {
        return 'Фото прогресса сохранено. Следующее напоминание через 30 дней.';
    }
}
