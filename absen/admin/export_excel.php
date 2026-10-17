<?php
// absen/admin/export_excel.php
require_once __DIR__ . '/auth_check.php';

// Load library PHPExcel (kompatibel penuh dengan PHP 5.4.16)
$phpExcelPath = __DIR__ . '/../../vendor/phpoffice/phpexcel/Classes/PHPExcel.php';
if (!file_exists($phpExcelPath)) {
    http_response_code(500);
    die('Error: Library vendor PHPExcel belum terinstall di vendor/phpoffice/phpexcel.');
}
require_once $phpExcelPath;

$type = isset($_GET['type']) ? $_GET['type'] : 'rapat';
$tempFiles = array();

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
function createTempImageFromBase64($base64Data, array &$tempFiles) {
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

function addKopSuratToSheet($sheet, $maxCol = 'F') {
    $kopCandidates = array(
        __DIR__ . '/../assets/kop.png',
        __DIR__ . '/../../kop.png',
        dirname(dirname(__DIR__)) . '/kop.png',
    );

    $kopFile = null;
    foreach ($kopCandidates as $candidate) {
        if (file_exists($candidate)) {
            $kopFile = $candidate;
            break;
        }
    }

    if ($kopFile) {
        // Atur tinggi baris 1-4 untuk ruang KOP surat
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getRowDimension(2)->setRowHeight(32);
        $sheet->getRowDimension(3)->setRowHeight(30);
        $sheet->getRowDimension(4)->setRowHeight(25);
        $sheet->getRowDimension(5)->setRowHeight(12); // Spasi pemisah

        $drawing = new PHPExcel_Worksheet_Drawing();
        $drawing->setName('KopSurat');
        $drawing->setDescription('Kop Surat Pengadilan Agama');
        $drawing->setPath($kopFile);
        $drawing->setCoordinates('A1');
        $drawing->setHeight(115);
        $drawing->setOffsetX(10);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($sheet);

        return 6; // Baris awal konten berikutnya
    }

    return 1;
}

/**
 * Tanda tangan mengetahui Ketua Pengadilan Agama di pojok kanan bawah
 */
function addTandaTanganKetua($sheet, $startRow, $col = 'D') {
    $row = $startRow + 2; // Spasi pemisah

    $sheet->setCellValue("{$col}{$row}", 'Mengetahui,');
    $sheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setSize(10)->setBold(true);
    $row++;

    $sheet->setCellValue("{$col}{$row}", 'KETUA PENGADILAN AGAMA PALEMBANG KELAS IA');
    $sheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setSize(10)->setBold(true);
    $row += 4; // Beri ruang tanda tangan fisik ketua

    $sheet->setCellValue("{$col}{$row}", 'MURSYID SYAH, S.Ag.');
    $sheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setSize(10)->setBold(true)->setUnderline(true);
    $row++;

    $sheet->setCellValue("{$col}{$row}", 'NIP. 197111251998031005');
    $sheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setSize(10)->setBold(true);

    return $row;
}

$objPHPExcel = new PHPExcel();
$sheet = $objPHPExcel->getActiveSheet();
$sheet->setShowGridLines(true);

// Default font styling
$objPHPExcel->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

$headerStyle = array(
    'font' => array(
        'bold' => true,
        'color' => array('rgb' => 'FFFFFF'),
        'size' => 11,
    ),
    'fill' => array(
        'type' => PHPExcel_Style_Fill::FILL_SOLID,
        'startcolor' => array('rgb' => '1F2937'), // Dark slate
    ),
    'alignment' => array(
        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
        'vertical'   => PHPExcel_Style_Alignment::VERTICAL_CENTER,
        'wrap'       => true,
    ),
    'borders' => array(
        'allborders' => array(
            'style' => PHPExcel_Style_Border::BORDER_THIN,
            'color' => array('rgb' => 'D1D5DB'),
        ),
    ),
);

$titleStyle = array(
    'font' => array(
        'bold' => true,
        'size' => 15,
        'color' => array('rgb' => '111827'),
    ),
    'alignment' => array(
        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
        'vertical'   => PHPExcel_Style_Alignment::VERTICAL_CENTER,
    ),
);

$metaLabelStyle = array(
    'font' => array('bold' => true, 'color' => array('rgb' => '4B5563'), 'size' => 10),
);

$dataBorder = array(
    'borders' => array(
        'allborders' => array(
            'style' => PHPExcel_Style_Border::BORDER_THIN,
            'color' => array('rgb' => 'E5E7EB'),
        ),
    ),
);

if ($type === 'rapat') {
    // =========================================================================
    // EXPORT REKAP PRESENSI RAPAT / ACARA
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

    $namaAcara = isset($active_acara['nama_acara']) ? $active_acara['nama_acara'] : '-';
    $pinAcara  = isset($active_acara['pin_acara']) ? $active_acara['pin_acara'] : '-';
    $statusAcara = isset($active_acara['status']) ? $active_acara['status'] : '-';

    $startKopRow = addKopSuratToSheet($sheet, 'E');

    // Judul & Metadata
    $rowJudul = $startKopRow;
    $sheet->setCellValue("A{$rowJudul}", 'REKAP PRESENSI RAPAT / ACARA');
    $sheet->getStyle("A{$rowJudul}")->applyFromArray($titleStyle);
    $sheet->getRowDimension($rowJudul)->setRowHeight(25);

    $rowMeta1 = $rowJudul + 1;
    $rowMeta2 = $rowJudul + 2;
    $rowMeta3 = $rowJudul + 3;
    $sheet->setCellValue("A{$rowMeta1}", 'Nama Acara: ' . $namaAcara);
    $sheet->setCellValue("A{$rowMeta2}", 'PIN Acara: ' . $pinAcara . '  |  Status: ' . $statusAcara);
    $sheet->setCellValue("A{$rowMeta3}", 'Tanggal Cetak: ' . date('d/m/Y H:i:s'));
    $sheet->getStyle("A{$rowMeta1}:A{$rowMeta3}")->applyFromArray($metaLabelStyle);

    // Header Kolom Tabel
    $headers = array('No', 'Waktu Absen', 'Nama Pegawai', 'Jabatan', 'Tanda Tangan');
    $cols = array('A', 'B', 'C', 'D', 'E');
    $startRow = $rowMeta3 + 2;

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
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray($dataBorder);

            // Tanda Tangan Image Embed
            $sigPath = createTempImageFromBase64(isset($row['tanda_tangan']) ? $row['tanda_tangan'] : null, $tempFiles);
            if ($sigPath && file_exists($sigPath)) {
                $sheet->setCellValue("E{$currentRow}", '');
                $sheet->getRowDimension($currentRow)->setRowHeight(45);
                $drawing = new PHPExcel_Worksheet_Drawing();
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
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray($dataBorder);
        $sheet->getRowDimension($currentRow)->setRowHeight(30);
        $currentRow++;
    }

    // Tanda Tangan Mengetahui Ketua Pengadilan Agama
    $currentRow = addTandaTanganKetua($sheet, $currentRow, 'D');

    // Catatan Kaki
    $currentRow += 2;
    $sheet->setCellValue("A{$currentRow}", '* Catatan: Data presensi ini sah dan berasal dari konfirmasi presensi pegawai via aplikasi Android / Web.');
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
        $uNama = $stmtU->fetchColumn() ? $stmtU->fetchColumn() : '';
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
    $hariIndo = array('1'=>'SENIN', '2'=>'SELASA', '3'=>'RABU', '4'=>'KAMIS', '5'=>'JUM\'AT', '6'=>'SABTU', '7'=>'MINGGU');
    $bulanIndo = array(
        '01'=>'JANUARI', '02'=>'FEBRUARI', '03'=>'MARET', '04'=>'APRIL',
        '05'=>'MEI', '06'=>'JUNI', '07'=>'JULI', '08'=>'AGUSTUS',
        '09'=>'SEPTEMBER', '10'=>'OKTOBER', '11'=>'NOVEMBER', '12'=>'DESEMBER'
    );

    if (!empty($tgl_mulai) && !empty($tgl_selesai) && $tgl_mulai === $tgl_selesai) {
        $ts = strtotime($tgl_mulai);
        $wHari = isset($hariIndo[date('N', $ts)]) ? $hariIndo[date('N', $ts)] : '';
        $d = date('j', $ts);
        $m = isset($bulanIndo[date('m', $ts)]) ? $bulanIndo[date('m', $ts)] : '';
        $y = date('Y', $ts);
        $hariTanggalText = ":   {$wHari},  {$d} {$m} {$y}";
    } elseif (!empty($tgl_mulai) || !empty($tgl_selesai)) {
        $hariTanggalText = ":   " . ($tgl_mulai ? $tgl_mulai : 'Awal') . " s/d " . ($tgl_selesai ? $tgl_selesai : 'Sekarang');
    } elseif (!empty($data_presensi_apel)) {
        $ts = strtotime($data_presensi_apel[0]['tanggal']);
        $wHari = isset($hariIndo[date('N', $ts)]) ? $hariIndo[date('N', $ts)] : '';
        $d = date('j', $ts);
        $m = isset($bulanIndo[date('m', $ts)]) ? $bulanIndo[date('m', $ts)] : '';
        $y = date('Y', $ts);
        $hariTanggalText = ":   {$wHari},  {$d} {$m} {$y}";
    } else {
        $ts = time();
        $wHari = isset($hariIndo[date('N', $ts)]) ? $hariIndo[date('N', $ts)] : '';
        $d = date('j', $ts);
        $m = isset($bulanIndo[date('m', $ts)]) ? $bulanIndo[date('m', $ts)] : '';
        $y = date('Y', $ts);
        $hariTanggalText = ":   {$wHari},  {$d} {$m} {$y}";
    }

    $startKopRow = addKopSuratToSheet($sheet, 'F');

    // Header Format persis ABSEN apel.xlsx
    $rowJudul = $startKopRow + 1;
    $sheet->mergeCells("A{$rowJudul}:F{$rowJudul}");
    $sheet->setCellValue("A{$rowJudul}", $judulApel);
    $sheet->getStyle("A{$rowJudul}")->getFont()->setName('Arial')->setSize(14)->setBold(true);
    $sheet->getStyle("A{$rowJudul}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension($rowJudul)->setRowHeight(26);

    // Baris HARI, TANGGAL
    $rowHari = $rowJudul + 2;
    $sheet->setCellValue("A{$rowHari}", 'HARI, TANGGAL');
    $sheet->getStyle("A{$rowHari}")->getFont()->setName('Arial')->setSize(10)->setBold(true);
    $sheet->getStyle("A{$rowHari}")->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

    $sheet->mergeCells("C{$rowHari}:F{$rowHari}");
    $sheet->setCellValue("C{$rowHari}", $hariTanggalText);
    $sheet->getStyle("C{$rowHari}")->getFont()->setName('Arial')->setSize(10)->setBold(true);
    $sheet->getStyle("C{$rowHari}")->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension($rowHari)->setRowHeight(20);

    // Table Headers persis ABSEN apel.xlsx
    $startRow = $rowHari + 1;
    $sheet->setCellValue("A{$startRow}", 'NO');
    $sheet->mergeCells("B{$startRow}:C{$startRow}");
    $sheet->setCellValue("B{$startRow}", 'NAMA');
    $sheet->setCellValue("D{$startRow}", 'JABATAN');
    $sheet->mergeCells("E{$startRow}:F{$startRow}");
    $sheet->setCellValue("E{$startRow}", 'TANDA TANGAN');

    $tableHeaderStyle = array(
        'font' => array(
            'name' => 'Arial',
            'bold' => true,
            'size' => 10,
            'color' => array('rgb' => '000000'),
        ),
        'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            'vertical'   => PHPExcel_Style_Alignment::VERTICAL_CENTER,
        ),
        'borders' => array(
            'allborders' => array(
                'style' => PHPExcel_Style_Border::BORDER_THIN,
                'color' => array('rgb' => '000000'),
            ),
        ),
    );

    $sheet->getStyle("A{$startRow}:F{$startRow}")->applyFromArray($tableHeaderStyle);
    $sheet->getRowDimension($startRow)->setRowHeight(26);

    $thinBorderAll = array(
        'borders' => array(
            'allborders' => array(
                'style' => PHPExcel_Style_Border::BORDER_THIN,
                'color' => array('rgb' => '000000'),
            ),
        ),
    );

    $currentRow = $startRow + 1;
    $no = 1;

    if (!empty($data_presensi_apel)) {
        foreach ($data_presensi_apel as $row) {
            $sheet->mergeCells("B{$currentRow}:C{$currentRow}");
            $sheet->mergeCells("E{$currentRow}:F{$currentRow}");

            $sheet->setCellValue("A{$currentRow}", $no);
            $sheet->setCellValue("B{$currentRow}", $row['nama']);
            $sheet->setCellValue("D{$currentRow}", $row['jabatan']);

            // Alignments
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$currentRow}:C{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("E{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

            $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($thinBorderAll);
            $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getFont()->setName('Arial')->setSize(9.5);

            // Tanda Tangan Image Embed
            $sigPath = createTempImageFromBase64(isset($row['tanda_tangan']) ? $row['tanda_tangan'] : null, $tempFiles);
            if ($sigPath && file_exists($sigPath)) {
                $sheet->setCellValue("E{$currentRow}", '');
                $sheet->getRowDimension($currentRow)->setRowHeight(44);
                $drawing = new PHPExcel_Worksheet_Drawing();
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
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($thinBorderAll);
        $sheet->getRowDimension($currentRow)->setRowHeight(30);
        $currentRow++;
    }

    // Bagian Mengetahui / Footer persis template ABSEN apel.xlsx
    $currentRow = addTandaTanganKetua($sheet, $currentRow, 'D');

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

    $startKopRow = addKopSuratToSheet($sheet, 'H');

    // Judul & Metadata
    $rowJudul = $startKopRow;
    $sheet->setCellValue("A{$rowJudul}", 'REKAP ABSENSI HARIAN');
    $sheet->getStyle("A{$rowJudul}")->applyFromArray($titleStyle);
    $sheet->getRowDimension($rowJudul)->setRowHeight(25);

    $rowMeta1 = $rowJudul + 1;
    $rowMeta2 = $rowJudul + 2;
    $rowMeta3 = $rowJudul + 3;
    $periodeLabel = ($harian_tgl_mulai ? $harian_tgl_mulai : 'Semua') . ' s/d ' . ($harian_tgl_selesai ? $harian_tgl_selesai : 'Semua');
    $sheet->setCellValue("A{$rowMeta1}", 'Periode: ' . $periodeLabel);
    $sheet->setCellValue("A{$rowMeta2}", 'Total Riwayat: ' . count($data_presensi_harian) . ' catatan');
    $sheet->setCellValue("A{$rowMeta3}", 'Tanggal Cetak: ' . date('d/m/Y H:i:s'));
    $sheet->getStyle("A{$rowMeta1}:A{$rowMeta3}")->applyFromArray($metaLabelStyle);

    $headers = array('No', 'Tanggal', 'NIP', 'Nama Pegawai', 'Jabatan', 'Jam Masuk (Check-in)', 'Jam Pulang (Check-out)', 'Foto');
    $cols = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H');
    $startRow = $rowMeta3 + 2;

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
            $sheet->setCellValue("C{$currentRow}", $row['nip'] ? $row['nip'] : '-');
            $sheet->setCellValue("D{$currentRow}", $row['nama']);
            $sheet->setCellValue("E{$currentRow}", $row['jabatan']);
            $sheet->setCellValue("F{$currentRow}", $row['waktu_checkin'] ? $row['waktu_checkin'] : '-');
            $sheet->setCellValue("G{$currentRow}", $row['waktu_checkout'] ? $row['waktu_checkout'] : '-');

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

            $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray($dataBorder);

            // Embed Foto Absen jika file foto ada
            $fotoPath = !empty($row['foto_path']) ? __DIR__ . '/../' . ltrim($row['foto_path'], '/\\') : null;
            if ($fotoPath && file_exists($fotoPath)) {
                $sheet->getRowDimension($currentRow)->setRowHeight(45);
                $drawing = new PHPExcel_Worksheet_Drawing();
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
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray($dataBorder);
        $sheet->getRowDimension($currentRow)->setRowHeight(30);
        $currentRow++;
    }

    // Tanda Tangan Mengetahui Ketua Pengadilan Agama (Kolom F agar pas di sisi kanan tabel A-H)
    $currentRow = addTandaTanganKetua($sheet, $currentRow, 'F');

    // Catatan Kaki
    $currentRow += 2;
    $sheet->setCellValue("A{$currentRow}", '* Catatan: Data absensi harian ini sah dan berasal dari konfirmasi presensi pegawai via aplikasi Android / Web.');
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

$writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$writer->save('php://output');
exit;
