# Laravel Backend Implementation Prompt
## PT. Indonesia Epson Industry — Goods Delivery & Receiving Verification System
## Backend Only (API-first for React Frontend)

---

You are an expert Laravel developer building a REST API backend. The frontend is React (handled separately), so this is a fully API-based backend — no Blade views, no web routes for UI. Every response must be JSON. The database is Supabase (PostgreSQL). Follow all Laravel best practices throughout.

---

## Tech Stack

- **Framework:** Laravel 11
- **Database:** Supabase (PostgreSQL) — configure via `.env`
- **Auth:** Laravel Sanctum (token-based, stateless API auth)
- **Storage:** Supabase Storage or Laravel Storage with S3 driver (configure via `.env`)
- **QR Token Generation:** `Str::uuid()` — no QR image stored server-side; the React frontend renders the QR image from the token string using a JS library
- **CV Integration:** External CV API (HTTP call via Laravel `Http` facade)
- **API:** RESTful JSON API, all routes under `/api/`

---

## Environment Configuration

Set up `.env` with the following keys:

```env
APP_NAME="Epson Verification System"
APP_ENV=local
APP_KEY=base64:ienVVsxl3HA/6k5eBPJT5/tbXU2Yv9leR3G1gbbwTzQ=
APP_DEBUG=true
APP_URL=http://localhost:8000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database - Supabase PostgreSQL (already correct, just added sslmode)
DB_CONNECTION=pgsql
DB_HOST=db.dagfxlcgaiaelgiuveej.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=Lyodra96cuy!
DB_SSLMODE=require

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Supabase Storage (S3-compatible) - fill these in from your Supabase dashboard
AWS_ACCESS_KEY_ID=your-supabase-storage-access-key
AWS_SECRET_ACCESS_KEY=your-supabase-storage-secret-key
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=your-bucket-name
AWS_ENDPOINT=https://dagfxlcgaiaelgiuveej.supabase.co/storage/v1/s3
AWS_USE_PATH_STYLE_ENDPOINT=true

# Sanctum - add your React dev server origin
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:5173

# CV API - fill in when CV service is ready
CV_API_URL=http://your-cv-api-endpoint.com
CV_API_KEY=your-cv-api-key
CV_API_TIMEOUT=30

VITE_APP_NAME="${APP_NAME}"

---

## Package Installation

Run these after creating the Laravel project:

```bash
composer require laravel/sanctum
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

Install `spatie/laravel-permission` for role-based access control. Configure it to use your custom `tabel_user` table.

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── OutboundController.php
│   │   ├── InboundController.php
│   │   ├── ScanSessionController.php
│   │   ├── FotoController.php
│   │   ├── DiscrepancyController.php
│   │   ├── DiscrepancyActionController.php
│   │   ├── DokumenR1Controller.php
│   │   ├── NotifikasiController.php
│   │   ├── DashboardController.php
│   │   └── Master/
│   │       ├── BarangController.php
│   │       ├── VendorController.php
│   │       └── GudangController.php
│   ├── Middleware/
│   │   └── CheckRole.php
│   └── Requests/
│       ├── Auth/LoginRequest.php
│       ├── Auth/RegisterRequest.php
│       ├── OutboundRequest.php
│       ├── InboundRequest.php
│       ├── ScanSessionRequest.php
│       ├── DiscrepancyActionRequest.php
│       └── DokumenR1Request.php
├── Models/
│   ├── User.php
│   ├── Vendor.php
│   ├── Barang.php
│   ├── Gudang.php
│   ├── Outbound.php
│   ├── OutboundDetail.php
│   ├── Inbound.php
│   ├── InboundDetail.php
│   ├── ScanSession.php
│   ├── Foto.php
│   ├── CvResult.php
│   ├── Discrepancy.php
│   ├── DiscrepancyAction.php
│   ├── DokumenR1.php
│   └── Notifikasi.php
├── Services/
│   ├── AuthService.php
│   ├── OutboundService.php
│   ├── InboundService.php
│   ├── ScanSessionService.php
│   ├── DiscrepancyService.php
│   ├── CVService.php
│   └── NotificationService.php
└── Http/Resources/
    ├── UserResource.php
    ├── OutboundResource.php
    ├── OutboundDetailResource.php
    ├── InboundResource.php
    ├── InboundDetailResource.php
    ├── ScanSessionResource.php
    ├── DiscrepancyResource.php
    ├── DokumenR1Resource.php
    └── NotifikasiResource.php
