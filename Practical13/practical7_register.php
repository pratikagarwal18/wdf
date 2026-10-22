<?php
/**
 * Practical 7: PHP Form Processing & JSON/CSV Storage
 * Beginner-level, clean PHP implementation
 */

session_start();

$dataDir = __DIR__ . '/data';
$jsonFile = $dataDir . '/submissions.json';
$csvFile = $dataDir . '/submissions.csv';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$errors = [];
$successMessage = '';
$formData = [
    'fullName'   => '',
    'enrollment' => '',
    'email'      => '',
    'mobile'     => '',
    'course'     => '',
    'year'       => '',
    'gender'     => '',
    'dob'        => '',
    'address'    => ''
];

// Process form when submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $val) {
        $formData[$key] = trim($_POST[$key] ?? '');
    }

    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $terms           = isset($_POST['terms']);

    // Beginner-level validation
    if (empty($formData['fullName'])) {
        $errors['fullName'] = 'Full Name is required.';
    }
    if (empty($formData['enrollment'])) {
        $errors['enrollment'] = 'Enrollment ID is required.';
    }
    if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid Email Address is required.';
    }
    if (empty($formData['mobile']) || strlen(preg_replace('/[^0-9]/', '', $formData['mobile'])) !== 10) {
        $errors['mobile'] = 'A valid 10-digit mobile number is required.';
    }
    if (empty($password) || strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match.';
    }
    if (empty($formData['course'])) {
        $errors['course'] = 'Please select a course.';
    }
    if (empty($formData['year'])) {
        $errors['year'] = 'Please select study year.';
    }
    if (empty($formData['gender'])) {
        $errors['gender'] = 'Please select gender.';
    }
    if (!$terms) {
        $errors['terms'] = 'You must accept the terms and conditions.';
    }

    // Save if no validation errors
    if (empty($errors)) {
        $newRecord = [
            'id'            => 'STU_' . time(),
            'timestamp'     => date('Y-m-d H:i:s'),
            'full_name'     => $formData['fullName'],
            'enrollment_id' => strtoupper($formData['enrollment']),
            'email'         => strtolower($formData['email']),
            'mobile'        => $formData['mobile'],
            'course'        => $formData['course'],
            'year'          => $formData['year'],
            'gender'        => $formData['gender'],
            'dob'           => $formData['dob'],
            'address'       => $formData['address']
        ];

        // Read existing JSON
        $existing = [];
        if (file_exists($jsonFile) && filesize($jsonFile) > 0) {
            $existing = json_decode(file_get_contents($jsonFile), true) ?: [];
        }
        $existing[] = $newRecord;
        file_put_contents($jsonFile, json_encode($existing, JSON_PRETTY_PRINT));

        // Append to CSV
        $isNewCsv = !file_exists($csvFile) || filesize($csvFile) === 0;
        $fp = fopen($csvFile, 'a');
        if ($fp) {
            if ($isNewCsv) {
                fputcsv($fp, ['id', 'timestamp', 'full_name', 'enrollment_id', 'email', 'mobile', 'course', 'year', 'gender', 'dob', 'address']);
            }
            fputcsv($fp, $newRecord);
            fclose($fp);
        }

        $successMessage = "Registration successful! Record has been saved to JSON and CSV files.";
        // Reset form data
        foreach ($formData as $k => $v) $formData[$k] = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 7: Student Registration (JSON Storage)</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 30px auto;
            background: #fff;
            padding: 26px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 9px;
            border: 1px solid var(--line);
            border-radius: 6px;
        }
        .err-text {
            color: #b91c1c;
            font-size: 0.82rem;
            margin-top: 3px;
            display: block;
        }
        .banner {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 0.92rem;
        }
        .banner-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .banner-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    </style>
