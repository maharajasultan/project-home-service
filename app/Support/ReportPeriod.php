<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportPeriod
{
    public function __construct(
        public readonly Carbon $from,   // 00:00:00
        public readonly Carbon $to,     // 23:59:59
    ) {
    }

    /**
     * Baca ?from=YYYY-MM-DD&to=YYYY-MM-DD. Default: awal bulan ini sampai hari ini.
     *
     * @throws ValidationException
     */
    public static function resolve(Request $request, int $maxDays = 366): self
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'from.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',
            'to.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
        ]);

        $from = ! empty($data['from']) ? Carbon::createFromFormat('!Y-m-d', $data['from']) : null;
        $to = ! empty($data['to']) ? Carbon::createFromFormat('!Y-m-d', $data['to']) : null;

        if ($from && $to && $to->lt($from)) {
            throw ValidationException::withMessages(['to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        }

        if (! $from && ! $to) {
            $from = now()->startOfMonth();
            $to = now();
        } elseif (! $to) {
            $to = $from->gt(now()) ? $from->copy() : now();
        } elseif (! $from) {
            $from = $to->copy()->startOfMonth();
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        if ($from->diffInDays($to) > $maxDays) {
            throw ValidationException::withMessages(['from' => "Rentang periode maksimal {$maxDays} hari."]);
        }

        return new self($from, $to);
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    public function days(): int
    {
        return $this->from->diffInDays($this->to->copy()->startOfDay()) + 1;
    }

    public function label(): string
    {
        return $this->from->format('d-m-Y').' s/d '.$this->to->format('d-m-Y');
    }
}