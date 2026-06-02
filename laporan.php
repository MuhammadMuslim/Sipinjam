<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/vendor/autoload.php';
requireLogin();

// Ambil filter
$fTglDari  = sanitize($_GET['tgl_dari']??'');
$fTglSmp   = sanitize($_GET['tgl_sampai']??'');
$fRuang    = (int)($_GET['ruang_id']??0);
$fStatus   = sanitize($_GET['status']??'');
$fExport   = sanitize($_GET['export']??'');

// Build query
$where = '1=1'; $params = []; $types = '';
if ($fTglDari) { $where .= ' AND p.tanggal>=?'; $params[]=$fTglDari; $types.='s'; }
if ($fTglSmp)  { $where .= ' AND p.tanggal<=?'; $params[]=$fTglSmp; $types.='s'; }
if ($fRuang)   { $where .= ' AND p.ruang_id=?'; $params[]=$fRuang; $types.='i'; }
if ($fStatus)  { $where .= ' AND p.status=?'; $params[]=$fStatus; $types.='s'; }

$sql = "SELECT p.*,m.nim,m.nama AS nama_mhs,m.prodi,
               d.nama AS nama_dosen,r.nama AS nama_ruang,
               DATE_FORMAT(p.tanggal,'%d/%m/%Y') AS tgl_fmt
        FROM peminjaman p
        JOIN mahasiswa m ON m.id=p.mahasiswa_id
        JOIN dosen d ON d.id=p.dosen_id
        JOIN ruang r ON r.id=p.ruang_id
        WHERE $where ORDER BY p.tanggal DESC,p.jam_mulai ASC";

$data    = dbFetchAll($sql,$params,$types);
$ruangan = dbFetchAll("SELECT id,nama FROM ruang ORDER BY nama");

// Statistik ringkas
$total    = count($data);
$disetujui= count(array_filter($data,fn($r)=>$r['status']==='disetujui'));
$menunggu = count(array_filter($data,fn($r)=>$r['status']==='menunggu'));
$ditolak  = count(array_filter($data,fn($r)=>$r['status']==='ditolak'));

