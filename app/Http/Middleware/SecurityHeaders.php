<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Headers de segurança aplicados em TODAS as respostas.
     * Protege contra XSS, Clickjacking, MIME sniffing, etc.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Impede que a página seja carregada dentro de iframes (Clickjacking)
        $response->headers->set('X-Frame-Options', 'DENY');

        // Impede MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Ativa proteção XSS do browser (legacy, mas mantido para compatibilidade)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer Policy — não vaza URL da página anterior
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy — desabilita funcionalidades desnecessárias
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Strict Transport Security — força HTTPS por 1 ano
        if (app()->isProduction()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Content Security Policy — apenas para respostas HTML
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html')) {
            $response->headers->set('Content-Security-Policy', $this->buildCSP());
        }

        // Remove header que revela tecnologia usada
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    private function buildCSP(): string
    {
        $policies = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https://res.cloudinary.com",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests",
        ];

        return implode('; ', $policies);
    }
}
