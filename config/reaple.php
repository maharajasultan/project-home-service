<?php

return [

    // Batas waktu pembayaran sejak pesanan dibuat.
    'payment_ttl_minutes' => 10,

    // Maksimal pesanan "belum dibayar" per user (mencegah menimbun slot jadwal).
    'max_pending_orders' => 3,

    'schedule' => [
        // Jam mulai kunjungan (1 pesanan = 1 slot teknisi).
        'slots' => ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'],
        // Pesanan minimal ... jam dari sekarang.
        'min_lead_hours' => 2,
        // Bisa memesan sampai ... hari ke depan.
        'max_days_ahead' => 14,
    ],

    'service_area' => [
        'label' => 'Bekasi',

        // Kecamatan yang dilayani. Silakan sesuaikan dengan area kerja teknisi Anda.
        'districts' => [
            // Kota Bekasi
            'Bantargebang', 'Bekasi Barat', 'Bekasi Selatan', 'Bekasi Timur', 'Bekasi Utara',
            'Jatiasih', 'Jatisampurna', 'Medan Satria', 'Mustikajaya', 'Pondok Gede',
            'Pondok Melati', 'Rawalumbu',
            // Kabupaten Bekasi (sebagian)
            'Tambun Selatan', 'Tambun Utara', 'Cibitung', 'Cikarang Pusat', 'Cikarang Utara',
            'Cikarang Barat', 'Cikarang Selatan', 'Babelan', 'Setu',
        ],

        // Jika alamat menyebut kota ini TANPA kata "bekasi", alamat ditolak.
        'outside_keywords' => ['jakarta', 'bogor', 'depok', 'tangerang', 'karawang', 'bandung', 'serpong'],

        // Kotak koordinat kasar wilayah Bekasi (dipakai jika Flutter mengirim lat/lng).
        'bounds' => [
            'lat_min' => -6.45, 'lat_max' => -6.10,
            'lng_min' => 106.88, 'lng_max' => 107.30,
        ],
            'technician' => [
        // Perjalanan baru boleh dimulai ... jam sebelum jadwal kunjungan.
        // Saat testing set TRIP_HOURS_BEFORE=999 di .env. Kembalikan ke 3 sebelum produksi.
        'trip_hours_before' => (int) env('TRIP_HOURS_BEFORE', 3),

        // Maksimal foto per jenis (sebelum / sesudah) per pesanan.
        'max_photos_per_type' => 5,

        // Lokasi dianggap "basi" jika tidak diperbarui selama ... detik.
        'location_stale_seconds' => 60,
    ],
    ],
];