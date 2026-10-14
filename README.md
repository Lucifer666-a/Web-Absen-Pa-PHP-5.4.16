# 📋 Web Absen Pa — Comprehensive Documentation

Welcome to **Web Absen Pa**, a modern, lightweight, and secure multi-channel attendance and event presence management system. Designed for governmental, corporate, or institutional environments, this platform streamlines employee presence recording across regular office days, official roll calls (morning/afternoon assemblies), and specialized meetings or seminars.

The system features:
1. **Responsive Web Portal** for employees with session protection and instant signature capture.
2. **Comprehensive Admin Dashboard** for monitoring, session management, user control, and Excel reporting.
3. **Robust REST API Backend** for mobile (Android) app integrations with bearer token security.

---

## 📑 Table of Contents

- [Key Features](#-key-features)
- [System Architecture & Interfaces](#-system-architecture--interfaces)
  - [1. Employee Web Portal (`/absen/web/`)](#1-employee-web-portal-absenweb)
  - [2. Administrative Panel (`/absen/admin/`)](#2-administrative-panel-absenadmin)
  - [3. Mobile REST API (`/absen/api/`)](#3-mobile-rest-api-absenapi)
- [Technology Stack](#-technology-stack)
- [Database Architecture](#-database-architecture)
- [Security & Validation Mechanisms](#-security--validation-mechanisms)
- [Directory Structure](#-directory-structure)
- [Getting Started & Installation](#-getting-started--installation)
- [Default Credentials](#-default-credentials)
- [API Reference Summary](#-api-reference-summary)
- [License](#-license)

---

## 🚀 Key Features

- **Multi-Category Attendance Tracking**:
  - **Daily Presence (Absensi Harian)**: Morning check-in and evening check-out with automated punctuality classification (`tepat_waktu` / `terlambat`).
  - **Assembly / Roll Call Presence (Presensi Apel)**: Scheduled for Monday Morning and Friday Afternoon assemblies with digital touch signature.
  - **Meeting / Seminar Presence (Presensi Rapat)**: Supports dynamic multi-event creation, PIN protection, opening/closing statuses, and digital signature verification.
- **Account-Locked Attendance**: Employees log into their individual account; presence is automatically tied to their authenticated profile—preventing proxy or fraudulent submissions on behalf of peers.
- **Digital Canvas Signature**: Touch-friendly, zero-dependency HTML5 Canvas signature pad with clear, preview, and base64 export capabilities.
- **One Presence Per Account Rule**: Robust database checks prevent double submissions for the same employee within the same active session or date.
- **Admin Command Center**: Tabbed real-time interface with live counters, data filtering (by employee, date range, or event), audit logs, and account activation switches (soft delete).
- **One-Click Excel Reporting**: Export attendance logs into formatted `.xlsx` spreadsheets (headers, metadata, timestamps, and footers).
- **Mobile-Ready REST API**: Complete JSON endpoints with token validation and refresh rotation for Android applications.

---

## 🖥 System Architecture & Interfaces

The system is separated into three distinct functional areas:

```
                          ┌───────────────────────────┐
                          │   System Entry Point      │
                          │   (absen/index.php)       │
                          └─────────────┬─────────────┘
                                        │ Redirects
                                        ▼
             ┌─────────────────────────────────────────────────────┐
             │       Employee Web Portal (/absen/web/)             │
             │   - login.php (with quick Admin Link)               │
             │   - index.php (Role Selection: Apel vs Rapat)       │
             │   - absen_apel.php & absen_rapat.php (Canvas Sign)  │
             └──────────────────────────┬──────────────────────────┘
                                        │
             ┌──────────────────────────┴──────────────────────────┐
             │                                                     │
             ▼                                                     ▼
┌───────────────────────────────┐             ┌───────────────────────────────┐
│ Administrative Dashboard      │             │ Android REST API Services     │
│ (/absen/admin/)               │             │ (/absen/api/)                 │
│ - Live Dashboard & Metrics    │             │ - Auth & Token Refresh        │
│ - Meeting/Event Management    │             │ - Daily In/Out Absen          │
│ - Employee Master Records     │             │ - Apel & Rapat Absen          │
│ - Excel Spreadsheet Exports   │             │ - User Status & History       │
└───────────────────────────────┘             └───────────────────────────────┘
                                        ▲
                                        │
                                ┌───────┴───────┐
                                │ MySQL Database│
                                │  (`db_absen`) │
                                └───────────────┘
```

---

### 1. Employee Web Portal (`/absen/web/`)

Tailored for mobile and desktop browsers with an intuitive, clean emerald theme:

1. **Employee Login (`web/login.php`)**:
   - Modern floating card interface with institutional branding.
   - Credentials authenticated against the `users` database table (bcrypt hash & active status verification).
   - Direct button in the corner to switch to **"Login sebagai Administrator"**.
2. **Portal Hub (`web/index.php`)**:
   - Greet banner displaying employee name, NIP, and designation.
   - Quick two-card selection: **Presensi Apel** or **Presensi Rapat**.
   - Logout button destroying employee session while preserving global settings.
3. **Assembly Attendance Form (`web/absen_apel.php`)**:
   - Locked identity card (Name, NIP, and Role auto-populated from session—no dropdowns).
   - Session selector (Monday Morning or Friday Afternoon) with auto-detection.
   - Interactive HTML5 signature pad.
4. **Meeting Attendance Form (`web/absen_rapat.php`)**:
   - Locked employee profile card.
   - Selection of open meetings/events fetched dynamically from the database.
   - Event PIN verification to ensure physical presence in the room.
   - Interactive HTML5 signature pad.
5. **Success Page (`web/success.php`)**:
   - Animated visual feedback indicating successful submission with summary card and return links.

---

### 2. Administrative Panel (`/absen/admin/`)

Secured via `checkAdminSession()`:

- **Dashboard Tab (`dashboard.php?tab=rapat`)**: View attendees across all meetings, filter by event or employee, preview digital signatures, and export to Excel.
- **Assembly Tab (`dashboard.php?tab=apel`)**: Monitor morning/afternoon assembly attendance, filter by date and employee, check absenteeism lists ("Pegawai Belum Absen").
- **Daily Attendance Tab (`dashboard.php?tab=harian`)**: Track check-in & check-out times, punctuality indicators, and download full logs.
- **Recap Tab (`dashboard.php?tab=rekap`)**: Monthly presence summary per employee across all attendance types with high-level KPI cards.
- **User Management Tab (`dashboard.php?tab=users`)**: Add, edit, or toggle employee active status (`is_active` soft-delete) without breaking historic foreign keys.
- **Event Management (`manage_acara.php`)**: Create meetings, assign 6-digit PIN codes, toggle status (`BUKA` / `TUTUP`), and manage attendees.
- **Settings & Logs (`dashboard.php?tab=pengaturan`)**: Global static API tokens, configuration details, and REST API testing endpoints.

---

### 3. Mobile REST API (`/absen/api/`)

Enables Android apps to synchronize with the centralized database:

- `POST /api/login.php`: Authenticates employee credentials, returns user profile and access token.
- `POST /api/logout.php`: Invalidates active session.
- `POST /api/refresh_token.php`: Generates fresh access token.
- `POST /api/absen_harian.php`: Records daily check-in (before cut-off) or check-out, with photo upload support.
- `POST /api/absen_apel.php`: Registers assembly attendance with digital signature payload.
- `POST /api/absen.php`: Registers meeting attendance validated against the event PIN.
- `GET  /api/acara.php`: Retrieves list of currently active meetings.
- `GET  /api/user_status.php`: Fetches today's attendance status and history for the requesting employee.

---

## 🛠 Technology Stack

### Backend
- **PHP 8.x**: Fast, dependency-free core with modular separation of concerns.
- **PDO (PHP Data Objects)**: Secure database communication with prepared statements.
- **Bcrypt (`password_hash` / `password_verify`)**: High-entropy password hashing.
- **JSON RESTful Architecture**: Clean, standardized response formats for client-side consuming.

### Frontend
- **HTML5 Semantic Markup**: Accessible and SEO-friendly structures.
- **Vanilla CSS3**: Modern design system utilizing custom CSS properties, flexbox, CSS grid, glassmorphism, responsive media queries, and smooth micro-animations (no heavy frontend frameworks required).
- **Vanilla JavaScript (ES6+)**:
  - Touch-friendly HTML5 Canvas for digital signature drawing.
  - Asynchronous form submissions (`fetch`) with instant UI feedback.
  - Client-side DOM filtering and search.
- **Google Fonts (Outfit / Inter / Plus Jakarta Sans)**: Sleek, high-readability modern typography.
- **SheetJS (xlsx.full.min.js)**: Client-side Excel `.xlsx` spreadsheet generation.

### Database & Storage
- **MySQL / MariaDB**: Relational schema with foreign key constraints, indexes, and transactional consistency.
- **Base64 / MediumText Storage**: Vector/canvas signatures stored directly in records for self-contained, portable reports.

---

## 🗄 Database Architecture

The system operates on the `db_absen` schema:

| Table | Description | Key Fields |
|---|---|---|
| `users` | Employee master data & web/mobile auth | `id`, `nip`, `nama`, `jabatan`, `username`, `password`, `is_active`, `created_at` |
| `admin` | Super administrator accounts | `id`, `username`, `password`, `nama_lengkap`, `created_at` |
| `acara` | Multi-meeting registry | `id`, `nama_acara`, `pin_acara`, `status` (`BUKA`/`TUTUP`), `created_at` |
| `presensi` | Attendance records for meetings | `id`, `acara_id` (FK), `user_id` (FK), `nama`, `jabatan`, `tanda_tangan`, `waktu_absen` |
| `presensi_apel` | Attendance records for assemblies | `id`, `user_id` (FK), `nama`, `jabatan`, `tanda_tangan`, `sesi` (`pagi`/`sore`), `tanggal`, `waktu` |
| `absensi_harian` | Daily office check-in / check-out | `id`, `user_id` (FK), `tanggal`, `waktu_checkin`, `waktu_checkout`, `status_checkin`, `foto_path` |
| `pengaturan` | Global token & static configurations | `id`, `access_token`, `updated_at` |

---

## 🔒 Security & Validation Mechanisms

- **SQL Injection Prevention**: 100% of queries use PDO Prepared Statements with bound parameters.
- **Cross-Site Request Forgery (CSRF)**: All web forms include cryptographically secure CSRF tokens verified via `csrf.php` before processing.
- **Cross-Site Scripting (XSS)**: Strict escaping of all output via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Session Protection**:
  - Distinct session keys for employees (`$_SESSION['pegawai_*']`) and administrators (`$_SESSION['admin_*']`) to prevent privilege escalation.
  - `session_regenerate_id(true)` executed upon every successful authentication.
- **Account State Checking**: Deactivated accounts (`is_active = 0`) are immediately blocked from both web and mobile login.
- **Duplicate Prevention**: Database checks enforce that a single user account cannot submit duplicate attendance for the same meeting or the same assembly session.

---

## 📁 Directory Structure

```
WebAbsenPa/
├── README.md                     # Project documentation (this file)
└── absen/                        # Main web application root
    ├── index.php                 # Entry router (redirects to web/index.php)
    ├── database.sql              # Database schema and seed data
    │
    ├── config/                   # Core configurations & utilities
    │   ├── database.php          # PDO connection & session helpers
    │   └── csrf.php              # CSRF protection tokens and middleware
    │
    ├── web/                      # Employee Presence Web Portal
    │   ├── auth_check.php        # Employee session guard
    │   ├── login.php             # Employee login page (with admin switch)
    │   ├── proses_login.php      # Employee authentication handler
    │   ├── logout.php            # Employee logout handler
    │   ├── index.php             # Portal home (Apel vs Rapat selector)
    │   ├── absen_apel.php        # Assembly attendance form (canvas sign)
    │   ├── proses_apel.php       # Assembly submission processor
    │   ├── absen_rapat.php       # Meeting attendance form (PIN + canvas)
    │   ├── proses_rapat.php      # Meeting submission processor
    │   └── success.php           # Post-submission confirmation screen
    │
    ├── admin/                    # Administrative Control Center
    │   ├── auth_check.php        # Admin authentication middleware
    │   ├── login.php             # Administrator login page
    │   ├── logout.php            # Administrator logout script
    │   ├── dashboard.php         # Central management dashboard (multi-tab)
    │   ├── manage_acara.php      # Meeting creation, PIN, and status toggles
    │   ├── manage_users.php      # Employee user CRUD and active toggles
    │   ├── export_excel.php      # Server-side spreadsheet exporter
    │   └── events.php            # Event helper handler
    │
    ├── api/                      # Mobile REST API Endpoints (JSON)
    │   ├── index.php             # API documentation & health check
    │   ├── login.php             # Mobile login endpoint
    │   ├── logout.php            # Mobile logout endpoint
    │   ├── refresh_token.php     # Token refresh endpoint
    │   ├── acara.php             # List active meetings
    │   ├── absen.php             # Submit meeting attendance
    │   ├── absen_apel.php        # Submit assembly attendance
    │   ├── absen_harian.php      # Submit daily check-in / check-out
    │   └── user_status.php       # User presence profile & history
    │
    ├── assets/                   # Static resources (CSS, JS, Icons)
    └── uploads/                  # Storage directory for captured photos
```

---

## ⚙️ Getting Started & Installation

### Prerequisites
- Web Server: **Apache** or **Nginx** (XAMPP, Laragon, or standalone)
- **PHP 8.0+** with `pdo_mysql`, `mbstring`, and `openssl` extensions enabled
- **MySQL 5.7+** or **MariaDB 10.3+**

### Step-by-Step Setup

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/Lucifer666-a/Web_Absen_Pa.git
   ```

2. **Deploy to Web Directory**:
   Move the project folder into your web server's document root (e.g., `C:/xampp/htdocs/WebAbsenPa`).

3. **Import Database Schema**:
   - Open phpMyAdmin or your MySQL CLI client:
     ```bash
     mysql -u root -p < absen/database.sql
     ```
   - This creates the `db_absen` database and seeds default administrative and sample employee accounts.

4. **Configure Database Connection**:
   Check `absen/config/database.php` and adjust credentials if needed:
   ```php
   $host = 'localhost';
   $db   = 'db_absen';
   $user = 'root';
   $pass = ''; // Set your database password here
   ```

5. **Launch in Browser**:
   - **Employee Web Portal**: `http://localhost/WebAbsenPa/absen/` (auto-redirects to `absen/web/login.php`)
   - **Admin Panel**: `http://localhost/WebAbsenPa/absen/admin/login.php`
   - **API Base**: `http://localhost/WebAbsenPa/absen/api/`

---

## 🔑 Default Credentials

> [!WARNING]
> Change these default passwords immediately when deploying to a production server!

### Administrator Account
- **URL**: `/absen/admin/login.php`
- **Username**: `admin`
- **Password**: `admin123`

### Sample Employee Account
- **URL**: `/absen/web/login.php`
- **Username**: `pegawai1`
- **Password**: `user123`

### Sample Default Meeting
- **Title**: `Rapat Perdana Acara Default`
- **PIN Code**: `123456`

---

## 📡 API Reference Summary

All API endpoints accept and return JSON (`Content-Type: application/json`):

| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/login.php` | Employee login, returns token & user payload | No |
| `POST` | `/api/logout.php` | Revoke session/token | Yes (Bearer) |
| `POST` | `/api/refresh_token.php` | Request a refreshed access token | Yes (Bearer) |
| `GET`  | `/api/acara.php` | List currently open meetings with status | No |
| `POST` | `/api/absen.php` | Submit meeting attendance with PIN & signature | Yes (Bearer) |
| `POST` | `/api/absen_apel.php` | Submit assembly attendance with signature | Yes (Bearer) |
| `POST` | `/api/absen_harian.php` | Submit daily morning check-in or evening check-out | Yes (Bearer) |
| `GET`  | `/api/user_status.php` | Check current user's daily attendance & history | Yes (Bearer) |

---

## 📄 License

This project is developed and maintained for internal institutional attendance management. Released under the [MIT License](LICENSE) (or organizational proprietary terms where applicable).
