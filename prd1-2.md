# Product Requirements Document (PRD) — Tier 2: Medium Complexity
## Database Migrations, Core Authentication Engine & REST API Services

| Metadata | Specification |
| :--- | :--- |
| **Document Target** | Tier 2 (Medium Complexity) — Backend Intern (Intern 2) |
| **Target Application** | Church Management Platform — Backend API & Services |
| **Primary Frameworks** | PHP 8.2+ / Laravel 10 / Laravel Sanctum / DomPDF / Eloquent ORM |
| **Target Repository** | `c:\laragon\www\perita_lodge` / Multi-Tenant Worktree |
| **Associated PRDs** | Tier 1 (Architecture & Tenancy Core), Tier 3 (Frontend Blade UI) |

---

## 1. Executive Summary & Assignee Scope

The Tier 2 engineering track owns the core data modeling, authentication mechanics, business logic layers, and RESTful API endpoints. 

As the Backend Intern, you will implement the database migrations, build the multi-identifier authentication engine (supporting both normalized phone numbers and emails), enforce the mandatory first-login password change flow, and deliver all JSON endpoints consumed by the Web Member Portal and the Flutter mobile application.

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    TIER 2 BACKEND API & BUSINESS LOGIC                       │
│                                                                              │
│   Incoming Request (via Tenant Context established by Tier 1)                │
│           │                                                                  │
│           ▼                                                                  │
│   Sanctum Auth & Multi-Identifier Verifier (Email OR Phone Normalization)    │
│           ├── Default Password? ──► Returns `force_password_change: true`    │
│           │                         Issues restricted token capability       │
│           └── Normal Access    ──► Issues full access Sanctum bearer token   │
│                                                                              │
│   Tenant Database Entities & Services                                        │
│   ├── Migrations: `members` extension, `families`, `duty_assignments`, etc.  │
│   ├── Member REST APIs: Dashboard, Profile, Prayer Requests, Duty Roster     │
│   ├── DomPDF Tax Generator: `/member/giving-statement/pdf`                   │
│   └── Admin Management Controllers: Roster scheduling & Pastoral triage      │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Database Schema & Migration Specifications

All migrations must be placed in `database/migrations/tenant/` (or standard migrations directory per Tier 1 tenancy configuration).

### 2.1 Schema Update: `members` Table
Create migration `add_auth_and_family_to_members_table.php`:
```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('members', function (Blueprint $table) {
            // Authentication & Security Credentials
            if (!Schema::hasColumn('members', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
            if (!Schema::hasColumn('members', 'is_default_password')) {
                $table->boolean('is_default_password')->default(true)->after('password');
            }
            if (!Schema::hasColumn('members', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('is_default_password');
            }
            if (!Schema::hasColumn('members', 'biometric_enabled')) {
                $table->boolean('biometric_enabled')->default(false)->after('password_changed_at');
            }
            
            // Normalized Phone for clean matching (+16145550199)
            if (!Schema::hasColumn('members', 'phone_normalized')) {
                $table->string('phone_normalized', 25)->nullable()->index()->after('telephone_number');
            }

            // Household & Family Structure
            if (!Schema::hasColumn('members', 'family_id')) {
                $table->unsignedBigInteger('family_id')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('members', 'is_head_of_household')) {
                $table->boolean('is_head_of_household')->default(false)->after('family_id');
            }
            if (!Schema::hasColumn('members', 'relationship_to_head')) {
                $table->string('relationship_to_head', 50)->nullable()->comment('Self, Spouse, Son, Daughter, Parent, Relative');
            }
        });
    }

    public function down(): void {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'password', 'is_default_password', 'password_changed_at',
                'biometric_enabled', 'phone_normalized', 'family_id',
                'is_head_of_household', 'relationship_to_head'
            ]);
        });
    }
};
```

