<?php
// sekolah_dahsboard.php
session_start();
include "db.php";

// PROTEKSI
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sekolah') {
    header("Location: index.php");
    exit;
}

$npsn = $_SESSION['npsn'] ?? null;
$kode_kcd_session = $_SESSION['kode_kcd'] ?? null;

// Ambil data sekolah + pengawas + kcd untuk header
$sekolah = null;
$pengawas = null;
$kcd = null;
if ($npsn) {
    $q = pg_query_params($conn,
        "SELECT s.npsn, s.nama_sekolah, s.kode_kcd, s.nip_pengawas,
                p.nama AS nama_pengawas, k.nama AS nama_kcd
         FROM sekolah s
         LEFT JOIN pengawas p ON p.nip = s.nip_pengawas
         LEFT JOIN kcd k ON k.kode_kcd = s.kode_kcd
         WHERE s.npsn = $1",
         array($npsn)
    );
    if ($r = pg_fetch_assoc($q)) {
        $sekolah = $r;
        $pengawas = ['nip' => $r['nip_pengawas'], 'nama' => $r['nama_pengawas']];
        $kcd = ['kode_kcd' => $r['kode_kcd'], 'nama' => $r['nama_kcd']];
    }
}

// AJAX handler
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (!empty($_POST['ajax']) && $_POST['ajax'] === '1');

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';
    $resp = function($ok, $msg = '', $data = []) {
        echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $data));
        exit;
    };

    /* ---------------- RENCANA BOS ---------------- */
    if ($action === 'add_rencana') {
        $nomor = trim($_POST['nomor_rencana'] ?? '');
        if ($nomor === '') {
            // auto generate: RAB/YEAR/NPSN/XXX
            $nomor = "RAB/" . date('Y') . "/" . $npsn . "/" . strval(rand(100,999));
        }
        $tanggal = $_POST['tanggal_dokumen'] ?? date('Y-m-d');
        $total = floatval($_POST['total_dana'] ?? 0);
        $tahun = intval($_POST['tahun_anggaran'] ?? date('Y'));

        // Insert dokumenanggaran (jenis = Rencana)
        $ins1 = pg_query_params($conn,
            "INSERT INTO dokumenanggaran (nomor_dokumen, npsn, kode_kcd, tanggal_dokumen, total_dana, jenis_dokumen, status_dokumen)
             VALUES ($1,$2,$3,$4,$5,'Rencana','draft')",
             array($nomor, $npsn, $sekolah['kode_kcd'] ?? $kode_kcd_session, $tanggal, $total)
        );
        if (!$ins1) $resp(false, 'Gagal menyimpan dokumen rencana (dokumenanggaran)');

        $ins2 = pg_query_params($conn,
            "INSERT INTO rencanaanggaran (nomor_dokumen, tahun_anggaran, tanggal_pengajuan, status_persetujuan)
             VALUES ($1,$2,$3,'diajukan')",
             array($nomor, $tahun, $tanggal)
        );
        if ($ins2) $resp(true, 'Rencana BOS ditambahkan', ['nomor'=>$nomor]);
        else $resp(false, 'Gagal menyimpan rencana BOS (rencanaanggaran)');
    }

    if ($action === 'edit_rencana') {
        $nomor = $_POST['nomor'] ?? '';
        $tahun = intval($_POST['tahun'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? null;
        $total = floatval($_POST['total_dana'] ?? 0);

        // update rencanaanggaran
        pg_query_params($conn,
            "UPDATE rencanaanggaran 
            SET tahun_anggaran = $1, tanggal_pengajuan = $2 
            WHERE nomor_dokumen = $3",
            array($tahun, $tanggal ?: null, $nomor)
        );

        // update dokumenanggaran
        $res = pg_query_params($conn,
            "UPDATE dokumenanggaran 
            SET total_dana = $1
            WHERE nomor_dokumen = $2",
            array($total, $nomor)
        );

        if ($res) $resp(true, 'Rencana diperbarui');
        else $resp(false, 'Gagal memperbarui rencana');
    }

    if ($action === 'delete_rencana') {
        $nomor = $_POST['nomor'] ?? '';
        $res = pg_query_params($conn,
            "DELETE FROM dokumenanggaran WHERE nomor_dokumen = $1 AND npsn = $2",
            array($nomor, $npsn)
        );
        if ($res) $resp(true, 'Rencana dihapus');
        else $resp(false, 'Gagal menghapus rencana');
    }

    /* ---------------- REALISASI BOS ---------------- */
    if ($action === 'add_realisasi') {
        $nomor_realisasi = trim($_POST['nomor_realisasi'] ?? '');
        if ($nomor_realisasi === '') {
            $nomor_realisasi = "REAL/" . date('Y') . "/" . $npsn . "/" . strval(rand(100,999));
        }

        $nomor_rab = trim($_POST['nomor_rab'] ?? '');
        if ($nomor_rab === '') $resp(false, 'Nomor RAB wajib dipilih.');

        // Pastikan RAB ada & milik sekolah
        $chk = pg_query_params($conn,
            "SELECT kode_kcd FROM dokumenanggaran WHERE nomor_dokumen=$1 AND npsn=$2 AND jenis_dokumen='Rencana'",
            array($nomor_rab, $npsn)
        );
        if (pg_num_rows($chk) == 0) $resp(false, 'Nomor RAB tidak valid.');
        $rkcd = pg_fetch_assoc($chk)['kode_kcd'];

        $tanggal_real = $_POST['tanggal_realisasi'] ?? date('Y-m-d');
        $jumlah = floatval($_POST['jumlah_realisasi'] ?? 0);
        $bukti = trim($_POST['bukti_transaksi'] ?? '');

        // Insert dokumenanggaran jenis Realisasi
        $ins1 = pg_query_params($conn,
            "INSERT INTO dokumenanggaran (nomor_dokumen, npsn, kode_kcd, tanggal_dokumen, total_dana, jenis_dokumen, status_dokumen)
            VALUES ($1,$2,$3,$4,$5,'Realisasi','diproses')",
            array($nomor_realisasi, $npsn, $rkcd, $tanggal_real, $jumlah)
        );
        if (!$ins1) $resp(false, 'Gagal membuat dokumen realisasi.');

        // Insert realisasianggaran
        $ins2 = pg_query_params($conn,
            "INSERT INTO realisasianggaran (nomor_dokumen, nomor_rab, tanggal_realisasi, jumlah_realisasi, bukti_transaksi, status_verifikasi)
            VALUES ($1,$2,$3,$4,$5,'Diajukan')",
            array($nomor_realisasi, $nomor_rab, $tanggal_real, $jumlah, $bukti)
        );

        if ($ins2) $resp(true, 'Realisasi BOS berhasil disimpan.', ['nomor'=>$nomor_realisasi]);
        else $resp(false, 'Gagal menyimpan detail realisasi.');
    }


    if ($action === 'edit_realisasi') {
        $nomor = $_POST['nomor'] ?? '';
        $tanggal = $_POST['tanggal_realisasi'] ?? null;
        $jumlah = floatval($_POST['jumlah'] ?? 0);
        $bukti = $_POST['bukti'] ?? '';
        $res = pg_query_params($conn,
            "UPDATE realisasianggaran SET tanggal_realisasi=$1, jumlah_realisasi=$2, bukti_transaksi=$3 WHERE nomor_dokumen = $4",
            array($tanggal ?: null, $jumlah, $bukti, $nomor)
        );
        if ($res) $resp(true, 'Realisasi diperbarui');
        else $resp(false, 'Gagal memperbarui realisasi');
    }

    if ($action === 'delete_realisasi') {
        $nomor = $_POST['nomor'] ?? '';
        // delete dokumenanggaran akan cascades ke realisasi jika FK ON DELETE CASCADE
        $res = pg_query_params($conn,
            "DELETE FROM dokumenanggaran WHERE nomor_dokumen = $1 AND npsn = $2",
            array($nomor, $npsn)
        );
        if ($res) $resp(true, 'Realisasi dihapus');
        else $resp(false, 'Gagal menghapus realisasi');
    }

    /* ---------------- ITEM ANGGARAN ---------------- */
    if ($action === 'add_item') {
        $nomor = $_POST['nomor_dokumen'] ?? '';
        $nama_item = trim($_POST['nama_item'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $jumlah = floatval($_POST['jumlah_rencana'] ?? 0);
        $satuan = trim($_POST['satuan'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($nomor === '' || $nama_item === '') $resp(false, 'Nomor dokumen & nama item wajib');

        // ensure the nomor_dokumen exists and is a Rencana (so item belongs to rencana)
        $chk = pg_query_params($conn, "SELECT 1 FROM dokumenanggaran WHERE nomor_dokumen = $1 AND jenis_dokumen='Rencana' AND npsn = $2", array($nomor, $npsn));
        if (pg_num_rows($chk) == 0) $resp(false, 'Nomor dokumen Rencana tidak ditemukan atau bukan milik sekolah Anda');

        $res = pg_query_params($conn,
            "INSERT INTO itemanggaran (nomor_dokumen, nama_item, kategori, jumlah_rencana, satuan, keterangan)
             VALUES ($1,$2,$3,$4,$5,$6)",
             array($nomor, $nama_item, $kategori, $jumlah, $satuan, $keterangan)
        );
        if ($res) $resp(true, 'Item anggaran ditambahkan');
        else $resp(false, 'Gagal menambahkan item');
    }

    if ($action === 'edit_item') {
        $id = intval($_POST['id'] ?? 0);
        $nama_item = trim($_POST['nama_item'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $jumlah = floatval($_POST['jumlah_rencana'] ?? 0);
        $satuan = trim($_POST['satuan'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        $res = pg_query_params($conn,
            "UPDATE itemanggaran SET nama_item=$1, kategori=$2, jumlah_rencana=$3, satuan=$4, keterangan=$5 WHERE kode_item = $6",
            array($nama_item, $kategori, $jumlah, $satuan, $keterangan, $id)
        );
        if ($res) $resp(true, 'Item diperbarui');
        else $resp(false, 'Gagal mengupdate item');
    }

    if ($action === 'delete_item') {
        $id = intval($_POST['id'] ?? 0);
        $res = pg_query_params($conn,
            "DELETE FROM itemanggaran WHERE kode_item = $1",
            array($id)
        );
        if ($res) $resp(true, 'Item dihapus');
        else $resp(false, 'Gagal menghapus item');
    }

    $resp(false, 'Action tidak dikenali');
}
// end AJAX handler
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Dashboard Sekolah — Sistem BOS</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
/* ================================
   ROOT VARIABLES
================================== */
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

/* ================================
   GLOBAL RESET & BASE
================================== */
*{
    box-sizing: border-box;
}

body{
    margin:0;
    font-family:'Inter', sans-serif;
    background:var(--bg);
    color:var(--text);
}

/* ================================
   NAVBAR
================================== */
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

/* ================================
   CONTAINER
================================== */
.container{
    max-width:1250px;
    margin:35px auto;
    padding:0 20px;
}

.page-title{
    font-size:28px;
    font-weight:700;
    margin-bottom:4px;
}

/* ================================
   WELCOME BOX
================================== */
.welcome-box{
    background:var(--card);
    padding:24px 30px;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    margin-bottom:28px;
}

.welcome-box .sub{
    color:var(--text-light);
    margin-top:6px;
    line-height:1.6;
}

/* ================================
   GRID LAYOUT
================================== */
.grid{
    display:flex;
    flex-direction:column;
    gap:28px;
}

/* optional 2 columns on desktop */
/*
@media(min-width:1000px){
    .grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:28px;
    }
}
*/

/* ================================
   PANEL / CARD
================================== */
.panel{
    background:var(--card);
    padding:26px;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
}

.panel h3{
    margin:0 0 16px 0;
    font-size:18px;
    font-weight:600;
    color:var(--primary-dark);
}

/* ================================
   TABLE STYLE
================================== */
.table-wrapper{
    overflow-x:auto;
}

.table{
    width:100%;
    min-width:700px;
    border-collapse:collapse;
}

.table th{
    background:#f1f2ff;
    padding:12px;
    font-size:14px;
    color:var(--text);
    text-align:left;
    border-bottom:2px solid #e0e7ff;
}

.table td{
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    color:#000; /* sesuai permintaan */
}

.empty{
    text-align:center;
    padding:14px;
    color:var(--text-light);
}

/* ================================
   BUTTONS
================================== */
.btn{
    background:var(--primary);
    border:none;
    padding:9px 14px;
    border-radius:10px;
    color:white;
    font-weight:600;
    cursor:pointer;
    font-size:14px;
    transition:0.2s;
}

.btn:hover{
    background:var(--primary-dark);
}

.btn.ghost{
    background:#eef2ff;
    color:var(--primary);
}

.btn.danger{
    background:var(--danger);
    color:white;
}

.inline-actions{
    display:flex;
    gap:8px;
}

/* ================================
   FORM INPUTS
================================== */
input, select, textarea{
    width:100%;
    padding:10px;
    border-radius:10px;
    border:1px solid #e2e8f0;
    margin-bottom:10px;
    font-size:15px;
    background:#fafafa;
}

input:focus, select:focus, textarea:focus{
    outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(99,102,241,0.25);
    background:white;
}

/* ================================
   DETAILS / SUMMARY
================================== */
.details-summary{
    cursor:pointer;
    font-weight:600;
    color:var(--primary);
    padding:10px 0;
    font-size:15px;
    transition:0.2s;
}

details[open] > summary.details-summary{
    color:var(--primary-dark);
}

/* spacing inside details */
details .edit-area{
    margin-top:14px;
}

/* ================================
   EDITOR INLINE (for editing row)
================================== */
.edit-area-inline{
    margin-top:10px;
    background:#f8f9fe;
    padding:14px;
    border-radius:12px;
    border:1px solid #e5e7eb;
}

/* ================================
   TOAST MESSAGE
================================== */
.toast{
    position:fixed;
    right:22px;
    bottom:22px;
    background:#111;
    color:white;
    padding:12px 16px;
    border-radius:10px;
    display:none;
    box-shadow:var(--shadow);
}

/* ================================
   SECTION TITLE
================================== */
.section-title{
    font-size:22px;
    font-weight:700;
    color:var(--primary-dark);
    margin:40px 0 10px;
}

/* ================================
   SMALL UTILITY CLASSES
================================== */
.divider{
    margin:40px 0 20px;
    border:none;
    border-top:1px solid #e5e7eb;
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
        <div class="page-title">Dashboard Sekolah</div>

        <?php if ($sekolah): ?>
        <div class="sub">
            Selamat datang, <strong><?php echo htmlspecialchars($sekolah['nama_sekolah']); ?></strong><br>
            NPSN: <strong><?php echo htmlspecialchars($sekolah['npsn']); ?></strong>

            <div style="margin-top:14px; font-size:15px; color:var(--text-light);">
                Dibawah pengawasan:
            </div>

            <div style="margin-top:4px; font-size:15px;">
                <?php if ($kcd): ?>
                    <b><?php echo htmlspecialchars($kcd['nama']); ?></b> — <?php echo htmlspecialchars($kcd['kode_kcd']); ?><br>
                <?php else: ?>
                    <b>KCD</b> — kode
                <?php endif; ?>

                <?php if ($pengawas && $pengawas['nama']): ?>
                    <b><?php echo htmlspecialchars($pengawas['nama']); ?></b>
                    — <?php echo htmlspecialchars($pengawas['nip']); ?>
                <?php else: ?>
                    Pengawas — NIP
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="sub">
            <strong>Sekolah</strong><br>
            NPSN: <strong><?php echo htmlspecialchars($npsn); ?></strong>
        </div>
        <?php endif; ?>
    </div>


    <div class="grid">

        <!-- Rencana BOS -->
        <div class="panel">
            <h3>Rencana Anggaran Danan BOS</h3>

            <details>
                <summary class="details-summary">+ Tambah Data Rencana Anggaran</summary>
                <form id="form-add-rencana" class="edit-area" onsubmit="return false;">
                    <div class="form-row"><input name="nomor_rencana" placeholder="Nomor Dokumen (kosongkan untuk auto)"></div>
                    <div class="form-row"><input type="date" name="tanggal_dokumen"></div>
                    <div class="form-row"><input type="number" name="tahun_anggaran" placeholder="Tahun Anggaran"></div>
                    <div class="form-row"><input type="number" name="total_dana" placeholder="Total Dana"></div>
                    <div style="display:flex;gap:8px"><button class="btn" onclick="addRencana()">Simpan</button></div>
                </form>
            </details>

            <div class="table-wrapper">
                <table class="table" id="table-rencana">
                    <thead><tr><th>Nomor Dokumen</th><th>Tanggal</th><th>Tahun</th><th>Status</th><th>Total Dana</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php
                    $q = pg_query_params($conn,
                        "SELECT da.nomor_dokumen, da.tanggal_dokumen, ra.tahun_anggaran, ra.status_persetujuan, da.total_dana
                         FROM dokumenanggaran da
                         JOIN rencanaanggaran ra ON ra.nomor_dokumen = da.nomor_dokumen
                         WHERE da.npsn = $1 AND da.jenis_dokumen='Rencana'
                         ORDER BY da.tanggal_dokumen DESC",
                        array($npsn)
                    );
                    if (pg_num_rows($q) == 0): ?>
                        <tr><td colspan="6" class="empty">Belum ada rencana.</td></tr>
                    <?php else:
                        while ($r = pg_fetch_assoc($q)): ?>
                        <tr data-pk="<?php echo htmlspecialchars($r['nomor_dokumen']); ?>">
                            <td class="col-nomor"><?php echo htmlspecialchars($r['nomor_dokumen']); ?></td>
                            <td class="col-tgl"><?php echo htmlspecialchars($r['tanggal_dokumen']); ?></td>
                            <td class="col-tahun"><?php echo htmlspecialchars($r['tahun_anggaran']); ?></td>
                            <td class="col-status"><?php echo htmlspecialchars($r['status_persetujuan']); ?></td>
                            <td class="col-total"><?php echo htmlspecialchars($r['total_dana']); ?></td>
                            <td>
                                <div class="inline-actions">
                                    <button class="btn ghost" onclick="startEditRencana(this)">Edit</button>
                                    <button class="btn danger" onclick="deleteRencana('<?php echo addslashes($r['nomor_dokumen']); ?>')">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile;
                    endif;
                    ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Realisasi BOS -->
        <div class="panel">
            <h3>Realisasi Anggaran Danan BOS</h3>

            <details>
                <summary class="details-summary">+ Tambah Data Realisasi Anggaran</summary>
                <form id="form-add-realisasi" class="edit-area" onsubmit="return false;">
                    <div class="form-row"><input name="nomor_realisasi" placeholder="Nomor Realisasi (kosongkan auto)"></div>
                    <div class="form-row">
                        <label>Pilih Nomor RAB (Dokumen Rencana)</label>
                        <select name="nomor_rab" id="sel-nomor-rab" required>
                            <option value="">-- pilih RAB --</option>
                            <?php
                            $q_rab = pg_query_params($conn,
                                "SELECT da.nomor_dokumen, ra.tahun_anggaran FROM dokumenanggaran da JOIN rencanaanggaran ra ON ra.nomor_dokumen=da.nomor_dokumen WHERE da.npsn=$1 AND da.jenis_dokumen='Rencana' ORDER BY da.tanggal_dokumen DESC",
                                array($npsn)
                            );
                            while ($rr = pg_fetch_assoc($q_rab)) {
                                echo "<option value=\"".htmlspecialchars($rr['nomor_dokumen'])."\">".htmlspecialchars($rr['nomor_dokumen'])." — ".htmlspecialchars($rr['tahun_anggaran'])."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-row"><input type="date" name="tanggal_realisasi"></div>
                    <div class="form-row"><input type="number" name="jumlah_realisasi" placeholder="Jumlah"></div>
                    <div class="form-row"><input name="bukti_transaksi" placeholder="Bukti (link / keterangan)"></div>
                    <div style="display:flex;gap:8px"><button class="btn" onclick="addRealisasi()">Simpan</button></div>
                </form>
            </details>

            <div class="table-wrapper">
                <table class="table" id="table-realisasi">
                    <thead><tr><th>Nomor Dokumen</th><th>Tanggal Realisasi</th><th>Jumlah</th><th>Status Verifikasi</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php
                    $q_realisasi = pg_query_params($conn,
                        "SELECT 
                            ra.nomor_dokumen,
                            ra.nomor_rab,
                            ra.tanggal_realisasi,
                            ra.jumlah_realisasi,
                            ra.bukti_transaksi,
                            ra.status_verifikasi,
                            d.total_dana
                        FROM realisasianggaran ra
                        JOIN dokumenanggaran d ON d.nomor_dokumen = ra.nomor_dokumen
                        WHERE d.npsn = $1 AND d.jenis_dokumen = 'Realisasi'
                        ORDER BY ra.tanggal_realisasi DESC",
                        array($npsn)
                    );
                    if (pg_num_rows($q_realisasi) == 0): ?>
                        <tr><td colspan="5" class="empty">Belum ada realisasi.</td></tr>
                    <?php else:
                        while ($r = pg_fetch_assoc($q_realisasi)): ?>
                        <tr data-pk="<?php echo htmlspecialchars($r['nomor_dokumen']); ?>">
                            <td><?php echo htmlspecialchars($r['nomor_dokumen']); ?></td>
                            <td><?php echo htmlspecialchars($r['tanggal_realisasi']); ?></td>
                            <td><?php echo htmlspecialchars($r['jumlah_realisasi']); ?></td>
                            <td><?php echo htmlspecialchars($r['status_verifikasi']); ?></td>
                            <td>
                                <div class="inline-actions">
                                    <button class="btn ghost" onclick="startEditRealisasi(this)">Edit</button>
                                    <button class="btn danger" onclick="deleteRealisasi('<?php echo addslashes($r['nomor_dokumen']); ?>')">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile;
                    endif;
                    ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Item Anggaran -->
        <div class="panel">
            <h3>Item Pembelian Anggaran Danan BOS</h3>

            <details>
                <summary class="details-summary">+ Tambah Item Pembelian</summary>
                <form id="form-add-item" class="edit-area" onsubmit="return false;">
                    <div class="form-row"><input name="nomor_dokumen" placeholder="Nomor Dokumen Rencana (RAB)" required></div>
                    <div class="form-row"><input name="nama_item" placeholder="Nama Item" required></div>
                    <div class="form-row"><input name="kategori" placeholder="Kategori"></div>
                    <div class="form-row"><input name="jumlah_rencana" placeholder="Jumlah Rencana"></div>
                    <div class="form-row"><input name="satuan" placeholder="Satuan"></div>
                    <div class="form-row"><input name="keterangan" placeholder="Keterangan"></div>
                    <div style="display:flex;gap:8px"><button class="btn" onclick="addItem()">Simpan</button></div>
                </form>
            </details>

            <br>

            <h3 style="margin-top:10px;">List Item Anggaran</h3>

            <?php
            // Ambil daftar dokumen Rencana (RAB) milik sekolah ini
            $q_rab = pg_query_params($conn,
                "SELECT da.nomor_dokumen, ra.tahun_anggaran, da.tanggal_dokumen
                FROM dokumenanggaran da
                JOIN rencanaanggaran ra ON ra.nomor_dokumen = da.nomor_dokumen
                WHERE da.npsn = $1 AND da.jenis_dokumen='Rencana'
                ORDER BY da.tanggal_dokumen DESC",
                array($npsn)
            );

            if (pg_num_rows($q_rab) == 0) {
                echo "<div class='empty'>Belum ada RAB / Rencana BOS.</div>";
            } else {

                while ($rab = pg_fetch_assoc($q_rab)) {

                    echo "
                    <details>
                        <summary class='details-summary' style='font-size:16px;margin-bottom:10px'>
                            <b>{$rab['nomor_dokumen']}</b> 
                            — Tahun: {$rab['tahun_anggaran']}
                            — Tanggal: {$rab['tanggal_dokumen']}
                        </summary>
                    ";

                    // Ambil item anggaran berdasarkan nomor dokumen RAB
                    $q_item = pg_query_params($conn,
                        "SELECT kode_item, nomor_dokumen, nama_item, kategori,
                                jumlah_rencana, satuan, keterangan
                        FROM itemanggaran
                        WHERE nomor_dokumen = $1
                        ORDER BY kode_item ASC",
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
                                <th>Aksi</th>
                            </tr>
                    ";

                    if (pg_num_rows($q_item) == 0) {
                        echo "<tr><td colspan='6' class='empty'>Belum ada item untuk RAB ini.</td></tr>";
                    } else {
                        while ($i = pg_fetch_assoc($q_item)) {
                            echo "
                            <tr data-pk='{$i['kode_item']}'>
                                <td class='col-nama'>{$i['nama_item']}</td>
                                <td class='col-kat'>{$i['kategori']}</td>
                                <td class='col-jml'>{$i['jumlah_rencana']}</td>
                                <td class='col-sat'>{$i['satuan']}</td>
                                <td class='col-ket'>{$i['keterangan']}</td>
                                <td>
                                    <div class='inline-actions'>
                                        <button class='btn ghost' onclick='startEditItem(this)'>Edit</button>
                                        <button class='btn danger' onclick='deleteItem(\"{$i['kode_item']}\")'>Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            ";
                        }
                    }

                    echo "
                        </table>
                    </div>
                    </details>
                    ";
                }
            }
            ?>
        </div>


        <div style="margin:40px 0 10px; font-size:22px; font-weight:700; color:var(--primary-dark);">
            Informasi dan Laporan - Read Only
        </div>


        <!-- Informasi Publik (Read) -->
        <div class="panel">
            <h3>Informasi Publik</h3>
            <div class="table-wrapper">
                <table class="table">
                    <tr><th>Nomor Info</th><th>Judul</th><th>Tanggal</th><th>Status</th></tr>
                    <?php
                    $q = pg_query_params($conn,
                        "SELECT nomor_informasi, judul, tanggal_publikasi, status FROM informasipublik WHERE kode_kcd = $1 ORDER BY tanggal_publikasi DESC",
                        array($sekolah['kode_kcd'] ?? $kode_kcd_session)
                    );
                    if (pg_num_rows($q)==0) echo "<tr><td colspan='4' class='empty'>Tidak ada informasi.</td></tr>";
                    else while ($r = pg_fetch_assoc($q)) {
                        echo "<tr><td>{$r['nomor_informasi']}</td><td>{$r['judul']}</td><td>{$r['tanggal_publikasi']}</td><td>{$r['status']}</td></tr>";
                    }
                    ?>
                </table>
            </div>
        </div>

    </div> <!-- grid -->
</div> <!-- container -->

<div id="toast" class="toast"></div>

<script>
// helpers
function showToast(msg, ok=true) {
    const t = document.getElementById('toast');
    t.style.display = 'block';
    t.style.background = ok ? '#111' : '#b91c1c';
    t.textContent = msg;
    setTimeout(()=> t.style.display='none', 2200);
}
async function postJSON(data) {
    const form = new FormData();
    form.append('ajax','1');
    for (const k in data) form.append(k, data[k]);
    const res = await fetch(location.href, {method:'POST', body: form});
    return res.json();
}

/* RENCANA */
async function addRencana(){
    const f = document.getElementById('form-add-rencana');
    const fd = new FormData(f);
    const res = await postJSON({
        action: 'add_rencana',
        nomor_rencana: fd.get('nomor_rencana'),
        tanggal_dokumen: fd.get('tanggal_dokumen'),
        tahun_anggaran: fd.get('tahun_anggaran'),
        total_dana: fd.get('total_dana')
    });
    if (res.ok) { showToast(res.msg); location.reload(); } else showToast(res.msg,false);
}
function startEditRencana(btn){
    const tr = btn.closest('tr');
    const pk = tr.dataset.pk;
    if (tr.querySelector('.edit-area-inline')) return;
    const tahun = tr.querySelector('.col-tahun').textContent.trim();
    const tgl = tr.querySelector('.col-tgl').textContent.trim();
    const editor = document.createElement('div');
    editor.className='edit-area-inline';
    editor.innerHTML = `
        <div class="form-row">
            <label>Tahun Anggaran</label>
            <input name="tahun" value="${escapeHtml(tahun)}">
        </div>

        <div class="form-row">
            <label>Tanggal Dokumen</label>
            <input type="date" name="tgl" value="${escapeHtml(tgl)}">
        </div>

        <div class="form-row">
            <label>Total Dana</label>
            <input name="total_dana" value="${tr.querySelector('.col-total').textContent.trim()}">
        </div>

        <div style="display:flex;gap:6px;margin-top:6px">
            <button class="btn">Simpan</button>
            <button class="btn ghost">Batal</button>
        </div>
    `;

    const actionsCell = tr.querySelector('td:last-child');
    actionsCell._old = actionsCell.innerHTML;
    actionsCell.innerHTML = '';
    actionsCell.appendChild(editor);
    const save = editor.querySelector('.btn'), cancel = editor.querySelector('.btn.ghost');
    save.addEventListener('click', async ()=>{
        const tahunVal = editor.querySelector('input[name=tahun]').value;
        const tglVal = editor.querySelector('input[name=tgl]').value;
        const res = await postJSON({
            action:'edit_rencana',
            nomor: pk,
            tahun: tahunVal,
            tanggal: tglVal,
            total_dana: editor.querySelector('input[name=total_dana]').value
        });
        if (res.ok) {
            showToast(res.msg);
            tr.querySelector('.col-tahun').textContent = tahunVal;
            tr.querySelector('.col-tgl').textContent = tglVal;
            tr.querySelector('.col-total').textContent = editor.querySelector('input[name=total_dana]').value;
            actionsCell.innerHTML = actionsCell._old; 
        } else 
            showToast(res.msg,false);
    });
    cancel.addEventListener('click', ()=> actionsCell.innerHTML = actionsCell._old);
}
async function deleteRencana(pk){
    if (!confirm('Hapus rencana ini?')) return;
    const res = await postJSON({action:'delete_rencana', nomor: pk});
    if (res.ok) { showToast(res.msg); const tr = document.querySelector(`#table-rencana tr[data-pk="${cssEscape(pk)}"]`); if (tr) tr.remove(); } else showToast(res.msg,false);
}

/* REALISASI */
async function addRealisasi(){
    const f = document.getElementById('form-add-realisasi');
    const fd = new FormData(f);
    const res = await postJSON({
        action:'add_realisasi',
        nomor_realisasi: fd.get('nomor_realisasi'),
        nomor_rab: fd.get('nomor_rab'),
        tanggal_realisasi: fd.get('tanggal_realisasi'),
        jumlah_realisasi: fd.get('jumlah_realisasi'),
        bukti_transaksi: fd.get('bukti_transaksi')
    });
    if (res.ok) { showToast(res.msg); location.reload(); } else showToast(res.msg,false);
}
function startEditRealisasi(btn){
    const tr = btn.closest('tr');
    const pk = tr.dataset.pk;
    if (tr.querySelector('.edit-area-inline')) return;
    const tgl = tr.querySelector('td:nth-child(2)').textContent.trim();
    const jumlah = tr.querySelector('td:nth-child(3)').textContent.trim();
    const editor = document.createElement('div'); editor.className='edit-area-inline';
    editor.innerHTML = `<div class="form-row"><input type="date" name="tanggal_realisasi" value="${tgl}"></div><div class="form-row"><input name="jumlah" value="${escapeHtml(jumlah)}"></div><div style="display:flex;gap:6px;margin-top:6px"><button class="btn">Simpan</button><button class="btn ghost">Batal</button></div>`;
    const actionsCell = tr.querySelector('td:last-child');
    actionsCell._old = actionsCell.innerHTML; actionsCell.innerHTML=''; actionsCell.appendChild(editor);
    const save = editor.querySelector('.btn'), cancel = editor.querySelector('.btn.ghost');
    save.addEventListener('click', async ()=>{
        const tglVal = editor.querySelector('input[name=tanggal_realisasi]').value;
        const jumlahVal = editor.querySelector('input[name=jumlah]').value;
        const res = await postJSON({action:'edit_realisasi', nomor: pk, tanggal_realisasi: tglVal, jumlah: jumlahVal});
        if (res.ok) { showToast(res.msg); tr.querySelector('td:nth-child(2)').textContent = tglVal; tr.querySelector('td:nth-child(3)').textContent = jumlahVal; actionsCell.innerHTML = actionsCell._old; } else showToast(res.msg,false);
    });
    cancel.addEventListener('click', ()=> actionsCell.innerHTML = actionsCell._old);
}
async function deleteRealisasi(pk){
    if (!confirm('Hapus realisasi ini?')) return;
    const res = await postJSON({action:'delete_realisasi', nomor: pk});
    if (res.ok) { showToast(res.msg); const tr = document.querySelector(`#table-realisasi tr[data-pk="${cssEscape(pk)}"]`); if (tr) tr.remove(); } else showToast(res.msg,false);
}

/* ITEM */
async function addItem(){
    const f = document.getElementById('form-add-item');
    const fd = new FormData(f);
    const res = await postJSON({
        action:'add_item',
        nomor_dokumen: fd.get('nomor_dokumen'),
        nama_item: fd.get('nama_item'),
        kategori: fd.get('kategori'),
        jumlah_rencana: fd.get('jumlah_rencana'),
        satuan: fd.get('satuan'),
        keterangan: fd.get('keterangan')
    });
    if (res.ok) { showToast(res.msg); location.reload(); } else showToast(res.msg,false);
}
function startEditItem(btn){
    const tr = btn.closest('tr');
    const pk = tr.dataset.pk;
    if (tr.querySelector('.edit-area-inline')) return;
    const nama = tr.querySelector('.col-nama').textContent.trim();
    const kat = tr.querySelector('.col-kat').textContent.trim();
    const jml = tr.querySelector('.col-jml').textContent.trim();
    const sat = tr.querySelector('.col-sat').textContent.trim();
    const ket = tr.querySelector('.col-ket').textContent.trim();
    const editor = document.createElement('div'); editor.className='edit-area-inline';
    editor.innerHTML = `<div class="form-row"><input name="nama_item" value="${escapeHtml(nama)}"></div><div class="form-row"><input name="kategori" value="${escapeHtml(kat)}"></div><div class="form-row"><input name="jumlah_rencana" value="${escapeHtml(jml)}"></div><div class="form-row"><input name="satuan" value="${escapeHtml(sat)}"></div><div class="form-row"><input name="keterangan" value="${escapeHtml(ket)}"></div><div style="display:flex;gap:6px;margin-top:6px"><button class="btn">Simpan</button><button class="btn ghost">Batal</button></div>`;
    const actionsCell = tr.querySelector('td:last-child'); actionsCell._old = actionsCell.innerHTML; actionsCell.innerHTML = ''; actionsCell.appendChild(editor);
    const save = editor.querySelector('.btn'), cancel = editor.querySelector('.btn.ghost');
    save.addEventListener('click', async ()=>{
        const payload = {
            action:'edit_item', id: pk,
            nama_item: editor.querySelector('input[name=nama_item]').value,
            kategori: editor.querySelector('input[name=kategori]').value,
            jumlah_rencana: editor.querySelector('input[name=jumlah_rencana]').value,
            satuan: editor.querySelector('input[name=satuan]').value,
            keterangan: editor.querySelector('input[name=keterangan]').value
        };
        const res = await postJSON(payload);
        if (res.ok) {
            showToast(res.msg);
            tr.querySelector('.col-nama').textContent = payload.nama_item;
            tr.querySelector('.col-kat').textContent = payload.kategori;
            tr.querySelector('.col-jml').textContent = payload.jumlah_rencana;
            tr.querySelector('.col-sat').textContent = payload.satuan;
            tr.querySelector('.col-ket').textContent = payload.keterangan;
            actionsCell.innerHTML = actionsCell._old;
        } else showToast(res.msg,false);
    });
    cancel.addEventListener('click', ()=> actionsCell.innerHTML = actionsCell._old);
}
async function deleteItem(pk){
    if (!confirm('Hapus item ini?')) return;
    const res = await postJSON({action:'delete_item', id: pk});
    if (res.ok) { showToast(res.msg); const tr = document.querySelector(`#table-item tr[data-pk="${cssEscape(pk)}"]`); if (tr) tr.remove(); } else showToast(res.msg,false);
}

// helpers
function escapeHtml(s){ if(!s) return ''; return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;'); }
function cssEscape(s){ return String(s).replaceAll('"','\\"').replaceAll("'", "\\'"); }
</script>
</body>
</html>