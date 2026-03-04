<?php

namespace App\Http\Controllers\Upload;

use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\ImageUploadRequest;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageUploadController extends Controller
{
    public function __construct(private CloudinaryService $cloudinary) {}

    /**
     * Upload de avatar do usuário via backend.
     * O frontend NUNCA envia direto ao Cloudinary.
     */
    public function avatar(ImageUploadRequest $request): JsonResponse
    {
        $user   = $request->user();
        $result = $this->cloudinary->uploadAvatar($request->file('image'), $user->id);

        // Remove avatar antigo do Cloudinary, se existir
        if ($user->avatar_cloudinary_id) {
            $this->cloudinary->delete($user->avatar_cloudinary_id);
        }

        $user->update([
            'avatar_url'            => $result['secure_url'],
            'avatar_cloudinary_id'  => $result['public_id'],
        ]);

        return response()->json([
            'message'    => 'Avatar atualizado com sucesso.',
            'avatar_url' => $result['secure_url'],
        ]);
    }
}