### 2.2 Schema Creation: `families` Table
Create migration `create_families_table.php`:
```php
Schema::create('families', function (Blueprint $table) {
    $table->id();
    $table->string('family_name', 150);
    $table->unsignedBigInteger('head_member_id')->nullable()->index();
    $table->string('address')->nullable();
    $table->string('city', 100)->nullable();
    $table->string('state', 50)->nullable();
    $table->string('postal_code', 20)->nullable();
    $table->timestamps();
});
```

### 2.3 Schema Creation: `prayer_requests` Table
Create migration `create_prayer_requests_table.php`:
```php
Schema::create('prayer_requests', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('member_id')->index();
    $table->string('title', 200);
    $table->text('description');
    $table->enum('category', ['Health', 'Family', 'Finances', 'Spiritual', 'Thanksgiving', 'General'])->default('General');
    $table->boolean('is_confidential')->default(true)->comment('True = Pastors Only; False = Bulletin/Public Intercession');
    $table->enum('status', ['pending', 'in_prayer', 'answered', 'archived'])->default('pending');
    $table->text('pastoral_notes')->nullable();
    $table->unsignedBigInteger('assigned_pastor_id')->nullable();
    $table->timestamps();

    $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
});
```

### 2.4 Schema Creation: `duty_assignments` Table
Create migration `create_duty_assignments_table.php`:
```php
Schema::create('duty_assignments', function (Blueprint $table) {
    $table->id();
    $table->string('duty_name', 150)->comment('e.g., Sunday Scripture Reading, Head Usher, Communion Steward');
    $table->unsignedBigInteger('member_id')->index();
    $table->date('service_date')->index();
    $table->string('service_time', 50)->default('09:00 AM');
    $table->enum('status', ['assigned', 'confirmed', 'declined', 'swapped'])->default('assigned');
    $table->text('decline_reason')->nullable();
    $table->unsignedBigInteger('swapped_with_member_id')->nullable();
    $table->timestamps();

    $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
});
```

---

## 3. Multi-Identifier Authentication & Security Engine

### 3.1 Phone Normalization Utility
Create `App\Support\PhoneSanitizer`:
```php
namespace App\Support;

class PhoneSanitizer
{
    public static function normalize(?string $phone): ?string
    {
        if (!$phone) return null;
        // Strip everything except digits and plus sign
        $sanitized = preg_replace('/[^\d+]/', '', $phone);
        if (!str_starts_with($sanitized, '+') && strlen($sanitized) === 10) {
            $sanitized = '+1' . $sanitized; // Standard North American fallback
        }
        return $sanitized;
    }
}
```

### 3.2 Authentication Endpoint (`POST /api/v1/auth/login`)
```php
public function login(Request $request)
{
    $request->validate([
        'identifier' => 'required|string',
        'password' => 'required|string',
        'device_name' => 'nullable|string'
    ]);

    $identifier = $request->identifier;
    $normalizedPhone = PhoneSanitizer::normalize($identifier);

    // Look up in members table
    $member = Member::where('email', $identifier)
        ->orWhere('phone_normalized', $normalizedPhone)
        ->orWhere('telephone_number', $identifier)
        ->first();

    if (!$member || !Hash::check($request->password, $member->password)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid credentials. Please verify your phone number/email and password.'
        ], 401);
    }

    // Determine default password status
    $forceChange = (bool) ($member->is_default_password || is_null($member->password_changed_at));
    $abilities = $forceChange ? ['force-password-change'] : ['*'];

    $token = $member->createToken($request->device_name ?? 'mobile-app', $abilities)->plainTextToken;

    return response()->json([
        'status' => 'success',
        'token' => $token,
        'force_password_change' => $forceChange,
        'user' => [
            'id' => $member->id,
            'name' => $member->first_name . ' ' . $member->last_name,
            'email' => $member->email,
            'role' => 'member',
            'member_code' => $member->mask ?? ('MEM-' . str_pad($member->id, 5, '0', STR_PAD_LEFT)),
            'qr_code_url' => $member->qr_code_url,
        ]
    ]);
}
```

