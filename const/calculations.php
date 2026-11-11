<?php
/**
 * const/calculations.php
 *
 * Tanzania Form V–VI / ACSEE-aware calculation engine.
 *
 * RESPONSIBILITIES (single source of truth):
 *   1. School TOTAL / AVERAGE / GRADE  -> uses ALL attended subjects.
 *   2. Division and POINTS             -> uses the 3 principal subjects
 *                                         defined for the student's
 *                                         combination.
 *                                         If fewer than 3 principal
 *                                         subjects have marks, the
 *                                         engine promotes the best-
 *                                         scoring non-principal
 *                                         subjects (fallback) so a
 *                                         division is still produced.
 *   3. Grade -> point mapping.
 *   4. Subject name normalisation and matching.
 *   5. Combination catalogue (code -> name + 3 principal subjects).
 *   6. Combination detection from a class name.
 *
 * THIS FILE MUST NOT:
 *   - Query the database.
 *   - Guess a combination from a student's marks.
 *
 * The result page supplies:
 *   $subjects    -> array of ['name' => ..., 'score' => ...]
 *   $combination -> e.g. 'PCM'
 *   $gradeSystem, $divisionSystem
 *
 * And receives back a fully-formed result object.
 *
 * -------------------------------------------------------------------
 * SCORE CONTRACT (IMPORTANT):
 *   null / ''  -> MISSING  (excluded from both school and ACSEE maths)
 *   0          -> a real mark -> grade F -> 7 points
 *   > 0        -> a real mark
 * -------------------------------------------------------------------
 * EXCLUDED SUBJECTS:
 *   A student may sit subjects that are NOT part of their combination
 *   (e.g. HTM, Academic Communication). Those subjects are still
 *   visible in the school totals, but they are only promoted into the
 *   division calculation if the principal set is incomplete.
 * -------------------------------------------------------------------
 * FALLBACK RULE:
 *   When fewer than 3 principal subjects have marks, the engine picks
 *   the best-scoring non-principal subjects (lowest points = highest
 *   grade; ties broken by the higher raw score) until 3 subjects are
 *   used. The chosen subjects are reported as fallback_subjects so
 *   the UI can label them. If even with fallback 3 subjects cannot be
 *   assembled, the result is marked incomplete.
 */

/* ================================================================
 * 1. GRADE ENGINE
 * ================================================================ */

/**
 * Return the letter grade for a score using the supplied grade system.
 * Callers must skip null/'' before calling.
 */
function get_grade($score, $gradeSystem)
{
    // Grade bands are defined for whole marks. Flooring decimal averages
    // prevents gaps between integer upper bounds, such as 59.8 falling
    // between D (maximum 59) and C (minimum 60).
    $score = floor(floatval($score));

    foreach ($gradeSystem as $grade) {
        if (
            isset($grade['min'], $grade['max'], $grade['name']) &&
            $score >= floatval($grade['min']) &&
            $score <= floatval($grade['max'])
        ) {
            return strtoupper(trim($grade['name']));
        }
    }

    return 'F';
}

/**
 * Return the remark text for a score using the supplied grade system.
 */
function get_grade_remark($score, $gradeSystem)
{
    $score = floor(floatval($score));

    foreach ($gradeSystem as $grade) {
        if (
            isset($grade['min'], $grade['max']) &&
            $score >= floatval($grade['min']) &&
            $score <= floatval($grade['max'])
        ) {
            return $grade['remark'] ?? '';
        }
    }

    return '';
}

/* ================================================================
 * 2. ACSEE GRADE -> POINT CONVERSION
 * ================================================================ */

function get_division_point_from_grade($grade)
{
    switch (strtoupper(trim((string)$grade))) {
        case 'A': return 1;
        case 'B': return 2;
        case 'C': return 3;
        case 'D': return 4;
        case 'E': return 5;
        case 'S': return 6;
        case 'F': return 7;
        default:  return 7;
    }
}

/* ================================================================
 * 3. SUBJECT NORMALISATION
 * ================================================================ */

/**
 * Normalise a subject name to a canonical lowercase form so that
 * "Mathematics", "maths", "ADV MATHS" etc. all collapse together.
 *
 * The map includes common abbreviations used in SRMS databases
 * AND subjects that are not principal (HTM, Academic Communication,
 * General Studies, etc.) so they are still recognised for school
 * totals and grade distribution.
 */
