<?php
// Set page-specific variables before including header
$pageTitle = "About | Top Exchange Food Corp";
$pageDescription = "Learn about Top Food Exchange Corp. - Premium Filipino food products since 1998. Our history, mission, and values.";

// Include the header
require_once 'header.php';
?>

<style>
/* --- General About Page Styles --- */
:root {
    --section-padding: 60px 0; /* Define standard padding */
    --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    --card-hover-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    --primary-color: #e46c1d; /* Your theme color */
    --light-bg: #f8f9fa;
}

.about_section {
    /* Padding applied inline currently, can be moved here */
    /* padding: var(--section-padding); */
}

.about_section h1, .about_section h2, .about_section h3 {
    color: #333;
    margin-bottom: 20px;
    font-weight: 600;
}

.about_section h2 {
    font-size: 2.2rem;
}
.about_section h2 i { /* Icon spacing for section titles */
     margin-right: 10px;
}

.about_section h3 {
    font-size: 1.5rem;
}

.about_section p {
    color: #555;
    line-height: 1.7;
}

/* --- Intro Section --- */
.about_section .row:first-of-type { /* Target the first row specifically */
    align-items: center; /* Vertically align image col and text col */
}
.about_img img {
    border-radius: 8px;
    box-shadow: var(--card-shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    max-width: 100%;
    height: auto;
}
.about_img img:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-hover-shadow);
}
.about_taital { /* Main page title */
    font-size: 2.8rem;
    margin-bottom: 25px;
}
.about_text {
    margin-bottom: 18px;
}
.about_text strong {
    color: #222;
}

/* --- Mission / Vision / Leadership / Testimonials Cards --- */
.card {
    border: none; /* Remove default border */
    box-shadow: var(--card-shadow);
    transition: box-shadow 0.3s ease;
    border-radius: 8px;
}
.card:hover {
    box-shadow: var(--card-hover-shadow);
}
.card .card-title {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 15px;
}
.card .card-title i {
    margin-right: 8px; /* Space after icon */
    width: 20px; /* Ensure consistent icon spacing */
    text-align: center;
}
.card .card-img-top {
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}
.testimonial-quote i {
    color: var(--primary-color);
    opacity: 0.6;
    font-size: 1.5rem;
}
.testimonial-author h6 {
    color: #333;
    font-weight: 600;
}

/* --- Core Values --- */
.core-values h3 i {
    margin-right: 8px;
}
.core-values .list-group-item {
    border: none; /* Remove borders */
    padding: 10px 0; /* Adjust padding */
    color: #555;
    background-color: transparent;
}
.core-values .list-group-item strong {
    color: #333;
    display: inline-block;
    min-width: 110px; /* Align the descriptions */
}

/* --- History Timeline --- */
.history-section {
    padding: 40px 0;
    /* background-color: var(--light-bg); Optional background */
}
.timeline {
    position: relative;
    max-width: 900px;
    margin: 0 auto;
}
.timeline::after { /* The central line */
    content: '';
    position: absolute;
    width: 4px;
    background-color: #dee2e6;
    top: 0;
    bottom: 0;
    left: 50%;
    margin-left: -2px;
}
.timeline-item {
    padding: 10px 40px;
    position: relative;
    background-color: inherit;
    width: 50%;
}
.timeline-item::after { /* The circles on the line */
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    right: -9px; /* Adjust based on line thickness */
    background-color: white;
    border: 3px solid var(--primary-color);
    top: 20px;
    border-radius: 50%;
    z-index: 1;
}
.timeline-item.left {
    left: 0;
}
.timeline-item.right {
    left: 50%;
}
.timeline-item.left::before { /* The connecting arrows */
    content: " ";
    height: 0;
    position: absolute;
    top: 22px;
    width: 0;
    z-index: 1;
    right: 30px;
    border: medium solid white;
    border-width: 10px 0 10px 10px;
    border-color: transparent transparent transparent white;
}
.timeline-item.right::before {
    content: " ";
    height: 0;
    position: absolute;
    top: 22px;
    width: 0;
    z-index: 1;
    left: 30px;
    border: medium solid white;
    border-width: 10px 10px 10px 0;
    border-color: transparent white transparent transparent;
}
.timeline-item.right::after {
    left: -9px; /* Adjust based on line thickness */
}
.timeline-content {
    padding: 20px 30px;
    background-color: white;
    position: relative;
    border-radius: 6px;
    box-shadow: var(--card-shadow);
}
.timeline-content h4 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 8px;
}
.timeline-date {
    position: absolute;
    top: 18px;
    width: 100px; /* Adjust width as needed */
    text-align: center;
    font-weight: bold;
    color: var(--primary-color);
    z-index: 2;
}
.timeline-item.left .timeline-date {
    right: -130px; /* Position date to the right */
}
.timeline-item.right .timeline-date {
    left: -130px; /* Position date to the left */
}


