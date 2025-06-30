<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle product updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_product'])) {
        $product_id = (int)$_POST['product_id'];
        $product_name = trim($_POST['product_name']);
        $product_price = (float)$_POST['product_price'];
        $product_quantity = (int)$_POST['product_quantity'];
        $product_category = trim($_POST['product_category']);
        
        if (empty($product_name) || $product_price <= 0 || $product_quantity < 0 || empty($product_category)) {
            $error_message = 'Lütfen geçerli değerler giriniz.';
        } else {
            $update_sql = "UPDATE urunler SET urunAd = ?, urunFiyat = ?, urunAdet = ?, kategori = ? WHERE urunId = ?";
            $update_stmt = mysqli_prepare($baglanti, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "sdisi", $product_name, $product_price, $product_quantity, $product_category, $product_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "Ürün başarıyla güncellendi!";
            } else {
                $error_message = "Güncelleme sırasında hata oluştu.";
            }
        }
    } elseif (isset($_POST['add_product'])) {
        $product_name = trim($_POST['new_product_name']);
        $product_price = (float)$_POST['new_product_price'];
        $product_quantity = (int)$_POST['new_product_quantity'];
        $product_category = trim($_POST['new_product_category']);
        
        if (empty($product_name) || $product_price <= 0 || $product_quantity < 0 || empty($product_category)) {
            $error_message = 'Lütfen geçerli değerler giriniz.';
        } else {
            $insert_sql = "INSERT INTO urunler (urunAd, urunFiyat, urunAdet, kategori) VALUES (?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($baglanti, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "sdis", $product_name, $product_price, $product_quantity, $product_category);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $success_message = "Yeni ürün başarıyla eklendi!";
            } else {
                $error_message = "Ürün eklenirken hata oluştu.";
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        
        $delete_sql = "DELETE FROM urunler WHERE urunId = ?";
        $delete_stmt = mysqli_prepare($baglanti, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $product_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $success_message = "Ürün başarıyla silindi!";
        } else {
            $error_message = "Ürün silinirken hata oluştu.";
        }
    } elseif (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        $category_description = trim($_POST['category_description']);
        
        if (empty($category_name)) {
            $error_message = 'Kategori adı gereklidir.';
        } else {
            $insert_sql = "INSERT INTO categories (name, description) VALUES (?, ?)";
            $insert_stmt = mysqli_prepare($baglanti, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "ss", $category_name, $category_description);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $success_message = "Yeni kategori başarıyla eklendi!";
            } else {
                $error_message = "Kategori eklenirken hata oluştu.";
            }
        }
    } elseif (isset($_POST['update_category'])) {
        $category_id = (int)$_POST['category_id'];
        $category_name = trim($_POST['category_name']);
        $category_description = trim($_POST['category_description']);
        
        if (empty($category_name)) {
            $error_message = 'Kategori adı gereklidir.';
        } else {
            $update_sql = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($baglanti, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ssi", $category_name, $category_description, $category_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "Kategori başarıyla güncellendi!";
            } else {
                $error_message = "Kategori güncellenirken hata oluştu.";
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        $category_id = (int)$_POST['category_id'];
        
        // Check if category is being used by any products
        $check_sql = "SELECT COUNT(*) as count FROM urunler u JOIN categories c ON u.kategori = c.name WHERE c.id = ?";
        $check_stmt = mysqli_prepare($baglanti, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $category_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_row = mysqli_fetch_assoc($check_result);
        
        if ($check_row['count'] > 0) {
            $error_message = "Bu kategori kullanılan ürünlerde bulunduğu için silinemez.";
        } else {
            $delete_sql = "DELETE FROM categories WHERE id = ?";
            $delete_stmt = mysqli_prepare($baglanti, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "i", $category_id);
            
            if (mysqli_stmt_execute($delete_stmt)) {
                $success_message = "Kategori başarıyla silindi!";
            } else {
                $error_message = "Kategori silinirken hata oluştu.";
            }
        }
    } elseif (isset($_POST['approve_order']) && isset($_POST['approve_order_id'])) {
        $order_id = (int)$_POST['approve_order_id'];
        $sql = "UPDATE orders SET status='shipped' WHERE id=?";
        $stmt = mysqli_prepare($baglanti, $sql);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Sipariş #$order_id başarıyla onaylandı ve kargoya verildi.";
        } else {
            $error_message = "Sipariş onaylanırken hata oluştu.";
        }
    }
}

// Get all products
$sql = "SELECT * FROM urunler ORDER BY urunId";
$result = mysqli_query($baglanti, $sql);

// Get all categories
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = mysqli_query($baglanti, $categories_sql);

// Get purchase history with user details
$history_sql = "SELECT ph.*, u.username, u.full_name, u.email 
                FROM purchase_history ph 
                JOIN users u ON ph.user_id = u.id 
                ORDER BY ph.purchase_date DESC";
$history_result = mysqli_query($baglanti, $history_sql);

// Get user purchase summary
$summary_sql = "SELECT u.id, u.username, u.full_name, u.email,
                COUNT(ph.id) as total_purchases,
                SUM(ph.total_price) as total_spent,
                SUM(ph.quantity) as total_items
                FROM users u 
                LEFT JOIN purchase_history ph ON u.id = ph.user_id 
                WHERE u.username != 'admin'
                GROUP BY u.id 
                ORDER BY total_spent DESC";
$summary_result = mysqli_query($baglanti, $summary_sql);

// Approve order if requested
if (isset($_GET['approve'])) {
    $order_id = intval($_GET['approve']);
    $sql = "UPDATE orders SET status='shipped' WHERE id=?";
    $stmt = mysqli_prepare($baglanti, $sql);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
}

// List pending orders
// $result = mysqli_query($baglanti, "SELECT * FROM orders WHERE status='pending'");
// while ($row = mysqli_fetch_assoc($result)) {
//     echo "Order #" . $row['id'] . " for user #" . $row['user_id'] . " - Product #" . $row['product_id'];
//     echo " <a href='admin.php?approve=" . $row['id'] . "'>Approve</a><br>";
// }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Retro Toyz</title>
    <link rel="icon" href="retroToyzLogo.png" type="image/x-icon">
    <style>
        @import url("admin.css");
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <nav class="nav">
                <div class="logo">
                    <img src="retroToyzLogo.png" alt="Retro Toyz Logo">
                    Retro Toyz - Admin Panel
                </div>
                <div class="nav-links">
                    <span class="admin-badge">ADMIN</span>
                    <a href="logout.php" class="logout-btn">Çıkış</a>
                </div>
            </nav>
        </div>
        
        <div class="admin-container">
            <h1 class="page-title">Admin Panel</h1>
            <div class="admin-welcome">
                Hoş geldiniz, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>! 
                Mağaza yönetimi için aşağıdaki sekmeleri kullanabilirsiniz.
            </div>
            
            <?php if ($success_message): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <div class="section-tabs">
                <button class="tab-btn active" onclick="showTab('products')">Ürün Yönetimi</button>
                <button class="tab-btn" onclick="showTab('categories')">Kategori Yönetimi</button>
                <button class="tab-btn" onclick="showTab('orders')">Sipariş Yönetimi</button>
                <button class="tab-btn" onclick="showTab('users')">Kullanıcı Geçmişi</button>
                <button class="tab-btn" onclick="showTab('history')">Satın Alma Geçmişi</button>
            </div>
            
            <!-- Products Tab -->
            <div id="products" class="tab-content active">
                <div class="add-product-section">
                    <h3>Yeni Ürün Ekle</h3>
                    <form method="POST" class="form-row">
                        <div class="form-group">
                            <label for="new_product_name">Ürün Adı</label>
                            <input type="text" id="new_product_name" name="new_product_name" required>
                        </div>
                        <div class="form-group">
                            <label for="new_product_price">Fiyat (₺)</label>
                            <input type="number" id="new_product_price" name="new_product_price" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="new_product_quantity">Stok</label>
                            <input type="number" id="new_product_quantity" name="new_product_quantity" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="new_product_category">Kategori</label>
                            <select id="new_product_category" name="new_product_category" required>
                                <option value="">Kategori Seçin</option>
                                <?php 
                                // Fetch categories fresh for this dropdown
                                $categories_result_for_add = mysqli_query($baglanti, "SELECT * FROM categories ORDER BY name");
                                while ($category = mysqli_fetch_assoc($categories_result_for_add)): ?>
                                    <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_product" class="btn btn-success">Ekle</button>
                    </form>
                </div>
                
                <h3>Mevcut Ürünler</h3>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Ürün ID</th>
                            <th>Ürün Adı</th>
                            <th>Fiyat (₺)</th>
                            <th>Stok</th>
                            <th>Kategori</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Fetch products fresh for the table
                        $products_result = mysqli_query($baglanti, "SELECT * FROM urunler ORDER BY urunId");
                        if ($products_result && mysqli_num_rows($products_result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($products_result)): ?>
                                <tr>
                                    <form method="POST">
                                        <td><?php echo $row['urunId']; ?></td>

                                        <td>
                                            <input type="hidden" name="product_id" value="<?php echo $row['urunId']; ?>">
                                            <input type="text" name="product_name" value="<?php echo htmlspecialchars($row['urunAd']); ?>" required class="table-input">
                                        </td>

                                        <td>
                                            <input type="number" name="product_price" value="<?php echo $row['urunFiyat']; ?>" step="0.01" min="0" required class="table-input">
                                        </td>

                                        <td>
                                            <input type="number" name="product_quantity" value="<?php echo $row['urunAdet']; ?>" min="0" required class="table-input">
                                        </td>

                                        <td>
                                            <select name="product_category" required class="table-input">
                                                <?php 
                                                // Fetch categories fresh for each product row
                                                $categories_result_for_row = mysqli_query($baglanti, "SELECT * FROM categories ORDER BY name");
                                                while ($category = mysqli_fetch_assoc($categories_result_for_row)): ?>
                                                    <option value="<?php echo htmlspecialchars($category['name']); ?>" 
                                                            <?php echo ($category['name'] === $row['kategori']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>

                                        <td class="action-buttons">
                                            <button type="submit" name="update_product" class="btn btn-primary btn-small">Güncelle</button>
                                            <button type="submit" name="delete_product" class="btn btn-danger btn-small" onclick="return confirm('Bu ürünü silmek istediğinizden emin misiniz?')">Sil</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #666;">Henüz ürün bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Categories Tab -->
            <div id="categories" class="tab-content">
                <div class="add-product-section">
                    <h3>Yeni Kategori Ekle</h3>
                    <form method="POST" class="form-row">
                        <div class="form-group">
                            <label for="category_name">Kategori Adı</label>
                            <input type="text" id="category_name" name="category_name" required>
                        </div>
                        <div class="form-group">
                            <label for="category_description">Açıklama</label>
                            <input type="text" id="category_description" name="category_description">
                        </div>
                        <button type="submit" name="add_category" class="btn btn-success">Ekle</button>
                    </form>
                </div>
                
                <h3>Mevcut Kategoriler</h3>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kategori Adı</th>
                            <th>Açıklama</th>
                            <th>Oluşturulma Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Reset categories result pointer
                        mysqli_data_seek($categories_result, 0);
                        if ($categories_result && mysqli_num_rows($categories_result) > 0): 
                        ?>
                            <?php while($category = mysqli_fetch_assoc($categories_result)): ?>
                                <tr>
                                    <form method="POST">
                                        <td><?php echo $category['id']; ?></td>
                                        <td>
                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                            <input type="text" name="category_name" value="<?php echo htmlspecialchars($category['name']); ?>" required class="table-input">
                                        </td>
                                        <td>
                                            <input type="text" name="category_description" value="<?php echo htmlspecialchars($category['description']); ?>" class="table-input">
                                        </td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($category['created_at'])); ?></td>
                                        <td class="action-buttons">
                                            <button type="submit" name="update_category" class="btn btn-primary btn-small">Güncelle</button>
                                            <button type="submit" name="delete_category" class="btn btn-danger btn-small" onclick="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?')">Sil</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #666;">Henüz kategori bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Order Management Tab -->
            <div id="orders" class="tab-content">
                <h3>🛒 Sipariş Yönetimi</h3>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Sipariş ID</th>
                            <th>Kullanıcı ID</th>
                            <th>Ürün ID</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Fetch all pending orders
                        $orders_result = mysqli_query($baglanti, "SELECT * FROM orders WHERE status = 'pending' ORDER BY created_at ASC");
                        if ($orders_result && mysqli_num_rows($orders_result) > 0):
                            while($order = mysqli_fetch_assoc($orders_result)): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <td><?php echo $order['user_id']; ?></td>
                                    <td><?php echo $order['product_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['status']); ?></td>
                                    <td><?php echo $order['created_at']; ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="approve_order_id" value="<?php echo $order['id']; ?>">
                                            <button type="submit" name="approve_order" class="btn btn-success btn-small">Onayla</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #666;">Onay bekleyen sipariş yok.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Users Tab -->
            <div id="users" class="tab-content">
                <h3>👥 Kullanıcı Satın Alma Özeti</h3>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>Ad Soyad</th>
                            <th>E-posta</th>
                            <th>Toplam Satın Alma</th>
                            <th>Toplam Ürün</th>
                            <th>Toplam Harcama (₺)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($summary_result && mysqli_num_rows($summary_result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($summary_result)): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo $row['total_purchases']; ?> adet</td>
                                    <td><?php echo $row['total_items'] ? $row['total_items'] : 0; ?> adet</td>
                                    <td>₺<?php echo number_format($row['total_spent'] ? $row['total_spent'] : 0, 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #666;">Henüz satın alma geçmişi bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- History Tab -->
            <div id="history" class="tab-content">
                <h3>📊 Detaylı Satın Alma Geçmişi</h3>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Ürün</th>
                            <th>Adet</th>
                            <th>Birim Fiyat (₺)</th>
                            <th>Toplam Fiyat (₺)</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($history_result && mysqli_num_rows($history_result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($history_result)): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['username']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['full_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                    <td><?php echo $row['quantity']; ?> adet</td>
                                    <td>₺<?php echo number_format($row['unit_price'], 2); ?></td>
                                    <td>₺<?php echo number_format($row['total_price'], 2); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($row['purchase_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #666;">Henüz satın alma geçmişi bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));

            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(button => button.classList.remove('active'));

            document.getElementById(tabName).classList.add('active');

            // Find the clicked button
            const clickedBtn = Array.from(tabButtons).find(btn => {
                const text = btn.textContent.toLowerCase();
                if (tabName === 'products' && text.includes('ürün')) return true;
                if (tabName === 'categories' && text.includes('kategori')) return true;
                if (tabName === 'users' && text.includes('kullanıcı')) return true;
                if (tabName === 'history' && text.includes('satın')) return true;
                return false;
            });
            if (clickedBtn) clickedBtn.classList.add('active');
        }
    </script>
</body>
</html> 