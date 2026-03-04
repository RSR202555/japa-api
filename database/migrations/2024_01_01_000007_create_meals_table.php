<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('meal_time', ['breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'supper']);
            $table->json('foods');
            $table->decimal('total_calories', 8, 2)->nullable();
            $table->decimal('total_protein', 7, 2)->nullable();
            $table->decimal('total_carbs', 7, 2)->nullable();
            $table->decimal('total_fat', 7, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('logged_at');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
