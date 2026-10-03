<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Support\CsvExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicianPerformanceController extends Controller
{
    private const PALETTE = ['#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#dc3545', '#20c997', '#ffc107', '#6c757d'];

    public function index(Request $request): View
    {
        $month = $this->month($request);
        $rows = $this->rows($month);

        $reviewCount = array_sum(array_column($rows, 'month_reviews'));
        $weighted = array_sum(array_map(fn ($r) => ($r['month_avg'] ?? 0) * $r['month_reviews'], $rows));

        $totals = [
            'jobs' => array_sum(array_column($rows, 'jobs')),
            'open' => array_sum(array_column($rows, 'open')),
            'value' => array_sum(array_column($rows, 'value')),
            'reviews' => $reviewCount,
            'avg' => $reviewCount > 0 ? round($weighted / $reviewCount, 2) : null,
        ];

        return view('admin.reports.technicians', [
            'month' => $month,
            'prev' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'next' => $month->lt(now()->startOfMonth()) ? $month->copy()->addMonthNoOverflow()->format('Y-m') : null,
            'rows' => $rows,
            'totals' => $totals,
            'chart' => $this->chart($month, $rows),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $month = $this->month($request);

        $rows = array_map(fn (array $r) => [
            $r['name'],
            $r['active'] ? 'Aktif' : 'Nonaktif',
            $r['jobs'],
            $r['open'],
            $r['value'],
            $r['month_avg'] !== null ? number_format($r['month_avg'], 2, ',', '') : '',
            $r['month_reviews'],
            number_format($r['overall_avg'], 2, ',', ''),
            $r['overall_count'],
            $r['unreplied'],
            $r['label'],
        ], $this->rows($month));

        return CsvExport::download(
            'kinerja-teknisi_'.$month->format('Y-m').'.csv',
            ['Teknisi', 'Status Akun', 'Pekerjaan Selesai', 'Terjadwal/Berjalan', 'Nilai Pekerjaan Selesai (Rp)',
                'Rating Bulan Ini', 'Ulasan Bulan Ini', 'Rating Keseluruhan', 'Total Ulasan', 'Ulasan Belum Dibalas', 'Penilaian'],
            $rows
        );
    }

    // ------------------------------------------------------------------

    private function month(Request $request): Carbon
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']], [
            'month.date_format' => 'Format bulan harus YYYY-MM.',
        ]);

        $month = isset($data['month'])
            ? Carbon::createFromFormat('!Y-m', $data['month'])->startOfMonth()
            : now()->startOfMonth();

        return $month->gt(now()->startOfMonth()) ? now()->startOfMonth() : $month;
    }

    private function rows(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $technicians = User::where('role', 'technician')
            ->with('technicianProfile')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $done = Order::toBase()
            ->where('status', 'completed')
            ->whereNotNull('technician_id')
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('technician_id, count(*) as jobs, sum(total) as value')
            ->groupBy('technician_id')
            ->get()
            ->keyBy('technician_id');

        $open = Order::toBase()
            ->whereIn('status', ['paid', 'on_the_way', 'in_progress'])
            ->whereNotNull('technician_id')
            ->whereBetween('scheduled_at', [$start, $end])
            ->selectRaw('technician_id, count(*) as jobs')
            ->groupBy('technician_id')
            ->pluck('jobs', 'technician_id');

        $ratings = Review::toBase()
            ->where('target_type', 'technician')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('target_id, avg(rating) as avg_rating, count(*) as total')
            ->groupBy('target_id')
            ->get()
            ->keyBy('target_id');

        $unreplied = Review::toBase()
            ->where('target_type', 'technician')
            ->whereNull('reply')
            ->selectRaw('target_id, count(*) as total')
            ->groupBy('target_id')
            ->pluck('total', 'target_id');

        $rows = [];

        foreach ($technicians as $t) {
            $d = $done->get($t->id);
            $r = $ratings->get($t->id);

            $jobs = (int) ($d->jobs ?? 0);
            $openJobs = (int) ($open[$t->id] ?? 0);
            $monthReviews = (int) ($r->total ?? 0);

            // Teknisi nonaktif tanpa aktivitas bulan ini tidak perlu ditampilkan.
            if (! $t->is_active && $jobs === 0 && $openJobs === 0 && $monthReviews === 0) {
                continue;
            }

            $monthAvg = $monthReviews > 0 ? round((float) $r->avg_rating, 2) : null;
            [$label, $badge] = $this->predicate($monthAvg);

            $rows[] = [
                'id' => $t->id,
                'name' => $t->name,
                'active' => (bool) $t->is_active,
                'jobs' => $jobs,
                'open' => $openJobs,
                'value' => (int) ($d->value ?? 0),
                'month_avg' => $monthAvg,
                'month_reviews' => $monthReviews,
                'overall_avg' => (float) ($t->technicianProfile?->avg_rating ?? 0),
                'overall_count' => (int) ($t->technicianProfile?->rating_count ?? 0),
                'unreplied' => (int) ($unreplied[$t->id] ?? 0),
                'label' => $label,
                'badge' => $badge,
            ];
        }

        usort($rows, fn ($a, $b) => [$b['jobs'], $b['month_avg'] ?? 0] <=> [$a['jobs'], $a['month_avg'] ?? 0]);

        return $rows;
    }

    /** @return array{0: string, 1: string} [label, kelas badge] */
    private function predicate(?float $avg): array
    {
        return match (true) {
            $avg === null => ['Belum ada penilaian', 'text-bg-secondary'],
            $avg >= 4.5 => ['Sangat Baik', 'text-bg-success'],
            $avg >= 4.0 => ['Baik', 'text-bg-primary'],
            $avg >= 3.0 => ['Cukup', 'text-bg-warning'],
            default => ['Perlu Evaluasi', 'text-bg-danger'],
        };
    }

    /** Pekerjaan selesai per teknisi untuk 6 bulan terakhir sampai bulan terpilih. */
    private function chart(Carbon $month, array $rows): array
    {
        $first = $month->copy()->subMonthsNoOverflow(5)->startOfMonth();

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $months[$first->copy()->addMonthsNoOverflow($i)->format('Y-m')] = $first->copy()->addMonthsNoOverflow($i)->format('m/Y');
        }

        $data = Order::toBase()
            ->where('status', 'completed')
            ->whereNotNull('technician_id')
            ->whereBetween('completed_at', [$first, $month->copy()->endOfMonth()])
            ->selectRaw("technician_id, DATE_FORMAT(completed_at, '%Y-%m') as ym, count(*) as total")
            ->groupBy('technician_id', 'ym')
            ->get()
            ->groupBy('technician_id');

        $datasets = [];

        foreach (array_slice($rows, 0, 8) as $i => $r) {
            $perMonth = ($data->get($r['id']) ?? collect())->pluck('total', 'ym');

            $datasets[] = [
                'label' => $r['name'],
                'data' => array_map(fn ($ym) => (int) ($perMonth[$ym] ?? 0), array_keys($months)),
                'backgroundColor' => self::PALETTE[$i % count(self::PALETTE)],
                'borderRadius' => 4,
            ];
        }

        return ['labels' => array_values($months), 'datasets' => $datasets];
    }
}