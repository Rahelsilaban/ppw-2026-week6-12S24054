# SIPUS-Del — Tahap 3 (Web Services & RESTful API)

Sistem Informasi Perpustakaan Institut Teknologi Del (SIPUS-Del) Tahap 3 merupakan pengembangan lanjutan dari sistem perpustakaan Minggu 6. Pada tahap ini, SIPUS-Del dilengkapi dengan arsitektur **Web Services** hibrida: **SOAP Web Service** untuk integrasi sistem bebas pustaka akademik, **RESTful API Provider** untuk integrasi aplikasi mobile/kiosk katalog dan sirkulasi peminjaman berintegritas tinggi (ACID), serta **REST Service Consumer** untuk pengayaan metadata buku (OpenLibrary API) dan repositori (GitHub REST API).

---

## Identitas Pengembang
- **Nama:** Rahel Silaban
- **NIM:** 12S24054
- **Program Studi:** Sarjana Sistem Informasi / Informatika
- **Institut:** Institut Teknologi Del
- **Branch Pengembangan:** `week9-rest-api`
- **Versi Framework:** Laravel 13 (`^13.0` / `13.34.0`), PHP `8.4.26 (cli)`

---

## 1. Analisis Kebutuhan dan Feasibility Web Services

### 1.1 Analisis Kebutuhan Sistem
1. **Interoperabilitas Bebas Pustaka (SOAP):** Sistem Informasi Akademik (SIA/BAAK) memerlukan layanan terstandarisasi untuk memverifikasi apakah calon wisudawan masih memiliki pinjaman aktif di perpustakaan. SOAP dipilih karena menggunakan kontrak formal (WSDL), format XML yang ketat, dan protokol enterprise yang didukung sistem legacy akademik.
2. **Katalog & Kiosk Mandiri (REST):** Mahasiswa dan staf membutuhkan antarmuka yang ringan, cepat, dan stateless untuk pencarian katalog buku, detail ketersediaan stok, serta riwayat sirkulasi melalui aplikasi mobile dan kiosk kampus.
3. **Integritas Sirkulasi ACID:** Operasi peminjaman dan pengembalian buku rentan terhadap *race condition* (stok minus atau peminjaman ganda). Dibutuhkan mekanisme transaksi atomik dan penguncian baris (*pessimistic locking* via `lockForUpdate()`).
4. **Keamanan Tanpa Status (Stateless Security):** REST API tidak boleh bergantung pada session cookie browser, melainkan menggunakan token berbasis header `Authorization: Bearer <token>` via Laravel Sanctum.
5. **Standarisasi Galat RFC 7807:** Setiap respons kegagalan API harus menyajikan format *Problem Details* dengan `Content-Type: application/problem+json` agar klien dapat mendeteksi tipe dan detail kesalahan secara konsisten.
6. **Eksternal Metadata (Service Consumer):** Perpustakaan mengonsumsi data buku global (OpenLibrary API) untuk melengkapi metadata (jumlah halaman, edisi, dan penerbit) secara otomatis tanpa menimpa data primer lokal.

### 1.2 Analisis Kelayakan Teknis (Feasibility)
- **Framework & Runtime:** PHP 8.4 dan Laravel 13 menyediakan dukungan bawaan untuk manipulasi XML (`ext-dom`, `ext-simplexml`), transaksi database ACID (`DB::transaction`), HTTP Client berbasis Guzzle (`Http` facade), dan token personal Sanctum.
- **Backward Compatibility:** Seluruh fitur web UI Blade, seeder, dan controller dari Minggu 6 tetap berjalan normal 100% tanpa perubahan skema destruktif.

---

## 2. Perbedaan Service Provider dan Service Consumer

| Dimensi | Service Provider (Penyedia Layanan) | Service Consumer (Konsumen Layanan) |
|---|---|---|
| **Definisi** | Sistem yang membuka endpoint API untuk dikonsumsi oleh aplikasi lain. | Sistem yang memanggil atau mengonsumsi endpoint API milik pihak ketiga/eksternal. |
| **Peran di SIPUS-Del** | Menyediakan SOAP endpoint (`/soap/sipus`) dan REST API katalog/peminjaman (`/api/v1/bukus`, `/api/v1/peminjamans`). | Mengonsumsi OpenLibrary API (`openlibrary.org`) dan GitHub REST API (`api.github.com`). |
| **Tanggung Jawab** | Validasi input, otentikasi token, eksekusi query database, pengembalian status code yang tepat, pemeliharaan kontrak WSDL/OpenAPI. | Penanganan konektivitas jaringan, batas timeout, *rate limiting*, *token security*, dan *graceful fallback* saat API luar mengalami gangguan. |
| **Format Pertukaran** | Menghasilkan XML Envelope (SOAP) dan JSON / RFC 7807 (REST). | Mengurai (parse) payload JSON dari penyedia eksternal. |

