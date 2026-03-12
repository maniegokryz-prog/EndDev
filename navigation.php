<?php
/**
 * Navigation Helper
 * Generates role-based navigation links
 */

// TOGGLE: Set to true to hide the Sync Settings navigation link (for VPS deployment)
$hide_sync_settings = true;

function getNavigationLinks()
{
    global $hide_sync_settings;
    $currentUser = getCurrentUser();
    $isAdmin = ($currentUser['role'] === 'admin' || ($currentUser['is_system_admin'] ?? false));

    $links = [];

    if ($isAdmin) {
        // Admin navigation
        $links[] = [
            'url' => '../dashboard/dashboard.php',
            'icon' => 'bi-house-door',
            'label' => 'Dashboard'
        ];
        $links[] = [
            'url' => '../attendancerep/attendancerep.php',
            'icon' => 'bi-file-earmark-bar-graph',
            'label' => 'Attendance Reports'
        ];
        $links[] = [
            'url' => '../staffmanagement/staff.php',
            'icon' => 'bi-people',
            'label' => 'Staff Management'
        ];
        // Add My Profile for admin (except for default admin account)
        $username = $currentUser['username'] ?? '';
        $employeeId = $currentUser['employee_id'] ?? '';
        if ($username !== 'admin') {
            $links[] = [
                'url' => '../staffmanagement/staff_profile.php?id=' . urlencode($employeeId),
                'icon' => 'bi-person-circle',
                'label' => 'My Profile'
            ];
        }
        $links[] = [
            'url' => '../settings/settings.php',
            'icon' => 'bi-gear',
            'label' => 'Settings'
        ];

        if (!$hide_sync_settings) {
            $links[] = [
                'url' => '../settings/sync_cloud_settings.php',
                'icon' => 'bi-cloud-arrow-up',
                'label' => 'Sync Settings'
            ];
        }
    } else {
        // Regular user navigation
        $employeeId = $currentUser['employee_id'] ?? '';
        $links[] = [
            'url' => '../staffmanagement/staff_profile.php?id=' . urlencode($employeeId),
            'icon' => 'bi-house-door',
            'label' => 'My Info'
        ];
        $links[] = [
            'url' => '../attendancerep/indirep.php?employee_id=' . urlencode($employeeId),
            'icon' => 'bi-file-earmark-bar-graph',
            'label' => 'My Attendance'
        ];
        // Staff Management is hidden for regular users
        $links[] = [
            'url' => '../settings/settings.php',
            'icon' => 'bi-gear',
            'label' => 'Settings'
        ];
    }

    // Logout link (same for everyone)
    $links[] = [
        'url' => '#',
        'icon' => 'bi-box-arrow-left',
        'label' => 'Logout',
        'onclick' => 'event.preventDefault(); showLogoutModal();'
    ];

    return $links;
}

