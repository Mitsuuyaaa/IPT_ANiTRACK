<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>ANI-TRACK | Farm Sales Tracker</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root {
  --g1: #0d2211;
  --g2: #1b3a1f;
  --g3: #2e7d32;
  --g4: #43a047;
  --g5: #66bb6a;
  --g6: #a5d6a7;
  --g7: #c8e6c9;
  --g8: #e8f5e9;
  --cream: #f5f0e8;
  --gold:  #d4a843;
  --gold-light: #f0c96a;
  --text: #0d2211;
  --text-mid: #2e4a30;
  --text-soft: #6a8a6c;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
  font-family: 'Poppins', sans-serif;
  background: var(--g1);
  color: #fff;
  overflow-x: hidden;
  cursor: default;
}

/* ── GRAIN OVERLAY ── */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='1'/%3E%3C/svg%3E");
  opacity: 0.04;
  pointer-events: none;
  z-index: 1000;
}

/* ── NAV ── */
nav {
  position: fixed; top: 0; left: 0; right: 0;
  z-index: 100;
  padding: 20px 60px;
  display: flex; align-items: center; justify-content: space-between;
  background: linear-gradient(to bottom, rgba(13,34,17,0.95) 0%, transparent 100%);
}
.nav-logo {
  font-family: 'Poppins', sans-serif;
  font-size: 22px; font-weight: 800;
  color: #fff; letter-spacing: 2px;
  text-decoration: none;
}
.nav-logo span { color: var(--g6); }
.nav-links { display: flex; align-items: center; gap: 10px; }
.nav-btn {
  padding: 10px 26px;
  border-radius: 50px;
  font-family: 'Poppins', sans-serif;
  font-size: 13px; font-weight: 600;
  cursor: pointer; text-decoration: none;
  transition: all 0.25s;
  letter-spacing: 0.5px;
}
.nav-btn.outline {
  background: transparent;
  border: 1.5px solid rgba(255,255,255,0.35);
  color: rgba(255,255,255,0.85);
}
.nav-btn.outline:hover {
  border-color: var(--g6);
  color: var(--g6);
  background: rgba(165,214,167,0.08);
}
.nav-btn.solid {
  background: var(--g4);
  border: 1.5px solid var(--g4);
  color: #fff;
  box-shadow: 0 4px 20px rgba(67,160,71,0.4);
}
.nav-btn.solid:hover {
  background: var(--g5);
  border-color: var(--g5);
  transform: translateY(-1px);
  box-shadow: 0 6px 24px rgba(67,160,71,0.5);
}

/* ── HERO ── */
.hero {
  min-height: 100vh;
  position: relative;
  display: flex; align-items: center;
  overflow: hidden;
}

/* Background layers */
.hero-bg {
  position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 70% 50%, rgba(46,125,50,0.25) 0%, transparent 60%),
    radial-gradient(ellipse 50% 80% at 20% 80%, rgba(27,58,31,0.6) 0%, transparent 55%),
    linear-gradient(135deg, #0d2211 0%, #162c1a 40%, #1b3a1f 70%, #0f2813 100%);
}

/* Decorative circles */
.hero-circle {
  position: absolute; border-radius: 50%;
  pointer-events: none;
}
.hc1 {
  width: 700px; height: 700px;
  border: 1px solid rgba(165,214,167,0.06);
  top: 50%; right: -100px;
  transform: translateY(-50%);
  animation: slowspin 30s linear infinite;
}
.hc2 {
  width: 500px; height: 500px;
  border: 1px solid rgba(165,214,167,0.08);
  top: 50%; right: 50px;
  transform: translateY(-50%);
  animation: slowspin 20s linear infinite reverse;
}
.hc3 {
  width: 300px; height: 300px;
  background: radial-gradient(circle, rgba(67,160,71,0.12) 0%, transparent 70%);
  top: 50%; right: 150px;
  transform: translateY(-50%);
}
@keyframes slowspin { to { transform: translateY(-50%) rotate(360deg); } }

