<?php
session_start();
include "db.php";

// Proteksi role Pengawas
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pengawas') {
    header("Location: index.php");
    exit;
}

$nip_pengawas = $_SESSION['nip'];

// Ambil nama Pengawas
$q = pg_query_params($conn,
    "SELECT nama FROM pengawas WHERE nip = $1",
    array($nip_pengawas)
);
$data = pg_fetch_assoc($q);
$nama_pengawas = $data ? $data['nama'] : "Pengawas";

// =============== HANDLE CRUD LAPORAN MANAJERIAL ===================

// CREATE
if (isset($_POST['add_laporan'])) {
    $nomor = $_POST['nomor_laporan'];
    $npsn = $_POST['npsn'];
    $tgl = $_POST['tanggal_laporan'];
    $temuan = $_POST['temuan'];
    $rek = $_POST['rekomendasi'];

    $ins = pg_query_params($conn,
        "INSERT INTO laporanmanajerial 
        (nomor_laporan, nip_pengawas, npsn, kode_kcd, tanggal_laporan, temuan, rekomendasi)
        VALUES ($1,$2,$3,
                (SELECT kode_kcd FROM pengawas WHERE nip=$2),
                $4,$5,$6)",
        array($nomor, $nip_pengawas, $npsn, $tgl, $temuan, $rek)
    );
}

// UPDATE
if (isset($_POST['update_lap'])) {
    $nomor = $_POST['nomor_lap_edit'];
    $temuan = $_POST['temuan_edit'];
    $rek = $_POST['rekomendasi_edit'];

    pg_query_params($conn,
        "UPDATE laporanmanajerial
         SET temuan=$1, rekomendasi=$2
         WHERE nomor_laporan=$3 AND nip_pengawas=$4",
        array($temuan, $rek, $nomor, $nip_pengawas)
    );
}

