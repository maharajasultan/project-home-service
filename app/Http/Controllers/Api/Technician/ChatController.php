<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Api\Concerns\RespondsWithChat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\Order;
use App\Services\ChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    use RespondsWithChat;

    public function __construct(private readonly ChatService $chat)
    {
    }

    /** GET /api/v1/technician/chats */
    public function conversations(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->chat->technicianConversations($request->user()),
            'OK',
            200,
            ['unread_total' => $this->chat->unreadCount($request->user())]
        );
    }

    /** GET /api/v1/technician/jobs/{id}/chat/messages?after_id=&before_id= */
    public function messages(Request $request, int $id): JsonResponse
    {
        $order = Order::where('technician_id', $request->user()->id)->findOrFail($id);
        [$after, $before] = $this->cursors($request);

        return $this->messagesResponse($this->chat->orderMessages($order, $request->user(), $after, $before));
    }

    /** POST /api/v1/technician/jobs/{id}/chat/messages  { message } */
    public function send(SendMessageRequest $request, int $id): JsonResponse
    {
        $order = Order::where('technician_id', $request->user()->id)->findOrFail($id);

        return $this->sentResponse(
            $this->chat->sendOrderMessage($order, $request->user(), $request->validated('message'))
        );
    }
}