function normalize_subject_name($subject)
{
    $subject = strtolower(trim((string)$subject));
    $subject = str_replace(['_', '-', '/', '.', ',', '(', ')'], ' ', $subject);
    $subject = preg_replace('/\s+/', ' ', $subject);

    $aliases = [
        // English
        'eng'                         => 'english language',
        'english'                     => 'english language',
        'english lang'                => 'english language',
        'eng lang'                    => 'english language',
        'english language'            => 'english language',

        // Kiswahili
        'kiswahili'                   => 'kiswahili',
        'kiswahili language'          => 'kiswahili',
        'kiswahili lang'              => 'kiswahili',

        // History (broad support for variants found in SRMS databases)
        'history'                     => 'history',
        'hist'                        => 'history',
        'history form five'           => 'history',
        'history form six'            => 'history',

        // Geography
        'geography'                   => 'geography',
        'geo'                         => 'geography',
        'geog'                        => 'geography',

        // Mathematics
        'adv mathematics'             => 'advanced mathematics',
        'adv maths'                   => 'advanced mathematics',
        'adv math'                    => 'advanced mathematics',
        'advanced maths'              => 'advanced mathematics',
        'advanced math'               => 'advanced mathematics',
        'a math'                      => 'advanced mathematics',
        'am'                          => 'advanced mathematics',
        'mathematics'                 => 'advanced mathematics',
        'maths'                       => 'advanced mathematics',
        'math'                        => 'advanced mathematics',

        // Basic Applied Mathematics
        'basic math'                  => 'basic applied mathematics',
        'basic mathematics'           => 'basic applied mathematics',
        'basic applied maths'         => 'basic applied mathematics',
        'bam'                         => 'basic applied mathematics',

        // Computer Science
        'comp science'                => 'computer science',
        'comp sci'                    => 'computer science',
        'computer studies'            => 'computer science',
        'cs'                          => 'computer science',

        // ICS
        'ics'                         => 'information and computer studies',
        'information computer studies' => 'information and computer studies',
        'information and computer studies' => 'information and computer studies',

        // Languages
        'french lang'                 => 'french language',
        'french'                      => 'french language',
        'arabic lang'                 => 'arabic language',
        'arabic'                      => 'arabic language',
        'chinese lang'                => 'chinese language',
        'chinese'                     => 'chinese language',

        // Nutrition
        'food nutrition'              => 'food and human nutrition',
        'food human nutrition'        => 'food and human nutrition',
        'nutrition'                   => 'food and human nutrition',
        'fhn'                         => 'food and human nutrition',

        // Business
        'business'                    => 'business studies',
        'business studies'            => 'business studies',
        'accounting'                  => 'accountancy',
        'accountancy'                 => 'accountancy',
        'accounts'                    => 'accountancy',
        'commerce'                    => 'commerce',

        // Economics
        'economics'                   => 'economics',
        'econ'                        => 'economics',

        // Religious
        'islamic studies'             => 'islamic knowledge',
        'islamic'                     => 'islamic knowledge',
        'islamic knowledge'           => 'islamic knowledge',
        'divinity'                    => 'divinity',

        // Sports
        'physical education'          => 'sports',
        'pe'                          => 'sports',
        'sports'                      => 'sports',

        // Sciences
        'biology'                     => 'biology',
        'bio'                         => 'biology',
        'chemistry'                   => 'chemistry',
        'chem'                        => 'chemistry',
        'physics'                     => 'physics',
        'phy'                         => 'physics',
        'agriculture'                 => 'agriculture',
        'agric'                       => 'agriculture',

        // HTM (excluded in most combos)
        'htm'                         => 'hospitality and tourism management',
        'hospitality tourism'         => 'hospitality and tourism management',
        'hospitality and tourism'     => 'hospitality and tourism management',
        'hospitality and tourism management' => 'hospitality and tourism management',

        // Academic Communication (excluded in most combos)
        'academic communication'      => 'academic communication',
        'academic comm'               => 'academic communication',
        'acad comm'                   => 'academic communication',
        'ac'                          => 'academic communication',

        // General Studies
        'general studies'             => 'general studies',
        'gs'                          => 'general studies',

        // Marine Science
        'marine science'              => 'marine science',
        'marine'                      => 'marine science',

        // Arts
        'fine art'                    => 'fine art',
        'fine arts'                   => 'fine art',
        'art'                         => 'fine art',
        'music'                       => 'music',
        'fasihi'                      => 'fasihi ya kiswahili',
        'fasihi ya kiswahili'         => 'fasihi ya kiswahili',
        'literature'                  => 'literature in english',
        'literature in english'       => 'literature in english',
        'lit in eng'                  => 'literature in english',
    ];

    return $aliases[$subject] ?? $subject;
}

function subjects_match($subjectA, $subjectB)
{
    return normalize_subject_name($subjectA) === normalize_subject_name($subjectB);
}

/* ================================================================
 * 4. TANZANIA FORM V–VI SUBJECT CATALOGUE
 * ================================================================ */

/**
 * Full catalogue of Form V–VI subjects recognised by this engine.
 * Includes principal and excluded subjects.
 */
