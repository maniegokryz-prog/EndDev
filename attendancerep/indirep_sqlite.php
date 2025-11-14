<?php
/**
 * Individual DTR Report - Reads from Kiosk SQLite Database
 * Displays time in/out records with month/year filters and CSV export
 */

session_start();
require '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

// Get employee ID from URL
$employee_id = $_GET['id'] ?? null;
$selected_month = $_GET['month'] ?? date('Y-m');

if (!$employee_id) {
    die("Employee ID is required");
}

// Get employee info from MySQL
$employee_query = "SELECT id, employee_id, first_name, last_name, department, position, profile_picture 
                  FROM employees 
                  WHERE id = ?";
$stmt = $conn->prepare($employee_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee_result = $stmt->get_result();

if ($employee_result->num_rows === 0) {
    die("Employee not found");
}

$employee = $employee_result->fetch_assoc();
$stmt->close();
$conn->close();

// Profile picture path
$profile_picture = !empty($employee['profile_picture']) 
    ? '../staffmanagement/assets/profile_pic/' . basename($employee['profile_picture'])
    : '../assets/img/default-avatar.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Individual DTR Report - <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="attendancerep.css">
    <style>
        .employee-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .employee-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .badge-complete { background-color: #28a745; }
        .badge-incomplete { background-color: #ffc107; color: #000; }
        .badge-absent { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h2 class="mb-4">
                    <i class="fas fa-clock"></i> Individual Daily Time Record
                </h2>

                <!-- Employee Information Card -->
                <div class="employee-card">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                                 alt="Profile" 
                                 class="employee-photo"
                                 onerror="this.src='../assets/img/default-avatar.png'">
                        </div>
                        <div class="col-md-6">
                            <h4><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h4>
                            <p class="mb-1">
                                <strong>Employee ID:</strong> <?php echo htmlspecialchars($employee['employee_id']); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Department:</strong> <?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?>
                            </p>
                            <p class="mb-0">
                                <strong>Position:</strong> <?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h6>Summary Statistics</h6>
                                <div id="stats-summary">
                                    <div class="d-flex justify-content-between">
                                        <span>Total Days:</span>
                                        <strong id="stat-total">--</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>On Time:</span>
                                        <strong id="stat-ontime">--</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Late:</span>
                                        <strong id="stat-late">--</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="monthFilter" class="form-label">Month</label>
                                <select id="monthFilter" class="form-select">
                                    <option value="01">January</option>
                                    <option value="02">February</option>
                                    <option value="03">March</option>
                                    <option value="04">April</option>
                                    <option value="05">May</option>
                                    <option value="06">June</option>
                                    <option value="07">July</option>
                                    <option value="08">August</option>
                                    <option value="09">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="yearFilter" class="form-label">Year</label>
                                <select id="yearFilter" class="form-select">
                                    <option value="2023">2023</option>
                                    <option value="2024">2024</option>
                                    <option value="2025">2025</option>
                                    <option value="2026">2026</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button id="loadReportBtn" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> <span id="btnText">Load Report</span>
                                </button>
                            </div>
                            <div class="col-md-3 text-end">
                                <button id="exportBtn" class="btn btn-success w-100">
                                    <i class="fas fa-file-csv"></i> Export to CSV
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Records Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Attendance Records</h5>
                    </div>
                    <div class="card-body">
                        <div id="loadingIndicator" class="loading">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading attendance records from kiosk database...</p>
                        </div>

                        <div id="emptyState" class="empty-state" style="display: none;">
                            <i class="fas fa-calendar-times fa-4x mb-3"></i>
                            <h5>No Records Found</h5>
                            <p>No attendance records found for the selected period.</p>
                        </div>

                        <div id="recordsTable" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Day</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Scheduled</th>
                                            <th>Actual</th>
                                            <th>Late (min)</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendanceTableBody">
                                        <!-- Records will be inserted here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const employeeId = <?php echo $employee_id; ?>;
        let currentRecords = [];

        // Set current month/year on page load
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const currentMonth = String(now.getMonth() + 1).padStart(2, '0');
            const currentYear = now.getFullYear();
            
            document.getElementById('monthFilter').value = currentMonth;
            document.getElementById('yearFilter').value = currentYear;
            
            // Load data immediately
            loadAttendanceData();
        });

        // Load attendance data from SQLite via API
        async function loadAttendanceData() {
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;
            const monthFilter = `${year}-${month}`;
            
            console.log('Loading data for:', monthFilter); // Debug log
            
            // Disable button and show loading state
            const btn = document.getElementById('loadReportBtn');
            const btnText = document.getElementById('btnText');
            btn.disabled = true;
            btnText.textContent = 'Loading...';
            
            // Show loading
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('recordsTable').style.display = 'none';
            
            try {
                const url = `api/get_attendance_from_sqlite.php?id=${employeeId}&month=${monthFilter}`;
                console.log('Fetching from:', url); // Debug log
                
                const response = await fetch(url);
                const data = await response.json();
                
                console.log('Response:', data); // Debug log
                
                if (data.success) {
                    currentRecords = data.data;
                    
                    // Update statistics
                    document.getElementById('stat-total').textContent = data.statistics.total_days;
                    document.getElementById('stat-ontime').textContent = data.statistics.on_time;
                    document.getElementById('stat-late').textContent = data.statistics.late;
                    
                    // Populate table
                    const tbody = document.getElementById('attendanceTableBody');
                    tbody.innerHTML = '';
                    
                    if (currentRecords.length === 0) {
                        document.getElementById('emptyState').style.display = 'block';
                    } else {
                        currentRecords.forEach(record => {
                            const row = createTableRow(record);
                            tbody.appendChild(row);
                        });
                        document.getElementById('recordsTable').style.display = 'block';
                    }
                } else {
                    console.error('API Error:', data.message); // Debug log
                    alert('Error: ' + data.message);
                    document.getElementById('emptyState').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading attendance data:', error);
                alert('Failed to load attendance data. Please check console for details.');
                document.getElementById('emptyState').style.display = 'block';
            } finally {
                document.getElementById('loadingIndicator').style.display = 'none';
                btn.disabled = false;
                btnText.textContent = 'Load Report';
            }
        }

        // Create table row from record
        function createTableRow(record) {
            const tr = document.createElement('tr');
            
            // Status badge color
            let statusBadge = 'badge-incomplete';
            if (record.status === 'complete') statusBadge = 'badge-complete';
            if (record.status === 'absent') statusBadge = 'badge-absent';
            
            tr.innerHTML = `
                <td>${record.date_formatted}</td>
                <td>${record.day_of_week}</td>
                <td>${record.time_in_formatted}</td>
                <td>${record.time_out_formatted}</td>
                <td>${record.scheduled_hours_display}</td>
                <td>${record.actual_hours_display}</td>
                <td>${record.late_minutes || 0}</td>
                <td><span class="badge ${statusBadge}">${record.status || 'N/A'}</span></td>
                <td>${record.notes || '--'}</td>
            `;
            
            return tr;
        }

        // Export to CSV
        function exportToCSV() {
            if (currentRecords.length === 0) {
                alert('No records to export');
                return;
            }
            
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;
            const employeeName = '<?php echo addslashes($employee['first_name'] . ' ' . $employee['last_name']); ?>';
            
            // CSV Headers
            let csv = 'Date,Day,Time In,Time Out,Scheduled Hours,Actual Hours,Late (min),Status,Notes\n';
            
            // CSV Rows
            currentRecords.forEach(record => {
                csv += `"${record.date_formatted}",`;
                csv += `"${record.day_of_week}",`;
                csv += `"${record.time_in_formatted}",`;
                csv += `"${record.time_out_formatted}",`;
                csv += `"${record.scheduled_hours_display}",`;
                csv += `"${record.actual_hours_display}",`;
                csv += `"${record.late_minutes || 0}",`;
                csv += `"${record.status || 'N/A'}",`;
                csv += `"${(record.notes || '--').replace(/"/g, '""')}"\n`;
            });
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `DTR_${employeeName.replace(/\s+/g, '_')}_${year}-${month}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Event listeners
        document.getElementById('loadReportBtn').addEventListener('click', loadAttendanceData);
        document.getElementById('exportBtn').addEventListener('click', exportToCSV);
        
        // Optional: Auto-reload when dropdowns change (remove if you only want manual load)
        document.getElementById('monthFilter').addEventListener('change', function() {
            // Auto-load is disabled, user must click "Load Report" button
            // Uncomment the line below to enable auto-load on change:
            // loadAttendanceData();
        });
        
        document.getElementById('yearFilter').addEventListener('change', function() {
            // Auto-load is disabled, user must click "Load Report" button
            // Uncomment the line below to enable auto-load on change:
            // loadAttendanceData();
        });
    </script>
</body>
</html>
