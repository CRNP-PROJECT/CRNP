<?php
/**
 * about.php — public About Us page.
 * No auth required. Shows the story, stats, curated essentials, and location with a map.
 */
require_once __DIR__ . '/../init.php';
security_headers();

$pageTitle  = 'About Us';
$activeNav  = 'about';
$layout     = '';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
  .about-hero{border-radius:var(--radius-lg);background:linear-gradient(160deg,#2a2118,#181210);color:var(--on-dark);padding:48px 36px;position:relative;overflow:hidden;border:1px solid #3a3026;box-shadow:var(--shadow-lg);}
  .about-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 85% 20%,rgba(192,138,46,.28),transparent 45%),radial-gradient(circle at 10% 90%,rgba(111,43,43,.22),transparent 42%);}
  .about-hero>*{position:relative;z-index:1;}
  .about-hero h1{color:#fff;max-width:20ch;}
  .about-hero p{color:#cdbfa6;max-width:50ch;font-size:16px;}
  .about-stats{display:grid;grid-template-columns:1fr;gap:16px;}
  @media(min-width:480px){.about-stats{grid-template-columns:repeat(3,1fr);}}
  .about-stat{text-align:center;padding:28px 16px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-sm);position:relative;overflow:hidden;}
  .about-stat::after{content:"";position:absolute;right:-20px;top:-20px;width:80px;height:80px;border-radius:999px;background:radial-gradient(circle,var(--gold-100),transparent 70%);opacity:.4;}
  .about-stat__num{font-family:var(--serif);font-size:2.4rem;font-weight:700;color:var(--ink);line-height:1;position:relative;z-index:1;}
  .about-stat__label{font-size:13px;color:var(--muted);margin-top:8px;letter-spacing:.06em;text-transform:uppercase;position:relative;z-index:1;}
  .about-section{display:grid;grid-template-columns:1fr;gap:28px;align-items:center;}
  @media(min-width:768px){.about-section{grid-template-columns:1fr 1fr;gap:40px;}}
  .about-section__media{border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);aspect-ratio:4/3;background:var(--bg-2);}
  .about-section__media img{width:100%;height:100%;object-fit:cover;}
  .about-contact-card{display:grid;grid-template-columns:1fr;gap:20px;}
  @media(min-width:640px){.about-contact-card{grid-template-columns:1fr 1fr;}}
  .map-frame{border-radius:var(--radius);overflow:hidden;border:1px solid var(--line);box-shadow:var(--shadow-sm);aspect-ratio:4/3;}
  .map-frame iframe{width:100%;height:100%;border:0;display:block;}
  .contact-item{display:flex;gap:14px;align-items:flex-start;}
  .contact-item__icon{width:42px;height:42px;border-radius:11px;background:var(--gold-100);color:var(--gold-600);display:grid;place-items:center;flex-shrink:0;}
  .contact-item__icon svg{width:20px;height:20px;}
  .contact-item__label{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);}
  .contact-item__value{font-size:15px;color:var(--ink);font-weight:500;margin-top:2px;line-height:1.5;}
</style>

<?php
$s = get_settings();
?>
<!-- Hero -->
<section class="about-hero">
  <div class="eyebrow" style="color:#c8a45c;font-size:12px;letter-spacing:.22em;text-transform:uppercase;">About us</div>
  <h1 style="margin-top:10px;"><?= e($s['about_headline']) ?></h1>
  <p style="margin-top:14px;"><?= e($s['about_body']) ?></p>
</section>

<!-- Stats -->
<div class="about-stats" style="margin:-28px 0 48px;position:relative;z-index:10;">
  <div class="about-stat">
    <div class="about-stat__num"><?= e($s['about_stat1_num']) ?></div>
    <div class="about-stat__label"><?= e($s['about_stat1_lbl']) ?></div>
  </div>
  <div class="about-stat">
    <div class="about-stat__num"><?= e($s['about_stat2_num']) ?></div>
    <div class="about-stat__label"><?= e($s['about_stat2_lbl']) ?></div>
  </div>
  <div class="about-stat">
    <div class="about-stat__num"><?= e($s['about_stat3_num']) ?></div>
    <div class="about-stat__label"><?= e($s['about_stat3_lbl']) ?></div>
  </div>
</div>

<!-- Curated Essentials -->
<div class="about-section" style="margin-bottom:48px;">
  <div class="about-section__media">
    <img src="https://sfile.chatglm.cn/images-ppt/8027e7cffdc6.jpg" alt="Catered event setup with tables, chairs, and skirting" loading="lazy">
  </div>
  <div>
    <div class="eyebrow" style="font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold-600);">Curated Essentials</div>
    <h2 style="margin:.2em 0 .4em;">Everything you need for a memorable event.</h2>
    <p style="color:var(--ink-soft);font-size:15px;line-height:1.7;">We provide quality rental equipment for your special occasions including tables, chairs, and skirting cloths. Our commitment is to make your events memorable and hassle-free.</p>
    <a class="btn btn--gold" href="/user/booking.php" style="margin-top:18px;">Browse rentals &rarr;</a>
  </div>
</div>

<!-- Let's Connect + Visit Our Location -->
<div class="card card--pad-lg">
  <div class="card__head"><h3>Let's Connect</h3></div>
  <div class="card__body">
    <p style="color:var(--ink-soft);margin-bottom:24px;">For inquiries and reservations, feel free to contact us anytime. We're here to help make your event seamless.</p>

    <div class="about-contact-card">
      <!-- Contact info -->
      <div class="col" style="gap:20px;">
        <div class="contact-item">
          <span class="contact-item__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
          <div>
            <div class="contact-item__label">Visit Our Location</div>
            <div class="contact-item__value"><?= e($s['address']) ?></div>
          </div>
        </div>
        <div class="contact-item">
          <span class="contact-item__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
          <div>
            <div class="contact-item__label">Hours</div>
            <div class="contact-item__value"><?= e($s['hours']) ?><br><small class="muted">Closed on Mondays</small></div>
          </div>
        </div>
        <div class="contact-item">
          <span class="contact-item__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.36 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.34 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
          <div>
            <div class="contact-item__label">Reservations</div>
            <div class="contact-item__value"><?= e($s['phone']) ?><br><small class="muted">Call or message us on GCash</small></div>
          </div>
        </div>
      </div>

      <!-- Map -->
      <div>
        <div class="contact-item__label" style="margin-bottom:10px;">Find us on the map</div>
        <div class="map-frame">
          <iframe src="https://maps.google.com/maps?q=Mabolo%2C+Iloilo+City+Proper%2C+Iloilo+City%2C+Philippines&z=15&output=embed" title="<?= e(BRAND_NAME) ?> location in Iloilo City" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
