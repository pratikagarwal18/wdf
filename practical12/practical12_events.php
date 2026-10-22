<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

initSecureSession();

$currentUser = getCurrentUser();
$isAdmin = ($currentUser && strtoupper($currentUser['role']) === 'ADMIN');

$mysqli = getMySQLiConnection(true);
if ($mysqli) {
    ensureEventsTableReady($mysqli);
}

$successMsg = '';
$errorMsg = '';
$fieldErrors = [];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_event' && !empty($_GET['id'])) {
    header('Content-Type: application/json');
    $e = getEventRecord($mysqli, (int)$_GET['id']);
    if ($e) {
        echo json_encode(['success' => true, 'event' => $e]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
    }
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $successMsg = "Event created successfully with validated poster!";
    elseif ($_GET['msg'] === 'updated') $successMsg = "Event record and poster updated successfully!";
    elseif ($_GET['msg'] === 'deleted') $successMsg = "Event record and poster file removed from server.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $action = $_POST['form_action'];

    if ($action === 'save_event') {
        $eventId = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
        $posterFile = isset($_FILES['poster']) && !empty($_FILES['poster']['name']) ? $_FILES['poster'] : null;

        $saveResult = saveEventRecord($mysqli, $_POST, $posterFile, $eventId);

        if ($saveResult['success']) {
            $redir = $eventId ? 'updated' : 'created';
            header("Location: practical12_events.php?msg={$redir}");
            exit;
        } else {
            $errorMsg = $saveResult['message'];
            $fieldErrors = $saveResult['errors'] ?? [];
        }
    } elseif ($action === 'delete_event') {
        $delId = (int)($_POST['delete_event_id'] ?? 0);
        $delResult = deleteEventRecord($mysqli, $delId);
        if ($delResult['success']) {
            header("Location: practical12_events.php?msg=deleted");
            exit;
        } else {
            $errorMsg = $delResult['message'];
        }
    }
}

