<?php
/**
 * Test script to verify STRICT monthly leave request limit
 * Version 2.0 - Sequential submission only
 */
require '../db_connection.php';

$test_employee_id = 1; // Change to a real employee ID for testing
$current_month = date('Y-m');

echo "<h2>Monthly Leave Request Limit Test (v2.0 - Strict Mode)</h2>";
echo "<p><strong>Testing for Employee ID:</strong> $test_employee_id</p>";
echo "<p><strong>Current Month:</strong> $current_month</p>";

echo "<hr>";

// RULE 1: Check for pending requests
echo "<h3>🔍 RULE 1: Pending Request Check</h3>";
$sql_pending = "SELECT 
                    el.id,
                    el.start_date,
                    el.end_date,
                    lt.type_name as leave_type
                FROM employee_leaves el
                INNER JOIN leave_types lt ON el.leave_type_id = lt.id
                WHERE el.employee_id = ? 
                AND el.status = 'pending'";

$stmt_pending = $conn->prepare($sql_pending);
$stmt_pending->bind_param("i", $test_employee_id);
$stmt_pending->execute();
$pending_result = $stmt_pending->get_result();
$pending_count = $pending_result->num_rows;

if ($pending_count > 0) {
    echo "<p style='color: red; font-weight: bold;'>❌ BLOCKED - Employee has $pending_count pending request(s)</p>";
    echo "<p><strong>Cannot submit new requests until admin approves or rejects pending request.</strong></p>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Type</th><th>Start Date</th><th>End Date</th></tr>";
    while ($row = $pending_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['leave_type']}</td>";
        echo "<td>{$row['start_date']}</td>";
        echo "<td>{$row['end_date']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green; font-weight: bold;'>✅ PASSED - No pending requests</p>";
}

echo "<hr>";

// RULE 2: Check approved requests this month
echo "<h3>🔍 RULE 2: Monthly Approved Limit Check</h3>";
$sql_approved = "SELECT 
                    el.id,
                    el.start_date,
                    el.end_date,
                    lt.type_name as leave_type
                FROM employee_leaves el
                INNER JOIN leave_types lt ON el.leave_type_id = lt.id
                WHERE el.employee_id = ? 
                AND el.status = 'approved'
                AND (DATE_FORMAT(el.start_date, '%Y-%m') = ? OR DATE_FORMAT(el.end_date, '%Y-%m') = ?)
                ORDER BY el.start_date DESC";

$stmt_approved = $conn->prepare($sql_approved);
$stmt_approved->bind_param("iss", $test_employee_id, $current_month, $current_month);
$stmt_approved->execute();
$approved_result = $stmt_approved->get_result();
$approved_count = $approved_result->num_rows;

$remaining = 2 - $approved_count;

echo "<p><strong>Approved Leaves This Month:</strong> $approved_count of 2</p>";
echo "<p><strong>Remaining Slots:</strong> $remaining</p>";

if ($approved_count >= 2) {
    echo "<p style='color: red; font-weight: bold;'>❌ BLOCKED - Monthly limit of 2 approved leaves reached</p>";
} else {
    echo "<p style='color: green; font-weight: bold;'>✅ PASSED - $remaining approved leave slot(s) available</p>";
}

if ($approved_count > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Type</th><th>Start Date</th><th>End Date</th></tr>";
    while ($row = $approved_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['leave_type']}</td>";
        echo "<td>{$row['start_date']}</td>";
        echo "<td>{$row['end_date']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// RULE 3: Overall validation summary
echo "<h3>📋 FINAL VALIDATION RESULT</h3>";

$can_submit = ($pending_count == 0 && $approved_count < 2);

if ($can_submit) {
    echo "<div style='background: #d4edda; padding: 20px; border: 2px solid #28a745; border-radius: 5px;'>";
    echo "<h4 style='color: #155724; margin-top: 0;'>✅ ALLOWED TO SUBMIT</h4>";
    echo "<p style='color: #155724;'><strong>Employee can submit a new leave request</strong></p>";
    echo "<ul style='color: #155724;'>";
    echo "<li>No pending requests blocking submission</li>";
    echo "<li>$remaining approved leave slot(s) available this month</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border: 2px solid #dc3545; border-radius: 5px;'>";
    echo "<h4 style='color: #721c24; margin-top: 0;'>❌ SUBMISSION BLOCKED</h4>";
    echo "<p style='color: #721c24;'><strong>Employee CANNOT submit new leave request</strong></p>";
    echo "<ul style='color: #721c24;'>";
    if ($pending_count > 0) {
        echo "<li>Has $pending_count pending request(s) - must wait for admin action</li>";
    }
    if ($approved_count >= 2) {
        echo "<li>Monthly limit reached - already has 2 approved leaves this month</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<br><hr>";

// Show all requests for complete picture
echo "<h3>📊 All Leave Requests (Complete History)</h3>";

$sql_all = "SELECT 
                el.id,
                el.start_date,
                el.end_date,
                el.status,
                el.created_at,
                lt.type_name as leave_type,
                DATE_FORMAT(el.start_date, '%Y-%m') as request_month
            FROM employee_leaves el
            INNER JOIN leave_types lt ON el.leave_type_id = lt.id
            WHERE el.employee_id = ?
            ORDER BY el.created_at DESC
            LIMIT 20";

$stmt_all = $conn->prepare($sql_all);
$stmt_all->bind_param("i", $test_employee_id);
$stmt_all->execute();
$result_all = $stmt_all->get_result();

if ($result_all->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Type</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Month</th><th>Created</th></tr>";
    
    while ($row = $result_all->fetch_assoc()) {
        $style = "";
        $indicator = "";
        
        // Highlight current month
        if ($row['request_month'] === $current_month) {
            $style = "background-color: #fff3cd;";
        }
        
        // Color by status
        if ($row['status'] === 'pending') {
            $style .= " font-weight: bold; color: #856404;";
            $indicator = "⏳";
        } elseif ($row['status'] === 'approved') {
            $indicator = "✅";
        } elseif ($row['status'] === 'rejected') {
            $style .= " color: #999; text-decoration: line-through;";
            $indicator = "❌";
        }
        
        echo "<tr style='$style'>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['leave_type']}</td>";
        echo "<td>{$row['start_date']}</td>";
        echo "<td>{$row['end_date']}</td>";
        echo "<td>$indicator <strong>{$row['status']}</strong></td>";
        echo "<td>{$row['request_month']}</td>";
        echo "<td>" . date('M j, Y H:i', strtotime($row['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p style='font-size: 0.9em;'><em>* Yellow highlighted = current month | ⏳ = Pending | ✅ = Approved | ❌ = Rejected</em></p>";
} else {
    echo "<p>No leave requests found for this employee.</p>";
}

echo "<hr>";
echo "<p style='font-size: 0.85em; color: #666;'><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";

$conn->close();
?>
