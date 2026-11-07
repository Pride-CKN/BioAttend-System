# BioAttend API — Catatan buat Integrasi Frontend

> Ini catatan biar kita gampang nyambungin frontend ke backend BioAttend. Ada daftar endpoint, siapa yang bisa pakai, contoh request, plus beberapa hal yang perlu diingat.
>
> **Base URL lokal:** `http://localhost:9271/api/v1`  
> **Health check:** `GET http://localhost:9271/api/health`  
> **Content-Type:** `application/json` (kecuali endpoint upload/unduhan)  
> **Authentication:** Laravel Sanctum Bearer Token  
> **Versi dokumen:** 1.0-draft

> Kalau ada bagian yang ditandai masih perlu dicek, jangan asal nebak payload-nya—kita cocokin dulu ke controller

---

## 1. Cara pakai API

### 1.1 Header yang perlu dikirim

Kalau endpoint-nya butuh login, tambahkan header ini:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

Kalau request-nya `multipart/form-data`, jangan tambahkan `Content-Type: application/json`

### 1.2 Bentuk response kalau berhasil

Bentuk respons sukses biasanya seperti ini:

```json
{
    "success": true,
    "message": "Berhasil",
    "data": {}
}
```

Kode status yang bakal sering ditemui:

- `200 OK`: request berhasil
- `201 Created`: data berhasil dibuat
- `202 Accepted`: request diterima/diproses secara asynchronous atau batch

Respons pagination:

```json
{
    "success": true,
    "message": "Berhasil",
    "data": [],
    "meta": {
        "total": 100,
        "per_page": 15,
        "current_page": 1,
        "last_page": 7,
        "from": 1,
        "to": 15
    }
}
```

`meta` cuma muncul di endpoint yang pakai pagination

### 1.3 Kalau request gagal

