<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$mysqli = getMySQLiConnection(true);
if (!$mysqli) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$input = array_merge($_GET, $_POST, json_decode(file_get_contents('php://input'), true) ?? []);
$action = $input['action'] ?? 'list';

if ($action === 'list') {
    $search = trim($input['search'] ?? '');
    $where = '';
    if (!empty($search)) {
        $safe = $mysqli->real_escape_string($search);
        $where = "WHERE full_name LIKE '%{$safe}%' OR roll_no LIKE '%{$safe}%' OR email LIKE '%{$safe}%'";
    }
    $res = $mysqli->query("SELECT student_id, roll_no, full_name, email, department, semester, phone, status FROM students {$where} ORDER BY student_id DESC");
    $data = [];
    while ($r = $res->fetch_assoc()) { $data[] = $r; }
    echo json_encode(['success' => true, 'count' => count($data), 'data' => $data]);
    exit;
}

if ($action === 'create') {
    $roll = strtoupper(trim($input['roll_no'] ?? ''));
    $name = trim($input['full_name'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));
    $phone = trim($input['phone'] ?? '');
    $dept = trim($input['department'] ?? 'Computer Engineering');
    $sem = (int)($input['semester'] ?? 1);

    if (empty($roll) || empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Roll number, name, and email are required.']);
        exit;
    }

    $res = saveStudentRecord($mysqli, [
        'roll_no' => $roll, 'full_name' => $name, 'email' => $email,
        'phone' => $phone, 'department' => $dept, 'course' => $dept,
        'semester' => $sem, 'status' => 'ACTIVE'
    ]);
    echo json_encode($res);
    exit;
}

if ($action === 'update') {
    $id = (int)($input['student_id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid student ID.']);
        exit;
    }
    $res = saveStudentRecord($mysqli, [
        'roll_no' => strtoupper(trim($input['roll_no'] ?? '')),
        'full_name' => trim($input['full_name'] ?? ''),
        'email' => strtolower(trim($input['email'] ?? '')),
        'phone' => trim($input['phone'] ?? ''),
        'department' => trim($input['department'] ?? ''),
        'course' => trim($input['department'] ?? ''),
        'semester' => (int)($input['semester'] ?? 1),
        'status' => 'ACTIVE'
    ], $id);
    echo json_encode($res);
    exit;
}

if ($action === 'delete') {
    $id = (int)($input['student_id'] ?? 0);
    $res = deleteStudentRecord($mysqli, $id);
    echo json_encode($res);
    exit;
}

if ($action === 'list_events') {
    $res = $mysqli->query("SELECT * FROM events ORDER BY event_date ASC");
    $data = [];
    while ($r = $res->fetch_assoc()) { $data[] = $r; }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