</head>
<body>

    <h1>StudentHub Portal</h1>

    <div style="text-align: center; padding: 10px; background: #fff; border-bottom: 1px solid var(--line);">
        <nav style="display: inline-flex; gap: 14px; justify-content: center;">
            <a href="index.html">Portal Home</a> |
            <a href="practical6.html">Practical 6 (JSON Explorer)</a> |
            <a href="view_records.php">View Stored Records</a> |
            <a href="login.php">Login</a>
        </nav>
    </div>

    <div class="form-container">
        <h2 style="margin-top: 0; text-align: center;">Practical 7: Student Registration Form</h2>
        <p style="text-align: center; margin-top: -10px; font-size: 0.88rem; color: var(--ink-soft);">
            Demonstrates PHP Form Handling, Input Validation, and JSON File Storage
        </p>

        <?php if ($successMessage): ?>
            <div class="banner banner-success">
                <strong>&#10003; Success!</strong> <?= htmlspecialchars($successMessage) ?><br>
                <a href="view_records.php" style="color: #166534; font-weight: bold;">View Saved Records &rarr;</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="banner banner-error">
                <strong>&#9888; Please correct the errors below:</strong>
            </div>
        <?php endif; ?>

        <form method="POST" action="practical7_register.php" style="border:none; box-shadow:none; padding:0; max-width:100%;">
            <div class="form-group">
                <label for="fullName">Full Name *</label>
                <input type="text" id="fullName" name="fullName" value="<?= htmlspecialchars($formData['fullName']) ?>" placeholder="e.g. Aarav Sharma" required>
                <?php if (isset($errors['fullName'])): ?><span class="err-text"><?= $errors['fullName'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="enrollment">Enrollment ID *</label>
                <input type="text" id="enrollment" name="enrollment" value="<?= htmlspecialchars($formData['enrollment']) ?>" placeholder="e.g. 21BCE1001" required>
                <?php if (isset($errors['enrollment'])): ?><span class="err-text"><?= $errors['enrollment'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>" placeholder="student@univ.edu" required>
                <?php if (isset($errors['email'])): ?><span class="err-text"><?= $errors['email'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="mobile">Mobile Number (10 Digits) *</label>
                <input type="tel" id="mobile" name="mobile" maxlength="10" value="<?= htmlspecialchars($formData['mobile']) ?>" placeholder="9876543210" required>
                <?php if (isset($errors['mobile'])): ?><span class="err-text"><?= $errors['mobile'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password (min 6 characters) *</label>
                <input type="password" id="password" name="password" placeholder="Create password" required>
                <?php if (isset($errors['password'])): ?><span class="err-text"><?= $errors['password'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirmPassword">Confirm Password *</label>
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm password" required>
                <?php if (isset($errors['confirmPassword'])): ?><span class="err-text"><?= $errors['confirmPassword'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="course">Course / Branch *</label>
                <select id="course" name="course" required>
                    <option value="">-- Select Course --</option>
                    <?php 
                    $courses = ['Computer Science', 'Information Technology', 'Electronics', 'Mechanical', 'Civil'];
                    foreach ($courses as $c): ?>
                        <option value="<?= $c ?>" <?= ($formData['course'] === $c) ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['course'])): ?><span class="err-text"><?= $errors['course'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="year">Year of Study *</label>
                <select id="year" name="year" required>
                    <option value="">-- Select Year --</option>
                    <?php
                    $years = ['1' => 'First Year', '2' => 'Second Year', '3' => 'Third Year', '4' => 'Fourth Year'];
                    foreach ($years as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($formData['year'] === (string)$k) ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['year'])): ?><span class="err-text"><?= $errors['year'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label>Gender *</label>
                <label style="display:inline; font-weight:normal;"><input type="radio" name="gender" value="Male" <?= ($formData['gender'] === 'Male') ? 'checked' : '' ?>> Male</label>
                <label style="display:inline; font-weight:normal;"><input type="radio" name="gender" value="Female" <?= ($formData['gender'] === 'Female') ? 'checked' : '' ?>> Female</label>
                <label style="display:inline; font-weight:normal;"><input type="radio" name="gender" value="Other" <?= ($formData['gender'] === 'Other') ? 'checked' : '' ?>> Other</label>
                <?php if (isset($errors['gender'])): ?><span class="err-text"><?= $errors['gender'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="dob">Date of Birth (Optional)</label>
                <input type="date" id="dob" name="dob" value="<?= htmlspecialchars($formData['dob']) ?>">
            </div>

            <div class="form-group">
                <label for="address">Address (Optional)</label>
                <textarea id="address" name="address" rows="2" placeholder="Enter residential address"><?= htmlspecialchars($formData['address']) ?></textarea>
            </div>

            <div class="form-group">
                <label style="font-weight:normal;">
                    <input type="checkbox" name="terms" required> I agree to the terms and certify that the information is accurate. *
                </label>
                <?php if (isset($errors['terms'])): ?><span class="err-text"><?= $errors['terms'] ?></span><?php endif; ?>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 14px;">
                <button type="submit" style="flex:1;">Submit Registration</button>
                <button type="reset" class="secondary-button" onclick="window.location.href='practical7_register.php'">Clear</button>
            </div>
        </form>
    </div>

</body>
</html>
