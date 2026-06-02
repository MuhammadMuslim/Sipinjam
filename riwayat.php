<?php
// ajax/riwayat.php
require_once __DIR__ . '/../config/functions.php';
$nim  = sanitize($_GET['nim'] ?? '');
$mhs  = dbFetchOne("SELECT id FROM mahasiswa WHERE nim=?",[$nim]);
if (!$mhs) { jsonResponse([]); }

$rows = dbFetchAll(
    "SELECT p.kode_pinjam,p.mata_kuliah,p.tanggal,p.jam_mulai,p.jam_selesai,p.status,
            r.nama AS nama_ruang,
            DATE_FORMAT(p.tanggal,'%d/%m/%Y') AS tgl_fmt
     FROM peminjaman p JOIN ruang r ON r.id=p.ruang_id
     WHERE p.mahasiswa_id=? ORDER BY p.created_at DESC LIMIT 20",
    [$mhs['id']], 'i'
);
jsonResponse($rows);