---

## 3. Perbandingan SOAP vs REST

| Kriteria Evaluasi | SOAP Web Service | RESTful Web API |
|---|---|---|
| **Protokol / Gaya Arsitektur** | Protokol formal berbasis XML (W3C standard). | Gaya arsitektur (*architectural style*) berbasis HTTP standard. |
| **Kontrak Layanan** | Sangat ketat menggunakan kontrak WSDL (*Web Services Description Language*). | Fleksibel menggunakan OpenAPI 3.0 / Swagger specification. |
| **Format Pesan** | Wajib XML (*Envelope, Header, Body, Fault*). | Fleksibel (umumnya JSON, bisa XML, YAML, HTML). |
| **Metode HTTP** | Umumnya menggunakan POST untuk seluruh operasi pertukaran envelope. | Memanfaatkan kata kerja HTTP standar (GET, POST, PUT, PATCH, DELETE). |
| **Status Code & Galat** | Kesalahan dikemas dalam elemen `<SOAP-ENV:Fault>` di dalam Body XML. | Menggunakan HTTP status codes (200, 201, 204, 400, 401, 404, 409, 422, 500) dan RFC 7807. |
| **Stateful vs Stateless** | Dapat dirancang stateful atau stateless. | Murni stateless (*client-server separation*). |
| **Kasus Penggunaan di SIPUS** | Verifikasi Bebas Pustaka untuk integrasi sistem BAAK/Akademik (`/soap/sipus`). | Katalog buku, peminjaman mahasiswa, dan autentikasi pustakawan (`/api/v1/*`). |

---

## 4. Daftar Seluruh Endpoint SOAP dan REST API

### 4.1 SOAP Web Service
| Method | Endpoint | Fungsi | Proteksi | Format Request / Response |
|---|---|---|---|---|
| `GET` | `/soap/sipus.wsdl` | Menampilkan dokumen kontrak WSDL 1.1 | Publik | XML Kontrak WSDL (`text/xml`) |
| `POST` | `/soap/sipus` | Memeriksa kelayakan bebas pustaka mahasiswa berdasarkan NIM | Publik / Sistem | SOAP 1.1 XML Envelope (`text/xml`) |

### 4.2 REST API Provider (v1)
| Method | Endpoint | Fungsi | Proteksi | Keterangan / Status Code |
|---|---|---|---|---|
| `POST` | `/api/v1/auth/login` | Login pustakawan & menerbitkan Bearer Token | Publik | 200 OK (access_token) / 401 Unauthorized |
| `GET` | `/api/v1/auth/me` | Melihat profil pemilik token aktif | Bearer Token | 200 OK / 401 Unauthorized |
| `POST` | `/api/v1/auth/logout` | Mencabut (revoke) Bearer Token aktif | Bearer Token | 200 OK / 401 Unauthorized |
| `GET` | `/api/v1/bukus` | Katalog publik (pagination, search, filter, sort, HATEOAS) | Publik | 200 OK (`with('kategori')`, eager loaded) |
| `GET` | `/api/v1/bukus/{id}` | Detail buku dan riwayat peminjaman | Publik | 200 OK / 404 RFC 7807 |
| `POST` | `/api/v1/bukus` | Menambahkan judul buku baru | Bearer Token | 201 Created + Header `Location` |
| `PUT` | `/api/v1/bukus/{id}` | Full replacement data buku | Bearer Token | 200 OK / 422 (semua field wajib) |
| `PATCH` | `/api/v1/bukus/{id}` | Partial update atribut buku tertentu | Bearer Token | 200 OK (mendukung `sometimes|required`) |
| `DELETE` | `/api/v1/bukus/{id}` | Menghapus buku (blokir jika masih dipinjam) | Bearer Token | 204 No Content / 409 Conflict |
| `GET` | `/api/v1/bukus/{id}/referensi-eksternal` | Konsumsi OpenLibrary API via ISBN | Publik | 200 OK (fallback aman jika offline) |
| `GET` | `/api/v1/github/repo` | Konsumsi GitHub REST API (token disensor) | Publik / Token | 200 OK (`ghp_****...1234`) |
| `GET` | `/api/v1/peminjamans` | Daftar transaksi peminjaman (filter status/NIM) | Bearer Token | 200 OK |
| `GET` | `/api/v1/peminjamans/{id}` | Detail transaksi peminjaman | Bearer Token | 200 OK / 404 RFC 7807 |
| `POST` | `/api/v1/peminjamans` | Transaksi peminjaman ACID & lockForUpdate | Bearer Token | 201 Created + `Location` / 422 / 409 |
| `PATCH` | `/api/v1/peminjamans/{id}/kembalikan` | Transaksi pengembalian buku ACID | Bearer Token | 200 OK / 409 (mencegah double return) |

