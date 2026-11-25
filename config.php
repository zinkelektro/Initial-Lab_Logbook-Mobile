<?php
declare(strict_types=1);

/*
 * Konfigurasi Logbook Alat Laboratorium
 */
// Override these values in the PHP-FPM environment on the server.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'lab_logbook';
const DB_USER = 'labuser';
const DB_PASS = '';

const APP_NAME = 'Logbook Alat Laboratorium';

// Identitas kop laporan PDF. Ganti sesuai identitas resmi laboratorium.
const LAB_INSTITUTION = 'UPTD BINA KONSTRUKSI DAN PENGUJIAN MATERIAL';
const LAB_NAME = 'LABORATORIUM PENGUJIAN MATERIAL';
const LAB_ADDRESS = 'Provinsi Nusa Tenggara Barat';
const LAB_CONTACT = '';
const LAB_CITY = 'Mataram';
const LAB_HEAD = 'Nama Kepala Laboratorium';
const LAB_HEAD_TITLE = 'Kepala Laboratorium';
const LAB_CHECKER = 'Nama Pemeriksa';

const TIMEZONE = 'Asia/Makassar';

date_default_timezone_set(TIMEZONE);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('LAB_DB_HOST') ?: DB_HOST;
        $name = getenv('LAB_DB_NAME') ?: DB_NAME;
        $user = getenv('LAB_DB_USER') ?: DB_USER;
        $pass = getenv('LAB_DB_PASS');
        if ($pass === false) $pass = DB_PASS;
        $dsn = 'mysql:host='.$host.';dbname='.$name.';charset=utf8mb4';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    if (empty($_SESSION['user_id'])) return $user = null;
    $st = db()->prepare("SELECT id, username, nama, role, aktif FROM admin WHERE id=? LIMIT 1");
    $st->execute([(int)$_SESSION['user_id']]);
    $user = $st->fetch() ?: null;
    if (!$user || !(int)$user['aktif']) {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['admin_id'], $_SESSION['admin_name']);
        $user = null;
    }
    return $user;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function role_level(string $role): int {
    return ['viewer'=>1, 'petugas'=>2, 'admin'=>3][$role] ?? 0;
}

function require_role(string $role): void {
    require_login();
    $u = current_user();
    if (!$u || role_level((string)$u['role']) < role_level($role)) {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="assets/style.css"><main class="container"><div class="panel"><h1>Akses ditolak</h1><p>Akun Anda tidak memiliki izin untuk membuka halaman ini.</p><a class="btn primary" href="index.php">Kembali ke Dashboard</a></div></main>';
        exit;
    }
}

function is_admin(): bool { return current_user()['role'] === 'admin'; }
function can_manage_operations(): bool { return in_array(current_user()['role'], ['admin','petugas'], true); }
