<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\TechnicianLocation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TechnicianJobService
{
    /** Status pesanan yang boleh dilihat teknisi (sudah dibayar). @return string[] */
    public static function visibleStatuses(): array
    {
        return array_map(fn (OrderStatus $s) => $s->value, OrderStatus::successful());
    }

    /** Ambil satu pesanan milik teknisi ini (404 jika bukan miliknya atau belum dibayar). */
    public function find(User $technician, int $orderId): Order
    {
        return Order::where('technician_id', $technician->id)
            ->whereIn('status', self::visibleStatuses())
            ->with(['user', 'items.product', 'photos'])
            ->findOrFail($orderId);
    }

    /** Tombol "Dalam Perjalanan". */
    public function startTrip(User $technician, int $orderId, ?float $lat, ?float $lng): void
    {
        DB::transaction(function () use ($technician, $orderId, $lat, $lng) {
            $order = $this->lockOwned($technician, $orderId);

            if ($order->status !== OrderStatus::Paid) {
                throw new BusinessException('Hanya pesanan yang sudah dibayar dan belum berangkat yang dapat dimulai.');
            }

            $busy = Order::where('technician_id', $technician->id)
                ->where('status', OrderStatus::OnTheWay->value)
                ->where('id', '!=', $order->id)
                ->exists();

            if ($busy) {
                throw new BusinessException('Anda masih punya perjalanan aktif. Selesaikan atau tiba dulu di lokasi tersebut.');
            }

            $opensAt = $order->scheduled_at->copy()->subHours((int) config('reaple.technician.trip_hours_before'));

            if (now()->lt($opensAt)) {
                throw new BusinessException(
                    'Belum waktunya berangkat. Perjalanan dapat dimulai mulai '.$opensAt->format('d-m-Y H:i').' WIB.'
                );
            }

            $order->update(['status' => OrderStatus::OnTheWay]);

            if ($lat !== null && $lng !== null) {
                $this->storeLocation($order->id, $technician->id, $lat, $lng);
            }
        });
    }

    /** Simpan posisi terbaru (dipanggil Flutter tiap ±10 detik). */
    public function updateLocation(User $technician, int $orderId, float $lat, float $lng): TechnicianLocation
    {
        $order = Order::where('technician_id', $technician->id)->findOrFail($orderId);

        if ($order->status !== OrderStatus::OnTheWay) {
            throw new BusinessException('Pelacakan lokasi hanya aktif saat status "Dalam Perjalanan".');
        }

        return $this->storeLocation($order->id, $technician->id, $lat, $lng);
    }

    /** Unggah foto. Foto "sebelum" pertama otomatis menandai teknisi sudah tiba (in_progress). */
    public function addPhoto(User $technician, int $orderId, string $type, UploadedFile $file): OrderPhoto
    {
        $path = null;

        try {
            return DB::transaction(function () use ($technician, $orderId, $type, $file, &$path) {
                $order = $this->lockOwned($technician, $orderId);

                if (! in_array($order->status, [OrderStatus::OnTheWay, OrderStatus::InProgress], true)) {
                    throw new BusinessException('Foto hanya dapat diunggah saat Anda dalam perjalanan atau sedang mengerjakan.');
                }

                if ($type === 'after' && $order->status !== OrderStatus::InProgress) {
                    throw new BusinessException('Unggah foto sebelum service lebih dulu, baru foto sesudah service.');
                }

                $max = (int) config('reaple.technician.max_photos_per_type');
                $count = OrderPhoto::where('order_id', $order->id)->where('type', $type)->count();

                if ($count >= $max) {
                    throw new BusinessException("Maksimal {$max} foto per jenis. Hapus salah satu foto jika ingin mengganti.");
                }

                $path = $file->store("order-photos/{$order->id}", 'public');

                $photo = OrderPhoto::create([
                    'order_id' => $order->id,
                    'type' => $type,
                    'path' => $path,
                ]);

                if ($type === 'before' && $order->status === OrderStatus::OnTheWay) {
                    $order->update(['status' => OrderStatus::InProgress]);
                    $this->clearLocation($order->id); // sudah tiba, hentikan pelacakan
                }

                return $photo;
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path); // jangan tinggalkan file yatim
            }

            throw $e;
        }
    }

    /** Hapus foto (hanya saat service berlangsung). */
    public function removePhoto(User $technician, int $orderId, int $photoId): void
    {
        $path = DB::transaction(function () use ($technician, $orderId, $photoId) {
            $order = $this->lockOwned($technician, $orderId);

            if ($order->status !== OrderStatus::InProgress) {
                throw new BusinessException('Foto hanya dapat dihapus saat service sedang berlangsung.');
            }

            $photo = OrderPhoto::where('order_id', $order->id)->findOrFail($photoId);
            $path = $photo->path;
            $photo->delete();

            return $path;
        });

        Storage::disk('public')->delete($path);
    }

    /** Tombol "Selesai". */
    public function complete(User $technician, int $orderId): void
    {
        DB::transaction(function () use ($technician, $orderId) {
            $order = $this->lockOwned($technician, $orderId);

            if ($order->status !== OrderStatus::InProgress) {
                throw new BusinessException('Pesanan hanya dapat diselesaikan setelah service dimulai (unggah foto sebelum service).');
            }

            $counts = OrderPhoto::where('order_id', $order->id)
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type');

            if (($counts['before'] ?? 0) < 1 || ($counts['after'] ?? 0) < 1) {
                throw new BusinessException('Unggah minimal 1 foto sebelum dan 1 foto sesudah service sebelum menyelesaikan pesanan.');
            }

            $order->update([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
            ]);

            $this->clearLocation($order->id);
        });
    }

    // ------------------------------------------------------------------

    /** Kunci baris pesanan milik teknisi (mencegah dua aksi bersamaan). */
    private function lockOwned(User $technician, int $orderId): Order
    {
        return Order::where('technician_id', $technician->id)
            ->whereIn('status', self::visibleStatuses())
            ->whereKey($orderId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** Satu baris per pesanan. updated_at selalu diperbarui walau teknisi diam di tempat. */
    private function storeLocation(int $orderId, int $technicianId, float $lat, float $lng): TechnicianLocation
    {
        $location = TechnicianLocation::firstOrNew([
            'order_id' => $orderId,
            'technician_id' => $technicianId,
        ]);

        $location->fill(['latitude' => $lat, 'longitude' => $lng]);

        if ($location->exists && ! $location->isDirty()) {
            $location->touch();
        } else {
            $location->save();
        }

        return $location;
    }

    private function clearLocation(int $orderId): void
    {
        TechnicianLocation::where('order_id', $orderId)->delete();
    }
}