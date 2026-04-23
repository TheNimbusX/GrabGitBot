<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_weight_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight_kg', 5, 2);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('starting_weight_kg', 5, 2)->nullable()->after('weight_kg');
            $table->boolean('notify_weekly_weight_reminder')->default(true)->after('notify_weekly_focus_reminder');
        });

        User::query()
            ->whereNotNull('weight_kg')
            ->whereNull('starting_weight_kg')
            ->each(function (User $user): void {
                $user->starting_weight_kg = $user->weight_kg;
                $user->save();
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['starting_weight_kg', 'notify_weekly_weight_reminder']);
        });

        Schema::dropIfExists('user_weight_logs');
    }
};
