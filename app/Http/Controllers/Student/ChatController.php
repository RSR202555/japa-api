<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Histórico de mensagens entre o aluno e o admin/treinador.
     */
    public function index(Request $request): JsonResponse
    {
        $userId    = $request->user()->id;
        $adminUser = User::role('admin')->first();

        if (! $adminUser) {
            return response()->json(['messages' => [], 'total' => 0]);
        }

        $messages = ChatMessage::where(function ($q) use ($userId, $adminUser) {
            $q->where('sender_id', $userId)->where('receiver_id', $adminUser->id);
        })->orWhere(function ($q) use ($userId, $adminUser) {
            $q->where('sender_id', $adminUser->id)->where('receiver_id', $userId);
        })
            ->latest()
            ->paginate(30);

        // Marcar como lidas
        ChatMessage::where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json($messages);
    }

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $adminUser = User::role('admin')->first();

        if (! $adminUser) {
            return response()->json(['message' => 'Treinador não encontrado.'], 404);
        }

        $message = ChatMessage::create([
            'sender_id'   => $request->user()->id,
            'receiver_id' => $adminUser->id,
            'content'     => strip_tags(trim($request->content)),
        ]);

        return response()->json([
            'message' => 'Mensagem enviada.',
            'data'    => $message->load('sender'),
        ], 201);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = ChatMessage::where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}
