<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Academic only (level 1)
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>SRMS - Announcements</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link href="summernote/summernote-lite.min.css" rel="stylesheet">
    <script src="summernote/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
    <script src="summernote/summernote-lite.min.js"></script>
    <script src="summernote/summernote-fontawesome.js"></script>
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <style>
        /* same as before */
        .app-content { background:transparent !important; }
        .tile { background:rgba(255,255,255,0.85); backdrop-filter:blur(12px); border-radius:28px; box-shadow:0 8px 20px rgba(0,0,0,0.08); border:1px solid rgba(255,255,255,0.6); transition:transform 0.25s ease, box-shadow 0.25s ease; padding:1.5rem; }
        .tile:hover { transform:translateY(-3px); box-shadow:0 15px 30px rgba(0,0,0,0.12); background:rgba(255,255,255,0.95); }
        .tile-title { font-weight:800; color:#1e293b; margin-bottom:1rem; font-size:1.2rem; border-bottom:2px solid rgba(67,97,238,0.3); padding-bottom:0.5rem; display:inline-block; }
        #srmsTable { width:100%; border-collapse:separate; border-spacing:0; }
        #srmsTable thead th { background:rgba(241,245,249,0.7); backdrop-filter:blur(4px); font-weight:700; font-size:0.85rem; border-bottom:2px solid #4361ee; color:#1e293b; padding:1rem 0.75rem; }
        #srmsTable tbody tr:hover { background:rgba(0,0,0,0.02); }
        #srmsTable td { vertical-align:middle; padding:0.9rem 0.75rem; border-bottom:1px solid rgba(0,0,0,0.05); color:#1e293b; }
        .btn-sm, .btn { border-radius:40px; padding:0.4rem 1.2rem; font-weight:600; font-size:0.85rem; transition:all 0.2s ease; margin:0 2px; border:none; box-shadow:0 2px 6px rgba(0,0,0,0.05); }
        .btn-sm:hover, .btn:hover { transform:translateY(-2px); box-shadow:0 8px 15px -6px rgba(0,0,0,0.15); }
        .btn-primary { background:linear-gradient(135deg,#4361ee,#1e40af); color:white; }
        .btn-danger { background:linear-gradient(135deg,#ef476f,#dc2626); color:white; }
        .modal-content { background:rgba(255,255,255,0.98); backdrop-filter:blur(16px); border:1px solid rgba(255,255,255,0.5); border-radius:28px; color:#1e293b; }
        .modal-header { border-bottom:1px solid rgba(0,0,0,0.08); padding:1.2rem 1.5rem; }
        .modal-title { font-weight:800; color:#1e293b; }
        .modal-body { padding:1.5rem; }
        .form-label { font-weight:700; margin-bottom:0.5rem; color:#1e293b; }
        .form-control, .form-select { background:white; border:1px solid rgba(0,0,0,0.1); border-radius:16px; padding:0.7rem 1rem; color:#1e293b; }
        .form-control:focus, .form-select:focus { border-color:#4361ee; box-shadow:0 0 0 3px rgba(67,97,238,0.2); }
        .btn-close { filter:none; }
        .note-editor.note-frame { border-radius:16px; border:1px solid rgba(0,0,0,0.1); }
        .app-title h1 { font-weight:800; color:white; text-shadow:0 1px 2px black; }
        .breadcrumb { background:rgba(0,0,0,0.3); backdrop-filter:blur(8px); border-radius:40px; padding:0.5rem 1rem; }
        .breadcrumb-item a, .breadcrumb-item.active { color:white !important; }
    </style>
</head>
<body>

<?php require 'inc/header_academic.php'; ?>
<?php require 'inc/sidebar_academic.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-megaphone me-2"></i> Announcements</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addModal">Add</button></li>
        </ul>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/new_announcement">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input required type="text" name="title" class="form-control txt-cap" placeholder="Enter Announcement Title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Audience</label>
                            <select class="form-select" name="audience" required>
                                <option selected disabled value="">Select one</option>
                                <option value="1">Students Only</option>
                                <option value="0">Teachers Only</option>
                                <option value="2">Students & Teachers</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Announcement</label>
                            <textarea name="announcement" id="summernote" required></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit" value="1" class="btn btn-primary">Add</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="ajax_callback">
                    <!-- dynamic -->
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <div class="table-responsive">
                        <h3 class="tile-title">Announcements</h3>
                        <table class="table table-hover" id="srmsTable">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Audience</th>
                                    <th>Create Date</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $stmt = $conn->prepare("SELECT * FROM tbl_announcements ORDER BY id DESC");
                                    $stmt->execute();
                                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($result as $row) {
                                        $audience = '';
                                        switch ($row['level']) {
                                            case 0: $audience = "Teachers Only"; break;
                                            case 1: $audience = "Students Only"; break;
                                            case 2: $audience = "Teachers & Students"; break;
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td><?php echo $audience; ?></td>
                                            <td><?php echo htmlspecialchars($row['create_date']); ?></td>
                                            <td align="center">
                                                <a onclick="set_announcement('<?php echo $row['id']; ?>');" class="btn btn-primary btn-sm" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editModal">Edit</a>
                                                <a onclick="del('academic/core/drop_announcement?id=<?php echo $row['id']; ?>', 'Delete Announcement?');" class="btn btn-danger btn-sm" href="javascript:void(0);">Delete</a>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="4" class="text-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script src="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.js"></script>

<!-- ========================================================= -->
<!--  Service Worker Registration & Polling (same as login)   -->
<!-- ========================================================= -->
<script>
(function() {
    'use strict';

    // --- Service Worker & Push Subscription ---
    const VAPID_PUBLIC_KEY = 'YOUR_VAPID_PUBLIC_KEY'; // <-- same as login

    async function registerServiceWorker() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                console.log('[SW] Registered on announcement page');

                const existingSubscription = await registration.pushManager.getSubscription();
                if (existingSubscription) {
                    // Optionally update server
                    sendSubscriptionToServer(existingSubscription);
                    return;
                }

                const permission = await Notification.requestPermission();
                if (permission !== 'granted') return;

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });
                sendSubscriptionToServer(subscription);

            } catch (err) {
                console.error('[SW] Error:', err);
            }
        }
    }

    function sendSubscriptionToServer(subscription) {
        const data = {
            endpoint: subscription.endpoint,
            keys: {
                auth: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth')))),
                p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh'))))
            }
        };
        $.ajax({
            url: 'core/subscribe.php',
            method: 'POST',
            data: { subscription: JSON.stringify(data) },
            dataType: 'json',
            success: function(res) {
                if (!res.success) console.warn('[SW] Subscribe failed:', res.error);
            },
            error: function() { console.error('[SW] Ajax error'); }
        });
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    $(document).ready(function() {
        registerServiceWorker();
    });

    // --- Polling for toast notifications (same as login) ---
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }
    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        document.cookie = name + "=" + value + "; expires=" + date.toUTCString() + "; path=/";
    }

    let lastNotifId = getCookie('last_notif_id') || 0;

    function checkNotifications() {
        $.ajax({
            url: 'core/get_notifications.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (!response.success || !response.notifications || response.notifications.length === 0) {
                    $('#notifBadge').text(0).hide();
                    return;
                }
                const notifs = response.notifications;
                let unseen = 0;
                let latestId = lastNotifId;
                notifs.forEach(function(n) {
                    if (n.id > lastNotifId) {
                        unseen++;
                        if (n.id > latestId) latestId = n.id;
                    }
                });
                if (unseen > 0) {
                    const latest = notifs[0];
                    // Show toast (using existing function from header, but we'll implement a simple one)
                    showToast(latest.message);
                    $('#notifBadge').text(unseen).show();
                    setCookie('last_notif_id', latestId, 7);
                    lastNotifId = latestId;
                } else {
                    $('#notifBadge').text(0).hide();
                }
            },
            error: function() {}
        });
    }

    function showToast(message) {
        // If a toast element exists in the header, use it; otherwise create one.
        let $toast = $('#notifToast');
        if (!$toast.length) {
            $toast = $(`
                <div class="notif-toast" id="notifToast">
                    <button class="btn-close-toast" id="closeToast">&times;</button>
                    <div class="toast-title"><i class="bi bi-megaphone-fill"></i> New Notification</div>
                    <div class="toast-body" id="toastMessage"></div>
                </div>
            `).appendTo('body');
            // Close button
            $('#closeToast').click(function() {
                $('#notifToast').removeClass('show');
            });
        }
        $('#toastMessage').text(message);
        $toast.addClass('show');
        setTimeout(function() {
            $toast.removeClass('show');
        }, 8000);
    }

    // Poll every 30s
    checkNotifications();
    setInterval(checkNotifications, 30000);
})();
</script>

<!-- ========================================================= -->
<!--  Existing announcement scripts (unchanged)                -->
<!-- ========================================================= -->
<script>
    $('#addModal').on('shown.bs.modal', function () {
        $('#summernote').summernote({ tabsize:2, height:120, fontNames:['Comic Sans MS'] });
    });
    $('#addModal').on('hidden.bs.modal', function () {
        $('#summernote').summernote('destroy');
    });

    $('#srmsTable').DataTable({
        "sort": false,
        "pageLength": 25,
        "language": { "search":"Filter:", "paginate": { "previous":"<i class='bi bi-chevron-left'></i>", "next":"<i class='bi bi-chevron-right'></i>" } }
    });

    function set_announcement(id) {
        $('#ajax_callback').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div> Loading...</div>');
        $.ajax({
            url: 'academic/ajax/get_announcement',
            method: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    var html = '<form class="app_frm" method="POST" action="academic/core/update_announcement">' +
                        '<input type="hidden" name="id" value="' + res.id + '">' +
                        '<div class="mb-3"><label class="form-label">Title</label><input required type="text" name="title" class="form-control" value="' + escapeHtml(res.title) + '"></div>' +
                        '<div class="mb-3"><label class="form-label">Audience</label><select class="form-select" name="audience" required>' +
                        '<option value="1"' + (res.level == 1 ? ' selected' : '') + '>Students Only</option>' +
                        '<option value="0"' + (res.level == 0 ? ' selected' : '') + '>Teachers Only</option>' +
                        '<option value="2"' + (res.level == 2 ? ' selected' : '') + '>Students & Teachers</option>' +
                        '</select></div>' +
                        '<div class="mb-3"><label class="form-label">Announcement</label><textarea name="announcement" id="editSummernote" required>' + escapeHtml(res.announcement) + '</textarea></div>' +
                        '<div class="d-flex gap-2"><button type="submit" name="submit" value="1" class="btn btn-primary">Update</button><button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button></div>' +
                        '</form>';
                    $('#ajax_callback').html(html);
                    $('#editSummernote').summernote({ tabsize:2, height:120 });
                } else {
                    $('#ajax_callback').html('<div class="alert alert-danger">' + res.error + '</div>');
                }
            },
            error: function() {
                $('#ajax_callback').html('<div class="alert alert-danger">Failed to load announcement.</div>');
            }
        });
    }

    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function del(url, msg) {
        if (confirm(msg)) window.location.href = url;
    }
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>