<?php
require_once __DIR__ . '/config/functions.php';
requireLogin();

// Simpan konfigurasi ke file .env sederhana
$configFile = __DIR__ . '/config/wa_config.json';
$config     = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
$templates  = $config['templates'] ?? [
    'pengajuan' => "Halo *{nama}*, peminjaman ruangan *{ruangan}* pada {tanggal} pukul {jam_mulai}–{jam_selesai} telah diterima. Kode: {kode}. Status: menunggu persetujuan.\n_SiPinjam_",
    'disetujui' => "✅ *Peminjaman Disetujui!*\nHalo *{nama}*, peminjaman Anda telah disetujui.\nRuangan: {ruangan} | {tanggal} | {jam_mulai}–{jam_selesai}\nKode: {kode}\n_SiPinjam_",
    'ditolak'   => "❌ *Peminjaman Ditolak*\nHalo *{nama}*, peminjaman Kode {kode} ditolak.\nAlasan: {alasan}\nSilakan ajukan kembali.\n_SiPinjam_",
];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = sanitize($_POST['action']??'');
    if ($action==='config') {
        $config['api_key']   = sanitize($_POST['api_key']??'');
        $config['templates'] = $templates;
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        jsonResponse(['status'=>'success','message'=>'Konfigurasi berhasil disimpan.']);
    }
    if ($action==='template') {
        $config['templates'] = [
            'pengajuan' => $_POST['tpl_pengajuan']??'',
            'disetujui' => $_POST['tpl_disetujui']??'',
            'ditolak'   => $_POST['tpl_ditolak']??'',
        ];
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        jsonResponse(['status'=>'success','message'=>'Template berhasil disimpan.']);
    }
    if ($action==='test') {
        $target  = sanitize($_POST['target']??'');
        $message = sanitize($_POST['message']??'');
        $result  = kirimWA($target, $message);
        jsonResponse(['status'=>$result?'success':'error','message'=>$result?'Pesan berhasil dikirim!':'Gagal kirim. Cek API key.']);
    }
}

// Log WA (10 terbaru)
$logWa = dbFetchAll("SELECT p.kode_pinjam,m.nama AS nama_mhs,m.no_hp,p.wa_notif_sent,p.updated_at
    FROM peminjaman p JOIN mahasiswa m ON m.id=p.mahasiswa_id
    ORDER BY p.updated_at DESC LIMIT 20");

$pageTitle = 'Notifikasi WhatsApp';
include __DIR__ . '/includes/header.php';
?>
<div class="row">
  <!-- Config -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fab fa-whatsapp mr-2 text-success"></i>Konfigurasi Fonnte API</h3></div>
      <div class="card-body">
        <div class="alert alert-info py-2"><i class="fas fa-info-circle mr-1"></i>
          Daftar API key di <a href="https://fonnte.com" target="_blank">fonnte.com</a>, lalu pairing nomor WhatsApp Anda.
        </div>
        <form id="formConfig">
          <input type="hidden" name="action" value="config">
          <div class="form-group">
            <label>API Key Fonnte</label>
            <input type="password" name="api_key" class="form-control" value="<?= e($config['api_key']??'') ?>" placeholder="Token dari dashboard Fonnte">
          </div>
          <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save mr-1"></i>Simpan Konfigurasi</button>
        </form>
        <hr>
        <h6 class="font-weight-bold mt-3">Test Kirim Pesan</h6>
        <form id="formTest">
          <input type="hidden" name="action" value="test">
          <div class="form-row">
            <div class="form-group col-md-5">
              <input type="text" name="target" class="form-control form-control-sm" placeholder="628xxxxxxxxxx">
            </div>
            <div class="form-group col-md-7">
              <input type="text" name="message" class="form-control form-control-sm" placeholder="Pesan test...">
            </div>
          </div>
          <button type="submit" class="btn btn-outline-success btn-sm"><i class="fas fa-paper-plane mr-1"></i>Kirim Test</button>
        </form>
      </div>
    </div>

    <!-- Template -->
    <div class="card">
      <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-comment-dots mr-2 text-primary"></i>Template Pesan</h3></div>
      <div class="card-body">
        <p class="text-muted small">Variabel: <code>{nama} {nim} {ruangan} {tanggal} {jam_mulai} {jam_selesai} {kode} {matkul} {dosen} {alasan}</code></p>
        <form id="formTemplate">
          <input type="hidden" name="action" value="template">
          <div class="form-group">
            <label class="font-weight-bold">Pengajuan Baru</label>
            <textarea name="tpl_pengajuan" class="form-control form-control-sm" rows="3"><?= e($templates['pengajuan']) ?></textarea>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Disetujui</label>
            <textarea name="tpl_disetujui" class="form-control form-control-sm" rows="3"><?= e($templates['disetujui']) ?></textarea>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Ditolak</label>
            <textarea name="tpl_ditolak" class="form-control form-control-sm" rows="3"><?= e($templates['ditolak']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Simpan Template</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Log -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-history mr-2 text-primary"></i>Log Notifikasi WA</h3></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="thead-light"><tr><th>Waktu</th><th>Mahasiswa</th><th>No. HP</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($logWa as $l): ?>
            <tr>
              <td><small><?= date('d/m H:i',strtotime($l['updated_at'])) ?></small></td>
              <td><small><?= e($l['nama_mhs']) ?></small></td>
              <td><small><?= e($l['no_hp']) ?></small></td>
              <td><?= $l['wa_notif_sent'] ? '<span class="badge badge-success">Terkirim</span>' : '<span class="badge badge-secondary">Belum</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$extraScript='<script>
$("#formConfig,#formTemplate,#formTest").on("submit",function(e){
  e.preventDefault();
  $.post(BASE_URL+"notifikasi.php",$(this).serialize()+"&csrf_token="+CSRF).done(d=>{
    if(d.status==="success")toastOk(d.message);else toastErr(d.message);
  });
});
</script>';
include __DIR__ . '/includes/footer.php';
?>
