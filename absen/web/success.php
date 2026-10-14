<?php
// absen/web/success.php
// Halaman konfirmasi sukses setelah submit absensi web

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah ada data sukses di session
$successData = $_SESSION['absen_success'] ?? null;
unset($_SESSION['absen_success']);

// Jika tidak ada data sukses, redirect kembali ke portal utama
if (!$successData) {
    header("Location: index.php");
    exit;
}

$hariIndonesia = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulanIndonesia = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$tglObj = strtotime($successData['tanggal']);
$hari = $hariIndonesia[date('l', $tglObj)] ?? '';
$tgl = date('d', $tglObj);
$bln = $bulanIndonesia[date('m', $tglObj)] ?? '';
$thn = date('Y', $tglObj);
$tanggalDisplay = "$hari, $tgl $bln $thn";

$tipeTitle = ($successData['tipe'] === 'apel') ? 'Presensi Apel' : 'Presensi Rapat';
$themeColor = ($successData['tipe'] === 'apel') ? '#059669' : '#2563eb';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Berhasil - Sistem Absensi Digital</title>
    <link rel="stylesheet" href="../assets/css/inter.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        :root {
            --theme: <?= $themeColor ?>;
            --bg-gradient: linear-gradient(135deg, #064e3b 0%, #059669 50%, #047857 100%);
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: <?= ($successData['tipe'] === 'apel') ? 'linear-gradient(135deg, #064e3b 0%, #059669 100%)' : 'linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%)' ?>;
            min-height: 100vh;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .success-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 540px;
            width: 100%;
            overflow: hidden;
            text-align: center;
        }
        .success-header {
            background: <?= $themeColor ?>;
            color: #ffffff;
            padding: 2.25rem 2rem 1.75rem;
        }
        .check-icon {
            width: 72px;
            height: 72px;
            background: rgba(255, 255, 255, 0.2);
            border: 3px solid rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn {
            0% { transform: scale(0.3); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .info-table {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.25rem;
            text-align: left;
            margin: 1.5rem 0;
            border: 1px solid #e2e8f0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 0.9rem;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .signature-preview {
            max-height: 70px;
            max-width: 160px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            padding: 4px;
        }
        .countdown-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.825rem;
            display: inline-block;
            margin-bottom: 1.25rem;
        }
    </style>
</head>
<body>

<div class="success-card">
    <div class="success-header">
        <div class="check-icon">✓</div>
        <h1 class="h4 fw-bold mb-1">Presensi Berhasil Dicatat!</h1>
        <p class="mb-0 text-white-50 small">Terima kasih atas partisipasi dan kehadiran Anda</p>
    </div>

    <div class="p-4 p-md-5">
        <div class="countdown-badge">
            ⏱️ Kembali ke menu utama dalam <strong id="countdown">7</strong> detik
        </div>

        <div class="info-table">
            <div class="info-row">
                <span class="text-muted">Jenis Kegiatan:</span>
                <strong class="text-dark"><?= htmlspecialchars($tipeTitle, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="info-row">
                <span class="text-muted">Keterangan:</span>
                <strong class="text-primary"><?= htmlspecialchars($successData['detail'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="info-row">
                <span class="text-muted">Nama:</span>
                <strong class="text-dark"><?= htmlspecialchars($successData['nama'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="info-row">
                <span class="text-muted">Jabatan / Instansi:</span>
                <span class="text-dark"><?= htmlspecialchars($successData['jabatan'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="info-row">
                <span class="text-muted">Waktu Kehadiran:</span>
                <span class="text-dark"><strong><?= htmlspecialchars($successData['waktu'], ENT_QUOTES, 'UTF-8') ?> WIB</strong> &bull; <?= $tanggalDisplay ?></span>
            </div>
            <?php if (!empty($successData['tanda_tangan'])): ?>
                <div class="info-row align-items-center">
                    <span class="text-muted">Tanda Tangan:</span>
                    <img src="<?= htmlspecialchars($successData['tanda_tangan'], ENT_QUOTES, 'UTF-8') ?>" alt="Tanda Tangan" class="signature-preview">
                </div>
            <?php endif; ?>
        </div>

        <div class="d-grid gap-2">
            <a href="index.php" class="btn btn-dark py-2 rounded-3 fw-semibold">
                &larr; Kembali ke Menu Utama
            </a>
            <?php if ($successData['tipe'] === 'apel'): ?>
                <a href="absen_apel.php" class="btn btn-outline-success py-2 rounded-3 small">
                    Absen Peserta Lain (Apel)
                </a>
            <?php else: ?>
                <a href="absen_rapat.php" class="btn btn-outline-primary py-2 rounded-3 small">
                    Absen Peserta Lain (Rapat)
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    let secondsLeft = 7;
    const countdownEl = document.getElementById('countdown');
    const timer = setInterval(() => {
        secondsLeft--;
        if (countdownEl) {
            countdownEl.textContent = secondsLeft;
        }
        if (secondsLeft <= 0) {
            clearInterval(timer);
            window.location.href = 'index.php';
        }
    }, 1000);
</script>
</body>
</html>
