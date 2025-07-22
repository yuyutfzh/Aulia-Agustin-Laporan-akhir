<?php
require 'config.php';

// Ambil data pemusnahan aset
$sql = "
    SELECT p.*, u.nama_lengkap, a.nama_barang, a.kode_barang
    FROM pemusnahan_aset p
    JOIN user u ON p.nip_petugas = u.nip
    LEFT JOIN aset_barang a ON p.aset_id = a.id
    ORDER BY p.tanggal_pemusnahan DESC
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Data Pemusnahan Aset - MONVEST</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h2>Data Pemusnahan Aset</h2>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Nama Barang</th>
                <th>Kode Barang</th>
                <th>Alasan</th>
                <th>Petugas</th>
                <th>Bukti</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            while ($row = $result->fetch_assoc()):
                $nama_barang = $row['nama_barang'] ?? '[Aset Terhapus]';
                $kode_barang = $row['kode_barang'] ?? '-';
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['tanggal_pemusnahan']) ?></td>
                <td><?= htmlspecialchars($nama_barang) ?></td>
                <td><?= htmlspecialchars($kode_barang) ?></td>
                <td><?= htmlspecialchars($row['alasan']) ?></td>
                <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                <td>
                    <?php if ($row['bukti_pemusnahan']): ?>
                        <a href="uploads/<?= htmlspecialchars($row['bukti_pemusnahan']) ?>" target="_blank">Lihat</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
