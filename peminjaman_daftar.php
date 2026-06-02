<?php
require_once __DIR__ . '/config/functions.php';
requireLogin();

$pageTitle = 'Daftar Peminjaman';

// Filter
$fStatus  = sanitize($_GET['status'] ?? '');
$fRuang   = (int)($_GET['ruang_id'] ?? 0);
$fTglDari = sanitize($_GET['tgl_dari'] ?? '');
$fTglSmp  = sanitize($_GET['tgl_sampai'] ?? '');

$where  = '1=1';
$params = [];
$types  = '';
if ($fStatus) { $where .= ' AND p.status=?'; $params[] = $fStatus; $types .= 's'; }
if ($fRuang)  { $where .= ' AND p.ruang_id=?'; $params[] = $fRuang; $types .= 'i'; }
if ($fTglDari){ $where .= ' AND p.tanggal>=?'; $params[] = $fTglDari; $types .= 's'; }
if ($fTglSmp) { $where .= ' AND p.tanggal<=?'; $params[] = $fTglSmp; $types .= 's'; }

$daftar  = dbFetchAll(
    "SELECT p.*,m.nim,m.nama AS nama_mhs,m.prodi,d.nama AS nama_dosen,r.nama AS nama_ruang
     FROM peminjaman p
     JOIN mahasiswa m ON m.id=p.mahasiswa_id
     JOIN dosen d ON d.id=p.dosen_id
     JOIN ruang r ON r.id=p.ruang_id
     WHERE $where ORDER BY p.created_at DESC", $params, $types
);
$ruangan = dbFetchAll("SELECT id,nama FROM ruang ORDER BY nama");

include __DIR__ . '/includes/header.php';
?>
<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="form-inline flex-wrap gap-2">
      <select name="status" class="form-control form-control-sm mr-2 mb-1">
        <option value="">Semua Status</option>
        <?php foreach(['menunggu','disetujui','ditolak','selesai','dibatalkan'] as $s): ?>
        <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="ruang_id" class="form-control form-control-sm mr-2 mb-1">
        <option value="">Semua Ruangan</option>
        <?php foreach($ruangan as $r): ?>
        <option value="<?= $r['id'] ?>" <?= $fRuang==$r['id']?'selected':'' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="tgl_dari" class="form-control form-control-sm mr-1 mb-1" value="<?= e($fTglDari) ?>" placeholder="Dari">
      <span class="mr-1 mb-1">s/d</span>
      <input type="date" name="tgl_sampai" class="form-control form-control-sm mr-2 mb-1" value="<?= e($fTglSmp) ?>">
      <button class="btn btn-sm btn-primary mb-1 mr-1"><i class="fas fa-filter mr-1"></i>Filter</button>
      <a href="peminjaman_daftar.php" class="btn btn-sm btn-outline-secondary mb-1">Reset</a>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header border-0 d-flex justify-content-between align-items-center">
    <h3 class="card-title mb-0"><i class="fas fa-list mr-2 text-primary"></i>Daftar Peminjaman (<?= count($daftar) ?>)</h3>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" data-datatable>
        <thead class="thead-light">
          <tr><th>Kode</th><th>NIM</th><th>Nama</th><th>Mata Kuliah</th><th>Dosen</th><th>Ruangan</th><th>Tanggal</th><th>Jam</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($daftar as $p): ?>
          <tr>
            <td><small><?= e($p['kode_pinjam']) ?></small></td>
            <td><?= e($p['nim']) ?></td>
            <td><?= e($p['nama_mhs']) ?></td>
            <td><?= e($p['mata_kuliah']) ?></td>
            <td><small><?= e($p['nama_dosen']) ?></small></td>
            <td><?= e($p['nama_ruang']) ?></td>
            <td><small><?= date('d/m/Y',strtotime($p['tanggal'])) ?></small></td>
            <td><small><?= substr($p['jam_mulai'],0,5) ?>–<?= substr($p['jam_selesai'],0,5) ?></small></td>
            <td><?= statusBadge($p['status']) ?></td>
            <td style="white-space:nowrap">
              <a href="peminjaman_detail.php?id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-info"><i class="fas fa-eye"></i></a>
              <?php if ($p['status']==='menunggu'): ?>
              <button onclick="aksi(<?= $p['id'] ?>,'setujui')" class="btn btn-xs btn-outline-success" title="Setujui"><i class="fas fa-check"></i></button>
              <button onclick="aksiTolak(<?= $p['id'] ?>)" class="btn btn-xs btn-outline-danger" title="Tolak"><i class="fas fa-times"></i></button>
              <?php endif; ?>
              <?php if ($p['status']==='disetujui'): ?>
              <button onclick="aksi(<?= $p['id'] ?>,'selesai')" class="btn btn-xs btn-outline-secondary" title="Tandai Selesai"><i class="fas fa-flag-checkered"></i></button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraScript = '
<script>
function aksi(id,act){
  const labels={setujui:"Setujui",selesai:"Tandai Selesai"};
  Swal.fire({title:labels[act]+" peminjaman ini?",icon:"question",showCancelButton:true,
    confirmButtonText:"Ya",cancelButtonText:"Batal"}).then(r=>{
    if(!r.isConfirmed)return;
    $.post(BASE_URL+"ajax/peminjaman_aksi.php",{id,aksi:act,csrf_token:CSRF})
      .done(d=>{if(d.status==="success"){toastOk(d.message);setTimeout(()=>location.reload(),1200);}else toastErr(d.message);});
  });
}
function aksiTolak(id){
  Swal.fire({title:"Alasan penolakan",input:"textarea",inputPlaceholder:"Tulis alasan...",
    icon:"warning",showCancelButton:true,confirmButtonText:"Tolak",confirmButtonColor:"#dc3545"}).then(r=>{
    if(!r.isConfirmed)return;
    $.post(BASE_URL+"ajax/peminjaman_aksi.php",{id,aksi:"tolak",alasan:r.value,csrf_token:CSRF})
      .done(d=>{if(d.status==="success"){toastOk(d.message);setTimeout(()=>location.reload(),1200);}else toastErr(d.message);});
  });
}
</script>';
include __DIR__ . '/includes/footer.php';
?>
