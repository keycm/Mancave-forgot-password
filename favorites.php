<?php
session_start();
include 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    // If accessed directly via URL, redirect. 
    // For AJAX calls, this might return the login page HTML, which is fine (handled by frontend check).
    header("Location: index.php?login=1");
    exit();
}

$user_id = $_SESSION['user_id'];
$loggedIn = true;

// --- 0. FETCH USER PROFILE PIC ---
$user_profile_pic = "";
$user_sql = "SELECT username, image_path FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result();
if ($user_data = $user_res->fetch_assoc()) {
    if (!empty($user_data['image_path'])) {
        $user_profile_pic = 'uploads/' . $user_data['image_path'];
    } else {
        $user_profile_pic = "https://ui-avatars.com/api/?name=" . urlencode($user_data['username']) . "&background=cd853f&color=fff&rounded=true&bold=true";
    }
}
$user_stmt->close();

// --- 1. ADD FAVORITE LOGIC ---
if (isset($_POST['add_id'])) {
    $art_id = intval($_POST['add_id']);
    
    // Check if it already exists to prevent duplicates
    $check_sql = "SELECT id FROM favorites WHERE user_id = ? AND artwork_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $art_id);
    $check_stmt->execute();
    $check_res = $check_stmt->get_result();

    if ($check_res->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, artwork_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $art_id);
        $stmt->execute();
    }
    // Exit to prevent loading the HTML below during an AJAX call
    exit('success');
}

// --- 2. REMOVE FAVORITE LOGIC ---
if (isset($_POST['remove_id'])) {
    $art_id = intval($_POST['remove_id']);
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND artwork_id = ?");
    $stmt->bind_param("ii", $user_id, $art_id);
    $stmt->execute();
    
    // If this was a standard form submit (from favorites page), refresh.
    // If AJAX, this redirect is ignored by the JS but the action still happens.
    if (!isset($_POST['ajax'])) {
        header("Location: favorites.php");
        exit();
    }
    exit('success');
}

