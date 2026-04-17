# Laravel Implementation Prompt
## PT. Indonesia Epson Industry — Goods Delivery & Receiving Verification System

---

You are an expert Laravel developer. Implement a full Laravel web application based on the system specification below. Follow all Laravel best practices and conventions throughout.

---

## Tech Stack

- **Framework:** Laravel 11
- **Database:** MySQL
- **Auth:** Laravel Breeze (with role-based middleware)
- **Frontend:** Blade + Tailwind CSS (mobile-friendly, since officers use mobile devices for scanning)
- **File Storage:** Laravel Storage (local or S3-compatible)
- **QR Code:** `simplesoftwareio/simple-qrcode` package (render QR from `qr_token` string)
- **Additional:** Laravel Sanctum for API auth (mobile scan endpoints)

---

## System Context

This system handles the verification of goods shipments between vendors and PT. Indonesia Epson Industry. Vendors submit shipment data online, a QR token is auto-generated, and factory officers scan that QR when goods arrive. Items are then scanned in batches using a mobile device camera, processed by an external Computer Vision (CV) API, and the system auto-detects discrepancies between what the vendor declared and what was actually received. Officers then take action on each discrepancy. If there is a mismatch, an R1 Return Delivery Order record is created (status tracking only, no document generation).

---

## System Flow (follow this exactly)

```
1. Vendor logs in → inputs shipment data (outbound) on website
2. On submit → system auto-generates qr_token (UUID/nanoid), locks the record
3. Vendor prints/downloads QR code rendered from qr_token → attaches to plastic packaging
4. Goods shipped → arrive at Epson factory
5. Officer scans QR on packaging → system finds outbound by qr_token → creates inbound record
   (updates: timestamp_terima, nama_penerima, lokasi_terakhir, status = arrived)
6. Officer takes goods out of packaging → starts scan session on mobile browser
7. Mobile device camera captures photo of items → photo uploaded to storage → sent to CV API
8. CV API returns: jumlah_terdeteksi, cacat_terdeteksi, confidence_score → saved to tabel_cv_result
9. Officer repeats scan until all boxes done (total_box_sudah_discan = total_box_expected)
10. When status_scan = 'selesai' → system auto-generates discrepancy records
11. Officer reviews results on website → takes action: approve / hold / return / recount
12. If mismatch → officer creates R1 document record (status only)
13. Vendor can log in and monitor: inbound status, discrepancy, R1 status
```

---

## Database — Migration Instructions

Create one migration file per table. Use the exact table names and column names as specified below. All primary keys use custom column names (not the Laravel default `id`). Set `$table->primary('ID_xxx')` accordingly.

### Table: tabel_user
```
ID_user         — unsignedBigInteger, PK, auto-increment
nama            — string(100)
email           — string(100), unique
password_hash   — string(255)
role            — enum: ['admin', 'petugas', 'supervisor', 'vendor']
ID_vendor       — unsignedBigInteger, nullable, FK → tabel_vendor (add after tabel_vendor migration)
created_at      — timestamp
```
> Note: This replaces Laravel's default `users` table. Update `config/auth.php` to use `tabel_user` with `ID_user` as the primary key and `password_hash` as the password column.

### Table: tabel_vendor
```
ID_vendor       — unsignedBigInteger, PK, auto-increment
nama_vendor     — string(100)
lokasi_vendor   — string(200)
kontak          — string(50)
email_vendor    — string(100)
aktif           — boolean, default true
```

### Table: tabel_barang
```
ID_barang       — unsignedBigInteger, PK, auto-increment
part_code       — string(50), unique
part_name       — string(100)
nama_barang     — string(150)
berat_gram      — float, nullable
satuan          — string(20)
deskripsi       — text, nullable
```

### Table: tabel_gudang
```
ID_gudang       — unsignedBigInteger, PK, auto-increment
nama_gudang     — string(100)
lokasi_gudang   — string(200)
kode_area       — string(20)
```

