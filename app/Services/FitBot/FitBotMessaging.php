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
}
