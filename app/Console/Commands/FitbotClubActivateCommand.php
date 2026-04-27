<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FitbotClubActivateCommand extends Command
{
    protected $signature = 'fitbot:club-activate {telegram_id : Telegram ID пользователя} {--days=30 : Срок клуба в днях} {--founder : Отметить founder price}';

    protected $description = 'Активирует FitBot Club пользователю после ручной оплаты';

    public function handle(): int
    {
        $telegramId = (int) $this->argument('telegram_id');
        $days = max(1, (int) $this->option('days'));

        $user = User::query()->where('telegram_id', $telegramId)->first();
        if ($user === null) {
            $this->error('Пользователь не найден');

            return self::FAILURE;
        }

        $base = $user->fitbot_club_until !== null && $user->fitbot_club_until->isFuture()
            ? $user->fitbot_club_until->copy()
            : Carbon::now();
        $user->fitbot_club_until = $base->addDays($days);
        if ((bool) $this->option('founder')) {
            $user->fitbot_club_founder = true;
        }
        $user->save();

        $this->info('FitBot Club активен до '.$user->fitbot_club_until->format('Y-m-d H:i:s'));

        return self::SUCCESS;
    }
}
