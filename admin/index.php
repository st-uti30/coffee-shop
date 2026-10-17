
<?php
session_start();
require_once __DIR__ . "/../db.php";

// Check that the user is logged in as an admin
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    http_response_code(403);
    exit("Access denied. Please log in with an admin account.");
}

$error = "";
$success = "";

// Create a CSRF security token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Handle the Add Product form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        http_response_code(403);
        exit("Invalid form token. Please refresh the page.");
    }

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $priceInput = trim($_POST["price"] ?? "");

    if (
        $name === "" ||
        strlen($name) > 150 ||
        strlen($description) > 5000 ||
        !preg_match('/\A[0-9]{1,8}(\.[0-9]{1,2})?\z/', $priceInput) ||
        (float)$priceInput <= 0
    ) {
        $error = "Please enter a valid name, description and price.";
    } else {
        $imagePath = null;
        $uploadFailed = false;

        // Image is optional
        if (
            isset($_FILES["image"]) &&
            $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES["image"];

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $uploadFailed = true;
                $error = "Image upload failed.";
            } elseif ($file["size"] > 3 * 1024 * 1024) {
                $uploadFailed = true;
                $error = "Image must be smaller than 3 MB.";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file["tmp_name"]);

                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (
                    !isset($allowedTypes[$mime]) ||
                    @getimagesize($file["tmp_name"]) === false
                ) {
                    $uploadFailed = true;
                    $error = "Please upload a valid JPG, PNG or WebP image.";
                } elseif (!is_dir(__DIR__ . "/../uploads")) {
                    $uploadFailed = true;
                    $error = "The uploads folder does not exist.";
                } else {
                    $filename = bin2hex(random_bytes(16))
                        . "." . $allowedTypes[$mime];

                    $destination = __DIR__ . "/../uploads/" . $filename;

                    if (move_uploaded_file($file["tmp_name"], $destination)) {
                        $imagePath = "uploads/" . $filename;
                    } else {
                        $uploadFailed = true;
                        $error = "Could not save the image.";
                    }
                }
            }
        }

        if (!$uploadFailed && $error === "") {
            $price = (float)$priceInput;

            $stmt = $conn->prepare(
                "INSERT INTO products
                (name, description, price, image, is_available)
                VALUES (?, ?, ?, ?, 1)"
            );

            $stmt->bind_param(
                "ssds",
                $name,
                $description,
                $price,
                $imagePath
            );

            if ($stmt->execute()) {
                $stmt->close();

                // Prevent the form from submitting again on refresh
                header("Location: index.php?added=1");
                exit;
            } else {
                $error = "Could not add the product. Please try again.";

                if ($imagePath !== null) {
                    @unlink(__DIR__ . "/../" . $imagePath);
                }
            }

            $stmt->close();
        }
    }
}

if (isset($_GET["added"])) {
    if (isset($_GET["updated"])) {
    $success = "Product updated successfully!";
}

if (isset($_GET["deleted"])) {
    $success = "Product deleted successfully!";
}
    $success = "Product added successfully!";
}

// Fetch all products
$result = $conn->query(
    "SELECT id, name, description, price, image, is_available
     FROM products
     ORDER BY id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Brew Haven</title>

    <link rel="stylesheet" href="../style.css">

    <style>
        .admin-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        .admin-box {
            background: white;
            padding: 25px;
            margin: 25px 0;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
        }

        .admin-box h2 {
            margin-bottom: 20px;
        }

        .admin-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .admin-form label {
            font-weight: bold;
            margin-top: 8px;
        }

        .admin-form input,
        .admin-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d8cec4;
            border-radius: 5px;
            font: inherit;
        }

        .admin-form button {
            margin-top: 15px;
            padding: 13px;
            background: #603d2a;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .admin-form button:hover {
            background: #80502f;
        }

        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .admin-product {
            border: 1px solid #eee4d8;
            border-radius: 8px;
            padding: 15px;
            overflow-wrap: anywhere;
        }

        .admin-product img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .admin-message {
            padding: 12px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .admin-success {
            background: #e7f5e8;
            color: #236b31;
        }

        .admin-error {
            background: #fce8e6;
            color: #a12622;
        }
    </style>
</head>

<body>

<header class="navbar">
    <a href="../index.php" class="logo">☕ Brew Haven</a>

    <nav>
        <a href="../index.php">View Website</a>
        <a href="orders.php">Customer Orders</a>
        <a href="../cart.php">Cart</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<main class="admin-container">

    <h1>Admin Dashboard</h1>
    <p>Manage your coffee shop products below.</p>

    <?php if ($success !== ""): ?>
        <p class="admin-message admin-success">
            <?php echo htmlspecialchars($success); ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p class="admin-message admin-error">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <section class="admin-box">
        <h2>Add New Coffee</h2>

        <form class="admin-form" method="POST"
              action="index.php" enctype="multipart/form-data">

            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

            <label for="name">Coffee Name</label>
            <input type="text" id="name" name="name"
                   maxlength="150" required>

            <label for="description">Description</label>
            <textarea id="description" name="description"
                      rows="3" maxlength="5000"
                      placeholder="Describe your coffee"></textarea>

            <label for="price">Price in Indonesian Rupiah (Rp)</label>
            <input type="number" id="price" name="price"
                   min="0.01" max="99999999.99"
                   step="0.01" required>

            <label for="image">Coffee Image (optional)</label>
            <input type="file" id="image" name="image"
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

            <button type="submit">+ Add Product</button>
        </form>
    </section>

    <section class="admin-box">
        <h2>Existing Products</h2>

        <div class="admin-grid">

            <?php while ($product = $result->fetch_assoc()): ?>

                <article class="admin-product">

                    <?php if (!empty($product["image"])): ?>
                        <img
                            src="../<?php echo htmlspecialchars($product["image"]); ?>"
                            alt="<?php echo htmlspecialchars($product["name"]); ?>"
                        >
                    <?php endif; ?>

                    <h3>
                        <?php echo htmlspecialchars($product["name"]); ?>
                    </h3>

                    <p>
                        <?php echo htmlspecialchars($product["description"] ?? ""); ?>
                    </p>

                    <p>
                        <strong>
                            Rp <?php echo number_format(
                                (float)$product["price"], 0, ",", "."
                            ); ?>
                        </strong>
                    </p>

                    <p>
                        Status:
                        <?php echo $product["is_available"]
                            ? "Available" : "Unavailable"; ?>
                    </p>

                </article>

            <?php endwhile; ?>

        </div>
    </section>

</main>

</body>
</html>
