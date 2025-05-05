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
    --section-padding-y: 60px; /* Vertical padding for sections */
    --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
    --card-hover-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    --primary-color: #e46c1d;
    --light-bg: #f8f9fa;
    --border-color: #dee2e6;
}

body {
    overflow-x: hidden; /* Prevent horizontal scroll */
}

/* REMOVED padding from the main wrapper */
.about_section {
    /* No top/bottom padding here */
    background-color: #fff; /* Ensure intro background is white */
}

/* ADD padding specifically to the first container (intro) */
.about_section > .container:first-of-type {
     padding-top: var(--section-padding-y);
     padding-bottom: var(--section-padding-y);
}


/* Keep consistent padding for subsequent sections */
.history-section,
.team-section,
.achievements-section,
.testimonials-section {
    padding-top: var(--section-padding-y);
    padding-bottom: var(--section-padding-y);
}

.about_section h1, .about_section h2, .about_section h3 {
    color: #333;
    margin-bottom: 20px;
    font-weight: 600;
}
.about_section h2 {
    font-size: 2.2rem;
    margin-bottom: 40px; /* More space below section titles */
}
.about_section h2 i {
     margin-right: 10px;
     color: var(--primary-color); /* Color icons */
}
.about_section h3 {
    font-size: 1.5rem;
}
.about_section p {
    color: #555;
    line-height: 1.7;
}

/* --- Intro Section --- */
.about_section .row.intro-row { /* Added class for specificity */
    align-items: center;
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
.about_taital {
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
    border: 1px solid var(--border-color); /* Subtle border */
    box-shadow: none; /* Remove base shadow, rely on hover */
    transition: box-shadow 0.3s ease, transform 0.3s ease;
    border-radius: 8px;
    height: 100%; /* Ensure cards in a row have same height */
}
.card:hover {
    box-shadow: var(--card-hover-shadow);
    transform: translateY(-3px);
}
.card .card-title {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 15px;
    font-size: 1.3rem; /* Slightly smaller */
}
.card .card-title i {
    margin-right: 8px;
    width: 20px;
    text-align: center;
}
.card .card-img-top {
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}
/* Specific styling for testimonial cards */
.testimonials-section .card {
     background-color: var(--light-bg); /* Different background */
     border-color: transparent;
}
.testimonial-quote i {
    color: var(--primary-color);
    opacity: 0.5;
    font-size: 1.8rem; /* Larger quote icon */
    float: left;
    margin-right: 10px;
    margin-bottom: 5px;
}
.testimonial-quote p {
    overflow: hidden; /* Clear the float */
    padding-top: 5px;
}
.testimonial-author {
    border-top: 1px solid var(--border-color);
    padding-top: 10px;
    margin-top: 15px !important; /* Ensure space above */
}
.testimonial-author h6 {
    color: #333;
    font-weight: 600;
    margin-bottom: 0;
}

/* --- Core Values --- */
.core-values {
    margin-top: 30px !important; /* Ensure space */
}
.core-values h3 i {
    margin-right: 8px;
    color: var(--primary-color);
}
.core-values .list-group-item {
    border: none;
    padding: 8px 0; /* Adjust padding */
    color: #555;
    background-color: transparent;
    border-bottom: 1px dashed var(--border-color); /* Subtle separator */
}
.core-values .list-group-item:last-child {
    border-bottom: none;
}
.core-values .list-group-item strong {
    color: #333;
    display: inline-block;
    min-width: 110px;
}

/* --- History Timeline --- */
.history-section {
    background-color: var(--light-bg); /* Add background */
}
.timeline {
    position: relative;
    max-width: 900px;
    margin: 0 auto;
}
.timeline::after { /* The central line */
    content: '';
    position: absolute;
    width: 3px; /* Thinner line */
    background-color: #ccc; /* Lighter line */
    top: 0;
    bottom: 0;
    left: 50%;
    margin-left: -1.5px;
    z-index: 1; /* Lower z-index */
}
.timeline-item {
    padding: 10px 40px;
    position: relative;
    background-color: inherit;
    width: 50%;
    z-index: 2; /* Ensure items are above line */
}
.timeline-item::after { /* The circles on the line */
    content: '';
    position: absolute;
    width: 15px; /* Slightly smaller */
    height: 15px;
    right: -8px; /* Adjust based on line thickness */
    background-color: white;
    border: 3px solid var(--primary-color);
    top: 25px; /* Align with arrow */
    border-radius: 50%;
    z-index: 3; /* ** Higher z-index than line ** */
}
.timeline-item.left {
    left: 0;
}
.timeline-item.right {
    left: 50%;
}
/* Arrow pointers - position relative to timeline-item */
.timeline-item::before {
    content: " ";
    height: 0;
    position: absolute;
    top: 28px; /* Align vertically with circle center */
    width: 0;
    z-index: 2; /* Above line, below circle */
    border: medium solid white; /* Base for border */
}
.timeline-item.left::before {
    right: 32px; /* Point towards the line */
    border-width: 8px 0 8px 8px;
    border-color: transparent transparent transparent #ffffff; /* Match content bg (white) */
}
.timeline-item.right::before {
    left: 32px; /* Point towards the line */
    border-width: 8px 8px 8px 0;
    border-color: transparent #ffffff transparent transparent; /* Match content bg (white) */
}
/* Adjust right item circle position */
.timeline-item.right::after {
    left: -8px; /* Adjust based on line thickness */
}
.timeline-content {
    padding: 20px 25px;
    background-color: #ffffff; /* Use white for contrast */
    position: relative;
    border-radius: 6px;
    box-shadow: var(--card-shadow);
    z-index: 2; /* Above line */
}
.timeline-content h4 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 8px;
}
.timeline-date {
    font-weight: bold;
    color: #555; /* Less prominent color */
    font-size: 0.9rem;
    margin-bottom: 10px; /* Space below date */
    display: block; /* Make it block for spacing */
}
/* Remove absolute positioning for date, place inside content */
.timeline-item.left .timeline-date,
.timeline-item.right .timeline-date {
   position: static; /* No longer absolute */
   width: auto;
   text-align: left;
   margin-bottom: 8px;
}


