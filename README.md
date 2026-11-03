# BioAttend-System

Project aplikasi absensi karyawan berbasis fingerprint

## Tech Stack

| Bagian         | Teknologi               |
| -------------- | ----------------------- |
| Backend        | Laravel 13              |
| Frontend       | React + Vite            |
| Database       | PostgreSQL 18           |
| Cache & Queue  | Redis (belum disiapkan) |
| API            | REST API                |
| Authentication | Laravel Sanctum         |
| Linter         | ESLint                  |

**Port Development:**

- Backend: `9271`
- Frontend: `9272`
- PostgreSQL: `5432` (Masih On Progres)

## Progress Sekarang

### Backend

- [x] Laravel 13.39.0 sudah dibuat
- [x] Port backend 9271 sudah diatur
- [x] PostgreSQL 18.1 on (On Progres)
- [x] API Ready, tapi sementara pake API Publick dulu aja buat tes pake data dummy. masih harus konfig lagi
- [x] `composer.json` dan `composer.lock` sudah dimasukkan
- [ ] Konfigurasi PostgreSQL ke Laravel
- [ ] Integrasi migration dan seeder
- [ ] Setup Redis
- [ ] Integrasi dan pengujian REST API
- [ ] Authentication dan role permission
- [ ] Pengujian business logic absensi dan potongan
- [ ] Konfigurasi CORS untuk frontend

### Frontend

- [x] React + Vite + Tailwind udah dibuat (sisanya install sendiri)
- [x] Port frontend 9272 udah diatur
- [ ] Build layout dan komponen UI
- [ ] Build halaman dashboard
- [ ] Build halaman karyawan, absensi, jadwal, dan pengajuan
- [ ] Integrasi dengan API backend

## Branch GitHub

| Branch                         | Kegunaan          |
| ------------------------------ | ----------------- |
| `main`                         | Kode utama        |
| `integration/backend-hardened` | Integrasi backend |
| `feature/*`                    | Fitur baru        |
| `fix/*`                        | Perbaikan bug     |

## Cara Coding dan Push

**Pertama kali clone repository:**

```bash
git clone https://github.com/Pride-CKN/BioAttend-System.git
cd BioAttend-System
```

**Sebelum mulai pekerjaan baru:**

```bash
git switch main
git pull origin main
```

**Bikin branch sendiri sesuai fitur:**

```bash
git switch -c feature/nama-fitur
```

Contoh:

- `feature/login-page`
- `feature/dashboard-hrd`
- `feature/attendance-page`
- `feature/employee-management`

**Kalau kerjaan udah selesai:**

```bash
git status
git add path/file-yang-diubah
git diff --cached
git commit -m "feat: nama fitur"
git push -u origin feature/nama-fitur
```

abis itu buka GitHub, bikin **Pull Request ke `main`**, terus tunggu direview sebelum di-merge

## Aturan Biar ngebuild gak Keroyokan wkwkwkwkw

1. Jangan coding atau push langsung ke `main`
2. Masing-masing pake branch sendiri
3. Satu branch fokus ke satu fitur
4. Sebelum mulai kerja, pull perubahan terbaru dari `main`
5. Jangan asal ngedit atau ngehapus file yang lagi dikerjain
6. Jangan pake `git push --force`
7. Jangan upload `.env`, password, API key, `vendor`, atau `node_modules`
8. Kalau ada conflict, jangan asal pilih versi kode. Diskusikan dulu
9. Kalau mau ngubah file yang sama, koordinasiin dulu
10. Semua perubahan masuk ke Pull Request

**Catatan:** Frontend udah boleh mulai build UI pake mock data, Backend masih gua integrasiin dan endpoint API belum final, jadi kalau butuh data atau endpoint tertentu langsung kabarin

Gass kroyok reponya, Yang penting jangan sampai kerjaan kita saling timpa wkwkwkwkwkw
