<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Handle balance loading
if (isset($_POST['load_balance'])) {
    $card_number = trim($_POST['card_number']);
    $card_name = trim($_POST['card_name']);
    $expiry_date = trim($_POST['expiry_date']);
    $cvv = trim($_POST['cvv']);
    $amount = (float)$_POST['amount'];
    
    // Basic validation
    $errors = [];
    
    if (empty($card_number) || strlen($card_number) < 13 || strlen($card_number) > 19) {
        $errors[] = "Geçerli bir kart numarası giriniz.";
    }
    
    if (empty($card_name)) {
        $errors[] = "Kart üzerindeki ismi giriniz.";
    }
    
    if (empty($expiry_date) || !preg_match('/^\d{2}\/\d{2}$/', $expiry_date)) {
        $errors[] = "Geçerli bir son kullanma tarihi giriniz (AA/YY formatında).";
    }
    
    if (empty($cvv) || strlen($cvv) < 3 || strlen($cvv) > 4) {
        $errors[] = "Geçerli bir CVV giriniz.";
    }
    
    if ($amount <= 0) {
        $errors[] = "Geçerli bir tutar giriniz.";
    }
    
    if (empty($errors)) {
        // In a real application, you would integrate with a payment gateway here
        // For this demo, we'll just add the balance directly
        
        // Start transaction
        mysqli_begin_transaction($baglanti);
        
        try {
            // Update user balance
            $update_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
            $update_stmt = mysqli_prepare($baglanti, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "di", $amount, $_SESSION['user_id']);
            mysqli_stmt_execute($update_stmt);
            
            // Record transaction
            $transaction_sql = "INSERT INTO balance_transactions (user_id, amount, transaction_type, description) VALUES (?, ?, 'deposit', ?)";
            $transaction_stmt = mysqli_prepare($baglanti, $transaction_sql);
            $description = "Kart ile bakiye yükleme: " . substr($card_number, -4) . " ile " . $amount . "₺";
            mysqli_stmt_bind_param($transaction_stmt, "ids", $_SESSION['user_id'], $amount, $description);
            mysqli_stmt_execute($transaction_stmt);
            
            // Update session balance
            $_SESSION['balance'] += $amount;
            
            mysqli_commit($baglanti);
            $success_message = "Bakiye başarıyla yüklendi! Yeni bakiyeniz: " . number_format($_SESSION['balance'], 2) . "₺";
            
        } catch (Exception $e) {
            mysqli_rollback($baglanti);
            $error_message = "Bakiye yükleme işlemi başarısız oldu. Lütfen tekrar deneyin.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get current user balance
$balance_sql = "SELECT balance FROM users WHERE id = ?";
$balance_stmt = mysqli_prepare($baglanti, $balance_sql);
mysqli_stmt_bind_param($balance_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);
$user_balance = mysqli_fetch_assoc($balance_result);
$_SESSION['balance'] = $user_balance['balance'];

// Get recent transactions
$transactions_sql = "SELECT * FROM balance_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$transactions_stmt = mysqli_prepare($baglanti, $transactions_sql);
mysqli_stmt_bind_param($transactions_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($transactions_stmt);
$transactions_result = mysqli_stmt_get_result($transactions_stmt);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakiye Yükle - Retro Toyz</title>
    <link rel="icon" href="retroToyzLogo.png" type="image/x-icon">
    <style>
        @import url("balance.css");
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
        
        <h1 class="page-title">Bakiye Yükle</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="content-grid">
            <!-- Balance Loading Form -->
            <div class="card">
                <h2>Kredi Kartı ile Bakiye Yükle</h2>
                <p>Güvenli bir şekilde bakiyenizi yükleyebilirsiniz.</p>
                
                <form method="POST" class="balance-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="card_number">Kart Numarası</label>
                            <input type="text" id="card_number" name="card_number" 
                                   placeholder="1234 5678 9012 3456" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="card_name">Kart Üzerindeki İsim</label>
                            <input type="text" id="card_name" name="card_name" 
                                   placeholder="Ad Soyad" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="expiry_date">Son Kullanma Tarihi</label>
                            <input type="text" id="expiry_date" name="expiry_date" 
                                   placeholder="AA/YY" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cvv">CVV</label>
                            <input type="text" id="cvv" name="cvv" 
                                   placeholder="123" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Yüklenecek Tutar (₺)</label>
                            <input type="number" id="amount" name="amount" 
                                   placeholder="100.00" step="0.01" min="1" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="load_balance" class="btn btn-primary">
                        Bakiye Yükle
                    </button>
                </form>
            </div>
            
            <!-- Current Balance and Transactions -->
            <div class="card">
                <h2>📊 Hesap Özeti</h2>
                
                <div class="current-balance">
                    <div class="balance-amount"><?php echo number_format($_SESSION['balance'], 2); ?>₺</div>
                    <div class="balance-label">Mevcut Bakiye</div>
                </div>
                
                <h3>Son İşlemler</h3>
                <?php if (mysqli_num_rows($transactions_result) > 0): ?>
                    <?php while ($transaction = mysqli_fetch_assoc($transactions_result)): ?>
                        <div class="transaction">
                            <div class="transaction-info">
                                <div class="transaction-amount <?php echo $transaction['transaction_type']; ?>">
                                    <?php echo $transaction['transaction_type'] === 'deposit' ? '+' : '-'; ?>
                                    <?php echo number_format($transaction['amount'], 2); ?>₺
                                </div>
                                <div class="transaction-description">
                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                </div>
                                <div class="transaction-date">
                                    <?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        Henüz işlem geçmişiniz bulunmamaktadır.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Format card number with spaces
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.replace(/\d{4}(?=.)/g, '$& ');
            e.target.value = formattedValue;
        });
        
        // Format expiry date
        document.getElementById('expiry_date').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
        
        // Format CVV (numbers only)
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    </script>
</body>
</html> 