/* Floating leaves */
.leaf {
  position: absolute;
  opacity: 0;
  animation: floatLeaf linear infinite;
  pointer-events: none;
}
@keyframes floatLeaf {
  0%   { opacity: 0; transform: translateY(100vh) rotate(0deg) scale(0.5); }
  10%  { opacity: 0.6; }
  90%  { opacity: 0.3; }
  100% { opacity: 0; transform: translateY(-20vh) rotate(720deg) scale(1); }
}

/* Diagonal divider */
.hero::after {
  content: '';
  position: absolute; bottom: -2px; left: 0; right: 0;
  height: 120px;
  background: var(--cream);
  clip-path: polygon(0 100%, 100% 100%, 100% 60%, 0 0);
}

.hero-inner {
  position: relative; z-index: 2;
  max-width: 1200px; margin: 0 auto;
  padding: 120px 60px 140px;
  display: flex; flex-direction: column;
  align-items: center; text-align: center;
}

.hero-left { }

.hero-eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(165,214,167,0.1);
  border: 1px solid rgba(165,214,167,0.25);
  border-radius: 50px;
  padding: 6px 16px;
  font-size: 11px; font-weight: 600;
  color: var(--g6); letter-spacing: 2px; text-transform: uppercase;
  margin-bottom: 24px;
  animation: fadeUp 0.8s ease both;
}
.hero-eyebrow::before {
  content: '';
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--g5);
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:0.5;transform:scale(1.4);} }

.hero-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(42px, 5.5vw, 72px);
  font-weight: 900;
  line-height: 1.05;
  color: #fff;
  margin-bottom: 24px;
  animation: fadeUp 0.8s 0.1s ease both;
}
.hero-title em {
  font-style: italic;
  color: var(--g6);
  position: relative;
}
.hero-title em::after {
  content: '';
  position: absolute;
  bottom: 2px; left: 0; right: 0; height: 3px;
  background: linear-gradient(90deg, var(--g5), transparent);
  border-radius: 2px;
}
.hero-title .gold { color: var(--gold-light); }

.hero-desc {
  font-size: 16px; line-height: 1.75;
  color: rgba(255,255,255,0.6);
  max-width: 580px;
  margin: 0 auto 40px;
  animation: fadeUp 0.8s 0.2s ease both;
}
.hero-desc strong { color: var(--g6); font-weight: 600; }

.hero-ctas {
  display: flex; align-items: center; gap: 14px;
  flex-wrap: wrap; justify-content: center;
  animation: fadeUp 0.8s 0.3s ease both;
}

.cta-main {
  display: inline-flex; align-items: center; gap: 10px;
  padding: 16px 36px;
  background: linear-gradient(135deg, var(--g5), var(--g3));
  color: #fff; border: none; border-radius: 50px;
  font-family: 'Poppins', sans-serif;
  font-size: 15px; font-weight: 700;
  cursor: pointer; text-decoration: none;
  box-shadow: 0 8px 32px rgba(67,160,71,0.45);
  transition: all 0.25s;
  letter-spacing: 0.5px;
  position: relative; overflow: hidden;
}
.cta-main::before {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
  opacity: 0; transition: opacity 0.25s;
}
.cta-main:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(67,160,71,0.55); }
.cta-main:hover::before { opacity: 1; }
.cta-main svg { width: 18px; height: 18px; transition: transform 0.25s; }
.cta-main:hover svg { transform: translateX(3px); }

.cta-secondary {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 16px 32px;
  background: transparent;
  border: 1.5px solid rgba(255,255,255,0.25);
  color: rgba(255,255,255,0.8);
  border-radius: 50px;
  font-family: 'Poppins', sans-serif;
  font-size: 15px; font-weight: 600;
  cursor: pointer; text-decoration: none;
  transition: all 0.25s;
}
.cta-secondary:hover {
  border-color: var(--g6);
  color: var(--g6);
  background: rgba(165,214,167,0.06);
}

.hero-stats {
  display: flex; gap: 32px; margin-top: 48px;
  justify-content: center;
  animation: fadeUp 0.8s 0.4s ease both;
}
.hero-stat-num {
  font-family: 'Playfair Display', serif;
  font-size: 28px; font-weight: 700;
  color: var(--g5); line-height: 1;
}
.hero-stat-label {
  font-size: 11px; color: rgba(255,255,255,0.45);
  margin-top: 4px; font-weight: 500;
  text-transform: uppercase; letter-spacing: 1px;
}
.hero-stat-divider { width: 1px; background: rgba(255,255,255,0.1); align-self: stretch; }

