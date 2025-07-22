<?php
session_start();
require 'config.php';

$level   = $_SESSION['level'] ?? 'guest';
$nama    = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama'] ?? 'User');

$success = "";
$error   = "";


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

// Proses form submission (hanya untuk admin)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $level == 'admin') {
    $aset_id = intval($_POST['aset_id']);
    $tanggal_pemusnahan = $_POST['tanggal_pemusnahan'];
    $alasan = trim($_POST['alasan']);
    $nip_petugas = isset($_SESSION['nip']) ? $_SESSION['nip'] : $_POST['nip_petugas'];

    $bukti_pemusnahan = "";
    if (isset($_FILES['bukti_pemusnahan']) && $_FILES['bukti_pemusnahan']['error'] === 0) {
        $file_tmp = $_FILES['bukti_pemusnahan']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['bukti_pemusnahan']['name']);
        $target_dir = "uploads/";
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            $bukti_pemusnahan = $file_name;
        } else {
            $error = "Upload bukti pemusnahan gagal!";
        }
    }

    if (!$error) {
        // Status awal adalah 'diajukan' untuk menunggu persetujuan kepala dinas
        $sql = "INSERT INTO pemusnahan_aset (aset_id, tanggal_pemusnahan, alasan, bukti_pemusnahan, nip_petugas, status)
                VALUES (?, ?, ?, ?, ?, 'diajukan')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $aset_id, $tanggal_pemusnahan, $alasan, $bukti_pemusnahan, $nip_petugas);

        if ($stmt->execute()) {
            $success = "Pengajuan pemusnahan aset berhasil dikirim dan menunggu persetujuan kepala dinas.";

            // Ambil nama aset
            $aset_q = $conn->query("SELECT nama_barang, kode_barang FROM aset_barang WHERE id = $aset_id");
            $aset = $aset_q->fetch_assoc();
            $nama_barang = $aset['nama_barang'];
            $kode_barang = $aset['kode_barang'];

            $aktivitas = "Mengajukan pemusnahan aset: $nama_barang (Kode: $kode_barang)";
            $tanggal = $tanggal_pemusnahan;

            logAktivitas($conn, $nip_petugas, $aktivitas);
            catatRiwayatPerubahan($conn, $aset_id, $tanggal, $aktivitas, $nip_petugas);
        } else {
            $error = "Gagal menyimpan data pemusnahan: " . $stmt->error;
        }
        $stmt->close();
    }
    $conn->close();
}

// Proses persetujuan/penolakan (hanya untuk kepala dinas)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $level == 'kepala_dinas' && isset($_POST['action_type'])) {
    $pemusnahan_id = intval($_POST['pemusnahan_id']);
    $action = $_POST['action_type']; // 'diterima' atau 'ditolak'
    

    // Update status pemusnahan
  $sql = "UPDATE pemusnahan_aset SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $action, $pemusnahan_id);



    if ($stmt->execute()) {
        // Ambil data pemusnahan untuk log
        $pemusnahan_q = $conn->query("SELECT p.*, a.nama_barang, a.kode_barang FROM pemusnahan_aset p 
                                     JOIN aset_barang a ON p.aset_id = a.id WHERE p.id = $pemusnahan_id");
        $pemusnahan_data = $pemusnahan_q->fetch_assoc();

        if ($action == 'diterima') {
            // Jika diterima, update is_deleted pada aset_barang
            $conn->query("UPDATE aset_barang SET is_deleted = 1 WHERE id = " . $pemusnahan_data['aset_id']);
            $success = "Pengajuan pemusnahan aset berhasil disetujui. Aset telah dihapus dari sistem.";
            $aktivitas = "Menyetujui pemusnahan aset: " . $pemusnahan_data['nama_barang'] . " (Kode: " . $pemusnahan_data['kode_barang'] . ")";
        } else {
            $success = "Pengajuan pemusnahan aset berhasil ditolak.";
            $aktivitas = "Menolak pemusnahan aset: " . $pemusnahan_data['nama_barang'] . " (Kode: " . $pemusnahan_data['kode_barang'] . ")";
        }

    
    } else {
        $error = "Gagal memproses persetujuan: " . $stmt->error;
    }
    $stmt->close();
}