---

## 5. Panduan Instalasi dan Menjalankan Proyek Secara Aman

### 5.1 Prasyarat
- PHP 8.3 atau 8.4 dengan ekstensi `pdo_mysql`, `pdo_sqlite`, `curl`, `dom`, `libxml`, `simplexml`, `mbstring`, `openssl`.
- Composer 2.x.
- MySQL Server (atau SQLite untuk pengetesan otomatis).

### 5.2 Langkah Instalasi
```powershell
# 1. Unduh dan periksa dependensi
composer install

# 2. Siapkan environment (hanya jika .env belum ada)
cp .env.example .env
php artisan key:generate

# 3. Jalankan migrasi secara aman tanpa merusak data lama
php artisan migrate

# 4. Jalankan pengujian otomatis untuk memverifikasi fungsionalitas
php artisan test

# 5. Jalankan server lokal
php artisan serve
```

> [!WARNING]
> **Aturan Keamanan Database:** Jangan pernah menjalankan `php artisan migrate:fresh` pada lingkungan yang menyimpan data nyata, karena perintah tersebut akan menghapus seluruh data tabel. Gunakan `php artisan migrate` untuk menerapkan migrasi tambahan (seperti `personal_access_tokens`).

---

## 6. Akun Pengujian Pustakawan
Untuk pengujian autentikasi REST API dan Web Blade:
- **Email:** `pustakawan@del.ac.id`
- **Password:** `password123`
- **Role:** Administrator / Pustakawan SIPUS-Del

---

## 7. Panduan Penggunaan Postman Collection
File Postman Collection resmi tersedia pada:
[`docs/SIPUS-Del-API-v1.postman_collection.json`](docs/SIPUS-Del-API-v1.postman_collection.json)

### Langkah Penggunaan:
1. Buka aplikasi Postman, klik **Import**, lalu pilih file `docs/SIPUS-Del-API-v1.postman_collection.json`.
2. Pastikan variabel koleksi `base_url` bernilai `http://127.0.0.1:8000`.
3. Jalankan request **2.1 Login Pustakawan (Token Automation)**. Skrip uji (*Tests*) pada request ini akan secara otomatis mengekstrak token dari respons:
   ```javascript
   var json = pm.response.json();
   pm.collectionVariables.set("token", json.access_token);
   ```
4. Variabel `{{token}}` akan terisi otomatis dan digunakan oleh seluruh request privat lainnya (`POST buku`, `PUT`, `PATCH`, `DELETE`, `POST peminjaman`, `PATCH kembalikan`, `logout`).
5. Jalankan request secara berurutan atau gunakan fitur **Postman Collection Runner**.

---

## 8. Penjelasan Teknis Implementasi Arsitektur

### 8.1 Autentikasi Stateless dengan Laravel Sanctum
- REST API SIPUS-Del menggunakan `Laravel\Sanctum` untuk menerbitkan *Personal Access Token* yang disimpan dalam bentuk hash SHA-256 pada tabel `personal_access_tokens`.
- Setiap permintaan ke rute privat diverifikasi melalui middleware `auth:sanctum`.
- Logout mencabut token yang bersangkutan via `$request->user()->currentAccessToken()->delete()`, sehingga token tidak dapat digunakan kembali.

