<?php
// absen/admin/export_excel.php
// Standalone Excel Exporter (HTML/MIME Spreadsheet)
// 100% Kompatibel dengan PHP 5.4 s/d PHP 8+, tanpa membutuhkan vendor/composer!
// Menghasilkan file Excel (.xls) lengkap dengan styling, Kop Surat (Base64 image), tanda tangan, dan tabel.

require_once 'auth_check.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'rapat';

// Helper load base64 kop surat
function getKopSuratBase64() {
    $kopCandidates = array(
        __DIR__ . '/../assets/kop.png',
        __DIR__ . '/../../kop.png',
        dirname(__DIR__) . '/../kop.png',
    );
    foreach ($kopCandidates as $cand) {
        if (file_exists($cand)) {
            $data = file_get_contents($cand);
            return 'data:image/png;base64,' . base64_encode($data);
        }
    }
    return null;
}

// Format Indonesia
$hariIndo = array('1'=>'SENIN', '2'=>'SELASA', '3'=>'RABU', '4'=>'KAMIS', '5'=>'JUM\'AT', '6'=>'SABTU', '7'=>'MINGGU');
$bulanIndo = array(
    '01'=>'JANUARI', '02'=>'FEBRUARI', '03'=>'MARET', '04'=>'APRIL',
    '05'=>'MEI', '06'=>'JUNI', '07'=>'JULI', '08'=>'AGUSTUS',
    '09'=>'SEPTEMBER', '10'=>'OKTOBER', '11'=>'NOVEMBER', '12'=>'DESEMBER'
);

$kopSrc = getKopSuratBase64();

