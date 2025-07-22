<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['nip'])) {
    header("Location: login.php");
    exit();
}

$nip = $_SESSION['nip'];
$nama = $_SESSION['nama_lengkap'];
$level = $_SESSION['level'];

try {
    // Query untuk mendapatkan data statistik berdasarkan level user
    if ($level == 'admin' || $level == 'kepala_dinas') {
        
        // Total aset
        $query_total = "SELECT COUNT(*) as total FROM aset_barang WHERE is_deleted = 0";
        $result_total = mysqli_query($conn, $query_total);
        $total_aset = mysqli_fetch_assoc($result_total)['total'];

        // Statistik kondisi aset
        $kondisi_data = [];
        $kondisi_list = ['Baik', 'Rusak Ringan', 'Rusak Berat', 'Tidak Diketahui'];
        
        foreach ($kondisi_list as $kondisi) {
            $query_kondisi = "SELECT COUNT(*) as jumlah 
                             FROM aset_barang ab
                             JOIN kondisi k ON ab.kondisi_id = k.id 
                             WHERE k.nama_kondisi = '$kondisi' AND ab.is_deleted = 0";
            $result_kondisi = mysqli_query($conn, $query_kondisi);
            $count = mysqli_fetch_assoc($result_kondisi)['jumlah'];
            
            // Sesuaikan dengan format yang digunakan di HTML
            $key = strtolower(str_replace(' ', '_', $kondisi));
            if ($kondisi == 'Baik') $key = 'baik';
            elseif ($kondisi == 'Rusak Ringan') $key = 'rusak_ringan';
            elseif ($kondisi == 'Rusak Berat') $key = 'rusak_berat';
            
            $kondisi_data[$key] = $count;
        }

        // Total nilai aset (jika ada kolom harga)
        $query_nilai = "SELECT SUM(COALESCE(harga_satuan_perolehan, 0) * COALESCE(jumlah, 1)) as total_nilai 
                       FROM aset_barang WHERE is_deleted = 0";
        $result_nilai = mysqli_query($conn, $query_nilai);
        $total_nilai = mysqli_fetch_assoc($result_nilai)['total_nilai'] ?? 0;

        // Total users
        $query_users = "SELECT COUNT(*) as total FROM user";
        $result_users = mysqli_query($conn, $query_users);
        $total_users = mysqli_fetch_assoc($result_users)['total'];

        // Aktivitas hari ini dari log_aktivitas
        $query_aktivitas_hari_ini = "SELECT COUNT(*) as total 
                                    FROM log_aktivitas 
                                    WHERE DATE(waktu) = CURDATE()";
        $result_aktivitas_hari_ini = mysqli_query($conn, $query_aktivitas_hari_ini);
        $aktivitas_hari_ini = mysqli_fetch_assoc($result_aktivitas_hari_ini)['total'];

        // Top 5 kategori berdasarkan jenis barang
        $query_kategori = "SELECT jb.nama_jenis, COUNT(*) as jumlah 
                          FROM aset_barang ab
                          JOIN jenis_barang jb ON ab.jenis_id = jb.id
                          WHERE ab.is_deleted = 0
                          GROUP BY ab.jenis_id, jb.nama_jenis
                          ORDER BY jumlah DESC 
                          LIMIT 5";
        $result_kategori = mysqli_query($conn, $query_kategori);
        $kategori_data = [];
        while ($row = mysqli_fetch_assoc($result_kategori)) {
            $kategori_data[] = [
                'nama_barang' => $row['nama_jenis'],
                'jumlah' => $row['jumlah']
            ];
        }

        // Aktivitas terbaru dari log_aktivitas dengan join ke user
        $query_aktivitas_terbaru = "SELECT la.*, u.nama_lengkap 
                                   FROM log_aktivitas la
                                   JOIN user u ON la.nip = u.nip
                                   ORDER BY la.waktu DESC 
                                   LIMIT 5";
        $result_aktivitas_terbaru = mysqli_query($conn, $query_aktivitas_terbaru);
        $aktivitas_terbaru = [];
        while ($row = mysqli_fetch_assoc($result_aktivitas_terbaru)) {
            $aktivitas_terbaru[] = [
                'nama_lengkap' => $row['nama_lengkap'],
                'perubahan' => $row['aktivitas'],
                'tanggal_perubahan' => $row['waktu']
            ];
        }

    } elseif ($level == 'petugas') {
        
        // Input hari ini oleh petugas yang login
        $query_input_hari_ini = "SELECT COUNT(*) as total 
                                FROM aset_barang 
                                WHERE nama_petugas = '$nama' 
                                AND DATE(created_at) = CURDATE()
                                AND is_deleted = 0";
        $result_input_hari_ini = mysqli_query($conn, $query_input_hari_ini);
        $input_hari_ini = mysqli_fetch_assoc($result_input_hari_ini)['total'];

        // Input bulan ini oleh petugas yang login
        $query_input_bulan_ini = "SELECT COUNT(*) as total 
                                 FROM aset_barang 
                                 WHERE nama_petugas = '$nama' 
                                 AND MONTH(created_at) = MONTH(CURDATE())
                                 AND YEAR(created_at) = YEAR(CURDATE())
                                 AND is_deleted = 0";
        $result_input_bulan_ini = mysqli_query($conn, $query_input_bulan_ini);
        $input_bulan_ini = mysqli_fetch_assoc($result_input_bulan_ini)['total'];

        // Total input oleh petugas yang login
        $query_total_input = "SELECT COUNT(*) as total 
                             FROM aset_barang 
                             WHERE nama_petugas = '$nama'
                             AND is_deleted = 0";
        $result_total_input = mysqli_query($conn, $query_total_input);
        $total_input = mysqli_fetch_assoc($result_total_input)['total'];

        // Input terbaru oleh petugas yang login
        $query_input_terbaru = "SELECT nama_barang, kode_barang, created_at 
                               FROM aset_barang 
                               WHERE nama_petugas = '$nama'
                               AND is_deleted = 0
                               ORDER BY created_at DESC 
                               LIMIT 5";
        $result_input_terbaru = mysqli_query($conn, $query_input_terbaru);
        $input_terbaru = [];
        while ($row = mysqli_fetch_assoc($result_input_terbaru)) {
            $input_terbaru[] = $row;
        }
    }

} catch (Exception $e) {
    // Set default values jika terjadi error
    if ($level == 'admin' || $level == 'kepala_dinas') {
        $total_aset = 0;
        $kondisi_data = ['baik' => 0, 'rusak_ringan' => 0, 'rusak_berat' => 0];
        $total_nilai = 0;
        $total_users = 0;
        $aktivitas_hari_ini = 0;
        $kategori_data = [];
        $aktivitas_terbaru = [];
    } else {
        $input_hari_ini = 0;
        $input_bulan_ini = 0;
        $total_input = 0;
        $input_terbaru = [];
    }
}

