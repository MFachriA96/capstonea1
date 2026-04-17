# Case Study — PT. Indonesia Epson Industry
## Topic A.1: Goods Delivery and Receiving Verification System

---

## Problem Statement

Vendors create shipments (Shipment/Delivery Order) with a list of boxes and their contents (parts & quantities). However, the current system does not digitally lock the shipment data. When the boxes arrive at the factory, there is no automatic mechanism to compare the vendor's outbound data with the factory's inbound data.

As a result:

1. Discrepancies (match/mismatch/missing/over) are often detected too late.
2. There is no digital evidence (photos, timestamps, location) for audit and claims.
3. Follow-up processes (approve/hold/return/recount) are unstructured and slow.
4. There is no dashboard to monitor discrepancies by vendor/date/line/part type.
5. Losses and fixed costs due to discrepancies are difficult to reduce because of the lack of proper data analytics.

---

## Proposed System Flow

```
Start
  → Vendor inputs data into the website
    (system automatically generates a QR code token containing:
     shipment number, items to be shipped (outbound), quantity, date, and location)
  → Vendor sends the goods
  → Goods arrive at Epson
  → Officer scans the QR code on the plastic packaging
    (updates: received date, location, received status)
  → Goods are taken out of the plastic packaging to be scanned (divided into several parts)
  → Mobile device accesses the website
  → Mobile device scans the items
  → Captured images are automatically stored in the database and processed by CV system
  → CV system detects the number of items and identifies any defects
  → Scanning process is repeated until all items in the package are processed
  → Calculation results are displayed on the website
  → Officer takes action (approve, return, or other)
  → IF mismatch: proceed to R1 document creation (Return Delivery Order) — status only, no document generation
→ Finish
```

---

## Database Design — Final Version

### tabel_user
| Column | Type | Description |
|---|---|---|
| ID_user | INT, PK, AUTO_INCREMENT | — |
| nama | VARCHAR(100) | — |
| email | VARCHAR(100), UNIQUE | — |
| password_hash | VARCHAR(255) | — |
| role | ENUM('admin', 'petugas', 'supervisor', 'vendor') | Vendor has account for live monitoring |
| ID_vendor | INT, FK → tabel_vendor, NULLABLE | Filled only if role = vendor |
| created_at | TIMESTAMP | — |

---

### tabel_vendor
| Column | Type | Description |
|---|---|---|
| ID_vendor | INT, PK, AUTO_INCREMENT | — |
| nama_vendor | VARCHAR(100) | — |
| lokasi_vendor | VARCHAR(200) | — |
| kontak | VARCHAR(50) | Vendor PIC phone/WhatsApp |
| email_vendor | VARCHAR(100) | — |
| aktif | BOOLEAN, DEFAULT TRUE | Soft delete; inactive vendor cannot login |

---

### tabel_barang
| Column | Type | Description |
|---|---|---|
| ID_barang | INT, PK, AUTO_INCREMENT | — |
| part_code | VARCHAR(50), UNIQUE | Unique part code from Epson system |
| part_name | VARCHAR(100) | Official part name |
| nama_barang | VARCHAR(150) | Descriptive/alias name |
| berat_gram | FLOAT, NULLABLE | Weight per unit (relevant: Epson parts < 3 grams) |
| satuan | VARCHAR(20) | pcs, box, etc. |
| deskripsi | TEXT, NULLABLE | — |

---

### tabel_gudang
| Column | Type | Description |
|---|---|---|
| ID_gudang | INT, PK, AUTO_INCREMENT | — |
| nama_gudang | VARCHAR(100) | — |
| lokasi_gudang | VARCHAR(200) | — |
| kode_area | VARCHAR(20) | e.g. REC-1, REC-3, SSA, Return-Destroy |

---

### tabel_outbound
| Column | Type | Description |
|---|---|---|
| ID_outbound | INT, PK, AUTO_INCREMENT | — |
| no_pengiriman | VARCHAR(50), UNIQUE | DO number, system-generated on vendor submit |
| ID_vendor | INT, FK → tabel_vendor | — |
| waktu_kirim | DATETIME | Date & time vendor ships |
| estimasi_tiba | DATETIME, NULLABLE | — |
| lokasi_asal | VARCHAR(200) | Vendor warehouse location |
| status | ENUM('draft', 'submitted', 'in_transit', 'arrived', 'verified') | Locked (non-editable) after status = submitted |
| qr_token | VARCHAR(100), UNIQUE | Unique random string generated on submit; frontend renders QR from this token using a text-to-QR library |
| dibuat_oleh | INT, FK → tabel_user | Vendor user who input data on website |
| created_at | TIMESTAMP | — |

