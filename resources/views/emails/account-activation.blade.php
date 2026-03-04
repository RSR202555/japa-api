<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ative sua conta — Japa Treinador</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f4f5; margin: 0; padding: 40px 20px; }
    .container { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.12); }
    .header { background: #16a34a; padding: 32px 40px; text-align: center; }
    .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; }
    .body { padding: 40px; }
    .body p { color: #374151; font-size: 15px; line-height: 1.6; margin: 0 0 16px; }
    .btn { display: inline-block; background: #16a34a; color: #fff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px; margin: 8px 0 24px; }
    .note { font-size: 13px; color: #6b7280; }
    .url-box { background: #f4f4f5; border-radius: 6px; padding: 12px 16px; font-size: 12px; color: #374151; word-break: break-all; margin-top: 16px; }
    .footer { padding: 24px 40px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Japa Treinador</h1>
    </div>
    <div class="body">
      <p>Olá, <strong>{{ $pending->name }}</strong>!</p>
      <p>
        Seu pagamento foi confirmado. Agora é só definir sua senha para
        ativar sua conta e começar sua jornada com o <strong>{{ $pending->plan->name }}</strong>.
      </p>
      <p style="text-align:center;">
        <a href="{{ $activationUrl }}" class="btn">Ativar minha conta</a>
      </p>
      <p class="note">
        Este link é válido por <strong>48 horas</strong>. Após esse prazo, entre em contato
        com nosso suporte para reenviar o e-mail de ativação.
      </p>
      <p class="note">Se você não realizou essa compra, ignore este e-mail.</p>
      <div class="url-box">
        Ou copie e cole este link no navegador:<br>
        {{ $activationUrl }}
      </div>
    </div>
    <div class="footer">
      © {{ date('Y') }} Japa Treinador · Todos os direitos reservados
    </div>
  </div>
</body>
</html>