```

---

## Database Migrations

Create one migration file per table in the correct order (respect FK dependencies). Use PostgreSQL-compatible syntax. All primary keys are custom column names — do NOT use the default Laravel `id()` shorthand.

**Migration order:**
1. tabel_vendor
2. tabel_user
3. tabel_barang
4. tabel_gudang
5. tabel_outbound
6. tabel_outbound_detail
7. tabel_inbound
8. tabel_inbound_detail
9. tabel_scan_session
10. tabel_foto
11. tabel_cv_result
12. tabel_discrepancy
13. tabel_discrepancy_action
14. tabel_dokumen_r1
15. tabel_notifikasi

---

### Migration: tabel_vendor
```php
Schema::create('tabel_vendor', function (Blueprint $table) {
    $table->bigIncrements('ID_vendor');
    $table->string('nama_vendor', 100);
    $table->string('lokasi_vendor', 200);
    $table->string('kontak', 50);
    $table->string('email_vendor', 100);
    $table->boolean('aktif')->default(true);
});
```

### Migration: tabel_user
```php
Schema::create('tabel_user', function (Blueprint $table) {
    $table->bigIncrements('ID_user');
    $table->string('nama', 100);
    $table->string('email', 100)->unique();
    $table->string('password_hash', 255);
    $table->enum('role', ['admin', 'petugas', 'supervisor', 'vendor']);
    $table->unsignedBigInteger('ID_vendor')->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('ID_vendor')->references('ID_vendor')->on('tabel_vendor')->nullOnDelete();
});
```

### Migration: tabel_barang
```php
Schema::create('tabel_barang', function (Blueprint $table) {
    $table->bigIncrements('ID_barang');
    $table->string('part_code', 50)->unique();
    $table->string('part_name', 100);
    $table->string('nama_barang', 150);
    $table->float('berat_gram')->nullable();
    $table->string('satuan', 20);
    $table->text('deskripsi')->nullable();
});
```

### Migration: tabel_gudang
```php
Schema::create('tabel_gudang', function (Blueprint $table) {
    $table->bigIncrements('ID_gudang');
    $table->string('nama_gudang', 100);
    $table->string('lokasi_gudang', 200);
    $table->string('kode_area', 20);
});
```

### Migration: tabel_outbound
```php
Schema::create('tabel_outbound', function (Blueprint $table) {
    $table->bigIncrements('ID_outbound');
    $table->string('no_pengiriman', 50)->unique();
    $table->unsignedBigInteger('ID_vendor');
    $table->dateTime('waktu_kirim');
    $table->dateTime('estimasi_tiba')->nullable();
    $table->string('lokasi_asal', 200);
    $table->enum('status', ['draft', 'submitted', 'in_transit', 'arrived', 'verified'])->default('draft');
    $table->string('qr_token', 100)->unique();
    $table->unsignedBigInteger('dibuat_oleh');
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('ID_vendor')->references('ID_vendor')->on('tabel_vendor');
    $table->foreign('dibuat_oleh')->references('ID_user')->on('tabel_user');
});
```

### Migration: tabel_outbound_detail
```php
Schema::create('tabel_outbound_detail', function (Blueprint $table) {
    $table->bigIncrements('ID_outbound_detail');
    $table->unsignedBigInteger('ID_outbound');
    $table->unsignedBigInteger('ID_barang');
    $table->integer('quantity_outbound');
    $table->integer('quantity_per_box');
    $table->integer('jumlah_box');
    $table->foreign('ID_outbound')->references('ID_outbound')->on('tabel_outbound')->cascadeOnDelete();
    $table->foreign('ID_barang')->references('ID_barang')->on('tabel_barang');
});
```

### Migration: tabel_inbound
```php
Schema::create('tabel_inbound', function (Blueprint $table) {
    $table->bigIncrements('ID_inbound');
    $table->unsignedBigInteger('ID_outbound')->unique();
    $table->unsignedBigInteger('ID_gudang');
    $table->unsignedBigInteger('ID_vendor');
    $table->dateTime('timestamp_terima');
    $table->string('nama_penerima', 100);
    $table->unsignedBigInteger('diterima_oleh');
    $table->string('qr_scan_result', 255);
    $table->string('lokasi_terakhir', 200)->nullable();
    $table->integer('total_box_expected');
    $table->integer('total_box_sudah_discan')->default(0);
    $table->enum('status_scan', ['menunggu', 'sedang_diproses', 'selesai'])->default('menunggu');
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('ID_outbound')->references('ID_outbound')->on('tabel_outbound');
    $table->foreign('ID_gudang')->references('ID_gudang')->on('tabel_gudang');
    $table->foreign('ID_vendor')->references('ID_vendor')->on('tabel_vendor');
    $table->foreign('diterima_oleh')->references('ID_user')->on('tabel_user');
});
```

### Migration: tabel_inbound_detail
```php
Schema::create('tabel_inbound_detail', function (Blueprint $table) {
    $table->bigIncrements('ID_inbound_detail');
    $table->unsignedBigInteger('ID_inbound');
    $table->unsignedBigInteger('ID_barang');
    $table->integer('quantity_cv_detect')->nullable();
    $table->integer('quantity_inbound')->nullable();
    $table->boolean('ada_cacat')->default(false);
    $table->text('catatan_cacat')->nullable();
    $table->foreign('ID_inbound')->references('ID_inbound')->on('tabel_inbound')->cascadeOnDelete();
    $table->foreign('ID_barang')->references('ID_barang')->on('tabel_barang');
});
```

### Migration: tabel_scan_session
```php
Schema::create('tabel_scan_session', function (Blueprint $table) {
    $table->bigIncrements('ID_session');
    $table->unsignedBigInteger('ID_inbound');
    $table->unsignedBigInteger('ID_barang');
    $table->integer('urutan_scan');
    $table->dateTime('waktu_mulai');
    $table->dateTime('waktu_selesai')->nullable();
    $table->enum('status_sesi', ['berlangsung', 'selesai'])->default('berlangsung');
    $table->unsignedBigInteger('ID_user');
    $table->foreign('ID_inbound')->references('ID_inbound')->on('tabel_inbound')->cascadeOnDelete();
    $table->foreign('ID_barang')->references('ID_barang')->on('tabel_barang');
    $table->foreign('ID_user')->references('ID_user')->on('tabel_user');
});
```

### Migration: tabel_foto
```php
Schema::create('tabel_foto', function (Blueprint $table) {
    $table->bigIncrements('ID_foto');
    $table->unsignedBigInteger('ID_session');
    $table->unsignedBigInteger('ID_inbound');
    $table->string('file_url', 500);
    $table->unsignedBigInteger('uploaded_by');
    $table->timestamp('timestamp')->useCurrent();
    $table->string('related_type', 50);
    $table->foreign('ID_session')->references('ID_session')->on('tabel_scan_session')->cascadeOnDelete();
    $table->foreign('ID_inbound')->references('ID_inbound')->on('tabel_inbound');
    $table->foreign('uploaded_by')->references('ID_user')->on('tabel_user');
});
```

### Migration: tabel_cv_result
```php
Schema::create('tabel_cv_result', function (Blueprint $table) {
    $table->bigIncrements('ID_cv_result');
    $table->unsignedBigInteger('ID_foto');
    $table->unsignedBigInteger('ID_session');
    $table->integer('jumlah_terdeteksi');
    $table->boolean('cacat_terdeteksi');
    $table->float('confidence_score');
    $table->string('model_version', 50);
    $table->timestamp('processed_at')->useCurrent();
    $table->foreign('ID_foto')->references('ID_foto')->on('tabel_foto')->cascadeOnDelete();
    $table->foreign('ID_session')->references('ID_session')->on('tabel_scan_session');
});
```

### Migration: tabel_discrepancy
```php
Schema::create('tabel_discrepancy', function (Blueprint $table) {
    $table->bigIncrements('ID_discrepancy');
    $table->unsignedBigInteger('ID_outbound_detail');
    $table->unsignedBigInteger('ID_inbound_detail');
    $table->integer('quantity_outbound');
    $table->integer('quantity_inbound');
    $table->integer('selisih');
    $table->enum('status', ['match', 'mismatch', 'missing', 'over']);
    $table->text('keterangan')->nullable();
    $table->timestamp('detected_at')->useCurrent();
    $table->foreign('ID_outbound_detail')->references('ID_outbound_detail')->on('tabel_outbound_detail');
    $table->foreign('ID_inbound_detail')->references('ID_inbound_detail')->on('tabel_inbound_detail');
});
```

### Migration: tabel_discrepancy_action
```php
Schema::create('tabel_discrepancy_action', function (Blueprint $table) {
    $table->bigIncrements('ID_action');
    $table->unsignedBigInteger('ID_discrepancy');
    $table->enum('action_type', ['approve', 'hold', 'return', 'recount']);
    $table->unsignedBigInteger('action_by');
    $table->timestamp('action_time')->useCurrent();
    $table->text('notes')->nullable();
    $table->enum('status_action', ['pending', 'done', 'cancelled'])->default('pending');
    $table->foreign('ID_discrepancy')->references('ID_discrepancy')->on('tabel_discrepancy')->cascadeOnDelete();
    $table->foreign('action_by')->references('ID_user')->on('tabel_user');
});
```

### Migration: tabel_dokumen_r1
```php
Schema::create('tabel_dokumen_r1', function (Blueprint $table) {
    $table->bigIncrements('ID_dokumen');
    $table->unsignedBigInteger('ID_discrepancy');
    $table->string('no_dokumen_r1', 50)->unique();
    $table->enum('status_dokumen', ['draft', 'dikirim_ke_vendor', 'diproses_vendor', 'closing'])->default('draft');
    $table->unsignedBigInteger('dibuat_oleh');
    $table->timestamp('dibuat_at')->useCurrent();
    $table->text('keterangan')->nullable();
    $table->foreign('ID_discrepancy')->references('ID_discrepancy')->on('tabel_discrepancy');
    $table->foreign('dibuat_oleh')->references('ID_user')->on('tabel_user');
});
```

### Migration: tabel_notifikasi
```php
Schema::create('tabel_notifikasi', function (Blueprint $table) {
    $table->bigIncrements('ID_notif');
    $table->unsignedBigInteger('ID_user');
    $table->string('judul', 200);
    $table->text('pesan');
    $table->string('related_type', 50);
    $table->unsignedBigInteger('related_id');
    $table->boolean('sudah_dibaca')->default(false);
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('ID_user')->references('ID_user')->on('tabel_user')->cascadeOnDelete();
});
```

---

## Models

Each model must define:
- `protected $table` — exact table name
- `protected $primaryKey` — custom PK column name
- `public $incrementing = true`
- `protected $keyType = 'int'`
- `public $timestamps = false` — most tables only have `created_at`, not `updated_at`
- `protected $fillable` — all writable columns
- All Eloquent relationships

### User Model (special — extends Authenticatable)
```php
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens;

