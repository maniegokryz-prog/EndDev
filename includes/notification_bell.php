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
      <button class="btn btn-sm btn-link text-primary" id="markAllRead" style="font-size: 0.75rem;">Mark all as
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
        class="small text-primary text-decoration-none">View all notifications</a>
    </div>
  </div>
</div>

<!-- All Notifications Modal -->
<div class="modal fade" id="allNotificationsModal" tabindex="-1" aria-labelledby="allNotificationsModalLabel"
  aria-hidden="true" data-bs-backdrop="false">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="allNotificationsModalLabel">
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
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-danger" id="deleteAllNotifications">
          <i class="bi bi-trash"></i> Delete All
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Notification Confirmation Modal -->
<div class="modal fade" id="deleteNotificationModal" tabindex="-1" aria-labelledby="deleteNotificationModalLabel"
  aria-hidden="true">
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
  aria-labelledby="deleteAllNotificationsModalLabel" aria-hidden="true">
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

<style>
  .notification-dropdown {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  #allNotificationsModal {
    pointer-events: none;
  }

  #allNotificationsModal .modal-dialog {
    pointer-events: auto;
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
            handleNotificationClick(notifId, notifLink);
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
      function handleNotificationClick(notificationId, link) {
        // Mark as read first
        const formData = new FormData();
        formData.append('action', 'mark_notification_read');
        formData.append('notification_id', notificationId);

        fetch('../staffmanagement/api/leave_request.php', {
          method: 'POST',
          body: formData
        }).then(() => {
          // Redirect to the link if it exists
          if (link && link.trim() !== '') {
            window.location.href = link;
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
          if (link && link.trim() !== '') {
            window.location.href = link;
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
          <div class="card mb-2" id="notif-${notif.id}">
            <div class="card-body p-3">
              <div class="d-flex align-items-start">
                <div class="flex-shrink-0 me-3" style="font-size: 2rem;">
                  ${iconHtml}
                </div>
                <div class="flex-grow-1">
                  <p class="mb-1">${notif.message}${unreadBadge}</p>
                  <small class="text-muted">
                    <i class="bi bi-clock me-1"></i>${formatTimeAgo(notif.created_at)}
                  </small>
                </div>
                <button class="btn btn-sm btn-outline-danger ms-2" onclick="deleteNotification(${notif.id})" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
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
</script>