/* Hero right — feature card stack */
.hero-right {
  position: relative;
  display: flex; align-items: center; justify-content: center;
  animation: fadeUp 0.8s 0.2s ease both;
}

.card-stack {
  position: relative;
  width: 340px; height: 400px;
}
.feat-card {
  position: absolute;
  background: rgba(255,255,255,0.05);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 20px;
  padding: 24px;
  transition: transform 0.3s ease;
}
.feat-card:hover { transform: translateY(-4px) !important; }

.fc-main {
  width: 300px;
  top: 0; left: 20px;
  background: linear-gradient(145deg, rgba(46,125,50,0.35), rgba(27,58,31,0.6));
  border-color: rgba(165,214,167,0.2);
  z-index: 3;
}
.fc-2 {
  width: 260px;
  top: 60px; left: 50px;
  background: rgba(13,34,17,0.7);
  z-index: 2;
  transform: rotate(3deg);
}
.fc-3 {
  width: 240px;
  top: 110px; left: 70px;
  background: rgba(13,34,17,0.5);
  z-index: 1;
  transform: rotate(6deg);
}

.fc-icon {
  width: 44px; height: 44px; border-radius: 12px;
  background: linear-gradient(135deg, var(--g4), var(--g3));
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 14px;
  box-shadow: 0 4px 16px rgba(67,160,71,0.4);
}
.fc-icon svg { width: 22px; height: 22px; color: #fff; }
.fc-title { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 6px; }
.fc-desc  { font-size: 12px; color: rgba(255,255,255,0.5); line-height: 1.6; }
.fc-amount { font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 700; color: var(--g5); margin-top: 12px; }
.fc-amount span { font-family: 'Poppins', sans-serif; font-size: 11px; color: rgba(255,255,255,0.4); font-weight: 500; margin-left: 4px; }

/* Floating badges */
.float-badge {
  position: absolute;
  background: rgba(255,255,255,0.95);
  border-radius: 12px;
  padding: 10px 14px;
  display: flex; align-items: center; gap: 8px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.3);
  z-index: 10;
  animation: floatBob 4s ease-in-out infinite;
}
.float-badge.fb1 { top: -20px; right: -20px; animation-delay: 0s; }
.float-badge.fb2 { bottom: 20px; right: -30px; animation-delay: 1.5s; }
@keyframes floatBob { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
.fb-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.fb-icon.green { background: #e8f5e9; }
.fb-icon svg { width: 14px; height: 14px; }
.fb-text { font-size: 11px; }
.fb-text strong { display: block; color: var(--text); font-weight: 700; font-size: 12px; }
.fb-text span { color: var(--text-soft); font-weight: 500; }

/* ── FEATURES SECTION ── */
.features {
  background: var(--cream);
  padding: 100px 60px;
  position: relative;
}
.features::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(67,160,71,0.3), transparent);
}

.section-label {
  text-align: center;
  font-size: 11px; font-weight: 700; letter-spacing: 3px;
  text-transform: uppercase; color: var(--g3);
  margin-bottom: 12px;
}
.section-title {
  text-align: center;
  font-family: 'Playfair Display', serif;
  font-size: clamp(32px, 4vw, 52px);
  font-weight: 900;
  color: var(--g1);
  margin-bottom: 16px;
  line-height: 1.1;
}
.section-title em { font-style: italic; color: var(--g3); }
.section-sub {
  text-align: center;
  font-size: 15px; color: var(--text-soft);
  max-width: 520px; margin: 0 auto 64px;
  line-height: 1.7;
}