protected $table = 'tabel_user';
protected $primaryKey = 'ID_user';
public $timestamps = false;

public function getAuthPassword()
{
    return $this->password_hash;
}

    protected $fillable = [
        'nama', 'email', 'password_hash', 'role', 'ID_vendor', 'created_at'
    ];

    protected $hidden = ['password_hash'];

    // Relationships
    public function vendor() { return $this->belongsTo(Vendor::class, 'ID_vendor', 'ID_vendor'); }
    public function outbounds() { return $this->hasMany(Outbound::class, 'dibuat_oleh', 'ID_user'); }
    public function notifikasi() { return $this->hasMany(Notifikasi::class, 'ID_user', 'ID_user'); }
}
```

> In `config/auth.php`, set `providers.users.model` to `App\Models\User`.
> Remove the default `users` table migration entirely.

### All Other Models — follow this pattern (example: Outbound)
```php
class Outbound extends Model
{
    protected $table = 'tabel_outbound';
    protected $primaryKey = 'ID_outbound';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'no_pengiriman', 'ID_vendor', 'waktu_kirim', 'estimasi_tiba',
        'lokasi_asal', 'status', 'qr_token', 'dibuat_oleh', 'created_at'
    ];

    public function vendor() { return $this->belongsTo(Vendor::class, 'ID_vendor', 'ID_vendor'); }
    public function pembuatOutbound() { return $this->belongsTo(User::class, 'dibuat_oleh', 'ID_user'); }
    public function details() { return $this->hasMany(OutboundDetail::class, 'ID_outbound', 'ID_outbound'); }
    public function inbound() { return $this->hasOne(Inbound::class, 'ID_outbound', 'ID_outbound'); }
}
```

Apply the same pattern for all 15 models. Define relationships in both directions (belongsTo on FK side, hasMany/hasOne on PK side).

---

## Authentication (Sanctum — Stateless Token Auth)

### AuthController — full implementation required

#### POST /api/auth/register
- Validate: `nama`, `email` (unique in tabel_user), `password` (min 8), `role`, `ID_vendor` (required if role = vendor)
- Hash password using `bcrypt()`, store in `password_hash`
- Return: `201` with user data + token

#### POST /api/auth/login
- Validate: `email`, `password`
- Find user by email in `tabel_user`
- Check password with `Hash::check($request->password, $user->password_hash)`
- Create Sanctum token: `$user->createToken('auth_token')->plainTextToken`
- Return: `200` with `{ token, user: { ID_user, nama, email, role } }`

#### POST /api/auth/logout
- Middleware: `auth:sanctum`
- Revoke current token: `$request->user()->currentAccessToken()->delete()`
- Return: `200` with message

#### GET /api/auth/me
- Middleware: `auth:sanctum`
- Return authenticated user data with role and vendor info if applicable

### Sanctum Configuration
In `bootstrap/app.php` (Laravel 11), register Sanctum middleware:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->statefulApi();
    $middleware->alias(['role' => CheckRole::class]);
})
```

