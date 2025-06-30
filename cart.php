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

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if user has addresses
$has_addresses = false;
$default_address = null;
$addresses_sql = "SELECT COUNT(*) as count FROM user_addresses WHERE user_id = ?";
$addresses_stmt = mysqli_prepare($baglanti, $addresses_sql);
mysqli_stmt_bind_param($addresses_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($addresses_stmt);
$addresses_result = mysqli_stmt_get_result($addresses_stmt);
$addresses_count = mysqli_fetch_assoc($addresses_result);
$has_addresses = $addresses_count['count'] > 0;

// Get default address if user has addresses
if ($has_addresses) {
    $default_address_sql = "SELECT * FROM user_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1";
    $default_address_stmt = mysqli_prepare($baglanti, $default_address_sql);
    mysqli_stmt_bind_param($default_address_stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($default_address_stmt);
    $default_address_result = mysqli_stmt_get_result($default_address_stmt);
    $default_address = mysqli_fetch_assoc($default_address_result);
}

// Handle cart updates
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantities'] as $product_id => $quantity) {
        $product_id = (int)$product_id;
        $quantity = (int)$quantity;
        
        if ($quantity > 0) {
            // Check stock availability
            $stock_sql = "SELECT urunAdet FROM urunler WHERE urunId = ?";
            $stock_stmt = mysqli_prepare($baglanti, $stock_sql);
            mysqli_stmt_bind_param($stock_stmt, "i", $product_id);
            mysqli_stmt_execute($stock_stmt);
            $stock_result = mysqli_stmt_get_result($stock_stmt);
            $stock_data = mysqli_fetch_assoc($stock_result);
            
            if ($quantity <= $stock_data['urunAdet']) {
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            } else {
                $error_message = "Yetersiz stok! Maksimum " . $stock_data['urunAdet'] . " adet ekleyebilirsiniz.";
            }
        } else {
            unset($_SESSION['cart'][$product_id]);
        }
    }
    $success_message = "Sepet güncellendi!";
}

