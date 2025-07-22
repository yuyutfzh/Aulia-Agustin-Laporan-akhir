<?php
session_start();

// Cek apakah user sudah login dan adalah admin
if (!isset($_SESSION['nip']) || $_SESSION['level'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Ambil data session
$nama = $_SESSION['nama_lengkap'];
$level = $_SESSION['level'];

// Koneksi ke database
$host = "localhost";
$user = "root";
$password = "";
$dbname = "monvest_db";

$conn = new mysqli($host, $user, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil foto profil user yang sedang login
$profile_foto = null;
$profile_sql = "SELECT foto FROM user WHERE nip = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("s", $_SESSION['nip']);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
if ($profile_row = $profile_result->fetch_assoc()) {
    $profile_foto = $profile_row['foto'];
}
$profile_stmt->close();

$success = "";
$error = "";

// Handle upload foto
$foto_path = NULL;
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/photos/';
    
    // Buat direktori jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_tmp = $_FILES['foto']['tmp_name'];
    $file_name = $_FILES['foto']['name'];
    $file_size = $_FILES['foto']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validasi ekstensi file
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_ext, $allowed_ext)) {
        $error = "Format foto tidak diizinkan! Gunakan JPG, JPEG, PNG, atau GIF.";
    } else if ($file_size > 2 * 1024 * 1024) { // Max 2MB
        $error = "Ukuran foto terlalu besar! Maksimal 2MB.";
    } else {
        // Generate nama file unik menggunakan NIP dari form
        $nip_for_filename = isset($_POST['nip']) ? trim($_POST['nip']) : 'temp';
        $new_filename = $nip_for_filename . '_' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $upload_path)) {
            $foto_path = $upload_path;
        } else {
            $error = "Gagal mengupload foto!";
        }
    }
}

// Cek jika form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    // Ambil dan bersihkan data input
    $nip = trim($_POST['nip']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $tempat_lahir = trim($_POST['tempat_lahir']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $alamat = trim($_POST['alamat']);
    $jabatan = trim($_POST['jabatan']);
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']);
    $password_plain = $_POST['password'];
    $level_user = $_POST['level'];

    // Validasi input wajib
    if (empty($nip) || empty($nama_lengkap) || empty($password_plain) || empty($level_user)) {
        $error = "NIP, Nama Lengkap, Password, dan Level wajib diisi!";
    } else {
        // Validasi NIP unik
        $check_sql = "SELECT nip FROM user WHERE nip = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $nip);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "NIP sudah terdaftar!";
            // Hapus foto yang sudah diupload jika NIP sudah ada
            if ($foto_path && file_exists($foto_path)) {
                unlink($foto_path);
            }
        } else {
            // Hash password
            $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
            
            // Set nilai default untuk field kosong
            $tempat_lahir = empty($tempat_lahir) ? NULL : $tempat_lahir;
            $tanggal_lahir = empty($tanggal_lahir) ? NULL : $tanggal_lahir;
            $alamat = empty($alamat) ? NULL : $alamat;
            $jabatan = empty($jabatan) ? NULL : $jabatan;
            $email = empty($email) ? NULL : $email;
            $no_hp = empty($no_hp) ? NULL : $no_hp;

            // Query insert dengan created_at menggunakan current timestamp
            $sql = "INSERT INTO user (nip, nama_lengkap, tempat_lahir, tanggal_lahir, alamat, jabatan, email, no_hp, foto, password, level, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssss", $nip, $nama_lengkap, $tempat_lahir, $tanggal_lahir, $alamat, $jabatan, $email, $no_hp, $foto_path, $password_hashed, $level_user);

            if ($stmt->execute()) {
                $success = "Akun berhasil dibuat!";
                // Reset form data after success
                $nip = $nama_lengkap = $tempat_lahir = $tanggal_lahir = $alamat = $jabatan = $email = $no_hp = $level_user = "";
            } else {
                $error = "Gagal membuat akun: " . $stmt->error;
                // Hapus foto yang sudah diupload jika gagal insert
                if ($foto_path && file_exists($foto_path)) {
                    unlink($foto_path);
                }
            }

            $stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun - MONVEST</title>
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
            gap: 15px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 25px;
            transition: background 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .user-avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
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
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.2);
            padding: 1px 6px;
            border-radius: 8px;
            margin-top: 2px;
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
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 1rem;
        }

        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }

        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4f46e5;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .required::after {
            content: " *";
            color: #ef4444;
        }

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
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.6);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }

        /* Photo Upload Styles */
        .photo-upload-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            padding: 20px;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            background: #f9fafb;
            transition: all 0.3s ease;
        }

        .photo-upload-container.dragover {
            border-color: #4f46e5;
            background: #f0f0ff;
        }

        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e5e7eb;
            display: none;
        }

        .photo-preview.show {
            display: block;
        }

        .photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 2rem;
        }

        .upload-text {
            text-align: center;
            color: #6b7280;
        }

        .upload-text h4 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: #374151;
        }

        .upload-text p {
            font-size: 0.85rem;
            margin-bottom: 10px;
        }

        .file-input-wrapper {
    position: relative;
    width: max-content;
    height: max-content;
}

