<?php
// absen/web/absen_apel.php
// Form presensi apel — data pegawai otomatis dari session login

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/auth_check.php'; // Wajib login

$pegawai = getPegawaiSession();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

$hariIni = $hariIndonesia[date('l')];
$tanggalDisplay = "$hariIni, " . date('d') . " " . $bulanIndonesia[date('m')] . " " . date('Y');
$jamDb = date('H:i');

// Default sesi berdasarkan hari/jam
$dayOfWeek  = (int)date('N');
$defaultSesi = ($dayOfWeek === 5 || (int)date('H') >= 12) ? 'sore' : 'pagi';

// Flash messages dari proses_apel.php
$errorMsg = $_SESSION['flash_error'] ?? '';
$oldSesi  = $_SESSION['flash_old']['sesi'] ?? $defaultSesi;
unset($_SESSION['flash_error'], $_SESSION['flash_old']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Apel - Sistem Absensi Digital</title>
    <link rel="stylesheet" href="../assets/css/inter.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        :root { --primary: #059669; --primary-hover: #047857; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #064e3b 0%, #059669 50%, #047857 100%);
            min-height: 100vh;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .form-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
            max-width: 580px;
            width: 100%;
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
            padding: 1.75rem 2rem;
            text-align: center;
        }
        /* Kartu identitas pegawai (read-only) */
        .pegawai-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1.5px solid #86efac;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .pegawai-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .pegawai-meta { flex: 1; min-width: 0; }
        .pegawai-meta .name {
            font-weight: 700;
            font-size: 0.95rem;
            color: #064e3b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pegawai-meta .detail {
            font-size: 0.78rem;
            color: #047857;
        }
        .signature-wrapper {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            background: #fff;
            position: relative;
            overflow: hidden;
            touch-action: none;
        }
        canvas#signature-pad {
            width: 100%;
            height: 180px;
            display: block;
            cursor: crosshair;
        }
        .btn-clear {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 10;
            font-size: 0.75rem;
            padding: 0.25rem 0.65rem;
            border-radius: 8px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.7rem 1rem;
            border: 1.5px solid #e2e8f0;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(5,150,105,0.12);
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 0.85rem 1.5rem;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.25s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -5px rgba(5,150,105,0.35);
            color: #fff;
        }
    </style>
</head>
<body>

<div class="form-card">
    <div class="form-header position-relative">
        <a href="index.php" class="btn btn-sm btn-outline-light position-absolute top-50 start-0 translate-middle-y ms-3" style="border-radius:10px;">
            &larr; Kembali
        </a>
        <h1 class="h4 fw-bold mb-1">Presensi Apel</h1>
        <p class="mb-0 text-white-50 small">Pengadilan Agama &bull; <?= $tanggalDisplay ?></p>
    </div>

    <div class="p-4 p-md-5">
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 rounded-3 mb-4" role="alert">
                <span>⚠️</span>
                <span><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <!-- Kartu Identitas Pegawai (read-only, dari session) -->
        <div class="pegawai-card">
            <div class="pegawai-avatar">👤</div>
            <div class="pegawai-meta">
                <div class="name"><?= htmlspecialchars($pegawai['nama'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="detail">
                    <?= htmlspecialchars($pegawai['jabatan'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($pegawai['nip'])): ?>
                        &bull; NIP: <?= htmlspecialchars($pegawai['nip'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge bg-success text-white small px-2 py-1">✓ Terverifikasi</span>
        </div>

        <form action="proses_apel.php" method="POST" id="absen-apel-form">
            <?= csrfField() ?>

            <div class="mb-4">
                <label for="sesi" class="form-label fw-semibold">Sesi Apel <span class="text-danger">*</span></label>
                <select class="form-select" id="sesi" name="sesi" required>
                    <option value="pagi" <?= $oldSesi === 'pagi' ? 'selected' : '' ?>>
                        🌅 Apel Pagi &nbsp;&mdash;&nbsp; Senin 07.00 - 08.30 WIB
                    </option>
                    <option value="sore" <?= $oldSesi === 'sore' ? 'selected' : '' ?>>
                        🌇 Apel Sore &nbsp;&mdash;&nbsp; Jumat 16.00 - 17.30 WIB
                    </option>
                </select>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-semibold mb-0">Tanda Tangan Digital <span class="text-danger">*</span></label>
                    <span class="text-muted small">Torehkan di area bawah</span>
                </div>
                <div class="signature-wrapper">
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-clear" id="btn-clear-sig">✕ Hapus TTD</button>
                    <canvas id="signature-pad"></canvas>
                </div>
                <input type="hidden" name="tanda_tangan" id="signature64">
                <div class="form-text text-muted small mt-1">✍️ Gunakan sentuhan jari (smartphone/tablet) atau mouse.</div>
            </div>

            <button type="submit" class="btn btn-submit w-100" id="btn-submit">
                💾 Simpan Kehadiran Apel
            </button>
        </form>
    </div>

    <div class="px-4 py-3 bg-light border-top text-center text-muted small">
        Sistem Absensi Digital &bull; Server Time: <strong><?= $jamDb ?> WIB</strong>
    </div>
</div>

<script src="../assets/js/signature_pad.umd.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const canvas = document.getElementById('signature-pad');
        const clearBtn = document.getElementById('btn-clear-sig');
        const sigInput = document.getElementById('signature64');
        const form = document.getElementById('absen-apel-form');
        let signaturePad = null;

        if (canvas) {
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                if (signaturePad) signaturePad.clear();
            }
            signaturePad = new SignaturePad(canvas, {
                penColor: 'rgb(15, 23, 42)',
                minWidth: 1.5,
                maxWidth: 3.5,
                backgroundColor: 'rgba(255,255,255,0)'
            });
            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            clearBtn.addEventListener('click', e => { e.preventDefault(); signaturePad.clear(); });

            form.addEventListener('submit', function (e) {
                if (signaturePad.isEmpty()) {
                    e.preventDefault();
                    alert("Tanda tangan wajib dibubuhkan!");
                    return;
                }
                sigInput.value = signaturePad.toDataURL('image/png');
                document.getElementById('btn-submit').disabled = true;
                document.getElementById('btn-submit').innerHTML = '⏳ Menyimpan...';
            });
        }
    });
</script>
</body>
</html>