### 8.2 Transaksi ACID dan Pessimistic Locking
- Peminjaman dan pengembalian dibungkus dalam `DB::transaction(callback, 3)` untuk menjamin **Atomicity** dan **Durability**.
- Penguncian baris eksplisit `lockForUpdate()` diterapkan pada tabel `bukus` dan `peminjamans` untuk menjamin **Isolation** terhadap *race condition*.
- Urutan penguncian baris konsisten (selalu mengunci buku terlebih dahulu sebelum peminjaman) untuk mencegah *deadlock*.
- **Consistency** ditegakkan dengan menolak peminjaman jika stok < 1 (422) atau jika mahasiswa masih meminjam buku yang sama (409 Conflict), serta menolak pengembalian berulang (409 Conflict).

### 8.3 Dynamic HATEOAS (Hypermedia As The Engine Of Application State)
- Pada [`BukuResource.php`](app/Http/Resources/BukuResource.php): Tautan `pinjam_buku` disajikan di dalam `_links` secara dinamis **hanya jika** `stok > 0`. Jika stok 0, aksi pinjam tidak disediakan.
- Pada [`PeminjamanResource.php`](app/Http/Resources/PeminjamanResource.php): Tautan `kembalikan_buku` disajikan di dalam `_links` **hanya jika** status pinjaman bernilai `dipinjam`. Jika status telah `dikembalikan`, tautan tersebut otomatis dihilangkan.

### 8.4 RFC 7807 Global Error Handling (Problem Details)
- Dikonfigurasi secara global pada `bootstrap/app.php` melalui kelas [`App\Exceptions\ProblemDetails`](app/Exceptions/ProblemDetails.php).
- Menghasilkan `Content-Type: application/problem+json` dengan struktur wajib: `type`, `title`, `status`, `detail`, dan `instance`.
- Menangani seluruh galat API tanpa menghasilkan redirect HTML:
  - `401 Unauthorized`: Akses tanpa Bearer token atau token invalid.
  - `404 Not Found`: Model / endpoint tidak ditemukan.
  - `409 Conflict`: Benturan status pinjaman aktif atau duplikasi sirkulasi.
  - `422 Unprocessable Content`: Validasi atribut atau stok habis.
  - `500 Internal Server Error`: Kesalahan server internal.

### 8.5 Kontrak OpenAPI 3.0
- Kontrak resmi tersimpan pada [`docs/openapi-sipus-v1.yaml`](docs/openapi-sipus-v1.yaml).
- Memuat spesifikasi lengkap untuk seluruh endpoint SOAP dan REST, skema request/response, komponen security `bearerAuth`, dan contoh representasi data.

---

## 9. Tujuh Kelompok Bukti Pengujian Sistem

Seluruh pengujian dijalankan dan divalidasi secara langsung pada sistem aplikasi:

### Kelompok 1: Pengujian SOAP XML Envelope dan SOAP Fault
- **WSDL Contract:** Mengakses `GET /soap/sipus.wsdl` menghasilkan kontrak WSDL 1.1 valid dengan status HTTP 200.
- **Bebas Pustaka Terpenuhi:** Mengirimkan envelope dengan NIM `12S24054` yang tidak memiliki pinjaman aktif menghasilkan:
  ```xml
  <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="http://sipus.del.ac.id/soap">
      <SOAP-ENV:Body>
          <tns:CheckClearanceStatusResponse>
              <nim_peminjam>12S24054</nim_peminjam>
              <active_loans>0</active_loans>
              <is_cleared>true</is_cleared>
              <message>Mahasiswa dinyatakan bebas pustaka (tidak ada pinjaman aktif).</message>
          </tns:CheckClearanceStatusResponse>
      </SOAP-ENV:Body>
  </SOAP-ENV:Envelope>
  ```
- **SOAP Fault (Malformed XML):** Mengirimkan XML cacat menghasilkan HTTP 500 dengan SOAP Fault:
  ```xml
  <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
      <SOAP-ENV:Body>
          <SOAP-ENV:Fault>
              <faultcode>SOAP-ENV:Client</faultcode>
              <faultstring>Format XML request cacat atau tidak valid (Malformed XML).</faultstring>
          </SOAP-ENV:Fault>
      </SOAP-ENV:Body>
  </SOAP-ENV:Envelope>
  ```

