<?php
require __DIR__ . '/config.php';

$school_name = get_school_info($conn, 'school_name', 'Our School');
$school_motto = get_school_info($conn, 'school_motto', 'Excellence in Education');
$school_email = get_school_info($conn, 'school_email', '');
$school_phone = get_school_info($conn, 'school_phone', '');
$school_whatsapp = get_school_info($conn, 'school_whatsapp', '');
$school_address = get_school_info($conn, 'school_address', '');
$school_website = get_school_info($conn, 'school_website', '');
$operating_hours = get_school_info($conn, 'operating_hours', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?= htmlspecialchars($school_name) ?></title>
    <link rel="stylesheet" href="external.css?v=4">
    <link rel="stylesheet" href="assets/toast.css">
    <style>
        html{scroll-behavior:smooth}
        body.public-page{background:radial-gradient(circle at 0% 0%,rgba(31,182,255,0.35),transparent 35%),radial-gradient(circle at 100% 100%,rgba(18,58,146,0.4),transparent 35%),linear-gradient(145deg,#071120,#123a92 55%,#1fb6ff)}
        .landing-top{display:flex;align-items:center;justify-content:space-between;padding:20px 40px;position:absolute;top:0;left:0;right:0;z-index:10}
        .landing-top .logo{font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.3px}
        .landing-top .logo span{color:#7fcbff}
        .landing-top nav{display:flex;gap:12px}
        .landing-top nav a{text-decoration:none;padding:10px 24px;border-radius:30px;font-size:14px;font-weight:700;transition:all 0.2s}
        .landing-top nav a:first-child{color:#fff;border:2px solid rgba(255,255,255,0.3)}
        .landing-top nav a:first-child:hover{border-color:#fff;background:rgba(255,255,255,0.1)}
        .landing-top nav a:last-child{background:#fff;color:#0d2b5e}
        .landing-top nav a:last-child:hover{background:#e8f0ff;transform:translateY(-1px)}
        .hero{padding:140px 40px 100px;text-align:center;color:#fff;position:relative}
        .hero h1{font-size:clamp(36px,7vw,68px);font-weight:900;margin:0 0 16px;line-height:1.08;letter-spacing:-1px}
        .hero h1 span{background:linear-gradient(135deg,#7fcbff,#b8e1ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .hero .motto{font-size:clamp(16px,2vw,22px);opacity:0.85;margin:0 auto 40px;max-width:600px;line-height:1.5}
        .hero-btns{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
        .hero-btns a{text-decoration:none;padding:16px 40px;border-radius:60px;font-size:16px;font-weight:800;transition:all 0.25s;display:inline-flex;align-items:center;gap:8px}
        .hero-btns .btn-primary-hero{background:#fff;color:#0d2b5e;box-shadow:0 8px 30px rgba(0,0,0,0.15)}
        .hero-btns .btn-primary-hero:hover{transform:translateY(-3px);box-shadow:0 14px 40px rgba(0,0,0,0.2)}
        .hero-btns .btn-outline-hero{background:transparent;color:#fff;border:2px solid rgba(255,255,255,0.4)}
        .hero-btns .btn-outline-hero:hover{background:rgba(255,255,255,0.1);border-color:#fff;transform:translateY(-3px)}
        .hero .scroll-indicator{position:absolute;bottom:30px;left:50%;transform:translateX(-50%);animation:bounce 2s infinite;opacity:0.6}
        @keyframes bounce{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(10px)}}
        .section{padding:80px 40px;max-width:1200px;margin:0 auto}
        .section-title{text-align:center;margin-bottom:16px;font-size:clamp(26px,4vw,38px);font-weight:800;color:#fff}
        .section-sub{text-align:center;margin-bottom:50px;color:rgba(255,255,255,0.7);font-size:17px;max-width:600px;margin-left:auto;margin-right:auto}
        .cards-grid-landing{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px}
        .landing-card{background:rgba(255,255,255,0.96);border-radius:24px;padding:32px 28px;box-shadow:0 8px 40px rgba(0,0,0,0.08);transition:transform 0.25s,box-shadow 0.25s;border:1px solid rgba(255,255,255,0.6)}
        .landing-card:hover{transform:translateY(-6px);box-shadow:0 16px 60px rgba(0,0,0,0.12)}
        .landing-card .step-num{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#1677ff,#0d57c6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;margin-bottom:16px}
        .landing-card h3{font-size:20px;margin:0 0 8px;color:#10233f}
        .landing-card p{color:#5f728c;line-height:1.6;margin:0;font-size:14px}
        .about-panel{background:rgba(255,255,255,0.96);border-radius:24px;padding:48px;box-shadow:0 8px 40px rgba(0,0,0,0.08);max-width:900px;margin:0 auto;text-align:center}
        .about-panel p{color:#5f728c;line-height:1.8;font-size:16px;margin:0}
        .about-panel .about-contact{margin-top:24px;display:flex;flex-wrap:wrap;gap:20px;justify-content:center}
        .about-panel .about-contact span{background:#f0f6ff;padding:8px 18px;border-radius:30px;font-size:14px;color:#10233f;font-weight:600;display:inline-flex;align-items:center;gap:6px}
        .staff-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        @media(max-width:700px){.staff-grid{grid-template-columns:1fr}}
        .staff-panel{background:rgba(255,255,255,0.96);border-radius:24px;padding:36px 32px;box-shadow:0 8px 40px rgba(0,0,0,0.08)}
        .staff-panel h3{font-size:22px;margin:0 0 12px;color:#10233f}
        .staff-panel p{color:#5f728c;line-height:1.7;font-size:14px;margin:0 0 16px}
        .staff-panel ul{list-style:none;padding:0;margin:0}
        .staff-panel ul li{padding:8px 0;color:#5f728c;font-size:14px;border-bottom:1px solid #eef4fb;display:flex;align-items:center;gap:10px}
        .staff-panel ul li:last-child{border-bottom:none}
        .staff-panel ul li::before{content:"✓";color:#1677ff;font-weight:800;font-size:16px}
        .staff-panel .cta-link{display:inline-block;margin-top:20px;padding:12px 32px;border-radius:30px;background:linear-gradient(135deg,#1677ff,#0d57c6);color:#fff;text-decoration:none;font-weight:700;font-size:14px;transition:all 0.2s}
        .staff-panel .cta-link:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(13,87,198,0.3)}
        .contact-panel{background:rgba(255,255,255,0.96);border-radius:24px;padding:48px;box-shadow:0 8px 40px rgba(0,0,0,0.08);max-width:800px;margin:0 auto}
        .contact-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin-top:24px}
        .contact-item{text-align:center}
        .contact-item .icon{font-size:28px;margin-bottom:8px}
        .contact-item h4{font-size:14px;font-weight:700;color:#5f728c;text-transform:uppercase;letter-spacing:0.5px;margin:0 0 4px}
        .contact-item p{color:#10233f;margin:0;font-size:15px;font-weight:600}
        .landing-footer{text-align:center;padding:40px;color:rgba(255,255,255,0.5);font-size:13px}
        .landing-footer a{color:rgba(255,255,255,0.7);text-decoration:none}
        .landing-footer a:hover{color:#fff}
        @media(max-width:640px){
            .landing-top{padding:16px 20px;flex-direction:column;gap:12px}
            .hero{padding:120px 20px 70px}
            .section{padding:50px 20px}
            .about-panel,.contact-panel{padding:28px 20px}
            .landing-card{padding:24px 20px}
        }
    </style>
</head>
<body class="public-page">
    <div class="landing-top">
        <div class="logo"><?= htmlspecialchars($school_name) ?><span>.</span></div>
        <nav>
            <a href="login.html">Sign In</a>
            <a href="signup.html">Register</a>
        </nav>
    </div>

    <section class="hero">
        <h1>Welcome to <span><?= htmlspecialchars($school_name) ?></span></h1>
        <p class="motto"><?= htmlspecialchars($school_motto) ?></p>
        <div class="hero-btns">
            <a href="signup.html" class="btn-primary-hero">Get Started</a>
            <a href="#about" class="btn-outline-hero">Learn More</a>
        </div>
        <div class="scroll-indicator">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M7 13l5 5 5-5M7 6l5 5 5-5"/></svg>
        </div>
    </section>

    <section id="about" class="section">
        <h2 class="section-title">About Our School</h2>
        <p class="section-sub">Dedicated to fostering academic excellence, character development, and lifelong learning.</p>
        <div class="about-panel">
            <p>
                <?= htmlspecialchars($school_name) ?> is committed to providing a nurturing environment where students thrive academically, socially, and emotionally. Our dedicated faculty and modern facilities ensure every learner reaches their full potential.
            </p>
            <div class="about-contact">
                <?php if ($school_email): ?><span>✉ <?= htmlspecialchars($school_email) ?></span><?php endif; ?>
                <?php if ($school_phone): ?><span>📞 <?= htmlspecialchars($school_phone) ?></span><?php endif; ?>
                <?php if ($school_whatsapp): ?><span>💬 <?= htmlspecialchars($school_whatsapp) ?></span><?php endif; ?>
                <?php if ($school_website): ?><span>🌐 <?= htmlspecialchars($school_website) ?></span><?php endif; ?>
            </div>
        </div>
    </section>

    <section id="students" class="section">
        <h2 class="section-title">Student Registration</h2>
        <p class="section-sub">Getting started is easy. Follow these simple steps to enroll as a student.</p>
        <div class="cards-grid-landing">
            <div class="landing-card">
                <div class="step-num">1</div>
                <h3>Create Your Account</h3>
                <p>Register with your email and a secure password. Accept the terms and conditions to proceed.</p>
            </div>
            <div class="landing-card">
                <div class="step-num">2</div>
                <h3>Complete Your Profile</h3>
                <p>Log in and fill in your personal details — full name, date of birth, contact info, and select your grade.</p>
            </div>
            <div class="landing-card">
                <div class="step-num">3</div>
                <h3>Wait for Approval</h3>
                <p>Once submitted, the school administration will review and approve your enrollment. You will receive a confirmation email.</p>
            </div>
            <div class="landing-card">
                <div class="step-num">4</div>
                <h3>Start Learning</h3>
                <p>After approval, log in to access your dashboard, view subjects, assignments, results, and school events.</p>
            </div>
        </div>
        <div style="text-align:center;margin-top:36px">
            <a href="signup.html" class="btn-primary-hero" style="text-decoration:none;padding:16px 40px;border-radius:60px;font-size:16px;font-weight:800;background:#fff;color:#0d2b5e;display:inline-block;box-shadow:0 8px 30px rgba(0,0,0,0.15);transition:all 0.25s">Register as a Student</a>
        </div>
    </section>

    <section id="staff" class="section">
        <h2 class="section-title">Staff &amp; Teacher Applications</h2>
        <p class="section-sub">Join our team of dedicated educators and staff members.</p>
        <div class="staff-grid">
            <div class="staff-panel">
                <h3>For Teachers</h3>
                <p>We are always looking for passionate, qualified teachers to join our faculty.</p>
                <ul>
                    <li>Submit your application through the school administration office</li>
                    <li>Provide your teaching qualifications, experience, and subject specialisation</li>
                    <li>Attend an interview and demo lesson</li>
                    <li>Upon approval, HR will create your staff account</li>
                    <li>Log in to access class management, grading, and student progress tools</li>
                </ul>
                <a href="login.html" class="cta-link">Already have an account? Sign In</a>
            </div>
            <div class="staff-panel">
                <h3>For Staff</h3>
                <p>Non-teaching positions across finance, HR, IT, and administration are available.</p>
                <ul>
                    <li>Send your CV and cover letter to the school administration</li>
                    <li>HR reviews your qualifications and experience</li>
                    <li>Successful candidates proceed to an interview</li>
                    <li>Once approved, an employee account is created for you</li>
                    <li>Access your department dashboard and work tools</li>
                </ul>
                <a href="mailto:<?= htmlspecialchars($school_email) ?>" class="cta-link">Contact HR</a>
            </div>
        </div>
    </section>

    <?php if ($school_address || $school_phone || $school_email || $operating_hours): ?>
    <section id="contact" class="section">
        <h2 class="section-title">Contact Us</h2>
        <p class="section-sub">We'd love to hear from you. Reach out to us through any of the channels below.</p>
        <div class="contact-panel">
            <div class="contact-grid">
                <?php if ($school_address): ?>
                <div class="contact-item">
                    <div class="icon">📍</div>
                    <h4>Address</h4>
                    <p><?= htmlspecialchars($school_address) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($school_phone): ?>
                <div class="contact-item">
                    <div class="icon">📞</div>
                    <h4>Phone</h4>
                    <p><?= htmlspecialchars($school_phone) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($school_email): ?>
                <div class="contact-item">
                    <div class="icon">✉</div>
                    <h4>Email</h4>
                    <p><?= htmlspecialchars($school_email) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($operating_hours): ?>
                <div class="contact-item">
                    <div class="icon">🕒</div>
                    <h4>Operating Hours</h4>
                    <p><?= htmlspecialchars($operating_hours) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <footer class="landing-footer">
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($school_name) ?>. All rights reserved.</p>
        <p><a href="login.html">Staff Login</a> &middot; <a href="signup.html">Student Registration</a> &middot; <a href="terms.php">Terms and Conditions</a></p>
    </footer>
</body>
</html>
