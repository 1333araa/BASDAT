<?php
session_start();
include "db.php";

// Proteksi role KCD
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'kcd') {
    header("Location: index.php");
    exit;
}

$kode_kcd = $_SESSION['kode_kcd'] ?? null;

// Ambil nama KCD
$q_kcd = pg_query_params($conn, "SELECT nama FROM kcd WHERE kode_kcd = $1", array($kode_kcd));
$data_kcd = pg_fetch_assoc($q_kcd);
$nama_kcd = $data_kcd ? $data_kcd['nama'] : "KCD";

// ---------- AJAX HANDLER (inline actions) ----------
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (!empty($_POST['ajax']) && $_POST['ajax'] === '1');

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // ---------- CREATE TINDAK LANJUT ----------
    if ($_POST['action'] === 'create_tindak') {
        $q = pg_query_params($conn,
            "INSERT INTO tindaklanjut
                (nomor_tindaklanjut, nomor_laporan, tanggal_tindak, jenis_tindakan, uraian_tindakan, status_tindakan, kode_kcd)
             VALUES ($1,$2,$3,$4,$5,$6,$7)",
            array(
                $_POST['nomor_tl'],
                $_POST['nomor_laporan'],
                $_POST['tgl_tindak'],
                $_POST['jenis_tindakan'],
                $_POST['uraian'],
                $_POST['status_t'],
                $kode_kcd
            )
        );

        echo json_encode([
            "ok" => $q ? true : false,
            "msg" => $q ? "Tindak lanjut berhasil ditambahkan" : pg_last_error($conn)
        ]);
        exit;
    }

    // ---------- CREATE INFORMASI PUBLIK ----------
    if ($_POST['action'] === 'create_info') {

        $q = pg_query_params($conn,
            "INSERT INTO informasipublik
                (nomor_informasi, nomor_laporan, kode_kcd, judul, tanggal_publikasi, deskripsi_info, status)
             VALUES ($1,$2,$3,$4,$5,$6,$7)",
            array(
                $_POST['nomor_info'],
                $_POST['nomor_laporan_info'],
                $kode_kcd,
                $_POST['judul'],
                $_POST['tgl'],
                $_POST['desk'],
                $_POST['status_info']
            )
        );

        echo json_encode([
            "ok" => $q ? true : false,
            "msg" => $q ? "Informasi publik berhasil ditambahkan" : pg_last_error($conn)
        ]);
        exit;
    }

    // ---------- EDIT INFORMASI PUBLIK ----------
    if ($_POST['action'] === 'edit_info') {

        $q = pg_query_params($conn,
            "UPDATE informasipublik
             SET judul=$1, tanggal_publikasi=$2, status=$3
             WHERE nomor_informasi=$4 AND kode_kcd=$5",
            array(
                $_POST['judul'],
                $_POST['tgl'],
                $_POST['status_info'],
                $_POST['pk'],
                $kode_kcd
            )
        );

        echo json_encode([
            "ok" => $q ? true : false,
            "msg" => $q ? "Informasi berhasil diperbarui" : pg_last_error($conn)
        ]);
        exit;
    }
    
    $action = $_POST['action'] ?? '';

    // helper: respond JSON and exit
    $resp = function($ok, $msg = '', $data = []) {
        echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $data));
        exit;
    };

    // ---------- TINDAK LANJUT ----------
    if ($action === 'add_tindak') {
        $nomor = trim($_POST['nomor_tl'] ?? '');
        $nomor_laporan = trim($_POST['nomor_laporan'] ?? '');
        $tgl = $_POST['tgl_tindak'] ?? null;
        $jenis = trim($_POST['jenis_tindakan'] ?? '');
        $uraian = trim($_POST['uraian'] ?? '');
        $status = trim($_POST['status_t'] ?? 'Belum Ditindak');

        if ($nomor === '') $nomor = uniqid('TL-');
        // simple insert
        $res = pg_query_params($conn,
            "INSERT INTO tindaklanjut (nomor_tindaklanjut, nomor_laporan, kode_kcd, tanggal_tindak, jenis_tindakan, uraian_tindakan, status_tindakan)
             VALUES ($1,$2,$3,$4,$5,$6,$7)",
             array($nomor, $nomor_laporan, $kode_kcd, $tgl ?: null, $jenis, $uraian, $status)
        );
        if ($res) $resp(true, 'Tindak lanjut berhasil ditambahkan', ['nomor' => $nomor]);
        else $resp(false, 'Gagal menambahkan tindak lanjut');
    }

    if ($action === 'edit_tindak') {
        $pk = $_POST['pk'] ?? '';
        $nomor_laporan = trim($_POST['nomor_laporan'] ?? '');
        $tgl = $_POST['tgl_tindak'] ?? null;
        $jenis = trim($_POST['jenis_tindakan'] ?? '');
        $uraian = trim($_POST['uraian'] ?? '');
        $status = trim($_POST['status_t'] ?? '');

        $res = pg_query_params($conn,
            "UPDATE tindaklanjut SET nomor_laporan=$1, tanggal_tindak=$2, jenis_tindakan=$3, uraian_tindakan=$4, status_tindakan=$5
             WHERE nomor_tindaklanjut = $6 AND kode_kcd = $7",
             array($nomor_laporan, $tgl ?: null, $jenis, $uraian, $status, $pk, $kode_kcd)
        );
        if ($res) $resp(true, 'Tindak lanjut diperbarui');
        else $resp(false, 'Gagal mengupdate tindak lanjut');
    }

    if ($action === 'delete_tindak') {
        $pk = $_POST['pk'] ?? '';
        $res = pg_query_params($conn,
            "DELETE FROM tindaklanjut WHERE nomor_tindaklanjut = $1 AND kode_kcd = $2",
            array($pk, $kode_kcd)
        );
        if ($res) $resp(true, 'Tindak lanjut dihapus');
        else $resp(false, 'Gagal menghapus tindak lanjut');
    }

    // ---------- INFORMASI PUBLIK ----------
    if ($action === 'add_info') {
        $nomor = trim($_POST['nomor_info'] ?? '');
        $nomor_laporan = trim($_POST['nomor_laporan_info'] ?? '');
        $judul = trim($_POST['judul'] ?? '');
        $tgl = $_POST['tgl'] ?? null;
        $des = trim($_POST['desk'] ?? '');
        $status = trim($_POST['status_info'] ?? 'Draft');

        if ($nomor === '') $nomor = uniqid('IP-');
        $res = pg_query_params($conn,
            "INSERT INTO informasipublik (nomor_informasi, nomor_laporan, kode_kcd, judul, tanggal_publikasi, deskripsi_info, status)
             VALUES ($1,$2,$3,$4,$5,$6,$7)",
             array($nomor, $nomor_laporan, $kode_kcd, $judul, $tgl ?: null, $des, $status)
        );
        if ($res) $resp(true, 'Informasi publik ditambahkan', ['nomor' => $nomor]);
        else $resp(false, 'Gagal menambah informasi publik');
    }

    if ($action === 'edit_info') {
        $pk = $_POST['pk'] ?? '';
        $nomor_laporan = trim($_POST['nomor_laporan_info'] ?? '');
        $judul = trim($_POST['judul'] ?? '');
        $tgl = $_POST['tgl'] ?? null;
        $des = trim($_POST['desk'] ?? '');
        $status = trim($_POST['status_info'] ?? '');

        $res = pg_query_params($conn,
            "UPDATE informasipublik SET nomor_laporan=$1, judul=$2, tanggal_publikasi=$3, deskripsi_info=$4, status=$5
             WHERE nomor_informasi = $6 AND kode_kcd = $7",
             array($nomor_laporan, $judul, $tgl ?: null, $des, $status, $pk, $kode_kcd)
        );
        if ($res) $resp(true, 'Informasi publik diperbarui');
        else $resp(false, 'Gagal mengupdate informasi publik');
    }

    if ($action === 'delete_info') {
        $pk = $_POST['pk'] ?? '';
        $res = pg_query_params($conn,
            "DELETE FROM informasipublik WHERE nomor_informasi = $1 AND kode_kcd = $2",
            array($pk, $kode_kcd)
        );
        if ($res) $resp(true, 'Informasi publik dihapus');
        else $resp(false, 'Gagal menghapus informasi publik');
    }

    // ---------- LAPORAN MANAJERIAL: edit rekomendasi ----------
    if ($action === 'edit_rekom') {
        $nomor = $_POST['nomor'] ?? '';
        $rekom = trim($_POST['rekom'] ?? '');
        if ($nomor !== '') {
            $res = pg_query_params($conn,
                "UPDATE laporanmanajerial SET rekomendasi = $1 WHERE nomor_laporan = $2 AND kode_kcd = $3",
                array($rekom, $nomor, $kode_kcd)
            );
            if ($res) $resp(true, 'Rekomendasi disimpan', ['rekom' => $rekom]);
            else $resp(false, 'Gagal menyimpan rekomendasi');
        } else $resp(false, 'Nomor laporan kosong');
    }

    // ---------- RENCANA BOS: edit status (allowed values: direvisi, disetujui, ditolak) ----------
    if ($action === 'edit_status_rencana') {
        $dok = $_POST['dok'] ?? '';
        $status = $_POST['status'] ?? '';
        $allowed = array('direvisi','disetujui','ditolak');
        if ($dok !== '' && in_array($status, $allowed, true)) {
            $res = pg_query_params($conn,
                "UPDATE rencanaanggaran SET status_persetujuan = $1 WHERE nomor_dokumen = $2",
                array($status, $dok)
            );
            if ($res) $resp(true, 'Status rencana diperbarui', ['status' => $status]);
            else $resp(false, 'Gagal memperbarui status rencana');
        } else $resp(false, 'Input tidak valid');
    }

    // ---------- REALISASI BOS: edit status_verifikasi (allowed: Diproses, Terverifikasi, Ditolak) ----------
    if ($action === 'edit_status_realisasi') {
        $dok = $_POST['dok'] ?? '';
        $status = $_POST['status'] ?? '';
        $allowed = array('Diproses','Terverifikasi','Ditolak');
        if ($dok !== '' && in_array($status, $allowed, true)) {
            $res = pg_query_params($conn,
                "UPDATE realisasianggaran SET status_verifikasi = $1 WHERE nomor_dokumen = $2",
                array($status, $dok)
            );
            if ($res) $resp(true, 'Status realisasi diperbarui', ['status' => $status]);
            else $resp(false, 'Gagal memperbarui status realisasi');
        } else $resp(false, 'Input tidak valid');
    }

    $resp(false, 'Action tidak dikenali');
}
// ---------- END AJAX HANDLER ----------

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">    
<meta charset="utf-8">
<title>Dashboard KCD — Soft Modern</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
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

