# Isi Backend BioAttend

Halo guys! Ini ringkasan isi backend yang sekarang ada di branch `integration/backend-hardened`. Baca ini dulu sebelum mulai nambah fitur, biar kita nggak bikin ulang sesuatu yang sebenarnya sudah ada.

> Catatan: cek implementasi dan test-nya sebelum dipakai

## Yang sudah ada di backend

### 1. Login, akun, dan hak akses

- Login dan logout
- Aktivasi akun dan lupa/ganti password
- Pengelolaan token dan sesi login
- Aturan password dan hashing
- Role `HRD`, `KADIV`, dan `EMPLOYEE`
- Middleware dan pengecekan akses berdasarkan role
- Pengelolaan user, role, dan permission

### 2. Data karyawan

- Lihat daftar dan detail karyawan
- Tambah karyawan dan edit data karyawan
- Tambah karyawan sekaligus membuat akun login
- Import karyawan secara massal
- Nonaktifkan karyawan tanpa menghapus histori
- Employee ID otomatis 6 digit
- Histori perubahan Employee ID dan penempatan
- Relasi kantor, divisi, unit, jabatan, dan atasan
- `device_user_identifier` untuk menyimpan PIN/identitas pengguna di sistem perangkat

### 3. Absensi

- Menerima dan menyimpan raw log absensi
- Memproses dan memproses ulang log
- Rekap dan detail absensi
- Klasifikasi scan, termasuk deteksi scan duplikat
- Penanganan keterlambatan, pulang cepat, dan absensi yang belum lengkap
- Daftar log yang belum terhubung ke data karyawan
- Pengajuan, persetujuan, dan penolakan koreksi absensi

### 4. Potongan gaji

- Perhitungan potongan absensi
- Perhitungan terkait keterlambatan dan ALPHA
- Input nominal gaji dasar
- Preview dan konfirmasi hasil perhitungan
- Histori perhitungan potongan
- Class `Money` untuk membantu perhitungan nominal uang

### 5. Pengajuan karyawan

- Pengajuan cuti dan pengecekan saldo cuti
- Pengajuan izin
- Pengajuan lembur
- Proses persetujuan dan penolakan sesuai akses

### 6. Jadwal kerja

- Master shift dan jadwal karyawan
- Assign jadwal untuk satu karyawan
- Assign jadwal secara massal
- Histori perubahan jadwal
- Resolusi jadwal berdasarkan konfigurasi company, division, dan employee
- Konfigurasi hari kerja dan toleransi keterlambatan

### 7. Master data organisasi

- Kantor (`offices`)
- Divisi (`divisions`)
- Unit (`units`)
- Jabatan (`positions`)
- Shift (`shifts`)
- Hari libur (`holidays`)
- Jenis cuti (`leave-types`)
- Data perangkat (`devices`)

### 8. Fitur pendukung

- Dashboard HRD, KADIV, dan EMPLOYEE
- Notifikasi
- Report
- Audit log
- Pengaturan sistem (`settings`)
- AI Assistant untuk HRD
- Health check API

### 9. Struktur integrasi perangkat

Di backend juga sudah ada beberapa bagian pendukung integrasi perangkat, seperti model dan konfigurasi device, service push/pull, job sinkronisasi, checkpoint, histori sinkronisasi, sama adapter SDK dan mock, tapi harus di cek lagi, soalnya gua gk ada alatnya buat tes

Bagian koneksi, SDK, UID terminal, konfigurasi, dan sinkronisasi fisik dikoordinasikan sulu sama yang pegang integrasi perangkat sama cek codenya dulu

## Yang perlu dilanjutkan

Ini daftar pekerjaan lanjutan yang bisa dibagi. Cek dulu kondisi aktualnya sebelum mulai, karena sebagian mungkin sudah dikerjakan di branch masing-masing

