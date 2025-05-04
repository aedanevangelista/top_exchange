<?php
// Set page-specific variables before including header
$pageTitle = "Home | Top Exchange Food Corp";
$pageDescription = "Top Food Exchange Corp. - Premium Filipino food products since 1998. Quality siopao, siomai, noodles, and sauces.";

// Start the session and initialize cart if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Include the header
require_once 'header.php'; // ** Ensure Font Awesome 5 Free (Solid) is linked here! **
?>

<!-- Added CSS Styles -->
<style>
    /* --- Product Card Uniform Height & Hierarchy --- */
    .cream_section .row {
        display: flex;
        flex-wrap: wrap;
        margin-left: -15px;
        margin-right: -15px;
    }
    .cream_section .col-md-4 {
        display: flex; /* Keep column as flex container */
        padding-left: 15px;
        padding-right: 15px;
        margin-bottom: 30px;
    }
    .product-card {
        display: flex; /* Keep card as flex container */
        flex-direction: column; /* Stack image and body vertically */
        width: 100%;
        height: 100%; /* Ensures card fills the column space */
        border: 1px solid #eee;
        border-radius: 8px;
        overflow: hidden;
        transition: box-shadow 0.3s ease;
        background-color: #fff; /* Ensure background for consistency */
    }
    .product-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .product-img {
        position: relative; /* Crucial for badge positioning */
        height: 250px; /* Set a fixed height for the image area */
        display: flex; /* Use flex to center the image inside */
        align-items: center; /* Vertical centering */
        justify-content: center; /* Horizontal centering */
        overflow: hidden;
        padding: 10px; /* Add some padding around the image */
        background-color: #f8f9fa; /* Light background for image area */
    }
    .product-img img {
        max-width: 100%;
        max-height: 100%; /* Ensure image doesn't exceed container height */
        height: auto; /* Maintain aspect ratio */
        width: auto; /* Maintain aspect ratio */
        object-fit: contain; /* Scale image down to fit, preserving aspect ratio */
        display: block;
    }
    .product-badge {
        position: absolute; /* Position relative to .product-img */
        top: 10px; /* Consistent top position */
        left: 10px; /* Consistent left position */
        background-color: rgba(0, 123, 255, 0.8); /* Example blue badge color */
        color: white;
        padding: 3px 8px;
        font-size: 0.75rem;
        font-weight: bold;
        border-radius: 4px;
        z-index: 1; /* Ensure badge is above image */
        text-transform: uppercase;
    }
    .product-badge.bestseller {
         background-color: rgba(220, 53, 69, 0.8); /* Red for bestseller */
    }
    .product-badge.popular {
         background-color: rgba(255, 193, 7, 0.8); /* Yellow for popular */
         color: #333;
    }
    .product-badge.new {
         background-color: rgba(40, 167, 69, 0.8); /* Green for new */
    }
    .product-body {
        padding: 20px; /* Slightly increased padding */
        display: flex;
        flex-direction: column;
        flex-grow: 1; /* Takes remaining vertical space */
        text-align: center; /* Center align text in the body */
    }
    .product-title {
        font-size: 1.15rem;
        font-weight: 600;
        margin-bottom: 8px; /* Adjusted spacing */
        color: #333;
    }
    .product-price {
        font-size: 1.25rem;
        font-weight: bold;
        color: #007bff; /* Adjust color */
        margin-bottom: 12px; /* Adjusted spacing */
    }
    .product-description {
        font-size: 0.9rem;
        color: #666;
        line-height: 1.5; /* Adjusted line height */
        margin-bottom: 15px; /* Adjusted spacing */
        flex-grow: 1; /* Crucial: Pushes rating/button down */
    }
    .product-rating {
        margin-bottom: 15px;
        font-size: 0.9rem;
    }
    .product-rating .fas, .product-rating .far {
        color: #ffc107; /* Gold stars */
    }
    .product-rating span {
        color: #777;
        margin-left: 5px; /* Space between stars and text */
    }
    .product-btn {
        background-color: #28a745; /* Adjust color */
        color: white;
        padding: 10px 20px; /* Adjusted padding */
        border-radius: 5px;
        text-decoration: none;
        text-align: center;
        transition: background-color 0.3s ease;
        margin-top: auto; /* Pushes button to bottom */
        align-self: center; /* Center button horizontally */
        display: inline-block;
        border: none; /* Remove default border if any */
        cursor: pointer;
    }
    .product-btn:hover {
        background-color: #218838; /* Adjust color */
        color: white;
        text-decoration: none;
    }

    /* --- Testimonial Card Uniform Height & Styling (UPDATED for Font Awesome) --- */
    .testimonial-section .row {
        display: flex;
        flex-wrap: wrap;
        margin-left: -15px;
        margin-right: -15px;
    }
     .testimonial-section .col-md-4 {
        display: flex;
        padding-left: 15px;
        padding-right: 15px;
        margin-bottom: 30px;
    }
    .testimonial-card {
        background-color: #ffffff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        position: relative;
        border-left: 5px solid #007bff; /* Adjust color */
    }
    /* UPDATED: Ensure Font Awesome 5 Free Solid is loaded */
    .testimonial-card::before {
        content: '\\f10d'; /* FontAwesome quote-left icon */
        font-family: 'Font Awesome 5 Free'; /* ** Crucial: Make sure FA5 Free is loaded ** */
        font-weight: 900; /* ** Crucial: Use the solid style weight ** */
        font-size: 2rem;
        color: #007bff; /* Adjust color */
        opacity: 0.15; /* Slightly adjusted opacity */
        position: absolute;
        top: 15px;
        left: 20px; /* Adjusted position slightly */
        z-index: 0;
    }
    .testimonial-text {
        font-style: italic;
        color: #555;
        margin-bottom: 15px;
        flex-grow: 1;
        line-height: 1.6;
        padding-top: 25px; /* Adjusted padding to ensure space from icon */
        position: relative; /* Ensure text is above the pseudo-element if overlap occurs */
        z-index: 1;
    }
    .testimonial-author {
        font-weight: bold;
        color: #333;
        margin-top: auto; /* Push author info down */
        margin-bottom: 2px;
        position: relative;
        z-index: 1;
    }
    .testimonial-position {
        font-size: 0.9em;
        color: #777;
        position: relative;
        z-index: 1;
    }

    /* --- Back to Top Button --- */
    .back-to-top {
        position: fixed;
        bottom: 25px;
        right: 25px;
        display: none; /* Initially hidden */
        width: 40px;
        height: 40px;
        background-color: rgba(0, 123, 255, 0.7); /* Adjust color */
        color: white;
        text-align: center;
        line-height: 40px; /* Center icon vertically */
        font-size: 18px;
        border-radius: 50%; /* Circular */
        z-index: 1000; /* Above other content */
        transition: background-color 0.3s ease, opacity 0.5s ease, visibility 0.5s ease;
        opacity: 0; /* Start faded out */
        visibility: hidden; /* Start hidden */
    }
    .back-to-top:hover {
        background-color: rgba(0, 123, 255, 1); /* Adjust color */
        color: white;
    }
    .back-to-top.visible {
        /* Styles applied by JS */
        display: block;
        opacity: 1;
        visibility: visible;
    }

    /* --- General Section Styling (Ensure consistency) --- */
    .section-title {
        font-size: 2.5rem; /* Example size */
        font-weight: 700;
        margin-bottom: 15px;
        color: #333;
    }
    .section-subtitle {
        font-size: 1.1rem; /* Example size */
        color: #666;
        margin-bottom: 40px; /* Space below subtitle */
        max-width: 600px; /* Prevent subtitle from being too wide */
        margin-left: auto;
        margin-right: auto;
    }
    .layout_padding {
        /* Adjust padding if needed, ensure consistency */
        padding-top: 80px;
        padding-bottom: 80px;
    }

    /* --- Feature Box Styling (Example Refinement) --- */
    .feature-box {
        text-align: center;
        padding: 20px;
        background-color: #fff; /* Optional background */
        border-radius: 8px; /* Optional rounding */
        margin-bottom: 20px; /* Space below boxes */
        box-shadow: 0 2px 5px rgba(0,0,0,0.05); /* Optional subtle shadow */
        height: 100%; /* Helps with alignment if needed */
    }
    .feature-icon {
        font-size: 2.5rem;
        color: #007bff; /* Adjust color */
        margin-bottom: 15px;
    }
    .feature-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 10px;
    }
    .feature-text {
        color: #555;
        font-size: 0.95rem;
    }

    /* --- Newsletter Section Styling (UPDATED) --- */
    .newsletter-section {
        background-color: #343a40; /* Dark background */
        color: #fff;
        padding: 70px 0; /* Increased padding */
    }
    /* Center content vertically in the row */
    .newsletter-section .align-items-center {
        align-items: center !important;
    }
    .newsletter-title {
        font-size: 2.2rem; /* Slightly larger */
        font-weight: 600;
        margin-bottom: 10px;
        line-height: 1.3;
    }
    .newsletter-text {
        color: #ccc;
        margin-bottom: 20px; /* Space below text on mobile */
        font-size: 1rem;
    }
    .newsletter-form .input-group {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); /* Add subtle shadow */
        border-radius: 30px; /* Apply rounding to the group */
        overflow: hidden; /* Hide overflow for rounding */
    }
    .newsletter-form .form-control {
        height: 55px; /* Slightly taller */
        border-radius: 30px 0 0 30px !important; /* Match group rounding (important needed for Bootstrap override) */
        border: none;
        font-size: 1rem;
        padding-left: 20px;
    }
    .newsletter-form .form-control:focus {
        box-shadow: none; /* Remove default focus glow */
        border: none;
    }
    .newsletter-form .btn {
        height: 55px; /* Match input height */
        border-radius: 0 30px 30px 0 !important; /* Match group rounding */
        background-color: #007bff; /* Adjust color */
        color: white;
        border: none;
        padding: 0 30px; /* More padding */
        font-weight: 500;
        text-transform: uppercase; /* Uppercase text */
        letter-spacing: 0.5px;
        transition: background-color 0.3s ease;
    }
    .newsletter-form .btn:hover {
        background-color: #0056b3; /* Adjust color */
    }

