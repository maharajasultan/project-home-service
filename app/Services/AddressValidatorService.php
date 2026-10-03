<?php

namespace App\Services;

use Illuminate\Support\Str;

class AddressValidatorService
{
    /** @return string[] */
    public function districts(): array
    {
        return config('reaple.service_area.districts', []);
    }

    /** Nama kecamatan baku (huruf besar/kecil disesuaikan), atau null jika di luar area. */
    public function canonicalDistrict(string $district): ?string
    {
        $needle = Str::lower(trim($district));

        foreach ($this->districts() as $name) {
            if (Str::lower($name) === $needle) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Validasi wilayah layanan.
     *
     * @return array<string,string> [field => pesan error]; kosong jika lolos
     */
    public function check(string $address, string $district, ?float $lat = null, ?float $lng = null): array
    {
        $errors = [];
        $label = config('reaple.service_area.label');

        if ($this->canonicalDistrict($district) === null) {
            $errors['district'] = "Maaf, layanan kami hanya mencakup wilayah {$label}. Pilih kecamatan dari daftar.";
        }

        $text = Str::lower($address);

        if (! Str::contains($text, 'bekasi')) {
            foreach (config('reaple.service_area.outside_keywords', []) as $keyword) {
                if (Str::contains($text, $keyword)) {
                    $errors['address'] = "Alamat terdeteksi di luar {$label}. Layanan hanya tersedia di wilayah {$label}.";
                    break;
                }
            }
        }

        if ($lat !== null && $lng !== null && ! $this->insideBounds($lat, $lng)) {
            $errors['latitude'] = "Titik lokasi berada di luar jangkauan layanan ({$label}).";
        }

        return $errors;
    }

    private function insideBounds(float $lat, float $lng): bool
    {
        $b = config('reaple.service_area.bounds');

        return $lat >= $b['lat_min'] && $lat <= $b['lat_max']
            && $lng >= $b['lng_min'] && $lng <= $b['lng_max'];
    }
}