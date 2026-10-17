@echo off
title PHP 5.4.45 Dev Server - Web Absen PA
color 0A

echo ============================================================
echo   PHP 5.4.45 Portable Dev Server - Web Absen PA
echo ============================================================
echo.

if not exist "C:\php54\php.exe" (
    echo [ERROR] PHP 5.4.45 tidak ditemukan di C:\php54\
    pause
    exit /b 1
)

echo [1/3] Mengecek MySQL XAMPP...
"C:\xampp\mysql\bin\mysqladmin.exe" -u root status >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] MySQL belum berjalan. Mencoba start...
    start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini"
    timeout /t 5 /nobreak >nul
) else (
    echo [OK] MySQL sudah berjalan.
)

echo [2/3] Memastikan database db_absen tersedia...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS db_absen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul

if exist "d:\GitHub\Web-Absen-Pa-PHP-5.4.16\absen\database.sql" (
    "C:\xampp\mysql\bin\mysql.exe" -u root db_absen < "d:\GitHub\Web-Absen-Pa-PHP-5.4.16\absen\database.sql" 2>nul
    echo [OK] Schema database siap.
)

echo [3/3] Menjalankan PHP 5.4.45 built-in server di port 8054...
echo.
echo ============================================================
echo   URL      : http://localhost:8054/absen/
echo   Admin    : http://localhost:8054/absen/admin/login.php
echo   PHP Ver  : 5.4.45
echo   Root Dir : d:\GitHub\Web-Absen-Pa-PHP-5.4.16
echo ============================================================
echo Tekan Ctrl+C untuk stop server.
echo.

start /min "" cmd /c "timeout /t 2 /nobreak >nul && start http://localhost:8054/absen/"

"C:\php54\php.exe" -S localhost:8054 -t "d:\GitHub\Web-Absen-Pa-PHP-5.4.16"
