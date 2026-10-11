<?php
// absen/admin/dashboard.php
require_once 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfCheck();
}

// Base URL REST API untuk Aplikasi Android
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$api_base_url = $protocol . $_SERVER['HTTP_HOST'] . str_replace('/admin/dashboard.php', '/api/', $_SERVER['PHP_SELF']);
$api_acara_url = $api_base_url . 'acara.php';
$api_absen_url = $api_base_url . 'absen.php';
$api_apel_url  = $api_base_url . 'absen_apel.php';

// Tentukan Tab Aktif: 'rapat', 'apel', 'harian', 'rekap', 'users', atau 'pengaturan'
$active_tab = $_GET['tab'] ?? 'rapat';
if (!in_array($active_tab, ['rapat', 'apel', 'harian', 'rekap', 'users', 'pengaturan'], true)) {
    $active_tab = 'rapat';
}

// ----------------------------------------------------
// DATA TAB 1: PRESENSI RAPAT / ACARA
// ----------------------------------------------------
$stmtListAcara = $pdo->query("SELECT * FROM acara ORDER BY id DESC");
$list_acara = $stmtListAcara->fetchAll();

$selected_acara_id = $_GET['acara_id'] ?? null;
if (!$selected_acara_id && count($list_acara) > 0) {
    $selected_acara_id = $list_acara[0]['id'];
}

$active_acara = null;
foreach ($list_acara as $acara) {
    if ($acara['id'] == $selected_acara_id) {
        $active_acara = $acara;
        break;
    }
}

$data_presensi = [];
if ($selected_acara_id) {
    $presensiStmt = $pdo->prepare("SELECT * FROM presensi WHERE acara_id = ? ORDER BY waktu_absen DESC");
    $presensiStmt->execute([$selected_acara_id]);
    $data_presensi = $presensiStmt->fetchAll();
}
$total_hadir = count($data_presensi);

// ----------------------------------------------------
// DATA TAB 2: PRESENSI APEL (PAGI / SORE)
// ----------------------------------------------------
$sesi_filter  = $_GET['sesi'] ?? 'semua';
$tgl_mulai    = $_GET['tgl_mulai'] ?? '';
$tgl_selesai  = $_GET['tgl_selesai'] ?? '';
$apel_user_id = (int)($_GET['apel_user_id'] ?? 0);

$sqlApel = "SELECT * FROM presensi_apel WHERE 1=1";
$paramsApel = [];

if (in_array($sesi_filter, ['pagi', 'sore'], true)) {
    $sqlApel .= " AND sesi = ?";
    $paramsApel[] = $sesi_filter;
}
if (!empty($tgl_mulai)) {
    $sqlApel .= " AND tanggal >= ?";
    $paramsApel[] = $tgl_mulai;
}
if (!empty($tgl_selesai)) {
    $sqlApel .= " AND tanggal <= ?";
    $paramsApel[] = $tgl_selesai;
}
if ($apel_user_id > 0) {
    // Ambil nama user untuk fallback jika user_id di presensi_apel NULL
    $stmtUFilter = $pdo->prepare("SELECT nama FROM users WHERE id = ? LIMIT 1");
    $stmtUFilter->execute([$apel_user_id]);
    $uFilterRow = $stmtUFilter->fetch();
    $uFilterNama = $uFilterRow ? $uFilterRow['nama'] : '';

    $sqlApel .= " AND (user_id = ? OR nama = ?)";
    $paramsApel[] = $apel_user_id;
    $paramsApel[] = $uFilterNama;
}

$sqlApel .= " ORDER BY tanggal DESC, waktu DESC, id DESC";
$stmtApel = $pdo->prepare($sqlApel);
$stmtApel->execute($paramsApel);
$data_presensi_apel = $stmtApel->fetchAll();
$total_apel = count($data_presensi_apel);

// ----------------------------------------------------
// DATA TAB 3: ABSENSI HARIAN
// ----------------------------------------------------
$harian_tgl_mulai   = $_GET['harian_tgl_mulai'] ?? '';
$harian_tgl_selesai = $_GET['harian_tgl_selesai'] ?? '';
$harian_user_id     = (int)($_GET['harian_user_id'] ?? 0);

$sqlHarian = "SELECT h.*, u.nama, u.jabatan, u.nip FROM absensi_harian h JOIN users u ON h.user_id = u.id WHERE 1=1";
$paramsHarian = [];

if (!empty($harian_tgl_mulai)) {
    $sqlHarian .= " AND h.tanggal >= ?";
    $paramsHarian[] = $harian_tgl_mulai;
}
if (!empty($harian_tgl_selesai)) {
    $sqlHarian .= " AND h.tanggal <= ?";
    $paramsHarian[] = $harian_tgl_selesai;
}
if ($harian_user_id > 0) {
    $sqlHarian .= " AND h.user_id = ?";
    $paramsHarian[] = $harian_user_id;
}

$sqlHarian .= " ORDER BY h.tanggal DESC, h.waktu_checkin DESC, h.id DESC";
$stmtHarian = $pdo->prepare($sqlHarian);
$stmtHarian->execute($paramsHarian);
$data_presensi_harian = $stmtHarian->fetchAll();
$total_harian = count($data_presensi_harian);

// ----------------------------------------------------
// DATA TAB 4: REKAP BULANAN & DAFTAR TIDAK HADIR
// ----------------------------------------------------
$rekap_bulan = $_GET['rekap_bulan'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $rekap_bulan)) {
    $rekap_bulan = date('Y-m');
}
$rekap_tgl_absent = $_GET['rekap_tgl_absent'] ?? date('Y-m-d');

// Query Rekap Kehadiran Per Pegawai
$stmtRekap = $pdo->prepare("
    SELECT u.id, u.nip, u.nama, u.jabatan,
        (SELECT COUNT(*) FROM presensi_apel a WHERE (a.user_id = u.id OR a.nama = u.nama) AND a.sesi = 'pagi' AND a.tanggal LIKE ?) AS total_pagi,
        (SELECT COUNT(*) FROM presensi_apel a WHERE (a.user_id = u.id OR a.nama = u.nama) AND a.sesi = 'sore' AND a.tanggal LIKE ?) AS total_sore,
        (SELECT COUNT(*) FROM presensi r WHERE (r.user_id = u.id OR r.nama = u.nama) AND r.waktu_absen LIKE ?) AS total_rapat,
        (SELECT COUNT(*) FROM absensi_harian h WHERE h.user_id = u.id AND h.tanggal LIKE ?) AS total_harian
    FROM users u
    ORDER BY u.nama ASC
");
$likeBulan = $rekap_bulan . '-%';
$stmtRekap->execute([$likeBulan, $likeBulan, $likeBulan, $likeBulan]);
$data_rekap_pegawai = $stmtRekap->fetchAll();

// Query Pegawai Tidak Hadir Apel pada tanggal tertentu
$stmtAbsentApel = $pdo->prepare("
    SELECT u.id, u.nip, u.nama, u.jabatan
    FROM users u
    WHERE u.is_active = 1 AND u.id NOT IN (
        SELECT DISTINCT user_id FROM presensi_apel WHERE tanggal = ? AND user_id IS NOT NULL
    ) AND u.nama NOT IN (
        SELECT DISTINCT nama FROM presensi_apel WHERE tanggal = ?
    )
    ORDER BY u.nama ASC
");
$stmtAbsentApel->execute([$rekap_tgl_absent, $rekap_tgl_absent]);
$data_absent_apel = $stmtAbsentApel->fetchAll();

// ----------------------------------------------------
// DATA TAB 5: KELOLA USERS / PEGAWAI
// ----------------------------------------------------
$stmtUsers = $pdo->query("SELECT * FROM users ORDER BY nama ASC");
$list_users = $stmtUsers->fetchAll();
$total_users = count($list_users);

// ----------------------------------------------------
// LOGIKA GANTI PASSWORD ADMIN & LOG AKTIVITAS & GRAFIK
// ----------------------------------------------------
$change_pass_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_admin_pass'])) {
    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!empty($old_pass) && !empty($new_pass) && !empty($confirm_pass)) {
        if ($new_pass !== $confirm_pass) {
            $change_pass_msg = 'error_confirm';
        } else {
            $admin_id = $_SESSION['admin_id'] ?? 0;
            $stmtAdm = $pdo->prepare("SELECT password FROM admin WHERE id = ? LIMIT 1");
            $stmtAdm->execute([$admin_id]);
            $admData = $stmtAdm->fetch();

            if ($admData && password_verify($old_pass, $admData['password'])) {
                $newHash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmtUp = $pdo->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $stmtUp->execute([$newHash, $admin_id]);
                logAdminActivity($pdo, 'Ganti Password Admin', 'Admin mengubah password akun sendiri');
                $change_pass_msg = 'success';
            } else {
                $change_pass_msg = 'error_old';
            }
        }
    } else {
        $change_pass_msg = 'error_empty';
    }
}

