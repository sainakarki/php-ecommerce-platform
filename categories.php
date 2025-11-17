<?php
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $parent_id = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null; // allow null for top-level

    if (empty($name)) {
        echo "<p style='color:red;'>Category name is required.</p>";
    } else {
        try {
            $slug = strtolower(str_replace(' ', '-', $name));
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $parent_id]);

            echo "<p style='color:green;'>Category '$name' added successfully!</p>";
        } catch (PDOException $e) {
            echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
        }
    }
}

// Fetch all categories for listing and parent dropdown
$stmt = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY parent_id ASC, name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to build nested category tree
function buildCategoryTree($categories, $parent_id = null, $level = 0) {
    $tree = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] === $parent_id) {
            $cat['level'] = $level;
            $tree[] = $cat;
            $tree = array_merge($tree, buildCategoryTree($categories, $cat['id'], $level + 1));
        }
    }
    return $tree;
}

$categoryTree = buildCategoryTree($categories);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Category Management</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background-color: #f9f9f9; }
        form { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 500px; margin-bottom: 30px; }
        input[type="text"], select { width: 100%; padding: 8px; margin: 6px 0 12px; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #007bff; border: none; padding: 10px; color: white; font-size: 16px; cursor: pointer; border-radius: 4px; }
        button:hover { background-color: #0069d9; }
        table { border-collapse: collapse; width: 100%; background-color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>

<h2>Add Category / Subcategory</h2>
<form method="POST" action="">
    <label for="name">Category/Subcategory Name:</label>
    <input type="text" name="name" placeholder="Enter name" required>

    <label for="parent_id">Parent Category (optional for top-level):</label>
    <select name="parent_id">
        <option value="">-- Top Level Category --</option>
        <?php
        foreach ($categoryTree as $cat) {
            $indent = str_repeat("&nbsp;&nbsp;&nbsp;", $cat['level']);
            echo "<option value='{$cat['id']}'>" . $indent . htmlspecialchars($cat['name']) . "</option>";
        }
        ?>
    </select>

    <button type="submit">Add Category/Subcategory</button>
</form>

<h2>All Categories</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Parent</th>
    </tr>
    <?php
    foreach ($categories as $cat) {
        $parentName = 'None';
        foreach ($categories as $p) {
            if ($p['id'] == $cat['parent_id']) {
                $parentName = htmlspecialchars($p['name']);
                break;
            }
        }
        echo "<tr>
                <td>{$cat['id']}</td>
                <td>" . htmlspecialchars($cat['name']) . "</td>
                <td>$parentName</td>
              </tr>";
    }
    ?>
</table>

</body>
</html>
