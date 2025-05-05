<?php
session_start();
$isLoggedIn = isset($_SESSION['role']) && $_SESSION['role'] === 'client';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>MacJ Pest Control Services</title>
  <meta name="description" content="Professional pest control services for homes and businesses">
  <meta name="keywords" content="pest control, termite control, rodent control, pest management, MacJ">

  <!-- Favicons -->
  <link rel="icon" href="assets/img/favicon.png">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <?php if ($isLoggedIn): ?>
  <!-- Client-side CSS for logged-in users -->
  <link href="../Client Side/css/variables.css" rel="stylesheet">
  <link href="../Client Side/css/main.css" rel="stylesheet">
  <link href="../Client Side/css/header.css" rel="stylesheet">
  <link href="../Client Side/css/sidebar.css" rel="stylesheet">
  <link href="../Client Side/css/client-common.css" rel="stylesheet">
  <link href="../Client Side/css/footer.css" rel="stylesheet">
  <link href="../Client Side/css/landing-integration.css" rel="stylesheet">
  <link href="../Client Side/css/form-validation-fix.css" rel="stylesheet">
  <link href="../Client Side/css/content-spacing-fix.css" rel="stylesheet">
  <?php endif; ?>
</head>

<body class="index-page">

  <!-- Preloader removed for testing -->
  <!-- <div id="preloader"></div> -->

  <!-- Header -->
  <header id="header" class="header d-flex align-items-center fixed-top">
    <div class="container-fluid container-xl d-flex align-items-center justify-content-between">

      <a href="index.html" class="logo d-flex align-items-center">
        <img src="assets/img/MACJLOGO.png" alt="MACJ Pest Control" class="img-fluid">
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="#hero" class="active">Home</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="#services">Services</a></li>
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>

      <div class="header-buttons d-flex align-items-center">
        <a class="btn-getstarted" href="../SignIn.php">Sign In</a>
      </div>

    </div>
  </header>

  <main class="main">

    <!-- Hero Section -->
    <section id="hero" class="hero section">
      <div class="container">
        <div class="row gy-5 align-items-center">
          <div class="col-lg-7 order-2 order-lg-1 d-flex flex-column justify-content-center">
            <h1>Professional Pest Control Solutions</h1>
            <p class="hero-text">We understand the importance of a pest-free environment for your home, business, and health. Our experienced team of licensed professionals is dedicated to providing top-notch pest control solutions tailored to meet your unique needs.</p>
            <div class="d-flex gap-4 mt-4">
              <a href="../SignIn.php" class="btn-get-started">Schedule Service</a>
              <a href="#services" class="btn-learn-more">Our Services</a>
            </div>
          </div>
          <div class="col-lg-5 order-1 order-lg-2 hero-img">
            <img src="assets/img/teammacj.jpg" class="img-fluid rounded-4 shadow-lg animated" alt="MACJ Pest Control Team">
          </div>
        </div>
      </div>
    </section><!-- End Hero Section -->



    <!-- About Section -->
    <section id="about" class="about section light-background">

      <!-- Section Title -->
      <div class="container section-title">
        <h2>About Us</h2>
        <p>Learn more about our company and our commitment to excellence</p>
      </div><!-- End Section Title -->

      <div class="container">
        <div class="row gy-5 align-items-center">

          <div class="content col-lg-5">
            <div class="about-img position-relative mb-4">
              <img src="assets/img/teammacj.jpg" class="img-fluid rounded-4 shadow" alt="MACJ Pest Control Team">
              <div class="experience-badge">
                <span class="years">21+</span>
                <span class="text">Years of Experience</span>
              </div>
            </div>
            <h3 class="mb-3">MACJ PEST CONTROL</h3>
            <p class="mb-4">
              Was founded by a licensed pest control professional with over twenty-one years of experience who is committed to developing and applying innovative solutions for various pest issues.
            </p>
            <div class="d-flex align-items-center mb-3">
              <i class="bi bi-check-circle-fill me-2 text-success"></i>
              <p class="mb-0">Licensed and certified professionals</p>
            </div>
            <div class="d-flex align-items-center mb-3">
              <i class="bi bi-check-circle-fill me-2 text-success"></i>
              <p class="mb-0">Eco-friendly pest control solutions</p>
            </div>
            <div class="d-flex align-items-center mb-4">
              <i class="bi bi-check-circle-fill me-2 text-success"></i>
              <p class="mb-0">Customized treatment plans</p>
            </div>
            <a href="#services" class="btn-learn-more">Our Services <i class="bi bi-arrow-right ms-2"></i></a>
          </div>

          <div class="col-lg-7">
            <div class="row gy-4">

              <div class="col-md-6">
                <div class="icon-box">
                  <i class="bi bi-bullseye text-primary"></i>
                  <h4>MISSION</h4>
                  <p>To build and establish a successful relationship with our clients as well as our suppliers. To provide our clients high quality and high standard service. To provide more jobs in order to contribute to our economy as well as providing our people an employee program that will enhance their personal growth.</p>
                </div>
              </div><!-- Icon-Box -->

              <div class="col-md-6">
                <div class="icon-box">
                  <i class="bi bi-eye text-primary"></i>
                  <h4>VISION</h4>
                  <p>To evolve as the "most excellent service provider" in the market, providing quality and honest service that every customer deserves.</p>
                </div>
              </div><!-- Icon-Box -->

              <div class="col-md-6">
                <div class="icon-box">
                  <i class="bi bi-award text-primary"></i>
                  <h4>CERTIFICATIONS</h4>
                  <p>DUNS accredited, FPA License Fumigator and Exterminator, FDA License to Operate and member of KAPESTCOPI INC. (Kapisanan ng mga Pest Control Operators ng Pilipinas).</p>
                </div>
              </div><!-- Icon-Box -->

              <div class="col-md-6">
                <div class="icon-box">
                  <i class="bi bi-shield-check text-primary"></i>
                  <h4>OUR VALUES</h4>
                  <p>We are committed to integrity, excellence, innovation, and customer satisfaction in every service we provide. Your safety and satisfaction are our top priorities.</p>
                </div>
              </div><!-- Icon-Box -->

            </div>
          </div>

        </div>
      </div>

    </section><!-- End About Section -->

    <!-- Services Section -->
    <section id="services" class="services section">
      <div class="container">
        <!-- Section Title -->
        <div class="section-title">
          <h2>Our Services</h2>
          <p>Comprehensive pest control solutions for your home and business</p>
        </div>
        <div class="row">
          <div class="col-12">
            <div id="servicesCarousel" class="carousel slide" data-bs-ride="carousel">
              <!-- Carousel Indicators -->
              <div class="carousel-indicators">
                <button type="button" data-bs-target="#servicesCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="General Pest Control"></button>
                <button type="button" data-bs-target="#servicesCarousel" data-bs-slide-to="1" aria-label="Termite Baiting"></button>
                <button type="button" data-bs-target="#servicesCarousel" data-bs-slide-to="2" aria-label="Wood Protection"></button>
                <button type="button" data-bs-target="#servicesCarousel" data-bs-slide-to="3" aria-label="Disinfections"></button>
                <button type="button" data-bs-target="#servicesCarousel" data-bs-slide-to="4" aria-label="Installation of Pipes"></button>
                <button type="button" data-bs-target="#servicesCarousel" data-bs-slide-to="5" aria-label="Weed Control"></button>
                <button type="button" data-bs-target="#servicesCarousel" data-bs-slide-to="6" aria-label="Rodent Control"></button>
                <button type="button" data-bs-target="#servicesCarousel" data-bs-slide-to="7" aria-label="Soil Poisoning"></button>
              </div>

              <!-- Carousel Content -->
              <div class="carousel-inner rounded-4 shadow-lg">
                <!-- General Pest Control Slide -->
                <div class="carousel-item active">
                  <div class="service-carousel-item">
                    <div class="row g-0">
                      <div class="col-md-6">
                        <img src="assets/img/GenPest.jpg" class="img-fluid carousel-img" alt="General Pest Control">
                      </div>
                      <div class="col-md-6 d-flex align-items-center">
                        <div class="carousel-content p-4 p-md-5">
                          <h3>General Pest Control</h3>
                          <p class="mb-4">Our comprehensive pest control services target a wide range of common household pests including ants, cockroaches, spiders, and more. We use integrated pest management techniques to effectively eliminate pests while minimizing environmental impact.</p>
                          <ul class="service-features">
                            <li><i class="bi bi-check-circle-fill me-2"></i> Thorough property inspection</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Customized treatment plans</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Preventive barrier treatments</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Follow-up monitoring</li>
                          </ul>
                          <div class="mt-4">
                            <a href="../SignIn.php" class="btn-learn-more">Schedule Service <i class="bi bi-arrow-right ms-2"></i></a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Termite Baiting Slide -->
                <div class="carousel-item">
                  <div class="service-carousel-item">
                    <div class="row g-0">
                      <div class="col-md-6">
                        <img src="assets/img/termite.jpg" class="img-fluid carousel-img" alt="Termite Baiting">
                      </div>
                      <div class="col-md-6 d-flex align-items-center">
                        <div class="carousel-content p-4 p-md-5">
                          <h3>Termite Baiting</h3>
                          <p class="mb-4">Our termite baiting system is an environmentally friendly approach to eliminating termite colonies. We install bait stations around your property that attract termites and eliminate the entire colony.</p>
                          <ul class="service-features">
                            <li><i class="bi bi-check-circle-fill me-2"></i> Minimal disruption to your property</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Targets the entire termite colony</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Regular monitoring and maintenance</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Long-term protection</li>
                          </ul>
                          <div class="mt-4">
                            <a href="../SignIn.php" class="btn-learn-more">Schedule Service <i class="bi bi-arrow-right ms-2"></i></a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Wood Protection Slide -->
                <div class="carousel-item">
                  <div class="service-carousel-item">
                    <div class="row g-0">
                      <div class="col-md-6">
                        <img src="assets/img/wood.jpg" class="img-fluid carousel-img" alt="Wood Protection">
                      </div>
                      <div class="col-md-6 d-flex align-items-center">
                        <div class="carousel-content p-4 p-md-5">
                          <h3>Wood Protection</h3>
                          <p class="mb-4">Our wood protection service safeguards your wooden structures from termites, wood-boring beetles, and other pests. We apply specialized treatments that penetrate the wood to provide long-lasting protection.</p>
                          <ul class="service-features">
                            <li><i class="bi bi-check-circle-fill me-2"></i> Comprehensive wood assessment</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Deep-penetrating treatments</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Protection against multiple wood pests</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Extends the life of wooden structures</li>
                          </ul>
                          <div class="mt-4">
                            <a href="../SignIn.php" class="btn-learn-more">Schedule Service <i class="bi bi-arrow-right ms-2"></i></a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Disinfections Slide -->
                <div class="carousel-item">
                  <div class="service-carousel-item">
                    <div class="row g-0">
                      <div class="col-md-6">
                        <img src="assets/img/Disinfection-01.webp" class="img-fluid carousel-img" alt="Disinfections">
                      </div>
                      <div class="col-md-6 d-flex align-items-center">
                        <div class="carousel-content p-4 p-md-5">
                          <h3>Disinfection</h3>
                          <p class="mb-4">Our professional disinfection services help eliminate harmful pathogens, bacteria, and viruses from your home or business. We use hospital-grade disinfectants that are effective yet safe for your family and pets.</p>
                          <ul class="service-features">
                            <li><i class="bi bi-check-circle-fill me-2"></i> Thorough surface disinfection</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Fogging for hard-to-reach areas</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Safe, EPA-approved products</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Residential and commercial services</li>
                          </ul>
                          <div class="mt-4">
                            <a href="../SignIn.php" class="btn-learn-more">Schedule Service <i class="bi bi-arrow-right ms-2"></i></a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Installation of Pipes Slide -->
                <div class="carousel-item">
                  <div class="service-carousel-item">
                    <div class="row g-0">
                      <div class="col-md-6">
                        <img src="assets/img/Pipes.jpg" class="img-fluid carousel-img" alt="Installation of Pipes">
                      </div>
                      <div class="col-md-6 d-flex align-items-center">
                        <div class="carousel-content p-4 p-md-5">
                          <h3>Installation of Pipes</h3>
                          <p class="mb-4">Our pipe installation services ensure proper drainage and water management around your property. We install high-quality pipes that help prevent water damage and create an environment less conducive to pest infestations.</p>
                          <ul class="service-features">
                            <li><i class="bi bi-check-circle-fill me-2"></i> Professional installation techniques</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Durable, high-quality materials</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Proper drainage solutions</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Reduces pest-friendly environments</li>
                          </ul>
                          <div class="mt-4">
                            <a href="../SignIn.php" class="btn-learn-more">Schedule Service <i class="bi bi-arrow-right ms-2"></i></a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Weed Control Slide -->
                <div class="carousel-item">
                  <div class="service-carousel-item">
                    <div class="row g-0">
                      <div class="col-md-6">
                        <img src="assets/img/weed.jpg" class="img-fluid carousel-img" alt="Weed Control">
                      </div>
                      <div class="col-md-6 d-flex align-items-center">
                        <div class="carousel-content p-4 p-md-5">
                          <h3>Weed Control</h3>
                          <p class="mb-4">Our weed control services help maintain the beauty and health of your lawn and garden. We use targeted treatments to eliminate unwanted weeds while preserving your desired plants and reducing pest habitats.</p>
                          <ul class="service-features">
                            <li><i class="bi bi-check-circle-fill me-2"></i> Selective weed targeting</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Pre-emergent and post-emergent treatments</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Environmentally responsible applications</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Ongoing maintenance plans available</li>
                          </ul>
                          <div class="mt-4">
                            <a href="../SignIn.php" class="btn-learn-more">Schedule Service <i class="bi bi-arrow-right ms-2"></i></a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Rodent Control Slide -->
                <div class="carousel-item">
                  <div class="service-carousel-item">
                    <div class="row g-0">
                      <div class="col-md-6">
                        <img src="assets/img/rodent.jpg" class="img-fluid carousel-img" alt="Rodent Control">
                      </div>
                      <div class="col-md-6 d-flex align-items-center">
                        <div class="carousel-content p-4 p-md-5">
                          <h3>Rodent Control</h3>
                          <p class="mb-4">Our specialized rodent control service targets rats, mice, and other rodents that can damage property and spread disease. We use safe and effective methods to eliminate rodents and prevent their return.</p>
                          <ul class="service-features">
                            <li><i class="bi bi-check-circle-fill me-2"></i> Complete property inspection</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Safe and humane removal methods</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Entry point sealing</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Preventive recommendations</li>
                          </ul>
                          <div class="mt-4">
                            <a href="../SignIn.php" class="btn-learn-more">Schedule Service <i class="bi bi-arrow-right ms-2"></i></a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Soil Poisoning Slide -->
                <div class="carousel-item">
                  <div class="service-carousel-item">
                    <div class="row g-0">
                      <div class="col-md-6">
                        <img src="assets/img/Soil.jpg" class="img-fluid carousel-img" alt="Soil Poisoning">
                      </div>
                      <div class="col-md-6 d-flex align-items-center">
                        <div class="carousel-content p-4 p-md-5">
                          <h3>Soil Poisoning</h3>
                          <p class="mb-4">Our soil poisoning treatment creates a chemical barrier in the soil to prevent termites and other ground pests from entering your structures. This pre-construction and post-construction service provides long-lasting protection.</p>
                          <ul class="service-features">
                            <li><i class="bi bi-check-circle-fill me-2"></i> Effective pre-construction treatment</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Long-lasting termite barrier</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Protection for foundations and structures</li>
                            <li><i class="bi bi-check-circle-fill me-2"></i> Professionally applied solutions</li>
                          </ul>
                          <div class="mt-4">
                            <a href="../SignIn.php" class="btn-learn-more">Schedule Service <i class="bi bi-arrow-right ms-2"></i></a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Carousel Controls -->
              <button class="carousel-control-prev" type="button" data-bs-target="#servicesCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#servicesCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
              </button>
            </div>
          </div>
        </div>
      </div>

    </section><!-- End Services Section -->

    <!-- Testimonials Section -->
    <section id="testimonials" class="testimonials section light-background">
      <div class="container">
        <!-- Section Title -->
        <div class="section-title">
          <h2>Testimonials</h2>
          <p>What our clients say about our services</p>
        </div>

        <div class="row">
          <div class="col-12">
            <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-inner">
                <?php
                // Connect to the database
                require_once '../db_config.php';

                // Query to get feedback with 4 or 5 stars from both tables
                $feedback_query = "
                    (SELECT
                        jf.comments,
                        jf.rating,
                        jf.created_at,
                        c.first_name,
                        c.last_name,
                        c.type_of_place,
                        'job_order' AS feedback_type
                    FROM joborder_feedback jf
                    JOIN clients c ON jf.client_id = c.client_id
                    WHERE jf.rating >= 4)

                    UNION

                    (SELECT
                        tf.comments,
                        tf.rating,
                        tf.created_at,
                        c.first_name,
                        c.last_name,
                        c.type_of_place,
                        'technician' AS feedback_type
                    FROM technician_feedback tf
                    JOIN clients c ON tf.client_id = c.client_id
                    WHERE tf.rating >= 4)

                    ORDER BY created_at DESC
                    LIMIT 10";

                $feedback_result = $pdo->query($feedback_query);
                $feedbacks = $feedback_result->fetchAll(PDO::FETCH_ASSOC);

                // If no feedback found, show default testimonials
                if (empty($feedbacks)) {
                    // Default testimonials
                    $default_testimonials = [
                        [
                            'comments' => 'MacJ Pest Control provided exceptional service for our termite problem. Their team was professional, thorough, and explained every step of the treatment process. We\'ve been pest-free for over a year now!',
                            'first_name' => 'Maria',
                            'last_name' => 'Santos',
                            'type_of_place' => 'House',
                            'rating' => 5
                        ],
                        [
                            'comments' => 'As a restaurant owner, pest control is critical to our business. MacJ has been our trusted partner for over 5 years. Their preventive treatments and quick response times have kept our establishment pest-free and our customers happy.',
                            'first_name' => 'John',
                            'last_name' => 'Reyes',
                            'type_of_place' => 'Restaurant',
                            'rating' => 5
                        ],
                        [
                            'comments' => 'We hired MacJ for our office building\'s quarterly pest management. Their team is always punctual, professional, and thorough. The eco-friendly solutions they use are perfect for our workplace environment.',
                            'first_name' => 'Anna',
                            'last_name' => 'Cruz',
                            'type_of_place' => 'Office',
                            'rating' => 4
                        ]
                    ];
                    $feedbacks = $default_testimonials;
                }

                // Display testimonials
                foreach ($feedbacks as $index => $feedback) {
                    $active_class = ($index === 0) ? 'active' : '';
                    $occupation = !empty($feedback['type_of_place']) ? $feedback['type_of_place'] . ' Owner' : 'Client';

                    // Default image based on index
                    $image_index = ($index % 3) + 1;
                    $image_path = "assets/img/testimonials/testimonial-{$image_index}.jpg";

                    // Generate stars based on rating
                    $stars = '';
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $feedback['rating']) {
                            $stars .= '<i class="bi bi-star-fill"></i>';
                        } elseif ($i - 0.5 <= $feedback['rating']) {
                            $stars .= '<i class="bi bi-star-half"></i>';
                        } else {
                            $stars .= '<i class="bi bi-star"></i>';
                        }
                    }

                    echo "
                    <div class='carousel-item {$active_class}'>
                      <div class='testimonial-item'>
                        <div class='row justify-content-center'>
                          <div class='col-lg-8'>
                            <div class='testimonial-content text-center'>
                              <p>
                                <i class='bi bi-quote quote-icon-left'></i>
                                {$feedback['comments']}
                                <i class='bi bi-quote quote-icon-right'></i>
                              </p>
                              <div class='testimonial-img'>
                                <img src='{$image_path}' class='img-fluid rounded-circle' alt=''>
                              </div>
                              <h3>{$feedback['first_name']} {$feedback['last_name']}</h3>
                              <h4>{$occupation}</h4>
                              <div class='stars'>
                                {$stars}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>";
                }
                ?>
              </div>

              <!-- Carousel Controls -->
              <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section><!-- End Testimonials Section -->

    <!-- Call to Action Section -->
    <section id="cta" class="cta section">
      <div class="container">
        <div class="row g-5">
          <div class="col-lg-8 col-md-6 content d-flex flex-column justify-content-center order-last order-md-first">
            <h3>Ready for a Pest-Free Environment?</h3>
            <p>Schedule a consultation with our pest control experts today. We'll create a customized treatment plan tailored to your specific needs.</p>
            <div class="cta-btn-container">
              <a class="cta-btn align-self-start" href="../SignIn.php">Get Started</a>
            </div>
          </div>
          <div class="col-lg-4 col-md-6 order-first order-md-last d-flex align-items-center">
            <div class="img">
              <img src="assets/img/cta-image.jpg" alt="" class="img-fluid">
            </div>
          </div>
        </div>
      </div>
    </section><!-- End Call to Action Section -->
  </main>

  <footer id="footer" class="footer">

    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-5 col-md-12 footer-info">
          <a href="index.html" class="logo d-flex align-items-center mb-3">
            <img src="assets/img/MACJLOGO.png" alt="MACJ Pest Control" class="img-fluid" style="max-height: 60px;">
          </a>
          <p>MacJ Pest Control Services provides professional pest management solutions for residential and commercial properties. With over 21 years of experience, we deliver effective and eco-friendly pest control services.</p>
          <h4 class="mt-4">Connect With Us</h4>
          <div class="social-links d-flex mt-3">
            <a href="#" class="twitter"><i class="bi bi-twitter-x"></i></a>
            <a href="#" class="facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>

        <div class="col-lg-2 col-6 footer-links">
          <h4>Useful Links</h4>
          <ul>
            <li><a href="#hero">Home</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="../SignIn.php">Sign In</a></li>
            <li><a href="#footer">Contact</a></li>
          </ul>
        </div>

        <div class="col-lg-2 col-6 footer-links">
          <h4>Our Services</h4>
          <ul>
            <li>General Pest Control</li>
            <li>Rodent Control</li>
            <li>Termite Baiting</li>
            <li>Soil Poisoning</li>
            <li>Wood Protection</li>
          </ul>
        </div>

        <div class="col-lg-3 col-md-12 footer-contact text-center text-md-start">
          <h4>Contact Us</h4>
          <p>
            30 Sto. Tomas St. <br>
            Brgy Don Manuel<br>
            Quezon City <br><br>
            <strong>Phone:</strong> (02)7369-3904/880-554040<br>
            <strong>Mobile:</strong> 09171457306 / 09055158398<br>
            <strong>Email:</strong> info@macjpestcontrol.com<br>
          </p>
        </div>

      </div>
    </div>

    <div class="container mt-4">
      <div class="copyright text-center">
        <p>© <span>Copyright</span> <strong class="px-1 sitename">MacJ Pest Control Services</strong> <span>All Rights Reserved</span></p>
      </div>
    </div>

  </footer>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Preloader removed for testing -->
  <!-- <div id="preloader"></div> -->

  <!-- Vendor JS Files -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Main JS File (Simplified) -->
  <script src="assets/js/main-simple.js"></script>

  <?php if ($isLoggedIn): ?>
  <!-- Client-side JS for logged-in users -->
  <script src="../Client Side/js/main.js"></script>
  <script src="../Client Side/js/sidebar.js"></script>
  <script src="../Client Side/js/form-validation-fix.js"></script>
  <?php endif; ?>

</body>

</html>
