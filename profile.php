<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Load user balance if not set
if (!isset($_SESSION['balance'])) {
    $balance_sql = "SELECT balance FROM users WHERE id = ?";
    $balance_stmt = mysqli_prepare($baglanti, $balance_sql);
    mysqli_stmt_bind_param($balance_stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($balance_stmt);
    $balance_result = mysqli_stmt_get_result($balance_stmt);
    $user_balance = mysqli_fetch_assoc($balance_result);
    $_SESSION['balance'] = $user_balance['balance'];
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    if (empty($full_name) || empty($email)) {
        $error_message = "Ad soyad ve e-posta gereklidir.";
    } else {
        // Check if email is already taken by another user
        $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_stmt = mysqli_prepare($baglanti, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "si", $email, $_SESSION['user_id']);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_message = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.";
        } else {
            $update_sql = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($baglanti, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ssi", $full_name, $email, $_SESSION['user_id']);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $_SESSION['full_name'] = $full_name;
                $success_message = "Profil bilgileri güncellendi!";
            } else {
                $error_message = "Profil güncellenirken bir hata oluştu.";
            }
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Tüm şifre alanları gereklidir.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Yeni şifreler eşleşmiyor.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Yeni şifre en az 6 karakter olmalıdır.";
    } else {
        // Verify current password
        $verify_sql = "SELECT password FROM users WHERE id = ?";
        $verify_stmt = mysqli_prepare($baglanti, $verify_sql);
        mysqli_stmt_bind_param($verify_stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);
        $user = mysqli_fetch_assoc($verify_result);
        
        if (password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_sql = "UPDATE users SET password = ? WHERE id = ?";
            $password_stmt = mysqli_prepare($baglanti, $password_sql);
            mysqli_stmt_bind_param($password_stmt, "si", $hashed_password, $_SESSION['user_id']);
            
            if (mysqli_stmt_execute($password_stmt)) {
                $success_message = "Şifre başarıyla değiştirildi!";
            } else {
                $error_message = "Şifre değiştirilirken bir hata oluştu.";
            }
        } else {
            $error_message = "Mevcut şifre yanlış.";
        }
    }
}

// Handle address operations
if (isset($_POST['add_address'])) {
    $address_title = trim($_POST['address_title']);
    $full_name = trim($_POST['address_full_name']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $district = trim($_POST['district']);
    $full_address = trim($_POST['full_address']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    if (empty($address_title) || empty($full_name) || empty($phone) || empty($city) || empty($district) || empty($full_address)) {
        $error_message = "Tüm adres alanları gereklidir.";
    } else {
        // If this is set as default, unset other defaults
        if ($is_default) {
            $unset_default_sql = "UPDATE user_addresses SET is_default = 0 WHERE user_id = ?";
            $unset_default_stmt = mysqli_prepare($baglanti, $unset_default_sql);
            mysqli_stmt_bind_param($unset_default_stmt, "i", $_SESSION['user_id']);
            mysqli_stmt_execute($unset_default_stmt);
        }
        
        $insert_sql = "INSERT INTO user_addresses (user_id, address_title, full_name, phone, city, district, full_address, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($baglanti, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "issssssi", $_SESSION['user_id'], $address_title, $full_name, $phone, $city, $district, $full_address, $is_default);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $success_message = "Adres başarıyla eklendi!";
        } else {
            $error_message = "Adres eklenirken bir hata oluştu.";
        }
    }
}

if (isset($_POST['update_address'])) {
    $address_id = (int)$_POST['address_id'];
    $address_title = trim($_POST['address_title']);
    $full_name = trim($_POST['address_full_name']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $district = trim($_POST['district']);
    $full_address = trim($_POST['full_address']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    if (empty($address_title) || empty($full_name) || empty($phone) || empty($city) || empty($district) || empty($full_address)) {
        $error_message = "Tüm adres alanları gereklidir.";
    } else {
        // If this is set as default, unset other defaults
        if ($is_default) {
            $unset_default_sql = "UPDATE user_addresses SET is_default = 0 WHERE user_id = ? AND id != ?";
            $unset_default_stmt = mysqli_prepare($baglanti, $unset_default_sql);
            mysqli_stmt_bind_param($unset_default_stmt, "ii", $_SESSION['user_id'], $address_id);
            mysqli_stmt_execute($unset_default_stmt);
        }
        
        $update_sql = "UPDATE user_addresses SET address_title = ?, full_name = ?, phone = ?, city = ?, district = ?, full_address = ?, is_default = ? WHERE id = ? AND user_id = ?";
        $update_stmt = mysqli_prepare($baglanti, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssssssiii", $address_title, $full_name, $phone, $city, $district, $full_address, $is_default, $address_id, $_SESSION['user_id']);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success_message = "Adres başarıyla güncellendi!";
        } else {
            $error_message = "Adres güncellenirken bir hata oluştu.";
        }
    }
}

if (isset($_POST['delete_address'])) {
    $address_id = (int)$_POST['address_id'];
    
    $delete_sql = "DELETE FROM user_addresses WHERE id = ? AND user_id = ?";
    $delete_stmt = mysqli_prepare($baglanti, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "ii", $address_id, $_SESSION['user_id']);
    
    if (mysqli_stmt_execute($delete_stmt)) {
        $success_message = "Adres başarıyla silindi!";
    } else {
        $error_message = "Adres silinirken bir hata oluştu.";
    }
}

// Get current user data
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($baglanti, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user_data = mysqli_fetch_assoc($user_result);

// Get user addresses
$addresses_sql = "SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC";
$addresses_stmt = mysqli_prepare($baglanti, $addresses_sql);
mysqli_stmt_bind_param($addresses_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($addresses_stmt);
$addresses_result = mysqli_stmt_get_result($addresses_stmt);
$addresses = [];
while ($address = mysqli_fetch_assoc($addresses_result)) {
    $addresses[] = $address;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Retro Toyz</title>
    <link rel="icon" href="retroToyzLogo.png" type="image/x-icon">
    <style>
        @import url("profile.css");
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <nav class="nav">
                <div class="logo">
                    <img src="retroToyzLogo.png" alt="Retro Toyz Logo">
                    Retro Toyz
                </div>
                <div class="nav-links">
                    <a href="urunler.php">Ürünler</a>
                    <a href="profile.php">Profil</a>
                    <a href="tracking.php" class="nav-link">Ürün Takibi</a>
                    <div class="balance-display">
                        <?php echo number_format($_SESSION['balance'], 2); ?>₺
                    </div>
                    <a href="balance.php" class="balance-load-btn">Bakiye Yükle</a>
                    <a href="cart.php" class="cart-icon">
                        Sepetim
                        <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                            <span class="cart-count"><?php echo count($_SESSION['cart']); ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="logout.php" class="logout-btn">Çıkış</a>
                </div>
            </nav>
        </div>
        
        <h1 class="page-title">Profil</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <div class="profile-container">
            <!-- Profile Information -->
            <div class="profile-card">
                <h2>Hesap Bilgileri</h2>
                
                <div class="user-info">
                    <div class="info-row">
                        <span class="info-label">Kullanıcı Adı:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user_data['username']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ad Soyad:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user_data['full_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">E-posta:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Bakiye:</span>
                        <span class="info-value"><?php echo number_format($user_data['balance'], 2); ?>₺</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Kayıt Tarihi:</span>
                        <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($user_data['created_at'])); ?></span>
                    </div>
                </div>
                
                <h3>Profil Bilgilerini Güncelle</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="full_name">Ad Soyad</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        Profili Güncelle
                    </button>
                </form>
            </div>
            
            <!-- Password Change -->
            <div class="profile-card">
                <h2>Şifre Değiştir</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Mevcut Şifre</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Yeni Şifre</label>
                        <input type="password" id="new_password" name="new_password" 
                               minlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Yeni Şifre (Tekrar)</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               minlength="6" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-success">
                        Şifreyi Değiştir
                    </button>
                </form>
                
                <div style="margin-top: 30px; padding: 20px; background: #e3f2fd; border-radius: 8px;">
                    <h3>Bakiye Yönetimi</h3>
                    <p>Mevcut bakiyeniz: <strong><?php echo number_format($_SESSION['balance'], 2); ?>₺</strong></p>
                    <a href="balance.php" class="btn btn-primary" style="text-decoration: none; display: inline-block; margin-top: 15px;">
                        Bakiye Yükle
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Address Management -->
        <div class="profile-card" style="margin-top: 30px;">
            <h2>Adres Yönetimi</h2>
            
            <!-- Add New Address Form -->
            <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                <h3>Yeni Adres Ekle</h3>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="address_title">Adres Başlığı</label>
                            <input type="text" id="address_title" name="address_title" placeholder="Ev, İş, vb." required>
                        </div>
                        
                        <div class="form-group">
                            <label for="address_full_name">Ad Soyad</label>
                            <input type="text" id="address_full_name" name="address_full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefon</label>
                            <input type="tel" id="phone" name="phone" placeholder="0555 123 45 67" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="city">Şehir</label>
                            <input type="text" id="city" name="city" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="district">İlçe</label>
                            <input type="text" id="district" name="district" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="full_address">Tam Adres</label>
                            <textarea id="full_address" name="full_address" placeholder="Mahalle, sokak, bina no, daire no" required></textarea>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_default" name="is_default">
                        <label for="is_default">Varsayılan adres olarak ayarla</label>
                    </div>
                    
                    <button type="submit" name="add_address" class="btn btn-primary">
                        Adres Ekle
                    </button>
                </form>
            </div>
            
            <!-- Existing Addresses -->
            <h3>Mevcut Adreslerim</h3>
            <?php if (empty($addresses)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">Henüz adres eklenmemiş.</p>
            <?php else: ?>
                <?php foreach ($addresses as $address): ?>
                    <div class="address-card <?php echo $address['is_default'] ? 'default' : ''; ?>" id="address-<?php echo $address['id']; ?>">
                        <div class="address-header">
                            <div class="address-title">
                                <?php echo htmlspecialchars($address['address_title']); ?>
                                <?php if ($address['is_default']): ?>
                                    <span class="default-badge">Varsayılan</span>
                                <?php endif; ?>
                            </div>
                            <div class="address-actions">
                                <button onclick="toggleEditAddress(<?php echo $address['id']; ?>)" class="btn btn-primary btn-small">Düzenle</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bu adresi silmek istediğinizden emin misiniz?')">
                                    <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                    <button type="submit" name="delete_address" class="btn btn-danger btn-small">Sil</button>
                                </form>
                            </div>
                        </div>
                        <div class="address-details" id="address-details-<?php echo $address['id']; ?>">
                            <strong><?php echo htmlspecialchars($address['full_name']); ?></strong><br>
                            <?php echo htmlspecialchars($address['phone']); ?><br>
                            <?php echo htmlspecialchars($address['city']); ?> / <?php echo htmlspecialchars($address['district']); ?><br>
                            <?php echo nl2br(htmlspecialchars($address['full_address'])); ?>
                        </div>
                        
                        <!-- Edit Form (Hidden by default) -->
                        <div class="edit-form" id="edit-form-<?php echo $address['id']; ?>" style="display: none;">
                            <h4>Adres Düzenle</h4>
                            <form method="POST" onsubmit="return validateEditForm(<?php echo $address['id']; ?>)">
                                <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="edit_address_title_<?php echo $address['id']; ?>">Adres Başlığı</label>
                                        <input type="text" id="edit_address_title_<?php echo $address['id']; ?>" name="address_title" 
                                               value="<?php echo htmlspecialchars($address['address_title']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_address_full_name_<?php echo $address['id']; ?>">Ad Soyad</label>
                                        <input type="text" id="edit_address_full_name_<?php echo $address['id']; ?>" name="address_full_name" 
                                               value="<?php echo htmlspecialchars($address['full_name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_phone_<?php echo $address['id']; ?>">Telefon</label>
                                        <input type="tel" id="edit_phone_<?php echo $address['id']; ?>" name="phone" 
                                               value="<?php echo htmlspecialchars($address['phone']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_city_<?php echo $address['id']; ?>">Şehir</label>
                                        <input type="text" id="edit_city_<?php echo $address['id']; ?>" name="city" 
                                               value="<?php echo htmlspecialchars($address['city']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_district_<?php echo $address['id']; ?>">İlçe</label>
                                        <input type="text" id="edit_district_<?php echo $address['id']; ?>" name="district" 
                                               value="<?php echo htmlspecialchars($address['district']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label for="edit_full_address_<?php echo $address['id']; ?>">Tam Adres</label>
                                        <textarea id="edit_full_address_<?php echo $address['id']; ?>" name="full_address" required><?php echo htmlspecialchars($address['full_address']); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="checkbox-group">
                                    <input type="checkbox" id="edit_is_default_<?php echo $address['id']; ?>" name="is_default" 
                                           <?php echo $address['is_default'] ? 'checked' : ''; ?>>
                                    <label for="edit_is_default_<?php echo $address['id']; ?>">Varsayılan adres olarak ayarla</label>
                                </div>
                                
                                <div class="edit-actions">
                                    <button type="submit" name="update_address" class="btn btn-primary">Güncelle</button>
                                    <button type="button" onclick="cancelEdit(<?php echo $address['id']; ?>)" class="btn btn-cancel">İptal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleEditAddress(addressId) {
            const addressDetails = document.getElementById('address-details-' + addressId);
            const editForm = document.getElementById('edit-form-' + addressId);
            
            if (editForm.style.display === 'none') {
                // Show edit form
                addressDetails.style.display = 'none';
                editForm.style.display = 'block';
            } else {
                // Hide edit form
                addressDetails.style.display = 'block';
                editForm.style.display = 'none';
            }
        }
        
        function cancelEdit(addressId) {
            const addressDetails = document.getElementById('address-details-' + addressId);
            const editForm = document.getElementById('edit-form-' + addressId);
            
            addressDetails.style.display = 'block';
            editForm.style.display = 'none';
        }
        
        function validateEditForm(addressId) {
            const title = document.getElementById('edit_address_title_' + addressId).value.trim();
            const fullName = document.getElementById('edit_address_full_name_' + addressId).value.trim();
            const phone = document.getElementById('edit_phone_' + addressId).value.trim();
            const city = document.getElementById('edit_city_' + addressId).value.trim();
            const district = document.getElementById('edit_district_' + addressId).value.trim();
            const fullAddress = document.getElementById('edit_full_address_' + addressId).value.trim();
            
            if (!title || !fullName || !phone || !city || !district || !fullAddress) {
                alert('Lütfen tüm alanları doldurunuz.');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html> 