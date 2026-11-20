<?php
declare(strict_types=1);

/*
 * Satu-satunya sumber data tim (Req 9.1, 9.2).
 *
 * Dipakai hanya oleh bin/sync-team.php untuk menulis tabel tim di README.md.
 * Tidak ada view yang membaca berkas ini: nama, NIM, inisial, dan avatar tidak
 * boleh muncul di halaman publik mana pun (Req 9.3, 9.4). Mengubah data di sini
 * cukup diikuti `php bin/sync-team.php`, tanpa menyunting berkas view (Req 9.9).
 *
 * Nama modul mengikuti daftar modul Haven di README bagian 2.
 *
 * Catatan: NIM Hasliza Syakina masih kosong dan diisi pemilik proyek.
 * Selama kosong, `php bin/sync-team.php --check` keluar dengan kode bukan nol
 * dan menyebut namanya (Req 9.8).
 *
 * @return list<array{name:string, nim:string, modules:list<string>}>
 */

return [
    [
        'name' => 'Muhammad Jundi Assabiq',
        'nim' => 'H1D025022',
        'modules' => [
            'Autentikasi & Akun',
            'Pelaporan',
            'Lacak Laporan',
            'Keamanan inti',
        ],
    ],
    [
        'name' => 'Muhammad Raffi Alfarizi',
        'nim' => 'H1D025032',
        'modules' => [
            'Manajemen Kasus',
            'Jadwal Pendampingan',
            'Pesan Aman',
            'Notifikasi & Audit Log',
        ],
    ],
    [
        'name' => 'Hasliza Syakina',
        'nim' => '',
        'modules' => [
            'Edukasi',
            'Direktori Layanan',
            'Dashboard & Statistik',
        ],
    ],
];
