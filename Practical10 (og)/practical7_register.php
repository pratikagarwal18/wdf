<?php
/**
 * Practical 7: PHP Form Processing with Server-Side Validation and JSON Storage
 * Target Format: JSON
 * Advanced Extension: CSRF Protection
 * Intermediate Extension: Linked to view_records.php
 */

session_start();

// 1. Generate CSRF token if not already present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Path to JSON storage file
$dataDir = __DIR__ . '/data';
$jsonFile = $dataDir . '/submissions.json';

// Ensure data folder exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Variables for sticky form and messages
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

// Helper function to sanitize input strings
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

// 2. Handle POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Advanced Extension: CSRF Token Check (with graceful session recovery)
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errors['csrf'] = 'Session security token expired. Please re-submit the form.';
    }

    // 3. Sanitize all submitted inputs
    $formData['fullName']   = isset($_POST['fullName']) ? sanitize_input($_POST['fullName']) : '';
    $formData['enrollment'] = isset($_POST['enrollment']) ? sanitize_input($_POST['enrollment']) : '';
    $formData['email']      = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $formData['mobile']     = isset($_POST['mobile']) ? sanitize_input($_POST['mobile']) : '';
    $formData['course']     = isset($_POST['course']) ? sanitize_input($_POST['course']) : '';
    $formData['year']       = isset($_POST['year']) ? sanitize_input($_POST['year']) : '';
    $formData['gender']     = isset($_POST['gender']) ? sanitize_input($_POST['gender']) : '';
    $formData['dob']        = isset($_POST['dob']) ? sanitize_input($_POST['dob']) : '';
    $formData['address']    = isset($_POST['address']) ? sanitize_input($_POST['address']) : '';

    $password        = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';
    $termsAccepted   = isset($_POST['terms']);

    // 4. Server-Side Validation Rules

    // Full Name: required, letters, spaces, dots, hyphens (2 to 60 characters)
    if (empty($formData['fullName'])) {
        $errors['fullName'] = 'Full Name is required.';
    } elseif (!preg_match("/^[a-zA-Z\s\.\-]{2,60}$/", $formData['fullName'])) {
        $errors['fullName'] = 'Full Name must contain letters and spaces (min 2 characters).';
    }

    // Enrollment ID: required, 3 to 20 alphanumeric characters
    if (empty($formData['enrollment'])) {
        $errors['enrollment'] = 'Enrollment ID is required.';
    } elseif (!preg_match("/^[A-Za-z0-9_-]{3,20}$/", $formData['enrollment'])) {
        $errors['enrollment'] = 'Enrollment ID must be 3 to 20 characters (alphanumeric).';
    }

    // Email Address: required, validated via filter_var
    if (empty($formData['email'])) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address (e.g., student@univ.edu).';
    }

    // Mobile Number: required, 10 digits
    $cleanMobile = preg_replace('/[^0-9]/', '', $formData['mobile']);
    if (empty($formData['mobile'])) {
        $errors['mobile'] = 'Mobile number is required.';
    } elseif (strlen($cleanMobile) !== 10) {
        $errors['mobile'] = 'Mobile number must be exactly 10 digits.';
    }

    // Password: required, min 6 characters for testing flexibility
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters long.';
    }

    // Confirm Password: must match password
    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match.';
    }

    // Course / Branch: whitelist check
    $validCourses = ['Computer Science', 'Information Technology', 'Electronics', 'Mechanical', 'Civil'];
    if (empty($formData['course'])) {
        $errors['course'] = 'Please select your course / branch.';
    } elseif (!in_array($formData['course'], $validCourses, true)) {
        $errors['course'] = 'Invalid course selected.';
    }

    // Year of Study: whitelist check
    $validYears = ['1', '2', '3', '4'];
    if (empty($formData['year'])) {
        $errors['year'] = 'Please select your year of study.';
    } elseif (!in_array($formData['year'], $validYears, true)) {
        $errors['year'] = 'Invalid year selected.';
    }

    // Gender: whitelist check
    $validGenders = ['Male', 'Female', 'Other'];
    if (empty($formData['gender'])) {
        $errors['gender'] = 'Please select your gender.';
    } elseif (!in_array($formData['gender'], $validGenders, true)) {
        $errors['gender'] = 'Invalid gender option.';
    }

    // Terms & Conditions Checkbox
    if (!$termsAccepted) {
        $errors['terms'] = 'You must check the box to accept terms and conditions.';
    }

    // 5. If No Errors -> Safe JSON Storage
    if (empty($errors)) {

        // Build structured record for JSON
        $newRecord = [
            'id'            => uniqid('STU_'),
            'timestamp'     => date('Y-m-d H:i:s'),
            'full_name'     => $formData['fullName'],
            'enrollment_id' => strtoupper($formData['enrollment']),
            'email'         => strtolower($formData['email']),
            'mobile'        => $cleanMobile,
            'course'        => $formData['course'],
            'year'          => $formData['year'],
            'gender'        => $formData['gender'],
            'dob'           => $formData['dob'] ? $formData['dob'] : null,
            'address'       => $formData['address'] ? $formData['address'] : null
        ];

        // Step A: Read existing JSON records if file exists
        $existingRecords = [];
        if (file_exists($jsonFile) && filesize($jsonFile) > 0) {
            $jsonString = file_get_contents($jsonFile);
            $decoded = json_decode($jsonString, true);
            if (is_array($decoded)) {
                $existingRecords = $decoded;
            }
        }

        // Step B: Append the new record
        $existingRecords[] = $newRecord;

        // Step C: Encode back to JSON with indentation (pretty print)
        $encodedJson = json_encode($existingRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Step D: Write safely to file with exclusive lock (LOCK_EX)
        if (file_put_contents($jsonFile, $encodedJson, LOCK_EX) !== false) {
            $successMessage = "Registration successful! Record has been validated and safely stored in JSON format.";

            // Reset form fields
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

            // Regenerate CSRF token for next submission
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $errors['file'] = "Error writing record to JSON file. Check file write permissions on the data folder.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 7: PHP Form Processing & JSON Storage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .banner {
            max-width: 620px;
            margin: 16px auto 0;
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 0.95rem;
            line-height: 1.45;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .banner.success {
            background-color: #ecfdf3;
            color: #027a48;
            border: 2px solid #a6f4c5;
        }
        .banner.error {
            background-color: #fef3f2;
            color: #b42318;
            border: 2px solid #fecdca;
        }
        .banner ul {
            margin: 8px 0 0;
            padding-left: 22px;
        }
        .banner li {
            margin: 4px 0;
            font-weight: 500;
        }
        .header-nav {
            max-width: 620px;
            margin: 15px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 4px;
        }
        .badge {
            display: inline-block;
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            padding: 3px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: var(--blue);
            margin-left: 6px;
        }
        .storage-indicator {
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 18px;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .is-invalid {
            border-color: #b42318 !important;
            background-color: #fffbfa !important;
            box-shadow: 0 0 0 2px rgba(180, 35, 24, 0.15) !important;
        }
        .error-hint {
            color: #b42318;
            font-size: 0.82rem;
            margin-top: 4px;
            display: block;
            font-weight: 600;
        }
        .btn-submit {
            background: linear-gradient(135deg, #1c2541 0%, #3547d6 100%) !important;
            color: #ffffff !important;
            padding: 12px 28px !important;
            font-size: 1rem !important;
            font-weight: 700 !important;
            border: none !important;
            border-radius: 999px !important;
            cursor: pointer !important;
            box-shadow: 0 4px 12px rgba(53, 71, 214, 0.35) !important;
            transition: transform 0.15s ease, box-shadow 0.15s ease !important;
        }
        .btn-submit:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 16px rgba(53, 71, 214, 0.5) !important;
        }
    </style>
</head>
<body>

    <h1>Student Portal</h1>
    <hr>

    <div class="header-nav">
        <h2>Practical 7: Student Registration Form <span class="badge">PHP + JSON Storage</span></h2>
    </div>

    <!-- Success Message Banner -->
    <?php if (!empty($successMessage)): ?>
        <div id="feedbackBanner" class="banner success" role="alert">
            <strong>✓ Registration Successful!</strong>
            <p style="margin: 6px 0 0;"><?php echo htmlspecialchars($successMessage); ?></p>
            <div style="margin-top: 10px;">
                <a href="view_records.php" style="font-weight: 700; color: #027a48; text-decoration: underline; font-size: 1rem;">
                    View Stored JSON Records Page (view_records.php) →
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Validation Error Banner -->
    <?php if (!empty($errors)): ?>
        <div id="feedbackBanner" class="banner error" role="alert">
            <strong>⚠ Form Submission Failed: Please fix <?php echo count($errors); ?> error(s) below:</strong>
            <ul>
                <?php foreach ($errors as $fieldKey => $errMsg): ?>
                    <li><?php echo htmlspecialchars($errMsg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Form: Method=POST, Action=Self -->
    <form id="registerForm" class="auth-form" method="POST" action="practical7_register.php" novalidate>
        
        <!-- Advanced Extension: Hidden CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="storage-indicator">
            <span>Target Storage: <strong>JSON (data/submissions.json)</strong></span>
            <a href="view_records.php" style="font-weight: 600; color: var(--blue);">View Stored Records ↗</a>
        </div>

        <p>Complete this form to submit your details with server-side validation and JSON file storage.</p>

        <!-- Full Name -->
        <div class="field">
            <label for="fullName">Full Name *</label>
            <input 
                id="fullName" 
                name="fullName" 
                type="text" 
                class="<?php echo isset($errors['fullName']) ? 'is-invalid' : ''; ?>"
                value="<?php echo htmlspecialchars($formData['fullName']); ?>" 
                placeholder="e.g. Aarav Sharma" 
                required>
            <?php if (isset($errors['fullName'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['fullName']); ?></span>
            <?php endif; ?>
        </div>

        <!-- Enrollment ID -->
        <div class="field">
            <label for="enrollment">Enrollment ID *</label>
            <input 
                id="enrollment" 
                name="enrollment" 
                type="text" 
                class="<?php echo isset($errors['enrollment']) ? 'is-invalid' : ''; ?>"
                value="<?php echo htmlspecialchars($formData['enrollment']); ?>" 
                placeholder="e.g. 21BCE1001" 
                required>
            <?php if (isset($errors['enrollment'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['enrollment']); ?></span>
            <?php endif; ?>
        </div>

        <!-- Email Address -->
        <div class="field">
            <label for="email">Email Address *</label>
            <input 
                id="email" 
                name="email" 
                type="email" 
                class="<?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                value="<?php echo htmlspecialchars($formData['email']); ?>" 
                placeholder="student@univ.edu" 
                required>
            <?php if (isset($errors['email'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['email']); ?></span>
            <?php endif; ?>
        </div>

        <!-- Mobile Number -->
        <div class="field">
            <label for="mobile">Mobile Number (10 digits) *</label>
            <input 
                id="mobile" 
                name="mobile" 
                type="tel" 
                maxlength="10"
                class="<?php echo isset($errors['mobile']) ? 'is-invalid' : ''; ?>"
                value="<?php echo htmlspecialchars($formData['mobile']); ?>" 
                placeholder="e.g. 9876543210" 
                required>
            <?php if (isset($errors['mobile'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['mobile']); ?></span>
            <?php endif; ?>
        </div>

        <!-- Password -->
        <div class="field">
            <label for="password">Password * <span class="optional">(min 6 characters)</span></label>
            <input 
                id="password" 
                name="password" 
                type="password" 
                class="<?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                placeholder="Create a password" 
                required>
            <?php if (isset($errors['password'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['password']); ?></span>
            <?php endif; ?>
        </div>

        <!-- Confirm Password -->
        <div class="field">
            <label for="confirmPassword">Confirm Password *</label>
            <input 
                id="confirmPassword" 
                name="confirmPassword" 
                type="password" 
                class="<?php echo isset($errors['confirmPassword']) ? 'is-invalid' : ''; ?>"
                placeholder="Re-enter password" 
                required>
            <?php if (isset($errors['confirmPassword'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['confirmPassword']); ?></span>
            <?php endif; ?>
        </div>

        <!-- Course / Branch Dropdown -->
        <div class="field">
            <label for="course">Course / Branch *</label>
            <select id="course" name="course" class="<?php echo isset($errors['course']) ? 'is-invalid' : ''; ?>" required>
                <option value="">Select course</option>
                <?php
                $courses = ['Computer Science', 'Information Technology', 'Electronics', 'Mechanical', 'Civil'];
                foreach ($courses as $c) {
                    $selected = ($formData['course'] === $c) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($c) . "\" $selected>" . htmlspecialchars($c) . "</option>";
                }
                ?>
            </select>
            <?php if (isset($errors['course'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['course']); ?></span>
            <?php endif; ?>
        </div>

        <!-- Year of Study Dropdown -->
        <div class="field">
            <label for="year">Year of Study *</label>
            <select id="year" name="year" class="<?php echo isset($errors['year']) ? 'is-invalid' : ''; ?>" required>
                <option value="">Select year</option>
                <?php
                $years = ['1' => 'First Year', '2' => 'Second Year', '3' => 'Third Year', '4' => 'Fourth Year'];
                foreach ($years as $val => $lbl) {
                    $selected = ($formData['year'] === (string)$val) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($val) . "\" $selected>" . htmlspecialchars($lbl) . "</option>";
                }
                ?>
            </select>
            <?php if (isset($errors['year'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['year']); ?></span>
            <?php endif; ?>
        </div>

        <!-- Gender Radio -->
        <fieldset class="field gender-field <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>">
            <legend>Gender *</legend>
            <label><input type="radio" name="gender" value="Male" <?php echo ($formData['gender'] === 'Male') ? 'checked' : ''; ?>> Male</label>
            <label><input type="radio" name="gender" value="Female" <?php echo ($formData['gender'] === 'Female') ? 'checked' : ''; ?>> Female</label>
            <label><input type="radio" name="gender" value="Other" <?php echo ($formData['gender'] === 'Other') ? 'checked' : ''; ?>> Other</label>
            <?php if (isset($errors['gender'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['gender']); ?></span>
            <?php endif; ?>
        </fieldset>

        <!-- Date of Birth (Optional) -->
        <div class="field">
            <label for="dob">Date of Birth <span class="optional">(optional)</span></label>
            <input id="dob" name="dob" type="date" value="<?php echo htmlspecialchars($formData['dob']); ?>">
        </div>

        <!-- Address (Optional) -->
        <div class="field">
            <label for="address">Address <span class="optional">(optional)</span></label>
            <textarea id="address" name="address" rows="3" placeholder="Enter your residential address"><?php echo htmlspecialchars($formData['address']); ?></textarea>
        </div>

        <!-- Terms and Conditions -->
        <div class="field terms-field">
            <label>
                <input id="terms" name="terms" type="checkbox" required> 
                I accept the terms and conditions and certify the information is true. *
            </label>
            <?php if (isset($errors['terms'])): ?>
                <span class="error-hint"><?php echo htmlspecialchars($errors['terms']); ?></span>
            <?php endif; ?>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn-submit">Submit Registration</button>
            <button type="reset" class="secondary-button" onclick="window.location.href='practical7_register.php'">Reset</button>
            <a href="view_records.php" style="margin-left: auto; align-self: center; font-size: 0.9rem; font-weight: 600;">
                Intermediate Task: View JSON Records ↗
            </a>
        </div>
    </form>

    <script>
        // Automatically scroll to error banner if one exists
        window.addEventListener('DOMContentLoaded', function() {
            var banner = document.getElementById('feedbackBanner');
            if (banner) {
                banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>

    <hr>
    <p>© 2026 Student Portal • Practical 7 Form Processor (JSON Storage)</p>
</body>
</html>
