<?php
// ============================================================
// index.php – Landing Page
// ============================================================
require_once 'includes/auth.php';

// Redirect logged-in users
if (is_logged_in()) {
    $dest = ($_SESSION['user_type'] === 'admin') ? 'admin/dashboard.php' : 'student/dashboard.php';
    header("Location: $dest");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SLSU GPTS – Graduate Profiling and Tracer System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --gold: #C9A84C;
  --gold-lt: #E8C97A;
  --navy: #E8F5E9;
  --navy-mid: #C8E6C9;
  --navy-lt: #A5D6A7;
  --white: #1B5E20;
  --gray: #2E7D32;
  --radius: 12px;
  --radius-lg: 24px;
  --transition: .35s cubic-bezier(.4,0,.2,1);
  --green-active: #2E7D32;
}
html { scroll-behavior: smooth; }
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--navy);
  color: var(--white);
  overflow-x: hidden;
  line-height: 1.6;
  background-image: url('slsubacks.jpg');
  background-size: cover;
  background-position: center center;
  background-attachment: fixed;
  background-repeat: no-repeat;
}
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background: rgba(27, 94, 32, 0.7);
  z-index: 0;
  pointer-events: none;
}
nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.1rem 4rem;
  background: rgba(232, 245, 233, .95);
  backdrop-filter: blur(18px);
  border-bottom: 1px solid rgba(76, 175, 80, .2);
  transition: var(--transition);
}
.nav-brand {
  display: flex; align-items: center; gap: .9rem;
  text-decoration: none;
}
.nav-logo {
  width: 44px; height: 44px; border-radius: 50%;
  background: linear-gradient(135deg, var(--gold), var(--gold-lt));
  display: flex; align-items: center; justify-content: center;
  font-family: 'Playfair Display', serif;
  font-weight: 900; font-size: 1.1rem; color: var(--navy);
  flex-shrink: 0;
}
.nav-title { font-weight: 600; font-size: .95rem; color: var(--white); line-height: 1.2; }
.nav-title span { display: block; font-size: .75rem; color: var(--gold); font-weight: 400; }
.nav-links { display: flex; align-items: center; gap: 2rem; }
.nav-links a {
  color: var(--gray);
  text-decoration: none;
  font-size: .9rem;
  font-weight: 500;
  transition: color var(--transition), border-bottom var(--transition);
  position: relative;
  padding-bottom: 4px;
}
.nav-links a::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 2px;
  background: var(--green-active);
  transition: width var(--transition);
}
.nav-links a:hover { color: var(--white); }
.nav-links a.active {
  color: var(--green-active);
  font-weight: 600;
}
.nav-links a.active::after {
  width: 100%;
}
.nav-links a.active i {
  color: var(--gold);
  margin-right: 4px;
}
.btn-nav {
  padding: .55rem 1.5rem;
  background: transparent;
  border: 1px solid var(--gold);
  color: var(--gold) !important;
  border-radius: 50px;
  font-size: .88rem !important;
  transition: var(--transition) !important;
}
.btn-nav::after { display: none !important; }
.btn-nav:hover { background: var(--gold) !important; color: var(--navy) !important; }
.btn-nav-solid {
  padding: .55rem 1.5rem;
  background: var(--gold);
  border: 1px solid var(--gold);
  color: var(--navy) !important;
  border-radius: 50px;
  font-size: .88rem !important;
  font-weight: 600 !important;
  transition: var(--transition) !important;
}
.btn-nav-solid::after { display: none !important; }
.btn-nav-solid:hover { background: var(--gold-lt) !important; }
.hero {
  min-height: 100vh;
  display: flex; 
  align-items: center;
  position: relative;
  padding: 8rem 4rem 5rem;
  overflow: hidden;
  z-index: 1;
}
.hero-content {
  max-width: 680px;
  position: relative;
  z-index: 2;
  animation: fadeUp .9s ease both;
}
@keyframes fadeUp {
  from { opacity:0; transform: translateY(40px); }
  to   { opacity:1; transform: translateY(0); }
}
.hero-badge {
  display: inline-flex; align-items: center; gap: .5rem;
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.25);
  border-radius: 50px;
  padding: .5rem 1.2rem;
  font-size: .75rem;
  color: #ffffff;
  letter-spacing: .08em;
  text-transform: uppercase;
  margin-bottom: 1.5rem;
  font-weight: 500;
}
.hero-badge i {
  color: var(--gold);
}
.hero h1 {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2.8rem, 5.5vw, 4.5rem);
  font-weight: 900;
  line-height: 1.08;
  margin-bottom: 1.5rem;
  color: #ffffff;
  text-shadow: 0 2px 30px rgba(0,0,0,0.4);
}
.hero h1 em {
  font-style: normal;
  color: var(--gold-lt);
  display: block;
}
.hero p {
  font-size: 1.1rem;
  color: rgba(255,255,255,0.95);
  max-width: 520px;
  margin-bottom: 2.5rem;
  font-weight: 300;
  line-height: 1.8;
  text-shadow: 0 2px 20px rgba(0,0,0,0.3);
}
.hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; }
.btn {
  display: inline-flex; align-items: center; gap: .6rem;
  padding: .85rem 2.2rem;
  border-radius: 50px;
  font-family: 'DM Sans', sans-serif;
  font-size: .95rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: var(--transition);
}
.btn-primary {
  background: linear-gradient(135deg, var(--gold), var(--gold-lt));
  color: var(--navy);
  box-shadow: 0 8px 40px rgba(201,168,76,.3);
}
.btn-primary:hover { 
  transform: translateY(-3px); 
  box-shadow: 0 12px 50px rgba(201,168,76,.5); 
}
.btn-outline {
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.3);
  color: #ffffff;
  backdrop-filter: blur(10px);
}
.btn-outline:hover { 
  background: rgba(255,255,255,0.25); 
  border-color: rgba(255,255,255,0.5);
  transform: translateY(-3px);
}
.section {
  padding: 6rem 4rem;
  position: relative;
  z-index: 1;
}
.section-label {
  display: block;
  text-align: center;
  font-size: .75rem;
  letter-spacing: .15em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: .75rem;
}
.section-title {
  text-align: center;
  font-family: 'Playfair Display', serif;
  font-size: clamp(1.8rem, 3vw, 2.8rem);
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 1rem;
  text-shadow: 0 2px 20px rgba(0,0,0,0.3);
}
.section-sub {
  text-align: center;
  color: rgba(255,255,255,0.9);
  max-width: 580px;
  margin: 0 auto 3.5rem;
  font-size: 1rem;
  font-weight: 300;
  text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  max-width: 1100px;
  margin: 0 auto;
}
.feature-card {
  background: rgba(255,255,255,0.12);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: var(--radius-lg);
  padding: 2rem;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}