function get_tanzania_form_v_vi_subjects()
{
    return [
        // Languages
        'Kiswahili',
        'English Language',
        'French Language',
        'Arabic Language',
        'Chinese Language',
        'Fasihi ya Kiswahili',
        'Literature in English',

        // Humanities
        'History',
        'Geography',
        'Divinity',
        'Islamic Knowledge',
        'Economics',

        // Business
        'Business Studies',
        'Accountancy',
        'Commerce',

        // Sciences
        'Physics',
        'Chemistry',
        'Biology',
        'Agriculture',
        'Food and Human Nutrition',
        'Advanced Mathematics',
        'Basic Applied Mathematics',
        'Computer Science',
        'Information and Computer Studies',
        'Marine Science',

        // Arts / Sports
        'Fine Art',
        'Music',
        'Sports',

        // Compulsory / support
        'General Studies',
        'Academic Communication',

        // Excluded / vocational
        'Hospitality and Tourism Management',
    ];
}

/* ================================================================
 * 5. FORM V–VI CURRICULUM COMBINATIONS
 * ================================================================ */

function get_form_v_vi_combinations()
{
    return [
        // ---------------- SOCIAL SCIENCE ----------------
        'HGK' => [
            'name' => 'History, Geography and Kiswahili',
            'area' => 'Social Science',
            'subjects' => ['History', 'Geography', 'Kiswahili']
        ],
        'HGL' => [
            'name' => 'History, Geography and English Language',
            'area' => 'Social Science',
            'subjects' => ['History', 'Geography', 'English Language']
        ],
        'HGF' => [
            'name' => 'History, Geography and French',
            'area' => 'Social Science',
            'subjects' => ['History', 'Geography', 'French Language']
        ],
        'HGAr' => [
            'name' => 'History, Geography and Arabic',
            'area' => 'Social Science',
            'subjects' => ['History', 'Geography', 'Arabic Language']
        ],
        'HGCh' => [
            'name' => 'History, Geography and Chinese',
            'area' => 'Social Science',
            'subjects' => ['History', 'Geography', 'Chinese Language']
        ],
        'HGFa' => [
            'name' => 'History, Geography and Fasihi ya Kiswahili',
            'area' => 'Social Science',
            'subjects' => ['History', 'Geography', 'Fasihi ya Kiswahili']
        ],
        'HGLi' => [
            'name' => 'History, Geography and Literature in English',
            'area' => 'Social Science',
            'subjects' => ['History', 'Geography', 'Literature in English']
        ],
        'HGE' => [
            'name' => 'History, Geography and Economics',
            'area' => 'Social Science',
            'subjects' => ['History', 'Geography', 'Economics']
        ],

        // ---------------- LANGUAGE ----------------
        'KLF' => [
            'name' => 'Kiswahili, English Language and French',
            'area' => 'Language',
            'subjects' => ['Kiswahili', 'English Language', 'French Language']
        ],
        'KLAr' => [
            'name' => 'Kiswahili, English Language and Arabic',
            'area' => 'Language',
            'subjects' => ['Kiswahili', 'English Language', 'Arabic Language']
        ],
        'KLCh' => [
            'name' => 'Kiswahili, English Language and Chinese',
            'area' => 'Language',
            'subjects' => ['Kiswahili', 'English Language', 'Chinese Language']
        ],
        'KArCh' => [
            'name' => 'Kiswahili, Arabic and Chinese',
            'area' => 'Language',
            'subjects' => ['Kiswahili', 'Arabic Language', 'Chinese Language']
        ],
        'KArF' => [
            'name' => 'Kiswahili, Arabic and French',
            'area' => 'Language',
            'subjects' => ['Kiswahili', 'Arabic Language', 'French Language']
        ],
        'LFAr' => [
            'name' => 'English Language, French and Arabic',
            'area' => 'Language',
            'subjects' => ['English Language', 'French Language', 'Arabic Language']
        ],
        'LFCh' => [
            'name' => 'English Language, French and Chinese',
            'area' => 'Language',
            'subjects' => ['English Language', 'French Language', 'Chinese Language']
        ],
        'FArCh' => [
            'name' => 'French, Arabic and Chinese',
            'area' => 'Language',
            'subjects' => ['French Language', 'Arabic Language', 'Chinese Language']
        ],
        'HLF' => [
            'name' => 'History, English Language and French',
            'area' => 'Language',
            'subjects' => ['History', 'English Language', 'French Language']
        ],
        'HLAr' => [
            'name' => 'History, English Language and Arabic',
            'area' => 'Language',
            'subjects' => ['History', 'English Language', 'Arabic Language']
        ],
        'HLCh' => [
            'name' => 'History, English Language and Chinese',
            'area' => 'Language',
            'subjects' => ['History', 'English Language', 'Chinese Language']
        ],

        // ---------------- BUSINESS STUDIES ----------------
        'EBuAc' => [
            'name' => 'Economics, Business Studies and Accountancy',
            'area' => 'Business Studies',
            'subjects' => ['Economics', 'Business Studies', 'Accountancy']
        ],
        'EGM' => [
            'name' => 'Economics, Geography and Mathematics',
            'area' => 'Business Studies',
            'subjects' => ['Economics', 'Geography', 'Advanced Mathematics']
        ],
        'ECsM' => [
            'name' => 'Economics, Computer Science and Mathematics',
            'area' => 'Business Studies',
            'subjects' => ['Economics', 'Computer Science', 'Advanced Mathematics']
        ],
        'BuAcCs' => [
            'name' => 'Business Studies, Accountancy and Computer Science',
            'area' => 'Business Studies',
            'subjects' => ['Business Studies', 'Accountancy', 'Computer Science']
        ],
        'BuAcM' => [
            'name' => 'Business Studies, Accountancy and Mathematics',
            'area' => 'Business Studies',
            'subjects' => ['Business Studies', 'Accountancy', 'Advanced Mathematics']
        ],
        'ECA' => [
            'name' => 'Economics, Commerce and Accountancy',
            'area' => 'Business Studies',
            'subjects' => ['Economics', 'Commerce', 'Accountancy']
        ],

        // ---------------- SCIENCE ----------------
        'PCM' => [
            'name' => 'Physics, Chemistry and Mathematics',
            'area' => 'Science',
            'subjects' => ['Physics', 'Chemistry', 'Advanced Mathematics']
        ],
        'PCB' => [
            'name' => 'Physics, Chemistry and Biology',
            'area' => 'Science',
            'subjects' => ['Physics', 'Chemistry', 'Biology']
        ],
        'PGM' => [
            'name' => 'Physics, Geography and Mathematics',
            'area' => 'Science',
            'subjects' => ['Physics', 'Geography', 'Advanced Mathematics']
        ],
        'PMCs' => [
            'name' => 'Physics, Mathematics and Computer Science',
            'area' => 'Science',
            'subjects' => ['Physics', 'Advanced Mathematics', 'Computer Science']
        ],
        'CBA' => [
            'name' => 'Chemistry, Biology and Agriculture',
            'area' => 'Science',
            'subjects' => ['Chemistry', 'Biology', 'Agriculture']
        ],
        'CBN' => [
            'name' => 'Chemistry, Biology and Food and Human Nutrition',
            'area' => 'Science',
            'subjects' => ['Chemistry', 'Biology', 'Food and Human Nutrition']
        ],
        'CBG' => [
            'name' => 'Chemistry, Biology and Geography',
            'area' => 'Science',
            'subjects' => ['Chemistry', 'Biology', 'Geography']
        ],

        // ---------------- SPORTS ----------------
        'BNS' => [
            'name' => 'Biology, Food and Human Nutrition and Sports',
            'area' => 'Sports',
            'subjects' => ['Biology', 'Food and Human Nutrition', 'Sports']
        ],
        'LMS' => [
            'name' => 'English Language, Music and Sports',
            'area' => 'Sports',
            'subjects' => ['English Language', 'Music', 'Sports']
        ],
        'KMS' => [
            'name' => 'Kiswahili, Music and Sports',
            'area' => 'Sports',
            'subjects' => ['Kiswahili', 'Music', 'Sports']
        ],
        'FaMS' => [
            'name' => 'Fasihi ya Kiswahili, Music and Sports',
            'area' => 'Sports',
            'subjects' => ['Fasihi ya Kiswahili', 'Music', 'Sports']
        ],
        'LiMS' => [
            'name' => 'Literature in English, Music and Sports',
            'area' => 'Sports',
            'subjects' => ['Literature in English', 'Music', 'Sports']
        ],

        /*
         * SCHOOL/LEGACY COMPATIBILITY:
         * HKL is kept for schools that still enter History + Kiswahili
         * + English as a single class.
         */
        'HKL' => [
            'name' => 'History, Kiswahili and English Language (School/Legacy HKL)',
            'area' => 'School Compatibility',
            'subjects' => ['History', 'Kiswahili', 'English Language']
        ],
    ];
}