</style>

<!-- banner section start -->
<div class="banner_section layout_padding">
    <div class="container">
        <div id="carouselExampleIndicators" class="carousel slide" data-ride="carousel">
            <ol class="carousel-indicators">
                <li data-target="#carouselExampleIndicators" data-slide-to="0" class="active"></li>
                <li data-target="#carouselExampleIndicators" data-slide-to="1"></li>
                <li data-target="#carouselExampleIndicators" data-slide-to="2"></li>
                <li data-target="#carouselExampleIndicators" data-slide-to="3"></li>
            </ol>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <div class="row">
                        <div class="col-sm-6">
                            <h1 class="banner_taital" data-aos="fade-down">Premium Siopao</h1>
                            <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Experience the authentic taste of our handcrafted siopao, made with premium ingredients and traditional recipes passed down through generations.</p>
                            <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Order Now</a></div>
                        </div>
                        <div class="col-sm-6" data-aos="zoom-in" data-aos-delay="300">
                            <div class="banner_img"><img src="/LandingPage/images/Siopao.png" alt="Premium Siopao"></div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="row">
                        <div class="col-sm-6">
                            <h1 class="banner_taital" data-aos="fade-down">Delicious Siomai</h1>
                            <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Our signature siomai combines premium pork with special seasonings, wrapped in thin, delicate wonton wrappers for the perfect bite.</p>
                            <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Order Now</a></div>
                        </div>
                        <div class="col-sm-6" data-aos="zoom-in" data-aos-delay="300">
                            <div class="banner_img"><img src="/LandingPage/images/Sioma1.png" alt="Delicious Siomai"></div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="row">
                        <div class="col-sm-6">
                            <h1 class="banner_taital" data-aos="fade-down">Flavorful Sauces</h1>
                            <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Our special blend of sauces enhances every bite. Made from premium ingredients and secret recipes passed down through generations.</p>
                            <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Order Now</a></div>
                        </div>
                        <div class="col-sm-6" data-aos="zoom-in" data-aos-delay="300">
                            <div class="banner_img"><img src="/LandingPage/images/Sauces.png" alt="Flavorful Sauces"></div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="row">
                        <div class="col-sm-6">
                            <h1 class="banner_taital" data-aos="fade-down">Quality Noodles</h1>
                            <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Made from the finest ingredients, our noodles maintain perfect texture and absorb flavors beautifully for your favorite noodle dishes.</p>
                            <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Order Now</a></div>
                        </div>
                        <div class="col-sm-6" data-aos="zoom-in" data-aos-delay="300">
                            <div class="banner_img"><img src="/LandingPage/images/Noodles.png" alt="Quality Noodles"></div>
                        </div>
                    </div>
                </div>
            </div>
            <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="sr-only">Previous</span>
            </a>
            <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="sr-only">Next</span>
            </a>
        </div>
    </div>
