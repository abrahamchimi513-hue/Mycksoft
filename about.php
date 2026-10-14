<?php
// about.php - Page À propos
// CORRECTION : Chemin corrigé pour inclure config.php
// require_once dirname(__DIR__) . '/../config.php';

// $page_title = "À propos - " . SITE_NAME;

// Récupérer les catégories pour le header
// $header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
// $header_show_products_link = true;

// CORRECTION : Chemin corrigé pour inclure header.php
// include dirname(__DIR__) . '/includes/header.php';
?>

<style>
:root {
    --primary-color: #2d3d48;
    --secondary-color: #3f37c9;
    --success-color: #10b981;
    --danger-color: #ce1126;
    --warning-color: #f59e0b;
    --info-color: #4895ef;
    --dark-color: #1e293b;
    --light-color: #f8fafc;
    --gradient: linear-gradient(135deg, #2d3d48 0%, #1e2c34 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: white;
    color: var(--dark-color);
    line-height: 1.6;
}

/* Hero Section */
.about-hero {
    background: var(--gradient);
    padding: 6rem 2rem;
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.about-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 30s linear infinite;
}

.about-hero::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 100px;
    background: linear-gradient(to top, rgba(255,255,255,0.1), transparent);
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.about-hero h1 {
    font-size: 4rem;
    margin-bottom: 1rem;
    position: relative;
    animation: fadeInUp 1s ease;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.about-hero p {
    font-size: 1.3rem;
    max-width: 800px;
    margin: 0 auto;
    opacity: 0.9;
    position: relative;
    animation: fadeInUp 1s ease 0.2s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Container principal */
.about-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 4rem 2rem;
}

/* Section Histoire */
.history-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
    margin-bottom: 6rem;
}

.history-content h2 {
    font-size: 2.5rem;
    color: var(--dark-color);
    margin-bottom: 1.5rem;
    position: relative;
}

.history-content h2::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 0;
    width: 80px;
    height: 4px;
    background: var(--gradient);
    border-radius: 2px;
}

.history-content p {
    font-size: 1.1rem;
    color: #4b5563;
    margin-bottom: 1.5rem;
    line-height: 1.8;
}

.history-image {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px -10px rgba(0,0,0,0.2);
    transition: transform 0.3s;
}

.history-image:hover {
    transform: scale(1.02);
}

.history-image img {
    width: 100%;
    height: auto;
    display: block;
}

/* Statistiques */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
    margin: 4rem 0;
    padding: 3rem;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 30px;
}

.stat-item {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.stat-item:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 35px -5px rgba(0,0,0,0.2);
}

.stat-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: var(--gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
}

.stat-number {
    font-size: 3rem;
    font-weight: 800;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
    line-height: 1;
}

.stat-label {
    color: #6b7280;
    font-size: 1rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Section Mission/Vision */
.mission-vision {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
    margin-bottom: 6rem;
}

.mission-card, .vision-card {
    padding: 3rem;
    border-radius: 30px;
    transition: all 0.3s;
}

.mission-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.vision-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.mission-card:hover, .vision-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 30px 40px -10px rgba(0,0,0,0.3);
}

.mission-card h3, .vision-card h3 {
    font-size: 2rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 15px;
}

.mission-card h3 i, .vision-card h3 i {
    font-size: 2.5rem;
    opacity: 0.9;
}

.mission-card p, .vision-card p {
    font-size: 1.1rem;
    line-height: 1.8;
    opacity: 0.95;
}

/* Équipe */
.team-section {
    margin-bottom: 6rem;
}

.section-title {
    text-align: center;
    font-size: 2.5rem;
    color: var(--dark-color);
    margin-bottom: 1rem;
}

.section-subtitle {
    text-align: center;
    font-size: 1.1rem;
    color: #6b7280;
    max-width: 700px;
    margin: 0 auto 4rem;
}

.team-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.team-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.team-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 35px -8px rgba(0,0,0,0.2);
}

.team-image {
    width: 100%;
    height: 300px;
    overflow: hidden;
    position: relative;
}

.team-image::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 100px;
    background: linear-gradient(to top, white, transparent);
}

