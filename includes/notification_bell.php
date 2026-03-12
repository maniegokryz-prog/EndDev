<?php
/**
 * Notification Bell Component
 * Include this in any page's top navbar to show notifications
 */
?>
<div class="notification" style="position: relative; display: inline-block;">
  <i class="bi bi-bell-fill fs-4 text-warning icon-btn" id="notificationBtn" style="cursor: pointer;"></i>
  <span id="notificationCount"
    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 0.65rem; font-weight: bold; display: none; pointer-events: none; text-shadow: 0 0 3px rgba(0,0,0,0.5);">
    0
  </span>

  <!-- Notification Dropdown -->
  <div class="notification-dropdown shadow-lg" id="notificationDropdown"
    style="display: none; position: absolute !important; top: 45px !important; right: 0 !important; width: 360px !important; max-width: 90vw !important; z-index: 9999 !important; background: white; border-radius: 8px; border: 1px solid #ddd;">
    <div class="notification-header d-flex justify-content-between align-items-center p-3 border-bottom"
      style="background: #f8f9fa; border-top-left-radius: 8px; border-top-right-radius: 8px;">
      <h6 class="mb-0 fw-bold">Notifications</h6>
      <button class="notif-link border-0 bg-transparent p-0" id="markAllRead" style="font-size: 0.75rem;">Mark all as
        read</button>
    </div>
    <div class="notification-body" id="notificationBody"
      style="max-height: 400px; overflow-y: auto; background: white;">
      <div class="text-center py-4 text-muted">
        <div class="spinner-border spinner-border-sm" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="small mt-2">Loading notifications...</p>
      </div>
    </div>
    <div class="notification-footer text-center p-2 border-top"
      style="background: #f8f9fa; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
      <a href="#" onclick="event.preventDefault(); openAllNotificationsModal();"
        class="small notif-link">View all notifications</a>
    </div>
  </div>
</div>

<!-- All Notifications Modal -->
<div class="modal fade" id="allNotificationsModal" tabindex="-1" aria-labelledby="allNotificationsModalLabel"
  aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header justify-content-center">
        <h5 class="modal-title fw-bold" id="allNotificationsModalLabel">
          <i class="bi bi-bell-fill text-warning me-2"></i>All Notifications
        </h5>
      </div>
      <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
        <div id="allNotificationsBody">
          <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mt-2">Loading all notifications...</p>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-danger px-4" id="deleteAllNotifications">
          <i class="bi bi-trash"></i> Delete All
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Notification Confirmation Modal -->
<div class="modal fade" id="deleteNotificationModal" tabindex="-1" aria-labelledby="deleteNotificationModalLabel"
  aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="deleteNotificationModalLabel">
          <i class="bi bi-exclamation-triangle text-warning me-2"></i>Delete Notification
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Are you sure you want to delete this notification? This action cannot be undone.</p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteNotification">
          <i class="bi bi-trash"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Delete All Notifications Confirmation Modal -->