// Handle remove item
if (isset($_POST['remove_item']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    unset($_SESSION['cart'][$product_id]);
    $success_message = "Ürün sepetten kaldırıldı!";
}

// Handle clear cart
if (isset($_POST['clear_cart'])) {
    $_SESSION['cart'] = [];
    $success_message = "Sepet temizlendi!";
}

// Handle purchase
if (isset($_POST['purchase']) && !empty($_SESSION['cart'])) {
    $total_amount = 0;
    
    // Calculate total and check stock
    foreach ($_SESSION['cart'] as $product_id => $item) {
        $stock_sql = "SELECT urunAdet, urunFiyat FROM urunler WHERE urunId = ?";
        $stock_stmt = mysqli_prepare($baglanti, $stock_sql);
        mysqli_stmt_bind_param($stock_stmt, "i", $product_id);
        mysqli_stmt_execute($stock_stmt);
        $stock_result = mysqli_stmt_get_result($stock_stmt);
        $stock_data = mysqli_fetch_assoc($stock_result);
        
        if ($item['quantity'] > $stock_data['urunAdet']) {
            $error_message = "Yetersiz stok! Lütfen sepetinizi güncelleyin.";
            break;
        }
        
        $total_amount += $item['quantity'] * $stock_data['urunFiyat'];
    }
    
    // Check if user has enough balance
    if (!isset($error_message) && $total_amount > $_SESSION['balance']) {
        $error_message = "Yetersiz bakiye! Gerekli: " . number_format($total_amount, 2) . "₺, Mevcut: " . number_format($_SESSION['balance'], 2) . "₺";
    }
    
    // Process purchase if no errors
    if (!isset($error_message)) {
        mysqli_begin_transaction($baglanti);
        
        try {
            // Update user balance
            $new_balance = $_SESSION['balance'] - $total_amount;
            $update_balance_sql = "UPDATE users SET balance = ? WHERE id = ?";
            $update_balance_stmt = mysqli_prepare($baglanti, $update_balance_sql);
            mysqli_stmt_bind_param($update_balance_stmt, "di", $new_balance, $_SESSION['user_id']);
            mysqli_stmt_execute($update_balance_stmt);
            
            // Record balance transaction
            $transaction_sql = "INSERT INTO balance_transactions (user_id, amount, transaction_type, description) VALUES (?, ?, 'purchase', ?)";
            $transaction_stmt = mysqli_prepare($baglanti, $transaction_sql);
            $description = "Ürün satın alma: " . count($_SESSION['cart']) . " ürün";
            mysqli_stmt_bind_param($transaction_stmt, "ids", $_SESSION['user_id'], $total_amount, $description);
            mysqli_stmt_execute($transaction_stmt);
            
            // Process each item
            foreach ($_SESSION['cart'] as $product_id => $item) {
                // Update stock
                $update_stock_sql = "UPDATE urunler SET urunAdet = urunAdet - ? WHERE urunId = ?";
                $update_stock_stmt = mysqli_prepare($baglanti, $update_stock_sql);
                mysqli_stmt_bind_param($update_stock_stmt, "ii", $item['quantity'], $product_id);
                mysqli_stmt_execute($update_stock_stmt);
                
                // Record purchase history
                $purchase_sql = "INSERT INTO purchase_history (user_id, product_id, product_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)";
                $purchase_stmt = mysqli_prepare($baglanti, $purchase_sql);
                $item_total = $item['quantity'] * $item['price'];
                mysqli_stmt_bind_param($purchase_stmt, "iisidd", $_SESSION['user_id'], $product_id, $item['name'], $item['quantity'], $item['price'], $item_total);
                mysqli_stmt_execute($purchase_stmt);

                // Insert into orders table (NEW)
                $order_sql = "INSERT INTO orders (user_id, product_id, status, notified) VALUES (?, ?, 'pending', 0)";
                $order_stmt = mysqli_prepare($baglanti, $order_sql);
                mysqli_stmt_bind_param($order_stmt, "ii", $_SESSION['user_id'], $product_id);
                mysqli_stmt_execute($order_stmt);
            }
            
            // Clear cart and set purchase flag
            $_SESSION['cart'] = [];
            $_SESSION['balance'] = $new_balance;
            $_SESSION['purchase_completed'] = true;
            
            mysqli_commit($baglanti);
            $success_message = "Kargonuz yolda!";
            
        } catch (Exception $e) {
            mysqli_rollback($baglanti);
            $error_message = "Satın alma işlemi başarısız oldu. Lütfen tekrar deneyin.";
        }
    }
}
$cart_total = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['quantity'] * $item['price'];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sepetim - Retro Toyz</title>
    <link rel="icon" href="retroToyzLogo.png" type="image/x-icon">
    <style>
        @import url("cart.css");
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
                        <?php if (count($_SESSION['cart']) > 0): ?>
                            <span class="cart-count"><?php echo count($_SESSION['cart']); ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="logout.php" class="logout-btn">Çıkış</a>
                </div>
            </nav>
        </div>
        
        <h1 class="page-title">Sepetim</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (empty($_SESSION['cart'])): ?>
            <div class="cart-container">
                <?php if (isset($_SESSION['purchase_completed']) && $_SESSION['purchase_completed'] && $has_addresses): ?>
                    <div class="cargo-message">
                        <h2>Kargonuz admin onayı bekliyor.</h2>
                        <p>Siparişiniz başarıyla alındı ve admin onayını bekliyor.</p>
                        <p>Onaylandıktan sonra kargonuz yola çıkacaktır.</p>
                        <?php if ($default_address): ?>
                            <div style="background: white; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #51cf66;">
                                <h4 style="color: #51cf66; margin-bottom: 15px;">Teslimat Adresi:</h4>
                                <div style="line-height: 1.8; color: #333;">
                                    <strong><?php echo htmlspecialchars($default_address['full_name']); ?></strong><br>
                                    <?php echo htmlspecialchars($default_address['phone']); ?><br>
                                    <?php echo htmlspecialchars($default_address['city']); ?> / <?php echo htmlspecialchars($default_address['district']); ?><br>
                                    <?php echo nl2br(htmlspecialchars($default_address['full_address'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <a href="urunler.php" class="btn btn-primary" style="text-decoration: none; display: inline-block; margin-top: 20px;">
                            Alışverişe Devam Et
                        </a>
                    </div>
                    <?php 
                    // Clear the purchase flag after showing the message
                    unset($_SESSION['purchase_completed']);
                    ?>
                <?php else: ?>
                    <div class="empty-cart">
                        <h2>Sepetiniz Boş</h2>
                        <p>Sepetinizde henüz ürün bulunmamaktadır.</p>
                        <a href="urunler.php" class="btn btn-primary" style="text-decoration: none; display: inline-block; margin-top: 20px;">
                            Alışverişe Başla
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <!-- Balance Information -->
                <div class="balance-info">
                    <div>Mevcut Bakiye: <span class="balance-amount"><?php echo number_format($_SESSION['balance'], 2); ?>₺</span></div>
                    <?php if ($cart_total > $_SESSION['balance']): ?>
                        <div style="color: #ff6b6b; margin-top: 10px;">
                            ⚠️ Yetersiz bakiye! Eksik: <?php echo number_format($cart_total - $_SESSION['balance'], 2); ?>₺
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="update_cart" value="1">
                    <div class="cart-item" style="font-weight: bold; background: #f8f9fa; border-radius: 8px;">
                        <div>Ürün</div>
                        <div>Birim Fiyat</div>
                        <div>Adet</div>
                        <div>Toplam</div>
                        <div>İşlem</div>
                    </div>
                    
                    <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                        <div class="cart-item">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-price">₺<?php echo number_format($item['price'], 2); ?></div>
                            <div>
                                <input type="number" name="quantities[<?php echo $product_id; ?>]" 
                                       value="<?php echo $item['quantity']; ?>" min="1" 
                                       class="quantity-input" onchange="this.form.submit()">
                            </div>
                            <div class="item-total">₺<?php echo number_format($item['quantity'] * $item['price'], 2); ?></div>
                            <div>
                                <button type="submit" name="remove_item" value="<?php echo $product_id; ?>" 
                                        class="btn btn-danger">Kaldır</button>
                                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="cart-summary">
                        <div class="summary-row">
                            <span>Toplam Ürün:</span>
                            <span><?php echo count($_SESSION['cart']); ?> adet</span>
                        </div>
                        <div class="summary-row">
                            <span>Toplam Tutar:</span>
                            <span>₺<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Ödenecek Tutar:</span>
                            <span>₺<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="cart-actions">
                        <!--<button type="submit" name="update_cart" class="btn btn-primary">Sepeti Güncelle</button>-->
                        <button type="submit" name="clear_cart" class="btn btn-secondary" 
                                onclick="return confirm('Sepeti temizlemek istediğinizden emin misiniz?')">
                            Sepeti Temizle
                        </button>
                        <button type="submit" name="purchase" class="btn btn-success" 
                                <?php echo $cart_total > $_SESSION['balance'] ? 'disabled' : ''; ?>>
                            Bakiye ile Satın Al
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 