/**
 * Normalise a combination code written in any casing / spacing.
 */
function normalize_combination_code($code)
{
    $code = trim((string)$code);
    $code = preg_replace('/\s+/', '', $code);

    $aliases = [
        'PCM1'    => 'PCM',
        'PCB1'    => 'PCB',
        'PGM1'    => 'PGM',
        'PMC'     => 'PMCs',
        'PMCS'    => 'PMCs',
        'PMC1'    => 'PMCs',
        'PMCS1'   => 'PMCs',
        'ECM'     => 'ECsM',
        'ECSM'    => 'ECsM',
        'HGK1'    => 'HGK',
        'HGL1'    => 'HGL',
        'HGF1'    => 'HGF',
        'HGE1'    => 'HGE',
        'HGAR1'   => 'HGAr',
        'HGCH1'   => 'HGCh',
        'HGFA1'   => 'HGFa',
        'HGLI1'   => 'HGLi',
        'KLF1'    => 'KLF',
        'KLAR1'   => 'KLAr',
        'KLCH1'   => 'KLCh',
        'KARCH1'  => 'KArCh',
        'KARF1'   => 'KArF',
        'LFAR1'   => 'LFAr',
        'LFCH1'   => 'LFCh',
        'FARCH1'  => 'FArCh',
        'HLF1'    => 'HLF',
        'HLAR1'   => 'HLAr',
        'HLCH1'   => 'HLCh',
        'EBUAC1'  => 'EBuAc',
        'ECSM1'   => 'ECsM',
        'ECA1'    => 'ECA',
        'BUACCS1' => 'BuAcCs',
        'BUACM1'  => 'BuAcM',
        'CBA1'    => 'CBA',
        'CBN1'    => 'CBN',
        'CBG1'    => 'CBG',
        'BNS1'    => 'BNS',
        'LMS1'    => 'LMS',
        'KMS1'    => 'KMS',
        'FAMS1'   => 'FaMS',
        'LIMS1'   => 'LiMS',
        'HKL1'    => 'HKL',
    ];

    $upper = strtoupper($code);
    return $aliases[$upper] ?? $code;
}