.features-grid {
  max-width: 1100px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 28px;
}
.feature-card {
  background: #fff;
  border-radius: 20px;
  padding: 32px 28px;
  border: 1px solid rgba(67,160,71,0.1);
  transition: transform 0.25s, box-shadow 0.25s;
  position: relative; overflow: hidden;
}
.feature-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: linear-gradient(90deg, var(--g4), var(--g5));
  transform: scaleX(0); transform-origin: left;
  transition: transform 0.3s ease;
}
.feature-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(67,160,71,0.15); }
.feature-card:hover::before { transform: scaleX(1); }
.feat-num {
  font-family: 'Playfair Display', serif;
  font-size: 48px; font-weight: 900;
  color: var(--g7);
  line-height: 1; margin-bottom: 16px;
}
.feat-card-icon {
  width: 48px; height: 48px; border-radius: 14px;
  background: var(--g8);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 18px;
}
.feat-card-icon svg { width: 24px; height: 24px; color: var(--g3); }
.feat-card-title { font-size: 17px; font-weight: 700; color: var(--g1); margin-bottom: 10px; }
.feat-card-desc  { font-size: 13px; color: var(--text-soft); line-height: 1.7; }

/* ── HOW IT WORKS ── */
.how {
  background: var(--g1);
  padding: 100px 60px;
  position: relative; overflow: hidden;
}
.how::before {
  content: '';
  position: absolute;
  width: 600px; height: 600px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(46,125,50,0.15) 0%, transparent 70%);
  top: 50%; left: -200px;
  transform: translateY(-50%);
}

.how-inner { max-width: 1100px; margin: 0 auto; }
.how-inner .section-title { color: #fff; }
.how-inner .section-sub   { color: rgba(255,255,255,0.5); }

.steps {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 0; position: relative; margin-top: 0;
}
.steps::before {
  content: '';
  position: absolute;
  top: 36px; left: calc(16.67% + 20px); right: calc(16.67% + 20px);
  height: 1px;
  background: linear-gradient(90deg, var(--g4), var(--g5), var(--g4));
  opacity: 0.4;
}

.step {
  text-align: center; padding: 0 24px;
  position: relative;
}
.step-num {
  width: 72px; height: 72px; border-radius: 50%;
  background: linear-gradient(135deg, var(--g3), var(--g4));
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 24px;
  font-family: 'Playfair Display', serif;
  font-size: 24px; font-weight: 900; color: #fff;
  box-shadow: 0 8px 32px rgba(67,160,71,0.4);
  position: relative; z-index: 1;
}
.step-title { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 10px; }
.step-desc  { font-size: 13px; color: rgba(255,255,255,0.5); line-height: 1.7; }

/* ── CTA SECTION ── */
.cta-section {
  background: var(--cream);
  padding: 100px 60px;
  text-align: center;
  position: relative; overflow: hidden;
}
.cta-section::before {
  content: '';
  position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 60% 80% at 50% 50%, rgba(67,160,71,0.06) 0%, transparent 70%);
}
.cta-inner { max-width: 700px; margin: 0 auto; position: relative; z-index: 1; }
.cta-inner .section-title { margin-bottom: 16px; }
.cta-inner .section-sub   { margin-bottom: 44px; }
.cta-pair { display: flex; align-items: center; justify-content: center; gap: 14px; flex-wrap: wrap; }

.cta-big {
  display: inline-flex; align-items: center; gap: 10px;
  padding: 18px 44px;
  background: linear-gradient(135deg, var(--g4), var(--g3));
  color: #fff; border: none; border-radius: 50px;
  font-family: 'Poppins', sans-serif;
  font-size: 16px; font-weight: 700;
  cursor: pointer; text-decoration: none;
  box-shadow: 0 8px 32px rgba(67,160,71,0.4);
  transition: all 0.25s;
}
.cta-big:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(67,160,71,0.55); background: linear-gradient(135deg, var(--g5), var(--g4)); }

.cta-alt {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 18px 36px;
  background: transparent;
  border: 2px solid rgba(46,125,50,0.4);
  color: var(--g3);
  border-radius: 50px;
  font-family: 'Poppins', sans-serif;
  font-size: 16px; font-weight: 600;
  cursor: pointer; text-decoration: none;
  transition: all 0.25s;
}
.cta-alt:hover { border-color: var(--g4); color: var(--g4); background: rgba(67,160,71,0.05); }

