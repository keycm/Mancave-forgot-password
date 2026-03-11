<?php
session_start();
include 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?login=1");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Update General Profile & Avatar
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // Handle File Upload
        $imageUpdateSQL = "";
        $params = [$username, $email];
        $types = "ss";

        if (!empty($_FILES['profile_image']['name'])) {
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            
            $fileName = time() . '_' . basename($_FILES['profile_image']['name']);
            $targetFilePath = $targetDir . $fileName;
            
            $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
            $allowTypes = array('jpg','png','jpeg','gif');
            
            if (in_array(strtolower($fileType), $allowTypes)) {
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
                    $imageUpdateSQL = ", image_path = ?";
                    $params[] = $fileName;
                    $types .= "s";
                } else {
                    $msg = "Error uploading image.";
                    $msg_type = "error";
                }
            } else {
                $msg = "Invalid file type. Only JPG, PNG & GIF allowed.";
                $msg_type = "error";
            }
        }

        if (empty($msg)) {
            $params[] = $user_id;
            $types .= "i";
            
            $sql = "UPDATE users SET username = ?, email = ? $imageUpdateSQL WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $_SESSION['username'] = $username;
                $msg = "Profile updated successfully!";
                $msg_type = "success";
            } else {
                $msg = "Error updating database: " . $conn->error;
                $msg_type = "error";
            }
        }
    }

    // 2. Change Password
    if (isset($_POST['change_password'])) {
        $current_pass = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        // Verify Current Password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user_data = $res->fetch_assoc();

        if (!password_verify($current_pass, $user_data['password'])) {
            $msg = "Current password is incorrect.";
            $msg_type = "error";
        } elseif (strlen($new_pass) < 8) {
            $msg = "New password must be at least 8 characters.";
            $msg_type = "error";
        } elseif ($new_pass !== $confirm_pass) {
            $msg = "New passwords do not match.";
            $msg_type = "error";
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $user_id);
            if ($stmt->execute()) {
                $msg = "Password changed successfully!";
                $msg_type = "success";
            } else {
                $msg = "Error updating password.";
                $msg_type = "error";
            }
        }
    }
}

