<?php
require_once __DIR__ . '/config/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

$result = dbFetchAll(
    "SELECT p.*, 
            m.nama AS nama_mhs,
            d.nama AS nama_dosen,
            r.nama AS nama_ruang
     FROM peminjaman p
     LEFT JOIN mahasiswa m ON m.id = p.mahasiswa_id
     LEFT JOIN dosen d ON d.id = p.dosen_id
     LEFT JOIN ruang r ON r.id = p.ruang_id
     WHERE p.id = ?",
    [$id],
    "i"
);

if (empty($result)) {
    die('Data peminjaman tidak ditemukan');
}
$data = $result[0];

include __DIR__.'/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>Detail Peminjaman</h3>
    </div>

    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th>Kode</th>
                <td><?= e($data['kode_pinjam']) ?></td>
            </tr>
            <tr>
                <th>Mahasiswa</th>
                <td><?= e($data['nama_mhs']) ?></td>
            </tr>
            <tr>
                <th>Dosen</th>
                <td><?= e($data['nama_dosen']) ?></td>
            </tr>
            <tr>
                <th>Ruangan</th>
                <td><?= e($data['nama_ruang']) ?></td>
            </tr>
            <tr>
                <th>Mata Kuliah</th>
                <td><?= e($data['mata_kuliah']) ?></td>
            </tr>
            <tr>
                <th>Tanggal</th>
                <td><?= e($data['tanggal']) ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><?= statusBadge($data['status']) ?></td>
            </tr>
        </table>

        <a href="peminjaman_daftar.php" class="btn btn-secondary">
            Kembali
        </a>
    </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>