/* --- Achievements Section --- */
.achievements-section {
     padding: 40px 0;
     background-color: var(--light-bg); /* Optional background */
}
.achievement-box {
    padding: 25px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: var(--card-shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 100%;
    display: flex; /* Added for centering */
    flex-direction: column; /* Added for centering */
    justify-content: center; /* Added for centering */
    align-items: center; /* Added for centering */
}
.achievement-box:hover {
     transform: translateY(-5px);
     box-shadow: var(--card-hover-shadow);
}
.achievement-icon i {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 15px;
}
.achievement-box h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
}
.achievement-box p {
    color: #555;
    font-size: 0.95rem;
    margin-bottom: 0;
}

/* --- Responsive Adjustments --- */
@media (max-width: 991.98px) { /* Adjust breakpoint if needed */
    .about_taital {
        font-size: 2.5rem; /* Slightly smaller */
    }
    .about_section h2 {
        font-size: 2rem;
    }
     .about_section h3 {
        font-size: 1.4rem;
    }
}

@media (max-width: 767.98px) {
    .about_section .row:first-of-type {
        flex-direction: column; /* Stack intro section */
    }
    .about_section .row:first-of-type .col-md-6 {
        width: 100%;
        margin-bottom: 30px;
    }
     .about_section .row:first-of-type .col-md-6:last-child {
         margin-bottom: 0;
     }
    .about_taital {
        font-size: 2.2rem;
        text-align: center;
    }
     .about_section h2 {
        font-size: 1.8rem;
    }
    .about_section h3 {
        font-size: 1.3rem;
    }

    /* Timeline adjustments */
    .timeline::after {
        left: 31px; /* Move line to left */
    }
    .timeline-item {
        width: 100%;
        padding-left: 70px; /* Make space for line/icon */
        padding-right: 25px;
    }
    .timeline-item.left, .timeline-item.right {
        left: 0%; /* Align all items left */
    }
    .timeline-item.left::before, .timeline-item.right::before {
        left: 60px; /* Adjust arrow position */
        border: medium solid white;
        border-width: 10px 10px 10px 0;
        border-color: transparent white transparent transparent;
    }
    .timeline-item.left::after, .timeline-item.right::after {
        left: 23px; /* Position circle on the line */
    }
    .timeline-item.left .timeline-date, .timeline-item.right .timeline-date {
        position: relative; /* Change date position */
        left: auto;
        right: auto;
        width: auto;
        text-align: left;
        margin-bottom: 5px;
        top: 0;
    }

    .core-values .col-md-6 {
        width: 100%; /* Stack core values */
    }
    .core-values .list-group-item strong {
        min-width: 90px; /* Adjust alignment */
    }
}

</style>