function get_combination_details($combination)
{
    $combination = normalize_combination_code($combination);
    if ($combination === '') return null;

    $combinations = get_form_v_vi_combinations();

    foreach ($combinations as $code => $details) {
        if (strcasecmp($code, $combination) === 0) {
            return [
                'code'     => $code,
                'name'     => $details['name'],
                'area'     => $details['area'],
                'subjects' => $details['subjects']
            ];
        }
    }

    return null;
}

function get_principal_subjects_for_combination($combination)
{
    $details = get_combination_details($combination);
    return $details['subjects'] ?? [];
}

/* ================================================================
 * 6. COMBINATION DETECTION FROM CLASS NAME
 * ================================================================ */

/**
 * Detect a combination code from a class name such as:
 *   "HKL FORM FIVE 2026"  -> HKL
 *   "Form Five PCM"       -> PCM
 *   "PGM-2026"            -> PGM
 *
 * This is intentionally the ONLY place that maps a class to a
 * combination. The calculation engine below never guesses.
 */
function detect_combination_from_class_name($className)
{
    $className = strtoupper((string)$className);
    $combinations = get_form_v_vi_combinations();

    // The school commonly stores Physics, Mathematics and Computer
    // Science classes as "PMC", while the catalogue uses "PMCs".
    if (preg_match('/(?<![A-Z])PMC(?![A-Z])/i', $className)) {
        return 'PMCs';
    }
    if (preg_match('/(?<![A-Z])ECM(?![A-Z])/i', $className)) {
        return 'ECsM';
    }

    // Longest code first so that HKL does not eat HGKL etc.
    $codes = array_keys($combinations);
    usort($codes, function ($a, $b) {
        return strlen($b) <=> strlen($a);
    });

    foreach ($codes as $code) {
        $pattern = '/(?<![A-Z])' . preg_quote(strtoupper($code), '/') . '(?![A-Z])/i';
        if (preg_match($pattern, $className)) {
            return $code;
        }

        // Fallback for classes stored with punctuation e.g. "P.C.M-2026".
        $compactClass = preg_replace('/[^A-Z0-9]/i', '', $className);
        if (stripos($compactClass, strtoupper($code)) !== false) {
            return $code;
        }
    }

    return null;
}

/* ================================================================
 * 7. PRINCIPAL vs EXCLUDED SUBJECT BREAKDOWN
 * ================================================================ */

/**
 * Split a student's subjects into:
 *   - principal : subjects that match the combination's principal list
 *   - excluded  : everything else (HTM, Academic Communication, etc.)
 *   - missing   : principal names not present in the student's list
 *
 * Missing principal names are reported so the UI can warn or so the
 * fallback logic can promote other subjects.
 */
