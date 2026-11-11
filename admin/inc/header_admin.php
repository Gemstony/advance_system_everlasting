<?php
/**
 * Admin Header – Ultimate Header for Administrators (30 themes, notifications, sidebar toggle)
 * 
 * Expects:
 * - $staff: array with staff details (fname, lname, id, display_image?, email?)
 * - $pdo: PDO connection (defined globally)
 * 
 * If not available, will try to fetch from session.
 * 
 * Displays:
 * - School name (text only, logo removed)
 * - Sidebar toggle button (id="sidebarToggle")
 * - Centered greeting: Hello, {first name}
 * - Theme selector (30 themes with real animations)
 * - Notifications dropdown (announcements for level 0 or 2)
 * - User dropdown (Profile, System Settings, Logout) – no user name text
 * 
 * On small devices (≤768px), sidebar automatically collapses on page load.
 * On larger screens, it starts expanded (or respects saved preference).
 */

if (!isset($staff) || empty($staff)) {
    if (isset($_SESSION['staff_id'])) {
        try {
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            $stmt = $pdo->prepare("SELECT id, fname, lname, email, gender, phone FROM tbl_staff WHERE id = ?");
            $stmt->execute([$_SESSION['staff_id']]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$staff) $staff = ['fname' => 'Admin', 'lname' => '', 'id' => $_SESSION['staff_id']];
        } catch (Exception $e) {
            $staff = ['fname' => 'Admin', 'lname' => '', 'id' => $_SESSION['staff_id']];
        }
    } else {
        $staff = ['fname' => 'Admin', 'lname' => '', 'id' => 0];
    }
}

// Fetch school info
if (!isset($school_name) || empty($school_name)) {
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $stmt = $pdo->prepare("SELECT name, logo FROM tbl_school LIMIT 1");
        $stmt->execute();
        $school = $stmt->fetch();
        $school_name = $school['name'] ?? 'SRMS';
    } catch (Exception $e) {
        $school_name = defined('WBName') ? WBName : 'SRMS';
    }
}

// Fetch unread notifications count and latest announcements for admin (level 0 or 2)
$notification_count = 0;
$latest_announcements = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_announcements WHERE level IN (0,2)");
        $stmt->execute();
        $notification_count = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT id, title, announcement, create_date FROM tbl_announcements WHERE level IN (0,2) ORDER BY create_date DESC LIMIT 5");
        $stmt->execute();
        $latest_announcements = $stmt->fetchAll();
    } catch (Exception $e) {}
}

