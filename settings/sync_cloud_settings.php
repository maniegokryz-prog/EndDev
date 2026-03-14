<?php
require_once '../auth_guard.php';
require_once '../navigation.php';

$currentUser = getCurrentUser();

// Default values
$config = [
    'sync_enabled' => true,
    // For Hostinger VPS, use the IP or domain pointing to VPS
    'api_url' => 'http://76.13.210.68/api/sync_endpoint.php',
    'api_key' => 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo',
    'sync_interval' => 60
];

// Load existing config if available
$configFile = '../config/sync_config.json';
if (file_exists($configFile)) {
    $loadedConfig = json_decode(file_get_contents($configFile), true);
    if ($loadedConfig) {
        $config = array_merge($config, $loadedConfig);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Cloud Sync Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../dashboard/dashboard.css">
    <link rel="stylesheet" href="settings.css">
</head>

<body>
    <div class="top-navbar d-flex align-items-center p-2 shadow-sm">
        <div class="menu-toggle d-lg-none">
            <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
        </div>
        <div class="ms-auto">
            <?php include '../includes/notification_bell.php'; ?>
        </div>
    </div>

    <div class="sidebar d-flex flex-column pt-5" id="sidebar">
        <div class="profile text-center p-3 mt-4">
            <img src="../assets/profile_pic/user.png" alt="Profile" class="rounded-circle mb-2" width="70" height="70">
            <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?></h5>
            <small class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User')); ?></small>
        </div>
        <nav class="nav flex-column px-2">
            <?php renderNavigation('Sync Settings'); ?>
        </nav>
    </div>

    <div class="content" id="content">
        <div class="container-fluid px-3 pt-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header py-3 bg-white">
                            <h6 class="m-0 font-weight-bold text-primary">IONOS Cloud Synchronization</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                These settings control how the local server synchronizes data with the IONOS cloud
                                server.
                                Changes here will be picked up by the auto-sync script automatically.
                            </div>

                            <form id="syncSettingsForm">
                                <div class="mb-3 form-check form-switch fa-2x">
                                    <input class="form-check-input" type="checkbox" id="sync_enabled"
                                        name="sync_enabled" <?php echo $config['sync_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fs-5 ms-2" for="sync_enabled">Enable Cloud
                                        Sync</label>
                                </div>

                                <div class="mb-3">
                                    <label for="api_url" class="form-label fw-bold">IONOS API URL</label>
                                    <input type="url" class="form-control" id="api_url" name="api_url"
                                        value="<?php echo htmlspecialchars($config['api_url']); ?>" required>
                                    <div class="form-text">The full URL to the `sync_endpoint.php` on your IONOS server.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="api_key" class="form-label fw-bold">API Key</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="api_key" name="api_key"
                                            value="<?php echo htmlspecialchars($config['api_key']); ?>" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleKey">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Must match the API_KEY defined in `sync_endpoint.php` on
                                        IONOS.</div>
                                </div>

                                <div class="mb-4">
                                    <label for="sync_interval" class="form-label fw-bold">Sync Interval
                                        (Seconds)</label>
                                    <input type="number" class="form-control" id="sync_interval" name="sync_interval"
                                        value="<?php echo intval($config['sync_interval']); ?>" min="10" max="3600">
                                </div>

                                <div class="d-flex justify-content-end gap-3">
                                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary fw-bold px-4"
                                        onclick="window.location.href='settings.php'">Cancel</button>
                                    <button type="submit" class="btn-modern btn-outline text-primary border-primary fw-bold px-4 d-flex align-items-center">
                                        <span class="spinner-border spinner-border-sm me-2 d-none"
                                            id="saveSpinner"></span>
                                        Save Configuration
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../dashboard/dashboard.js"></script>
    <script>
        document.getElementById('toggleKey').addEventListener('click', function () {
            const input = document.getElementById('api_key');
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        document.getElementById('syncSettingsForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const spinner = document.getElementById('saveSpinner');

            btn.disabled = true;
            spinner.classList.remove('d-none');

            const formData = new FormData(this);
            // Handle checkbox manually
            formData.set('sync_enabled', document.getElementById('sync_enabled').checked ? '1' : '0');

            fetch('processes/save_sync_config.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Configuration saved successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Connection error: ' + error);
                })
                .finally(() => {
                    btn.disabled = false;
                    spinner.classList.add('d-none');
                });
        });

        function showLogoutModal() {
            var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();
        }
    </script>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
                <div class="modal-body text-center py-4">
                    <h5 class="fw-bold mb-3">Confirm Logout</h5>
                    <p class="mb-0 fs-6">Are you sure you want to log out?</p>
                </div>
                <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4" data-bs-dismiss="modal">No</button>
                    <form method="POST" action="logout.php" style="display: inline;">
                        <input type="hidden" name="confirm_logout" value="1">
                        <button type="submit" class="btn-modern btn-outline text-danger border-danger px-4">Yes, Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>