### Table: tabel_outbound
```
ID_outbound     — unsignedBigInteger, PK, auto-increment
no_pengiriman   — string(50), unique
ID_vendor       — unsignedBigInteger, FK → tabel_vendor
waktu_kirim     — dateTime
estimasi_tiba   — dateTime, nullable
lokasi_asal     — string(200)
status          — enum: ['draft', 'submitted', 'in_transit', 'arrived', 'verified'], default 'draft'
qr_token        — string(100), unique
dibuat_oleh     — unsignedBigInteger, FK → tabel_user
created_at      — timestamp
```

### Table: tabel_outbound_detail
```
ID_outbound_detail  — unsignedBigInteger, PK, auto-increment
ID_outbound         — unsignedBigInteger, FK → tabel_outbound
ID_barang           — unsignedBigInteger, FK → tabel_barang
quantity_outbound   — integer
quantity_per_box    — integer
jumlah_box          — integer
```

### Table: tabel_inbound
```
ID_inbound              — unsignedBigInteger, PK, auto-increment
ID_outbound             — unsignedBigInteger, FK → tabel_outbound, unique
ID_gudang               — unsignedBigInteger, FK → tabel_gudang
ID_vendor               — unsignedBigInteger, FK → tabel_vendor
timestamp_terima        — dateTime
nama_penerima           — string(100)
diterima_oleh           — unsignedBigInteger, FK → tabel_user
qr_scan_result          — string(255)
lokasi_terakhir         — string(200), nullable
total_box_expected      — integer
total_box_sudah_discan  — integer, default 0
status_scan             — enum: ['menunggu', 'sedang_diproses', 'selesai'], default 'menunggu'
created_at              — timestamp
```

### Table: tabel_inbound_detail
```
ID_inbound_detail   — unsignedBigInteger, PK, auto-increment
ID_inbound          — unsignedBigInteger, FK → tabel_inbound
ID_barang           — unsignedBigInteger, FK → tabel_barang
quantity_cv_detect  — integer, nullable
quantity_inbound    — integer, nullable
ada_cacat           — boolean, default false
catatan_cacat       — text, nullable
```

### Table: tabel_scan_session
```
ID_session      — unsignedBigInteger, PK, auto-increment
ID_inbound      — unsignedBigInteger, FK → tabel_inbound
ID_barang       — unsignedBigInteger, FK → tabel_barang
urutan_scan     — integer
waktu_mulai     — dateTime
waktu_selesai   — dateTime, nullable
status_sesi     — enum: ['berlangsung', 'selesai'], default 'berlangsung'
ID_user         — unsignedBigInteger, FK → tabel_user
```
> When `status_sesi` is updated to `'selesai'`, increment `total_box_sudah_discan` in `tabel_inbound`. If `total_box_sudah_discan = total_box_expected`, set `status_scan = 'selesai'` and trigger discrepancy generation.

### Table: tabel_foto
```
ID_foto         — unsignedBigInteger, PK, auto-increment
ID_session      — unsignedBigInteger, FK → tabel_scan_session
ID_inbound      — unsignedBigInteger, FK → tabel_inbound
file_url        — string(500)
uploaded_by     — unsignedBigInteger, FK → tabel_user
timestamp       — timestamp
related_type    — string(50)  — values: 'scan_barang', 'cacat', 'qr_packaging'
```

### Table: tabel_cv_result
```
ID_cv_result        — unsignedBigInteger, PK, auto-increment
ID_foto             — unsignedBigInteger, FK → tabel_foto
ID_session          — unsignedBigInteger, FK → tabel_scan_session
jumlah_terdeteksi   — integer
cacat_terdeteksi    — boolean
confidence_score    — float
model_version       — string(50)
processed_at        — timestamp
```

### Table: tabel_discrepancy
```
ID_discrepancy      — unsignedBigInteger, PK, auto-increment
ID_outbound_detail  — unsignedBigInteger, FK → tabel_outbound_detail
ID_inbound_detail   — unsignedBigInteger, FK → tabel_inbound_detail
quantity_outbound   — integer
quantity_inbound    — integer
selisih             — integer  — computed: quantity_inbound - quantity_outbound
status              — enum: ['match', 'mismatch', 'missing', 'over']
keterangan          — text, nullable
detected_at         — timestamp
```
> Status logic: selisih = 0 → 'match', selisih < 0 → 'missing', selisih > 0 → 'over', any other mismatch → 'mismatch'

