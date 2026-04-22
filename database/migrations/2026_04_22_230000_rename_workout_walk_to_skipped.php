<?php

use App\Models\DailyCheck;
use App\Services\RatingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('daily_checks')->where('workout_variant', 'walk')->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('daily_checks')->whereIn('id', $ids)->update([
            'workout_variant' => 'skipped',
            'workout_rating' => 'red',
        ]);

        $rating = app(RatingService::class);
        DailyCheck::query()->whereIn('id', $ids)->where('is_completed', true)->chunkById(100, function ($checks) use ($rating) {
            foreach ($checks as $check) {
                $rating->recalculateDailyCheck($check);
                $check->save();
            }
        });
    }

    public function down(): void
    {
        // Не откатываем: новые «skipped» не отличить от бывших «walk».
    }
};
