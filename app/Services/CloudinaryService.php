<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private string $uploadPreset;
    private int $maxFileSize;

    public function __construct()
    {
        $this->cloudName    = config('cloudinary.cloud_name');
        $this->apiKey       = config('cloudinary.api_key');
        $this->apiSecret    = config('cloudinary.api_secret');
        $this->uploadPreset = config('cloudinary.upload_preset', 'japa_treinador_secure');
        $this->maxFileSize  = config('cloudinary.max_file_size', 5242880);
    }

    /**
     * Upload de foto de evolução.
     * Pasta separada por usuário para isolamento.
     */
    public function uploadProgressPhoto(UploadedFile $file, int $userId): array
    {
        return $this->upload($file, [
            'folder'          => "japa_treinador/progress_photos/user_{$userId}",
            'transformation'  => [
                ['width' => 1200, 'height' => 1600, 'crop' => 'limit', 'quality' => 'auto'],
            ],
            'eager'           => [
                ['width' => 300, 'height' => 400, 'crop' => 'fill', 'gravity' => 'auto'],
            ],
        ]);
    }

    /**
     * Upload de avatar.
     */
    public function uploadAvatar(UploadedFile $file, int $userId): array
    {
        return $this->upload($file, [
            'folder'         => "japa_treinador/avatars/user_{$userId}",
            'transformation' => [
                ['width' => 400, 'height' => 400, 'crop' => 'fill', 'gravity' => 'face', 'quality' => 'auto'],
            ],
            'overwrite'      => true,
            'public_id'      => "avatar_{$userId}",
        ]);
    }

    /**
     * Remove imagem do Cloudinary.
     */
    public function delete(string $publicId): bool
    {
        try {
            $timestamp = time();
            $signature = $this->generateSignature([
                'public_id' => $publicId,
                'timestamp' => $timestamp,
            ]);

            $response = \Illuminate\Support\Facades\Http::post(
                "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy",
                [
                    'public_id' => $publicId,
                    'timestamp' => $timestamp,
                    'api_key'   => $this->apiKey,
                    'signature' => $signature,
                ]
            );

            return $response->json('result') === 'ok';
        } catch (\Throwable $e) {
            Log::error('Erro ao deletar imagem do Cloudinary', [
                'public_id' => $publicId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Upload genérico — assina a requisição via API Secret (nunca exposto ao frontend).
     */
    private function upload(UploadedFile $file, array $options = []): array
    {
        // Validação extra de segurança
        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'])) {
            throw new \InvalidArgumentException('Tipo de arquivo não permitido.');
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('Arquivo excede o tamanho máximo permitido.');
        }

        $timestamp = time();
        $params    = array_merge($options, ['timestamp' => $timestamp]);
        $signature = $this->generateSignature($params);

        $response = \Illuminate\Support\Facades\Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post(
            "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload",
            array_merge($params, [
                'api_key'   => $this->apiKey,
                'signature' => $signature,
            ])
        );

        if (! $response->successful()) {
            Log::error('Falha no upload Cloudinary', ['response' => $response->json()]);
            throw new \RuntimeException('Falha ao fazer upload da imagem.');
        }

        $data = $response->json();

        return [
            'public_id'     => $data['public_id'],
            'secure_url'    => $data['secure_url'],
            'thumbnail_url' => $data['eager'][0]['secure_url'] ?? $data['secure_url'],
            'width'         => $data['width'],
            'height'        => $data['height'],
        ];
    }

    /**
     * Gera assinatura HMAC-SHA1 para autenticar upload.
     * O api_secret NUNCA é enviado ao frontend.
     */
    private function generateSignature(array $params): string
    {
        ksort($params);
        $str = http_build_query($params);
        return sha1($str . $this->apiSecret);
    }
}