```json
{
    "success": false,
    "message": "Data tidak valid",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

Bagian `errors` bisa saja nggak ada kalau error-nya memang nggak punya rincian validasi.

Kalau ketemu kode error di bawah, tinggal sesuaikan respons di frontend:

- `401`: belum login/token tidak valid atau kedaluwarsa
- `403`: akun ini nggak punya akses ke aksi atau data yang diminta
- `404`: data atau sesi yang dicari nggak ketemu
- `409`: ada proses yang bentrok, atau status datanya belum cocok untuk aksi tersebut
- `422`: ada isian yang nggak lolos validasi atau aturan sistem
- `429`: request terlalu sering atau CAPTCHA perlu dicek
- `503`: layanan yang dibutuhkan belum siap atau belum disetel

### 1.4 Role dan batas akses

Satu akun cuma punya satu role aktif:

- `HRD`: akses pengelolaan sistem dan data lintas divisi
- `KADIV`: akses data divisinya dan proses persetujuan sesuai kewenangan
- `EMPLOYEE`: akses data pribadi

Ada endpoint yang dibatasi langsung lewat middleware di route, ada juga yang ngecek hak akses di controller. Jadi jangan langsung mengira endpoint tanpa `EnsureRole` itu bebas dipakai, ya

---

## 2. Login & akun

Endpoint buat login dan ngurus akun semuanya ada di `/auth`

| Method | Endpoint                | Siapa yang bisa akses | Catatan                                              |
| ------ | ----------------------- | --------------------- | ---------------------------------------------------- |
| POST   | `/auth/login`           | Publik                | Masuk dan dapat Bearer token                         |
| POST   | `/auth/activate`        | Publik                | Aktifkan akun pakai token                            |
| POST   | `/auth/forgot-password` | Publik                | Minta link reset password                            |
| POST   | `/auth/change-password` | Login                 | Ganti password                                       |
| POST   | `/auth/logout`          | Login                 | Hapus token yang sedang dipakai                      |
| POST   | `/auth/logout-all`      | Login                 | Keluar dari semua sesi dan hapus semua token sendiri |
| GET    | `/auth/sessions`        | Login                 | Lihat daftar sesi sendiri                            |
| DELETE | `/auth/sessions/{id}`   | Login                 | Hapus salah satu sesi sendiri                        |
| GET    | `/auth/profile`         | Login                 | Lihat profil akun                                    |
| PUT    | `/auth/profile`         | Login                 | Ganti nama atau email profil                         |

### 2.1 Login

`POST /auth/login`

Request:

```json
{
    "email": "pegawai@perusahaan.co.id",
    "password": "password"
}
```

Contoh response `200`:

```json
{
    "success": true,
    "message": "Login berhasil",
    "data": {
        "token": "SANCTUM_PLAIN_TEXT_TOKEN",
        "token_type": "Bearer",
        "expires_in": null,
        "user": {
            "id": 1,
            "name": "Nama Pengguna",
            "email": "pegawai@perusahaan.co.id",
            "is_active": true,
            "must_change_password": false,
            "last_login_at": "2026-11-06T10:00:00.000000Z",
            "roles": ["EMPLOYEE"],
            "employee": {
                "id": 1,
                "nip": "NIP-001",
                "full_name": "Nama Karyawan",
                "division": "Operasional",
                "division_id": 1,
                "unit": "Regional",
                "unit_id": 1,
                "position": "Staff",
                "employment_status": "ACTIVE"
            }
        }
    }
}
```

Yang perlu diingat:

- `expires_in` ngikutin pengaturan Sanctum. Nilainya bisa `null`
- `employee` bisa `null` kalau akun tersebut belum terhubung ke data karyawan
- Kalau `must_change_password=true`, arahkan pengguna ke halaman ganti password dulu sebelum masuk ke fitur aplikasi
- Login dibatasi maksimal 5 kali percobaan dalam 15 menit untuk kombinasi email dan IP yang sama
- Kalau gagal login sudah mencapai ambang CAPTCHA (default-nya 3 kali), frontend perlu mengirim `captcha_token`. Kalau provider CAPTCHA belum disetel, backend bisa membalas dengan `429`

### 2.2 Aktivasi akun

`POST /auth/activate`

Request:

```json
{
    "email": "pegawai@perusahaan.co.id",
    "token": "TOKEN_AKTIVASI",
    "password": "PasswordBaruYangMemenuhiPolicy"
}
```

Token aktivasi cuma bisa dipakai sekali dan ada batas waktunya. Setelah aktivasi berhasil, pengguna perlu login. Perlu diingat, aktivasi password nggak akan mengaktifkan lagi akun yang sudah dinonaktifkan HRD.

### 2.3 Lupa password

`POST /auth/forgot-password`

```json
{
    "email": "pegawai@perusahaan.co.id"
}
```

Kalau layanan email belum disiapkan di production, backend akan membalas `503` dan pengguna perlu menghubungi HRD. Jadi, jangan tampilkan pesan "email berhasil dikirim" sebelum backend benar-benar memberi respons sukses.

### 2.4 Ganti password

`POST /auth/change-password`

```json
{
    "current_password": "PasswordLama",
    "password": "PasswordBaruYangMemenuhiPolicy"
}
```

Kalau berhasil, semua token API dan sesi pengguna akan dicabut. Arahkan pengguna untuk login lagi.

### 2.5 Logout dan sesi login

- `POST /auth/logout`: menghapus token yang sedang dipakai.
- `POST /auth/logout-all`: menghapus semua token dan sesi milik pengguna.
- `GET /auth/sessions`: menampilkan gabungan token API (`tok_{id}`) dan sesi web (`sess_{id}`).
- `DELETE /auth/sessions/{id}`: isi ID dengan prefix `tok_` atau `sess_`. Sesi yang bisa dihapus cuma sesi milik akun yang sedang login.

Data sesi berisi `id`, `type`, `name`, `ip_address`, `last_used_at`, `created_at`, dan `is_current`.

### 2.6 Profil akun

`GET /auth/profile` bentuk data `user`-nya sama seperti bagian `user` di response login.

`PUT /auth/profile` bisa menerima field berikut (boleh kirim salah satu saja):

```json
{
    "name": "Nama Baru",
    "email": "emailbaru@perusahaan.co.id"
}
```

Lewat endpoint ini, yang bisa diubah cuma `name` dan `email`.

---

## 3. Dashboard

| Method | Endpoint              | Siapa yang bisa akses |
| ------ | --------------------- | --------------------- |
| GET    | `/dashboard/hrd`      | HRD                   |
| GET    | `/dashboard/kadiv`    | HRD, KADIV            |
| GET    | `/dashboard/employee` | HRD, KADIV, EMPLOYEE  |

Di dashboard HRD ada ringkasan karyawan, absensi hari ini, approval yang belum diproses, dan status hitung potongan periode berjalan.

Di dashboard KADIV ada ringkasan divisi, jumlah anggota tim, absensi hari ini, dan pengajuan yang masih nunggu persetujuan KADIV.

Dashboard employee menampilkan ringkasan absensi periode berjalan dan info potongan milik karyawan yang lagi login.

> Nama field dashboard masih perlu dicocokkan lagi dengan response asli dari controller. Jadi, jangan buru-buru mengunci interface TypeScript cuma berdasarkan ringkasan ini.

---

## 4. Employees

Bagian ini dipakai buat ngurus data karyawan, mulai dari lihat daftar, tambah karyawan, bikin akun, edit data, sampai import banyak karyawan sekaligus.

| Method | Endpoint              | Siapa yang bisa akses                        | Buat apa?                                  |
| ------ | --------------------- | -------------------------------------------- | ------------------------------------------ |
| GET    | `/employees`          | HRD, KADIV                                   | Lihat daftar karyawan sesuai aksesnya      |
| POST   | `/employees`          | HRD                                          | Tambah data karyawan                       |
| POST   | `/employees/register` | HRD                                          | Tambah karyawan sekaligus bikin akun login |
| POST   | `/employees/import`   | HRD                                          | Import sampai 1.000 karyawan sekaligus     |
| GET    | `/employees/{id}`     | Pengguna login, tetap dicek cakupan aksesnya | Lihat detail karyawan                      |
| PUT    | `/employees/{id}`     | HRD                                          | Edit data karyawan                         |
| DELETE | `/employees/{id}`     | HRD                                          | Nonaktifkan karyawan, bukan hapus permanen |

### 4.1 Lihat daftar karyawan

`GET /employees`

Bisa pakai query berikut kalau mau menyaring atau mencari data:

- `q`: cari berdasarkan nama, NIP, Employee ID, atau email.
- `office_id`: filter berdasarkan kantor.
- `division_id`: filter berdasarkan divisi.
- `unit_id`: filter berdasarkan unit.
- `employment_status`: filter status karyawan.
- `per_page`: jumlah data per halaman (default 15).

HRD bisa melihat semua karyawan. KADIV hanya bisa melihat karyawan yang masuk dalam divisinya.

### 4.2 Tambah data karyawan

`POST /employees`

Contoh request:

```json
{
    "nip": "NIP-001",
    "device_user_identifier": "001234",
    "full_name": "Nama Karyawan",
    "email": "pegawai@perusahaan.co.id",
    "phone": "081234567890",
    "gender": "MALE",
    "join_date": "2026-11-06",
    "employment_status": "ACTIVE",
    "office_id": 1,
    "division_id": 1,
    "unit_id": 1,
    "position_id": 1,
    "manager_id": null,
    "effective_from": "2026-11-06",
    "reason": "Pembuatan data karyawan"
}
```

Yang wajib diisi:

- `nip`: maksimal 30 karakter dan harus unik.
- `full_name`: nama lengkap karyawan.
- `office_id`: ID kantor.
- `division_id`: ID divisi.

Sisanya boleh dikirim sesuai kebutuhan:

- `device_user_identifier`: identifier/PIN pengguna di perangkat, opsional. Isinya angka 1–9 digit dan harus unik. Kirim sebagai string supaya angka nol di depan nggak hilang.
- `email`, `phone`, `gender`, `join_date`.
- `employment_status`: `ACTIVE`, `INACTIVE`, atau `RESIGNED`.
- `unit_id`: boleh `null` kalau karyawan nggak ditempatkan di unit.
- `position_id`, `manager_id`.
- `effective_from`: tanggal mulai penempatan. Kalau nggak dikirim, backend pakai tanggal hari ini.
- `reason`: alasan pembuatan/perubahan penempatan.

Employee ID dan histori penempatan bakal dibuat otomatis oleh backend. Kalau sukses, API balas `201 Created`.

### 4.3 Sekalian bikin akun login

`POST /employees/register`

Request-nya mirip dengan `POST /employees`, tapi ada beberapa aturan tambahan:

- `email` wajib dan harus unik di data karyawan maupun akun user.
- `role` wajib diisi `KADIV` atau `EMPLOYEE`.
- `office_id` dan `division_id` wajib; `unit_id` boleh `null`.
- `device_user_identifier` tetap opsional, formatnya angka 1–9 digit dan harus unik.
- `effective_from` dan `reason` boleh dikosongkan.

Kalau berhasil, respons `201 Created` berisi:

- `employee`: data karyawan.
- `user`: data akun dan role.
- `activation`: token aktivasi, waktu kedaluwarsa, dan info pengiriman.

Token aktivasi cuma muncul sekali, jadi simpan dan bagikan baik-baik. Password sementara dibuat otomatis oleh backend dan nggak ikut dikirim di response. Pas pertama kali login, akun baru wajib ganti password.

### 4.4 Lihat detail atau edit karyawan

**Lihat detail**

`GET /employees/{id}`

Endpoint ini ngasih data karyawan, termasuk kantor, divisi, unit, jabatan, akun/role, dan histori Employee ID. Tapi bukan berarti semua pengguna bisa lihat semua data—backend tetap ngecek hak aksesnya.

**Edit data**

`PUT /employees/{id}`

Field dasar bisa dikirim sebagian saja. Field yang bisa diedit:

- `nip`
- `device_user_identifier`
- `full_name`
- `email`
- `phone`
- `gender`
- `join_date`
- `employment_status`
- `position_id`
- `manager_id`

Khusus `device_user_identifier`:

- Boleh nggak dikirim kalau memang nggak diubah.
- Kirim `null` kalau mau mengosongkannya.
- Kalau diisi, harus angka 1–9 digit dan nggak boleh sama dengan identifier karyawan lain.

Kalau mau mengubah penempatan, kirim tiga field ini sekaligus:

```json
{
    "office_id": 1,
    "division_id": 1,
    "unit_id": 2,
    "effective_from": "2026-11-07",
    "reason": "Pindah unit kerja"
}
```

`unit_id` boleh `null` kalau memang nggak ada unit. `effective_from` dan `reason` boleh dikirim saat mengubah penempatan; kalau tanggal efektif nggak dikirim, backend pakai tanggal hari ini. Keduanya jangan dikirim kalau update-nya bukan untuk mengubah penempatan.

Kalau penempatan berubah, backend tetap lewat service Employee ID supaya kode karyawan dan histori penempatannya nggak berantakan.

Kalau status karyawan diubah menjadi selain `ACTIVE`, akun login terkait ikut dinonaktifkan, token dicabut, dan sesi login dihapus.

### 4.5 Kalau karyawan sudah nggak aktif

`DELETE /employees/{id}`

Endpoint ini **bukan buat hapus permanen**. Data karyawannya tetap ada, cuma statusnya diubah jadi `INACTIVE`. Akun login ikut dinonaktifkan, token dan sesi dicabut, tapi histori tetap disimpan.

### 4.6 Import banyak karyawan

`POST /employees/import`

Contoh request:

```json
{
    "rows": [
        {
            "nip": "NIP-002",
            "device_user_identifier": "001235",
            "full_name": "Nama Karyawan 2",
            "office_id": 1,
            "division_id": 1,
            "unit_id": 1,
            "effective_from": "2026-11-06"
        }
    ]
}
```

Yang perlu diperhatikan:

- `rows` harus berupa array dan isinya minimal 1, maksimal 1.000 karyawan.
- Tiap baris wajib punya `nip`, `full_name`, `office_id`, `division_id`, dan `unit_id`.
- `unit_id` wajib dikirim, tapi nilainya boleh `null`.
- `device_user_identifier` opsional. Kalau diisi, harus angka 1–9 digit, unik di database, dan nggak boleh duplikat di batch import yang sama.
- NIP yang sudah ada di database akan dilewati, bukan dibuat ulang.
- Employee ID dan histori penempatan dibuat otomatis oleh backend.

Kalau request-nya diterima, API membalas `202 Accepted` dan ngasih ringkasan:

- `created`: jumlah karyawan yang berhasil dibuat.
- `skipped`: jumlah baris yang dilewati.
- `device_push_jobs_queued`: jumlah antrean push yang dibuat, bukan jaminan bahwa perangkat sudah selesai tersinkron.

### 4.7 Catatan soal `device_user_identifier`

`device_user_identifier` adalah identifier/PIN yang dipakai untuk mencocokkan pengguna perangkat dengan data karyawan. Field ini beda dari `nip` dan Employee ID BioAttend.

Aturannya:

- Formatnya string angka, panjang 1–9 digit.
- Boleh `null` kalau belum ada identifier.
- Harus unik untuk setiap karyawan.
- Pertahankan angka nol di depan. Contohnya kirim `"001234"`, jangan `1234`.
- Detail komunikasi dan sinkronisasi langsung dengan mesin ditangani pada bagian integrasi perangkat terpisah.

---

## 5. Urusan absensi

| Method | Endpoint                               | Siapa yang bisa akses      |
| ------ | -------------------------------------- | -------------------------- |
| GET    | `/attendance/my`                       | Login, data sendiri        |
| GET    | `/attendance/{id}`                     | Login, pemeriksaan cakupan |
| GET    | `/attendance/recap`                    | HRD, KADIV                 |
| GET    | `/attendance/raw-logs`                 | HRD                        |
| POST   | `/attendance/ingest`                   | HRD                        |
| POST   | `/attendance/process`                  | HRD                        |
| POST   | `/attendance/reprocess`                | HRD                        |
| POST   | `/attendance/verify`                   | HRD                        |
| GET    | `/attendance/corrections`              | Login, pemeriksaan cakupan |
| POST   | `/attendance/corrections`              | Login, pemeriksaan cakupan |
| POST   | `/attendance/corrections/{id}/approve` | HRD, KADIV                 |
| POST   | `/attendance/corrections/{id}/reject`  | HRD, KADIV                 |
| GET    | `/attendance/unmatched-logs`           | HRD                        |
| POST   | `/attendance/unmatched-logs/{id}/map`  | HRD                        |

### 5.1 Lihat absensi sendiri

`GET /attendance/my`

Kalau perlu, endpoint ini juga bisa dikasih query: `start_date`, `end_date`, `page`, `per_page`. Default pagination 31 item per halaman. Akun harus terhubung ke employee.

### 5.2 Rekap absensi

`GET /attendance/recap`

Query yang harus ada:

- `start_date` wajib, format tanggal.
- `end_date` wajib dan harus sama atau setelah `start_date`.
- `employee_id` opsional.
- `division_id` opsional.
- `status` opsional.

Response-nya berisi periode, jumlah tiap status (total, present, late, early departure, absent, incomplete, leave, permission, holiday), dan `records`.

### 5.3 Detail absensi

`GET /attendance/{id}` ngasih detail record, employee, shift, dan correction terkait. Backend tetap ngecek apakah akun yang minta data punya akses ke employee tersebut.

### 5.4 Kirim data scan

`POST /attendance/ingest`

```json
{
    "device_id": 1,
    "employee_id": 1,
    "nip": "NIP-001",
    "scanned_at": "2026-11-06T08:00:00+07:00",
    "raw_payload": {}
}
```

- `scanned_at` wajib diisi dengan tanggal dan waktu yang valid.
- `device_id`, `employee_id`, `nip`, dan `raw_payload` boleh dikirim atau dikosongkan, sesuai validasi controller.
- Kalau `employee_id` nggak dikirim tapi `nip` ada, backend akan coba mencari employee dari NIP tersebut.
- Response `200` kalau employee ketemu dan log berhasil diproses. Kalau log belum bisa dicocokkan ke employee, response-nya `202`.

### 5.5 Proses dan cek absensi

`POST /attendance/process`

```json
{
    "work_date": "2026-11-06"
}
```

Memproses attendance untuk tanggal kerja dan menandai record yang belum lengkap. Respons `202`.

`POST /attendance/reprocess` nggak perlu body request. Backend akan memproses ulang log yang gagal, lalu mengirim jumlah `reprocessed` dengan status `202`.

`POST /attendance/verify`

```json
{
    "attendance_record_ids": [1, 2],
    "notes": "Sudah diperiksa HRD"
}
```

`attendance_record_ids` wajib berupa array dan minimal berisi satu ID absensi yang valid. `notes` boleh dikosongkan. Response-nya berisi jumlah `verified`.

### 5.6 Log mentah

`GET /attendance/raw-logs`

Kalau perlu, endpoint ini juga bisa dikasih query:

- `employee_id`
- `device_id`
- `processing_status`
- `is_duplicate` (boolean)
- `date`
- `per_page` (default 50)

### 5.7 Koreksi absensi

`GET /attendance/corrections`: query `status` opsional; pagination default 15.

`POST /attendance/corrections`:

```json
{
    "attendance_record_id": 1,
    "field": "check_in_at",
    "new_value": "2026-11-06 08:00:00",
    "reason": "Mesin gagal merekam scan"
}
```

- `attendance_record_id`: wajib dan harus ada.
- `field`: `check_in_at` atau `check_out_at`.
- `new_value`: wajib string maksimal 255 karakter.
- `reason`: wajib string.
- Pengguna hanya dapat mengajukan koreksi dalam cakupan aksesnya.

Approve: `POST /attendance/corrections/{id}/approve`  
Reject: `POST /attendance/corrections/{id}/reject` dengan body:

```json
{
    "note": "Alasan penolakan"
}
```

Untuk endpoint daftar dan mapping unmatched log, cek lagi nama field request serta response-nya di controller sebelum mulai dibuat di frontend.

---

## 6. Urusan potongan gaji

| Method | Endpoint                     | Siapa yang bisa akses      |
| ------ | ---------------------------- | -------------------------- |
| GET    | `/deductions`                | HRD, KADIV                 |
| POST   | `/deductions/calculate`      | HRD                        |
| GET    | `/deductions/export`         | HRD, KADIV                 |
| GET    | `/deductions/my`             | Login, data sendiri        |
| POST   | `/deductions/preview`        | HRD, KADIV                 |
| GET    | `/deductions/{id}`           | Login, pemeriksaan cakupan |
| POST   | `/deductions/{id}/confirm`   | HRD                        |
| GET    | `/deductions/{id}/histories` | Login, pemeriksaan cakupan |
| POST   | `/deductions/{id}/nominal`   | HRD                        |
| PUT    | `/deductions/{id}/nominal`   | HRD (kompatibilitas)       |

### 6.1 Lihat daftar dan detail potongan

`GET /deductions`: query opsional `period`, `status`, `page`, `per_page` (default 15). HRD melihat seluruh data; KADIV dibatasi divisinya.

`GET /deductions/my`: data milik employee yang login; query `period` opsional; pagination default 12.

`GET /deductions/{id}`: lihat detail potongan, mulai dari event, persentase, nominal, PPh, pembulatan, status, sampai rincian event. Aksesnya tetap dibatasi sesuai employee/divisi.

`GET /deductions/{id}/histories`: histori perubahan deduction, sesuai cakupan akses.

### 6.2 Hitung potongan

`POST /deductions/calculate`

```json
{
    "period": "2026-10",
    "office_id": 1,
    "division_id": 1,
    "unit_id": 1,
    "employee_ids": [1, 2],
    "recalculate": false
}
```

- `period` wajib format `YYYY-MM`.
- Filter `office_id`, `division_id`, `unit_id`, `employee_ids` opsional.
- `recalculate` opsional boolean.
- HRD only. Respons `202`.

### 6.3 Simulasi potongan

`POST /deductions/preview`

Query/body JSON:

```json
{
    "employee_id": 1,
    "period": "2026-10",
    "base_amount": 5000000
}
```

`employee_id` dan `period` wajib; `base_amount` opsional, minimal 0.

### 6.4 Atur nominal dan konfirmasi potongan

`POST /deductions/{id}/nominal`:

```json
{
    "base_amount": 5000000,
    "note": "Penyesuaian nominal gaji dasar"
}
```

`base_amount` wajib lebih dari 0; `note` opsional maksimal 1.000 karakter. `PUT` tersedia sebagai route kompatibilitas dengan aksi yang sama.

`POST /deductions/{id}/confirm`:

```json
{
    "note": "Dikonfirmasi HRD"
}
```

`note` boleh dikosongkan, maksimal 1.000 karakter. Kalau status atau prosesnya bentrok, backend bisa membalas `409`.

### 6.5 Export data

`GET /deductions/export?period=2026-10`

`period` wajib diisi dengan format `YYYY-MM`. Hasilnya berupa CSV sesuai hak akses. Di frontend, tangani response ini sebagai file/blob, bukan JSON biasa.

---

## 7. Pengajuan: Cuti, Izin, dan Lembur

### 7.1 Cuti

| Method | Endpoint                       | Siapa yang bisa akses   |
| ------ | ------------------------------ | ----------------------- |
| GET    | `/requests/leave`              | Login, cakupan employee |
| POST   | `/requests/leave`              | Login, cakupan employee |
| GET    | `/requests/leave-balances`     | Login, cakupan employee |
| POST   | `/requests/leave/{id}/approve` | HRD, KADIV              |
| POST   | `/requests/leave/{id}/reject`  | HRD, KADIV              |

`GET /requests/leave` query opsional: `status`, `start_date`, `end_date`; pagination default 15.

`POST /requests/leave`:

```json
{
    "employee_id": 1,
    "leave_type_id": 1,
    "start_date": "2026-11-10",
    "end_date": "2026-11-11",
    "reason": "Keperluan keluarga"
}
```

- `employee_id` opsional; default ke employee milik akun login.
- `leave_type_id`, `start_date`, `end_date` wajib.
- `end_date` tidak boleh sebelum `start_date`.
- `reason` opsional.
- Pengajuan cuti tahunan (`ANNUAL`) harus diajukan minimal 3 hari sebelum tanggal mulai.
- Batas hari per pengajuan dan sisa cuti akan dicek oleh backend.
- Respons `201`.

`GET /requests/leave-balances`: query `year` opsional; default tahun berjalan.

Kalau mau menolak pengajuan, kirim body ini:

```json
{
    "note": "Alasan penolakan"
}
```

### 7.2 Izin

| Method | Endpoint                            | Siapa yang bisa akses   |
| ------ | ----------------------------------- | ----------------------- |
| GET    | `/requests/permission`              | Login, cakupan employee |
| POST   | `/requests/permission`              | Login, cakupan employee |
| POST   | `/requests/permission/{id}/approve` | HRD, KADIV              |
| POST   | `/requests/permission/{id}/reject`  | HRD, KADIV              |

Endpoint `GET` bisa difilter pakai `status`, `start_date`, atau `end_date`.

`POST /requests/permission`:

```json
{
    "employee_id": 1,
    "type": "PERSONAL",
    "date": "2026-11-06",
    "start_time": "10:00",
    "end_time": "12:00",
    "reason": "Keperluan pribadi"
}
```

- `employee_id` opsional; default ke employee akun login.
- `type`: `PERSONAL`, `SICK`, atau `OTHER`.
- `date` wajib.
- `start_time` dan `end_time` nullable, tetapi jika salah satu diisi, pasangan waktunya harus ikut diisi.
- Format waktu `H:i` atau `H:i:s`.
- `reason` opsional maksimal 2.000 karakter.
- Backend akan ngecek durasinya, termasuk kalau jamnya melewati tengah malam.
- Respons `201`.

### 7.3 Lembur

| Method | Endpoint                          | Siapa yang bisa akses   |
| ------ | --------------------------------- | ----------------------- |
| GET    | `/requests/overtime`              | Login, cakupan employee |
| POST   | `/requests/overtime`              | KADIV                   |
| POST   | `/requests/overtime/{id}/approve` | HRD, KADIV              |
| POST   | `/requests/overtime/{id}/reject`  | HRD, KADIV              |

Endpoint `GET` bisa difilter pakai `status`, `start_date`, atau `end_date`.

`POST /requests/overtime`:

```json
{
    "employee_id": 1,
    "date": "2026-11-06",
    "start_time": "17:00",
    "end_time": "19:00",
    "reason": "Penyelesaian pekerjaan"
}
```

- Route create hanya untuk KADIV.
- `employee_id` opsional dan default ke employee yang terhubung ke akun KADIV.
- `date`, `start_time`, `end_time` wajib.
- `reason` opsional.
- Durasi minimal 60 menit dan maksimal 12 jam.
- Tanggal kerja maksimal 7 hari ke belakang sesuai validasi controller.
- Request tidak boleh overlap dengan request lain yang belum ditolak pada tanggal yang sama.
- Respons `201`.

Saat approve, boleh kirim `approved_minutes` (angka minimal 1 dan nggak boleh lebih lama dari durasi pengajuan). Kalau reject, `note` wajib diisi.

---

## 8. Jadwal kerja

| Method | Endpoint                 | Siapa yang bisa akses      |
| ------ | ------------------------ | -------------------------- |
| GET    | `/schedules`             | HRD, KADIV                 |
| POST   | `/schedules/assign`      | HRD, KADIV                 |
| POST   | `/schedules/bulk-assign` | HRD, KADIV                 |
| GET    | `/schedules/histories`   | HRD, KADIV                 |
| GET    | `/schedules/my`          | Login, employee            |
| GET    | `/schedules/resolve`     | Login, pemeriksaan cakupan |
| POST   | `/schedules/validate`    | HRD, KADIV                 |

### 8.1 Lihat jadwal

`GET /schedules` query opsional: `work_date`, `start_date`, `end_date`, `employee_id`, `page`, `per_page`. Default pagination 31.

`GET /schedules/my` bisa difilter pakai `start_date`, `end_date`, `page`, dan `per_page`. Akunnya harus terhubung ke employee.

### 8.2 Atur satu jadwal

`POST /schedules/assign`

```json
{
    "employee_id": 1,
    "work_date": "2026-11-06",
    "shift_id": 1,
    "notes": "Jadwal reguler"
}
```

`employee_id`, `work_date`, dan `shift_id` wajib; `notes` opsional. Backend memeriksa cakupan employee.

### 8.3 Atur jadwal sekaligus

`POST /schedules/bulk-assign`

```json
{
    "employee_ids": [1, 2, 3],
    "shift_id": 1,
    "start_date": "2026-11-09",
    "end_date": "2026-11-13",
    "include_weekend": false
}
```

- `employee_ids`: array wajib, 1–500 ID unik.
- `shift_id`, `start_date`, `end_date` wajib.
- `include_weekend` boolean opsional.
- Rentang maksimal 366 hari.
- Semua employee yang dipilih harus masuk dalam cakupan akses akun yang melakukan request.
- Response `202` ngasih jumlah jadwal yang berhasil di-assign lewat field `assigned`.

### 8.4 Cek jadwal

`GET /schedules/resolve?employee_id=1&date=2026-11-06`

Response-nya berisi employee, tanggal, scope, status hari libur/hari kerja, toleransi keterlambatan, work days, dan shift hasil pengecekan.

`POST /schedules/validate`:

```json
{
    "scope_type": "DIVISION",
    "scope_id": 1,
    "effective_from": "2026-11-01",
    "effective_to": "2026-12-31"
}
```

- `scope_type`: `COMPANY`, `DIVISION`, atau `EMPLOYEE`.
- `scope_id` wajib untuk scope `DIVISION` dan `EMPLOYEE`.
- `effective_from` wajib; `effective_to` opsional dan tidak boleh sebelum tanggal mulai.
- Kalau konfigurasi jadwal bentrok, backend bisa membalas `422` dan menyertakan info `conflict`.

### 8.5 Pengaturan jadwal kerja

| Method | Endpoint                               | Siapa yang bisa akses       |
| ------ | -------------------------------------- | --------------------------- |
| GET    | `/work-schedule-configs/company`       | Login                       |
| PUT    | `/work-schedule-configs/company`       | HRD                         |
| GET    | `/work-schedule-configs/division/{id}` | HRD, KADIV (cakupan divisi) |
| PUT    | `/work-schedule-configs/division/{id}` | HRD, KADIV (cakupan divisi) |
| GET    | `/work-schedule-configs/employee/{id}` | Login, pemeriksaan cakupan  |
| PUT    | `/work-schedule-configs/employee/{id}` | HRD                         |

Body PUT konfigurasi:

```json
{
    "shift_id": 1,
    "work_days": [1, 2, 3, 4, 5],
    "late_tolerance_minutes": 10,
    "effective_from": "2026-11-01",
    "effective_to": null,
    "reason": "Penyesuaian jadwal kerja"
}
```

Field:

- `shift_id`: nullable integer, jika diisi harus ada pada master shifts.
- `work_days`: nullable array; setiap angka 1–7.
- `late_tolerance_minutes`: nullable integer minimal 0.
- `effective_from`: wajib.
- `effective_to`: nullable, tidak boleh sebelum `effective_from`.
- `reason`: nullable string.

Urutan prioritas konfigurasi: EMPLOYEE > DIVISION > COMPANY. Kalau ada beberapa konfigurasi, yang paling spesifik yang dipakai.

Nggak ada endpoint `GET /work-schedule-configs/histories`. Kalau mau lihat histori jadwal, pakai `GET /schedules/histories`.

---

## 9. Data master

Endpoint data master di bagian ini khusus HRD, ya.

| Method | Endpoint                  | Keterangan         |
| ------ | ------------------------- | ------------------ |
| GET    | `/master/{resource}`      | Daftar master      |
| POST   | `/master/{resource}`      | Buat master        |
| GET    | `/master/{resource}/{id}` | Detail             |
| PUT    | `/master/{resource}/{id}` | Update             |
| DELETE | `/master/{resource}/{id}` | Nonaktifkan master |

Resource yang bisa dipakai:

- `offices`
- `divisions`
- `units`
- `positions`
- `shifts`
- `holidays`
- `leave-types`
- `devices`

### 9.1 Filter daftar

- `q`: pencarian.
- `is_active`: filter status aktif.
- `division_id`: filter khusus resource `units`.
- `all=true`: ambil semua data tanpa pagination.
- Kalau `all=true` nggak dikirim, data ditampilkan per halaman (default 15 item).

### 9.2 Field buat tambah atau edit data master

**offices**

- `code`: wajib, satu digit.
- `name`: wajib.
- `is_active`: boolean opsional.

**divisions**

- `code`: wajib, unik, maksimal 30 karakter.
- `name`: wajib.
- `head_id`: nullable, ID employee.
- `is_active`: boolean opsional.

**units**

- `code`: wajib, unik, maksimal 30 karakter.
- `name`: wajib.
- `division_id`: wajib.
- `head_id`: nullable, ID employee.
- `employee_code_digit`: wajib integer 1–9.
- `is_active`: boolean opsional.

**positions**

- `code`: wajib, unik, maksimal 30 karakter.
- `name`: wajib.
- `level`: nullable, maksimal 30 karakter.
- `is_active`: boolean opsional.

**shifts**

- `code`: nullable.
- `name`: wajib.
- `start_time`, `end_time`: wajib, format `H:i` atau `H:i:s`.
- `late_tolerance_minutes`: integer minimal 0.
- `min_work_hours`: numeric minimal 0.
- `is_night_shift`, `is_active`: boolean opsional.

**holidays**

- `date`: wajib dan unik.
- `name`: wajib.
- `type`: nullable.
- `is_paid`: boolean opsional.
- `year`: nullable integer.
- `is_active`: boolean opsional.

**leave-types**

- `code`: wajib dan unik.
- `name`: wajib.
- `default_quota`: integer minimal 0.
- `max_days_per_request`: integer minimal 1.
- `requires_attachment`, `is_active`: boolean opsional.

**devices**

- `name`: wajib.
- `model`: nullable.
- `ip_address`: wajib dan harus berupa IP valid.
- `port`: integer 1–65535.
- `commkey`, `location`: nullable.
- `is_active`: boolean opsional.

Aksi `DELETE` untuk data master cuma menonaktifkan data (`is_active=false`), bukan menghapusnya permanen. Khusus unit, divisi dan digitnya nggak bisa bebas diubah kalau unit itu sudah pernah dipakai karyawan atau tercatat di histori Employee ID.

---

## 10. Notifikasi

| Method | Endpoint                   | Siapa yang bisa akses |
| ------ | -------------------------- | --------------------- |
| GET    | `/notifications`           | Login                 |
| POST   | `/notifications/read-all`  | Login                 |
| POST   | `/notifications/{id}/read` | Login                 |

`GET /notifications` bisa difilter pakai `is_read` dan default-nya menampilkan 20 data per halaman. `read-all` menandai semua notifikasi milik akun yang login sebagai sudah dibaca. Sementara endpoint `read` menandai satu notifikasi tertentu sebagai sudah dibaca. Backend tetap ngecek kepemilikan notifikasinya.

Bentuk lengkap data notifikasi masih perlu dicek ke response asli dari controller sebelum tipe frontend dibuat final.

---

## 11. Laporan

| Method | Endpoint                 | Siapa yang bisa akses |
| ------ | ------------------------ | --------------------- |
| GET    | `/reports`               | HRD, KADIV            |
| POST   | `/reports`               | HRD, KADIV            |
| GET    | `/reports/{id}/download` | HRD, KADIV            |

`GET /reports` menampilkan daftar laporan. HRD bisa lihat semua laporan, sedangkan role selain HRD cuma bisa lihat laporan yang dibuat sendiri.

`POST /reports`:

```json
{
    "name": "Laporan Absensi Oktober",
    "type": "ATTENDANCE",
    "period_start": "2026-10-01",
    "period_end": "2026-10-31",
    "format": "XLSX"
}
```

Field yang dicek backend:

- `name` wajib.
- `type`: `ATTENDANCE`, `DEDUCTION`, `OVERTIME`, atau `AUDIT`.
- `period_start`, `period_end` wajib.
- `format`: `CSV`, `PDF`, atau `XLSX`.

Catatan: saat dokumen ini dibuat, endpoint pembuatan report mengembalikan status/metadata laporan dengan status `DONE` dan `file_path` masih `null`. Jadi, endpoint `/reports/{id}/download` jangan dulu dianggap pasti mengirim file. Cek implementasi file stream-nya sebelum bikin fitur download di frontend.

---

## 12. Asisten AI

Semua endpoint AI di bagian ini cuma bisa diakses HRD.

| Method | Endpoint                          | Siapa yang bisa akses    |
| ------ | --------------------------------- | ------------------------ |
| POST   | `/ai/chat`                        | HRD, rate limit 30/menit |
| GET    | `/ai/conversations`               | HRD                      |
| GET    | `/ai/conversations/{id}/messages` | HRD                      |

Fitur AI cuma tersedia buat HRD. Untuk bentuk request dan response chat, conversation, dan messages, cek lagi `AiController` sebelum frontend menetapkan payload dan tipe datanya.

---

## 13. Sinkronisasi perangkat

Semua endpoint perangkat di bagian ini cuma bisa diakses HRD.

| Method | Endpoint                   | Keterangan               |
| ------ | -------------------------- | ------------------------ |
| GET    | `/devices/offline`         | Daftar perangkat offline |
| POST   | `/devices/push-employee`   | Minta push data employee |
| GET    | `/devices/sync-histories`  | Histori sinkronisasi     |
| GET    | `/devices/{id}/checkpoint` | Checkpoint perangkat     |
| GET    | `/devices/{id}/histories`  | Histori perangkat        |
| GET    | `/devices/{id}/logs`       | Log integrasi perangkat  |
| POST   | `/devices/{id}/retry`      | Retry proses             |
| POST   | `/devices/{id}/sync`       | Jalankan sinkronisasi    |
| POST   | `/devices/{id}/test`       | Tes koneksi              |

Sebelum bikin tombol atau tampilan untuk fitur perangkat, cocokkan dulu payload, response, dan efek tiap aksi dengan `DeviceSyncController`. Endpoint test/sync juga bukan jaminan perangkat fisiknya sudah tersambung ke environment frontend.

---

## 14. Pengaturan sistem, user, role, permission, dan audit

Daftar route di bawah ini khusus HRD, kecuali kalau ada keterangan lain:

| Method | Endpoint                     | Keterangan                 |
| ------ | ---------------------------- | -------------------------- |
| GET    | `/audit-logs`                | Daftar audit log           |
| GET    | `/audit-logs/export`         | Export audit log           |
| GET    | `/permissions`               | Daftar permission          |
| GET    | `/roles`                     | Daftar role dan permission |
| PUT    | `/roles/{id}/permissions`    | Atur permission role       |
| GET    | `/settings`                  | Daftar setting             |
| PUT    | `/settings`                  | Update setting             |
| GET    | `/users`                     | Daftar user                |
| POST   | `/users`                     | Buat user                  |
| POST   | `/users/{id}/reset-password` | Reset password             |
| PATCH  | `/users/{id}/role`           | Ubah role                  |
| PATCH  | `/users/{id}/status`         | Ubah status akun           |

Beberapa aturan yang sudah dicek:

- Saat bikin user, akun harus dihubungkan ke employee yang aktif dan role yang dipilih adalah `KADIV` atau `EMPLOYEE`.
- Akun KADIV harus punya divisi.
- Saat akun dibuat, backend mengembalikan token aktivasi yang cuma ditampilkan sekali. Masa berlakunya 72 jam dan HRD membagikannya secara manual.
- Saat role diganti, role aktif disesuaikan menjadi satu role dan token/sesi lama dicabut.
- Role atau status akun HRD nggak bisa diubah lewat alur ini.
- Saat password di-reset, aturan password tetap berlaku, pengguna wajib mengganti password, dan token/sesi lama dicabut.
- Update permission role memakai array berisi ID permission.
- Update setting memakai array objek dengan bentuk `{key, value, type}`.
- Audit log bisa difilter lewat `module`, `action`, `user_id`, dan `date_from`. Default-nya 50 data per halaman, dan export-nya berupa CSV.

Kalau mau bikin form user/role/settings, cocokkan lagi nama field request dan bentuk response-nya dengan method di controller terkait.

---

## 15. Cek API

`GET /api/health`

Endpoint health ada di luar prefix `/api/v1`. Pakai endpoint ini buat cek apakah API/service merespons, bukan buat mengecek status login pengguna.

---

## 16. Catatan buat yang ngerjain frontend

1. Simpan token dengan cara yang aman di frontend. Untuk request yang butuh login, sertakan header `Authorization: Bearer ...`.
2. Kalau dapat `401`, arahkan pengguna ke halaman login dan bersihkan state autentikasinya.
3. Kalau dapat `403`, tampilkan pesan bahwa aksesnya ditolak. Menyembunyikan tombol di UI saja belum cukup buat jadi pengaman.
4. Kalau dapat `422`, tampilkan pesan error sesuai field dari `errors`.
5. Kalau dapat `409`, kasih tahu ada konflik data/proses, lalu minta pengguna memuat ulang data atau statusnya.
6. Untuk pagination, ambil informasinya dari `meta.current_page`, `meta.last_page`, `meta.per_page`, dan `meta.total`.
7. Kalau ambil export CSV, tangani response-nya sebagai blob/file, bukan sebagai JSON biasa.
8. Tulis enum persis seperti yang diminta backend. Contohnya `ACTIVE`, `INACTIVE`, `RESIGNED`, `MALE`, dan `FEMALE`.
9. Jangan anggap semua `DELETE` berarti data hilang permanen. Untuk employee dan data master, endpoint ini cuma menonaktifkan data.
10. Hak akses dan batas data tetap dicek backend. Frontend tinggal menyesuaikan tampilan dan navigasinya.

---

## 17. Bagian yang masih perlu kita cek lagi

Route untuk bagian-bagian ini sudah dicek, tapi detail payload dan response controller-nya belum semuanya kita cocokkan:

- AI: `AiController` (chat, conversations, messages).
- Sinkronisasi perangkat: `DeviceSyncController` (offline, push, histories, checkpoint, retry, sync, test).
- Unmatched log absensi: daftar dan proses mapping.
- Bentuk data notifikasi.
- Detail response dashboard.
- Bentuk response report dan fitur download-nya.
- Field request/response untuk endpoint users, roles, permissions, dan settings.

Pakai dokumen ini buat mulai nyambungin frontend ke backend. Bagian yang disebut di atas masih perlu kita cek ke controller sebelum payload dan response-nya dipakai sebagai patokan final.
