<?php
session_start();
include "db.php";

$role = $_POST['role'];
$username = $_POST['username'];
$password = $_POST['password'];  // plaintext dahulu

// Gunakan struktur tabel asli kamu
switch ($role) {

    case "kcd":
        $sql = "SELECT * FROM KCD WHERE email = $1 OR kode_kcd = $1";
        $res = pg_query_params($conn, $sql, array($username));
        $data = pg_fetch_assoc($res);

        if ($data) {
            $_SESSION['role'] = "kcd";
            $_SESSION['kode_kcd'] = $data['kode_kcd'];
            header("Location: kcd_dashboard.php");
            exit;
        }
        break;


    case "pengawas":
        $sql = "SELECT * FROM Pengawas WHERE email = $1 OR nip = $1";
        $res = pg_query_params($conn, $sql, array($username));
        $data = pg_fetch_assoc($res);

        if ($data) {
            $_SESSION['role'] = "pengawas";
            $_SESSION['nip'] = $data['nip'];
            $_SESSION['kode_kcd'] = $data['kode_kcd'];
            header("Location: pengawas_dashboard.php");
            exit;
        }
        break;

    case "sekolah":
        $sql = "SELECT * FROM sekolah WHERE email_sekolah = $1 OR npsn = $1";
        $res = pg_query_params($conn, $sql, array($username));
        $data = pg_fetch_assoc($res);

        if ($data) {
            $_SESSION['role'] = "sekolah";
            $_SESSION['npsn'] = $data['npsn'];
            $_SESSION['kode_kcd'] = $data['kode_kcd'];
            header("Location: sekolah_dashboard.php");
            exit;
        }
        break;

    case "publik":
        $sql = "SELECT * FROM Publik WHERE email = $1 OR publik_id::text = $1";
        $res = pg_query_params($conn, $sql, array($username));
        $data = pg_fetch_assoc($res);

        if ($data) {
            $_SESSION['role'] = "publik";
            $_SESSION['publik_id'] = $data['publik_id'];
            header("Location: publik_dashboard.php");
            exit;
        }
        break;
}

// Jika gagal
$_SESSION['error'] = "Login gagal! Periksa kembali identitas Anda.";
header("Location: index.php");
exit;
?>
