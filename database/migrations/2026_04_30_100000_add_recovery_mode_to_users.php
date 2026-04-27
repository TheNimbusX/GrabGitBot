<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('recovery_mode_until')->nullable()->after('last_message_to_bot_at');
            $table->timestamp('recovery_mode_started_at')->nullable()->after('recovery_mode_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['recovery_mode_until', 'recovery_mode_started_at']);
        });
    }
};