</div>
<!-- banner section end -->

<!-- Features Section -->
<div class="about_section layout_padding" style="background-color: #f8f9fa;">
    <div class="container">
        <div class="row mb-5">
            <div class="col-md-12 text-center">
                <h2 class="section-title" data-aos="fade-up">Why Choose Us</h2>
                <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">Quality, tradition, and excellence since 1998</p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3 class="feature-title">Premium Quality</h3>
                    <p class="feature-text">We use only the finest ingredients and maintain strict quality control to ensure every product meets our high standards.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3 class="feature-title">25+ Years Experience</h3>
                    <p class="feature-text">With over two decades in the industry, we've perfected our recipes and processes to deliver consistent excellence.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                    <h3 class="feature-title">Fast Delivery</h3>
                    <p class="feature-text">We ensure timely delivery of your orders with proper packaging to maintain product freshness and quality.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- About Section -->
<div class="about_section layout_padding" style="background-color: #fff;">
    <div class="container">
        <div class="row">
            <div class="col-md-6" data-aos="fade-right">
                <div class="about_img"><img src="/LandingPage/images/about_image_1.png" alt="About Top Food Exchange Corp." class="img-fluid"></div>
            </div>
            <div class="col-md-6" data-aos="fade-left">
                <h1 class="about_taital">Top Exchange Food Corp.</h1>
                <p class="about_text"><strong>Top Exchange Food Corporation (TEFC)</strong> is a top-tier broad line food service supply integrator based in the Philippines. The company began in 1998, primarily addressing the market need for high-quality Chinese ingredients.</p>
                <p class="about_text">Today, we continue to meet this need with the help of various partners in our international network of supply resources, providing high-quality Filipino food products including siopao, siomai, noodles, and sauces.</p>
                <div class="read_bt_1" data-aos="fade-up" data-aos-delay="400"><a href="/LandingPage/about.php">Read More</a></div>
            </div>
        </div>
    </div>
