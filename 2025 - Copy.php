<?php

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 60);

// ==== CẤU HÌNH ====
// Google Drive CSV direct download URL (direct download API)
define('URL_CSV', 'https://drive.google.com/uc?export=download&id=1otJvLZs9vLhw04HhLHvBRztISFC3akiJ');

// Bảng ánh xạ môn sang cột (0‑based index)
$colMap = [
  'SOBAODANH' => 1,
  'Toán'      => 2,
  'Văn'       => 3,
  'Lí'        => 4,
  'Hóa'       => 5,
  'Sinh'      => 6,
  'Tin học'   => 7,
  'Công nghệ công nghiệp' => 8,
  'Công nghệ nông nghiệp' => 9,
  'Sử'        => 10,
  'Địa'       => 11,
  'GDKT&PL'   => 12,  // Giáo dục kinh tế và pháp luật
  'Ngoại ngữ' => 13,
  'LangCode'  => 14,  // Mã môn ngoại ngữ
];

// Danh sách môn 2018 để hiển thị trong form
$subjects2018 = [
  'Toán','Văn','Lí','Hóa','Sinh','Tin học',
  'Công nghệ công nghiệp','Công nghệ nông nghiệp',
  'Sử','Địa','GDKT&PL','Ngoại ngữ'
];

/**
 * 1) Tìm tổng điểm của SBD
 */
function getSumForSBD(string $url, string $targetSbd, array $colMap, array $chosen): float|false {
    // tải tạm CSV
    $tmp = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($tmp, fopen($url, 'r'));

    $f = new SplFileObject($tmp);
    $f->setFlags(SplFileObject::READ_CSV);
    $f->setCsvControl(',');

    // bỏ header
    $f->seek(1);

    while (!$f->eof()) {
        $row = $f->fgetcsv();
        if (!$row || trim($row[$colMap['SOBAODANH']] ?? '') === '') {
            continue;
        }
        if (trim($row[$colMap['SOBAODANH']]) === $targetSbd) {
            // tính sum theo đúng logic cũ
            $sum = 0;
            // môn 1 & 2
            foreach (['m1','m2'] as $k) {
                $col = $colMap[$chosen[$k]] ?? null;
                if ($col===null || !is_numeric($row[$col] ?? null)) {
                    unlink($tmp); return false;
                }
                $sum += (float)$row[$col];
            }
            // môn 3
            if ($chosen['m3'] === 'Ngoại ngữ') {
                $colVal  = $colMap['Ngoại ngữ'];
                $colCode = $colMap['LangCode'];
                $valLang = $row[$colVal] ?? '';
                $code    = $row[$colCode] ?? '';
                if (!is_numeric($valLang) || ($chosen['lang']!=='' && $chosen['lang']!==$code)) {
                    unlink($tmp); return false;
                }
                $sum += (float)$valLang;
            } else {
                $col = $colMap[$chosen['m3']] ?? null;
                if ($col===null || !is_numeric($row[$col] ?? null)) {
                    unlink($tmp); return false;
                }
                $sum += (float)$row[$col];
            }

            unlink($tmp);
            return $sum;
        }
    }

    unlink($tmp);
    return false;
}

/**
 * 2) Đếm rank và tie_count theo sum vừa tìm được
 */
function getRankAndTie(string $url, float $sumSbd, array $colMap, array $chosen): array {
    $tmp = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($tmp, file_get_contents($url));

    $f = new SplFileObject($tmp);
    $f->setFlags(SplFileObject::READ_CSV);
    $f->setCsvControl(',');
    $f->seek(1);

    $countHigher = 0;
    $countTie    = 0;

    while (!$f->eof()) {
        $row = $f->fgetcsv();
        if (!$row || trim($row[$colMap['SOBAODANH']] ?? '') === '') {
            continue;
        }

        // tính sum với cùng logic
        $sum = 0; $ok = true;
        foreach (['m1','m2'] as $k) {
            $col = $colMap[$chosen[$k]] ?? null;
            if ($col===null || !is_numeric($row[$col] ?? null)) {
                $ok = false; break;
            }
            $sum += (float)$row[$col];
        }
        if (!$ok) continue;

        if ($chosen['m3'] === 'Ngoại ngữ') {
            $colVal  = $colMap['Ngoại ngữ'];
            $colCode = $colMap['LangCode'];
            $valLang = $row[$colVal] ?? '';
            $code    = $row[$colCode] ?? '';
            if (!is_numeric($valLang) || ($chosen['lang']!=='' && $chosen['lang']!==$code)) {
                continue;
            }
            $sum += (float)$valLang;
        } else {
            $col = $colMap[$chosen['m3']] ?? null;
            if ($col===null || !is_numeric($row[$col] ?? null)) {
                continue;
            }
            $sum += (float)$row[$col];
        }

        if ($sum > $sumSbd)       $countHigher++;
        elseif (abs($sum - $sumSbd) < 1e-6) $countTie++;
    }

    unlink($tmp);
    return [
      'rank'      => $countHigher + 1,
      'tie_count' => $countTie,
    ];
}

