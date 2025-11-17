<?php
session_start();

// Database connection
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

// Assume customer is logged in
$customer_id = $_SESSION['customer_id'] ?? 1; // Replace with real session ID

// 1️⃣ Check if the customer has an open cart
$stmt = $pdo->prepare("SELECT id FROM carts WHERE customer_id = ? AND status = 'open' LIMIT 1");
$stmt->execute([$customer_id]);
$cart_id = $stmt->fetchColumn();

// 2️⃣ If no open cart, create one
if (!$cart_id) {
    $stmt = $pdo->prepare("INSERT INTO carts (customer_id) VALUES (?)");
    $stmt->execute([$customer_id]);
    $cart_id = $pdo->lastInsertId();
}

// 3️⃣ Now $cart_id can be used to insert items into cart_items
echo "Current cart ID for customer $customer_id is: $cart_id";