.feature-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent, var(--gold), transparent);
  transform: scaleX(0);
  transition: var(--transition);
}
.feature-card:hover { 
  transform: translateY(-6px); 
  border-color: rgba(201,168,76,.4);
  background: rgba(255,255,255,0.18);
}
.feature-card:hover::before { transform: scaleX(1); }
.feature-icon {
  width: 52px; height: 52px;
  border-radius: 14px;
  background: rgba(201,168,76,.2);
  border: 1px solid rgba(201,168,76,.3);
  display: flex; align-items: center; justify-content: center;
  color: var(--gold); font-size: 1.3rem;
  margin-bottom: 1.25rem;
}
.feature-card h3 {
  font-family: 'Playfair Display', serif;
  font-size: 1.1rem;
  color: #ffffff;
  margin-bottom: .6rem;
  text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.feature-card p { 
  color: rgba(255,255,255,0.85); 
  font-size: .9rem; 
  line-height: 1.65; 
  font-weight: 300; 
}
.steps-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 2rem;
  max-width: 1000px;
  margin: 0 auto;
}
.step { 
  text-align: center; 
  padding: 1.5rem 1rem;
  background: rgba(255,255,255,0.08);
  backdrop-filter: blur(10px);
  border-radius: var(--radius-lg);
  border: 1px solid rgba(255,255,255,0.1);
  transition: var(--transition);
}
.step:hover {
  background: rgba(255,255,255,0.15);
  transform: translateY(-4px);
}
.step-num {
  width: 56px; height: 56px; border-radius: 50%;
  background: linear-gradient(135deg, var(--gold), var(--gold-lt));
  color: var(--navy);
  font-family: 'Playfair Display', serif;
  font-size: 1.4rem; font-weight: 900;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1rem;
  box-shadow: 0 8px 40px rgba(201,168,76,.25);
}
.step h3 { 
  font-size: 1rem; 
  font-weight: 600; 
  margin-bottom: .5rem; 
  color: #ffffff;
  text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.step p { 
  font-size: .87rem; 
  color: rgba(255,255,255,0.85); 
  line-height: 1.6; 
  font-weight: 300; 
}
.cta-section {
  padding: 6rem 4rem;
  text-align: center;
  position: relative;
  z-index: 1;
}
.login-cards {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  max-width: 780px;
  margin: 3rem auto 0;
}
.login-card {
  background: rgba(255,255,255,0.12);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: var(--radius-lg);
  padding: 2.5rem 2rem;
  text-decoration: none;
  transition: var(--transition);
  display: block;
}
.login-card:hover {
  transform: translateY(-5px);
  border-color: var(--gold);
  box-shadow: 0 8px 40px rgba(201,168,76,.25);
  background: rgba(255,255,255,0.18);
}
.login-card .card-icon {
  width: 64px; height: 64px; border-radius: 50%;
  background: linear-gradient(135deg, var(--gold), var(--gold-lt));
  display: flex; align-items: center; justify-content: center;
  color: var(--navy); font-size: 1.6rem;
  margin: 0 auto 1.25rem;
}
.login-card h3 { 
  font-family: 'Playfair Display', serif; 
  font-size: 1.3rem; 
  color: #ffffff; 
  margin-bottom: .5rem;
  text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.login-card p { 
  color: rgba(255,255,255,0.85); 
  font-size: .88rem; 
  margin-bottom: 1.5rem; 
}
.card-link {
  display: inline-flex; align-items: center; gap: .4rem;
  color: var(--gold); font-size: .88rem; font-weight: 600;
}
footer {
  background: rgba(0,0,0,0.4);
  backdrop-filter: blur(15px);
  padding: 3rem 4rem 2rem;
  border-top: 1px solid rgba(255,255,255,0.1);
  position: relative;
  z-index: 1;
}
.footer-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 3rem;
  margin-bottom: 2.5rem;
}
.footer-brand p { 
  color: rgba(255,255,255,0.8); 
  font-size: .88rem; 
  line-height: 1.7; 
  margin-top: .75rem; 
  font-weight: 300; 
}
.footer-col h4 { 
  color: var(--gold); 
  font-size: .85rem; 
  letter-spacing: .08em; 
  text-transform: uppercase; 
  margin-bottom: 1rem; 
}
.footer-col a { 
  display: block; 
  color: rgba(255,255,255,0.7); 
  text-decoration: none; 
  font-size: .88rem; 
  margin-bottom: .5rem; 
  transition: color var(--transition); 
}
.footer-col a:hover { color: #ffffff; }
.footer-col a i {
  color: var(--gold);
  margin-right: 0.3rem;
}
.footer-bottom {
  border-top: 1px solid rgba(255,255,255,0.1);
  padding-top: 1.5rem;
  display: flex; align-items: center; justify-content: space-between;
  color: rgba(255,255,255,0.6); 
  font-size: .8rem;
}
@media (max-width: 1024px) {
  .login-cards { grid-template-columns: 1fr; max-width: 400px; }
  .footer-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
  nav { padding: 1rem 1.5rem; }
  .nav-links { gap: 1rem; }
  .hero, .section, .cta-section { padding-left: 1.5rem; padding-right: 1.5rem; }
  footer { padding: 2.5rem 1.5rem 1.5rem; }
  .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
  .footer-bottom { flex-direction: column; gap: .5rem; text-align: center; }
}
@media (max-width: 560px) {
  .nav-links .btn-nav { display: none; }
  .hero-actions { flex-direction: column; }
  .btn { justify-content: center; }
}
</style>
</head>
<body>
<nav id="navbar">
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">S</div>
    <div class="nav-title">SLSU GPTS <span>Lucena Campus</span></div>
  </a>
  <div class="nav-links">
    <a href="#features" data-section="features"><i class="fas fa-check-circle" style="display:none;"></i> Features</a>
    <a href="#how-it-works" data-section="how-it-works"><i class="fas fa-check-circle" style="display:none;"></i> How It Works</a>
    <a href="#portal" data-section="portal"><i class="fas fa-check-circle" style="display:none;"></i> Portal</a>
    <a href="login.php" class="btn-nav">Sign In</a>
    <a href="register.php" class="btn-nav-solid">Register</a>
  </div>
</nav>
<section class="hero" id="hero">
  <div class="hero-content">
    <div class="hero-badge"><i class="fas fa-graduation-cap"></i> Southern Luzon State University – Lucena</div>
    <h1>Graduate Profiling <em>&amp; Tracer System</em></h1>
    <p>An integrated digital platform connecting SLSU graduates with the university — tracking career pathways, gathering employment data, and delivering automated notifications for continuous institutional improvement.</p>
    <div class="hero-actions">
      <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create Account</a>
      <a href="#features" class="btn btn-outline"><i class="fas fa-compass"></i> Explore Features</a>
    </div>
  </div>
</section>
<section class="section" id="features">
  <span class="section-label">Core Capabilities</span>
  <h2 class="section-title">Everything in One Platform</h2>
  <p class="section-sub">A comprehensive solution built for SLSU Lucena to track, connect, and communicate with its graduates effectively.</p>
  <div class="features-grid">
    <div class="feature-card"><div class="feature-icon"><i class="fas fa-id-card"></i></div><h3>Graduate Profiling</h3><p>Maintain complete academic and personal profiles for every SLSU graduate, searchable and filterable by program, batch, and more.</p></div>
    <div class="feature-card"><div class="feature-icon"><i class="fas fa-map-marked-alt"></i></div><h3>Employment Tracer</h3><p>Gather structured employment data through annual tracer surveys — occupation, employer, salary range, and job relevance to degree.</p></div>
    <div class="feature-card"><div class="feature-icon"><i class="fas fa-bell"></i></div><h3>Automated Notifications</h3><p>Send email and in-system notifications automatically for survey deadlines, account approvals, and important announcements.</p></div>
    <div class="feature-card"><div class="feature-icon"><i class="fas fa-chart-pie"></i></div><h3>Analytics &amp; Reports</h3><p>Visual dashboards with employment trends, program outcomes, and accreditation-ready statistical reports for administrators.</p></div>
    <div class="feature-card"><div class="feature-icon"><i class="fas fa-lock"></i></div><h3>Secure &amp; Role-based</h3><p>Separate portals for graduates and administrators with secure authentication, CSRF protection, and full audit logging.</p></div>
    <div class="feature-card"><div class="feature-icon"><i class="fas fa-mobile-alt"></i></div><h3>Responsive Design</h3><p>Access from any device — desktop, tablet, or mobile. Built with modern, accessible HTML and CSS for all screen sizes.</p></div>
  </div>
</section>
<section class="section" id="how-it-works">
  <span class="section-label">Process</span>
  <h2 class="section-title">How It Works</h2>
  <p class="section-sub">Simple steps for graduates to get started with the SLSU GPTS platform.</p>
  <div class="steps-container">
    <div class="step"><div class="step-num">1</div><h3>Create Account</h3><p>Graduates register using their SLSU student ID and official email address to verify identity.</p></div>
    <div class="step"><div class="step-num">2</div><h3>Complete Profile</h3><p>Fill in your personal details, academic information, and upload your profile photo.</p></div>
    <div class="step"><div class="step-num">3</div><h3>Answer Survey</h3><p>Respond to the annual tracer survey providing your current employment status and feedback.</p></div>
    <div class="step"><div class="step-num">4</div><h3>Stay Connected</h3><p>Receive notifications from SLSU and stay updated on alumni events and announcements.</p></div>
  </div>
</section>
<section class="cta-section" id="portal">
  <span class="section-label">Access the System</span>
  <h2 class="section-title">Choose Your Portal</h2>
  <p class="section-sub">Select your role to access the appropriate login portal.</p>
  <div class="login-cards">
    <a href="login.php?role=student" class="login-card">
      <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
      <h3>Graduate Portal</h3>
      <p>For SLSU alumni and graduates to manage their profile, complete tracer surveys, and view notifications.</p>
      <span class="card-link">Graduate Login <i class="fas fa-arrow-right"></i></span>
    </a>
    <a href="login.php?role=admin" class="login-card">
      <div class="card-icon"><i class="fas fa-user-shield"></i></div>
      <h3>Admin Portal</h3>
      <p>For SLSU faculty and staff to manage graduate data, generate reports, and send notifications.</p>
      <span class="card-link">Admin Login <i class="fas fa-arrow-right"></i></span>
    </a>
  </div>
</section>
<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
        <div class="nav-logo">S</div>
        <div><div style="font-weight:700;color:#ffffff;">SLSU GPTS</div><div style="font-size:.75rem;color:var(--gold);">Graduate Profiling &amp; Tracer System</div></div>
      </div>
      <p>An integrated digital platform developed for Southern Luzon State University – Lucena Campus to track graduate outcomes and strengthen institutional quality assurance.</p>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <a href="index.php">Home</a>
      <a href="register.php">Register</a>
      <a href="login.php">Sign In</a>
      <a href="#features">Features</a>
    </div>
    <div class="footer-col">
      <h4>Contact</h4>
      <a href="#"><i class="fas fa-map-marker-alt"></i> Lucena City, Quezon</a>
      <a href="mailto:gpts@slsu.edu.ph"><i class="fas fa-envelope"></i> gpts@slsu.edu.ph</a>
      <a href="https://slsu.edu.ph" target="_blank"><i class="fas fa-globe"></i> slsu.edu.ph</a>
    </div>
  </div>
  <div class="footer-bottom">
    <span>&copy; <?= date('Y') ?> Southern Luzon State University – Lucena. All rights reserved.</span>
    <span>GPTS v1.0.0</span>
  </div>
</footer>
<script>
const nav = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  nav.style.padding = window.scrollY > 60 ? '.7rem 4rem' : '1.1rem 4rem';
});

