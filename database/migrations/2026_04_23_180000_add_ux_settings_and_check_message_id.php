<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_checks', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_progress_message_id')->nullable()->after('is_completed');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('weekly_focus_note', 255)->nullable()->after('next_progress_photo_at');
            $table->boolean('notify_morning')->default(true)->after('weekly_focus_note');
            $table->boolean('notify_evening')->default(true)->after('notify_morning');
            $table->boolean('notify_churn')->default(true)->after('notify_evening');
            $table->boolean('notify_quiet_enabled')->default(true)->after('notify_churn');
            $table->string('quiet_hours_start', 5)->default('22:00')->after('notify_quiet_enabled');
            $table->string('quiet_hours_end', 5)->default('08:00')->after('quiet_hours_start');
        });
    }

    public function down(): void
    {
        Schema::table('daily_checks', function (Blueprint $table) {
            $table->dropColumn('telegram_progress_message_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'weekly_focus_note',
                'notify_morning',
                'notify_evening',
                'notify_churn',
                'notify_quiet_enabled',
                'quiet_hours_start',
                'quiet_hours_end',
            ]);
        });
    }
};