In `config/sanctum.php`, set `stateful` domains to match `SANCTUM_STATEFUL_DOMAINS` from `.env`.

All protected API routes use `middleware('auth:sanctum')`.

---

## API Routes — Complete Structure

Define all routes in `routes/api.php`. Group by feature with appropriate middleware.

```php
// Public routes (no auth required)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Protected routes (Sanctum auth required)
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Master Data (admin only)
    Route::middleware('role:admin')->prefix('master')->group(function () {
        Route::apiResource('barang',  BarangController::class);
        Route::apiResource('vendor',  VendorController::class);
        Route::apiResource('gudang',  GudangController::class);
    });

    // Outbound
    Route::prefix('outbound')->group(function () {
        Route::get('/',                   [OutboundController::class, 'index']);
        Route::post('/',                  [OutboundController::class, 'store']);        // vendor, admin
        Route::get('/{id}',               [OutboundController::class, 'show']);
        Route::put('/{id}',               [OutboundController::class, 'update']);      // only if status=draft
        Route::delete('/{id}',            [OutboundController::class, 'destroy']);     // only if status=draft
        Route::post('/{id}/submit',       [OutboundController::class, 'submit']);      // locks the record, generates qr_token
        Route::get('/{id}/qr-token',      [OutboundController::class, 'getQrToken']); // returns qr_token for React to render QR
    });

    // Inbound
    Route::prefix('inbound')->group(function () {
        Route::get('/',                   [InboundController::class, 'index']);
        Route::post('/scan-qr',           [InboundController::class, 'scanQr']);       // officer scans QR → creates inbound
        Route::get('/{id}',               [InboundController::class, 'show']);
        Route::put('/{id}',               [InboundController::class, 'update']);       // update lokasi_terakhir, nama_penerima
        Route::get('/{id}/progress',      [InboundController::class, 'progress']);     // scan progress (box count)
    });

    // Scan Session
    Route::prefix('scan-session')->group(function () {
        Route::get('/',                   [ScanSessionController::class, 'index']);
        Route::post('/',                  [ScanSessionController::class, 'store']);    // start a new scan session
        Route::get('/{id}',               [ScanSessionController::class, 'show']);
        Route::post('/{id}/upload-foto',  [ScanSessionController::class, 'uploadFoto']); // upload photo → triggers CV
        Route::post('/{id}/complete',     [ScanSessionController::class, 'complete']); // close session → update box count
        Route::put('/{id}/inbound-detail',[ScanSessionController::class, 'updateInboundDetail']); // officer overrides quantity_inbound
    });

    // Discrepancy
    Route::prefix('discrepancy')->group(function () {
        Route::get('/',                   [DiscrepancyController::class, 'index']);    // filterable by vendor, date, status, part
        Route::get('/{id}',               [DiscrepancyController::class, 'show']);
        Route::post('/{id}/action',       [DiscrepancyActionController::class, 'store']); // officer takes action
        Route::get('/{id}/actions',       [DiscrepancyActionController::class, 'index']); // action history
    });

    // R1 Document
    Route::prefix('dokumen-r1')->group(function () {
        Route::get('/',                   [DokumenR1Controller::class, 'index']);
        Route::post('/',                  [DokumenR1Controller::class, 'store']);      // supervisor/admin only
        Route::get('/{id}',               [DokumenR1Controller::class, 'show']);
        Route::put('/{id}/status',        [DokumenR1Controller::class, 'updateStatus']); // update status_dokumen
    });

    // Notifications
    Route::prefix('notifikasi')->group(function () {
        Route::get('/',                   [NotifikasiController::class, 'index']);     // get user's notifications
        Route::post('/{id}/read',         [NotifikasiController::class, 'markRead']); // mark as read
        Route::post('/read-all',          [NotifikasiController::class, 'markAllRead']);
        Route::get('/unread-count',       [NotifikasiController::class, 'unreadCount']);
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary',            [DashboardController::class, 'summary']);    // counts, totals
        Route::get('/discrepancy-stats',  [DashboardController::class, 'discrepancyStats']); // by vendor/date/status
        Route::get('/pending-actions',    [DashboardController::class, 'pendingActions']);
        Route::get('/vendor-performance', [DashboardController::class, 'vendorPerformance']); // discrepancy rate per vendor
    });

});
```