function get_student_subject_breakdown($subjects, $combination)
{
    $principalNames = get_principal_subjects_for_combination($combination);

    $principal = [];
    $excluded  = [];
    $missing   = [];

    if (empty($principalNames)) {
        // No combination known -> nothing is principal.
        foreach ($subjects as $subject) {
            if (isset($subject['name'])) {
                $excluded[] = $subject;
            }
        }
        return [
            'principal' => [],
            'excluded'  => $excluded,
            'missing'   => [],
            'known'     => false
        ];
    }

    $matchedNames = [];

    foreach ($subjects as $subject) {
        if (!isset($subject['name'])) continue;

        $isPrincipal = false;
        foreach ($principalNames as $principalName) {
            if (subjects_match($subject['name'], $principalName)) {
                $isPrincipal = true;
                $matchedNames[] = $principalName;
                break;
            }
        }

        if ($isPrincipal) {
            $principal[] = $subject;
        } else {
            $excluded[] = $subject;
        }
    }

    foreach ($principalNames as $principalName) {
        $found = false;
        foreach ($matchedNames as $m) {
            if (strcasecmp($m, $principalName) === 0) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missing[] = $principalName;
        }
    }

    return [
        'principal' => $principal,
        'excluded'  => $excluded,
        'missing'   => $missing,
        'known'     => true
    ];
}

/* ================================================================
 * 8. STUDENT PRINCIPAL SUBJECTS
 * ================================================================ */

/**
 * Return only the principal subjects for the given combination,
 * preserving their order from the combination definition.
 */
function get_student_principal_subjects($subjects, $combination)
{
    $principalNames = get_principal_subjects_for_combination($combination);
    if (count($principalNames) !== 3) return [];

    $principal = [];

    foreach ($principalNames as $principalName) {
        foreach ($subjects as $subject) {
            if (!isset($subject['name'])) continue;

            if (subjects_match($subject['name'], $principalName)) {
                $principal[] = $subject;
                break;
            }
        }
    }

    return $principal;
}

/* ================================================================
 * 9. HELPERS (fallback + filtering)
 * ================================================================ */

/**
 * Return the subset of $subjects that have a real mark.
 * null / '' is treated as not attended. 0 is a real mark.
 */
function filter_subjects_with_marks($subjects)
{
    $out = [];
    foreach ($subjects as $s) {
        if (!isset($s['name'])) continue;
        if (!array_key_exists('score', $s)) continue;
        if ($s['score'] === null || $s['score'] === '') continue;
        $out[] = $s;
    }
    return $out;
}

/**
 * Pick the best-scoring subjects from $candidates to fill $needed slots.
 * "Best" means lowest ACSEE points (highest grade). Ties broken by
 * the higher raw score. Subjects without marks are ignored.
 */
function pick_fallback_subjects($candidates, $needed, $gradeSystem)
{
    if ($needed <= 0) return [];

    $pool = filter_subjects_with_marks($candidates);

    usort($pool, function ($a, $b) use ($gradeSystem) {
        $ga = get_grade($a['score'], $gradeSystem);
        $gb = get_grade($b['score'], $gradeSystem);
        $pa = get_division_point_from_grade($ga);
        $pb = get_division_point_from_grade($gb);

        if ($pa === $pb) {
            return (float)$b['score'] <=> (float)$a['score'];
        }
        return $pa - $pb;
    });

    return array_slice($pool, 0, $needed);
}

/**
 * Does the subject list already contain the given subject name?
 */
function subject_list_contains($subjects, $name)
{
    foreach ($subjects as $s) {
        if (!isset($s['name'])) continue;
        if (subjects_match($s['name'], $name)) return true;
    }
    return false;
}

/* ================================================================
 * 10. SCHOOL RESULT (ALL SUBJECTS)
 * ================================================================ */

/**
 * Compute the school-facing result for a set of subjects.
 *
 * RULES:
 *   - null / ''  -> subject is ignored entirely (not attended).
 *   - 0          -> included as 0% and graded normally.
 *   - > 0        -> included.
 *
 * The school result never depends on a combination.
 */
function calculate_school_result($subjects, $gradeSystem)
{
    $total    = 0;
    $count    = 0;
    $attended = [];
    $gradeCounts = ['A'=>0,'B'=>0,'C'=>0,'D'=>0,'E'=>0,'S'=>0,'F'=>0];

    foreach ($subjects as $subject) {
        if (!isset($subject['name'])) continue;
        if (!array_key_exists('score', $subject)) continue;

        $raw = $subject['score'];
        if ($raw === null || $raw === '') continue;

        $score = (float)$raw;
        $total += $score;
        $count++;

        $grade = get_grade($score, $gradeSystem);
        if (isset($gradeCounts[$grade])) {
            $gradeCounts[$grade]++;
        }

        $attended[] = [
            'name'   => $subject['name'],
            'score'  => $score,
            'grade'  => $grade,
            'remark' => get_grade_remark($score, $gradeSystem),
        ];
    }

    $average  = $count > 0 ? round($total / $count) : 0;
    $avgGrade = $count > 0 ? get_grade($average, $gradeSystem) : 'N/A';
    $avgRemark= $count > 0 ? get_grade_remark($average, $gradeSystem) : 'N/A';

    return [
        'total'          => $total,
        'count'          => $count,
        'average'        => $average,
        'average_grade'  => $avgGrade,
        'average_remark' => $avgRemark,
        'attended'       => $attended,
        'grade_counts'   => $gradeCounts,
    ];
}

