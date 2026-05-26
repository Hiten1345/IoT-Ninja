<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body class="login-page-body">
    <div class="login-wrapper">
        <div class="login-container user-login-container">
            <div class="app-logo">IoT Control Platform</div>
            <h1>User Login</h1>
            <p class="subtitle">Access your IoT dashboard</p>
            <?php if (isset($_GET['registered'])): ?>
                <div class="message success">Registration successful! You can now log in.</div>
            <?php endif; ?>
            <?php if (isset($_GET['reset_success'])): ?>
                <div class="message success">Password reset successfully! You can now log in with your new password.</div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <form method="post" action="login">
                <div class="form-group">
                    <label for="email">Email Address (Gmail)</label>
                    <input type="email" id="email" name="email" placeholder="example@gmail.com" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-button">Login</button>
            </form>
            
            <div class="google-login-separator">
                <span>OR</span>
            </div>

            <div class="google-login-button-container">
                <div id="g_id_onload"
                     data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                     data-login_uri="<?php echo APP_BASE_URL; ?>?action=google_login"
                     data-auto_prompt="false">
                </div>
                <div class="g_id_signin"
                     data-type="standard"
                     data-size="large"
                     data-theme="outline"
                     data-text="sign_in_with"
                     data-shape="rectangular"
                     data-logo_alignment="left">
                </div>
            </div>
            <div class="login-links">
                <?php if (get_app_config('allow_self_registration') === true): ?>
                <a href="register">Create an account</a>
                <br>
                <?php endif; ?>
                <a href="forgot-password">Forgot Password?</a>
            </div>
        </div>
    </div>
</body>
</html>