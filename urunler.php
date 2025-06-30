<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['balance'])) {
    $balance_sql = "SELECT balance FROM users WHERE id = ?";
    $balance_stmt = mysqli_prepare($baglanti, $balance_sql);
    mysqli_stmt_bind_param($balance_stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($balance_stmt);
    $balance_result = mysqli_stmt_get_result($balance_stmt);
    $user_balance = mysqli_fetch_assoc($balance_result);
    $_SESSION['balance'] = $user_balance['balance'];
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_POST['add_to_cart']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    

    $sql = "SELECT * FROM urunler WHERE urunId = ?";
    $stmt = mysqli_prepare($baglanti, $sql);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    
    if ($product) {

        $current_cart_quantity = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id]['quantity'] : 0;
        $total_requested = $current_cart_quantity + $quantity;
        
        if ($total_requested <= $product['urunAdet']) {
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'id' => $product['urunId'],
                    'name' => $product['urunAd'],
                    'price' => $product['urunFiyat'],
                    'quantity' => $quantity
                ];
            }
            $success_message = "Ürün sepete eklendi!";
        } else {
            $error_message = "Yetersiz stok! Maksimum " . $product['urunAdet'] . " adet ekleyebilirsiniz.";
        }
    }
}

// Handle rating and comment submission
if (isset($_POST['submit_rating']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    if ($rating >= 1 && $rating <= 5) {
        // Check if user already rated this product
        $check_sql = "SELECT id FROM product_ratings WHERE user_id = ? AND product_id = ?";
        $check_stmt = mysqli_prepare($baglanti, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $_SESSION['user_id'], $product_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Update existing rating
            $update_sql = "UPDATE product_ratings SET rating = ?, comment = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND product_id = ?";
            $update_stmt = mysqli_prepare($baglanti, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "isii", $rating, $comment, $_SESSION['user_id'], $product_id);
            mysqli_stmt_execute($update_stmt);
            $success_message = "Değerlendirmeniz güncellendi!";
        } else {
            // Insert new rating
            $insert_sql = "INSERT INTO product_ratings (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($baglanti, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "iiis", $_SESSION['user_id'], $product_id, $rating, $comment);
            mysqli_stmt_execute($insert_stmt);
            $success_message = "Değerlendirmeniz kaydedildi!";
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$price_min = isset($_GET['price_min']) ? (float)$_GET['price_min'] : '';
$price_max = isset($_GET['price_max']) ? (float)$_GET['price_max'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// Build query with filters
$sql = "SELECT u.*, 
        AVG(pr.rating) as avg_rating,
        COUNT(pr.id) as rating_count
        FROM urunler u 
        LEFT JOIN product_ratings pr ON u.urunId = pr.product_id";

$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($category_filter)) {
    $where_conditions[] = "u.kategori = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

if (!empty($price_min)) {
    $where_conditions[] = "u.urunFiyat >= ?";
    $params[] = $price_min;
    $param_types .= "d";
}

if (!empty($price_max)) {
    $where_conditions[] = "u.urunFiyat <= ?";
    $params[] = $price_max;
    $param_types .= "d";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " GROUP BY u.urunId";

// Add sorting
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY u.urunFiyat ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY u.urunFiyat DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY avg_rating DESC, u.urunAd ASC";
        break;
    default:
        $sql .= " ORDER BY u.urunAd ASC";
}

$stmt = mysqli_prepare($baglanti, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$sonuc = mysqli_stmt_get_result($stmt);

// Get categories for filter
$categories_sql = "SELECT name FROM categories ORDER BY name";
$categories_result = mysqli_query($baglanti, $categories_sql);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['name'];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürünler - Mağaza</title>
    <link rel="icon" href="retroToyzLogo.png" type="image/x-icon">
    <style>
        @import url("urunler.css");
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
        
        <h1 class="page-title"> Ürünlerimiz</h1>
        <div class="welcome-message">
            Hoş geldiniz, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong> !
        </div>
        
        <?php if (isset($_SESSION['order_shipped_notification'])): ?>
            <div class="success-message" style="margin-bottom:20px;">
                <?php echo htmlspecialchars($_SESSION['order_shipped_notification']); ?>
            </div>
            <?php unset($_SESSION['order_shipped_notification']); ?>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="category">Kategori:</label>
                    <select name="category" id="category">
                    <option value="" <?php echo empty($category_filter) ? 'selected' : ''; ?>>Tüm Kategoriler</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" 
                                    <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="price_min">Minimum Fiyat:</label>
                    <input type="number" name="price_min" id="price_min" 
                           value="<?php echo htmlspecialchars($price_min); ?>" 
                           placeholder="0" step="0.01" min="0">
                </div>
                
                <div class="filter-group">
                    <label for="price_max">Maksimum Fiyat:</label>
                    <input type="number" name="price_max" id="price_max" 
                           value="<?php echo htmlspecialchars($price_max); ?>" 
                           placeholder="Giriniz..." step="0.01" min="0">
                </div>
                
                <div class="filter-group">
                    <label for="sort">Sıralama:</label>
                    <select name="sort" id="sort">
                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>İsme Göre</option>
                        <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Fiyat (Düşük-Yüksek)</option>
                        <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Fiyat (Yüksek-Düşük)</option>
                        <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>Puana Göre</option>
                    </select>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">Filtrele</button>
                    <a href="urunler.php" class="btn btn-secondary">Temizle</a>
                </div>
            </form>
        </div>
        
        <div class="products-grid">
            <?php if ($sonuc && mysqli_num_rows($sonuc) > 0): ?>
                <?php while($satir = mysqli_fetch_assoc($sonuc)): ?>
                    <?php 

                    $cart_quantity = isset($_SESSION['cart'][$satir['urunId']]) ? $_SESSION['cart'][$satir['urunId']]['quantity'] : 0;
                    $available_stock = $satir['urunAdet'] - $cart_quantity;
                    

                    $user_rating_sql = "SELECT rating, comment FROM product_ratings WHERE user_id = ? AND product_id = ?";
                    $user_rating_stmt = mysqli_prepare($baglanti, $user_rating_sql);
                    mysqli_stmt_bind_param($user_rating_stmt, "ii", $_SESSION['user_id'], $satir['urunId']);
                    mysqli_stmt_execute($user_rating_stmt);
                    $user_rating_result = mysqli_stmt_get_result($user_rating_stmt);
                    $user_rating = mysqli_fetch_assoc($user_rating_result);
                    

                    $comments_sql = "SELECT pr.comment, pr.rating, pr.created_at, u.full_name 
                                   FROM product_ratings pr 
                                   JOIN users u ON pr.user_id = u.id 
                                   WHERE pr.product_id = ? AND pr.comment IS NOT NULL AND pr.comment != ''
                                   ORDER BY pr.created_at DESC LIMIT 3";
                    $comments_stmt = mysqli_prepare($baglanti, $comments_sql);
                    mysqli_stmt_bind_param($comments_stmt, "i", $satir['urunId']);
                    mysqli_stmt_execute($comments_stmt);
                    $comments_result = mysqli_stmt_get_result($comments_stmt);
                    ?>
                    <div class="product-card">
                        <div class="product-name"><?php echo htmlspecialchars($satir['urunAd']); ?></div>
                        <div class="product-category"><?php echo htmlspecialchars($satir['kategori']); ?></div>
                        <div class="product-price">₺<?php echo number_format($satir['urunFiyat'], 2); ?></div>
                        <div class="product-stock">
                            Stok: <?php echo $satir['urunAdet']; ?> adet
                            <?php if ($cart_quantity > 0): ?>
                                <br><small>(Sepetinizde: <?php echo $cart_quantity; ?> adet)</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="rating-section">
                            <div class="rating-display">
                                <div class="stars">
                                    <?php 
                                    $avg_rating = $satir['avg_rating'] ? round($satir['avg_rating'], 1) : 0;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $avg_rating) {
                                            echo '<span class="star">★</span>';
                                        } else {
                                            echo '<span class="star empty">☆</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                <span class="rating-text">
                                    <?php echo $avg_rating; ?>/5 
                                    (<?php echo $satir['rating_count'] ? $satir['rating_count'] : 0; ?> değerlendirme)
                                </span>
                            </div>
                            
                            <form method="POST" class="rating-form">
                                <input type="hidden" name="product_id" value="<?php echo $satir['urunId']; ?>">
                                <div class="rating-inputs">
                                    <select name="rating" required>
                                        <option value="">Puan seçin</option>
                                        <option value="1" <?php echo ($user_rating && $user_rating['rating'] == 1) ? 'selected' : ''; ?>>1 - Çok Kötü</option>
                                        <option value="2" <?php echo ($user_rating && $user_rating['rating'] == 2) ? 'selected' : ''; ?>>2 - Kötü</option>
                                        <option value="3" <?php echo ($user_rating && $user_rating['rating'] == 3) ? 'selected' : ''; ?>>3 - Orta</option>
                                        <option value="4" <?php echo ($user_rating && $user_rating['rating'] == 4) ? 'selected' : ''; ?>>4 - İyi</option>
                                        <option value="5" <?php echo ($user_rating && $user_rating['rating'] == 5) ? 'selected' : ''; ?>>5 - Mükemmel</option>
                                    </select>
                                    <textarea name="comment" placeholder="Yorumunuzu yazın (opsiyonel)"><?php echo $user_rating ? htmlspecialchars($user_rating['comment']) : ''; ?></textarea>
                                </div>
                                <button type="submit" name="submit_rating" class="btn btn-primary">
                                    <?php echo $user_rating ? 'Değerlendirmeyi Güncelle' : 'Değerlendir'; ?>
                                </button>
                            </form>
                            
                            <?php if (mysqli_num_rows($comments_result) > 0): ?>
                                <div class="comments-section">
                                    <h4>Son Yorumlar:</h4>
                                    <?php while ($comment = mysqli_fetch_assoc($comments_result)): ?>
                                        <div class="comment">
                                            <div class="comment-header">
                                                <span class="comment-author"><?php echo htmlspecialchars($comment['full_name']); ?></span>
                                                <span class="comment-date"><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></span>
                                            </div>
                                            <div class="comment-text"><?php echo htmlspecialchars($comment['comment']); ?></div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" class="add-to-cart-form">
                            <input type="hidden" name="product_id" value="<?php echo $satir['urunId']; ?>">
                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $available_stock; ?>" 
                                   class="quantity-input" <?php echo $available_stock <= 0 ? 'disabled' : ''; ?>>
                            <button type="submit" name="add_to_cart" class="add-to-cart-btn" 
                                    <?php echo $available_stock <= 0 ? 'disabled' : ''; ?>>
                                <?php echo $available_stock <= 0 ? 'Stok Yok' : 'Sepete Ekle'; ?>
                            </button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; color: white; font-size: 1.2em;">
                    Filtre kriterlerinize uygun ürün bulunamadı.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 