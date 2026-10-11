<?php
// absen/config/ip_whitelist.php
// Network / IP Whitelist restriction helper for Attendance endpoints

/**
 * Pengaturan Jaringan Terkoordinasi / IP Whitelist
 * Masukkan rentang IP CIDR atau IP spesifik kantor yang diizinkan untuk melakukan absensi.
 * Jika set $IP_WHITELIST_ENABLED = false, validasi IP akan dilewati.
 */
$GLOBALS['IP_WHITELIST_ENABLED'] = true;

$GLOBALS['ALLOWED_NETWORKS'] = [
    '127.0.0.1/32',     // Localhost IPv4 (Development)
    '::1/128',          // Localhost IPv6 (Development)
    '192.168.5.100/32', // IP Spesifik Kantor
    '192.168.5.0/24',   // Subnet Jaringan Wi-Fi/LAN Kantor (192.168.5.x)
    '192.168.0.0/16',   // Jaringan Lokal Class C
    '10.0.0.0/8',       // Jaringan Lokal Class A
    '172.16.0.0/12',    // Jaringan Lokal Class B
];

/**
 * Ambil IP client yang melakukan request secara aman
 * Menggunakan REMOTE_ADDR untuk mencegah IP spoofing via Header HTTP (X-Forwarded-For)
 */
function getClientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Memeriksa apakah suatu IP berada dalam salah satu subnet/IP yang diizinkan
 */
function isIpAllowed(string $ip, ?array $allowedNetworks = null): bool {
    if ($allowedNetworks === null) {
        $allowedNetworks = $GLOBALS['ALLOWED_NETWORKS'] ?? [];
    }

    // Tangani perbandingan langsung IP (misal ::1 atau IP persis)
    foreach ($allowedNetworks as $network) {
        if ($network === $ip) {
            return true;
        }

        if (strpos($network, '/') !== false) {
            list($subnet, $bits) = explode('/', $network, 2);
            $bits = (int)$bits;

            // Pengujian IPv4 CIDR
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipLong     = ip2long($ip);
                $subnetLong = ip2long($subnet);

                if ($ipLong !== false && $subnetLong !== false) {
                    $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
                    if (($ipLong & $mask) === ($subnetLong & $mask)) {
                        return true;
                    }
                }
            }

            // Pengujian IPv6 CIDR
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ipBin     = inet_pton($ip);
                $subnetBin = inet_pton($subnet);

                if ($ipBin !== false && $subnetBin !== false) {
                    $bytes = (int)ceil($bits / 8);
                    $same  = true;
                    for ($i = 0; $i < $bytes; $i++) {
                        $mask = 0xFF;
                        if ($i === $bytes - 1 && ($bits % 8) !== 0) {
                            $mask = (0xFF << (8 - ($bits % 8))) & 0xFF;
                        }
                        if ((ord($ipBin[$i]) & $mask) !== (ord($subnetBin[$i]) & $mask)) {
                            $same = false;
                            break;
                        }
                    }
                    if ($same) {
                        return true;
                    }
                }
            }
        }
    }

    return false;
}

/**
 * Enforce network restriction middleware
 * Menghentikan eksekusi jika request berasal dari luar jaringan yang diizinkan.
 */
function enforceNetworkRestriction(?array $allowedNetworks = null): void {
    $enabled = $GLOBALS['IP_WHITELIST_ENABLED'] ?? true;
    if (!$enabled) {
        return;
    }

    $clientIp = getClientIp();

    if (!isIpAllowed($clientIp, $allowedNetworks)) {
        error_log("[IP Whitelist Blocked] Akses ditolak dari IP: " . $clientIp);

        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status'  => 'error',
            'code'    => 'NETWORK_RESTRICTED',
            'message' => 'Fitur absensi hanya dapat diakses melalui jaringan Wi-Fi/LAN kantor.',
            'your_ip' => $clientIp
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
