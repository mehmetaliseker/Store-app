<?php
session_start();
require_once 'config.php';

$error = '';

// If user is already logged in, redirect appropriately
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['username'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: urunler.php');
    }
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        // Check user credentials
        $sql = "SELECT id, username, password, full_name FROM users WHERE username = ? OR email = ?";
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
                
                // Redirect based on user type
                if ($user['username'] === 'admin') {
                    header('Location: admin.php');
                } else {
                    header('Location: urunler.php');
                }
                exit();
            } else {
                $error = 'Geçersiz kullanıcı adı veya şifre.';
            }
        } else {
            $error = 'Geçersiz kullanıcı adı veya şifre.';
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
        @import url("index.css");
    </style>
</head>
<body>
    <div class="wrapper">
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="retroToyzLogo.png" alt="Retro Toyz Logo">
                Retro Toyz
            </div>
            <p>Hesabınıza giriş yapın</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Kullanıcı Adı veya E-posta</label>
                <input type="text" placeholder="Kullanıcı Adı Giriniz.." id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" placeholder="Şifre Giriniz.." id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Giriş Yap</button>
        </form>
        
        <div class="register-link">
            Hesabınız yok mu? <a href="register.php">Kayıt olun</a>
        </div>
    </div>
        </div>
</body>
</html> 