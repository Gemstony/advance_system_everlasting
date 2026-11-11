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

// -------------------------------------------------------------------
// Ensure the events table exists
// -------------------------------------------------------------------
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tbl_events` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `description` text,
        `event_date` date NOT NULL,
        `event_time` time DEFAULT NULL,
        `location` varchar(255) DEFAULT NULL,
        `audience` enum('teachers','students','both') NOT NULL DEFAULT 'teachers',
        `posted_by` int(11) NOT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `posted_by` (`posted_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// -------------------------------------------------------------------
// AJAX Handlers – placed before any HTML output
// -------------------------------------------------------------------
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($_POST['action'] === 'add_event') {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $event_date = $_POST['event_date'];
            $event_time = $_POST['event_time'] ?: null;
            $location = trim($_POST['location']);
            $audience = $_POST['audience'];
            $posted_by = (int)$_POST['posted_by'];

            if (empty($title) || empty($event_date)) {
                throw new Exception('Title and event date are required.');
            }

            $stmt = $pdo->prepare("INSERT INTO tbl_events (title, description, event_date, event_time, location, audience, posted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $description, $event_date, $event_time, $location, $audience, $posted_by]);
            echo json_encode(['success' => true, 'message' => 'Event added successfully']);
        }
        elseif ($_POST['action'] === 'update_event') {
            $id = (int)$_POST['id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $event_date = $_POST['event_date'];
            $event_time = $_POST['event_time'] ?: null;
            $location = trim($_POST['location']);
            $audience = $_POST['audience'];

            if (empty($title) || empty($event_date)) {
                throw new Exception('Title and event date are required.');
            }

            $stmt = $pdo->prepare("UPDATE tbl_events SET title=?, description=?, event_date=?, event_time=?, location=?, audience=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$title, $description, $event_date, $event_time, $location, $audience, $id]);
            echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
        }
        elseif ($_POST['action'] === 'delete_event') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM tbl_events WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Event deleted']);
        }
        else {
            throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle GET request for single event (used by edit modal)
if (isset($_GET['get_event'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_GET['get_event'];
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $stmt = $pdo->prepare("SELECT * FROM tbl_events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($event) {
            echo json_encode(['success' => true, 'event' => $event]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Event not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------------
// Normal Page Display – fetch all events
// -------------------------------------------------------------------
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT e.*, CONCAT(s.fname, ' ', s.lname) AS poster_name
        FROM tbl_events e
        LEFT JOIN tbl_staff s ON e.posted_by = s.id
        ORDER BY e.event_date DESC, e.created_at DESC
    ");
    $stmt->execute();
    $events = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Events Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            box-shadow: 0 20px 35px -8px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.3);
        }
        .event-title { font-weight: 600; }
        .badge-audience { font-size: 0.7rem; padding: 0.3rem 0.6rem; border-radius: 20px; }
        .badge-teachers { background: #4361ee; color: white; }
        .badge-students { background: #06d6a0; color: white; }
        .badge-both { background: #ffb703; color: #212529; }
    </style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">SRMS</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="academic/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php require 'inc/sidebar_academic.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-calendar-event me-2"></i> Event Management</h1></div>
        <div class="text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal" id="addEventBtn"><i class="bi bi-plus-circle"></i> Add New Event</button>
        </div>
    </div>

    <div class="tile glass-card p-4">
        <div class="tile-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="eventsTable">
                    <thead>
                        32<th>Title</th><th>Date</th><th>Time</th><th>Location</th><th>Audience</th><th>Posted By</th><th>Posted On</th><th>Actions</th>                </thead>
                    <tbody>
                        <?php foreach ($events as $event):
                            $date = date('d/m/Y', strtotime($event['event_date']));
                            $time = $event['event_time'] ? date('H:i', strtotime($event['event_time'])) : '-';
                            $audienceClass = '';
                            $audienceText = '';
                            switch ($event['audience']) {
                                case 'teachers': $audienceClass = 'badge-teachers'; $audienceText = 'Teachers'; break;
                                case 'students': $audienceClass = 'badge-students'; $audienceText = 'Students'; break;
                                case 'both': $audienceClass = 'badge-both'; $audienceText = 'Both'; break;
                            }
                            $created = date('d/m/Y H:i', strtotime($event['created_at']));
                        ?>
                        <tr data-id="<?php echo $event['id']; ?>">
                            <td class="event-title"><?php echo htmlspecialchars($event['title']); ?></td>
                            <td><?php echo $date; ?></td>
                            <td><?php echo $time; ?></td>
                            <td><?php echo htmlspecialchars($event['location']); ?></td>
                            <td><span class="badge-audience <?php echo $audienceClass; ?>"><?php echo $audienceText; ?></span></td>
                            <td><?php echo htmlspecialchars($event['poster_name']); ?></td>
                            <td><?php echo $created; ?></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-primary edit-event" data-id="<?php echo $event['id']; ?>"><i class="bi bi-pencil"></i> Edit</button>
                                <button class="btn btn-sm btn-danger delete-event" data-id="<?php echo $event['id']; ?>"><i class="bi bi-trash"></i> Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal for Add/Edit Event -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel">Add New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="eventForm">
                        <input type="hidden" name="id" id="eventId">
                        <div class="mb-3">
                            <label for="eventTitle" class="form-label">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="eventTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="eventDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="eventDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="eventDate" class="form-label">Event Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="eventDate" name="event_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="eventTime" class="form-label">Event Time</label>
                                <input type="time" class="form-control" id="eventTime" name="event_time">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="eventLocation" class="form-label">Location</label>
                            <input type="text" class="form-control" id="eventLocation" name="location">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Audience</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="audience" id="audienceTeachers" value="teachers" checked>
                                    <label class="form-check-label" for="audienceTeachers">Teachers only</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="audience" id="audienceStudents" value="students">
                                    <label class="form-check-label" for="audienceStudents">Students only</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="audience" id="audienceBoth" value="both">
                                    <label class="form-check-label" for="audienceBoth">Both</label>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="posted_by" value="<?php echo $id; ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEventBtn">Save Event</button>
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
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function(){
    var table = $('#eventsTable').DataTable({
        pageLength: 25,
        lengthMenu: [10,25,50,100],
        order: [[1, 'desc']],
        columnDefs: [{ orderable: false, targets: [7] }]
    });

    // Add new event button – clear form
    $('#addEventBtn').on('click', function() {
        $('#eventForm')[0].reset();
        $('#eventId').val('');
        $('#eventModalLabel').text('Add New Event');
        $('input[name="audience"][value="teachers"]').prop('checked', true);
    });

    // Edit event button – load data via AJAX
    $(document).on('click', '.edit-event', function() {
        var id = $(this).data('id');
        $.ajax({
            url: '',
            method: 'GET',
            data: { get_event: id },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#eventId').val(res.event.id);
                    $('#eventTitle').val(res.event.title);
                    $('#eventDescription').val(res.event.description);
                    $('#eventDate').val(res.event.event_date);
                    $('#eventTime').val(res.event.event_time);
                    $('#eventLocation').val(res.event.location);
                    $('input[name="audience"][value="' + res.event.audience + '"]').prop('checked', true);
                    $('#eventModalLabel').text('Edit Event');
                    $('#eventModal').modal('show');
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                Swal.fire('AJAX Error', error + '\n' + xhr.responseText, 'error');
            }
        });
    });

    // Save event (add or update)
    $('#saveEventBtn').on('click', function() {
        var formData = $('#eventForm').serialize();
        var id = $('#eventId').val();
        var action = id ? 'update_event' : 'add_event';
        formData += '&action=' + action;

        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                $('#saveEventBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Saving...');
            },
            success: function(res) {
                if (res.success) {
                    $('#eventModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                Swal.fire('AJAX Error', error + '\n' + xhr.responseText, 'error');
            },
            complete: function() {
                $('#saveEventBtn').prop('disabled', false).html('Save Event');
            }
        });
    });

    // Delete event
    $(document).on('click', '.delete-event', function() {
        var id = $(this).data('id');
        var $row = $(this).closest('tr');
        Swal.fire({
            title: 'Are you sure?',
            text: "This event will be permanently deleted.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: { action: 'delete_event', id: id },
                    dataType: 'json',
                    beforeSend: function() {
                        $row.find('.delete-event, .edit-event').prop('disabled', true);
                    },
                    success: function(res) {
                        if (res.success) {
                            table.row($row).remove().draw();
                            Swal.fire('Deleted!', res.message, 'success');
                        } else {
                            Swal.fire('Error', res.error, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('AJAX Error', error + '\n' + xhr.responseText, 'error');
                    },
                    complete: function() {
                        $row.find('.delete-event, .edit-event').prop('disabled', false);
                    }
                });
            }
        });
    });
});
</script>
</body>
</html>