// ============================================================
// Active Nav Link Highlighting
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
  const sections = ['features', 'how-it-works', 'portal'];
  const navLinks = document.querySelectorAll('.nav-links a[data-section]');

  function updateActiveLink() {
    let currentSection = '';
    const scrollPosition = window.scrollY + 120;

    sections.forEach(sectionId => {
      const section = document.getElementById(sectionId);
      if (section) {
        const sectionTop = section.offsetTop;
        const sectionBottom = sectionTop + section.offsetHeight;

        if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
          currentSection = sectionId;
        }
      }
    });

    navLinks.forEach(link => {
      const section = link.getAttribute('data-section');
      const icon = link.querySelector('i');

      if (section === currentSection) {
        link.classList.add('active');
        if (icon) icon.style.display = 'inline';
      } else {
        link.classList.remove('active');
        if (icon) icon.style.display = 'none';
      }
    });
  }

  window.addEventListener('scroll', updateActiveLink);
  window.addEventListener('load', updateActiveLink);
  window.addEventListener('hashchange', function() {
    setTimeout(updateActiveLink, 100);
  });

  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const targetId = this.getAttribute('href').substring(1);
      const targetSection = document.getElementById(targetId);
      
      if (targetSection) {
        e.preventDefault();
        
        navLinks.forEach(l => {
          l.classList.remove('active');
          const icon = l.querySelector('i');
          if (icon) icon.style.display = 'none';
        });
        
        this.classList.add('active');
        const icon = this.querySelector('i');
        if (icon) icon.style.display = 'inline';
        
        targetSection.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });
});
</script>
</body>
</html>