// DELETE
if (isset($_POST['del_lap'])) {
    $nomor = $_POST['nomor_del'];

    pg_query_params($conn,
        "DELETE FROM laporanmanajerial 
         WHERE nomor_laporan=$1 AND nip_pengawas=$2",
        array($nomor, $nip_pengawas)
    );
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Pengawas</title>

<style>
/* A1 Soft Modern White theme */
:root{
    --bg:#f7fafc;
    --card:#ffffff;
    --muted:#6b7280;
    --accent:#4f46e5;
    --danger:#ef4444;
    --shadow:0 8px 22px rgba(15,23,42,0.06);
    --radius:12px;
}
body{
    font-family:'Inter','Segoe UI',system-ui,sans-serif;
    background:var(--bg);
    margin:0;color:#0f172a;
}
.container{max-width:1200px;margin:28px auto;padding:0 20px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.header .left h1{margin:0;font-size:28px}
.header .left .sub{color:var(--muted);margin-top:6px}
.logout{background:#ef4444;color:#fff;padding:8px 12px;border-radius:8px;text-decoration:none}
.grid{display:flex;flex-direction:column;gap:28px}
.panel{
    background:var(--card);padding:20px;border-radius:var(--radius);
    box-shadow:var(--shadow);min-height:140px;display:flex;flex-direction:column;gap:12px
}
.panel h3{margin:0;font-size:18px}
.btn{
    background:var(--accent);color:white;padding:8px 12px;border-radius:10px;
    text-decoration:none;font-weight:600;border:none;cursor:pointer
}
.btn.danger{background:var(--danger)}
.details-summary{cursor:pointer;color:var(--accent);font-weight:600}
.table-wrapper{overflow-x:auto;width:100%}
.table{
    width:100%;border-collapse:collapse;margin-top:8px;min-width:600px
}
.table th,.table td{
    padding:10px;border-bottom:1px solid #eef2f7;text-align:left;font-size:14px;white-space:nowrap
}
input,textarea,select{
    width:100%;padding:9px;border-radius:8px;border:1px solid #e6e9ef
}
textarea{min-height:80px}
.empty{color:var(--muted);padding:12px}
.section-title{font-size:22px;margin-top:20px;font-weight:700;color:#374151}
.divider{border:0;border-top:2px solid #e5e7eb;margin:40px 0 10px 0}

.btn.ghost {
    background: transparent;
    border: 1px solid #d1d5db;
    color: #374151;
    padding: 7px 10px;
    border-radius: 8px;
    cursor: pointer;
}
.btn.ghost:hover {
    background: #f3f4f6;
}

.btn.danger {
    background: #ef4444;
    color: white;
}

.btn.edit {
    background: rgba(79,70,229,0.08);
    color: #4f46e5;
    border: 1px solid rgba(79,70,229,0.35);
    padding: 7px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}
.btn.edit:hover {
    background: rgba(79,70,229,0.16);
}

:root{
    --bg:#f8f9fc;
    --card:#ffffff;
    --text:#1e293b;
    --text-light:#64748b;
    --primary:#4f46e5;
    --primary-dark:#4338ca;
    --danger:#ef4444;
    --radius:14px;
    --shadow:0 6px 18px rgba(0,0,0,0.06);
}

*{box-sizing:border-box;}

body{
    margin:0;
    font-family:'Inter', sans-serif;
    background:var(--bg);
    color:var(--text);
}

/* ---------------- NAVBAR (NEW FOR PENGAWAS) ----------------- */
.navbar{
    width:100%;
    background:var(--card);
    padding:14px 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    box-shadow:var(--shadow);
    position:sticky;
    top:0;
    z-index:50;
}

.nav-left{
    display:flex;
    align-items:center;
    gap:14px;
}

.brand{
    font-size:22px;
    font-weight:700;
    color:var(--primary);
}

.menu{
    display:flex;
    gap:24px;
    margin-left:40px;
}

.menu a{
    text-decoration:none;
    color:var(--text-light);
    font-size:15px;
    font-weight:500;
}

.menu a:hover{
    color:var(--primary);
}

.logout-btn{
    background:var(--danger);
    padding:8px 14px;
    color:white;
    border-radius:10px;
    text-decoration:none;
    font-weight:600;
}

/* ---------------- PAGE CONTAINER ----------------- */
.container{
    max-width:1250px;
    margin:35px auto;
    padding:0 20px;
}

.page-title{
    font-size:28px;
    font-weight:700;
    margin-bottom:6px;
}

.welcome-box{
    background:var(--card);
    padding:22px 28px;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    margin-bottom:24px;
}

.welcome-box .sub{
    color:var(--text-light);
    margin-top:4px;
}

/* ---------------- PANEL ----------------- */
.panel{
    background:var(--card);
    padding:24px;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    margin-bottom:26px;
}

.panel h3{
    margin:0 0 14px;
    font-size:18px;
    font-weight:600;
    color:var(--primary-dark);
}

/* ---------------- TABLE ----------------- */
.table-wrapper{
    overflow-x:auto;
}

.table{
    width:100%;
    border-collapse:collapse;
    min-width:650px;
}

.table th{
    background:#f1f2ff;
    text-align:left;
    padding:12px;
    font-size:14px;
    color:var(--text);
    border-bottom:1px solid #dde3ff;
}

.table td{
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    color:black;
    font-size:14px;
}

/* ---------------- INPUT / FORM ----------------- */
input, select, textarea{
    width:100%;
    padding:10px;
    border-radius:10px;
    border:1px solid #e2e8f0;
    margin-bottom:10px;
    font-size:15px;
}

input:focus, select:focus, textarea:focus{
    outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(79,70,229,0.25);
}

/* ---------------- BUTTONS ----------------- */
.btn{
    background:var(--primary);
    border:none;
    padding:8px 12px;
    border-radius:10px;
    color:white;
    font-weight:600;
    cursor:pointer;
    font-size:14px;
}

.btn:hover{
    background:var(--primary-dark);
}

.btn.ghost{
    background:#eef2ff;
    color:var(--primary);
    border:none;
}

.btn.ghost:hover{
    background:#e0e7ff;
}

.btn.danger{
    background:var(--danger);
    color:white;
}

.btn.danger:hover{
    background:#dc2626;
}

/* ---------------- DETAILS / SUMMARY ----------------- */
.details-summary{
    cursor:pointer;
    font-weight:600;
    color:var(--primary);
    padding:8px 0;
}

/* ---------------- SECTION TITLE ----------------- */
.section-title{
    font-size:22px;
    font-weight:700;
    color:var(--primary-dark);
    margin:40px 0 10px;
}

.divider{
    border:0;
    border-top:1px solid #e5e7eb;
    margin:40px 0 10px 0;
}


</style>
</head>
<body>
    <div class="navbar">
    <div class="nav-left">
        <div class="brand">Si Manggaran</div>
        <div class="menu">
            <a href="#">Dashboard</a>
            <a href="#panel-laporan">Laporan</a>
            <a href="#panel-sekolah">Sekolah</a>
            <a href="#panel-rencana">Rencana BOS</a>
            <a href="#panel-item">Item BOS</a>
            <a href="#panel-realisasi">Realisasi</a>
        </div>
    </div>

    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<div class="container">

    <div class="welcome-box">
    <div class="page-title">Dashboard Pengawas</div>
    <div class="sub">
        Selamat datang, <b><?= $nama_pengawas ?></b><br>
        NIP: <b><?= $nip_pengawas ?></b>
    </div>
</div>


    <div class="grid">

        <!-- ================== LAPORAN MANAJERIAL (MAINTAIN) ================= -->
        <div class="panel">
            <h3>Laporan Manajerial – Maintain</h3>

            <details>
                <summary class="details-summary">Tambah Laporan</summary>
                <form method="POST" style="margin-top:10px;">
                    <div class="form-row">
                        <label>Nomor Laporan</label>
                        <input type="text" name="nomor_laporan" required>
                    </div>

                    <div class="form-row">
                        <label>NPSN Sekolah</label>
                        <input type="text" name="npsn" required>
                    </div>

                    <div class="form-row">
                        <label>Tanggal Laporan</label>
                        <input type="date" name="tanggal_laporan" required>
                    </div>

                    <div class="form-row">
                        <label>Temuan</label>
                        <textarea name="temuan"></textarea>
                    </div>

                    <div class="form-row">
                        <label>Rekomendasi</label>
                        <textarea name="rekomendasi"></textarea>
                    </div>

                    <button class="btn" name="add_laporan">Simpan</button>
                </form>
            </details>

            <div class="table-wrapper">
                <table class="table">
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Temuan</th>
                        <th>Rekomendasi</th>
                        <th>Aksi</th>
                    </tr>

                    <?php
                    $q = pg_query_params($conn,
                        "SELECT nomor_laporan, tanggal_laporan, temuan, rekomendasi
                         FROM laporanmanajerial
                         WHERE nip_pengawas=$1
                         ORDER BY tanggal_laporan DESC",
                        array($nip_pengawas)
                    );

                    if (pg_num_rows($q) == 0) {
                        echo "<tr><td colspan='5' class='empty'>Belum ada laporan.</td></tr>";
                    } else {
                        while ($r = pg_fetch_assoc($q)) {
                            echo "
                            <tr>
                                <td>{$r['nomor_laporan']}</td>
                                <td>{$r['tanggal_laporan']}</td>
                                <td>{$r['temuan']}</td>
                                <td>{$r['rekomendasi']}</td>
                                <td>
                                    <!-- Tombol Hapus -->
                                    <form method='POST' style='display:inline-block; margin-right:6px;'>
                                        <input type='hidden' name='nomor_del' value='{$r['nomor_laporan']}'>
                                        <button class='btn danger' name='del_lap'>Hapus</button>
                                    </form>

                                    <!-- Edit -->
                                    <details style='display:inline-block;'>
                                        <summary class='btn edit'>Edit</summary>
                                        <form method='POST' style='margin-top:8px;'>
                                            <input type='hidden' name='nomor_lap_edit' value='{$r['nomor_laporan']}'>
                                            <textarea name='temuan_edit' style='width:240px; height:60px;'>{$r['temuan']}</textarea>
                                            <textarea name='rekomendasi_edit' style='width:240px; height:60px;'>{$r['rekomendasi']}</textarea>
                                            <button class='btn' name='update_lap' style='margin-top:6px;'>Simpan</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>";
                        }
                    }
                    ?>
                </table>
            </div>
        </div>

        <!-- ========================= QUERY SECTION ========================== -->
        <hr class="divider">
        <h2 class="section-title">Query (Read Only)</h2>

        <!-- SEKOLAH -->
        <div class="panel">
            <h3>Sekolah Dibawah Pengawasan</h3>
            <div class="table-wrapper">
                <table class="table">
                    <tr>
                        <th>NPSN</th>
                        <th>Nama Sekolah</th>
                        <th>Alamat</th>
                        <th>Email</th>
                    </tr>
                    <?php
                    $q = pg_query_params($conn,
                        "SELECT npsn, nama_sekolah, alamat, email_sekolah
                         FROM sekolah
                         WHERE nip_pengawas=$1",
                         array($nip_pengawas)
                    );

                    if (pg_num_rows($q)==0){
                        echo "<tr><td colspan='4' class='empty'>Tidak ada sekolah.</td></tr>";
                    } else {
                        while ($s = pg_fetch_assoc($q)) {
                            echo "
                            <tr>
                                <td>{$s['npsn']}</td>
                                <td>{$s['nama_sekolah']}</td>
                                <td>{$s['alamat']}</td>
                                <td>{$s['email_sekolah']}</td>
                            </tr>";
                        }
                    }
                    ?>
                </table>
            </div>
        </div>

        <!-- RENCANA BOS -->
        <div class="panel">
            <h3>Rencana BOS</h3>
            <div class="table-wrapper">
                <table class="table">
                    <tr>
                        <th>Nomor</th>
                        <th>Tahun</th>
                        <th>Status</th>
                        <th>Total</th>
                    </tr>
                    <?php
                    $q = pg_query_params($conn,
                        "SELECT da.nomor_dokumen, ra.tahun_anggaran,
                                ra.status_persetujuan, da.total_dana
                         FROM dokumenanggaran da
                         JOIN rencanaanggaran ra 
                         ON ra.nomor_dokumen = da.nomor_dokumen
                         WHERE da.npsn IN (
                             SELECT npsn FROM sekolah WHERE nip_pengawas = $1
                         ) AND da.jenis_dokumen='Rencana'",
                         array($nip_pengawas)
                    );

                    if (pg_num_rows($q)==0){
                        echo "<tr><td colspan='4' class='empty'>Tidak ada data.</td></tr>";
                    } else {
                        while ($r = pg_fetch_assoc($q)) {
                            echo "
                            <tr>
                                <td>{$r['nomor_dokumen']}</td>
                                <td>{$r['tahun_anggaran']}</td>
                                <td>{$r['status_persetujuan']}</td>
                                <td>{$r['total_dana']}</td>
                            </tr>";
                        }
                    }
                    ?>
                </table>
            </div>
        </div>

        <!-- ITEM ANGGARAN (Expand Sekolah → Expand RAB → Tampil Item) -->
        <div class="panel">
            <h3>Item Pembelian Anggaran Dana BOS</h3>

        <?php
        $q_sekolah = pg_query_params($conn,
            "SELECT npsn, nama_sekolah 
            FROM sekolah
            WHERE nip_pengawas = $1
            ORDER BY nama_sekolah ASC",
            array($nip_pengawas)
        );

        if (pg_num_rows($q_sekolah) == 0) {
            echo "<div class='empty'>Tidak ada sekolah dibawah pengawasan Anda.</div>";
        }

        while ($s = pg_fetch_assoc($q_sekolah)) {

            echo "
            <details>
                <summary class='details-summary' style='margin-bottom:10px;'>
                    <b>{$s['nama_sekolah']}</b> — NPSN: {$s['npsn']}
                </summary>
            ";

            // Ambil semua dokumen RAB sekolah itu
            $q_rab = pg_query_params($conn,
                "SELECT da.nomor_dokumen, da.tanggal_dokumen, ra.tahun_anggaran
                FROM dokumenanggaran da
                JOIN rencanaanggaran ra ON da.nomor_dokumen = ra.nomor_dokumen
                WHERE da.npsn = $1 AND da.jenis_dokumen='Rencana'
                ORDER BY da.tanggal_dokumen DESC",
                array($s['npsn'])
            );

            if (pg_num_rows($q_rab) == 0) {
                echo "<div class='empty' style='margin-left:15px;'>Tidak ada dokumen RAB.</div>";
            }

            while ($rab = pg_fetch_assoc($q_rab)) {

                echo "
                <details style='margin-left:15px;'>
                    <summary class='details-summary'>
                        <b>{$rab['nomor_dokumen']}</b> — 
                        Tahun: {$rab['tahun_anggaran']} —
                        Tanggal: {$rab['tanggal_dokumen']}
                    </summary>
                ";

                // Ambil item anggaran berdasarkan nomor dokumen
                $q_item = pg_query_params($conn,
                    "SELECT nama_item, kategori, jumlah_rencana, satuan, keterangan
                    FROM itemanggaran
                    WHERE nomor_dokumen = $1
                    ORDER BY nama_item ASC",
                    array($rab['nomor_dokumen'])
                );

                echo "
                    <div class='table-wrapper'>
                        <table class='table'>
                            <tr>
                                <th>Nama Item</th>
                                <th>Kategori</th>
                                <th>Jumlah</th>
                                <th>Satuan</th>
                                <th>Keterangan</th>
                            </tr>
                ";

                if (pg_num_rows($q_item) == 0) {
                    echo "<tr><td colspan='5' class='empty'>Tidak ada item.</td></tr>";
                } else {
                    while ($i = pg_fetch_assoc($q_item)) {
                        echo "
                            <tr>
                                <td>{$i['nama_item']}</td>
                                <td>{$i['kategori']}</td>
                                <td>{$i['jumlah_rencana']}</td>
                                <td>{$i['satuan']}</td>
                                <td>{$i['keterangan']}</td>
                            </tr>
                        ";
                    }
                }

                echo "
                        </table>
                    </div>
                </details>
                <br>
                ";
            }

            echo "</details><br>";
        }
        ?>
        </div>


        <!-- REALISASI BOS -->
        <div class="panel">
            <h3>Realisasi BOS</h3>
            <div class="table-wrapper">
                <table class="table">
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                    </tr>
                    <?php
                    $q = pg_query_params($conn,
                        "SELECT da.nomor_dokumen, ra.tanggal_realisasi,
                                ra.jumlah_realisasi, ra.status_verifikasi
                         FROM dokumenanggaran da
                         LEFT JOIN realisasianggaran ra 
                         ON ra.nomor_dokumen = da.nomor_dokumen
                         WHERE da.npsn IN (
                             SELECT npsn FROM sekolah WHERE nip_pengawas = $1
                         ) AND da.jenis_dokumen='Realisasi'",
                         array($nip_pengawas)
                    );

                    if (pg_num_rows($q)==0){
                        echo "<tr><td colspan='4' class='empty'>Tidak ada data.</td></tr>";
                    } else {
                        while ($r = pg_fetch_assoc($q)) {
                            echo "
                            <tr>
                                <td>{$r['nomor_dokumen']}</td>
                                <td>{$r['tanggal_realisasi']}</td>
                                <td>{$r['jumlah_realisasi']}</td>
                                <td>{$r['status_verifikasi']}</td>
                            </tr>";
                        }
                    }
                    ?>
                </table>
            </div>
        </div>

        <!-- INFORMASI PUBLIK -->
        <div class="panel">
            <h3>Informasi Publik (Terkait Laporannya)</h3>
            <div class="table-wrapper">
                <table class="table">
                    <tr>
                        <th>Nomor Info</th>
                        <th>Judul</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                    </tr>
                    <?php
                    $q = pg_query_params($conn,
                        "SELECT nomor_informasi, judul, tanggal_publikasi, status
                         FROM informasipublik
                         WHERE nomor_laporan IN (
                             SELECT nomor_laporan FROM laporanmanajerial WHERE nip_pengawas=$1
                         )",
                         array($nip_pengawas)
                    );

                    if (pg_num_rows($q)==0){
                        echo "<tr><td colspan='4' class='empty'>Tidak ada informasi.</td></tr>";
                    } else {
                        while ($i = pg_fetch_assoc($q)) {
                            echo "
                            <tr>
                                <td>{$i['nomor_informasi']}</td>
                                <td>{$i['judul']}</td>
                                <td>{$i['tanggal_publikasi']}</td>
                                <td>{$i['status']}</td>
                            </tr>";
                        }
                    }
                    ?>
                </table>
            </div>
        </div>

    </div><!-- grid -->
</div><!-- container -->

</body>
</html>
