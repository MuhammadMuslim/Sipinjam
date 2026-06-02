<?php
require_once __DIR__ . '/config/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action==='simpan') {
        $id   = (int)($_POST['id']??0);
        $data = [
            'kode'      => sanitize($_POST['kode']??''),
            'nama'      => sanitize($_POST['nama']??''),
            'kapasitas' => (int)($_POST['kapasitas']??0),
            'lantai'    => (int)($_POST['lantai']??1),
            'fasilitas' => sanitize($_POST['fasilitas']??''),
            'status'    => sanitize($_POST['status']??'aktif'),
        ];

        // Handle upload foto
        if (!empty($_FILES['foto']['name'])) {
            $uploadDir = __DIR__ . '/uploads/ruangan/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext      = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed) && $_FILES['foto']['size'] <= 2*1024*1024) {
                $filename = 'ruang_' . time() . '_' . mt_rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $filename)) {
                    // Hapus foto lama jika edit
                    if ($id) {
                        $old = dbFetchOne("SELECT foto FROM ruang WHERE id=?", [$id], 'i');
                        if (!empty($old['foto']) && file_exists($uploadDir . $old['foto'])) {
                            unlink($uploadDir . $old['foto']);
                        }
                    }
                    $data['foto'] = $filename;
                }
            } else {
                jsonResponse(['status'=>'error','message'=>'Format foto harus JPG/PNG/WEBP, maks 2MB.']);
            }
        }

        // Hapus foto jika diminta (tanpa upload baru)
        if (empty($_FILES['foto']['name']) && ($_POST['hapus_foto']??'0') === '1' && $id) {
            $old = dbFetchOne("SELECT foto FROM ruang WHERE id=?", [$id], 'i');
            if (!empty($old['foto'])) {
                $uploadDir = __DIR__ . '/uploads/ruangan/';
                if (file_exists($uploadDir . $old['foto'])) unlink($uploadDir . $old['foto']);
            }
            $data['foto'] = null;
        }

        if ($id) { dbUpdate('ruang',$data,'id=?',[$id]); jsonResponse(['status'=>'success','message'=>'Data berhasil diperbarui.']); }
        else     { dbInsert('ruang',$data); jsonResponse(['status'=>'success','message'=>'Ruangan berhasil ditambahkan.']); }
    }
    if ($action==='hapus') { dbDelete('ruang','id=?',[(int)($_POST['id']??0)]); jsonResponse(['status'=>'success','message'=>'Ruangan berhasil dihapus.']); }
    if ($action==='get')   { jsonResponse(dbFetchOne("SELECT * FROM ruang WHERE id=?",[(int)($_GET['id']??0)],'i')??[]); }
}

$ruangan   = dbFetchAll("SELECT * FROM ruang ORDER BY nama");
$pageTitle = 'Master Ruangan';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-header border-0 d-flex justify-content-between align-items-center">
    <h3 class="card-title mb-0"><i class="fas fa-building mr-2 text-primary"></i>Data Ruangan</h3>
    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalRuang"><i class="fas fa-plus mr-1"></i>Tambah</button>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" data-datatable>
        <thead class="thead-light">
          <tr><th>Kode</th><th>Foto</th><th>Nama</th><th>Kapasitas</th><th>Lantai</th><th>Fasilitas</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($ruangan as $r): ?>
          <tr>
            <td><?= e($r['kode']) ?></td>
            <td>
              <?php if(!empty($r['foto'])): ?>
              <img src="<?= BASE_URL ?>uploads/ruangan/<?= e($r['foto']) ?>"
                   style="width:60px;height:45px;object-fit:cover;border-radius:6px;">
              <?php else: ?>
              <span class="text-muted small"><i class="fas fa-image mr-1"></i>—</span>
              <?php endif; ?>
            </td>
            <td><?= e($r['nama']) ?></td>
            <td><?= e($r['kapasitas']) ?> org</td><td>Lantai <?= e($r['lantai']) ?></td>
            <td><small><?= e($r['fasilitas']) ?></small></td>
            <td><span class="badge badge-<?= $r['status']==='aktif'?'success':($r['status']==='perbaikan'?'warning':'secondary') ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
              <button onclick="editRuang(<?= $r['id'] ?>)" class="btn btn-xs btn-outline-warning"><i class="fas fa-edit"></i></button>
              <button onclick="hapusRuang(<?= $r['id'] ?>)" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalRuang"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="modalRuangTitle">Tambah Ruangan</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
  <form id="formRuang" enctype="multipart/form-data">
  <div class="modal-body">
    <input type="hidden" id="ruangId" name="id" value="0">
    <div class="form-row">
      <div class="form-group col-md-4"><label>Kode *</label><input type="text" name="kode" id="ruangKode" class="form-control" required placeholder="R101"></div>
      <div class="form-group col-md-4"><label>Kapasitas *</label><input type="number" name="kapasitas" id="ruangKap" class="form-control" required></div>
      <div class="form-group col-md-4"><label>Lantai</label><input type="number" name="lantai" id="ruangLantai" class="form-control" value="1" min="1"></div>
    </div>
    <div class="form-group"><label>Nama Ruangan *</label><input type="text" name="nama" id="ruangNama" class="form-control" required></div>
    <div class="form-group"><label>Fasilitas</label><input type="text" name="fasilitas" id="ruangFas" class="form-control" placeholder="AC, Proyektor, ..."></div>
    <div class="form-group">
      <label>Foto Ruangan <small class="text-muted">(JPG/PNG/WEBP, maks 2MB)</small></label>
      <div id="fotoPreviewWrap" class="mb-2" style="display:none">
        <img id="fotoPreview" src="" alt="preview"
             style="width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6">
        <button type="button" class="btn btn-xs btn-outline-danger mt-1" id="btnHapusFoto">
          <i class="fas fa-times mr-1"></i>Hapus foto
        </button>
      </div>
      <input type="file" name="foto" id="ruangFoto" class="form-control-file" accept="image/*">
      <input type="hidden" name="hapus_foto" id="hapusFoto" value="0">
    </div>
    <div class="form-group"><label>Status</label>
      <select name="status" id="ruangStatus" class="form-control">
        <option value="aktif">Aktif</option>
        <option value="perbaikan">Perbaikan</option>
        <option value="nonaktif">Nonaktif</option>
      </select>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Simpan</button>
  </div>
  </form>