### 3.3 Password Change (`POST /api/v1/auth/change-default-password`)
Guarded by `auth:sanctum` with ability `force-password-change`:
* **Validation**: `new_password` required, min 8 characters, confirmed, different from default password.
* **Execution**: Update `password = Hash::make($new_password)`, set `is_default_password = false`, `password_changed_at = now()`.
* **Revocation**: Delete old restricted token and return fresh unrestricted token.

---

## 4. Member Portal RESTful API Endpoints

### 4.1 Dashboard Overview (`GET /api/v1/member/dashboard`)
Returns JSON summary for member view:
* `next_duty`: Closest upcoming assignment in `duty_assignments` where `service_date >= today`.
* `financial_summary`: YTD total offerings and unpaid dues from `offerings` and `annual_bills`.
* `prayer_summary`: Count of active requests (`in_prayer` or `pending`).
* `qr_pass`: Member digital pass payload.

### 4.2 Member Profile (`GET /api/v1/member/profile` & `PUT /api/v1/member/profile`)
* **GET**: Returns member biographical data, contact info, address, and eager loads `family.members`.
* **PUT**: Allows updating `telephone_number` (auto-updates `phone_normalized`), `address`, and `emergency_contact`. Email updates require admin verification.

### 4.3 Duty Roster (`GET /api/v1/member/duty-roster` & `POST /api/v1/member/duty-roster/{id}/status`)
* **GET**: Lists assignments for authenticated member ordered by `service_date ASC`.
* **POST**: Accepts `{ "status": "confirmed" | "declined", "reason": "optional text" }`. Updates status and timestamps.

### 4.4 Prayer Requests (`POST /api/v1/member/prayer-requests`)
* Accepts `{ "title", "description", "category", "is_confidential" }`.
* Validates and creates record tied to authenticated member ID. Status defaults to `pending`.

### 4.5 Official Giving Statement PDF (`GET /member/giving-statement/pdf`)
Generate official tax statement using `Barryvdh\DomPDF\Facade\Pdf`:
```php
public function downloadTaxPdf(Request $request)
{
    $member = auth()->user();
    $year = $request->query('year', date('Y'));

    $contributions = Offering::where('member_id', $member->id)
        ->whereYear('date', $year)
        ->orderBy('date', 'asc')
        ->get();

    $totalAmount = $contributions->sum('amount');

    $pdf = Pdf::loadView('pdf.giving-statement', compact('member', 'contributions', 'totalAmount', 'year'));
    return $pdf->download("giving_statement_{$year}_{$member->id}.pdf");
}
```

---

## 5. Admin Backend Controllers

### 5.1 Admin Duty Roster Controller (`Admin\DutyRosterController`)
* `index(Request $request)`: Filter by date range or duty role.
* `store(Request $request)`: Assign single or recurring duties to members.
* `destroy($id)`: Remove duty assignment with audit logging.

### 5.2 Admin Prayer Desk Controller (`Admin\PrayerDeskController`)
* `index()`: Filter pending/confidential requests for pastors.
* `updateStatus($id, Request $request)`: Change status (`in_prayer`, `answered`) and append `pastoral_notes`.

---

## 6. Deliverables & Definition of Done for Backend Intern

1. [ ] All 4 migrations created, executed, and rollback-tested cleanly.
2. [ ] `Member`, `Family`, `PrayerRequest`, and `DutyAssignment` models created with Eloquent relationships.
3. [ ] Phone number normalization helper implemented and unit-tested with various input formats.
4. [ ] Multi-identifier login and mandatory password change endpoints operational and returning standardized JSON.
5. [ ] Member dashboard, profile, duty roster, and prayer request endpoints functional with Postman/cURL test collection.
6. [ ] PDF statement generation endpoint functional and formatting verified with sample data.
7. [ ] API error handling adheres to standard formats (HTTP 422 for validation, 401 for auth, 403 for forbidden).