$saved_theme = isset($_COOKIE['site_theme']) ? $_COOKIE['site_theme'] : 'theme-1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <style>
        /* ---------- GLOBAL & THEMES (30) – same as academic header ---------- */
        body {
            transition: background 0.3s ease;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            position: relative;
        }

        /* Backgrounds for 30 themes (identical to student header) */
        body.theme-1 { background: linear-gradient(135deg, rgba(0,0,0,0.85), rgba(0,0,0,0.7)), url('https://csscrafter.com/wp-content/uploads/2023/08/mysticforest.jpeg'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-2 { background: #0a0a2a; }
        body.theme-3 { background: linear-gradient(135deg, #0b2b3b, #1a4a6f); }
        body.theme-4 { background: linear-gradient(135deg, #1a2980, #26d0ce); }
        body.theme-5 { background: #0a0a0a; }
        body.theme-6 { background: #0f172a; }
        body.theme-7 { background: linear-gradient(to bottom, #0b0b2b, #1b2735 70%, #090a0f); }
        body.theme-8 { background: linear-gradient(315deg, #65005e, #3c84ce, #30eee2, #ff1919); background-size: 400% 400%; animation: gradientShift 15s ease infinite; }
        body.theme-9 { background: url('https://i.pinimg.com/originals/44/6e/3b/446e3b79395a287ca32f7977dd83b290.jpg'); background-size: cover; background-attachment: fixed; }
        body.theme-10 { background: #0b0b2b; }
        body.theme-11 { background: radial-gradient(#333, #000); }
        body.theme-12 { background: linear-gradient(180deg, #04fafd, 5%, #119dff, 50%, #030423); }
        body.theme-13 { background: linear-gradient(315deg, rgba(101,0,94,1) 3%, rgba(60,132,206,1) 38%, rgba(48,238,226,1) 68%, rgba(255,25,25,1) 98%); background-size: 400% 400%; animation: gradientShift 15s ease infinite; }
        body.theme-14 { background: black; }
        body.theme-15 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-16 { background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1600&q=80'); background-size: cover; background-position: center 30%; background-attachment: fixed; }
        body.theme-17 { background: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-18 { background: linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1522383225653-ed111181a6b1?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-19 { background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1502086223501-7ea6ecd79368?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-20 { background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.35)), url('https://images.unsplash.com/photo-1503788311183-fa3bf9c4f32b?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-21 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1511497584788-876760111969?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-22 { background: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.55)), url('https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-23 { background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-24 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1545569341-9eb8b30979d9?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-25 { background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1500673922987-e212871fec22?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-26 { background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.45)), url('https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-27 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1502082553048-f009c37129b9?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-28 { background: linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.45)), url('https://images.unsplash.com/photo-1448375240586-882707db888b?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-29 { background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1501854140801-50d01698950b?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }
        body.theme-30 { background: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.55)), url('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=1600&q=80'); background-size: cover; background-position: center; background-attachment: fixed; }

        @keyframes gradientShift { 0% { background-position: 0% 0%; } 50% { background-position: 100% 100%; } 100% { background-position: 0% 0%; } }

        /* ---------- HEADER STYLES (logo removed) ---------- */
        .app-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(10, 10, 20, 0.55);
            backdrop-filter: blur(18px) saturate(180%);
            padding: 0.5rem 2rem;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,215,0,0.3);
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .app-header.scrolled {
            background: rgba(5, 5, 15, 0.85);
            border-bottom: 2px solid transparent;
            border-image: linear-gradient(90deg, #ffe200, #ff6b6b, #3b82f6, #a855f7) 1;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .school-name {
            font-size: 1.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, #ffe200);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: 0.5px;
        }
        .app-sidebar__toggle {
            background: rgba(255,255,255,0.12);
            border-radius: 14px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            color: #ffe200;
            cursor: pointer;
            font-size: 1.5rem;
            text-decoration: none;
        }
        .app-sidebar__toggle:hover {
            background: rgba(255,226,0,0.25);
            transform: scale(1.05) rotate(90deg);
        }
        .header-center {
            text-align: center;
            flex: 1;
            color: white;
            pointer-events: none;
            padding: 0 10px;
        }
        .greeting-text {
            font-size: 1rem;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .greeting-name {
            font-weight: 700;
            color: #ffe200;
        }
        .app-nav {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .app-nav__item {
            padding: 8px 16px;
            border-radius: 40px;
            background: rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            cursor: pointer;
            text-decoration: none;
            backdrop-filter: blur(8px);
            transition: 0.25s;
            font-weight: 500;
        }
        .app-nav__item:hover {
            background: rgba(255,226,0,0.2);
            transform: translateY(-2px);
            color: #ffe200;
        }
        .theme-label {
            font-size: 0.9rem;
        }

        /* Dropdowns (unchanged) */
        .theme-menu, .notification-dropdown-menu, .user-menu {
            max-height: 85vh;
            overflow-y: auto;
            background: rgba(10, 10, 20, 0.98);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255,226,0,0.3);
            border-radius: 24px;
            margin-top: 12px;
            box-shadow: 0 20px 35px -12px rgba(0,0,0,0.5);
        }
        .dropdown-item, .dropdown-header, .dropdown-header .badge, .user-info strong, .user-info small {
            color: white !important;
        }
        .dropdown-item:hover {
            background: rgba(255,226,0,0.2) !important;
            color: #ffe200 !important;
        }
        .dropdown-divider {
            border-top-color: rgba(255,255,255,0.1);
        }
        .text-muted {
            color: rgba(255,255,255,0.6) !important;
        }

        .theme-menu {
            width: 560px;
        }
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(2,1fr);
            gap: 14px;
            padding: 20px;
        }
        .theme-option {
            cursor: pointer;
            border-radius: 16px;
            overflow: hidden;
            background: rgba(255,255,255,0.05);
            transition: all 0.3s;
            border: 1px solid transparent;
        }
        .theme-option:hover {
            transform: translateY(-4px);
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,226,0,0.4);
        }
        .theme-option.active {
            border: 2px solid #ffe200;
            background: rgba(255,226,0,0.15);
        }
        .theme-preview {
            height: 110px;
            position: relative;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        /* Preview styles (unchanged) */
        .preview-1 { background: linear-gradient(135deg,#1a3c2c,#0d2818); }
        .preview-2 { background: #0a0a2a; }
        .preview-3 { background: linear-gradient(135deg,#0b2b3b,#1a4a6f); }
        .preview-4 { background: linear-gradient(135deg,#1a2980,#26d0ce); }
        .preview-5 { background: #0a0a0a; }
        .preview-6 { background: #0f172a; }
        .preview-7 { background: linear-gradient(to bottom, #0b0b2b, #1b2735); }
        .preview-8 { background: linear-gradient(315deg,#65005e,#3c84ce,#30eee2,#ff1919); }
        .preview-9 { background: url('https://i.pinimg.com/originals/44/6e/3b/446e3b79395a287ca32f7977dd83b290.jpg') center/cover; }
        .preview-10 { background: #0b0b2b; }
        .preview-11 { background: radial-gradient(#333,#000); }
        .preview-12 { background: linear-gradient(180deg,#04fafd,#119dff,#030423); }
        .preview-13 { background: linear-gradient(315deg,#65005e,#3c84ce,#30eee2,#ff1919); }
        .preview-14 { background: black; }
        .preview-15 { background: url('https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=400&q=80') center/cover; }
        .preview-16 { background: url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&q=80') center/cover; }
        .preview-17 { background: url('https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?w=400&q=80') center/cover; }
        .preview-18 { background: url('https://images.unsplash.com/photo-1522383225653-ed111181a6b1?w=400&q=80') center/cover; }
        .preview-19 { background: url('https://images.unsplash.com/photo-1502086223501-7ea6ecd79368?w=400&q=80') center/cover; }
        .preview-20 { background: url('https://images.unsplash.com/photo-1503788311183-fa3bf9c4f32b?w=400&q=80') center/cover; }
        .preview-21 { background: url('https://images.unsplash.com/photo-1511497584788-876760111969?w=400&q=80') center/cover; }
        .preview-22 { background: url('https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80') center/cover; }
        .preview-23 { background: url('https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=400&q=80') center/cover; }
        .preview-24 { background: url('https://images.unsplash.com/photo-1545569341-9eb8b30979d9?w=400&q=80') center/cover; }
        .preview-25 { background: url('https://images.unsplash.com/photo-1500673922987-e212871fec22?w=400&q=80') center/cover; }
        .preview-26 { background: url('https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=400&q=80') center/cover; }
        .preview-27 { background: url('https://images.unsplash.com/photo-1502082553048-f009c37129b9?w=400&q=80') center/cover; }
        .preview-28 { background: url('https://images.unsplash.com/photo-1448375240586-882707db888b?w=400&q=80') center/cover; }
        .preview-29 { background: url('https://images.unsplash.com/photo-1501854140801-50d01698950b?w=400&q=80') center/cover; }
        .preview-30 { background: url('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=400&q=80') center/cover; }

        [class^="preview-"]::after {
            content: "🌿";
            position: absolute;
            bottom: 6px;
            right: 6px;
            font-size: 22px;
            text-shadow: 0 0 3px black;
        }
        .preview-16::after { content: "🌲☀️"; }
        .preview-17::after { content: "🍁"; }
        .preview-18::after { content: "🌸"; }
        .preview-19::after { content: "🌿"; }
        .preview-20::after { content: "🌾"; }
        .preview-21::after { content: "🌼"; }
        .preview-22::after { content: "❄️🌲"; }
        .preview-23::after { content: "🍂"; }
        .preview-24::after { content: "🎋"; }
        .preview-25::after { content: "💜🌾"; }
        .preview-26::after { content: "🌹"; }
        .preview-27::after { content: "🏞️"; }
        .preview-28::after { content: "🌺"; }
        .preview-29::after { content: "🌫️🌲"; }
        .preview-30::after { content: "✨🌳"; }

        .theme-info {
            padding: 8px;
            text-align: center;
            color: white;
        }
        .theme-info strong {
            font-size: 0.85rem;
            display: block;
        }
        .theme-info small {
            font-size: 0.65rem;
            opacity: 0.7;
        }

        /* ---------- STARRY NIGHT (theme‑14) ---------- */
        .background-container {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            pointer-events: none;
            z-index: -2;
        }
        .stars {
            background: black url(https://s3-us-west-2.amazonaws.com/s.cdpn.io/1231630/stars.png) repeat;
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            right: 0;
            display: block;
            z-index: 0;
        }
        .twinkling {
            width: 10000px;
            height: 100%;
            background: transparent url("https://s3-us-west-2.amazonaws.com/s.cdpn.io/1231630/twinkling.png") repeat;
            background-size: 1000px 1000px;
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            z-index: 2;
            animation: move-background 70s linear infinite;
        }
        .clouds {
            width: 10000px;
            height: 100%;
            background: transparent url("https://s3-us-west-2.amazonaws.com/s.cdpn.io/1231630/clouds_repeat.png") repeat;
            background-size: 1000px 1000px;
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            z-index: 3;
            animation: move-background 150s linear infinite;
        }
        .background-container img {
            height: 70vh;
            width: auto;
            position: absolute;
            z-index: 4;
            right: 20px;
            bottom: 20px;
            pointer-events: none;
        }
        @keyframes move-background {
            from { transform: translate3d(0px, 0px, 0px); }
            to { transform: translate3d(1000px, 0px, 0px); }
        }

        /* ---------- FALLING LEAVES (theme‑11) ---------- */
        .leaves-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }
        .leaf {
            position: absolute;
            display: inline-block;
            width: 80px;
            height: 80px;
            background: url("http://premiumcoding.com/CSSTricks/fallingLeaves/leaf.png") no-repeat center;
            background-size: contain;
            animation: fall 10s linear infinite;
        }
        @keyframes fall {
            0% { transform: translate(0, -10vh) rotate(0deg); opacity: 1; }
            75% { transform: translate(100px, 80vh) rotate(270deg); opacity: 1; }
            100% { transform: translate(150px, 100vh) rotate(360deg); opacity: 0; }
        }
        .leaf:nth-child(5n+5) { animation-delay: 1.3s; }
        .leaf:nth-child(3n+2) { animation-delay: 1.5s; }
        .leaf:nth-child(2n+5) { animation-delay: 1.7s; }
        .leaf:nth-child(3n+10) { animation-delay: 2.7s; }
        .leaf:nth-child(7n+2) { animation-delay: 3.5s; }
        .leaf:nth-child(4n+5) { animation-delay: 5.5s; }
        .leaf:nth-child(3n+7) { animation-delay: 8s; }

        /* ---------- BUBBLE WONDER (theme‑12) – sliding panels ---------- */
        .bubble-wonder-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            background-color: #2f2f2f;
        }
        .bubble-wonder-container .main {
            height: 100vh;
            width: 100vw;
            position: relative;
        }
        .bubble-wonder-container .d1 {
            position: absolute;
            background-image: url(https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80);
            background-size: 2700px 1500px;
            height: 30vh;
            width: 15vw;
            background-position: 0 50%;
            box-shadow: 0px 0px 25px rgba(0, 0, 0, 0.8);
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            animation: dd1 10s 1, dd12 10s 1;
            animation-delay: 4s, 14s;
        }
        .bubble-wonder-container .d2 {
            position: absolute;
            background-image: url(https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80);
            background-size: 2700px 1500px;
            height: 50vh;
            width: 25vw;
            background-position: -10vw 50%;
            left: 10vw;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
            animation: dd2 10s 2;
            animation-delay: 4s;
        }
        .bubble-wonder-container .d3 {
            position: absolute;
            background-image: url(https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80);
            background-size: 2700px 1500px;
            overflow: hidden;
            height: 100vh;
            width: 40vw;
            left: 25vw;
            box-shadow: 0px 0px 25px rgba(0, 0, 0, 0.8);
            background-position: -35vw 50%;
            top: 50%;
            transform: translateY(-50%);
            z-index: 3;
            animation: dd3 10s 2;
            animation-delay: 4s;
        }
        .bubble-wonder-container .d4 {
            position: absolute;
            overflow: hidden;
            background-image: url(https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80);
            background-size: 2700px 1500px;
            height: 80vh;
            width: 25vw;
            left: 60vw;
            background-position: -70vw 50%;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
            animation: dd4 10s 2;
            animation-delay: 4s;
        }
        @keyframes dd1 { 0% {} 50% { width: 95vw; } }
        @keyframes dd12 { 0% {} 50% { background-position: Calc(0vw - 40px) 50%; } }
        @keyframes dd2 { 0% {} 50% { background-position: Calc(-10vw - 40px) 50%; } }
        @keyframes dd3 { 0% {} 50% { background-position: Calc(-35vw - 40px) 50%; } }
        @keyframes dd4 { 0% {} 50% { background-position: Calc(-70vw - 40px) 50%; } }

        .theme-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        .theme-15-container .fog-layer {
            position: fixed;
            top: 0;
            left: 0;
            width: 200%;
            height: 100%;
            background: repeating-linear-gradient(90deg, rgba(255,255,240,0.08) 0px, rgba(255,255,240,0.08) 300px, transparent 300px, transparent 600px);
            pointer-events: none;
            animation: fogMove 20s linear infinite;
            z-index: 0;
        }
        @keyframes fogMove {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .theme-16-container .light-ray {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(ellipse at 30% 0%, rgba(255,240,150,0.12) 0%, transparent 70%);
            pointer-events: none;
            animation: swayLight 12s infinite alternate;
            z-index: 0;
        }
        @keyframes swayLight {
            0% { opacity: 0.3; transform: translateX(-2%); }
            100% { opacity: 0.7; transform: translateX(2%); }
        }

        /* Notification bell */
        .bell-wrapper {
            position: relative;
            cursor: pointer;
        }
        .notification-bell i {
            color: #ffe200;
            font-size: 1.3rem;
            transition: transform 0.2s;
            display: inline-block;
        }
        .notification-bell:hover i {
            animation: bellRing 0.6s ease both;
        }
        .shake-bell {
            animation: shakeEffect 0.5s ease-in-out !important;
        }
        @keyframes shakeEffect {
            0% { transform: translate(0,0) rotate(0deg); }
            20% { transform: translate(-2px,0) rotate(-10deg); }
            40% { transform: translate(2px,0) rotate(10deg); }
            60% { transform: translate(-1px,0) rotate(-5deg); }
            80% { transform: translate(1px,0) rotate(5deg); }
            100% { transform: translate(0,0) rotate(0deg); }
        }
        @keyframes bellRing {
            0% { transform: rotate(0); }
            20% { transform: rotate(15deg); }
            40% { transform: rotate(-12deg); }
            60% { transform: rotate(8deg); }
            80% { transform: rotate(-4deg); }
            100% { transform: rotate(0); }
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background: #ef476f;
            color: white;
            border-radius: 20px;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: bold;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 4px rgba(0,0,0,0.3);
        }
        .notification-badge.pulse {
            animation: pulseBadge 1s infinite;
        }
        @keyframes pulseBadge {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,71,111,0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(239,71,111,0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,71,111,0); }
        }

        /* User avatar – only icon, no text */
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            overflow: hidden;
            background: rgba(255,255,255,0.2);
            border: 2px solid #ffe200;
            position: relative;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .user-avatar:hover {
            transform: scale(1.05);
            border-color: white;
        }
        .user-avatar-placeholder i {
            font-size: 1.5rem;
            color: #ffe200;
        }
        .user-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .online-dot {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 10px;
            height: 10px;
            background: #4ade80;
            border-radius: 50%;
            border: 1px solid white;
            box-shadow: 0 0 2px black;
        }
        .user-chevron {
            color: #ffe200;
            transition: transform 0.3s;
            font-size: 0.9rem;
            margin-left: 4px;
        }
        .user-dropdown:hover .user-chevron {
            transform: rotate(180deg);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .app-header {
                padding: 0.5rem 1rem;
                height: 70px;
                gap: 8px;
            }
            .school-name {
                display: none;
            }
            .header-center {
                padding: 0 5px;
            }
            .greeting-text {
                font-size: 0.8rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .app-nav {
                gap: 6px;
            }
            .app-nav__item {
                padding: 6px 10px;
            }
            @media (max-width: 480px) {
                .theme-label {
                    display: none;
                }
                .app-nav__item {
                    padding: 6px 8px;
                }
            }
            .theme-menu {
                width: 340px;
            }
            .theme-grid {
                grid-template-columns: 1fr;
            }
            .background-container img {
                height: 40vh;
                right: 10px;
                bottom: 10px;
            }
        }
        .ripple {
            position: relative;
            overflow: hidden;
        }
        .ripple:after {
            content: "";
            display: block;
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
            background-repeat: no-repeat;
            background-position: 50%;
            transform: scale(10,10);
            opacity: 0;
            transition: transform .3s, opacity .5s;
        }
        .ripple:active:after {
            transform: scale(0,0);
            opacity: .3;
            transition: 0s;
        }
    </style>
</head>
<body class="theme-<?php echo $saved_theme; ?>">
    <!-- Theme containers (static) – identical to student header -->
    <div class="theme-container theme-1-container" data-theme="theme-1"></div>
    <div class="theme-container theme-2-container" data-theme="theme-2" style="display:none;"></div>
    <div class="theme-container theme-3-container" data-theme="theme-3" style="display:none;"></div>
    <div class="theme-container theme-4-container" data-theme="theme-4" style="display:none;"></div>
    <div class="theme-container theme-5-container" data-theme="theme-5" style="display:none;"></div>
    <div class="theme-container theme-6-container" data-theme="theme-6" style="display:none;"><canvas id="bubbleCanvasTheme"></canvas></div>
    <div class="theme-container theme-7-container" data-theme="theme-7" style="display:none;"></div>
    <div class="theme-container theme-8-container" data-theme="theme-8" style="display:none;"></div>
    <div class="theme-container theme-9-container" data-theme="theme-9" style="display:none;"></div>
    <div class="theme-container theme-10-container" data-theme="theme-10" style="display:none;"></div>
    <div class="theme-container theme-11-container" data-theme="theme-11" style="display:none;"><div class="leaves-container"></div></div>
    <div class="theme-container theme-12-container" data-theme="theme-12" style="display:none;"><div class="bubble-wonder-container"><div class="main"><div class="d1"></div><div class="d2"></div><div class="d3"></div><div class="d4"></div></div></div></div>
    <div class="theme-container theme-13-container" data-theme="theme-13" style="display:none;"></div>
    <div class="theme-container theme-14-container" data-theme="theme-14" style="display:none;"><div class="background-container"><img src="https://s3-us-west-2.amazonaws.com/s.cdpn.io/1231630/moon2.png" alt=""><div class="stars"></div><div class="twinkling"></div><div class="clouds"></div></div></div>
    <div class="theme-container theme-15-container" data-theme="theme-15" style="display:none;"><div class="fog-layer"></div></div>
    <div class="theme-container theme-16-container" data-theme="theme-16" style="display:none;"><div class="light-ray"></div></div>
    <div class="theme-container theme-17-container" data-theme="theme-17" style="display:none;"></div>
    <div class="theme-container theme-18-container" data-theme="theme-18" style="display:none;"></div>
    <div class="theme-container theme-19-container" data-theme="theme-19" style="display:none;"></div>
    <div class="theme-container theme-20-container" data-theme="theme-20" style="display:none;"></div>
    <div class="theme-container theme-21-container" data-theme="theme-21" style="display:none;"></div>
    <div class="theme-container theme-22-container" data-theme="theme-22" style="display:none;"></div>
    <div class="theme-container theme-23-container" data-theme="theme-23" style="display:none;"></div>
    <div class="theme-container theme-24-container" data-theme="theme-24" style="display:none;"></div>
    <div class="theme-container theme-25-container" data-theme="theme-25" style="display:none;"></div>
    <div class="theme-container theme-26-container" data-theme="theme-26" style="display:none;"></div>
    <div class="theme-container theme-27-container" data-theme="theme-27" style="display:none;"></div>
    <div class="theme-container theme-28-container" data-theme="theme-28" style="display:none;"></div>
    <div class="theme-container theme-29-container" data-theme="theme-29" style="display:none;"></div>
    <div class="theme-container theme-30-container" data-theme="theme-30" style="display:none;"></div>

    <!-- HEADER (admin version, no logo icon, no term line) -->
    <header class="app-header">
        <div class="header-left">
            <div class="school-name"><?php echo htmlspecialchars(substr($school_name, 0, 25)); ?></div>
            <a class="app-sidebar__toggle" href="#" id="sidebarToggle" aria-label="Toggle Sidebar">
                <i class="bi bi-grid-3x3-gap-fill"></i>
            </a>
        </div>

        <div class="header-center">
            <div class="greeting-text">
                Hello, <span class="greeting-name"><?php echo htmlspecialchars(explode(' ', trim($staff['fname']))[0]); ?></span>
            </div>
        </div>

        <ul class="app-nav">
            <!-- Theme Selector (unchanged) -->
            <li class="dropdown theme-dropdown">
                <a class="app-nav__item theme-selector-btn" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-palette-fill"></i>
                    <span class="theme-label" id="themeButtonLabel">Themes</span>
                    <i class="bi bi-chevron-down"></i>
                </a>
                <div class="dropdown-menu theme-menu dropdown-menu-end">
                    <div class="dropdown-header"><i class="bi bi-palette"></i> Choose Theme (30)</div>
                    <div class="dropdown-divider"></div>
                    <div class="theme-grid">
                        <div class="theme-option" data-theme="theme-1" data-name="Mystical Forest"><div class="theme-preview preview-1"></div><div class="theme-info"><strong>Mystical Forest</strong><small>Floating lights</small></div></div>
                        <div class="theme-option" data-theme="theme-2" data-name="Glow Particles"><div class="theme-preview preview-2"></div><div class="theme-info"><strong>Glow Particles</strong><small>Rotating orbs</small></div></div>
                        <div class="theme-option" data-theme="theme-3" data-name="Fluid Up"><div class="theme-preview preview-3"></div><div class="theme-info"><strong>Fluid Up</strong><small>Rising bubbles</small></div></div>
                        <div class="theme-option" data-theme="theme-4" data-name="Animated Waves"><div class="theme-preview preview-4"></div><div class="theme-info"><strong>Animated Waves</strong><small>Flowing water</small></div></div>
                        <div class="theme-option" data-theme="theme-5" data-name="Neon Bulbs"><div class="theme-preview preview-5"></div><div class="theme-info"><strong>Neon Bulbs</strong><small>Glowing lights</small></div></div>
                        <div class="theme-option" data-theme="theme-6" data-name="3D Bubbles"><div class="theme-preview preview-6"></div><div class="theme-info"><strong>3D Bubbles</strong><small>Glossy spheres</small></div></div>
                        <div class="theme-option" data-theme="theme-7" data-name="Stars & Shooting Stars"><div class="theme-preview preview-7"></div><div class="theme-info"><strong>Stars & Shooting Stars</strong><small>Twinkling sky</small></div></div>
                        <div class="theme-option" data-theme="theme-8" data-name="Rainbow Gradient"><div class="theme-preview preview-8"></div><div class="theme-info"><strong>Rainbow Gradient</strong><small>Shifting colors</small></div></div>
                        <div class="theme-option" data-theme="theme-9" data-name="Fireflies"><div class="theme-preview preview-9"></div><div class="theme-info"><strong>Fireflies</strong><small>Floating flies</small></div></div>
                        <div class="theme-option" data-theme="theme-10" data-name="Cosmic Stars"><div class="theme-preview preview-10"></div><div class="theme-info"><strong>Cosmic Stars</strong><small>Shooting stars</small></div></div>
                        <div class="theme-option" data-theme="theme-11" data-name="Falling Leaves"><div class="theme-preview preview-11"></div><div class="theme-info"><strong>Falling Leaves</strong><small>Autumn magic</small></div></div>
                        <div class="theme-option" data-theme="theme-12" data-name="Bubble Wonder"><div class="theme-preview preview-12"></div><div class="theme-info"><strong>Bubble Wonder</strong><small>Floating bubbles</small></div></div>
                        <div class="theme-option" data-theme="theme-13" data-name="Ocean Waves"><div class="theme-preview preview-13"></div><div class="theme-info"><strong>Ocean Waves</strong><small>Gentle waves</small></div></div>
                        <div class="theme-option" data-theme="theme-14" data-name="Starry Night"><div class="theme-preview preview-14"></div><div class="theme-info"><strong>Starry Night</strong><small>Moon & clouds</small></div></div>
                        <div class="theme-option" data-theme="theme-15" data-name="Misty Pine Forest"><div class="theme-preview preview-15"></div><div class="theme-info"><strong>Misty Pine Forest</strong><small>Drifting fog & pines</small></div></div>
                        <div class="theme-option" data-theme="theme-16" data-name="Sunlit Redwoods"><div class="theme-preview preview-16"></div><div class="theme-info"><strong>Sunlit Redwoods</strong><small>God rays & giants</small></div></div>
                        <div class="theme-option" data-theme="theme-17" data-name="Autumn Maple Trail"><div class="theme-preview preview-17"></div><div class="theme-info"><strong>Autumn Maple Trail</strong><small>Crisp golden leaves</small></div></div>
                        <div class="theme-option" data-theme="theme-18" data-name="Cherry Blossom Path"><div class="theme-preview preview-18"></div><div class="theme-info"><strong>Cherry Blossom Path</strong><small>Floating pink petals</small></div></div>
                        <div class="theme-option" data-theme="theme-19" data-name="Mossy Ancient Woods"><div class="theme-preview preview-19"></div><div class="theme-info"><strong>Mossy Ancient Woods</strong><small>Deep green tranquility</small></div></div>
                        <div class="theme-option" data-theme="theme-20" data-name="Birch Grove Serenity"><div class="theme-preview preview-20"></div><div class="theme-info"><strong>Birch Grove Serenity</strong><small>Bright & calm</small></div></div>
                        <div class="theme-option" data-theme="theme-21" data-name="Wildflower Meadow"><div class="theme-preview preview-21"></div><div class="theme-info"><strong>Wildflower Meadow</strong><small>Colorful blossoms</small></div></div>
                        <div class="theme-option" data-theme="theme-22" data-name="Snowy Pine Forest"><div class="theme-preview preview-22"></div><div class="theme-info"><strong>Snowy Pine Forest</strong><small>Winter wonderland</small></div></div>
                        <div class="theme-option" data-theme="theme-23" data-name="Riverside Autumn"><div class="theme-preview preview-23"></div><div class="theme-info"><strong>Riverside Autumn</strong><small>Reflections & leaves</small></div></div>
                        <div class="theme-option" data-theme="theme-24" data-name="Bamboo Grove"><div class="theme-preview preview-24"></div><div class="theme-info"><strong>Bamboo Grove</strong><small>Zen & serenity</small></div></div>
                        <div class="theme-option" data-theme="theme-25" data-name="Lavender Dreams"><div class="theme-preview preview-25"></div><div class="theme-info"><strong>Lavender Dreams</strong><small>Purple haze</small></div></div>
                        <div class="theme-option" data-theme="theme-26" data-name="Rose Garden"><div class="theme-preview preview-26"></div><div class="theme-info"><strong>Rose Garden</strong><small>Romantic reds</small></div></div>
                        <div class="theme-option" data-theme="theme-27" data-name="Alpine Meadow"><div class="theme-preview preview-27"></div><div class="theme-info"><strong>Alpine Meadow</strong><small>Mountain flowers</small></div></div>
                        <div class="theme-option" data-theme="theme-28" data-name="Rainforest Canopy"><div class="theme-preview preview-28"></div><div class="theme-info"><strong>Rainforest Canopy</strong><small>Lush & exotic</small></div></div>
                        <div class="theme-option" data-theme="theme-29" data-name="Foggy Valley"><div class="theme-preview preview-29"></div><div class="theme-info"><strong>Foggy Valley</strong><small>Mystical mist</small></div></div>
                        <div class="theme-option" data-theme="theme-30" data-name="Enchanted Forest"><div class="theme-preview preview-30"></div><div class="theme-info"><strong>Enchanted Forest</strong><small>Magical glade</small></div></div>
                    </div>
                </div>
            </li>

            <!-- Notifications Dropdown -->
            <li class="dropdown notification-dropdown">
                <a class="app-nav__item notification-bell" href="#" data-bs-toggle="dropdown">
                    <div class="bell-wrapper">
                        <i class="bi bi-bell-fill" id="bellIcon"></i>
                        <span id="notificationBadge" class="notification-badge <?php echo $notification_count > 0 ? 'pulse' : ''; ?>" style="display: <?php echo $notification_count > 0 ? 'flex' : 'none'; ?>;">
                            <?php echo $notification_count > 0 ? ($notification_count > 99 ? '99+' : $notification_count) : '0'; ?>
                        </span>
                    </div>
                </a>
                <div class="dropdown-menu notification-dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header"><i class="bi bi-bell-fill"></i> Notifications <span class="badge bg-warning text-dark"><?php echo $notification_count; ?> New</span></div>
                    <div class="dropdown-divider"></div>
                    <?php if (empty($latest_announcements)): ?>
                        <div class="dropdown-item text-center text-muted">No new announcements</div>
                    <?php else: ?>
                        <?php foreach ($latest_announcements as $ann): ?>
                            <a class="dropdown-item" href="admin/announcements">
                                <i class="bi bi-megaphone-fill text-primary"></i>
                                <div class="notification-content">
                                    <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                                    <small><?php echo date('M d, H:i', strtotime($ann['create_date'])); ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-center" href="admin/announcements">View All <i class="bi bi-arrow-right"></i></a>
                    <?php endif; ?>
                </div>
            </li>

            <!-- User Dropdown – admin version -->
            <li class="dropdown user-dropdown">
                <a class="app-nav__item" href="#" data-bs-toggle="dropdown" style="gap: 4px;">
                    <div class="user-avatar">
                        <?php if (!empty($staff['display_image']) && $staff['display_image'] !== 'DEFAULT' && file_exists('../images/staff/' . $staff['display_image'])): ?>
                            <img src="images/staff/<?php echo htmlspecialchars($staff['display_image']); ?>" class="user-avatar-img">
                        <?php else: ?>
                            <div class="user-avatar-placeholder"><i class="bi bi-person-fill"></i></div>
                        <?php endif; ?>
                        <div class="online-dot"></div>
                    </div>
                    <i class="bi bi-chevron-down user-chevron"></i>
                </a>
                <ul class="dropdown-menu user-menu dropdown-menu-end">
                    <li class="dropdown-header"><div class="user-info"><strong><?php echo htmlspecialchars($staff['fname'] . ' ' . ($staff['lname'] ?? '')); ?></strong><small><?php echo htmlspecialchars($staff['id']); ?></small></div></li>
                    <li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person-circle"></i> My Profile</a></li>
                    <li><a class="dropdown-item" href="admin/profile"><i class="bi bi-shield-lock"></i> Security Settings</a></li>
                    <li><a class="dropdown-item" href="admin/system_settings"><i class="bi bi-gear"></i> System Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </li>
        </ul>
    </header>

    <script>
        // Ripple effect
        document.querySelectorAll('.app-nav__item').forEach(el => el.classList.add('ripple'));

        // Scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.app-header');
            if (window.scrollY > 50) header.classList.add('scrolled');
            else header.classList.remove('scrolled');
        });

        // ========== SIDEBAR TOGGLE – AUTO COLLAPSE ON MOBILE ==========
        function getSidebarElement() {
            return document.querySelector('.app-sidebar, .sidebar, #sidebar');
        }

        function getSidebarOverlay() {
            return document.querySelector('.app-sidebar__overlay, .sidebar-overlay');
        }

        function syncSidebarDisplay() {
            const sidebar = getSidebarElement();
            const overlay = getSidebarOverlay();
            const isHidden = document.body.classList.contains('sidebar-hidden');
            
            if (sidebar) {
                sidebar.style.display = isHidden ? 'none' : '';
            }
            if (overlay) {
                overlay.style.display = isHidden ? 'none' : '';
            }
        }

        function setSidebarState(collapsed) {
            if (collapsed) {
                document.body.classList.add('sidebar-hidden');
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                document.body.classList.remove('sidebar-hidden');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
            syncSidebarDisplay();
        }

        function initSidebarState() {
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                setSidebarState(true);
            } else {
                const saved = localStorage.getItem('sidebarCollapsed');
                if (saved !== null) {
                    setSidebarState(saved === 'true');
                } else {
                    setSidebarState(false);
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            initSidebarState();
            const toggleBtn = document.getElementById('sidebarToggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const currentlyHidden = document.body.classList.contains('sidebar-hidden');
                    setSidebarState(!currentlyHidden);
                });
            }
            setTimeout(syncSidebarDisplay, 100);
        });

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    syncSidebarDisplay();
                }
            });
        });
        observer.observe(document.body, { attributes: true });

        // NOTIFICATION BELL SHAKE
        const bellIcon = document.getElementById('bellIcon');
        if (bellIcon) {
            setInterval(() => {
                bellIcon.classList.add('shake-bell');
                setTimeout(() => {
                    bellIcon.classList.remove('shake-bell');
                }, 500);
            }, 2000);
        }

        // ========== THEME EFFECTS ENGINE ==========
        const themeNames = {
            'theme-1': 'Mystical Forest', 'theme-2': 'Glow Particles', 'theme-3': 'Fluid Up', 'theme-4': 'Animated Waves',
            'theme-5': 'Neon Bulbs', 'theme-6': '3D Bubbles', 'theme-7': 'Stars & Shooting Stars', 'theme-8': 'Rainbow Gradient',
            'theme-9': 'Fireflies', 'theme-10': 'Cosmic Stars', 'theme-11': 'Falling Leaves', 'theme-12': 'Bubble Wonder',
            'theme-13': 'Ocean Waves', 'theme-14': 'Starry Night', 'theme-15': 'Misty Pine Forest', 'theme-16': 'Sunlit Redwoods',
            'theme-17': 'Autumn Maple Trail', 'theme-18': 'Cherry Blossom Path', 'theme-19': 'Mossy Ancient Woods',
            'theme-20': 'Birch Grove Serenity', 'theme-21': 'Wildflower Meadow', 'theme-22': 'Snowy Pine Forest',
            'theme-23': 'Riverside Autumn', 'theme-24': 'Bamboo Grove', 'theme-25': 'Lavender Dreams', 'theme-26': 'Rose Garden',
            'theme-27': 'Alpine Meadow', 'theme-28': 'Rainforest Canopy', 'theme-29': 'Foggy Valley', 'theme-30': 'Enchanted Forest'
        };

        function updateThemeButtonLabel(themeId) {
            const btnLabel = document.getElementById('themeButtonLabel');
            if (btnLabel && themeNames[themeId]) {
                btnLabel.textContent = themeNames[themeId];
            } else if (btnLabel) {
                btnLabel.textContent = 'Themes';
            }
        }

        function createFallingLeaves() {
            const container = document.querySelector('.theme-11-container .leaves-container');
            if (!container || container._leavesCreated) return;
            for (let i = 0; i < 30; i++) {
                const leaf = document.createElement('div');
                leaf.classList.add('leaf');
                leaf.style.left = Math.random() * 100 + '%';
                leaf.style.animationDuration = 8 + Math.random() * 6 + 's';
                leaf.style.animationDelay = Math.random() * 10 + 's';
                container.appendChild(leaf);
            }
            container._leavesCreated = true;
        }

        function applyTheme(themeId) {
            let themes = [];
            for (let i = 1; i <= 30; i++) themes.push('theme-'+i);
            document.body.classList.remove(...themes);
            document.body.classList.add(themeId);
            document.querySelectorAll('.theme-container').forEach(c => c.style.display = 'none');
            let selected = document.querySelector(`.${themeId}-container`);
            if (selected) selected.style.display = 'block';
            localStorage.setItem('site_theme', themeId);
            document.querySelectorAll('.theme-option').forEach(opt => {
                if (opt.getAttribute('data-theme') === themeId) opt.classList.add('active');
                else opt.classList.remove('active');
            });
            updateThemeButtonLabel(themeId);
            if (themeId === 'theme-6') initBubbleCanvas();
            if (themeId === 'theme-12') {} // already handled by container visibility
            if (themeId === 'theme-18') initCherryBlossomPetals();
            if (themeId === 'theme-11') createFallingLeaves();
        }

        function initBubbleCanvas() {
            let canvas = document.getElementById('bubbleCanvasTheme');
            if (!canvas || canvas._bubbleAnim) return;
            let ctx = canvas.getContext('2d');
            let width = window.innerWidth, height = window.innerHeight;
            let bubbles = [], time = 0;
            function resize() { width = window.innerWidth; height = window.innerHeight; canvas.width = width; canvas.height = height; }
            window.addEventListener('resize', resize);
            resize();
            class Bubble {
                constructor() {
                    this.x = Math.random() * width;
                    this.y = Math.random() * height;
                    this.r = Math.random() * 35 + 15;
                    this.vx = (Math.random() - 0.5) * 0.08;
                    this.vy = (Math.random() - 0.5) * 0.08;
                    this.hue = Math.random() * 360;
                    this.op = Math.random() * 0.25 + 0.15;
                }
                update(t) {
                    this.x += this.vx + Math.sin(t * 0.01 + this.hue) * 0.1;
                    this.y += this.vy + Math.cos(t * 0.007 + this.hue) * 0.1;
                    if (this.x + this.r < 0) this.x = width + this.r;
                    if (this.x - this.r > width) this.x = -this.r;
                    if (this.y + this.r < 0) this.y = height + this.r;
                    if (this.y - this.r > height) this.y = -this.r;
                }
                draw() {
                    ctx.save();
                    ctx.beginPath();
                    let grad = ctx.createRadialGradient(this.x - this.r*0.35, this.y - this.r*0.35, this.r*0.2, this.x, this.y, this.r);
                    grad.addColorStop(0, `hsla(${this.hue}, 85%, 75%, ${this.op})`);
                    grad.addColorStop(0.5, `hsla(${this.hue}, 80%, 65%, ${this.op*0.9})`);
                    grad.addColorStop(1, `hsla(${this.hue}, 70%, 45%, ${this.op*0.5})`);
                    ctx.fillStyle = grad;
                    ctx.arc(this.x, this.y, this.r, 0, Math.PI*2);
                    ctx.fill();
                    ctx.beginPath();
                    ctx.arc(this.x - this.r*0.25, this.y - this.r*0.25, this.r*0.25, 0, Math.PI*2);
                    ctx.fillStyle = `rgba(255,255,255,${this.op*0.9})`;
                    ctx.fill();
                    ctx.restore();
                }
            }
            function animate() {
                if (!ctx) return;
                ctx.clearRect(0, 0, width, height);
                time += 0.02;
                bubbles.forEach(b => { b.update(time); b.draw(); });
                requestAnimationFrame(animate);
            }
            for (let i = 0; i < 35; i++) bubbles.push(new Bubble());
            animate();
            canvas._bubbleAnim = true;
        }

        function initCherryBlossomPetals() {
            let container = document.querySelector('.theme-18-container');
            if (!container || container._petalsDone) return;
            for (let i = 0; i < 40; i++) {
                let petal = document.createElement('div');
                petal.classList.add('petal');
                let size = 6 + Math.random() * 10;
                petal.style.width = size + 'px';
                petal.style.height = (size * 1.2) + 'px';
                petal.style.left = Math.random() * 100 + '%';
                petal.style.animationDuration = 8 + Math.random() * 12 + 's';
                petal.style.animationDelay = Math.random() * 15 + 's';
                container.appendChild(petal);
            }
            container._petalsDone = true;
        }

        // Theme switching
        (function() {
            let currentTheme = localStorage.getItem('site_theme') || 'theme-1';
            applyTheme(currentTheme);
            
            document.querySelectorAll('.theme-option').forEach(opt => {
                opt.addEventListener('click', function(e) {
                    e.preventDefault();
                    let tid = this.getAttribute('data-theme');
                    applyTheme(tid);
                    let dd = this.closest('.dropdown-menu');
                    if (dd && dd.previousElementSibling) {
                        let bs = bootstrap.Dropdown.getInstance(dd.previousElementSibling);
                        if (bs) bs.hide();
                    }
                });
            });
        })();

        // Notification badge updater
        function updateNotificationBadge() {
            fetch('admin/ajax/get_notification_count', { method:'GET', headers:{'X-Requested-With':'XMLHttpRequest'} })
                .then(r=>r.json()).then(d=>{
                    let badge = document.getElementById('notificationBadge');
                    if(badge && d.count!==undefined) {
                        let oldCount = parseInt(badge.innerText) || 0;
                        if(d.count > oldCount && oldCount > 0) {
                            if(bellIcon) bellIcon.classList.add('shake-bell');
                            setTimeout(() => { if(bellIcon) bellIcon.classList.remove('shake-bell'); }, 500);
                        }
                        if(d.count>0) {
                            badge.style.display='flex';
                            badge.innerText = d.count>99 ? '99+' : d.count;
                            badge.classList.add('pulse');
                        } else {
                            badge.style.display='none';
                            badge.classList.remove('pulse');
                        }
                    }
                }).catch(e=>console.log);
        }
        setInterval(updateNotificationBadge, 30000);
        updateNotificationBadge();
    </script>
</body>
</html>