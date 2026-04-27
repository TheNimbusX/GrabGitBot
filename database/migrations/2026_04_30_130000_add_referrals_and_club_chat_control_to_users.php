<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('referred_by_user_id')->nullable()->after('fitbot_club_founder')->constrained('users')->nullOnDelete();
            $table->timestamp('referral_rewarded_at')->nullable()->after('referred_by_user_id');
            $table->timestamp('fitbot_club_chat_removed_at')->nullable()->after('referral_rewarded_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_user_id');
            $table->dropColumn(['referral_rewarded_at', 'fitbot_club_chat_removed_at']);
        });
    }
};
