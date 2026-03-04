<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('infinitypay_charge_id')->nullable()->unique()->index();
            $table->text('payment_url')->nullable();
            $table->string('activation_token', 80)->nullable()->unique()->index();
            $table->enum('status', ['pending', 'paid', 'activated', 'expired'])->default('pending');
            $table->timestamp('expires_at')->nullable(); // expiração do link de ativação (48h após pagamento)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
