<?php

declare(strict_types=1);

$prefix = 'hibah';

/*
| SATU workspace "Bantuan Daerah" dengan TIGA seksi di dalamnya.
|
| Bukan tiga workspace terpisah: workspace yang dibuka menyembunyikan
| workspace lain dari sidebar, jadi staf yang sedang di Hibah tidak melihat
| Bansos sama sekali sampai kembali ke Home. Dengan seksi, ketiganya selalu
| terlihat dan berpindah cukup satu klik.
|
| Penanda seksi = entri submenu TANPA `url`, memakai kunci `section`.
| Didukung sidebar nawasara-ui sejak v0.1.x; paket yang tidak memakainya
| tidak terpengaruh.
|
| Awalan permission tetap `hibah.` mengikuti NAMA PAKET, bukan label
| workspace — itu yang membuat permission dapat ditelusuri ke paket asalnya.
*/

/** Satu seksi: judul + halaman-halamannya. */
$section = static function (string $label, string $icon, string $segment, array $children) use ($prefix): array {
    $items = [[
        'section' => $label,
        'icon' => $icon,
        'permission' => "hibah.{$segment}.view",
    ]];

    foreach ($children as $child => $childLabel) {
        $items[] = [
            'label' => $childLabel,
            'icon' => $child === 'barang' ? 'lucide-package' : 'lucide-banknote',
            'url' => url("{$prefix}/{$segment}/{$child}"),
            'permission' => "hibah.{$segment}.view",
            'navigate' => true,
        ];
    }

    $items[] = [
        'label' => 'Laporan',
        'icon' => 'lucide-chart-bar',
        'url' => url("{$prefix}/{$segment}/laporan"),
        'permission' => "hibah.{$segment}.view",
        'navigate' => true,
    ];

    return $items;
};

return [
    [
        'workspace' => 'bantuan-daerah',
        'label' => 'Bantuan Daerah',
        'icon' => 'lucide-hand-coins',
        'group' => 'Layanan',
        'url' => '',

        // TANPA permission di level workspace — kalau diisi
        // 'hibah.hibah.view', staf yang hanya berhak atas bansos tidak akan
        // melihat workspace-nya sama sekali (accessible() menyaring dengan
        // satu permission ini saja). Penggerbangan sesungguhnya ada di tiap
        // seksi dan tiap submenu, yang masing-masing punya permission
        // peruntukannya sendiri — dan seksi yang seluruh isinya tak
        // terjangkau tidak menampilkan apa pun.
        'permission' => null,
        'submenu' => array_merge(
            $section('Hibah', 'lucide-hand-coins', 'hibah', [
                'uang' => 'Hibah Uang',
                'barang' => 'Hibah Barang',
            ]),
            $section('Bansos', 'lucide-heart-handshake', 'bansos', [
                'uang' => 'Bansos Uang',
                'barang' => 'Bansos Barang',
            ]),
            $section('Bantuan Keuangan', 'lucide-landmark', 'bantuan-keuangan', [
                'umum' => 'Umum',
                'khusus' => 'Khusus',
            ]),
            [
                [
                    'section' => 'Penerima',
                    'icon' => 'lucide-users-round',
                    'permission' => 'hibah.recipient.view',
                ],
                [
                    'label' => 'Daftar Penerima',
                    'icon' => 'lucide-list',
                    'url' => url($prefix.'/penerima'),
                    'permission' => 'hibah.recipient.view',
                    'navigate' => true,
                ],
                [
                    'section' => 'Pengaturan',
                    'icon' => 'lucide-settings',
                    'permission' => 'hibah.import',
                ],
                [
                    'label' => 'Impor Data',
                    'icon' => 'lucide-upload',
                    'url' => url($prefix.'/import'),
                    'permission' => 'hibah.import',
                    'navigate' => true,
                ],
            ],
        ),
    ],
];
