<?php
require_once __DIR__ . '/config/functions.php';

$dosen   = dbFetchAll("SELECT * FROM dosen WHERE is_active=1 ORDER BY nama");
$ruangan = dbFetchAll("SELECT * FROM ruang WHERE status='aktif' ORDER BY nama");

// Status ruangan: apakah sedang terpakai saat ini
$now  = date('H:i:s');
$today = date('Y-m-d');
$ruanganStatus = dbFetchAll("
    SELECT r.id, r.nama, r.kapasitas, r.status, r.foto,
           MAX(CASE
               WHEN p.status = 'disetujui'
                AND p.tanggal = '$today'
                AND p.jam_mulai <= '$now'
                AND p.jam_selesai > '$now'
               THEN 1 ELSE 0
           END) AS is_terpakai
    FROM ruang r
    LEFT JOIN peminjaman p ON p.ruang_id = r.id
    GROUP BY r.id, r.nama, r.kapasitas, r.status, r.foto
    ORDER BY r.nama
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Form Peminjaman Kelas — SiPinjam</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { background: #f0f4f8; font-family: 'Nunito', sans-serif; }
    .top-bar { background: #001f5b; color: #fff; padding: 12px 0; }
    .form-card { border-radius: .75rem; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
    .conflict-alert { display: none; }
    .step-label {
      font-size: .75rem; text-transform: uppercase; letter-spacing: .05em;
      color: #6c757d; margin-bottom: 4px; font-weight: 600;
    }

    /* ── Autocomplete shared styles ─────────────────────── */
    .autocomplete-wrapper { position: relative; }
    .autocomplete-dropdown {
      position: absolute; top: 100%; left: 0; right: 0; z-index: 9999;
      background: #fff; border: 1px solid #ced4da; border-top: none;
      border-radius: 0 0 .4rem .4rem;
      box-shadow: 0 6px 20px rgba(0,0,0,.1);
      max-height: 220px; overflow-y: auto; display: none;
    }
    .autocomplete-item {
      padding: 8px 14px; cursor: pointer;
      border-bottom: 1px solid #f0f4f8; transition: background .15s;
    }
    .autocomplete-item:last-child { border-bottom: none; }
    .autocomplete-item:hover,
    .autocomplete-item.active { background: #e8f0fe; }
    .autocomplete-item .ac-nama { font-weight: 600; font-size: .9rem; color: #212529; }
    .autocomplete-item .ac-sub  { font-size: .78rem; color: #6c757d; }
    .autocomplete-loading { padding: 10px 14px; color: #6c757d; font-size: .85rem; }

    /* ── Selected badge shared ──────────────────────────── */
    .selected-badge {
      display: none; align-items: center; gap: 8px;
      border-radius: .4rem; padding: 7px 12px; margin-top: 6px;
    }
    .selected-badge.green {
      background: #e9f5e9; border: 1px solid #b2dfb2;
    }
    .selected-badge .badge-nama  { font-weight: 700; font-size: .88rem; }
    .selected-badge .badge-sub   { font-size: .75rem; color: #555; }
    .selected-badge .btn-clear {
      margin-left: auto; background: none; border: none;
      color: #999; cursor: pointer; font-size: 1rem; line-height: 1;
    }
    .selected-badge .btn-clear:hover { color: #dc3545; }

    /* ── Error inline ───────────────────────────────────── */
    .inline-error {
      background: #fdecea; border: 1px solid #f5c2c7;
      border-radius: .4rem; padding: 10px 14px;
      display: none; margin-top: 6px;
    }

    /* ── Status Ruangan ─────────────────────────────────── */
    .dashboard-title   { font-size: 1.4rem; font-weight: 700; color: #001f5b; }
    .dashboard-subtitle{ color: #6c757d; font-size: .9rem; }
    .room-card { border-radius: .75rem; transition: transform .15s, box-shadow .15s; }
    .room-card:hover   { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.12) !important; }
    .room-status {
      display: inline-block; width: 10px; height: 10px;
      border-radius: 50%; margin-right: 6px; vertical-align: middle;
    }
    .room-free   { background: #28a745; box-shadow: 0 0 0 3px rgba(40,167,69,.25); }
    .room-used   { background: #dc3545; box-shadow: 0 0 0 3px rgba(220,53,69,.25); }
    .room-repair { background: #6c757d; box-shadow: 0 0 0 3px rgba(108,117,125,.25); }

    /* ── Foto Ruangan ───────────────────────────────────── */
    .room-img {
      width: 100%; height: 130px;
      object-fit: cover;
      border-radius: .75rem .75rem 0 0;
    }
    .room-img-placeholder {
      width: 100%; height: 130px;
      background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
      border-radius: .75rem .75rem 0 0;
      display: flex; align-items: center; justify-content: center;
      color: #adb5bd; font-size: 2.2rem;
    }
  </style>
</head>
<body>

<div class="top-bar">
  <div class="container d-flex align-items-center justify-content-between">
    <span><i class="fas fa-building mr-2"></i><strong>SiPinjam</strong> — Sistem Peminjaman Kelas</span>
    <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-outline-light">
      <i class="fas fa-lock mr-1"></i>Admin
    </a>
  </div>
</div>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      <div class="alert alert-info py-2 mb-3">
        <i class="fas fa-info-circle mr-2"></i>
        Form ini dapat diisi langsung tanpa login. Ketik NIM atau nama untuk mencari data mahasiswa.
      </div>

      <form id="formPinjam">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="mahasiswa_id" id="mahasiswa_id">

        <div class="row">

          <!-- ══ Kolom Kiri: Data Peminjam ══════════════════════════════ -->
          <div class="col-md-6">
            <div class="card form-card mb-4">
              <div class="card-header bg-white border-0 pb-0">
                <h6 class="font-weight-bold text-primary mb-0">
                  <i class="fas fa-user-graduate mr-2"></i>Data Peminjam
                </h6>
              </div>
              <div class="card-body">

                <!-- ── Autocomplete Mahasiswa ─────────────────────────── -->
                <div class="form-group">
                  <div class="step-label">Cari Mahasiswa (NIM / Nama)</div>
                  <div class="autocomplete-wrapper">
                    <input type="text" id="mhsInput" class="form-control"
                           placeholder="Ketik NIM atau nama mahasiswa..." autocomplete="off">
                    <div class="autocomplete-dropdown" id="mhsDropdown"></div>
                  </div>

                  <!-- Badge mahasiswa terpilih -->
                  <div class="selected-badge green" id="mhsSelectedBadge">
                    <i class="fas fa-user-graduate text-success"></i>
                    <div>
                      <div class="badge-nama" id="mhsSelectedNama"></div>
                      <div class="badge-sub"  id="mhsSelectedSub"></div>
                    </div>
                    <button type="button" class="btn-clear" id="btnClearMhs" title="Ganti mahasiswa">
                      <i class="fas fa-times-circle"></i>
                    </button>
                  </div>

                  <div class="inline-error" id="mhsError">
                    <i class="fas fa-exclamation-circle mr-1 text-danger"></i>
                    Mahasiswa tidak ditemukan.
                  </div>
                </div>

                <!-- Mata Kuliah -->
                <div class="form-group">
                  <div class="step-label">Mata Kuliah</div>
                  <input type="text" name="mata_kuliah" class="form-control"
                         placeholder="Nama mata kuliah" required>
                </div>

                <!-- ── Autocomplete Dosen ──────────────────────────────── -->
                <div class="form-group">
                  <div class="step-label">Dosen Pengampu</div>
                  <input type="hidden" name="dosen_id" id="dosen_id">
                  <div class="autocomplete-wrapper">
                    <input type="text" id="dosenInput" class="form-control"
                           placeholder="Ketik nama atau NIDN dosen..." autocomplete="off">
                    <div class="autocomplete-dropdown" id="dosenDropdown"></div>
                  </div>

                  <!-- Badge dosen terpilih -->
                  <div class="selected-badge green" id="dosenSelectedBadge">
                    <i class="fas fa-chalkboard-teacher text-success"></i>
                    <div>
                      <div class="badge-nama" id="dosenSelectedNama"></div>
                      <div class="badge-sub"  id="dosenSelectedNidn"></div>
                    </div>
                    <button type="button" class="btn-clear" id="btnClearDosen" title="Ganti dosen">
                      <i class="fas fa-times-circle"></i>
                    </button>
                  </div>

                  <div class="inline-error" id="dosenError">
                    <i class="fas fa-exclamation-circle mr-1 text-danger"></i>
                    Dosen tidak ditemukan.
                  </div>
                </div>

                <!-- Keterangan -->
                <div class="form-group">
                  <div class="step-label">Keterangan</div>
                  <textarea name="keterangan" class="form-control" rows="2"
                            placeholder="Keperluan tambahan (opsional)"></textarea>
                </div>

              </div>
            </div>
          </div>

          <!-- ══ Kolom Kanan: Detail Peminjaman ═════════════════════════ -->
          <div class="col-md-6">
            <div class="card form-card mb-4">
              <div class="card-header bg-white border-0 pb-0">
                <h6 class="font-weight-bold text-primary mb-0">
                  <i class="fas fa-calendar-alt mr-2"></i>Detail Peminjaman
                </h6>
              </div>
              <div class="card-body">

                <div class="form-group">
                  <div class="step-label">Ruangan</div>
                  <select name="ruang_id" id="ruangSelect" class="form-control" required>
                    <option value="">— Pilih Ruangan —</option>
                    <?php foreach ($ruangan as $r): ?>
                    <option value="<?= $r['id'] ?>">
                      <?= e($r['nama']) ?> (Kapasitas: <?= $r['kapasitas'] ?>)
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <div class="step-label">Tanggal</div>
                    <input type="date" name="tanggal" id="tglInput" class="form-control"
                           min="<?= date('Y-m-d') ?>" required>
                  </div>
                  <div class="form-group col-md-6">
                    <div class="step-label">Jumlah Peserta</div>
                    <input type="number" name="jumlah_peserta" class="form-control"
                           placeholder="0" min="1" required>
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <div class="step-label">Jam Mulai</div>
                    <select name="jam_mulai" id="jamMulai" class="form-control" required>
                      <?php for ($h = 7; $h <= 20; $h++): ?>
                      <option><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00</option>
                      <?php endfor; ?>
                    </select>
                  </div>
                  <div class="form-group col-md-6">
                    <div class="step-label">Jam Selesai</div>
                    <select name="jam_selesai" id="jamSelesai" class="form-control" required>
                      <?php for ($h = 8; $h <= 21; $h++): ?>
                      <option><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00</option>
                      <?php endfor; ?>
                    </select>
                  </div>
                </div>

                <div class="alert alert-danger conflict-alert" id="conflictAlert">
                  <i class="fas fa-exclamation-triangle mr-2"></i>
                  <strong>Bentrok Jadwal!</strong> Ruangan sudah dipesan pada waktu tersebut.
                </div>

                <button type="submit" id="btnSubmit" class="btn btn-primary btn-block btn-lg mt-2">
                  <i class="fas fa-paper-plane mr-2"></i>Ajukan Peminjaman
                </button>

              </div>
            </div>
          </div>

        </div><!-- /.row -->
      </form>

      <!-- Riwayat -->
      <div id="riwayatSection" style="display:none">
        <div class="card form-card">
          <div class="card-header bg-white border-0">
            <h6 class="font-weight-bold text-primary mb-0">
              <i class="fas fa-history mr-2"></i>Riwayat Peminjaman Anda
            </h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0" id="tblRiwayat">
                <thead class="thead-light">
                  <tr>
                    <th>Kode</th><th>Ruangan</th><th>Mata Kuliah</th>
                    <th>Tanggal</th><th>Jam</th><th>Status</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ STATUS RUANGAN ══════════════════════════════════════════ -->
      <div class="card shadow-sm mb-4 mt-4">
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
          $badge='secondary'; $label='Perbaikan'; $dot='room-repair';
      } elseif($r['is_terpakai']){
          $badge='danger';    $label='Terpakai';  $dot='room-used';
      } else {
          $badge='success';   $label='Tersedia';  $dot='room-free';
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
            <h5 class="font-weight-bold mb-2"><?= e($r['nama']) ?></h5>
            <div class="text-muted small mb-3">Kapasitas <?= $r['kapasitas'] ?> Orang</div>
            <span class="room-status <?= $dot ?>"></span>
            <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
      <!-- ══ /STATUS RUANGAN ════════════════════════════════════════ -->

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
let isBentrok  = false;

/* ═══════════════════════════════════════════════════════════════════
   HELPER — Generic Autocomplete Builder
   Dipakai oleh mahasiswa DAN dosen agar perilakunya identik.
═══════════════════════════════════════════════════════════════════ */
function makeAutocomplete({ inputId, dropdownId, badgeId, badgeNamaId, badgeSubId,
                            clearBtnId, hiddenId, ajaxUrl, minChar,
                            renderItem,   // fn(item) → { nama, sub }
                            onSelect,     // fn(item) dipanggil setelah dipilih
                            onClear       // fn() dipanggil setelah dibersihkan
                          }) {
  let timer       = null;
  let activeIndex = -1;

  const $input  = $('#' + inputId);
  const $dd     = $('#' + dropdownId);
  const $badge  = $('#' + badgeId);
  const $hidden = $('#' + hiddenId);

  function showBadge(item) {
    const r = renderItem(item);
    $('#' + badgeNamaId).text(r.nama);
    $('#' + badgeSubId).html(r.sub);
    $badge.css('display', 'flex');
    $input.val('').hide();
    $dd.hide();
  }

  function selectItem(item) {
    $hidden.val(item.id);
    showBadge(item);
    if (onSelect) onSelect(item);
  }

  function renderDropdown(items) {
    if (!items || !items.length) {
      $dd.html('<div class="autocomplete-loading text-muted">' +
               '<i class="fas fa-user-slash mr-1"></i>Data tidak ditemukan.</div>').show();
      return;
    }
    let html = '';
    items.forEach((item, idx) => {
      const r = renderItem(item);
      html += `<div class="autocomplete-item" data-idx="${idx}">
        <div class="ac-nama">${r.nama}</div>
        <div class="ac-sub">${r.sub}</div>
      </div>`;
    });
    $dd.html(html).show();
    activeIndex = -1;

    $dd.find('.autocomplete-item').each(function (i) {
      $(this).on('click', function () { selectItem(items[i]); });
    });
  }

  $input.on('input', function () {
    const q = $(this).val().trim();
    clearTimeout(timer);
    if (q.length < (minChar || 2)) { $dd.hide(); return; }

    $dd.html('<div class="autocomplete-loading">' +
             '<i class="fas fa-spinner fa-spin mr-1"></i>Mencari...</div>').show();

    timer = setTimeout(() => {
      $.get(BASE_URL + ajaxUrl, { q })
        .done(d => renderDropdown(d.results))
        .fail(() => $dd.hide());
    }, 300);
  });

  $input.on('keydown', function (e) {
    const items = $dd.find('.autocomplete-item');
    if (!items.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = Math.min(activeIndex + 1, items.length - 1);
      items.removeClass('active').eq(activeIndex).addClass('active');
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = Math.max(activeIndex - 1, 0);
      items.removeClass('active').eq(activeIndex).addClass('active');
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (activeIndex >= 0) items.eq(activeIndex).trigger('click');
    } else if (e.key === 'Escape') {
      $dd.hide();
    }
  });

  $('#' + clearBtnId).on('click', function () {
    $hidden.val('');
    $badge.hide();
    $input.val('').show().focus();
    if (onClear) onClear();
  });
}

/* ═══════════════════════════════════════════════════════════════════
   HELPER — escape string dari server sebelum disuntikkan ke HTML
═══════════════════════════════════════════════════════════════════ */
function esc(str) {
  return $('<span>').text(str ?? '').html();
}

/* ═══════════════════════════════════════════════════════════════════
   INISIALISASI AUTOCOMPLETE — MAHASISWA
═══════════════════════════════════════════════════════════════════ */
makeAutocomplete({
  inputId    : 'mhsInput',
  dropdownId : 'mhsDropdown',
  badgeId    : 'mhsSelectedBadge',
  badgeNamaId: 'mhsSelectedNama',
  badgeSubId : 'mhsSelectedSub',
  clearBtnId : 'btnClearMhs',
  hiddenId   : 'mahasiswa_id',
  ajaxUrl    : 'ajax/cari_mahasiswa.php',
  minChar    : 2,
  renderItem : item => ({
    nama: item.nama,
    sub : `<i class="fas fa-id-card mr-1"></i>NIM: ${esc(item.nim)} &nbsp;|&nbsp; ${esc(item.prodi)}`
  }),
  onSelect: item => {
    $('#mhsError').hide();
    loadRiwayat(item.nim);
  },
  onClear: () => {
    $('#riwayatSection').hide();
  }
});

/* ═══════════════════════════════════════════════════════════════════
   INISIALISASI AUTOCOMPLETE — DOSEN
═══════════════════════════════════════════════════════════════════ */
makeAutocomplete({
  inputId    : 'dosenInput',
  dropdownId : 'dosenDropdown',
  badgeId    : 'dosenSelectedBadge',
  badgeNamaId: 'dosenSelectedNama',
  badgeSubId : 'dosenSelectedNidn',
  clearBtnId : 'btnClearDosen',
  hiddenId   : 'dosen_id',
  ajaxUrl    : 'ajax/cari_dosen.php',
  minChar    : 2,
  renderItem : item => ({
    nama: item.nama,
    sub : `<i class="fas fa-id-card mr-1"></i>NIDN: ${esc(item.nidn)}`
  }),
  onSelect: item => {
    $('#dosenError').hide();
  }
});

/* ═══════════════════════════════════════════════════════════════════
   TUTUP DROPDOWN SAAT KLIK DI LUAR
═══════════════════════════════════════════════════════════════════ */
$(document).on('click', function (e) {
  if (!$(e.target).closest('.autocomplete-wrapper').length) {
    $('#mhsDropdown, #dosenDropdown').hide();
  }
});

/* ═══════════════════════════════════════════════════════════════════
   CEK BENTROK JADWAL
═══════════════════════════════════════════════════════════════════ */
function cekBentrok() {
  const ruang = $('#ruangSelect').val();
  const tgl   = $('#tglInput').val();
  const jm    = $('#jamMulai').val();
  const js    = $('#jamSelesai').val();
  if (!ruang || !tgl || !jm || !js) return;
  $.post(BASE_URL + 'ajax/cek_bentrok.php', {
    ruang_id: ruang, tanggal: tgl,
    jam_mulai: jm, jam_selesai: js,
    csrf_token: '<?= csrfToken() ?>'
  }).done(d => {
    isBentrok = d.bentrok;
    if (d.bentrok) $('#conflictAlert').show();
    else           $('#conflictAlert').hide();
  });
}
$('#ruangSelect, #tglInput, #jamMulai, #jamSelesai').on('change', cekBentrok);

/* ═══════════════════════════════════════════════════════════════════
   RIWAYAT PEMINJAMAN
═══════════════════════════════════════════════════════════════════ */
function loadRiwayat(nim) {
  $.get(BASE_URL + 'ajax/riwayat.php', { nim }).done(rows => {
    if (!rows.length) { $('#riwayatSection').hide(); return; }
    const badges = {
      menunggu: 'warning', disetujui: 'success',
      ditolak: 'danger',   selesai: 'info', dibatalkan: 'secondary'
    };
    let html = '';
    rows.forEach(r => {
      const b = badges[r.status] || 'secondary';
      html += `<tr>
        <td><small>${r.kode_pinjam}</small></td>
        <td>${r.nama_ruang}</td>
        <td>${r.mata_kuliah}</td>
        <td>${r.tgl_fmt}</td>
        <td>${r.jam_mulai.substr(0,5)}–${r.jam_selesai.substr(0,5)}</td>
        <td><span class="badge badge-${b}">${r.status.charAt(0).toUpperCase() + r.status.slice(1)}</span></td>
      </tr>`;
    });
    $('#tblRiwayat tbody').html(html);
    $('#riwayatSection').show();
  });
}

/* ═══════════════════════════════════════════════════════════════════
   SUBMIT FORM
═══════════════════════════════════════════════════════════════════ */
$('#formPinjam').on('submit', function (e) {
  e.preventDefault();

  if (!$('#mahasiswa_id').val()) {
    Swal.fire('Perhatian', 'Pilih mahasiswa terlebih dahulu menggunakan kolom pencarian.', 'warning');
    return;
  }
  if (!$('#dosen_id').val()) {
    Swal.fire('Perhatian', 'Pilih dosen pengampu terlebih dahulu.', 'warning');
    return;
  }
  if (isBentrok) {
    Swal.fire('Bentrok Jadwal', 'Ruangan sudah dipesan pada waktu tersebut.', 'error');
    return;
  }
  if ($('#jamSelesai').val() <= $('#jamMulai').val()) {
    Swal.fire('Perhatian', 'Jam selesai harus lebih besar dari jam mulai.', 'warning');
    return;
  }

  const btn = $('#btnSubmit');
  btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...');

  $.post(BASE_URL + 'ajax/simpan_peminjaman.php', $(this).serialize())
    .done(d => {
      if (d.status === 'success') {
        Swal.fire({
          icon: 'success', title: 'Berhasil!',
          html: `Peminjaman berhasil diajukan!<br>
                 <strong>Kode: ${d.kode}</strong><br>
                 Notifikasi WhatsApp telah dikirim.`,
          confirmButtonText: 'OK'
        }).then(() => location.reload());
      } else {
        Swal.fire('Gagal', d.message || 'Terjadi kesalahan', 'error');
        btn.prop('disabled', false)
           .html('<i class="fas fa-paper-plane mr-2"></i>Ajukan Peminjaman');
      }
    });
});
</script>

</body>
</html>