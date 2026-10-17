
<?php
session_start();
require_once __DIR__ . "/db.php";

// Get all available products
$result = $conn->query(
    "SELECT id, name, description, price, image
     FROM products
     WHERE is_available = 1
     ORDER BY id DESC"
);

if (!$result) {
    die("Unable to load products.");
}

// Calculate cart item count
$cartCount = 0;

if (isset($_SESSION["cart"]) && is_array($_SESSION["cart"])) {
    foreach ($_SESSION["cart"] as $quantity) {
        $cartCount += (int)$quantity;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brew Haven | Coffee Shop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <a href="index.php" class="logo">☕ Brew Haven</a>

    <nav>
        <a href="index.php">Home</a>
        <a href="cart.php">Cart (<?php echo $cartCount; ?>)</a>
        <a href="my_orders.php">My Orders</a>
        <?php if (isset($_SESSION["user_id"])): ?>
            <span class="welcome">
                Hi, <?php
                echo htmlspecialchars($_SESSION["user_name"] ?? "Customer");
                ?>
            </span>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<section class="hero">
    <div class="hero-content">
        <p class="eyebrow">FRESHLY BREWED EVERY DAY</p>
        <h1>Your daily cup<br>of happiness.</h1>
        <p>Discover your favourite coffee, made with love.</p>
        <a href="#menu" class="btn">Explore Our Menu</a>
    </div>
</section>

<section class="menu-section" id="menu">

    <div class="section-heading">
        <p class="eyebrow">MADE FOR COFFEE LOVERS</p>
        <h2>Our Coffee Menu</h2>
        <p>Find the perfect cup for your day.</p>
    </div>

    <div class="product-grid">

        <?php if ($result->num_rows > 0): ?>

            <?php while ($product = $result->fetch_assoc()): ?>

                <article class="product-card">

                    <?php
                    $imagePath = $product["image"] ?? "";

                    $validImage = $imagePath !== ""
                        && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $imagePath)
                        && !str_starts_with($imagePath, "//")
                        && !str_contains($imagePath, "..")
                        && preg_match(
                            '/\A[a-zA-Z0-9_\/-]+\.(jpg|jpeg|png|webp)\z/i',
                            $imagePath
                        );
                    ?>

                    <?php if ($validImage): ?>
                        <img
                            src="<?php echo htmlspecialchars($imagePath); ?>"
                            alt="<?php echo htmlspecialchars($product["name"]); ?>"
                            class="product-image"
                        >
                    <?php else: ?>
                        <div class="image-placeholder">☕</div>
                    <?php endif; ?>

                    <div class="product-info">

                        <h3>
                            <?php echo htmlspecialchars($product["name"]); ?>
                        </h3>

                        <p class="description">
                            <?php
                            echo htmlspecialchars(
                                $product["description"]
                                ?? "Freshly prepared coffee."
                            );
                            ?>
                        </p>

                        <div class="product-bottom">

                            <span class="price">
                                Rp <?php
                                echo number_format(
                                    (float)$product["price"],
                                    0,
                                    ",",
                                    "."
                                );
                                ?>
                            </span>

                            <!-- Add to Cart Form -->
                            <form action="add_to_cart.php" method="POST">

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?php echo (int)$product["id"]; ?>"
                                >

                                <input
                                    type="hidden"
                                    name="quantity"
                                    value="1"
                                >

                                <button type="submit" class="add-btn">
                                    + Add to Cart
                                </button>

                            </form>

                        </div>
                    </div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <p>No coffee products available yet.</p>

        <?php endif; ?>

    </div>
</section>

<footer>
    <p>☕ Brew Haven — Made with love, served with warmth.</p>
</footer>

</body>
</html>
