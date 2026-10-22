<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

initSecureSession();
$mysqli = getMySQLiConnection(true);

if ($mysqli) {
    ensureStudentsTableReady($mysqli);
}

$flashMessage = $_SESSION['flash_msg'] ?? ($_GET['msg'] ?? null);
$flashType    = $_SESSION['flash_type'] ?? ($_GET['msg_type'] ?? 'info');
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_student' && !empty($_GET['id'])) {
    header('Content-Type: application/json');
    if (!$mysqli) {
        echo json_encode(['success' => false, 'message' => 'Database offline']);
        exit;
    }
    $studentId = (int)$_GET['id'];
    $student = getStudentRecord($mysqli, $studentId);
    if ($student) {
        $regList = [];
        $regStmt = $mysqli->prepare("SELECT e.title, e.category, r.ticket_code, r.attendance_status 
                                     FROM registrations r 
                                     JOIN events e ON r.event_id = e.event_id 
                                     WHERE r.student_id = ?");
        if ($regStmt) {
            $regStmt->bind_param("i", $studentId);
            $regStmt->execute();
            $res = $regStmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $regList[] = $r;
            }
            $regStmt->close();
        }
        $student['registrations'] = $regList;
        echo json_encode(['success' => true, 'student' => $student]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student record not found']);
    }
    exit;
}

$formErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        $_SESSION['flash_msg'] = 'Security validation failed (Invalid CSRF Token). Please reload and try again.';
        $_SESSION['flash_type'] = 'danger';
        header("Location: practical11_students.php");
        exit;
    }

    if (!$mysqli) {
        $_SESSION['flash_msg'] = 'Database connection error. Ensure MySQL is running in XAMPP.';
        $_SESSION['flash_type'] = 'danger';
        header("Location: practical11_students.php");
        exit;
    }

    if ($action === 'create') {
        $result = saveStudentRecord($mysqli, $_POST, null);
        if ($result['success']) {
            $_SESSION['flash_msg'] = $result['message'];
            $_SESSION['flash_type'] = 'success';
            header("Location: practical11_students.php");
            exit;
        } else {
            $formErrors = $result['errors'];
            $flashMessage = $result['message'];
            $flashType = 'danger';
        }
    }

    elseif ($action === 'update') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $result = saveStudentRecord($mysqli, $_POST, $studentId);
        if ($result['success']) {
            $_SESSION['flash_msg'] = $result['message'];
            $_SESSION['flash_type'] = 'success';
            header("Location: practical11_students.php?" . http_build_query([
                'search'     => $_GET['search'] ?? '',
                'department' => $_GET['department'] ?? '',
                'course'     => $_GET['course'] ?? '',
                'semester'   => $_GET['semester'] ?? '',
                'status'     => $_GET['status'] ?? '',
                'page'       => $_GET['page'] ?? 1
            ]));
            exit;
        } else {
            $formErrors = $result['errors'];
            $flashMessage = $result['message'];
            $flashType = 'danger';
        }
    }

    elseif ($action === 'delete') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $result = deleteStudentRecord($mysqli, $studentId);
        $_SESSION['flash_msg'] = $result['message'];
        $_SESSION['flash_type'] = $result['success'] ? 'success' : 'danger';
        header("Location: practical11_students.php?" . http_build_query([
            'search'     => $_GET['search'] ?? '',
            'department' => $_GET['department'] ?? '',
            'course'     => $_GET['course'] ?? '',
            'semester'   => $_GET['semester'] ?? '',
            'status'     => $_GET['status'] ?? '',
            'page'       => $_GET['page'] ?? 1
        ]));
        exit;
    }
}

$searchParam     = trim($_GET['search'] ?? '');
$departmentParam = trim($_GET['department'] ?? '');
$courseParam     = trim($_GET['course'] ?? '');
$semesterParam   = (int)($_GET['semester'] ?? 0);
$statusParam     = strtoupper(trim($_GET['status'] ?? 'ACTIVE'));
$sortColParam    = trim($_GET['sort'] ?? 'roll_no');
$sortDirParam    = strtoupper(trim($_GET['order'] ?? 'ASC'));
$pageParam       = max(1, (int)($_GET['page'] ?? 1));
$perPageParam    = max(5, min(50, (int)($_GET['per_page'] ?? 10)));

$currentFilters = [
    'search'     => $searchParam,
    'department' => $departmentParam,
    'course'     => $courseParam,
    'semester'   => $semesterParam,
    'status'     => $statusParam
];