/* ── FOOTER ── */
footer {
  background: var(--g1);
  padding: 40px 60px;
  display: flex; align-items: center; justify-content: space-between;
  border-top: 1px solid rgba(255,255,255,0.06);
}
.footer-logo { font-family: 'Poppins', sans-serif; font-size: 18px; font-weight: 800; color: #fff; letter-spacing: 2px; }
.footer-logo span { color: var(--g6); }
.footer-text { font-size: 12px; color: rgba(255,255,255,0.3); }
.footer-tagline { font-size: 12px; color: rgba(255,255,255,0.25); font-style: italic; }

/* ── ANIMATIONS ── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(24px); }
  to   { opacity: 1; transform: translateY(0); }
}

.reveal {
  opacity: 0; transform: translateY(30px);
  transition: opacity 0.7s ease, transform 0.7s ease;
}
.reveal.visible { opacity: 1; transform: translateY(0); }
.reveal-delay-1 { transition-delay: 0.1s; }
.reveal-delay-2 { transition-delay: 0.2s; }
.reveal-delay-3 { transition-delay: 0.3s; }

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
  nav { padding: 18px 24px; }
  .hero-inner { grid-template-columns: 1fr; padding: 100px 24px 100px; text-align: center; }
  .hero-right { display: none; }
  .hero-ctas { justify-content: center; }
  .hero-stats { justify-content: center; }
  .hero::after { display: none; }
  .features { padding: 70px 24px; }
  .features-grid { grid-template-columns: 1fr; }
  .how { padding: 70px 24px; }
  .steps { grid-template-columns: 1fr; gap: 40px; }
  .steps::before { display: none; }
  .cta-section { padding: 70px 24px; }
  footer { flex-direction: column; gap: 12px; text-align: center; padding: 32px 24px; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav>
  <a href="index.php" class="nav-logo">ANI<span>TRACK</span></a>
  <div class="nav-links">
    <a href="frontend/auth/login.php"    class="nav-btn outline">Sign In</a>
    <a href="frontend/auth/register.php" class="nav-btn solid">Get Started</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hc1 hero-circle"></div>
  <div class="hc2 hero-circle"></div>
  <div class="hc3 hero-circle"></div>

  <!-- Floating leaves (SVG) -->
  <svg class="leaf" style="width:18px;left:8%;animation-duration:14s;animation-delay:0s;" viewBox="0 0 24 24" fill="rgba(165,214,167,0.6)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>
  <svg class="leaf" style="width:14px;left:15%;animation-duration:18s;animation-delay:3s;" viewBox="0 0 24 24" fill="rgba(102,187,106,0.5)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>
  <svg class="leaf" style="width:22px;left:5%;animation-duration:22s;animation-delay:7s;" viewBox="0 0 24 24" fill="rgba(165,214,167,0.4)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>
  <svg class="leaf" style="width:16px;left:25%;animation-duration:16s;animation-delay:5s;" viewBox="0 0 24 24" fill="rgba(102,187,106,0.35)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>

  <div class="hero-inner">
    <!-- LEFT -->
    <div class="hero-left">
      <div class="hero-eyebrow">🌾 For Filipino Farmers & Vendors</div>

      <h1 class="hero-title">
        Manage Your<br>
        <em>Farm Sales</em><br>
        <span class="gold">Digitally.</span>
      </h1>

      <p class="hero-desc">
        No more lost notebooks or guesswork. ANI-TRACK gives you a <strong>complete digital system</strong> to record sales, track inventory, and manage customers — built for the Filipino farm market.
      </p>

      <div class="hero-ctas">
        <a href="frontend/auth/register.php" class="cta-main">
          Start for Free
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12,5 19,12 12,19"/></svg>
        </a>
        <a href="frontend/auth/login.php" class="cta-secondary">
          Sign In
        </a>
      </div>

      <div class="hero-stats">
        <div>
          <div class="hero-stat-num">100%</div>
          <div class="hero-stat-label">Free to Use</div>
        </div>
        <div class="hero-stat-divider"></div>
        <div>
          <div class="hero-stat-num">3-in-1</div>
          <div class="hero-stat-label">Sales · Inventory · Customers</div>
        </div>
        <div class="hero-stat-divider"></div>
        <div>
          <div class="hero-stat-num">₱0</div>
          <div class="hero-stat-label">Setup Cost</div>
        </div>
      </div>
    </div>


  </div>
</section>

<!-- FEATURES -->
<section class="features">
  <div class="section-label">What We Offer</div>
  <h2 class="section-title">Everything You Need to<br><em>Run Your Farm Business</em></h2>
  <p class="section-sub">From recording your first sale to tracking your best-selling products — ANI-TRACK handles it all in one simple dashboard.</p>

  <div class="features-grid">
    <div class="feature-card reveal">
      <div class="feat-num">01</div>
      <div class="feat-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </div>
      <div class="feat-card-title">Sales Tracking</div>
      <div class="feat-card-desc">Record every sale with product name, quantity, price, and customer details. View your full transaction history with date filters and total revenue summaries.</div>
    </div>
    <div class="feature-card reveal reveal-delay-1">
      <div class="feat-num">02</div>
      <div class="feat-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
      </div>
      <div class="feat-card-title">Inventory Management</div>
      <div class="feat-card-desc">Add your products with categories, units, and prices. Get automatic low-stock alerts and see the total value of your inventory at a glance.</div>
    </div>
    <div class="feature-card reveal reveal-delay-2">
      <div class="feat-num">03</div>
      <div class="feat-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="feat-card-title">Customer Records</div>
      <div class="feat-card-desc">Keep a digital record of all your buyers — name, contact details, and address. Link customers to sales so you always know who's buying what.</div>
    </div>
    <div class="feature-card reveal">
      <div class="feat-num">04</div>
      <div class="feat-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      </div>
      <div class="feat-card-title">Live Dashboard</div>
      <div class="feat-card-desc">See your total revenue, top products, recent transactions, and low stock warnings all on one screen the moment you log in.</div>
    </div>
    <div class="feature-card reveal reveal-delay-1">
      <div class="feat-num">05</div>
      <div class="feat-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <div class="feat-card-title">Secure & Private</div>
      <div class="feat-card-desc">Your data is yours. Every account is separate — your sales and customers are only visible to you, protected by secure login and hashed passwords.</div>
    </div>
    <div class="feature-card reveal reveal-delay-2">
      <div class="feat-num">06</div>
      <div class="feat-card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>
      </div>
      <div class="feat-card-title">Stock Auto-Deduction</div>
      <div class="feat-card-desc">When you record a sale from your inventory, stock is automatically deducted. Delete a sale and the stock comes right back. No manual counting needed.</div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how">
  <div class="how-inner">
    <div class="section-label" style="color:var(--g6);">How It Works</div>
    <h2 class="section-title">Up and Running in<br><em style="color:var(--g5);">Three Simple Steps</em></h2>
    <p class="section-sub">No complicated setup. No technical knowledge needed. Just create an account and start tracking.</p>

    <div class="steps">
      <div class="step reveal">
        <div class="step-num">1</div>
        <div class="step-title">Create Your Account</div>
        <div class="step-desc">Sign up as a Farmer or Vendor in under a minute. Fill in your basic info and you're ready to go.</div>
      </div>
      <div class="step reveal reveal-delay-1">
        <div class="step-num">2</div>
        <div class="step-title">Add Your Products</div>
        <div class="step-desc">Build your inventory by adding your products, setting prices, and defining stock quantities.</div>
      </div>
      <div class="step reveal reveal-delay-2">
        <div class="step-num">3</div>
        <div class="step-title">Record & Track Sales</div>
        <div class="step-desc">Record every transaction in seconds. Watch your dashboard update with live revenue and top products.</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="cta-inner">
    <div class="section-label">Ready to Start?</div>
    <h2 class="section-title">Your Farm Business,<br><em>Organized at Last</em></h2>
    <p class="section-sub">Join ANI-TRACK today and replace your paper notebooks with a clean, reliable digital system — completely free.</p>
    <div class="cta-pair">
      <a href="frontend/auth/register.php" class="cta-big">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        Create Free Account
      </a>
      <a href="frontend/auth/login.php" class="cta-alt">
        Already have an account? Sign In
      </a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-logo">ANI<span>TRACK</span></div>
  <div class="footer-text">© <?php echo date('Y'); ?> ANI-TRACK · Built for Filipino Farmers & Vendors</div>
  <div class="footer-tagline">Track Your Harvest, Grow Your Business</div>
</footer>

<script>
// Scroll reveal
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      observer.unobserve(e.target);
    }
  });
}, { threshold: 0.15 });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>
</body>
</html>