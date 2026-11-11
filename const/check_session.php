<?php
if(!isset($_COOKIE["__SRMS__logged"])) {
    $res = "0";
} else {
    if(!isset($_COOKIE["__SRMS__key"])) {
        $res = "0";
    } else {
        $session_key = $_COOKIE["__SRMS__key"];
        $current_ip = $_SERVER['REMOTE_ADDR'];
        $res = "0";

        try {
            $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->exec("SET SESSION sql_mode = ''");

            // Single query with LEFT JOIN to both staff and students
            $stmt = $conn->prepare("SELECT 
                s.*,
                stf.fname AS staff_fname, stf.lname AS staff_lname, stf.gender AS staff_gender, stf.email AS staff_email, stf.level AS staff_level, stf.status AS staff_status,
                std.fname AS student_fname, std.mname AS student_mname, std.lname AS student_lname, std.gender AS student_gender, std.email AS student_email, std.class AS student_class, std.level AS student_level, std.display_image AS student_img, std.status AS student_status
                FROM tbl_login_sessions s
                LEFT JOIN tbl_staff stf ON s.staff = stf.id
                LEFT JOIN tbl_students std ON s.student = std.id
                WHERE s.session_key = ?");
            $stmt->execute([$session_key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $res = "0";
            } else {
                $session_ip = $row['ip_address'];
                if ($current_ip != $session_ip) {
                    $res = "3";
                } else {
                    // Check if staff column is NOT NULL
                    if (!is_null($row['staff'])) {
                        // Staff user (admin, academic, teacher, accountant)
                        $account_id = $row['staff'];
                        $fname = $row['staff_fname'];
                        $lname = $row['staff_lname'];
                        $gender = $row['staff_gender'];
                        $email = $row['staff_email'];
                        $level = $row['staff_level'];
                        $status = $row['staff_status'];
                        $res = ($status == "1") ? "1" : "2";
                    } 
                    // Check if student column is NOT NULL
                    elseif (!is_null($row['student'])) {
                        // Student user
                        $account_id = $row['student'];
                        $fname = $row['student_fname'];
                        $mname = $row['student_mname'];
                        $lname = $row['student_lname'];
                        $gender = $row['student_gender'];
                        $email = $row['student_email'];
                        $class = (int)$row['student_class'];
                        $level = $row['student_level'];
                        $img = $row['student_img'];
                        $status = $row['student_status'];
                        $res = ($status == "1") ? "1" : "2";

                        // Get class name
                        $stmt2 = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ?");
                        $stmt2->execute([$class]);
                        $classRow = $stmt2->fetch();
                        $act_class = $classRow ? $classRow['name'] : 'Unknown';
                    } else {
                        $res = "0";
                    }
                }
            }
        } catch(PDOException $e) {
            error_log("Session check error: " . $e->getMessage());
            $res = "0";
        }
    }
}

// Maintenance mode check
if ($res == "1" && $level != "0") {
    try {
        if (!isset($conn) || !$conn) {
            $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $conn->exec("ALTER TABLE tbl_school ADD COLUMN IF NOT EXISTS maintenance_mode TINYINT NOT NULL DEFAULT 0");
        $stmt = $conn->query("SELECT maintenance_mode FROM tbl_school WHERE id = 1");
        $maintenance = $stmt->fetchColumn();
        if ($maintenance == 1) {
            $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
            $root = preg_replace('#/(const|admin|academic|teacher|student|accountant).*$#', '', $base);
            header("Location: " . $root . "/maintenance.php");
            exit;
        }
    } catch (PDOException $e) {}
}
?>