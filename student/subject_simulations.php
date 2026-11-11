<?php
// student/subject_simulations.php - Subject selection with links to each subject's dedicated page
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

$current_view = isset($_GET['view']) && $_GET['view'] === 'all' ? 'all' : 'assigned';

// Helper to parse class list (identical to original)
function parse_class_list($raw) {
    if (is_array($raw)) return $raw;
    $un = @unserialize($raw);
    if ($un !== false && is_array($un)) return array_map('intval', $un);
    $json = @json_decode($raw, true);
    if (is_array($json)) return array_map('intval', $json);
    $raw_trim = trim((string)$raw);
    if ($raw_trim === '') return [];
    if (ctype_digit($raw_trim)) return [(int)$raw_trim];
    $parts = preg_split('/\s*,\s*/', $raw_trim);
    $ints = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (ctype_digit($p)) $ints[] = (int)$p;
    }
    return $ints;
}

// Convert subject name to URL‑safe slug (matches generator's makeSlug)
function subjectSlug($name) {
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9]+/', '_', $name);
    return trim($name, '_');
}

/**
 * Generate a subject-specific SVG picture (illustration)
 * @param string $subjectName
 * @return string SVG markup
 */
function getSubjectPicture($subjectName) {
    $id = uniqid(); // unique ID for gradients if needed, but we can keep simple
    $svgBase = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="80" height="80" style="display:block; margin:0 auto;">';
    $svgEnd = '</svg>';
    
    // Map subject name to a specific illustration
    $lower = strtolower($subjectName);
    
    if (strpos($lower, 'chemistry') !== false) {
        // Beaker with liquid
        return $svgBase . '<rect x="30" y="40" width="40" height="40" rx="6" fill="#3b7cff" opacity="0.15"/><path d="M35 40 L45 20 L55 20 L65 40" stroke="#3b7cff" stroke-width="3" fill="none"/><rect x="38" y="50" width="24" height="30" rx="4" fill="#3b7cff" opacity="0.7"/><circle cx="50" cy="65" r="4" fill="#fff"/><path d="M45 20 L48 15 L52 15 L55 20" fill="#3b7cff"/>' . $svgEnd;
    }
    if (strpos($lower, 'biology') !== false) {
        // DNA / Leaf
        return $svgBase . '<path d="M50 20 L50 80" stroke="#28a745" stroke-width="3" stroke-dasharray="6 4"/><path d="M30 35 Q50 25 70 35" stroke="#28a745" stroke-width="2.5" fill="none"/><path d="M30 65 Q50 75 70 65" stroke="#28a745" stroke-width="2.5" fill="none"/><circle cx="50" cy="30" r="5" fill="#28a745"/><circle cx="50" cy="70" r="5" fill="#28a745"/>' . $svgEnd;
    }
    if (strpos($lower, 'physics') !== false) {
        // Atom / magnet
        return $svgBase . '<ellipse cx="50" cy="50" rx="30" ry="12" stroke="#20c997" stroke-width="2.5" fill="none" transform="rotate(30 50 50)"/><ellipse cx="50" cy="50" rx="30" ry="12" stroke="#20c997" stroke-width="2.5" fill="none" transform="rotate(-30 50 50)"/><ellipse cx="50" cy="50" rx="30" ry="12" stroke="#20c997" stroke-width="2.5" fill="none" transform="rotate(90 50 50)"/><circle cx="50" cy="50" r="5" fill="#20c997"/>' . $svgEnd;
    }
    if (strpos($lower, 'agriculture') !== false) {
        // Plant
        return $svgBase . '<path d="M50 70 L50 45" stroke="#2e8b57" stroke-width="3"/><path d="M50 45 Q35 30 25 40" stroke="#2e8b57" stroke-width="2.5" fill="none"/><path d="M50 45 Q65 30 75 40" stroke="#2e8b57" stroke-width="2.5" fill="none"/><path d="M50 55 Q40 45 35 55" stroke="#2e8b57" stroke-width="2" fill="none"/><circle cx="50" cy="72" r="5" fill="#8B4513"/>' . $svgEnd;
    }
    if (strpos($lower, 'computer science') !== false || strpos($lower, 'computer') !== false) {
        // Monitor
        return $svgBase . '<rect x="25" y="30" width="50" height="35" rx="5" fill="#6f42c1" opacity="0.15" stroke="#6f42c1" stroke-width="2"/><rect x="35" y="38" width="30" height="20" rx="2" fill="#6f42c1" opacity="0.6"/><path d="M45 68 L55 68 L50 78 L45 68Z" fill="#6f42c1"/><line x1="50" y1="78" x2="50" y2="85" stroke="#6f42c1" stroke-width="2"/>' . $svgEnd;
    }
    if (strpos($lower, 'geography') !== false) {
        // Globe
        return $svgBase . '<circle cx="50" cy="50" r="30" fill="#0d6efd" opacity="0.2" stroke="#0d6efd" stroke-width="2"/><ellipse cx="50" cy="50" rx="12" ry="30" stroke="#0d6efd" stroke-width="2" fill="none"/><path d="M20 45 L80 45 M20 55 L80 55" stroke="#0d6efd" stroke-width="1.5"/>' . $svgEnd;
    }
    if (strpos($lower, 'historia') !== false || strpos($lower, 'history') !== false) {
        // Scroll or parchment
        return $svgBase . '<path d="M25 35 Q40 30 50 35 Q60 30 75 35 L75 75 Q60 70 50 75 Q40 70 25 75Z" fill="#fd7e14" opacity="0.15" stroke="#fd7e14" stroke-width="2"/><path d="M35 45 L65 45 M35 55 L65 55 M35 65 L55 65" stroke="#fd7e14" stroke-width="2"/>' . $svgEnd;
    }
    if (strpos($lower, 'bible knowledge') !== false) {
        // Open book with cross
        return $svgBase . '<path d="M30 30 L50 40 L70 30 L70 70 L50 60 L30 70Z" fill="#d63384" opacity="0.2" stroke="#d63384" stroke-width="2"/><line x1="50" y1="40" x2="50" y2="70" stroke="#d63384" stroke-width="2"/>' . $svgEnd;
    }
    if (strpos($lower, 'elimu ya dini ya kiislamu') !== false || strpos($lower, 'islam') !== false) {
        // Mosque crescent
        return $svgBase . '<circle cx="55" cy="45" r="20" fill="#198754" opacity="0.2" stroke="#198754" stroke-width="2"/><path d="M40 45 A20 20 0 0 0 70 35 A25 25 0 0 1 40 45Z" fill="#198754"/>' . $svgEnd;
    }
    if (strpos($lower, 'mathematics') !== false) {
        // Infinity / plus
        return $svgBase . '<path d="M30 50 Q40 30 50 50 Q60 70 70 50" stroke="#0dcaf0" stroke-width="3" fill="none"/><path d="M70 50 Q60 30 50 50 Q40 70 30 50" stroke="#0dcaf0" stroke-width="3" fill="none"/>' . $svgEnd;
    }
    if (strpos($lower, 'additional mathematics') !== false) {
        // Integral sign + sqrt
        return $svgBase . '<text x="30" y="70" font-size="45" fill="#ffc107" font-weight="bold">∫</text><text x="60" y="40" font-size="30" fill="#ffc107">√x</text>' . $svgEnd;
    }
    if (strpos($lower, 'kiswahili') !== false) {
        // Book with Swahili pattern
        return $svgBase . '<rect x="30" y="30" width="40" height="45" rx="3" fill="#e83e8c" opacity="0.15" stroke="#e83e8c" stroke-width="2"/><line x1="40" y1="45" x2="60" y2="45" stroke="#e83e8c" stroke-width="2"/><line x1="40" y1="55" x2="55" y2="55" stroke="#e83e8c" stroke-width="2"/><path d="M50 30 L55 20 L45 20Z" fill="#e83e8c"/>' . $svgEnd;
    }
    if (strpos($lower, 'english language') !== false) {
        // Letters ABC
        return $svgBase . '<text x="30" y="55" font-size="35" fill="#17a2b8" font-weight="bold">A</text><text x="50" y="55" font-size="35" fill="#17a2b8" font-weight="bold">B</text><text x="70" y="55" font-size="35" fill="#17a2b8" font-weight="bold">C</text>' . $svgEnd;
    }
    if (strpos($lower, 'chinese') !== false) {
        // Chinese character 汉
        return $svgBase . '<rect x="30" y="30" width="40" height="45" rx="4" fill="#dc3545" opacity="0.15" stroke="#dc3545" stroke-width="2"/><text x="50" y="65" font-size="32" fill="#dc3545" text-anchor="middle" font-family="serif">汉</text>' . $svgEnd;
    }
    if (strpos($lower, 'arabic') !== false) {
        // Arabic calligraphy shape
        return $svgBase . '<path d="M30 55 Q45 35 60 45 Q70 50 75 40" stroke="#6f42c1" stroke-width="3" fill="none"/><circle cx="35" cy="55" r="3" fill="#6f42c1"/><text x="50" y="80" font-size="20" fill="#6f42c1" text-anchor="middle">عرب</text>' . $svgEnd;
    }
    if (strpos($lower, 'french') !== false) {
        // Eiffel tower silhouette + French flag
        return $svgBase . '<polygon points="50,30 40,60 60,60" fill="#20c997" opacity="0.6"/><rect x="25" y="65" width="50" height="8" fill="#0055A4"/><rect x="25" y="70" width="50" height="8" fill="#FFFFFF"/><rect x="25" y="75" width="50" height="8" fill="#EF4135"/>' . $svgEnd;
    }
    if (strpos($lower, 'literature in english') !== false) {
        // Quill and book
        return $svgBase . '<path d="M45 35 L70 20 L68 23 L45 38Z" fill="#fd7e14"/><path d="M30 45 L60 60 L65 55 L35 40Z" fill="#fd7e14" opacity="0.6"/><rect x="25" y="60" width="45" height="20" rx="3" fill="#fd7e14" opacity="0.2" stroke="#fd7e14"/>' . $svgEnd;
    }
    if (strpos($lower, 'fasihi ya kiswahili') !== false) {
        // Traditional African drum + book
        return $svgBase . '<ellipse cx="50" cy="55" rx="18" ry="12" fill="#e83e8c" opacity="0.4"/><rect x="40" y="45" width="20" height="10" fill="#e83e8c"/><circle cx="50" cy="40" r="6" fill="#e83e8c" opacity="0.7"/>' . $svgEnd;
    }
    if (strpos($lower, 'business studies') !== false) {
        // Graph growing
        return $svgBase . '<rect x="25" y="70" width="10" height="10" fill="#0d6efd"/><rect x="40" y="55" width="10" height="25" fill="#0d6efd"/><rect x="55" y="40" width="10" height="40" fill="#0d6efd"/><rect x="70" y="25" width="10" height="55" fill="#0d6efd"/><polyline points="30,75 45,60 60,45 75,30" stroke="#ffc107" stroke-width="2" fill="none"/>' . $svgEnd;
    }
    if (strpos($lower, 'bookkeeping') !== false) {
        // Ledger / calculator
        return $svgBase . '<rect x="30" y="25" width="40" height="55" rx="3" fill="#6c757d" opacity="0.2" stroke="#6c757d" stroke-width="2"/><line x1="38" y1="35" x2="62" y2="35" stroke="#6c757d" stroke-width="2"/><line x1="38" y1="45" x2="62" y2="45" stroke="#6c757d" stroke-width="1.5"/><line x1="38" y1="55" x2="50" y2="55" stroke="#6c757d" stroke-width="1.5"/>' . $svgEnd;
    }
    if (strpos($lower, 'textile') !== false) {
        // Scissors and thread
        return $svgBase . '<path d="M30 60 L55 40 M30 65 L55 45" stroke="#d63384" stroke-width="3"/><circle cx="28" cy="62" r="5" stroke="#d63384" stroke-width="2" fill="none"/><path d="M65 50 Q75 60 70 70 Q65 60 65 50Z" fill="#d63384" opacity="0.5"/>' . $svgEnd;
    }
    if (strpos($lower, 'food and nutrition') !== false) {
        // Apple or fork
        return $svgBase . '<circle cx="50" cy="45" r="20" fill="#28a745" opacity="0.3"/><path d="M50 25 L53 15 L47 15Z" fill="#28a745"/><path d="M40 40 L60 60 M60 40 L40 60" stroke="#ffc107" stroke-width="2"/>' . $svgEnd;
    }
    if (strpos($lower, 'music') !== false) {
        // Music notes
        return $svgBase . '<circle cx="40" cy="55" r="6" fill="#ffc107"/><path d="M46 55 L46 35 L65 30 L65 50" stroke="#ffc107" stroke-width="2.5" fill="none"/><circle cx="62" cy="50" r="5" fill="#ffc107"/>' . $svgEnd;
    }
    if (strpos($lower, 'sports') !== false) {
        // Trophy / ball
        return $svgBase . '<circle cx="50" cy="50" r="20" fill="#fd7e14" opacity="0.2" stroke="#fd7e14" stroke-width="2"/><path d="M45 65 L55 65 L52 80 L48 80Z" fill="#fd7e14"/><path d="M35 35 L45 45 M65 35 L55 45" stroke="#fd7e14" stroke-width="2"/>' . $svgEnd;
    }
    if (strpos($lower, 'fine arts') !== false) {
        // Palette with brush
        return $svgBase . '<circle cx="50" cy="45" r="25" fill="#8b5cf6" opacity="0.2"/><circle cx="40" cy="38" r="5" fill="#e83e8c"/><circle cx="60" cy="40" r="5" fill="#0dcaf0"/><circle cx="55" cy="55" r="5" fill="#ffc107"/><path d="M50 70 L65 80" stroke="#8b5cf6" stroke-width="3"/>' . $svgEnd;
    }
    if (strpos($lower, 'performing arts') !== false) {
        // Mask / theater
        return $svgBase . '<ellipse cx="50" cy="50" rx="18" ry="24" fill="#dc3545" opacity="0.2" stroke="#dc3545" stroke-width="2"/><path d="M38 50 Q50 40 62 50" stroke="#dc3545" stroke-width="2" fill="none"/><circle cx="43" cy="55" r="2" fill="#dc3545"/><circle cx="57" cy="55" r="2" fill="#dc3545"/>' . $svgEnd;
    }
    // Generic subject picture (book + star)
    return $svgBase . '<rect x="35" y="35" width="30" height="40" rx="3" fill="#6c757d" opacity="0.15" stroke="#6c757d" stroke-width="2"/><path d="M50 45 L55 35 L45 35Z" fill="#6c757d"/><line x1="42" y1="55" x2="58" y2="55" stroke="#6c757d" stroke-width="1.5"/><line x1="42" y1="62" x2="52" y2="62" stroke="#6c757d" stroke-width="1.5"/>' . $svgEnd;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT s.*, c.name AS class_name FROM tbl_students s LEFT JOIN tbl_classes c ON s.class = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    if (!$student) die("Student not found.");
    $fname = $student['fname'];
    $lname = $student['lname'];
    $class_id = $student['class'];
    $class_name = $student['class_name'] ?? 'Not Assigned';
    $gender = $student['gender'] ?? '';
    $email = $student['email'] ?? '';

    // Get assigned subject IDs
    $assigned_subject_ids = [];
    $stmt = $pdo->query("SELECT id, class, subject FROM tbl_subject_combinations WHERE status = 1");
    $all_combs = $stmt->fetchAll();
    foreach ($all_combs as $comb) {
        $classes = parse_class_list($comb['class']);
        if (in_array($class_id, $classes)) {
            $assigned_subject_ids[] = (int)$comb['subject'];
        }
    }
    $assigned_subject_ids = array_unique($assigned_subject_ids);

    $assigned_subjects = [];
    if (!empty($assigned_subject_ids)) {
        $placeholders = implode(',', array_fill(0, count($assigned_subject_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, name FROM tbl_subjects WHERE id IN ($placeholders) AND status = 1 ORDER BY name");
        $stmt->execute($assigned_subject_ids);
        $assigned_subjects = $stmt->fetchAll();
    }

} catch (Exception $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Student photo handling
$profile_image = null;
$use_icon = false;
if (!empty($student['display_image']) && $student['display_image'] !== 'DEFAULT') {
    $custom = 'images/students/' . $student['display_image'];
    if (file_exists(__DIR__ . '/../' . $custom)) $profile_image = $custom;
}
if (!$profile_image) {
    $gender_img = 'images/students/' . $gender . '.png';
    if (file_exists(__DIR__ . '/../' . $gender_img)) $profile_image = $gender_img;
}
if (!$profile_image) $use_icon = true;

$student_header = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $student_id,
    'display_image' => $profile_image ?? null,
    'gender' => $gender,
    'email' => $email
];

// Master list of all subjects (icon, colour, description)
$all_subjects_master = [
    'Chemistry' => ['icon' => 'bi bi-calculator-fill', 'color' => '#3b7cff', 'desc' => '3D Periodic Table – Explore elements, properties, and atomic structure.'],
    'Biology' => ['icon' => 'bi bi-tree', 'color' => '#28a745', 'desc' => 'Cell structure, genetics, and ecosystems.'],
    'Physics' => ['icon' => 'bi bi-graph-up', 'color' => '#20c997', 'desc' => 'Motion, forces, electricity, and waves.'],
    'Agriculture' => ['icon' => 'bi bi-flower1', 'color' => '#2e8b57', 'desc' => 'Crop production, livestock, and soil science.'],
    'Computer Science' => ['icon' => 'bi bi-pc-display', 'color' => '#6f42c1', 'desc' => 'Programming, algorithms, and digital logic.'],
    'Geography' => ['icon' => 'bi bi-globe2', 'color' => '#0d6efd', 'desc' => 'Map reading, climate, physical and human geography.'],
    'Historia ya Tanzania na Maadili' => ['icon' => 'bi bi-clock-history', 'color' => '#fd7e14', 'desc' => 'Tanzanian history, culture, and ethics.'],
    'History' => ['icon' => 'bi bi-journal-bookmark-fill', 'color' => '#8b5cf6', 'desc' => 'World and African history.'],
    'Bible Knowledge' => ['icon' => 'bi bi-book', 'color' => '#d63384', 'desc' => 'Biblical studies and religious education.'],
    'Elimu ya Dini ya Kiislamu' => ['icon' => 'bi bi-star', 'color' => '#198754', 'desc' => 'Islamic studies and teachings.'],
    'Basic Mathematics' => ['icon' => 'bi bi-infinity', 'color' => '#0dcaf0', 'desc' => 'Algebra, geometry, and arithmetic.'],
    'Additional Mathematics' => ['icon' => 'bi bi-plus-slash-minus', 'color' => '#ffc107', 'desc' => 'Advanced mathematics topics.'],
    'Kiswahili' => ['icon' => 'bi bi-translate', 'color' => '#e83e8c', 'desc' => 'Sarufi, fasihi, ufahamu, na insha.'],
    'English Language' => ['icon' => 'bi bi-chat-text', 'color' => '#17a2b8', 'desc' => 'Grammar, literature, comprehension, writing.'],
    'Chinese' => ['icon' => 'bi bi-chat-dots', 'color' => '#dc3545', 'desc' => 'Mandarin language and culture.'],
    'Arabic' => ['icon' => 'bi bi-chat-square', 'color' => '#6f42c1', 'desc' => 'Arabic language and calligraphy.'],
    'French' => ['icon' => 'bi bi-chat-quote', 'color' => '#20c997', 'desc' => 'French language and francophone culture.'],
    'Literature in English' => ['icon' => 'bi bi-journal-bookmark-fill', 'color' => '#fd7e14', 'desc' => 'Poetry, prose, plays, and literary analysis.'],
    'Fasihi ya Kiswahili' => ['icon' => 'bi bi-book', 'color' => '#e83e8c', 'desc' => 'Uchambuzi wa mashairi, tamthilia na riwaya.'],
    'Business Studies' => ['icon' => 'bi bi-shop', 'color' => '#0d6efd', 'desc' => 'Entrepreneurship, marketing, and management.'],
    'Bookkeeping' => ['icon' => 'bi bi-calculator', 'color' => '#6c757d', 'desc' => 'Accounting, ledgers, and financial records.'],
    'Textile and Garment Construction' => ['icon' => 'bi bi-scissors', 'color' => '#d63384', 'desc' => 'Fabric design, sewing, and garment making.'],
    'Food and Nutrition' => ['icon' => 'bi bi-egg-fried', 'color' => '#28a745', 'desc' => 'Healthy eating, food science, and meal planning.'],
    'Music' => ['icon' => 'bi bi-music-note-beamed', 'color' => '#ffc107', 'desc' => 'Music theory, instruments, and composition.'],
    'Sports' => ['icon' => 'bi bi-trophy', 'color' => '#fd7e14', 'desc' => 'Physical education, games, and athletics.'],
    'Fine Arts' => ['icon' => 'bi bi-palette', 'color' => '#8b5cf6', 'desc' => 'Drawing, painting, and creative design.'],
    'Performing Arts' => ['icon' => 'bi bi-theater', 'color' => '#dc3545', 'desc' => 'Drama, dance, and stage performance.'],
];

// Function to render a subject card with added relevant picture (SVG)
function renderSubjectCard($name, $data) {
    $slug = subjectSlug($name);
    $link = "student/{$slug}_index.php";
    $icon = $data['icon'];
    $color = $data['color'];
    $desc = $data['desc'];
    $picture = getSubjectPicture($name);
    
    return <<<HTML
    <div class="col-md-6 col-lg-4 mb-4">
        <a href="{$link}" class="text-decoration-none">
            <div class="tile text-center h-100">
                <div class="tile-body">
                    <div class="subject-picture mb-2">{$picture}</div>
                    <i class="{$icon}" style="font-size: 2.8rem; color: {$color};"></i>
                    <h5 class="mt-3">{$name}</h5>
                    <p class="text-muted">{$desc}</p>
                    <span class="btn btn-primary rounded-pill px-4">Explore →</span>
                </div>
            </div>
        </a>
    </div>
HTML;
}

// Build subject lists
$all_subjects_html = [];
foreach ($all_subjects_master as $name => $data) {
    $all_subjects_html[] = renderSubjectCard($name, $data);
}

$assigned_subjects_html = [];
foreach ($assigned_subjects as $subj) {
    $name = $subj['name'];
    if (isset($all_subjects_master[$name])) {
        $assigned_subjects_html[] = renderSubjectCard($name, $all_subjects_master[$name]);
    } else {
        $assigned_subjects_html[] = renderSubjectCard($name, ['icon' => 'bi bi-book', 'color' => '#6c757d', 'desc' => 'Interactive simulation and learning materials.']);
    }
}

$script_name = 'student/subject_simulations.php';
?>
<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="quick-actions mb-4">
        <a href="student/view" class="btn"><i class="bi bi-person"></i> My Profile</a>
        <a href="student/subjects" class="btn"><i class="bi bi-book"></i> My Subjects</a>
        <a href="student/results" class="btn"><i class="bi bi-file-text"></i> Full Results</a>
        <a href="student/learning_program.php" class="btn"><i class="bi bi-arrow-left"></i> Back to Learn</a>
    </div>

    <div class="student-info-card">
        <div class="row align-items-center">
            <div class="col-md-2 text-center mb-3 mb-md-0">
                <?php if ($use_icon): ?>
                    <div class="profile-icon"><i class="bi bi-person-circle"></i></div>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="profile-img">
                <?php endif; ?>
            </div>
            <div class="col-md-10">
                <div class="row">
                    <div class="col-md-4">
                        <div class="info-item"><span class="info-icon"><i class="bi bi-person-badge"></i></span><span><strong>Reg No:</strong> <?php echo htmlspecialchars($student_id); ?></span></div>
                        <div class="info-item"><span class="info-icon"><i class="bi bi-building"></i></span><span><strong>Class:</strong> <?php echo htmlspecialchars($class_name); ?></span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-item"><span class="info-icon"><i class="bi bi-gender-<?php echo strtolower($gender); ?>"></i></span><span><strong>Gender:</strong> <?php echo htmlspecialchars($gender); ?></span></div>
                        <div class="info-item"><span class="info-icon"><i class="bi bi-envelope"></i></span><span><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tile mb-4 text-center">
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="<?php echo $script_name; ?>?view=assigned" class="btn <?php echo $current_view === 'assigned' ? 'btn-primary' : 'btn-outline-secondary'; ?> rounded-pill px-4">
                <i class="bi bi-person-check"></i> My Assigned Subjects
            </a>
            <a href="<?php echo $script_name; ?>?view=all" class="btn <?php echo $current_view === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?> rounded-pill px-4">
                <i class="bi bi-grid-3x3-gap-fill"></i> All Subjects
            </a>
        </div>
        <p class="text-muted mt-3 mb-0">
            <?php if ($current_view === 'assigned'): ?>
                Showing subjects from your class schedule (<?php echo count($assigned_subjects_html); ?> subjects).
            <?php else: ?>
                Showing all O‑Level subjects (<?php echo count($all_subjects_html); ?> subjects).
            <?php endif; ?>
        </p>
    </div>

    <div class="row">
        <?php
        if ($current_view === 'assigned') {
            if (empty($assigned_subjects_html)) {
                echo '<div class="col-12"><div class="alert alert-info text-center">No subjects assigned to your class yet. Please contact the academic office.</div></div>';
            } else {
                echo implode('', $assigned_subjects_html);
            }
        } else {
            echo implode('', $all_subjects_html);
        }
        ?>
    </div>
</main>

<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script>
$(document).ready(function(){
    const scrollBtn = document.getElementById('scrollTopBtn');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) scrollBtn.classList.add('show');
        else scrollBtn.classList.remove('show');
    });
    scrollBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>

<style>
/* All existing styles (unchanged) plus enhancements for subject pictures */
.app-content { position: relative; z-index: 1; background: transparent; }
.student-info-card, .tile, .quick-actions .btn {
    background: #ffffff;
    border-radius: 28px;
    margin-bottom: 1.5rem;
    box-shadow: 0 15px 35px -10px rgba(0,0,0,0.1), 0 0 0 1px rgba(0,0,0,0.02);
    transition: all 0.25s ease;
}
.student-info-card:hover, .tile:hover, .quick-actions .btn:hover {
    transform: translateY(-5px) scale(1.01);
    box-shadow: 0 25px 40px -12px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
}
.student-info-card { padding: 1.5rem; }
.profile-img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #3b7cff; transition: transform 0.2s; }
.profile-img:hover { transform: scale(1.02); }
.profile-icon { font-size: 5rem; color: #3b7cff; text-align: center; }
.info-item { display: flex; align-items: center; margin-bottom: 0.8rem; color: #1e293b; }
.info-icon { width: 30px; color: #3b7cff; font-size: 1.3rem; }
.quick-actions .btn {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #1e293b;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
    border-radius: 40px;
    padding: 0.5rem 1.2rem;
}
.quick-actions .btn:hover { background: #f1f5f9; transform: translateY(-2px); }
.tile { padding: 1.5rem; margin-bottom: 1.5rem; background: #ffffff; color: #1e293b; }
.subject-picture svg {
    width: 80px;
    height: 80px;
    display: block;
    margin: 0 auto;
    transition: transform 0.2s ease;
}
.tile:hover .subject-picture svg {
    transform: scale(1.05);
}
.scroll-top-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 52px;
    height: 52px;
    background: linear-gradient(145deg, #00c6ff, #0072ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.6rem;
    cursor: pointer;
    box-shadow: 0 6px 0 #003f7f, 0 8px 18px rgba(0,0,0,0.25);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
    border: none;
}
.scroll-top-btn.show { opacity: 1; visibility: visible; }
.scroll-top-btn:hover { transform: translateY(-3px); box-shadow: 0 9px 0 #003f7f, 0 12px 22px rgba(0,0,0,0.3); }
body.dark .student-info-card, body.dark .tile, body.dark .quick-actions .btn {
    background: rgba(30,41,59,0.92);
    color: #e2e8f0;
}
body.dark .quick-actions .btn {
    background: #1e293b;
    border-color: #334155;
}
body.dark .info-item { color: #cbd5e1; }
body.dark .subject-picture svg {
    filter: drop-shadow(0 0 1px #ffffff80);
}
</style>
</body>
</html>