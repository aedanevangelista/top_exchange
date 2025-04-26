<?php
// Set page-specific variables before including header
$pageTitle = "Home | Top Exchange Food Corp";
$pageDescription = "Top Food Exchange Corp. - Premium Filipino food products since 1998. Quality siopao, siomai, noodles, and sauces.";

// Start the session and initialize cart
session_start();
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Include the header
require_once 'header.php';
?>

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
                            <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Experience the authentic taste of our handcrafted siopao, made with premium ingredients and traditional recipes perfected over generations.</p>
                            <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Shop Now</a></div>
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
                            <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Our signature siomai combines premium pork with special seasonings, wrapped in thin, delicate wonton wrappers for an unforgettable flavor experience.</p>
                            <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Shop Now</a></div>
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
                            <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Our special blend of sauces enhances every bite. Made from premium ingredients and secret recipes passed down through generations of master chefs.</p>
                            <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Shop Now</a></div>
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
                            <p class="banner_text" data-aos="fade-right" data-aos-delay="200">Made from the finest ingredients, our noodles maintain perfect texture and absorb flavors beautifully whether stir-fried, boiled, or used in soups.</p>
                            <div class="started_text" data-aos="fade-up" data-aos-delay="400"><a href="<?php echo isset($_SESSION['username']) ? '/LandingPage/ordering.php' : '/LandingPage/login.php'; ?>">Shop Now</a></div>
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
<div class="about_section" style="padding: 100px 0; background-color: #f8f9fa;">
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
<div class="about_section layout_padding" style="background-color: #fff; padding: 100px 0;">
    <div class="container">
        <div class="row">
            <div class="col-md-6" data-aos="fade-right">
                <div class="about_img"><img src="/LandingPage/images/about_image_1.png" alt="About Top Food Exchange Corp." class="img-fluid"></div>
            </div>
            <div class="col-md-6" data-aos="fade-left">
                <h1 class="about_taital">Top Exchange Food Corp.</h1>
                <p class="about_text"><strong>Top Exchange Food Corporation (TEFC)</strong> is a top-tier broad line food service supply integrator based in the Philippines. The company began in 1998 as a single-product supplier, responding to the growing demand for delicious homemade siomai and other dimsum products among Filipinos.</p>
                <p class="about_text">Today, we continue to meet this need with the help of various partners in our international network of supply resources, providing high-quality Filipino food that represents our commitment to quality, reliability, and superior taste.</p>
                <div class="read_bt_1" data-aos="fade-up" data-aos-delay="400"><a href="/LandingPage/about.php">Read More</a></div>
            </div>
        </div>
    </div>
</div>

<!-- Products Section -->
<div class="cream_section layout_padding" style="padding: 100px 0; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
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
                        <span class="product-badge">BESTSELLER</span>
                    </div>
                    <div class="product-body">
                        <div class="product-price">₱280</div>
                        <h5 class="product-title">Premium Pork Siomai</h5>
                        <p class="product-description">1kg pack (approx. 50 pieces) of our signature pork siomai with special seasonings.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
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
                        <span class="product-badge">POPULAR</span>
                    </div>
                    <div class="product-body">
                        <div class="product-price">₱260</div>
                        <h5 class="product-title">Special Sharksfin Dumpling</h5>
                        <p class="product-description">1kg pack (approx. 45 pieces) of our premium sharksfin dumplings.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
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
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
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
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
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
                        <span class="product-badge">NEW</span>
                    </div>
                    <div class="product-body">
                        <div class="product-price">₱350</div>
                        <h5 class="product-title">Pancit Canton</h5>
                        <p class="product-description">1kg pack (serves 8-10 people) of our premium pancit canton noodles.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
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
                        <h5 class="product-title">Asado Siopai</h5>
                        <p class="product-description">10 pieces pack (regular size) of our classic asado-filled siopao.</p>
                        <div class="product-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
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
<div class="testimonial-section" style="padding: 100px 0; background-color: #f8f9fa;">
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
<div class="newsletter-section">
    <div class="container">
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

<?php
// Include the footer
require_once 'footer.php';
?>