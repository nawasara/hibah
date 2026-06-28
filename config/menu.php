<?php

$prefix = 'hibah';

return [
    [
        // Workspace ID unik 'hibah' — JANGAN gabung ke workspace lain
        // (WorkspaceManager merge by ID, label/icon ikut yang load pertama).
        'workspace' => 'hibah',
        'label' => 'Hibah & Bansos',
        'icon' => 'lucide-hand-coins',
        'group' => 'Layanan',
        'url' => '',
        'permission' => 'hibah.pengajuan.view',
        'submenu' => [
            [
                'label' => 'Pengajuan',
                'icon' => 'lucide-file-text',
                'url' => url($prefix.'/pengajuan'),
                'permission' => 'hibah.pengajuan.view',
                'navigate' => true,
            ],
            [
                'label' => 'Laporan',
                'icon' => 'lucide-chart-bar',
                'url' => url($prefix.'/laporan'),
                'permission' => 'hibah.laporan.view',
                'navigate' => true,
            ],
            [
                'label' => 'Master Kategori',
                'icon' => 'lucide-tags',
                'url' => url($prefix.'/kategori'),
                'permission' => 'hibah.kategori.manage',
                'navigate' => true,
            ],
            [
                'label' => 'Import Data',
                'icon' => 'lucide-upload',
                'url' => url($prefix.'/import'),
                'permission' => 'hibah.import',
                'navigate' => true,
            ],
        ],
    ],
];