.team-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.team-card:hover .team-image img {
    transform: scale(1.1);
}

.team-info {
    padding: 1.5rem;
    text-align: center;
    position: relative;
    background: white;
}

.team-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 0.5rem;
}

.team-role {
    color: var(--primary-color);
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.team-social {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.team-social a {
    width: 35px;
    height: 35px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    text-decoration: none;
    transition: all 0.3s;
}

.team-social a:hover {
    background: var(--gradient);
    color: white;
    transform: translateY(-3px);
}

/* Valeurs */
.values-section {
    margin-bottom: 6rem;
    padding: 4rem;
    background: #f8fafc;
    border-radius: 40px;
}

.values-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 3rem;
    margin-top: 3rem;
}

.value-card {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 20px;
    transition: all 0.3s;
}

.value-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 30px -5px rgba(0,0,0,0.15);
}

.value-icon {
    width: 90px;
    height: 90px;
    margin: 0 auto 1.5rem;
    background: var(--gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 36px;
}

.value-card h3 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: var(--dark-color);
}

.value-card p {
    color: #6b7280;
    line-height: 1.6;
}

/* Témoignages */
.testimonials-section {
    margin-bottom: 6rem;
    padding: 4rem 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 40px;
    color: white;
}

.testimonials-section .section-title,
.testimonials-section .section-subtitle {
    color: white;
}

.testimonials-section .section-subtitle {
    opacity: 0.9;
}

.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    margin-top: 3rem;
    padding: 0 2rem;
}

.testimonial-card {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    padding: 2rem;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s;
}

.testimonial-card:hover {
    transform: translateY(-10px);
    background: rgba(255,255,255,0.15);
}

.testimonial-content {
    font-size: 1.1rem;
    line-height: 1.8;
    margin-bottom: 1.5rem;
    font-style: italic;
}

.testimonial-content i {
    color: rgba(255,255,255,0.5);
    margin-right: 5px;
}

.testimonial-author {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.testimonial-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid white;
}

.testimonial-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.testimonial-info h4 {
    font-size: 1.1rem;
    margin-bottom: 0.2rem;
}

.testimonial-info p {
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Partenaires */
.partners-section {
    margin-bottom: 6rem;
    text-align: center;
}

.partners-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 2rem;
    margin-top: 3rem;
}

.partner-logo {
    padding: 2rem;
    background: #f8fafc;
    border-radius: 20px;
    transition: all 0.3s;
}

.partner-logo:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 25px -5px rgba(0,0,0,0.1);
}

.partner-logo img {
    max-width: 100%;
    height: auto;
    filter: grayscale(100%);
    opacity: 0.7;
    transition: all 0.3s;
}

.partner-logo:hover img {
    filter: grayscale(0%);
    opacity: 1;
}

/* Contact CTA */
.contact-cta {
    background: white;
    padding: 4rem;
    border-radius: 40px;
    text-align: center;
    box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
    border: 1px solid #e2e8f0;
}

.contact-cta h2 {
    font-size: 2.5rem;
    color: var(--dark-color);
    margin-bottom: 1rem;
}

.contact-cta p {
    font-size: 1.1rem;
    color: #6b7280;
    margin-bottom: 2rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.contact-cta .btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 1rem 2.5rem;
    font-size: 1.1rem;
    background: var(--gradient);
    color: white;
    border-radius: 50px;
    text-decoration: none;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.contact-cta .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 30px -5px rgba(45,61,72,0.4);
}

/* Timeline */
.timeline {
    position: relative;
    padding: 2rem 0;
    margin: 4rem 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 100%;
    background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
    border-radius: 2px;
}

.timeline-item {
    position: relative;
    margin-bottom: 3rem;
}

.timeline-item:nth-child(odd) {
    padding-right: calc(50% + 2rem);
}

.timeline-item:nth-child(even) {
    padding-left: calc(50% + 2rem);
}

.timeline-content {
    background: white;
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    position: relative;
    border: 1px solid #e2e8f0;
}

.timeline-content::before {
    content: '';
    position: absolute;
    top: 20px;
    width: 30px;
    height: 30px;
    background: white;
    transform: rotate(45deg);
    border: 1px solid #e2e8f0;
}

