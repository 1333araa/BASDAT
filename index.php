<?php session_start(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login Sistem BOS</title>

<style>
body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background: #f3f4f6;
    height: 100vh;
    display: flex;
}

/* ===================== */
/* PANEL KIRI (5/8) */
/* ===================== */
.left-panel {
    flex: 5;
    background: url('banner.jpg') center/cover no-repeat;
    position: relative;
    display: flex;
    align-items: center;
    color: white;
    padding: 60px;
}

.left-overlay {
    position: absolute;
    inset: 0;
    background: rgba(54, 65, 133, 0.55); /* sedikit gelap biar teks terbaca */
}

.left-content {
    position: relative;
    max-width: 55%;
}

.left-title {
    font-size: 38px;
    font-weight: 700;
    margin-bottom: 10px;
}

.left-subtitle {
    font-size: 20px;
    font-weight: 700;
    color: #fcd34d; /* emas seperti referensi */
}

/* ===================== */
/* PANEL KANAN (3/8) */
/* ===================== */
.right-panel {
    flex: 3;
    background: #ffffff;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
}

.card {
    width: 100%;
    max-width: 360px;
    padding: 35px 30px;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.10);
}

h2 {
    margin: 0 0 25px 0;
    font-size: 24px;
    text-align: center;
    color: #4338ca;
    font-weight: 700;
}

label {
    display: block;
    margin-bottom: 6px;
    color: #374151;
    font-size: 14px;
    font-weight: 600;
}

input, select {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    margin-bottom: 15px;
    font-size: 15px;
    background: #f8fafc;
    transition: 0.2s;
}

input:focus, select:focus {
    border-color: #6366f1;
    background: white;
    outline: none;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.25);
}

.btn {
    width: 100%;
    background: #4f46e5;
    color: white;
    padding: 12px;
    border-radius: 10px;
    cursor: pointer;
    border: none;
    font-size: 16px;
    font-weight: 600;
    transition: 0.2s ease;
}

.btn:hover {
    background: #4338ca;
}

.error {
    color: #dc2626;
    background: #fee2e2;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
    font-size: 14px;
}

@media(max-width: 900px) {
    .left-panel { display: none; }
    .right-panel { flex: 1; }
    body { justify-content: center; }
}
</style>
</head>
<body>

<!-- =============================== -->
<!--   PANEL KIRI (GAMBAR 5/8)       -->
<!-- =============================== -->
<div class="left-panel">
    <div class="left-overlay"></div>

    <div class="left-content">
        <div class="left-title">Selamat Datang</div>
        <div class="left-subtitle">di Sistem Manajemen Anggaran Dana BOS</div>
    </div>
</div>

<!-- =============================== -->
<!--   PANEL KANAN (LOGIN FORM)      -->
<!-- =============================== -->
<div class="right-panel">
    <div class="card">
        <h2>User Login Portal</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="POST" action="auth.php">

            <label>Access Level</label>
            <select name="role" required>
                <option value="">-- User Type --</option>
                <option value="kcd">KCD</option>
                <option value="pengawas">Pengawas</option>
                <option value="sekolah">Sekolah</option>
                <option value="publik">Publik</option>
            </select>

            <label>Email / NIP / NPSN</label>
            <input type="text" name="username" placeholder="Masukkan identitas" required>

            <label>Password</label>
            <input type="password" name="password" placeholder="Masukkan password" required>

            <button class="btn">Masuk</button>
        </form>
    </div>
</div>

</body>
</html>
