<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['nip'])) {
    header("Location: login.php");
    exit();
}

// Ambil data session
$nama = $_SESSION['nama_lengkap'];
$level = $_SESSION['level'];

require 'config.php';

// Fungsi untuk mendapatkan kode barang terakhir berdasarkan jenis
function getLastKodeBarang($conn, $jenis_id) {
    $sql = "SELECT kode_barang FROM aset_barang WHERE jenis_id = ? ORDER BY kode_barang DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jenis_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['kode_barang'];
    }
    return null;
}

// Fungsi untuk generate kode barang baru
function generateKodeBarang($conn, $jenis_id) {
    // Format: 132XXYYYYYYY (XX = kode jenis, YYYYYY = urutan)
    $prefix = "132" . str_pad($jenis_id, 2, '0', STR_PAD_LEFT); // Format prefix: 132 + 2 digit jenis
    
    // Dapatkan kode terakhir
    $lastKode = getLastKodeBarang($conn, $jenis_id);
    
    if ($lastKode) {
        // Ambil sequence dari kode terakhir (7 digit terakhir)
        $lastSequence = substr($lastKode, 5);
        // Increment sequence
        $sequence = intval($lastSequence) + 1;
    } else {
        // Jika belum ada, mulai dari 1
        $sequence = 1;
    }
    
    // Format sequence menjadi 7 digit dengan leading zero
    $sequencePart = str_pad($sequence, 7, '0', STR_PAD_LEFT);
    
    return $prefix . $sequencePart;
}

$success = "";
$error = "";

function logAktivitas($conn, $nip, $aktivitas) {
    $sql = "INSERT INTO log_aktivitas (nip, aktivitas) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $nip, $aktivitas);
    $stmt->execute();
    $stmt->close();
}