---

## Role-Based Access Control

Use `spatie/laravel-permission` OR a simple custom `CheckRole` middleware. If using custom middleware:

```php
// app/Http/Middleware/CheckRole.php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    if (!in_array($request->user()->role, $roles)) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    return $next($request);
}
```

Register it in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['role' => CheckRole::class]);
})
```

**Role permissions:**
| Role | Access |
|---|---|
| `admin` | Full access to everything including master data |
| `supervisor` | View all inbound, discrepancy, dashboard; create/update R1; approve actions |
| `petugas` | Create inbound (scan QR), run scan sessions, upload photos, take discrepancy actions |
| `vendor` | Only their own: outbound CRUD, inbound status, discrepancy read, R1 status read |

For vendor scope: in `OutboundController@index`, filter by `ID_vendor` of the logged-in user if role is `vendor`.

---

## Service Classes — Full Business Logic

### AuthService
```
- register(array $data): User
  → bcrypt password, store as password_hash
  → create user, return user + token

- login(string $email, string $password): array
  → find user by email
  → Hash::check against password_hash
  → createToken, return ['token' => ..., 'user' => ...]
```

### OutboundService
```
- createOutbound(array $data, User $user): Outbound
  → generate no_pengiriman: 'DO-' . date('Ymd') . '-' . str_pad(nextSequence(), 4, '0', STR_PAD_LEFT)
  → set status = 'draft'
  → save outbound + outbound_detail records in DB transaction
  → return outbound with details

