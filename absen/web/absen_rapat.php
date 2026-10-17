<?php
// absen/web/absen_rapat.php
// Form presensi rapat — data pegawai otomatis dari session login

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/auth_check.php';

$pegawai = getPegawaiSession();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$hariIndonesia = array(
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
);
$bulanIndonesia = array(
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
);

$hariIni = $hariIndonesia[date('l')];
$tanggalDisplay = "$hariIni, " . date('d') . " " . $bulanIndonesia[date('m')] . " " . date('Y');
$jamDb = date('H:i');

// Flash messages
$errorMsg = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
$oldInput = isset($_SESSION['flash_old']) ? $_SESSION['flash_old'] : array();
unset($_SESSION['flash_error'], $_SESSION['flash_old']);

// Daftar acara/rapat yang sedang BUKA
$daftarAcara = array();
try {
    $stmtAcara = $pdo->query("SELECT id, nama_acara FROM acara WHERE status = 'BUKA' ORDER BY id DESC");
    $daftarAcara = $stmtAcara->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $daftarAcara = array();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Rapat - Sistem Absensi Digital</title>
    <link rel="stylesheet" href="../assets/css/inter.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        :root { --primary: #2563eb; --primary-hover: #1d4ed8; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #1d4ed8 100%);
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
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 1.75rem 2rem;
            text-align: center;
        }
        /* Kartu identitas pegawai */
        .pegawai-card {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1.5px solid #93c5fd;
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
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
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
            color: #1e3a8a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pegawai-meta .detail { font-size: 0.78rem; color: #1d4ed8; }
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
            top: 8px; right: 8px;
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
            box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
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
            box-shadow: 0 12px 20px -5px rgba(37,99,235,0.35);
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
        <h1 class="h4 fw-bold mb-1">Presensi Rapat / Acara</h1>
        <p class="mb-0 text-white-50 small">Pengadilan Agama &bull; <?= $tanggalDisplay ?></p>
    </div>

    <div class="p-4 p-md-5">
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 rounded-3 mb-4" role="alert">
                <span>⚠️</span>
                <span><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($daftarAcara)): ?>
            <div class="text-center py-4">
                <div class="display-5 text-muted mb-3">📁</div>
                <h5 class="fw-bold">Tidak Ada Rapat Terbuka</h5>
                <p class="text-muted small">Belum ada rapat atau acara yang berstatus <strong>BUKA</strong>. Hubungi panitia untuk membuka sesi rapat.</p>
                <a href="index.php" class="btn btn-outline-secondary mt-2">Kembali ke Menu Utama</a>
            </div>
        <?php else: ?>

            <!-- Kartu Identitas Pegawai (read-only) -->
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
                <span class="badge bg-primary text-white small px-2 py-1">✓ Terverifikasi</span>
            </div>

            <form action="proses_rapat.php" method="POST" id="absen-rapat-form">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="acara_id" class="form-label fw-semibold">Pilih Kegiatan / Rapat <span class="text-danger">*</span></label>
                    <select class="form-select" id="acara_id" name="acara_id" required>
                        <option value="">-- Pilih Rapat yang Sedang Aktif --</option>
                        <?php foreach ($daftarAcara as $ac): ?>
                            <option value="<?= $ac['id'] ?>" <?= ((isset($oldInput['acara_id']) ? $oldInput['acara_id'] : '') == $ac['id']) ? 'selected' : '' ?>>
                                📌 <?= htmlspecialchars($ac['nama_acara'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="pin" class="form-label fw-semibold">PIN Rapat / Acara <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="pin" name="pin" required
                        placeholder="Masukkan PIN yang diberikan panitia" autocomplete="off" maxlength="10">
                    <div class="form-text small text-muted">
                        🔒 Tanyakan PIN kepada panitia di lokasi atau dari layar proyektor.
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-semibold mb-0">Tanda Tangan Kehadiran <span class="text-danger">*</span></label>
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
                    💾 Simpan Kehadiran Rapat
                </button>
            </form>
        <?php endif; ?>
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
        const form = document.getElementById('absen-rapat-form');
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
                minWidth: 1.5, maxWidth: 3.5,
                backgroundColor: 'rgba(255,255,255,0)'
            });
            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            clearBtn.addEventListener('click', e => { e.preventDefault(); signaturePad.clear(); });

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (signaturePad.isEmpty()) {
                        e.preventDefault();
                        alert("Tanda tangan wajib dibubuhkan sebelum mengirim!");
                        return;
                    }
                    sigInput.value = signaturePad.toDataURL('image/png');
                    document.getElementById('btn-submit').disabled = true;
                    document.getElementById('btn-submit').innerHTML = '⏳ Menyimpan...';
                });
            }
        }
    });
</script>
</body>
</html>