/* ================================================================
 * 11. POINTS FOR A SET OF SUBJECTS
 * ================================================================ */

/**
 * Sum the ACSEE points for the given subjects.
 * Returns null if any subject lacks a real mark.
 * Expects exactly 3 subjects in normal use.
 */
function calculate_points_for_subjects($subjects, $gradeSystem)
{
    if (count($subjects) < 3) return null;

    $points = 0;
    foreach ($subjects as $s) {
        if (!array_key_exists('score', $s) || $s['score'] === null || $s['score'] === '') {
            return null;
        }
        $grade = get_grade($s['score'], $gradeSystem);
        $points += get_division_point_from_grade($grade);
    }
    return $points;
}

/**
 * Legacy helper: sum the ACSEE points for the 3 principal subjects of
 * the given combination. Does NOT fall back; use calculate_acsee_result()
 * for the fallback-aware flow.
 *
 * IMPORTANT: 0 is a valid mark and produces grade F = 7 points.
 */
function calculate_acsee_points($subjects, $combination, $gradeSystem)
{
    $principalSubjects = get_student_principal_subjects($subjects, $combination);

    if (count($principalSubjects) !== 3) {
        return null;
    }

    return calculate_points_for_subjects($principalSubjects, $gradeSystem);
}

/* ================================================================
 * 12. DIVISION FROM POINTS
 * ================================================================ */

function get_division_from_points($totalPoints, $divisionSystem = null)
{
    $totalPoints = intval($totalPoints);

    // NECTA ACSEE division bands are fixed: lower points represent better
    // performance, and only the three principal subjects are counted.
    if ($totalPoints >= 3  && $totalPoints <= 7)  return 'I';
    if ($totalPoints >= 8  && $totalPoints <= 9)  return 'II';
    if ($totalPoints >= 10 && $totalPoints <= 13) return 'III';
    if ($totalPoints >= 14 && $totalPoints <= 16) return 'IV';
    if ($totalPoints >= 17 && $totalPoints <= 21) return '0';

    return '0';
}

/* ================================================================
 * 13. FULL ACSEE RESULT (with fallback)
 * ================================================================ */

/**
 * Returns a rich result object:
 *   - combination_code / name / area
 *   - principal_subjects : principal subjects that have marks (used)
 *   - fallback_subjects  : non-principal subjects promoted to fill
 *                          gaps in the principal set (used)
 *   - used_subjects      : principal + fallback; exactly the 3 that
 *                          drive the points / division
 *   - excluded_subjects  : non-principal subjects NOT used for points
 *   - missing_principals : principal names that had no matching row
 *   - points / division
 *   - complete           : true if 3 subjects could be used
 *   - using_fallback     : true if any fallback subject was used
 *   - status:
 *       OK
 *       OK_WITH_FALLBACK
 *       UNKNOWN_COMBINATION
 *       INCOMPLETE_PRINCIPAL_SUBJECTS
 *       CALCULATION_ERROR
 *
 * @param array      $subjects         [ ['name' => ..., 'score' => ...], ... ]
 * @param string     $combination      e.g. 'PCM'
 * @param array      $gradeSystem
 * @param array|null $divisionSystem
 * @param bool       $allowFallback    default true
 */