---

### tabel_outbound_detail
| Column | Type | Description |
|---|---|---|
| ID_outbound_detail | INT, PK, AUTO_INCREMENT | — |
| ID_outbound | INT, FK → tabel_outbound | — |
| ID_barang | INT, FK → tabel_barang | — |
| quantity_outbound | INT | Total quantity shipped by vendor |
| quantity_per_box | INT | Items per box/plastic packaging |
| jumlah_box | INT | Number of packages in this shipment |

---

### tabel_inbound
| Column | Type | Description |
|---|---|---|
| ID_inbound | INT, PK, AUTO_INCREMENT | — |
| ID_outbound | INT, FK → tabel_outbound, UNIQUE | One outbound can only have one inbound |
| ID_gudang | INT, FK → tabel_gudang | Official receiving area (e.g. REC-1) |
| ID_vendor | INT, FK → tabel_vendor | Redundant for easier dashboard queries |
| timestamp_terima | DATETIME | Time officer first scans QR on plastic packaging |
| nama_penerima | VARCHAR(100) | — |
| diterima_oleh | INT, FK → tabel_user | — |
| qr_scan_result | VARCHAR(255) | Raw data result from scanning QR on plastic packaging |
| lokasi_terakhir | VARCHAR(200), NULLABLE | Static last known location when goods were received (e.g. "Loading Dock Gate 2", "Area REC-3 Rack B"); filled manually or via dropdown |
| total_box_expected | INT | Copied from total boxes in outbound_detail when inbound is created |
| total_box_sudah_discan | INT, DEFAULT 0 | Increments each time a scan session is completed |
| status_scan | ENUM('menunggu', 'sedang_diproses', 'selesai') | Automatically set to 'selesai' when total_box_sudah_discan = total_box_expected; triggers discrepancy generation |
| created_at | TIMESTAMP | — |

---

### tabel_inbound_detail
| Column | Type | Description |
|---|---|---|
| ID_inbound_detail | INT, PK, AUTO_INCREMENT | — |
| ID_inbound | INT, FK → tabel_inbound | — |
| ID_barang | INT, FK → tabel_barang | — |
| quantity_cv_detect | INT, NULLABLE | Auto-filled by CV system |
| quantity_inbound | INT, NULLABLE | Final accepted quantity; defaults to quantity_cv_detect, officer can override before submitting |
| ada_cacat | BOOLEAN, DEFAULT FALSE | Flag from CV |
| catatan_cacat | TEXT, NULLABLE | Defect description if any |

---

### tabel_scan_session
| Column | Type | Description |
|---|---|---|
| ID_session | INT, PK, AUTO_INCREMENT | — |
| ID_inbound | INT, FK → tabel_inbound | — |
| ID_barang | INT, FK → tabel_barang | Item being scanned in this session |
| urutan_scan | INT | Session number: 1st, 2nd, etc. within one inbound |
| waktu_mulai | DATETIME | — |
| waktu_selesai | DATETIME, NULLABLE | Null if session still in progress |
| status_sesi | ENUM('berlangsung', 'selesai') | On 'selesai': system updates total_box_sudah_discan in tabel_inbound |
| ID_user | INT, FK → tabel_user | Officer performing the scan |

---

### tabel_foto
| Column | Type | Description |
|---|---|---|
| ID_foto | INT, PK, AUTO_INCREMENT | — |
| ID_session | INT, FK → tabel_scan_session | — |
| ID_inbound | INT, FK → tabel_inbound | Redundant for audit trail |
| file_url | VARCHAR(500) | File path/URL on storage server |
| uploaded_by | INT, FK → tabel_user | — |
| timestamp | TIMESTAMP | Time photo was taken on mobile device (auto) |
| related_type | VARCHAR(50) | Photo context: 'scan_barang', 'cacat', 'qr_packaging' |

