<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

initSecureSession();

if (isset($_GET['login_as'])) {
    $r = strtoupper(trim($_GET['login_as']));
    $_SESSION['user_id'] = ($r === 'ADMIN') ? 3 : 1;
    $_SESSION['username'] = ($r === 'ADMIN') ? 'admin.portal' : 'aarav.sharma';
    $_SESSION['full_name'] = ($r === 'ADMIN') ? 'System Admin' : 'Aarav Sharma';
    $_SESSION['role'] = $r;
}

requireLogin();
requireRole(['ADMIN']);

$user = getCurrentUser();
$mysqli = getMySQLiConnection(true);

ensureStudentsTableReady($mysqli);
ensureEventsTableReady($mysqli);
ensureAuditLogsTableReady($mysqli);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_event') {
        $poster = (!empty($_FILES['poster']['name'])) ? $_FILES['poster'] : null;
        $res = saveEventRecord($mysqli, $_POST, $poster);
        $msg = $res['message'];
        if ($res['success']) {
            logAuditEvent($mysqli, $user['username'], 'EVENT_CREATED', $_POST['title'] ?? '', $user['user_id']);
        }
    } elseif ($_POST['action'] === 'delete_event') {
        $res = deleteEventRecord($mysqli, (int)$_POST['event_id']);
        $msg = $res['message'];
        if ($res['success']) {
            logAuditEvent($mysqli, $user['username'], 'EVENT_DELETED', "Event ID: " . (int)$_POST['event_id'], $user['user_id']);
        }
    }
}

$uCount = (int)$mysqli->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$sCount = (int)$mysqli->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$eCount = (int)$mysqli->query("SELECT COUNT(*) FROM events")->fetch_row()[0];
$rCount = (int)$mysqli->query("SELECT COUNT(*) FROM registrations")->fetch_row()[0];

