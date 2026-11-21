# Asumsi Prototipe

- Data uji bersifat sintetis/anonim (Batasan 10): 66 vendor (39 Trade, 27 Non-Trade), riwayat 2025-S2 s.d. 2026-S2, dibuat melalui `php artisan migrate:fresh --seed`.
- Seluruh aksi tersimpan di basis data terpusat sesuai ERD (22 tabel); akses dibatasi per peran setelah login (Tabel 3.5).
- Bobot item per kategori (A 8/10, B 10/8, C 8/6, D 6/6, E 7/7, F 7/6, G 6/5) dan pembagian fungsi penilai per item adalah asumsi tim; total kategori mengikuti form resmi (18/18/14/12/14/13/11). Perlu dicocokkan dengan form Excel resmi.
- Batas rekomendasi: >= 3,00 Recommended, >= 2,50 Recommended dengan Catatan, < 2,50 Not Recommended dan membuka tindak lanjut. Dapat diubah di Pengaturan Sistem.
- Jadwal reminder (H-7, H-3, H-1, tiap 2 hari setelah tenggat, eskalasi H+3) adalah usulan tim dan dapat diubah tanpa ubah kode; divalidasi bersama perusahaan pada UAT.
- Keputusan "Re-development" memetakan status vendor ke "Tidak Direkomendasikan" sampai evaluasi berikutnya.
- Email dikirim melalui mailer yang dikonfigurasi di `.env` (default lokal: `log`). Reminder terjadwal membutuhkan `php artisan schedule:work` (atau cron `schedule:run`).
- Ekspor PDF memakai tampilan cetak peramban ("Simpan sebagai PDF"); ekspor Excel menghasilkan berkas .xlsx asli.