function calculate_acsee_result($subjects, $combination, $gradeSystem, $divisionSystem = null, $allowFallback = true)
{
    $details   = get_combination_details($combination);
    $breakdown = get_student_subject_breakdown($subjects, $combination);

    if ($details === null) {
        return [
            'combination_code'   => $combination,
            'combination_name'   => null,
            'area'               => null,
            'principal_subjects' => [],
            'fallback_subjects'  => [],
            'used_subjects'      => [],
            'excluded_subjects'  => $breakdown['excluded'],
            'missing_principals' => [],
            'points'             => null,
            'division'           => null,
            'complete'           => false,
            'using_fallback'     => false,
            'status'             => 'UNKNOWN_COMBINATION'
        ];
    }

    // Principal subjects that actually have a real mark.
    $principalWithMarks = filter_subjects_with_marks($breakdown['principal']);

    $fallbackSubjects = [];
    $usingFallback    = false;

    // If the principal set is short, promote the best remaining subjects.
    if (count($principalWithMarks) < 3 && $allowFallback) {
        $needed = 3 - count($principalWithMarks);

        // Candidates: excluded subjects, minus any whose normalised name
        // collides with a principal name (prevents double-counting).
        $candidates = [];
        foreach ($breakdown['excluded'] as $ex) {
            if (!isset($ex['name'])) continue;

            $isDuplicatePrincipal = false;
            foreach ($details['subjects'] as $pName) {
                if (subjects_match($ex['name'], $pName)) {
                    $isDuplicatePrincipal = true;
                    break;
                }
            }
            if ($isDuplicatePrincipal) continue;

            $candidates[] = $ex;
        }

        $fallbackSubjects = pick_fallback_subjects($candidates, $needed, $gradeSystem);

        if (count($principalWithMarks) + count($fallbackSubjects) >= 3) {
            $usingFallback = true;
        }
    }

    $usedSubjects = array_merge($principalWithMarks, $fallbackSubjects);

    // If we still can't assemble 3 subjects, the result is incomplete.
    if (count($usedSubjects) < 3) {
        return [
            'combination_code'   => $details['code'],
            'combination_name'   => $details['name'],
            'area'               => $details['area'],
            'principal_subjects' => $principalWithMarks,
            'fallback_subjects'  => [],
            'used_subjects'      => $usedSubjects,
            'excluded_subjects'  => $breakdown['excluded'],
            'missing_principals' => $breakdown['missing'],
            'points'             => null,
            'division'           => null,
            'complete'           => false,
            'using_fallback'     => false,
            'status'             => 'INCOMPLETE_PRINCIPAL_SUBJECTS'
        ];
    }

    // Compute points from exactly the 3 chosen subjects.
    $points = calculate_points_for_subjects($usedSubjects, $gradeSystem);

    if ($points === null) {
        return [
            'combination_code'   => $details['code'],
            'combination_name'   => $details['name'],
            'area'               => $details['area'],
            'principal_subjects' => $principalWithMarks,
            'fallback_subjects'  => $fallbackSubjects,
            'used_subjects'      => $usedSubjects,
            'excluded_subjects'  => $breakdown['excluded'],
            'missing_principals' => $breakdown['missing'],
            'points'             => null,
            'division'           => null,
            'complete'           => false,
            'using_fallback'     => $usingFallback,
            'status'             => 'CALCULATION_ERROR'
        ];
    }

    // Build the truly-excluded list: everything non-principal that was
    // NOT promoted to fallback.
    $usedNames = [];
    foreach ($usedSubjects as $u) {
        $usedNames[] = normalize_subject_name($u['name'] ?? '');
    }

    $excludedNotCounted = [];
    foreach ($breakdown['excluded'] as $ex) {
        if (!isset($ex['name'])) continue;
        $norm = normalize_subject_name($ex['name']);
        if (in_array($norm, $usedNames, true)) continue;
        $excludedNotCounted[] = $ex;
    }

    return [
        'combination_code'   => $details['code'],
        'combination_name'   => $details['name'],
        'area'               => $details['area'],
        'principal_subjects' => $principalWithMarks,
        'fallback_subjects'  => $fallbackSubjects,
        'used_subjects'      => $usedSubjects,
        'excluded_subjects'  => $excludedNotCounted,
        'missing_principals' => $breakdown['missing'],
        'points'             => $points,
        'division'           => get_division_from_points($points, $divisionSystem),
        'complete'           => true,
        'using_fallback'     => $usingFallback,
        'status'             => $usingFallback ? 'OK_WITH_FALLBACK' : 'OK'
    ];
}

/* ================================================================
 * 14. LEGACY FUNCTIONS
 *
 * Kept ONLY so older pages do not break immediately. Do not use on
 * new pages — they pick the "best three" from the entire mark list
 * and cannot know the student's combination.
 * ================================================================ */

/**
 * @deprecated Use calculate_acsee_result() with a combination instead.
 *
 * Picks the three smallest points from all scores. This is WRONG
 * for ACSEE because it does not know the student's combination.
 */
function get_points($marks)
{
    global $gradeSystem;

    if (!isset($gradeSystem) || empty($gradeSystem)) {
        trigger_error('Grade system not set in global', E_USER_WARNING);
        return 0;
    }

    $points = [];

    foreach ($marks as $score) {
        if ($score === null || $score === '') continue;

        $grade = get_grade($score, $gradeSystem);
        $points[] = get_division_point_from_grade($grade);
    }

    sort($points);
    return array_sum(array_slice($points, 0, 3));
}

/**
 * @deprecated Use calculate_acsee_result() with a combination instead.
 */
function get_division($marks)
{
    global $divisionSystem;
    return get_division_from_points(
        get_points($marks),
        $divisionSystem ?? null
    );
}

/**
 * University entry points. Independent of ACSEE combination rules.
 */
function get_university_points($scores, $gradeSystem)
{
    $total = 0;

    foreach ($scores as $score) {
        if ($score === null || $score === '') continue;

        $grade = get_grade($score, $gradeSystem);

        switch ($grade) {
            case 'A': $total += 5;   break;
            case 'B': $total += 4;   break;
            case 'C': $total += 3;   break;
            case 'D': $total += 2;   break;
            case 'E': $total += 1;   break;
            case 'S': $total += 0.5; break;
        }
    }

    return $total;
}