### Kelompok 2: Login Bearer Token Sanctum
- **POST /api/v1/auth/login** mengembalikan HTTP 200 dengan plainTextToken:
  ```json
  {
    "success": true,
    "message": "Login pustakawan berhasil. Bearer token telah diterbitkan.",
    "token_type": "Bearer",
    "access_token": "1|5aYt4m...sampleToken",
    "user": { "id": 1, "name": "Pustakawan", "email": "pustakawan@del.ac.id" }
  }
  ```
- **Penolakan Galat (401 RFC 7807):** Login gagal atau request tanpa token mengembalikan `application/problem+json` dengan status 401.

### Kelompok 3: GET Katalog Buku dengan Pagination & HATEOAS
- **GET /api/v1/bukus?page=1&per_page=5** menghasilkan daftar koleksi buku dengan pagination `meta` dan tautan HATEOAS kontekstual:
  - Buku dengan `stok > 0` memuat `_links.pinjam_buku`.
  - Buku dengan `stok = 0` tidak menyertakan `_links.pinjam_buku`.

### Kelompok 4: POST Buku Baru dengan 201 Created & Header Location
- **POST /api/v1/bukus** dengan header `Authorization: Bearer <token>`:
  - Status: `201 Created`
  - Header: `Location: http://127.0.0.1:8000/api/v1/bukus/21`
  - Body: JSON representasi BukuResource lengkap beserta relasi kategori.

### Kelompok 5: Perbedaan PUT (Full Replacement) dan PATCH (Partial Update)
- **PUT /api/v1/bukus/{id}:** Menolak request jika salah satu atribut wajib dihilangkan (menghasilkan 422 Problem Details). Berhasil memperbarui data hanya jika seluruh atribut buku dikirimkan.
- **PATCH /api/v1/bukus/{id}:** Berhasil memperbarui sebagian atribut saja (misalnya hanya mengirimkan `{"stok": 15}`) tanpa menghilangkan atau membatalkan atribut judul/penulis/penerbit lainnya.

### Kelompok 6: Transaksi Peminjaman ACID, Penolakan Stok Habis (422), dan Konflik (409)
- **Peminjaman Berhasil (201 Created):** Stok buku didekremen secara aman, transaksi tercatat dengan kode pinjam unik, mengembalikan header `Location`.
- **Penolakan Stok Habis (422 RFC 7807):** Jika stok buku bernilai 0, transaksi otomatis dibatalkan (*rollback*) dengan status 422:
  ```json
  {
    "type": "https://tools.ietf.org/html/rfc4918#section-11.2",
    "title": "Unprocessable Content",
    "status": 422,
    "detail": "Stok buku habis. Transaksi peminjaman ditolak.",
    "instance": "/api/v1/peminjamans"
  }
  ```
- **Penolakan Pinjaman Ganda (409 Conflict):** Jika mahasiswa dengan NIM yang sama masih memiliki pinjaman aktif untuk buku yang sama, transaksi ditolak dengan HTTP 409 Conflict Problem Details.
- **Pengembalian Buku (200 OK):** Mengubah status menjadi `dikembalikan`, mengisi `tanggal_kembali`, dan menginkremen stok buku. Pengembalian kedua ditolak dengan HTTP 409 (*Double return prevention*).

### Kelompok 7: Pengujian REST API Consumer (OpenLibrary & GitHub API)
- **OpenLibrary API:** Mengonsumsi metadata buku eksternal via ISBN dengan timeout 8 detik dan fallback otomatis yang transparan.
- **GitHub REST API v3:** Mengonsumsi data repositori `Rahelsilaban/ppw-2026-week6-12S24054` dengan token disensor:
  ```json
  {
    "success": true,
    "source": "GitHub REST API v3",
    "authenticated": true,
    "token_used": "ghp_****...1234",
    "repository": {
      "name": "ppw-2026-week6-12S24054",
      "owner": "Rahelsilaban",
      "html_url": "https://github.com/Rahelsilaban/ppw-2026-week6-12S24054"
    }
  }
  ```

---

## 10. Status Pengujian Otomatis (Automated Testing)
Seluruh fungsionalitas diuji melalui PHPUnit Feature & Unit Test Harness:

```powershell
php artisan test
```

**Hasil:**
- **58 Tests Passed**
- **899 Assertions Passed**
- Waktu Eksekusi: ~7.7 detik
- Status: **Zero Failure, Zero Warning**

---

*SIPUS-Del — Sistem Informasi Perpustakaan Institut Teknologi Del © 2026 Rahel Silaban (12S24054)*
