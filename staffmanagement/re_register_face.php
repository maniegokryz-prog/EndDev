<?php
// staffmanagement/re_register_face.php
require_once '../auth_guard.php';
require_once '../navigation.php';
require '../db_connection.php';

// Check if user is logged in
$currentUser = getCurrentUser();

$employee_id = $_GET['id'] ?? '';
if (empty($employee_id)) {
    die("Employee ID is required.");
}

// Fetch basic employee info
$stmt = $conn->prepare("SELECT id, employee_id, first_name, last_name, roles, department, profile_photo FROM employees WHERE employee_id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    die("Employee not found.");
}

$fullName = $employee['first_name'] . ' ' . $employee['last_name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-register Face - <?php echo htmlspecialchars($fullName); ?></title>

    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <!-- <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> -->

    <!-- Styles -->
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="staff.css?v=<?php echo time(); ?>">

    <!-- Face API scripts -->
    <script src="../assets/js/face-api.min.js"></script>
    <!-- Tensorflow/Blazeface (Check local availability) -->
    <script src="../assets/js/tf.min.js"></script>
    <!-- <script src="../assets/js/blazeface.js"></script> --> <!-- Blazeface not found locally -->

    <style>
        /* Sidebar & Dashboard Layout Styles (Matching dashboard.css) */
        :root {
            --primary-color: #103932;
            --secondary-color: #d8af35;
            --light-bg: #f4f6f9;
        }

        body {
            overflow-x: hidden;
            background-color: var(--light-bg);
            font-family: 'Arial', sans-serif;
            margin: 0;
        }

        /* -----------------------
           TOP NAVBAR (#103932)
           ----------------------- */
        .top-navbar {
            background-color: var(--primary-color);
            height: 60px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            /* Above sidebar in some views, or managed by resizing */
            transition: padding-left 0.3s;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* Icon Buttons in Navbar */
        .icon-btn {
            color: #ffc107;
            /* Gold/Yellow */
            cursor: pointer;
            padding: 5px;
            transition: 0.2s;
        }

        .icon-btn:hover {
            opacity: 0.8;
        }

        /* -----------------------
           SIDEBAR
           ----------------------- */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            /* Default open on desktop */
            background-color: var(--primary-color);
            color: #fff;
            z-index: 1002;
            transition: all 0.3s;
            overflow-y: auto;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 60px;
            /* Space for hidden top bar if needed, or overlap */
        }

        /* Collapsed State for Desktop */
        .sidebar.collapsed {
            left: -250px;
        }

        .sidebar .profile {
            text-align: center;
            padding: 20px;
            margin-top: 20px;
        }

        .sidebar .profile img {
            border: 3px solid #dee2e6;
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 50%;
        }

        .sidebar .nav-link {
            color: #fff;
            padding: 12px 20px;
            font-size: 0.95rem;
            border-radius: 4px;
            margin: 4px 10px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            margin-right: 15px;
            font-size: 1.1rem;
            width: 25px;
            text-align: center;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffc107;
        }

        .sidebar .nav-link.active {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            /* Dark text on gold bg */
            font-weight: 600;
        }

        /* -----------------------
           CONTENT AREA
           ----------------------- */
        .content {
            margin-top: 60px;
            margin-left: 250px;
            /* Aligned with open sidebar */
            transition: margin-left 0.3s;
            min-height: calc(100vh - 60px);
            padding: 30px;
        }

        /* Expanded state (when sidebar is collapsed) */
        .content.expanded {
            margin-left: 0;
        }

        /* -----------------------
           RESPONSIVE
           ----------------------- */
        @media (max-width: 991px) {
            .sidebar {
                left: -250px;
            }

            /* Hidden by default on mobile */
            .sidebar.active {
                left: 0;
            }

            /* Open on mobile */

            .content {
                margin-left: 0;
            }

            .content.expanded {
                margin-left: 0;
            }

            /* Logic same */

            .top-navbar {
                padding-left: 20px;
            }
        }

        @media (min-width: 992px) {
            /* Fix top navbar to span full width but sit above content? 
               Usually Dashboard has navbar fixed full width z-1001, Sidebar z-1002 overlaps it, OR navbar starts at 250px.
               Looking at dashboard.css from before: .top-navbar { left: 0; right: 0; }
               And Sidebar is fixed top:0 left:-250 etc.
               Since sidebar is dark green and navbar is dark green, they blend if they overlap.
            */
        }

        /* Face Registration Layout */
        .reg-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }

        /* Camera Layout */
        .camera-section {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .camera-container {
            flex: 1;
            min-width: 300px;
            position: relative;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 4/3;
            display: flex;
            /* Center video */
            align-items: center;
            justify-content: center;
        }

        .camera-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #detection-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        #face-guidance {
            flex: 1;
            min-width: 250px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid var(--secondary-color);
        }

        #face-guidance h4 {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 15px;
        }

        .status-item {
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .instruction-box {
            background: #e7f1ff;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        /* Step Box */
        .step-box {
            background-color: #fff3cd;
            border-radius: 12px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
            border: 1px solid #ffeeba;
        }

        .step-box h4 {
            color: #856404;
            font-weight: 700;
        }

        /* Thumbnails */
        .thumbnail-item {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #ddd;
            position: relative;
        }

        .thumbnail-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>

<body>

    <!-- Top Navbar -->
    <div class="top-navbar d-flex justify-content-between align-items-center shadow-sm" id="topNavbar">
        <div class="d-flex align-items-center gap-3">
            <!-- Hamburger Menu -->
            <i class="bi bi-list fs-3 icon-btn" onclick="toggleSidebar()"></i>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- Notification Bell -->
            <?php
            if (file_exists('../includes/notification_bell.php')) {
                include '../includes/notification_bell.php';
            } else {
                echo '<i class="bi bi-bell-fill fs-5 icon-btn"></i>';
            }
            ?>
            <!-- User Profile NOT in Navbar per screenshot, only Sidebar -->
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column" id="sidebar">
        <!-- Close button for mobile? Usually tapping outside or toggle works. -->

        <!-- Profile Section -->
        <div class="profile">
            <img src="<?php echo (!empty($currentUser['profile_photo']) && $currentUser['profile_photo'] !== 'N/A') ? '../' . htmlspecialchars($currentUser['profile_photo']) . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>"
                alt="Profile" onerror="this.src='../assets/profile_pic/user.png';">
            <h5 class="mb-0 mt-2 fw-bold"><?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?></h5>
            <small
                style="color: #ffc107; font-weight: 600;"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User')); ?></small>
        </div>

        <!-- Navigation -->
        <nav class="nav flex-column px-2">
            <?php renderNavigation('Staff Management'); ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="content" id="content">

        <!-- Header Row -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-0">Re-register Face Data</h4>
            </div>
            <a href="staff_profile.php?id=<?php echo htmlspecialchars($employee_id); ?>"
                class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Profile
            </a>
        </div>

        <!-- Main Card -->
        <div class="reg-card">
            <h5 class="mb-4 text-dark fw-bold">Capture Face Data</h5>

            <div class="alert alert-warning mb-4 border-0" style="background-color: #fff3cd; color: #856404;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Warning:</strong> This process will delete all existing face data for
                <strong><?php echo htmlspecialchars($fullName); ?></strong>.
            </div>

            <div id="face_registration_container">

                <div class="camera-section">
                    <!-- Left: Camera -->
                    <div class="camera-container">
                        <video id="video" autoplay muted playsinline></video>
                        <canvas id="canvas" style="display:none;"></canvas>
                        <canvas id="detection-overlay"></canvas>
                    </div>

                    <!-- Right: Status -->
                    <div id="face-guidance">
                        <h4>Face Detection Status:</h4>
                        <div class="status-item" id="face-status">
                            <i class="bi bi-person"></i> Looking for face...
                        </div>
                        <div class="status-item" id="orientation-status">
                            <i class="bi bi-compass"></i> Orientation: Unknown
                        </div>
                        <div class="status-item" id="lighting-status">
                            <i class="bi bi-brightness-high"></i> Lighting: Unknown
                        </div>

                        <div class="instruction-box" id="guidance-message">
                            <i class="bi bi-info-circle-fill me-2"></i> Position your face in the camera view
                        </div>
                    </div>
                </div>

                <!-- Bottom: Step & Actions -->
                <div class="step-box">
                    <h4 id="current-angle">Step 1 of 5: Face Forward</h4>
                    <p id="angle-instruction" class="mb-3">Look directly at the camera with a neutral expression</p>
                    <div class="d-flex justify-content-start gap-2">
                        <button type="button" id="capture-btn" class="btn btn-success px-4"
                            style="background-color: #28a745;">Capture Photo</button>
                        <button type="button" id="skip-btn" class="btn btn-warning px-4 text-white">Skip This
                            Angle</button>
                    </div>
                </div>

                <div id="captured-photos" class="mt-4">
                    <h5 class="fw-bold mb-3">Captured Photos:</h5>
                    <div id="photo-thumbnails" class="d-flex gap-2 flex-wrap"></div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                    <button type="button" id="save-faces-btn" class="btn btn-success px-5 py-2 fw-bold" disabled
                        onclick="submitFaceData()">
                        Save Face Registration
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Hidden inputs for JS data storage -->
    <input type="hidden" id="face_photos" value="">

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4 border-0 shadow">
                <div class="modal-body">
                    <div class="mb-3 text-success">
                        <i class="bi bi-check-circle-fill" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="mb-2 fw-bold text-dark">Success!</h3>
                    <p class="text-muted mb-4">Face data has been successfully updated.</p>
                    <p class="small text-muted">Redirecting to profile in <span id="countdown">3</span>s...</p>

                    <a href="staff_profile.php?id=<?php echo htmlspecialchars($employee_id); ?>"
                        class="btn btn-primary px-4 rounded-pill">
                        Return to Profile Now
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title">Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="errorMessage"></div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; justify-content:center; align-items:center; color:white; flex-direction:column;">
        <div class="spinner-border text-light mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
        <h4 id="loadingText" class="fw-light">Processing...</h4>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="../assets/js/face-detection.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/camera-controller.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/face-registration-app.js?v=<?php echo time(); ?>"></script>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');

            // Logic for desktop vs mobile
            if (window.innerWidth >= 992) {
                // Desktop: Toggle collapsed class
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            } else {
                // Mobile: Toggle active class
                sidebar.classList.toggle('active');
            }
        }

        // Set context
        window.currentFaceEmployeeId = "<?php echo $employee['employee_id']; ?>";

        document.addEventListener('DOMContentLoaded', async function () {
            if (window.FaceRegistrationApp) {
                if (!window.faceApp) {
                    window.faceApp = new FaceRegistrationApp();
                }
                try {
                    await window.faceApp.initialize();
                } catch (e) {
                    console.error('Init Exception:', e);
                }
            } else {
                console.error('FaceRegistrationApp not loaded');
            }
        });

        function submitFaceData() {
            if (!window.currentFaceEmployeeId) return;
            const facePhotos = document.getElementById('face_photos').value;
            if (!facePhotos || facePhotos === '[]') {
                showWizardError('Please capture face photos first.');
                return;
            }
            showLoading('Saving Face Data & Updating Embeddings...');

            const formData = new FormData();
            formData.append('employee_id', window.currentFaceEmployeeId);
            formData.append('face_photos', facePhotos);

            fetch('processes/update_face_registration.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showSuccessAndRedirect();
                    } else {
                        showWizardError(data.message);
                    }
                })
                .catch(err => {
                    hideLoading();
                    showWizardError('Server error: ' + err.message);
                    console.error(err);
                });
        }

        function showSuccessAndRedirect() {
            const modalEl = document.getElementById('successModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();

            let seconds = 3;
            const countdownEl = document.getElementById('countdown');
            const interval = setInterval(() => {
                seconds--;
                if (countdownEl) countdownEl.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(interval);
                    window.location.href = 'staff_profile.php?id=' + window.currentFaceEmployeeId;
                }
            }, 1000);
        }

        function showLoading(msg) {
            document.getElementById('loadingText').textContent = msg;
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        function showWizardError(msg) {
            document.getElementById('errorMessage').textContent = msg;
            new bootstrap.Modal(document.getElementById('errorModal')).show();
        }
    </script>
</body>

</html>