</div>

<!-- Products Section -->
<div class="cream_section layout_padding" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
    <div class="container">
        <div class="row mb-5">
            <div class="col-md-12 text-center">
                <h2 class="section-title" data-aos="fade-up">Our Best Sellers</h2>
                <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">Customer favorites that never disappoint</p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="product-card">
                    <div class="product-img">
                        <img src="/LandingPage/images/Sioma1.png" alt="Premium Pork Siomai">
                        <span class="product-badge bestseller">BESTSELLER</span>
                    </div>
                    <div class="product-body">
                        <div class="product-price">₱280</div>
                        <h5 class="product-title">Premium Pork Siomai</h5>
                        <p class="product-description">1kg pack (approx. 50 pieces) of our signature pork siomai with special seasonings.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <span class="ml-2">(128 reviews)</span>
                        </div>
                        <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                 <div class="product-card">
                    <div class="product-img">
                        <img src="/LandingPage/images/dumpling.png" alt="Special Sharksfin Dumpling">
                        <span class="product-badge popular">POPULAR</span>
                    </div>
                    <div class="product-body">
                        <div class="product-price">₱260</div>
                        <h5 class="product-title">Special Sharksfin Dumpling</h5>
                        <p class="product-description">1kg pack (approx. 45 pieces) of our premium sharksfin dumplings.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                            <span class="ml-2">(96 reviews)</span>
                        </div>
                        <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                 <div class="product-card">
                    <div class="product-img">
                        <img src="/LandingPage/images/wanton.png" alt="Wanton Regular">
                    </div>
                    <div class="product-body">
                        <div class="product-price">₱315</div>
                        <h5 class="product-title">Wanton Regular</h5>
                        <p class="product-description">1kg pack (approx. 60 pieces) of our classic wanton dumplings.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i>
                            <span class="ml-2">(87 reviews)</span>
                        </div>
                        <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                 <div class="product-card">
                    <div class="product-img">
                        <img src="/LandingPage/images/driedegg.png" alt="Dried Egg Noodles">
                    </div>
                    <div class="product-body">
                        <div class="product-price">₱185</div>
                        <h5 class="product-title">Dried Egg Noodles</h5>
                        <p class="product-description">500g pack (serves 4-5 people) of our premium dried egg noodles.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                            <span class="ml-2">(112 reviews)</span>
                        </div>
                        <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                 <div class="product-card">
                    <div class="product-img">
                        <img src="/LandingPage/images/pancitcanton.png" alt="Pancit Canton">
                        <span class="product-badge new">NEW</span>
                    </div>
                    <div class="product-body">
                        <div class="product-price">₱350</div>
                        <h5 class="product-title">Pancit Canton</h5>
                        <p class="product-description">1kg pack (serves 8-10 people) of our premium pancit canton noodles.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <span class="ml-2">(64 reviews)</span>
                        </div>
                        <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                 <div class="product-card">
                    <div class="product-img">
                        <img src="/LandingPage/images/asadosiopao.png" alt="Asado Siopao">
                    </div>
                    <div class="product-body">
                        <div class="product-price">₱280</div>
                        <h5 class="product-title">Asado Siopao</h5>
                        <p class="product-description">10 pieces pack (regular size) of our classic asado-filled siopao.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i>
                            <span class="ml-2">(143 reviews)</span>
                        </div>
                        <a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>" class="product-btn">Add to Cart</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="seemore_bt mt-5" data-aos="fade-up"><a href="/LandingPage/ordering.php">View All Products</a></div>
    </div>
