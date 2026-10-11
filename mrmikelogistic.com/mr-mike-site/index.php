<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';
$services = ['Airport transfer', 'City ride', 'Interstate ride', 'Outstation trip', 'Errand / business trip'];
$statuses = ['New', 'Confirmed', 'In progress', 'Completed', 'Cancelled'];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$bookingSuccessPopup = !empty($_SESSION['booking_success_popup']);
unset($_SESSION['booking_success_popup']);
$view = isset($_GET['admin']) ? 'admin' : 'home';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'book') {
            $name = trim((string) ($_POST['customer_name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $service = (string) ($_POST['service_type'] ?? '');
            $pickup = trim((string) ($_POST['pickup_location'] ?? ''));
            $dropoff = trim((string) ($_POST['dropoff_location'] ?? ''));
            $date = (string) ($_POST['travel_date'] ?? '');
            $time = (string) ($_POST['travel_time'] ?? '');
            $passengers = filter_var($_POST['passengers'] ?? 1, FILTER_VALIDATE_INT);
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $validDate = DateTime::createFromFormat('Y-m-d', $date);
            if (!$name || !$phone || !$pickup || !$dropoff || !in_array($service, $services, true) || !$validDate || $validDate->format('Y-m-d') !== $date || $date < date('Y-m-d') || !preg_match('/^\d{2}:\d{2}$/', $time) || !$passengers || $passengers < 1 || $passengers > 12 || ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                throw new RuntimeException('Please check the booking details and try again.');
            }
            $ref = 'MM' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = db()->prepare('INSERT INTO bookings (reference_code,customer_name,phone,email,service_type,pickup_location,dropoff_location,travel_date,travel_time,passengers,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$ref, $name, $phone, $email ?: null, $service, $pickup, $dropoff, $date, $time, $passengers, $notes ?: null]);
            $_SESSION['flash'] = ['kind' => 'success', 'text' => "Booking received. Your reference is {$ref}. We’ll contact you to confirm the ride."];
            $_SESSION['booking_success_popup'] = true;
            header('Location: index.php#book');
            exit;
        }
        if ($action === 'login') {
            $stmt = db()->prepare('SELECT id,name,password_hash FROM admins WHERE email=? LIMIT 1');
            $stmt->execute([strtolower(trim((string) ($_POST['email'] ?? '')))]);
            $admin = $stmt->fetch();
            if (!$admin || !password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash']))
                throw new RuntimeException('Email or password is incorrect.');
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            header('Location: index.php?admin=1');
            exit;
        }
        if ($action === 'logout') {
            unset($_SESSION['admin_id'], $_SESSION['admin_name']);
            session_regenerate_id(true);
            header('Location: index.php?admin=1');
            exit;
        }
        if (!is_admin()) {
            http_response_code(403);
            exit('Admin login required.');
        }
        if ($action === 'update') {
            $id = filter_var($_POST['booking_id'] ?? 0, FILTER_VALIDATE_INT);
            $status = (string) ($_POST['status'] ?? '');
            if (!$id || !in_array($status, $statuses, true))
                throw new RuntimeException('Invalid booking update.');
            db()->prepare('UPDATE bookings SET status=? WHERE id=?')->execute([$status, $id]);
            $_SESSION['flash'] = ['kind' => 'success', 'text' => 'Booking status updated.'];
            header('Location: index.php?admin=1');
            exit;
        }
        if ($action === 'delete') {
            $id = filter_var($_POST['booking_id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id)
                throw new RuntimeException('Invalid booking.');
            db()->prepare('DELETE FROM bookings WHERE id=?')->execute([$id]);
            $_SESSION['flash'] = ['kind' => 'success', 'text' => 'Booking deleted.'];
            header('Location: index.php?admin=1');
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['kind' => 'error', 'text' => ($e instanceof PDOException ? 'The request could not be saved. Check that MySQL is configured and try again.' : $e->getMessage())];
        header('Location: ' . ($view === 'admin' || is_admin() ? 'index.php?admin=1' : 'index.php#book'));
        exit;
    }
}

$bookings = [];
$metrics = ['total' => 0, 'new' => 0, 'upcoming' => 0, 'completed' => 0];
if ($view === 'admin' && is_admin()) {
    try {
        $bookings = db()->query('SELECT * FROM bookings ORDER BY created_at DESC LIMIT 150')->fetchAll();
        $m = db()->query("SELECT COUNT(*) total, SUM(status='New') new_count, SUM(status IN ('New','Confirmed','In progress') AND travel_date >= CURDATE()) upcoming, SUM(status='Completed') completed FROM bookings")->fetch();
        $metrics = ['total' => (int) $m['total'], 'new' => (int) $m['new_count'], 'upcoming' => (int) $m['upcoming'], 'completed' => (int) $m['completed']];
    } catch (Throwable $e) {
        $flash = ['kind' => 'error', 'text' => 'Database unavailable. Import schema.sql and check your DB settings.'];
    }
}
?><!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10191b">
    <title><?= $view === 'admin' ? 'Admin dashboard' : 'MR MIKE LOGISTICS | Rides across Nigeria' ?></title>
    <link rel="stylesheet" href="styles.css">
</head>

<body class="<?= $view === 'admin' ? 'admin-page' : '' ?>">
    <?php if ($view === 'admin'): ?>
        <?php if ($flash): ?>
            <div class="toast <?= h($flash['kind']) ?>"><?= h($flash['text']) ?></div><?php endif; ?>
        <?php if (!is_admin()): ?>
            <main class="login-wrap"><a class="brand" href="index.php"><img class="brand-logo" src="mr-mike-logistics-logo.svg" alt="MR MIKE LOGISTICS"></a>
                <section class="login-card">
                    <p class="eyebrow">OPERATIONS PORTAL</p>
                    <h1>Welcome back.</h1>
                    <p class="muted">Sign in to manage bookings and trips.</p>
                    <form method="post" class="form-stack"><input type="hidden" name="csrf"
                            value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="login"><label>Email
                            address<input required type="email" name="email"
                                autocomplete="username"></label><label>Password<input required type="password" name="password"
                                autocomplete="current-password"></label><button class="btn btn-dark full">Sign in
                            <span>→</span></button></form><a class="back-link" href="index.php">← Back to website</a>
                </section>
            </main>
        <?php else: ?>
            <div class="admin-layout">
                <aside class="sidebar"><a class="brand" href="index.php"><img class="brand-logo" src="mr-mike-logistics-logo.svg" alt="MR MIKE LOGISTICS"></a>
                    <p class="nav-label">WORKSPACE</p><a class="nav-item active" href="index.php?admin=1"><span>▦</span>
                        Bookings</a><a class="nav-item" href="index.php"><span>↗</span> View website</a>
                    <div class="sidebar-bottom">
                        <div class="avatar"><?= h(strtoupper(substr((string) $_SESSION['admin_name'], 0, 1))) ?></div>
                        <div><strong><?= h((string) $_SESSION['admin_name']) ?></strong><small>Administrator</small></div>
                        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden"
                                name="action" value="logout"><button class="icon-button" title="Sign out">↪</button></form>
                    </div>
                </aside>
                <main class="dashboard">
                    <div class="dash-top">
                        <div>
                            <p class="eyebrow">OPERATIONS / BOOKINGS</p>
                            <h1>Good day, <?= h((string) $_SESSION['admin_name']) ?>.</h1>
                            <p class="muted">Here’s what’s happening with your rides.</p>
                        </div><a class="btn btn-yellow" href="index.php#book">＋ New booking</a>
                    </div>
                    <div class="metric-grid">
                        <article class="metric"><span>Total bookings</span><strong><?= $metrics['total'] ?></strong><small>All
                                time</small><i>▦</i></article>
                        <article class="metric"><span>Needs attention</span><strong><?= $metrics['new'] ?></strong><small>New
                                requests</small><i class="gold">◷</i></article>
                        <article class="metric"><span>Upcoming
                                rides</span><strong><?= $metrics['upcoming'] ?></strong><small>Active and
                                scheduled</small><i>↗</i></article>
                        <article class="metric">
                            <span>Completed</span><strong><?= $metrics['completed'] ?></strong><small>Successfully
                                delivered</small><i>✓</i></article>
                    </div>
                    <section class="table-card">
                        <div class="table-head">
                            <div>
                                <h2>Recent bookings</h2>
                                <p class="muted">Review requests and keep every trip on track.</p>
                            </div><span class="count-pill"><?= count($bookings) ?> shown</span>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>REFERENCE / CUSTOMER</th>
                                        <th>TRIP</th>
                                        <th>DATE & TIME</th>
                                        <th>PASSENGERS</th>
                                        <th>STATUS</th>
                                        <th>MANAGE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $b): ?>
                                        <tr>
                                            <td><strong><?= h($b['reference_code']) ?></strong><small><?= h($b['customer_name']) ?>
                                                    · <?= h($b['phone']) ?></small></td>
                                            <td><strong><?= h($b['service_type']) ?></strong><small><?= h($b['pickup_location']) ?>
                                                    → <?= h($b['dropoff_location']) ?></small><?php if ($b['notes']): ?><small>Note:
                                                        <?= h($b['notes']) ?></small><?php endif; ?></td>
                                            <td><?= h(date('M j, Y', strtotime($b['travel_date']))) ?><small><?= h(date('g:i A', strtotime($b['travel_time']))) ?></small>
                                            </td>
                                            <td><?= (int) $b['passengers'] ?></td>
                                            <td><span
                                                    class="status status-<?= strtolower(str_replace(' ', '-', h($b['status']))) ?>"><?= h($b['status']) ?></span>
                                            </td>
                                            <td>
                                                <div class="manage-cell">
                                                    <form method="post" class="inline-form"><input type="hidden" name="csrf"
                                                            value="<?= h(csrf_token()) ?>"><input type="hidden" name="action"
                                                            value="update"><input type="hidden" name="booking_id"
                                                            value="<?= (int) $b['id'] ?>"><select name="status"
                                                            aria-label="Update status"><?php foreach ($statuses as $s): ?>
                                                                <option <?= $s === $b['status'] ? 'selected' : '' ?>><?= h($s) ?></option>
                                                            <?php endforeach; ?>
                                                        </select><button class="mini-btn">Save</button></form>
                                                    <form method="post"
                                                        onsubmit="return confirm('Delete this booking permanently?')"><input
                                                            type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input
                                                            type="hidden" name="action" value="delete"><input type="hidden"
                                                            name="booking_id" value="<?= (int) $b['id'] ?>"><button
                                                            class="delete-btn" title="Delete booking">×</button></form>
                                                </div>
                                            </td>
                                        </tr><?php endforeach; ?>
                                    <?php if (!$bookings): ?>
                                        <tr>
                                            <td colspan="6" class="empty">No bookings yet. New requests will appear here.</td>
                                        </tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <p class="dash-foot">MR MIKE LOGISTICS <span>·</span> Operations dashboard</p>
                </main>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($flash): ?>
            <div class="toast <?= h($flash['kind']) ?>"><?= h($flash['text']) ?></div><?php endif; ?>
        <header class="site-header">
            <div class="nav-wrap"><a class="brand" href="#top"><img class="brand-logo" src="mr-mike-logistics-logo.svg" alt="MR MIKE LOGISTICS"></a>
                <nav><a href="#services">Services</a><a href="#how">How it works</a><a href="#contact">Contact</a></nav><a
                    class="btn btn-yellow nav-cta" href="#book">Book a ride <span>↗</span></a><button class="menu-toggle"
                    aria-label="Open menu">☰</button>
            </div>
        </header>
        <main id="top">
            <section class="hero">
                <div class="hero-grid">
                    <div class="hero-copy">
                        <p class="eyebrow"><span class="eyebrow-dot"></span> YOUR RIDE, YOUR WAY</p>
                        <h1>Go anywhere.<br><em>Arrive with ease.</em></h1>
                        <p class="hero-lede">Reliable rides and travel support across Nigeria. From airport pickups to
                            long-distance journeys, we’ll get you there safely and on time.</p>
                        <div class="hero-actions"><a class="btn btn-yellow" href="#book">Book your ride <span>→</span></a><a
                                class="text-link" href="tel:+2347036458543">Call us <b>+234 703 645 8543</b></a></div>
                        <div class="hero-proof">
                            <div class="proof-icon">✓</div><span><strong>Safe ride. Reliable service.</strong><small>Every
                                    trip, every time.</small></span>
                            <div class="proof-divider"></div>
                            <div class="proof-icon">◎</div><span><strong>Nationwide coverage</strong><small>City to city,
                                    door to door.</small></span>
                        </div>
                    </div>
                    <div class="hero-art">
                        <div class="art-glow"></div>
                        <div class="route-label label-airport"><span>✈</span>
                            <div><b>Airport transfers</b><small>On time, every time</small></div>
                        </div>
                        <img class="hero-car-photo" src="https://images.unsplash.com/photo-1699412948716-f5a2b28accd5?auto=format&amp;fit=crop&amp;w=1400&amp;q=85" alt="Black SUV on a city street" fetchpriority="high">
                        <div class="route-label label-city"><span>⌖</span>
                            <div><b>Across Nigeria</b><small>Wherever you need to go</small></div>
                        </div>
                        <div class="art-stamp">MM<span>LOGISTICS</span></div>
                    </div>
                </div>
                <div class="hero-bottom"><span>MADE FOR THE WAY YOU MOVE</span>
                    <div></div><span>SAFE RIDE <b>·</b> RELIABLE SERVICE <b>·</b> ON TIME</span>
                </div>
            </section>
            <section class="service-section" id="services">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">WHAT WE DO</p>
                        <h2>More than just a ride.</h2>
                    </div>
                    <p>Thoughtful transport for your everyday plans and the journeys that take you further.</p>
                </div>
                <div class="service-grid">
                    <article class="service-card"><span class="service-icon">✈</span><span class="service-number">01</span>
                        <h3>Airport transfers</h3>
                        <p>Reliable pickup and drop-off, planned around your flight.</p><a href="#book">Book airport ride
                            <b>↗</b></a>
                    </article>
                    <article class="service-card"><span class="service-icon">⌖</span><span class="service-number">02</span>
                        <h3>City rides</h3>
                        <p>Get around your city comfortably, whether it’s one stop or many.</p><a href="#book">Book a city
                            ride <b>↗</b></a>
                    </article>
                    <article class="service-card"><span class="service-icon">⇄</span><span class="service-number">03</span>
                        <h3>Interstate trips</h3>
                        <p>Go between cities with dependable, door-to-door service.</p><a href="#book">Plan your trip
                            <b>↗</b></a>
                    </article>
                    <article class="service-card"><span class="service-icon">✓</span><span class="service-number">04</span>
                        <h3>Outstation & errands</h3>
                        <p>Personal trips, business visits, and important tasks handled with care.</p><a href="#book">Tell
                            us what you need <b>↗</b></a>
                    </article>
                </div>
            </section>
            <section class="book-section" id="book">
                <div class="book-intro">
                    <p class="eyebrow">LET’S GET YOU THERE</p>
                    <h2>Your next trip<br>starts right here.</h2>
                    <p>Share a few details and our team will get back to confirm your ride and fare.</p>
                    <div class="contact-note"><span>☎</span>
                        <div><small>Prefer to talk?</small><a href="tel:+2347036458543">+234 703 645 8543</a></div>
                    </div>
                    <div class="book-promise">✦ <span>We’ll confirm availability and pricing before your trip is
                            finalized.</span></div>
                </div>
                <form method="post" class="booking-form" id="booking-form"><input type="hidden" name="csrf"
                        value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="book">
                    <div class="form-title">
                        <div><span>01</span>
                            <h3>Trip details</h3>
                        </div><small>* Required fields</small>
                    </div>
                    <div class="field-grid"><label class="span-2">Service type *<select name="service_type" required>
                                <option value="">Select a service</option><?php foreach ($services as $s): ?>
                                    <option><?= h($s) ?></option><?php endforeach; ?>
                            </select></label><label>Pickup location *<input required name="pickup_location"
                                placeholder="City, airport or address"></label><label>Drop-off location *<input required
                                name="dropoff_location" placeholder="Where are you headed?"></label><label>Travel date
                            *<input required type="date" name="travel_date" min="<?= date('Y-m-d') ?>"></label><label>Pickup
                            time *<input required type="time" name="travel_time"></label><label>Passengers *<select
                                name="passengers" required><?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?> passenger<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?>
                            </select></label></div>
                    <div class="form-title customer-title">
                        <div><span>02</span>
                            <h3>Your details</h3>
                        </div>
                    </div>
                    <div class="field-grid"><label>Your full name *<input required name="customer_name" autocomplete="name"
                                placeholder="e.g. Ada Okafor"></label><label>Phone number *<input required name="phone"
                                autocomplete="tel" placeholder="+234"></label><label class="span-2">Email address<input
                                type="email" name="email" autocomplete="email" placeholder="you@example.com"></label><label
                            class="span-2">Anything we should know?<textarea name="notes" rows="3"
                                placeholder="Flight number, extra stops, luggage…"></textarea></label></div><button
                        class="btn btn-dark submit-btn">Request this ride <span>→</span></button>
                    <p class="form-terms">Submitting a request doesn’t charge you. We’ll contact you to confirm.</p>
                </form>
            </section>
            <section class="steps-section" id="how">
                <p class="eyebrow">SIMPLE FROM START TO ARRIVAL</p>
                <h2>Three steps. One smooth ride.</h2>
                <div class="steps-grid">
                    <article><span>01</span>
                        <div class="step-icon">↗</div>
                        <h3>Tell us your plans</h3>
                        <p>Choose your service and share the trip details.</p>
                    </article>
                    <article><span>02</span>
                        <div class="step-icon">☎</div>
                        <h3>We confirm with you</h3>
                        <p>Our team checks availability and confirms your fare.</p>
                    </article>
                    <article><span>03</span>
                        <div class="step-icon">⌖</div>
                        <h3>Enjoy the journey</h3>
                        <p>Your driver arrives ready to get you there safely.</p>
                    </article>
                </div>
            </section>
            <section class="closing-cta" id="contact">
                <div>
                    <p class="eyebrow">READY WHEN YOU ARE</p>
                    <h2>Let’s make your next trip<br><em>a little easier.</em></h2>
                </div><a class="btn btn-yellow" href="#book">Book your ride <span>→</span></a>
            </section>
        </main>
        <footer class="site-footer">
            <div class="footer-main"><a class="brand" href="#top"><img class="brand-logo" src="mr-mike-logistics-logo.svg" alt="MR MIKE LOGISTICS"></a>
                <p>Getting you there, safely and on time.</p><a href="tel:+2347036458543">+234 703 645 8543</a>
            </div>
            <div class="footer-bottom"><span>© <?= date('Y') ?> MR MIKE LOGISTICS</span><span>SAFE RIDE · RELIABLE SERVICE ·
                    ON TIME</span><a href="index.php?admin=1">Admin login</a></div>
        </footer>
    <?php endif; ?>
    <?php if ($bookingSuccessPopup): ?>
    <script>window.alert("you have successfully book a ride, we will get back shortly");</script>
    <?php endif; ?>
    <script src="app.js"></script>
</body>

</html>
