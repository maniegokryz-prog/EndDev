<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Leave Requests - Admin</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/styles.css">
  
  <style>
    .leave-request-card {
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .leave-request-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .status-badge-pending {
      background-color: #ffc107;
      color: #000;
    }
    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background-color: #dc3545;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
  </style>
</head>
<body>
<?php
require '../db_connection.php';
?>

<div class="container-fluid p-4">
  <div class="row mb-4">
    <div class="col">
      <h2>Leave Request Management</h2>
      <p class="text-muted">Review and approve employee leave requests</p>
    </div>
    <div class="col-auto">
      <button class="btn btn-primary" onclick="refreshRequests()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </button>
    </div>
  </div>

  <!-- Pending Requests Section -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-warning text-dark">
      <h5 class="mb-0">
        <i class="bi bi-clock-history"></i> Pending Requests
        <span class="badge bg-dark ms-2" id="pendingCount">0</span>
      </h5>
    </div>
    <div class="card-body">
      <div id="pendingRequestsContainer">
        <div class="text-center py-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2">Loading requests...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Approve Leave Request</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to approve this leave request?</p>
        <div id="approveDetails"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="confirmApprove()">Approve</button>
      </div>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Reject Leave Request</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to reject this leave request?</p>
        <div id="rejectDetails" class="mb-3"></div>
        <label class="form-label">Reason for rejection:</label>
        <textarea class="form-control" id="rejectionReason" rows="3" placeholder="Optional: Provide a reason"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="confirmReject()">Reject</button>
      </div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
let currentLeaveId = null;
const approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));

function loadPendingRequests() {
  fetch('api/leave_request.php?action=get_pending_requests')
    .then(res => res.json())
    .then(response => {
      const container = document.getElementById('pendingRequestsContainer');
      const countBadge = document.getElementById('pendingCount');
      
      if (!response.success) {
        container.innerHTML = '<p class="text-danger">Error loading requests</p>';
        return;
      }
      
      countBadge.textContent = response.count;
      
      if (response.count === 0) {
        container.innerHTML = '<p class="text-muted text-center py-4">No pending leave requests</p>';
        return;
      }
      
      let html = '<div class="row g-3">';
      response.data.forEach(request => {
        html += `
          <div class="col-md-6 col-lg-4">
            <div class="card leave-request-card h-100">
              <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                  <img src="../${request.profile_photo || 'assets/profile_pic/user.png'}" 
                       class="rounded-circle me-2" 
                       width="40" 
                       height="40"
                       onerror="this.src='../assets/profile_pic/user.png'">
                  <div>
                    <h6 class="mb-0">${request.employee_name}</h6>
                    <small class="text-muted">${request.employee_code} - ${request.position}</small>
                  </div>
                </div>
                
                <div class="mb-2">
                  <span class="badge bg-info">${request.leave_type}</span>
                  <span class="badge status-badge-pending">${request.status}</span>
                </div>
                
                <p class="mb-2">
                  <i class="bi bi-calendar-range"></i>
                  <strong>${request.formatted_dates}</strong>
                </p>
                
                ${request.reason ? `<p class="small text-muted mb-3">${request.reason}</p>` : ''}
                
                <small class="text-muted">Requested: ${new Date(request.created_at).toLocaleString()}</small>
                
                <div class="d-flex gap-2 mt-3">
                  <button class="btn btn-success btn-sm flex-fill" onclick="showApproveModal(${request.id}, '${request.employee_name}', '${request.formatted_dates}')">
                    <i class="bi bi-check-lg"></i> Approve
                  </button>
                  <button class="btn btn-danger btn-sm flex-fill" onclick="showRejectModal(${request.id}, '${request.employee_name}', '${request.formatted_dates}')">
                    <i class="bi bi-x-lg"></i> Reject
                  </button>
                </div>
              </div>
            </div>
          </div>
        `;
      });
      html += '</div>';
      
      container.innerHTML = html;
    })
    .catch(error => {
      console.error('Error:', error);
      document.getElementById('pendingRequestsContainer').innerHTML = 
        '<p class="text-danger">Error loading requests</p>';
    });
}

function showApproveModal(leaveId, employeeName, dates) {
  currentLeaveId = leaveId;
  document.getElementById('approveDetails').innerHTML = `
    <strong>Employee:</strong> ${employeeName}<br>
    <strong>Dates:</strong> ${dates}
  `;
  approveModal.show();
}

function showRejectModal(leaveId, employeeName, dates) {
  currentLeaveId = leaveId;
  document.getElementById('rejectDetails').innerHTML = `
    <strong>Employee:</strong> ${employeeName}<br>
    <strong>Dates:</strong> ${dates}
  `;
  document.getElementById('rejectionReason').value = '';
  rejectModal.show();
}

function confirmApprove() {
  const formData = new FormData();
  formData.append('action', 'approve_request');
  formData.append('leave_id', currentLeaveId);
  formData.append('approved_by', 'admin');
  
  fetch('api/leave_request.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(response => {
    if (response.success) {
      approveModal.hide();
      loadPendingRequests();
      alert('Leave request approved successfully!');
    } else {
      alert('Error: ' + response.error);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to approve request');
  });
}

function confirmReject() {
  const reason = document.getElementById('rejectionReason').value;
  const formData = new FormData();
  formData.append('action', 'reject_request');
  formData.append('leave_id', currentLeaveId);
  formData.append('rejected_by', 'admin');
  formData.append('rejection_reason', reason);
  
  fetch('api/leave_request.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(response => {
    if (response.success) {
      rejectModal.hide();
      loadPendingRequests();
      alert('Leave request rejected');
    } else {
      alert('Error: ' + response.error);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to reject request');
  });
}

function refreshRequests() {
  loadPendingRequests();
}

// Load on page load
document.addEventListener('DOMContentLoaded', loadPendingRequests);

// Auto-refresh every 30 seconds
setInterval(loadPendingRequests, 30000);
</script>
</body>
</html>
