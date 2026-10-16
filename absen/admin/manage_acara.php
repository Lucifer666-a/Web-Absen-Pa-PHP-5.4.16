<?php
// absen/admin/manage_acara.php
require_once 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfCheck();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $nama_acara = trim(isset($_POST['nama_acara']) ? $_POST['nama_acara'] : '');
        $pin_acara = trim(isset($_POST['pin_acara']) ? $_POST['pin_acara'] : '');

        if (!empty($nama_acara) && !empty($pin_acara)) {
            // Tutup semua acara yang sedang buka
            $pdo->query("UPDATE acara SET status = 'TUTUP' WHERE status = 'BUKA'");

            // Insert acara baru
            $sql = "INSERT INTO acara (nama_acara, pin_acara, status) VALUES (?, ?, 'BUKA')";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute(array($nama_acara, $pin_acara))) {
                logAdminActivity($pdo, 'Membuat Acara Rapat', "Acara: {$nama_acara} (PIN: {$pin_acara})");
                header("Location: dashboard.php?msg=success");
                exit;
            }
        }
    } elseif ($action === 'toggle_status') {
        $acara_id = isset($_POST['acara_id']) ? $_POST['acara_id'] : '';
        $current_status = isset($_POST['current_status']) ? $_POST['current_status'] : '';
        
        $new_status = ($current_status === 'BUKA') ? 'TUTUP' : 'BUKA';

        // Jika mengubah menjadi BUKA, tutup yang lain dulu agar hanya ada 1 yg BUKA
        if ($new_status === 'BUKA') {
            $pdo->query("UPDATE acara SET status = 'TUTUP' WHERE status = 'BUKA'");
        }

        $stmt = $pdo->prepare("UPDATE acara SET status = ? WHERE id = ?");
        if ($stmt->execute(array($new_status, $acara_id))) {
            logAdminActivity($pdo, 'Ubah Status Rapat', "Acara ID: {$acara_id} diubah ke {$new_status}");
            header("Location: dashboard.php?acara_id=" . $acara_id . "&msg=success");
            exit;
        }
    } elseif ($action === 'edit') {
        $acara_id   = (int)(isset($_POST['acara_id']) ? $_POST['acara_id'] : 0);
        $nama_acara = trim(isset($_POST['nama_acara']) ? $_POST['nama_acara'] : '');
        $pin_acara  = trim(isset($_POST['pin_acara']) ? $_POST['pin_acara'] : '');

        if ($acara_id > 0 && !empty($nama_acara) && !empty($pin_acara)) {
            $stmt = $pdo->prepare("UPDATE acara SET nama_acara = ?, pin_acara = ? WHERE id = ?");
            if ($stmt->execute(array($nama_acara, $pin_acara, $acara_id))) {
                logAdminActivity($pdo, 'Edit Acara Rapat', "Acara ID: {$acara_id} - Nama: {$nama_acara}");
                header("Location: dashboard.php?tab=rapat&acara_id=" . $acara_id . "&msg=success");
                exit;
            }
        }
    } elseif ($action === 'delete') {
        $acara_id = (int)(isset($_POST['acara_id']) ? $_POST['acara_id'] : 0);
        if ($acara_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM acara WHERE id = ?");
            $stmt->execute(array($acara_id));
            logAdminActivity($pdo, 'Hapus Acara Rapat', "Acara ID: {$acara_id} dihapus");
        }
        header("Location: dashboard.php?tab=rapat&msg=success");
        exit;
    }

    // Fallback jika error
    header("Location: dashboard.php?msg=error");
    exit;
} else {
    http_response_code(405);
    die("Method Not Allowed");
}
?>