$searchQuery  = trim($_GET['search'] ?? '');
$filterCat    = trim($_GET['category'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$pageParam    = max(1, (int)($_GET['page'] ?? 1));
$viewMode     = in_array($_GET['view'] ?? '', ['table', 'grid']) ? $_GET['view'] : 'grid';

$filters = [
    'search'   => $searchQuery,
    'category' => $filterCat,
    'status'   => $filterStatus
];

$eventData = [
    'data'        => [],
    'total'       => 0,
    'page'        => 1,
    'per_page'    => 9,
    'total_pages' => 1
];
$metrics = [
    'total'          => 0,
    'upcoming'       => 0,
    'ongoing'        => 0,
    'completed'      => 0,
    'cancelled'      => 0,
    'total_capacity' => 0,
    'categories'     => []
];

if ($mysqli) {
    $eventData = fetchEventsList($mysqli, $filters, $pageParam, 9, 'event_date', 'ASC');
    $metrics = getEventSummaryMetrics($mysqli);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management CRUD with Poster Upload | Practical 12</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --p12-navy: #0f172a;
            --p12-navy-card: #1e293b;
            --p12-indigo: #4f46e5;
            --p12-indigo-hover: #4338ca;
            --p12-cyan: #06b6d4;
            --p12-bg: #f8fafc;
            --p12-card-bg: #ffffff;
            --p12-border: #e2e8f0;
            --p12-text-main: #0f172a;
            --p12-text-muted: #64748b;
        }

        body {
            background: var(--p12-bg);
            color: var(--p12-text-main);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }

        .p12-navbar {
            background: linear-gradient(135deg, #090d16 0%, #0f172a 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            padding: 16px 24px;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .p12-nav-inner {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .p12-brand {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.02em;
        }

        .p12-brand span {
            color: var(--p12-cyan);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .p12-nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .p12-nav-btn {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.86rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .p12-nav-btn-muted {
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.06);
        }

        .p12-nav-btn-muted:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.14);
        }

        .p12-nav-btn-primary {
            background: var(--p12-indigo);
            color: #ffffff;
        }

        .p12-nav-btn-primary:hover {
            background: var(--p12-indigo-hover);
        }

        .p12-container {
            max-width: 1300px;
            margin: 28px auto;
            padding: 0 20px;
        }

        .p12-hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
            border-radius: 20px;
            padding: 32px 36px;
            color: #ffffff;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(67, 56, 202, 0.25);
        }

        .p12-hero h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.95rem;
            margin: 0 0 8px 0;
            font-weight: 800;
        }

        .p12-hero p {
            margin: 0;
            color: #e0e7ff;
            font-size: 0.98rem;
            max-width: 760px;
            line-height: 1.55;
        }

        .btn-add-event {
            background: #ffffff;
            color: #312e81;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 12px 22px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-add-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            background: #f8fafc;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            background: var(--p12-card-bg);
            border: 1px solid var(--p12-border);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .metric-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .metric-val {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .metric-lbl {
            font-size: 0.8rem;
            color: var(--p12-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .p12-toolbar {
            background: var(--p12-card-bg);
            border: 1px solid var(--p12-border);
            border-radius: 16px;
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            flex: 1;
        }

        .search-input-wrap {
            position: relative;
            flex: 1;
            min-width: 240px;
        }

        .search-input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1.5px solid var(--p12-border);
            border-radius: 10px;
            font-size: 0.9rem;
            box-sizing: border-box;
            background: #ffffff;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--p12-indigo);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        .search-icon-svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        .select-filter {
            padding: 10px 14px;
            border: 1.5px solid var(--p12-border);
            border-radius: 10px;
            font-size: 0.88rem;
            background: #ffffff;
            color: var(--p12-text-main);
            font-weight: 500;
            cursor: pointer;
        }

        .select-filter:focus {
            outline: none;
            border-color: var(--p12-indigo);
        }

        .btn-filter-apply {
            background: var(--p12-indigo);
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-filter-apply:hover {
            background: var(--p12-indigo-hover);
        }

        .btn-filter-reset {
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .view-switchers {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
        }

        .view-btn {
            border: none;
            background: transparent;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            color: #475569;
            text-decoration: none;
        }

        .view-btn.active {
            background: #ffffff;
            color: var(--p12-indigo);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .alert-banner {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.92rem;
            font-weight: 500;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 22px;
            margin-bottom: 30px;
        }

        .event-card {
            background: var(--p12-card-bg);
            border: 1px solid var(--p12-border);
            border-radius: 18px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            position: relative;
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }

        .poster-container {
            position: relative;
            width: 100%;
            height: 200px;
            background: #0f172a;
            overflow: hidden;
            cursor: pointer;
        }

        .poster-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .event-card:hover .poster-img {
            transform: scale(1.04);
        }

        .poster-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #94a3b8;
            font-size: 0.88rem;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        .badge-status-chip {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            backdrop-filter: blur(8px);
        }

        .status-upcoming {
            background: rgba(37, 99, 235, 0.9);
            color: #ffffff;
        }

        .status-ongoing {
            background: rgba(16, 185, 129, 0.9);
            color: #ffffff;
        }

        .status-completed {
            background: rgba(71, 85, 105, 0.9);
            color: #ffffff;
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.9);
            color: #ffffff;
        }

        .badge-category-chip {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(15, 23, 42, 0.8);
            color: #38bdf8;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 700;
            backdrop-filter: blur(6px);
        }

        .event-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .event-code-tag {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--p12-indigo);
            margin-bottom: 6px;
            letter-spacing: 0.04em;
        }

        .event-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            color: var(--p12-text-main);
            line-height: 1.35;
        }

        .event-meta-list {
            margin: 0 0 16px 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.84rem;
            color: var(--p12-text-muted);
        }

        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .event-desc {
            font-size: 0.86rem;
            color: #475569;
            line-height: 1.5;
            margin-bottom: 18px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            border-top: 1px solid #f1f5f9;
            padding-top: 14px;
        }

        .btn-card-action {
            flex: 1;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-view {
            background: #f1f5f9;
            color: #334155;
        }

        .btn-view:hover {
            background: #e2e8f0;
        }

        .btn-edit {
            background: #eef2ff;
            color: var(--p12-indigo);
        }

        .btn-edit:hover {
            background: #e0e7ff;
        }

        .btn-delete {
            background: #fef2f2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fee2e2;
        }

        .table-wrap {
            background: #ffffff;
            border: 1px solid var(--p12-border);
            border-radius: 16px;
            overflow-x: auto;
            margin-bottom: 30px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
        }

        .p12-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            text-align: left;
        }

        .p12-table th {
            background: #f8fafc;
            color: #475569;
            padding: 14px 18px;
            font-weight: 700;
            border-bottom: 2px solid var(--p12-border);
        }

        .p12-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .p12-table tr:hover {
            background: #f8fafc;
        }

        .table-poster-thumb {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            border: 1px solid #e2e8f0;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-dialog {
            background: #ffffff;
            border-radius: 20px;
            max-width: 640px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--p12-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: #ffffff;
            z-index: 10;
        }

        .modal-header h3 {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            color: var(--p12-text-main);
        }

        .btn-modal-close {
            background: transparent;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: #94a3b8;
            padding: 4px;
            line-height: 1;
        }

        .btn-modal-close:hover {
            color: #0f172a;
        }

        .modal-body {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .form-col-full {
            grid-column: span 2;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--p12-border);
            border-radius: 10px;
            font-size: 0.9rem;
            box-sizing: border-box;
            background: #ffffff;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--p12-indigo);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }

        .form-control.has-error {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .err-msg {
            font-size: 0.78rem;
            color: #dc2626;
            margin-top: 4px;
            display: block;
            font-weight: 500;
        }

        .dropzone-box {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 22px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .dropzone-box:hover {
            border-color: var(--p12-indigo);
            background: #f1f5f9;
        }

        .poster-preview-pane {
            margin-top: 12px;
            display: none;
            align-items: center;
            gap: 14px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
        }

        .poster-preview-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }

        .modal-footer {
            padding: 18px 24px;
            border-top: 1px solid var(--p12-border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f8fafc;
            border-radius: 0 0 20px 20px;
        }

        .btn-cancel {
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit {
            background: var(--p12-indigo);
            color: #ffffff;
            border: none;
            padding: 10px 22px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        .btn-submit:hover {
            background: var(--p12-indigo-hover);
        }

        .lightbox-modal {
            max-width: 500px;
            text-align: center;
        }

        .lightbox-img {
            width: 100%;
            max-height: 480px;
            object-fit: contain;
            border-radius: 12px;
            background: #090d16;
            margin-bottom: 14px;
        }

        .p12-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 24px 0 40px 0;
        }

        .page-link-btn {
            padding: 8px 14px;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid var(--p12-border);
            color: var(--p12-text-main);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .page-link-btn.active {
            background: var(--p12-indigo);
            color: #ffffff;
            border-color: var(--p12-indigo);
        }
    </style>
</head>
<body>

    <header class="p12-navbar">
        <div class="p12-nav-inner">
            <a href="index.html" class="p12-brand">
                StudentHub <span>Event Management Portal</span>
            </a>
            <div class="p12-nav-links">
                <a href="admin_dashboard.php" class="p12-nav-btn p12-nav-btn-muted">Admin Dashboard</a>
                <a href="practical11_students.php" class="p12-nav-btn p12-nav-btn-muted">Practical 11 (Students CRUD)</a>
                <a href="practical12_test.php" class="p12-nav-btn p12-nav-btn-primary">Practical 12 Test Suite</a>
                <?php if ($currentUser): ?>
                    <span style="font-size: 0.82rem; background: rgba(255,255,255,0.12); padding: 5px 10px; border-radius: 6px;">
                        <?= htmlspecialchars($currentUser['username']) ?> (<?= htmlspecialchars($currentUser['role']) ?>)
                    </span>
                    <a href="logout.php" style="color: #f87171; text-decoration: none; font-size: 0.82rem; font-weight: 600;">Log Out</a>
                <?php else: ?>
                    <a href="login.php" class="p12-nav-btn p12-nav-btn-muted">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="p12-container">

        <div class="p12-hero">
            <div>
                <span style="background: rgba(255,255,255,0.15); color: #c7d2fe; font-size: 0.78rem; font-weight: 800; padding: 4px 12px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.04em;">
                    Practical 12 Laboratory Module
                </span>
                <h1 style="margin-top: 10px;">Event Management CRUD with Poster Upload</h1>
                <p>
                    Add, view, edit, and delete university events with validated poster uploads. Validates file size (max 2MB), authentic MIME types (JPEG, PNG, WEBP), image dimension integrity, and secure server storage.
                </p>
            </div>
            <div>
                <button type="button" class="btn-add-event" id="btnOpenAddModal">
                    + Create New Event
                </button>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert-banner alert-success">
                <span>✓</span>
                <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert-banner alert-error">
                <span>⚠</span>
                <span><?= htmlspecialchars($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon-box" style="background: #e0e7ff; color: #4338ca;">&#128197;</div>
                <div>
                    <div class="metric-val"><?= $metrics['total'] ?></div>
                    <div class="metric-lbl">Total Events</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon-box" style="background: #dbeafe; color: #1d4ed8;">&#9200;</div>
                <div>
                    <div class="metric-val"><?= $metrics['upcoming'] ?></div>
                    <div class="metric-lbl">Upcoming</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon-box" style="background: #dcfce7; color: #15803d;">&#9654;</div>
                <div>
                    <div class="metric-val"><?= $metrics['ongoing'] ?></div>
                    <div class="metric-lbl">Ongoing</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon-box" style="background: #f1f5f9; color: #475569;">&#10003;</div>
                <div>
                    <div class="metric-val"><?= $metrics['completed'] ?></div>
                    <div class="metric-lbl">Completed</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon-box" style="background: #fef3c7; color: #b45309;">&#128101;</div>
                <div>
                    <div class="metric-val"><?= number_format($metrics['total_capacity']) ?></div>
                    <div class="metric-lbl">Total Seats</div>
                </div>
            </div>
        </div>

        <div class="p12-toolbar">
            <form method="GET" action="practical12_events.php" class="filter-form">
                <div class="search-input-wrap">
                    <svg class="search-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" class="search-input" placeholder="Search by title, code, venue..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>

                <select name="category" class="select-filter">
                    <option value="">All Categories</option>
                    <?php
                    $categories = ['Technical', 'Workshop', 'Cultural', 'Sports', 'Seminar'];
                    foreach ($categories as $cat) {
                        $sel = ($filterCat === $cat) ? 'selected' : '';
                        echo "<option value=\"{$cat}\" {$sel}>{$cat}</option>";
                    }
                    ?>
                </select>

                <select name="status" class="select-filter">
                    <option value="">All Statuses</option>
                    <option value="UPCOMING" <?= ($filterStatus === 'UPCOMING') ? 'selected' : '' ?>>Upcoming</option>
                    <option value="ONGOING" <?= ($filterStatus === 'ONGOING') ? 'selected' : '' ?>>Ongoing</option>
                    <option value="COMPLETED" <?= ($filterStatus === 'COMPLETED') ? 'selected' : '' ?>>Completed</option>
                    <option value="CANCELLED" <?= ($filterStatus === 'CANCELLED') ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">

                <button type="submit" class="btn-filter-apply">Filter</button>
                <?php if (!empty($searchQuery) || !empty($filterCat) || !empty($filterStatus)): ?>
                    <a href="practical12_events.php?view=<?= htmlspecialchars($viewMode) ?>" class="btn-filter-reset">Reset</a>
                <?php endif; ?>
            </form>

            <div class="view-switchers">
                <a href="practical12_events.php?<?= http_build_query(array_merge($_GET, ['view' => 'grid'])) ?>" class="view-btn <?= ($viewMode === 'grid') ? 'active' : '' ?>">
                    Grid View
                </a>
                <a href="practical12_events.php?<?= http_build_query(array_merge($_GET, ['view' => 'table'])) ?>" class="view-btn <?= ($viewMode === 'table') ? 'active' : '' ?>">
                    Table View
                </a>
            </div>
        </div>

        <?php if (empty($eventData['data'])): ?>
            <div style="background: #ffffff; border: 1.5px dashed var(--p12-border); border-radius: 18px; padding: 48px 24px; text-align: center;">
                <div style="font-size: 2.8rem; margin-bottom: 12px; color: #94a3b8;">&#128197;</div>
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; margin: 0 0 6px 0;">No Events Found</h3>
                <p style="color: var(--p12-text-muted); font-size: 0.92rem; margin: 0 0 16px 0;">No event records matched your filter criteria or no events are registered yet.</p>
                <button type="button" class="btn-add-event" onclick="document.getElementById('btnOpenAddModal').click()">
                    + Add First Event
                </button>
            </div>
        <?php else: ?>

            <?php if ($viewMode === 'grid'): ?>
                <div class="events-grid">
                    <?php foreach ($eventData['data'] as $evt): ?>
                        <div class="event-card">
                            <div class="poster-container" onclick="openLightbox('<?= !empty($evt['poster_image']) ? 'uploads/posters/' . htmlspecialchars($evt['poster_image']) : '' ?>', '<?= htmlspecialchars(addslashes($evt['title'])) ?>', '<?= htmlspecialchars(addslashes($evt['event_code'])) ?>')">
                                <?php if (!empty($evt['poster_image']) && file_exists(__DIR__ . '/uploads/posters/' . $evt['poster_image'])): ?>
                                    <img src="uploads/posters/<?= htmlspecialchars($evt['poster_image']) ?>" alt="Poster" class="poster-img" loading="lazy">
                                <?php else: ?>
                                    <div class="poster-placeholder">
                                        <div style="font-size: 1.6rem; margin-bottom: 4px;">&#128444;</div>
                                        <span>No Poster Uploaded</span>
                                    </div>
                                <?php endif; ?>

                                <span class="badge-status-chip status-<?= strtolower($evt['status']) ?>">
                                    <?= htmlspecialchars($evt['status']) ?>
                                </span>
                                <span class="badge-category-chip">
                                    <?= htmlspecialchars($evt['category']) ?>
                                </span>
                            </div>

                            <div class="event-body">
                                <div class="event-code-tag"><?= htmlspecialchars($evt['event_code']) ?></div>
                                <h3 class="event-title"><?= htmlspecialchars($evt['title']) ?></h3>

                                <ul class="event-meta-list">
                                    <li class="event-meta-item">
                                        <span>&#128197;</span>
                                        <span><?= date('D, M d, Y &bull; h:i A', strtotime($evt['event_date'])) ?></span>
                                    </li>
                                    <li class="event-meta-item">
                                        <span>&#128205;</span>
                                        <span><?= htmlspecialchars($evt['venue']) ?></span>
                                    </li>
                                    <li class="event-meta-item">
                                        <span>&#127919;</span>
                                        <span>Capacity: <?= (int)$evt['max_capacity'] ?> attendees (<?= (int)$evt['registered_count'] ?> registered)</span>
                                    </li>
                                </ul>

                                <div class="event-desc">
                                    <?= htmlspecialchars($evt['description'] ?: 'No description provided.') ?>
                                </div>

                                <div class="card-actions">
                                    <button type="button" class="btn-card-action btn-view" onclick="openLightbox('<?= !empty($evt['poster_image']) ? 'uploads/posters/' . htmlspecialchars($evt['poster_image']) : '' ?>', '<?= htmlspecialchars(addslashes($evt['title'])) ?>', '<?= htmlspecialchars(addslashes($evt['event_code'])) ?>')">
                                        Poster
                                    </button>
                                    <button type="button" class="btn-card-action btn-edit" onclick='openEditModal(<?= json_encode($evt) ?>)'>
                                        Edit
                                    </button>
                                    <button type="button" class="btn-card-action btn-delete" onclick="confirmDelete(<?= (int)$evt['event_id'] ?>, '<?= htmlspecialchars(addslashes($evt['title'])) ?>', '<?= htmlspecialchars(addslashes($evt['event_code'])) ?>')">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="table-wrap">
                    <table class="p12-table">
                        <thead>
                            <tr>
                                <th>Poster</th>
                                <th>Event Code</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Date &amp; Time</th>
                                <th>Venue</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventData['data'] as $evt): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($evt['poster_image']) && file_exists(__DIR__ . '/uploads/posters/' . $evt['poster_image'])): ?>
                                            <img src="uploads/posters/<?= htmlspecialchars($evt['poster_image']) ?>" alt="Thumb" class="table-poster-thumb" onclick="openLightbox('uploads/posters/<?= htmlspecialchars($evt['poster_image']) ?>', '<?= htmlspecialchars(addslashes($evt['title'])) ?>', '<?= htmlspecialchars(addslashes($evt['event_code'])) ?>')">
                                        <?php else: ?>
                                            <div style="width: 52px; height: 52px; border-radius: 8px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: #64748b;">N/A</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($evt['event_code']) ?></strong></td>
                                    <td>
                                        <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($evt['title']) ?></div>
                                        <div style="font-size: 0.78rem; color: #64748b;"><?= htmlspecialchars(substr($evt['description'] ?? '', 0, 60)) ?>...</div>
                                    </td>
                                    <td><span style="background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 6px; font-size: 0.76rem; font-weight: 700;"><?= htmlspecialchars($evt['category']) ?></span></td>
                                    <td style="font-size: 0.82rem; white-space: nowrap;"><?= date('M d, Y h:i A', strtotime($evt['event_date'])) ?></td>
                                    <td><?= htmlspecialchars($evt['venue']) ?></td>
                                    <td><?= (int)$evt['max_capacity'] ?></td>
                                    <td><span class="badge-status-chip" style="position: static;"><?= htmlspecialchars($evt['status']) ?></span></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button type="button" class="btn-card-action btn-edit" style="display: inline-block; padding: 4px 10px;" onclick='openEditModal(<?= json_encode($evt) ?>)'>Edit</button>
                                        <button type="button" class="btn-card-action btn-delete" style="display: inline-block; padding: 4px 10px;" onclick="confirmDelete(<?= (int)$evt['event_id'] ?>, '<?= htmlspecialchars(addslashes($evt['title'])) ?>', '<?= htmlspecialchars(addslashes($evt['event_code'])) ?>')">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($eventData['total_pages'] > 1): ?>
                <div class="p12-pagination">
                    <?php for ($i = 1; $i <= $eventData['total_pages']; $i++): ?>
                        <a href="practical12_events.php?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link-btn <?= ($i === $eventData['page']) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <div class="modal-overlay" id="eventFormModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="formModalTitle">Create New Event</h3>
                <button type="button" class="btn-modal-close" onclick="closeFormModal()">&times;</button>
            </div>
            <form method="POST" action="practical12_events.php" enctype="multipart/form-data" id="eventSaveForm">
                <input type="hidden" name="form_action" value="save_event">
                <input type="hidden" name="event_id" id="field_event_id" value="">

                <div class="modal-body">
                    <div class="form-grid">
                        <div>
                            <label class="form-label">Event Code *</label>
                            <input type="text" name="event_code" id="field_event_code" class="form-control <?= isset($fieldErrors['event_code']) ? 'has-error' : '' ?>" placeholder="e.g. EVT-AI-2026" required>
                            <?php if (isset($fieldErrors['event_code'])): ?>
                                <span class="err-msg"><?= htmlspecialchars($fieldErrors['event_code']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label">Category *</label>
                            <select name="category" id="field_category" class="form-control" required>
                                <option value="Technical">Technical</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Cultural">Cultural</option>
                                <option value="Sports">Sports</option>
                                <option value="Seminar">Seminar</option>
                            </select>
                        </div>

                        <div class="form-col-full">
                            <label class="form-label">Event Title *</label>
                            <input type="text" name="title" id="field_title" class="form-control <?= isset($fieldErrors['title']) ? 'has-error' : '' ?>" placeholder="e.g. NextGen Artificial Intelligence Summit" required>
                            <?php if (isset($fieldErrors['title'])): ?>
                                <span class="err-msg"><?= htmlspecialchars($fieldErrors['title']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label">Date &amp; Time *</label>
                            <input type="datetime-local" name="event_date" id="field_event_date" class="form-control <?= isset($fieldErrors['event_date']) ? 'has-error' : '' ?>" required>
                            <?php if (isset($fieldErrors['event_date'])): ?>
                                <span class="err-msg"><?= htmlspecialchars($fieldErrors['event_date']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label">Max Capacity *</label>
                            <input type="number" name="max_capacity" id="field_max_capacity" class="form-control" min="1" max="10000" value="50" required>
                        </div>

                        <div class="form-col-full">
                            <label class="form-label">Venue Location *</label>
                            <input type="text" name="venue" id="field_venue" class="form-control" placeholder="e.g. Auditorium Hall A, Campus Central" required>
                        </div>

                        <div class="form-col-full">
                            <label class="form-label">Event Status</label>
                            <select name="status" id="field_status" class="form-control">
                                <option value="UPCOMING">Upcoming</option>
                                <option value="ONGOING">Ongoing</option>
                                <option value="COMPLETED">Completed</option>
                                <option value="CANCELLED">Cancelled</option>
                            </select>
                        </div>

                        <div class="form-col-full">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="field_description" class="form-control" rows="3" placeholder="Provide event schedule, rules, or details..."></textarea>
                        </div>

                        <div class="form-col-full">
                            <label class="form-label">Event Poster (Upload &amp; Validation)</label>
                            <div class="dropzone-box" onclick="document.getElementById('posterInput').click()">
                                <div style="font-size: 1.8rem; margin-bottom: 4px; color: var(--p12-indigo);">&#128247;</div>
                                <div style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Click or drop image to upload poster</div>
                                <div style="font-size: 0.78rem; color: #64748b; margin-top: 4px;">
                                    Max file size: <strong>2MB</strong> &bull; Allowed: <strong>JPG, JPEG, PNG, WEBP</strong>
                                </div>
                                <input type="file" name="poster" id="posterInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="handlePosterSelection(this)">
                            </div>

                            <?php if (isset($fieldErrors['poster'])): ?>
                                <span class="err-msg" style="margin-top: 6px;"><?= htmlspecialchars($fieldErrors['poster']) ?></span>
                            <?php endif; ?>

                            <div class="poster-preview-pane" id="previewPane">
                                <img src="" alt="Poster Preview" class="poster-preview-img" id="previewImgElem">
                                <div style="flex: 1;">
                                    <div style="font-size: 0.85rem; font-weight: 700; color: #0f172a;" id="previewFileName">Selected Image</div>
                                    <div style="font-size: 0.76rem; color: #64748b;" id="previewFileSize">0 KB</div>
                                    <span style="font-size: 0.72rem; color: #16a34a; font-weight: 700;">✓ Size and type validated client-side</span>
                                </div>
                                <button type="button" class="btn-modal-close" style="font-size: 1.1rem;" onclick="clearPosterSelection()">&times;</button>
                            </div>

                            <div id="currentPosterNote" style="display: none; margin-top: 8px; font-size: 0.8rem; color: #475569; background: #f8fafc; padding: 6px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                Note: Current poster will be retained if no new file is uploaded.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeFormModal()">Cancel</button>
                    <button type="submit" class="btn-submit" id="btnSubmitForm">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="deleteConfirmModal">
        <div class="modal-dialog" style="max-width: 440px;">
            <div class="modal-header">
                <h3 style="color: #b91c1c;">Confirm Event Deletion</h3>
                <button type="button" class="btn-modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <form method="POST" action="practical12_events.php">
                <input type="hidden" name="form_action" value="delete_event">
                <input type="hidden" name="delete_event_id" id="delete_event_id" value="">

                <div class="modal-body" style="text-align: center; padding: 24px;">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 14px auto;">
                        &#128465;
                    </div>
                    <p style="margin: 0 0 10px 0; font-weight: 700; color: #1e293b; font-size: 1.05rem;">
                        Are you sure you want to delete this event?
                    </p>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-bottom: 12px; text-align: left;">
                        <div style="font-size: 0.8rem; color: #64748b;">Event Code: <strong id="del_code_text" style="color: #0f172a;"></strong></div>
                        <div style="font-size: 0.9rem; font-weight: 700; color: #0f172a; margin-top: 4px;" id="del_title_text"></div>
                    </div>
                    <p style="font-size: 0.8rem; color: #64748b; margin: 0;">
                        This action will permanently delete the event record and remove the associated poster file from server disk.
                    </p>
                </div>

                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-submit" style="background: #dc2626;">Yes, Delete Event</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="lightboxModal" onclick="closeLightbox(event)">
        <div class="modal-dialog lightbox-modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div>
                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--p12-indigo);" id="lb_code"></div>
                    <h3 id="lb_title" style="font-size: 1.15rem; margin: 0;"></h3>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeLightbox(null)">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <img src="" alt="Full Poster" id="lb_img" class="lightbox-img">
                <div style="display: flex; justify-content: center; gap: 10px;">
                    <a href="" id="lb_download" download class="btn-filter-apply" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        Download High-Res Poster
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const addModal = document.getElementById('eventFormModal');
        const deleteModal = document.getElementById('deleteConfirmModal');
        const lightboxModal = document.getElementById('lightboxModal');
        const form = document.getElementById('eventSaveForm');

        document.getElementById('btnOpenAddModal').addEventListener('click', function () {
            form.reset();
            document.getElementById('field_event_id').value = '';
            document.getElementById('formModalTitle').textContent = 'Create New Event';
            document.getElementById('btnSubmitForm').textContent = 'Save Event';
            document.getElementById('previewPane').style.display = 'none';
            document.getElementById('currentPosterNote').style.display = 'none';
            
            const randomCode = 'EVT-' + Math.floor(1000 + Math.random() * 9000);
            document.getElementById('field_event_code').value = randomCode;

            addModal.style.display = 'flex';
        });

        function closeFormModal() {
            addModal.style.display = 'none';
        }

        function openEditModal(evt) {
            form.reset();
            document.getElementById('formModalTitle').textContent = 'Edit Event: ' + evt.event_code;
            document.getElementById('btnSubmitForm').textContent = 'Update Event';
            document.getElementById('field_event_id').value = evt.event_id;
            document.getElementById('field_event_code').value = evt.event_code;
            document.getElementById('field_title').value = evt.title;
            document.getElementById('field_category').value = evt.category;
            
            if (evt.event_date) {
                const formattedDate = evt.event_date.replace(' ', 'T').substring(0, 16);
                document.getElementById('field_event_date').value = formattedDate;
            }
            
            document.getElementById('field_max_capacity').value = evt.max_capacity;
            document.getElementById('field_venue').value = evt.venue;
            document.getElementById('field_status').value = evt.status;
            document.getElementById('field_description').value = evt.description || '';

            const previewPane = document.getElementById('previewPane');
            const previewImgElem = document.getElementById('previewImgElem');
            const previewFileName = document.getElementById('previewFileName');
            const previewFileSize = document.getElementById('previewFileSize');
            const currentNote = document.getElementById('currentPosterNote');

            if (evt.poster_image) {
                previewPane.style.display = 'flex';
                previewImgElem.src = 'uploads/posters/' + evt.poster_image;
                previewFileName.textContent = evt.poster_image;
                previewFileSize.textContent = 'Existing server file';
                currentNote.style.display = 'block';
            } else {
                previewPane.style.display = 'none';
                currentNote.style.display = 'none';
            }

            addModal.style.display = 'flex';
        }

        function confirmDelete(id, title, code) {
            document.getElementById('delete_event_id').value = id;
            document.getElementById('del_title_text').textContent = title;
            document.getElementById('del_code_text').textContent = code;
            deleteModal.style.display = 'flex';
        }

        function closeDeleteModal() {
            deleteModal.style.display = 'none';
        }

        function openLightbox(imgSrc, title, code) {
            if (!imgSrc) return;
            document.getElementById('lb_img').src = imgSrc;
            document.getElementById('lb_download').href = imgSrc;
            document.getElementById('lb_title').textContent = title;
            document.getElementById('lb_code').textContent = code;
            lightboxModal.style.display = 'flex';
        }

        function closeLightbox(e) {
            lightboxModal.style.display = 'none';
        }

        function handlePosterSelection(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                const maxSize = 2 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('Validation Alert: File exceeds maximum allowed size of 2MB (' + (file.size / 1048576).toFixed(2) + 'MB). Please choose a smaller file.');
                    input.value = '';
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Validation Alert: Invalid file type (' + file.type + '). Only JPG, PNG, and WEBP formats are accepted.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('previewImgElem').src = e.target.result;
                    document.getElementById('previewFileName').textContent = file.name;
                    document.getElementById('previewFileSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
                    document.getElementById('previewPane').style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        }

        function clearPosterSelection() {
            document.getElementById('posterInput').value = '';
            document.getElementById('previewPane').style.display = 'none';
        }

        window.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const m = params.get('modal');
            if (m === 'add' || m === 'create') {
                document.getElementById('btnOpenAddModal').click();
                if (params.get('demo') === '1') {
                    document.getElementById('field_event_code').value = 'EVT-CLOUD-2026';
                    document.getElementById('field_title').value = 'Cloud Native & DevOps Summit 2026';
                    document.getElementById('field_category').value = 'Technical';
                    document.getElementById('field_event_date').value = '2026-11-20T10:00';
                    document.getElementById('field_max_capacity').value = '150';
                    document.getElementById('field_venue').value = 'Auditorium Hall B, Tech Complex';
                    document.getElementById('field_status').value = 'UPCOMING';
                    document.getElementById('field_description').value = 'Comprehensive university seminar covering Kubernetes, container orchestration, microservices, and continuous deployment workflows.';
                }
                if (params.get('preview') === '1') {
                    document.getElementById('previewPane').style.display = 'flex';
                    document.getElementById('previewImgElem').src = 'uploads/posters/poster_ai_summit.png';
                    document.getElementById('previewFileName').textContent = 'poster_cloud_summit.png';
                    document.getElementById('previewFileSize').textContent = '245.8 KB (Valid <= 2MB)';
                }
            } else if (m === 'edit') {
                const eventId = params.get('id') || '1';
                fetch('practical12_events.php?ajax=get_event&id=' + eventId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.event) {
                            openEditModal(data.event);
                        }
                    });
            } else if (m === 'delete') {
                const eventId = params.get('id') || '1';
                fetch('practical12_events.php?ajax=get_event&id=' + eventId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.event) {
                            confirmDelete(data.event.event_id, data.event.title, data.event.event_code);
                        }
                    });
            } else if (m === 'lightbox') {
                const eventId = params.get('id') || '1';
                fetch('practical12_events.php?ajax=get_event&id=' + eventId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.event && data.event.poster_image) {
                            openLightbox('uploads/posters/' + data.event.poster_image, data.event.title, data.event.event_code);
                        }
                    });
            }
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFormModal();
                closeDeleteModal();
                closeLightbox();
            }
        });
    </script>

</body>
</html>