// ----------------------------------------------------------------
// Export PDF
// ----------------------------------------------------------------
if ($fExport === 'pdf') {
    if (!class_exists('\Dompdf\Dompdf')) {
        die('<div class="alert alert-danger">Dompdf belum terinstal. Jalankan: <code>composer require dompdf/dompdf</code></div>');
    }
    ob_start();
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
      body{font-family:DejaVu Sans,sans-serif;font-size:10px}
      h3{text-align:center;margin-bottom:4px}
      p.sub{text-align:center;color:#666;margin-bottom:12px}
      table{width:100%;border-collapse:collapse}
      th{background:#001f5b;color:#fff;padding:5px 6px;text-align:left}
      td{padding:4px 6px;border-bottom:1px solid #eee}
      tr:nth-child(even)td{background:#f8f9fa}
      .badge{padding:2px 6px;border-radius:3px;font-size:9px}
      .ok{background:#d4edda;color:#155724}.wait{background:#fff3cd;color:#856404}
      .err{background:#f8d7da;color:#721c24}.info{background:#d1ecf1;color:#0c5460}
    </style></head><body>';
    echo '<h3>Laporan Peminjaman Kelas</h3>';
    echo '<p class="sub">Dicetak: '.date('d/m/Y H:i:s').' | Total: '.$total.' data</p>';
    echo '<table><thead><tr><th>No</th><th>Kode</th><th>Tanggal</th><th>NIM</th><th>Nama</th><th>Prodi</th><th>Mata Kuliah</th><th>Dosen</th><th>Ruangan</th><th>Jam</th><th>Peserta</th><th>Status</th></tr></thead><tbody>';
    foreach ($data as $i => $r) {
        $bc = ['disetujui'=>'ok','menunggu'=>'wait','ditolak'=>'err','selesai'=>'info'][$r['status']]??'';
        echo '<tr><td>'.($i+1).'</td><td><small>'.$r['kode_pinjam'].'</small></td><td>'.$r['tgl_fmt'].'</td>
              <td>'.$r['nim'].'</td><td>'.$r['nama_mhs'].'</td><td><small>'.$r['prodi'].'</small></td>
              <td>'.$r['mata_kuliah'].'</td><td><small>'.$r['nama_dosen'].'</small></td>
              <td>'.$r['nama_ruang'].'</td><td>'.substr($r['jam_mulai'],0,5).'–'.substr($r['jam_selesai'],0,5).'</td>
              <td>'.$r['jumlah_peserta'].'</td>
              <td><span class="badge '.$bc.'">'.ucfirst($r['status']).'</span></td></tr>';
    }
    echo '</tbody></table></body></html>';
    $html = ob_get_clean();

    $opts = new \Dompdf\Options();
    $opts->set('defaultFont','DejaVu Sans');
    $dompdf = new \Dompdf\Dompdf($opts);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','landscape');
    $dompdf->render();
    $dompdf->stream('laporan-'.date('Ymd').'.pdf',['Attachment'=>false]);
    exit;
}

// ----------------------------------------------------------------
// Export Excel (via HTML table — universal, tanpa library)
// ----------------------------------------------------------------
if ($fExport === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan-'.date('Ymd').'.xls"');
    echo '<table border="1">
    <tr style="background:#001f5b;color:#fff"><th>No</th><th>Kode</th><th>Tanggal</th><th>NIM</th><th>Nama</th><th>Prodi</th><th>Mata Kuliah</th><th>Dosen</th><th>Ruangan</th><th>Jam Mulai</th><th>Jam Selesai</th><th>Peserta</th><th>Status</th></tr>';
    foreach ($data as $i => $r) {
        echo '<tr><td>'.($i+1).'</td><td>'.$r['kode_pinjam'].'</td><td>'.$r['tgl_fmt'].'</td>
              <td>'.$r['nim'].'</td><td>'.$r['nama_mhs'].'</td><td>'.$r['prodi'].'</td>
              <td>'.$r['mata_kuliah'].'</td><td>'.$r['nama_dosen'].'</td><td>'.$r['nama_ruang'].'</td>
              <td>'.substr($r['jam_mulai'],0,5).'</td><td>'.substr($r['jam_selesai'],0,5).'</td>
              <td>'.$r['jumlah_peserta'].'</td><td>'.ucfirst($r['status']).'</td></tr>';
    }
    echo '</table>';
    exit;
}

$pageTitle = 'Laporan Peminjaman';
include __DIR__ . '/includes/header.php';
?>
<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="form-inline flex-wrap">
      <label class="mr-1 mb-1">Dari</label>
      <input type="date" name="tgl_dari" class="form-control form-control-sm mr-2 mb-1" value="<?= e($fTglDari) ?>">
      <label class="mr-1 mb-1">s/d</label>
      <input type="date" name="tgl_sampai" class="form-control form-control-sm mr-2 mb-1" value="<?= e($fTglSmp) ?>">
      <select name="ruang_id" class="form-control form-control-sm mr-2 mb-1">
        <option value="">Semua Ruangan</option>
        <?php foreach($ruangan as $r): ?>
        <option value="<?= $r['id'] ?>" <?= $fRuang==$r['id']?'selected':'' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-control form-control-sm mr-2 mb-1">
        <option value="">Semua Status</option>
        <?php foreach(['menunggu','disetujui','ditolak','selesai','dibatalkan'] as $s): ?>
        <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-primary mr-1 mb-1"><i class="fas fa-filter mr-1"></i>Filter</button>
      <a href="laporan.php" class="btn btn-sm btn-outline-secondary mr-2 mb-1">Reset</a>
      <!-- Export -->
      <a href="laporan.php?<?= http_build_query(array_merge($_GET,['export'=>'pdf'])) ?>" class="btn btn-sm btn-outline-danger mr-1 mb-1">
        <i class="fas fa-file-pdf mr-1"></i>PDF</a>
      <a href="laporan.php?<?= http_build_query(array_merge($_GET,['export'=>'excel'])) ?>" class="btn btn-sm btn-outline-success mb-1">
        <i class="fas fa-file-excel mr-1"></i>Excel</a>
    </form>
  </div>
</div>

<!-- Statistik -->
<div class="row mb-3">
  <div class="col-md-3"><div class="small-box bg-primary mb-0"><div class="inner"><h3><?= $total ?></h3><p>Total</p></div><div class="icon"><i class="fas fa-list"></i></div></div></div>
  <div class="col-md-3"><div class="small-box bg-success mb-0"><div class="inner"><h3><?= $disetujui ?></h3><p>Disetujui</p></div><div class="icon"><i class="fas fa-check"></i></div></div></div>
  <div class="col-md-3"><div class="small-box bg-warning mb-0"><div class="inner"><h3><?= $menunggu ?></h3><p>Menunggu</p></div><div class="icon"><i class="fas fa-clock"></i></div></div></div>
  <div class="col-md-3"><div class="small-box bg-danger mb-0"><div class="inner"><h3><?= $ditolak ?></h3><p>Ditolak</p></div><div class="icon"><i class="fas fa-times"></i></div></div></div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" data-datatable>
        <thead class="thead-light">
          <tr><th>No</th><th>Kode</th><th>Tanggal</th><th>NIM</th><th>Nama</th><th>Prodi</th><th>Mata Kuliah</th><th>Dosen</th><th>Ruangan</th><th>Jam</th><th>Peserta</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($data as $i => $r): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><small><?= e($r['kode_pinjam']) ?></small></td>
            <td><small><?= e($r['tgl_fmt']) ?></small></td>
            <td><?= e($r['nim']) ?></td><td><?= e($r['nama_mhs']) ?></td>
            <td><small><?= e($r['prodi']) ?></small></td>
            <td><?= e($r['mata_kuliah']) ?></td>
            <td><small><?= e($r['nama_dosen']) ?></small></td>
            <td><?= e($r['nama_ruang']) ?></td>
            <td><small><?= substr($r['jam_mulai'],0,5) ?>–<?= substr($r['jam_selesai'],0,5) ?></small></td>
            <td><?= e($r['jumlah_peserta']) ?></td>
            <td><?= statusBadge($r['status']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
