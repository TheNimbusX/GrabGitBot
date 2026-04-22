<?php

namespace App\Services\FitBot;

use App\Enums\ActivityLevel;
use App\Enums\CheckRating;
use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\OnboardingStep;
use App\Enums\PhotoType;
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

    private function handleMessage(array $msg): void
    {
        $chatId = (int) $msg['chat']['id'];
        $from = $msg['from'] ?? [];
        $telegramId = (int) ($from['id'] ?? 0);

        if ($telegramId === 0) {
            return;
        }

        $user = $this->syncUser($telegramId, $from);

        $this->maybeRemindProgressPhoto($user, $chatId);

        $text = trim((string) ($msg['text'] ?? ''));
        if ($text !== '' && Str::startsWith($text, '/')) {
            $command = $this->normalizeBotCommand($text);
            match ($command) {
                '/start' => $this->cmdStart($user, $chatId),
                '/check' => $this->cmdCheck($user, $chatId),
                '/rating' => $this->cmdRating($user, $chatId),
                '/settings' => $this->cmdSettings($user, $chatId),
                default => $this->telegram->sendMessage(
                    $chatId,
                    'Неизвестная команда. Команды: /start, /check, /rating, /settings'
                ),
            };

            return;
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
                'Выбери действие в меню или команды: /check, /rating, /settings',
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

        if (str_starts_with($data, 'set:')) {
            $this->handleSettingsCallback($user, $chatId, $data);

            return;
        }

        if ($data === 'pay:ai') {
            $this->telegram->sendMessage($chatId, 'Персональный план (AI) доступен в платной версии. Скоро добавим оплату и генерацию программы.');

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
                'Снова привет! Я FitBot — держим питание, сон, тренировки и воду под контролем.',
                $this->mainMenuKeyboard()
            );

            return;
        }

        match ($user->onboardingStepEnum()) {
            OnboardingStep::AskGender => $this->askGender($chatId),
            OnboardingStep::AskAge => $this->telegram->sendMessage(
                $chatId,
                'Сколько тебе <b>полных лет</b>? (число, например 27) — учту в расчёте калорий.'
            ),
            OnboardingStep::AskWeight => $this->telegram->sendMessage(
                $chatId,
                'Введи <b>вес в кг</b> (например 75.5).'
            ),
            OnboardingStep::AskHeight => $this->telegram->sendMessage(
                $chatId,
                'Введи <b>рост в см</b> (например 180).'
            ),
            OnboardingStep::AskActivity => $this->askActivity($chatId),
            OnboardingStep::AskGoal => $this->askGoal($chatId),
            OnboardingStep::AskExperience => $this->askExperience($chatId),
            OnboardingStep::AskSleep => $this->telegram->sendMessage(
                $chatId,
                'Сколько часов сна в цель? Введи число (например <b>7.5</b>).'
            ),
            OnboardingStep::AskBeforePhoto => $this->telegram->sendMessage(
                $chatId,
                'Пришли фото «до» или нажми «Пропустить».',
                $this->telegram->inlineKeyboard([
                    [['text' => 'Пропустить фото', 'callback_data' => 'onb:photo:skip']],
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
                'Сегодняшний чек-ин уже заполнен. Завтра снова жду /check'
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

        $this->sendNextCheckQuestion($user, $chatId, $check);
    }

    private function cmdRating(User $user, int $chatId): void
    {
        if (! $user->hasCompletedOnboarding()) {
            $this->telegram->sendMessage($chatId, 'Сначала завершите онбординг: /start');

            return;
        }

        $text = $this->rating->formatSummaryMessage($user);
        $this->telegram->sendMessage($chatId, $text, $this->mainMenuKeyboard(), null);
    }

    private function cmdSettings(User $user, int $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            '<b>Настройки</b>'."\n\n"
            .'• <b>Сменить анкету</b> — заново пройти вопросы (пол, возраст, вес…). История чек-инов сохранится, цели пересчитаются после завершения.'."\n"
            .'• <b>Удалить аккаунт</b> — сотрутся все чек-ины, фото и цели. Потом можно снова /start.',
            $this->settingsMenuKeyboard()
        );
    }

    /** @return array<string, mixed> */
    private function mainMenuKeyboard(): array
    {
        return $this->telegram->inlineKeyboard([
            [
                ['text' => 'Чек-ин', 'callback_data' => 'menu:check'],
                ['text' => 'Рейтинг', 'callback_data' => 'menu:rating'],
            ],
            [
                ['text' => '⚙️ Настройки', 'callback_data' => 'menu:settings'],
            ],
            [
                ['text' => '👉 Персональный план (AI)', 'callback_data' => 'pay:ai'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function settingsMenuKeyboard(): array
    {
        return $this->telegram->inlineKeyboard([
            [['text' => '✏️ Сменить анкету', 'callback_data' => 'set:edit']],
            [['text' => '🗑 Удалить аккаунт', 'callback_data' => 'set:del:s']],
        ]);
    }

    private function handleSettingsCallback(User $user, int $chatId, string $data): void
    {
        if ($data === 'set:edit') {
            Cache::forget($this->deleteAccountCacheKey($user->telegram_id));
            $this->resetProfileForReonboarding($user);
            $this->telegram->sendMessage(
                $chatId,
                'Ок, начинаем анкету заново. История <b>чек-инов</b> остаётся; после нового онбординга пересчитаю калории и БЖУ.'
            );
            $this->askGender($chatId);

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
                $user->delete();
                $this->telegram->sendMessage(
                    $chatId,
                    'Аккаунт удалён. Чтобы начать с чистого листа, отправь /start.'
                );

                return;
            }
        }
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
        $user->onboarding_step = OnboardingStep::AskGender->value;
        $user->save();
    }

    private function handleOnboardingCallback(User $user, int $chatId, string $data): void
    {
        $parts = explode(':', $data, 3);
        if (count($parts) < 3) {
            return;
        }
        [, $key, $value] = $parts;

        if ($key === 'gender') {
            $gender = Gender::tryFrom($value);
            if (! $gender) {
                return;
            }
            $user->gender = $gender->value;
            $user->onboarding_step = OnboardingStep::AskAge->value;
            $user->save();
            $this->telegram->sendMessage(
                $chatId,
                'Сколько тебе <b>полных лет</b>? (число, например 27) — учту в расчёте калорий.'
            );

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
            $this->telegram->sendMessage(
                $chatId,
                'Сколько часов сна хочешь держать в цель? Введи число (например <b>7.5</b>).'
            );

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
            OnboardingStep::AskAge => $this->onboardingAge($user, $chatId, $text),
            OnboardingStep::AskWeight => $this->onboardingWeight($user, $chatId, $text),
            OnboardingStep::AskHeight => $this->onboardingHeight($user, $chatId, $text),
            OnboardingStep::AskSleep => $this->onboardingSleep($user, $chatId, $text),
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
        $this->telegram->sendMessage($chatId, 'Отлично. Теперь <b>рост в см</b> (например 180).');
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
        $this->telegram->sendMessage($chatId, 'Принято. Теперь <b>вес в кг</b> (например 75.5).');
    }

    private function onboardingHeight(User $user, int $chatId, string $text): void
    {
        $h = $this->parseInt($text);
        if ($h === null || $h < 120 || $h > 230) {
            $this->telegram->sendMessage($chatId, 'Укажи рост в см целым числом (120–230).');

            return;
        }
        $user->height_cm = $h;
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
        $user->onboarding_step = OnboardingStep::AskBeforePhoto->value;
        $user->save();
        $this->telegram->sendMessage(
            $chatId,
            'Загрузи фото «до» (одно сообщение с фото) или нажми «Пропустить».',
            $this->telegram->inlineKeyboard([
                [['text' => 'Пропустить фото', 'callback_data' => 'onb:photo:skip']],
            ])
        );
    }

    private function askGender(int $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            'Привет! Начнём с <b>пола</b> — от него зависят расчёт калорий, БЖУ и пример тренировок.',
            $this->telegram->inlineKeyboard([
                [['text' => 'Мужской', 'callback_data' => 'onb:gender:'.Gender::Male->value]],
                [['text' => 'Женский', 'callback_data' => 'onb:gender:'.Gender::Female->value]],
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
        $user->next_progress_photo_at = Carbon::now()->addDays(30);
        $user->save();

        $this->plans->applyBasePlan($user);
        $user->refresh();

        $this->telegram->sendMessage($chatId, $this->plans->buildPlanMessage($user));
        $this->telegram->sendMessage(
            $chatId,
            'Готово! Каждый день отмечай /check, смотри /rating. Раз в ~30 дней напомню про фото прогресса.',
            $this->mainMenuKeyboard()
        );
    }

    private function handleCheckCallback(User $user, int $chatId, string $data): void
    {
        $parts = explode(':', $data);
        if (count($parts) !== 4) {
            return;
        }
        [, $id, $axis, $ratingVal] = $parts;
        $check = DailyCheck::query()->where('user_id', $user->id)->whereKey((int) $id)->first();
        if (! $check || $check->is_completed) {
            return;
        }

        $rating = CheckRating::tryFrom($ratingVal);
        if (! $rating) {
            return;
        }

        match ($axis) {
            'diet' => $check->diet_rating = $rating->value,
            'sleep' => $check->sleep_rating = $rating->value,
            'workout' => $check->workout_rating = $rating->value,
            'water' => $check->water_rating = $rating->value,
            default => null,
        };

        $check->save();

        if ($check->diet_rating && $check->sleep_rating && $check->workout_rating && $check->water_rating) {
            $check->is_completed = true;
            $this->rating->recalculateDailyCheck($check);
            $check->save();
            $this->telegram->sendMessage(
                $chatId,
                'Чек-ин сохранён! Сегодня: <b>'.$check->total_score.'</b> / '.RatingService::MAX_DAILY_POINTS.' баллов.',
                $this->mainMenuKeyboard()
            );

            return;
        }

        $this->sendNextCheckQuestion($user, $chatId, $check);
    }

    private function sendNextCheckQuestion(User $user, int $chatId, DailyCheck $check): void
    {
        $axis = null;
        $label = '';
        if (! $check->diet_rating) {
            $axis = 'diet';
            $label = 'Питание сегодня?';
        } elseif (! $check->sleep_rating) {
            $axis = 'sleep';
            $label = 'Сон вчера?';
        } elseif (! $check->workout_rating) {
            $axis = 'workout';
            $label = 'Тренировка сегодня?';
        } elseif (! $check->water_rating) {
            $axis = 'water';
            $label = 'Вода сегодня?';
        }

        if ($axis === null) {
            return;
        }

        $keyboard = $this->ratingKeyboard((int) $check->id, $axis);
        $this->telegram->sendMessage($chatId, $label, $keyboard);
    }

    private function ratingKeyboard(int $checkId, string $axis): array
    {
        $prefix = 'chk:'.$checkId.':'.$axis.':';

        return $this->telegram->inlineKeyboard([
            [
                ['text' => CheckRating::Green->emoji().' идеально', 'callback_data' => $prefix.CheckRating::Green->value],
                ['text' => CheckRating::Yellow->emoji().' нормально', 'callback_data' => $prefix.CheckRating::Yellow->value],
                ['text' => CheckRating::Red->emoji().' плохо', 'callback_data' => $prefix.CheckRating::Red->value],
            ],
        ]);
    }

    private function syncUser(int $telegramId, array $from): User
    {
        $user = User::query()->firstOrNew(['telegram_id' => $telegramId]);
        if (! $user->exists) {
            $user->onboarding_step = OnboardingStep::AskGender->value;
        }
        $user->username = $from['username'] ?? null;
        $user->first_name = $from['first_name'] ?? null;
        $user->last_name = $from['last_name'] ?? null;
        $user->save();

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
        $this->telegram->sendMessage($chatId, 'Фото прогресса сохранено. Следующее напоминание через 30 дней.');
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

    /** Telegram шлёт команды как /settings@BotName — убираем суффикс бота. */
    private function normalizeBotCommand(string $text): string
    {
        $token = strtolower(Str::before(trim($text), ' '));

        return str_contains($token, '@') ? Str::before($token, '@') : $token;
    }
}
