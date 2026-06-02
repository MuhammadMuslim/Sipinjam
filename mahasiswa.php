<?php
require_once __DIR__ . '/config/functions.php';
requireLogin();

// Handle AJAX
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action==='simpan') {
        $id    = (int)($_POST['id']??0);
        $data  = [
            'nim'      => sanitize($_POST['nim']??''),
            'nama'     => sanitize($_POST['nama']??''),
            'prodi'    => sanitize($_POST['prodi']??''),
            'angkatan' => (int)($_POST['angkatan']??0),
            'no_hp'    => sanitize($_POST['no_hp']??''),
            'email'    => sanitize($_POST['email']??''),
        ];
        if ($id) { dbUpdate('mahasiswa',$data,'id=?',[$id]); jsonResponse(['status'=>'success','message'=>'Data berhasil diperbarui.']); }
        else     { dbInsert('mahasiswa',$data); jsonResponse(['status'=>'success','message'=>'Mahasiswa berhasil ditambahkan.']); }
    }
    if ($action==='hapus') {
        $id = (int)($_POST['id']??0);
        dbDelete('mahasiswa','id=?',[$id]);
        jsonResponse(['status'=>'success','message'=>'Data berhasil dihapus.']);
    }
    if ($action==='get') {
        $id  = (int)($_GET['id']??0);
        jsonResponse(dbFetchOne("SELECT * FROM mahasiswa WHERE id=?",[$id],'i') ?? []);
    }

if ($action==='import') {

    require_once __DIR__.'/vendor/autoload.php';


    if (!isset($_FILES['file_excel'])) {
        jsonResponse([
            'status'=>'error',
            'message'=>'File tidak ditemukan'
        ]);
    }

    $file = $_FILES['file_excel']['tmp_name'];

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    $berhasil = 0;

    foreach(array_slice($rows,1) as $row){

        if(empty($row[0])) continue;

        dbInsert('mahasiswa',[
            'nim'      => trim($row[0]),
            'nama'     => trim($row[1]),
            'prodi'    => trim($row[2]),
            'angkatan' => (int)$row[3],
            'no_hp'    => trim($row[4]),
            'email'    => trim($row[5])
        ]);

        $berhasil++;
    }

    jsonResponse([
        'status'=>'success',
        'message'=>"$berhasil data berhasil diimport"
    ]);
}

}


$keyword = $_GET['q'] ?? '';
$keyword = htmlspecialchars($keyword);

if ($keyword) {
    $mahasiswa = dbFetchAll(
        "SELECT * FROM mahasiswa WHERE nim LIKE '%$keyword%' OR nama LIKE '%$keyword%' ORDER BY nama"
    );
} else {
    $mahasiswa = dbFetchAll("SELECT * FROM mahasiswa ORDER BY nama");
}

$pageTitle = 'Master Mahasiswa';
include __DIR__ . '/includes/header.php';
?>
<div class="card">

  <div>
    <button class="btn btn-sm btn-success mr-1" data-toggle="modal" data-target="#modalImport"><i class="fas fa-file-excel"></i> Import Excel</button>
    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalMhs"><i class="fas fa-plus"></i> Tambah</button>