.file-input-wrapper input[type="file"] {
    position: center;
    top: 0
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
}


        .file-input-btn {
            background: #4f46e5;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .file-input-btn:hover {
            background: #4338ca;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        .modal-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .modal-header svg {
            width: 32px;
            height: 32px;
            fill: white;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }

        .modal-body {
            padding: 30px;
            text-align: center;
        }

        .modal-message {
            font-size: 1.1rem;
            color: #374151;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.6);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                flex-direction: column;
            }

            .modal-content {
                margin: 20% auto;
                width: 95%;
            }

            .modal-body {
                padding: 20px;
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
            </div>

            <div class="menu-section">
                <a href="buat_akun.php" class="menu-item active">
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
                <a href="dashboard.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,3V9H21V3M13,21H21V11H13M3,21H11V15H3M3,13H11V3H3V13Z"/>
                    </svg>
                    Dashboard
                </a>
            <div class="menu-section">
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

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <svg viewBox="0 0 24 24">
                    <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z"/>
                </svg>
                <h3 class="modal-title">Berhasil!</h3>
            </div>
            <div class="modal-body">
                <p class="modal-message">Akun berhasil dibuat dengan sukses.</p>
                <div class="modal-actions">
                    <button class="btn btn-success" onclick="closeModal()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Buat Akun Baru</h1>
            <p class="page-subtitle">Tambah akun pengguna baru ke dalam sistem MONVEST</p>
        </div>

        <div class="form-container">
            <?php if (!empty($success)): ?>
                <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
                <script>
                    document.getElementById('successModal').style.display = 'block';
                </script>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="createAccountForm">
                <div class="form-grid">
                    <!-- Foto Profil -->
                    <div class="form-group full-width">
                        <label>Foto Profil</label>
                        <div class="photo-upload-container" id="photoUploadContainer">
                            <img id="photoPreview" class="photo-preview" src="" alt="Preview">
                            <div class="photo-placeholder" id="photoPlaceholder">
                                <svg viewBox="0 0 24 24" width="48" height="48">
                                    <path fill="currentColor" d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                                </svg>
                            </div>
                            <div class="upload-text">
                                <h4>Upload Foto Profil</h4>
                                <p>Pilih file gambar (JPG, PNG, GIF) - Max 2MB</p>
                                <div class="file-input-wrapper">
                                    <div class="file-input-btn">Pilih File</div>
                                    <input type="file" name="foto" id="foto" accept="image/*" onchange="previewPhoto(this)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- NIP -->
                    <div class="form-group">
                        <label for="nip" class="required">NIP</label>
                        <input type="text" id="nip" name="nip" value="<?php echo isset($nip) ? htmlspecialchars($nip) : ''; ?>" required maxlength="20">
                    </div>

                    <!-- Nama Lengkap -->
                    <div class="form-group">
                        <label for="nama_lengkap" class="required">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo isset($nama_lengkap) ? htmlspecialchars($nama_lengkap) : ''; ?>" required maxlength="100">
                    </div>

                    <!-- Tempat Lahir -->
                    <div class="form-group">
                        <label for="tempat_lahir">Tempat Lahir</label>
                        <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?php echo isset($tempat_lahir) ? htmlspecialchars($tempat_lahir) : ''; ?>" maxlength="50">
                    </div>

                    <!-- Tanggal Lahir -->
                    <div class="form-group">
                        <label for="tanggal_lahir">Tanggal Lahir</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo isset($tanggal_lahir) ? htmlspecialchars($tanggal_lahir) : ''; ?>">
                    </div>

                    <!-- Alamat -->
                    <div class="form-group full-width">
                        <label for="alamat">Alamat</label>
                        <textarea id="alamat" name="alamat" placeholder="Masukkan alamat lengkap..."><?php echo isset($alamat) ? htmlspecialchars($alamat) : ''; ?></textarea>
                    </div>

                    <!-- Jabatan -->
                    <div class="form-group">
                        <label for="jabatan">Jabatan</label>
                        <input type="text" id="jabatan" name="jabatan" value="<?php echo isset($jabatan) ? htmlspecialchars($jabatan) : ''; ?>" maxlength="100">
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" maxlength="100">
                    </div>

                    <!-- No HP -->
                    <div class="form-group">
                        <label for="no_hp">No. HP</label>
                        <input type="tel" id="no_hp" name="no_hp" value="<?php echo isset($no_hp) ? htmlspecialchars($no_hp) : ''; ?>" maxlength="15">
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="required">Password</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>

                    <!-- Level -->
                    <div class="form-group">
                        <label for="level" class="required">Level Akses</label>
                        <select id="level" name="level" required>
                            <option value="">Pilih Level</option>
                            <option value="admin" <?php echo (isset($level_user) && $level_user == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            <option value="kepala_dinas" <?php echo (isset($level_user) && $level_user == 'kepala_dinas') ? 'selected' : ''; ?>>Kepala Dinas</option>
                            <option value="petugas" <?php echo (isset($level_user) && $level_user == 'petugas') ? 'selected' : ''; ?>>Petugas</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">
                        <svg viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M12.5,8C13.3,8 14,8.7 14,9.5V11H16V9.5C16,7.6 14.4,6 12.5,6S9,7.6 9,9.5V11H10V9.5C10,8.7 10.7,8 11.5,8H12.5M6,20V10H18V20H6Z"/>
                        </svg>
                        Reset Form
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M15,14C12.33,14 7,15.33 7,18V20H23V18C23,15.33 17.67,14 15,14M6,10V7H4V10H1V12H4V15H6V12H9V10M15,12A4,4 0 0,0 19,8A4,4 0 0,0 15,4A4,4 0 0,0 11,8A4,4 0 0,0 15,12Z"/>
                        </svg>
                        Buat Akun
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Toggle Sidebar
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

        // Photo Preview
        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            const placeholder = document.getElementById('photoPlaceholder');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.add('show');
                    placeholder.style.display = 'none';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Drag and Drop functionality
        const uploadContainer = document.getElementById('photoUploadContainer');
        
        uploadContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadContainer.classList.add('dragover');
        });
        
        uploadContainer.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadContainer.classList.remove('dragover');
        });
        
        uploadContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadContainer.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('foto');
                fileInput.files = files;
                previewPhoto(fileInput);
            }
        });

        // Modal functions
        function closeModal() {
            document.getElementById('successModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('successModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Form validation
        document.getElementById('createAccountForm').addEventListener('submit', function(e) {
            const nip = document.getElementById('nip').value.trim();
            const namaLengkap = document.getElementById('nama_lengkap').value.trim();
            const password = document.getElementById('password').value;
            const level = document.getElementById('level').value;
            
            if (!nip || !namaLengkap || !password || !level) {
                e.preventDefault();
                alert('NIP, Nama Lengkap, Password, dan Level wajib diisi!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password minimal 6 karakter!');
                return false;
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);

        // Responsive sidebar handling
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.querySelector('.overlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                
                if (!sidebar.classList.contains('collapsed')) {
                    mainContent.classList.remove('expanded');
                }
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on first input
            document.getElementById('nip').focus();
            
            // Set current date as max for birth date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('tanggal_lahir').setAttribute('max', today);
        });
    </script>
</body>
</html>