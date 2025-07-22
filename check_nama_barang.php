<?php
require 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['nama_barang'])) {
    echo json_encode(['exists' => false]);
    exit();
}

$namaBarang = trim($_GET['nama_barang']);

// Cek apakah nama barang sudah ada di database
$sql = "SELECT COUNT(*) as total FROM aset_barang WHERE nama_barang = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $namaBarang);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode(['exists' => $row['total'] > 0]);
?>