<?php
require '../db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Leave Approvals - Attendance System</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="settings.css">
  <style>
    .leave-card {
      transition: all 0.3s ease;
      border-left: 4px solid #ffc107;
    }
    .leave-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .badge-days {
      font-size: 0.9rem;
      padding: 0.4rem 0.8rem;
    }
  </style>
</head>

<body>
  <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">
    <div class="menu-toggle">
      <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
    </div>
    <div class="notification">
      <i class="bi bi-bell-fill fs-4 text-warning icon-btn"></i>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column pt-5" id="sidebar">
    <div class="profile text-center p-3 mt-4">
      <img src="pic.png" alt="Placeholder" class="rounded-circle mb-2" width="70">
      <h5 class="mb-0">Kryztian Maniego</h5>
      <small class="role">Admin</small>
    </div>
    <nav class="nav flex-column px-2">
      <a class="nav-link" href="../dashboard/dashboard.php"><i class="bi bi-house-door me-2"></i> Dashboard</a>
      <a class="nav-link" href="../attendancerep/attendancerep.php"><i class="bi bi-file-earmark-bar-graph me-2"></i> Attendance Reports</a>
      <a class="nav-link" href="../staffmanagement/staff.php"><i class="bi bi-people me-2"></i> Staff Management</a>
      <a class="nav-link active" href="../settings/settings.php"><i class="bi bi-gear me-2"></i> Settings</a>
      <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="content pt-3" id="content">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold display-4 text-dark">Leave Approvals</h2>
        <a href="../settings/settings.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-2"></i>Back to Settings
        </a>
      </div>

      <!-- Pending Requests -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Pending Leave Requests</h5>
              <span class="badge bg-dark" id="pendingCount">Loading...</span>
            </div>
            <div class="card-body" id="pendingLeaves">
              <div class="text-center py-4">
                <div class="spinner-border text-warning" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Approve Confirmation Modal -->
  <div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Approve Leave Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p id="approveDetails"></p>
          <p class="text-muted mb-0">Are you sure you want to approve this leave request?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="confirmApproveBtn">
            <i class="bi bi-check-lg me-2"></i>Approve
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Reject Confirmation Modal -->
  <div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Leave Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p id="rejectDetails"></p>
          <div class="mb-3">
            <label class="form-label">Reason for Rejection (Optional):</label>
            <textarea class="form-control" id="rejectReason" rows="3" placeholder="Enter reason..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmRejectBtn">
            <i class="bi bi-x-lg me-2"></i>Reject
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    let currentLeaveId = null;
    const approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
    const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));

    // Load pending leave requests
    async function loadPendingLeaves() {
      try {
        const response = await fetch('../staffmanagement/api/leave_management.php?action=get_pending');
        const result = await response.json();

        const pendingContainer = document.getElementById('pendingLeaves');
        const pendingCount = document.getElementById('pendingCount');

        if (result.success && result.data.leaves.length > 0) {
          pendingCount.textContent = result.data.total;
          
          let html = '';
          result.data.leaves.forEach(leave => {
            const profilePic = leave.profile_photo || 'assets/profile_pic/default.png';
            html += `
              <div class="leave-card card mb-3">
                <div class="card-body">
                  <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                      <img src="../staffmanagement/${profilePic}" class="rounded-circle" width="80" height="80" alt="Profile" style="object-fit: cover;">
                    </div>
                    <div class="col-md-6">
                      <h5 class="mb-1">${leave.employee_name}</h5>
                      <p class="text-muted mb-1">
                        <i class="bi bi-briefcase me-1"></i>${leave.position || 'N/A'} - ${leave.department || 'N/A'}
                      </p>
                      <p class="mb-1">
                        <strong>Leave Type:</strong> <span class="badge bg-info">${leave.leave_type}</span>
                      </p>
                      <p class="mb-1">
                        <i class="bi bi-calendar-event me-1"></i>
                        <strong>${formatDate(leave.start_date)}</strong> to <strong>${formatDate(leave.end_date)}</strong>
                        <span class="badge badge-days bg-secondary ms-2">${leave.duration_days} day(s)</span>
                      </p>
                      ${leave.reason ? `<p class="text-muted mb-0"><small><i class="bi bi-chat-left-text me-1"></i>${leave.reason}</small></p>` : ''}
                    </div>
                    <div class="col-md-4 text-end">
                      <p class="text-muted mb-2">
                        <small>Submitted: ${new Date(leave.created_at).toLocaleDateString()}</small>
                      </p>
                      <button class="btn btn-success me-2" onclick="showApproveModal(${leave.id}, '${leave.employee_name}', '${leave.leave_type}', '${leave.start_date}', '${leave.end_date}')">
                        <i class="bi bi-check-lg me-1"></i>Approve
                      </button>
                      <button class="btn btn-danger" onclick="showRejectModal(${leave.id}, '${leave.employee_name}', '${leave.leave_type}', '${leave.start_date}', '${leave.end_date}')">
                        <i class="bi bi-x-lg me-1"></i>Reject
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            `;
          });

          pendingContainer.innerHTML = html;
        } else {
          pendingCount.textContent = '0';
          pendingContainer.innerHTML = `
            <div class="text-center py-5">
              <i class="bi bi-inbox fs-1 text-muted"></i>
              <p class="text-muted mt-3">No pending leave requests</p>
            </div>
          `;
        }
      } catch (error) {
        console.error('Error loading leaves:', error);
        document.getElementById('pendingLeaves').innerHTML = `
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>Error loading leave requests
          </div>
        `;
      }
    }

    function formatDate(dateStr) {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function showApproveModal(leaveId, employeeName, leaveType, startDate, endDate) {
      currentLeaveId = leaveId;
      document.getElementById('approveDetails').innerHTML = `
        <strong>${employeeName}</strong><br>
        ${leaveType} Leave<br>
        ${formatDate(startDate)} - ${formatDate(endDate)}
      `;
      approveModal.show();
    }

    function showRejectModal(leaveId, employeeName, leaveType, startDate, endDate) {
      currentLeaveId = leaveId;
      document.getElementById('rejectDetails').innerHTML = `
        <strong>${employeeName}</strong><br>
        ${leaveType} Leave<br>
        ${formatDate(startDate)} - ${formatDate(endDate)}
      `;
      document.getElementById('rejectReason').value = '';
      rejectModal.show();
    }

    // Approve leave
    document.getElementById('confirmApproveBtn').addEventListener('click', async () => {
      const btn = document.getElementById('confirmApproveBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Approving...';

      try {
        const response = await fetch('../staffmanagement/api/leave_management.php?action=approve_leave', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ leave_id: currentLeaveId })
        });

        const result = await response.json();

        if (result.success) {
          approveModal.hide();
          alert(`✅ Leave request approved for ${result.data.employee_name}`);
          loadPendingLeaves();
        } else {
          alert('❌ Error: ' + result.message);
        }
      } catch (error) {
        alert('❌ Network error: ' + error.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Approve';
      }
    });

    // Reject leave
    document.getElementById('confirmRejectBtn').addEventListener('click', async () => {
      const btn = document.getElementById('confirmRejectBtn');
      const reason = document.getElementById('rejectReason').value;
      
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Rejecting...';

      try {
        const response = await fetch('../staffmanagement/api/leave_management.php?action=reject_leave', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
            leave_id: currentLeaveId,
            reason: reason || null
          })
        });

        const result = await response.json();

        if (result.success) {
          rejectModal.hide();
          alert(`✅ Leave request rejected for ${result.data.employee_name}`);
          loadPendingLeaves();
        } else {
          alert('❌ Error: ' + result.message);
        }
      } catch (error) {
        alert('❌ Network error: ' + error.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-lg me-2"></i>Reject';
      }
    });

    // Sidebar toggle
    document.getElementById('menu-btn').addEventListener('click', function() {
      document.getElementById('sidebar').classList.toggle('active');
      document.getElementById('content').classList.toggle('shifted');
    });

    // Load on page load
    loadPendingLeaves();
  </script>
</body>
</html>
