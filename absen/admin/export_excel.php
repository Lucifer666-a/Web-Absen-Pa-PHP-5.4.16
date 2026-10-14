<?php
// absen/admin/export_excel.php
require_once 'auth_check.php';

// Load Composer autoload
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    die('Error: Library vendor PhpSpreadsheet belum terinstall.');
}
require_once $autoloadPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$type = $_GET['type'] ?? 'rapat';
$tempFiles = [];

// Cleanup temporary image files on shutdown
register_shutdown_function(function() use (&$tempFiles) {
    foreach ($tempFiles as $f) {
        if (file_exists($f)) {
            @unlink($f);
        }
    }
});

/**
 * Decode Base64 data URI to temp PNG file
 */
function createTempImageFromBase64(?string $base64Data, array &$tempFiles): ?string {
    if (empty($base64Data)) {
        return null;
    }
    
    $pos = strpos($base64Data, ',');
    if ($pos !== false) {
        $rawBase64 = substr($base64Data, $pos + 1);
    } else {
        $rawBase64 = $base64Data;
    }
    
    $decoded = base64_decode($rawBase64, true);
    if ($decoded === false || strlen($decoded) < 20) {
        return null;
    }
    
    $tmpFile = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
    if (file_put_contents($tmpFile, $decoded) !== false) {
        $tempFiles[] = $tmpFile;
        return $tmpFile;
    }
    return null;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setShowGridLines(true);

// Default font styling
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1F2937'], // Dark slate
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'D1D5DB'],
        ],
    ],
];

$titleStyle = [
    'font' => [
        'bold' => true,
        'size' => 15,
        'color' => ['rgb' => '111827'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ],
];

$metaLabelStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '4B5563'], 'size' => 10],
];

$dataBorder = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'E5E7EB'],
        ],
    ],
];

