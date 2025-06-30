<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';
$redirect_to_login = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    
    // Address fields
    $address_title = trim($_POST['address_title']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $district = trim($_POST['district']);
    $full_address = trim($_POST['full_address']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Tüm alanları doldurunuz.';
    } elseif ($password !== $confirm_password) {
        $error = 'Şifreler eşleşmiyor.';
    } elseif (strlen($password) < 3) {
        $error = 'Şifre en az 3 karakter olmalıdır.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi giriniz.';
    } elseif (empty($address_title) || empty($phone) || empty($city) || empty($district) || empty($full_address)) {
        $error = 'Tüm adres alanları gereklidir.';
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = mysqli_prepare($baglanti, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Bu kullanıcı adı veya e-posta adresi zaten kullanılıyor.';
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($baglanti, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "ssss", $username, $email, $hashed_password, $full_name);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $user_id = mysqli_insert_id($baglanti);
                
                // Insert address
                $address_sql = "INSERT INTO user_addresses (user_id, address_title, full_name, phone, city, district, full_address, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                $address_stmt = mysqli_prepare($baglanti, $address_sql);
                mysqli_stmt_bind_param($address_stmt, "issssss", $user_id, $address_title, $full_name, $phone, $city, $district, $full_address);
                mysqli_stmt_execute($address_stmt);
                
                $success = 'Kayıt başarılı! Giriş sayfasına yönlendiriliyorsunuz...';
                $redirect_to_login = true;
            } else {
                $error = 'Kayıt sırasında bir hata oluştu.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Retro Toyz</title>
    <link rel="icon" href="retroToyzLogo.png" type="image/x-icon">
    <style>
        @import url("register.css");
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="retroToyzLogo.png" alt="Retro Toyz Logo">
                Retro Toyz
            </div>
            <p>Hesabınızı oluşturun</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <div class="countdown" id="countdown">3</div>
            </div>
        <?php endif; ?>
        
        <?php if (!$redirect_to_login): ?>
        <form method="POST" action="">
            <!-- Account Information -->
            <div class="form-section">
                <h3>Hesap Bilgileri</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Ad Soyad</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Kullanıcı Adı</label>
                        <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Telefon</label>
                        <input type="tel" id="phone" name="phone" placeholder="0555 123 45 67" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Şifre</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Şifre Tekrar</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="form-section">
                <h3>Adres Bilgileri</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="address_title">Adres Başlığı</label>
                        <input type="text" id="address_title" name="address_title" placeholder="Ev, İş, vb." value="<?php echo isset($_POST['address_title']) ? htmlspecialchars($_POST['address_title']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="city">Şehir</label>
                        <input type="text" id="city" name="city" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="district">İlçe</label>
                        <input type="text" id="district" name="district" value="<?php echo isset($_POST['district']) ? htmlspecialchars($_POST['district']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="full_address">Tam Adres</label>
                        <textarea id="full_address" name="full_address" placeholder="Mahalle, sokak, bina no, daire no" required><?php echo isset($_POST['full_address']) ? htmlspecialchars($_POST['full_address']) : ''; ?></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn">Kayıt Ol</button>
        </form>
        
        <div class="login-link">
            Zaten hesabınız var mı? <a href="login.php">Giriş yapın</a>
        </div>
        <?php else: ?>
        <div class="redirect-message">
            <p>Giriş sayfasına yönlendiriliyorsunuz...</p>
            <p><a href="login.php?registered=success">Hemen giriş yapmak için tıklayın</a></p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($redirect_to_login): ?>
    <script>
        let countdown = 3;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = 'login.php?registered=success';
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html> 