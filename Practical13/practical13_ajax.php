<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 13: Fetch API &amp; AJAX CRUD without Page Reload</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1050px; margin: 24px auto; padding: 0 16px; }
        .card { background: #fff; padding: 22px; border-radius: var(--radius); border: 1px solid var(--line); box-shadow: var(--shadow); margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px; }
        .form-grid label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 0.85rem; }
        .form-grid input, .form-grid select { width: 100%; max-width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid var(--line); border-radius: 6px; }
        .banner { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 0.9rem; display: none; }
        .banner-ok { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .banner-err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .banner-loading { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
        .btn-action { padding: 4px 10px; font-size: 0.82rem; border-radius: 4px; text-decoration: none; cursor: pointer; display: inline-block; }
        .btn-edit { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .btn-del { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .live-search-box { display: flex; gap: 10px; margin-bottom: 14px; align-items: center; }
        .live-search-box input { flex: 1; padding: 9px; border: 1px solid var(--line); border-radius: 6px; }
        .badge-status { font-weight: bold; font-size: 0.8rem; padding: 2px 8px; border-radius: 999px; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <h1>StudentHub Portal</h1>

    <div style="text-align: center; padding: 10px; background: #fff; border-bottom: 1px solid var(--line);">
        <nav style="display: inline-flex; gap: 14px; justify-content: center;">
            <a href="index.html">Portal Home</a> |
            <a href="practical13_test.php" style="color:var(--gold-deep); font-weight:bold;">Practical 13 API Test &rarr;</a> |
            <a href="practical11_students.php">Practical 11 (Standard CRUD)</a> |
            <a href="login.php">Login</a>
        </nav>
    </div>

    <div class="container">
        
        <div id="loadingBanner" class="banner banner-loading">&#8987; <strong>Loading:</strong> Communicating with PHP JSON API...</div>
        <div id="successBanner" class="banner banner-ok">&#10003; <span id="successText">Action completed successfully.</span></div>
        <div id="errorBanner" class="banner banner-err">&#9888; <span id="errorText">An error occurred.</span></div>

        
        <div class="card">
            <h2 id="formTitle" style="margin-top: 0; text-align: left; background: none; color: var(--navy); padding: 0;">
                Add New Student (via Fetch API)
            </h2>
            <p style="margin-top: -6px; margin-bottom: 14px; font-size: 0.85rem; color: var(--ink-soft); padding: 0;">
                Submits asynchronous POST request to <code>practical13_api.php</code> without full page reload.
            </p>

            <form id="studentForm" onsubmit="handleFormSubmit(event);" style="border:none; box-shadow:none; padding:0; max-width:100%;">
                <input type="hidden" id="student_id" name="student_id" value="">

                <div class="form-grid">
                    <div>
                        <label for="roll_no">Roll Number *</label>
                        <input type="text" id="roll_no" name="roll_no" placeholder="e.g. 24BCE1099" required>
                    </div>
                    <div>
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" placeholder="e.g. Rohan Patel" required>
                    </div>
                    <div>
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" placeholder="rohan@studenthub.edu" required>
                    </div>
                    <div>
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" placeholder="9876543210" required>
                    </div>
                    <div>
                        <label for="department">Department</label>
                        <select id="department" name="department">
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Electronics &amp; Comm">Electronics &amp; Comm</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                        </select>
                    </div>
                    <div>
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester">
                            <option value="1">Semester 1</option>
                            <option value="2">Semester 2</option>
                            <option value="3">Semester 3</option>
                            <option value="4">Semester 4</option>
                            <option value="5">Semester 5</option>
                            <option value="6">Semester 6</option>
                            <option value="7">Semester 7</option>
                            <option value="8">Semester 8</option>
                        </select>
                    </div>
                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" id="btnSubmit">Save Student Record</button>
                    <button type="button" id="btnCancel" onclick="resetFormState();" style="display:none; background:#cbd5e1; color:#0f172a;">Cancel Edit</button>
                </div>
            </form>
        </div>

        
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
                <h2 style="margin:0; background:none; color:var(--navy); padding:0;">
                    Live Students Table (<span id="studentCount">0</span> records)
                </h2>
                <button type="button" onclick="loadStudents();" style="padding:6px 14px; font-size:0.85rem;">
                    &#8635; Refresh Live Data
                </button>
            </div>

            
            <div class="live-search-box">
                <input type="text" id="searchInput" placeholder="Type here to filter students live via Fetch API..." oninput="debounceSearch();">
            </div>

            
            <table style="width: 100%; font-size: 0.88rem;">
                <thead>
                    <tr>
                        <th>Roll No</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Sem</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody">
                    <tr><td colspan="7" style="text-align:center;">Loading records from PHP API...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    
    <script>
        const API_URL = 'practical13_api.php';
        let searchTimer = null;

        
        function showFeedback(type, text) {
            const ok = document.getElementById('successBanner');
            const err = document.getElementById('errorBanner');
            const load = document.getElementById('loadingBanner');

            ok.style.display = 'none';
            err.style.display = 'none';
            load.style.display = (type === 'loading') ? 'block' : 'none';

            if (type === 'ok') {
                document.getElementById('successText').innerText = text;
                ok.style.display = 'block';
                setTimeout(() => ok.style.display = 'none', 4000);
            } else if (type === 'err') {
                document.getElementById('errorText').innerText = text;
                err.style.display = 'block';
            }
        }

        
        async function loadStudents(query = '') {
            showFeedback('loading');
            try {
                const res = await fetch(`${API_URL}?action=list&search=${encodeURIComponent(query)}`);
                const json = await res.json();
                showFeedback('idle');

                if (json.success) {
                    renderTable(json.data);
                    document.getElementById('studentCount').innerText = json.count;
                } else {
                    showFeedback('err', json.message);
                }
            } catch (err) {
                showFeedback('err', 'Network error while fetching students: ' + err.message);
            }
        }

        
        function renderTable(list) {
            const tbody = document.getElementById('studentTableBody');
            if (!list || list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#64748b;">No matching student records found.</td></tr>';
                return;
            }

            let html = '';
            list.forEach(s => {
                const badgeClass = (s.status === 'ACTIVE') ? 'badge-active' : 'badge-inactive';
                html += `
                    <tr id="row-${s.student_id}">
                        <td><strong>${escapeHtml(s.roll_no)}</strong></td>
                        <td>${escapeHtml(s.full_name)}</td>
                        <td>${escapeHtml(s.email)}</td>
                        <td>${escapeHtml(s.department)}</td>
                        <td>${s.semester}</td>
                        <td><span class="badge-status ${badgeClass}">${s.status}</span></td>
                        <td>
                            <button type="button" class="btn-action btn-edit" onclick="editStudent(${s.student_id});">Edit</button>
                            <button type="button" class="btn-action btn-del" onclick="deleteStudent(${s.student_id}, '${escapeHtml(s.full_name)}');">Delete</button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        
        async function handleFormSubmit(e) {
            e.preventDefault();
            const id = document.getElementById('student_id').value;
            const action = id ? 'update' : 'create';

            const payload = {
                action: action,
                student_id: id,
                roll_no: document.getElementById('roll_no').value.trim(),
                full_name: document.getElementById('full_name').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                department: document.getElementById('department').value,
                semester: document.getElementById('semester').value,
                status: document.getElementById('status').value
            };

            showFeedback('loading');
            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if (json.success) {
                    showFeedback('ok', json.message);
                    resetFormState();
                    loadStudents(); 
                } else {
                    let errMsg = json.message;
                    if (json.errors) {
                        errMsg += ' (' + Object.values(json.errors).join(', ') + ')';
                    }
                    showFeedback('err', errMsg);
                }
            } catch (err) {
                showFeedback('err', 'Failed to save student: ' + err.message);
            }
        }

        
        async function editStudent(id) {
            showFeedback('loading');
            try {
                const res = await fetch(`${API_URL}?action=get&id=${id}`);
                const json = await res.json();
                showFeedback('idle');

                if (json.success && json.data) {
                    const s = json.data;
                    document.getElementById('student_id').value = s.student_id;
                    document.getElementById('roll_no').value = s.roll_no;
                    document.getElementById('full_name').value = s.full_name;
                    document.getElementById('email').value = s.email;
                    document.getElementById('phone').value = s.phone;
                    document.getElementById('department').value = s.department;
                    document.getElementById('semester').value = s.semester;
                    document.getElementById('status').value = s.status;

                    document.getElementById('formTitle').innerText = `Edit Student #${s.student_id} (${s.full_name})`;
                    document.getElementById('btnSubmit').innerText = 'Update Student Record';
                    document.getElementById('btnCancel').style.display = 'inline-block';

                    window.scrollTo({ top: 120, behavior: 'smooth' });
                } else {
                    showFeedback('err', json.message || 'Unable to fetch student details.');
                }
            } catch (err) {
                showFeedback('err', 'Error fetching record: ' + err.message);
            }
        }

        
        async function deleteStudent(id, name) {
            if (!confirm(`Are you sure you want to delete student "${name}"?`)) return;

            showFeedback('loading');
            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', student_id: id })
                });
                const json = await res.json();

                if (json.success) {
                    showFeedback('ok', json.message);
                    
                    const row = document.getElementById(`row-${id}`);
                    if (row) row.remove();
                    
                    const curCount = parseInt(document.getElementById('studentCount').innerText, 10);
                    if (!isNaN(curCount) && curCount > 0) {
                        document.getElementById('studentCount').innerText = curCount - 1;
                    }
                } else {
                    showFeedback('err', json.message);
                }
            } catch (err) {
                showFeedback('err', 'Delete failed: ' + err.message);
            }
        }

        
        function resetFormState() {
            document.getElementById('studentForm').reset();
            document.getElementById('student_id').value = '';
            document.getElementById('formTitle').innerText = 'Add New Student (via Fetch API)';
            document.getElementById('btnSubmit').innerText = 'Save Student Record';
            document.getElementById('btnCancel').style.display = 'none';
        }

        
        function debounceSearch() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                const q = document.getElementById('searchInput').value.trim();
                loadStudents(q);
            }, 300);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.innerText = text ?? '';
            return div.innerHTML;
        }

        
        window.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const searchParam = params.get('search');
            const action = params.get('action');
            const feedback = params.get('feedback');

            if (searchParam) {
                document.getElementById('searchInput').value = searchParam;
                loadStudents(searchParam);
            } else {
                loadStudents();
            }

            if (action === 'add' && params.get('demo') === '1') {
                document.getElementById('roll_no').value = '24BCE199';
                document.getElementById('full_name').value = 'Sameer Kulkarni';
                document.getElementById('email').value = 'sameer.k@studenthub.edu';
                document.getElementById('phone').value = '9876543999';
                document.getElementById('department').value = 'Computer Engineering';
                document.getElementById('semester').value = '3';
                document.getElementById('status').value = 'ACTIVE';
            } else if (action === 'edit' && params.get('id')) {
                setTimeout(() => editStudent(params.get('id')), 250);
            }

            if (feedback === 'add_success') {
                showFeedback('ok', "Student 'Sameer Kulkarni' added successfully via Fetch API without page reload!");
            } else if (feedback === 'update_success') {
                showFeedback('ok', "Student record #1 updated successfully via Fetch API without page reload!");
            } else if (feedback === 'delete_success') {
                showFeedback('ok', "Student record #25 deleted successfully via Fetch API (DOM row removed without reload)!");
            }
        });
    </script>

</body>
</html>
