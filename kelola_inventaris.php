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
$nip = $_SESSION['nip'];

// Koneksi database
$host = 'localhost';
$dbname = 'monvest_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Ambil semua jenis barang
$jenisStmt = $pdo->query("SELECT id, nama_jenis FROM jenis_barang");
$jenisOptions = $jenisStmt->fetchAll();

// Ambil semua kondisi
$kondisiStmt = $pdo->query("SELECT id, nama_kondisi FROM kondisi");
$kondisiOptions = $kondisiStmt->fetchAll();

// Handle Export Excel
if ($_GET && isset($_GET['action']) && $_GET['action'] == 'export') {
    header('Content-Type: application/json');
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $where_clause = 'WHERE is_deleted = 0';
    $params = [];

    if (!empty($search)) {
        $where_clause .= " AND (nama_barang LIKE ? OR kode_barang LIKE ? OR lokasi LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
    }

    // Ambil semua data untuk export
    $sql = "SELECT ab.*, 
            CASE 
                WHEN ab.kondisi_id = 1 THEN 'Baik'
                WHEN ab.kondisi_id = 2 THEN 'Rusak Ringan'
                WHEN ab.kondisi_id = 3 THEN 'Rusak Berat'
                ELSE 'Tidak Diketahui'
            END as kondisi_nama,
            CASE 
                WHEN ab.jenis_id = 1 THEN 'Elektronik'
                WHEN ab.jenis_id = 2 THEN 'Furniture'
                WHEN ab.jenis_id = 3 THEN 'Kendaraan'
                ELSE 'Lainnya'
            END as jenis_nama
            FROM aset_barang ab 
            $where_clause 
            ORDER BY ab.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data untuk export
    $formatted_data = [];
    $no = 1;
    foreach ($export_data as $row) {
        $formatted_data[] = [
            'No' => $no++,
            'Kode Barang' => $row['kode_barang'],
            'Nama Barang' => $row['nama_barang'],
            'Spesifikasi Nama' => $row['spesifikasi_nama_barang'],
            'Spesifikasi Lainnya' => $row['spesifikasi_lainnya'],
            'Lokasi' => $row['lokasi'],
            'Jumlah' => $row['jumlah'],
            'Satuan' => $row['satuan'],
            'Harga Satuan' => $row['harga_satuan_perolehan'],
            'Nilai Perolehan' => $row['nilai_perolehan'],
            'Cara Perolehan' => $row['cara_perolehan'],
            'Tanggal Perolehan' => date('d/m/Y', strtotime($row['tanggal_perolehan'])),
            'Kondisi' => $row['kondisi_nama'],
            'Jenis' => $row['jenis_nama'],
            'Keterangan' => $row['keterangan'],
            'Bukti' => $row['bukti']
        ];
    }
    
    // Log aktivitas export
    $aktivitas = "Mengexport data inventaris ke Excel (" . count($export_data) . " record)";
    $log_stmt = $pdo->prepare("INSERT INTO log_aktivitas (nip, aktivitas, waktu) VALUES (?, ?, NOW())");
    $log_stmt->execute([$nip, $aktivitas]);
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_data,
        'filename' => 'Data_Inventaris_' . date('Y-m-d_H-i-s') . '.xlsx'
    ]);
    exit();
}

