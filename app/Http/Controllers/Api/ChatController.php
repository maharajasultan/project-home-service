<?php

namespace App\Http\Controllers\Api;

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

    /** GET /api/v1/chats  (daftar percakapan: admin + tiap pesanan) */
    public function conversations(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->chat->userConversations($request->user()),
            'OK',
            200,
            ['unread_total' => $this->chat->unreadCount($request->user())]
        );
    }

    /** GET /api/v1/chat/unread-count  (badge di Bottom Navbar, semua role) */
    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(['unread' => $this->chat->unreadCount($request->user())]);
    }

    /** GET /api/v1/orders/{id}/chat/messages?after_id=&before_id= */
    public function orderMessages(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);
        [$after, $before] = $this->cursors($request);

        return $this->messagesResponse($this->chat->orderMessages($order, $request->user(), $after, $before));
    }

    /** POST /api/v1/orders/{id}/chat/messages  { message } */
    public function sendOrderMessage(SendMessageRequest $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        return $this->sentResponse(
            $this->chat->sendOrderMessage($order, $request->user(), $request->validated('message'))
        );
    }

    /** GET /api/v1/chat/support/messages?after_id=&before_id= */
    public function supportMessages(Request $request): JsonResponse
    {
        [$after, $before] = $this->cursors($request);

        return $this->messagesResponse($this->chat->supportMessages($request->user(), $after, $before));
    }

    /** POST /api/v1/chat/support/messages  { message } */
    public function sendSupportMessage(SendMessageRequest $request): JsonResponse
    {
        return $this->sentResponse(
            $this->chat->sendSupportMessage($request->user(), $request->validated('message'))
        );
    }
}