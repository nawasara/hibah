<?php

declare(strict_types=1);

$prefix = 'hibah';

/*
| Tiga workspace terpisah di bawah satu grup.
|
| Label lama "Hibah & Bansos" dibuang: ia menyebut dua dari tiga hal yang
| ditangani paket ini, dan tidak ada label workspace lain di repo ini yang
| memakai "&".
|
| ⚠️ ID workspace WAJIB berbeda. WorkspaceManager menggabungkan workspace
| ber-ID sama dan mengambil label/icon dari yang termuat lebih dulu secara
| alfabet — pernah terjadi: workspace `monitoring` berubah jadi "Database"
| karena database-monitor termuat duluan.
|
| Awalan permission tetap `hibah.` mengikuti NAMA PAKET, bukan label
| workspace. Itu yang membuat sebuah permission dapat ditelusuri kembali ke
| paket yang mendefinisikannya.
*/

/** Submenu daftar + laporan untuk satu peruntukan. */
$menuFor = static function (string $segment, array $children) use ($prefix): array {
    $items = [];

    foreach ($children as $child => $label) {
        $items[] = [
            'label' => $label,
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
        'workspace' => 'hibah',
        'label' => 'Hibah',
        'icon' => 'lucide-hand-coins',
        'group' => 'Bantuan Daerah',
        'url' => '',
        'permission' => 'hibah.hibah.view',
        'submenu' => $menuFor('hibah', [
            'uang' => 'Hibah Uang',
            'barang' => 'Hibah Barang',
        ]),
    ],

    [
        'workspace' => 'bansos',
        'label' => 'Bansos',
        'icon' => 'lucide-heart-handshake',
        'group' => 'Bantuan Daerah',
        'url' => '',
        'permission' => 'hibah.bansos.view',
        'submenu' => $menuFor('bansos', [
            'uang' => 'Bansos Uang',
            'barang' => 'Bansos Barang',
        ]),
    ],

    [
        'workspace' => 'bantuan-keuangan',
        'label' => 'Bantuan Keuangan',
        'icon' => 'lucide-landmark',
        'group' => 'Bantuan Daerah',
        'url' => '',
        'permission' => 'hibah.bantuan-keuangan.view',
        'submenu' => $menuFor('bantuan-keuangan', [
            'umum' => 'Umum',
            'khusus' => 'Khusus',
        ]),
    ],

    [
        'workspace' => 'hibah-pengaturan',
        'label' => 'Pengaturan Hibah',
        'icon' => 'lucide-settings',
        'group' => 'Bantuan Daerah',
        'url' => '',
        'permission' => 'hibah.import',
        'submenu' => [
            [
                'label' => 'Impor Data',
                'icon' => 'lucide-upload',
                'url' => url($prefix.'/import'),
                'permission' => 'hibah.import',
                'navigate' => true,
            ],
        ],
    ],
];