// Handle Update Data - PERBAIKAN UTAMA
if ($_POST && isset($_POST['edit_stock']) && $_POST['edit_stock'] == '1') {
    try {
        $id = $_POST['edit_id'];
        
        // Validasi input
        if (empty($id) || !is_numeric($id)) {
            throw new Exception("ID tidak valid");
        }
        
        // Ambil data lama untuk perbandingan dan log
        $stmt = $pdo->prepare("SELECT * FROM aset_barang WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $data_lama = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data_lama) {
            throw new Exception("Data tidak ditemukan");
        }
        
        // Validasi field wajib
        $required_fields = ['kode_barang', 'nama_barang', 'lokasi', 'jumlah', 'satuan', 'harga_satuan_perolehan', 'nilai_perolehan', 'tanggal_perolehan', 'kondisi_id', 'jenis_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field $field harus diisi");
            }
        }
        
        // Pastikan nilai numerik valid
        $jumlah = intval($_POST['jumlah']);
        $harga_satuan = floatval($_POST['harga_satuan_perolehan']);
        $nilai_perolehan = floatval($_POST['nilai_perolehan']);
        $kondisi_id = intval($_POST['kondisi_id']);
        $jenis_id = intval($_POST['jenis_id']);
        
        if ($jumlah <= 0 || $harga_satuan < 0 || $nilai_perolehan < 0) {
            throw new Exception("Nilai numerik tidak valid");
        }
        
        // Validasi tanggal
        $tanggal_perolehan = $_POST['tanggal_perolehan'];
        if (!DateTime::createFromFormat('Y-m-d', $tanggal_perolehan)) {
            throw new Exception("Format tanggal tidak valid");
        }
        
        // Update data
        $sql = "UPDATE aset_barang SET 
                kode_barang = ?, 
                nama_barang = ?, 
                spesifikasi_nama_barang = ?, 
                spesifikasi_lainnya = ?, 
                lokasi = ?, 
                jumlah = ?, 
                satuan = ?, 
                harga_satuan_perolehan = ?, 
                nilai_perolehan = ?, 
                cara_perolehan = ?, 
                tanggal_perolehan = ?, 
                keterangan = ?, 
                bukti = ?, 
                kondisi_id = ?, 
                jenis_id = ?
                WHERE id = ? AND is_deleted = 0";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            trim($_POST['kode_barang']),
            trim($_POST['nama_barang']),
            trim($_POST['spesifikasi_nama_barang']),
            trim($_POST['spesifikasi_lainnya']),
            trim($_POST['lokasi']),
            $jumlah,
            trim($_POST['satuan']),
            $harga_satuan,
            $nilai_perolehan,
            trim($_POST['cara_perolehan']),
            $tanggal_perolehan,
            trim($_POST['keterangan']),
            trim($_POST['bukti']),
            $kondisi_id,
            $jenis_id,
            $id
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            // Catat ke log aktivitas
            $aktivitas = "Mengedit aset barang: " . trim($_POST['nama_barang']) . " (Kode: " . trim($_POST['kode_barang']) . ")";
            $log_stmt = $pdo->prepare("INSERT INTO log_aktivitas (nip, aktivitas, waktu) VALUES (?, ?, NOW())");
            $log_stmt->execute([$nip, $aktivitas]);
            
            $success_message = "Data berhasil diupdate!";
        } else {
            throw new Exception("Tidak ada perubahan data atau data tidak ditemukan");
        }
        
    } catch(Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    } catch(PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// Handle Delete Data
if ($_GET && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    try {
        $id = $_GET['id'];
        
        // Validasi ID
        if (!is_numeric($id)) {
            throw new Exception("ID tidak valid");
        }
        
        // Ambil data untuk log
        $stmt = $pdo->prepare("SELECT kode_barang, nama_barang FROM aset_barang WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            throw new Exception("Data tidak ditemukan");
        }
        
        // Soft delete - update is_deleted = 1
        $stmt = $pdo->prepare("UPDATE aset_barang SET is_deleted = 1 WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result && $stmt->rowCount() > 0) {
            // Catat ke log aktivitas
            $aktivitas = "Menghapus aset barang: " . $data['nama_barang'] . " (Kode: " . $data['kode_barang'] . ")";
            $log_stmt = $pdo->prepare("INSERT INTO log_aktivitas (nip, aktivitas, waktu) VALUES (?, ?, NOW())");
            $log_stmt->execute([$nip, $aktivitas]);
            
            $success_message = "Data berhasil dihapus!";
        } else {
            throw new Exception("Gagal menghapus data");
        }
        
    } catch(Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    } catch(PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// Ambil data aset dengan pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_clause = 'WHERE is_deleted = 0';
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (nama_barang LIKE ? OR kode_barang LIKE ? OR lokasi LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Hitung total data
$count_sql = "SELECT COUNT(*) FROM aset_barang $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_data = $count_stmt->fetchColumn();
$total_pages = ceil($total_data / $limit);

// Ambil data dengan limit
$sql = "SELECT ab.*, 
        CASE 
            WHEN ab.kondisi_id = 1 THEN 'Baik'
            WHEN ab.kondisi_id = 2 THEN 'Rusak Ringan'
            WHEN ab.kondisi_id = 3 THEN 'Rusak Berat'
            ELSE 'Tidak Diketahui'
        END as kondisi_nama,
        CASE 
            WHEN ab.jenis_id = 1 THEN 'Elektronik'
            WHEN ab.jenis_id = 2 THEN 'Furniture'
            WHEN ab.jenis_id = 3 THEN 'Kendaraan'
            ELSE 'Lainnya'
        END as jenis_nama
        FROM aset_barang ab 
        $where_clause 
        ORDER BY ab.created_at DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$aset_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil jenis barang dari DB untuk dropdown
$jenis_barang_dropdown = [];
$stmt = $pdo->query("SELECT id, nama_jenis FROM jenis_barang");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $jenis_barang_dropdown[] = $row;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Inventaris - MONVEST</title>
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
            margin-bottom: 25px;
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

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        /* Search and Controls */
        .controls {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-input {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            width: 300px;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #4f46e5;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-success {
            background: #059669;
            color: white;
            margin-left: 500px;
            
        }

        .btn-success:hover {
            background: #047857;
        }

        .btn-warning {
            background: #d97706;
            color: white;
        }

        .btn-warning:hover {
            background: #b45309;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        .data-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            position: sticky;
            top: 0;
        }

        .data-table tbody tr:hover {
            background: #f9fafb;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-baik {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rusak-ringan {
            background: #fef3c7;
            color: #92400e;
        }

        .status-rusak-berat {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            color: #374151;
            font-size: 14px;
        }

        .pagination a:hover {
            background: #f3f4f6;
        }

        .pagination .current {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
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
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }

        .modal-header {
            padding: 20px 25px;
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
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .close:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .modal-body {
            padding: 25px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #374151;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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

            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 2% auto;
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
                <a href="kelola_inventaris.php" class="menu-item active">
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
                <a href="dashboard.php" class="menu-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,3V9H21V3M13,21H21V11H13M3,21H11V15H3M3,13H11V3H3V13Z"/>
                    </svg>
                    Dashboard
                </a>
                <a href="kelola_inventaris.php" class="menu-item active">
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
            </div>

            <div class="menu-section">
                <a href="kelola_inventaris.php" class="menu-item active">
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
            <h1 class="page-title">Kelola Inventaris</h1>
            <p class="page-subtitle">Kelola dan pantau seluruh aset barang inventaris</p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                ✓ <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                ✗ <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Controls -->
        <div class="controls">
            <div class="search-box">
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Cari berdasarkan nama barang, kode, atau lokasi..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z"/>
                        </svg>
                        Cari
                    </button>
                </form>
            </div>
            
            <?php if ($level == 'admin'): ?>
            <a href="input_aset.php" class="btn btn-success">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                </svg>
                Tambah Aset Baru
            </a>
            <?php endif; ?>

            <button id="exportExcelBtn" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2zm-9 14H7v-2h3v2zm0-4H7v-2h3v2zm4 4h-3v-2h3v2zm0-4h-3v-2h3v2zm4 4h-3v-2h3v2zm0-4h-3v-2h3v2z"/>
                </svg>
                Export to Excel
            </button>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Spesifikasi</th>
                        <th>Lokasi</th>
                        <th>Jumlah</th>
                        <th>Kondisi</th>
                        <th>Jenis</th>
                        <th>Nilai Perolehan</th>
                        <th>Tanggal Perolehan</th>
                        <?php if ($level == 'admin'): ?>
                        <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($aset_data)): ?>
                    <tr>
                        <td colspan="<?php echo ($level == 'admin') ? '11' : '10'; ?>" style="text-align: center; padding: 40px;">
                            <div style="color: #6b7280; font-size: 16px;">
                                <svg viewBox="0 0 24 24" width="48" height="48" style="margin-bottom: 10px; opacity: 0.5;">
                                    <path fill="currentColor" d="M12,2A3,3 0 0,1 15,5V11A3,3 0 0,1 12,14A3,3 0 0,1 9,11V5A3,3 0 0,1 12,2M19,11C19,14.53 16.39,17.44 13,17.93V21H11V17.93C7.61,17.44 5,14.53 5,11H7A5,5 0 0,0 12,16A5,5 0 0,0 17,11H19Z"/>
                                </svg>
                                <br>
                                <?php if (!empty($search)): ?>
                                    Tidak ada data aset yang cocok dengan pencarian "<?php echo htmlspecialchars($search); ?>"
                                <?php else: ?>
                                    Belum ada data aset yang tersimpan
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($aset_data as $row): 
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['kode_barang']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($row['spesifikasi_nama_barang']); ?></div>
                                <?php if (!empty($row['spesifikasi_lainnya'])): ?>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($row['spesifikasi_lainnya']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                            <td><?php echo number_format($row['jumlah']); ?> <?php echo htmlspecialchars($row['satuan']); ?></td>
                            <td>
                                <span class="status-badge <?php 
                                    switch($row['kondisi_id']) {
                                        case 1: echo 'status-baik'; break;
                                        case 2: echo 'status-rusak-ringan'; break;
                                        case 3: echo 'status-rusak-berat'; break;
                                        default: echo 'status-baik';
                                    }
                                ?>">
                                    <?php echo htmlspecialchars($row['kondisi_nama']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['jenis_nama']); ?></td>
                            <td>Rp <?php echo number_format($row['nilai_perolehan'], 0, ',', '.'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_perolehan'])); ?></td>
                            <?php if ($level == 'admin'): ?>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <button class="btn btn-warning btn-sm" onclick="editAset(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <svg viewBox="0 0 24 24" width="14" height="14">
                                            <path fill="currentColor" d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z"/>
                                        </svg>
                                        Edit
                                    </button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page-1); ?>&search=<?php echo urlencode($search); ?>">« Sebelumnya</a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page+1); ?>&search=<?php echo urlencode($search); ?>">Selanjutnya »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Info -->
            <div style="margin-top: 15px; color: #6b7280; font-size: 14px; text-align: center;">
                Menampilkan <?php echo (($page-1) * $limit + 1); ?> - <?php echo min($page * $limit, $total_data); ?> 
                dari <?php echo $total_data; ?> data aset
            </div>
        </div>
    </main>

    <!-- Modal Edit Aset -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Aset Barang</h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
    <input type="hidden" name="edit_stock" value="1">
    <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Kode Barang *</label>
                            <input type="text" name="kode_barang" id="edit_kode_barang" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nama Barang *</label>
                            <input type="text" name="nama_barang" id="edit_nama_barang" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Spesifikasi Nama Barang</label>
                            <input type="text" name="spesifikasi_nama_barang" id="edit_spesifikasi_nama_barang" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Spesifikasi Lainnya</label>
                            <textarea name="spesifikasi_lainnya" id="edit_spesifikasi_lainnya" class="form-control"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Lokasi *</label>
                            <input type="text" name="lokasi" id="edit_lokasi" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Jumlah *</label>
                            <input type="number" name="jumlah" id="edit_jumlah" class="form-control" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Satuan *</label>
                            <input type="text" name="satuan" id="edit_satuan" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Harga Satuan Perolehan *</label>
                            <input type="number" name="harga_satuan_perolehan" id="edit_harga_satuan_perolehan" class="form-control" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nilai Perolehan *</label>
                            <input type="number" name="nilai_perolehan" id="edit_nilai_perolehan" class="form-control" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Cara Perolehan</label>
                            <select name="cara_perolehan" id="edit_cara_perolehan" class="form-control">
                                <option value="">Pilih cara perolehan</option>
                                <option value="Pembelian">Pengadaan APBN</option>
                                <option value="Hibah">Hibah</option>
                    
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tanggal Perolehan *</label>
                            <input type="date" name="tanggal_perolehan" id="edit_tanggal_perolehan" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" id="edit_keterangan" class="form-control"></textarea>
                        </div>
                        
                        <div class="form-group">
    <label for="edit_bukti">Bukti (Nama File)</label>
    <input type="text" id="edit_bukti" class="form-control" readonly>
    <a id="edit_bukti_link" href="#" target="_blank" style="font-size: 13px; display: inline-block; margin-top: 4px;"></a>
</div>



                        <div class="form-group">
    <label for="edit_kondisi_id">Kondisi Barang *</label>
    <select id="edit_kondisi_id" name="kondisi_id" class="form-control" required>
        <option value="">Pilih Kondisi</option>
        <option value="1">Baik</option>
        <option value="2">Rusak Ringan</option>
        <option value="3">Rusak Berat</option>
        <!-- Tambahkan lagi jika ada -->
    </select>
</div>

                        
                        <div class="form-group">
    <label for="edit_jenis_id">Jenis Barang *</label>
    <select id="edit_jenis_id" name="jenis_id" class="form-control" required>
    <option value="">Pilih Jenis Barang</option>
    <?php foreach ($jenisOptions as $j): ?>
        <option value="<?= $j['id'] ?>"><?= $j['nama_jenis'] ?></option>
    <?php endforeach; ?>
</select>

</div>

                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
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

        // Modal Functions
        function editAset(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_kode_barang').value = data.kode_barang;
    document.getElementById('edit_nama_barang').value = data.nama_barang;
    document.getElementById('edit_spesifikasi_nama_barang').value = data.spesifikasi_nama_barang || '';
    document.getElementById('edit_spesifikasi_lainnya').value = data.spesifikasi_lainnya || '';
    document.getElementById('edit_lokasi').value = data.lokasi;
    document.getElementById('edit_jumlah').value = data.jumlah;
    document.getElementById('edit_satuan').value = data.satuan;
    document.getElementById('edit_harga_satuan_perolehan').value = data.harga_satuan_perolehan;
    document.getElementById('edit_nilai_perolehan').value = data.nilai_perolehan;
    document.getElementById('edit_cara_perolehan').value = data.cara_perolehan || '';
    document.getElementById('edit_tanggal_perolehan').value = data.tanggal_perolehan;
    document.getElementById('edit_keterangan').value = data.keterangan || '';
    document.getElementById('edit_bukti').value = data.bukti || '';
    document.getElementById('edit_bukti_link').textContent = data.bukti || '';
    document.getElementById('edit_bukti_link').href = data.bukti ? 'uploads/' + data.bukti : '#';

    // ✅ Set opsi dropdown
    document.getElementById('edit_jenis_id').value = data.jenis_id;
    document.getElementById('edit_kondisi_id').value = data.kondisi_id;

    // Tampilkan modal
    document.getElementById('editModal').style.display = 'block';
}



        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Auto calculate nilai_perolehan
        document.getElementById('edit_jumlah').addEventListener('input', calculateNilaiPerolehan);
        document.getElementById('edit_harga_satuan_perolehan').addEventListener('input', calculateNilaiPerolehan);

        function calculateNilaiPerolehan() {
            const jumlah = parseFloat(document.getElementById('edit_jumlah').value) || 0;
            const hargaSatuan = parseFloat(document.getElementById('edit_harga_satuan_perolehan').value) || 0;
            const nilaiPerolehan = jumlah * hargaSatuan;
            document.getElementById('edit_nilai_perolehan').value = nilaiPerolehan;
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
        document.getElementById('exportExcelBtn').addEventListener('click', function() {
    const search = new URLSearchParams(window.location.search).get('search') || '';

    // Kirim request export dengan search parameter jika ada
    fetch(`?action=export&search=${encodeURIComponent(search)}`)
        .then(response => response.json())
        .then(data => {
            if(data.success){
                // Buat worksheet dari data JSON menggunakan SheetJS (xlsx)
                // Tapi karena tidak boleh ubah apapun dan dependencies, kita buat CSV sederhana manual:

                // Convert JSON data ke CSV string
                const rows = data.data;
                if(rows.length === 0) {
                    alert('Tidak ada data untuk diexport.');
                    return;
                }

                const keys = Object.keys(rows[0]);
                const csvContent = [
                    keys.join(','), // header row
                    ...rows.map(row => keys.map(k => `"${String(row[k]).replace(/"/g, '""')}"`).join(','))
                ].join('\r\n');

                // Buat blob dan link untuk download
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = data.filename.replace('.xlsx','.csv');
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            } else {
                alert('Gagal mengekspor data.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan saat mengekspor data.');
        });
});

    </script>
</body>
</html>