// Ambil data session untuk navbar
$nama = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : (isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin');
$level = isset($_SESSION['level']) ? $_SESSION['level'] : 'admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemusnahan Aset - MONVEST</title>
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
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 1rem;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
        }

        .form-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-body {
            padding: 2rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .btn-secondary {
            background-color: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background-color: #e5e7eb;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-diajukan {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-diterima {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-ditolak {
            background-color: #fee2e2;
            color: #dc2626;
        }

        /* Table styles */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tr:hover {
            background-color: #f9fafb;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 12px 12px 0 0;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1rem 2rem 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .close {
            color: white;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            opacity: 0.7;
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

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
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
                <a href="dashboard.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,3V9H21V3M13,21H21V11H13M3,21H11V15H3M3,13H11V3H3V13Z"/>
                    </svg>
                    Dashboard
                </a>
                <a href="input_aset.php" class="menu-item">
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
                <a href="pemusnahan_aset.php" class="menu-item active">
                    <svg viewBox="0 0 24 24">
                        <path d="M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M7,6H17V19H7V6M9,8V17H11V8H9M13,8V17H15V8H13Z"/>
                    </svg>
                    Pemusnahan Aset
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
                <a href="profile.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                    </svg>
                    Profile
                </a>
            </div>
            <?php endif; ?>

            <?php if ($level == 'kepala_dinas'): ?>
            <div class="menu-section">
                <a href="dashboard.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,3V9H21V3M13,21H21V11H13M3,21H11V15H3M3,13H11V3H3V13Z"/>
                    </svg>
                    Dashboard
                </a>
                <a href="kelola_inventaris.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8Z"/>
                    </svg>
                    Inventaris
                </a>
                <a href="data_akun.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M16,13C15.71,13 15.38,13 15.03,13.05C16.19,13.89 17,15 17,16.5V19H23V16.5C23,14.17 18.33,13 16,13M8,13C5.67,13 1,14.17 1,16.5V19H15V16.5C15,14.17 10.33,13 8,13M8,11A3,3 0 0,0 11,8A3,3 0 0,0 8,5A3,3 0 0,0 5,8A3,3 0 0,0 8,11M16,11A3,3 0 0,0 19,8A3,3 0 0,0 16,5A3,3 0 0,0 13,8A3,3 0 0,0 16,11Z"/>
                    </svg>
                    Kelola Data User
                </a>
                
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
                <a href="pemusnahan_aset.php" class="menu-item active">
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
            <?php endif; ?>

            <div class="logout-item">
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
        <div class="page-header">
            <h1 class="page-title">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
                    <path d="M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M7,6H17V19H7V6M9,8V17H11V8H9M13,8V17H15V8H13Z"/>
                </svg>
                <?php echo ($level == 'kepala_dinas') ? 'Persetujuan Pemusnahan Aset' : 'Pemusnahan Aset'; ?>
            </h1>
            <p class="page-subtitle">
                <?php echo ($level == 'kepala_dinas') ? 'Kelola persetujuan pemusnahan aset yang diajukan' : 'Kelola pemusnahan aset barang inventaris'; ?>
            </p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z"/>
                </svg>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M13,13H11V7H13M13,17H11V15H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                </svg>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($level == 'admin'): ?>
        <!-- Form Pengajuan Pemusnahan (Hanya untuk Admin) -->
        <div class="form-container">
            <div class="form-header">
                <h3>
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                        <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                    Form Pengajuan Pemusnahan Aset
                </h3>
            </div>
            <div class="form-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4Z"/>
                            </svg>
                            Pilih Aset yang akan dimusnahkan
                        </label>
                        <select name="aset_id" class="form-control" required>
                            <option value="">-- Pilih Aset --</option>
                            <?php
                            $aset_query = $conn->query("SELECT id, kode_barang, nama_barang FROM aset_barang WHERE is_deleted = 0 ORDER BY nama_barang");
                            while ($aset = $aset_query->fetch_assoc()):
                            ?>
                                <option value="<?php echo $aset['id']; ?>">
                                    <?php echo $aset['kode_barang'] . ' - ' . $aset['nama_barang']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M19,19H5V8H19M16,1V3H8V1H6V3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3H18V1M17,12H12V17H17V12Z"/>
                            </svg>
                            Tanggal Pemusnahan
                        </label>
                        <input type="date" name="tanggal_pemusnahan" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                            </svg>
                            Alasan Pemusnahan
                        </label>
                        <textarea name="alasan" class="form-control" rows="4" placeholder="Jelaskan alasan mengapa aset ini perlu dimusnahkan..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                            </svg>
                            Bukti Pemusnahan (Opsional)
                        </label>
                        <input type="file" name="bukti_pemusnahan" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        <small style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;">
                            File yang diizinkan: JPG, PNG, PDF, DOC, DOCX (Max: 5MB)
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M9,22A1,1 0 0,1 8,21V18H4A2,2 0 0,1 2,16V4C2,2.89 2.9,2 4,2H20A2,2 0 0,1 22,4V16A2,2 0 0,1 20,18H13.9L10.2,21.71C10,21.9 9.75,22 9.5,22V22H9Z"/>
                        </svg>
                        Ajukan Pemusnahan Aset
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabel Daftar Pengajuan Pemusnahan -->
        <div class="form-container">
            <div class="form-header">
                <h3>
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                        <path d="M3,3H21V5H3V3M3,7H21V9H3V7M3,11H21V13H3V11M3,15H21V17H3V15M3,19H21V21H3V19Z"/>
                    </svg>
                    <?php echo ($level == 'kepala_dinas') ? 'Daftar Pengajuan Pemusnahan' : 'Riwayat Pengajuan Pemusnahan'; ?>
                </h3>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Petugas</th>
                            <?php if ($level == 'kepala_dinas'): ?>
                            <th>Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, a.kode_barang, a.nama_barang, u.nama_lengkap
        FROM pemusnahan_aset p
        JOIN aset_barang a ON p.aset_id = a.id
        LEFT JOIN user u ON p.nip_petugas = u.nip ";

if ($level == 'kepala_dinas') {
    // Kepala dinas hanya melihat yang menunggu persetujuan
    $sql .= "WHERE p.status = 'diajukan' ";
} else {
    // Admin/operator melihat yang sudah diproses
    $sql .= "WHERE p.status IN ('diterima', 'ditolak') ";
}

$sql .= "ORDER BY p.tanggal_pemusnahan DESC";

$result = $conn->query($sql);

                        $no = 1;
                        
                        if ($result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($row['kode_barang']); ?></td>
                                <td><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['tanggal_pemusnahan'])); ?></td>
                                <td><?php echo htmlspecialchars(substr($row['alasan'], 0, 50)) . (strlen($row['alasan']) > 50 ? '...' : ''); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['nama_lengkap'] ?: $row['nip_petugas']); ?></td>
                                <?php if ($level == 'kepala_dinas'): ?>
                                <td>
                                    <button class="btn btn-success" onclick="showModal(<?php echo $row['id']; ?>, 'diterima', '<?php echo htmlspecialchars($row['nama_barang']); ?>')">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                            <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z"/>
                                        </svg>
                                        Terima
                                    </button>
                                    <button class="btn btn-danger" onclick="showModal(<?php echo $row['id']; ?>, 'ditolak', '<?php echo htmlspecialchars($row['nama_barang']); ?>')">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                            <path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                                        </svg>
                                        Tolak
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="<?php echo ($level == 'kepala_dinas') ? '8' : '7'; ?>" style="text-align: center; padding: 2rem; color: #6b7280;">
                                    <?php echo ($level == 'kepala_dinas') ? 'Tidak ada pengajuan pemusnahan yang menunggu persetujuan' : 'Belum ada pengajuan pemusnahan'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Persetujuan (Hanya untuk Kepala Dinas) -->
    <?php if ($level == 'kepala_dinas'): ?>
    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="modalTitle">Konfirmasi Persetujuan</h4>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="approvalForm">
                <div class="modal-body">
                    <input type="hidden" name="pemusnahan_id" id="pemusnahan_id">
                    <input type="hidden" name="action_type" id="action_type">
                    
                    <p id="confirmationText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn" id="confirmBtn">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }

        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('mobile-open');
            }
        }

        // Mobile responsive
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            } else {
                sidebar.classList.remove('mobile-open');
            }
        });

        // Toggle mobile sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        }

        <?php if ($level == 'kepala_dinas'): ?>
        function showModal(pemusnahanId, action, namaBarang) {
            const modal = document.getElementById('approvalModal');
            const modalTitle = document.getElementById('modalTitle');
            const confirmationText = document.getElementById('confirmationText');
            const confirmBtn = document.getElementById('confirmBtn');
            const actionType = document.getElementById('action_type');
            const pemusnahanIdInput = document.getElementById('pemusnahan_id');
            
            pemusnahanIdInput.value = pemusnahanId;
            actionType.value = action;
            
            if (action === 'diterima') {
                modalTitle.textContent = 'Konfirmasi Persetujuan';
                confirmationText.textContent = `Apakah Anda yakin ingin menyetujui pemusnahan aset "${namaBarang}"? Aset akan dihapus dari sistem.`;
                confirmBtn.textContent = 'Setujui';
                confirmBtn.className = 'btn btn-success';
            } else {
                modalTitle.textContent = 'Konfirmasi Penolakan';
                confirmationText.textContent = `Apakah Anda yakin ingin menolak pemusnahan aset "${namaBarang}"?`;
                confirmBtn.textContent = 'Tolak';
                confirmBtn.className = 'btn btn-danger';
            }
            
            modal.style.display = 'block';
        }

        function closeModal() {
            const modal = document.getElementById('approvalModal');
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('approvalModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        <?php endif; ?>
    </script>
</body>
</html>