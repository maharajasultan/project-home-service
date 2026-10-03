<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\ChatMessage;
use App\Models\Order;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatService
{
    public const PAGE_SIZE = 50;

    // ================= Chat pesanan (user <-> teknisi) =================

    public function orderMessages(Order $order, User $viewer, ?int $afterId, ?int $beforeId): Collection
    {
        $this->counterpartId($order, $viewer); // validasi akses

        return $this->fetch(ChatMessage::where('order_id', $order->id), $viewer, $afterId, $beforeId);
    }

    public function sendOrderMessage(Order $order, User $sender, string $text): ChatMessage
    {
        return ChatMessage::create([
            'sender_id' => $sender->id,
            'receiver_id' => $this->counterpartId($order, $sender),
            'order_id' => $order->id,
            'message' => $text,
        ]);
    }

    // ================= Chat umum (user <-> admin) =================

    public function supportMessages(User $user, ?int $afterId, ?int $beforeId): Collection
    {
        return $this->fetch($this->supportQuery($user), $user, $afterId, $beforeId);
    }

    public function sendSupportMessage(User $user, string $text): ChatMessage
    {
        $adminId = User::where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if (! $adminId) {
            throw new BusinessException('Layanan chat admin belum tersedia.', 503);
        }

        return ChatMessage::create([
            'sender_id' => $user->id,
            'receiver_id' => $adminId,
            'order_id' => null,
            'message' => $text,
        ]);
    }

    // ================= Daftar percakapan =================

    /** Percakapan user: admin (selalu ada di atas) + satu per pesanan yang sudah dibayar. */
    public function userConversations(User $user): array
    {
        $orders = Order::where('user_id', $user->id)
            ->whereIn('status', $this->successfulValues())
            ->with('technician')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $threads = $this->orderThreads($orders, $user, fn (Order $o) => $o->technician);

        $last = $this->supportQuery($user)->latest('id')->first();

        $support = [
            'type' => 'support',
            'order_id' => null,
            'order_code' => null,
            'title' => 'Admin reaple.id',
            'subtitle' => 'Bantuan umum',
            'avatar_url' => null,
            'last_message' => $this->lastPayload($last, $user),
            'unread_count' => $this->supportQuery($user)
                ->where('receiver_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        ];

        return [$support, ...$threads];
    }

    /** Percakapan teknisi: satu per pesanan miliknya yang sudah dibayar. */
    public function technicianConversations(User $technician): array
    {
        $orders = Order::where('technician_id', $technician->id)
            ->whereIn('status', $this->successfulValues())
            ->with('user')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $this->orderThreads($orders, $technician, fn (Order $o) => $o->user);
    }

    public function unreadCount(User $user): int
    {
        return ChatMessage::where('receiver_id', $user->id)->whereNull('read_at')->count();
    }

    // ------------------------------------------------------------------

    private function counterpartId(Order $order, User $viewer): int
    {
        if (! in_array($order->status, OrderStatus::successful(), true)) {
            throw new BusinessException('Chat dengan teknisi tersedia setelah pembayaran berhasil.', 403);
        }

        if ((int) $viewer->id === (int) $order->user_id) {
            $counterpart = $order->technician_id;

            if (! $counterpart) {
                throw new BusinessException('Teknisi untuk pesanan ini tidak tersedia.', 422);
            }
        } elseif ((int) $viewer->id === (int) $order->technician_id) {
            $counterpart = $order->user_id;
        } else {
            throw new BusinessException('Anda tidak memiliki akses ke chat ini.', 403);
        }

        return (int) $counterpart;
    }

    private function supportQuery(User $user): Builder
    {
        return ChatMessage::whereNull('order_id')->where(
            fn ($q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id)
        );
    }

    /**
     * after_id  : pesan lebih baru dari id tersebut (urut lama ke baru) -> untuk polling.
     * before_id : pesan lebih lama dari id tersebut -> untuk "muat pesan sebelumnya".
     * keduanya kosong : 50 pesan terakhir.
     */
    private function fetch(Builder $query, User $viewer, ?int $afterId, ?int $beforeId): Collection
    {
        if ($afterId !== null) {
            $messages = $query->where('id', '>', $afterId)->orderBy('id')->limit(self::PAGE_SIZE)->get();
        } else {
            if ($beforeId !== null) {
                $query->where('id', '<', $beforeId);
            }

            $messages = $query->orderByDesc('id')->limit(self::PAGE_SIZE)->get()->reverse()->values();
        }

        $unreadIds = $messages
            ->filter(fn (ChatMessage $m) => (int) $m->receiver_id === (int) $viewer->id && $m->read_at === null)
            ->pluck('id');

        if ($unreadIds->isNotEmpty()) {
            ChatMessage::whereIn('id', $unreadIds)->update(['read_at' => now()]);
        }

        return $messages;
    }

    private function orderThreads(Collection $orders, User $viewer, callable $counterpart): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        $ids = $orders->pluck('id');

        $lastIds = ChatMessage::whereIn('order_id', $ids)
            ->selectRaw('max(id) as id')
            ->groupBy('order_id')
            ->pluck('id');

        $last = ChatMessage::whereIn('id', $lastIds)->get()->keyBy('order_id');

        $unread = ChatMessage::whereIn('order_id', $ids)
            ->where('receiver_id', $viewer->id)
            ->whereNull('read_at')
            ->selectRaw('order_id, count(*) as total')
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        return $orders
            ->map(function (Order $order) use ($counterpart, $last, $unread, $viewer) {
                $person = $counterpart($order);

                return [
                    'type' => 'order',
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'title' => $person?->name ?? 'Pengguna',
                    'subtitle' => 'Pesanan '.$order->order_code,
                    'avatar_url' => MediaUrl::resolve($person?->avatar),
                    'last_message' => $this->lastPayload($last->get($order->id), $viewer),
                    'unread_count' => (int) ($unread[$order->id] ?? 0),
                ];
            })
            ->sortByDesc(fn (array $t) => $t['last_message']['created_at'] ?? '0')
            ->values()
            ->all();
    }

    private function lastPayload(?ChatMessage $message, User $viewer): ?array
    {
        if (! $message) {
            return null;
        }

        return [
            'text' => Str::limit($message->message, 80),
            'is_mine' => (int) $message->sender_id === (int) $viewer->id,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    /** @return string[] */
    private function successfulValues(): array
    {
        return array_map(fn (OrderStatus $s) => $s->value, OrderStatus::successful());
    }
}