// --- 3. FETCH FAVORITES FOR DISPLAY ---
$favorites = [];
$sql = "SELECT a.* FROM favorites f 
        JOIN artworks a ON f.artwork_id = a.id 
        WHERE f.user_id = ? 
        ORDER BY f.id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $favorites[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites | ManCave Gallery</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="home.css">

    <!-- Initialize Theme before page renders to prevent flashing -->
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    
    <style>
        /* Subpage Header Overrides - Matching index.php sizing and spacing exactly */
        .navbar:not(.scrolled) { 
            background: var(--bg-light) !important; 
            border-bottom: 1px solid var(--border-color) !important; 
        }
        .navbar:not(.scrolled) .nav-links a { color: var(--primary) !important; text-shadow: none !important; }
        .navbar:not(.scrolled) .header-icon-btn, 
        .navbar:not(.scrolled) .profile-pill { 
            background: #f8f8f8 !important; 
            border-color: #eee !important; 
            color: var(--primary) !important; 
        }
        .navbar:not(.scrolled) .profile-name, 
        .navbar:not(.scrolled) .fa-chevron-down, 
        .navbar:not(.scrolled) .mobile-menu-icon { 
            color: var(--primary) !important; 
        }

        /* Dark Mode: Match the exact transparent button look of index.php's top state */
        [data-theme="dark"] .navbar:not(.scrolled) { 
            background: var(--bg-dark) !important; 
            border-bottom: 1px solid rgba(255,255,255,0.05) !important; 
        }
        [data-theme="dark"] .navbar:not(.scrolled) .nav-links a { color: #ffffff !important; }
        [data-theme="dark"] .navbar:not(.scrolled) .header-icon-btn, 
        [data-theme="dark"] .navbar:not(.scrolled) .profile-pill { 
            background: rgba(255, 255, 255, 0.1) !important; 
            border-color: rgba(255, 255, 255, 0.2) !important; 
            color: #ffffff !important; 
        }
        [data-theme="dark"] .navbar:not(.scrolled) .profile-name { color: #ffffff !important; }
        [data-theme="dark"] .navbar:not(.scrolled) .fa-chevron-down { color: rgba(255, 255, 255, 0.7) !important; }
        [data-theme="dark"] .navbar:not(.scrolled) .mobile-menu-icon { color: #ffffff !important; }

        /* Ensure active favorite heart icon styling sticks out */
        .header-icon-btn .fa-heart { color: var(--accent) !important; }

        /* PAGE CONTENT */
        .page-header-min { padding-top: 130px; padding-bottom: 40px; border-bottom: 1px solid var(--border-color); margin-bottom: 40px; }
        .page-title { font-family: var(--font-head); font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; }
        .breadcrumb { font-size: 0.9rem; color: var(--secondary); }
        .breadcrumb a { font-weight: 700; color: var(--primary); }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        /* Grid */
        .collection-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 40px 30px; padding-bottom: 60px; }

        /* Remove Button */
        .btn-remove-float {
            position: absolute; top: 10px; right: 10px;
            background: rgba(255,255,255,0.9); border: none;
            width: 35px; height: 35px; border-radius: 50%;
            color: #ff4d4d; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center;
            transition: 0.3s;
        }
        .btn-remove-float:hover { background: #ff4d4d; color: white; transform: scale(1.1); }
        [data-theme="dark"] .btn-remove-float { background: rgba(30,30,30,0.9); box-shadow: 0 2px 5px rgba(0,0,0,0.5); }

        /* Empty State */
        .empty-state { text-align: center; padding: 100px 0; grid-column: 1/-1; }
        .empty-icon { font-size: 4rem; color: var(--border-color); margin-bottom: 20px; }
        .btn-outline-dark { border: 2px solid var(--primary); color: var(--primary); padding: 10px 30px; border-radius: 50px; font-weight: 700; transition: 0.3s; }
        .btn-outline-dark:hover { background: var(--primary); color: var(--white); }

        /* --- CUSTOM MODAL ALERT CSS --- */
        .custom-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.4); 
            display: flex; align-items: center; justify-content: center;
            z-index: 9999;
            opacity: 0; visibility: hidden;
            transition: all 0.3s ease;
        }
        .custom-modal-overlay.active {
            opacity: 1; visibility: visible;
        }
        .custom-modal-box {
            background: var(--white);
            border-radius: 16px;
            padding: 40px 30px;
            width: 90%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateY(20px) scale(0.95);
            transition: all 0.3s ease;
        }
        [data-theme="dark"] .custom-modal-box { box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .custom-modal-overlay.active .custom-modal-box {
            transform: translateY(0) scale(1);
        }
        .custom-modal-icon {
            width: 64px; height: 64px;
            background: #fee2e2; 
            color: #ef4444; 
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px auto;
        }
        [data-theme="dark"] .custom-modal-icon { background: rgba(239, 68, 68, 0.15); }
        .custom-modal-title {
            font-family: var(--font-head);
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 12px;
            font-weight: 700;
        }
        .custom-modal-text {
            font-family: var(--font-main);
            font-size: 1.05rem;
            color: var(--secondary);
            margin-bottom: 30px;
        }
        .custom-modal-actions {
            display: flex;
            gap: 15px;
        }
        .custom-modal-btn {
            flex: 1;
            padding: 12px 0;
            border: none;
            border-radius: 8px;
            font-family: var(--font-main);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .cancel-btn {
            background: var(--bg-light);
            color: var(--primary);
        }
        .cancel-btn:hover {
            background: var(--border-color);
        }
        .confirm-btn {
            background: #3b82f6; 
            color: #fff;
        }
        .confirm-btn:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="container nav-container">
            <a href="./" class="logo">
                <img src="uploads/logo.png" alt="The ManCave Gallery" onerror="this.onerror=null; this.src='LOGOS.png';">
            </a>
            <ul class="nav-links" id="navLinks">
                <li><a href="index.php#home">Home</a></li>
                <li><a href="index.php#gallery">Collection</a></li>
                <li><a href="index.php#artists">Artists</a></li>
                <li><a href="index.php#services">Services</a></li>
                <li><a href="index.php#news">News</a></li>
                <li><a href="index.php#contact-form">Visit</a></li>
            </ul>
            <div class="nav-actions">
                <button class="header-icon-btn" id="themeToggle" title="Toggle Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>

                <a href="favorites.php" class="header-icon-btn" title="My Favorites"> <i class="far fa-heart"></i>
                </a>
                <div class="notification-wrapper">
                    <button class="header-icon-btn" id="notifBtn" title="Notifications">
                        <i class="far fa-bell"></i>
                        <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span>Notifications</span>
                            <button id="markAllRead" class="small-btn">Mark all read</button>
                        </div>
                        <ul class="notif-list" id="notifList">
                            <li class="no-notif">Loading...</li>
                        </ul>
                    </div>
                </div>
                <div class="user-dropdown">
                    <div class="profile-pill">
                        <img src="<?php echo htmlspecialchars($user_profile_pic); ?>" alt="Profile" class="profile-img">
                        <span class="profile-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: rgba(255,255,255,0.7);"></i>
                    </div>
                    <div class="dropdown-content">
                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="admin.php"><i class="fas fa-cog"></i> Dashboard</a> <?php endif; ?>
                        <a href="profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a> <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a> </div>
                </div>
                
                <div class="mobile-menu-icon" onclick="toggleMobileMenu()"><i class="fas fa-bars"></i></div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header-min">
            <div class="breadcrumb"><a href="index.php">Home</a> / Favorites</div>
            <h1 class="page-title">My Collection</h1>
            <p style="color: var(--secondary);">Your personally curated selection of artworks.</p>
        </div>

        <?php if (empty($favorites)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="far fa-heart"></i></div>
                <h3 style="color: var(--primary);">No favorites yet.</h3>
                <p style="margin-bottom:30px; color:var(--secondary);">Go explore the collection and save what inspires you.</p>
                <a href="collection.php" class="btn-outline-dark">Browse Collection</a>
            </div>
        <?php else: ?>
            <div class="collection-grid">
                <?php foreach ($favorites as $art): 
                    $imgSrc = !empty($art['image_path']) ? 'uploads/'.$art['image_path'] : 'https://placehold.co/600x800?text=Art';
                ?>
                <div class="art-card-new" data-aos="fade-up">
                    <div class="art-img-wrapper-new">
                        
                        <form method="POST" onsubmit="showDeleteModal(event, this);">
                            <input type="hidden" name="remove_id" value="<?php echo $art['id']; ?>">
                            <button type="submit" class="btn-remove-float" title="Remove Favorite">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>

                        <a href="artwork_details.php?id=<?php echo $art['id']; ?>" class="art-link-wrapper">
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($art['title']); ?>">
                        </a>
                    </div>
                    
                    <div class="art-content-new">
                        <div class="art-title-new"><?php echo htmlspecialchars($art['title']); ?></div>
                        <div class="art-meta-new">
                            <span class="artist-name-new"><?php echo htmlspecialchars($art['artist']); ?></span>
                        </div>
                        <div class="art-footer-new">
                            <span class="price-new">Php <?php echo number_format($art['price']); ?></span>
                            <a href="artwork_details.php?id=<?php echo $art['id']; ?>" class="btn-circle" title="View Details">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- CUSTOM DELETE MODAL HTML -->
    <div id="custom-delete-modal" class="custom-modal-overlay" onclick="closeDeleteModalOnOutside(event)">
        <div class="custom-modal-box">
            <div class="custom-modal-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h2 class="custom-modal-title">Remove Favorite</h2>
            <p class="custom-modal-text">Remove this artwork from your favorites?</p>
            <div class="custom-modal-actions">
                <button type="button" class="custom-modal-btn cancel-btn" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="custom-modal-btn confirm-btn" id="confirmDeleteBtn">Remove</button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, offset: 50 });

        // --- Custom Modal Logic ---
        let formToSubmit = null;

        function showDeleteModal(event, form) {
            event.preventDefault(); 
            formToSubmit = form;
            const modal = document.getElementById('custom-delete-modal');
            modal.classList.add('active');
        }

        function closeDeleteModal() {
            const modal = document.getElementById('custom-delete-modal');
            modal.classList.remove('active');
            formToSubmit = null;
        }

        function closeDeleteModalOnOutside(event) {
            if (event.target.id === 'custom-delete-modal') {
                closeDeleteModal();
            }
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (formToSubmit) {
                formToSubmit.submit(); 
            }
        });

        // --- THEME TOGGLE LOGIC ---
        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            const themeIcon = themeBtn.querySelector('i');
            
            // Re-apply icon correctly upon script load
            if (localStorage.getItem('theme') === 'dark') {
                themeIcon.classList.replace('fa-moon', 'fa-sun');
            }

            themeBtn.addEventListener('click', () => {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'light');
                    themeIcon.classList.replace('fa-sun', 'fa-moon');
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                    themeIcon.classList.replace('fa-moon', 'fa-sun');
                }
            });
        }

        // --- BURGER MENU TOGGLE ---
        window.toggleMobileMenu = function() {
            const navLinks = document.getElementById('navLinks');
            if(navLinks) navLinks.classList.toggle('active');
        };

        // --- NAVBAR & HEADER ---
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if(window.scrollY > 50) navbar.classList.add('scrolled');
            else navbar.classList.remove('scrolled');
        });

        // Header & Notification Logic
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const userDropdown = document.querySelector('.user-dropdown');
            const profilePill = document.querySelector('.profile-pill');
            const notifBadge = document.getElementById('notifBadge');
            const notifList = document.getElementById('notifList');
            const markAllBtn = document.getElementById('markAllRead');

            if (profilePill && userDropdown) {
                profilePill.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    if (notifDropdown) notifDropdown.classList.remove('active');
                });
            }

            if (notifBtn) {
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('active');
                    if (userDropdown) userDropdown.classList.remove('active');
                });
                
                // Fetch Notifications matching Index.php implementation
                function fetchNotifications() {
                    fetch('fetch_notifications.php')
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                if (data.unread_count > 0) {
                                    notifBadge.innerText = data.unread_count;
                                    notifBadge.style.display = 'block';
                                } else {
                                    notifBadge.style.display = 'none';
                                }
                                notifList.innerHTML = '';
                                if (data.notifications.length === 0) {
                                    notifList.innerHTML = '<li class="no-notif">No new notifications</li>';
                                } else {
                                    data.notifications.forEach(notif => {
                                        const item = document.createElement('li');
                                        item.className = `notif-item ${notif.is_read == 0 ? 'unread' : ''}`;
                                        item.innerHTML = `
                                            <div class="notif-msg">${notif.message}</div>
                                            <div class="notif-time">${notif.created_at}</div>
                                            <button class="btn-notif-close" title="Delete">×</button>
                                        `;
                                        
                                        // Mark Read on Click
                                        item.addEventListener('click', (e) => {
                                            if (e.target.classList.contains('btn-notif-close')) return;
                                            const formData = new FormData();
                                            formData.append('id', notif.id);
                                            fetch('mark_as_read.php', { method: 'POST', body: formData }) 
                                                .then(() => fetchNotifications());
                                        });

                                        // Delete Notification
                                        item.querySelector('.btn-notif-close').addEventListener('click', (e) => {
                                            e.stopPropagation();
                                            if(!confirm('Delete this notification?')) return;
                                            const formData = new FormData();
                                            formData.append('id', notif.id);
                                            fetch('delete_notifications.php', { method: 'POST', body: formData }) 
                                                .then(res => res.json())
                                                .then(d => { if(d.status === 'success') fetchNotifications(); });
                                        });

                                        notifList.appendChild(item);
                                    });
                                }
                            }
                        });
                }

                if (markAllBtn) {
                    markAllBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        fetch('mark_all_as_read.php', { method: 'POST' }) 
                            .then(() => fetchNotifications());
                    });
                }

                fetchNotifications();
                setInterval(fetchNotifications, 30000);
            }

            window.addEventListener('click', () => {
                if (notifDropdown) notifDropdown.classList.remove('active');
                if (userDropdown) userDropdown.classList.remove('active');
            });
            if (userDropdown) userDropdown.addEventListener('click', (e) => e.stopPropagation());
            if (notifDropdown) notifDropdown.addEventListener('click', (e) => e.stopPropagation());
        });
    </script>
</body>
</html>