function renderNavigation($currentPage = '')
{
    $links = getNavigationLinks();

    // Inject minimal CSS so active indicator is visible on all pages
    // without requiring each page to include the dashboard stylesheet.
    echo "<style>\n";
    echo ".sidebar .nav-link{display:flex !important;align-items:center !important;padding:10px 12px !important;color:#fff !important;text-decoration:none !important;margin:4px 0 !important;white-space:nowrap !important;font-family:Arial,sans-serif !important;font-weight:normal !important;font-size:15px !important;line-height:1.5 !important;box-sizing:border-box !important;border:1px solid transparent !important;letter-spacing:normal !important;}\n";
    echo ".sidebar .nav-link i{width:30px !important;min-width:30px !important;font-size:18px !important;margin-right:10px !important;text-align:center !important;display:inline-block !important;}\n";
    echo ".sidebar .nav-link.active{background-color:#d8af35 !important;color:#103932 !important;font-weight:normal !important;border-radius:8px !important;margin:4px 0 !important;padding:10px 12px !important;}\n";
    echo "</style>\n";

    // Determine current script filename (basename) for URL-based matching
    $currentScript = '';
    if (!empty($_SERVER['REQUEST_URI'])) {
        $currentScript = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    }

    // -------------------------------------------------------------------------
    // DETERMINE THE SINGLE ACTIVE LINK
    // -------------------------------------------------------------------------
    $activeIndex = -1;
    $currentPage = trim($currentPage);
    
    // 1. Precise Label Match (Case-Insensitive)
    if ($currentPage !== '') {
        foreach ($links as $index => $link) {
            if (strcasecmp(trim($link['label']), $currentPage) === 0) {
                $activeIndex = $index;
                break;
            }
        }
    }

    // 2. Exact Filename Match
    if ($activeIndex === -1 && $currentScript !== '') {
        foreach ($links as $index => $link) {
            if (!empty($link['url']) && $link['url'] !== '#') {
                $parsedUrl = parse_url($link['url'], PHP_URL_PATH);
                if ($parsedUrl !== null) {
                    $linkFile = basename($parsedUrl);
                    if ($linkFile !== '' && strcasecmp($linkFile, $currentScript) === 0) {
                        // For staff_profile.php, we need to be careful. 
                        // If we are on staff_profile but $currentPage was something else that didn't match,
                        // this Filename match is a good fallback.
                        $activeIndex = $index;
                        break;
                    }
                }
            }
        }
    }

    // 3. Partial Label Match (Fuzzy Fallback)
    if ($activeIndex === -1 && $currentPage !== '') {
        $cmpCurrent = strtolower($currentPage);
        foreach ($links as $index => $link) {
            $cmpLabel = strtolower(trim($link['label']));
            if (strpos($cmpLabel, $cmpCurrent) !== false || strpos($cmpCurrent, $cmpLabel) !== false) {
                // Exclusion logic for settings vs sync settings
                if (($cmpCurrent === 'settings' && $cmpLabel === 'sync settings') ||
                    ($cmpCurrent === 'sync settings' && $cmpLabel === 'settings')) {
                    continue;
                }
                $activeIndex = $index;
                break;
            }
        }
    }

    // -------------------------------------------------------------------------
    // RENDER LINKS
    // -------------------------------------------------------------------------
    foreach ($links as $index => $link) {
        $activeClass = ($index === $activeIndex) ? 'active' : '';

        // Check if link has onclick attribute
        $onclickAttr = isset($link['onclick']) ? 'onclick="' . htmlspecialchars($link['onclick'], ENT_QUOTES, 'UTF-8') . '"' : '';

        echo '<a class="nav-link ' . $activeClass . '" href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '" ' . $onclickAttr . '>';
        echo '<i class="bi ' . $link['icon'] . '"></i> ' . htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
        echo '</a>' . "\n";
    }

    // --- Global Background Sync Engine ---
    // Inject this script into every page to ensure instant cross-app syncing
    echo "<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Only trigger the sync engine if we are on the Localhost server
        if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') return;
        
        function triggerGlobalSync() {
            // Determine relative path to api directory
            const pathSegments = window.location.pathname.split('/');
            const baseFolder = pathSegments.length > 1 && pathSegments[1] ? '/' + pathSegments[1] : '';
            const apiPath = baseFolder + '/api/run_sync.php';

            fetch(apiPath)
                .then(r => r.json())
                .then(data => {
                    if (data.success && (data.stats.pushed > 0 || data.stats.pulled > 0)) {
                        console.log('Global Background Auto-Sync:', data.stats);
                        // If we are currently on the dashboard, gracefully refresh the feed when new data arrives
                        if (typeof loadDefaultAttendanceFeed === 'function') {
                            // Only reload feed if it's the dashboard
                            if(document.getElementById('attendanceList')) loadDefaultAttendanceFeed();
                        }
                    }
                }).catch(e => console.error('Sync Error:', e));
        }

        // Fire instantly when ANY page loads!
        triggerGlobalSync();
        
        // Then fire every 30 seconds instead of 60 seconds for faster replication
        setInterval(triggerGlobalSync, 30000);
    });
    </script>\n";
}
?>