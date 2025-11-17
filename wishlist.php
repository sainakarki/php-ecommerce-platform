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

// Get or create wishlist
$stmt = $pdo->prepare("SELECT id FROM wishlists WHERE customer_id = ? LIMIT 1");
$stmt->execute([$customer_id]);
$wishlist_id = $stmt->fetchColumn();

if (!$wishlist_id) {
    $stmt = $pdo->prepare("INSERT INTO wishlists (customer_id, name) VALUES (?, 'My Wishlist')");
    $stmt->execute([$customer_id]);
    $wishlist_id = $pdo->lastInsertId();
}

// Remove item
if (isset($_GET['remove_id'])) {
    $remove_id = intval($_GET['remove_id']);
    $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?");
    $stmt->execute([$wishlist_id, $remove_id]);
    header("Location: wishlist.php");
    exit;
}

// Fetch wishlist items
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.description, p.price_cents
    FROM wishlist_items w
    JOIN products p ON w.product_id = p.id
    WHERE w.wishlist_id = ?
");
$stmt->execute([$wishlist_id]);
$wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Wishlist</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background-color: #f9f9f9; }
        table { border-collapse: collapse; width: 100%; background: #fff; border-radius: 8px; overflow: hidden; }
        th, td { border: 1px solid #ccc; padding: 12px; text-align: left; }
        th { background: #28a745; color: white; }
        a.button { display: inline-block; padding: 6px 10px; background: #dc3545; color: #fff; text-decoration: none; border-radius: 4px; }
        a.button:hover { background: #c82333; }
    </style>
</head>
<body>

<h2>My Wishlist</h2>

<?php if (empty($wishlist_items)): ?>
    <p>Your wishlist is empty.</p>
<?php else: ?>
    <table>
        <tr>
            <th>Product Name</th>
            <th>Description</th>
            <th>Price (USD)</th>
            <th>Action</th>
        </tr>
        <?php foreach ($wishlist_items as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= htmlspecialchars($item['description']) ?></td>
            <td><?= number_format($item['price_cents'] / 100, 2) ?></td>
            <td><a class="button" href="wishlist.php?remove_id=<?= $item['id'] ?>">Remove</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<p><a href="products.php">Back to Products</a></p>

</body>
</html>