</div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" data-datatable>
        <thead class="thead-light">
          <tr><th>NIM</th><th>Nama</th><th>Prodi</th><th>Angkatan</th><th>No. HP</th><th>Email</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($mahasiswa as $m): ?>
          <tr>
            <td><?= e($m['nim']) ?></td><td><?= e($m['nama']) ?></td><td><?= e($m['prodi']) ?></td>
            <td><?= e($m['angkatan']) ?></td><td><?= e($m['no_hp']) ?></td><td><?= e($m['email']) ?></td>
            <td>
              <button onclick="editMhs(<?= $m['id'] ?>)" class="btn btn-xs btn-outline-warning"><i class="fas fa-edit"></i></button>
              <button onclick="konfirmHapus('mahasiswa.php?action=hapus','mahasiswa ini',<?= $m['id'] ?>)" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalMhs"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="modalMhsTitle">Tambah Mahasiswa</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
  <form id="formMhs">
  <div class="modal-body">
    <input type="hidden" id="mhsId" name="id" value="0">
    <div class="form-row">
      <div class="form-group col-md-6"><label>NIM *</label><input type="text" name="nim" id="mhsNim" class="form-control" required></div>
      <div class="form-group col-md-6"><label>Angkatan *</label><input type="number" name="angkatan" id="mhsAngkatan" class="form-control" required></div>
    </div>
    <div class="form-group"><label>Nama *</label><input type="text" name="nama" id="mhsNama" class="form-control" required></div>
    <div class="form-group"><label>Program Studi *</label>
      <select name="prodi" id="mhsProdi" class="form-control">
        <?php foreach(['Teknik Informatika','Sistem Informasi','Teknik Elektro','Teknik Sipil','Manajemen'] as $p): ?>
        <option><?= $p ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <div class="form-group col-md-6"><label>No. HP</label><input type="text" name="no_hp" id="mhsHp" class="form-control"></div>
      <div class="form-group col-md-6"><label>Email</label><input type="email" name="email" id="mhsEmail" class="form-control"></div>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Simpan</button>
  </div>
  </form>
</div></div></div>

<div class="modal fade" id="modalImport">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Import Data Mahasiswa</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form id="formImport" enctype="multipart/form-data">

                <div class="modal-body">

                    <div class="alert alert-info">
                        Format Excel:
                        <br>
                        nim | nama | prodi | angkatan | no_hp | email
                    </div>

                    <input type="file"
                           name="file_excel"
                           accept=".xlsx,.xls"
                           class="form-control"
                           required>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        Import
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<?php
$extraScript = '
<script>
$("#formImport").on("submit", function(e){

    e.preventDefault();

    let formData = new FormData(this);

    formData.append("csrf_token", CSRF);

    $.ajax({
        url: BASE_URL + "mahasiswa.php?action=import",
        type: "POST",
        data: formData,
        processData:false,
        contentType:false,
        success:function(d){

            if(d.status==="success"){

                toastOk(d.message);

                $("#modalImport").modal("hide");

                setTimeout(()=>{
                    location.reload();
                },1000);

            }else{
                toastErr(d.message);
            }
        }
    });

});
function editMhs(id){
  $.get(BASE_URL+"mahasiswa.php",{action:"get",id}).done(d=>{
    $("#mhsId").val(d.id); $("#mhsNim").val(d.nim); $("#mhsNama").val(d.nama);
    $("#mhsProdi").val(d.prodi); $("#mhsAngkatan").val(d.angkatan);
    $("#mhsHp").val(d.no_hp); $("#mhsEmail").val(d.email);
    $("#modalMhsTitle").text("Edit Mahasiswa");
    $("#modalMhs").modal("show");
  });
}
function konfirmHapus(url, label, id){
  Swal.fire({title:"Hapus "+label+"?",text:"Data tidak bisa dikembalikan.",icon:"warning",
    showCancelButton:true,confirmButtonColor:"#dc3545",confirmButtonText:"Ya, Hapus",cancelButtonText:"Batal"
  }).then(r=>{
    if(!r.isConfirmed)return;
    $.post(url+"&action=hapus",{id,csrf_token:CSRF}).done(d=>{
      if(d.status==="success"){toastOk(d.message);setTimeout(()=>location.reload(),1200);}else toastErr(d.message);
    });
  });
}
$("#formMhs").on("submit",function(e){
  e.preventDefault();
  $.post(BASE_URL+"mahasiswa.php?action=simpan",$(this).serialize()+"&csrf_token="+CSRF).done(d=>{
    if(d.status==="success"){toastOk(d.message);$("#modalMhs").modal("hide");setTimeout(()=>location.reload(),1000);}
    else toastErr(d.message);
  });
});
$("#modalMhs").on("hidden.bs.modal",function(){
  $("#formMhs")[0].reset(); $("#mhsId").val(0); $("#modalMhsTitle").text("Tambah Mahasiswa");
});
</script>';
include __DIR__ . '/includes/footer.php';
?>