### Table: tabel_discrepancy_action
```
ID_action       — unsignedBigInteger, PK, auto-increment
ID_discrepancy  — unsignedBigInteger, FK → tabel_discrepancy
action_type     — enum: ['approve', 'hold', 'return', 'recount']
action_by       — unsignedBigInteger, FK → tabel_user
action_time     — timestamp
notes           — text, nullable
status_action   — enum: ['pending', 'done', 'cancelled'], default 'pending'
```

### Table: tabel_dokumen_r1
```
ID_dokumen      — unsignedBigInteger, PK, auto-increment
ID_discrepancy  — unsignedBigInteger, FK → tabel_discrepancy
no_dokumen_r1   — string(50), unique
status_dokumen  — enum: ['draft', 'dikirim_ke_vendor', 'diproses_vendor', 'closing'], default 'draft'
dibuat_oleh     — unsignedBigInteger, FK → tabel_user
dibuat_at       — timestamp
keterangan      — text, nullable
```

### Table: tabel_notifikasi
```
ID_notif        — unsignedBigInteger, PK, auto-increment
ID_user         — unsignedBigInteger, FK → tabel_user
judul           — string(200)
pesan           — text
related_type    — string(50)  — values: 'inbound', 'discrepancy', 'dokumen_r1', 'scan_session'
related_id      — unsignedBigInteger
sudah_dibaca    — boolean, default false
created_at      — timestamp
```

---

## Models — Instructions

Create one Eloquent model per table. Follow these rules:

- Set `protected $table` to the exact table name (e.g. `'tabel_outbound'`)
- Set `protected $primaryKey` to the custom PK column name (e.g. `'ID_outbound'`)
- Set `public $incrementing = true` and `protected $keyType = 'int'`
- Set `public $timestamps = false` for tables without `updated_at` (most tables only have `created_at`)
- Define all `$fillable` fields
- Define all Eloquent relationships (`belongsTo`, `hasMany`, `hasOne`) matching the FK structure above
- For the `User` model: extend `Authenticatable`, set `protected $authPasswordName = 'password_hash'`

---

## Key Business Logic — Service Classes

Create a `app/Services/` directory with the following service classes:

### OutboundService
- `createOutbound(array $data)`: validates input, generates `no_pengiriman` (format: `DO-YYYYMMDD-XXXX`), generates `qr_token` using `Str::uuid()`, saves outbound + outbound_detail records, returns the created outbound
- `submitOutbound(int $id)`: changes status from `draft` to `submitted`, locks the record (no further edits allowed)

### InboundService
- `createInboundFromQr(string $qr_token, array $officerData)`: looks up outbound by `qr_token`, creates inbound record, copies `total_box_expected` from sum of `jumlah_box` in outbound_detail, sends notification to supervisor
- `completeScanSession(int $session_id)`: sets `status_sesi = 'selesai'`, increments `total_box_sudah_discan` on the parent inbound, checks if scanning is complete, if yes calls `DiscrepancyService::generateDiscrepancies()`

### DiscrepancyService
- `generateDiscrepancies(int $inbound_id)`: loops through each `tabel_inbound_detail`, compares `quantity_inbound` vs `quantity_outbound` from the matching `tabel_outbound_detail`, computes `selisih`, determines status automatically, inserts records into `tabel_discrepancy`, sends notifications to supervisor and vendor

### NotificationService
- `send(int $user_id, string $judul, string $pesan, string $related_type, int $related_id)`: inserts into `tabel_notifikasi`
- Trigger notifications at these points: inbound created, scan session completed, discrepancy generated, action taken on discrepancy, R1 status changed

---

## Controllers & Routes

### Web Routes (resources)
```
/login                          — AuthController
/dashboard                      — DashboardController (role-aware)
/outbound                       — OutboundController (vendor: create/view own; admin/supervisor: view all)
/outbound/{id}/qr               — show QR code rendered from qr_token
/inbound                        — InboundController
/inbound/scan-qr                — QR scanning landing page (officer scans QR here)
/scan-session                   — ScanSessionController
/discrepancy                    — DiscrepancyController
/discrepancy/{id}/action        — DiscrepancyActionController
/dokumen-r1                     — DokumenR1Controller
/notifikasi                     — NotifikasiController
/master/barang                  — BarangController (admin only)
/master/vendor                  — VendorController (admin only)
/master/gudang                  — GudangController (admin only)
```

