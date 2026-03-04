<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ProgressPhoto;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProgressPhotoController extends Controller
{
    public function __construct(private CloudinaryService $cloudinary) {}

    public function index(Request $request): JsonResponse
    {
        $photos = ProgressPhoto::where('user_id', $request->user()->id)
            ->when($request->query('angle'), fn ($q) => $q->where('angle', $request->query('angle')))
            ->latest('taken_at')
            ->paginate(12);

        return response()->json($photos);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image'          => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'angle'          => ['required', 'string', 'in:front,back,side_left,side_right'],
            'weight_at_photo' => ['nullable', 'numeric', 'min:20', 'max:500'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'taken_at'       => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Upload via backend — nunca expõe credenciais Cloudinary ao frontend
        $result = $this->cloudinary->uploadProgressPhoto(
            $request->file('image'),
            $request->user()->id
        );

        $photo = ProgressPhoto::create([
            'user_id'              => $request->user()->id,
            'cloudinary_public_id' => $result['public_id'],
            'image_url'            => $result['secure_url'],
            'thumbnail_url'        => $result['thumbnail_url'],
            'angle'                => $request->angle,
            'weight_at_photo'      => $request->weight_at_photo,
            'notes'                => strip_tags(trim($request->notes ?? '')),
            'taken_at'             => $request->taken_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Foto enviada com sucesso.',
            'photo'   => $photo,
        ], 201);
    }

    public function destroy(Request $request, ProgressPhoto $progressPhoto): JsonResponse
    {
        $this->authorize('delete', $progressPhoto);

        // Remove do Cloudinary
        $this->cloudinary->delete($progressPhoto->cloudinary_public_id);

        $progressPhoto->delete();

        return response()->json(['message' => 'Foto removida com sucesso.']);
    }
}
