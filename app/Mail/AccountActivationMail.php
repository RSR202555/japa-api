<?php

namespace App\Mail;

use App\Models\PendingRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $activationUrl;

    public function __construct(
        public PendingRegistration $pending,
        string $token,
    ) {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://japatreinador.com.br')), '/');
        $this->activationUrl = "{$frontendUrl}/ativar-conta/{$token}";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ative sua conta — Japa Treinador',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-activation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