### API Routes (for mobile scan flow, protected by Sanctum)
```
POST /api/scan-session/start        — start a new scan session
POST /api/scan-session/upload-foto  — upload photo, trigger CV processing
POST /api/scan-session/complete     — complete a session
GET  /api/inbound/{id}/status       — get current scan progress
```

---

## Role-Based Access (Middleware)

Create a `CheckRole` middleware. Apply as follows:

- `admin`: full access to everything including master data
- `supervisor`: view all inbound, discrepancy, dashboard; approve/reject actions; create R1
- `petugas`: scan QR, create inbound, run scan sessions, upload photos
- `vendor`: only see their own outbound records, inbound status, discrepancy status, and R1 status for their shipments

---

## QR Code Implementation

Install package:
```bash
composer require simplesoftwareio/simple-qrcode
```

In the QR view (`/outbound/{id}/qr`):
```blade
{!! QrCode::size(300)->generate($outbound->qr_token) !!}
```

The QR encodes only the `qr_token` string. When the officer scans it, the app reads that token and calls `InboundService::createInboundFromQr()` to look up the shipment.

---

## CV Integration

Create a `app/Services/CVService.php`:
- `processPhoto(string $file_path): array` — sends the photo to an external CV API endpoint (store the CV API URL in `.env` as `CV_API_URL`)
- Returns: `['jumlah_terdeteksi' => int, 'cacat_terdeteksi' => bool, 'confidence_score' => float, 'model_version' => string]`
- On success: saves result to `tabel_cv_result`, updates `quantity_cv_detect` and `ada_cacat` in the corresponding `tabel_inbound_detail`
- Handle API failure gracefully: log the error, set `quantity_cv_detect = null`, allow officer to input manually

---

## Dashboard

Build a role-aware dashboard with the following panels:

**For supervisor/admin:**
- Total shipments today vs this month
- Discrepancy count by status (match / mismatch / missing / over) — bar or donut chart
- Discrepancy list filterable by: vendor, date range, part type, status
- Pending actions (discrepancies with no action yet)
- R1 documents by status

**For vendor:**
- Their own shipment history and current status
- Any discrepancy on their shipments
- R1 document status

**For petugas:**
- Inbound queue (shipments arrived, pending scan)
- Active scan sessions

---

## Important Implementation Notes

1. **`tabel_user` replaces the default Laravel `users` table.** Update `config/auth.php`: set `model` to `App\Models\User`, `table` to `tabel_user`, and password field to `password_hash`. Update all auth scaffolding accordingly.

2. **Discrepancy auto-generation must be atomic.** Wrap `DiscrepancyService::generateDiscrepancies()` in a database transaction to prevent partial inserts.

3. **`quantity_inbound` is editable before final submit.** In the scan session UI, show the CV result as a pre-filled value but allow the officer to override it before clicking "Submit & Finalize." Once finalized, the value is locked and discrepancy is generated.

4. **Outbound records are immutable after `status = submitted`.** Add a check in `OutboundController@update` and the model to reject edits if status is not `draft`.

5. **`total_box_sudah_discan` must be updated inside a DB transaction** when a scan session completes, to prevent race conditions if two sessions finish simultaneously.

6. **Mobile-first UI for scan pages.** The pages under `/scan-session` and `/inbound/scan-qr` must be fully usable on a mobile browser — large tap targets, camera input (`<input type="file" accept="image/*" capture="environment">`), and minimal layout.

7. **Notifications are simple database notifications** (stored in `tabel_notifikasi`). No real-time websockets needed. Poll or refresh on page load. Show unread count in the navbar.

8. **CV API is external.** Do not implement the CV logic itself. Only implement the HTTP call to the CV endpoint, handle the response, and store results. Use Laravel's `Http` facade with a timeout and retry.
