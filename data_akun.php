
<?php
session_start();

// Cek apakah user sudah login dan memiliki level admin
if (!isset($_SESSION['nip']) || !in_array($_SESSION['level'], ['admin', 'kepala_dinas'])) {
    header("Location: login.php");
    exit();
}


// Koneksi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "monvest_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Ambil data session
$nama = $_SESSION['nama_lengkap'];
$level = $_SESSION['level'];
$nip_admin = $_SESSION['nip'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $response = array();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'get_user':
                $nip = $_POST['nip'];
                $stmt = $pdo->prepare("SELECT * FROM user WHERE nip = ?");
                $stmt->execute([$nip]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($user);
                exit();
                
            case 'update_user':
                try {
                    $nip = $_POST['nip'];
                    $nama_lengkap = $_POST['nama_lengkap'];
                    $tempat_lahir = $_POST['tempat_lahir'];
                    $tanggal_lahir = $_POST['tanggal_lahir'];
                    $alamat = $_POST['alamat'];
                    $jabatan = $_POST['jabatan'];
                    $email = $_POST['email'];
                    $no_hp = $_POST['no_hp'];
                    $user_level = $_POST['level'];
                    
                    // Ambil data lama untuk riwayat
                    $stmt = $pdo->prepare("SELECT * FROM user WHERE nip = ?");
                    $stmt->execute([$nip]);
                    $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Update user
                    $stmt = $pdo->prepare("UPDATE user SET nama_lengkap = ?, tempat_lahir = ?, tanggal_lahir = ?, alamat = ?, jabatan = ?, email = ?, no_hp = ?, level = ? WHERE nip = ?");
                    $stmt->execute([$nama_lengkap, $tempat_lahir, $tanggal_lahir, $alamat, $jabatan, $email, $no_hp, $user_level, $nip]);
                    
                    // Log riwayat perubahan - Cek apakah tabel memiliki kolom asset_id
                    $perubahan = "Update data user: $nama_lengkap (NIP: $nip)";
                    
                    // Cek struktur tabel riwayat_perubahan
                    $checkColumns = $pdo->query("SHOW COLUMNS FROM riwayat_perubahan");
                    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (in_array('asset_id', $columns)) {
                        // Jika ada kolom asset_id
                        $stmt = $pdo->prepare("INSERT INTO riwayat_perubahan (asset_id, tanggal_perubahan, perubahan, nip_petugas) VALUES (?, NOW(), ?, ?)");
                        $stmt->execute([0, $perubahan, $nip_admin]);
                    } else {
                        // Jika tidak ada kolom asset_id, sesuaikan dengan struktur yang ada
                        $stmt = $pdo->prepare("INSERT INTO riwayat_perubahan (tanggal_perubahan, perubahan, nip_petugas) VALUES (NOW(), ?, ?)");
                        $stmt->execute([$perubahan, $nip_admin]);
                    }
                    
                    // Log aktivitas
                    $aktivitas = "Mengedit data user: $nama_lengkap (NIP: $nip)";
                    $stmt = $pdo->prepare("INSERT INTO log_aktivitas (nip, aktivitas, waktu) VALUES (?, ?, NOW())");
                    $stmt->execute([$nip_admin, $aktivitas]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Data user berhasil diupdate';
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Error: ' . $e->getMessage();
                }
                echo json_encode($response);
                exit();
                
            case 'delete_user':
                try {
                    $nip = $_POST['nip'];
                    
                    // Ambil nama user untuk log
                    $stmt = $pdo->prepare("SELECT nama_lengkap FROM user WHERE nip = ?");
                    $stmt->execute([$nip]);
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    $nama_user = $user_data['nama_lengkap'];
                    
                    // Hapus user
                    $stmt = $pdo->prepare("DELETE FROM user WHERE nip = ?");
                    $stmt->execute([$nip]);
                    
                    // Log riwayat perubahan - Cek apakah tabel memiliki kolom asset_id
                    $perubahan = "Hapus data user: $nama_user (NIP: $nip)";
                    
                    // Cek struktur tabel riwayat_perubahan
                    $checkColumns = $pdo->query("SHOW COLUMNS FROM riwayat_perubahan");
                    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (in_array('asset_id', $columns)) {
                        // Jika ada kolom asset_id
                        $stmt = $pdo->prepare("INSERT INTO riwayat_perubahan (asset_id, tanggal_perubahan, perubahan, nip_petugas) VALUES (?, NOW(), ?, ?)");
                        $stmt->execute([0, $perubahan, $nip_admin]);
                    } else {
                        // Jika tidak ada kolom asset_id, sesuaikan dengan struktur yang ada
                        $stmt = $pdo->prepare("INSERT INTO riwayat_perubahan (tanggal_perubahan, perubahan, nip_petugas) VALUES (NOW(), ?, ?)");
                        $stmt->execute([$perubahan, $nip_admin]);
                    }
                    
                    // Log aktivitas
                    $aktivitas = "Menghapus data user: $nama_user (NIP: $nip)";
                    $stmt = $pdo->prepare("INSERT INTO log_aktivitas (nip, aktivitas, waktu) VALUES (?, ?, NOW())");
                    $stmt->execute([$nip_admin, $aktivitas]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Data user berhasil dihapus';
                } catch (Exception $e) {
                    $response['success'] = false;
                    $response['message'] = 'Error: ' . $e->getMessage();
                }
                echo json_encode($response);
                exit();
        }
    }
}

// Ambil data user
$stmt = $pdo->query("SELECT * FROM user ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data User - MONVEST</title>
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

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 1rem;
        }

        /* Data Table */
        .data-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
        }

        .search-box {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            width: 300px;
            transition: border-color 0.3s ease;
        }

        .search-box:focus {
            outline: none;
            border-color: #4f46e5;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .data-table th {
            background: #f8fafc;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }

        .data-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #374151;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-admin {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-sekretaris {
            background: #dcfce7;
            color: #16a34a;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1f2937;
        }

        .close {
            color: #6b7280;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #374151;
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
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

            .search-box {
                width: 200px;
            }

            .data-table {
                font-size: 0.8rem;
            }

            .data-table th,
            .data-table td {
                padding: 10px;
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

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
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
                <a href="buat_akun.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M15,14C12.33,14 7,15.33 7,18V20H23V18C23,15.33 17.67,14 15,14M6,10V7H4V10H1V12H4V15H6V12H9V10M15,12A4,4 0 0,0 19,8A4,4 0 0,0 15,4A4,4 0 0,0 11,8A4,4 0 0,0 15,12Z"/>
                    </svg>
                    Buat Akun Baru
                </a>
                <a href="data_akun.php" class="menu-item active">
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
                <a href="data_akun.php" class="menu-item active">
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
            <h1 class="page-title">Kelola Data User</h1>
            <p class="page-subtitle">Mengelola data akun pengguna sistem MONVEST</p>
        </div>

        <!-- Data Table -->
        <div class="data-table-container">
            <div class="table-header">
                <h3 class="table-title">Daftar Pengguna</h3>
                <input type="text" class="search-box" id="searchInput" placeholder="Cari berdasarkan nama atau NIP...">
            </div>

            <div id="alertContainer"></div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>NIP</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Jabatan</th>
                        <th>No. HP</th>
                        <th>Level</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['nip']); ?></td>
                        <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                        <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['jabatan'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['no_hp'] ?? '-'); ?></td>
                        <td>
                            <span class="status-badge <?php echo $user['level'] == 'admin' ? 'status-admin' : 'status-sekretaris'; ?>">
                                <?php echo strtoupper($user['level']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="viewUser('<?php echo $user['nip']; ?>')">
                                    <svg viewBox="0 0 24 24" width="16" height="16">
                                        <path fill="currentColor" d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z"/>
                                    </svg>
                                    Lihat
                                </button>
                                <button class="btn btn-warning" onclick="editUser('<?php echo $user['nip']; ?>')">
                                    <svg viewBox="0 0 24 24" width="16" height="16">
                                        <path fill="currentColor" d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z"/>
                                    </svg>
                                    Edit
                                </button>
                                <?php if ($user['nip'] != $nip_admin): ?>
                                <button class="btn btn-danger" onclick="deleteUser('<?php echo $user['nip']; ?>', '<?php echo htmlspecialchars($user['nama_lengkap']); ?>')">
                                    <svg viewBox="0 0 24 24" width="16" height="16">
                                        <path fill="currentColor" d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z"/>
                                    </svg>
                                    Hapus
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal Lihat User -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Detail User</h3>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit User</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="edit_nip" name="nip">
                    
                    <div class="form-group">
                        <label class="form-label">NIP</label>
                        <input type="text" class="form-control" id="edit_nip_display" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap *</label>
                        <input type="text" class="form-control" id="edit_nama_lengkap" name="nama_lengkap" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tempat Lahir</label>
                        <input type="text" class="form-control" id="edit_tempat_lahir" name="tempat_lahir">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" class="form-control" id="edit_tanggal_lahir" name="tanggal_lahir">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" id="edit_alamat" name="alamat" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Jabatan</label>
                        <input type="text" class="form-control" id="edit_jabatan" name="jabatan">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">No. HP</label>
                        <input type="text" class="form-control" id="edit_no_hp" name="no_hp">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Level User *</label>
                        <select class="form-control" id="edit_level" name="level" required>
                            <option value="admin">Admin</option>
                            <option value="kepala_dinas">Kepala Dinas</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Batal</button>
                <button type="button" class="btn btn-primary" onclick="updateUser()">Simpan Perubahan</button>
            </div>
        </div>
    </div>

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

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#userTableBody tr');
            
            tableRows.forEach(row => {
                const nip = row.cells[0].textContent.toLowerCase();
                const nama = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                
                if (nip.includes(searchTerm) || nama.includes(searchTerm) || email.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // View user
        function viewUser(nip) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_user&nip=' + nip
            })
            .then(response => response.json())
            .then(data => {
                if (data) {
                    const modalBody = document.getElementById('viewModalBody');
                    modalBody.innerHTML = `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <strong>NIP:</strong><br>
                                <span>${data.nip || '-'}</span>
                            </div>
                            <div>
                                <strong>Nama Lengkap:</strong><br>
                                <span>${data.nama_lengkap || '-'}</span>
                            </div>
                            <div>
                                <strong>Tempat Lahir:</strong><br>
                                <span>${data.tempat_lahir || '-'}</span>
                            </div>
                            <div>
                                <strong>Tanggal Lahir:</strong><br>
                                <span>${data.tanggal_lahir ? formatDate(data.tanggal_lahir) : '-'}</span>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <strong>Alamat:</strong><br>
                                <span>${data.alamat || '-'}</span>
                            </div>
                            <div>
                                <strong>Jabatan:</strong><br>
                                <span>${data.jabatan || '-'}</span>
                            </div>
                            <div>
                                <strong>Email:</strong><br>
                                <span>${data.email || '-'}</span>
                            </div>
                            <div>
                                <strong>No. HP:</strong><br>
                                <span>${data.no_hp || '-'}</span>
                            </div>
                            <div>
                                <strong>Level:</strong><br>
                                <span class="status-badge ${data.level == 'admin' ? 'status-admin' : 'status-sekretaris'}">
                                    ${data.level ? data.level.toUpperCase() : '-'}
                                </span>
                            </div>
                            <div>
                                <strong>Tanggal Dibuat:</strong><br>
                                <span>${data.created_at ? formatDateTime(data.created_at) : '-'}</span>
                            </div>
                        </div>
                    `;
                    document.getElementById('viewModal').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Terjadi kesalahan saat mengambil data user', 'danger');
            });
        }

        // Edit user
        function editUser(nip) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_user&nip=' + nip
            })
            .then(response => response.json())
            .then(data => {
                if (data) {
                    document.getElementById('edit_nip').value = data.nip;
                    document.getElementById('edit_nip_display').value = data.nip;
                    document.getElementById('edit_nama_lengkap').value = data.nama_lengkap || '';
                    document.getElementById('edit_tempat_lahir').value = data.tempat_lahir || '';
                    document.getElementById('edit_tanggal_lahir').value = data.tanggal_lahir || '';
                    document.getElementById('edit_alamat').value = data.alamat || '';
                    document.getElementById('edit_jabatan').value = data.jabatan || '';
                    document.getElementById('edit_email').value = data.email || '';
                    document.getElementById('edit_no_hp').value = data.no_hp || '';
                    document.getElementById('edit_level').value = data.level || '';
                    
                    document.getElementById('editModal').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Terjadi kesalahan saat mengambil data user', 'danger');
            });
        }

        // Update user
        function updateUser() {
            const form = document.getElementById('editUserForm');
            const formData = new FormData(form);
            formData.append('action', 'update_user');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeModal('editModal');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Terjadi kesalahan saat mengupdate data user', 'danger');
            });
        }

        // Delete user
        function deleteUser(nip, nama) {
            if (confirm(`Apakah Anda yakin ingin menghapus user ${nama}?`)) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_user&nip=' + nip
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Terjadi kesalahan saat menghapus data user', 'danger');
                });
            }
        }

        // Show alert
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;
            
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // Format date helper
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID');
        }

        // Format datetime helper
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID') + ' ' + date.toLocaleTimeString('id-ID');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Responsive sidebar
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.querySelector('.overlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
            }
        });
    </script>
</body>
</html>