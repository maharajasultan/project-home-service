<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChatController extends Controller
{
    private const PAGE_SIZE = 50;

    /** Daftar percakapan + (jika ?user=ID) jendela chat. */
    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'integer', 'min:1'],
        ]);

        $term = trim((string) $request->query('q', ''));
        $conversations = $term !== '' ? $this->searchCustomers($term) : $this->conversations();

        $userId = (int) $request->query('user', 0);
        $active = $userId > 0 ? $this->customer($userId) : null;

        return view('admin.chat.index', compact('conversations', 'active', 'term'));
    }

    /** GET admin/chat/{userId}/messages?after_id=&before_id=  (JSON, dipolling halaman chat) */
    public function messages(Request $request, int $userId): JsonResponse
    {
        $user = $this->customer($userId);

        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:1'],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $this->threadQuery($user);

        if (isset($data['after_id'])) {
            $messages = $query->where('id', '>', $data['after_id'])->orderBy('id')->limit(self::PAGE_SIZE)->get();
        } else {
            if (isset($data['before_id'])) {
                $query->where('id', '<', $data['before_id']);
            }

            $messages = $query->orderByDesc('id')->limit(self::PAGE_SIZE)->get()->reverse()->values();
        }

        // Pesan dari pelanggan yang baru dibaca admin.
        $unread = $messages
            ->filter(fn (ChatMessage $m) => (int) $m->sender_id === (int) $user->id && $m->read_at === null)
            ->pluck('id');

        if ($unread->isNotEmpty()) {
            ChatMessage::whereIn('id', $unread)->update(['read_at' => now()]);
        }

        return response()->json([
            'data' => $messages->map(fn (ChatMessage $m) => $this->payload($m, $user))->values(),
            'meta' => ['has_more' => $messages->count() >= self::PAGE_SIZE],
        ]);
    }

    /** POST admin/chat/{userId}/messages  { message } */
    public function send(Request $request, int $userId): JsonResponse
    {
        $user = $this->customer($userId);

        $request->merge(['message' => trim((string) $request->input('message'))]);
        $data = $request->validate(
            ['message' => ['required', 'string', 'max:1000']],
            ['message.required' => 'Pesan tidak boleh kosong.', 'message.max' => 'Pesan maksimal 1000 karakter.']
        );

        $message = ChatMessage::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $user->id,
            'order_id' => null,
            'message' => $data['message'],
        ]);

        return response()->json(['data' => $this->payload($message, $user)], 201);
    }

    // ------------------------------------------------------------------

    private function customer(int $id): User
    {
        return User::where('role', UserRole::User->value)->findOrFail($id);
    }

    private function threadQuery(User $user)
    {
        return ChatMessage::whereNull('order_id')->where(
            fn ($q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id)
        );
    }

    private function payload(ChatMessage $m, User $customer): array
    {
        return [
            'id' => $m->id,
            'is_mine' => (int) $m->sender_id !== (int) $customer->id, // dari sisi admin
            'message' => $m->message,
            'time' => $m->created_at->format('d/m H:i'),
        ];
    }

    /** Pelanggan yang pernah chat, terbaru di atas. */
    private function conversations(): array
    {
        $admins = "select id from users where role = 'admin'";

        $rows = DB::table('chat_messages')
            ->whereNull('order_id')
            ->selectRaw("CASE WHEN sender_id IN ({$admins}) THEN receiver_id ELSE sender_id END AS partner_id")
            ->selectRaw('MAX(id) AS last_id')
            ->selectRaw("SUM(CASE WHEN read_at IS NULL AND sender_id NOT IN ({$admins}) THEN 1 ELSE 0 END) AS unread")
            ->groupBy('partner_id')
            ->orderByDesc('last_id')
            ->limit(100)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::where('role', UserRole::User->value)->whereIn('id', $rows->pluck('partner_id'))->get()->keyBy('id');
        $last = ChatMessage::whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');

        return $rows
            ->filter(fn ($row) => $users->has($row->partner_id))
            ->map(function ($row) use ($users, $last) {
                $message = $last->get($row->last_id);

                return [
                    'user' => $users->get($row->partner_id),
                    'last' => $message ? Str::limit($message->message, 50) : null,
                    'last_at' => $message?->created_at,
                    'unread' => (int) $row->unread,
                ];
            })
            ->values()
            ->all();
    }

    /** Cari pelanggan (untuk memulai chat baru). */
    private function searchCustomers(string $term): array
    {
        $users = User::where('role', UserRole::User->value)
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(20)
            ->get();

        $unread = ChatMessage::whereNull('order_id')->whereNull('read_at')
            ->whereIn('sender_id', $users->pluck('id'))
            ->selectRaw('sender_id, count(*) as total')
            ->groupBy('sender_id')
            ->pluck('total', 'sender_id');

        return $users->map(fn (User $u) => [
            'user' => $u,
            'last' => null,
            'last_at' => null,
            'unread' => (int) ($unread[$u->id] ?? 0),
        ])->all();
    }
}