// Data Log Aktivitas (20 Terakhir)
$stmtLogs = $pdo->query("SELECT * FROM log_aktivitas ORDER BY id DESC LIMIT 20");
$list_logs = $stmtLogs->fetchAll();

// Data Grafik Kehadiran 7 Hari Terakhir
$chart_dates = [];
$chart_apel_data = [];
$chart_harian_data = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_dates[] = date('d/m', strtotime($d));
    
    // Apel count
    $stA = $pdo->prepare("SELECT COUNT(*) FROM presensi_apel WHERE tanggal = ?");
    $stA->execute([$d]);
    $chart_apel_data[] = (int)$stA->fetchColumn();

    // Harian count
    $stH = $pdo->prepare("SELECT COUNT(*) FROM absensi_harian WHERE tanggal = ?");
    $stH->execute([$d]);
    $chart_harian_data[] = (int)$stH->fetchColumn();
}

$msg = $_GET['msg'] ?? '';

// Helper label untuk tab aktif
$tab_titles = [
    'rapat'      => ['title' => 'Presensi Rapat / Acara', 'icon' => '📋'],
    'apel'       => ['title' => 'Presensi Apel (Pagi / Sore)', 'icon' => '📣'],
    'harian'     => ['title' => 'Absen Harian', 'icon' => '📅'],
    'rekap'      => ['title' => 'Rekap & Daftar Tidak Hadir', 'icon' => '📊'],
    'users'      => ['title' => 'Data Pegawai / Users', 'icon' => '👥'],
    'pengaturan' => ['title' => 'Pengaturan & Log Aktivitas', 'icon' => '⚙️'],
];
$current_tab_info = $tab_titles[$active_tab] ?? ['title' => 'Dashboard', 'icon' => '⚡'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Presensi Digital</title>
    <!-- Font Inter (lokal, tanpa internet) -->
    <link href="../assets/css/inter.css" rel="stylesheet">
    <!-- Bootstrap 5 CSS (lokal) -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- SheetJS & Chart.js (lokal) -->
    <script src="../assets/js/xlsx.full.min.js"></script>
    <script src="../assets/js/chart.min.js"></script>
    <style>
        /* Tab panel switching tanpa reload */
        .tab-panel { display: none; }
        .tab-panel.active-panel { display: block; }
        :root {
            --bg-base: #0b0f19;
            --bg-surface: #111827;
            --bg-card: #162032;
            --bg-card-hover: #1c2940;
            --bg-sidebar: #0d1322;
            --bg-input: #0e1526;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(255, 255, 255, 0.16);
            --text-main: #f3f4f6;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --primary-accent: #38bdf8;
            --primary-accent-glow: rgba(56, 189, 248, 0.2);
            --success-accent: #10b981;
            --warning-accent: #f59e0b;
            --danger-accent: #f43f5e;
            --sidebar-width: 270px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* ---------------- Layout & Sidebar ---------------- */
        .app-layout {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1040;
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-brand {
            padding: 1.5rem 1.4rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            border-bottom: 1px solid var(--border-subtle);
            background: linear-gradient(180deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0) 100%);
        }

        .brand-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid rgba(56, 189, 248, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px var(--primary-accent-glow);
        }

        .brand-text h1 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
            letter-spacing: -0.02em;
        }

        .brand-text p {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin: 0;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .sidebar-user {
            padding: 1.1rem 1.4rem;
            border-bottom: 1px solid var(--border-subtle);
            background: rgba(255,255,255,0.015);
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1e293b;
            border: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-accent);
            font-weight: 700;
            font-size: 0.85rem;
        }

        .user-meta {
            overflow: hidden;
            flex: 1;
        }

        .user-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-main);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.72rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .sidebar-menu {
            flex: 1;
            padding: 1rem 0.85rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .menu-section-label {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.75rem 0.65rem 0.35rem 0.65rem;
        }

        .nav-item-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.7rem 0.9rem;
            border-radius: 9px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-item-btn .nav-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-item-btn .nav-icon {
            font-size: 1.15rem;
            line-height: 1;
        }

        .nav-item-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(2px);
        }

        .nav-item-btn.active {
            color: #ffffff;
            background: linear-gradient(90deg, rgba(56, 189, 248, 0.15) 0%, rgba(56, 189, 248, 0.05) 100%);
            border: 1px solid rgba(56, 189, 248, 0.25);
            font-weight: 600;
        }

        .nav-item-btn.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            bottom: 20%;
            width: 3px;
            background: var(--primary-accent);
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 10px var(--primary-accent);
        }

        .sidebar-actions {
            padding: 1rem 1.1rem;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sidebar-footer {
            padding: 0.9rem 1.4rem;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0,0,0,0.2);
        }

        /* ---------------- Main Content Area ---------------- */
        .main-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            width: calc(100% - var(--sidebar-width));
            transition: margin-left 0.28s ease, width 0.28s ease;
        }

        .topbar {
            height: 70px;
            background: rgba(17, 24, 39, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-subtle);
            position: sticky;
            top: 0;
            z-index: 1020;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.75rem;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-toggle-btn {
            display: none;
            background: transparent;
            border: 1px solid var(--border-subtle);
            color: var(--text-main);
            border-radius: 8px;
            padding: 0.45rem 0.65rem;
            cursor: pointer;
        }

        .page-header-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header-title h2 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
            color: #ffffff;
            letter-spacing: -0.01em;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .content-body {
            padding: 1.75rem;
            flex: 1;
        }

        /* ---------------- Dark UI Components ---------------- */
        .card-custom {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .card-custom:hover {
            border-color: var(--border-hover);
        }

        .card-stat {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .table-wrapper {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 1.5rem;
            overflow-x: auto;
            box-shadow: 0 4px 24px rgba(0,0,0,0.25);
        }

        /* ---------------- Tables in Dark Theme ---------------- */
        .table {
            color: var(--text-main) !important;
            margin-bottom: 0;
            border-color: var(--border-subtle) !important;
        }

        .table thead th {
            background: rgba(255, 255, 255, 0.04) !important;
            color: var(--text-secondary) !important;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border-subtle) !important;
            border-top: none;
        }

        .table tbody td {
            padding: 0.85rem 1rem;
            background: transparent !important;
            color: var(--text-main) !important;
            border-bottom: 1px solid var(--border-subtle) !important;
            font-size: 0.875rem;
        }

        .table-hover tbody tr:hover td {
            background: rgba(255, 255, 255, 0.035) !important;
        }

        .table-primary, .table-primary td {
            background: rgba(56, 189, 248, 0.12) !important;
            color: #ffffff !important;
        }

        .table-success, .table-success td {
            background: rgba(16, 185, 129, 0.15) !important;
            color: #ffffff !important;
        }

        .table-info, .table-info td {
            background: rgba(14, 165, 233, 0.15) !important;
            color: #ffffff !important;
        }

        .signature-img {
            max-height: 48px;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 3px;
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
            border-radius: 6px;
        }

        /* ---------------- Form Controls in Dark Theme ---------------- */
        .form-control, .form-select {
            background-color: var(--bg-input) !important;
            border: 1px solid var(--border-subtle) !important;
            color: var(--text-main) !important;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--bg-input) !important;
            border-color: var(--primary-accent) !important;
            box-shadow: 0 0 0 3px var(--primary-accent-glow) !important;
            color: #ffffff !important;
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-label {
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .input-group-text {
            background-color: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid var(--border-subtle) !important;
            color: var(--text-secondary) !important;
        }

        /* ---------------- Buttons & Pills ---------------- */
        .btn-modern-primary {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(2, 132, 199, 0.25);
            transition: all 0.2s ease;
        }

        .btn-modern-primary:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            border-color: var(--primary-accent);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-modern-outline {
            background: transparent;
            border: 1px solid var(--border-subtle);
            color: var(--text-main);
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-modern-outline:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--border-hover);
            color: #ffffff;
        }

        /* ---------------- Modals in Dark Theme ---------------- */
        .modal-content {
            background-color: var(--bg-surface) !important;
            border: 1px solid var(--border-subtle) !important;
            border-radius: 14px;
            color: var(--text-main);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-subtle) !important;
            padding: 1.25rem 1.5rem;
        }

        .modal-header .modal-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: #ffffff;
        }

        .modal-footer {
            border-top: 1px solid var(--border-subtle) !important;
            padding: 1.1rem 1.5rem;
        }

        .modal-backdrop.show {
            opacity: 0.75;
        }

        /* ---------------- Alerts ---------------- */
        .alert-dark-custom {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid var(--border-subtle);
            color: var(--text-main);
            border-radius: 10px;
        }

        /* ---------------- Badges ---------------- */
        .badge-count {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-main);
            font-size: 0.72rem;
            padding: 0.25em 0.6em;
            border-radius: 20px;
        }

        /* ---------------- Sidebar Backdrop for Mobile ---------------- */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 1030;
        }

        /* ---------------- Responsive Styles ---------------- */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-backdrop.show {
                display: block;
            }

            .main-wrapper {
                margin-left: 0;
                width: 100%;
            }

            .sidebar-toggle-btn {
                display: inline-flex;
            }

            .content-body {
                padding: 1.25rem 1rem;
            }

            .topbar {
                padding: 0 1.25rem;
            }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- Backdrop Overlay untuk Mobile/Tablet -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar(false)"></div>

    <!-- ================= SIDEBAR NAVIGATION ================= -->
    <aside class="sidebar" id="appSidebar">
        <!-- Logo / Brand Header -->
        <div class="sidebar-brand">
            <div class="brand-icon-box">
                🛡️
            </div>
            <div class="brand-text">
                <h1>Presensi Admin</h1>
                <p>Digital Dashboard</p>
            </div>
        </div>

        <!-- Profil Admin & Live Status Indicator -->
        <div class="sidebar-user">
            <div class="user-pill">
                <div class="user-avatar">
                    <?= strtoupper(substr(htmlspecialchars($_SESSION['admin_nama'] ?? 'A'), 0, 2)) ?>
                </div>
                <div class="user-meta">
                    <p class="user-name" title="<?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') ?>">
                        <?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') ?>
                    </p>
                    <p class="user-role">Administrator</p>
                </div>
            </div>
            <div class="mt-2">
                <span id="sse-status-badge" class="badge bg-success-subtle text-success border px-2 py-1 w-100 text-center" style="font-size: 0.72rem;">
                    <span class="spinner-grow spinner-grow-sm me-1" role="status" aria-hidden="true" style="width: 0.55rem; height: 0.55rem;"></span>
                    Real-time Live
                </span>
            </div>
        </div>

        <!-- Menu Navigasi Vertikal -->
        <div class="sidebar-menu">
            <div class="menu-section-label">Presensi & Acara</div>
            
            <a href="#" onclick="switchTab('rapat');return false;" 
               class="nav-item-btn" data-tab="rapat" id="nav-rapat">
                <div class="nav-left">
                    <span class="nav-icon">📋</span>
                    <span>Presensi Rapat</span>
                </div>
            </a>

            <a href="#" onclick="switchTab('apel');return false;" 
               class="nav-item-btn" data-tab="apel" id="nav-apel">
                <div class="nav-left">
                    <span class="nav-icon">📣</span>
                    <span>Presensi Apel</span>
                </div>
            </a>

            <a href="#" onclick="switchTab('harian');return false;" 
               class="nav-item-btn" data-tab="harian" id="nav-harian">
                <div class="nav-left">
                    <span class="nav-icon">📅</span>
                    <span>Absen Harian</span>
                </div>
            </a>

            <div class="menu-section-label">Laporan & Pengguna</div>

            <a href="#" onclick="switchTab('rekap');return false;" 
               class="nav-item-btn" data-tab="rekap" id="nav-rekap">
                <div class="nav-left">
                    <span class="nav-icon">📊</span>
                    <span>Rekap & Tidak Hadir</span>
                </div>
            </a>

            <a href="#" onclick="switchTab('users');return false;" 
               class="nav-item-btn" data-tab="users" id="nav-users">
                <div class="nav-left">
                    <span class="nav-icon">👥</span>
                    <span>Data Pegawai</span>
                </div>
                <span class="badge-count"><?= $total_users ?></span>
            </a>

            <div class="menu-section-label">Sistem & Pengaturan</div>

            <a href="#" onclick="switchTab('pengaturan');return false;" 
               class="nav-item-btn" data-tab="pengaturan" id="nav-pengaturan">
                <div class="nav-left">
                    <span class="nav-icon">⚙️</span>
                    <span>Pengaturan & Log</span>
                </div>
            </a>
        </div>

        <!-- Tombol Quick Action di Sidebar -->
        <div class="sidebar-actions">
            <button type="button" class="btn btn-modern-primary btn-sm w-100 py-2 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#newAcaraModal">
                <span>➕</span>
                <span>Buat Acara Rapat</span>
            </button>
            <button type="button" class="btn btn-modern-outline btn-sm w-100 py-2 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#newUserModal">
                <span>👤</span>
                <span>Tambah Pegawai</span>
            </button>
        </div>

        <!-- Footer Sidebar -->
        <div class="sidebar-footer">
            <a href="logout.php" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2 py-1">
                <span>🚪</span>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- ================= MAIN WRAPPER ================= -->
    <main class="main-wrapper">
        <!-- Topbar Header -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle-btn" type="button" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                    ☰
                </button>
                <div class="page-header-title">
                    <span id="pageHeaderIcon" style="font-size: 1.25rem;"><?= $current_tab_info['icon'] ?></span>
                    <h2 id="pageHeaderTitle"><?= htmlspecialchars($current_tab_info['title']) ?></h2>
                </div>
            </div>

            <div class="topbar-right">
                <button class="btn btn-modern-outline btn-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseApiInfo" aria-expanded="false" aria-controls="collapseApiInfo">
                    <span>📡</span>
                    <span class="d-none d-sm-inline">Info REST API</span>
                </button>
                <a href="logout.php" class="btn btn-outline-danger btn-sm d-none d-sm-inline-flex align-items-center gap-1">
                    <span>Logout</span>
                </a>
            </div>
        </header>

        <!-- Toast Container untuk Real-time SSE -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;" id="toastContainer"></div>

        <!-- Content Body -->
        <div class="content-body">
            <!-- Alert Notifikasi Status Operasi -->
            <?php if ($msg === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    ✨ <strong>Berhasil!</strong> Perubahan telah disimpan ke database.
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($msg === 'error_duplicate'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ⚠️ <strong>Gagal!</strong> NIP atau Username sudah digunakan oleh pengguna lain.
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($msg === 'error_nip_invalid'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ⚠️ <strong>Format Salah!</strong> NIP harus berupa angka saja.
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($msg === 'error_username_spaces'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ⚠️ <strong>Format Salah!</strong> Username tidak boleh mengandung spasi.
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($msg === 'error_fields'): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    ⚠️ <strong>Data Tidak Lengkap!</strong> Mohon lengkapi seluruh field yang wajib diisi.
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($msg === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ⚠️ <strong>Kesalahan Sistem!</strong> Terjadi kendala saat memproses permintaan Anda.
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Header REST API Collapsible Panel -->
            <div class="collapse mb-4" id="collapseApiInfo">
                <div class="card card-custom">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold mb-0 text-white">Integrasi REST API (Aplikasi Android)</h6>
                                <small class="text-secondary">Dokumentasi Endpoint & Connection Base URL</small>
                            </div>
                            <span class="badge bg-success-subtle text-success border">REST API Aktif</span>
                        </div>
                        
                        <p class="mb-2 text-secondary small">Base URL API yang dimasukkan ke aplikasi Android:</p>
                        <div class="input-group mb-3">
                            <span class="input-group-text small">Base API URL</span>
                            <input type="text" class="form-control font-monospace" id="linkApiBase" value="<?= htmlspecialchars($api_base_url) ?>" readonly>
                            <button class="btn btn-modern-primary" type="button" onclick="copyApiLink('linkApiBase')">Copy Base URL</button>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="badge bg-info-subtle text-info border" title="Login pengguna/pegawai">POST /api/login.php</span>
                            <span class="badge bg-primary-subtle text-primary border" title="Ambil status harian & riwayat lintas device">GET /api/user_status.php</span>
                            <span class="badge bg-success-subtle text-success border" title="Check-in & Check-out harian">POST /api/absen_harian.php</span>
                            <span class="badge bg-primary-subtle text-primary border" title="Ambil daftar acara rapat BUKA">GET /api/acara.php</span>
                            <span class="badge bg-success-subtle text-success border" title="Kirim presensi rapat">POST /api/absen.php</span>
                            <span class="badge bg-warning-subtle text-warning border text-dark" title="Kirim presensi apel pagi/sore">POST /api/absen_apel.php</span>
                        </div>
                        <small class="text-muted d-block mt-3">*Sembunyikan panel ini pada penggunaan produksi untuk menjaga kerahasiaan endpoint server.</small>
                    </div>
                </div>
            </div>

            <!-- ================= KONTEN TAB (semua di-render, switch via JS) ================= -->
            <div class="table-wrapper">
                <?php /* TAB 1: RAPAT */ ?>
                <div class="tab-panel" id="panel-rapat">
                    <!-- ================= TAB 1: PRESENSI RAPAT ================= -->
                    <div class="row mb-4 align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="m-0 fw-bold text-white">Rekap Presensi Rapat</h5>
                            <small class="text-secondary">Ringkasan: <span class="badge bg-primary px-2 py-1" id="badge-total-hadir">Hadir <?= $total_hadir ?> dari <?= $total_users ?> pegawai</span></small>
                        </div>
                        <div class="col-md-6 text-md-end d-flex flex-wrap justify-content-md-end gap-2">
                            <form method="GET" action="dashboard.php" class="d-flex align-items-center">
                                <input type="hidden" name="tab" value="rapat">
                                <label class="me-2 fw-bold text-nowrap text-secondary small">Pilih Acara:</label>
                                <select name="acara_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 200px;">
                                    <?php foreach($list_acara as $acara): ?>
                                        <option value="<?= $acara['id'] ?>" <?= $selected_acara_id == $acara['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($acara['nama_acara']) ?> (<?= $acara['status'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <?php if ($active_acara): ?>
                                <button class="btn btn-success btn-sm text-nowrap d-flex align-items-center gap-1" onclick="exportToExcel()">
                                    <span>📊</span> Download Excel
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($active_acara): ?>
                        <div class="alert alert-dark-custom d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 p-3 mb-4">
                            <div>
                                <strong class="text-white">Informasi Acara Dipilih:</strong><br>
                                <span class="text-secondary">Nama:</span> <strong class="text-white"><?= htmlspecialchars($active_acara['nama_acara']) ?></strong><br>
                                <span class="text-secondary">PIN:</span> <span class="badge bg-dark border text-light"><?= htmlspecialchars($active_acara['pin_acara']) ?></span> | 
                                <span class="text-secondary">Dibuat:</span> <span class="text-light"><?= date('d/m/Y H:i', strtotime($active_acara['created_at'])) ?></span> | 
                                <span class="text-secondary">Status:</span> <span class="badge status-badge <?= $active_acara['status'] == 'BUKA' ? 'bg-success' : 'bg-danger' ?>"><?= $active_acara['status'] ?></span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-modern-outline" data-bs-toggle="modal" data-bs-target="#listAcaraModal">
                                    📑 Semua Rapat
                                </button>
                                <button type="button" class="btn btn-sm btn-modern-outline" onclick="openEditAcaraModal(<?= htmlspecialchars(json_encode($active_acara)) ?>)">
                                    ✏️ Edit Acara
                                </button>
                                <form method="POST" action="manage_acara.php" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="acara_id" value="<?= $active_acara['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $active_acara['status'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $active_acara['status'] == 'BUKA' ? 'btn-danger' : 'btn-success' ?>">
                                        Ubah ke <?= $active_acara['status'] == 'BUKA' ? 'TUTUP' : 'BUKA' ?>
                                    </button>
                                </form>
                                <form method="POST" action="manage_acara.php" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus acara rapat ini beserta seluruh data kehadirannya?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="acara_id" value="<?= $active_acara['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">🗑️ Hapus</button>
                                </form>
                            </div>
                        </div>

                        <table class="table table-hover align-middle" id="presensiTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Waktu Absen</th>
                                    <th>Nama Pegawai</th>
                                    <th>Jabatan</th>
                                    <th>Tanda Tangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total_hadir > 0): ?>
                                    <?php $no=1; foreach($data_presensi as $row): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= date('d/m/Y H:i:s', strtotime($row['waktu_absen'])) ?></td>
                                            <td><strong class="text-white"><?= htmlspecialchars($row['nama']) ?></strong></td>
                                            <td><?= htmlspecialchars($row['jabatan']) ?></td>
                                            <td>
                                                <img src="<?= $row['tanda_tangan'] ?>" alt="Tanda Tangan" class="signature-img">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Belum ada data kehadiran pada acara ini</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <p class="text-secondary mb-3">Belum ada acara rapat yang dibuat.</p>
                            <button type="button" class="btn btn-modern-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newAcaraModal">
                                + Buat Acara Rapat Pertama
                            </button>
                        </div>
                    <?php endif; ?>

                </div><!-- /panel-rapat -->
                <?php /* TAB 2: APEL */ ?>
                <div class="tab-panel" id="panel-apel">
                    <!-- ================= TAB 2: PRESENSI APEL ================= -->
                    <div class="row mb-3 align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="m-0 fw-bold text-white">Rekap Presensi Apel</h5>
                            <small class="text-secondary">Apel Senin Pagi & Apel Jumat Sore (Total: <?= $total_apel ?> presensi)</small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" onclick="exportApelToExcel()">
                                <span>📊</span> Download Excel Apel
                            </button>
                        </div>
                    </div>

                    <!-- Form Filter Presensi Apel -->
                    <form method="GET" action="dashboard.php" class="row g-2 mb-4 align-items-end p-3 rounded border" style="background: rgba(255,255,255,0.02); border-color: var(--border-subtle) !important;">
                        <input type="hidden" name="tab" value="apel">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Filter Sesi Apel:</label>
                            <select name="sesi" class="form-select form-select-sm">
                                <option value="semua" <?= $sesi_filter === 'semua' ? 'selected' : '' ?>>Semua Sesi (Pagi & Sore)</option>
                                <option value="pagi" <?= $sesi_filter === 'pagi' ? 'selected' : '' ?>>Apel Pagi (Senin)</option>
                                <option value="sore" <?= $sesi_filter === 'sore' ? 'selected' : '' ?>>Apel Sore (Jumat)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Filter Pegawai:</label>
                            <select name="apel_user_id" class="form-select form-select-sm">
                                <option value="0">Semua Pegawai</option>
                                <?php foreach($list_users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $apel_user_id == $u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Tanggal Mulai:</label>
                            <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_mulai) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Tanggal Selesai:</label>
                            <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_selesai) ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-modern-primary btn-sm flex-fill">Filter</button>
                            <a href="dashboard.php?tab=apel" class="btn btn-modern-outline btn-sm">Reset</a>
                        </div>
                    </form>

                    <table class="table table-hover align-middle" id="presensiApelTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Tanggal & Jam</th>
                                <th>Sesi Apel</th>
                                <th>Nama Pegawai</th>
                                <th>Jabatan</th>
                                <th>Tanda Tangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_apel > 0): ?>
                                <?php $no=1; foreach($data_presensi_apel as $row): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <?= date('d/m/Y', strtotime($row['tanggal'])) ?> 
                                            <small class="text-secondary ms-1">(<?= date('H:i:s', strtotime($row['waktu'])) ?>)</small>
                                        </td>
                                        <td>
                                            <?php 
                                                $hariIndo = ['1'=>'Senin', '2'=>'Selasa', '3'=>'Rabu', '4'=>'Kamis', '5'=>'Jumat', '6'=>'Sabtu', '7'=>'Minggu'];
                                                $numHari = date('N', strtotime($row['tanggal']));
                                                $namaHari = $hariIndo[$numHari] ?? '';
                                            ?>
                                            <?php if ($row['sesi'] === 'pagi'): ?>
                                                <span class="badge bg-primary">🌅 Pagi (<?= $namaHari ?>)</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">🌇 Sore (<?= $namaHari ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong class="text-white"><?= htmlspecialchars($row['nama']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['jabatan']) ?></td>
                                        <td>
                                            <?php if (!empty($row['tanda_tangan'])): ?>
                                                <img src="<?= $row['tanda_tangan'] ?>" alt="Tanda Tangan" class="signature-img">
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada data presensi apel sesuai filter.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                </div><!-- /panel-apel -->
                <?php /* TAB 3: HARIAN */ ?>
                <div class="tab-panel" id="panel-harian">
                    <!-- ================= TAB 3: ABSENSI HARIAN ================= -->
                    <div class="row mb-3 align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="m-0 fw-bold text-white">Rekap Absensi Harian</h5>
                            <small class="text-secondary">Check-in Pagi & Check-out Sore Pegawai (Total: <?= $total_harian ?> riwayat)</small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" onclick="exportHarianToExcel()">
                                <span>📊</span> Download Excel Absen Harian
                            </button>
                        </div>
                    </div>

                    <!-- Form Filter Absensi Harian -->
                    <form method="GET" action="dashboard.php" class="row g-2 mb-4 align-items-end p-3 rounded border" style="background: rgba(255,255,255,0.02); border-color: var(--border-subtle) !important;">
                        <input type="hidden" name="tab" value="harian">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Filter Pegawai:</label>
                            <select name="harian_user_id" class="form-select form-select-sm">
                                <option value="0">Semua Pegawai</option>
                                <?php foreach($list_users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $harian_user_id == $u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nama']) ?> (<?= htmlspecialchars($u['jabatan']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Tanggal Mulai:</label>
                            <input type="date" name="harian_tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($harian_tgl_mulai) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Tanggal Selesai:</label>
                            <input type="date" name="harian_tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($harian_tgl_selesai) ?>">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-modern-primary btn-sm flex-fill">Terapkan Filter</button>
                            <a href="dashboard.php?tab=harian" class="btn btn-modern-outline btn-sm">Reset</a>
                        </div>
                    </form>

                    <table class="table table-hover align-middle" id="presensiHarianTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Tanggal</th>
                                <th>Nama Pegawai</th>
                                <th>Jabatan</th>
                                <th>Jam Masuk (Check-in)</th>
                                <th>Jam Pulang (Check-out)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_harian > 0): ?>
                                <?php $no=1; foreach($data_presensi_harian as $row): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                        <td><strong class="text-white"><?= htmlspecialchars($row['nama']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['jabatan']) ?></td>
                                        <td>
                                            <?php if (!empty($row['waktu_checkin'])): ?>
                                                <span class="badge bg-success-subtle text-success border"><?= htmlspecialchars($row['waktu_checkin']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted font-monospace">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['waktu_checkout'])): ?>
                                                <span class="badge bg-primary-subtle text-primary border"><?= htmlspecialchars($row['waktu_checkout']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted font-monospace">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada data absensi harian sesuai filter.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                </div><!-- /panel-harian -->
                <?php /* TAB 4: REKAP */ ?>
                <div class="tab-panel" id="panel-rekap">
                    <!-- ================= TAB 4: REKAP BULANAN & DAFTAR TIDAK HADIR ================= -->
                    
                    <!-- SECTION 1: Rekap Per Pegawai Per Bulan -->
                    <div class="row mb-3 align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="m-0 fw-bold text-white">Rekap Kehadiran Per Pegawai Per Bulan</h5>
                            <small class="text-secondary">Jumlah partisipasi apel, rapat, & absen harian per bulan</small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <form method="GET" action="dashboard.php" class="d-flex align-items-center justify-content-md-end gap-2">
                                <input type="hidden" name="tab" value="rekap">
                                <label class="fw-bold small text-nowrap text-secondary">Pilih Bulan:</label>
                                <input type="month" name="rekap_bulan" class="form-control form-control-sm" style="max-width: 180px;" value="<?= htmlspecialchars($rekap_bulan) ?>" onchange="this.form.submit()">
                                <input type="hidden" name="rekap_tgl_absent" value="<?= htmlspecialchars($rekap_tgl_absent) ?>">
                            </form>
                        </div>
                    </div>

                    <table class="table table-hover align-middle mb-5">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>NIP</th>
                                <th>Nama Pegawai</th>
                                <th>Jabatan</th>
                                <th class="text-center">🌅 Apel Pagi</th>
                                <th class="text-center">🌇 Apel Sore</th>
                                <th class="text-center">📋 Rapat</th>
                                <th class="text-center">📅 Harian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($data_rekap_pegawai) > 0): ?>
                                <?php $no=1; foreach($data_rekap_pegawai as $row): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($row['nip'] ?: '-') ?></td>
                                        <td><strong class="text-white"><?= htmlspecialchars($row['nama']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['jabatan']) ?></td>
                                        <td class="text-center"><span class="badge bg-primary-subtle text-primary border fs-6"><?= $row['total_pagi'] ?>x</span></td>
                                        <td class="text-center"><span class="badge bg-warning-subtle text-warning border fs-6"><?= $row['total_sore'] ?>x</span></td>
                                        <td class="text-center"><span class="badge bg-info-subtle text-info border fs-6"><?= $row['total_rapat'] ?>x</span></td>
                                        <td class="text-center"><span class="badge bg-success-subtle text-success border fs-6"><?= $row['total_harian'] ?>x</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Tidak ada data rekap pegawai.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <hr class="my-4" style="border-color: var(--border-subtle);">

                    <!-- SECTION 2: Daftar Tidak Hadir (Apel) pada Tanggal Tertentu -->
                    <div class="row mb-3 align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="m-0 fw-bold text-danger">⚠️ Daftar Pegawai Tidak Hadir (Apel)</h5>
                            <small class="text-secondary">Daftar pegawai yang belum/tidak ada catatan presensi apel pada tanggal terpilih</small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <form method="GET" action="dashboard.php" class="d-flex align-items-center justify-content-md-end gap-2">
                                <input type="hidden" name="tab" value="rekap">
                                <input type="hidden" name="rekap_bulan" value="<?= htmlspecialchars($rekap_bulan) ?>">
                                <label class="fw-bold small text-nowrap text-secondary">Pilih Tanggal Apel:</label>
                                <input type="date" name="rekap_tgl_absent" class="form-control form-control-sm" style="max-width: 180px;" value="<?= htmlspecialchars($rekap_tgl_absent) ?>" onchange="this.form.submit()">
                            </form>
                        </div>
                    </div>

                    <div class="alert alert-dark-custom py-2 px-3 small mb-3">
                        Menampilkan daftar pegawai yang <strong>TIDAK HADIR</strong> pada tanggal <strong><?= date('d/m/Y', strtotime($rekap_tgl_absent)) ?></strong> (Total tidak hadir: <strong class="text-danger"><?= count($data_absent_apel) ?></strong> pegawai).
                    </div>

                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>NIP</th>
                                <th>Nama Pegawai</th>
                                <th>Jabatan</th>
                                <th>Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($data_absent_apel) > 0): ?>
                                <?php $no=1; foreach($data_absent_apel as $row): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($row['nip'] ?: '-') ?></td>
                                        <td><strong class="text-white"><?= htmlspecialchars($row['nama']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['jabatan']) ?></td>
                                        <td><span class="badge bg-danger-subtle text-danger border">❌ Tidak Hadir Apel</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-success py-4">🎉 Seluruh pegawai aktif tercatat HADIR pada tanggal ini!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                </div><!-- /panel-rekap -->
                <?php /* TAB 5: USERS */ ?>
                <div class="tab-panel" id="panel-users">
                    <!-- ================= TAB 5: DATA PEGAWAI / USERS ================= -->
                    <div class="row mb-3 align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="m-0 fw-bold text-white">Daftar Pegawai / Pengguna Android</h5>
                            <small class="text-secondary">Akun terdaftar yang digunakan untuk login di aplikasi Android (Total: <?= $total_users ?> pegawai)</small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <button type="button" class="btn btn-modern-primary btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#newUserModal">
                                <span>➕</span> Tambah Pegawai Baru
                            </button>
                        </div>
                    </div>

                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>NIP</th>
                                <th>Nama Pegawai</th>
                                <th>Jabatan</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th style="width: 220px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_users > 0): ?>
                                <?php $no=1; foreach($list_users as $user): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($user['nip'] ?: '-') ?></td>
                                        <td><strong class="text-white"><?= htmlspecialchars($user['nama']) ?></strong></td>
                                        <td><?= htmlspecialchars($user['jabatan']) ?></td>
                                        <td><span class="badge bg-dark border text-light"><?= htmlspecialchars($user['username']) ?></span></td>
                                        <td>
                                            <?php if ((int)($user['is_active'] ?? 1) === 1): ?>
                                                <span class="badge bg-success-subtle text-success border">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-modern-outline me-1" 
                                                    onclick="openEditUserModal(<?= htmlspecialchars(json_encode($user)) ?>)">
                                                ✏️ Edit
                                            </button>
                                            <form action="manage_users.php" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin merubah status aktif pegawai ini?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-sm <?= ((int)($user['is_active'] ?? 1) === 1) ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                                    <?= ((int)($user['is_active'] ?? 1) === 1) ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada pegawai terdaftar. Silakan klik tombol "Tambah Pegawai Baru".</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                </div><!-- /panel-users -->
                <?php /* TAB 6: PENGATURAN */ ?>
                <div class="tab-panel" id="panel-pengaturan">
                    <!-- ================= TAB 6: PENGATURAN & LOG AKTIVITAS ================= -->
                    
                    <?php if ($change_pass_msg === 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            ✨ Password admin berhasil diperbarui!
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif ($change_pass_msg === 'error_old'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            ⚠️ Password lama Anda tidak sesuai.
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif ($change_pass_msg === 'error_confirm'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            ⚠️ Konfirmasi password baru tidak cocok.
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif ($change_pass_msg === 'error_empty'): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            ⚠️ Mohon lengkapi seluruh bidang form password.
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4 mb-4">
                        <!-- Grafik Kehadiran 7 Hari Terakhir -->
                        <div class="col-lg-7">
                            <div class="card card-custom h-100 p-3">
                                <h6 class="fw-bold mb-3 text-white">📈 Grafik Kehadiran (7 Hari Terakhir)</h6>
                                <div style="height: 260px;">
                                    <canvas id="attendanceChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Form Ganti Password Admin -->
                        <div class="col-lg-5">
                            <div class="card card-custom h-100 p-3">
                                <h6 class="fw-bold mb-3 text-white">🔑 Ganti Password Admin</h6>
                                <form method="POST" action="dashboard.php?tab=pengaturan">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action_change_admin_pass" value="1">
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Password Lama</label>
                                        <input type="password" name="old_password" class="form-control form-control-sm" required placeholder="Masukkan password saat ini">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Password Baru</label>
                                        <input type="password" name="new_password" class="form-control form-control-sm" required placeholder="Minimal 6 karakter">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small mb-1">Konfirmasi Password Baru</label>
                                        <input type="password" name="confirm_password" class="form-control form-control-sm" required placeholder="Ulangi password baru">
                                    </div>
                                    <button type="submit" class="btn btn-modern-primary btn-sm w-100 py-2">Update Password</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Log Aktivitas Admin -->
                    <div class="card card-custom p-3 mt-4">
                        <h6 class="fw-bold mb-3 text-white">📜 Log Aktivitas Admin (20 Terakhir)</h6>
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.875rem;">
                            <thead>
                                <tr>
                                    <th style="width: 170px;">Waktu</th>
                                    <th style="width: 150px;">Admin</th>
                                    <th style="width: 200px;">Aksi</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($list_logs) > 0): ?>
                                    <?php foreach($list_logs as $lg): ?>
                                        <tr>
                                            <td><small class="text-secondary"><?= date('d/m/Y H:i:s', strtotime($lg['created_at'])) ?></small></td>
                                            <td><strong class="text-white"><?= htmlspecialchars($lg['admin_nama']) ?></strong></td>
                                            <td><span class="badge bg-secondary-subtle text-light border"><?= htmlspecialchars($lg['aksi']) ?></span></td>
                                            <td><?= htmlspecialchars($lg['detail'] ?: '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada riwayat log aktivitas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div><!-- /panel-pengaturan -->
            </div><!-- /table-wrapper -->
        </div><!-- /content-body -->
    </main>
</div>

<!-- ================= MODAL-MODAL DIALOG ================= -->

<!-- Modal Daftar Semua Acara Rapat -->
<div class="modal fade" id="listAcaraModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Daftar Semua Acara Rapat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 50px;">No</th>
              <th>Nama Acara</th>
              <th>PIN</th>
              <th>Status</th>
              <th>Tanggal Dibuat</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($list_acara) > 0): ?>
              <?php $noAcara=1; foreach($list_acara as $ac): ?>
                <tr class="<?= $ac['id'] == $selected_acara_id ? 'table-primary' : '' ?>">
                  <td><?= $noAcara++ ?></td>
                  <td><strong class="text-white"><?= htmlspecialchars($ac['nama_acara']) ?></strong></td>
                  <td><span class="badge bg-dark border"><?= htmlspecialchars($ac['pin_acara']) ?></span></td>
                  <td><span class="badge <?= $ac['status'] === 'BUKA' ? 'bg-success' : 'bg-danger' ?>"><?= $ac['status'] ?></span></td>
                  <td><small class="text-secondary"><?= date('d/m/Y H:i', strtotime($ac['created_at'])) ?></small></td>
                  <td>
                    <a href="dashboard.php?tab=rapat&acara_id=<?= $ac['id'] ?>" class="btn btn-sm btn-modern-primary me-1">Pilih</a>
                    <button class="btn btn-sm btn-modern-outline me-1" onclick="openEditAcaraModal(<?= htmlspecialchars(json_encode($ac)) ?>)">Edit</button>
                    <form action="manage_acara.php" method="POST" class="d-inline" onsubmit="return confirm('Hapus acara rapat ini?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="acara_id" value="<?= $ac['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">Belum ada acara rapat.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-modern-outline" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Edit Acara Rapat -->
<div class="modal fade" id="editAcaraModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Acara Rapat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="manage_acara.php" method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="acara_id" id="edit_acara_id">
          <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Nama Acara</label>
                <input type="text" class="form-control" name="nama_acara" id="edit_nama_acara" required>
            </div>
            <div class="mb-3">
                <label class="form-label">PIN Acara</label>
                <input type="text" class="form-control" name="pin_acara" id="edit_pin_acara" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-modern-outline" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-modern-primary">Simpan Perubahan</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Buat Acara Baru -->
<div class="modal fade" id="newAcaraModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Buat Acara Rapat Baru</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="manage_acara.php" method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="create">
          <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Nama Acara</label>
                <input type="text" class="form-control" name="nama_acara" required placeholder="Contoh: Rapat Koordinasi Mingguan">
            </div>
            <div class="mb-3">
                <label class="form-label">PIN Acara</label>
                <input type="text" class="form-control" name="pin_acara" required placeholder="Contoh: 123456">
                <div class="form-text text-secondary">Peserta harus memasukkan PIN ini untuk bisa absen rapat.</div>
            </div>
            <div class="alert alert-warning py-2 mb-0 mt-3" style="font-size: 0.85rem;">
                ℹ️ Acara lain yang berstatus BUKA akan otomatis ditutup saat acara rapat baru dibuat.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-modern-outline" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-modern-primary">Buat & Buka Acara</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Tambah Pegawai Baru -->
<div class="modal fade" id="newUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Pegawai Baru</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="manage_users.php" method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="create">
          <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">NIP (Hanya Angka, Opsional)</label>
                <input type="text" class="form-control" name="nip" placeholder="Contoh: 199001012020121001" pattern="[0-9]*" title="NIP hanya boleh berupa angka">
            </div>
            <div class="mb-3">
                <label class="form-label">Nama Lengkap *</label>
                <input type="text" class="form-control" name="nama" required placeholder="Contoh: Ahmad Fauzi">
            </div>
            <div class="mb-3">
                <label class="form-label">Jabatan *</label>
                <input type="text" class="form-control" name="jabatan" required placeholder="Contoh: Staf IT">
            </div>
            <div class="mb-3">
                <label class="form-label">Username (Tanpa Spasi) *</label>
                <input type="text" class="form-control" name="username" required placeholder="Contoh: pegawai1" pattern="^\S+$" title="Username tidak boleh memuat spasi">
            </div>
            <div class="mb-3">
                <label class="form-label">Password *</label>
                <input type="password" class="form-control" name="password" required placeholder="Masukkan password awal">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-modern-outline" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success">Simpan Pegawai</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit Pegawai -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Pegawai / Reset Password</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="manage_users.php" method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="user_id" id="edit_user_id">
          <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">NIP (Hanya Angka, Opsional)</label>
                <input type="text" class="form-control" name="nip" id="edit_nip" pattern="[0-9]*" title="NIP hanya boleh berupa angka">
            </div>
            <div class="mb-3">
                <label class="form-label">Nama Lengkap *</label>
                <input type="text" class="form-control" name="nama" id="edit_nama" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Jabatan *</label>
                <input type="text" class="form-control" name="jabatan" id="edit_jabatan" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Username (Tanpa Spasi) *</label>
                <input type="text" class="form-control" name="username" id="edit_username" required pattern="^\S+$" title="Username tidak boleh memuat spasi">
            </div>
            <div class="mb-3">
                <label class="form-label">Password Baru (Biarkan kosong jika tidak diubah)</label>
                <input type="password" class="form-control" name="password" placeholder="Isi hanya jika ingin mereset password">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active" value="1">
                <label class="form-check-label text-light" for="edit_is_active">Status Akun Aktif</label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-modern-outline" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-modern-primary">Simpan Perubahan</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>

<script>
// ---------------- Dynamic Tab Switching (Tanpa Reload Halaman) ----------------
const tabMeta = {
    'rapat': { title: 'Presensi Rapat / Acara', icon: '📋' },
    'apel': { title: 'Presensi Apel (Pagi / Sore)', icon: '📣' },
    'harian': { title: 'Absen Harian', icon: '📅' },
    'rekap': { title: 'Rekap & Daftar Tidak Hadir', icon: '📊' },
    'users': { title: 'Data Pegawai / Users', icon: '👥' },
    'pengaturan': { title: 'Pengaturan & Log Aktivitas', icon: '⚙️' }
};

function switchTab(tabId, updateUrl = true) {
    if (!tabMeta[tabId]) tabId = 'rapat';

    // 1. Sembunyikan semua panel, munculkan panel target
    document.querySelectorAll('.tab-panel').forEach(function(panel) {
        panel.classList.remove('active-panel');
    });
    var targetPanel = document.getElementById('panel-' + tabId);
    if (targetPanel) {
        targetPanel.classList.add('active-panel');
    }

    // 2. Update status aktif tombol navigasi sidebar
    document.querySelectorAll('.nav-item-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    var targetNav = document.getElementById('nav-' + tabId);
    if (targetNav) {
        targetNav.classList.add('active');
    }

    // 3. Update judul & icon topbar
    var headerIcon = document.getElementById('pageHeaderIcon');
    var headerTitle = document.getElementById('pageHeaderTitle');
    if (headerIcon && tabMeta[tabId]) headerIcon.textContent = tabMeta[tabId].icon;
    if (headerTitle && tabMeta[tabId]) headerTitle.textContent = tabMeta[tabId].title;

    // 4. Update parameter tab di URL tanpa reload halaman
    if (updateUrl && window.history && window.history.replaceState) {
        var url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.replaceState({}, '', url);
    }

    // 5. Resize Chart jika tab rekap dibuka
    if (tabId === 'rekap' && window.myAttendanceChart) {
        setTimeout(function() {
            window.myAttendanceChart.resize();
        }, 60);
    }

    // 6. Tutup sidebar di layar HP/mobile jika sedang terbuka
    if (window.innerWidth < 992) {
        toggleSidebar(false);
    }
}

// Inisialisasi tab aktif saat pertama kali load
document.addEventListener('DOMContentLoaded', function() {
    var urlParams = new URLSearchParams(window.location.search);
    var currentTab = urlParams.get('tab') || '<?= $active_tab ?>';
    switchTab(currentTab, false);
});

// Tangani tombol back / forward browser
window.addEventListener('popstate', function() {
    var urlParams = new URLSearchParams(window.location.search);
    var currentTab = urlParams.get('tab') || 'rapat';
    switchTab(currentTab, false);
});

// ---------------- Responsive Sidebar Toggle ----------------
function toggleSidebar(forceState) {
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !backdrop) return;
    
    if (typeof forceState === 'boolean') {
        if (forceState) {
            sidebar.classList.add('show');
            backdrop.classList.add('show');
        } else {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        }
    } else {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
    }
}

// ---------------- Copy REST API Link ----------------
function copyApiLink(elementId) {
    var copyText = document.getElementById(elementId);
    if (!copyText) return;
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    alert("Base URL REST API berhasil disalin:\n" + copyText.value);
}

// ---------------- Modal Opener Handlers ----------------
function openEditAcaraModal(acara) {
    document.getElementById('edit_acara_id').value = acara.id;
    document.getElementById('edit_nama_acara').value = acara.nama_acara || '';
    document.getElementById('edit_pin_acara').value = acara.pin_acara || '';
    var modal = new bootstrap.Modal(document.getElementById('editAcaraModal'));
    modal.show();
}

function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_nip').value = user.nip || '';
    document.getElementById('edit_nama').value = user.nama || '';
    document.getElementById('edit_jabatan').value = user.jabatan || '';
    document.getElementById('edit_username').value = user.username || '';
    document.getElementById('edit_is_active').checked = (user.is_active === undefined || parseInt(user.is_active) === 1);
    var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

// ---------------- Export Excel Handlers ----------------
function exportToExcel() {
    var table = document.getElementById("presensiTable");
    if (!table) return;
    
    var wb = XLSX.utils.book_new();
    var wsData = [];
    
    wsData.push(["REKAP PRESENSI RAPAT / ACARA"]);
    wsData.push(["Nama Acara: <?= $active_acara ? addslashes(htmlspecialchars($active_acara['nama_acara'])) : '-' ?>"]);
    wsData.push(["PIN Acara: <?= $active_acara ? addslashes(htmlspecialchars($active_acara['pin_acara'])) : '-' ?> | Status: <?= $active_acara ? $active_acara['status'] : '-' ?>"]);
    wsData.push(["Tanggal Cetak: " + new Date().toLocaleDateString('id-ID')]);
    wsData.push([]);
    
    wsData.push(["No", "Waktu Absen", "Nama Pegawai", "Jabatan", "Status Tanda Tangan"]);
    
    for (var i = 1; i < table.rows.length; i++) {
        var row = table.rows[i];
        if (row.cells.length < 4) continue;
        var no = row.cells[0].innerText.trim();
        var waktu = row.cells[1].innerText.trim();
        var nama = row.cells[2].innerText.trim();
        var jabatan = row.cells[3].innerText.trim();
        var hasSign = row.cells[4] && row.cells[4].querySelector('img') ? "Ada Tanda Tangan (Digital)" : "-";
        wsData.push([no, waktu, nama, jabatan, hasSign]);
    }
    
    wsData.push([]);
    wsData.push(["* Catatan: Data presensi ini berasal dari konfirmasi pegawai melalui aplikasi Android, bukan pengganti presensi resmi."]);
    
    var ws = XLSX.utils.aoa_to_sheet(wsData);
    XLSX.utils.book_append_sheet(wb, ws, "Presensi Rapat");
    XLSX.writeFile(wb, "Rekap_Presensi_Rapat_<?= $active_acara ? preg_replace('/[^A-Za-z0-9\-]/', '_', $active_acara['nama_acara']) : 'Acara' ?>.xlsx");
}

function exportApelToExcel() {
    var table = document.getElementById("presensiApelTable");
    if (!table) return;
    
    var wb = XLSX.utils.book_new();
    var wsData = [];
    
    wsData.push(["REKAP PRESENSI APEL (PAGI / SORE)"]);
    wsData.push(["Filter Sesi: <?= htmlspecialchars($sesi_filter) ?> | Periode: <?= htmlspecialchars($tgl_mulai ?: 'Semua') ?> s/d <?= htmlspecialchars($tgl_selesai ?: 'Semua') ?>"]);
    wsData.push(["Tanggal Cetak: " + new Date().toLocaleDateString('id-ID')]);
    wsData.push([]);
    
    wsData.push(["No", "Tanggal & Jam", "Sesi Apel", "Nama Pegawai", "Jabatan", "Status Tanda Tangan"]);
    
    for (var i = 1; i < table.rows.length; i++) {
        var row = table.rows[i];
        if (row.cells.length < 5) continue;
        var no = row.cells[0].innerText.trim();
        var tgl = row.cells[1].innerText.trim();
        var sesi = row.cells[2].innerText.trim();
        var nama = row.cells[3].innerText.trim();
        var jabatan = row.cells[4].innerText.trim();
        var hasSign = row.cells[5] && row.cells[5].querySelector('img') ? "Ada Tanda Tangan (Digital)" : "-";
        wsData.push([no, tgl, sesi, nama, jabatan, hasSign]);
    }
    
    wsData.push([]);
    wsData.push(["* Catatan: Data presensi apel ini berasal dari konfirmasi pegawai melalui aplikasi Android, bukan pengganti presensi resmi."]);
    
    var ws = XLSX.utils.aoa_to_sheet(wsData);
    XLSX.utils.book_append_sheet(wb, ws, "Presensi Apel");
    XLSX.writeFile(wb, "Rekap_Presensi_Apel_<?= htmlspecialchars($sesi_filter) ?>_<?= date('Y-m-d') ?>.xlsx");
}

function exportHarianToExcel() {
    var table = document.getElementById("presensiHarianTable");
    if (!table) return;
    
    var wb = XLSX.utils.book_new();
    var wsData = [];
    
    wsData.push(["REKAP ABSENSI HARIAN"]);
    wsData.push(["Periode: <?= htmlspecialchars($harian_tgl_mulai ?: 'Semua') ?> s/d <?= htmlspecialchars($harian_tgl_selesai ?: 'Semua') ?>"]);
    wsData.push(["Tanggal Cetak: " + new Date().toLocaleDateString('id-ID')]);
    wsData.push([]);
    
    wsData.push(["No", "Tanggal", "Nama Pegawai", "Jabatan", "Jam Masuk (Check-in)", "Jam Pulang (Check-out)"]);
    
    for (var i = 1; i < table.rows.length; i++) {
        var row = table.rows[i];
        if (row.cells.length < 6) continue;
        var no = row.cells[0].innerText.trim();
        var tgl = row.cells[1].innerText.trim();
        var nama = row.cells[2].innerText.trim();
        var jabatan = row.cells[3].innerText.trim();
        var inTime = row.cells[4].innerText.trim();
        var outTime = row.cells[5].innerText.trim();
        wsData.push([no, tgl, nama, jabatan, inTime, outTime]);
    }
    
    wsData.push([]);
    wsData.push(["* Catatan: Data absensi harian ini berasal dari konfirmasi pegawai melalui aplikasi Android, bukan pengganti presensi resmi."]);
    
    var ws = XLSX.utils.aoa_to_sheet(wsData);
    XLSX.utils.book_append_sheet(wb, ws, "Absensi Harian");
    XLSX.writeFile(wb, "Rekap_Absensi_Harian_<?= date('Y-m-d') ?>.xlsx");
}

// ---------------- Inisialisasi Grafik Chart.js ----------------
document.addEventListener("DOMContentLoaded", function () {
    var ctx = document.getElementById('attendanceChart');
    if (ctx) {
        window.myAttendanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_dates) ?>,
                datasets: [
                    {
                        label: 'Presensi Apel',
                        data: <?= json_encode($chart_apel_data) ?>,
                        backgroundColor: '#38bdf8',
                        borderRadius: 6
                    },
                    {
                        label: 'Absensi Harian',
                        data: <?= json_encode($chart_harian_data) ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#94a3b8',
                            font: { family: 'Inter', size: 12 }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#94a3b8', font: { family: 'Inter' } },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: '#94a3b8', font: { family: 'Inter' } },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    }
                }
            }
        });
    }

    // Sambungkan Real-time SSE
    connectSSE();
});

// ====================================================
// REAL-TIME NOTIFICATION (SSE - SERVER-SENT EVENTS)
// ====================================================
let lastPresensiId = 0;
let lastApelId     = 0;
let lastHarianId   = 0;
let totalHadirCount = <?= (int)($total_hadir ?? 0) ?>;
const totalUsersCount = <?= (int)($total_users ?? 0) ?>;
const selectedAcaraId = <?= (int)($selected_acara_id ?? 0) ?>;

function playChime() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime);
        osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.35);
    } catch(e) {}
}