if ($type === 'rapat') {
    // =========================================================================
    // EXPORT REKAP RAPAT / ACARA
    // =========================================================================
    $acara_id = isset($_GET['acara_id']) ? (int)$_GET['acara_id'] : 0;
    $active_acara = null;

    if ($acara_id > 0) {
        $stmtAcara = $pdo->prepare("SELECT * FROM acara WHERE id = ?");
        $stmtAcara->execute(array($acara_id));
        $active_acara = $stmtAcara->fetch();
    } else {
        $stmtAcara = $pdo->query("SELECT * FROM acara ORDER BY id DESC LIMIT 1");
        $active_acara = $stmtAcara->fetch();
        if ($active_acara) {
            $acara_id = $active_acara['id'];
        }
    }

    $data_presensi = array();
    if ($acara_id > 0) {
        $presensiStmt = $pdo->prepare("SELECT * FROM presensi WHERE acara_id = ? ORDER BY waktu_absen DESC");
        $presensiStmt->execute(array($acara_id));
        $data_presensi = $presensiStmt->fetchAll();
    }

    $namaAcara   = isset($active_acara['nama_acara']) ? $active_acara['nama_acara'] : '-';
    $pinAcara    = isset($active_acara['pin_acara']) ? $active_acara['pin_acara'] : '-';
    $statusAcara = isset($active_acara['status']) ? $active_acara['status'] : '-';

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $namaAcara);
    $filename = "Rekap_Presensi_Rapat_{$safeName}_" . date('Ymd_His') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        table { border-collapse: collapse; width: 100%; }
        .kop-table td { border: none; padding: 2px; }
        .data-table th, .data-table td { border: 1px solid #333333; padding: 6px 8px; }
        .data-table th { background-color: #1F2937; color: #FFFFFF; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .title { font-size: 15pt; font-weight: bold; color: #111827; margin: 10px 0 5px 0; }
        .meta { font-size: 10pt; color: #4B5563; font-weight: bold; }
        .ttd { font-weight: bold; font-size: 10pt; }
    </style></head><body>';

    if ($kopSrc) {
        echo '<table class="kop-table" style="margin-bottom: 15px;"><tr><td colspan="5" align="center"><img src="' . $kopSrc . '" height="115" alt="Kop Surat"></td></tr></table>';
    }

    echo '<div class="title">REKAP PRESENSI RAPAT / ACARA</div>';
    echo '<table class="kop-table" style="margin-bottom: 12px;">';
    echo '<tr><td class="meta" colspan="5">Nama Acara : ' . htmlspecialchars($namaAcara, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    echo '<tr><td class="meta" colspan="5">PIN Acara  : ' . htmlspecialchars($pinAcara, ENT_QUOTES, 'UTF-8') . '  |  Status : ' . htmlspecialchars($statusAcara, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    echo '<tr><td class="meta" colspan="5">Tanggal Cetak : ' . date('d/m/Y H:i:s') . '</td></tr>';
    echo '</table>';

    echo '<table class="data-table">';
    echo '<thead><tr>';
    echo '<th style="width: 50px;">No</th>';
    echo '<th style="width: 160px;">Waktu Absen</th>';
    echo '<th style="width: 250px;">Nama Pegawai</th>';
    echo '<th style="width: 200px;">Jabatan</th>';
    echo '<th style="width: 140px;">Tanda Tangan</th>';
    echo '</tr></thead><tbody>';

    if (!empty($data_presensi)) {
        $no = 1;
        foreach ($data_presensi as $row) {
            echo '<tr>';
            echo '<td class="text-center">' . $no++ . '</td>';
            echo '<td class="text-center">' . date('d/m/Y H:i:s', strtotime($row['waktu_absen'])) . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($row['jabatan'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="text-center">';
            if (!empty($row['tanda_tangan'])) {
                $sig = $row['tanda_tangan'];
                if (strpos($sig, 'data:image') === 0) {
                    echo '<img src="' . $sig . '" height="38" alt="TTD">';
                } else {
                    echo '<span style="color:#059669; font-weight:bold;">Terverifikasi</span>';
                }
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5" class="text-center" style="padding: 15px;">Belum ada data presensi untuk acara ini.</td></tr>';
    }
    echo '</tbody></table>';

    // TTD Ketua
    echo '<br><br>';
    echo '<table class="kop-table">';
    echo '<tr><td colspan="3"></td><td colspan="2" class="text-center ttd">Mengetahui,<br>KETUA PENGADILAN AGAMA PALEMBANG KELAS IA<br><br><br><br><u>MURSYID SYAH, S.Ag.</u><br>NIP. 197111251998031005</td></tr>';
    echo '</table>';

    echo '<div style="font-size: 9pt; font-style: italic; color: #6B7280; margin-top: 20px;">* Catatan: Data presensi ini sah dan dicatat secara digital melalui sistem absensi digital Pengadilan Agama.</div>';
    echo '</body></html>';
    exit;

} elseif ($type === 'apel') {
    // =========================================================================
    // EXPORT REKAP PRESENSI APEL (PAGI / SORE)
    // =========================================================================
    $sesi_filter  = isset($_GET['sesi']) ? $_GET['sesi'] : 'semua';
    $tgl_mulai    = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : '';
    $tgl_selesai  = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : '';
    $apel_user_id = isset($_GET['apel_user_id']) ? (int)$_GET['apel_user_id'] : 0;

    $sqlApel = "SELECT * FROM presensi_apel WHERE 1=1";
    $paramsApel = array();

    if (in_array($sesi_filter, array('pagi', 'sore'), true)) {
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
        $stmtU = $pdo->prepare("SELECT nama FROM users WHERE id = ?");
        $stmtU->execute(array($apel_user_id));
        $uNama = $stmtU->fetchColumn();
        $uNama = $uNama ? $uNama : '';
        $sqlApel .= " AND (user_id = ? OR nama = ?)";
        $paramsApel[] = $apel_user_id;
        $paramsApel[] = $uNama;
    }

    $sqlApel .= " ORDER BY tanggal DESC, waktu DESC, id DESC";
    $stmtApel = $pdo->prepare($sqlApel);
    $stmtApel->execute($paramsApel);
    $data_presensi_apel = $stmtApel->fetchAll();

    if ($sesi_filter === 'pagi') {
        $judulApel = 'DAFTAR HADIR APEL PAGI';
    } elseif ($sesi_filter === 'sore') {
        $judulApel = 'DAFTAR HADIR APEL SORE';
    } else {
        $judulApel = 'DAFTAR HADIR APEL PAGI / SORE';
    }

    if (!empty($tgl_mulai) && !empty($tgl_selesai) && $tgl_mulai === $tgl_selesai) {
        $ts = strtotime($tgl_mulai);
        $wHari = isset($hariIndo[date('N', $ts)]) ? $hariIndo[date('N', $ts)] : '';
        $d = date('j', $ts);
        $m = isset($bulanIndo[date('m', $ts)]) ? $bulanIndo[date('m', $ts)] : '';
        $y = date('Y', $ts);
        $hariTanggalText = ": &nbsp; {$wHari}, &nbsp;{$d} {$m} {$y}";
    } elseif (!empty($tgl_mulai) || !empty($tgl_selesai)) {
        $hariTanggalText = ": &nbsp; " . ($tgl_mulai ? $tgl_mulai : 'Awal') . " s/d " . ($tgl_selesai ? $tgl_selesai : 'Sekarang');
    } elseif (!empty($data_presensi_apel)) {
        $ts = strtotime($data_presensi_apel[0]['tanggal']);
        $wHari = isset($hariIndo[date('N', $ts)]) ? $hariIndo[date('N', $ts)] : '';
        $d = date('j', $ts);
        $m = isset($bulanIndo[date('m', $ts)]) ? $bulanIndo[date('m', $ts)] : '';
        $y = date('Y', $ts);
        $hariTanggalText = ": &nbsp; {$wHari}, &nbsp;{$d} {$m} {$y}";
    } else {
        $ts = time();
        $wHari = isset($hariIndo[date('N', $ts)]) ? $hariIndo[date('N', $ts)] : '';
        $d = date('j', $ts);
        $m = isset($bulanIndo[date('m', $ts)]) ? $bulanIndo[date('m', $ts)] : '';
        $y = date('Y', $ts);
        $hariTanggalText = ": &nbsp; {$wHari}, &nbsp;{$d} {$m} {$y}";
    }

    $filename = "DAFTAR_HADIR_APEL_" . strtoupper($sesi_filter) . "_" . date('Ymd_His') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        table { border-collapse: collapse; width: 100%; }
        .kop-table td { border: none; padding: 2px; }
        .data-table th, .data-table td { border: 1px solid #000000; padding: 5px 6px; }
        .data-table th { background-color: #F3F4F6; color: #000000; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .title { font-size: 14pt; font-weight: bold; text-align: center; margin: 15px 0 10px 0; }
        .meta-label { font-weight: bold; font-size: 10pt; }
        .ttd { font-weight: bold; font-size: 10pt; }
    </style></head><body>';

    if ($kopSrc) {
        echo '<table class="kop-table" style="margin-bottom: 10px;"><tr><td colspan="5" align="center"><img src="' . $kopSrc . '" height="115" alt="Kop Surat"></td></tr></table>';
    }

    echo '<div class="title">' . $judulApel . '</div>';
    echo '<table class="kop-table" style="margin-bottom: 10px;">';
    echo '<tr><td style="width: 140px;" class="meta-label">HARI, TANGGAL</td><td colspan="4" class="meta-label">' . $hariTanggalText . '</td></tr>';
    echo '</table>';

    echo '<table class="data-table">';
    echo '<thead><tr>';
    echo '<th style="width: 45px;">NO</th>';
    echo '<th style="width: 250px;">NAMA</th>';
    echo '<th style="width: 200px;">JABATAN</th>';
    echo '<th colspan="2" style="width: 180px;">TANDA TANGAN</th>';
    echo '</tr></thead><tbody>';

    if (!empty($data_presensi_apel)) {
        $no = 1;
        foreach ($data_presensi_apel as $row) {
            $isOdd = ($no % 2 !== 0);
            echo '<tr>';
            echo '<td class="text-center">' . $no . '.</td>';
            echo '<td class="text-left">' . htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($row['jabatan'], ENT_QUOTES, 'UTF-8') . '</td>';

            if (!empty($row['tanda_tangan']) && strpos($row['tanda_tangan'], 'data:image') === 0) {
                if ($isOdd) {
                    echo '<td class="text-center" style="width: 90px;"><img src="' . $row['tanda_tangan'] . '" height="36" alt="TTD"></td>';
                    echo '<td class="text-center" style="width: 90px;"></td>';
                } else {
                    echo '<td class="text-center" style="width: 90px;"></td>';
                    echo '<td class="text-center" style="width: 90px;"><img src="' . $row['tanda_tangan'] . '" height="36" alt="TTD"></td>';
                }
            } else {
                if ($isOdd) {
                    echo '<td class="text-left" style="width: 90px;">' . $no . '. ..........</td>';
                    echo '<td style="width: 90px;"></td>';
                } else {
                    echo '<td style="width: 90px;"></td>';
                    echo '<td class="text-left" style="width: 90px;">' . $no . '. ..........</td>';
                }
            }
            echo '</tr>';
            $no++;
        }
    } else {
        echo '<tr><td colspan="5" class="text-center" style="padding: 15px;">Belum ada data presensi apel sesuai filter.</td></tr>';
    }
    echo '</tbody></table>';

    // TTD Ketua
    echo '<br><br>';
    echo '<table class="kop-table">';
    echo '<tr><td colspan="3"></td><td colspan="2" class="text-center ttd">Mengetahui,<br>KETUA PENGADILAN AGAMA PALEMBANG KELAS IA<br><br><br><br><u>MURSYID SYAH, S.Ag.</u><br>NIP. 197111251998031005</td></tr>';
    echo '</table>';

    echo '<div style="font-size: 9pt; font-style: italic; color: #6B7280; margin-top: 20px;">* Catatan: Data presensi apel ini sah dan dicatat secara digital melalui sistem absensi digital Pengadilan Agama.</div>';
    echo '</body></html>';
    exit;

} elseif ($type === 'harian') {
    // =========================================================================
    // EXPORT REKAP ABSENSI HARIAN
    // =========================================================================
    $harian_tgl_mulai   = isset($_GET['harian_tgl_mulai']) ? $_GET['harian_tgl_mulai'] : '';
    $harian_tgl_selesai = isset($_GET['harian_tgl_selesai']) ? $_GET['harian_tgl_selesai'] : '';
    $harian_user_id     = isset($_GET['harian_user_id']) ? (int)$_GET['harian_user_id'] : 0;

    $sqlHarian = "SELECT h.*, u.nama, u.jabatan, u.nip FROM absensi_harian h JOIN users u ON h.user_id = u.id WHERE 1=1";
    $paramsHarian = array();

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

    $periodeLabel = ($harian_tgl_mulai ? $harian_tgl_mulai : 'Semua') . ' s/d ' . ($harian_tgl_selesai ? $harian_tgl_selesai : 'Semua');
    $filename = "Rekap_Absensi_Harian_" . date('Ymd_His') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        table { border-collapse: collapse; width: 100%; }
        .kop-table td { border: none; padding: 2px; }
        .data-table th, .data-table td { border: 1px solid #333333; padding: 6px 8px; }
        .data-table th { background-color: #1F2937; color: #FFFFFF; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .title { font-size: 15pt; font-weight: bold; margin: 10px 0 5px 0; }
        .meta { font-size: 10pt; color: #4B5563; font-weight: bold; }
        .ttd { font-weight: bold; font-size: 10pt; }
    </style></head><body>';

    if ($kopSrc) {
        echo '<table class="kop-table" style="margin-bottom: 12px;"><tr><td colspan="8" align="center"><img src="' . $kopSrc . '" height="115" alt="Kop Surat"></td></tr></table>';
    }

    echo '<div class="title">REKAP ABSENSI HARIAN</div>';
    echo '<table class="kop-table" style="margin-bottom: 12px;">';
    echo '<tr><td class="meta" colspan="8">Periode : ' . htmlspecialchars($periodeLabel, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    echo '<tr><td class="meta" colspan="8">Total Riwayat : ' . count($data_presensi_harian) . ' catatan</td></tr>';
    echo '<tr><td class="meta" colspan="8">Tanggal Cetak : ' . date('d/m/Y H:i:s') . '</td></tr>';
    echo '</table>';

    echo '<table class="data-table">';
    echo '<thead><tr>';
    echo '<th style="width: 45px;">No</th>';
    echo '<th style="width: 100px;">Tanggal</th>';
    echo '<th style="width: 130px;">NIP</th>';
    echo '<th style="width: 220px;">Nama Pegawai</th>';
    echo '<th style="width: 180px;">Jabatan</th>';
    echo '<th style="width: 120px;">Jam Masuk (Check-in)</th>';
    echo '<th style="width: 120px;">Jam Pulang (Check-out)</th>';
    echo '<th style="width: 90px;">Foto</th>';
    echo '</tr></thead><tbody>';

    if (!empty($data_presensi_harian)) {
        $no = 1;
        foreach ($data_presensi_harian as $row) {
            echo '<tr>';
            echo '<td class="text-center">' . $no++ . '</td>';
            echo '<td class="text-center">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($row['nip'] ? $row['nip'] : '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($row['jabatan'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($row['waktu_checkin'] ? $row['waktu_checkin'] : '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="text-center">' . htmlspecialchars($row['waktu_checkout'] ? $row['waktu_checkout'] : '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="text-center">';
            $fotoPath = !empty($row['foto_path']) ? __DIR__ . '/../' . ltrim($row['foto_path'], '/\\') : null;
            if ($fotoPath && file_exists($fotoPath)) {
                $fotoData = base64_encode(file_get_contents($fotoPath));
                echo '<img src="data:image/jpeg;base64,' . $fotoData . '" height="45" alt="Foto">';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="8" class="text-center" style="padding: 15px;">Belum ada data absensi harian sesuai filter.</td></tr>';
    }
    echo '</tbody></table>';

    // TTD Ketua
    echo '<br><br>';
    echo '<table class="kop-table">';
    echo '<tr><td colspan="5"></td><td colspan="3" class="text-center ttd">Mengetahui,<br>KETUA PENGADILAN AGAMA PALEMBANG KELAS IA<br><br><br><br><u>MURSYID SYAH, S.Ag.</u><br>NIP. 197111251998031005</td></tr>';
    echo '</table>';

    echo '<div style="font-size: 9pt; font-style: italic; color: #6B7280; margin-top: 20px;">* Catatan: Data absensi harian ini sah dan berasal dari konfirmasi presensi pegawai via aplikasi Android / Web.</div>';
    echo '</body></html>';
    exit;

} else {
    http_response_code(400);
    die('Tipe export tidak valid.');
}