</div>

<!-- Testimonials Section -->
<div class="testimonial-section layout_padding" style="background-color: #f8f9fa;">
    <div class="container">
        <div class="row mb-5">
            <div class="col-md-12 text-center">
                <h2 class="section-title" data-aos="fade-up">What Our Clients Say</h2>
                <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">Trusted by restaurants and food businesses nationwide</p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="testimonial-card">
                    <p class="testimonial-text">"Top Food Exchange Corp. has been our reliable supplier for over 5 years. Their siomai quality is consistently excellent, and our customers love it!"</p>
                    <div class="testimonial-author">Maria Santos</div>
                    <div class="testimonial-position">Owner, Mila's Carinderia</div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                <div class="testimonial-card">
                    <p class="testimonial-text">"The asado siopao from TEFC is our bestseller! The flavor is perfect, and the texture is always consistent. Highly recommended for food businesses."</p>
                    <div class="testimonial-author">Juan Dela Cruz</div>
                    <div class="testimonial-position">Manager, Kainan sa Kanto</div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                <div class="testimonial-card">
                    <p class="testimonial-text">"We switched to TEFC's noodles last year and never looked back. The quality is superior, and their delivery is always on time."</p>
                    <div class="testimonial-author">Liza Tan</div>
                    <div class="testimonial-position">Chef, Lutong Bahay Restaurant</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Newsletter Section -->
<div class="newsletter-section"> <!-- Styling applied via CSS -->
    <div class="container">
        <!-- Added align-items-center here -->
        <div class="row align-items-center">
            <div class="col-md-6" data-aos="fade-right">
                <h2 class="newsletter-title">Join Our Newsletter</h2>
                <p class="newsletter-text">Subscribe to get updates on new products, special offers, and industry tips.</p>
            </div>
            <div class="col-md-6" data-aos="fade-left">
                <form class="newsletter-form">
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Your email address" required>
                        <div class="input-group-append">
                            <button class="btn" type="submit">Subscribe</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<a href="#" class="back-to-top"><i class="fas fa-arrow-up"></i></a>

<!-- Added JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const backToTopButton = document.querySelector('.back-to-top');

    if (backToTopButton) {
        // Function to toggle button visibility
        const toggleVisibility = () => {
            if (window.scrollY > 300) { // Show after scrolling 300px
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        };

        // Smooth scroll to top functionality
        backToTopButton.addEventListener('click', (e) => {
            e.preventDefault(); // Prevent default anchor behavior
            window.scrollTo({
                top: 0,
                behavior: 'smooth' // Smooth scroll animation
            });
        });

        // Listen for scroll events
        window.addEventListener('scroll', toggleVisibility);

        // Initial check in case the page loads already scrolled
        toggleVisibility();
    }

    // Initialize AOS (if not already done elsewhere)
    // Make sure AOS library is included before this script
    if (typeof AOS !== 'undefined') {
      AOS.init({
          duration: 800, // Animation duration
          once: true // Whether animation should happen only once - while scrolling down
      });
    } else {
        console.warn('AOS library not found. Animations will not work.');
    }
});
</script>

<?php
// Include the footer
require_once 'footer.php';
?>