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

// Ambil data log aktivitas dengan join ke tabel user
$result = $conn->query("SELECT l.*, u.nama_lengkap 
                        FROM log_aktivitas l 
                        LEFT JOIN user u ON l.nip = u.nip 
                        ORDER BY waktu DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas - MONVEST</title>
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

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 1rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: #4f46e5;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon.blue { background: #dbeafe; color: #3b82f6; }
        .stat-icon.green { background: #dcfce7; color: #16a34a; }
        .stat-icon.purple { background: #ede9fe; color: #8b5cf6; }

        .stat-icon svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.85rem;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
        }

        .filter-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #374151;
            background: white;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
            vertical-align: top;
        }

        tr:hover {
            background: #f9fafb;
        }

        .row-number {
            width: 60px;
            text-align: center;
            font-weight: 600;
            color: #6b7280;
        }

        .datetime-cell {
            white-space: nowrap;
            font-family: 'Courier New', monospace;
            color: #4b5563;
            min-width: 150px;
        }

        .datetime-date {
            font-weight: 600;
            color: #1f2937;
        }

        .datetime-time {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 2px;
        }

        .nip-cell {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #4b5563;
            display: inline-block;
        }

        .user-name {
            font-weight: 500;
            color: #1f2937;
        }

        .activity-cell {
            max-width: 400px;
            line-height: 1.5;
        }

        .activity-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .activity-login { background: #dcfce7; color: #166534; }
        .activity-logout { background: #fef2f2; color: #dc2626; }
        .activity-create { background: #dbeafe; color: #1d4ed8; }
        .activity-update { background: #fef3c7; color: #d97706; }
        .activity-delete { background: #fce7f3; color: #be185d; }
        .activity-default { background: #f3f4f6; color: #374151; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            fill: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 8px;
            color: #374151;
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

            .stats-row {
                grid-template-columns: 1fr;
            }

            th, td {
                padding: 10px;
                font-size: 0.85rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .filter-container {
                width: 100%;
                justify-content: flex-end;
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
                <a href="log_aktivitas.php" class="menu-item active">
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
                <a href="log_aktivitas.php" class="menu-item active">
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
                <a href="profile.php" class="menu-item ">
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
            <h1 class="page-title">Log Aktivitas Sistem</h1>
            <p class="page-subtitle">Monitor semua aktivitas pengguna dalam sistem MONVEST</p>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>›</span>
                <span>Log Aktivitas</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24">
                            <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo $result ? $result->num_rows : 0; ?></div>
                        <div class="stat-label">Total Aktivitas</div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24">
                            <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M11,16.5L18,9.5L16.59,8.09L11,13.67L7.91,10.59L6.5,12L11,16.5Z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-number">
                            <?php 
                            $today_query = $conn->query("SELECT COUNT(*) as count FROM log_aktivitas WHERE DATE(waktu) = CURDATE()");
                            $today_count = $today_query ? $today_query->fetch_assoc()['count'] : 0;
                            echo $today_count;
                            ?>
                        </div>
                        <div class="stat-label">Aktivitas Hari Ini</div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24">
                            <path d="M16,12A2,2 0 0,1 18,10A2,2 0 0,1 20,12A2,2 0 0,1 18,14A2,2 0 0,1 16,12M10,12A2,2 0 0,1 12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12M4,12A2,2 0 0,1 6,10A2,2 0 0,1 8,12A2,2 0 0,1 6,14A2,2 0 0,1 4,12Z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-number">
                            <?php 
                            $user_query = $conn->query("SELECT COUNT(DISTINCT nip) as count FROM log_aktivitas");
                            $user_count = $user_query ? $user_query->fetch_assoc()['count'] : 0;
                            echo $user_count;
                            ?>
                        </div>
                        <div class="stat-label">Pengguna Aktif</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title">Riwayat Aktivitas Pengguna</h3>
                <div class="filter-container">
                    <select class="filter-select" onchange="filterByDate(this.value)">
                        <option value="">Semua Waktu</option>
                        <option value="today">Hari Ini</option>
                        <option value="week">7 Hari Terakhir</option>
                        <option value="month">30 Hari Terakhir</option>
                    </select>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="row-number">No</th>
                            <th>Waktu</th>
                            <th>NIP</th>
                            <th>Nama Pengguna</th>
                            <th>Aktivitas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php 
                            $no = 1;
                            while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="row-number"><?= $no++ ?></td>
                                    <td class="datetime-cell">
                                        <div class="datetime-date"><?= date('d/m/Y', strtotime($row['waktu'])) ?></div>
                                        <div class="datetime-time"><?= date('H:i:s', strtotime($row['waktu'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="nip-cell"><?= htmlspecialchars($row['nip']) ?></span>
                                    </td>
                                    <td>
                                        <div class="user-name"><?= htmlspecialchars($row['nama_lengkap'] ?? 'User Tidak Ditemukan') ?></div>
                                    </td>
                                    <td class="activity-cell">
                                        <?php
                                        $aktivitas = strtolower($row['aktivitas']);
                                        $class = 'activity-default';
                                        
                                        if (strpos($aktivitas, 'login') !== false) {
                                            $class = 'activity-login';
                                        } elseif (strpos($aktivitas, 'logout') !== false) {
                                            $class = 'activity-logout';
                                        } elseif (strpos($aktivitas, 'tambah') !== false || strpos($aktivitas, 'create') !== false || strpos($aktivitas, 'buat') !== false) {
                                            $class = 'activity-create';
                                        } elseif (strpos($aktivitas, 'update') !== false || strpos($aktivitas, 'ubah') !== false || strpos($aktivitas, 'edit') !== false) {
                                            $class = 'activity-update';
                                        } elseif (strpos($aktivitas, 'delete') !== false || strpos($aktivitas, 'hapus') !== false) {
                                            $class = 'activity-delete';
                                        }
                                        ?>
                                        <span class="activity-type <?= $class ?>">
                                            <?php
                                            if ($class == 'activity-login') echo 'LOGIN';
                                            elseif ($class == 'activity-logout') echo 'LOGOUT';
                                            elseif ($class == 'activity-create') echo 'CREATE';
                                            elseif ($class == 'activity-update') echo 'UPDATE';
                                            elseif ($class == 'activity-delete') echo 'DELETE';
                                            else echo 'SYSTEM';
                                            ?>
                                        </span>
                                        <div style="margin-top: 5px;">
                                            <?= htmlspecialchars($row['aktivitas']) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M12,2C13.1,2 14,2.9 14,4C14,5.1 13.1,6 12,6C10.9,6 10,5.1 10,4C10,2.9 10.9,2 12,2M21,9V7L15,1H5C3.89,1 3,1.89 3,3V21A2,2 0 0,0 5,23H19A2,2 0 0,0 21,21V9M19,9H14V4H5V21H19V9Z"/>
                                        </svg>
                                        <h3>Tidak Ada Data</h3>
                                        <p>Belum ada aktivitas yang tercatat dalam sistem</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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

        // Filter by date
        function filterByDate(period) {
            const currentUrl = new URL(window.location);
            
            if (period) {
                currentUrl.searchParams.set('filter', period);
            } else {
                currentUrl.searchParams.delete('filter');
            }
            
            window.location.href = currentUrl.toString();
        }

        // Handle responsive
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('mobile-open');
                document.querySelector('.overlay').classList.remove('show');
            }
        });

        // Auto refresh setiap 30 detik
        setInterval(function() {
            location.reload();
        }, 30000);

        // Highlight new entries
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            const now = new Date();
            
            rows.forEach(function(row) {
                const dateCell = row.querySelector('.datetime-cell .datetime-date');
                const timeCell = row.querySelector('.datetime-cell .datetime-time');
                
                if (dateCell && timeCell) {
                    const dateText = dateCell.textContent;
                    const timeText = timeCell.textContent;
                    
                    // Parse tanggal dari format dd/mm/yyyy
                    const dateParts = dateText.split('/');
                    const timeParts = timeText.split(':');
                    
                    if (dateParts.length === 3 && timeParts.length === 3) {
                        const rowDate = new Date(
                            parseInt(dateParts[2]), // year
                            parseInt(dateParts[1]) - 1, // month (0-indexed)
                            parseInt(dateParts[0]), // day
                            parseInt(timeParts[0]), // hour
                            parseInt(timeParts[1]), // minute
                            parseInt(timeParts[2])  // second
                        );
                        
                        // Highlight jika kurang dari 5 menit yang lalu
                        const diffMinutes = (now - rowDate) / (1000 * 60);
                        if (diffMinutes < 5 && diffMinutes >= 0) {
                            row.style.backgroundColor = '#fef3c7';
                            row.style.borderLeft = '4px solid #f59e0b';
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>

