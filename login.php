<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

// Check if user came from successful registration
if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $success = 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        // Check if input is email or username
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($baglanti, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $username, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['balance'] = $user['balance']; // Load user balance
                
                // After successful login:
                $user_id = $_SESSION['user_id'];

                // Check for shipped, not-notified orders
                $sql = "SELECT * FROM orders WHERE user_id=? AND status='shipped' AND notified=0";
                $stmt = mysqli_prepare($baglanti, $sql);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $result_orders = mysqli_stmt_get_result($stmt);

                if (mysqli_num_rows($result_orders) > 0) {
                    $_SESSION['order_shipped_notification'] = 'Ürününüz başarıyla kargoya verildi.';
                    // Mark as notified
                    $sql_update = "UPDATE orders SET notified=1 WHERE user_id=? AND status='shipped' AND notified=0";
                    $stmt_update = mysqli_prepare($baglanti, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, "i", $user_id);
                    mysqli_stmt_execute($stmt_update);
                }

                header('Location: urunler.php');
                exit();
            } else {
                $error = 'Geçersiz şifre.';
            }
        } else {
            $error = 'Kullanıcı bulunamadı.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Retro Toyz</title>
    <link rel="icon" href="retroToyzLogo.png" type="image/x-icon">
    <style>
        @import url("login.css");
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="retroToyzLogo.png" alt="Retro Toyz Logo">
                Retro Toyz
            </div>
            <p>Hesabınıza giriş yapın</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Kullanıcı Adı veya E-posta</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Giriş Yap</button>
        </form>
        
        <div class="register-link">
            Hesabınız yok mu? <a href="register.php">Kayıt olun</a>
        </div>
    </div>
</body>
</html> 