function catatRiwayatPerubahan($conn, $aset_id, $tanggal, $perubahan, $nip_petugas) {
    $sql = "INSERT INTO riwayat_perubahan (aset_id, tanggal_perubahan, perubahan, nip_petugas)
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $aset_id, $tanggal, $perubahan, $nip_petugas);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_petugas = trim($_POST['nama_petugas']);
    $nip = trim($_POST['nip']);
    $nama_barang = trim($_POST['nama_barang']);
    $jenis_id = intval($_POST['jenis_id']);

    // Generate kode barang berdasarkan jenis
    $kode_barang = generateKodeBarang($conn, $jenis_id);

    $spesifikasi_nama_barang = trim($_POST['spesifikasi_nama_barang']);
    $spesifikasi_lainnya = trim($_POST['spesifikasi_lainnya']);
    $lokasi = trim($_POST['lokasi']);
    $jumlah = intval($_POST['jumlah']);
    $satuan = trim($_POST['satuan']);
    $harga_satuan_perolehan = floatval($_POST['harga_satuan_perolehan']);
    $nilai_perolehan = floatval($_POST['nilai_perolehan']);
    $cara_perolehan = trim($_POST['cara_perolehan']);
    $tanggal_perolehan = $_POST['tanggal_perolehan'];
    $keterangan = trim($_POST['keterangan']);
    $kondisi_id = intval($_POST['kondisi_id']);

    $bukti = "";
    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === 0) {
        $file_tmp = $_FILES['bukti']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['bukti']['name']);
        $target_dir = "uploads/";
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            $bukti = $file_name;
        } else {
            $error = "Upload gambar gagal!";
        }
    }

    if (!$error) {
        $sql = "INSERT INTO aset_barang (
            nama_petugas, nip, kode_barang, nama_barang, jenis_id,
            spesifikasi_nama_barang, spesifikasi_lainnya,
            lokasi, jumlah, satuan, harga_satuan_perolehan,
            nilai_perolehan, cara_perolehan, tanggal_perolehan,
            keterangan, kondisi_id, bukti
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssisssssdssssss", 
            $nama_petugas, $nip, $kode_barang, $nama_barang, $jenis_id,
            $spesifikasi_nama_barang, $spesifikasi_lainnya, $lokasi,
            $jumlah, $satuan, $harga_satuan_perolehan, $nilai_perolehan,
            $cara_perolehan, $tanggal_perolehan, $keterangan, $kondisi_id, $bukti
        );

        if ($stmt->execute()) {
            $success = "Data aset berhasil disimpan!";
            $aset_id = $conn->insert_id;
            $nip_petugas = isset($_SESSION['nip']) ? $_SESSION['nip'] : $nip;
            $tanggal = date("Y-m-d");
            $perubahan = "Penambahan aset: $nama_barang (Kode: $kode_barang)";

            catatRiwayatPerubahan($conn, $aset_id, $tanggal, $perubahan, $nip_petugas);
            logAktivitas($conn, $nip_petugas, $perubahan);
        } else {
            $error = "Gagal menyimpan data: " . $stmt->error;
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Aset Barang - MONVEST</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            overflow-x: hidden;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .menu-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand svg {
            width: 32px;
            height: 32px;
            fill: white;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .user-role {
            font-size: 0.85rem;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 2px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            width: 280px;
            height: calc(100vh - 70px);
            background: white;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
            transform: translateX(0);
            transition: transform 0.3s ease;
            z-index: 999;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            transform: translateX(-280px);
        }

        .sidebar-header {
            height: 20px;
            width: 50px;
            padding-top: 40px;
            padding-left: 50px;
            padding-bottom: 100px;
            
        }

        .sidebar-header img {
            height: 70px;
            width: 160px;
            
        }

        .sidebar-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .sidebar-subtitle {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 25px 10px;
        }

        .menu-item {
            display: block;
            padding: 12px 25px;
            color: #374151;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-item:hover {
            background: #f3f4f6;
            color: #4f46e5;
            padding-left: 30px;
        }

        .menu-item.active {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
        }

        .menu-item.active:hover {
            padding-left: 25px;
        }

        .menu-item svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .logout-item {
            border-top: 1px solid #e5e7eb;
            margin-top: 20px;
            padding-top: 20px;
        }

        .logout-item .menu-item {
            color: #dc2626;
        }

        .logout-item .menu-item:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 70px);
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .page-header {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .page-header h1 {
            color: #1f2937;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 1.1rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: #4f46e5;
            text-decoration: none;
        }

        .breadcrumb span {
            color: #9ca3af;
        }

        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 25px;
        }

        .form-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .form-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .form-body {
            padding: 30px;
        }

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-grid.single {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-label.required::after {
            content: " *";
            color: #dc2626;
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-file {
            padding: 12px 16px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .form-file:hover {
            border-color: #4f46e5;
            background: #f3f4f6;
        }

        .file-input {
            display: none;
        }

        .file-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: #6b7280;
        }

        .file-label svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-280px);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .navbar {
                padding: 0 20px;
            }

            .user-info {
                display: none;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                justify-content: center;
            }
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
        }

        .overlay.show {
            display: block;
        }

        /* Tambahkan di bagian style */
        #nama-barang-feedback {
            padding: 5px 10px;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 0.85rem;
            display: none;
        }

        /* Untuk input yang valid */
        .input-valid {
            border-color: #166534 !important;
            box-shadow: 0 0 0 3px rgba(22, 101, 52, 0.1) !important;
        }

        /* Untuk input yang invalid */
        .input-invalid {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <svg viewBox="0 0 24 24" width="24" height="24">
                    <path fill="currentColor" d="M3,6H21V8H3V6M3,11H21V13H3V11M3,16H21V18H3V16Z"/>
                </svg>
            </button>
        </div>
        <div class="navbar-right">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($nama); ?></div>
                <div class="user-role"><?php echo strtoupper($level); ?></div>
            </div>
            <div class="user-avatar">
                <?php echo strtoupper(substr($nama, 0, 1)); ?>
            </div>
        </div>
    </nav>

    <!-- Overlay for mobile -->
    <div class="overlay" onclick="closeSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="gambar/logo1.png" alt="logo monvest">
        </div>
        
        <nav class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-section-title">Menu</div>
            </div>

            <?php if ($level == 'admin'): ?>
            <div class="menu-section">

            <a href="dashboard.php" class="menu-item ">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,3V9H21V3M13,21H21V11H13M3,21H11V15H3M3,13H11V3H3V13Z"/>
                    </svg>
                    Dashboard
                </a>
            
                <a href="input_aset.php" class="menu-item active">
                    <svg viewBox="0 0 24 24">
                        <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                    Input Aset
                </a>
                <a href="kelola_inventaris.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8Z"/>
                    </svg>
                    Kelola Inventaris
                </a>
            </div>

            <div class="menu-section">
                <a href="buat_akun.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M15,14C12.33,14 7,15.33 7,18V20H23V18C23,15.33 17.67,14 15,14M6,10V7H4V10H1V12H4V15H6V12H9V10M15,12A4,4 0 0,0 19,8A4,4 0 0,0 15,4A4,4 0 0,0 11,8A4,4 0 0,0 15,12Z"/>
                    </svg>
                    Buat Akun Baru
                </a>
                <a href="data_akun.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M16,13C15.71,13 15.38,13 15.03,13.05C16.19,13.89 17,15 17,16.5V19H23V16.5C23,14.17 18.33,13 16,13M8,13C5.67,13 1,14.17 1,16.5V19H15V16.5C15,14.17 10.33,13 8,13M8,11A3,3 0 0,0 11,8A3,3 0 0,0 8,5A3,3 0 0,0 5,8A3,3 0 0,0 8,11M16,11A3,3 0 0,0 19,8A3,3 0 0,0 16,5A3,3 0 0,0 13,8A3,3 0 0,0 16,11Z"/>
                    </svg>
                    Kelola Data User
                </a>
            </div>

            <div class="menu-section">
                <a href="riwayat_perubahan.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3"/>
                    </svg>
                    Riwayat Perubahan
                </a>
                <a href="log_aktivitas.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    Log Aktivitas
                </a>
            </div>

            <div class="menu-section">
                <a href="pemusnahan_aset.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M7,6H17V19H7V6M9,8V17H11V8H9M13,8V17H15V8H13Z"/>
                    </svg>
                    Pemusnahan aset
                </a>
                <a href="profile.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                    </svg>
                    Profile
                </a>
            </div>

            <?php elseif ($level == 'kepala_dinas'): ?>
            <div class="menu-section">
                <a href="dashboard.php" class="menu-item ">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,3V9H21V3M13,21H21V11H13M3,21H11V15H3M3,13H11V3H3V13Z"/>
                    </svg>
                    Dashboard 
                </a>
                <div class="menu-section-title">Laporan</div>
                <a href="#" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    Laporan Inventaris
                </a>
                <a href="#" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M3,3H21A1,1 0 0,1 22,4V20A1,1 0 0,1 21,21H3A1,1 0 0,1 2,20V4A1,1 0 0,1 3,3M4,5V19H20V5H4M6,7H18V9H6V7M6,11H18V13H6V11M6,15H18V17H6V15Z"/>
                    </svg>
                    Export Laporan
                </a>
            </div>

            <?php elseif ($level == 'petugas'): ?>
            <div class="menu-section">
                <a href="dashboard.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,3V9H21V3M13,21H21V11H13M3,21H11V15H3M3,13H11V3H3V13Z"/>
                    </svg>
                    Dashboard
                </a>
                <a href="input_aset.php" class="menu-item active">
                    <svg viewBox="0 0 24 24">
                        <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                    Input Aset
                </a>
            </div>

            <div class="menu-section">
                <a href="kelola_inventaris.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8Z"/>
                    </svg>
                    Kelola Inventaris
                </a>
                <a href="profile.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                    </svg>
                    Profile
                </a>
            </div>

            <?php endif; ?>

            <div class="menu-section logout-item">
                <a href="logout.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M16,17V14H9V10H16V7L21,12L16,17M14,2A2,2 0 0,1 16,4V6H14V4H5V20H14V18H16V20A2,2 0 0,1 14,22H5A2,2 0 0,1 3,20V4A2,2 0 0,1 5,2H14Z"/>
                    </svg>
                    Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Input Aset Barang</h1>
            <p>Tambahkan data aset baru ke dalam sistem inventaris</p>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>›</span>
                <span>Input Aset</span>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <h2>Form Input Aset Barang</h2>
                <p>Lengkapi semua informasi aset dengan detail yang akurat</p>
            </div>
            <div class="form-body">
                <!-- Alerts -->
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <svg viewBox="0 0 24 24">
                            <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z"/>
                        </svg>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <svg viewBox="0 0 24 24">
                            <path d="M13,14H11V10H13M13,18H11V16H13M1,21H23L12,2L1,21Z"/>
                        </svg>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" action="">
                    <!-- Informasi Petugas -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Nama Petugas</label>
                            <input type="text" name="nama_petugas" class="form-input" required
                            value="<?php echo htmlspecialchars($nama); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">NIP</label>
                            <input type="text" name="nip" class="form-input" required
                            value="<?php echo htmlspecialchars($_SESSION['nip']); ?>" readonly>
                        </div>
                    </div>

                    <!-- Informasi Barang -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Nama Barang</label>
                            <input type="text" name="nama_barang" id="nama_barang" class="form-input" required>
                            <div id="nama-barang-feedback" style="margin-top: 5px; font-size: 0.85rem; display: none;"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Jenis Barang</label>
                            <select name="jenis_id" class="form-select" required>
                                <option value="">-- Pilih Jenis --</option>
                                <?php
                                $conn = new mysqli("localhost", "root", "", "monvest_db");
                                $jenis = $conn->query("SELECT id, nama_jenis FROM jenis_barang");
                                while ($row = $jenis->fetch_assoc()) {
                                    echo "<option value='{$row['id']}'>{$row['nama_jenis']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Spesifikasi Barang -->
                    <div class="form-grid single">
                        <div class="form-group">
                            <label class="form-label required">Kondisi Barang</label>
                            <select name="kondisi_id" class="form-select" required>
                                <option value="">-- Pilih Kondisi --</option>
                                <?php
                                $kondisi = $conn->query("SELECT id, nama_kondisi FROM kondisi");
                                while ($row = $kondisi->fetch_assoc()) {
                                    echo "<option value='{$row['id']}'>{$row['nama_kondisi']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Spesifikasi Nama Barang</label>
                            <textarea name="spesifikasi_nama_barang" class="form-textarea" placeholder="Masukkan spesifikasi detail nama barang..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Spesifikasi Lainnya</label>
                            <textarea name="spesifikasi_lainnya" class="form-textarea" placeholder="Masukkan spesifikasi tambahan lainnya..."></textarea>
                        </div>
                    </div>

                    <!-- Lokasi dan Jumlah -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Lokasi</label>
                            <input type="text" name="lokasi" class="form-input" placeholder="Lokasi penyimpanan aset">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Jumlah</label>
                            <input type="number" name="jumlah" class="form-input" min="1" value="1" required>
                        </div>
                    </div>

                    <!-- Satuan dan Harga -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Satuan</label>
                            <input type="text" name="satuan" class="form-input" placeholder="Unit, Pcs, Kg, dll">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Harga Satuan Perolehan</label>
                            <input type="number" step="0.01" name="harga_satuan_perolehan" class="form-input" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Nilai Perolehan dan Cara Perolehan -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nilai Perolehan</label>
                            <input type="number" step="0.01" name="nilai_perolehan" class="form-input" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Cara Perolehan</label>
                            <select name="cara_perolehan" class="form-select" required>
                                <option value="">-- Pilih Cara Perolehan --</option>
                                <option value="Hibah">Hibah</option>
                                <option value="Pengadaan APBD">Pengadaan APBD</option>
                            </select>
                        </div>

                    </div>

                    <!-- Tanggal Perolehan -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Tanggal Perolehan</label>
                            <input type="date" name="tanggal_perolehan" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Upload Bukti Aset</label>
                            <div class="form-file">
                                <input type="file" name="bukti" accept="image/*" class="file-input" id="file-input">
                                <label for="file-input" class="file-label">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                    </svg>
                                    Pilih file gambar...
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Keterangan -->
                    <div class="form-grid single">
                        <div class="form-group full-width">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-textarea" placeholder="Catatan tambahan tentang aset..."></textarea>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <svg viewBox="0 0 24 24">
                                <path d="M20,11V13H8L13.5,18.5L12.08,19.92L4.16,12L12.08,4.08L13.5,5.5L8,11H20Z"/>
                            </svg>
                            Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <svg viewBox="0 0 24 24">
                                <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z"/>
                            </svg>
                            Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.querySelector('.overlay');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        }

        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('show');
        }

        // File input handling
        document.getElementById('file-input').addEventListener('change', function(e) {
            const label = document.querySelector('.file-label');
            if (e.target.files.length > 0) {
                label.innerHTML = `
                    <svg viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    ${e.target.files[0].name}
                `;
            } else {
                label.innerHTML = `
                    <svg viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    Pilih file gambar...
                `;
            }
        });

        // Auto calculate nilai perolehan
        document.querySelector('input[name="harga_satuan_perolehan"]').addEventListener('input', calculateNilaiPerolehan);
        document.querySelector('input[name="jumlah"]').addEventListener('input', calculateNilaiPerolehan);

        function calculateNilaiPerolehan() {
            const hargaSatuan = parseFloat(document.querySelector('input[name="harga_satuan_perolehan"]').value) || 0;
            const jumlah = parseInt(document.querySelector('input[name="jumlah"]').value) || 0;
            const nilaiPerolehan = hargaSatuan * jumlah;
            
            document.querySelector('input[name="nilai_perolehan"]').value = nilaiPerolehan.toFixed(2);
        }

        // Auto hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);

        // Di bagian script, tambahkan kode berikut:
        document.getElementById('nama_barang').addEventListener('input', function() {
            const namaBarang = this.value.trim();
            const feedbackElement = document.getElementById('nama-barang-feedback');
            
            if (namaBarang.length > 2) { // Mulai cek setelah 3 karakter
                fetch(`check_nama_barang.php?nama_barang=${encodeURIComponent(namaBarang)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            feedbackElement.textContent = '⚠️ Barang dengan nama ini sudah terdaftar!';
                            feedbackElement.style.color = '#dc2626';
                            feedbackElement.style.display = 'block';
                            this.style.borderColor = '#dc2626';
                        } else {
                            feedbackElement.textContent = '✔️ Nama barang tersedia';
                            feedbackElement.style.color = '#166534';
                            feedbackElement.style.display = 'block';
                            this.style.borderColor = '#166534';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        feedbackElement.style.display = 'none';
                        this.style.borderColor = '#e5e7eb';
                    });
            } else {
                feedbackElement.style.display = 'none';
                this.style.borderColor = '#e5e7eb';
            }
        });

        // Juga tambahkan validasi saat form disubmit
        document.querySelector('form').addEventListener('submit', function(e) {
            const namaBarang = document.getElementById('nama_barang').value.trim();
            const feedbackElement = document.getElementById('nama-barang-feedback');
            
            // Jika ingin memblokir submit jika barang sudah ada
            if (feedbackElement.textContent.includes('sudah terdaftar')) {
                e.preventDefault();
                alert('Barang dengan nama ini sudah terdaftar. Silahkan gunakan nama lain.');
                document.getElementById('nama_barang').focus();
            }
        });
    </script>
</body>
</html>