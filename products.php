<?php
session_start();

// DB connection
$host = 'localhost';
$dbname = 'SainaEcom';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Assume logged-in customer (replace with $_SESSION['customer_id'] in real app)
$customer_id = 1;

// Pre-insert parent categories
$parentCategories = [
    ['Fruits', 'fruits'],
    ['Vegetables', 'vegetables']
];

foreach ($parentCategories as $cat) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name, slug, parent_id) VALUES (?, ?, NULL)");
    $stmt->execute([$cat[0], $cat[1]]);
}

// Handle add product form (for admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $parent_id = $_POST['parent_id'] ?? null;
    $subcategory = trim($_POST['subcategory'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($parent_id) || empty($subcategory) || empty($product_name) || empty($sku) || empty($price)) {
        echo "<p style='color:red;'>All fields except stock and description are required.</p>";
    } else {
        $stock = intval($stock);
        if ($stock < 0) $stock = 0;

        try {
            // Insert subcategory if not exists
            $slug_sub = strtolower(str_replace(' ', '-', $subcategory));
            $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$subcategory, $slug_sub, $parent_id]);
            $subcategory_id = $pdo->lastInsertId();

            if (!$subcategory_id) {
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND parent_id = ?");
                $stmt->execute([$slug_sub, $parent_id]);
                $subcategory_id = $stmt->fetchColumn();
            }

            // Insert product
            $slug_product = strtolower(str_replace(' ', '-', $product_name));
            $stmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, sku, description, price_cents, currency, stock_qty)
                                   VALUES (?, ?, ?, ?, ?, ?, 'USD', ?)");
            $stmt->execute([
                $subcategory_id,
                $product_name,
                $slug_product,
                $sku,
                $description,
                intval(floatval($price) * 100), // store in cents
                $stock
            ]);

            echo "<p style='color:green;'>Product added successfully under subcategory '$subcategory'!</p>";
        } catch (PDOException $e) {
            echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
        }
    }
}

// Handle Add to Cart / Add to Wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1; // integer only

    if ($product_id > 0) {
        $stmt = $pdo->prepare("SELECT stock_qty, price_cents FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            if ($quantity < 1) $quantity = 1;
            if ($quantity > $product['stock_qty']) $quantity = $product['stock_qty'];

            if (isset($_POST['add_to_cart'])) {
                // Get or create open cart
                $stmt = $pdo->prepare("SELECT id FROM carts WHERE customer_id = ? AND status='open' LIMIT 1");
                $stmt->execute([$customer_id]);
                $cart_id = $stmt->fetchColumn();
                if (!$cart_id) {
                    $stmt = $pdo->prepare("INSERT INTO carts (customer_id) VALUES (?)");
                    $stmt->execute([$customer_id]);
                    $cart_id = $pdo->lastInsertId();
                }

                // Insert or update cart item
                $stmt = $pdo->prepare("
                    INSERT INTO cart_items (cart_id, product_id, quantity, unit_price_cents)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ");
                $stmt->execute([$cart_id, $product_id, $quantity, $product['price_cents']]);

                // Reduce stock
                $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);

                header("Location: cartitems.php");
                exit;
            } elseif (isset($_POST['add_to_wishlist'])) {
                // Get or create wishlist
                $stmt = $pdo->prepare("SELECT id FROM wishlists WHERE customer_id = ? LIMIT 1");
                $stmt->execute([$customer_id]);
                $wishlist_id = $stmt->fetchColumn();
                if (!$wishlist_id) {
                    $stmt = $pdo->prepare("INSERT INTO wishlists (customer_id, name) VALUES (?, 'My Wishlist')");
                    $stmt->execute([$customer_id]);
                    $wishlist_id = $pdo->lastInsertId();
                }

                $stmt = $pdo->prepare("INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)");
                $stmt->execute([$wishlist_id, $product_id]);

                header("Location: wishlist.php");
                exit;
            }
        }
    }
}

// Fetch all products
$stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Management & Shop</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background-color: #f9f9f9; }
        form { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 600px; margin-bottom: 30px; }
        input[type="text"], input[type="number"], select, textarea { width: 100%; padding: 8px; margin: 6px 0 12px; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #28a745; border: none; padding: 10px; color: white; font-size: 16px; cursor: pointer; border-radius: 4px; margin-right: 5px; }
        button:hover { background-color: #218838; }
        .product { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 15px; }
    </style>
</head>
<body>

<h2>Add Product</h2>
<form method="POST" action="">
    <input type="hidden" name="add_product" value="1">

    <label for="parent_id">Choose Category (Parent):</label>
    <select name="parent_id" required>
        <option value="">-- Select Parent Category --</option>
        <?php
        $stmt = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
        }
        ?>
    </select>

    <label for="subcategory">Subcategory Name:</label>
    <input type="text" name="subcategory" placeholder="e.g., Citrus Fruits" required>

    <label for="product_name">Product Name:</label>
    <input type="text" name="product_name" placeholder="e.g., Orange" required>

    <label for="sku">SKU:</label>
    <input type="text" name="sku" placeholder="e.g., SKU12345" required>

    <label for="price">Price (Dollars per kg):</label>
    <input type="number" step="1" name="price" placeholder="e.g., 350" required>

    <label for="stock">Stock Quantity (kg):</label>
    <input type="number" step="1" min="0" name="stock" placeholder="e.g., 50" required>

    <label for="description">Description:</label>
    <textarea name="description" placeholder="Optional product description"></textarea>

    <button type="submit">Add Product</button>
</form>

<h2>Products</h2>
<?php foreach ($products as $p): ?>
    <div class="product">
        <p><strong><?= htmlspecialchars($p['name']) ?></strong></p>
        <p>Price: <?= $p['price_cents'] / 100 ?> Dollars/kg</p>
        <p>Stock: <?= $p['stock_qty'] ?> kg</p>

        <form method="POST" action="">
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">

            <label>Quantity (kg):</label>
            <input type="number" name="quantity" step="1" min="1" max="<?= $p['stock_qty'] ?>" value="1">

            <button type="submit" name="add_to_cart">Add to Cart</button>
            <button type="submit" name="add_to_wishlist">Add to Wishlist</button>
        </form>
    </div>
<?php endforeach; ?>

</body>
</html>