<!-- about section start -->
<!-- Using var(--section-padding) requires it defined in :root or style block -->
<div class="about_section layout_padding" style="background-color: #fff;">
    <div class="container">
        <div class="row"> <!-- Intro Row -->
            <div class="col-md-6" data-aos="fade-right">
                <div class="about_img"><img src="/LandingPage/images/about_image_1.png" class="img-fluid" alt="About Top Exchange Food Corp"></div>
                <!-- Consider removing second image or placing differently if layout is crowded -->
                <div class="about_img mt-4" data-aos="fade-right" data-aos-delay="200"><img src="/LandingPage/images/about_image_2.png" class="img-fluid" alt="Our Facilities"></div>
            </div>
            <div class="col-md-6" data-aos="fade-left">
                <h1 class="about_taital">Top Exchange Food Corporation</h1>
                <p class="about_text"><strong>Top Exchange Food Corporation (TEFC)</strong> is a top-tier broad line food service supply integrator based in the Philippines. The company began in 1998, primarily addressing the market need for high-quality Chinese ingredients. Over the years, we have expanded our portfolio to include a wide range of premium Filipino and international food products.</p>
                <p class="about_text">Our commitment to quality, innovation, and customer satisfaction has made us a trusted partner for thousands of businesses across the nation. We leverage our extensive network and state-of-the-art facilities to ensure timely delivery and product excellence.</p>

                <div class="mission-vision mt-5">
                    <div class="row">
                        <div class="col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h3 class="card-title"><i class="fas fa-bullseye"></i>Our Mission</h3>
                                    <p class="card-text">To be the premier food service provider in the Philippines by delivering exceptional quality products, innovative solutions, and unmatched customer service.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h3 class="card-title"><i class="fas fa-eye"></i>Our Vision</h3>
                                    <p class="card-text">To revolutionize the food service industry by creating a seamless bridge between global food innovations and local culinary traditions, making quality accessible to all.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="core-values mt-4" data-aos="fade-up" data-aos-delay="400">
                    <h3><i class="fas fa-star"></i>Core Values</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Quality:</strong> We source only the finest ingredients</li>
                                <li class="list-group-item"><strong>Integrity:</strong> Honesty in all our dealings</li>
                                <li class="list-group-item"><strong>Innovation:</strong> Continuous improvement in our offerings</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Sustainability:</strong> Environmentally responsible practices</li>
                                <li class="list-group-item"><strong>Customer Focus:</strong> Exceeding expectations</li>
                                <li class="list-group-item"><strong>Teamwork:</strong> Collaborative success</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Section -->
        <div class="row mt-5 pt-4">
            <div class="col-md-12">
                <div class="history-section">
                    <h2 class="text-center mb-5" data-aos="fade-up"><i class="fas fa-history"></i>Our History</h2>
                    <div class="timeline">
                        <div class="timeline-item left" data-aos="fade-right">
                            <div class="timeline-date">1998</div>
                            <div class="timeline-content">
                                <h4>Foundation</h4>
                                <p>Top Exchange Food Corporation was established with a small warehouse in Metro Manila, serving local restaurants and food businesses.</p>
                            </div>
                        </div>
                        <div class="timeline-item right" data-aos="fade-left">
                            <div class="timeline-date">2005</div>
                            <div class="timeline-content">
                                <h4>First Expansion</h4>
                                <p>Opened regional distribution centers in Luzon, Visayas, and Mindanao to better serve clients nationwide.</p>
                            </div>
                        </div>
                        <div class="timeline-item left" data-aos="fade-right">
                            <div class="timeline-date">2012</div>
                            <div class="timeline-content">
                                <h4>International Partnerships</h4>
                                <p>Established key partnerships with international suppliers to bring global food products to the Philippine market.</p>
                            </div>
                        </div>
                        <div class="timeline-item right" data-aos="fade-left">
                            <div class="timeline-date">2020</div>
                            <div class="timeline-content">
                                <h4>Digital Transformation</h4>
                                <p>Launched e-commerce platform to serve customers more efficiently during the pandemic.</p>
                            </div>
                        </div>
                        <div class="timeline-item left" data-aos="fade-right">
                            <div class="timeline-date">Present</div>
                            <div class="timeline-content">
                                <h4>Market Leadership</h4>
                                <p>Recognized as one of the leading food service providers in the country with over 5,000 satisfied clients.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leadership Section -->
        <div class="row mt-5 pt-4">
            <div class="col-md-12">
                <div class="team-section">
                    <h2 class="text-center mb-4" data-aos="fade-up"><i class="fas fa-users"></i>Meet Our Leadership Team</h2>
                    <div class="row">
                        <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                            <div class="card h-100">
                                <img src="/LandingPage/images/team_ceo.jpg" class="card-img-top" alt="CEO" style="height: 250px; object-fit: cover;">
                                <div class="card-body text-center"> <!-- Added text-center -->
                                    <h5 class="card-title">Juan Dela Cruz</h5>
                                    <p class="card-subtitle mb-2 text-muted">Founder & CEO</p>
                                    <p class="card-text">With over 25 years in the food industry, Juan established TEFC with a vision to transform food distribution in the Philippines.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                            <div class="card h-100">
                                <img src="/LandingPage/images/team_cfo.jpg" class="card-img-top" alt="CFO" style="height: 250px; object-fit: cover;">
                                <div class="card-body text-center"> <!-- Added text-center -->
                                    <h5 class="card-title">Maria Santos</h5>
                                    <p class="card-subtitle mb-2 text-muted">Chief Financial Officer</p>
                                    <p class="card-text">Maria brings financial expertise that has guided TEFC's sustainable growth and expansion.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="400">
                            <div class="card h-100">
                                <img src="/LandingPage/images/team_coo.jpg" class="card-img-top" alt="COO" style="height: 250px; object-fit: cover;">
                                <div class="card-body text-center"> <!-- Added text-center -->
                                    <h5 class="card-title">Roberto Garcia</h5>
                                    <p class="card-subtitle mb-2 text-muted">Chief Operations Officer</p>
                                    <p class="card-text">Roberto oversees our nationwide logistics network ensuring timely deliveries to all clients.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Achievements Section -->
        <div class="row mt-5 pt-4">
            <div class="col-md-12">
                <div class="achievements-section">
                    <h2 class="text-center mb-4" data-aos="fade-up"><i class="fas fa-trophy"></i>Our Achievements</h2>
                    <div class="row text-center">
                        <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="200">
                            <div class="achievement-box">
                                <div class="achievement-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <h3>25+</h3>
                                <p>Warehouses Nationwide</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="300">
                            <div class="achievement-box">
                                <div class="achievement-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3>5,000+</h3>
                                <p>Satisfied Clients</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="400">
                            <div class="achievement-box">
                                <div class="achievement-icon">
                                    <i class="fas fa-globe"></i>
                                </div>
                                <h3>50+</h3>
                                <p>International Suppliers</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="500">
                            <div class="achievement-box">
                                <div class="achievement-icon">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <h3>15+</h3>
                                <p>Industry Awards</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Testimonials Section -->
        <div class="row mt-5 pt-4">
            <div class="col-md-12">
                <div class="testimonials-section">
                    <h2 class="text-center mb-4" data-aos="fade-up"><i class="fas fa-quote-left"></i>What Our Clients Say</h2>
                    <div class="row">
                        <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="testimonial-quote">
                                        <i class="fas fa-quote-left"></i>
                                        <p class="card-text mt-3">Top Exchange Food Corp has been our trusted supplier for over 10 years. Their consistent quality and reliable delivery make them an invaluable partner.</p>
                                    </div>
                                    <div class="testimonial-author mt-3 text-right"> <!-- Added text-right -->
                                        <h6 class="mb-0">- Manuel Reyes</h6>
                                        <small class="text-muted">Owner, Reyes Restaurant Chain</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="testimonial-quote">
                                        <i class="fas fa-quote-left"></i>
                                        <p class="card-text mt-3">Their customer service is exceptional. Whenever we have special requests, they go above and beyond to accommodate our needs.</p>
                                    </div>
                                    <div class="testimonial-author mt-3 text-right"> <!-- Added text-right -->
                                        <h6 class="mb-0">- Sofia Lim</h6>
                                        <small class="text-muted">Purchasing Manager, Grand Hotel</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="400">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="testimonial-quote">
                                        <i class="fas fa-quote-left"></i>
                                        <p class="card-text mt-3">The variety of international products they offer has allowed us to expand our menu and attract more customers. Highly recommended!</p>
                                    </div>
                                    <div class="testimonial-author mt-3 text-right"> <!-- Added text-right -->
                                        <h6 class="mb-0">- Carlos Tan</h6>
                                        <small class="text-muted">Executive Chef, Fusion Bistro</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- about section end -->

<?php
// Include the footer
require_once 'footer.php';
?>