<?php
// Function to get time elapsed string
function time_elapsed_string_sync($datetime, $full = false) {
    if ($datetime == 'Never') return 'Never';
    
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'min',
        's' => 'sec',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Path to sync logs (relative to admin/includes/)
$logFile = '../../logs/sync_status.json';
$status = "Unknown";
$lastSync = "Never";
$message = "Waiting for sync data...";
$color = "secondary"; // gray
$icon = "bi-cloud-slash";

if (file_exists($logFile)) {
    $data = json_decode(file_get_contents($logFile), true);
    if ($data) {
        $lastSyncTime = isset($data['last_sync']) ? $data['last_sync'] : null;
        
        if ($lastSyncTime) {
            $lastSync = time_elapsed_string_sync($lastSyncTime);
            
            //Check if sync is stale (older than 5 mins)
            $diffSeconds = time() - strtotime($lastSyncTime);
            if ($diffSeconds > 300) {
                $status = "Stale";
                $color = "warning";
                $icon = "bi-cloud-minus";
                $message = "Last sync was " . $lastSync;
            } else {
                if (isset($data['status']) && $data['status'] === 'success') {
                    $status = "Active";
                    $color = "success";
                    $icon = "bi-cloud-check";
                    $message = "Synced " . $lastSync;
                } else {
                    $status = "Error";
                    $color = "danger";
                    $icon = "bi-cloud-slash";
                    $message = isset($data['message']) ? $data['message'] : "Sync failed";
                }
            }
        }
    }
}
?>

<div class="col-xl-3 col-md-6 mb-4">
    <div class="card border-left-<?php echo $color; ?> shadow h-100 py-2">
        <div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-<?php echo $color; ?> text-uppercase mb-1">
                        IONOS Cloud Sync
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <?php echo $status; ?>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        <?php echo $message; ?>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="bi <?php echo $icon; ?> fa-2x text-gray-300" style="font-size: 2rem;"></i>
                </div>
            </div>
            <div class="row mt-2">
                 <div class="col-12">
                    <div class="progress progress-sm mr-2">
                        <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar"
                             style="width: 100%" aria-valuenow="100" aria-valuemin="0"
                             aria-valuemax="100"></div>
                    </div>
                 </div>
            </div>
            <div class="row mt-2">
                 <div class="col-12 text-center">
                    <a href="../settings/sync_cloud_settings.php" class="text-xs font-weight-bold text-<?php echo $color; ?>">
                        Manage Settings <i class="bi bi-arrow-right"></i>
                    </a>
                 </div>
            </div>
        </div>
    </div>
</div>