.timeline-item:nth-child(odd) .timeline-content::before {
    right: -16px;
    border-left: none;
    border-bottom: none;
}

.timeline-item:nth-child(even) .timeline-content::before {
    left: -16px;
    border-right: none;
    border-top: none;
}

.timeline-year {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: var(--gradient);
    color: white;
    border-radius: 30px;
    font-weight: 600;
    margin-bottom: 1rem;
}

.timeline-title {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
    color: var(--dark-color);
}

.timeline-desc {
    color: #6b7280;
    line-height: 1.6;
}

.timeline-dot {
    position: absolute;
    top: 20px;
    width: 20px;
    height: 20px;
    background: white;
    border: 4px solid var(--primary-color);
    border-radius: 50%;
    z-index: 2;
}

.timeline-item:nth-child(odd) .timeline-dot {
    right: -10px;
}

.timeline-item:nth-child(even) .timeline-dot {
    left: -10px;
}

/* Responsive */
@media (max-width: 1024px) {
    .about-hero h1 {
        font-size: 3rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .team-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .values-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .testimonials-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .partners-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .about-hero h1 {
        font-size: 2.5rem;
    }
    
    .about-hero p {
        font-size: 1.1rem;
    }
    
    .history-section {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .mission-vision {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        padding: 2rem;
    }
    
    .team-grid {
        grid-template-columns: 1fr;
    }
    
    .values-grid {
        grid-template-columns: 1fr;
    }
    
    .testimonials-grid {
        grid-template-columns: 1fr;
    }
    
    .partners-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .timeline::before {
        left: 30px;
    }
    
    .timeline-item:nth-child(odd),
    .timeline-item:nth-child(even) {
        padding: 0 0 0 80px;
    }
    
    .timeline-content::before {
        display: none;
    }
    
    .timeline-dot {
        left: 20px !important;
        right: auto !important;
    }
}

@media (max-width: 480px) {
    .about-hero h1 {
        font-size: 2rem;
    }
    
    .about-hero {
        padding: 4rem 1rem;
    }
    
    .partners-grid {
        grid-template-columns: 1fr;
    }
    
    .contact-cta {
        padding: 2rem;
    }
    
    .contact-cta h2 {
        font-size: 2rem;
    }
}
</style>

<!-- Hero Section -->
<section class="about-hero">
    <h1>À propos de Mycksoft</h1>
    <p>Votre partenaire de confiance pour l'informatique au Cameroun depuis 2023</p>
</section>

<div class="about-container">
    <!-- Histoire -->
    <div class="history-section">
        <div class="history-content">
            <h2>Notre Histoire</h2>
            <p>Fondée en 2023, par M. MOUKAM KAMCOUE Yann Cédric </p>
            <p>Notre mission est simple : rendre la technologie accessible à tous, avec des produits de qualité et un service client exceptionnel. En 5 ans, nous avons équipé plus de 10 000 clients, des particuliers aux grandes entreprises.</p>
            <p>Aujourd'hui, Mycksoft c'est une équipe de 3 passionnés, un showroom à Douala, et une plateforme e-commerce qui livre dans tout le Cameroun.</p>
        </div>
        <div class="history-image">
            <img src="../assets/images/about/history.jpg" alt="Notre histoire" onerror="this.src='https://via.placeholder.com/600x400?text=Mycksoft+History'">
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-number">3+</div>
            <div class="stat-label">Années d'expérience</div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number">3</div>
            <div class="stat-label">Employés passionnés</div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-number">10k+</div>
            <div class="stat-label">Clients satisfaits</div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon">
                <i class="fas fa-truck"></i>
            </div>
            <div class="stat-number">48h</div>
            <div class="stat-label">Livraison express</div>
        </div>
    </div>

    <!-- Mission & Vision -->
    <div class="mission-vision">
        <div class="mission-card">
            <h3><i class="fas fa-bullseye"></i> Notre Mission</h3>
            <p>Démocratiser l'accès aux technologies de pointe au Cameroun en offrant des produits de qualité à des prix compétitifs, accompagnés d'un service client irréprochable et de conseils d'experts.</p>
        </div>
        
        <div class="vision-card">
            <h3><i class="fas fa-eye"></i> Notre Vision</h3>
            <p>Devenir le leader incontesté de la distribution de matériel informatique en Afrique centrale, en créant un écosystème technologique qui forme et accompagne la prochaine génération d'innovateurs.</p>
        </div>
    </div>

    <!-- Timeline -->
    <div class="section-title">Notre Parcours</div>
    <div class="section-subtitle">Les étapes clés qui ont marqué notre évolution</div>
    
    <div class="timeline">
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-content">
                <span class="timeline-year">2020</span>
                <h3 class="timeline-title">Création de Mycksoft</h3>
                <p class="timeline-desc">Fondation de l'entreprise par M MOUKAM en 2023.</p>
            </div>
        </div>
        
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-content">
               
            </div>
        </div>
        
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-content">
                <span class="timeline-year">2022</span>
                <h3 class="timeline-title">Lancement du e-commerce</h3>
                <p class="timeline-desc">Notre site web est lancé, permettant la vente en ligne dans tout le Cameroun.</p>
            </div>
        </div>
        
        

    <!-- Équipe -->
    <div class="team-section">
        <h2 class="section-title">Notre Équipe</h2>
        <p class="section-subtitle">Des passionnés de technologie à votre service</p>
        
        <div class="team-grid">
            <div class="team-card">
                <div class="team-image">
                    <img src="../assets/images/team/ceo.jpg" alt="Jean Kamga" onerror="this.src='https://via.placeholder.com/300x400?text=Jean+Kamga'">
                </div>
                <div class="team-info">
                    <h3 class="team-name"> MOUKAM KAMGOUE Yann Cédric</h3>
                    <div class="team-role">Fondateur</div>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="team-card">
                <div class="team-image">
                    <img src="../assets/images/team/cto.jpg" alt="Paul Ndi" onerror="this.src='https://via.placeholder.com/300x400?text=Paul+Ndi'">
                </div>
                <div class="team-info">
                    <h3 class="team-name">Paul Ndi</h3>
                    <div class="team-role">CTO</div>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="team-card">
                <div class="team-image">
                    <img src="../assets/images/team/marketing.jpg" alt="Marie Etoa" onerror="this.src='https://via.placeholder.com/300x400?text=Marie+Etoa'">
                </div>
                <div class="team-info">
                    <h3 class="team-name">Marie Etoa</h3>
                    <div class="team-role">Directrice Marketing</div>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="team-card">
                <div class="team-image">
                    <img src="../assets/images/team/sales.jpg" alt="Pierre Mbarga" onerror="this.src='https://via.placeholder.com/300x400?text=Pierre+Mbarga'">
                </div>
                <div class="team-info">
                    <h3 class="team-name">Pierre Mbarga</h3>
                    <div class="team-role">Directeur Commercial</div>
                    <div class="team-social">
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Valeurs -->
    <div class="values-section">
        <h2 class="section-title">Nos Valeurs</h2>
        <p class="section-subtitle">Les principes qui guident notre action au quotidien</p>
        
        <div class="values-grid">
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3>Intégrité</h3>
                <p>Nous agissons avec honnêteté et transparence dans toutes nos relations.</p>
            </div>
            
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3>Excellence</h3>
                <p>Nous visons l'excellence dans tout ce que nous entreprenons.</p>
            </div>
            
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h3>Passion</h3>
                <p>Notre passion pour la technologie nous pousse à nous dépasser.</p>
            </div>
            
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Esprit d'équipe</h3>
                <p>Nous croyons en la force du collectif et de la collaboration.</p>
            </div>
            
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h3>Innovation</h3>
                <p>Nous encourageons la créativité et l'innovation à tous les niveaux.</p>
            </div>
            
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h3>Responsabilité</h3>
                <p>Nous sommes engagés dans une démarche éco-responsable.</p>
            </div>
        </div>
    </div>

    <!-- Témoignages -->
    <div class="testimonials-section">
        <h2 class="section-title">Ce que disent nos clients</h2>
        <p class="section-subtitle">La satisfaction de nos clients est notre plus belle récompense</p>
        
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <i class="fas fa-quote-left"></i>
                    Un service client exceptionnel ! J'ai été livré en 24h et le produit est conforme à mes attentes. Je recommande vivement Mycksoft.
                </div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">
                        <img src="../assets/images/testimonials/client1.jpg" alt="Client" onerror="this.src='https://via.placeholder.com/60x60?text=JD'">
                    </div>
                    <div class="testimonial-info">
                        <h4>Jean Dupont</h4>
                        <p>Entrepreneur</p>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <i class="fas fa-quote-left"></i>
                    Meilleure expérience d'achat en ligne au Cameroun. Le site est intuitif, les prix compétitifs et la livraison rapide.
                </div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">
                        <img src="../assets/images/testimonials/client2.jpg" alt="Client" onerror="this.src='https://via.placeholder.com/60x60?text=MB'">
                    </div>
                    <div class="testimonial-info">
                        <h4>Marie Bikoï</h4>
                        <p>Architecte</p>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <i class="fas fa-quote-left"></i>
                    Je suis client depuis 3 ans et je n'ai jamais été déçu. Le service après-vente est réactif et professionnel.
                </div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">
                        <img src="../assets/images/testimonials/client3.jpg" alt="Client" onerror="this.src='https://via.placeholder.com/60x60?text=PT'">
                    </div>
                    <div class="testimonial-info">
                        <h4>Pierre Tchamba</h4>
                        <p>Ingénieur</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Partenaires -->
    <div class="partners-section">
        <h2 class="section-title">Nos Partenaires</h2>
        <p class="section-subtitle">Ils nous font confiance</p>
        
        <div class="partners-grid">
            <div class="partner-logo">
                <img src="../assets/images/partners/dell.png" alt="Dell" onerror="this.src='https://via.placeholder.com/150x80?text=Dell'">
            </div>
            <div class="partner-logo">
                <img src="../assets/images/partners/hp.png" alt="HP" onerror="this.src='https://via.placeholder.com/150x80?text=HP'">
            </div>
            <div class="partner-logo">
                <img src="../assets/images/partners/lenovo.png" alt="Lenovo" onerror="this.src='https://via.placeholder.com/150x80?text=Lenovo'">
            </div>
            <div class="partner-logo">
                <img src="../assets/images/partners/apple.png" alt="Apple" onerror="this.src='https://via.placeholder.com/150x80?text=Apple'">
            </div>
            <div class="partner-logo">
                <img src="../assets/images/partners/microsoft.png" alt="Microsoft" onerror="this.src='https://via.placeholder.com/150x80?text=Microsoft'">
            </div>
        </div>
    </div>

    <!-- Contact CTA -->
    <div class="contact-cta">
        <h2>Prêt à nous rejoindre ?</h2>
        <p>Que vous soyez un particulier ou une entreprise, notre équipe est là pour vous conseiller et vous accompagner dans vos projets.</p>
        <a href="contact.php" class="btn">
            <i class="fas fa-envelope"></i>
            Contactez-nous
        </a>
        <a href="index.php" class="btn" style="margin-left: 1rem; background: white; color: var(--primary-color);">
            <i class="fas fa-store"></i>
            Découvrir nos produits
        </a>
    </div>
</div>

<script>
// Animation au scroll
document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.stat-item, .value-card, .team-card, .testimonial-card, .partner-logo').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s ease';
        observer.observe(el);
    });
});

// Compteur animé pour les statistiques
function animateNumber(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        element.textContent = Math.floor(progress * (end - start) + start) + (end > 1000 ? 'k+' : '');
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Démarrer l'animation quand les stats sont visibles
const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const numbers = entry.target.querySelectorAll('.stat-number');
            numbers.forEach(num => {
                const value = parseInt(num.textContent);
                if (value > 1000) {
                    animateNumber(num, 0, value/1000, 2000);
                } else {
                    animateNumber(num, 0, value, 2000);
                }
            });
            statsObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });

document.querySelectorAll('.stats-grid').forEach(grid => {
    statsObserver.observe(grid);
});
</script>

<?php 
// CORRECTION : Chemin corrigé pour inclure footer.php
// include dirname(__DIR__) . '/includes/footer.php'; 
?>