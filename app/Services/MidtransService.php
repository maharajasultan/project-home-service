<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;
use Throwable;

class MidtransService
{
    private string $serverKey;

    public function __construct()
    {
        $this->serverKey = (string) config('midtrans.server_key');

        Config::$serverKey = $this->serverKey;
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$isSanitized = (bool) config('midtrans.is_sanitized');
        Config::$is3ds = (bool) config('midtrans.is_3ds');
    }

    /** Buat transaksi Snap. Return ['token' => ..., 'redirect_url' => ...]. */
    public function createSnapTransaction(array $params): array
    {
        if ($this->serverKey === '') {
            Log::error('MIDTRANS_SERVER_KEY belum diisi di .env');
            throw new BusinessException('Pembayaran belum dikonfigurasi. Hubungi admin.', 503);
        }

        try {
            $result = $this->toArray(Snap::createTransaction($params));
        } catch (Throwable $e) {
            Log::error('Midtrans: gagal membuat transaksi Snap', ['error' => $e->getMessage()]);
            throw new BusinessException('Gagal membuat transaksi pembayaran. Coba lagi beberapa saat.', 502);
        }

        if (empty($result['token']) || empty($result['redirect_url'])) {
            Log::error('Midtrans: respons Snap tidak lengkap', ['result' => $result]);
            throw new BusinessException('Gagal membuat transaksi pembayaran. Coba lagi beberapa saat.', 502);
        }

        return ['token' => $result['token'], 'redirect_url' => $result['redirect_url']];
    }

    /** Status transaksi dari Midtrans. Null jika user belum memilih metode bayar (belum ada transaksi). */
    public function fetchStatus(string $midtransOrderId): ?array
    {
        try {
            $result = $this->toArray(Transaction::status($midtransOrderId));
        } catch (Throwable $e) {
            if ((int) $e->getCode() === 404 || str_contains($e->getMessage(), '404')) {
                return null;
            }

            Log::error('Midtrans: gagal mengambil status', ['order_id' => $midtransOrderId, 'error' => $e->getMessage()]);
            throw new BusinessException('Gagal memeriksa status pembayaran. Coba lagi.', 502);
        }

        if ((int) ($result['status_code'] ?? 200) === 404) {
            return null;
        }

        return $result;
    }

    /** Matikan tagihan pending di Midtrans. Kegagalan diabaikan (hanya dicatat). */
    public function expireQuietly(string $midtransOrderId): void
    {
        try {
            Transaction::expire($midtransOrderId);
        } catch (Throwable $e) {
            Log::info('Midtrans: expire dilewati', ['order_id' => $midtransOrderId, 'error' => $e->getMessage()]);
        }
    }

    /** signature_key = SHA512(order_id + status_code + gross_amount + ServerKey) */
    public function isValidSignature(array $n): bool
    {
        if ($this->serverKey === '') {
            return false;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $key) {
            if (! isset($n[$key]) || ! is_scalar($n[$key])) {
                return false;
            }
        }

        $expected = hash('sha512', $n['order_id'].$n['status_code'].$n['gross_amount'].$this->serverKey);

        return hash_equals($expected, (string) $n['signature_key']);
    }

    private function toArray(mixed $value): array
    {
        return (array) json_decode(json_encode($value), true);
    }
}