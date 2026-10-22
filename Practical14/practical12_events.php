<?php


require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

initSecureSession();
$mysqli = getMySQLiConnection(true);

if ($mysqli) {
    ensureEventsTableReady($mysqli);
}

$message = '';
$msgType = 'ok';
$editEvent = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = (int)($_POST['event_id'] ?? 0);
    $res = deleteEventRecord($mysqli, $delId);
    $message = $res['message'];
    $msgType = $res['success'] ? 'ok' : 'err';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $eventId = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    $posterFile = isset($_FILES['poster']) && !empty($_FILES['poster']['name']) ? $_FILES['poster'] : null;

    $res = saveEventRecord($mysqli, $_POST, $posterFile, $eventId);
    $message = $res['message'];
    $msgType = $res['success'] ? 'ok' : 'err';
}


if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editEvent = getEventRecord($mysqli, (int)$_GET['edit']);
}


$searchParam   = trim($_GET['search'] ?? '');
$categoryParam = trim($_GET['category'] ?? '');
$statusParam   = strtoupper(trim($_GET['status'] ?? ''));
$pageParam     = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 8;

$filters = [
    'search'   => $searchParam,
    'category' => $categoryParam,
    'status'   => $statusParam
];

$eventData = [
    'data'        => [],
    'total'       => 0,
    'page'        => 1,
    'per_page'    => $perPage,
    'total_pages' => 1
];

