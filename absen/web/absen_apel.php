<?php
// absen/web/absen_apel.php
// Halaman form web presensi apel (Apel Pagi / Sore)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';

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
$tgl = date('d');
$bln = $bulanIndonesia[date('m')];
$thn = date('Y');
$tanggalDisplay = "$hariIni, $tgl $bln $thn";
$tanggalDb = date('Y-m-d');
$jamDb = date('H:i');

// Tentukan default sesi berdasarkan jam & hari
$dayOfWeek = (int)date('N'); // 1 = Senin, 5 = Jumat
$defaultSesi = 'pagi';
if ($dayOfWeek === 5 || (int)date('H') >= 12) {
    $defaultSesi = 'sore';
}

// Ambil pesan flash error/sukses dari session jika ada
$errorMsg = $_SESSION['flash_error'] ?? '';
$oldInput = $_SESSION['flash_old'] ?? [];
unset($_SESSION['flash_error'], $_SESSION['flash_old']);

// Ambil daftar pegawai aktif untuk opsi autocomplete / select cepat
$daftarPegawai = [];
try {
    $stmtUsers = $pdo->query("SELECT id, nama, jabatan FROM users WHERE is_active = 1 ORDER BY nama ASC");
    $daftarPegawai = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $daftarPegawai = [];
}
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
        :root {
            --primary: #059669;
            --primary-hover: #047857;
            --bg-gradient: linear-gradient(135deg, #064e3b 0%, #059669 50%, #047857 100%);
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
        .form-card {
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-header {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
            padding: 1.75rem 2rem;
            text-align: center;
        }
        .signature-wrapper {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            background: #ffffff;
            position: relative;
            touch-action: none;
            overflow: hidden;
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
            border: 1px solid #d1d5db;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.15);
        }
        .btn-submit {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            border-radius: 12px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(5, 150, 105, 0.3);
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="form-card">
    <div class="form-header position-relative">
        <a href="index.php" class="btn btn-sm btn-outline-light position-absolute top-50 start-0 translate-middle-y ms-3" style="border-radius: 10px;">
            &larr; Kembali
        </a>
        <h1 class="h4 fw-bold mb-1">Presensi Apel</h1>
        <p class="mb-0 text-white-50 small">Pengadilan Agama &bull; Tanggal: <?= $tanggalDisplay ?></p>
    </div>

    <div class="p-4 p-md-5">
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
                <div>⚠️</div>
                <div><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="proses_apel.php" method="POST" id="absen-apel-form">
            <?= csrfField() ?>
            <input type="hidden" name="user_id" id="user_id" value="<?= htmlspecialchars($oldInput['user_id'] ?? '') ?>">

            <!-- Pilih Pegawai Terdaftar (Opsional untuk percepat pengisian) -->
            <?php if (!empty($daftarPegawai)): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted small mb-1">Pilih Pegawai Terdaftar (Opsional)</label>
                    <select class="form-select form-select-sm" id="select_pegawai">
                        <option value="">-- Pilih untuk otomatis mengisi nama & jabatan --</option>
                        <?php foreach ($daftarPegawai as $p): ?>
                            <option value="<?= $p['id'] ?>" data-nama="<?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?>" data-jabatan="<?= htmlspecialchars($p['jabatan'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($p['nama'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($p['jabatan'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="nama" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nama" name="nama" required placeholder="Contoh: Ahmad Fauzi, S.H." value="<?= htmlspecialchars($oldInput['nama'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label for="jabatan" class="form-label fw-semibold">Jabatan / Bagian <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="jabatan" name="jabatan" required placeholder="Contoh: Panitera Pengganti" value="<?= htmlspecialchars($oldInput['jabatan'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label for="sesi" class="form-label fw-semibold">Sesi Apel <span class="text-danger">*</span></label>
                <select class="form-select" id="sesi" name="sesi" required>
                    <option value="pagi" <?= (($oldInput['sesi'] ?? $defaultSesi) === 'pagi') ? 'selected' : '' ?>>
                        Apel Pagi (Senin 07.00 - 08.30 WIB)
                    </option>
                    <option value="sore" <?= (($oldInput['sesi'] ?? $defaultSesi) === 'sore') ? 'selected' : '' ?>>
                        Apel Sore (Jumat 16.00 - 17.30 WIB)
                    </option>
                </select>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-semibold mb-0">Tanda Tangan Digital <span class="text-danger">*</span></label>
                    <span class="text-muted small">Torehkan di area bawah</span>
                </div>
                <div class="signature-wrapper">
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-clear" id="btn-clear-sig">
                        ✕ Hapus TTD
                    </button>
                    <canvas id="signature-pad"></canvas>
                </div>
                <input type="hidden" name="tanda_tangan" id="signature64">
                <div class="form-text small text-muted">
                    ✍️ Gunakan sentuhan jari (smartphone/tablet) atau mouse untuk membubuhkan tanda tangan.
                </div>
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
        const selectPegawai = document.getElementById('select_pegawai');
        const inputNama = document.getElementById('nama');
        const inputJabatan = document.getElementById('jabatan');
        const inputUserId = document.getElementById('user_id');

        // Autocomplete dari select pegawai
        if (selectPegawai) {
            selectPegawai.addEventListener('change', function () {
                const opt = selectPegawai.options[selectPegawai.selectedIndex];
                if (opt && opt.value) {
                    inputNama.value = opt.getAttribute('data-nama') || '';
                    inputJabatan.value = opt.getAttribute('data-jabatan') || '';
                    inputUserId.value = opt.value;
                }
            });
        }

        // Setup Signature Pad
        let signaturePad = null;
        if (canvas) {
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                const ctx = canvas.getContext("2d");
                ctx.scale(ratio, ratio);
                if (signaturePad) {
                    signaturePad.clear();
                }
            }

            signaturePad = new SignaturePad(canvas, {
                penColor: 'rgb(15, 23, 42)',
                backgroundColor: 'rgba(255, 255, 255, 0)',
                minWidth: 1.5,
                maxWidth: 3.5
            });

            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                signaturePad.clear();
            });

            form.addEventListener('submit', function (e) {
                if (signaturePad.isEmpty()) {
                    e.preventDefault();
                    alert("Tanda tangan wajib dibubuhkan sebelum mengirim presensi!");
                    return false;
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