/* ---------------- NAVBAR ----------------- */
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

/* ---------------- PAGE BODY ----------------- */
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
    max-width:80%;
    background:var(--card);
    padding:24px;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    margin-bottom:26px;
}

.panel h3{
    margin:0 0 14px 0;
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
}

.table td{
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    color:black; /* sesuai permintaan */
}

.details-summary{
    cursor:pointer;
    font-weight:600;
    color:var(--primary);
}

/* ---------------- BUTTON ----------------- */
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

.btn.danger{
    background:var(--danger);
    color:white;
}

input, select, textarea{
    width:100%;
    padding:10px;
    border-radius:10px;
    border:1px solid #e2e8f0;
    margin-bottom:10px;
}

.edit-area-inline{
    margin-top:8px;
}

.toast{
    position:fixed;
    right:22px;
    bottom:22px;
    background:#111;
    color:white;
    padding:12px 16px;
    border-radius:10px;
    display:none;
}
</style>
</head>
<body>
    <div class="navbar">
        <div class="nav-left">
            <div class="brand">Si Manggaran</div>
            <div class="menu">
                <a href="#">Dashboard</a>
                <a href="#">Rencana BOS</a>
                <a href="#">Realisasi</a>
                <a href="#">Laporan</a>
                <a href="#">Informasi Publik</a>
                <a href="#">Pengaturan</a>
            </div>
        </div>

        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

