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
    
    foreach ($links as $link) {
        $activeClass = (strpos($currentPage, $link['label']) !== false) ? 'active' : '';
        echo '<a class="nav-link ' . $activeClass . '" href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<i class="bi ' . $link['icon'] . ' me-2"></i> ' . htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
        echo '</a>' . "\n";
    }
}
?>
