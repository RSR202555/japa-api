<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('cloudinary_public_id');
            $table->string('image_url');
            $table->string('thumbnail_url')->nullable();
            $table->enum('angle', ['front', 'back', 'side_left', 'side_right']);
            $table->decimal('weight_at_photo', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('taken_at');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_photos');
    }
};
