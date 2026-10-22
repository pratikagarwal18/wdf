<?php


header('Content-Type: application/json; charset=utf-8');


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$mysqli = getMySQLiConnection(true);

if (!$mysqli) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Ensure MySQL is running in XAMPP.'
    ]);
    exit;
}

ensureStudentsTableReady($mysqli);


$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$requestData = array_merge($_GET, $_POST, $jsonInput);

$action = $requestData['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'list' : '');




if ($action === 'list') {
    $search = trim($requestData['search'] ?? '');
    $dept   = trim($requestData['department'] ?? '');

    $where = [];
    $types = '';
    $params = [];

    if (!empty($search)) {
        $searchWild = "%{$search}%";
        $where[] = "(full_name LIKE ? OR roll_no LIKE ? OR email LIKE ?)";
        $types .= 'sss';
        $params[] = $searchWild;
        $params[] = $searchWild;
        $params[] = $searchWild;
    }

    if (!empty($dept)) {
        $where[] = "department = ?";
        $types .= 's';
        $params[] = $dept;
    }

    $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT student_id, roll_no, full_name, email, department, semester, phone, status, created_at 
            FROM students {$whereSql} 
            ORDER BY student_id DESC";

    $stmt = $mysqli->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $students = [];
    while ($row = $res->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'count'   => count($students),
        'data'    => $students
    ]);
    exit;
}




if ($action === 'get') {
    $id = (int)($requestData['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid student ID.']);
        exit;
    }

    $student = getStudentRecord($mysqli, $id);
    if ($student) {
        echo json_encode(['success' => true, 'data' => $student]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student record not found.']);
    }
    exit;
}




if ($action === 'create') {
    $rollNo   = strtoupper(trim($requestData['roll_no'] ?? ''));
    $fullName = trim($requestData['full_name'] ?? '');
    $email    = strtolower(trim($requestData['email'] ?? ''));
    $phone    = trim($requestData['phone'] ?? '');
    $dept     = trim($requestData['department'] ?? 'Computer Engineering');
    $course   = trim($requestData['course'] ?? $dept);
    $semester = (int)($requestData['semester'] ?? 1);
    $status   = in_array($requestData['status'] ?? '', ['ACTIVE', 'INACTIVE']) ? $requestData['status'] : 'ACTIVE';

    
    $errors = [];
    if (empty($rollNo))   $errors['roll_no'] = 'Roll Number is required.';
    if (empty($fullName)) $errors['full_name'] = 'Full Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid Email is required.';
    if (empty($phone))    $errors['phone'] = 'Phone number is required.';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
        exit;
    }

    $saveData = [
        'roll_no'    => $rollNo,
        'full_name'  => $fullName,
        'email'      => $email,
        'phone'      => $phone,
        'department' => $dept,
        'course'     => $course,
        'semester'   => $semester,
        'status'     => $status
    ];

    $res = saveStudentRecord($mysqli, $saveData, null);
    if ($res['success']) {
        http_response_code(201);
        echo json_encode([
            'success'    => true,
            'message'    => "Student '{$fullName}' added successfully!",
            'student_id' => $res['student_id']
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $res['message'], 'errors' => $res['errors'] ?? []]);
    }
    exit;
}




if ($action === 'update') {
    $id = (int)($requestData['student_id'] ?? $requestData['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required for update.']);
        exit;
    }

    $existing = getStudentRecord($mysqli, $id);
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student record not found.']);
        exit;
    }

    $rollNo   = strtoupper(trim($requestData['roll_no'] ?? $existing['roll_no']));
    $fullName = trim($requestData['full_name'] ?? $existing['full_name']);
    $email    = strtolower(trim($requestData['email'] ?? $existing['email']));
    $phone    = trim($requestData['phone'] ?? $existing['phone']);
    $dept     = trim($requestData['department'] ?? $existing['department']);
    $course   = trim($requestData['course'] ?? $dept);
    $semester = (int)($requestData['semester'] ?? $existing['semester']);
    $status   = in_array($requestData['status'] ?? '', ['ACTIVE', 'INACTIVE']) ? $requestData['status'] : $existing['status'];

    $saveData = [
        'roll_no'    => $rollNo,
        'full_name'  => $fullName,
        'email'      => $email,
        'phone'      => $phone,
        'department' => $dept,
        'course'     => $course,
        'semester'   => $semester,
        'status'     => $status
    ];

    $res = saveStudentRecord($mysqli, $saveData, $id);
    if ($res['success']) {
        echo json_encode([
            'success'    => true,
            'message'    => "Student '{$fullName}' updated successfully!",
            'student_id' => $id
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $res['message'], 'errors' => $res['errors'] ?? []]);
    }
    exit;
}




if ($action === 'delete') {
    $id = (int)($requestData['student_id'] ?? $requestData['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid Student ID is required for deletion.']);
        exit;
    }

    $res = deleteStudentRecord($mysqli, $id);
    if ($res['success']) {
        echo json_encode(['success' => true, 'message' => $res['message']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $res['message']]);
    }
    exit;
}


http_response_code(400);
echo json_encode([
    'success' => false,
    'message' => "Unknown action '{$action}'. Supported actions: list, get, create, update, delete."
]);
exit;
