<?php
// publik.php
session_start();
include "db.php";

// Jika ada permintaan AJAX untuk mengambil detail artikel
if (isset($_GET['get_info'])) {
    $nomor = $_GET['get_info'];
    $q = pg_query_params($conn,
        "SELECT i.nomor_informasi, i.judul, i.tanggal_publikasi, i.deskripsi_info, i.kode_kcd, k.nama AS nama_kcd
         FROM informasipublik i
         LEFT JOIN kcd k ON k.kode_kcd = i.kode_kcd
         WHERE i.nomor_informasi = $1 AND i.status = 'Dipublikasikan'",
         array($nomor)
    );
    if ($row = pg_fetch_assoc($q)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'data' => $row
        ]);
        exit;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Informasi tidak ditemukan']);
        exit;
    }
}

// Ambil daftar informasi publik yang Dipublikasikan
$q = pg_query($conn,
    "SELECT i.nomor_informasi, i.judul, i.tanggal_publikasi, i.kode_kcd, k.nama AS nama_kcd
     FROM informasipublik i
     LEFT JOIN kcd k ON k.kode_kcd = i.kode_kcd
     WHERE i.status = 'Dipublikasikan'
     ORDER BY i.tanggal_publikasi DESC NULLS LAST, i.nomor_informasi DESC"
);

$items = [];
while ($r = pg_fetch_assoc($q)) $items[] = $r;

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Informasi Publik — Sistem BOS</title>
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
    font-family:'Inter','Segoe UI',system-ui,sans-serif;
    background:var(--bg);
    color:var(--text);
}

/* ---------------- NAVBAR ---------------- */
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

/* -------------- CONTAINER -------------- */
.container{
    max-width:1700px;
    margin:35px auto;
    padding:0 80px;
}

.page-title{
    font-size:28px;
    font-weight:700;
    margin-bottom:6px;
}

.sub{
    color:var(--text-light);
    font-size:14px;
}

/* -------------- GRID -------------- */
.grid{
    display:flex;
    gap:26px;
    align-items:flex-start;
}

/* -------------- SIDEBAR -------------- */
.sidebar{
    width:400px;
    min-width:260px;
    background:var(--card);
    padding:20px;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    height: calc(100vh - 160px);
    overflow-y:auto;
}

.search-box{
    margin-bottom:15px;
}

.input{
    width:100%;
    padding:10px;
    border-radius:10px;
    border:1px solid #e2e8f0;
    font-size:15px;
}

.input:focus{
    outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(79,70,229,0.25);
}

/* -------------- LIST -------------- */
.list{
    padding:0;
    list-style:none;
    margin:0;
}

.list li{
    padding:10px 12px;
    border-radius:10px;
    margin-bottom:10px;
    cursor:pointer;
    transition:0.15s;
    background: #ffffff;
}

.list li:hover{
    background:#eef2ff;
}

.list .meta{
    display:block;
    color:var(--text-light);
    font-size:13px;
    margin-bottom:4px;
}

.title-small{
    font-size:15px;
    font-weight:600;
}

/* -------------- MAIN ARTICLE -------------- */
.main{
    flex:1;
    background:var(--card);
    padding:28px;
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    min-height:400px;
}

.article-title{
    font-size:30px;
    margin:0 0 8px 0;
}

.article-meta{
    color:var(--text-light);
    margin-bottom:14px;
}

.article-body{
    line-height:1.8;
    color:var(--text);
    white-space:normal;
    font-size:18px;
}

.empty{
    color:var(--text-light);
    padding:12px;
}

/* -------------- LINK -------------- */
.backlink{
    color:var(--primary);
    text-decoration:none;
    font-weight:600;
    margin-top:16px;
    display:inline-block;
}

.backlink:hover{
    color:var(--primary-dark);
}

.header-box{
    background: var(--card);
    padding: 22px 28px;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 26px;
}

.header-box .title{
    font-size: 26px;
    font-weight: 700;
    margin: 0;
}

.header-box .subtitle{
    color: var(--text-light);
    margin-top: 6px;
    font-size: 14px;
}

.header-box .info{
    margin-top: 10px;
    font-size: 13px;
    color: var(--text-light);
    background: #eef2ff;
    padding: 6px 10px;
    border-radius: 8px;
    width: max-content;
    font-weight: 600;
}

</style>
</head>
<body>
    <div class="navbar">
        <div class="nav-left">
            <div class="brand">Si Manggaran</div>
            <div class="menu">
                <a href="publik.php">Informasi Publik</a>
                <a href="index.php">Login</a>
            </div>
        </div>
    </div>
