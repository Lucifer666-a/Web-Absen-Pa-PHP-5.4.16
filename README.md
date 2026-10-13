# Web Absen Pa — Changelog Implementasi

Dokumen ini mencatat semua perubahan aktual yang telah dikerjakan berdasarkan
GitHub Issues **#8–#17** (ditutup 07 Oktober 2026).
Semua nama tabel, nama fungsi, dan nama file di bawah ini telah diverifikasi
langsung dari kode.

---

## ✅ Issue #8 — Tab Absen Harian beserta Download Excel

**File:** `absen/admin/dashboard.php`

### Yang dikerjakan:

1. **Active tab**: `'harian'` ditambahkan ke array validasi
   `['rapat', 'apel', 'harian', 'rekap', 'users', 'pengaturan']`.

2. **Query backend** mengambil dari tabel **`absensi_harian`** (nama aktual di DB)
   dengan JOIN ke `users`, filter `user_id` dan rentang tanggal.

3. **Filter**: dropdown pegawai + input tanggal mulai & selesai.

4. **Tabel HTML**: kolom No, Tanggal, Nama Pegawai, Jabatan,
   Jam Masuk (`waktu_checkin`), Jam Pulang (`waktu_checkout`).

5. **Export Excel**: fungsi JS `exportHarianToExcel()` via SheetJS,
   nama file `absen_harian_YYYY-MM-DD.xlsx`.

---

## ✅ Issue #9 — Proteksi Akses Admin di Server (Login Check)

**File:** `absen/config/database.php`, `absen/admin/auth_check.php`, `absen/admin/dashboard.php`

### Yang dikerjakan:

1. **Fungsi `checkAdminSession()`** di `config/database.php`:
   - Cek `$_SESSION['admin_logged_in'] === true`
   - Jika tidak ada → `header("Location: ../admin/login.php"); exit;`

2. **`auth_check.php`** memanggil `require_once database.php` lalu `checkAdminSession()`.

3. **`dashboard.php`** baris pertama: `require_once 'auth_check.php'` sebelum output HTML.

---

## ✅ Issue #10 — Validasi Data Pegawai dan Fitur Nonaktifkan Akun

**File:** `absen/admin/manage_users.php`, `absen/config/database.php`, `absen/api/login.php`

### Yang dikerjakan:

1. **Validasi tambah/edit pegawai** (server-side): NIP wajib angka, Username tanpa spasi.
2. **Kolom `is_active`** (TINYINT default 1) ditambahkan otomatis via `ALTER TABLE`.
3. **Tombol Nonaktifkan/Aktifkan** di tabel pegawai (soft-delete).
4. **`api/login.php`** menolak login Android jika `is_active = 0`.

---

## ✅ Issue #11 — Rekap Per Pegawai Per Bulan dan Daftar Tidak Hadir

**File:** `absen/admin/dashboard.php`

### Yang dikerjakan:

1. **Tab "📊 Rekap"** dengan dua sub-section.
2. **Rekap bulanan per pegawai**: query dengan sub-select COUNT dari `presensi_apel`, `presensi`, dan `absensi_harian`.
3. **Daftar tidak hadir apel**: query `NOT IN` subquery untuk pegawai aktif yang tidak ada di `presensi_apel`.
4. **Card summary**: "Hadir X dari Y pegawai".

---

## ✅ Issue #12 — Perbaikan Filter dan Tampilan Tabel

**File:** `absen/admin/dashboard.php`

### Yang dikerjakan:

1. Filter pegawai di tab Apel.
2. Pencarian real-time dan pagination di tabel.
3. Daftar semua rapat beserta status, PIN, dan tombol aksi.
4. Kartu REST API dipindahkan ke tab Pengaturan & Log.

---

## ✅ Issue #13 — Fix: Label Sesi Apel Salah Hari

**File:** `absen/api/absen_apel.php`

### Yang dikerjakan:

1. Label hari dihitung dari tanggal aktual: `$dayOfWeek = (int)date('N', strtotime($tanggal));`.
2. Validasi jam & hari apel (Pagi: Senin 07:00–08:30, Sore: Jumat 16:00–17:30).

---

## ✅ Issue #14 — Perbaikan Format Excel (Header & Catatan Kaki)

**File:** `absen/admin/dashboard.php`

### Yang dikerjakan:

