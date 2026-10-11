<?php
// absen/admin/events.php
// Server-Sent Events (SSE) Endpoint for Real-time Attendance Notifications

// Nonaktifkan batas waktu eksekusi skrip (opsional, batas waktu loop 45 detik)
set_time_limit(60);
ignore_user_abort(true);

require_once __DIR__ . '/../config/database.php';

// Memastikan hanya admin yang login yang dapat menyambung ke SSE stream
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized admin session']);
    exit;
}

// Set Header SSE
header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Matikan output buffering PHP agar data terkirim secara instan
while (ob_get_level() > 0) {
    ob_end_flush();
}

// Inisialisasi ID Terakhir dari query GET (jika ada)
$last_presensi_id = (int)($_GET['last_presensi_id'] ?? 0);
$last_apel_id     = (int)($_GET['last_apel_id']     ?? 0);
$last_harian_id   = (int)($_GET['last_harian_id']   ?? 0);

// Jika ID awal belum dikirim (pertama kali connect), ambil ID terbesar saat ini dari DB
if ($last_presensi_id === 0) {
    $stmt = $pdo->query("SELECT IFNULL(MAX(id), 0) AS max_id FROM presensi");
    $last_presensi_id = (int)($stmt->fetch()['max_id'] ?? 0);
}

if ($last_apel_id === 0) {
    $stmt = $pdo->query("SELECT IFNULL(MAX(id), 0) AS max_id FROM presensi_apel");
    $last_apel_id = (int)($stmt->fetch()['max_id'] ?? 0);
}

if ($last_harian_id === 0) {
    $stmt = $pdo->query("SELECT IFNULL(MAX(id), 0) AS max_id FROM absensi_harian");
    $last_harian_id = (int)($stmt->fetch()['max_id'] ?? 0);
}

// Kirimkan status inisialisasi awal ke klien
echo "event: init\n";
echo "data: " . json_encode([
    'status'           => 'connected',
    'last_presensi_id' => $last_presensi_id,
    'last_apel_id'     => $last_apel_id,
    'last_harian_id'   => $last_harian_id
], JSON_UNESCAPED_UNICODE) . "\n\n";
flush();

// Loop SSE selama maks 15 iterasi (~45 detik per koneksi)
// Browser EventSource akan otomatis reconnect saat koneksi berakhir
$max_cycles = 15;

for ($cycle = 0; $cycle < $max_cycles; $cycle++) {
    if (connection_aborted()) {
        break;
    }

    $new_presensi = [];
    $new_apel     = [];
    $new_harian   = [];

    // 1. Cek Presensi Acara/Rapat baru
    $stmt = $pdo->prepare("
        SELECT p.id, p.acara_id, p.nama, p.jabatan, p.tanda_tangan, p.waktu_absen, a.nama_acara
        FROM presensi p
        LEFT JOIN acara a ON p.acara_id = a.id
        WHERE p.id > ?
        ORDER BY p.id ASC
    ");
    $stmt->execute([$last_presensi_id]);
    $new_presensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($new_presensi)) {
        $last_presensi_id = (int)end($new_presensi)['id'];
    }

    // 2. Cek Presensi Apel baru
    $stmt = $pdo->prepare("
        SELECT id, nama, jabatan, tanda_tangan, sesi, tanggal, waktu, created_at
        FROM presensi_apel
        WHERE id > ?
        ORDER BY id ASC
    ");
    $stmt->execute([$last_apel_id]);
    $new_apel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($new_apel)) {
        $last_apel_id = (int)end($new_apel)['id'];
    }

    // 3. Cek Absensi Harian baru
    $stmt = $pdo->prepare("
        SELECT h.id, h.user_id, h.tanggal, h.waktu_checkin, h.waktu_checkout, h.created_at, h.updated_at, u.nama, u.jabatan, u.nip
        FROM absensi_harian h
        JOIN users u ON h.user_id = u.id
        WHERE h.id > ?
        ORDER BY h.id ASC
    ");
    $stmt->execute([$last_harian_id]);
    $new_harian = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($new_harian)) {
        $last_harian_id = (int)end($new_harian)['id'];
    }

    // Jika ada data baru di salah satu tabel, kirimkan event 'new_absen'
    if (!empty($new_presensi) || !empty($new_apel) || !empty($new_harian)) {
        echo "event: new_absen\n";
        echo "data: " . json_encode([
            'status'           => 'success',
            'last_presensi_id' => $last_presensi_id,
            'last_apel_id'     => $last_apel_id,
            'last_harian_id'   => $last_harian_id,
            'new_presensi'     => $new_presensi,
            'new_apel'         => $new_apel,
            'new_harian'       => $new_harian,
            'timestamp'        => date('H:i:s')
        ], JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    } else {
        // Heartbeat comment ping agar koneksi tetap hidup
        echo ": ping - " . date('H:i:s') . "\n\n";
        flush();
    }

    sleep(3);
}