<div class="container">
    <div class="header-box">
        <div class="title">Informasi Publik</div>
        <div class="subtitle">Berita & pengumuman publik terkait BOS — dapat diakses tanpa login</div>
        <div class="info">Menampilkan artikel: Dipublikasikan</div>
    </div>

    <div class="grid">
        <!-- SIDEBAR -->
        <aside class="sidebar" aria-label="Daftar Informasi Publik">
            <div style="margin-bottom:12px">
                <div style="font-weight:700;margin-bottom:6px">Daftar Informasi Publik</div>
                <div style="color:var(--muted);font-size:13px">Klik judul untuk baca detail</div>
            </div>

            <div class="search-box">
                <input id="searchInput" class="input" placeholder="Cari judul..." oninput="filterList()">
            </div>

            <ul id="list" class="list">
                <?php if (count($items) === 0): ?>
                    <li class="empty">Belum ada informasi publik yang dipublikasikan.</li>
                <?php else: ?>
                    <?php foreach ($items as $it): 
                        $tgl = $it['tanggal_publikasi'] ? date('d M Y', strtotime($it['tanggal_publikasi'])) : '';
                    ?>
                        <li data-nomor="<?php echo htmlspecialchars($it['nomor_informasi']); ?>" onclick="loadArticle('<?php echo addslashes($it['nomor_informasi']); ?>')">
                            <span class="meta">[<?php echo htmlspecialchars($tgl); ?>] <span style="color:var(--muted)"><?php echo htmlspecialchars($it['nama_kcd']); ?></span></span>
                            <div class="title-small"><?php echo htmlspecialchars($it['judul']); ?></div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

            <div style="margin-top:12px;color:var(--muted);font-size:13px">Total: <strong id="totalCount"><?php echo count($items); ?></strong></div>
        </aside>

        <!-- MAIN ARTICLE -->
        <main class="main" id="mainArticle" aria-live="polite">
            <div id="placeholder">
                <h2 style="margin-top:0" class="article-title">Selamat datang — Pilih informasi di kiri</h2>
                <div class="article-meta">Pilih salah satu judul pada daftar untuk membaca detail informasi publik.</div>
                <div class="article-body" style="margin-top:16px;color:var(--muted)">
                    Informasi yang dipublikasikan akan tampil di sini. Kamu juga bisa mencari judul menggunakan kotak pencarian di sebelah kiri.
                </div>
            </div>
        </main>
    </div>

</div>

<script>
// Data: list nomor untuk mengambil pertama kali (ambil nomor pertama dari sidebar)
(function(){
    const first = document.querySelector('#list li[data-nomor]');
    if (first) {
        // auto-load first item for better UX
        loadArticle(first.getAttribute('data-nomor'));
    }
})();

async function loadArticle(nomor) {
    if (!nomor) return;
    const main = document.getElementById('mainArticle');
    // show loading skeleton
    main.innerHTML = `<div class="article-title">Memuat...</div><div class="article-meta"></div><div class="article-body">Sedang mengambil isi artikel…</div>`;

    try {
        const res = await fetch(`?get_info=${encodeURIComponent(nomor)}`);
        const json = await res.json();
        if (!json.ok) {
            main.innerHTML = `<div class="article-title">Tidak dapat memuat</div><div class="article-body">${escapeHtml(json.msg || 'Terjadi kesalahan')}</div>`;
            return;
        }
        const d = json.data;
        const tgl = d.tanggal_publikasi ? formatDate(d.tanggal_publikasi) : '';
        const kcd = d.nama_kcd ? escapeHtml(d.nama_kcd) + ' - ' + escapeHtml(d.kode_kcd) : escapeHtml(d.kode_kcd || '');
        // render article
        main.innerHTML = `
            <h2 class="article-title">${escapeHtml(d.judul)}</h2>
            <div class="article-meta">${tgl} · Sumber: ${kcd}</div>
            <div class="article-body">${nl2br(escapeHtml(d.deskripsi_info || '—'))}</div>
            <a class="backlink" href="?">Lihat daftar lengkap</a>
        `;

        // highlight selected item in sidebar
        document.querySelectorAll('#list li').forEach(li => {
            li.style.background = '';
        });
        const sel = document.querySelector('#list li[data-nomor="'+cssEscape(nomor)+'"]');
        if (sel) sel.style.background = '#eef2ff';
    } catch (e) {
        main.innerHTML = `<div class="article-title">Kesalahan jaringan</div><div class="article-body">Tidak dapat mengambil data. Cek koneksi Anda.</div>`;
        console.error(e);
    }
}

function filterList(){
    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    const lis = document.querySelectorAll('#list li[data-nomor]');
    let count = 0;
    lis.forEach(li => {
        const t = li.querySelector('.title-small').textContent.toLowerCase();
        const m = li.querySelector('.meta').textContent.toLowerCase();
        if (t.includes(q) || m.includes(q)) {
            li.style.display = '';
            count++;
        } else li.style.display = 'none';
    });
    document.getElementById('totalCount').textContent = count;
}

// helpers
function escapeHtml(s){ if(!s && s !== 0) return ''; return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;'); }
function nl2br(s){ return String(s).replace(/\n/g,'<br>'); }
function formatDate(d) {
    try {
        const dt = new Date(d);
        const opt = { day:'2-digit', month:'short', year:'numeric' };
        return dt.toLocaleDateString('id-ID', opt);
    } catch(e){ return d; }
}
function cssEscape(s){ return String(s).replaceAll('"','\\"').replaceAll("'", "\\'"); }
</script>
</body>
</html>
