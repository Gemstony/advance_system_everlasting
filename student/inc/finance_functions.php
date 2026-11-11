<?php
// student/inc/finance_functions.php

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return number_format(floatval($amount), 0, '.', ',') . ' TZS';
    }
}

if (!function_exists('getStudentFeeAccount')) {
    function getStudentFeeAccount($pdo, $student_id, $academic_year = '2026') {
        $stmt = $pdo->prepare("SELECT * FROM tbl_student_fee_accounts WHERE student_id = ? AND academic_year = ?");
        $stmt->execute([$student_id, $academic_year]);
        return $stmt->fetch();
    }
}

if (!function_exists('updateFeeAccountBalance')) {
    function updateFeeAccountBalance($pdo, $student_id, $academic_year) {
        $stmt = $pdo->prepare("SELECT SUM(paid_amount) AS total_paid FROM tbl_fee_payments WHERE student_id = ? AND verification_status = 'verified'");
        $stmt->execute([$student_id]);
        $total_paid = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT total_expected FROM tbl_student_fee_accounts WHERE student_id = ? AND academic_year = ?");
        $stmt->execute([$student_id, $academic_year]);
        $account = $stmt->fetch();
        if ($account) {
            $expected = $account['total_expected'];
            $balance = $expected - $total_paid;
            $status = ($balance <= 0) ? 'cleared' : ($total_paid > 0 ? 'partial' : 'pending');
            $stmt = $pdo->prepare("UPDATE tbl_student_fee_accounts SET total_paid = ?, balance_due = ?, status = ? WHERE student_id = ? AND academic_year = ?");
            $stmt->execute([$total_paid, $balance, $status, $student_id, $academic_year]);
        }
    }
}

if (!function_exists('generateInvoiceNumber')) {
    function generateInvoiceNumber($pdo) {
        $year = date('Y');
        $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_fee_invoices WHERE invoice_no LIKE 'INV-{$year}-%'");
        $count = $stmt->fetchColumn() + 1;
        return "INV-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('generateReceiptNumber')) {
    function generateReceiptNumber($pdo) {
        $year = date('Y');
        $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_fee_receipts WHERE receipt_no LIKE 'RCP-{$year}-%'");
        $count = $stmt->fetchColumn() + 1;
        return "RCP-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('isFeeModuleEnabled')) {
    function isFeeModuleEnabled($pdo) {
        $stmt = $pdo->prepare("SELECT setting_value FROM tbl_system_settings WHERE setting_key = 'fee_module_enabled'");
        $stmt->execute();
        return $stmt->fetchColumn() == '1';
    }
}

if (!function_exists('getStudentPhoto')) {
    function getStudentPhoto($student) {
        $profile_image = null;
        $use_icon = false;
        $base = __DIR__ . '/../../'; // project root
        if (!empty($student['display_image']) && $student['display_image'] !== 'DEFAULT') {
            $custom = 'images/students/' . $student['display_image'];
            if (file_exists($base . $custom)) $profile_image = $custom;
        }
        if (!$profile_image) {
            $gender_img = 'images/students/' . $student['gender'] . '.png';
            if (file_exists($base . $gender_img)) $profile_image = $gender_img;
        }
        if (!$profile_image) $use_icon = true;
        return ['image' => $profile_image, 'use_icon' => $use_icon];
    }
}
?>