1. **Tes API yang sudah ada.** Cek request, response, validasi, status code, dan pembatasan akses HRD/KADIV/EMPLOYEE
2. **Cek kontrak API untuk frontend.** Cocokkan nama field dan bentuk response controller dengan `API_DOCUMENTATION.md`; update dokumentasi kalau ada perubahan
3. **AI API.** Pastikan alur chat, daftar conversation, dan pesan conversation sudah punya request/response yang jelas dan dites
4. **Unmatched attendance logs.** Cek alur daftar log yang belum terpetakan dan proses mapping ke karyawan
5. **Notification dan dashboard.** Cocokkin bentuk data yang dikirim API dengan kebutuhan tampilan frontend
6. **Report.** Lanjutkan pengecekan proses generate laporan dan mekanisme download file
7. **System API.** Tes pengelolaan users, roles, permissions, dan settings
8. **Automated test.** Tambahkan atau rapikan test untuk aturan bisnis yang sudah ada, lalu jalankan test yang relevan
9. **Integrasi perangkat.** sesuain pembagian tugas sama yang memegang ZKTeco, jangan bikin service pengganti sebelum periksa service sama adapter yang udah ada

## Sebelum mulai ngoding

- Ambil branch terbaru dulu
- Baca `API_DOCUMENTATION.md` untuk melihat endpoint yang sudah didokumentasikan
- Cari implementasi yang sudah ada sebelum membuat controller, model, service, atau migration baru
- Jangan ngubah atau ngehapus migration sembarangan. Perubahan database harus dicek dampaknya ke migration lain dan database yang sudah ada
- Kalau mengubah endpoint atau format response, kasih tau dulu
- Jangan anggap fitur sudah aman hanya karena file atau endpoint-nya sudah ada, Cek test dan perilakunya

## Cara ambil backend terbaru

Pindah ke branch integrasi dan tarik perubahan terbaru:

```bash
git fetch origin
git switch integration/backend-hardened
git pull --ff-only origin integration/backend-hardened
```

Kalau branch `integration/backend-hardened` belum ada di laptop:

```bash
git fetch origin
git switch -c integration/backend-hardened --track origin/integration/backend-hardened
```

Kalau ada perubahan lokal yang belum di-commit, amanin dulu sebelum pindah branch atau pull biar kerjaan sendiri nggak ketimpa

---

Intinya, backend dasarnya sudah punya banyak modul. Jadi sebelum bikin sesuatu dari nol, cek dulu file, route, service, dan dokumentasinya. Fokus selanjutnya tinggal mastiin implementasinya benar-benar jalan, dites, dan siap diintegrasikan sama frontend

## 10. Bagian teknis pendukung (token, session, cache, queue, dll.)

Selain fitur yang kelihatan di API, backend juga sudah punya beberapa mekanisme internal. Ini juga perlu dicek sebelum bikin mekanisme baru

### Token dan login

- Login menggunakan **Laravel Sanctum Bearer Token**
- Setelah login berhasil, API mengembalikan `token` dan `token_type: Bearer`
- `POST /auth/logout` mencabut token yang sedang dipakai
- `POST /auth/logout-all` mencabut semua token dan sesi milik akun yang sedang login
- Pergantian password juga mencabut token dan sesi supaya akun harus login ulang
- Token aktivasi akun dibuat secara acak, yang disimpan di database adalah hash-nya. Token asli cuma ditampilkan pas proses pembuatan akun dan punya masa berlaku (default konfigurasi 72 jam)
- Jangan buat sistem token baru sebelum cek tabel `personal_access_tokens`, alur Sanctum, dan endpoint auth yang sudah ada

### Session login

- Backend punya pengelolaan sesi sendiri, termasuk daftar sesi dan pencabutan sesi tertentu
- Data sesi menyimpan informasi seperti user, hash token sesi, IP address, perangkat, dan waktu aktivitas terakhir
- Endpoint terkait: `GET /auth/sessions`, `DELETE /auth/sessions/{id}`, dan `POST /auth/logout-all`
- Konfigurasi `SESSION_DRIVER` memakai `file`. Jangan asal menggantinya jadi `database`, karena tabel `sessions` di project ini punya struktur khusus dan bukan tabel standar Laravel Database Session Handler

### Cache

- Cache Laravel dikonfigurasi pake store `database` (`CACHE_STORE=database`)
- Tabel `cache` dan `cache_locks` udah tersedia di database
- Sebelum menambahkan cache untuk data tertentu, cek dulu apakah data tersebut memang sudah memakai `Cache::`, berapa lama TTL-nya, dan gimana cache dibersihkan saat data berubah. Jangan nambahin cache hanya supaya terlihat lebih cepat, karena data absensi, role, dan pengaturan tertentu harus tetap konsisten
- Perintah buat bersihin cache konfigurasi saat ada perubahan `.env` atau config:

    ```bash
    php artisan optimize:clear
    ```

