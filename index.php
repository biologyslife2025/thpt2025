<?php
// index.php

// ==== CẤU HÌNH ====
define('CONFIG_FILE', __DIR__ . '/years.json');
$years = file_exists(CONFIG_FILE)
    ? json_decode(file_get_contents(CONFIG_FILE), true)
    : [];

// XỬ LÝ ADMIN
if (isset($_GET['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $year   = trim($_POST['year'] ?? '');
    if ($action === 'add' && preg_match('/^\d{4}$/', $year) && !in_array($year, $years)) {
        $years[] = $year;
    } elseif ($action === 'delete') {
        $years = array_filter($years, fn($y) => $y !== $year);
    }
    sort($years);
    file_put_contents(CONFIG_FILE, json_encode(array_values($years), JSON_PRETTY_PRINT));
    header('Location: index.php?admin=1');
    exit;
}

// HIỂN THỊ ADMIN
if (isset($_GET['admin'])):
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Quản lý năm tra cứu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="/favicon-32x32.png?v=2" type="image/png" sizes="32x32">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background: #ffffff; }
    h1 { color: #003366; }
    .btn-primary { background: #003366; border-color: #003366; }
    .btn-primary:hover { background: #002244; border-color: #002244; }
  </style>
</head>
<body class="p-4">
  <h1 class="mb-4 text-center">QUẢN LÝ NĂM TRA CỨU</h1>
  <form method="post" class="row g-2 mb-4 justify-content-center">
    <input type="hidden" name="action" value="add">
    <div class="col-auto">
      <input name="year" class="form-control" placeholder="Ví dụ: 2026" required>
    </div>
    <div class="col-auto">
      <button class="btn btn-primary">Thêm năm</button>
    </div>
  </form>
  <div class="d-flex justify-content-center">
    <table class="table table-striped table-bordered w-50 bg-white">
      <thead class="table-primary">
        <tr><th>Năm</th><th>Hành động</th></tr>
      </thead>
      <tbody>
        <?php if ($years): foreach ($years as $y): ?>
        <tr>
          <td><?= htmlspecialchars($y) ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="year" value="<?= htmlspecialchars($y) ?>">
              <button class="btn btn-danger btn-sm"
                onclick="return confirm('Xóa năm <?= $y ?>?')">Xóa</button>
            </form>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="2" class="text-center">Chưa có năm nào. Hãy thêm.</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
  <div class="text-center mt-3">
    <a href="index.php" class="btn btn-secondary">Quay lại trang chủ</a>
  </div>
</body>
</html>
<?php
exit;
endif;

// XỬ LÝ CHỌN NĂM
$error = '';
if (isset($_GET['year'])) {
    $year = $_GET['year'];
    if (in_array($year, $years)) {
        $file = __DIR__ . "/{$year}.php";
        if (file_exists($file)) {
            include $file;
            exit;
        } else {
            $error = "Chưa có file cho năm {$year}.";
        }
    } else {
        $error = "Năm {$year} chưa được cấu hình.";
    }
}

// HIỂN THỊ TRANG CHỦ
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Tra cứu điểm và xếp hạng</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="/favicon-32x32.png?v=2" type="image/png" sizes="32x32">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background: #ffffff; }
    h1 { color: #003366; }
    .btn-year {
      width: 100px; margin: 0.5rem;
      background: #003366; border: none;
    }
    .btn-year:hover {
      background: #002244;
    }
    footer { color: #003366; margin-top: 4rem; text-align: center; }
  </style>
</head>
<body class="p-4 text-center">
  <h1 class="mb-4"><b>CHỌN NĂM TRA CỨU ĐIỂM VÀ XẾP HẠNG</b></h1>
  <?php if ($error): ?>
    <div class="alert alert-warning w-50 mx-auto"><?= htmlspecialchars($error) ?></div>
  <?php endif ?>
  <div>
    <?php if ($years): foreach ($years as $y): ?>
      <a href="?year=<?= $y ?>" class="btn btn-year text-white"><?= $y ?></a>
    <?php endforeach; else: ?>
      <p>Chưa có dữ liệu điểm thi.
    <?php endif ?>
  </div>
  <footer>
    Dữ liệu điểm thi thuộc Bộ Giáo dục và Đào tạo<br>
    © 2025 - Biology's Life 2025
  </footer>
</body>
</html>