<div class="modal fade" id="deleteAllNotificationsModal" tabindex="-1"
  aria-labelledby="deleteAllNotificationsModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="deleteAllNotificationsModalLabel">
          <i class="bi bi-exclamation-triangle text-danger me-2"></i>Delete All Notifications
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0"><strong>Warning:</strong> This will permanently delete ALL your notifications. This action
          cannot be undone.</p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteAllNotifications">
          <i class="bi bi-trash"></i> Delete All
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Rejected Schedule Detail Modal -->
<div class="modal fade" id="rejectedScheduleDetailModal" tabindex="-1" aria-hidden="true" style="z-index: 10100;" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header" style="background: #fff3cd; border-bottom: 0;">
        <h5 class="modal-title fw-bold" style="color: #664d03;">
          <i class="bi bi-x-circle me-2 text-danger"></i>Schedule Request Rejected
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <!-- Remarks -->
        <div id="rejectionRemarksSection" class="alert alert-danger d-flex align-items-start gap-2 mb-4" style="display:none !important">
          <i class="bi bi-chat-left-text-fill fs-5 flex-shrink-0 mt-1"></i>
          <div>
            <strong>Admin Remarks:</strong>
            <p class="mb-0 mt-1" id="rejectionRemarksText"></p>
          </div>
        </div>
        <!-- Loading spinner -->
        <div id="rejectionModalLoading" class="text-center py-4">
          <div class="spinner-border text-danger" role="status"></div>
          <p class="text-muted mt-2 small">Loading schedule...</p>
        </div>
        <!-- Calendar -->
        <div id="rejectedScheduleCalendarWrap" style="display:none;">
          <h6 class="fw-bold mb-3"><i class="bi bi-calendar-week me-2"></i>Requested Schedule</h6>
          <div id="rejectedScheduleCalendarView" style="overflow-x: auto;"></div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
  .notification-dropdown {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  .notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.2s;
  }

  .notification-item:hover {
    background-color: #f8f9fa;
  }

  .notification-item.unread {
    background-color: #e7f3ff;
  }

  /* Notification Dropdown Links */
  .notif-link {
    color: #007bff !important;
    text-decoration: underline !important;
    font-weight: normal !important;
    background: transparent !important;
    border: none !important;
    transition: color 0.15s ease-in-out;
    cursor: pointer;
    padding: 0;
  }

  .notif-link:hover {
    color: #000000ff !important;
    text-decoration: underline !important;
  }

  /* Modern Outline Button Hovers */
  .btn-modern.btn-outline {
    transition: all 0.2s ease !important;
  }

  .btn-modern.btn-outline.text-secondary:hover {
    background-color: #6c757d !important;
    color: #fff !important;
    border-color: #6c757d !important;
  }

  .btn-modern.btn-outline.text-danger:hover {
    background-color: #dc3545 !important;
    color: #fff !important;
    border-color: #dc3545 !important;
  }
</style>

