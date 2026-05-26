<?php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ' . get_admin_page_url());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor User - <?php echo htmlspecialchars($user_uid_to_monitor); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="top-bar-admin">
        <a href="<?php echo get_admin_page_url(); ?>" style="text-decoration: none; color: white; font-size: 24px; padding: 0 10px; margin-right: 10px;" title="Back to Admin Panel">←</a>
        <div class="logo">User Monitor</div>
        <div class="admin-info">Monitoring: <strong><?php echo htmlspecialchars($user_uid_to_monitor); ?></strong></div>
        <div class="time-display-admin" id="admin-time-display">IST: Loading...</div>
    </div>
    <div class="main-container-admin" style="display: block;">
        <div class="content-admin">
            <h2>Live Data for User: <?php echo htmlspecialchars($user_uid_to_monitor); ?></h2>
            <div class="table-container">
                <table id="userMonitorTable">
                    <thead><tr><th>Loading...</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="footer-admin">
        <span>&copy; <?php echo date('Y'); ?> Ninja IoT Admin</span>
        <span id="admin-footer-time-display">IST: ...</span>
    </div>

    <script src="scripts.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const uid = "<?php echo htmlspecialchars($user_uid_to_monitor); ?>";
            // Initial call to populate the table immediately
            AdminApp.updateSingleUserMonitorTable(uid);
            // Set an interval to refresh the data every 7 seconds
            setInterval(() => AdminApp.updateSingleUserMonitorTable(uid), 7000);
            
            // Also keep the clock updated
            AdminApp.updateAdminTimeDisplay();
            setInterval(AdminApp.updateAdminTimeDisplay, 1000);
        });
    </script>
</body>
</html>