// XỬ LÝ FORM
$result = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $sbd = trim($_POST['sbd'] ?? '');
  $chosen = [
    'm1'   => $_POST['m1']   ?? '',
    'm2'   => $_POST['m2']   ?? '',
    'm3'   => $_POST['m3']   ?? '',
    'lang' => $_POST['lang'] ?? '',
  ];

  // 1) Tìm sum
  $sumSbd = getSumForSBD(URL_CSV, $sbd, $colMap, $chosen);

  if ($sumSbd === false) {
    $result = [];  // không tìm thấy hoặc data thiếu
  } else {
    // 2) Tính rank & tie_count
    $rt = getRankAndTie(URL_CSV, $sumSbd, $colMap, $chosen);
    $result = [
      'SOBAODANH'=> $sbd,
      'sum'      => $sumSbd,
      'rank'     => $rt['rank'],
      'tie_count'=> $rt['tie_count'],
    ];
  }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <!-- Responsive meta -->
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Favicon -->
  <link rel="icon" href="/favicon-32x32.png?v=2" type="image/png" sizes="32x32">
  <!-- Bootstrap CSS (phiên bản 5.2.3) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Font: Inter -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

  <title>Tra cứu điểm và xếp hạng</title>

  <!-- Custom styles -->
  <style>
    /* Áp dụng font Inter cho toàn trang */
    body {
      font-family: 'Inter', sans-serif;
      background-color: #ffffff; /* nền nhạt */
    }
    h1, .form-label {
      color: #003366;
    }
    .btn-primary {
      color: #ffffff;
    }
    .col-md-4 {
      width: 25%;
    }
    .btn-primary {
      background-color: #003366;
      border-color: #003366;
    }
    .btn-primary:hover {
      background-color: #002244;
      border-color: #002244;
    }
    .alert-success {
      border-left: 4px solid #003366;
    }
    footer {
      color: #003366;
      padding: 1rem 0;
      text-align: center;
      margin-top: 4rem;
    }
    .d1{
      width: 30%;
    }
    @media (max-width: 1000px) {
      .col-md-4 {
        width: 100%;
      }
      .d1 {
        width: 100%;
      }
    }
  </style>
</head>
<body class="p-4">
  <h1 class="mb-4" style="text-align: center;"><b>TRA CỨU ĐIỂM VÀ XẾP HẠNG 2025</b></h1>
  <form method="post" class="mb-5">
    <div class="alert alert-info">
    <b>Hướng dẫn tra cứu:</b><br>
    - Chọn đúng tổ hợp <b>3 môn xét tuyển</b> (3 môn được chọn phải thuộc 4 môn thi tốt nghiệp THPT).<br>
    - Nếu tổ hợp xét có môn <b>Ngoại ngữ</b> thì chọn làm môn thi thứ ba và cần chọn đúng <b>mã môn ngoại ngữ</b> (N1 - Tiếng Anh, N2 - Tiếng Nga, N3 - Tiếng Pháp, N4 - Tiếng Trung, N5 - Tiếng Đức, N6 - Tiếng Nhật, N7 - Tiếng Hàn).</i><br>
    - Môn <b>Giáo dục công dân</b> (theo chương trình cũ 2006) tương ứng với môn <b>Giáo dục kinh tế và pháp luật</b> - GDKT&PL (theo chương trình mới 2018).<br>
    <i><b>Lưu ý:</b> Xếp hạng các khối thi chỉ mang tính chất tham khảo; Tải lại trang trước khi tra tổ hợp môn thi mới.</i>
    </div>
    <div class="mb-3 d1">
      <label class="form-label"><b>Số báo danh:</b></label>
      <input name="sbd" class="form-control" required value="<?= htmlspecialchars($_POST['sbd'] ?? '') ?>">
    </div>
    <label class="form-label"><b>Chọn tổ hợp môn thi theo khối thi:</b></label>
    <div class="row mb-4">
      <div class="col-md-4 mb-2">
        <select name="m1" class="form-select" required>
          <option value="">Chọn Môn thi thứ nhất</option>
          <?php foreach ($subjects2018 as $s): ?>
            <option <?= ($_POST['m1'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-md-4 mb-2">
        <select name="m2" class="form-select" required>
          <option value="">Chọn Môn thi thứ hai</option>
          <?php foreach ($subjects2018 as $s): ?>
            <option <?= ($_POST['m2'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-md-4 mb-2">
        <select name="m3" id="sel_m3" class="form-select" required>
          <option value="">Chọn Môn thi thứ ba</option>
          <?php foreach ($subjects2018 as $s): ?>
            <option <?= ($_POST['m3'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <!-- Chọn mã ngoại ngữ (ẩn mặc định) -->
      <div class="col-md-4 mb-2" id="div_lang" style="display:none;">
        <select name="lang" class="form-select">
          <option value="">Chọn mã môn ngoại ngữ</option>
          <?php foreach (['N1','N2','N3','N4','N5','N6','N7'] as $code): ?>
            <option <?= (($_POST['lang'] ?? '') === $code) ? 'selected' : '' ?>><?= $code ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Tra cứu</button>
  </form>

  <?php if (!empty($result)): ?>
    <div class="alert alert-success">
      Số báo danh: <b><?= htmlspecialchars($result['SOBAODANH']) ?></b> – Tổng điểm: <b><?= $result['sum'] ?></b> – Xếp hạng: <b><?= $result['rank'] ?></b>
      <?php if ($result['tie_count'] > 1): ?>
        (Có <?= $result['tie_count'] ?> thí sinh cùng mức điểm)
      <?php endif ?>
    </div>
  <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="alert alert-danger">Không tìm thấy dữ liệu Số báo danh trong tổ hợp môn đã chọn.</div>
  <?php endif ?>

  <footer>
    Dữ liệu điểm thi thuộc Bộ Giáo dục và Đào tạo
  </footer>

  <!-- Bootstrap JS + Popper (nếu cần) -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function(){
      const sel3 = document.getElementById('sel_m3');
      const divLang = document.getElementById('div_lang');
      function toggleLang() {
        divLang.style.display = (sel3.value === 'Ngoại ngữ') ? '' : 'none';
      }
      sel3.addEventListener('change', toggleLang);
      toggleLang();
    });
  </script>
</body>
</html>