1. Baris judul + nama laporan + periode filter.
2. Header kolom dicetak tebal.
3. Catatan kaki rekap presensi resmi.

---

## ✅ Issue #15 — Log Aktivitas Admin, Ganti Password, dan Grafik

**File:** `absen/config/database.php`, `absen/admin/dashboard.php`

### Yang dikerjakan:

1. Tabel `log_aktivitas` dan fungsi `logAdminActivity()`.
2. Grafik Kehadiran (7 Hari Terakhir) via Chart.js.
3. Ganti Password Admin dan tabel Log Aktivitas.

---

## ✅ Issue #16 — Autentikasi API Android menggunakan JWT Bearer Token

**File:** `absen/config/jwt.php`, `absen/config/database.php`, `absen/api/login.php`, `absen/api/refresh_token.php`, `absen/api/logout.php`, `absen/api/user_status.php`, `absen/api/absen_harian.php`, `absen/api/absen_apel.php`, `absen/api/absen.php`

### Yang dikerjakan:

1. **JWT Helper (`config/jwt.php`)**: Implementasi native PHP JWT HS256.
2. **Skema DB `token_blacklist`**: Menyimpan token JTI yang di-revoke.
3. **Login API (`api/login.php`)**: Mengembalikan `access_token` (8 jam) & `refresh_token` (7 hari).
4. **Refresh & Logout**: `api/refresh_token.php` dan `api/logout.php`.
5. **Proteksi API**: Header `Authorization: Bearer <token>` diwajibkan di semua endpoint API.

---

## ✅ Issue #17 — Proteksi CSRF pada Semua Form POST Admin

**File:** `absen/config/csrf.php`, `absen/config/database.php`, `absen/admin/login.php`, `absen/admin/manage_users.php`, `absen/admin/manage_acara.php`, `absen/admin/dashboard.php`

### Yang dikerjakan:

1. **CSRF Helper (`config/csrf.php`)**:
   - `generateCsrfToken()`: Menghasilkan token acak 64 karakter heksadesimal (`bin2hex(random_bytes(32))`).
   - `csrfField()`: Menghasilkan elemen `<input type="hidden" name="csrf_token" value="...">`.
   - `verifyCsrfToken()`: Memverifikasi kesesuaian token request dengan session via `hash_equals()`.
   - `enforceCsrfCheck()`: Middleware penolak request POST tanpa token valid (HTTP 403 Forbidden).

2. **Integrasi Form & Handler Admin**:
   - Disisipkan `enforceCsrfCheck()` pada awal pemrosesan POST di `admin/login.php`, `admin/manage_users.php`, `admin/manage_acara.php`, dan `admin/dashboard.php`.
   - Disisipkan `<?= csrfField() ?>` pada seluruh elemen form POST di panel administrator.

---

## ✅ Issue #31 — Restriksi IP Jaringan Lokal Kantor pada Endpoint Presensi

**File:** `absen/config/ip_whitelist.php`, `absen/config/database.php`, `absen/api/absen.php`, `absen/api/absen_apel.php`, `absen/api/absen_harian.php`

### Yang dikerjakan:

1. **IP Whitelist Helper (`config/ip_whitelist.php`)**:
   - `getClientIp()`: Mengambil `$_SERVER['REMOTE_ADDR']` secara aman tanpa mempercayai header `X-Forwarded-For` yang rentan diajukan secara terlarang oleh klien.
   - `isIpAllowed($ip, $allowedNetworks)`: Memvalidasi alamat IP terhadap IP kantor utama **`192.168.5.100`**, rentan subnet Wi-Fi/LAN kantor (`192.168.5.0/24`), CIDR IPv4 umum (`192.168.0.0/16`, `10.0.0.0/8`), serta IP localhost (`127.0.0.1/32`, `::1/128`).
   - `enforceNetworkRestriction()`: Middleware penolak request dari IP di luar jaringan kantor dengan HTTP status `403 Forbidden` dan format JSON `code: "NETWORK_RESTRICTED"`.

2. **Integrasi Endpoint Absensi**:
   - Disisipkan `enforceNetworkRestriction()` pada endpoint `api/absen.php`, `api/absen_apel.php`, dan `api/absen_harian.php`.
   - Endpoint autentikasi (login, refresh token, status user) tetap terbuka tanpa pembatasan IP agar pengguna dapat masuk terlebih dahulu.

