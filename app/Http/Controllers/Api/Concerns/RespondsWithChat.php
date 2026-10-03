<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Services\ChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait RespondsWithChat
{
    /** @return array{0: ?int, 1: ?int} [after_id, before_id] */
    protected function cursors(Request $request): array
    {
        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:1'],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return [
            isset($data['after_id']) ? (int) $data['after_id'] : null,
            isset($data['before_id']) ? (int) $data['before_id'] : null,
        ];
    }

    /**
     * meta.has_more:
     *  - dengan after_id  : true = mungkin masih ada pesan lebih baru, panggil lagi segera.
     *  - tanpa after_id   : true = masih ada pesan lebih lama (pakai before_id untuk memuatnya).
     */
    protected function messagesResponse(Collection $messages): JsonResponse
    {
        return ApiResponse::success(
            ChatMessageResource::collection($messages)->resolve(),
            'OK',
            200,
            [
                'last_id' => $messages->last()?->id,
                'has_more' => $messages->count() >= ChatService::PAGE_SIZE,
                'server_time' => now()->toIso8601String(),
            ]
        );
    }

    protected function sentResponse(ChatMessage $message): JsonResponse
    {
        return ApiResponse::success(
            (new ChatMessageResource($message))->resolve(),
            'Pesan terkirim.',
            201
        );
    }
}