if ($type === 'rapat') {
    // =========================================================================
    // EXPORT REKAP PRESENSI RAPAT / ACARA
    // =========================================================================
    $acara_id = (int)($_GET['acara_id'] ?? 0);
    $active_acara = null;

    if ($acara_id > 0) {
        $stmtAcara = $pdo->prepare("SELECT * FROM acara WHERE id = ?");
        $stmtAcara->execute([$acara_id]);
        $active_acara = $stmtAcara->fetch();
    } else {
        $stmtAcara = $pdo->query("SELECT * FROM acara ORDER BY id DESC LIMIT 1");
        $active_acara = $stmtAcara->fetch();
        if ($active_acara) {
            $acara_id = $active_acara['id'];
        }
    }

    $data_presensi = [];
    if ($acara_id > 0) {
        $presensiStmt = $pdo->prepare("SELECT * FROM presensi WHERE acara_id = ? ORDER BY waktu_absen DESC");
        $presensiStmt->execute([$acara_id]);
        $data_presensi = $presensiStmt->fetchAll();
    }

    $namaAcara = $active_acara['nama_acara'] ?? '-';
    $pinAcara  = $active_acara['pin_acara'] ?? '-';
    $statusAcara = $active_acara['status'] ?? '-';

    // Judul & Metadata
    $sheet->setCellValue('A1', 'REKAP PRESENSI RAPAT / ACARA');
    $sheet->getStyle('A1')->applyFromArray($titleStyle);
    $sheet->getRowDimension(1)->setRowHeight(25);

    $sheet->setCellValue('A2', 'Nama Acara: ' . $namaAcara);
    $sheet->setCellValue('A3', 'PIN Acara: ' . $pinAcara . '  |  Status: ' . $statusAcara);
    $sheet->setCellValue('A4', 'Tanggal Cetak: ' . date('d/m/Y H:i:s'));
    $sheet->getStyle('A2:A4')->applyFromArray($metaLabelStyle);

    // Header Kolom Tabel
    $headers = ['No', 'Waktu Absen', 'Nama Pegawai', 'Jabatan', 'Tanda Tangan'];
    $cols = ['A', 'B', 'C', 'D', 'E'];
    $startRow = 6;

    foreach ($headers as $idx => $h) {
        $cell = $cols[$idx] . $startRow;
        $sheet->setCellValue($cell, $h);
    }
    $sheet->getStyle("A{$startRow}:E{$startRow}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($startRow)->setRowHeight(28);

    $currentRow = $startRow + 1;
    $no = 1;

    if (!empty($data_presensi)) {
        foreach ($data_presensi as $row) {
            $sheet->setCellValue("A{$currentRow}", $no);
            $sheet->setCellValue("B{$currentRow}", date('d/m/Y H:i:s', strtotime($row['waktu_absen'])));
            $sheet->setCellValue("C{$currentRow}", $row['nama']);
            $sheet->setCellValue("D{$currentRow}", $row['jabatan']);

            // Alignments
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray($dataBorder);

            // Tanda Tangan Image Embed
            $sigPath = createTempImageFromBase64($row['tanda_tangan'] ?? null, $tempFiles);
            if ($sigPath && file_exists($sigPath)) {
                $sheet->getRowDimension($currentRow)->setRowHeight(45);
                $drawing = new Drawing();
                $drawing->setName('TTD_' . $no);
                $drawing->setDescription('Tanda Tangan ' . $row['nama']);
                $drawing->setPath($sigPath);
                $drawing->setCoordinates("E{$currentRow}");
                $drawing->setHeight(50);
                $drawing->setOffsetX(15);
                $drawing->setOffsetY(4);
                $drawing->setWorksheet($sheet);
            } else {
                $sheet->setCellValue("E{$currentRow}", '-');
                $sheet->getRowDimension($currentRow)->setRowHeight(24);
            }

            $currentRow++;
            $no++;
        }
    } else {
        $sheet->setCellValue("A{$currentRow}", 'Belum ada data kehadiran pada acara ini');
        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray($dataBorder);
        $sheet->getRowDimension($currentRow)->setRowHeight(30);
        $currentRow++;
    }

    // Catatan Kaki
    $currentRow++;
    $sheet->setCellValue("A{$currentRow}", '* Catatan: Data presensi ini sah dan berasal dari konfirmasi presensi pegawai via aplikasi Android.');
    $sheet->getStyle("A{$currentRow}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('6B7280');

    // Lebar Kolom
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(22);
    $sheet->getColumnDimension('C')->setWidth(30);
    $sheet->getColumnDimension('D')->setWidth(26);
    $sheet->getColumnDimension('E')->setWidth(22);

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $namaAcara);
    $filename = "Rekap_Presensi_Rapat_{$safeName}_" . date('Ymd_His') . ".xlsx";

} elseif ($type === 'apel') {
    // =========================================================================
    // EXPORT REKAP PRESENSI APEL (PAGI / SORE)
    // =========================================================================
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
        $stmtU = $pdo->prepare("SELECT nama FROM users WHERE id = ?");
        $stmtU->execute([$apel_user_id]);
        $uNama = $stmtU->fetchColumn() ?: '';
        $sqlApel .= " AND (user_id = ? OR nama = ?)";
        $paramsApel[] = $apel_user_id;
        $paramsApel[] = $uNama;
    }

    $sqlApel .= " ORDER BY tanggal DESC, waktu DESC, id DESC";
    $stmtApel = $pdo->prepare($sqlApel);
    $stmtApel->execute($paramsApel);
    $data_presensi_apel = $stmtApel->fetchAll();

    // Tentukan Judul Sesi Apel
    if ($sesi_filter === 'pagi') {
        $judulApel = 'DAFTAR HADIR APEL PAGI';
    } elseif ($sesi_filter === 'sore') {
        $judulApel = 'DAFTAR HADIR APEL SORE';
    } else {
        $judulApel = 'DAFTAR HADIR APEL PAGI / SORE';
    }

    // Format Hari, Tanggal Indonesia
    $hariIndo = ['1'=>'SENIN', '2'=>'SELASA', '3'=>'RABU', '4'=>'KAMIS', '5'=>'JUM\'AT', '6'=>'SABTU', '7'=>'MINGGU'];
    $bulanIndo = [
        '01'=>'JANUARI', '02'=>'FEBRUARI', '03'=>'MARET', '04'=>'APRIL',
        '05'=>'MEI', '06'=>'JUNI', '07'=>'JULI', '08'=>'AGUSTUS',
        '09'=>'SEPTEMBER', '10'=>'OKTOBER', '11'=>'NOVEMBER', '12'=>'DESEMBER'
    ];

    if (!empty($tgl_mulai) && !empty($tgl_selesai) && $tgl_mulai === $tgl_selesai) {
        $ts = strtotime($tgl_mulai);
        $wHari = $hariIndo[date('N', $ts)] ?? '';
        $d = date('j', $ts);
        $m = $bulanIndo[date('m', $ts)] ?? '';
        $y = date('Y', $ts);
        $hariTanggalText = ":   {$wHari},  {$d} {$m} {$y}";
    } elseif (!empty($tgl_mulai) || !empty($tgl_selesai)) {
        $hariTanggalText = ":   " . ($tgl_mulai ?: 'Awal') . " s/d " . ($tgl_selesai ?: 'Sekarang');
    } elseif (!empty($data_presensi_apel)) {
        $ts = strtotime($data_presensi_apel[0]['tanggal']);
        $wHari = $hariIndo[date('N', $ts)] ?? '';
        $d = date('j', $ts);
        $m = $bulanIndo[date('m', $ts)] ?? '';
        $y = date('Y', $ts);
        $hariTanggalText = ":   {$wHari},  {$d} {$m} {$y}";
    } else {
        $ts = time();
        $wHari = $hariIndo[date('N', $ts)] ?? '';
        $d = date('j', $ts);
        $m = $bulanIndo[date('m', $ts)] ?? '';
        $y = date('Y', $ts);
        $hariTanggalText = ":   {$wHari},  {$d} {$m} {$y}";
    }

    // Header Format persis ABSEN apel.xlsx
    $sheet->mergeCells('A6:F6');
    $sheet->setCellValue('A6', $judulApel);
    $sheet->getStyle('A6')->getFont()->setName('Arial')->setSize(14)->setBold(true);
    $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(6)->setRowHeight(26);

    // Baris 8: HARI, TANGGAL
    $sheet->setCellValue('A8', 'HARI, TANGGAL');
    $sheet->getStyle('A8')->getFont()->setName('Arial')->setSize(10)->setBold(true);
    $sheet->getStyle('A8')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->mergeCells('C8:F8');
    $sheet->setCellValue('C8', $hariTanggalText);
    $sheet->getStyle('C8')->getFont()->setName('Arial')->setSize(10)->setBold(true);
    $sheet->getStyle('C8')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(8)->setRowHeight(20);

    // Baris 10: Table Headers persis ABSEN apel.xlsx
    $startRow = 10;
    $sheet->setCellValue('A10', 'NO');
    $sheet->mergeCells('B10:C10');
    $sheet->setCellValue('B10', 'NAMA');
    $sheet->setCellValue('D10', 'JABATAN');
    $sheet->mergeCells('E10:F10');
    $sheet->setCellValue('E10', 'TANDA TANGAN');

    $tableHeaderStyle = [
        'font' => [
            'name' => 'Arial',
            'bold' => true,
            'size' => 10,
            'color' => ['rgb' => '000000'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];

    $sheet->getStyle("A10:F10")->applyFromArray($tableHeaderStyle);
    $sheet->getRowDimension(10)->setRowHeight(26);

    $thinBorderAll = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];

    $currentRow = 11;
    $no = 1;

    if (!empty($data_presensi_apel)) {
        foreach ($data_presensi_apel as $row) {
            $sheet->mergeCells("B{$currentRow}:C{$currentRow}");
            $sheet->mergeCells("E{$currentRow}:F{$currentRow}");

            $sheet->setCellValue("A{$currentRow}", $no);
            $sheet->setCellValue("B{$currentRow}", $row['nama']);
            $sheet->setCellValue("D{$currentRow}", $row['jabatan']);

            // Alignments
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$currentRow}:C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("E{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($thinBorderAll);
            $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getFont()->setName('Arial')->setSize(9.5);

            // Tanda Tangan Image Embed
            $sigPath = createTempImageFromBase64($row['tanda_tangan'] ?? null, $tempFiles);
            if ($sigPath && file_exists($sigPath)) {
                $sheet->getRowDimension($currentRow)->setRowHeight(44);
                $drawing = new Drawing();
                $drawing->setName('TTD_Apel_' . $no);
                $drawing->setDescription('Tanda Tangan ' . $row['nama']);
                $drawing->setPath($sigPath);
                $drawing->setCoordinates("E{$currentRow}");
                $drawing->setHeight(48);
                $drawing->setOffsetX(35);
                $drawing->setOffsetY(2);
                $drawing->setWorksheet($sheet);
            } else {
                $sheet->setCellValue("E{$currentRow}", '');
                $sheet->getRowDimension($currentRow)->setRowHeight(24);
            }

            $currentRow++;
            $no++;
        }
    } else {
        $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", 'Belum ada data presensi apel sesuai filter.');
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($thinBorderAll);
        $sheet->getRowDimension($currentRow)->setRowHeight(30);
        $currentRow++;
    }

    // Bagian Mengetahui / Footer persis template ABSEN apel.xlsx
    $currentRow += 2; // Beri jarak 2 baris kosong
    $ttdCol = 'D';

    $sheet->setCellValue("{$ttdCol}{$currentRow}", 'Mengetahui,');
    $sheet->getStyle("{$ttdCol}{$currentRow}")->getFont()->setName('Arial')->setSize(10)->setBold(true);
    $currentRow++;

    $sheet->setCellValue("{$ttdCol}{$currentRow}", 'KETUA PENGADILAN AGAMA PALEMBANG KELAS IA');
    $sheet->getStyle("{$ttdCol}{$currentRow}")->getFont()->setName('Arial')->setSize(10)->setBold(true);
    $currentRow += 4; // Beri ruang tanda tangan fisik ketua

    $sheet->setCellValue("{$ttdCol}{$currentRow}", 'MURSYID SYAH, S.Ag.');
    $sheet->getStyle("{$ttdCol}{$currentRow}")->getFont()->setName('Arial')->setSize(10)->setBold(true)->setUnderline(true);
    $currentRow++;

    $sheet->setCellValue("{$ttdCol}{$currentRow}", 'NIP. 197111251998031005');
    $sheet->getStyle("{$ttdCol}{$currentRow}")->getFont()->setName('Arial')->setSize(10)->setBold(true);

    // Set Lebar Kolom yang proporsional mengikuti ABSEN apel.xlsx
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(34);
    $sheet->getColumnDimension('E')->setWidth(14);
    $sheet->getColumnDimension('F')->setWidth(14);

    $filename = "DAFTAR_HADIR_APEL_" . strtoupper($sesi_filter) . "_" . date('Ymd_His') . ".xlsx";

} elseif ($type === 'harian') {
    // =========================================================================
    // EXPORT REKAP ABSENSI HARIAN
    // =========================================================================
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

    // Judul & Metadata
    $sheet->setCellValue('A1', 'REKAP ABSENSI HARIAN');
    $sheet->getStyle('A1')->applyFromArray($titleStyle);
    $sheet->getRowDimension(1)->setRowHeight(25);

    $periodeLabel = ($harian_tgl_mulai ? $harian_tgl_mulai : 'Semua') . ' s/d ' . ($harian_tgl_selesai ? $harian_tgl_selesai : 'Semua');
    $sheet->setCellValue('A2', 'Periode: ' . $periodeLabel);
    $sheet->setCellValue('A3', 'Total Riwayat: ' . count($data_presensi_harian) . ' catatan');
    $sheet->setCellValue('A4', 'Tanggal Cetak: ' . date('d/m/Y H:i:s'));
    $sheet->getStyle('A2:A4')->applyFromArray($metaLabelStyle);

    $headers = ['No', 'Tanggal', 'NIP', 'Nama Pegawai', 'Jabatan', 'Jam Masuk (Check-in)', 'Jam Pulang (Check-out)', 'Foto'];
    $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $startRow = 6;

    foreach ($headers as $idx => $h) {
        $cell = $cols[$idx] . $startRow;
        $sheet->setCellValue($cell, $h);
    }
    $sheet->getStyle("A{$startRow}:H{$startRow}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($startRow)->setRowHeight(28);

    $currentRow = $startRow + 1;
    $no = 1;

    if (!empty($data_presensi_harian)) {
        foreach ($data_presensi_harian as $row) {
            $sheet->setCellValue("A{$currentRow}", $no);
            $sheet->setCellValue("B{$currentRow}", date('d/m/Y', strtotime($row['tanggal'])));
            $sheet->setCellValue("C{$currentRow}", $row['nip'] ?: '-');
            $sheet->setCellValue("D{$currentRow}", $row['nama']);
            $sheet->setCellValue("E{$currentRow}", $row['jabatan']);
            $sheet->setCellValue("F{$currentRow}", $row['waktu_checkin'] ?: '-');
            $sheet->setCellValue("G{$currentRow}", $row['waktu_checkout'] ?: '-');

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray($dataBorder);

            // Embed Foto Absen jika file foto ada
            $fotoPath = !empty($row['foto_path']) ? __DIR__ . '/../' . ltrim($row['foto_path'], '/\\') : null;
            if ($fotoPath && file_exists($fotoPath)) {
                $sheet->getRowDimension($currentRow)->setRowHeight(45);
                $drawing = new Drawing();
                $drawing->setName('Foto_' . $no);
                $drawing->setDescription('Foto Absen ' . $row['nama']);
                $drawing->setPath($fotoPath);
                $drawing->setCoordinates("H{$currentRow}");
                $drawing->setHeight(48);
                $drawing->setOffsetX(15);
                $drawing->setOffsetY(4);
                $drawing->setWorksheet($sheet);
            } else {
                $sheet->setCellValue("H{$currentRow}", '-');
                $sheet->getRowDimension($currentRow)->setRowHeight(24);
            }

            $currentRow++;
            $no++;
        }
    } else {
        $sheet->setCellValue("A{$currentRow}", 'Belum ada data absensi harian sesuai filter.');
        $sheet->mergeCells("A{$currentRow}:H{$currentRow}");
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray($dataBorder);
        $sheet->getRowDimension($currentRow)->setRowHeight(30);
        $currentRow++;
    }

    $currentRow++;
    $sheet->setCellValue("A{$currentRow}", '* Catatan: Data absensi harian ini sah dan berasal dari konfirmasi presensi pegawai via aplikasi Android.');
    $sheet->getStyle("A{$currentRow}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('6B7280');

    // Column widths
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(28);
    $sheet->getColumnDimension('E')->setWidth(24);
    $sheet->getColumnDimension('F')->setWidth(20);
    $sheet->getColumnDimension('G')->setWidth(20);
    $sheet->getColumnDimension('H')->setWidth(18);

    $filename = "Rekap_Absensi_Harian_" . date('Ymd_His') . ".xlsx";
} else {
    http_response_code(400);
    die('Tipe export tidak valid.');
}

// =========================================================================
// OUTPUT FILE EXCEL KE BROWSER
// =========================================================================
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); // IE9
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Past date
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
