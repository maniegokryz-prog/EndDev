<?php
// Function to get time elapsed string (same as original widget)
if (!function_exists('time_elapsed_string_sync')) {
    function time_elapsed_string_sync($datetime, $full = false)
    {
        if ($datetime == 'Never')
            return 'Never';
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        // Calculate weeks manually to avoid deprecation warning for dynamic property
        $weeks = floor($diff->d / 7);
        $days = $diff->d - ($weeks * 7);

        $time_map = [
            'y' => $diff->y,
            'm' => $diff->m,
            'w' => $weeks,
            'd' => $days,
            'h' => $diff->h,
            'i' => $diff->i,
            's' => $diff->s,
        ];

        $string = array('y' => 'y', 'm' => 'm', 'w' => 'w', 'd' => 'd', 'h' => 'h', 'i' => 'm', 's' => 's');
        foreach ($string as $k => &$v) {
            if ($time_map[$k]) {
                $v = $time_map[$k] . $v;
            } else {
                unset($string[$k]);
            }
        }
        if (!$full)
            $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}

// Path to sync logs (relative to admin/includes/)
$logFile = __DIR__ . '/../../logs/sync_status.json';
$status = "Unknown";
$lastSync = "Never";
$message = "Waiting...";
$badgeClass = "bg-secondary";
$icon = "bi-cloud-slash";
$tooltip = "Sync Status: Unknown";

if (file_exists($logFile)) {
    $data = json_decode(file_get_contents($logFile), true);
    if ($data) {
        $lastSyncTime = isset($data['last_sync']) ? $data['last_sync'] : null;

        if ($lastSyncTime) {
            $lastSync = time_elapsed_string_sync($lastSyncTime);
            $diffSeconds = time() - strtotime($lastSyncTime);

            if ($diffSeconds > 30) {
                $status = "Stale";
                $badgeClass = "bg-warning text-dark";
                $icon = "bi-cloud-minus";
                $tooltip = "Last synced " . $lastSync;
            } else {
                if (isset($data['status']) && $data['status'] === 'success') {
                    $status = "Active";
                    $badgeClass = "bg-success";
                    $icon = "bi-cloud-check";
                    $tooltip = "Synced " . $lastSync;
                } else {
                    $status = "Error";
                    $badgeClass = "bg-danger";
                    $icon = "bi-cloud-slash";
                    $tooltip = "Sync failed: " . (isset($data['message']) ? $data['message'] : "Unknown error");
                }
            }
        }
    }
}
?>
<!-- Topbar Info Pill -->
<a href="../settings/sync_cloud_settings.php" class="text-decoration-none me-3" title="<?php echo $tooltip; ?>"
    data-bs-toggle="tooltip" data-bs-placement="bottom">
    <div
        class="badge rounded-pill <?php echo $badgeClass; ?> d-flex align-items-center py-2 px-3 shadow-sm border border-light">
        <i class="bi <?php echo $icon; ?> me-2 fs-6"></i>
        <span class="fw-normal d-none d-sm-inline">Sync: <strong><?php echo $status; ?></strong></span>
    </div>
</a>