// Fetch User Data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$profileImg = !empty($user['image_path']) 
    ? 'uploads/' . $user['image_path'] 
    : "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=cd853f&color=fff&rounded=true&bold=true";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings | ManCave Gallery</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="home.css">

    <!-- Initialize Theme before page renders -->
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>

    <style>
        /* Navbar Subpage Overrides - Matching Favorites.php */
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

        /* Dark Mode Consistency */
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

        /* Page Layout Customizations */
        .settings-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 30px;
            max-width: 1200px;
            margin: 130px auto 60px;
            padding: 0 20px;
            align-items: start;
        }

        .settings-nav { 
            background: var(--white); 
            padding: 15px; 
            border-radius: 12px; 
            border: 1px solid var(--border-color); 
            position: sticky; 
            top: 100px; 
        }
        .settings-nav a { 
            display: flex; align-items: center; gap: 10px; padding: 12px; 
            color: var(--secondary); font-weight: 700; border-radius: 8px; 
            margin-bottom: 5px; transition: 0.2s; font-size: 0.9rem; 
        }
        .settings-nav a:hover { background: var(--bg-light); color: var(--primary); }
        .settings-nav a.active { background: var(--accent); color: #fff; }

        .settings-content { 
            background: var(--white); 
            border: 1px solid var(--border-color); 
            padding: 35px; 
            border-radius: 12px; 
            box-shadow: var(--shadow-soft); 
        }
        
        .settings-header { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
        .settings-header h2 { font-family: var(--font-head); margin: 0 0 5px; font-size: 2rem; color: var(--primary); }

        .profile-header-card {
            display: flex; align-items: center; gap: 25px; margin-bottom: 35px;
            background: var(--bg-light); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color);
        }
        .avatar-preview { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid var(--white); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        .forms-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .form-section h4 { font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 15px; border-left: 3px solid var(--accent); padding-left: 10px; }
        
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: var(--secondary); font-size: 0.85rem; }
        .form-group input { 
            width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); 
            border-radius: 8px; background: var(--bg-light); color: var(--primary); 
            transition: 0.3s; font-family: var(--font-main); 
        }
        .form-group input:focus { border-color: var(--accent); outline: none; }

        /* Save Changes Button Styles */
        .btn-save { 
            background: #121212; /* Keep button dark for visibility */
            color: #fff; 
            padding: 12px 30px; 
            border-radius: 50px; 
            font-weight: 700; 
            border: none; 
            cursor: pointer; 
            transition: 0.3s; 
            width: 100%; 
            margin-top: 10px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .btn-save:hover { background: var(--accent); transform: translateY(-2px); }

        /* Dark Mode Button Override */
        [data-theme="dark"] .btn-save {
            background: #2a2a2a; /* Visible dark gray in dark mode */
            border: 1px solid #444;
        }

        .btn-upload { 
            margin-top: 10px; border: 1px solid var(--border-color); background: var(--white); 
            padding: 8px 18px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; 
            cursor: pointer; color: var(--primary); 
        }

        @media (max-width: 992px) {
            .settings-layout { grid-template-columns: 1fr; margin-top: 110px; }
            .forms-grid { grid-template-columns: 1fr; }
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
                        <img src="<?php echo htmlspecialchars($profileImg); ?>" alt="Profile" class="profile-img">
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

    <div class="settings-layout">
        <aside class="settings-nav">
            <a href="profile.php" class="active"><i class="fas fa-user-circle"></i> General Info</a>
            <a href="favorites.php"><i class="far fa-heart"></i> My Favorites</a>
            <a href="logout.php" style="color:var(--brand-red); margin-top:15px; border-top:1px solid var(--border-color); border-radius:0; padding-top:15px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <main class="settings-content">
            <div class="settings-header">
                <h2>Account Settings</h2>
                <p>Manage your gallery profile and security credentials.</p>
            </div>

            <?php if($msg): ?>
                <div class="alert-error" style="background: <?php echo ($msg_type == 'success') ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo ($msg_type == 'success') ? '#16a34a' : '#dc2626'; ?>; border: 1px solid <?php echo ($msg_type == 'success') ? '#bbf7d0' : '#fecaca'; ?>; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: 700;">
                    <i class="fas <?php echo ($msg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                
                <div class="profile-header-card">
                    <img src="<?php echo htmlspecialchars($profileImg); ?>" class="avatar-preview" id="avatarPreview">
                    <div class="user-meta">
                        <h4 style="margin:0; font-size:1.5rem; color:var(--primary);"><?php echo htmlspecialchars($user['username']); ?></h4>
                        <span style="font-size:0.8rem; color:var(--accent); font-weight:700; text-transform:uppercase; letter-spacing:1px;"><?php echo htmlspecialchars($user['role'] === 'admin' ? 'Administrator' : 'Collector'); ?></span>
                        <div>
                            <button class="btn-upload" type="button" onclick="document.getElementById('fileInput').click()">Change Portrait</button>
                            <input type="file" id="fileInput" name="profile_image" style="display:none;" onchange="previewImage(event)" accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="forms-grid">
                    <div class="form-section">
                        <h4>Identity</h4>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
                    </div>

                    <div class="form-section">
                        <h4>Security</h4>
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" placeholder="Verify to update">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" placeholder="Minimum 8 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Repeat new password">
                        </div>
                        <button type="submit" name="change_password" class="btn-save" style="background:#2a2a2a; border:1px solid #444;">Update Security</button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Image Preview
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function(){
                const output = document.getElementById('avatarPreview');
                output.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }

        // --- THEME TOGGLE LOGIC ---
        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            const themeIcon = themeBtn.querySelector('i');
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

        // Burger Menu
        window.toggleMobileMenu = function() {
            const navLinks = document.getElementById('navLinks');
            if(navLinks) navLinks.classList.toggle('active');
        };

        // Header Logic
        document.addEventListener('DOMContentLoaded', () => {
            const notifBtn = document.getElementById('notifBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            const userDropdown = document.querySelector('.user-dropdown');
            const profilePill = document.querySelector('.profile-pill');
            const notifBadge = document.getElementById('notifBadge');
            const notifList = document.getElementById('notifList');
            const markAllBtn = document.getElementById('markAllRead');

            if (profilePill) {
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
                                        
                                        item.addEventListener('click', (e) => {
                                            if (e.target.classList.contains('btn-notif-close')) return;
                                            const formData = new FormData();
                                            formData.append('id', notif.id);
                                            fetch('mark_as_read.php', { method: 'POST', body: formData }) 
                                                .then(() => fetchNotifications());
                                        });

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
        });

        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if(window.scrollY > 50) navbar.classList.add('scrolled');
            else navbar.classList.remove('scrolled');
        });
    </script>
</body>
</html>