// Format rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Format tanggal Indonesia
function formatTanggal($tanggal) {
    $nama_bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    if ($timestamp === false) {
        return 'Tanggal tidak valid';
    }
    
    // Cek apakah tanggal hari ini
    if (date('Y-m-d', $timestamp) == date('Y-m-d')) {
        return 'Hari ini, ' . date('H:i', $timestamp);
    }
    
    // Cek apakah tanggal kemarin
    if (date('Y-m-d', $timestamp) == date('Y-m-d', strtotime('-1 day'))) {
        return 'Kemarin, ' . date('H:i', $timestamp);
    }
    
    $hari = date('d', $timestamp);
    $bulan = $nama_bulan[date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan . ' ' . $tahun;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MONVEST</title>
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

        .welcome-card {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(79, 172, 254, 0.3);
        }

        .welcome-card h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .welcome-card p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-color, #3b82f6);
        }

        .stat-card.blue { --card-color: #3b82f6; }
        .stat-card.green { --card-color: #16a34a; }
        .stat-card.purple { --card-color: #8b5cf6; }
        .stat-card.orange { --card-color: #ea580c; }
        .stat-card.red { --card-color: #dc2626; }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            float: right;
            background: var(--card-color);
            color: white;
        }

        .stat-icon svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
            line-height: 1;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-trend {
            font-size: 0.8rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .trend-up { color: #16a34a; }
        .trend-down { color: #dc2626; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }

        .chart-card, .activity-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1f2937;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-name {
            font-weight: 500;
            color: #374151;
        }

        .category-count {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .activity-icon.create { background: #dcfce7; color: #16a34a; }
        .activity-icon.update { background: #dbeafe; color: #3b82f6; }
        .activity-icon.delete { background: #fee2e2; color: #dc2626; }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.9rem;
            color: #374151;
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-top: 30px;
        }

        .quick-actions h3 {
            color: #1f2937;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            color: #475569;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            border-color: #4f46e5;
            background: #f1f5f9;
            transform: translateY(-2px);
        }

        .action-btn svg {
            width: 20px;
            height: 20px;
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
            }

            .navbar {
                padding: 0 20px;
            }

            .user-info {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
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

        /* Petugas Dashboard Styles */
        .petugas-dashboard {
    width: 100%;
    padding-right: 300px;
}



        .petugas-welcome {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        }

        .petugas-stats {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .input-summary {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .summary-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 15px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .summary-item {
            text-align: center;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .summary-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #16a34a;
        }

        .summary-label {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 5px;
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

            <a href="dashboard.php" class="menu-item active">
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
                <a href="dashboard.php" class="menu-item active">
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

        
            <?php elseif ($level == 'petugas'): ?>
            <div class="menu-section">
                <a href="dashboard.php" class="menu-item active">
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

            <div class="logout-item">
                <a href="logout.php" class="menu-item" onclick="return confirm('Apakah Anda yakin ingin logout?')">
                    <svg viewBox="0 0 24 24">
                        <path d="M16,17V14H9V10H16V7L21,12L16,17M14,2A2,2 0 0,1 16,4V6H14V4H5V20H14V18H16V20A2,2 0 0,1 14,22H5A2,2 0 0,1 3,20V4A2,2 0 0,1 5,2H14Z"/>
                    </svg>
                    Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content <?php echo ($level == 'petugas') ? 'petugas-dashboard' : ''; ?>">
        <!-- Welcome Card -->
        <div class="welcome-card <?php echo ($level == 'petugas') ? 'petugas-welcome' : ''; ?>">
            <h1>Selamat Datang, <?php echo htmlspecialchars($nama); ?>!</h1>
            <p>
                <?php 
                if ($level == 'admin') {
                    echo "Kelola sistem inventaris aset dengan mudah dan efisien.";
                } elseif ($level == 'kepala_dinas') {
                    echo "Pantau dan kelola inventaris aset untuk pengambilan keputusan yang tepat.";
                } else {
                    echo "Input dan kelola data aset dengan mudah dan akurat.";
                }
                ?>
            </p>
        </div>

        <?php if ($level == 'admin' || $level == 'kepala_dinas'): ?>
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,2A3,3 0 0,1 15,5V11A3,3 0 0,1 12,14A3,3 0 0,1 9,11V5A3,3 0 0,1 12,2M19,11C19,14.53 16.39,17.44 13,17.93V21H11V17.93C7.61,17.44 5,14.53 5,11H7A5,5 0 0,0 12,16A5,5 0 0,0 17,11H19Z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo number_format($total_aset); ?></div>
                <div class="stat-label">Total Aset</div>
                <div class="stat-trend trend-up">
                    <svg viewBox="0 0 24 24" width="12" height="12">
                        <path fill="currentColor" d="M13,20H11V8L5.5,13.5L4.08,12.08L12,4.16L19.92,12.08L18.5,13.5L13,8V20Z"/>
                    </svg>
                    Data terbaru
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,2C13.1,2 14,2.9 14,4C14,5.1 13.1,6 12,6C10.9,6 10,5.1 10,4C10,2.9 10.9,2 12,2M21,9V7L15,1H5C3.89,1 3,1.89 3,3V21A2,2 0 0,0 5,23H19A2,2 0 0,0 21,21V9M19,9H14V4H5V19H19V9Z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo isset($kondisi_data['baik']) ? number_format($kondisi_data['baik']) : '0'; ?></div>
                <div class="stat-label">Kondisi Baik</div>
                <div class="stat-trend trend-up">
                    <svg viewBox="0 0 24 24" width="12" height="12">
                        <path fill="currentColor" d="M13,20H11V8L5.5,13.5L4.08,12.08L12,4.16L19.92,12.08L18.5,13.5L13,8V20Z"/>
                    </svg>
                    <?php echo $total_aset > 0 ? round((($kondisi_data['baik'] ?? 0) / $total_aset) * 100, 1) : 0; ?>%
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M11,15H13V17H11V15M11,7H13V13H11V7M12,2C6.47,2 2,6.5 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20Z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo isset($kondisi_data['rusak_ringan']) ? number_format($kondisi_data['rusak_ringan']) : '0'; ?></div>
                <div class="stat-label">Rusak Ringan</div>
                <div class="stat-trend">
                    Perlu perhatian
                </div>
            </div>

            <div class="stat-card red">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,2L13.09,8.26L22,9L17.22,13.78L18.18,22L12,18.54L5.82,22L6.78,13.78L2,9L10.91,8.26L12,2Z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo isset($kondisi_data['rusak_berat']) ? number_format($kondisi_data['rusak_berat']) : '0'; ?></div>
                <div class="stat-label">Rusak Berat</div>
                <div class="stat-trend trend-down">
                    <svg viewBox="0 0 24 24" width="12" height="12">
                        <path fill="currentColor" d="M11,4H13V16L18.5,10.5L19.92,11.92L12,19.84L4.08,11.92L5.5,10.5L11,16V4Z"/>
                    </svg>
                    Perlu tindakan
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M7,15H9C9,16.08 10.37,17 12,17C13.63,17 15,16.08 15,15C15,13.9 13.96,13.5 11.76,12.97C9.64,12.44 7,11.78 7,9C7,7.21 8.47,5.69 10.5,5.18V3H13.5V5.18C15.53,5.69 17,7.21 17,9H15C15,7.92 13.63,7 12,7C10.37,7 9,7.92 9,9C9,10.1 10.04,10.5 12.24,11.03C14.36,11.56 17,12.22 17,15C17,16.79 15.53,18.31 13.5,18.82V21H10.5V18.82C8.47,18.31 7,16.79 7,15Z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo formatRupiah($total_nilai); ?></div>
                <div class="stat-label">Total Nilai Aset</div>
                <div class="stat-trend">
                    Nilai keseluruhan
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M16,4C16.88,4 17.67,4.5 18,5.26L19,7H20A2,2 0 0,1 22,9V20A2,2 0 0,1 20,22H4A2,2 0 0,1 2,20V9A2,2 0 0,1 4,7H5L6,5.26C6.33,4.5 7.12,4 8,4H16M16.5,13.5L13.5,9.5L10.75,13L9,11L6.5,13.5H16.5Z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo number_format($total_users); ?></div>
                <div class="stat-label">Total Pengguna</div>
                <div class="stat-trend">
                    Pengguna aktif
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Chart Card -->
            <div class="chart-card">
                <div class="card-header">
                    <h3 class="card-title">Aset Berdasarkan Kategori</h3>
                    <span style="font-size: 0.9rem; color: #6b7280;">Top 5 Kategori</span>
                </div>
                <div class="chart-content">
                    <?php if (!empty($kategori_data)): ?>
                        <?php foreach ($kategori_data as $index => $kategori): ?>
                        <div class="category-item">
                            <div>
                                <div class="category-name"><?php echo htmlspecialchars($kategori['nama_barang'] ?? 'Tidak Diketahui'); ?></div>
                                <div style="font-size: 0.8rem; color: #9ca3af; margin-top: 2px;">
                                    <?php echo round((($kategori['jumlah'] / $total_aset) * 100), 1); ?>% dari total
                                </div>
                            </div>
                            <div class="category-count"><?php echo number_format($kategori['jumlah']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: #6b7280; padding: 40px;">
                            <svg viewBox="0 0 24 24" width="48" height="48" style="margin-bottom: 15px; fill: #d1d5db;">
                                <path d="M13,9H11V7H13M13,17H11V11H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                            </svg>
                            <p>Belum ada data aset</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Activity Card -->
            <div class="activity-card">
                <div class="card-header">
                    <h3 class="card-title">Aktivitas Terbaru</h3>
                    <span style="font-size: 0.9rem; color: #6b7280;"><?php echo number_format($aktivitas_hari_ini); ?> hari ini</span>
                </div>
                <div class="activity-content">
                    <?php if (!empty($aktivitas_terbaru)): ?>
                        <?php foreach ($aktivitas_terbaru as $aktivitas): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php 
                                echo (strpos(strtolower($aktivitas['perubahan']), 'tambah') !== false || strpos(strtolower($aktivitas['perubahan']), 'input') !== false) ? 'create' : 
                                     ((strpos(strtolower($aktivitas['perubahan']), 'ubah') !== false || strpos(strtolower($aktivitas['perubahan']), 'update') !== false) ? 'update' : 'delete');
                            ?>">
                                <?php 
                                if (strpos(strtolower($aktivitas['perubahan']), 'tambah') !== false || strpos(strtolower($aktivitas['perubahan']), 'input') !== false) {
                                    echo '+';
                                } elseif (strpos(strtolower($aktivitas['perubahan']), 'ubah') !== false || strpos(strtolower($aktivitas['perubahan']), 'update') !== false) {
                                    echo '↻';
                                } else {
                                    echo '×';
                                }
                                ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?php echo htmlspecialchars($aktivitas['nama_lengkap'] ?? 'Unknown'); ?></strong>
                                    <?php echo htmlspecialchars($aktivitas['perubahan']); ?>
                                </div>
                                <div class="activity-time"><?php echo formatTanggal($aktivitas['tanggal_perubahan']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: #6b7280; padding: 40px;">
                            <svg viewBox="0 0 24 24" width="48" height="48" style="margin-bottom: 15px; fill: #d1d5db;">
                                <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,6A2,2 0 0,1 14,8A2,2 0 0,1 12,10A2,2 0 0,1 10,8A2,2 0 0,1 12,6M12,20C10.03,20 8.25,19.26 6.96,18.03C7.5,16.5 10.5,15.5 12,15.5C13.5,15.5 16.5,16.5 17.04,18.03C15.75,19.26 13.97,20 12,20Z"/>
                            </svg>
                            <p>Belum ada aktivitas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>Aksi Cepat</h3>
            <div class="action-buttons">
                <?php if ($level == 'admin'): ?>
                <a href="input_aset.php" class="action-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                    Input Aset Baru
                </a>
                <a href="kelola_inventaris.php" class="action-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M9,10V12H7V10H9M13,10V12H11V10H13M17,10V12H15V10H17M19,3A2,2 0 0,1 21,5V19A2,2 0 0,1 19,21H5C3.89,21 3,20.1 3,19V5A2,2 0 0,1 5,3H6V1H8V3H16V1H18V3H19M19,19V8H5V19H19M9,14V16H7V14H9M13,14V16H11V14H13M17,14V16H15V14H17Z"/>
                    </svg>
                    Kelola Inventaris
                </a>
                <a href="buat_akun.php" class="action-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M15,14C12.33,14 7,15.33 7,18V20H23V18C23,15.33 17.67,14 15,14M6,10V7H4V10H1V12H4V15H6V12H9V10M15,12A4,4 0 0,0 19,8A4,4 0 0,0 15,4A4,4 0 0,0 11,8A4,4 0 0,0 15,12Z"/>
                    </svg>
                    Buat Akun Baru
                </a>
                <a href="riwayat_perubahan.php" class="action-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,3A9,9 0 0,0 4,12H1L4.89,15.89L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3Z"/>
                    </svg>
                    Lihat Riwayat
                </a>
                <?php elseif ($level == 'kepala_dinas'): ?>
                <a href="kelola_inventaris.php" class="action-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M9,10V12H7V10H9M13,10V12H11V10H13M17,10V12H15V10H17M19,3A2,2 0 0,1 21,5V19A2,2 0 0,1 19,21H5C3.89,21 3,20.1 3,19V5A2,2 0 0,1 5,3H6V1H8V3H16V1H18V3H19M19,19V8H5V19H19M9,14V16H7V14H9M13,14V16H11V14H13M17,14V16H15V14H17Z"/>
                    </svg>
                    Kelola Inventaris
                </a>
                <a href="riwayat_perubahan.php" class="action-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,3A9,9 0 0,0 4,12H1L4.89,15.89L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3Z"/>
                    </svg>
                    Lihat Riwayat
                </a>
                <a href="log_aktivitas.php" class="action-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M14,12H15.5V16.82L17.94,18.23L17.19,19.53L14,17.69V12M16,9A7,7 0 0,1 23,16A7,7 0 0,1 16,23C14.12,23 12.45,22.3 11.22,21.18C10.95,20.95 10.71,20.69 10.5,20.42C10.39,20.27 10.29,20.12 10.19,19.96C9.5,19 9.07,17.84 9,16.61C9,16.4 9,16.2 9,16A7,7 0 0,1 16,9M16,11A5,5 0 0,0 11,16A5,5 0 0,0 16,21A5,5 0 0,0 21,16A5,5 0 0,0 16,11M15,1H9V3H15V1M20.25,7.75L18.8,6.3L17.4,7.7L18.85,9.15L20.25,7.75M3.5,10.5V12.5H5.5V10.5H3.5M6.3,6.3L4.85,4.85L3.45,6.25L4.9,7.7L6.3,6.3M1,17H8C8.08,15.45 8.4,13.97 8.91,12.61C7.87,12.23 6.69,12 5.5,12A6.5,6.5 0 0,0 5.5,25H7.5A4.5,4.5 0 0,1 7.5,16H1V17Z"/>
                    </svg>
                    Log Aktivitas
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php else: // Dashboard untuk Petugas ?>
        
        <!-- Stats untuk Petugas -->
        <div class="stats-grid petugas-stats">
            <div class="stat-card green">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo number_format($input_hari_ini); ?></div>
                <div class="stat-label">Input Hari Ini</div>
                <div class="stat-trend trend-up">
                    <svg viewBox="0 0 24 24" width="12" height="12">
                        <path fill="currentColor" d="M13,20H11V8L5.5,13.5L4.08,12.08L12,4.16L19.92,12.08L18.5,13.5L13,8V20Z"/>
                    </svg>
                    Target tercapai
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M9,10V12H7V10H9M13,10V12H11V10H13M17,10V12H15V10H17M19,3A2,2 0 0,1 21,5V19A2,2 0 0,1 19,21H5C3.89,21 3,20.1 3,19V5A2,2 0 0,1 5,3H6V1H8V3H16V1H18V3H19M19,19V8H5V19H19M9,14V16H7V14H9M13,14V16H11V14H13M17,14V16H15V14H17Z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo number_format($input_bulan_ini); ?></div>
                <div class="stat-label">Input Bulan Ini</div>
                <div class="stat-trend">
                    Total bulan
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,2C13.1,2 14,2.9 14,4C14,5.1 13.1,6 12,6C10.9,6 10,5.1 10,4C10,2.9 10.9,2 12,2M21,9V7L15,1H5C3.89,1 3,1.89 3,3V21A2,2 0 0,0 5,23H19A2,2 0 0,0 21,21V9M19,9H14V4H5V19H19V9Z"/>
                    </svg>
                </div>
                <div class="stat-number"><?php echo number_format($total_input); ?></div>
                <div class="stat-label">Total Input Saya</div>
                <div class="stat-trend">
                    Keseluruhan
                </div>
            </div>
        </div>

        <!-- Quick Actions untuk Petugas -->
        <div class="quick-actions">
            <h3>Aksi Cepat</h3>
            <div class="action-buttons">
                <a href="input_aset.php" class="action-btn primary">
                    <svg viewBox="0 0 24 24">
                        <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                    Input Aset Baru
                </a>
            </div>
        </div>

        <!-- Input History untuk Petugas -->
        <div class="chart-card" style="margin-top: 20px;">
            <div class="card-header">
                <h3 class="card-title">Riwayat Input Terbaru</h3>
                <span style="font-size: 0.9rem; color: #6b7280;">5 Input Terakhir</span>
            </div>
            <div class="activity-content">
                <?php if (!empty($input_terbaru)): ?>
                    <?php foreach ($input_terbaru as $input): ?>
                    <div class="activity-item">
                        <div class="activity-icon create">+</div>
                        <div class="activity-content">
                            <div class="activity-text">
                                <strong><?php echo htmlspecialchars($input['nama_barang']); ?></strong>
                                <span style="color: #6b7280;">- <?php echo htmlspecialchars($input['kode_barang']); ?></span>
                            </div>
                            <div class="activity-time"><?php echo formatTanggal($input['created_at']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; color: #6b7280; padding: 40px;">
                        <svg viewBox="0 0 24 24" width="48" height="48" style="margin-bottom: 15px; fill: #d1d5db;">
                            <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                        </svg>
                        <p>Belum ada input aset</p>
                        <a href="input_aset.php" style="color: #3b82f6; text-decoration: none;">Mulai input aset pertama</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </main>

    <script>
        // Auto refresh dashboard setiap 5 menit
        setTimeout(function() {
            location.reload();
        }, 300000);

        // Update waktu real-time
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('current-time').textContent = now.toLocaleDateString('id-ID', options);
        }

        // Update time immediately and then every minute
        if (document.getElementById('current-time')) {
            updateTime();
            setInterval(updateTime, 60000);
        }

        // Smooth scroll untuk anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Tooltips untuk stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            });
        });

        // Interactive action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-1px)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Loading animation untuk statistik
        document.querySelectorAll('.stat-number').forEach(number => {
            const finalValue = parseInt(number.textContent.replace(/,/g, ''));
            if (finalValue > 0) {
                let currentValue = 0;
                const increment = Math.ceil(finalValue / 30);
                const timer = setInterval(() => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        currentValue = finalValue;
                        clearInterval(timer);
                    }
                    number.textContent = currentValue.toLocaleString('id-ID');
                }, 50);
            }
        });

        // Notification system (jika ada)
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 1000;
                transform: translateX(400px);
                transition: transform 0.3s ease;
            `;
            
            switch(type) {
                case 'success':
                    notification.style.backgroundColor = '#10b981';
                    break;
                case 'error':
                    notification.style.backgroundColor = '#ef4444';
                    break;
                case 'warning':
                    notification.style.backgroundColor = '#f59e0b';
                    break;
                default:
                    notification.style.backgroundColor = '#3b82f6';
            }
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Check for URL parameters untuk notifikasi
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success')) {
            showNotification('Operasi berhasil dilakukan!', 'success');
        }
        if (urlParams.get('error')) {
            showNotification('Terjadi kesalahan!', 'error');
        }

        // Responsive sidebar toggle
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }

        // Auto-collapse sidebar pada mobile
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        });

        // Progressive loading untuk chart data
        function loadChartData() {
            // Animate category items
            document.querySelectorAll('.category-item').forEach((item, index) => {
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animate activity items
            document.querySelectorAll('.activity-item').forEach((item, index) => {
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, index * 150);
            });
        }

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial states
            document.querySelectorAll('.category-item').forEach(item => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                item.style.transition = 'all 0.3s ease';
            });

            document.querySelectorAll('.activity-item').forEach(item => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                item.style.transition = 'all 0.3s ease';
            });

            // Load data with animation
            setTimeout(loadChartData, 500);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + / untuk fokus ke search (jika ada)
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                const searchInput = document.querySelector('input[type="search"]');
                if (searchInput) searchInput.focus();
            }
            
            // Escape untuk menutup modal/dropdown
            if (e.key === 'Escape') {
                document.querySelectorAll('.dropdown.open').forEach(dropdown => {
                    dropdown.classList.remove('open');
                });
            }
        });

        // Auto-save preferences (contoh untuk tema, layout, dll)
        function saveUserPreference(key, value) {
            try {
                localStorage.setItem(`monvest_${key}`, value);
            } catch (e) {
                console.log('LocalStorage tidak tersedia');
            }
        }

        function getUserPreference(key, defaultValue = null) {
            try {
                return localStorage.getItem(`monvest_${key}`) || defaultValue;
            } catch (e) {
                return defaultValue;
            }
        }

        // Error handling untuk AJAX requests (jika diperlukan)
        function handleAjaxError(xhr, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            showNotification('Terjadi kesalahan koneksi', 'error');
        }

        // Print functionality
        function printDashboard() {
            window.print();
        }

        // Export data functionality (placeholder)
        function exportData(format = 'pdf') {
            showNotification(`Mengekspor data dalam format ${format.toUpperCase()}...`, 'info');
            // Implementasi export akan ditambahkan sesuai kebutuhan
        }

        console.log('Dashboard loaded successfully');
    </script>
</body>
</html>