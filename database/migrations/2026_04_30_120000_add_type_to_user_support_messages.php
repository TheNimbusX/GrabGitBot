<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_support_messages', function (Blueprint $table) {
            $table->string('type', 24)->default('support')->after('telegram_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_support_messages', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