- submitOutbound(int $id, User $user): Outbound
  → verify status is 'draft', verify user owns this outbound (if role=vendor)
  → generate qr_token = Str::uuid()
  → update status = 'submitted'
  → return updated outbound with qr_token
  → (React frontend will render QR image from qr_token)
```

### InboundService
```
- createInboundFromQr(string $qr_token, array $data, User $officer): Inbound
  → find outbound by qr_token (404 if not found)
  → verify outbound status is 'submitted' or 'in_transit' (reject if already 'arrived')
  → calculate total_box_expected = sum of jumlah_box from tabel_outbound_detail
  → create tabel_inbound record
  → create empty tabel_inbound_detail records for each outbound_detail (quantity_inbound = null)
  → update outbound status = 'arrived'
  → send notification to supervisor via NotificationService
  → return inbound with outbound and details
```

### ScanSessionService
```
- startSession(int $inbound_id, int $barang_id, User $user): ScanSession
  → determine urutan_scan = count existing sessions for this inbound + 1
  → set waktu_mulai = now(), status_sesi = 'berlangsung'
  → update inbound status_scan = 'sedang_diproses'
  → return session

- uploadFoto(int $session_id, UploadedFile $file, User $user): array
  → store file to storage (Supabase S3), get file_url
  → save to tabel_foto
  → call CVService::processPhoto(file_url)
  → save cv_result to tabel_cv_result
  → update tabel_inbound_detail: quantity_cv_detect += jumlah_terdeteksi, ada_cacat = true if any cacat
  → return { foto, cv_result }

