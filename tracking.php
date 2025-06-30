<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM purchase_history WHERE user_id = ? ORDER BY purchase_date DESC";
$stmt = mysqli_prepare($baglanti, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$sql = "
    SELECT 
        ph.*, 
        o.status AS order_status, 
        o.created_at AS order_created_at 
    FROM 
        purchase_history ph
    LEFT JOIN orders o ON o.id = (
        SELECT id FROM orders 
        WHERE user_id = ph.user_id AND product_id = ph.product_id 
        ORDER BY created_at DESC 
        LIMIT 1
    )
    WHERE 
        ph.user_id = ?
    ORDER BY 
        ph.purchase_date DESC
";
$stmt = mysqli_prepare($baglanti, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satın Alma Geçmişi - Retro Toyz</title>
    <link rel="icon" href="retroToyzLogo.png" type="image/x-icon">
    <style>
        @import url("tracking.css");
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
        <h1 class="page-title">Satın Alma Geçmişi</h1>
        <table class="history-table">
            <tr>
                <th>Ürün</th>
                <th>Adet</th>
                <th>Birim Fiyat (₺)</th>
                <th>Toplam Fiyat (₺)</th>
                <th>Satın Alınma</th>
                <th>Kargo Durumu</th>
            </tr>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td><?= $row['quantity'] ?></td>
                        <td><?= number_format($row['unit_price'], 2) ?></td>
                        <td><?= number_format($row['total_price'], 2) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($row['purchase_date'])) ?></td>
                        <td>
                            <?php
                                if (isset($row['order_status']) && $row['order_status'] === 'shipped') {
                                    echo "<span style='color: green; font-weight: bold;'>Sipariş Onaylandı!<br>" . 
                                        date('d.m.Y H:i', strtotime($row['order_created_at'])) . "</span>";
                                } else {
                                    echo "<span style='color: orange; font-weight: bold;'>Onay Bekliyor...</span>";
                                }
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" class="no-history">Henüz satın alma geçmişiniz yok.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>
