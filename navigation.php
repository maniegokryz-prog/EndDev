<?php
/**
 * Navigation Helper
 * Generates role-based navigation links
 */

function getNavigationLinks() {
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
        // Add My Profile for admin
        $employeeId = $currentUser['employee_id'] ?? '';
        $links[] = [
            'url' => '../staffmanagement/staffinfo.php?id=' . urlencode($employeeId),
            'icon' => 'bi-person-circle',
            'label' => 'My Profile'
        ];
        $links[] = [
            'url' => '../settings/settings.php',
            'icon' => 'bi-gear',
            'label' => 'Settings'
        ];
    } else {
        // Regular user navigation
        $employeeId = $currentUser['employee_id'] ?? '';
        $links[] = [
            'url' => '../staffmanagement/staffinfo.php?id=' . urlencode($employeeId),
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
        'url' => 'logout.php',
        'icon' => 'bi-box-arrow-left',
        'label' => 'Logout'
    ];
    
    return $links;
}

function renderNavigation($currentPage = '') {
    $links = getNavigationLinks();

    // Inject minimal CSS so active indicator is visible on all pages
    // without requiring each page to include the dashboard stylesheet.
    echo "<style>\n";
    echo ".sidebar .nav-link{display:flex;align-items:center;padding:10px 12px;color:#fff;text-decoration:none;}\n";
    echo ".sidebar .nav-link i{min-width:30px;}\n";
    echo ".sidebar .nav-link.active{background-color:#d8af35;color:#103932;font-weight:700;border-radius:8px;margin:4px 0;padding:10px 12px;}\n";
    echo "</style>\n";

    // Determine current script filename (basename) for URL-based matching
    $currentScript = '';
    if (!empty($_SERVER['REQUEST_URI'])) {
        $currentScript = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    }

    foreach ($links as $link) {
        $activeClass = '';

        // 1) Try filename-based matching (most robust)
        if (!empty($link['url'])) {
            $linkFile = basename(parse_url($link['url'], PHP_URL_PATH));
            if ($linkFile !== '' && $currentScript !== '' && strcasecmp($linkFile, $currentScript) === 0) {
                $activeClass = 'active';
            }
        }

        // 2) Fallback: allow case-insensitive partial label matching
        if ($activeClass === '' && trim($currentPage) !== '') {
            $cmpCurrent = strtolower(trim($currentPage));
            $cmpLabel = strtolower(trim($link['label']));
            if (strpos($cmpLabel, $cmpCurrent) !== false || strpos($cmpCurrent, $cmpLabel) !== false) {
                $activeClass = 'active';
            }
        }

        echo '<a class="nav-link ' . $activeClass . '" href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<i class="bi ' . $link['icon'] . ' me-2"></i> ' . htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
        echo '</a>' . "\n";
    }
}
?>