function showSseToast(title, message, bgClass = 'bg-primary') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center text-white border-0 shadow-lg ' + bgClass;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <div class="fw-bold fs-6 mb-1">${title}</div>
                <div>${message}</div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    container.appendChild(toastEl);
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toastEl, { delay: 6000 });
        bsToast.show();
    }
    toastEl.addEventListener('hidden.bs.toast', function() {
        toastEl.remove();
    });
}

function updateSseBadge(connected) {
    const badge = document.getElementById('sse-status-badge');
    if (!badge) return;
    if (connected) {
        badge.className = 'badge bg-success-subtle text-success border px-2 py-1 w-100 text-center';
        badge.innerHTML = '<span class="spinner-grow spinner-grow-sm me-1" role="status"></span>Real-time Live';
    } else {
        badge.className = 'badge bg-warning-subtle text-warning border px-2 py-1 w-100 text-center';
        badge.innerHTML = '⚠️ Menghubungkan...';
    }
}

function renumberTableRows(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const rows = tbody.querySelectorAll('tr');
    let no = 1;
    rows.forEach(row => {
        const firstCell = row.querySelector('td');
        if (firstCell && !isNaN(parseInt(firstCell.innerText.trim()))) {
            firstCell.innerText = no++;
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function connectSSE() {
    let url = 'events.php?last_presensi_id=' + lastPresensiId + '&last_apel_id=' + lastApelId + '&last_harian_id=' + lastHarianId;
    const sse = new EventSource(url);

    sse.onopen = function() {
        updateSseBadge(true);
    };

    sse.addEventListener('init', function(e) {
        try {
            const data = JSON.parse(e.data);
            if (data.last_presensi_id) lastPresensiId = data.last_presensi_id;
            if (data.last_apel_id) lastApelId = data.last_apel_id;
            if (data.last_harian_id) lastHarianId = data.last_harian_id;
        } catch(err){}
    });

    sse.addEventListener('new_absen', function(e) {
        try {
            const payload = JSON.parse(e.data);
            if (payload.last_presensi_id) lastPresensiId = payload.last_presensi_id;
            if (payload.last_apel_id) lastApelId = payload.last_apel_id;
            if (payload.last_harian_id) lastHarianId = payload.last_harian_id;

            let hasNew = false;

            // 1. Presensi Rapat / Acara Baru
            if (payload.new_presensi && payload.new_presensi.length > 0) {
                payload.new_presensi.forEach(item => {
                    hasNew = true;
                    showSseToast('📋 Absen Rapat Baru!', `<b>${escapeHtml(item.nama)}</b> (${escapeHtml(item.jabatan)}) telah absen di rapat <i>${escapeHtml(item.nama_acara || 'Acara')}</i>`, 'bg-primary');
                    
                    if (selectedAcaraId === parseInt(item.acara_id)) {
                        const table = document.getElementById('presensiTable');
                        if (table) {
                            const tbody = table.querySelector('tbody');
                            const emptyRow = tbody.querySelector('td[colspan]');
                            if (emptyRow) emptyRow.closest('tr').remove();

                            const tr = document.createElement('tr');
                            tr.className = 'table-success';
                            tr.innerHTML = `
                                <td>1</td>
                                <td>${item.waktu_absen || payload.timestamp}</td>
                                <td>${escapeHtml(item.nama)}</td>
                                <td>${escapeHtml(item.jabatan)}</td>
                                <td>
                                    ${item.tanda_tangan ? `<img src="${item.tanda_tangan}" alt="Tanda Tangan" class="signature-img">` : '-'}
                                </td>
                            `;
                            tbody.insertBefore(tr, tbody.firstChild);
                            renumberTableRows('presensiTable');
                            setTimeout(() => tr.classList.remove('table-success'), 3000);

                            totalHadirCount++;
                            const badge = document.getElementById('badge-total-hadir');
                            if (badge) {
                                badge.innerText = `Hadir ${totalHadirCount} dari ${totalUsersCount} pegawai`;
                            }
                        }
                    }
                });
            }

            // 2. Presensi Apel Baru
            if (payload.new_apel && payload.new_apel.length > 0) {
                payload.new_apel.forEach(item => {
                    hasNew = true;
                    const sesiLabel = item.sesi === 'pagi' ? 'Apel Pagi (Senin)' : 'Apel Sore (Jumat)';
                    showSseToast('📣 Presensi Apel Baru!', `<b>${escapeHtml(item.nama)}</b> (${escapeHtml(item.jabatan)}) mengirim presensi <i>${sesiLabel}</i>`, 'bg-info text-dark');

                    const table = document.getElementById('presensiApelTable');
                    if (table) {
                        const tbody = table.querySelector('tbody');
                        const emptyRow = tbody.querySelector('td[colspan]');
                        if (emptyRow) emptyRow.closest('tr').remove();

                        const tr = document.createElement('tr');
                        tr.className = 'table-info';
                        const badgeSesi = item.sesi === 'pagi' ? '<span class="badge bg-primary">🌅 Pagi</span>' : '<span class="badge bg-warning text-dark">🌇 Sore</span>';
                        tr.innerHTML = `
                            <td>1</td>
                            <td>${item.tanggal} <small class="text-secondary">(${item.waktu})</small></td>
                            <td>${badgeSesi}</td>
                            <td>${escapeHtml(item.nama)}</td>
                            <td>${escapeHtml(item.jabatan)}</td>
                            <td>${item.tanda_tangan ? `<img src="${item.tanda_tangan}" alt="Tanda Tangan" class="signature-img">` : '-'}</td>
                        `;
                        tbody.insertBefore(tr, tbody.firstChild);
                        renumberTableRows('presensiApelTable');
                        setTimeout(() => tr.classList.remove('table-info'), 3000);
                    }
                });
            }

            // 3. Absensi Harian Baru
            if (payload.new_harian && payload.new_harian.length > 0) {
                payload.new_harian.forEach(item => {
                    hasNew = true;
                    const aksiText = item.waktu_checkout ? 'Check-out Pulang' : 'Check-in Masuk';
                    showSseToast('📅 Absen Harian!', `<b>${escapeHtml(item.nama)}</b> (${escapeHtml(item.jabatan)}) melakukan <i>${aksiText}</i>`, 'bg-success');

                    const table = document.getElementById('presensiHarianTable');
                    if (table) {
                        const tbody = table.querySelector('tbody');
                        const emptyRow = tbody.querySelector('td[colspan]');
                        if (emptyRow) emptyRow.closest('tr').remove();

                        const tr = document.createElement('tr');
                        tr.className = 'table-success';
                        tr.innerHTML = `
                            <td>1</td>
                            <td>${item.tanggal}</td>
                            <td>${escapeHtml(item.nama)}</td>
                            <td>${escapeHtml(item.jabatan)}</td>
                            <td><span class="badge bg-success-subtle text-success border">${item.waktu_checkin || '-'}</span></td>
                            <td><span class="badge bg-warning-subtle text-warning border text-dark">${item.waktu_checkout || '-'}</span></td>
                        `;
                        tbody.insertBefore(tr, tbody.firstChild);
                        renumberTableRows('presensiHarianTable');
                        setTimeout(() => tr.classList.remove('table-success'), 3000);
                    }
                });
            }

            if (hasNew) {
                playChime();
            }
        } catch(err) {
            console.error('Error processing SSE message:', err);
        }
    });

    sse.onerror = function() {
        updateSseBadge(false);
    };
}
</script>
</body>
</html>
