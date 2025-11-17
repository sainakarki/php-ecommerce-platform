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

// Assume logged-in customer
$customer_id = 1;

// Get or create open cart
$stmt = $pdo->prepare("SELECT id FROM carts WHERE customer_id = ? AND status='open' LIMIT 1");
$stmt->execute([$customer_id]);
$cart_id = $stmt->fetchColumn();
if (!$cart_id) {
    $stmt = $pdo->prepare("INSERT INTO carts (customer_id) VALUES (?)");
    $stmt->execute([$customer_id]);
    $cart_id = $pdo->lastInsertId();
}

// Remove item from cart and restore stock
if (isset($_POST['remove_item'])) {
    $item_id = $_POST['item_id'];

    $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart_items WHERE id = ? AND cart_id = ?");
    $stmt->execute([$item_id, $cart_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['product_id']]);

        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
        $stmt->execute([$item_id, $cart_id]);
    }
}

// Update item quantity (integer only)
if (isset($_POST['update_item'])) {
    $item_id = $_POST['item_id'];
    $new_qty = (int)$_POST['quantity']; // enforce integer quantity

    if ($new_qty > 0) {
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?");
        $stmt->execute([$new_qty, $item_id, $cart_id]);
    } else {
        // If quantity is 0 or negative, remove item
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
        $stmt->execute([$item_id, $cart_id]);
    }
}

// Fetch cart items
$stmt = $pdo->prepare("
    SELECT ci.id as item_id, p.name, ci.quantity, ci.unit_price_cents
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.cart_id = ?
");
$stmt->execute([$cart_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total
$total_amount_cents = 0;
foreach ($cart_items as $item) {
    $total_amount_cents += $item['unit_price_cents'] * $item['quantity'];
}
$total_amount_dollars = $total_amount_cents / 100;
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Cart</title>
</head>
<body>
<h2>Cart Items</h2>
<a href="products.php">← Back to Products</a>
<table border="1" cellpadding="5">
    <tr>
        <th>Product</th>
        <th>Quantity (kg)</th>
        <th>Unit Price ($)</th>
        <th>Total ($)</th>
        <th>Action</th>
    </tr>
    <?php foreach ($cart_items as $item): ?>
    <tr>
        <td><?= htmlspecialchars($item['name']) ?></td>
        <td>
            <form method="post" style="display:inline;">
                <input type="number" step="1" min="1" name="quantity" value="<?= (int)$item['quantity'] ?>">
                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                <button type="submit" name="update_item">Update</button>
            </form>
        </td>
        <td>$<?= number_format($item['unit_price_cents'] / 100, 2) ?></td>
        <td>$<?= number_format(($item['unit_price_cents'] * $item['quantity']) / 100, 2) ?></td>
        <td>
            <form method="post">
                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                <button type="submit" name="remove_item">Remove</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<h3>Total Amount: $<?= number_format($total_amount_dollars, 2) ?></h3>
</body>
</html>
