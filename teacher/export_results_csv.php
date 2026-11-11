<?php
// teacher/export_results.php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

// permission guard (teacher)
if ($res !== "1" || $level !== "2") {
    header("Location: ../");
    exit;
}

// helpers
function parse_class_field($raw) {
    $out = [];
    if ($raw === null) return $out;
    $un = @unserialize($raw);
    if ($un !== false && is_array($un)) {
        foreach ($un as $v) if (is_numeric($v)) $out[] = (int)$v;
        return array_values(array_unique($out));
    }
    $j = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
        foreach ($j as $v) if (is_numeric($v)) $out[] = (int)$v;
        return array_values(array_unique($out));
    }
    preg_match_all('/\d+/', (string)$raw, $m);
    if (!empty($m[0])) foreach ($m[0] as $v) $out[] = (int)$v;
    return array_values(array_unique($out));
}

// Build a robust action URL for the POST handler located in the same directory
$dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
$processPath = $dir . '/export_results_process.php';

// fetch classes + meta
try {
    $dsn = 'mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset;
    $pdo = new PDO($dsn, DBUser, DBPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query("SELECT id, name FROM tbl_classes ORDER BY name ASC");
    $classesList = $stmt->fetchAll();

    $classMeta = [];
    foreach ($classesList as $c) $classMeta[$c['id']] = ['name' => $c['name'], 'subjects' => []];

    $stmt = $pdo->query("SELECT sc.id AS scid, sc.class AS class_raw, sc.subject AS subject_id, sc.teacher AS teacher_id, s.name AS subject_name, st.fname AS t_fname, st.lname AS t_lname
                         FROM tbl_subject_combinations sc
                         LEFT JOIN tbl_subjects s ON sc.subject = s.id
                         LEFT JOIN tbl_staff st ON sc.teacher = st.id
                         ORDER BY sc.id ASC");
    $scRows = $stmt->fetchAll();

    foreach ($scRows as $sc) {
        $classesForSc = parse_class_field($sc['class_raw']);
        $teacherName = trim(($sc['t_fname'] ?? '') . ' ' . ($sc['t_lname'] ?? ''));
        $subjectName = $sc['subject_name'] ?? 'Subject';
        foreach ($classesForSc as $cid) {
            if (!isset($classMeta[$cid])) continue;
            $classMeta[$cid]['subjects'][] = trim($subjectName . ($teacherName ? ' — ' . $teacherName : ''));
        }
    }

} catch (PDOException $e) {
    echo "DB error: " . htmlspecialchars($e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SRMS - Export Results</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" type="text/css" href="loader/waitMe.css">
<link rel="stylesheet" href="select2/dist/css/select2.min.css">
<style>
.form-container { padding:20px; background:#fff; border-radius:6px; margin-top:20px; }
.form-row{ margin-bottom:12px; }
label{ display:block; margin-bottom:6px; font-weight:600; }
.small-note{ font-size:13px; color:#666; margin-top:6px; }
.class-meta { color:#444; font-size:13px; display:block; margin-top:3px; }
.controls { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
.preview-stats { font-size:13px; color:#444; margin-left:12px; }
</style>
</head>
<body class="app sidebar-mini">

<!-- header -->
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">SRMS</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<!-- sidebar -->
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Teacher</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="teacher/terms"><i class="app-menu__icon feather icon-folder"></i><span class="app-menu__label">Academic Terms</span></a></li>
<li><a class="app-menu__item" href="teacher/combinations"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">Subject Combinations</span></a></li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/list_students"><i class="icon bi bi-circle-fill"></i> List Students</a></li>
<li><a class="treeview-item active" href="teacher/export_results"><i class="icon bi bi-circle-fill"></i> Export Results</a></li>
</ul>
</li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Examination Results</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/import_results"><i class="icon bi bi-circle-fill"></i> Import Results</a></li>
<li><a class="treeview-item" href="teacher/manage_results"><i class="icon bi bi-circle-fill"></i> View Results</a></li>
</ul>
</li>
<li><a class="app-menu__item" href="teacher/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="teacher/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>

<!-- main content -->
<main class="app-content">
<div class="app-title">
<div>
<h1>Export Results</h1>
</div>
</div>

<div class="form-container">
  <?php if (!empty($_SESSION['error'])): ?>
    <div style="color:red; margin-bottom:12px;"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
  <?php endif; ?>

  <form id="exportForm" method="post" action="<?php echo htmlspecialchars($processPath); ?>">
    <div class="form-row">
      <label>Export mode</label>
      <div>
        <label><input type="radio" name="mode" value="filled" checked> Export filled results (current view)</label><br>
        <label><input type="radio" name="mode" value="all"> Export all students (select classes)</label>
      </div>
      <div class="small-note">"Filled results" uses the current session result context (class, subject combination, term).</div>
    </div>

    <div class="form-row" id="classesBox" style="display:none;">
      <label for="classes">Select classes (multiple allowed)</label>
      <select id="classes" name="classes[]" multiple style="width:100%; max-width:100%;">
        <?php foreach ($classMeta as $cid => $meta): ?>
          <?php $label = htmlspecialchars($meta['name']); ?>
          <option value="<?php echo (int)$cid; ?>"><?php echo $label; ?></option>
        <?php endforeach; ?>
      </select>
      <div class="class-meta small-note">Include all students in selected classes.</div>
    </div>

    <div class="form-row">
      <label>Missing-score behavior</label>
      <div>
        <label><input type="radio" name="missing" value="zero" checked> Output <strong>0</strong></label>
        <label style="margin-left:12px;"><input type="radio" name="missing" value="blank"> Leave blank cell</label>
      </div>
    </div>

    <div class="form-row">
      <label>Advanced options</label>
      <div>
        <label><input type="checkbox" name="only_with_scores" value="1"> Only students with scores</label><br>
        <label><input type="checkbox" name="include_meta" value="1"> Include CLASS/SUBJECT/TEACHER columns</label>
      </div>
    </div>

    <div class="form-row controls">
      <button type="submit" class="btn btn-primary">Export</button>
      <button type="button" id="previewBtn" class="btn btn-outline-secondary">Preview counts</button>
      <a href="teacher/manage_results" class="btn btn-secondary">Back</a>
      <div class="preview-stats" id="previewStats" aria-live="polite"></div>
    </div>
  </form>

  <hr>
  <h4>Classes & assigned subjects / teachers</h4>
  <div style="max-height:340px; overflow:auto; border:1px solid #eee; padding:8px;">
    <?php foreach ($classMeta as $cid => $m): ?>
      <div style="padding:8px;border-bottom:1px solid #f2f2f2;">
        <strong><?php echo htmlspecialchars($m['name']); ?></strong>
        <div class="small-note">
          <?php echo !empty($m['subjects']) ? htmlspecialchars(implode('; ', $m['subjects'])) : '<em>No subjects assigned</em>'; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<script>
$(function(){
  var $mode = $('input[name="mode"]');
  var $classesBox = $('#classesBox');
  var $classesSelect = $('#classes');
  var $previewBtn = $('#previewBtn');
  var $previewStats = $('#previewStats');

  $classesSelect.select2({ placeholder: 'Select classes...', allowClear: true, width: '100%' });

  function toggleMode() {
    var val = $('input[name="mode"]:checked').val();
    if (val === 'all') $classesBox.show(); else $classesBox.hide();
  }
  $mode.on('change', toggleMode);
  toggleMode();

  $previewBtn.on('click', function(){
    var form = $('#exportForm');
    var data = form.serializeArray();
    data.push({name: 'preview', value: '1'});
    $previewStats.text('Loading preview...');
    $.ajax({
      url: form.attr('action'),
      method: 'POST',
      data: data,
      dataType: 'json',
      timeout: 15000
    }).done(function(resp){
      if (!resp || typeof resp.success === 'undefined') { $previewStats.text('Preview failed'); return; }
      if (!resp.success) { $previewStats.text('Preview: ' + (resp.message || 'No data')); return; }
      var html = 'Students: ' + resp.total_students;
      if (typeof resp.with_scores !== 'undefined') html += ' | With scores: ' + resp.with_scores;
      if (typeof resp.missing_scores !== 'undefined') html += ' | Missing scores: ' + resp.missing_scores;
      $previewStats.html(html);
    }).fail(function(xhr, st, err){ $previewStats.text('Preview request failed: ' + (err || st)); });
  });
});
</script>
</body>
</html>