<?php
// absen/web/index.php
// Landing page antarmuka web absensi: Pilih Absen Apel atau Absen Rapat

require_once __DIR__ . '/../config/database.php';

$hariIndonesia = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulanIndonesia = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$hariIni = $hariIndonesia[date('l')];
$tgl = date('d');
$bln = $bulanIndonesia[date('m')];
$thn = date('Y');
$tanggalDisplay = "$hariIni, $tgl $bln $thn";

// Cek jumlah rapat/acara yang sedang aktif
$totalAcaraBuka = 0;
try {
    $stmtAcara = $pdo->query("SELECT COUNT(*) FROM acara WHERE status = 'BUKA'");
    $totalAcaraBuka = (int)$stmtAcara->fetchColumn();
} catch (Exception $e) {
    $totalAcaraBuka = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Absensi Digital - Portal Presensi</title>
    <link rel="stylesheet" href="../assets/css/inter.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        :root {
            --primary: #0f766e;
            --primary-hover: #115e59;
            --primary-light: #ccfbf1;
            --bg-gradient: linear-gradient(135deg, #042f2e 0%, #0f766e 45%, #134e4a 100%);
            --card-bg: rgba(255, 255, 255, 0.98);
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .portal-card {
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 860px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .portal-header {
            background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
            color: #ffffff;
            padding: 2.25rem 2rem;
            text-align: center;
            position: relative;
        }
        .badge-status {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            padding: 0.35rem 0.9rem;
            border-radius: 50px;
            font-size: 0.825rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .option-card {
            border: 2px solid #e5e7eb;
            border-radius: 20px;
            padding: 2rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            background: #ffffff;
            height: 100%;
            position: relative;
        }
        .option-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(15, 118, 110, 0.15);
            color: inherit;
        }
        .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            margin-bottom: 1.25rem;
            transition: transform 0.25s ease;
        }
        .option-card:hover .icon-circle {
            transform: scale(1.08);
        }
        .icon-apel {
            background: #ecfdf5;
            color: #059669;
        }
        .icon-rapat {
            background: #eff6ff;
            color: #2563eb;
        }
        .live-clock {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .portal-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 1.25rem 2rem;
            font-size: 0.85rem;
            color: #64748b;
        }
        .btn-action {
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.25rem;
            transition: all 0.2s ease;
        }
    </style>
</head>
<body>

<div class="portal-card">
    <div class="portal-header">
        <div class="badge-status mb-2">
            <span>●</span> Pengadilan Agama
        </div>
        <h1 class="h3 fw-bold mb-1">Sistem Absensi Digital</h1>
        <p class="mb-0 text-white-50" style="font-size: 0.95rem;">Silakan pilih kegiatan presensi yang akan Anda ikuti</p>
    </div>

    <div class="p-4 p-md-5">
        <div class="row g-4">
            <!-- Pilihan 1: Absen Apel -->
            <div class="col-md-6">
                <a href="absen_apel.php" class="option-card">
                    <div>
                        <div class="icon-circle icon-apel">
                            🎖️
                        </div>
                        <h2 class="h5 fw-bold text-dark mb-2">Presensi Apel</h2>
                        <p class="text-muted small mb-3">
                            Absensi pelaksanaan <strong>Apel Pagi (Senin)</strong> dan <strong>Apel Sore (Jumat)</strong> dengan verifikasi tanda tangan digital.
                        </p>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Senin: 07.00 - 08.30</span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle">Jumat: 16.00 - 17.30</span>
                        </div>
                    </div>
                    <div>
                        <div class="btn btn-outline-success btn-action w-100">
                            Masuk Presensi Apel &rarr;
                        </div>
                    </div>
                </a>
            </div>

            <!-- Pilihan 2: Absen Rapat -->
            <div class="col-md-6">
                <a href="absen_rapat.php" class="option-card">
                    <div>
                        <div class="icon-circle icon-rapat">
                            📋
                        </div>
                        <h2 class="h5 fw-bold text-dark mb-2">Presensi Rapat / Acara</h2>
                        <p class="text-muted small mb-3">
                            Absensi daftar hadir rapat koordinasi, sosialisasi, dan kegiatan dinas aktif menggunakan PIN acara dan tanda tangan.
                        </p>
                        <div class="mb-2">
                            <?php if ($totalAcaraBuka > 0): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    🟢 <?= $totalAcaraBuka ?> Rapat Aktif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    ⚪ Tidak ada rapat terbuka
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div class="btn btn-outline-primary btn-action w-100">
                            Masuk Presensi Rapat &rarr;
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div class="portal-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div>
            📅 <strong><?= $tanggalDisplay ?></strong> &bull; <span class="live-clock" id="live-clock">--:--:--</span> WIB
        </div>
        <div>
            <a href="../admin/login.php" class="text-decoration-none text-muted small">
                🔒 Login Panel Administrator
            </a>
        </div>
    </div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        const clockEl = document.getElementById('live-clock');
        if (clockEl) {
            clockEl.textContent = `${h}:${m}:${s}`;
        }
    }
    updateClock();
    setInterval(updateClock, 1000);
</script>
</body>
</html>