### Queue dan background job

- Queue dikonfigurasi menggunakan database (`QUEUE_CONNECTION=database`)
- Tabel `jobs` dan tabel terkait queue sudah disiapkan
- Beberapa proses memakai job supaya pekerjaan tidak harus dilakukan langsung di request HTTP
- Worker untuk antrean aplikasi yang sudah digunakan bisa dijalankan dengan:

    ```bash
    php artisan queue:work database --queue=notifications,default --tries=3 --timeout=30
    ```

- Worker harus aktif biar job yang masuk ke antrean tersebut benar benar diproses. Kalau worker mati, job bisa menumpuk di database
- Antrean `device-sync` sama proses fisik ZKTeco dikoordinasikan dulu. Jangan mengubah konfigurasi atau ngejalanin tes perangkat tanpa koordinasi
- Sebelum menambah job baru, cek dulu class Job, tempat dispatch, nama queue, retry, timeout, dan cara menangani job gagal

### Rate limit dan CAPTCHA

- Login punya pembatasan percobaan berdasarkan kombinasi email dan IP. Dokumentasi API mencatat batas default 5 percobaan per 15 menit
- CAPTCHA punya ambang pemicu yang dikonfigurasi (default yang didokumentasikan: 3 kegagalan). Tapi kalo provider CAPTCHA belum tersedia/terkonfigurasi, API dapat menolak request sama `429`.
- Endpoint AI chat juga punya rate limit yang didokumentasikan 30 request per menit
- Jangan buat limiter baru buat endpoint yang sama sebelum cek middleware, route, dan konfigurasi yang sudah dipakai

### Keamanan, audit, dan histori

- Middleware yang tersedia itu `EnsureRole`, `EnsurePasswordChanged`, `RequestContext`, sama `SecurityHeaders`
- Password mengikuti policy dan hashing; histori password juga punya model/tabel tersendiri
- Aktivitas penting dicatat lewat `AuditService` dan model `AuditLog`
- Ada model/log untuk beberapa kebutuhan integrasi dan histori proses. Cek implementasinya sebelum menambah tabel log baru
- Validasi request mending tetep taro di Form Request atau `$request->validate()` sesuai pola controller yang udah ada

### Konfigurasi environment yang perlu diketahui

Contoh konfigurasi lokal yang sudah disesuaikan untuk project ini:

| Variable           | Nilai/penggunaan      | Catatan                                                                         |
| ------------------ | --------------------- | ------------------------------------------------------------------------------- |
| `DB_CONNECTION`    | `pgsql`               | Project menggunakan PostgreSQL                                                  |
| `SESSION_DRIVER`   | `file`                | Jangan diganti ke `database` tanpa menyesuaikan session handler dan skema tabel |
| `CACHE_STORE`      | `database`            | Menggunakan tabel `cache` dan `cache_locks`                                     |
| `QUEUE_CONNECTION` | `database`            | Job disimpan ke database sebelum diproses worker                                |
| `APP_DEBUG`        | `false` pada template | Untuk production, jangan aktifkan debug                                         |

Nilai `.env` lokal bisa berbeda sesuai komputer masing-masing

## 11. Yang perlu dicek khusus untuk bagian teknis

Bagian ini bukan berarti semuanya bermasalah, ini daftar pengecekan sebelum menambah implementasi:

1. Tes alur login, logout, logout-all, aktivasi akun, pergantian password, dan pencabutan sesi
2. Pastikan expiry token dan token aktivasi mengikuti konfigurasi yang dipakai environment
3. Cek pemakaian cache yang benar-benar ada, TTL, invalidasi, dan konsistensi datanya
4. Pastikan worker berjalan di environment pengujian/deploy dan job gagal bisa dipantau atau di-retry
5. Cek rate limit login dan AI sesuai konfigurasi; jangan mengandalkan UI untuk membatasi request
6. Pastikan audit log mencatat aksi penting tanpa menyimpan password, bearer token, atau rahasia lain

---

Intinya, jangan cuma cek endpoint/fitur yang kelihatan. Token, session, cache, queue, rate limit, keamanan, dan audit juga, Sebelum bikin yang baru, cek mekanisme yang sudah ada dan tes alurnya supaya nggak dobel kerja
