<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('fitbot_club_until')->nullable()->after('recovery_mode_started_at');
            $table->boolean('fitbot_club_founder')->default(false)->after('fitbot_club_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fitbot_club_until', 'fitbot_club_founder']);
        });
    }
};
