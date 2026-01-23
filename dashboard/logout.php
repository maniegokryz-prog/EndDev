<?php
/**
 * Logout Handler with confirmation modal
 * Shows a Bootstrap modal asking the user to confirm logout.
 * If confirmed (POST), destroys the session and redirects to login.
 */

session_start();

// If the form was submitted (confirm logout), perform logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
        // Destroy all session data
        session_unset();
        session_destroy();

        // Clear session cookie
        if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
        }

        // Redirect to login page
        header('Location: ../login/login.php?message=logged_out');
        exit();
}

// Otherwise render a small page that shows a confirmation modal
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Logout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background-color: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0;">

<!-- Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <h5 class="mb-3">Confirm Logout</h5>
                <p class="mb-0">Are you sure you want to log out?</p>
            </div>
            <div class="modal-footer">
                <a href="dashboard.php" class="btn btn-secondary">No</a>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="confirm_logout" value="1">
                    <button type="submit" class="btn btn-danger">Yes, Log out</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-show the modal on page load
    document.addEventListener('DOMContentLoaded', function () {
        var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
        logoutModal.show();
    });
</script>

</body>
</html>
