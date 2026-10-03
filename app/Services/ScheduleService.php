<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\TechnicianSchedule;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    private const DAY_NAMES = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    private const MONTH_NAMES = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];

    /** Daftar hari yang bisa dipilih (hari ini sampai N hari ke depan). */
    public function dates(): array
    {
        $today = now()->startOfDay();
        $maxDays = (int) config('reaple.schedule.max_days_ahead');
        $dates = [];

        for ($i = 0; $i <= $maxDays; $i++) {
            $day = $today->copy()->addDays($i);

            $dates[] = [
                'date' => $day->toDateString(),
                'day_name' => self::DAY_NAMES[$day->dayOfWeek],
                'label' => self::DAY_NAMES[$day->dayOfWeek].', '.$day->day.' '.self::MONTH_NAMES[$day->month].' '.$day->year,
            ];
        }

        return $dates;
    }

    /** Semua jam beserta teknisi yang masih kosong pada tanggal tertentu. */
    public function slotsFor(string $date): array
    {
        $day = $this->parseDate($date, 'date');

        $technicians = $this->activeTechnicians()->get();

        $booked = TechnicianSchedule::whereDate('date', $day->toDateString())
            ->get(['technician_id', 'time_slot'])
            ->groupBy(fn ($row) => substr($row->time_slot, 0, 5))
            ->map(fn ($rows) => $rows->pluck('technician_id')->all());

        $earliest = now()->addHours((int) config('reaple.schedule.min_lead_hours'));

        $slots = [];

        foreach (config('reaple.schedule.slots') as $time) {
            $slotAt = $this->combine($day->toDateString(), $time);
            $taken = $booked->get($time, []);

            $free = $technicians->reject(fn (User $t) => in_array($t->id, $taken, true));

            $reason = null;
            if ($slotAt->lt($earliest)) {
                $reason = 'Waktu sudah lewat atau terlalu mendadak.';
            } elseif ($free->isEmpty()) {
                $reason = 'Semua teknisi penuh. Pilih jam lain.';
            }

            $slots[] = [
                'time' => $time,
                'available' => $reason === null,
                'reason' => $reason,
                'technicians' => $reason === null
                    ? $free->map(fn (User $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'avatar_url' => MediaUrl::resolve($t->avatar),
                        'avg_rating' => (float) ($t->technicianProfile?->avg_rating ?? 0),
                        'rating_count' => (int) ($t->technicianProfile?->rating_count ?? 0),
                    ])->values()->all()
                    : [],
            ];
        }

        return ['date' => $day->toDateString(), 'slots' => $slots];
    }

    /** Pastikan tanggal dan jam valid, lalu kembalikan waktu kunjungan lengkap. */
    public function assertBookable(string $date, string $time): Carbon
    {
        $day = $this->parseDate($date, 'scheduled_date');

        if (! in_array($time, config('reaple.schedule.slots'), true)) {
            throw ValidationException::withMessages(['scheduled_time' => 'Jam yang dipilih tidak tersedia.']);
        }

        $scheduledAt = $this->combine($day->toDateString(), $time);
        $hours = (int) config('reaple.schedule.min_lead_hours');

        if ($scheduledAt->lt(now()->addHours($hours))) {
            throw ValidationException::withMessages([
                'scheduled_time' => "Jadwal minimal {$hours} jam dari sekarang. Pilih jam lain.",
            ]);
        }

        return $scheduledAt;
    }

    /** Teknisi aktif (akun aktif dan profil aktif). */
    public function activeTechnicians(): Builder
    {
        return User::query()
            ->where('role', UserRole::Technician->value)
            ->where('is_active', true)
            ->whereHas('technicianProfile', fn ($q) => $q->where('is_active', true))
            ->with('technicianProfile')
            ->orderBy('name');
    }

    private function parseDate(string $date, string $field): Carbon
    {
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Format tanggal harus YYYY-MM-DD.']);
        }

        $today = now()->startOfDay();
        $last = $today->copy()->addDays((int) config('reaple.schedule.max_days_ahead'));

        if ($day->lt($today) || $day->gt($last)) {
            throw ValidationException::withMessages([
                $field => 'Tanggal harus antara hari ini sampai '.$last->toDateString().'.',
            ]);
        }

        return $day;
    }

    private function combine(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", config('app.timezone'));
    }
}