<div class="container">
    <div class="welcome-box">
        <div class="page-title">Dashboard KCD</div>
        <div class="sub">
            Selamat datang, <strong><?= htmlspecialchars($nama_kcd); ?>!</strong><br>
            Kode: <strong><?= htmlspecialchars($kode_kcd); ?></strong>
        </div>
    </div>

    <div class="grid">

        <!-- Tindak Lanjut (Maintain) -->
        <div class="panel" id="panel-tindak">
            <h3>Tindak Lanjut</h3>

            <details>
                <summary class="details-summary">+ Tambah Tindak Lanjut</summary>
                <form id="form-add-tindak" class="edit-area">
                    <div class="form-row"><input name="nomor_tl" placeholder="Nomor (kosongkan jika auto)"></div>
                    <div class="form-row"><input name="nomor_laporan" placeholder="Nomor Laporan" required></div>
                    <div class="form-row"><input type="date" name="tgl_tindak"></div>
                    <div class="form-row"><input name="jenis_tindakan" placeholder="Jenis"></div>
                    <div class="form-row"><textarea name="uraian" placeholder="Uraian"></textarea></div>
                    <div class="form-row"><select name="status_t"><option>Belum Ditindak</option><option>Dalam Proses</option><option>Selesai</option><option>Diverifikasi</option><option>Ditolak</option></select></div>
                    <div style="display:flex;gap:8px;"><button type="button" class="btn" onclick="addTindak()">Simpan</button></div>
                </form>
            </details>

            <div class="table-wrapper">
                <table class="table" id="table-tindak">
                    <thead><tr><th>Sekolah</th><th>Nomor TL</th><th>Nomor Laporan</th><th>Tanggal</th><th>Jenis</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php
                    $q = pg_query_params($conn, "SELECT tl.nomor_tindaklanjut, tl.nomor_laporan, tl.tanggal_tindak,
                                                        tl.jenis_tindakan, tl.status_tindakan,
                                                        s.nama_sekolah
                                                FROM tindaklanjut tl
                                                JOIN laporanmanajerial lm ON lm.nomor_laporan = tl.nomor_laporan
                                                JOIN sekolah s ON s.npsn = lm.npsn
                                                WHERE tl.kode_kcd = $1
                                                ORDER BY tl.tanggal_tindak DESC
                                                ", array($kode_kcd));
                    if (pg_num_rows($q) == 0): ?>
                        <tr><td colspan="6" class="empty">Belum ada data.</td></tr>
                    <?php else:
                        while ($r = pg_fetch_assoc($q)): ?>
                        <tr data-pk="<?php echo htmlspecialchars($r['nomor_tindaklanjut']); ?>">
                            <td class="col-sekolah"><?php echo htmlspecialchars($r['nama_sekolah']); ?></td>
                            <td class="col-nomor"><?php echo htmlspecialchars($r['nomor_tindaklanjut']); ?></td>
                            <td class="col-nomorlap"><?php echo htmlspecialchars($r['nomor_laporan']); ?></td>
                            <td class="col-tgl"><?php echo htmlspecialchars($r['tanggal_tindak']); ?></td>
                            <td class="col-jenis"><?php echo htmlspecialchars($r['jenis_tindakan']); ?></td>
                            <td class="col-status"><?php echo htmlspecialchars($r['status_tindakan']); ?></td>
                            <td>
                                <div class="inline-actions">
                                    <button class="btn ghost" onclick="startEditTindak(this)">Edit</button>
                                    <button class="btn danger" onclick="deleteTindak('<?php echo addslashes($r['nomor_tindaklanjut']); ?>')">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile;
                    endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Informasi Publik (Maintain) -->
        <div class="panel" id="panel-info">
            <h3>Informasi Publik</h3>

            <details>
                <summary class="details-summary">+ Tambah Informasi Publik</summary>
                <form id="form-add-info" class="edit-area">
                    <div class="form-row"><input name="nomor_info" placeholder="Nomor (kosongkan jika auto)"></div>
                    <div class="form-row"><input name="nomor_laporan_info" placeholder="Nomor Laporan" required></div>
                    <div class="form-row"><input name="judul" placeholder="Judul"></div>
                    <div class="form-row"><input type="date" name="tgl"></div>
                    <div class="form-row"><textarea name="desk" placeholder="Deskripsi"></textarea></div>
                    <div class="form-row"><select name="status_info"><option>Draft</option><option>Menunggu Verifikasi</option><option>Dipublikasikan</option><option>Ditarik</option><option>Diarsipkan</option></select></div>
                    <div style="display:flex;gap:8px;"><button type="button" class="btn" onclick="addInfo()">Simpan</button></div>
                </form>
            </details>

            <div class="table-wrapper">
                <table class="table" id="table-info">
                    <thead><tr><th>Sekolah</th><th>Nomor</th><th>Judul</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php
                    $q = pg_query_params($conn, "SELECT ip.nomor_informasi, ip.judul, ip.tanggal_publikasi, ip.status,
                                                        s.nama_sekolah
                                                FROM informasipublik ip
                                                JOIN laporanmanajerial lm ON lm.nomor_laporan = ip.nomor_laporan
                                                JOIN sekolah s ON s.npsn = lm.npsn
                                                WHERE ip.kode_kcd = $1
                                                ORDER BY ip.tanggal_publikasi DESC
                                                ", array($kode_kcd));
                    if (pg_num_rows($q) == 0): ?>
                        <tr><td colspan="5" class="empty">Belum ada data.</td></tr>
                    <?php else:
                        while ($r = pg_fetch_assoc($q)): ?>
                        <tr data-pk="<?php echo htmlspecialchars($r['nomor_informasi']); ?>">
                            <td class="col-sekolah"><?php echo htmlspecialchars($r['nama_sekolah']); ?></td>
                            <td class="col-nomor"><?php echo htmlspecialchars($r['nomor_informasi']); ?></td>
                            <td class="col-judul"><?php echo htmlspecialchars($r['judul']); ?></td>
                            <td class="col-tgl"><?php echo htmlspecialchars($r['tanggal_publikasi']); ?></td>
                            <td class="col-status"><?php echo htmlspecialchars($r['status']); ?></td>
                            <td>
                                <div class="inline-actions">
                                    <button class="btn ghost" onclick="startEditInfo(this)">Edit</button>
                                    <button class="btn danger" onclick="deleteInfo('<?php echo addslashes($r['nomor_informasi']); ?>')">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile;
                    endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <hr class="divider">
        <h2 class="section-title">Query (Read Only)</h2>

        <!-- Laporan Manajerial (Read + edit rekomendasi) -->
        <div class="panel" id="panel-laporan">
            <h3>Laporan Manajerial</h3>

            <div class="table-wrapper">
                <table class="table" id="table-laporan">
                    <thead>
                        <tr>
                            <th>Pengawas</th>
                            <th>Nama Sekolah</th>
                            <th>Nomor Laporan</th>
                            <th>Tanggal</th>
                            <th>Temuan</th>
                            <th>Rekomendasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $q = pg_query_params($conn, "
                        SELECT lm.nomor_laporan, lm.tanggal_laporan, lm.temuan, lm.rekomendasi,
                            s.nama_sekolah,
                            p.nama AS nama_pengawas
                        FROM laporanmanajerial lm
                        JOIN sekolah s ON s.npsn = lm.npsn
                        JOIN pengawas p ON p.nip = lm.nip_pengawas
                        WHERE lm.kode_kcd = $1
                        ORDER BY lm.tanggal_laporan DESC
                    ", array($kode_kcd));

                    if (pg_num_rows($q) == 0): ?>
                        <tr><td colspan="7" class="empty">Tidak ada laporan.</td></tr>
                    <?php else:
                        while ($r = pg_fetch_assoc($q)): ?>
                        <tr data-pk="<?php echo htmlspecialchars($r['nomor_laporan']); ?>">
                            <td><?php echo htmlspecialchars($r['nama_pengawas']); ?></td>
                            <td><?php echo htmlspecialchars($r['nama_sekolah']); ?></td>
                            <td><?php echo htmlspecialchars($r['nomor_laporan']); ?></td>
                            <td><?php echo htmlspecialchars($r['tanggal_laporan']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($r['temuan'])); ?></td>

                            <td class="col-rekom">
                                <div class="view-mode"><?php echo nl2br(htmlspecialchars($r['rekomendasi'])); ?></div>
                            </td>

                            <td>
                                <button class="btn ghost" onclick="startEditRekom(this)">Edit</button>
                            </td>
                        </tr>
                        <?php endwhile;
                    endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- Rencana BOS (Read + edit status) -->
        <div class="panel" id="panel-rencana">
            <h3>Rencana Anggaran Danan BOS</h3>

            <div class="table-wrapper">
                <table class="table" id="table-rencana">
                    <thead>
                        <tr>
                            <th>Nama Sekolah</th>
                            <th>Nomor Dokumen</th>
                            <th>Tahun</th>
                            <th>Total Dana</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $q = pg_query_params($conn, "
                        SELECT da.nomor_dokumen, ra.tahun_anggaran, ra.status_persetujuan, 
                            da.total_dana, s.nama_sekolah
                        FROM dokumenanggaran da
                        JOIN rencanaanggaran ra ON ra.nomor_dokumen = da.nomor_dokumen
                        JOIN sekolah s ON s.npsn = da.npsn
                        WHERE da.kode_kcd = $1 AND da.jenis_dokumen='Rencana'
                        ORDER BY da.tanggal_dokumen DESC
                    ", array($kode_kcd));

                    if (pg_num_rows($q) == 0): ?>
                        <tr><td colspan="6" class="empty">Tidak ada data.</td></tr>
                    <?php else:
                        while ($r = pg_fetch_assoc($q)): ?>
                        <tr data-pk="<?php echo htmlspecialchars($r['nomor_dokumen']); ?>">
                            <td><?php echo htmlspecialchars($r['nama_sekolah']); ?></td>
                            <td><?php echo htmlspecialchars($r['nomor_dokumen']); ?></td>
                            <td><?php echo htmlspecialchars($r['tahun_anggaran']); ?></td>
                            <td><?php echo htmlspecialchars($r['total_dana']); ?></td>
                            <td class="col-status">
                                <div class="view-mode"><?php echo htmlspecialchars($r['status_persetujuan']); ?></div>
                            </td>
                            <td>
                                <button class="btn ghost" onclick="startEditRencana(this)">Edit</button>
                            </td>
                        </tr>
                        <?php endwhile;
                    endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <div class="panel">
            <h3>Item Pembelian Anggaran Dana BOS</h3>
        <?php
        $q_sekolah = pg_query_params($conn,
            "SELECT npsn, nama_sekolah 
            FROM sekolah
            WHERE kode_kcd = $1
            ORDER BY nama_sekolah ASC",
            array($kode_kcd)
        );

        while ($s = pg_fetch_assoc($q_sekolah)) {

            echo "<details>
                    <summary class='details-summary' style='margin-bottom:10px;'>
                        <b>{$s['nama_sekolah']}</b> — NPSN: {$s['npsn']}
                    </summary>";

            // ambil semua RAB dokumen untuk sekolah ini
            $q_rab = pg_query_params($conn,
                "SELECT da.nomor_dokumen, da.tanggal_dokumen, ra.tahun_anggaran
                FROM dokumenanggaran da
                JOIN rencanaanggaran ra ON da.nomor_dokumen = ra.nomor_dokumen
                WHERE da.npsn = $1
                ORDER BY da.tanggal_dokumen DESC",
                array($s['npsn'])
            );

            while ($rab = pg_fetch_assoc($q_rab)) {

                echo "<details style='margin-left:15px;'>
                        <summary class='details-summary'>
                            <b>{$rab['nomor_dokumen']}</b> —
                            Tahun: {$rab['tahun_anggaran']} —
                            Tanggal: {$rab['tanggal_dokumen']}
                        </summary>";

                // ambil items
                $q_item = pg_query_params($conn,
                    "SELECT nama_item, kategori, jumlah_rencana, satuan, keterangan
                    FROM itemanggaran
                    WHERE nomor_dokumen = $1
                    ORDER BY nama_item ASC",
                    array($rab['nomor_dokumen'])
                );

                echo "<div class='table-wrapper'>
                        <table class='table'>
                            <tr>
                                <th>Nama Item</th>
                                <th>Kategori</th>
                                <th>Jumlah</th>
                                <th>Satuan</th>
                                <th>Keterangan</th>
                            </tr>";

                if (pg_num_rows($q_item) == 0) {
                    echo "<tr><td colspan='5' class='empty'>Tidak ada item.</td></tr>";
                } else {
                    while ($i = pg_fetch_assoc($q_item)) {
                        echo "<tr>
                                <td>{$i['nama_item']}</td>
                                <td>{$i['kategori']}</td>
                                <td>{$i['jumlah_rencana']}</td>
                                <td>{$i['satuan']}</td>
                                <td>{$i['keterangan']}</td>
                            </tr>";
                    }
                }

                echo "</table></div></details><br>";
            }

            echo "</details><br>";
        }
        ?>
        </div>

        <!-- Realisasi BOS (Read + edit status_verifikasi) -->
        <div class="panel" id="panel-realisasi">
            <h3>Realisasi Anggaran Danan BOS</h3>

            <div class="table-wrapper">
                <table class="table" id="table-realisasi">
                    <thead>
                        <tr>
                            <th>Nama Sekolah</th>
                            <th>Nomor Dokumen</th>
                            <th>Tanggal Realisasi</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $q = pg_query_params($conn, "
                        SELECT da.nomor_dokumen, r.tanggal_realisasi, r.jumlah_realisasi, 
                            r.status_verifikasi, s.nama_sekolah
                        FROM dokumenanggaran da
                        JOIN sekolah s ON s.npsn = da.npsn
                        LEFT JOIN realisasianggaran r ON r.nomor_dokumen = da.nomor_dokumen
                        WHERE da.kode_kcd = $1 AND da.jenis_dokumen='Realisasi'
                        ORDER BY da.tanggal_dokumen DESC
                    ", array($kode_kcd));

                    if (pg_num_rows($q) == 0): ?>
                        <tr><td colspan="6" class="empty">Tidak ada realisasi.</td></tr>
                    <?php else:
                        while ($r = pg_fetch_assoc($q)): ?>
                        <tr data-pk="<?php echo htmlspecialchars($r['nomor_dokumen']); ?>">
                            <td><?php echo htmlspecialchars($r['nama_sekolah']); ?></td>
                            <td><?php echo htmlspecialchars($r['nomor_dokumen']); ?></td>
                            <td><?php echo htmlspecialchars($r['tanggal_realisasi']); ?></td>
                            <td><?php echo htmlspecialchars($r['jumlah_realisasi']); ?></td>

                            <td class="col-status">
                                <div class="view-mode"><?php echo htmlspecialchars($r['status_verifikasi']); ?></div>
                            </td>

                            <td>
                                <button class="btn ghost" onclick="startEditRealisasi(this)">Edit</button>
                            </td>
                        </tr>
                        <?php endwhile;
                    endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


    </div> <!-- grid -->
</div> <!-- container -->

<div id="toast" class="toast"></div>

<script>
// Helper: show toast
function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.style.display = 'block';
    t.style.background = ok ? '#111' : '#b91c1c';
    t.textContent = msg;
    setTimeout(() => t.style.display = 'none', 2500);
}

// ---------- AJAX helpers ----------
async function postJSON(data) {
    const form = new FormData();
    form.append('ajax', '1');
    for (const k in data) form.append(k, data[k]);
    const resp = await fetch(location.href, { method: 'POST', body: form });
    return resp.json();
}

// ---------- TINDAK LANJUT CRUD ----------
async function addTindak(){
    const f = document.getElementById('form-add-tindak');
    const fd = new FormData(f);

    const res = await postJSON({
        action: 'add_tindak',
        nomor_tl: fd.get("nomor_tl"),
        nomor_laporan: fd.get("nomor_laporan"),
        tgl_tindak: fd.get("tgl_tindak"),
        jenis_tindakan: fd.get("jenis_tindakan"),
        uraian: fd.get("uraian"),
        status_t: fd.get("status_t")
    });

    if (res.ok) {
        showToast(res.msg);
        location.reload();
    } else showToast(res.msg, false);
}

function startEditTindak(btn){
    const tr = btn.closest('tr');
    const pk = tr.dataset.pk;
    // prevent multiple edit areas
    if (tr.querySelector('.edit-area-inline')) return;
    const nomor = tr.querySelector('.col-nomor').textContent.trim();
    const nomor_laporan = tr.querySelector('.col-nomorlap').textContent.trim();
    const tgl = tr.querySelector('.col-tgl').textContent.trim();
    const jenis = tr.querySelector('.col-jenis').textContent.trim();
    const status = tr.querySelector('.col-status').textContent.trim();

    const editor = document.createElement('div');
    editor.className = 'edit-area-inline';
    editor.innerHTML = `
        <div class="form-row"><input type="text" name="nomor_laporan" value="${escapeHtml(nomor_laporan)}"></div>
        <div class="form-row"><input type="date" name="tgl_tindak" value="${tgl}"></div>
        <div class="form-row"><input type="text" name="jenis_tindakan" value="${escapeHtml(jenis)}"></div>
        <div class="form-row"><select name="status_t">
            <option ${status==='Belum Ditindak'?'selected':''}>Belum Ditindak</option>
            <option ${status==='Dalam Proses'?'selected':''}>Dalam Proses</option>
            <option ${status==='Selesai'?'selected':''}>Selesai</option>
            <option ${status==='Diverifikasi'?'selected':''}>Diverifikasi</option>
            <option ${status==='Ditolak'?'selected':''}>Ditolak</option>
        </select></div>
        <div style="display:flex;gap:6px;margin-top:6px;">
            <button class="btn" type="button">Simpan</button>
            <button class="btn ghost" type="button">Batal</button>
        </div>
    `;
    const actionsCell = tr.querySelector('td:last-child');
    // temporarily store old content
    actionsCell._old = actionsCell.innerHTML;
    actionsCell.innerHTML = '';
    actionsCell.appendChild(editor);

    const saveBtn = editor.querySelector('.btn');
    const cancelBtn = editor.querySelector('.btn.ghost');

    saveBtn.addEventListener('click', async ()=>{
        const nomLap = editor.querySelector('input[name=nomor_laporan]').value;
        const tglVal = editor.querySelector('input[name=tgl_tindak]').value;
        const jenisVal = editor.querySelector('input[name=jenis_tindakan]').value;
        const statVal = editor.querySelector('select[name=status_t]').value;

        const res = await postJSON({
            action: 'edit_tindak',
            pk: pk,
            nomor_laporan: nomLap,
            tgl_tindak: tglVal,
            jenis_tindakan: jenisVal,
            status_t: statVal
        });
        if (res.ok) {
            showToast(res.msg);
            // update row cells
            tr.querySelector('.col-nomorlap').textContent = nomLap;
            tr.querySelector('.col-tgl').textContent = tglVal;
            tr.querySelector('.col-jenis').textContent = jenisVal;
            tr.querySelector('.col-status').textContent = statVal;
            actionsCell.innerHTML = actionsCell._old;
        } else showToast(res.msg, false);
    });

    cancelBtn.addEventListener('click', ()=>{
        actionsCell.innerHTML = actionsCell._old;
    });
}

async function deleteTindak(pk){
    if (!confirm('Hapus tindak lanjut ini?')) return;
    const res = await postJSON({ action: 'delete_tindak', pk: pk });
    if (res.ok) {
        showToast(res.msg);
        // remove row
        const tr = document.querySelector(`#table-tindak tr[data-pk="${cssEscape(pk)}"]`);
        if (tr) tr.remove();
    } else showToast(res.msg, false);
}

// ---------- INFORMASI PUBLIK CRUD ----------
async function addInfo(){
    const f = document.getElementById('form-add-info');
    const fd = new FormData(f);

    const res = await postJSON({
        action: 'add_info',
        nomor_info: fd.get("nomor_info"),
        nomor_laporan_info: fd.get("nomor_laporan_info"),
        judul: fd.get("judul"),
        tgl: fd.get("tgl"),
        desk: fd.get("desk"),
        status_info: fd.get("status_info")
    });

    if (res.ok) {
        showToast(res.msg);
        location.reload();
    } else showToast(res.msg, false);
}

function startEditInfo(btn){
    const tr = btn.closest('tr');
    const pk = tr.dataset.pk;
    if (tr.querySelector('.edit-area-inline')) return;

    const judul = tr.querySelector('.col-judul').textContent.trim();
    const tgl = tr.querySelector('.col-tgl').textContent.trim();
    const status = tr.querySelector('.col-status').textContent.trim();

    const editor = document.createElement('div');
    editor.className = 'edit-area-inline';
    editor.innerHTML = `
        <div class="form-row"><input name="judul" value="${escapeHtml(judul)}"></div>
        <div class="form-row"><input type="date" name="tgl" value="${tgl}"></div>
        <div class="form-row"><select name="status_info">
            <option ${status==='Draft'?'selected':''}>Draft</option>
            <option ${status==='Menunggu Verifikasi'?'selected':''}>Menunggu Verifikasi</option>
            <option ${status==='Dipublikasikan'?'selected':''}>Dipublikasikan</option>
            <option ${status==='Ditarik'?'selected':''}>Ditarik</option>
            <option ${status==='Diarsipkan'?'selected':''}>Diarsipkan</option>
        </select></div>
        <div style="display:flex;gap:6px;margin-top:6px;">
            <button class="btn" type="button">Simpan</button>
            <button class="btn ghost" type="button">Batal</button>
        </div>
    `;
    const actionsCell = tr.querySelector('td:last-child');
    actionsCell._old = actionsCell.innerHTML;
    actionsCell.innerHTML = '';
    actionsCell.appendChild(editor);

    const saveBtn = editor.querySelector('.btn');
    const cancelBtn = editor.querySelector('.btn.ghost');

    saveBtn.addEventListener('click', async ()=>{
        const jud = editor.querySelector('input[name=judul]').value;
        const tglVal = editor.querySelector('input[name=tgl]').value;
        const statVal = editor.querySelector('select[name=status_info]').value;

        const res = await postJSON({ 
        action: 'edit_info',
        pk: pk,
        judul: jud,
        tgl: tglVal,
        status_info: statVal
        });


        if (res.ok) {
            showToast(res.msg);
            tr.querySelector('.col-judul').textContent = jud;
            tr.querySelector('.col-tgl').textContent = tglVal;
            tr.querySelector('.col-status').textContent = statVal;
            actionsCell.innerHTML = actionsCell._old;
        } else showToast(res.msg, false);
    });

    cancelBtn.addEventListener('click', ()=> actionsCell.innerHTML = actionsCell._old);
}


async function deleteInfo(pk){
    if (!confirm('Hapus informasi publik ini?')) return;
    const res = await postJSON({ action: 'delete_info', pk: pk });
    if (res.ok) {
        showToast(res.msg);
        const tr = document.querySelector(`#table-info tr[data-pk="${cssEscape(pk)}"]`);
        if (tr) tr.remove();
    } else showToast(res.msg, false);
}

// ---------- LAPORAN: edit rekomendasi (style 2) ----------
function startEditRekom(btn){
    const tr = btn.closest('tr');
    const td = tr.querySelector('.col-rekom'); // <-- FIX
    const pk = tr.dataset.pk;

    if (td.querySelector('.edit-area-inline')) return;

    const view = td.querySelector('.view-mode');
    const current = view.innerText.trim();

    const editor = document.createElement('div');
    editor.className = 'edit-area-inline';
    editor.innerHTML = `
        <textarea name="rekom" style="width:100%;min-height:80px;">${escapeHtml(current)}</textarea>
        <div style="display:flex;gap:6px;margin-top:6px;">
            <button class="btn">Simpan</button>
            <button class="btn ghost">Batal</button>
        </div>
    `;

    view.style.display = 'none';
    td.appendChild(editor);

    editor.querySelector('.btn').addEventListener('click', async ()=>{
        const rekom = editor.querySelector('textarea[name=rekom]').value;

        const res = await postJSON({
            action: 'edit_rekom',
            nomor: pk,
            rekom: rekom
        });

        if (res.ok) {
            showToast(res.msg);
            view.innerHTML = nl2br(escapeHtml(rekom));
            editor.remove();
            view.style.display = '';
        } else showToast(res.msg, false);
    });

    editor.querySelector('.btn.ghost').addEventListener('click', ()=>{
        editor.remove();
        view.style.display = '';
    });
}

// ---------- RENCANA: edit status ----------
function startEditRencana(btn){
    const tr = btn.closest('tr');
    const td = tr.querySelector('.col-status'); // FIX
    const pk = tr.dataset.pk;

    if (td.querySelector('.edit-area-inline')) return;

    const view = td.querySelector('.view-mode');
    const current = view.innerText.trim();

    const editor = document.createElement('div');
    editor.className = 'edit-area-inline';
    editor.innerHTML = `
        <select name="status_rencana">
            <option value="direvisi">direvisi</option>
            <option value="disetujui">disetujui</option>
            <option value="ditolak">ditolak</option>
        </select>
        <div style="display:flex;gap:6px;margin-top:6px;">
            <button class="btn">Simpan</button>
            <button class="btn ghost">Batal</button>
        </div>
    `;

    editor.querySelector('select').value = current;
    view.style.display = 'none';
    td.appendChild(editor);

    editor.querySelector('.btn').addEventListener('click', async ()=>{
        const val = editor.querySelector('select').value;

        const res = await postJSON({
            action: 'edit_status_rencana',
            dok: pk,
            status: val
        });

        if (res.ok) {
            showToast(res.msg);
            view.textContent = val;
            editor.remove();
            view.style.display = '';
        } else showToast(res.msg, false);
    });

    editor.querySelector('.btn.ghost').addEventListener('click', ()=>{
        editor.remove();
        view.style.display = '';
    });
}

// ---------- REALISASI: edit status_verifikasi ----------
function startEditRealisasi(btn){
    const tr = btn.closest('tr');
    const td = tr.querySelector('.col-status'); // FIX
    const pk = tr.dataset.pk;

    if (td.querySelector('.edit-area-inline')) return;

    const view = td.querySelector('.view-mode');
    const current = view.innerText.trim();

    const editor = document.createElement('div');
    editor.className = 'edit-area-inline';
    editor.innerHTML = `
        <select name="status_realisasi">
            <option value="Diproses">Diproses</option>
            <option value="Terverifikasi">Terverifikasi</option>
            <option value="Ditolak">Ditolak</option>
        </select>
        <div style="display:flex;gap:6px;margin-top:6px;">
            <button class="btn">Simpan</button>
            <button class="btn ghost">Batal</button>
        </div>
    `;

    editor.querySelector('select').value = current;
    view.style.display = 'none';
    td.appendChild(editor);

    editor.querySelector('.btn').addEventListener('click', async ()=>{
        const val = editor.querySelector('select').value;

        const res = await postJSON({
            action: 'edit_status_realisasi',
            dok: pk,
            status: val
        });

        if (res.ok) {
            showToast(res.msg);
            view.textContent = val;
            editor.remove();
            view.style.display = '';
        } else showToast(res.msg, false);
    });

    editor.querySelector('.btn.ghost').addEventListener('click', ()=>{
        editor.remove();
        view.style.display = '';
    });
}

// ---------- small helpers ----------
function escapeHtml(s){ if(!s) return ''; return s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;'); }
function nl2br(s){ return s.replace(/\n/g,'<br>'); }
// CSS selector safe escape for attribute lookup
function cssEscape(s){ return s.replaceAll('"','\\"').replaceAll("'", "\\'"); }
</script>
</body>
</html>