---

## Checklist Pengujian

| # | Uji | Hasil yang benar |
|---|---|---|
| 1 | Buka `dashboard.php` di incognito tanpa login | Redirect ke `login.php` |
| 2 | Kirim absen apel pagi hari Rabu | HTTP 400 — ditolak |
| 3 | Kirim absen apel sore hari Senin | HTTP 400 — ditolak |
| 4 | Kirim absen apel pagi Senin jam 09:00 | HTTP 400 — melewati batas jam |
| 5 | Nonaktifkan pegawai → login Android | Login ditolak (`is_active = 0`) |
| 6 | Tambah pegawai NIP berhuruf | Validasi server menolak |
| 7 | Tambah pegawai username berspasi | Validasi server menolak |
| 8 | Unduh Excel tab Harian | Ada judul, catatan kaki, nama file `absen_harian_*.xlsx` |
| 9 | Rekap bulan ini | Angka cocok isi `presensi_apel` + `absensi_harian` |
| 10 | Ganti password → login ulang | Password baru berlaku, tercatat di Log Aktivitas |
| 11 | Login API Android (`api/login.php`) | Mengembalikan `access_token` (JWT) & `refresh_token` |
| 12 | Akses API tanpa Header Authorization | HTTP 401 — Akses ditolak |
| 13 | Logout API (`api/logout.php`) | Token masuk `token_blacklist`, request berikutnya ditolak |
| 14 | Submit form POST admin tanpa CSRF token | HTTP 403 Forbidden — Request ditolak |
| 15 | Akses API Absen dari IP luar kantor (Public IP) | HTTP 403 Forbidden — `NETWORK_RESTRICTED` |

---

---

## ✅ Issue #18 — Embed Gambar Tanda Tangan Nyata ke Export Excel

**File:** `absen/admin/export_excel.php`, `absen/admin/dashboard.php`, `composer.json`

### Yang dikerjakan:

1. **Instalasi PhpSpreadsheet**: Menambahkan dependency `phpoffice/phpspreadsheet` via Composer dan mengaktifkan ekstensi PHP `gd` dan `zip`.
2. **Endpoint Server-Side (`admin/export_excel.php`)**:
   - Menghasilkan file Excel `.xlsx` menggunakan `PhpOffice\PhpSpreadsheet\Spreadsheet`.
   - Mengambil data presensi (Rapat, Apel, Absen Harian) langsung dari database.
   - Mendekode data Base64 tanda tangan pegawai menjadi gambar nyata dan menanamkannya langsung ke dalam sel kolom "Tanda Tangan" menggunakan `PhpOffice\PhpSpreadsheet\Worksheet\Drawing`.
   - Memformat header tebal dengan background warna elegan, borders, alignment rapi, judul dokumen, metadata filter/acara, serta catatan kaki administratif resmi.
   - Membersihkan file temporary gambar secara otomatis setelah proses unduh selesai (`register_shutdown_function`).
3. **Pembaruan Dashboard (`admin/dashboard.php`)**:
   - Menghubungkan tombol Download Excel pada tab Rapat, Apel, dan Absen Harian langsung ke endpoint server-side `export_excel.php` dengan parameter filter yang sesuai.

---

## Ringkasan File yang Diubah

| File | Issue |
|---|---|
| `absen/admin/dashboard.php` | #8, #9, #11, #12, #14, #15, #17, #18 |
| `absen/admin/export_excel.php` | #18 (Baru) |
| `absen/admin/manage_users.php` | #10, #17 |
| `absen/admin/manage_acara.php` | #17 |
| `absen/admin/login.php` | #10, #16, #17 |
| `absen/admin/auth_check.php` | #9 |
| `absen/api/absen_apel.php` | #13, #16, #31 |
| `absen/api/absen_harian.php` | #16, #31 |
| `absen/api/absen.php` | #16, #31 |
| `absen/api/user_status.php` | #16 |
| `absen/api/refresh_token.php` | #16 |
| `absen/api/logout.php` | #16 |
| `absen/config/csrf.php` | #17 (Baru) |
| `absen/config/jwt.php` | #16 |
| `absen/config/ip_whitelist.php` | #31 (Baru) |
| `absen/config/database.php` | #9, #10, #15, #16, #17, #31 |

---

*Terakhir diperbarui: 06 Oktober 2026.*