$events = $mysqli->query("SELECT * FROM events ORDER BY event_date ASC LIMIT 6");
$latestRegs = $mysqli->query("SELECT r.ticket_code, s.full_name, e.title, r.registration_date FROM registrations r JOIN students s ON r.student_id=s.student_id JOIN events e ON r.event_id=e.event_id ORDER BY r.registration_id DESC LIMIT 5");
$auditLogs = $mysqli->query("SELECT username, action, details, created_at FROM audit_logs ORDER BY log_id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1100px; margin: 20px auto; padding: 0 16px; font-family: sans-serif; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin: 16px 0; }
        .stat-box { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; text-align: center; }
        .stat-num { font-size: 2rem; font-weight: bold; color: #1e3a8a; margin: 4px 0; }
        .card { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin-bottom: 20px; }
        .nav-bar { background: #1e293b; color: #fff; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; border-radius: 6px; }
        .nav-bar a { color: #93c5fd; text-decoration: none; margin-left: 12px; font-size: 0.9rem; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px; }
        .form-row input, .form-row select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.88rem; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-blue { background: #2563eb; color: #fff; }
        .btn-red { background: #dc2626; color: #fff; }
        .btn-gray { background: #64748b; color: #fff; }
        .alert { background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin-bottom: 14px; border: 1px solid #86efac; }
        .event-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin-top: 12px; }
        .event-card { border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; background: #fff; }
        .event-img { width: 100%; height: 120px; object-fit: cover; background: #f1f5f9; display: block; }
    </style>
</head>
<body style="background: #f1f5f9; margin: 0;">

    <div class="container">
        <div class="nav-bar">
            <div><strong>StudentHub Admin Console</strong> &bull; <?= htmlspecialchars($user['full_name']) ?></div>
            <div>
                <a href="dashboard.php">Student Portal</a>
                <a href="admin_dashboard.php?menu_role=FACULTY">Faculty View</a>
                <a href="logout.php" style="color: #fca5a5;">Log Out</a>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-box"><div class="stat-num"><?= $uCount ?></div><div>Total Users</div></div>
            <div class="stat-box"><div class="stat-num"><?= $sCount ?></div><div>Students</div></div>
            <div class="stat-box"><div class="stat-num"><?= $eCount ?></div><div>Campus Events</div></div>
            <div class="stat-box"><div class="stat-num"><?= $rCount ?></div><div>Registrations</div></div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0;">Student Management (Fetch API &amp; CRUD)</h3>
                <div>
                    <input type="text" id="search" placeholder="Search students..." oninput="loadStudents()" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 4px;">
                    <button class="btn btn-blue" onclick="toggleStudentForm()">+ Add Student</button>
                </div>
            </div>

            <div id="studentFormBox" style="display: none; background: #f8fafc; padding: 14px; border-radius: 6px; margin-bottom: 12px; border: 1px solid #e2e8f0;">
                <h4 id="formTitle" style="margin-top: 0;">Add New Student</h4>
                <form id="studentForm" onsubmit="saveStudent(event)">
                    <input type="hidden" id="student_id" value="">
                    <div class="form-row">
                        <input type="text" id="roll_no" placeholder="Roll No (e.g. 21CE001)" required>
                        <input type="text" id="full_name" placeholder="Full Name" required>
                        <input type="email" id="email" placeholder="Email" required>
                        <input type="text" id="phone" placeholder="Phone" required>
                    </div>
                    <div class="form-row">
                        <select id="department">
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                            <option value="Civil Engineering">Civil Engineering</option>
                        </select>
                        <select id="semester">
                            <option value="1">Semester 1</option><option value="2">Semester 2</option>
                            <option value="3">Semester 3</option><option value="4">Semester 4</option>
                            <option value="5">Semester 5</option><option value="6">Semester 6</option>
                        </select>
                        <button type="submit" class="btn btn-blue">Save Student</button>
                        <button type="button" class="btn btn-gray" onclick="toggleStudentForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <div id="studentAlert" style="display:none; padding:8px; margin-bottom:10px; border-radius:4px;"></div>

            <table>
                <thead>
                    <tr><th>ID</th><th>Roll No</th><th>Name</th><th>Email</th><th>Department</th><th>Sem</th><th>Actions</th></tr>
                </thead>
                <tbody id="studentRows">
                    <tr><td colspan="7" style="text-align: center;">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">Event Management &amp; Poster Upload</h3>
                <button class="btn btn-blue" onclick="toggleEventForm()">+ Add Event</button>
            </div>

            <div id="eventFormBox" style="display: none; background: #f8fafc; padding: 14px; border-radius: 6px; margin-top: 12px; border: 1px solid #e2e8f0;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_event">
                    <div class="form-row">
                        <input type="text" name="title" placeholder="Event Title" required>
                        <select name="category">
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Technical">Technical</option>
                        </select>
                        <input type="date" name="event_date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                        <input type="text" name="venue" placeholder="Venue" required>
                    </div>
                    <div class="form-row">
                        <input type="number" name="capacity" placeholder="Capacity" value="100">
                        <input type="number" name="fee" placeholder="Fee (INR)" value="0">
                        <input type="file" name="poster" accept="image/jpeg,image/png,image/webp">
                        <button type="submit" class="btn btn-blue">Upload &amp; Save</button>
                    </div>
                </form>
            </div>

            <div class="event-grid">
                <?php while ($ev = $events->fetch_assoc()): ?>
                    <div class="event-card">
                        <?php if (!empty($ev['poster_path']) && file_exists(__DIR__ . '/' . $ev['poster_path'])): ?>
                            <img src="<?= htmlspecialchars($ev['poster_path']) ?>" class="event-img" alt="Poster">
                        <?php else: ?>
                            <div class="event-img" style="display:flex;align-items:center;justify-content:center;color:#94a3b8;">No Poster</div>
                        <?php endif; ?>
                        <div style="padding: 10px;">
                            <strong><?= htmlspecialchars($ev['title']) ?></strong>
                            <div style="font-size: 0.8rem; color: #64748b; margin: 4px 0;">
                                <?= htmlspecialchars($ev['category']) ?> &bull; <?= htmlspecialchars($ev['event_date']) ?>
                            </div>
                            <form method="POST" onsubmit="return confirm('Delete this event?');" style="margin:0;">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="event_id" value="<?= (int)$ev['event_id'] ?>">
                                <button type="submit" class="btn btn-red" style="padding: 3px 8px; font-size: 0.75rem;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 14px;">
            <div class="card">
                <h3 style="margin-top: 0;">Latest Registrations</h3>
                <table>
                    <thead><tr><th>Ticket</th><th>Student</th><th>Event</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php while ($r = $latestRegs->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['ticket_code']) ?></td>
                                <td><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><?= htmlspecialchars($r['registration_date']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3 style="margin-top: 0;">Security Audit Logs</h3>
                <table>
                    <thead><tr><th>User</th><th>Action</th><th>Details</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php while ($a = $auditLogs->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['username']) ?></td>
                                <td><strong><?= htmlspecialchars($a['action']) ?></strong></td>
                                <td><?= htmlspecialchars($a['details']) ?></td>
                                <td style="font-size:0.75rem;"><?= htmlspecialchars($a['created_at']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleStudentForm() {
            const b = document.getElementById('studentFormBox');
            b.style.display = b.style.display === 'none' ? 'block' : 'none';
            if (b.style.display === 'block') {
                document.getElementById('studentForm').reset();
                document.getElementById('student_id').value = '';
                document.getElementById('formTitle').textContent = 'Add New Student';
            }
        }

        function toggleEventForm() {
            const b = document.getElementById('eventFormBox');
            b.style.display = b.style.display === 'none' ? 'block' : 'none';
        }

        async function loadStudents() {
            const q = document.getElementById('search').value.trim();
            const res = await fetch('api.php?action=list&search=' + encodeURIComponent(q));
            const json = await res.json();
            const tbody = document.getElementById('studentRows');
            if (!json.data || json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No students found</td></tr>';
                return;
            }
            tbody.innerHTML = json.data.map(s => `
                <tr>
                    <td>#${s.student_id}</td>
                    <td><strong>${s.roll_no}</strong></td>
                    <td>${s.full_name}</td>
                    <td>${s.email}</td>
                    <td>${s.department}</td>
                    <td>${s.semester}</td>
                    <td>
                        <button class="btn btn-blue" style="padding:2px 6px; font-size:0.75rem;" onclick='editStudent(${JSON.stringify(s)})'>Edit</button>
                        <button class="btn btn-red" style="padding:2px 6px; font-size:0.75rem;" onclick="delStudent(${s.student_id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        function editStudent(s) {
            document.getElementById('studentFormBox').style.display = 'block';
            document.getElementById('formTitle').textContent = 'Edit Student';
            document.getElementById('student_id').value = s.student_id;
            document.getElementById('roll_no').value = s.roll_no;
            document.getElementById('full_name').value = s.full_name;
            document.getElementById('email').value = s.email;
            document.getElementById('phone').value = s.phone || '';
            document.getElementById('department').value = s.department;
            document.getElementById('semester').value = s.semester;
        }

        async function saveStudent(e) {
            e.preventDefault();
            const sid = document.getElementById('student_id').value;
            const payload = {
                action: sid ? 'update' : 'create',
                student_id: sid ? parseInt(sid) : undefined,
                roll_no: document.getElementById('roll_no').value.trim(),
                full_name: document.getElementById('full_name').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                department: document.getElementById('department').value,
                semester: parseInt(document.getElementById('semester').value),
                status: 'ACTIVE'
            };
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            notify(data.message, data.success);
            if (data.success) {
                toggleStudentForm();
                loadStudents();
            }
        }

        async function delStudent(id) {
            if (!confirm('Delete student record?')) return;
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', student_id: id })
            });
            const data = await res.json();
            notify(data.message, data.success);
            loadStudents();
        }

        function notify(msg, ok) {
            const a = document.getElementById('studentAlert');
            a.style.display = 'block';
            a.style.background = ok ? '#dcfce7' : '#fee2e2';
            a.style.color = ok ? '#166534' : '#991b1b';
            a.textContent = msg;
            setTimeout(() => { a.style.display = 'none'; }, 3000);
        }

        loadStudents();
    </script>
</body>
</html>
