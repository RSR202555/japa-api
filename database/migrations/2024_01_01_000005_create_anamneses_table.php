<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anamneses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->decimal('weight', 5, 2);
            $table->decimal('height', 4, 2);
            $table->decimal('body_fat_percentage', 5, 2)->nullable();
            $table->enum('objective', ['weight_loss', 'muscle_gain', 'health', 'endurance', 'maintenance']);
            $table->enum('physical_activity_level', ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extremely_active']);
            $table->json('health_conditions')->nullable();
            $table->text('medications')->nullable();
            $table->json('food_restrictions')->nullable();
            $table->json('food_preferences')->nullable();
            $table->tinyInteger('meals_per_day');
            $table->decimal('water_intake_liters', 4, 2)->nullable();
            $table->decimal('sleep_hours', 4, 2)->nullable();
            $table->tinyInteger('stress_level')->nullable();
            $table->text('additional_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anamneses');
    }
};