</div></div></div>

<?php
$extraScript='<script>
// Preview foto saat pilih file
$("#ruangFoto").on("change", function(){
  const file = this.files[0];
  if(!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    $("#fotoPreview").attr("src", e.target.result);
    $("#fotoPreviewWrap").show();
  };
  reader.readAsDataURL(file);
});

// Hapus foto (tandai hapus, sembunyikan preview)
$("#btnHapusFoto").on("click", function(){
  $("#fotoPreview").attr("src","");
  $("#fotoPreviewWrap").hide();
  $("#ruangFoto").val("");
  $("#hapusFoto").val("1");
});

function editRuang(id){
  $.get(BASE_URL+"ruangan.php",{action:"get",id}).done(d=>{
    $("#ruangId").val(d.id);$("#ruangKode").val(d.kode);$("#ruangNama").val(d.nama);
    $("#ruangKap").val(d.kapasitas);$("#ruangLantai").val(d.lantai);
    $("#ruangFas").val(d.fasilitas);$("#ruangStatus").val(d.status);
    // Tampilkan foto lama jika ada
    if(d.foto){
      $("#fotoPreview").attr("src", BASE_URL+"uploads/ruangan/"+d.foto);
      $("#fotoPreviewWrap").show();
    } else {
      $("#fotoPreviewWrap").hide();
    }
    $("#hapusFoto").val("0");
    $("#modalRuangTitle").text("Edit Ruangan");$("#modalRuang").modal("show");
  });
}
function hapusRuang(id){
  Swal.fire({title:"Hapus ruangan ini?",icon:"warning",showCancelButton:true,confirmButtonColor:"#dc3545",
    confirmButtonText:"Ya, Hapus"}).then(r=>{
    if(!r.isConfirmed)return;
    $.post(BASE_URL+"ruangan.php?action=hapus",{id,csrf_token:CSRF}).done(d=>{
      if(d.status==="success"){toastOk(d.message);setTimeout(()=>location.reload(),1000);}
    });
  });
}
$("#formRuang").on("submit",function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append("csrf_token", CSRF);
  $.ajax({
    url: BASE_URL+"ruangan.php?action=simpan",
    method: "POST",
    data: fd,
    processData: false,
    contentType: false
  }).done(d=>{
    if(d.status==="success"){toastOk(d.message);$("#modalRuang").modal("hide");setTimeout(()=>location.reload(),1000);}
    else toastErr(d.message);
  });
});
$("#modalRuang").on("hidden.bs.modal",function(){
  $("#formRuang")[0].reset();
  $("#ruangId").val(0);
  $("#modalRuangTitle").text("Tambah Ruangan");
  $("#fotoPreviewWrap").hide();
  $("#hapusFoto").val("0");
});
</script>';
include __DIR__ . '/includes/footer.php';
?>