---

### tabel_cv_result
| Column | Type | Description |
|---|---|---|
| ID_cv_result | INT, PK, AUTO_INCREMENT | — |
| ID_foto | INT, FK → tabel_foto | Photo processed by CV |
| ID_session | INT, FK → tabel_scan_session | — |
| jumlah_terdeteksi | INT | Number of objects detected by CV in this photo |
| cacat_terdeteksi | BOOLEAN | — |
| confidence_score | FLOAT | Model confidence score 0.0–1.0 |
| model_version | VARCHAR(50) | CV model version (for accuracy evaluation) |
| processed_at | TIMESTAMP | Time CV processing completed |

---

### tabel_discrepancy
| Column | Type | Description |
|---|---|---|
| ID_discrepancy | INT, PK, AUTO_INCREMENT | — |
| ID_outbound_detail | INT, FK → tabel_outbound_detail | — |
| ID_inbound_detail | INT, FK → tabel_inbound_detail | — |
| quantity_outbound | INT | Copied at time discrepancy record is created |
| quantity_inbound | INT | Copied at time discrepancy record is created |
| selisih | INT | quantity_inbound - quantity_outbound (negative = shortage, positive = excess) |
| status | ENUM('match', 'mismatch', 'missing', 'over') | Set automatically by system based on selisih value |
| keterangan | TEXT, NULLABLE | — |
| detected_at | TIMESTAMP | Time system auto-generated this record |

---

### tabel_discrepancy_action
| Column | Type | Description |
|---|---|---|
| ID_action | INT, PK, AUTO_INCREMENT | — |
| ID_discrepancy | INT, FK → tabel_discrepancy | — |
| action_type | ENUM('approve', 'hold', 'return', 'recount') | Officer's chosen action per flow |
| action_by | INT, FK → tabel_user | — |
| action_time | TIMESTAMP | — |
| notes | TEXT, NULLABLE | — |
| status_action | ENUM('pending', 'done', 'cancelled') | — |

---

### tabel_dokumen_r1
| Column | Type | Description |
|---|---|---|
| ID_dokumen | INT, PK, AUTO_INCREMENT | — |
| ID_discrepancy | INT, FK → tabel_discrepancy | — |
| no_dokumen_r1 | VARCHAR(50), UNIQUE | R1 number issued by Epson to vendor |
| status_dokumen | ENUM('draft', 'dikirim_ke_vendor', 'diproses_vendor', 'closing') | Vendor can monitor this status via their account |
| dibuat_oleh | INT, FK → tabel_user | — |
| dibuat_at | TIMESTAMP | — |
| keterangan | TEXT, NULLABLE | — |

---

### tabel_notifikasi
| Column | Type | Description |
|---|---|---|
| ID_notif | INT, PK, AUTO_INCREMENT | — |
| ID_user | INT, FK → tabel_user | Notification recipient |
| judul | VARCHAR(200) | — |
| pesan | TEXT | — |
| related_type | VARCHAR(50) | 'inbound', 'discrepancy', 'dokumen_r1', 'scan_session' |
| related_id | INT | Related entity ID |
| sudah_dibaca | BOOLEAN, DEFAULT FALSE | — |
| created_at | TIMESTAMP | — |

---

## Summary

Total tables: **14**

| Table | Purpose |
|---|---|
| tabel_user | System users (admin, petugas, supervisor, vendor) |
| tabel_vendor | Vendor master data |
| tabel_barang | Part/item master data |
| tabel_gudang | Warehouse/area master data |
| tabel_outbound | Vendor shipment header + QR token |
| tabel_outbound_detail | Per-item detail of each shipment |
| tabel_inbound | Receiving record when goods arrive at Epson |
| tabel_inbound_detail | Per-item detail of received goods + CV result |
| tabel_scan_session | Each scanning iteration (goods split into multiple scans) |
| tabel_foto | Photos captured during scanning |
| tabel_cv_result | Computer Vision detection results per photo |
| tabel_discrepancy | Auto-generated comparison: outbound vs inbound |
| tabel_discrepancy_action | Officer action taken on each discrepancy |
| tabel_dokumen_r1 | R1 Return Delivery Order status tracking |
| tabel_notifikasi | Notification system for all user roles |