if ($mysqli) {
    $eventData = fetchEventsList($mysqli, $filters, $pageParam, $perPage, 'event_date', 'ASC');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 12: Event Management CRUD &amp; Poster Upload</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1100px; margin: 26px auto; padding: 0 16px; }
        .card { background: #fff; padding: 22px; border-radius: var(--radius); border: 1px solid var(--line); box-shadow: var(--shadow); margin-bottom: 22px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 14px; }
        .form-grid label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 0.88rem; }
        .form-grid input, .form-grid select, .form-grid textarea { width: 100%; max-width: 100%; padding: 8px; box-sizing: border-box; }
        .banner-ok { background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; border: 1px solid #86efac; margin-bottom: 18px; }
        .banner-err { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; border: 1px solid #fca5a5; margin-bottom: 18px; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 16px; }
        .filter-bar input, .filter-bar select { padding: 8px; border: 1px solid var(--line); border-radius: 6px; }
        .event-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; }
        .event-card { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
        .poster-thumb { width: 100%; height: 140px; object-fit: cover; background: #f1f5f9; display: block; }
        .event-body { padding: 14px; flex: 1; display: flex; flex-direction: column; }
        .event-tag { font-size: 0.75rem; text-transform: uppercase; font-weight: bold; color: var(--blue); }
        .event-title { margin: 6px 0; font-size: 1.05rem; font-weight: bold; }
        .event-meta { font-size: 0.82rem; color: var(--ink-soft); margin-bottom: 10px; line-height: 1.4; }
        .action-links { margin-top: auto; display: flex; gap: 8px; }
        .btn-sm { padding: 4px 10px; font-size: 0.82rem; border-radius: 4px; text-decoration: none; display: inline-block; }
        .btn-edit { background: #e0e7ff; color: #3730a3; }
        .btn-del { background: #fee2e2; color: #991b1b; border: none; cursor: pointer; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 18px; align-items: center; }
        .pagination a, .pagination span { padding: 6px 14px; border: 1px solid var(--line); border-radius: 6px; text-decoration: none; color: var(--ink); }
        .pagination .active { background: var(--blue); color: #fff; border-color: var(--blue); }
    </style>
</head>
<body>

    <h1>StudentHub Portal</h1>

    <div style="text-align: center; padding: 10px; background: #fff; border-bottom: 1px solid var(--line);">
        <nav style="display: inline-flex; gap: 14px; justify-content: center;">
            <a href="index.html">Portal Home</a> |
            <a href="practical12_test.php">Practical 12 Test Suite &rarr;</a> |
            <a href="practical11_students.php">Practical 11 Students</a> |
            <a href="login.php">Login</a>
        </nav>
    </div>

    <div class="container">

        <?php if ($message): ?>
            <div class="<?= $msgType === 'ok' ? 'banner-ok' : 'banner-err' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        
        <div class="card">
            <h2 style="margin-top: 0; text-align: left; background: none; color: var(--navy); padding: 0;">
                <?= $editEvent ? 'Edit Event &amp; Poster' : 'Add New Event (with Poster Upload)' ?>
            </h2>

            <form method="POST" action="practical12_events.php" enctype="multipart/form-data" style="border:none; box-shadow:none; padding:0; max-width:100%;">
                <input type="hidden" name="action" value="save">
                <?php if ($editEvent): ?>
                    <input type="hidden" name="event_id" value="<?= $editEvent['event_id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div>
                        <label for="event_code">Event Code *</label>
                        <input type="text" id="event_code" name="event_code" value="<?= htmlspecialchars($editEvent['event_code'] ?? '') ?>" placeholder="e.g. EVT-AI-2026" required>
                    </div>
                    <div>
                        <label for="title">Event Title *</label>
                        <input type="text" id="title" name="title" value="<?= htmlspecialchars($editEvent['title'] ?? '') ?>" placeholder="e.g. NextGen AI Summit" required>
                    </div>
                    <div>
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <?php 
                            $cats = ['Technical', 'Workshop', 'Cultural', 'Sports', 'Seminar'];
                            foreach ($cats as $c): ?>
                                <option value="<?= $c ?>" <?= (($editEvent['category'] ?? '') === $c) ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="event_date">Event Date &amp; Time *</label>
                        <input type="datetime-local" id="event_date" name="event_date" value="<?= !empty($editEvent['event_date']) ? date('Y-m-d\TH:i', strtotime($editEvent['event_date'])) : '' ?>" required>
                    </div>
                    <div>
                        <label for="venue">Venue *</label>
                        <input type="text" id="venue" name="venue" value="<?= htmlspecialchars($editEvent['venue'] ?? '') ?>" placeholder="Auditorium Hall A" required>
                    </div>
                    <div>
                        <label for="max_capacity">Max Capacity</label>
                        <input type="number" id="max_capacity" name="max_capacity" min="1" max="5000" value="<?= htmlspecialchars($editEvent['max_capacity'] ?? 50) ?>">
                    </div>
                    <div>
                        <label for="poster">Event Poster Image (JPG/PNG/WEBP &le; 2MB)</label>
                        <input type="file" id="poster" name="poster" accept=".jpg,.jpeg,.png,.webp">
                        <?php if (!empty($editEvent['poster_image'])): ?>
                            <small>Current: <?= htmlspecialchars($editEvent['poster_image']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <?php foreach (['UPCOMING', 'ONGOING', 'COMPLETED', 'CANCELLED'] as $st): ?>
                                <option value="<?= $st ?>" <?= (($editEvent['status'] ?? 'UPCOMING') === $st) ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <label for="description" style="display:block; margin-bottom:4px; font-weight:600; font-size:0.88rem;">Description</label>
                    <textarea id="description" name="description" rows="2" placeholder="Brief event description..."><?= htmlspecialchars($editEvent['description'] ?? '') ?></textarea>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit"><?= $editEvent ? 'Update Event &amp; Poster' : 'Create Event' ?></button>
                    <?php if ($editEvent): ?>
                        <a href="practical12_events.php" style="background:#e2e8f0; color:#1e293b; padding:10px 18px; border-radius:999px; text-decoration:none; font-weight:600;">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        
        <div class="card">
            <h2 style="margin-top: 0; text-align: left; background: none; color: var(--navy); padding: 0;">
                Events Catalog (Total: <?= $eventData['total'] ?>)
            </h2>

            
            <form method="GET" action="practical12_events.php" class="filter-bar" style="border:none; box-shadow:none; padding:0; max-width:100%;">
                <input type="text" name="search" value="<?= htmlspecialchars($searchParam) ?>" placeholder="Search title, venue, code...">
                
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= $c ?>" <?= ($categoryParam === $c) ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (['UPCOMING', 'ONGOING', 'COMPLETED', 'CANCELLED'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($statusParam === $st) ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" style="padding:8px 16px;">Filter Events</button>
                <a href="practical12_events.php" style="padding:8px 12px; font-size:0.88rem; text-decoration:none;">Reset</a>
            </form>

            
            <div class="event-grid">
                <?php if (!empty($eventData['data'])): ?>
                    <?php foreach ($eventData['data'] as $e): ?>
                        <div class="event-card">
                            <?php if (!empty($e['poster_image']) && file_exists(__DIR__ . '/uploads/posters/' . $e['poster_image'])): ?>
                                <img src="uploads/posters/<?= htmlspecialchars($e['poster_image']) ?>" alt="Poster" class="poster-thumb">
                            <?php else: ?>
                                <div class="poster-thumb" style="display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:0.85rem;">
                                    &#128444; No Poster Uploaded
                                </div>
                            <?php endif; ?>
                            <div class="event-body">
                                <span class="event-tag"><?= htmlspecialchars($e['category']) ?> &bull; <?= htmlspecialchars($e['status']) ?></span>
                                <div class="event-title"><?= htmlspecialchars($e['title']) ?></div>
                                <div class="event-meta">
                                    <strong>Code:</strong> <?= htmlspecialchars($e['event_code']) ?><br>
                                    <strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($e['event_date'])) ?><br>
                                    <strong>Venue:</strong> <?= htmlspecialchars($e['venue']) ?> (Cap: <?= $e['max_capacity'] ?>)
                                </div>
                                <div class="action-links">
                                    <a href="practical12_events.php?edit=<?= $e['event_id'] ?>" class="btn-sm btn-edit">Edit</a>
                                    <form method="POST" action="practical12_events.php" style="display:inline; border:none; box-shadow:none; padding:0;" onsubmit="return confirm('Delete event <?= htmlspecialchars($e['title']) ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="event_id" value="<?= $e['event_id'] ?>">
                                        <button type="submit" class="btn-sm btn-del">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="grid-column: 1 / -1; text-align: center; color: var(--ink-soft);">No matching events found.</p>
                <?php endif; ?>
            </div>

            
            <?php if ($eventData['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($eventData['page'] > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $eventData['page'] - 1])) ?>">&laquo; Prev</a>
                    <?php endif; ?>

                    <span>Page <?= $eventData['page'] ?> of <?= $eventData['total_pages'] ?></span>

                    <?php if ($eventData['page'] < $eventData['total_pages']): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $eventData['page'] + 1])) ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