<script>
  (function () {
    // Wait for DOM to load
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initNotifications);
    } else {
      initNotifications();
    }

    function initNotifications() {
      const notificationBtn = document.getElementById('notificationBtn');
      const notificationDropdown = document.getElementById('notificationDropdown');
      const notificationBody = document.getElementById('notificationBody');
      const notificationCount = document.getElementById('notificationCount');
      const markAllReadBtn = document.getElementById('markAllRead');

      // Move modals to body to ensure correct stacking context (fixes backdrop issues)
      ['allNotificationsModal', 'deleteNotificationModal', 'deleteAllNotificationsModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.parentElement !== document.body) {
          document.body.appendChild(el);
        }
      });

      if (!notificationBtn) return;

      let notifications = [];

      // Toggle notification dropdown
      notificationBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        const isVisible = notificationDropdown.style.display === 'block';
        notificationDropdown.style.display = isVisible ? 'none' : 'block';

        if (!isVisible) {
          loadNotifications();
        }
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', function (e) {
        if (!notificationDropdown.contains(e.target) && e.target !== notificationBtn) {
          notificationDropdown.style.display = 'none';
        }
      });

      // Load notifications
      async function loadNotifications() {
        try {
          notificationBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

          const response = await fetch('../staffmanagement/api/leave_request.php?action=get_notifications');
          const result = await response.json();

          if (!result.success) {
            throw new Error(result.error || 'Failed to load notifications');
          }

          notifications = result.data || [];
          updateNotificationCount();
          renderNotifications();

        } catch (error) {
          console.error('Error loading notifications:', error);
          notificationBody.innerHTML = '<div class="text-center py-4 text-danger"><small>Failed to load notifications</small></div>';
        }
      }

      // Render notifications
      function renderNotifications() {
        if (notifications.length === 0) {
          notificationBody.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-bell-slash fs-1 d-block mb-2"></i><small>No notifications</small></div>';
          return;
        }

        notificationBody.innerHTML = notifications.map(notif => {
          // Determine icon based on notification type
          let iconHtml = '<i class="bi bi-file-earmark-text" style="color: #0d6832;"></i>'; // Default for leave_request
          if (notif.type.includes('approved')) {
            iconHtml = '<i class="bi bi-check-circle-fill" style="color: #0d6832;"></i>';
          } else if (notif.type.includes('rejected')) {
            iconHtml = '<i class="bi bi-x-circle-fill" style="color: #0d6832;"></i>';
          } else if (notif.type.includes('schedule')) {
            iconHtml = '<i class="bi bi-calendar-check" style="color: #0d6832;"></i>';
          } else if (notif.type.includes('late')) {
            iconHtml = '<i class="bi bi-clock-history" style="color: #0d6832;"></i>';
          }

          const unreadClass = notif.is_read === '0' || notif.is_read === 0 ? 'unread' : '';
          const notifId = notif.id;
          const notifLink = notif.link || '';

          return `
          <div class="notification-item ${unreadClass}" data-notif-id="${notifId}" data-notif-link="${notifLink}" style="cursor: pointer;">
            <div class="d-flex align-items-start">
              <div class="me-3 flex-shrink-0" style="font-size: 1.5rem;">
                ${iconHtml}
              </div>
              <div class="flex-grow-1">
                <p class="mb-1 small">${notif.message}</p>
                <small class="text-muted">${formatTimeAgo(notif.created_at)}</small>
              </div>
            </div>
          </div>
        `;
        }).join('');

        // Add click event listeners to notification items
        document.querySelectorAll('.notification-item').forEach(item => {
          item.addEventListener('click', function () {
            const notifId = this.getAttribute('data-notif-id');
            const notifLink = this.getAttribute('data-notif-link');
            const notif = notifications.find(n => n.id == notifId);
            handleNotificationClick(notifId, notifLink, notif ? notif.type : '', notif ? notif.message : '');
          });
        });
      }

      // Update notification count badge
      function updateNotificationCount() {
        const unreadCount = notifications.filter(n => n.is_read === '0' || n.is_read === 0).length;
        if (unreadCount > 0) {
          notificationCount.textContent = unreadCount > 99 ? '99+' : unreadCount;
          notificationCount.style.display = 'block';
        } else {
          notificationCount.style.display = 'none';
        }
      }

      // Mark notification as read
      window.markAsRead = async function (notificationId) {
        try {
          const formData = new FormData();
          formData.append('action', 'mark_notification_read');
          formData.append('notification_id', notificationId);

          const response = await fetch('../staffmanagement/api/leave_request.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();
          if (result.success) {
            const notif = notifications.find(n => n.id == notificationId);
            if (notif) notif.is_read = '1';
            updateNotificationCount();
            renderNotifications();
          }
        } catch (error) {
          console.error('Error marking notification as read:', error);
        }
      };

      // Handle notification click with redirect
      function handleNotificationClick(notificationId, link, notifType, notifMessage) {
        // Check if this is a schedule rejection notification
        const isScheduleRejection = (notifType === 'schedule_change') &&
          (notifMessage && notifMessage.toLowerCase().includes('rejected'));

        if (isScheduleRejection) {
          // Mark as read then show detail modal (don't navigate away)
          const formData = new FormData();
          formData.append('action', 'mark_notification_read');
          formData.append('notification_id', notificationId);
          fetch('../staffmanagement/api/leave_request.php', { method: 'POST', body: formData });

          openRejectionDetailModal(notificationId, notifMessage);
          return;
        }

        // Helper to resolve hardcoded /EndDev paths for Hostinger
        const getDynamicLink = (origLink) => {
          if (!origLink || origLink.trim() === '' || origLink.trim() === '#') return null;
          let dynamicLink = origLink.trim();
          
          // If we are in the local /EndDev/ environment but the link is missing it, prepend it.
          if (window.location.pathname.includes('/EndDev/') && dynamicLink.startsWith('/') && !dynamicLink.startsWith('/EndDev/')) {
              dynamicLink = '/EndDev' + dynamicLink;
          }
          
          // If we are NOT in /EndDev/ (e.g., Hostinger) but the link has it, remove it.
          if (dynamicLink.startsWith('/EndDev/') && !window.location.pathname.includes('/EndDev/')) {
             dynamicLink = dynamicLink.replace('/EndDev/', '/');
          }
          return dynamicLink;
        };

        const resolvedLink = getDynamicLink(link);

        // Mark as read first
        const formData = new FormData();
        formData.append('action', 'mark_notification_read');
        formData.append('notification_id', notificationId);

        fetch('../staffmanagement/api/leave_request.php', {
          method: 'POST',
          body: formData
        }).then(() => {
          // Redirect to the link if it exists
          if (resolvedLink) {
            window.location.href = resolvedLink;
          } else {
            // If no link, just mark as read and update UI
            const notif = notifications.find(n => n.id == notificationId);
            if (notif) notif.is_read = '1';
            updateNotificationCount();
            renderNotifications();
          }
        }).catch(error => {
          console.error('Error handling notification click:', error);
          // Still redirect even if marking as read fails
          if (resolvedLink) {
            window.location.href = resolvedLink;
          }
        });
      }

      // Expose to window for global access
      window.handleNotificationClick = handleNotificationClick;

      // Mark all as read
      if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async function () {
          try {
            const unreadIds = notifications.filter(n => n.is_read === '0' || n.is_read === 0).map(n => n.id);
            if (unreadIds.length === 0) return;

            for (const id of unreadIds) {
              await window.markAsRead(id);
            }
          } catch (error) {
            console.error('Error marking all as read:', error);
          }
        });
      }

      // Format time ago
      function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return date.toLocaleDateString();
      }

      // Load notifications on page load and refresh every 30 seconds
      loadNotifications();
      setInterval(loadNotifications, 30000);
    }

    // Open all notifications modal
    window.openAllNotificationsModal = function () {
      // Close the notification dropdown first for cleaner look
      const notificationDropdown = document.getElementById('notificationDropdown');
      if (notificationDropdown) {
        notificationDropdown.style.display = 'none';
      }

      const modal = new bootstrap.Modal(document.getElementById('allNotificationsModal'));
      modal.show();
      loadAllNotifications();
    };

    // Load all notifications in modal
    async function loadAllNotifications() {
      const allNotificationsBody = document.getElementById('allNotificationsBody');

      try {
        allNotificationsBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

        const response = await fetch('../staffmanagement/api/leave_request.php?action=get_notifications');
        const result = await response.json();

        if (!result.success) {
          throw new Error(result.error || 'Failed to load notifications');
        }

        const notifications = result.data || [];

        if (notifications.length === 0) {
          allNotificationsBody.innerHTML = `
          <div class="text-center py-5 text-muted">
            <i class="bi bi-bell-slash" style="font-size: 3rem;"></i>
            <p class="mt-3">No notifications</p>
          </div>
        `;
          return;
        }

        allNotificationsBody.innerHTML = notifications.map(notif => {
          let iconHtml = '<i class="bi bi-file-earmark-text" style="color: #0d6832;"></i>';
          if (notif.type.includes('approved')) iconHtml = '<i class="bi bi-check-circle-fill" style="color: #0d6832;"></i>';
          else if (notif.type.includes('rejected')) iconHtml = '<i class="bi bi-x-circle-fill" style="color: #0d6832;"></i>';
          else if (notif.type.includes('schedule')) iconHtml = '<i class="bi bi-calendar-check" style="color: #0d6832;"></i>';
          else if (notif.type.includes('late')) iconHtml = '<i class="bi bi-clock-history" style="color: #0d6832;"></i>';

          const unreadBadge = notif.is_read === '0' || notif.is_read === 0
            ? '<span class="badge bg-primary ms-2">New</span>'
            : '';

          return `
          <div class="border-bottom py-3 px-2" id="notif-${notif.id}">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0 me-3" style="font-size: 1.5rem;">
                ${iconHtml}
              </div>
              <div class="flex-grow-1">
                <p class="mb-1" style="font-size: 0.95rem;">${notif.message}${unreadBadge}</p>
                <small class="text-muted" style="font-size: 0.8rem;">
                  <i class="bi bi-clock me-1"></i>${formatTimeAgo(notif.created_at)}
                </small>
              </div>
              <button class="btn btn-outline-danger btn-sm ms-2" onclick="deleteNotification(${notif.id})" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        `;
        }).join('');

      } catch (error) {
        console.error('Error loading all notifications:', error);
        allNotificationsBody.innerHTML = `
        <div class="text-center py-4 text-danger">
          <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
          <p class="mt-2">Failed to load notifications</p>
          <small>${error.message}</small>
        </div>
      `;
      }
    }

    // Delete single notification
    let notificationIdToDelete = null;

    window.deleteNotification = function (notificationId) {
      notificationIdToDelete = notificationId;
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteNotificationModal'));
      deleteModal.show();
    };

    // Confirm delete single notification
    document.getElementById('confirmDeleteNotification')?.addEventListener('click', async function () {
      if (!notificationIdToDelete) return;

      const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteNotificationModal'));
      deleteModal.hide();

      try {
        const formData = new FormData();
        formData.append('action', 'delete_notification');
        formData.append('notification_id', notificationIdToDelete);

        const response = await fetch('../staffmanagement/api/leave_request.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          // Remove from UI with animation
          const notifElement = document.getElementById('notif-' + notificationIdToDelete);
          if (notifElement) {
            notifElement.style.transition = 'opacity 0.3s';
            notifElement.style.opacity = '0';
            setTimeout(() => notifElement.remove(), 300);
          }

          // Reload notifications in bell dropdown
          if (typeof initNotifications !== 'undefined') {
            setTimeout(() => {
              const notificationBtn = document.getElementById('notificationBtn');
              if (notificationBtn) {
                notificationBtn.click();
                setTimeout(() => notificationBtn.click(), 100);
              }
            }, 500);
          }
        } else {
          alert('Failed to delete notification: ' + (result.error || 'Unknown error'));
        }
      } catch (error) {
        console.error('Error deleting notification:', error);
        alert('Failed to delete notification');
      } finally {
        notificationIdToDelete = null;
      }
    });

    // Delete all notifications
    document.getElementById('deleteAllNotifications')?.addEventListener('click', function () {
      const deleteAllModal = new bootstrap.Modal(document.getElementById('deleteAllNotificationsModal'));
      deleteAllModal.show();
    });

    // Confirm delete all notifications
    document.getElementById('confirmDeleteAllNotifications')?.addEventListener('click', async function () {
      const deleteAllModal = bootstrap.Modal.getInstance(document.getElementById('deleteAllNotificationsModal'));
      deleteAllModal.hide();

      try {
        const formData = new FormData();
        formData.append('action', 'delete_all_notifications');

        const response = await fetch('../staffmanagement/api/leave_request.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          loadAllNotifications();
          // Reload notifications in bell dropdown
          setTimeout(() => {
            const notificationBtn = document.getElementById('notificationBtn');
            if (notificationBtn) {
              notificationBtn.click();
              setTimeout(() => notificationBtn.click(), 100);
            }
          }, 500);

          // Show success message
          const allNotificationsBody = document.getElementById('allNotificationsBody');
          if (allNotificationsBody) {
            allNotificationsBody.innerHTML = `
            <div class="text-center py-5 text-success">
              <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
              <p class="mt-3">${result.message || 'All notifications deleted successfully'}</p>
            </div>
          `;
          }
        } else {
          alert('Failed to delete notifications: ' + (result.error || 'Unknown error'));
        }
      } catch (error) {
        console.error('Error deleting all notifications:', error);
        alert('Failed to delete all notifications');
      }
    });

    function formatTimeAgo(dateString) {
      const date = new Date(dateString);
      const now = new Date();
      const diff = Math.floor((now - date) / 1000);

      if (diff < 60) return 'Just now';
      if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
      if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
      if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
      return date.toLocaleDateString();
    }
  })();

  // ---- Rejection Detail Modal ----
  window.openRejectionDetailModal = async function(notificationId) {
    var modalEl = document.getElementById('rejectedScheduleDetailModal');
    if (modalEl && modalEl.parentElement !== document.body) document.body.appendChild(modalEl);

    document.getElementById('rejectionRemarksSection').style.cssText = 'display:none !important';
    document.getElementById('rejectionRemarksText').textContent = '';
    document.getElementById('rejectionModalLoading').style.display = 'block';
    document.getElementById('rejectedScheduleCalendarWrap').style.display = 'none';
    document.getElementById('rejectedScheduleCalendarView').innerHTML = '';

    var dropdown = document.getElementById('notificationDropdown');
    if (dropdown) dropdown.style.display = 'none';

    var bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();

    fetch('../staffmanagement/api/leave_request.php?action=get_rejected_schedule_detail&notification_id=' + notificationId)
      .then(function(r){ return r.json(); })
      .then(function(data){
        document.getElementById('rejectionModalLoading').style.display = 'none';
        document.getElementById('rejectedScheduleCalendarWrap').style.display = 'block';
        if (data.remarks) {
          document.getElementById('rejectionRemarksSection').style.cssText = 'display:flex !important';
          document.getElementById('rejectionRemarksText').textContent = data.remarks;
        }
        if (data.schedule_data && data.schedule_data.length > 0) {
          renderRejectionCalendar(document.getElementById('rejectedScheduleCalendarView'), data.schedule_data);
        } else {
          document.getElementById('rejectedScheduleCalendarView').innerHTML = '<p style="text-align:center;color:#666;padding:20px;">No schedule data available.</p>';
        }
      })
      .catch(function(err){
        document.getElementById('rejectionModalLoading').innerHTML = '<p style="color:red;text-align:center;">Failed to load schedule details.</p>';
        console.error(err);
      });
  };

  function renderRejectionCalendar(container, scheduleBlocks) {
    var dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    var colors   = ['#4a7c59','#8b4a6b','#b85450','#5b9bd5','#ffc000','#c55a11','#7030a0'];
    var ROW = 50;
    function fmt(t) {
      if(!t) return '';
      var p=t.split(':'), h=parseInt(p[0],10), m=p[1], ap=h>=12?'PM':'AM';
      h=h%12||12; return h+':'+m+' '+ap;
    }
    var minH=7, maxH=17;
    scheduleBlocks.forEach(function(b){
      if(b.startTime) minH=Math.min(minH,parseInt(b.startTime.split(':')[0],10));
      if(b.endTime)   maxH=Math.max(maxH,Math.ceil(parseInt(b.endTime.split(':')[0],10)+(parseInt(b.endTime.split(':')[1],10)>0?1:0)));
    });
    minH=Math.max(0,minH-1); maxH=Math.min(24,maxH+1);

    var gridStyle='display:grid;grid-template-columns:65px repeat(7,1fr);gap:1px;background:#e2e8f0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;min-width:680px;';
    var html='<div style="'+gridStyle+'">';
    html+='<div style="background:#f8fafc;font-weight:600;text-align:center;padding:8px 2px;font-size:.8em;">Time</div>';
    dayNames.forEach(function(d){ html+='<div style="background:#f8fafc;font-weight:600;text-align:center;padding:8px 2px;font-size:.8em;">'+d+'</div>'; });

    html+='<div style="background:#f8fafc;">';
    for(var h=minH;h<=maxH;h++) {
      html+='<div style="height:'+ROW+'px;text-align:right;padding:3px 6px;font-size:.7em;color:#64748b;border-bottom:1px solid #e2e8f0;">'+fmt(String(h).padStart(2,'0')+':00')+'</div>';
    }
    html+='</div>';

    dayNames.forEach(function(day){
      var total=(maxH-minH+1)*ROW;
      html+='<div style="background:white;position:relative;min-height:'+total+'px;">';
      for(var h=minH;h<=maxH;h++) html+='<div style="height:'+ROW+'px;border-bottom:1px dashed #e2e8f0;"></div>';
      scheduleBlocks.forEach(function(block,idx){
        if(!block.days || !block.days.includes(day)) return;
        var sh=parseInt(block.startTime.split(':')[0],10), sm=parseInt(block.startTime.split(':')[1],10);
        var eh=parseInt(block.endTime.split(':')[0],10), em=parseInt(block.endTime.split(':')[1],10);
        var top=((sh-minH)*ROW)+(sm/60*ROW);
        var ht=((eh-minH)*ROW+(em/60*ROW))-top;
        var clr=block.color||colors[idx%colors.length];
        var label=(block.subject&&block.subject!=='N/A'&&block.class&&block.class!=='N/A')
          ?'<div style="font-weight:bold;font-size:.7em;">'+block.subject+'</div><div style="font-size:.65em;">'+block.class+'</div>'
          :'<div style="font-weight:bold;font-size:.7em;">Work Shift</div>';
        html+='<div style="position:absolute;top:'+top+'px;left:2px;right:2px;height:'+ht+'px;background:'+clr+';border-radius:4px;color:white;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:2px;box-sizing:border-box;">'+label+'<div style="font-size:.65em;opacity:.9;">'+fmt(block.startTime)+' - '+fmt(block.endTime)+'</div></div>';
      });
      html+='</div>';
    });
    html+='</div>';
    container.innerHTML=html;
  }
</script>