- completeSession(int $session_id): Inbound
  → wrap in DB transaction
  → set status_sesi = 'selesai', waktu_selesai = now()
  → increment tabel_inbound.total_box_sudah_discan by 1
  → if total_box_sudah_discan == total_box_expected:
      → set inbound status_scan = 'selesai'
      → call DiscrepancyService::generateDiscrepancies(inbound_id)
  → return updated inbound

- updateInboundDetail(int $session_id, int $barang_id, int $quantity_inbound): InboundDetail
  → officer overrides quantity_inbound before finalizing
  → only allowed if inbound status_scan != 'selesai'
```

### DiscrepancyService
```
- generateDiscrepancies(int $inbound_id): void
  → wrap entire function in DB transaction
  → get all inbound_details for this inbound
  → for each inbound_detail:
      → find matching outbound_detail (same ID_barang, same ID_outbound)
      → set quantity_inbound = inbound_detail.quantity_inbound (or quantity_cv_detect if not overridden)
      → compute selisih = quantity_inbound - quantity_outbound
      → determine status:
          selisih == 0              → 'match'
          quantity_inbound == 0    → 'missing'
          selisih > 0              → 'over'
          selisih < 0              → 'mismatch'
      → insert into tabel_discrepancy
  → update outbound status = 'verified'
  → send notifications: supervisor + vendor (NotificationService)
```

### CVService
```
- processPhoto(string $file_url): array
  → POST to CV_API_URL with file_url in payload, include CV_API_KEY header
  → timeout: CV_API_TIMEOUT seconds
  → on success: return [jumlah_terdeteksi, cacat_terdeteksi, confidence_score, model_version]
  → on failure: log error, return [jumlah_terdeteksi: null, cacat_terdeteksi: false, confidence_score: 0, model_version: 'unknown']
  → never throw exception to caller — always return gracefully
```

### NotificationService
```
- send(int $user_id, string $judul, string $pesan, string $related_type, int $related_id): void
  → insert into tabel_notifikasi

Trigger notifications at:
  1. Inbound created → notify supervisor: "Barang dari vendor X telah tiba"
  2. Scan session completed (all boxes done) → notify supervisor: "Scan selesai, discrepancy sedang diproses"
  3. Discrepancy generated → notify supervisor + vendor: "Discrepancy ditemukan pada pengiriman X"
  4. Discrepancy action taken → notify supervisor: "Aksi [action_type] diambil untuk discrepancy X"
  5. R1 status updated → notify vendor: "Status R1 dokumen X diperbarui menjadi Y"
