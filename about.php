<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="about-section">
                <h1>About Birzeit Flat Rent</h1>
                
                <div class="about-content">
                    <div class="about-agency">
                        <h2>The Agency</h2>
                        <p>Birzeit Flat Rent was established in 2010 as a premier flat rental agency serving the Birzeit area. Our mission is to connect property owners with quality tenants, providing a seamless rental experience for both parties.</p>
                        
                        <p>Over the years, we have grown to become the leading rental agency in the region, with a reputation for excellent service and a wide selection of properties. Our team of experienced professionals is dedicated to helping customers find their perfect home and assisting owners in managing their rental properties.</p>
                        
                        <h3>Awards and Recognition</h3>
                        <ul>
                            <li>Best Rental Agency in Palestine - 2018, 2020, 2022</li>
                            <li>Excellence in Customer Service - Palestinian Chamber of Commerce, 2019</li>
                            <li>Top-Rated Real Estate Service - Birzeit Business Association, 2021</li>
                        </ul>
                        
                        <h3>Management Hierarchy</h3>
                        <div class="management-hierarchy">
                            <div class="hierarchy-level">
                                <div class="hierarchy-position">
                                    <strong>CEO</strong>
                                    <p>Ahmad Nasser</p>
                                </div>
                            </div>
                            
                            <div class="hierarchy-level">
                                <div class="hierarchy-position">
                                    <strong>Operations Director</strong>
                                    <p>Layla Mansour</p>
                                </div>
                                
                                <div class="hierarchy-position">
                                    <strong>Finance Director</strong>
                                    <p>Omar Khalil</p>
                                </div>
                                
                                <div class="hierarchy-position">
                                    <strong>Marketing Director</strong>
                                    <p>Rania Haddad</p>
                                </div>
                            </div>
                            
                            <div class="hierarchy-level">
                                <div class="hierarchy-position">
                                    <strong>Property Managers</strong>
                                    <p>Team of 5 professionals</p>
                                </div>
                                
                                <div class="hierarchy-position">
                                    <strong>Customer Service</strong>
                                    <p>Team of 8 representatives</p>
                                </div>
                                
                                <div class="hierarchy-position">
                                    <strong>Administrative Staff</strong>
                                    <p>Team of 4 administrators</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="about-city">
                        <h2>The City of Birzeit</h2>
                        
                        <figure class="city-image">
                            <img src="/placeholder.svg?height=300&width=500" alt="Birzeit City">
                            <figcaption>Beautiful view of Birzeit</figcaption>
                        </figure>
                        
                        <p>Birzeit is a Palestinian town located in the central West Bank, about 10 kilometers north of Ramallah and 25 kilometers north of Jerusalem. The town is home to the prestigious Birzeit University, one of the oldest and most prominent Palestinian universities.</p>
                        
                        <h3>Key Facts</h3>
                        <ul>
                            <li><strong>Population:</strong> Approximately 5,000 residents</li>
                            <li><strong>Location:</strong> Central West Bank, Palestine</li>
                            <li><strong>Weather:</strong> Mediterranean climate with hot, dry summers and mild, rainy winters</li>
                            <li><strong>Famous Places:</strong> Birzeit University, Old Town, Birzeit Brewery</li>
                        </ul>
                        
                        <h3>Famous Products</h3>
                        <p>Birzeit is known for its olive oil production, traditional Palestinian embroidery, and local crafts. The town also hosts several cultural festivals throughout the year, celebrating Palestinian heritage and arts.</p>
                        
                        <h3>Famous People</h3>
                        <p>Many notable academics, politicians, and artists have emerged from Birzeit, particularly through its university. The town has produced numerous leaders in Palestinian society, including educators, engineers, and cultural figures.</p>
                        
                        <h3>Learn More About Birzeit</h3>
                        <ul class="external-links">
                            <li><a href="https://en.wikipedia.org/wiki/Birzeit" target="_blank">Birzeit on Wikipedia</a></li>
                            <li><a href="https://www.birzeit.edu/en" target="_blank">Birzeit University</a></li>
                            <li><a href="https://visitpalestine.ps/where-to-go/ramallah-area/birzeit/" target="_blank">Visit Palestine - Birzeit</a></li>
                        </ul>
                    </div>
                    
                    <div class="business-activities">
                        <h2>Main Business Activities</h2>
                        
                        <p>At Birzeit Flat Rent, we offer a comprehensive range of services to meet the needs of both property owners and tenants:</p>
                        
                        <ul class="activities-list">
                            <li>
                                <h3>Flat Rental Services</h3>
                                <p>We connect property owners with qualified tenants, handling all aspects of the rental process from listing to contract signing.</p>
                            </li>
                            
                            <li>
                                <h3>Property Management</h3>
                                <p>For owners who prefer a hands-off approach, we offer complete property management services, including maintenance, rent collection, and tenant relations.</p>
                            </li>
                            
                            <li>
                                <h3>Student Housing Solutions</h3>
                                <p>Specializing in accommodations for Birzeit University students, we help find suitable housing options near campus.</p>
                            </li>
                            
                            <li>
                                <h3>Short-Term Rentals</h3>
                                <p>We offer furnished apartments for short-term stays, catering to visitors, researchers, and temporary residents.</p>
                            </li>
                            
                            <li>
                                <h3>Rental Consultation</h3>
                                <p>Our experts provide advice on rental pricing, property improvements, and market trends to maximize rental income.</p>
                            </li>
                            
                            <li>
                                <h3>Online Rental Platform</h3>
                                <p>Our website provides a user-friendly interface for browsing available properties, scheduling viewings, and managing rental agreements.</p>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
