<?php

namespace App\Services\FitBot;

use App\Enums\ActivityLevel;
use App\Enums\CheckRating;
use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\OnboardingStep;
use App\Enums\PhotoType;
use App\Enums\UserPlanMode;
use App\Enums\WorkoutCheckVariant;
use App\Models\DailyCheck;
use App\Models\Photo;
use App\Models\User;
use App\Services\PlanGeneratorService;
use App\Services\RatingService;
use App\Services\Telegram\TelegramBotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FitBotService
{
    /** @var array{user_id: int, chat_id: int}|null */
    private ?array $deferredProgressPhotoReminder = null;

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly PlanGeneratorService $plans,
        private readonly RatingService $rating,
    ) {}

    public function handleUpdate(array $u): void
    {
        if (isset($u['callback_query'])) {
            $this->handleCallback($u['callback_query']);

            return;
        }

        if (isset($u['message'])) {
            $this->handleMessage($u['message']);
        }
    }

    public function flushDeferredProgressPhotoReminder(): void
    {
        if ($this->deferredProgressPhotoReminder === null) {
            return;
        }
        $payload = $this->deferredProgressPhotoReminder;
        $this->deferredProgressPhotoReminder = null;

        $user = User::query()->find($payload['user_id']);
        if ($user === null) {
            return;
        }
        $this->maybeRemindProgressPhoto($user, $payload['chat_id']);
    }

    private function scheduleDeferredProgressPhotoReminder(User $user, int $chatId): void
    {
        $this->deferredProgressPhotoReminder = ['user_id' => $user->id, 'chat_id' => $chatId];
    }

    private function handleMessage(array $msg): void
    {
        $chatId = (int) $msg['chat']['id'];
        $from = $msg['from'] ?? [];
        $telegramId = (int) ($from['id'] ?? 0);

        if ($telegramId === 0) {
            return;
        }

        $user = $this->syncUser($telegramId, $from);

        $text = $this->normalizeMessageText((string) ($msg['text'] ?? ''));
        $isCommand = $text !== '' && Str::startsWith($text, '/');
        if (! $isCommand && empty($msg['photo']) && ! $this->isUserCancelCheckPhrase($text)) {
            $this->scheduleDeferredProgressPhotoReminder($user, $chatId);
        }

        if ($isCommand) {
            $command = $this->normalizeBotCommand($text);
            match ($command) {
                '/start' => $this->cmdStart($user, $chatId),
                '/check' => $this->cmdCheck($user, $chatId),
                '/cancel' => $this->cmdCancelCheck($user, $chatId),
                '/rating' => $this->cmdRating($user, $chatId),
                '/plan' => $this->cmdPlan($user, $chatId),
                '/analytics', '/stats', '/аналитика' => $this->cmdExtendedAnalytics($user, $chatId),
                '/settings', '/настройки', '/setting' => $this->cmdSettings($user, $chatId),
                default => $this->telegram->sendMessage(
                    $chatId,
                    '🤔 Неизвестная команда. Доступны: /start, /check, /cancel, /rating, /plan, /analytics, /settings',
                    $user->hasCompletedOnboarding() ? $this->mainMenuKeyboard() : null
                ),
            };

            return;
        }

        if ($text !== '' && $this->isPlainSettingsRequest($text)) {
            $this->cmdSettings($user, $chatId);

            return;
        }

        if ($text !== '' && ! $isCommand && $user->hasCompletedOnboarding()) {
            $focusKey = $this->weeklyFocusPromptCacheKey($telegramId);
            if (Cache::has($focusKey)) {
                Cache::forget($focusKey);
                $user->weekly_focus_note = Str::limit(trim($text), 255);
                $user->save();
                $this->telegram->sendMessage(
                    $chatId,
                    'Записал <b>фокус недели</b> — строка в конце <b>/rating</b> и после чек-ина.',
                    $this->mainMenuKeyboard()
                );

                return;
            }
        }

        if ($text !== '') {
            $menuAction = $this->replyMenuAction($text);
            if ($menuAction !== null) {
                match ($menuAction) {
                    'check' => $this->cmdCheck($user, $chatId),
                    'rating' => $this->cmdRating($user, $chatId),
                    'plan' => $this->cmdPlan($user, $chatId),
                    'settings' => $this->cmdSettings($user, $chatId),
                    'analytics' => $this->cmdExtendedAnalytics($user, $chatId),
                    'plan_ai' => $this->telegram->sendMessage(
                        $chatId,
                        '💎 <b>PRO / AI-тренировки</b> — позже, с оплатой. Сейчас пользуйся планом и чек-инами бесплатно.',
                        $this->mainMenuKeyboard()
                    ),
                };

                return;
            }
        }

        if ($text !== '' && $user->hasCompletedOnboarding() && $this->isUserCancelCheckPhrase($text)) {
            $this->cmdCancelCheck($user, $chatId);

            return;
        }

        if ($text !== '' && $user->hasCompletedOnboarding()) {
            if ($this->tryConsumeCheckQuantityReply($user, $chatId, $text)) {
                return;
            }
        }

        if (! empty($msg['photo']) && $user->onboardingStepEnum() === OnboardingStep::AskBeforePhoto) {
            $fileId = (string) $msg['photo'][array_key_last($msg['photo'])]['file_id'];
            $this->finishOnboardingWithOptionalPhoto($user, $chatId, $fileId);

            return;
        }

        if (! empty($msg['photo']) && $user->hasCompletedOnboarding()) {
            $this->saveProgressPhoto($user, $chatId, $msg['photo']);

            return;
        }

        $step = $user->onboardingStepEnum();
        if ($step !== null && $text !== '') {
            $this->handleOnboardingText($user, $chatId, $step, $text);

            return;
        }

        if ($user->hasCompletedOnboarding()) {
            $this->telegram->sendMessage(
                $chatId,
                '👇 Кнопки внизу или команды: /check, /cancel, /rating, /plan, /analytics, /settings',
                $this->mainMenuKeyboard()
            );
        }
    }

    private function handleCallback(array $cq): void
    {
        $from = $cq['from'] ?? [];
        $telegramId = (int) ($from['id'] ?? 0);
        $data = (string) ($cq['data'] ?? '');
        $chatId = (int) ($cq['message']['chat']['id'] ?? 0);

        if ($telegramId === 0 || $chatId === 0) {
            return;
        }

        $user = $this->syncUser($telegramId, $from);
        $this->telegram->answerCallbackQuery($cq['id']);

        if ($data === 'menu:check') {
            $this->cmdCheck($user, $chatId);

            return;
        }

        if ($data === 'menu:rating') {
            $this->cmdRating($user, $chatId);

            return;
        }

        if ($data === 'menu:settings') {
            $this->cmdSettings($user, $chatId);

            return;
        }

        if ($data === 'menu:plan') {
            $this->cmdPlan($user, $chatId);

            return;
        }

        if ($data === 'menu:analytics') {
            $this->cmdExtendedAnalytics($user, $chatId);

            return;
        }

        if (str_starts_with($data, 'set:')) {
            $editMid = isset($cq['message']['message_id']) ? (int) $cq['message']['message_id'] : null;
            $this->handleSettingsCallback($user, $chatId, $data, $editMid);

            return;
        }

        if ($data === 'pay:ai') {
            $this->telegram->sendMessage(
                $chatId,
                '💎 <b>PRO</b> (когда подключим): AI-тренировки под твой уровень и цель, режим давления, серии, коллажи прогресса, аналитика.'
                ."\n\n"
                .'Сейчас всё основное уже в бесплатной версии — продолжай чек-ины и план.',
                $this->mainMenuKeyboard()
            );

            return;
        }

        if (str_starts_with($data, 'onb:')) {
            $this->handleOnboardingCallback($user, $chatId, $data);

            return;
        }

        if (str_starts_with($data, 'chk:')) {
            $this->handleCheckCallback($user, $chatId, $data);

            return;
        }
    }

    private function cmdStart(User $user, int $chatId): void
    {
        if ($user->hasCompletedOnboarding()) {
            $this->telegram->sendMessage(
                $chatId,
                '👋 Снова привет! Я <b>FitBot</b> — держим питание 😋, сон 😴, тренировки 💪 и воду 💧 под контролем.',
                $this->mainMenuKeyboard()
            );

            return;
        }

        match ($user->onboardingStepEnum()) {
            OnboardingStep::AskWelcome => $this->sendWelcomeScreen($chatId),
            OnboardingStep::AskPlanChoice => $this->askPlanChoice($chatId),
            OnboardingStep::AskGender => $this->askGender($user, $chatId),
            OnboardingStep::AskAge => $this->sendOnboardingAgePrompt($user, $chatId),
            OnboardingStep::AskWeight => $this->telegram->sendMessage(
                $chatId,
                'Введи <b>вес в кг</b> (например 75.5).',
                $this->onboardingTextPromptKeyboard()
            ),
            OnboardingStep::AskHeight => $this->telegram->sendMessage(
                $chatId,
                'Введи <b>рост в см</b> (например 180).',
                $this->onboardingTextPromptKeyboard()
            ),
            OnboardingStep::AskActivity => $this->askActivity($chatId),
            OnboardingStep::AskGoal => $this->askGoal($chatId),
            OnboardingStep::AskExperience => $this->askExperience($chatId),
            OnboardingStep::AskSleep => $this->sendOnboardingSleepPrompt($user, $chatId),
            OnboardingStep::AskWaterGoal => $this->telegram->sendMessage(
                $chatId,
                '💧 Сколько <b>мл воды в день</b> для твоей цели? (например <b>2500</b> или <b>2.5 л</b>) — от этого считается балл за воду в /check.',
                $this->onboardingTextPromptKeyboard()
            ),
            OnboardingStep::AskBeforePhoto => $this->telegram->sendMessage(
                $chatId,
                'Пришли фото «до» или нажми «Пропустить».',
                $this->telegram->inlineKeyboard([
                    [['text' => 'Пропустить фото', 'callback_data' => 'onb:photo:skip']],
                    [['text' => '◀️ Назад', 'callback_data' => 'onb:back']],
                ])
            ),
            default => $this->telegram->sendMessage($chatId, 'Продолжим настройку — следуй подсказкам выше.'),
        };
    }

    private function cmdCheck(User $user, int $chatId): void
    {
        if (! $user->hasCompletedOnboarding()) {
            $this->telegram->sendMessage($chatId, 'Сначала завершите онбординг: /start');

            return;
        }

        $today = Carbon::today()->toDateString();

        $existing = DailyCheck::query()
            ->where('user_id', $user->id)
            ->whereDate('check_date', $today)
            ->first();

        if ($existing && $existing->is_completed) {
            $this->telegram->sendMessage(
                $chatId,
                'Сегодняшний чек-ин уже заполнен. Завтра снова жду /check',
                $this->mainMenuKeyboard()
            );

            return;
        }

        $check = $existing ?? new DailyCheck([
            'user_id' => $user->id,
            'check_date' => $today,
            'is_completed' => false,
            'total_score' => 0,
        ]);
        if (! $check->exists) {
            $check->save();
        }

        $this->showCheckProgress($user, $chatId, $check);
    }

    private function cmdRating(User $user, int $chatId): void
    {
        if (! $user->hasCompletedOnboarding()) {
            $this->telegram->sendMessage($chatId, 'Сначала завершите онбординг: /start');

            return;
        }

        $text = $this->rating->formatSummaryMessage($user);
        $this->telegram->sendMessage($chatId, $text, $this->mainMenuKeyboard());
    }

    private function cmdExtendedAnalytics(User $user, int $chatId): void
    {
        if (! $user->hasCompletedOnboarding()) {
            $this->telegram->sendMessage($chatId, 'Сначала завершите онбординг: /start');

            return;
        }

        $text = $this->rating->formatExtendedAnalyticsMessage($user);
        $this->telegram->sendMessage($chatId, $text, $this->mainMenuKeyboard());
    }

    private function cmdPlan(User $user, int $chatId): void
    {
        if (! $user->hasCompletedOnboarding()) {
            $this->telegram->sendMessage($chatId, '⏳ Сначала заверши онбординг: /start');

            return;
        }

        $this->telegram->sendMessage($chatId, $this->plans->buildPlanMessage($user), $this->mainMenuKeyboard());
    }

    private function cmdSettings(User $user, int $chatId): void
    {
        // Без HTML: иначе при ошибке разбора сущностей Telegram отвечает ok=false при HTTP 200 — сообщение не уходит.
        $this->telegram->sendMessage(
            $chatId,
            "⚙️ <b>Настройки</b>\n\n"
            ."✏️ <b>Сменить анкету</b> — заново пройти вопросы (пол, возраст, вес…). История чек-инов сохранится, цели пересчитаются после завершения.\n\n"
            .'🗑 <b>Удалить аккаунт</b> — сотрутся все чек-ины, фото и цели. Потом снова /start.',
            $this->settingsMenuKeyboard()
        );
    }

    /** Сбросить незавершённый чек-ин за сегодня (если есть). */
    private function cmdCancelCheck(User $user, int $chatId): void
    {
        if (! $user->hasCompletedOnboarding()) {
            $this->telegram->sendMessage($chatId, 'Сначала завершите онбординг: /start');

            return;
        }

        if ($this->tryCancelIncompleteCheck($user, $chatId)) {
            return;
        }

        $this->telegram->sendMessage(
            $chatId,
            'Незавершённого чек-ина за сегодня нет. Открыть заново: <b>/check</b>.',
            $this->mainMenuKeyboard()
        );
    }

    private function isUserCancelCheckPhrase(string $text): bool
    {
        $t = Str::lower(trim($text));

        return in_array($t, [
            'отмена',
            'отменить',
            'отменить чек-ин',
            'cancel',
            'стоп',
            '❌ отменить чек-ин',
        ], true);
    }

    /**
     * Удалить черновик чек-ина за сегодня. При несовпадении $checkId (старая кнопка) пробуем любой черновик за сегодня.
     */
    private function tryCancelIncompleteCheck(User $user, int $chatId, ?int $checkId = null): bool
    {
        $today = Carbon::today()->toDateString();
        $check = null;
        if ($checkId !== null) {
            $check = DailyCheck::query()
                ->where('user_id', $user->id)
                ->where('is_completed', false)
                ->whereDate('check_date', $today)
                ->whereKey($checkId)
                ->first();
        }
        if ($check === null) {
            $check = DailyCheck::query()
                ->where('user_id', $user->id)
                ->where('is_completed', false)
                ->whereDate('check_date', $today)
                ->first();
        }

        if ($check === null) {
            return false;
        }

        if ($check->telegram_progress_message_id) {
            $this->telegram->deleteMessages($chatId, [(int) $check->telegram_progress_message_id]);
        }

        $check->delete();
        $this->telegram->sendMessage(
            $chatId,
            '❌ <b>Чек-ин отменён.</b>'."\n\n"
            .'Начать снова: кнопка <b>Чек-ин</b> или <b>/check</b>.'."\n"
            .'<i>/cancel</i> всегда сбрасывает незавершённый чек за сегодня.',
            $this->mainMenuKeyboard()
        );

        return true;
    }

    /** Нижняя (reply) клавиатура — кнопки «Настройки» и остальное всегда видны под полем ввода. */
    /** @return array<string, mixed> */
    private function mainMenuKeyboard(): array
    {
        return $this->telegram->fitbotMainMenuKeyboard();
    }

    /** @return array<string, mixed> */
    private function settingsMenuKeyboard(): array
    {
        return $this->telegram->inlineKeyboard([
            [['text' => '✏️ Сменить анкету', 'callback_data' => 'set:edit']],
            [['text' => '🔔 Уведомления', 'callback_data' => 'set:notif:menu']],
            [['text' => '📌 Фокус недели', 'callback_data' => 'set:focus:ask']],
            [['text' => '🗑 Удалить аккаунт', 'callback_data' => 'set:del:s']],
            [['text' => '🏠 В главное меню', 'callback_data' => 'set:home']],
        ]);
    }

    private function weeklyFocusPromptCacheKey(int $telegramId): string
    {
        return 'fitbot:weekly_focus_prompt:'.$telegramId;
    }

    private function onboardingTextPromptKeyboard(): array
    {
        return $this->telegram->inlineKeyboard([
            [['text' => '◀️ Назад', 'callback_data' => 'onb:back']],
        ]);
    }

    private function sendOnboardingAgePrompt(User $user, int $chatId): void
    {
        $agePrompt = $user->isDisciplineOnlyMode()
            ? 'Сколько тебе <b>полных лет</b>? Напиши число (например 27).'
            : 'Сколько тебе <b>полных лет</b>? (число, например 27) — учту в расчёте калорий.';
        $this->telegram->sendMessage($chatId, $agePrompt, $this->onboardingTextPromptKeyboard());
    }

    private function sendOnboardingSleepPrompt(User $user, int $chatId): void
    {
        $t = $user->isDisciplineOnlyMode()
            ? 'Сколько часов <b>сна в сутки</b> хочешь держать в цель? Введи число (например <b>7.5</b>) — для чек-ина.'
            : 'Сколько часов сна в цель? Введи число (например <b>7.5</b>).';
        $this->telegram->sendMessage($chatId, $t, $this->onboardingTextPromptKeyboard());
    }

    private function handleSettingsCallback(User $user, int $chatId, string $data, ?int $editMessageId = null): void
    {
        if ($data === 'set:home') {
            Cache::forget($this->deleteAccountCacheKey($user->telegram_id));
            $this->telegram->sendMessage(
                $chatId,
                'Ок, ниже снова основные кнопки. Незавершённый чек-ин за сегодня можно сбросить: <b>/cancel</b>.',
                $this->mainMenuKeyboard()
            );

            return;
        }

        if ($data === 'set:edit') {
            Cache::forget($this->deleteAccountCacheKey($user->telegram_id));
            $this->resetProfileForReonboarding($user);
            $this->telegram->sendMessage(
                $chatId,
                'Ок, начинаем заново: снова описание бота и выбор режима. История чек-инов остаётся; для режима «план FitBot» после анкеты снова пересчитаю калории и БЖУ.',
                $this->telegram->replyKeyboardRemove(),
                null
            );
            $this->sendWelcomeScreen($chatId);

            return;
        }

        if ($data === 'set:del:n') {
            Cache::forget($this->deleteAccountCacheKey($user->telegram_id));
            $this->telegram->sendMessage($chatId, 'Удаление отменено.', $this->settingsMenuKeyboard());

            return;
        }

        if ($data === 'set:del:s') {
            Cache::put($this->deleteAccountCacheKey($user->telegram_id), 0, now()->addMinutes(15));
            $this->telegram->sendMessage(
                $chatId,
                '<b>Удаление аккаунта — подтверждение 1 из 3</b>'."\n\n"
                .'Будут безвозвратно удалены: чек-ины, фото, цели и вся анкета. Продолжить?',
                $this->telegram->inlineKeyboard([
                    [
                        ['text' => 'Да, удалить', 'callback_data' => 'set:del:y'],
                        ['text' => 'Отмена', 'callback_data' => 'set:del:n'],
                    ],
                ])
            );

            return;
        }

        if ($data === 'set:del:y') {
            $key = $this->deleteAccountCacheKey($user->telegram_id);
            $step = Cache::get($key);
            if ($step === null) {
                $this->telegram->sendMessage(
                    $chatId,
                    'Сессия подтверждения истекла или не начата. Нажми «Удалить аккаунт» в /settings ещё раз.',
                    $this->settingsMenuKeyboard()
                );

                return;
            }

            if ($step === 0) {
                Cache::put($key, 1, now()->addMinutes(15));
                $this->telegram->sendMessage(
                    $chatId,
                    '<b>Подтверждение 2 из 3</b>'."\n\n"
                    .'Ты уверен? Восстановить данные будет нельзя.',
                    $this->telegram->inlineKeyboard([
                        [
                            ['text' => 'Да, точно', 'callback_data' => 'set:del:y'],
                            ['text' => 'Отмена', 'callback_data' => 'set:del:n'],
                        ],
                    ])
                );

                return;
            }

            if ($step === 1) {
                Cache::put($key, 2, now()->addMinutes(15));
                $this->telegram->sendMessage(
                    $chatId,
                    '<b>Последнее подтверждение — 3 из 3</b>'."\n\n"
                    .'После этого аккаунт исчезнет. Нажми «Удалить навсегда», если решение окончательное.',
                    $this->telegram->inlineKeyboard([
                        [
                            ['text' => '🗑 Удалить навсегда', 'callback_data' => 'set:del:y'],
                            ['text' => 'Отмена', 'callback_data' => 'set:del:n'],
                        ],
                    ])
                );

                return;
            }

            if ($step === 2) {
                Cache::forget($key);
                $this->telegram->deleteRecordedOutboundMessagesForUser($user);
                $this->telegram->sendMessage(
                    $chatId,
                    'Аккаунт удалён. Чтобы начать с чистого листа, отправь /start.'
                );
                $user->delete();

                return;
            }
        }

        if ($data === 'set:focus:ask') {
            Cache::forget($this->deleteAccountCacheKey($user->telegram_id));
            Cache::put($this->weeklyFocusPromptCacheKey($user->telegram_id), 1, now()->addMinutes(20));
            $this->telegram->sendMessage(
                $chatId,
                '📌 <b>Фокус недели</b>'."\n\n"
                .'Напиши <b>одну короткую строку</b> (до 255 символов), что важно на эту неделю. Пример: «меньше сладкого», «8 ч сна», «3 тренировки».'."\n\n"
                .'<i>Отмена — просто открой другое меню или подожди 20 мин.</i>'
            );

            return;
        }

        if ($data === 'set:notif:menu') {
            Cache::forget($this->deleteAccountCacheKey($user->telegram_id));
            $this->sendNotificationsSettingsPanel($user, $chatId, $editMessageId);

            return;
        }

        if ($data === 'set:notif:back') {
            $this->telegram->sendMessage(
                $chatId,
                '⚙️ <b>Настройки</b>',
                $this->settingsMenuKeyboard()
            );

            return;
        }

        if (str_starts_with($data, 'set:notif:toggle:')) {
            $field = substr($data, strlen('set:notif:toggle:'));
            if ($field === 'morning') {
                $user->notify_morning = ! (bool) $user->notify_morning;
            } elseif ($field === 'evening') {
                $user->notify_evening = ! (bool) $user->notify_evening;
            } elseif ($field === 'churn') {
                $user->notify_churn = ! (bool) $user->notify_churn;
            } elseif ($field === 'quiet') {
                $user->notify_quiet_enabled = ! (bool) $user->notify_quiet_enabled;
            }
            $user->save();
            $this->sendNotificationsSettingsPanel($user, $chatId, $editMessageId);

            return;
        }

        if (str_starts_with($data, 'set:notif:quiet:')) {
            $preset = substr($data, strlen('set:notif:quiet:'));
            $pair = match ($preset) {
                '22_08' => ['22:00', '08:00'],
                '23_07' => ['23:00', '07:00'],
                '00_07' => ['00:00', '07:00'],
                default => null,
            };
            if ($pair !== null) {
                [$start, $end] = $pair;
                $user->quiet_hours_start = $start;
                $user->quiet_hours_end = $end;
                $user->save();
            }
            $this->sendNotificationsSettingsPanel($user, $chatId, $editMessageId);

            return;
        }
    }

    private function sendNotificationsSettingsPanel(User $user, int $chatId, ?int $editMessageId): void
    {
        $user->refresh();
        $on = fn (bool $v) => $v ? '✅' : '⛔️';
        $text = '🔔 <b>Уведомления</b>'."\n\n"
            .'Что включено:'."\n"
            .$on((bool) $user->notify_morning).' Утро (мотивация по расписанию)'."\n"
            .$on((bool) $user->notify_evening).' Вечер (напоминание про чек-ин)'."\n"
            .$on((bool) $user->notify_churn).' Возврат после паузы'."\n"
            .$on((bool) $user->notify_quiet_enabled).' Тихие часы: не беспокоить'."\n\n"
            .'Окно тишины: <b>'.e((string) $user->quiet_hours_start).' — '.e((string) $user->quiet_hours_end).'</b> (локаль сервера)'."\n\n"
            .'<i>Пресеты меняют только интервал «не беспокоить».</i>';

        $markup = $this->telegram->inlineKeyboard([
            [
                ['text' => 'Утро', 'callback_data' => 'set:notif:toggle:morning'],
                ['text' => 'Вечер', 'callback_data' => 'set:notif:toggle:evening'],
                ['text' => 'Паузы', 'callback_data' => 'set:notif:toggle:churn'],
            ],
            [
                ['text' => 'Тихие часы вкл/выкл', 'callback_data' => 'set:notif:toggle:quiet'],
            ],
            [
                ['text' => '22:00–08:00', 'callback_data' => 'set:notif:quiet:22_08'],
                ['text' => '23:00–07:00', 'callback_data' => 'set:notif:quiet:23_07'],
            ],
            [
                ['text' => '00:00–07:00', 'callback_data' => 'set:notif:quiet:00_07'],
            ],
            [
                ['text' => '◀️ К настройкам', 'callback_data' => 'set:notif:back'],
            ],
        ]);

        if ($editMessageId !== null && $this->telegram->editMessageText($chatId, $editMessageId, $text, $markup)) {
            return;
        }
        $this->telegram->sendMessage($chatId, $text, $markup);
    }

    private function deleteAccountCacheKey(int $telegramId): string
    {
        return 'fitbot:account_delete:'.$telegramId;
    }

    private function resetProfileForReonboarding(User $user): void
    {
        Photo::query()
            ->where('user_id', $user->id)
            ->where('type', PhotoType::Before->value)
            ->delete();

        $user->age = null;
        $user->weight_kg = null;
        $user->height_cm = null;
        $user->gender = null;
        $user->activity_level = null;
        $user->goal = null;
        $user->experience = null;
        $user->sleep_target_hours = null;
        $user->daily_calories_target = null;
        $user->protein_g = null;
        $user->fat_g = null;
        $user->carbs_g = null;
        $user->water_goal_ml = null;
        $user->before_photo_file_id = null;
        $user->next_progress_photo_at = null;
        $user->weekly_focus_note = null;
        $user->plan_mode = null;
        $user->onboarding_step = OnboardingStep::AskWelcome->value;
        $user->save();
    }

    private function handleOnboardingCallback(User $user, int $chatId, string $data): void
    {
        if ($data === 'onb:back') {
            $this->onboardingGoBack($user, $chatId);

            return;
        }

        $parts = explode(':', $data, 3);
        if (count($parts) < 3) {
            return;
        }
        [, $key, $value] = $parts;

        if ($key === 'welcome' && $value === 'continue') {
            $user->onboarding_step = OnboardingStep::AskPlanChoice->value;
            $user->save();
            $this->askPlanChoice($chatId);

            return;
        }

        if ($key === 'plan') {
            $mode = UserPlanMode::tryFrom($value);
            if (! $mode) {
                return;
            }
            $user->plan_mode = $mode->value;
            $user->onboarding_step = OnboardingStep::AskGender->value;
            $user->save();
            $this->askGender($user, $chatId);

            return;
        }

        if ($key === 'gender') {
            $gender = Gender::tryFrom($value);
            if (! $gender) {
                return;
            }
            $user->gender = $gender->value;
            $user->onboarding_step = OnboardingStep::AskAge->value;
            $user->save();
            $this->sendOnboardingAgePrompt($user, $chatId);

            return;
        }

        if ($key === 'activity') {
            $act = ActivityLevel::tryFrom($value);
            if (! $act) {
                return;
            }
            $user->activity_level = $act->value;
            $user->onboarding_step = OnboardingStep::AskGoal->value;
            $user->save();
            $this->askGoal($chatId);

            return;
        }

        if ($key === 'goal') {
            $goal = FitnessGoal::tryFrom($value);
            if (! $goal) {
                return;
            }
            $user->goal = $goal->value;
            $user->onboarding_step = OnboardingStep::AskExperience->value;
            $user->save();
            $this->askExperience($chatId);

            return;
        }

        if ($key === 'exp') {
            $exp = ExperienceLevel::tryFrom($value);
            if (! $exp) {
                return;
            }
            $user->experience = $exp->value;
            $user->onboarding_step = OnboardingStep::AskSleep->value;
            $user->save();
            $this->sendOnboardingSleepPrompt($user, $chatId);

            return;
        }

        if ($key === 'photo' && $value === 'skip') {
            $this->finishOnboardingWithOptionalPhoto($user, $chatId, null);

            return;
        }
    }

    private function handleOnboardingText(User $user, int $chatId, OnboardingStep $step, string $text): void
    {
        match ($step) {
            OnboardingStep::AskWelcome, OnboardingStep::AskPlanChoice => $this->telegram->sendMessage(
                $chatId,
                '👆 Чтобы продолжить, нажми кнопку под предыдущим сообщением.'
            ),
            OnboardingStep::AskAge => $this->onboardingAge($user, $chatId, $text),
            OnboardingStep::AskWeight => $this->onboardingWeight($user, $chatId, $text),
            OnboardingStep::AskHeight => $this->onboardingHeight($user, $chatId, $text),
            OnboardingStep::AskSleep => $this->onboardingSleep($user, $chatId, $text),
            OnboardingStep::AskWaterGoal => $this->onboardingWaterGoal($user, $chatId, $text),
            default => null,
        };
    }

    private function onboardingWeight(User $user, int $chatId, string $text): void
    {
        $w = $this->parseFloat($text);
        if ($w === null || $w < 30 || $w > 300) {
            $this->telegram->sendMessage($chatId, 'Нужно реалистичное значение веса в кг (30–300). Попробуй ещё раз.');

            return;
        }
        $user->weight_kg = $w;
        $user->onboarding_step = OnboardingStep::AskHeight->value;
        $user->save();
        $this->telegram->sendMessage(
            $chatId,
            'Отлично. Теперь <b>рост в см</b> (например 180).',
            $this->onboardingTextPromptKeyboard()
        );
    }

    private function onboardingAge(User $user, int $chatId, string $text): void
    {
        $age = $this->parseInt($text);
        if ($age === null || $age < 14 || $age > 100) {
            $this->telegram->sendMessage($chatId, 'Укажи возраст числом <b>от 14 до 100</b> лет.');

            return;
        }
        $user->age = $age;
        $user->onboarding_step = OnboardingStep::AskWeight->value;
        $user->save();
        $this->telegram->sendMessage(
            $chatId,
            'Принято. Теперь <b>вес в кг</b> (например 75.5).',
            $this->onboardingTextPromptKeyboard()
        );
    }

    private function onboardingHeight(User $user, int $chatId, string $text): void
    {
        $h = $this->parseInt($text);
        if ($h === null || $h < 120 || $h > 230) {
            $this->telegram->sendMessage($chatId, 'Укажи рост в см целым числом (120–230).');

            return;
        }
        $user->height_cm = $h;
        if ($user->isDisciplineOnlyMode()) {
            $user->onboarding_step = OnboardingStep::AskSleep->value;
            $user->save();
            $this->sendOnboardingSleepPrompt($user, $chatId);

            return;
        }
        $user->onboarding_step = OnboardingStep::AskActivity->value;
        $user->save();
        $this->askActivity($chatId);
    }

    private function onboardingSleep(User $user, int $chatId, string $text): void
    {
        $s = $this->parseFloat($text);
        if ($s === null || $s < 4 || $s > 12) {
            $this->telegram->sendMessage($chatId, 'Введи часы сна числом (например 7.5), обычно 4–12.');

            return;
        }
        $user->sleep_target_hours = $s;
        if ($user->isDisciplineOnlyMode()) {
            $user->onboarding_step = OnboardingStep::AskWaterGoal->value;
            $user->save();
            $this->telegram->sendMessage(
                $chatId,
                '💧 Сколько <b>мл воды в день</b> для твоей цели? (например <b>2500</b> или <b>2.5 л</b>) — от этого считается балл за воду в /check.',
                $this->onboardingTextPromptKeyboard()
            );

            return;
        }
        $user->onboarding_step = OnboardingStep::AskBeforePhoto->value;
        $user->save();
        $this->telegram->sendMessage(
            $chatId,
            'Загрузи фото «до» (одно сообщение с фото) или нажми «Пропустить».',
            $this->telegram->inlineKeyboard([
                [['text' => 'Пропустить фото', 'callback_data' => 'onb:photo:skip']],
                [['text' => '◀️ Назад', 'callback_data' => 'onb:back']],
            ])
        );
    }

    private function onboardingWaterGoal(User $user, int $chatId, string $text): void
    {
        $ml = $this->parseWaterMlFromText($text);
        if ($ml === null || $ml < 400 || $ml > 12000) {
            $this->telegram->sendMessage(
                $chatId,
                '💧 Укажи объём в мл или литрах (например 2500 или 2 л), в диапазоне примерно 400–12000 мл.'
            );

            return;
        }
        $user->water_goal_ml = $ml;
        $user->save();
        $this->finishDisciplineOnboarding($user, $chatId);
    }

    private function onboardingGoBack(User $user, int $chatId): void
    {
        $step = $user->onboardingStepEnum();
        if ($step === null || $step === OnboardingStep::AskWelcome) {
            $this->telegram->sendMessage($chatId, 'Это первый шаг — назад некуда.');

            return;
        }

        if ($step === OnboardingStep::AskPlanChoice) {
            $user->plan_mode = null;
            $user->onboarding_step = OnboardingStep::AskWelcome->value;
            $user->save();
            $this->sendWelcomeScreen($chatId);

            return;
        }

        if ($step === OnboardingStep::AskGender) {
            $user->gender = null;
            $user->onboarding_step = OnboardingStep::AskPlanChoice->value;
            $user->save();
            $this->askPlanChoice($chatId);

            return;
        }

        if ($step === OnboardingStep::AskAge) {
            $user->age = null;
            $user->gender = null;
            $user->onboarding_step = OnboardingStep::AskGender->value;
            $user->save();
            $this->askGender($user, $chatId);

            return;
        }

        if ($step === OnboardingStep::AskWeight) {
            $user->weight_kg = null;
            $user->age = null;
            $user->onboarding_step = OnboardingStep::AskAge->value;
            $user->save();
            $this->sendOnboardingAgePrompt($user, $chatId);

            return;
        }

        if ($step === OnboardingStep::AskHeight) {
            $user->height_cm = null;
            $user->weight_kg = null;
            $user->onboarding_step = OnboardingStep::AskWeight->value;
            $user->save();
            $this->telegram->sendMessage(
                $chatId,
                'Введи <b>вес в кг</b> (например 75.5).',
                $this->onboardingTextPromptKeyboard()
            );

            return;
        }

        if ($step === OnboardingStep::AskActivity) {
            $user->activity_level = null;
            $user->height_cm = null;
            $user->onboarding_step = OnboardingStep::AskHeight->value;
            $user->save();
            $this->telegram->sendMessage(
                $chatId,
                'Введи <b>рост в см</b> (например 180).',
                $this->onboardingTextPromptKeyboard()
            );

            return;
        }

        if ($step === OnboardingStep::AskGoal) {
            $user->goal = null;
            $user->activity_level = null;
            $user->onboarding_step = OnboardingStep::AskActivity->value;
            $user->save();
            $this->askActivity($chatId);

            return;
        }

        if ($step === OnboardingStep::AskExperience) {
            $user->experience = null;
            $user->goal = null;
            $user->onboarding_step = OnboardingStep::AskGoal->value;
            $user->save();
            $this->askGoal($chatId);

            return;
        }

        if ($step === OnboardingStep::AskSleep) {
            $user->sleep_target_hours = null;
            if ($user->isDisciplineOnlyMode()) {
                $user->height_cm = null;
                $user->onboarding_step = OnboardingStep::AskHeight->value;
                $user->save();
                $this->telegram->sendMessage(
                    $chatId,
                    'Введи <b>рост в см</b> (например 180).',
                    $this->onboardingTextPromptKeyboard()
                );
            } else {
                $user->experience = null;
                $user->onboarding_step = OnboardingStep::AskExperience->value;
                $user->save();
                $this->askExperience($chatId);
            }

            return;
        }

        if ($step === OnboardingStep::AskWaterGoal) {
            $user->water_goal_ml = null;
            $user->sleep_target_hours = null;
            $user->onboarding_step = OnboardingStep::AskSleep->value;
            $user->save();
            $this->sendOnboardingSleepPrompt($user, $chatId);

            return;
        }

        if ($step === OnboardingStep::AskBeforePhoto) {
            $user->sleep_target_hours = null;
            $user->onboarding_step = OnboardingStep::AskSleep->value;
            $user->save();
            $this->sendOnboardingSleepPrompt($user, $chatId);

            return;
        }
    }

    private function finishDisciplineOnboarding(User $user, int $chatId): void
    {
        $user->daily_calories_target = null;
        $user->protein_g = null;
        $user->fat_g = null;
        $user->carbs_g = null;
        $user->activity_level = null;
        $user->goal = null;
        $user->experience = null;
        $user->onboarding_step = null;
        $user->next_progress_photo_at = Carbon::now()->addDays(30);
        $user->save();

        $this->telegram->sendMessage($chatId, $this->plans->buildPlanMessage($user));
        $this->telegram->sendMessage(
            $chatId,
            '🎉 <b>Готово!</b> Режим без плана FitBot: каждый день <b>/check</b> ✍️, статистика <b>/rating</b> 📊. Калории и меню ведёшь сам — я помогаю не сбиться с дисциплины.'
            ."\n".'Раз в ~30 дней могу напомнить про фото прогресса 📷',
            $this->mainMenuKeyboard()
        );
    }

    private function sendWelcomeScreen(int $chatId): void
    {
        $text = "👋 <b>Привет! Я FitBot</b>\n\n"
            ."Я помогаю не терять нить дисциплины:\n"
            ."• 📝 <b>Чек-ин</b> — питание, сон, движение, вода за день (с баллами)\n"
            ."• 📊 <b>Рейтинг</b> — серия дней и подсказки\n"
            ."• 📋 <b>План</b> — твои цели по сну и воде (или полный план от меня)\n"
            ."• ⏰ Напоминания утром и вечером\n\n"
            .'Нажми <b>«Продолжить»</b>, чтобы выбрать режим и пройти короткую настройку.';

        $this->telegram->sendMessage(
            $chatId,
            $text,
            $this->telegram->inlineKeyboard([
                [['text' => '▶️ Продолжить', 'callback_data' => 'onb:welcome:continue']],
            ])
        );
    }

    private function askPlanChoice(int $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            '🎯 <b>Какой режим тебе подходит?</b>'."\n\n"
            .'• <b>План от FitBot</b> — рассчитаю калории, БЖУ, пример дня и блок про тренировки (дольше анкета).'."\n"
            .'• <b>Свой план</b> — только чек-ины и дисциплина: спрошу сон, воду и базовые данные, без меню и ккал от меня.',
            $this->telegram->inlineKeyboard([
                [['text' => '📊 План от FitBot', 'callback_data' => 'onb:plan:'.UserPlanMode::Full->value]],
                [['text' => '✅ Свой план — только дисциплина', 'callback_data' => 'onb:plan:'.UserPlanMode::Discipline->value]],
                [['text' => '◀️ Назад', 'callback_data' => 'onb:back']],
            ])
        );
    }

    private function askGender(User $user, int $chatId): void
    {
        $intro = $user->isDisciplineOnlyMode()
            ? 'Начнём с <b>пола</b> — так подсказки будут точнее.'
            : 'Начнём с <b>пола</b> — от него зависят расчёт калорий, БЖУ и пример тренировок.';

        $this->telegram->sendMessage(
            $chatId,
            $intro,
            $this->telegram->inlineKeyboard([
                [
                    ['text' => 'Мужской', 'callback_data' => 'onb:gender:'.Gender::Male->value],
                    ['text' => 'Женский', 'callback_data' => 'onb:gender:'.Gender::Female->value],
                ],
                [['text' => '◀️ Назад', 'callback_data' => 'onb:back']],
            ])
        );
    }

    private function askActivity(int $chatId): void
    {
        $lines = ['Какая у тебя <b>повседневная активность</b>? Это влияет на калории.', ''];
        foreach (ActivityLevel::cases() as $a) {
            $lines[] = '• <b>'.$a->labelRu().'</b> — '.$a->descriptionRu();
        }
        $rows = [];
        foreach (ActivityLevel::cases() as $a) {
            $rows[] = [['text' => $a->labelRu(), 'callback_data' => 'onb:activity:'.$a->value]];
        }
        $rows[] = [['text' => '◀️ Назад', 'callback_data' => 'onb:back']];
        $this->telegram->sendMessage(
            $chatId,
            implode("\n", $lines),
            $this->telegram->inlineKeyboard($rows)
        );
    }

    private function askGoal(int $chatId): void
    {
        $rows = [];
        foreach (FitnessGoal::cases() as $g) {
            $rows[] = [['text' => '🎯 '.$g->labelRu(), 'callback_data' => 'onb:goal:'.$g->value]];
        }
        $rows[] = [['text' => '◀️ Назад', 'callback_data' => 'onb:back']];
        $this->telegram->sendMessage(
            $chatId,
            'Какая <b>цель</b>?',
            $this->telegram->inlineKeyboard($rows)
        );
    }

    private function askExperience(int $chatId): void
    {
        $rows = [];
        foreach (ExperienceLevel::cases() as $e) {
            $rows[] = [['text' => $e->labelRu(), 'callback_data' => 'onb:exp:'.$e->value]];
        }
        $rows[] = [['text' => '◀️ Назад', 'callback_data' => 'onb:back']];
        $this->telegram->sendMessage(
            $chatId,
            'Твой <b>опыт</b> тренировок?',
            $this->telegram->inlineKeyboard($rows)
        );
    }

    private function finishOnboardingWithOptionalPhoto(User $user, int $chatId, ?string $fileId): void
    {
        if ($fileId !== null) {
            $user->before_photo_file_id = $fileId;
            Photo::query()->create([
                'user_id' => $user->id,
                'file_id' => $fileId,
                'type' => PhotoType::Before->value,
            ]);
        }

        $user->onboarding_step = null;
        $user->plan_mode = UserPlanMode::Full->value;
        $user->next_progress_photo_at = Carbon::now()->addDays(30);
        $user->save();

        $this->plans->applyBasePlan($user);
        $user->refresh();

        $this->telegram->sendMessage($chatId, $this->plans->buildPlanMessage($user));
        $this->telegram->sendMessage($chatId, FitBotMessaging::onboardingDisciplineIntro());
        $this->telegram->sendMessage($chatId, FitBotMessaging::onboardingFreeVsProHint());
        $this->telegram->sendMessage(
            $chatId,
            '🎉 '.FitBotMessaging::onboardingAfterPlanFooter()
            ."\n\n".'Раз в ~30 дней напомню про фото прогресса 📷',
            $this->mainMenuKeyboard()
        );
    }

    private function handleCheckCallback(User $user, int $chatId, string $data): void
    {
        $parts = explode(':', $data);
        if (count($parts) === 3 && ($parts[2] ?? '') === 'cancel') {
            $cid = (int) ($parts[1] ?? 0);
            if ($cid > 0) {
                $this->tryCancelIncompleteCheck($user, $chatId, $cid);
            }

            return;
        }

        if (count($parts) !== 4) {
            return;
        }
        [, $id, $axis, $ratingVal] = $parts;
        $check = DailyCheck::query()->where('user_id', $user->id)->whereKey((int) $id)->first();
        if (! $check || $check->is_completed) {
            return;
        }

        if ($axis === 'workout') {
            $variant = WorkoutCheckVariant::tryFrom($ratingVal)
                ?? ($ratingVal === 'walk' ? WorkoutCheckVariant::Skipped : null);
            if (! $variant) {
                return;
            }
            $check->workout_variant = $variant->value;
            $check->workout_rating = match ($variant) {
                WorkoutCheckVariant::Trained, WorkoutCheckVariant::Rest => CheckRating::Green->value,
                WorkoutCheckVariant::Skipped => CheckRating::Red->value,
            };
            $check->save();
            $this->finalizeOrContinueCheck($user, $chatId, $check);

            return;
        }

        if ($axis !== 'diet') {
            return;
        }

        $rating = CheckRating::tryFrom($ratingVal);
        if (! $rating) {
            return;
        }

        $check->diet_rating = $rating->value;
        $check->save();
        $this->finalizeOrContinueCheck($user, $chatId, $check);
    }

    private function finalizeOrContinueCheck(User $user, int $chatId, DailyCheck $check): void
    {
        $check->refresh();
        if ($check->diet_rating && $check->sleep_rating && $check->workout_rating && $check->water_rating) {
            $check->is_completed = true;
            $this->rating->recalculateDailyCheck($check);
            $check->save();

            $this->forgetEveningReminderCachesForUser((int) $user->id);

            $priorMax = $this->rating->lastCompletedCheckDateBefore($user, $check->check_date->toDateString());
            $comeback = $priorMax !== null
                && Carbon::parse($priorMax)->startOfDay()->diffInDays($check->check_date->copy()->startOfDay()) >= 2;

            $streak = $this->rating->checkInStreakDays($user);

            $blocks = ['✅ <b>Чек-ин сохранён.</b>'];
            if ($comeback) {
                $blocks[] = FitBotMessaging::comebackHead();
            }
            $blocks[] = FitBotMessaging::completedCheckClosing($check, $this->rating);
            $streakLine = FitBotMessaging::streakCelebrationLine($streak);
            if ($streakLine !== null) {
                $blocks[] = $streakLine;
            }
            $weekPhoto = $this->maybeWeekPhotoNudgeBlock($user);
            if ($weekPhoto !== null) {
                $blocks[] = $weekPhoto;
            }
            $blocks[] = $this->rating->weeklyFocusLine($user);

            $mid = $check->telegram_progress_message_id;
            if ($mid) {
                $this->telegram->deleteMessages($chatId, [(int) $mid]);
            }
            $check->telegram_progress_message_id = null;
            $check->save();

            $this->telegram->sendMessage(
                $chatId,
                implode("\n\n", $blocks),
                $this->mainMenuKeyboard()
            );

            return;
        }

        $this->showCheckProgress($user, $chatId, $check);
    }

    private function tryConsumeCheckQuantityReply(User $user, int $chatId, string $text): bool
    {
        $today = Carbon::today()->toDateString();
        $check = DailyCheck::query()
            ->where('user_id', $user->id)
            ->whereDate('check_date', $today)
            ->where('is_completed', false)
            ->first();

        if (! $check) {
            return false;
        }

        if (! $check->diet_rating) {
            $this->showCheckProgress(
                $user,
                $chatId,
                $check,
                'Сначала выбери <b>питание</b> кнопками ниже (или <b>/cancel</b>).'
            );

            return true;
        }

        if ($check->diet_rating && ! $check->sleep_rating) {
            return $this->applyCheckSleepHoursFromText($user, $chatId, $check, $text);
        }

        if ($check->diet_rating && $check->sleep_rating && ! $check->workout_rating) {
            $this->showCheckProgress(
                $user,
                $chatId,
                $check,
                'Выбери <b>движение</b> кнопками ниже (или <b>/cancel</b>).'
            );

            return true;
        }

        if ($check->diet_rating && $check->sleep_rating && $check->workout_rating && ! $check->water_rating) {
            return $this->applyCheckWaterFromText($user, $chatId, $check, $text);
        }

        return false;
    }

    private function applyCheckSleepHoursFromText(User $user, int $chatId, DailyCheck $check, string $text): bool
    {
        $hours = $this->parseFloat($text);
        if ($hours === null || $hours < 0 || $hours > 16) {
            $this->showCheckProgress(
                $user,
                $chatId,
                $check,
                'Напиши часы сна числом от <b>0</b> до <b>16</b> (например <b>7.5</b>).'
            );

            return true;
        }

        $target = (float) ($user->sleep_target_hours ?? 8.0);
        $rating = $this->rating->sleepRatingFromHours($hours, $target);
        $check->sleep_hours_actual = $hours;
        $check->sleep_rating = $rating->value;
        $check->save();

        $this->finalizeOrContinueCheck($user, $chatId, $check);

        return true;
    }

    private function applyCheckWaterFromText(User $user, int $chatId, DailyCheck $check, string $text): bool
    {
        $ml = $this->parseWaterMlFromText($text);
        if ($ml === null || $ml < 100 || $ml > 20000) {
            $this->showCheckProgress(
                $user,
                $chatId,
                $check,
                'Не разобрал объём. Нужно примерно <b>100–20 000</b> мл. Примеры: <code>2000</code>, <code>1.5 л</code>.'
            );

            return true;
        }

        $goal = (int) ($user->water_goal_ml ?? 2500);
        $rating = $this->rating->waterRatingFromMl($ml, $goal);
        $check->water_ml_actual = $ml;
        $check->water_rating = $rating->value;
        $check->save();

        $this->finalizeOrContinueCheck($user, $chatId, $check);

        return true;
    }

    private function parseWaterMlFromText(string $text): ?int
    {
        $t = Str::lower(str_replace(',', '.', trim($text)));
        if ($t === '') {
            return null;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(л|l)\b/u', $t, $m)) {
            return (int) round((float) $m[1] * 1000);
        }
        if (preg_match('/^(\d+)\s*мл/u', $t, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\d+$/', $t)) {
            $n = (int) $t;

            return $n >= 100 ? $n : (int) round($n * 1000);
        }
        if (preg_match('/^(\d+(?:\.\d+)?)$/', $t, $m)) {
            $f = (float) $m[1];

            return $f <= 12 ? (int) round($f * 1000) : (int) round($f);
        }

        return null;
    }

    private function showCheckProgress(User $user, int $chatId, DailyCheck $check, ?string $inlineError = null): void
    {
        $check->refresh();
        $text = $this->buildCheckProgressText($user, $check, $inlineError);
        $markup = $this->buildCheckProgressReplyMarkup($check);

        $mid = $check->telegram_progress_message_id;
        if ($mid) {
            if ($this->telegram->editMessageText($chatId, (int) $mid, $text, $markup)) {
                return;
            }
        }

        $newId = $this->telegram->sendMessageReturnId($chatId, $text, $markup);
        if ($newId !== null) {
            $check->telegram_progress_message_id = $newId;
            $check->save();
        }
    }

    /** @return list<string> */
    private function checkProgressStatusLines(DailyCheck $check): array
    {
        $lines = [];

        $dr = CheckRating::tryFrom((string) $check->diet_rating);
        $lines[] = ($dr ? '✅' : '⬜').' Питание'
            .($dr ? ': '.$dr->emoji().' '.$dr->labelRu() : '');

        if ($check->sleep_rating) {
            $sr = CheckRating::tryFrom((string) $check->sleep_rating);
            $h = $check->sleep_hours_actual;
            $lines[] = '✅ Сон: '
                .($h !== null ? $h.' ч · ' : '')
                .($sr ? $sr->emoji().' '.$sr->labelRu() : '');
        } else {
            $lines[] = '⬜ Сон';
        }

        if ($check->workout_rating) {
            $wv = WorkoutCheckVariant::tryFrom((string) $check->workout_variant);
            $wr = CheckRating::tryFrom((string) $check->workout_rating);
            $lines[] = '✅ Движение: '
                .($wv !== null ? $wv->labelRu() : '…')
                .($wr !== null ? ' · '.$wr->emoji() : '');
        } else {
            $lines[] = '⬜ Движение';
        }

        if ($check->water_rating) {
            $wr = CheckRating::tryFrom((string) $check->water_rating);
            $ml = $check->water_ml_actual;
            $lines[] = '✅ Вода: '
                .($ml !== null ? $ml.' мл · ' : '')
                .($wr !== null ? $wr->emoji().' '.$wr->labelRu() : '');
        } else {
            $lines[] = '⬜ Вода';
        }

        return $lines;
    }

    private function buildCheckProgressText(User $user, DailyCheck $check, ?string $error = null): string
    {
        $parts = [];
        if ($error !== null && $error !== '') {
            $parts[] = '⚠️ '.$error;
            $parts[] = '';
        }
        $parts[] = '<b>Чек-ин за сегодня</b>';
        $parts = array_merge($parts, $this->checkProgressStatusLines($check));
        $parts[] = '';
        $parts[] = $this->checkProgressCurrentQuestionText($user, $check);

        return implode("\n", $parts);
    }

    private function checkProgressCurrentQuestionText(User $user, DailyCheck $check): string
    {
        if (! $check->diet_rating) {
            return '🍽 <b>Питание сегодня?</b> Как получилось держать план?'."\n\n"
                .'<i>/cancel или кнопка внизу — прервать чек.</i>';
        }

        if (! $check->sleep_rating) {
            $target = $user->sleep_target_hours ?? 8;

            return '😴 <b>Сон прошлой ночью</b>'."\n\n"
                .'Сколько часов реально спал? Напиши <b>одно число</b> в чат (например <b>7.5</b>).'."\n"
                .'🎯 Цель из анкеты: <b>'.$target.'</b> ч.'."\n"
                .'Диапазон: <b>0–16</b> ч. Можно запятой: <code>7,5</code>'."\n\n"
                .'<i>Застрял — /cancel или кнопка ниже.</i>';
        }

        if (! $check->workout_rating) {
            return '💪 <b>Движение сегодня</b>'."\n\n".'<i>Отмена чек-ина — кнопка внизу.</i>';
        }

        if (! $check->water_rating) {
            $goal = (int) ($user->water_goal_ml ?? 2500);

            return '💧 <b>Вода за день</b>'."\n\n"
                .'Сколько примерно выпил? Если не считал — оцени на глаз.'."\n"
                .'Примеры: <code>2000</code>, <code>1.5 л</code>, <code>2500 мл</code>'."\n"
                .'🎯 Цель: <b>'.$goal.'</b> мл · допустимо примерно <b>100–20 000</b> мл'."\n\n"
                .'<i>/cancel — выйти из чек-ина.</i>';
        }

        return '';
    }

    private function buildCheckProgressReplyMarkup(DailyCheck $check): array
    {
        $id = (int) $check->id;
        if (! $check->diet_rating) {
            return $this->dietRatingKeyboard($id);
        }
        if (! $check->sleep_rating) {
            return $this->checkCancelMarkup($id);
        }
        if (! $check->workout_rating) {
            return $this->workoutVariantKeyboard($id);
        }
        if (! $check->water_rating) {
            return $this->checkCancelMarkup($id);
        }

        return $this->checkCancelMarkup($id);
    }

    /** Одна строка inline: отменить незавершённый чек-ин. */
    private function checkCancelMarkup(int $checkId): array
    {
        return $this->telegram->inlineKeyboard([
            [['text' => '❌ Отменить чек-ин', 'callback_data' => 'chk:'.$checkId.':cancel']],
        ]);
    }

    private function dietRatingKeyboard(int $checkId): array
    {
        $prefix = 'chk:'.$checkId.':diet:';

        return $this->telegram->inlineKeyboard([
            [
                ['text' => CheckRating::Green->emoji().' идеально', 'callback_data' => $prefix.CheckRating::Green->value],
                ['text' => CheckRating::Yellow->emoji().' нормально', 'callback_data' => $prefix.CheckRating::Yellow->value],
                ['text' => CheckRating::Red->emoji().' плохо', 'callback_data' => $prefix.CheckRating::Red->value],
            ],
            [['text' => '❌ Отменить чек-ин', 'callback_data' => 'chk:'.$checkId.':cancel']],
        ]);
    }

    private function workoutVariantKeyboard(int $checkId): array
    {
        $p = 'chk:'.$checkId.':workout:';

        return $this->telegram->inlineKeyboard([
            [['text' => '💪 Позанимался', 'callback_data' => $p.WorkoutCheckVariant::Trained->value]],
            [['text' => '😴 День отдыха', 'callback_data' => $p.WorkoutCheckVariant::Rest->value]],
            [['text' => '❌ Прогулял (пропустил тренировку)', 'callback_data' => $p.WorkoutCheckVariant::Skipped->value]],
            [['text' => '❌ Отменить чек-ин', 'callback_data' => 'chk:'.$checkId.':cancel']],
        ]);
    }

    private function forgetEveningReminderCachesForUser(int $userId): void
    {
        $today = now()->toDateString();
        Cache::forget("fitbot:evening_soft:{$userId}:{$today}");
        Cache::forget("fitbot:evening_strict:{$userId}:{$today}");
    }

    /** Одноразовый блок через ~7 дней в боте (не путать с напоминанием раз в 30 дней). */
    private function maybeWeekPhotoNudgeBlock(User $user): ?string
    {
        $key = 'fitbot:week7_photo_hint:'.$user->id;
        if (Cache::has($key)) {
            return null;
        }
        if (FitBotMessaging::dayNumberInBot($user, now()) < 7) {
            return null;
        }
        Cache::put($key, true, now()->addDays(365));

        return FitBotMessaging::weekPhotoEncouragement();
    }

    private function syncUser(int $telegramId, array $from): User
    {
        $user = User::query()->firstOrNew(['telegram_id' => $telegramId]);
        if (! $user->exists) {
            $user->onboarding_step = OnboardingStep::AskWelcome->value;
        }
        $user->username = $from['username'] ?? null;
        $user->first_name = $from['first_name'] ?? null;
        $user->last_name = $from['last_name'] ?? null;
        if (! $user->exists || $user->isDirty()) {
            $user->save();
        }

        return $user;
    }

    private function maybeRemindProgressPhoto(User $user, int $chatId): void
    {
        if (! $user->hasCompletedOnboarding()) {
            return;
        }
        $due = $user->next_progress_photo_at;
        if ($due === null || $due->isFuture()) {
            return;
        }

        $cacheKey = 'fitbot:progress_photo_prompt:'.$user->id.':'.Carbon::today()->toDateString();
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->endOfDay());

        $this->telegram->sendMessage(
            $chatId,
            'Пора обновить <b>фото прогресса</b> (раз в 30 дней). Пришли одно фото сообщением.'
            ."\n\n"
            .'Фото не спорит — даже когда голова ищет отмазку.',
            $this->mainMenuKeyboard()
        );
    }

    /** @param array<int, array<string, mixed>> $photoSizes */
    private function saveProgressPhoto(User $user, int $chatId, array $photoSizes): void
    {
        $fileId = (string) $photoSizes[array_key_last($photoSizes)]['file_id'];
        Photo::query()->create([
            'user_id' => $user->id,
            'file_id' => $fileId,
            'type' => PhotoType::Progress->value,
        ]);
        $user->next_progress_photo_at = Carbon::now()->addDays(30);
        $user->save();
        $this->telegram->sendMessage(
            $chatId,
            'Фото прогресса сохранено. Следующее напоминание через 30 дней.',
            $this->mainMenuKeyboard()
        );
    }

    private function parseFloat(string $text): ?float
    {
        $normalized = str_replace(',', '.', trim($text));
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function parseInt(string $text): ?int
    {
        $normalized = trim($text);
        if (! preg_match('/^\d+$/', $normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    /** BOM, полноширинный «/» (U+FF0F) и похожие — Telegram/клавиатуры иногда шлют не ASCII-слэш. */
    private function normalizeMessageText(string $text): string
    {
        $text = preg_replace('/^\x{FEFF}/u', '', $text);
        $text = trim($text);
        if ($text === '') {
            return $text;
        }
        $first = mb_substr($text, 0, 1);
        if (in_array($first, ['／', '⁄', '∕'], true)) {
            $text = '/'.mb_substr($text, 1);
        }

        return $text;
    }

    /** Telegram шлёт команды как /settings@BotName — убираем суффикс бота. */
    private function normalizeBotCommand(string $text): string
    {
        $token = Str::lower(Str::before(trim($text), ' '));

        return str_contains($token, '@') ? Str::before($token, '@') : $token;
    }

    private function isPlainSettingsRequest(string $text): bool
    {
        $plain = Str::lower(trim($text));

        return in_array($plain, [
            'settings',
            'setting',
            'сеттингс',
            'настройки',
            'опции',
        ], true);
    }

    /** Тексты с нижней (reply) клавиатуры главного меню. */
    private function replyMenuAction(string $text): ?string
    {
        return match (trim($text)) {
            'Чек-ин' => 'check',
            'Рейтинг' => 'rating',
            '📋 План', 'План' => 'plan',
            '⚙️ Настройки', 'Настройки' => 'settings',
            '📈 Расширенная аналитика' => 'analytics',
            '👉 Персональный план (AI)' => 'plan_ai',
            default => null,
        };
    }
}
