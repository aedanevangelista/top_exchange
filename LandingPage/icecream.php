<?php
// Connect to MySQL
$conn = new mysqli("localhost", "root", "", "top_exchange");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch products using correct column names
$sql = "SELECT item_description AS name, price, image_path, packaging, category FROM products";  
$result = $conn->query($sql);

// Get selected category from URL (if any)
$category = isset($_GET['category']) ? $_GET['category'] : '';

if (!empty($category)) {
    $sql .= " WHERE category = ?";
}

// Prepare and execute query
$stmt = $conn->prepare($sql);

if (!empty($category)) {
    $stmt->bind_param("s", $category);
}

$stmt->execute();
$result = $stmt->get_result();

// Debugging: Show error if query fails
if (!$result) {
    die("Query failed: " . $conn->error);
}

// Fetch available categories
$category_result = $conn->query("SELECT DISTINCT category FROM products");

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <!-- Responsive-->
    <link rel="stylesheet" href="css/responsive.css">
      <!-- fevicon -->
      <link rel="icon" href="images/fevicon.png" type="image/gif" />
      <!-- font css -->
      <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
      <!-- Scrollbar Custom CSS -->
      <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
      <!-- Tweaks for older IEs-->
      <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/admin/css/font-awesome.css">
       <!-- site metas -->
       <title>About</title>
      <meta name="keywords" content="">
      <meta name="description" content="">
      <meta name="author" content="">



      <style>
/* Popup Styles */
#popupNotification {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0,0,0,0.3);
    text-align: center;
    z-index: 1000;
}
#popupOverlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $(".add-to-cart").click(function(event) {
        event.preventDefault(); // Prevent default link action

        $("#popupOverlay, #popupNotification").fadeIn();
    });

    $("#closePopup").click(function() {
        $("#popupOverlay, #popupNotification").fadeOut();
        setTimeout(function() {
            window.location.href = "login.php"; // Redirect after closing
        }, 500);
    });
});
</script>  

    
      
</head>
<body>

<div class="header_section header_bg">
         <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
               <a class="navbar-brand"href="index.html"><img src="images/resized_food_corp_logo.png"></a>
               <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
               <span class="navbar-toggler-icon"></span>
               </button>
               <div class="collapse navbar-collapse" id="navbarSupportedContent">
                  <ul class="navbar-nav ml-auto">
                     <li class="nav-item active">
                        <a class="nav-link" href="index.html">Home</a>
                     </li>
                     <li class="nav-item">
                        <a class="nav-link" href="about.html">About</a>
                     </li>
                     <li class="nav-item">
                        <a class="nav-link" href="icecream.php">Products</a>
                     </li>
                     <li class="nav-item">
                        <a class="nav-link" href="contact.html">Contact Us</a>
                     </li>
                  </ul>
                  <form class="form-inline my-2 my-lg-0">
                     <div class="login_bt"><a href="login.php">Login <span style="color: #222222;"><i class="fa fa-user" aria-hidden="true"></i></span></a></div>
                     <div class="fa fa-search form-control-feedback"></div>
                  </form>
               </div>
            </nav>
         </div>
      </div>
      <!-- header section end -->

<!-- Category Filter -->
<form method="GET" action="">
    <select name="category" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php while ($row = $category_result->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($row['category']) ?>" <?= $category == $row['category'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['category']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</form>

<div class="cream_section layout_padding">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1 class="cream_taital">Best Seller Products</h1>
                <p class="cream_text">Eat well. Live well!</p>
            </div>
        </div>

        <div class="cream_section_2">
        <div class="row">
            <?php 
            while ($row = mysqli_fetch_assoc($result)) { ?>
                <div class="col-md-4">
                    <div class="cream_box">
                        <div class="cream_img">
                            <img src="<?php echo htmlspecialchars($row['image_path']); ?>">   
                        </div>
                        <div class="price_text">
                             ₱<?php echo isset($row['price']) ? number_format($row['price'], 2) : '0.00'; ?>
                        </div>
                        <h6 class="strawberry_text">
                            <?php echo isset($row['name']) ? htmlspecialchars($row['name']) : 'No Name'; ?>
                         </h6>
                        <p class="cream_text">
                            Packaging: <?php echo isset($row['packaging']) ? htmlspecialchars($row['packaging']) : 'N/A'; ?>
                        </p>
                        <div class="cart_bt">
                            <a href="login.php" class="add-to-cart">Add To Cart</a>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    </div>
</div>

<div id="popupOverlay"></div>
<div id="popupNotification">
    <p>Please log in to continue.</p>
    <button id="closePopup" class="btn btn-success">OK</button>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
<!-- copyright section start -->
<div class="copyright_section margin_top90">
         <div class="container">
            <p class="copyright_text">2025 All Rights Reserved. Design by STI Munoz Students</p>
         </div>
      </div>
      <!-- copyright section end -->