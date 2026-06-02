<?php
require_once __DIR__ . '/config/functions.php';
requireLogin();
if (!hasRole('super_admin','admin')) { die('Akses ditolak.'); }

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action==='simpan') {
        $id   = (int)($_POST['id']??0);
        $data = [
            'nama'      => sanitize($_POST['nama']??''),
            'username'  => sanitize($_POST['username']??''),
            'role'      => sanitize($_POST['role']??'operator'),
            'is_active' => (int)($_POST['is_active']??1),
        ];
        $pwd = $_POST['password']??'';
        if ($pwd) $data['password'] = password_hash($pwd, PASSWORD_BCRYPT);
        if ($id) {
            dbUpdate('users',$data,'id=?',[$id]);
            jsonResponse(['status'=>'success','message'=>'User berhasil diperbarui.']);
        } else {
            if (!$pwd) jsonResponse(['status'=>'error','message'=>'Password wajib diisi.']);
            $data['password'] = password_hash($pwd, PASSWORD_BCRYPT);
            dbInsert('users',$data);
            jsonResponse(['status'=>'success','message'=>'User berhasil ditambahkan.']);
        }
    }
    if ($action==='hapus') {
        $id = (int)($_POST['id']??0);
        if ($id === (int)$_SESSION['user_id']) jsonResponse(['status'=>'error','message'=>'Tidak bisa menghapus akun sendiri.']);
        dbDelete('users','id=?',[$id]);
        jsonResponse(['status'=>'success','message'=>'User berhasil dihapus.']);
    }
    if ($action==='get') {
        $row = dbFetchOne("SELECT id,nama,username,role,is_active FROM users WHERE id=?",[(int)($_GET['id']??0)],'i');
        jsonResponse($row??[]);
    }
}

$users     = dbFetchAll("SELECT * FROM users ORDER BY nama");
$pageTitle = 'Manajemen Admin';
include __DIR__ . '/includes/header.php';
?>
<div class="row">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-users mr-2 text-primary"></i>Daftar Pengguna</h3>
        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalUser"><i class="fas fa-plus mr-1"></i>Tambah User</button>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="thead-light"><tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?= e($u['nama']) ?></td>
              <td><?= e($u['username']) ?></td>
              <td><span class="badge badge-<?= $u['role']==='super_admin'?'danger':($u['role']==='admin'?'primary':'secondary') ?>"><?= e($u['role']) ?></span></td>
              <td><span class="badge badge-<?= $u['is_active']?'success':'secondary' ?>"><?= $u['is_active']?'Aktif':'Nonaktif' ?></span></td>
              <td><small><?= $u['last_login'] ? date('d/m/Y H:i',strtotime($u['last_login'])) : '—' ?></small></td>
              <td>
                <button onclick="editUser(<?= $u['id'] ?>)" class="btn btn-xs btn-outline-warning"><i class="fas fa-edit"></i></button>
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <button onclick="hapusUser(<?= $u['id'] ?>)" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-info-circle mr-2 text-primary"></i>Info Login</h3></div>
      <div class="card-body">
        <p><strong>Login saat ini:</strong><br><?= e($_SESSION['user']['nama']) ?></p>
        <p><strong>Role:</strong> <?= e($_SESSION['user']['role']) ?></p>
        <hr>
        <p class="text-muted small">Password disimpan dalam format bcrypt. Kosongkan field password saat edit jika tidak ingin mengubah.</p>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalUser"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="modalUserTitle">Tambah User</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
  <form id="formUser">
  <div class="modal-body">
    <input type="hidden" id="userId" name="id" value="0">
    <div class="form-group"><label>Nama Lengkap *</label><input type="text" name="nama" id="uNama" class="form-control" required></div>
    <div class="form-group"><label>Username *</label><input type="text" name="username" id="uUsername" class="form-control" required></div>
    <div class="form-group"><label>Password <span id="pwdHint" class="text-muted small">(wajib diisi)</span></label>
      <input type="password" name="password" id="uPassword" class="form-control" placeholder="Min. 8 karakter">
    </div>
    <div class="form-row">
      <div class="form-group col-md-6"><label>Role</label>
        <select name="role" id="uRole" class="form-control">
          <option value="operator">Operator</option>
          <option value="admin">Admin</option>
          <option value="super_admin">Super Admin</option>
        </select>
      </div>
      <div class="form-group col-md-6"><label>Status</label>
        <select name="is_active" id="uAktif" class="form-control"><option value="1">Aktif</option><option value="0">Nonaktif</option></select>
      </div>
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
function editUser(id){
  $.get(BASE_URL+"admin.php",{action:"get",id}).done(d=>{
    $("#userId").val(d.id);$("#uNama").val(d.nama);$("#uUsername").val(d.username);
    $("#uRole").val(d.role);$("#uAktif").val(d.is_active);
    $("#uPassword").attr("placeholder","Kosongkan jika tidak diubah");
    $("#pwdHint").text("(kosongkan jika tidak diubah)");
    $("#modalUserTitle").text("Edit User");$("#modalUser").modal("show");
  });
}
function hapusUser(id){
  Swal.fire({title:"Hapus user ini?",icon:"warning",showCancelButton:true,
    confirmButtonColor:"#dc3545",confirmButtonText:"Ya, Hapus"}).then(r=>{
    if(!r.isConfirmed)return;
    $.post(BASE_URL+"admin.php?action=hapus",{id,csrf_token:CSRF}).done(d=>{
      if(d.status==="success"){toastOk(d.message);setTimeout(()=>location.reload(),1000);}else toastErr(d.message);
    });
  });
}
$("#formUser").on("submit",function(e){
  e.preventDefault();
  $.post(BASE_URL+"admin.php?action=simpan",$(this).serialize()+"&csrf_token="+CSRF).done(d=>{
    if(d.status==="success"){toastOk(d.message);$("#modalUser").modal("hide");setTimeout(()=>location.reload(),1000);}
    else toastErr(d.message);
  });
});
$("#modalUser").on("hidden.bs.modal",function(){
  $("#formUser")[0].reset();$("#userId").val(0);
  $("#modalUserTitle").text("Tambah User");
  $("#pwdHint").text("(wajib diisi)");
  $("#uPassword").attr("placeholder","Min. 8 karakter");
});
</script>';
include __DIR__ . '/includes/footer.php';
?>