```

---

## API Response Format

All API responses must follow this consistent JSON structure:

### Success
```json
{
  "success": true,
  "message": "Outbound created successfully",
  "data": { ... }
}
```

### Success with pagination
```json
{
  "success": true,
  "message": "OK",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

### Error (validation)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

### Error (general)
```json
{
  "success": false,
  "message": "Outbound not found"
}
```

Create a base `ApiResponse` trait or helper to standardize these responses across all controllers:
```php
// app/Traits/ApiResponse.php
trait ApiResponse {
    protected function success($data = null, string $message = 'OK', int $code = 200): JsonResponse {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }
    protected function error(string $message, int $code = 400, $errors = null): JsonResponse {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }
}
```

Use Laravel API Resources (`php artisan make:resource`) for transforming all model output before returning.

---

## Error Handling

In `bootstrap/app.php`, register a global exception handler for API-friendly errors:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (AuthenticationException $e, Request $request) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    });
    $exceptions->render(function (ModelNotFoundException $e, Request $request) {
        return response()->json(['success' => false, 'message' => 'Resource not found'], 404);
    });
    $exceptions->render(function (ValidationException $e, Request $request) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $e->errors()
        ], 422);
    });
    $exceptions->render(function (AuthorizationException $e, Request $request) {
        return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
    });
})
```

---

## Dashboard Endpoints — Query Logic

### GET /api/dashboard/summary
Returns:
```json
{
  "total_outbound_today": 12,
  "total_inbound_today": 9,
  "total_discrepancy_today": 4,
  "pending_actions": 3,
  "discrepancy_by_status": {
    "match": 40,
    "mismatch": 15,
    "missing": 8,
    "over": 5
  }
}
```

### GET /api/dashboard/discrepancy-stats
Query params: `vendor_id`, `date_from`, `date_to`, `status`, `part_code`
Returns paginated discrepancy list with vendor name, part info, selisih, status.

### GET /api/dashboard/vendor-performance
Returns per-vendor discrepancy rate:
```json
[
  { "vendor": "Vendor A", "total_shipments": 30, "total_discrepancies": 4, "rate": "13.3%" }
]
```

---

## Important Implementation Rules

1. **No Blade views, no web routes for UI.** Remove the default welcome route. The only routes are under `/api/`.

2. **Remove CSRF middleware from API routes.** Laravel 11 handles this automatically for `api.php` routes, but verify it is not applied.

3. **PostgreSQL-specific:** Do not use MySQL-only syntax. Use `$table->bigIncrements()` and avoid `$table->id()` shorthand since primary key names are custom. Enum columns work in PostgreSQL — use them as specified.

4. **Custom primary keys everywhere.** Every model must explicitly declare `$primaryKey`. Eloquent's default assumption of `id` will break FK resolution and relationships if not overridden.

5. **`tabel_user` replaces Laravel's default `users` table entirely.** Delete the default `create_users_table` migration. Update `config/auth.php`. The `password_hash` column must be referenced via `$authPasswordName = 'password_hash'` in the User model (Laravel 11 feature).

6. **Outbound immutability after submit.** In `OutboundController@update` and `OutboundController@destroy`, check `if ($outbound->status !== 'draft') return $this->error('Cannot modify a submitted outbound', 403)`.

7. **Discrepancy generation must be wrapped in a DB transaction.** Use `DB::transaction(function() { ... })` in `DiscrepancyService::generateDiscrepancies()`.

8. **`total_box_sudah_discan` increment must use atomic DB operation** to prevent race conditions: `DB::table('tabel_inbound')->where('ID_inbound', $id)->increment('total_box_sudah_discan')` — do NOT read-then-write.

9. **Vendor data scoping.** When role is `vendor`, always add `->where('ID_vendor', auth()->user()->ID_vendor)` to queries for outbound, inbound, discrepancy, and R1 endpoints. Never expose other vendors' data.

10. **File uploads.** Store photos to Supabase Storage via the S3 driver. Return public URL as `file_url` in `tabel_foto`. Handle file validation: accept only `image/jpeg`, `image/png`, max 10MB.

11. **CORS.** Since the React frontend runs on a different origin, configure `config/cors.php`:
```php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:3000', 'https://your-react-domain.com'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

12. **Pagination.** All list endpoints (`index`) must be paginated using `->paginate(15)`. Include pagination meta in the response.

13. **Seeders.** Create seeders for: one admin user, one supervisor user, one petugas user, two vendor users with vendor records, sample barang (5 parts), and sample gudang (3 areas). Run with `php artisan db:seed`.
