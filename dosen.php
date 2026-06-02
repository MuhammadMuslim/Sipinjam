<?php
require_once __DIR__ . '/config/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
if ($action === 'import') {

    require_once __DIR__ . '/vendor/autoload.php';

    if (!isset($_FILES['file_excel'])) {
        jsonResponse([
            'status' => 'error',
            'message' => 'File tidak ditemukan'
        ]);
    }

    $file = $_FILES['file_excel']['tmp_name'];

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    } catch (Exception $e) {
        jsonResponse([
            'status' => 'error',
            'message' => 'File Excel tidak valid'
        ]);
    }

    $sheet = $spreadsheet->getActiveSheet();
    $rows  = $sheet->toArray();

    $berhasil = 0;
    $skip = 0;

    foreach (array_slice($rows, 1) as $row) {

        if (empty($row[0])) continue;

        // optional: hindari duplikat NIDN
        $cek = dbFetchOne("SELECT id FROM dosen WHERE nidn=?", [trim($row[1])], 's');

        if ($cek) {
            $skip++;
            continue;
        }

    dbInsert('dosen', [
    'nidn'     => trim($row[0]),
    'nama'     => trim($row[1]),
    'jabatan'  => trim($row[2]),
    'no_wa'    => trim($row[3]),
    'email'    => trim($row[4]),
    'is_active'=> 1
  ]);

        $berhasil++;
    }

    jsonResponse([
        'status'  => 'success',
        'message' => "Import selesai. Berhasil: $berhasil, Dilewati (duplikat): $skip"
    ]);
}
    if ($action==='hapus') { dbDelete('dosen','id=?',[(int)($_POST['id']??0)]); jsonResponse(['status'=>'success','message'=>'Data berhasil dihapus.']); }
    if ($action==='get')   { jsonResponse(dbFetchOne("SELECT * FROM dosen WHERE id=?",[(int)($_GET['id']??0)],'i')??[]); }
}

$dosen     = dbFetchAll("SELECT * FROM dosen ORDER BY nama");
$pageTitle = 'Master Dosen';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
<div>
    <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#modalImportDosen"><i class="fas fa-file-excel"></i> Import Excel</button>
      <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalDosen"><i class="fas fa-plus mr-1"></i>Tambah</button>
    </button>
</div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" data-datatable>
        <thead class="thead-light">
          <tr><th>NIDN</th><th>Nama</th><th>Jabatan</th><th>No. WA</th><th>Email</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($dosen as $d): ?>
          <tr>
            <td><?= e($d['nidn']) ?></td><td><?= e($d['nama']) ?></td><td><?= e($d['jabatan']) ?></td>
            <td><?= e($d['no_wa']) ?></td><td><?= e($d['email']) ?></td>
            <td><span class="badge badge-<?= $d['is_active']?'success':'secondary' ?>"><?= $d['is_active']?'Aktif':'Nonaktif' ?></span></td>
            <td>
              <button onclick="editDosen(<?= $d['id'] ?>)" class="btn btn-xs btn-outline-warning"><i class="fas fa-edit"></i></button>
              <button onclick="hapusDosen(<?= $d['id'] ?>)" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalImportDosen">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Import Data Dosen</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <form id="formImportDosen" enctype="multipart/form-data">

        <div class="modal-body">

          <div class="alert alert-info">
            Format Excel: nama | nidn | no_wa | email
          </div>

          <input type="file" name="file_excel" class="form-control" required>

        </div>

        <div class="modal-footer">
          <button class="btn btn-success">Import</button>
        </div>

      </form>

    </div>
  </div>
</div>

<div class="modal fade" id="modalDosen"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="modalDosenTitle">Tambah Dosen</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
  <form id="formDosen">
  <div class="modal-body">
    <input type="hidden" id="dosenId" name="id" value="0">
    <div class="form-row">
      <div class="form-group col-md-6"><label>NIDN *</label><input type="text" name="nidn" id="dosenNidn" class="form-control" required></div>
      <div class="form-group col-md-6"><label>Jabatan</label><input type="text" name="jabatan" id="dosenJabatan" class="form-control"></div>
    </div>
    <div class="form-group"><label>Nama *</label><input type="text" name="nama" id="dosenNama" class="form-control" required></div>
    <div class="form-row">
      <div class="form-group col-md-6"><label>No. WhatsApp</label><input type="text" name="no_wa" id="dosenWa" class="form-control" placeholder="628xxxxxxxx"></div>
      <div class="form-group col-md-6"><label>Email</label><input type="email" name="email" id="dosenEmail" class="form-control"></div>
    </div>
    <div class="form-group"><label>Status</label>
      <select name="is_active" id="dosenAktif" class="form-control"><option value="1">Aktif</option><option value="0">Nonaktif</option></select>
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
function editDosen(id){
  $.get(BASE_URL+"dosen.php",{action:"get",id}).done(d=>{
    $("#dosenId").val(d.id);$("#dosenNidn").val(d.nidn);$("#dosenNama").val(d.nama);
    $("#dosenJabatan").val(d.jabatan);$("#dosenWa").val(d.no_wa);$("#dosenEmail").val(d.email);
    $("#dosenAktif").val(d.is_active);$("#modalDosenTitle").text("Edit Dosen");
    $("#modalDosen").modal("show");
  });
}

$("#formImportDosen").on("submit", function(e){

    e.preventDefault();

    let fd = new FormData(this);
    fd.append("csrf_token", CSRF);

    $.ajax({
        url: BASE_URL + "dosen.php?action=import",
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function(res){

            if(res.status === "success"){
                toastOk(res.message);
                $("#modalImportDosen").modal("hide");
                setTimeout(()=>location.reload(),1000);
            } else {
                toastErr(res.message);
            }
        }
    });

});
function hapusDosen(id){
  Swal.fire({title:"Hapus dosen ini?",icon:"warning",showCancelButton:true,confirmButtonColor:"#dc3545",
    confirmButtonText:"Ya, Hapus"}).then(r=>{
    if(!r.isConfirmed)return;
    $.post(BASE_URL+"dosen.php?action=hapus",{id,csrf_token:CSRF}).done(d=>{
      if(d.status==="success"){toastOk(d.message);setTimeout(()=>location.reload(),1000);}
    });
  });
}
$("#formDosen").on("submit",function(e){
  e.preventDefault();
  $.post(BASE_URL+"dosen.php?action=simpan",$(this).serialize()+"&csrf_token="+CSRF).done(d=>{
    if(d.status==="success"){toastOk(d.message);$("#modalDosen").modal("hide");setTimeout(()=>location.reload(),1000);}
    else toastErr(d.message);
  });
});
$("#modalDosen").on("hidden.bs.modal",function(){
  $("#formDosen")[0].reset();$("#dosenId").val(0);$("#modalDosenTitle").text("Tambah Dosen");
});
</script>';
include __DIR__ . '/includes/footer.php';
?>
