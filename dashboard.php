<?php
require_once __DIR__ . '/config/functions.php';
requireLogin();

$pageTitle = 'Dashboard';

// Statistik
$statHari   = dbFetchOne("SELECT COUNT(*) AS total,
    SUM(status='disetujui') AS ok, SUM(status='menunggu') AS tunggu
    FROM peminjaman WHERE tanggal = CURDATE()");
$statBulan  = dbFetchOne("SELECT COUNT(*) AS total,
    SUM(status='disetujui') AS ok
    FROM peminjaman WHERE YEAR(tanggal)=YEAR(NOW()) AND MONTH(tanggal)=MONTH(NOW())");
$totalRuang = dbFetchOne("SELECT COUNT(*) AS n FROM ruang WHERE status='aktif'")['n'];
$ruangPakai = dbFetchOne("SELECT COUNT(DISTINCT ruang_id) AS n FROM peminjaman
    WHERE tanggal=CURDATE() AND status IN('menunggu','disetujui')
    AND jam_mulai<=TIME(NOW()) AND jam_selesai>TIME(NOW())")['n'];
$menunggu   = dbFetchOne("SELECT COUNT(*) AS n FROM peminjaman WHERE status='menunggu'")['n'];

// Grafik bulanan
$grafik = dbFetchAll("SELECT MONTH(tanggal) AS bln, COUNT(*) AS total
    FROM peminjaman WHERE YEAR(tanggal)=YEAR(NOW()) AND status!='dibatalkan'
    GROUP BY MONTH(tanggal) ORDER BY bln");
$grafikData = array_fill(0, 12, 0);
foreach ($grafik as $g) $grafikData[$g['bln']-1] = (int)$g['total'];

// Status ruangan
$ruanganStatus = dbFetchAll("
SELECT r.*,
CASE WHEN EXISTS(
    SELECT 1
    FROM peminjaman p
    WHERE p.ruang_id = r.id
      AND p.tanggal = CURDATE()
      AND p.status='disetujui'
) THEN 1 ELSE 0 END AS is_terpakai
FROM ruang r
ORDER BY r.nama
");

// Rekap terbaru
$rekap = dbFetchAll("SELECT p.*, m.nim, m.nama AS nama_mhs, m.prodi,
    d.nama AS nama_dosen, r.nama AS nama_ruang
    FROM peminjaman p
    JOIN mahasiswa m ON m.id=p.mahasiswa_id
    JOIN dosen d ON d.id=p.dosen_id
    JOIN ruang r ON r.id=p.ruang_id
    ORDER BY p.created_at DESC LIMIT 10");

include __DIR__ . '/includes/header.php';
?>

<style>
.room-card{
    border:none;
    border-radius:15px;
    transition:.25s;
}
.room-card:hover{
    transform:translateY(-4px);
    box-shadow:0 10px 25px rgba(0,0,0,.12);
}
.room-status{
    width:12px;
    height:12px;
    border-radius:50%;
    display:inline-block;
    margin-right:8px;
}
.room-free{background:#28a745;}
.room-used{background:#dc3545;}
.room-repair{background:#6c757d;}

.room-card .room-img{
    width:100%;
    height:120px;
    object-fit:cover;
    border-radius:12px 12px 0 0;
}
.room-card .room-img-placeholder{
    width:100%;
    height:120px;
    background:linear-gradient(135deg,#e9ecef 0%,#dee2e6 100%);
    border-radius:12px 12px 0 0;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#adb5bd;
    font-size:2rem;
}

.stat-card{
    border:none;
    border-radius:15px;
    transition:.2s;
}
.stat-card:hover{
    transform:translateY(-3px);
}

.dashboard-title{
    font-size:1.6rem;
    font-weight:700;
    color:#185FA5;
}
.dashboard-subtitle{
    color:#6c757d;
}
</style>

<!-- STATUS RUANGAN -->
<div class="card shadow-sm mb-4">
    <div class="card-body text-center">

        <h2 class="dashboard-title mb-2">
            <i class="fas fa-building mr-2"></i>
            Status Ruangan Saat Ini
        </h2>

        <p class="dashboard-subtitle mb-0">
            Monitoring penggunaan ruang secara realtime
        </p>

    </div>
</div>

<div class="row mb-4">

<?php foreach($ruanganStatus as $r): ?>

<?php

if($r['status']==='perbaikan'){
    $badge='secondary';
    $label='Perbaikan';
    $dot='room-repair';
}
elseif($r['is_terpakai']){
    $badge='danger';
    $label='Terpakai';
    $dot='room-used';
}
else{
    $badge='success';
    $label='Tersedia';
    $dot='room-free';
}

?>

<div class="col-xl-3 col-lg-4 col-md-6">

    <div class="card room-card shadow-sm mb-3">

        <?php if(!empty($r['foto'])): ?>
        <img src="<?= BASE_URL ?>uploads/ruangan/<?= e($r['foto']) ?>"
             alt="<?= e($r['nama']) ?>" class="room-img">
        <?php else: ?>
        <div class="room-img-placeholder">
            <i class="fas fa-door-open"></i>
        </div>
        <?php endif; ?>

        <div class="card-body">

            <h5 class="font-weight-bold mb-2">
                <?= e($r['nama']) ?>
            </h5>

            <div class="text-muted small mb-3">
                Kapasitas <?= $r['kapasitas'] ?> Orang
            </div>

            <span class="room-status <?= $dot ?>"></span>

            <span class="badge badge-<?= $badge ?>">
                <?= $label ?>
            </span>

        </div>

    </div>

</div>

<?php endforeach; ?>

</div>

<!-- RINGKASAN -->
<div class="alert alert-light border shadow-sm mb-4">

    <i class="fas fa-info-circle text-primary mr-2"></i>

    Saat ini terdapat

    <strong><?= $ruangPakai ?></strong>

    dari

    <strong><?= $totalRuang ?></strong>

    ruangan yang sedang digunakan.

    <?php if($menunggu>0): ?>

    <span class="text-danger ml-3">

        <i class="fas fa-clock"></i>

        <?= $menunggu ?>

        pengajuan menunggu persetujuan.

    </span>

    <?php endif; ?>

</div>

<!-- STATISTIK -->
<div class="row">

<div class="col-lg-3 col-md-6">
    <div class="card stat-card shadow-sm">
        <div class="card-body text-center">
            <i class="fas fa-door-open fa-2x text-info mb-3"></i>
            <h3><?= $statHari['total'] ?? 0 ?></h3>
            <p class="mb-0">Peminjaman Hari Ini</p>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="card stat-card shadow-sm">
        <div class="card-body text-center">
            <i class="fas fa-calendar-check fa-2x text-success mb-3"></i>
            <h3><?= $statBulan['total'] ?? 0 ?></h3>
            <p class="mb-0">Peminjaman Bulan Ini</p>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="card stat-card shadow-sm">
        <div class="card-body text-center">
            <i class="fas fa-building fa-2x text-warning mb-3"></i>
            <h3><?= $ruangPakai ?>/<?= $totalRuang ?></h3>
            <p class="mb-0">Ruangan Terpakai</p>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="card stat-card shadow-sm">
        <div class="card-body text-center">
            <i class="fas fa-clock fa-2x text-danger mb-3"></i>
            <h3><?= $menunggu ?></h3>
            <p class="mb-0">Menunggu Persetujuan</p>
        </div>
    </div>
</div>

</div>

<!-- GRAFIK -->
<div class="card shadow-sm mb-4">

    <div class="card-header bg-white border-0">

        <h5 class="mb-0">

            <i class="fas fa-chart-bar text-primary mr-2"></i>

            Grafik Peminjaman <?= date('Y') ?>

        </h5>

    </div>

    <div class="card-body">

        <canvas id="chartBulanan" height="90"></canvas>

    </div>

</div>
<?php
$extraScript = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById("chartBulanan"),{
  type:"bar",
  data:{
    labels:["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agt","Sep","Okt","Nov","Des"],
    datasets:[{label:"Peminjaman",data:'.json_encode(array_values($grafikData)).',
      backgroundColor:"rgba(24,95,165,.7)",borderColor:"#185FA5",borderWidth:1,borderRadius:4}]
  },
  options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});
function aksi(id,act){
  Swal.fire({title:act==="setujui"?"Setujui peminjaman ini?":"Tolak?",icon:"question",
    showCancelButton:true,confirmButtonText:"Ya",cancelButtonText:"Batal"}).then(r=>{
    if(!r.isConfirmed)return;
    $.post(BASE_URL+"ajax/peminjaman_aksi.php",{id,aksi:act,csrf_token:CSRF})
      .done(d=>{if(d.status==="success"){toastOk(d.message);setTimeout(()=>location.reload(),1500);}else toastErr(d.message);});
  });
}
function aksiTolak(id){
  Swal.fire({title:"Alasan penolakan",input:"textarea",inputPlaceholder:"Tulis alasan...",
    icon:"warning",showCancelButton:true,confirmButtonText:"Tolak",confirmButtonColor:"#dc3545"}).then(r=>{
    if(!r.isConfirmed)return;
    $.post(BASE_URL+"ajax/peminjaman_aksi.php",{id,aksi:"tolak",alasan:r.value,csrf_token:CSRF})
      .done(d=>{if(d.status==="success"){toastOk(d.message);setTimeout(()=>location.reload(),1500);}else toastErr(d.message);});
  });
}
</script>';
include __DIR__ . '/includes/footer.php';
?>