$studentData = [
    'data'        => [],
    'total'       => 0,
    'page'        => 1,
    'per_page'    => $perPageParam,
    'total_pages' => 1,
    'sort_col'    => $sortColParam,
    'sort_dir'    => $sortDirParam
];
$metrics = [
    'total'       => 0,
    'active'      => 0,
    'inactive'    => 0,
    'deleted'     => 0,
    'departments' => [],
    'courses'     => []
];

if ($mysqli) {
    $studentData = fetchStudentsList($mysqli, $currentFilters, $pageParam, $perPageParam, $sortColParam, $sortDirParam);
    $metrics = getStudentSummaryMetrics($mysqli);
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management CRUD with Search &amp; Filter | Practical 11</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --p11-navy: #0f172a;
            --p11-navy-light: #1e293b;
            --p11-blue: #2563eb;
            --p11-blue-hover: #1d4ed8;
            --p11-cyan: #06b6d4;
            --p11-bg: #f8fafc;
            --p11-card: #ffffff;
            --p11-border: #e2e8f0;
            --p11-text: #0f172a;
            --p11-text-muted: #64748b;
            --p11-green: #10b981;
            --p11-amber: #f59e0b;
            --p11-rose: #ef4444;
            --p11-radius: 12px;
            --p11-shadow: 0 4px 6px -1px rgba(0,0,0,0.06), 0 2px 4px -2px rgba(0,0,0,0.04);
            --p11-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
        }

        body {
            background-color: var(--p11-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--p11-text);
            margin: 0;
            padding-bottom: 60px;
        }

        
        .app-header {
            background: linear-gradient(135deg, var(--p11-navy) 0%, #172554 100%);
            color: #ffffff;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            border-bottom: 3px solid var(--p11-cyan);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-title .badge-pill {
            background: rgba(6, 182, 212, 0.2);
            color: #38bdf8;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 999px;
            border: 1px solid rgba(56, 189, 248, 0.3);
            text-transform: uppercase;
            font-weight: 700;
        }

        .nav-link-group {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .nav-link-group a {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .nav-link-group a:hover,
        .nav-link-group a.active {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-link-group .cta-test-btn {
            background: var(--p11-blue);
            color: #ffffff;
            font-weight: 600;
        }

        .nav-link-group .cta-test-btn:hover {
            background: var(--p11-blue-hover);
        }

        
        .crud-container {
            max-width: 1320px;
            margin: 28px auto;
            padding: 0 20px;
        }

        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }

        .header-text h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--p11-navy);
            margin: 0 0 6px 0;
            letter-spacing: -0.02em;
        }

        .header-text p {
            color: var(--p11-text-muted);
            font-size: 0.95rem;
            margin: 0;
            max-width: 780px;
            line-height: 1.5;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        
        .flash-banner {
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 0.94rem;
            font-weight: 500;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: var(--p11-shadow);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .flash-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .flash-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .flash-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .flash-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .flash-close-btn {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0 4px;
            line-height: 1;
            opacity: 0.7;
        }
        .flash-close-btn:hover { opacity: 1; }

        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            background: var(--p11-card);
            border: 1px solid var(--p11-border);
            border-radius: var(--p11-radius);
            padding: 18px 20px;
            box-shadow: var(--p11-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--p11-shadow-lg);
        }

        .metric-info .metric-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: var(--p11-text-muted);
            margin-bottom: 4px;
        }

        .metric-info .metric-value {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--p11-navy);
            line-height: 1.1;
        }

        .metric-icon-box {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .icon-blue  { background: #eff6ff; color: #2563eb; }
        .icon-green { background: #ecfdf5; color: #10b981; }
        .icon-amber { background: #fffbeb; color: #f59e0b; }
        .icon-rose  { background: #fef2f2; color: #ef4444; }

        
        .toolbar-card {
            background: var(--p11-card);
            border: 1px solid var(--p11-border);
            border-radius: var(--p11-radius);
            padding: 20px;
            box-shadow: var(--p11-shadow);
            margin-bottom: 24px;
        }

        .toolbar-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }

        .status-pills {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
            gap: 4px;
            flex-wrap: wrap;
        }

        .status-pill-link {
            text-decoration: none;
            color: var(--p11-text-muted);
            font-size: 0.84rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pill-link:hover {
            color: var(--p11-text);
        }

        .status-pill-link.active {
            background: #ffffff;
            color: var(--p11-navy);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .status-pill-link .count-tag {
            font-size: 0.72rem;
            background: rgba(100, 116, 139, 0.15);
            padding: 2px 6px;
            border-radius: 999px;
        }

        .status-pill-link.active .count-tag {
            background: var(--p11-navy);
            color: #ffffff;
        }

        .toolbar-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 16px;
            font-size: 0.88rem;
            font-weight: 600;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--p11-blue);
            color: #ffffff;
        }
        .btn-primary:hover {
            background: var(--p11-blue-hover);
            transform: translateY(-1px);
        }

        .btn-success {
            background: #10b981;
            color: #ffffff;
        }
        .btn-success:hover {
            background: #059669;
        }

        .btn-outline {
            background: #ffffff;
            border-color: var(--p11-border);
            color: var(--p11-text);
        }
        .btn-outline:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .btn-danger {
            background: #ef4444;
            color: #ffffff;
        }
        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.78rem;
            border-radius: 6px;
        }

        
        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1.2fr 1fr auto auto;
            gap: 12px;
            align-items: center;
        }

        @media (max-width: 1024px) {
            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 640px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
        }

        .form-control {
            width: 100%;
            padding: 9px 12px;
            font-size: 0.88rem;
            border: 1px solid var(--p11-border);
            border-radius: 8px;
            background: #ffffff;
            color: var(--p11-text);
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--p11-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        
        .table-card {
            background: var(--p11-card);
            border: 1px solid var(--p11-border);
            border-radius: var(--p11-radius);
            box-shadow: var(--p11-shadow);
            overflow: hidden;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.88rem;
        }

        .students-table th {
            background: #f8fafc;
            color: var(--p11-text-muted);
            font-weight: 600;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 18px;
            border-bottom: 1px solid var(--p11-border);
            white-space: nowrap;
        }

        .students-table th a.sort-link {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .students-table th a.sort-link:hover {
            color: var(--p11-navy);
        }

        .students-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .students-table tr:hover td {
            background-color: #f8fafc;
        }

        .students-table tr.deleted-row td {
            background-color: #fffaf0;
            opacity: 0.9;
        }

        
        .student-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .student-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        .student-avatar.inactive {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
        }

        .student-avatar.deleted {
            background: linear-gradient(135deg, #f87171 0%, #dc2626 100%);
        }

        .student-name {
            font-weight: 600;
            color: var(--p11-navy);
            margin-bottom: 2px;
        }

        .student-email {
            font-size: 0.78rem;
            color: var(--p11-text-muted);
        }

        .roll-badge {
            font-family: 'Outfit', monospace;
            font-weight: 700;
            font-size: 0.85rem;
            color: #1e40af;
            background: #eff6ff;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #dbeafe;
            display: inline-block;
        }

        .dept-tag {
            font-size: 0.78rem;
            color: #475569;
            background: #f1f5f9;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 3px;
        }

        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .badge-active {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .badge-active .badge-dot { background: #10b981; }

        .badge-inactive {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .badge-inactive .badge-dot { background: #f59e0b; }

        .badge-deleted {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .badge-deleted .badge-dot { background: #ef4444; }

        
        .actions-group {
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
        }

        .action-icon-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid var(--p11-border);
            background: #ffffff;
            color: var(--p11-text);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .action-icon-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .action-icon-btn.edit-btn:hover {
            color: var(--p11-blue);
            border-color: #93c5fd;
            background: #eff6ff;
        }

        .action-icon-btn.delete-btn:hover {
            color: var(--p11-rose);
            border-color: #fca5a5;
            background: #fef2f2;
        }

        .action-icon-btn.restore-btn:hover {
            color: var(--p11-green);
            border-color: #86efac;
            background: #f0fdf4;
        }

        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--p11-text-muted);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--p11-navy);
            margin-bottom: 6px;
        }

        
        .table-footer {
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            background: #ffffff;
            border-top: 1px solid var(--p11-border);
            font-size: 0.88rem;
            color: var(--p11-text-muted);
        }

        .pagination-nav {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .page-link-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 6px;
            border: 1px solid var(--p11-border);
            background: #ffffff;
            color: var(--p11-text);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.84rem;
            transition: all 0.15s ease;
        }

        .page-link-pill:hover {
            background: #f1f5f9;
        }

        .page-link-pill.active {
            background: var(--p11-navy);
            color: #ffffff;
            border-color: var(--p11-navy);
        }

        .page-link-pill.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-card {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            max-height: 92vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            transform: scale(0.95);
            transition: transform 0.25s ease;
        }

        .modal-card form {
            display: flex;
            flex-direction: column;
            max-height: 92vh;
            overflow: hidden;
        }

        .modal-overlay.active .modal-card {
            transform: scale(1);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--p11-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--p11-navy);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--p11-text-muted);
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        .modal-close:hover { color: var(--p11-navy); }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 580px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
        }

        .form-label span.req {
            color: #ef4444;
        }

        .form-error-feedback {
            font-size: 0.78rem;
            color: #dc2626;
            margin-top: 2px;
        }

        .modal-footer {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid var(--p11-border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
        }

        
        .detail-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .detail-label {
            width: 140px;
            font-weight: 600;
            color: var(--p11-text-muted);
            font-size: 0.86rem;
            flex-shrink: 0;
        }
        .detail-val {
            color: var(--p11-navy);
            font-size: 0.9rem;
            font-weight: 500;
        }
    </style>
</head>
<body>

    
    <header class="app-header">
        <a href="index.html" class="brand-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                <path d="M6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
            StudentHub CRUD Portal
            <span class="badge-pill">Practical 11</span>
        </a>
        <nav class="nav-link-group">
            <a href="dashboard.php">Dashboard</a>
            <a href="admin_dashboard.php">Admin Roster</a>
            <a href="practical10_test.php">Practical 10</a>
            <a href="practical11_students.php" class="active">Students CRUD</a>
            <a href="practical11_test.php" class="cta-test-btn">&rarr; Run Automated Test Suite</a>
            <a href="db_setup.php">DB Setup</a>
        </nav>
    </header>

    <div class="crud-container">

        
        <?php if ($flashMessage): ?>
            <div class="flash-banner flash-<?= htmlspecialchars($flashType) ?>">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php if ($flashType === 'success'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?php elseif ($flashType === 'warning'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($flashMessage) ?></span>
                </div>
                <button type="button" class="flash-close-btn" onclick="this.parentElement.remove();">&times;</button>
            </div>
        <?php endif; ?>

        
        <div class="page-header">
            <div class="header-text">
                <h1>Student Management System</h1>
                <p>
                    Full Object-Oriented MySQLi CRUD module featuring parameterized SELECT, INSERT, UPDATE, and DELETE queries, multi-field search (by Name, Email, Roll No, Course), cascading filters, and pagination.
                </p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn btn-primary" onclick="openAddStudentModal();">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add New Student
                </button>
            </div>
        </div>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-info">
                    <div class="metric-label">Total Seeded Students</div>
                    <div class="metric-value"><?= number_format($metrics['total']) ?></div>
                </div>
                <div class="metric-icon-box icon-blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <div class="metric-label">Active Enrolled</div>
                    <div class="metric-value"><?= number_format($metrics['active']) ?></div>
                </div>
                <div class="metric-icon-box icon-green">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <div class="metric-label">Inactive / On Leave</div>
                    <div class="metric-value"><?= number_format($metrics['inactive']) ?></div>
                </div>
                <div class="metric-icon-box icon-amber">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
            </div>
        </div>

        <div class="toolbar-card">
            <div class="toolbar-top">
                <div class="status-pills">
                    <a href="practical11_students.php?status=ACTIVE" class="status-pill-link <?= ($statusParam === 'ACTIVE') ? 'active' : '' ?>">
                        Active Roster
                        <span class="count-tag"><?= $metrics['active'] ?></span>
                    </a>
                    <a href="practical11_students.php?status=ALL" class="status-pill-link <?= ($statusParam === 'ALL' || empty($statusParam)) ? 'active' : '' ?>">
                        All Records
                        <span class="count-tag"><?= $metrics['total'] ?></span>
                    </a>
                    <a href="practical11_students.php?status=INACTIVE" class="status-pill-link <?= ($statusParam === 'INACTIVE') ? 'active' : '' ?>">
                        Inactive
                        <span class="count-tag"><?= $metrics['inactive'] ?></span>
                    </a>
                </div>

                <div class="toolbar-buttons">
                    <?php if (!empty($searchParam) || !empty($departmentParam) || !empty($courseParam) || $semesterParam > 0): ?>
                        <a href="practical11_students.php?status=<?= urlencode($statusParam) ?>" class="btn btn-outline btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Reset Filters
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            
            <form method="GET" action="practical11_students.php" class="filter-form">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusParam) ?>">
                
                
                <div>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, roll no, course..." value="<?= htmlspecialchars($searchParam) ?>">
                </div>

                
                <div>
                    <select name="department" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($metrics['departments'] as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>" <?= ($departmentParam === $dept) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                
                <div>
                    <select name="course" class="form-control">
                        <option value="">All Courses</option>
                        <?php foreach ($metrics['courses'] as $course): ?>
                            <option value="<?= htmlspecialchars($course) ?>" <?= ($courseParam === $course) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                
                <div>
                    <select name="semester" class="form-control">
                        <option value="0">All Semesters</option>
                        <?php for ($s = 1; $s <= 8; $s++): ?>
                            <option value="<?= $s ?>" <?= ($semesterParam === $s) ? 'selected' : '' ?>>
                                Semester <?= $s ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                
                <div>
                    <select name="per_page" class="form-control" style="width: auto;">
                        <option value="5" <?= ($perPageParam === 5) ? 'selected' : '' ?>>5 / page</option>
                        <option value="10" <?= ($perPageParam === 10) ? 'selected' : '' ?>>10 / page</option>
                        <option value="20" <?= ($perPageParam === 20) ? 'selected' : '' ?>>20 / page</option>
                        <option value="50" <?= ($perPageParam === 50) ? 'selected' : '' ?>>50 / page</option>
                    </select>
                </div>

                
                <div>
                    <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        Filter
                    </button>
                </div>
            </form>
        </div>

        
        <div class="table-card">
            <div class="table-responsive">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="practical11_students.php?<?= http_build_query(array_merge($currentFilters, ['sort' => 'roll_no', 'order' => ($sortColParam === 'roll_no' && $sortDirParam === 'ASC') ? 'DESC' : 'ASC', 'per_page' => $perPageParam])) ?>" class="sort-link">
                                    Roll Number
                                    <?php if ($sortColParam === 'roll_no'): ?>
                                        <?= ($sortDirParam === 'ASC') ? '&#9650;' : '&#9660;' ?>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="practical11_students.php?<?= http_build_query(array_merge($currentFilters, ['sort' => 'full_name', 'order' => ($sortColParam === 'full_name' && $sortDirParam === 'ASC') ? 'DESC' : 'ASC', 'per_page' => $perPageParam])) ?>" class="sort-link">
                                    Student Name &amp; Email
                                    <?php if ($sortColParam === 'full_name'): ?>
                                        <?= ($sortDirParam === 'ASC') ? '&#9650;' : '&#9660;' ?>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Course &amp; Department</th>
                            <th>
                                <a href="practical11_students.php?<?= http_build_query(array_merge($currentFilters, ['sort' => 'semester', 'order' => ($sortColParam === 'semester' && $sortDirParam === 'ASC') ? 'DESC' : 'ASC', 'per_page' => $perPageParam])) ?>" class="sort-link">
                                    Semester
                                    <?php if ($sortColParam === 'semester'): ?>
                                        <?= ($sortDirParam === 'ASC') ? '&#9650;' : '&#9660;' ?>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Contact Phone</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($studentData['data'])): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-icon">&#128269;</div>
                                        <div class="empty-title">No Students Found</div>
                                        <p>No student records match your active search or filter criteria.</p>
                                        <a href="practical11_students.php" class="btn btn-outline btn-sm" style="margin-top: 10px;">Clear All Filters</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($studentData['data'] as $s): ?>
                                <?php
                                    $initials = '';
                                    $nameParts = explode(' ', trim($s['full_name']));
                                    foreach ($nameParts as $p) {
                                        if (!empty($p)) $initials .= strtoupper($p[0]);
                                        if (strlen($initials) >= 2) break;
                                    }
                                    if (empty($initials)) $initials = 'ST';
                                ?>
                                <tr>
                                    <td>
                                        <span class="roll-badge"><?= htmlspecialchars($s['roll_no']) ?></span>
                                    </td>
                                    <td>
                                        <div class="student-cell">
                                            <div class="student-avatar <?= strtolower($s['status']) ?>">
                                                <?= htmlspecialchars($initials) ?>
                                            </div>
                                            <div>
                                                <div class="student-name">
                                                    <a href="javascript:void(0)" onclick="viewStudentDetails(<?= (int)$s['student_id'] ?>)" style="color: inherit; text-decoration: none;">
                                                        <?= htmlspecialchars($s['full_name']) ?>
                                                    </a>
                                                </div>
                                                <div class="student-email"><?= htmlspecialchars($s['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($s['course'] ?? $s['department']) ?></div>
                                        <div class="dept-tag"><?= htmlspecialchars($s['department']) ?></div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: #334155;">Sem <?= (int)$s['semester'] ?></span>
                                    </td>
                                    <td>
                                        <span style="font-family: monospace; color: #475569;"><?= htmlspecialchars($s['phone']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($s['status'] === 'ACTIVE'): ?>
                                            <span class="badge badge-active"><span class="badge-dot"></span> Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive"><span class="badge-dot"></span> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.78rem; color: var(--p11-text-muted);" title="<?= htmlspecialchars($s['created_at']) ?>">
                                            <?= date('d M Y', strtotime($s['created_at'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions-group">
                                            <button type="button" class="action-icon-btn" title="View Student Profile" onclick="viewStudentDetails(<?= (int)$s['student_id'] ?>)">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </button>
                                            <button type="button" class="action-icon-btn edit-btn" title="Edit Student Record" onclick="openEditStudentModal(<?= (int)$s['student_id'] ?>)">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            <button type="button" class="action-icon-btn delete-btn" title="Delete Student Record" onclick="confirmDeleteStudent(<?= (int)$s['student_id'] ?>, '<?= htmlspecialchars(addslashes($s['full_name'])) ?>', '<?= htmlspecialchars($s['roll_no']) ?>')">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            
            <div class="table-footer">
                <div>
                    Showing 
                    <strong><?= ($studentData['total'] > 0) ? (($studentData['page'] - 1) * $studentData['per_page'] + 1) : 0 ?></strong> 
                    to 
                    <strong><?= min($studentData['total'], $studentData['page'] * $studentData['per_page']) ?></strong> 
                    of 
                    <strong><?= $studentData['total'] ?></strong> students
                </div>

                <?php if ($studentData['total_pages'] > 1): ?>
                    <div class="pagination-nav">
                        
                        <a href="practical11_students.php?<?= http_build_query(array_merge($currentFilters, ['page' => max(1, $studentData['page'] - 1), 'per_page' => $perPageParam, 'sort' => $sortColParam, 'order' => $sortDirParam])) ?>" class="page-link-pill <?= ($studentData['page'] <= 1) ? 'disabled' : '' ?>">
                            &larr; Prev
                        </a>

                        <?php for ($p = 1; $p <= $studentData['total_pages']; $p++): ?>
                            <?php if ($p == 1 || $p == $studentData['total_pages'] || abs($p - $studentData['page']) <= 2): ?>
                                <a href="practical11_students.php?<?= http_build_query(array_merge($currentFilters, ['page' => $p, 'per_page' => $perPageParam, 'sort' => $sortColParam, 'order' => $sortDirParam])) ?>" class="page-link-pill <?= ($p === $studentData['page']) ? 'active' : '' ?>">
                                    <?= $p ?>
                                </a>
                            <?php elseif (abs($p - $studentData['page']) == 3): ?>
                                <span style="padding: 0 4px; color: #94a3b8;">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        
                        <a href="practical11_students.php?<?= http_build_query(array_merge($currentFilters, ['page' => min($studentData['total_pages'], $studentData['page'] + 1), 'per_page' => $perPageParam, 'sort' => $sortColParam, 'order' => $sortDirParam])) ?>" class="page-link-pill <?= ($studentData['page'] >= $studentData['total_pages']) ? 'disabled' : '' ?>">
                            Next &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    
    
    
    <div id="studentFormModal" class="modal-overlay">
        <div class="modal-card">
            <form id="studentCrudForm" method="POST" action="practical11_students.php">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="student_id" id="formStudentId" value="">

                <div class="modal-header">
                    <h3 id="modalTitle">Add New Student</h3>
                    <button type="button" class="modal-close" onclick="closeStudentModal()">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="form-grid">
                        
                        <div class="form-group">
                            <label class="form-label">Roll Number <span class="req">*</span></label>
                            <input type="text" name="roll_no" id="inputRollNo" class="form-control" placeholder="e.g. 21BCE1099" required maxlength="20" style="text-transform: uppercase;">
                            <div class="form-error-feedback" id="errRollNo"></div>
                        </div>

                        
                        <div class="form-group">
                            <label class="form-label">Full Name <span class="req">*</span></label>
                            <input type="text" name="full_name" id="inputFullName" class="form-control" placeholder="e.g. Rahul Sharma" required maxlength="100">
                            <div class="form-error-feedback" id="errFullName"></div>
                        </div>

                        
                        <div class="form-group">
                            <label class="form-label">Email Address <span class="req">*</span></label>
                            <input type="email" name="email" id="inputEmail" class="form-control" placeholder="e.g. rahul@studenthub.edu" required maxlength="100">
                            <div class="form-error-feedback" id="errEmail"></div>
                        </div>

                        
                        <div class="form-group">
                            <label class="form-label">Phone Number <span class="req">*</span></label>
                            <input type="tel" name="phone" id="inputPhone" class="form-control" placeholder="10-digit number" required maxlength="15">
                            <div class="form-error-feedback" id="errPhone"></div>
                        </div>

                        
                        <div class="form-group">
                            <label class="form-label">Department <span class="req">*</span></label>
                            <select name="department" id="inputDepartment" class="form-control" required>
                                <option value="">Select Department...</option>
                                <option value="Computer Engineering">Computer Engineering</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Electronics &amp; Comm">Electronics &amp; Comm</option>
                                <option value="Mechanical Engineering">Mechanical Engineering</option>
                                <option value="Data Science &amp; AI">Data Science &amp; AI</option>
                            </select>
                        </div>

                        
                        <div class="form-group">
                            <label class="form-label">Course Degree Program <span class="req">*</span></label>
                            <input type="text" name="course" id="inputCourse" class="form-control" placeholder="e.g. B.Tech Computer Science" required maxlength="80">
                        </div>

                        
                        <div class="form-group">
                            <label class="form-label">Semester <span class="req">*</span></label>
                            <select name="semester" id="inputSemester" class="form-control" required>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>">Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        
                        <div class="form-group">
                            <label class="form-label">Account Status <span class="req">*</span></label>
                            <select name="status" id="inputStatus" class="form-control" required>
                                <option value="ACTIVE">ACTIVE (Enrolled)</option>
                                <option value="INACTIVE">INACTIVE (On Leave / In Review)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeStudentModal()">Cancel</button>
                    <button type="submit" id="btnSubmitForm" class="btn btn-primary">Save Student Record</button>
                </div>
            </form>
        </div>
    </div>

    
    
    
    <div id="studentDetailsModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Student Academic Profile</h3>
                <button type="button" class="modal-close" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div style="text-align: center; padding: 30px;">Loading student record...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    
    
    
    <div id="deleteConfirmModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header" style="background: #fff1f2; border-bottom: 1px solid #fecdd3;">
                <h3 style="color: #9f1239; display: flex; align-items: center; gap: 8px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Confirm Student Deletion
                </h3>
                <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.95rem; color: #334155; margin-top: 0;">
                    Are you sure you want to delete the student record for:
                </p>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 1.05rem;" id="delStudentName">...</div>
                    <div style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">Roll Number: <strong id="delStudentRoll">...</strong></div>
                </div>

                <div style="font-size: 0.88rem; color: #dc2626; line-height: 1.5;">
                    Warning: This action will permanently delete the student record from the database.
                </div>
            </div>
            <div class="modal-footer" style="justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>

                <form method="POST" action="practical11_students.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="student_id" id="delStudentId" value="">
                    <button type="submit" class="btn btn-danger">
                        Delete Student
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddStudentModal() {
            document.getElementById('modalTitle').textContent = 'Add New Student';
            document.getElementById('formAction').value = 'create';
            document.getElementById('formStudentId').value = '';
            document.getElementById('studentCrudForm').reset();
            document.getElementById('btnSubmitForm').textContent = 'Save Student Record';
            document.getElementById('studentFormModal').classList.add('active');
        }

        function openEditStudentModal(studentId) {
            document.getElementById('modalTitle').textContent = 'Edit Student Record';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formStudentId').value = studentId;
            document.getElementById('btnSubmitForm').textContent = 'Update Student Record';

            fetch('practical11_students.php?ajax=get_student&id=' + studentId)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.student) {
                        const s = data.student;
                        document.getElementById('inputRollNo').value     = s.roll_no;
                        document.getElementById('inputFullName').value   = s.full_name;
                        document.getElementById('inputEmail').value      = s.email;
                        document.getElementById('inputPhone').value      = s.phone;
                        document.getElementById('inputDepartment').value = s.department;
                        document.getElementById('inputCourse').value     = s.course || s.department;
                        document.getElementById('inputSemester').value   = s.semester;
                        document.getElementById('inputStatus').value     = s.status;
                        document.getElementById('studentFormModal').classList.add('active');
                    } else {
                        alert('Failed to load student details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error communicating with database.');
                });
        }

        function closeStudentModal() {
            document.getElementById('studentFormModal').classList.remove('active');
        }

        function viewStudentDetails(studentId) {
            const contentDiv = document.getElementById('detailsContent');
            contentDiv.innerHTML = '<div style="text-align: center; padding: 30px;">Loading profile...</div>';
            document.getElementById('studentDetailsModal').classList.add('active');

            fetch('practical11_students.php?ajax=get_student&id=' + studentId)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.student) {
                        const s = data.student;
                        let regHtml = '<div style="color: #64748b; font-size: 0.85rem; font-style: italic;">No registered campus events.</div>';
                        if (s.registrations && s.registrations.length > 0) {
                            regHtml = '<ul style="margin: 6px 0; padding-left: 18px; font-size: 0.88rem;">' +
                                s.registrations.map(r => `<li><strong>${r.title}</strong> (${r.category}) - Ticket: <code>${r.ticket_code}</code> [${r.attendance_status}]</li>`).join('') +
                                '</ul>';
                        }

                        contentDiv.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">
                                <div style="width: 54px; height: 54px; border-radius: 50%; background: #2563eb; color: #fff; font-size: 1.3rem; font-weight: 800; display: flex; align-items: center; justify-content: center;">
                                    ${s.full_name.charAt(0)}
                                </div>
                                <div>
                                    <h4 style="margin: 0; font-size: 1.2rem; color: #0f172a; font-family: 'Outfit', sans-serif;">${s.full_name}</h4>
                                    <div style="font-size: 0.86rem; color: #64748b; margin-top: 2px;">
                                        Roll No: <span class="roll-badge">${s.roll_no}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-row"><div class="detail-label">Email:</div><div class="detail-val">${s.email}</div></div>
                            <div class="detail-row"><div class="detail-label">Phone:</div><div class="detail-val">${s.phone}</div></div>
                            <div class="detail-row"><div class="detail-label">Department:</div><div class="detail-val">${s.department}</div></div>
                            <div class="detail-row"><div class="detail-label">Course:</div><div class="detail-val">${s.course || s.department}</div></div>
                            <div class="detail-row"><div class="detail-label">Semester:</div><div class="detail-val">Semester ${s.semester}</div></div>
                            <div class="detail-row"><div class="detail-label">Account Status:</div><div class="detail-val"><span class="badge badge-${s.status.toLowerCase()}">${s.status}</span></div></div>
                            <div class="detail-row"><div class="detail-label">Registered On:</div><div class="detail-val">${s.created_at}</div></div>
                            <div style="margin-top: 18px;">
                                <div style="font-weight: 700; color: #0f172a; margin-bottom: 6px; font-size: 0.88rem;">Event Registrations:</div>
                                ${regHtml}
                            </div>
                        `;
                    } else {
                        contentDiv.innerHTML = '<div style="color: #dc2626; padding: 20px;">Could not load student profile.</div>';
                    }
                })
                .catch(err => {
                    contentDiv.innerHTML = '<div style="color: #dc2626; padding: 20px;">Network or database error.</div>';
                });
        }

        function closeDetailsModal() {
            document.getElementById('studentDetailsModal').classList.remove('active');
        }

        function confirmDeleteStudent(studentId, fullName, rollNo) {
            document.getElementById('delStudentId').value = studentId;
            document.getElementById('delStudentName').textContent = fullName;
            document.getElementById('delStudentRoll').textContent = rollNo;
            document.getElementById('deleteConfirmModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').classList.remove('active');
        }

        window.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const m = params.get('modal');
            if (m === 'add') {
                openAddStudentModal();
                if (params.get('demo') === '1') {
                    document.getElementById('inputRollNo').value = '24BCE1199';
                    document.getElementById('inputFullName').value = 'Yashvardhan Singhal';
                    document.getElementById('inputEmail').value = 'yash.singhal@studenthub.edu';
                    document.getElementById('inputPhone').value = '9876543299';
                    document.getElementById('inputDepartment').value = 'Computer Engineering';
                    document.getElementById('inputCourse').value = 'B.Tech Computer Science';
                    document.getElementById('inputSemester').value = '2';
                    document.getElementById('inputStatus').value = 'ACTIVE';
                }
            } else if (m === 'edit' && params.get('id')) {
                openEditStudentModal(params.get('id'));
            } else if (m === 'delete' && params.get('id')) {
                confirmDeleteStudent(params.get('id'), params.get('name') || 'Aarav Sharma', params.get('roll') || '21BCE1001');
            } else if (m === 'view' && params.get('id')) {
                viewStudentDetails(params.get('id'));
            }
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeStudentModal();
                closeDetailsModal();
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