/* --- Achievements Section --- */
.achievements-section {
     /* background-color: var(--light-bg); Kept */
     border-top: 1px solid var(--border-color); /* Separator */
     border-bottom: 1px solid var(--border-color); /* Separator */

}
.achievement-box {
    padding: 25px;
    background-color: transparent; /* Make transparent */
    border-radius: 8px;
    /* box-shadow: var(--card-shadow); Removed */
    transition: transform 0.3s ease, border-color 0.3s ease, background-color 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    border: 1px solid transparent; /* Add border for hover effect */
}
.achievement-box:hover {
     transform: translateY(-5px);
     /* box-shadow: var(--card-hover-shadow); Removed */
     border-color: var(--border-color); /* Show border on hover */
     background-color: #fff; /* White background on hover */

}
.achievement-icon i {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 15px;
    transition: transform 0.3s ease;
}
.achievement-box:hover .achievement-icon i {
    transform: scale(1.1);
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
@media (max-width: 991.98px) { /* Medium devices */
    .about_taital {
        font-size: 2.5rem;
    }
    .about_section h2 {
        font-size: 2rem;
    }
     .about_section h3 {
        font-size: 1.4rem;
    }
    .core-values .list-group-item strong {
        min-width: 90px; /* Adjust alignment */
    }
}

@media (max-width: 767.98px) { /* Small devices */
    .about_section .row.intro-row {
        flex-direction: column;
    }
    .about_section .row.intro-row .col-md-6 {
        width: 100%;
        margin-bottom: 30px;
    }
     .about_section .row.intro-row .col-md-6:last-child {
         margin-bottom: 0;
     }
    .about_taital {
        font-size: 2.2rem;
        text-align: center;
    }
     .about_section h2 {
        font-size: 1.8rem;
        margin-bottom: 30px;
    }
    .about_section h3 {
        font-size: 1.3rem;
    }

    /* Timeline adjustments for mobile */
    .timeline::after {
        left: 20px; /* Move line further left */
    }
    .timeline-item {
        width: 100%;
        padding-left: 55px; /* Make space for line/icon */
        padding-right: 15px; /* Reduce right padding */
    }
    .timeline-item.left, .timeline-item.right {
        left: 0%;
    }
    .timeline-item.left::before, .timeline-item.right::before {
        left: 46px; /* Adjust arrow position */
        border-width: 8px 8px 8px 0;
        border-color: transparent #ffffff transparent transparent; /* Pointing right, white bg */
    }
    .timeline-item.left::after, .timeline-item.right::after {
        left: 13px; /* Position circle on the line */
    }
    .timeline-item.left .timeline-date, .timeline-item.right .timeline-date {
       /* Already static from desktop rules */
    }
    .timeline-content {
        padding: 15px 20px; /* Smaller padding */
    }

    .core-values .col-md-6 {
        width: 100%;
    }
}

</style>

<!-- about section start -->
<!-- Removed layout_padding style from here -->
<div class="about_section" style="background-color: #fff;">
    <!-- This container gets padding from CSS -->
    <div class="container">
        <!-- Added class="intro-row" -->
        <div class="row intro-row">
            <div class="col-md-6" data-aos="fade-right">
                <div class="about_img"><img src="/LandingPage/images/about_image_1.png" class="img-fluid" alt="About Top Exchange Food Corp"></div>
                <div class="about_img mt-4" data-aos="fade-right" data-aos-delay="200"><img src="/LandingPage/images/about_image_2.png" class="img-fluid" alt="Our Facilities"></div>
            </div>
            <div class="col-md-6" data-aos="fade-left">
                <h1 class="about_taital">Top Exchange Food Corporation</h1>
                <p class="about_text"><strong>Top Exchange Food Corporation (TEFC)</strong> is a top-tier broad line food service supply integrator based in the Philippines. The company began in 1998, primarily addressing the market need for high-quality Chinese ingredients. Over the years, we have expanded our portfolio to include a wide range of premium Filipino and international food products.</p>
                <p class="about_text">Our commitment to quality, innovation, and customer satisfaction has made us a trusted partner for thousands of businesses across the nation. We leverage our extensive network and state-of-the-art facilities to ensure timely delivery and product excellence.</p>

                <div class="mission-vision mt-4"> <!-- Reduced top margin -->
                    <div class="row">
                        <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-delay="200"> <!-- Use col-lg for better control -->
                            <div class="card h-100">
                                <div class="card-body">
                                    <h3 class="card-title"><i class="fas fa-bullseye"></i>Our Mission</h3>
                                    <p class="card-text">To be the premier food service provider in the Philippines by delivering exceptional quality products, innovative solutions, and unmatched customer service.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-delay="300"> <!-- Use col-lg for better control -->
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
                    <!-- Removed inner row, let Bootstrap stack columns -->
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
    </div> <!-- End container for intro -->

        <!-- History Section (Wrapped in its own div for background) -->
        <div class="history-section">
             <div class="container">
                 <div class="row">
                    <div class="col-md-12">
                        <h2 class="text-center mb-5" data-aos="fade-up"><i class="fas fa-history"></i>Our History</h2>
                        <div class="timeline">
                            <div class="timeline-item left" data-aos="fade-right">
                                <div class="timeline-content">
                                    <div class="timeline-date">1998</div>
                                    <h4>Foundation</h4>
                                    <p>Top Exchange Food Corporation was established with a small warehouse in Metro Manila, serving local restaurants and food businesses.</p>
                                </div>
                            </div>
                            <div class="timeline-item right" data-aos="fade-left">
                                <div class="timeline-content">
                                     <div class="timeline-date">2005</div>
                                    <h4>First Expansion</h4>
                                    <p>Opened regional distribution centers in Luzon, Visayas, and Mindanao to better serve clients nationwide.</p>
                                </div>
                            </div>
                            <div class="timeline-item left" data-aos="fade-right">
                                <div class="timeline-content">
                                     <div class="timeline-date">2012</div>
                                    <h4>International Partnerships</h4>
                                    <p>Established key partnerships with international suppliers to bring global food products to the Philippine market.</p>
                                </div>
                            </div>
                            <div class="timeline-item right" data-aos="fade-left">
                                <div class="timeline-content">
                                     <div class="timeline-date">2020</div>
                                    <h4>Digital Transformation</h4>
                                    <p>Launched e-commerce platform to serve customers more efficiently during the pandemic.</p>
                                </div>
                            </div>
                            <div class="timeline-item left" data-aos="fade-right">
                                <div class="timeline-content">
                                     <div class="timeline-date">Present</div>
                                    <h4>Market Leadership</h4>
                                    <p>Recognized as one of the leading food service providers in the country with over 5,000 satisfied clients.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- End history-section -->

        <!-- Leadership Section -->
        <div class="team-section">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h2 class="text-center mb-4" data-aos="fade-up"><i class="fas fa-users"></i>Meet Our Leadership Team</h2>
                        <div class="row">
                            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200"> <!-- Adjusted grid -->
                                <div class="card h-100">
                                    <img src="/LandingPage/images/team_ceo.jpg" class="card-img-top" alt="CEO" style="height: 250px; object-fit: cover;">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Juan Dela Cruz</h5>
                                        <p class="card-subtitle mb-2 text-muted">Founder & CEO</p>
                                        <p class="card-text">With over 25 years in the food industry, Juan established TEFC with a vision to transform food distribution in the Philippines.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300"> <!-- Adjusted grid -->
                                <div class="card h-100">
                                    <img src="/LandingPage/images/team_cfo.jpg" class="card-img-top" alt="CFO" style="height: 250px; object-fit: cover;">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Maria Santos</h5>
                                        <p class="card-subtitle mb-2 text-muted">Chief Financial Officer</p>
                                        <p class="card-text">Maria brings financial expertise that has guided TEFC's sustainable growth and expansion.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400"> <!-- Adjusted grid -->
                                <div class="card h-100">
                                    <img src="/LandingPage/images/team_coo.jpg" class="card-img-top" alt="COO" style="height: 250px; object-fit: cover;">
                                    <div class="card-body text-center">
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
        </div> <!-- End team-section -->

        <!-- Achievements Section -->
         <div class="achievements-section">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h2 class="text-center mb-4" data-aos="fade-up"><i class="fas fa-trophy"></i>Our Achievements</h2>
                        <div class="row text-center">
                             <!-- Use col-lg-3 col-md-6 for better stacking -->
                            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                                <div class="achievement-box">
                                    <div class="achievement-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <h3>25+</h3>
                                    <p>Warehouses Nationwide</p>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                                <div class="achievement-box">
                                    <div class="achievement-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <h3>5,000+</h3>
                                    <p>Satisfied Clients</p>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                                <div class="achievement-box">
                                    <div class="achievement-icon">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                    <h3>50+</h3>
                                    <p>International Suppliers</p>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="500">
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
        </div> <!-- End achievements-section -->

        <!-- Testimonials Section -->
        <div class="testimonials-section">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h2 class="text-center mb-4" data-aos="fade-up"><i class="fas fa-quote-left"></i>What Our Clients Say</h2>
                        <div class="row">
                            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="testimonial-quote">
                                            <i class="fas fa-quote-left"></i>
                                            <p class="card-text">Top Exchange Food Corp has been our trusted supplier for over 10 years. Their consistent quality and reliable delivery make them an invaluable partner.</p>
                                        </div>
                                        <div class="testimonial-author mt-3 text-right">
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
                                            <p class="card-text">Their customer service is exceptional. Whenever we have special requests, they go above and beyond to accommodate our needs.</p>
                                        </div>
                                        <div class="testimonial-author mt-3 text-right">
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
                                            <p class="card-text">The variety of international products they offer has allowed us to expand our menu and attract more customers. Highly recommended!</p>
                                        </div>
                                        <div class="testimonial-author mt-3 text-right">
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
        </div> <!-- End testimonials-section -->

</div> <!-- about section end (Original main wrapper) -->

<?php
// Include the footer
require_once 'footer.php';
?>