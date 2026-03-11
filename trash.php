<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// --- FILTERING LOGIC ---
$filterSource = $_GET['source'] ?? 'all';
$sortOrder = $_GET['sort'] ?? 'desc';

$whereSQL = "1";
if ($filterSource !== 'all') {
    $sourceEscaped = mysqli_real_escape_string($conn, $filterSource);
    $whereSQL .= " AND source = '$sourceEscaped'";
}

$orderSQL = ($sortOrder === 'asc') ? "ASC" : "DESC";

$trash_items = [];
$sql = "SELECT * FROM trash_bin WHERE $whereSQL ORDER BY deleted_at $orderSQL";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $trash_items[] = $row;
    }
}

// Time-based greeting
$hour = date('H');
if ($hour < 12) $greeting = "Good Morning";
elseif ($hour < 18) $greeting = "Good Afternoon";
else $greeting = "Good Evening";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin | ManCave Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Playfair+Display:wght@600;700&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* =========================================
           UNIFORM ADMIN THEME (Matches admin.php)
           ========================================= */
        :root {
            --bg-body: #f8f9fc;
            --text-main: #333333;
            --text-muted: #888888;
            --border-color: rgba(0,0,0,0.05);
            --card-bg: #ffffff;
            --shadow-color: rgba(0,0,0,0.05);
            --input-bg: #ffffff;

            --sidebar-bg: #ffffff; 
            --sidebar-text: #666666;
            --sidebar-active-bg: #cd853f;
            --sidebar-active-text: #ffffff;
            --sidebar-hover: #fff5eb;
            --logo-color: #333333;
            
            --accent: #cd853f;
            --accent-hover: #b07236;
            
            --green: #10b981;
            --red: #ef4444;
            --blue: #3b82f6;
            --orange: #f59e0b;
            --purple: #8b5cf6;
            
            --sidebar-width: 280px;
            --radius-soft: 12px;
            
            --font-head: 'Playfair Display', serif;
            --font-main: 'Nunito Sans', sans-serif;
            --font-script: 'Pacifico', cursive;
        }

        [data-theme="dark"] {
            --bg-body: #121212;
            --text-main: #e0e0e0;
            --text-muted: #a0a0a0;
            --border-color: rgba(255,255,255,0.1);
            --card-bg: #1e1e1e;
            --shadow-color: rgba(0,0,0,0.3);
            --input-bg: #2a2a2a;
            --sidebar-bg: #1a1a1a; 
            --sidebar-text: #b0b0b0;
            --sidebar-active-bg: #cd853f;
            --sidebar-active-text: #ffffff;
            --sidebar-hover: #2c2c2c;
            --logo-color: #ffffff;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-main);
            transition: background-color 0.3s ease, color 0.3s ease;
            margin: 0; padding: 0;
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }
        ul { list-style: none; padding: 0; margin: 0; }
        * { box-sizing: border-box; }

        /* === SIDEBAR (Matches admin.php) === */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            padding: 30px 20px;
            display: flex; flex-direction: column;
            border-right: 1px solid var(--border-color);
            box-shadow: 5px 0 25px var(--shadow-color);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            text-align: center; margin-bottom: 40px; padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-logo {
            text-decoration: none; display: flex; flex-direction: column; align-items: center; line-height: 1;
        }
        .sidebar-logo img { 
            width:170px; height:60px; margin: 10px 0 10px;
        }

        .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; }
        .sidebar-nav li { margin-bottom: 8px; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 15px; padding: 14px 20px;
            color: var(--sidebar-text); font-weight: 700; font-size: 0.95rem;
            border-radius: var(--radius-soft); transition: all 0.3s ease;
            white-space: nowrap;
        }
        .sidebar-nav a i { width: 20px; text-align: center; font-size: 1.1rem; opacity: 0.7; }
        .sidebar-nav a:hover { background: var(--sidebar-hover); color: var(--accent); transform: translateX(3px); }
        .sidebar-nav a:hover i { color: var(--accent); opacity: 1; }
        .sidebar-nav li.active a { background: var(--sidebar-active-bg); color: var(--sidebar-active-text); box-shadow: 0 4px 12px rgba(205, 133, 63, 0.3); }
        .sidebar-nav li.active a i { color: var(--sidebar-active-text); opacity: 1; }

        .sidebar-footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color); }
        .btn-logout {
            display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;
            padding: 12px; background: rgba(255, 77, 77, 0.1); color: #ff4d4d;
            border-radius: var(--radius-soft); font-weight: 700; transition: 0.3s;
        }
        .btn-logout:hover { background: #ff4d4d; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(255, 77, 77, 0.2); }

        /* === MAIN CONTENT === */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px 40px;
            width: calc(100% - var(--sidebar-width));
        }

        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .page-header h1 { font-family: var(--font-head); font-size: 2.2rem; color: var(--text-main); margin: 0 0 5px 0; }
        .page-header p { color: var(--text-muted); font-size: 1rem; margin: 0; }

        .header-actions { display: flex; align-items: center; gap: 20px; }
        .icon-btn {
            background: var(--card-bg); width: 45px; height: 45px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: var(--text-muted); cursor: pointer; border: 1px solid var(--border-color);
            transition: 0.3s; box-shadow: 0 2px 5px var(--shadow-color);
        }
        .icon-btn:hover { color: var(--accent); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

        .user-profile {
            display: flex; align-items: center; gap: 15px; background: var(--card-bg);
            padding: 6px 6px 6px 20px; border-radius: 50px; border: 1px solid var(--border-color);
            box-shadow: 0 2px 5px var(--shadow-color);
        }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-info .name { display: block; font-weight: 800; font-size: 0.9rem; color: var(--text-main); }
        .profile-info .role { font-size: 0.75rem; color: var(--accent); font-weight: 700; text-transform: uppercase; }
        .avatar img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--bg-body); }

        /* === RECYCLE BIN ELEMENTS === */
        .controls-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 15px; flex-wrap: wrap; }
        .filter-form { display: flex; gap: 10px; align-items: center; }
        
        select { 
            padding: 10px 20px; border-radius: 50px; 
            border: 1px solid var(--border-color); 
            background: var(--input-bg); 
            color: var(--text-main); 
            outline: none; cursor: pointer; 
            font-weight: 700; font-size: 0.9rem;
            transition: 0.3s;
            font-family: var(--font-main);
        }
        select:focus { border-color: var(--accent); }

        .card { background: var(--card-bg); border-radius: var(--radius-soft); box-shadow: 0 5px 15px var(--shadow-color); padding: 30px; border: 1px solid var(--border-color); }
        .table-responsive { overflow-x: auto; }
        .styled-table { width: 100%; border-collapse: collapse; }
        .styled-table th { text-align: left; padding: 18px 25px; color: var(--text-muted); border-bottom: 2px solid var(--border-color); font-weight: 700; background: var(--bg-body); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; }
        .styled-table td { padding: 18px 25px; border-bottom: 1px solid var(--border-color); color: var(--text-main); vertical-align: middle; }
        .styled-table tr:hover { background: rgba(0,0,0,0.02); }

        .id-badge { background: var(--bg-body); padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.8rem; color: var(--text-muted); border: 1px solid var(--border-color); }

        /* Source Badges */
        .badge-source { padding: 5px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; display: inline-block; letter-spacing: 0.5px; }
        .source-artworks { background: rgba(139, 92, 246, 0.1); color: var(--purple); }
        .source-bookings { background: rgba(59, 130, 246, 0.1); color: var(--blue); }
        .source-services { background: rgba(249, 115, 22, 0.1); color: var(--orange); }
        .source-users { background: rgba(239, 68, 68, 0.1); color: var(--red); }
        .source-events { background: rgba(16, 185, 129, 0.1); color: var(--green); }
        .source-artists { background: rgba(236, 72, 153, 0.1); color: #ec4899; }
        .source-inquiries { background: rgba(107, 114, 128, 0.1); color: #6b7280; }

        .actions { display: flex; gap: 8px; justify-content: flex-end; }
        .btn-icon { width: 32px; height: 32px; border-radius: 6px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; }
        .btn-icon.edit { background: rgba(16, 185, 129, 0.1); color: var(--green); } /* Undo/Restore */
        .btn-icon.delete { background: rgba(239, 68, 68, 0.1); color: var(--red); }
        .btn-icon:hover { transform: translateY(-2px); opacity: 0.8; }

        /* Notifications (Matches admin.php) */
        .notif-wrapper { position: relative; }
        .notif-bell .dot { position: absolute; top: 0; right: 0; background: var(--red); color: white; font-size: 0.6rem; font-weight: 700; border-radius: 50%; min-width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; border: 2px solid var(--card-bg); }
        .notif-dropdown { display: none; position: absolute; right: -10px; top: 60px; width: 320px; background: var(--card-bg); border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); border: 1px solid var(--border-color); z-index: 1100; overflow: hidden; }
        .notif-dropdown.active { display: block; }
        .notif-header { padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: 700; color: var(--text-main); background: var(--bg-body); }
        .notif-list { max-height: 300px; overflow-y: auto; }
        .notif-item { padding: 15px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: 0.2s; color: var(--text-main); }
        .notif-item:hover { background: var(--bg-body); }
        .notif-item.unread { border-left: 3px solid var(--accent); background: rgba(205, 133, 63, 0.05); }
        .notif-time { font-size: 0.75rem; color: var(--text-muted); margin-top: 5px; display: block; }
        .no-notif { padding: 20px; text-align: center; color: var(--text-muted); font-style: italic; }
        .small-btn { background: none; border: none; color: var(--accent); font-weight: 700; font-size: 0.75rem; cursor: pointer; text-transform: uppercase; }

        /* Modals */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: 0.3s; z-index: 2000; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card.small { width: 400px; max-width: 90%; padding: 40px 30px; text-align: center; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); background: var(--card-bg); transform: translateY(20px); transition: 0.3s; }
        .modal-overlay.active .modal-card.small { transform: translateY(0); }
        
        .modal-header-icon { width: 70px; height: 70px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
        .delete-icon { background: rgba(239, 68, 68, 0.1); color: var(--red); }
        .restore-icon { background: rgba(16, 185, 129, 0.1); color: var(--green); }
        
        .btn-friendly { padding: 12px 25px; border-radius: 50px; font-weight: 700; border: none; cursor: pointer; transition: 0.2s; font-size: 0.9rem; font-family: var(--font-main); }
        .btn-friendly:hover { transform: translateY(-2px); opacity: 0.9; }

        @media (max-width: 1024px) {
            .sidebar { width: 80px; padding: 20px 10px; }
            .sidebar-logo img { width: 60px; height: auto; }
            .sidebar-nav span, .sidebar-footer span { display: none; }
            .sidebar-nav a { justify-content: center; padding: 15px; }
            .main-content { margin-left: 80px; width: calc(100% - 80px); }
        }
    </style>
</head>
<body>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('adminTheme');
            if (savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="./" class="sidebar-logo">
                <img src="uploads/logo.png" alt="The ManCave Gallery" onerror="this.onerror=null; this.src='LOGOS.png';">
            </a>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="admin.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                <li><a href="reservations.php"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a></li>
                <li><a href="content.php"><i class="fas fa-layer-group"></i> <span>Website Content</span></a></li>
                <li><a href="manage_hero.php"><i class="fas fa-images"></i> <span>Manage Slider</span></a></li>
                <li><a href="manage_news.php"><i class="fas fa-newspaper"></i> <span>Gallery Updates</span></a></li>
                <li><a href="manage_team.php"><i class="fas fa-user-tie"></i> <span>Manage Artists</span></a></li>
                <li><a href="manage_about_artists.php"><i class="fas fa-users-cog"></i> <span>About: Meet Artists</span></a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> <span>Customers & Staff</span></a></li>
                <li><a href="feedback.php"><i class="fas fa-comments"></i> <span>Message & Feedback</span></a></li>
                <li class="active"><a href="trash.php"><i class="fas fa-trash-alt"></i> <span>Recycle Bin</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="page-header">
                <h1>Recycle Bin</h1>
                <p>Restore deleted items or remove them permanently.</p>
            </div>
            
            <div class="header-actions">
                <button class="icon-btn" id="themeToggle" title="Toggle Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>

                <div class="notif-wrapper">
                    <div class="icon-btn notif-bell" id="adminNotifBtn">
                        <i class="far fa-bell"></i>
                        <span class="dot" id="adminNotifBadge" style="display:none;">0</span>
                    </div>
                    
                    <div class="notif-dropdown" id="adminNotifDropdown">
                        <div class="notif-header">
                            <span>Notifications</span>
                            <button id="adminMarkAllRead" class="small-btn">Mark all read</button>
                        </div>
                        <ul class="notif-list" id="adminNotifList">
                            <li class="no-notif">Loading...</li>
                        </ul>
                    </div>
                </div>

                <div class="user-profile">
                    <div class="profile-info">
                        <span class="name">Administrator</span>
                        <span class="role">Super Admin</span>
                    </div>
                    <div class="avatar"><img src="https://ui-avatars.com/api/?name=Admin&background=cd853f&color=fff" alt="Admin"></div>
                </div>
            </div>
        </header>

        <div class="controls-bar">
            <form method="GET" class="filter-form">
                <select name="source" onchange="this.form.submit()">
                    <option value="all" <?= $filterSource == 'all' ? 'selected' : '' ?>>All Sources</option>
                    <option value="artworks" <?= $filterSource == 'artworks' ? 'selected' : '' ?>>Artworks</option>
                    <option value="bookings" <?= $filterSource == 'bookings' ? 'selected' : '' ?>>Bookings</option>
                    <option value="services" <?= $filterSource == 'services' ? 'selected' : '' ?>>Services</option>
                    <option value="events" <?= $filterSource == 'events' ? 'selected' : '' ?>>Events</option>
                    <option value="artists" <?= $filterSource == 'artists' ? 'selected' : '' ?>>Artists</option>
                    <option value="users" <?= $filterSource == 'users' ? 'selected' : '' ?>>Users</option>
                    <option value="inquiries" <?= $filterSource == 'inquiries' ? 'selected' : '' ?>>Inquiries</option>
                    <option value="ratings" <?= $filterSource == 'ratings' ? 'selected' : '' ?>>Ratings</option>
                </select>

                <select name="sort" onchange="this.form.submit()">
                    <option value="desc" <?= $sortOrder == 'desc' ? 'selected' : '' ?>>Newest Deleted</option>
                    <option value="asc" <?= $sortOrder == 'asc' ? 'selected' : '' ?>>Oldest Deleted</option>
                </select>
            </form>
        </div>

        <div class="card table-card">
            <div class="table-responsive">
                <table class="styled-table" id="trashTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Details</th>
                            <th>Source</th>
                            <th>Deleted Date</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trash_items)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">No items found in trash.</td></tr>
                        <?php else: ?>
                            <?php foreach ($trash_items as $item): 
                                $parts = explode('|', $item['item_name'], 2);
                                $displayName = $parts[0];
                                $sourceClass = 'source-' . strtolower($item['source']);
                            ?>
                            <tr>
                                <td><span class="id-badge">#<?= $item['id'] ?></span></td>
                                <td><strong><?= htmlspecialchars($displayName) ?></strong></td>
                                <td><span class="badge-source <?= $sourceClass ?>"><?= ucfirst($item['source']) ?></span></td>
                                <td>
                                    <i class="far fa-clock" style="color:var(--text-muted); margin-right:5px;"></i>
                                    <?= date('M d, Y h:i A', strtotime($item['deleted_at'])) ?>
                                </td>
                                <td style="text-align: right;">
                                    <div class="actions">
                                        <button class="btn-icon edit" onclick="confirmAction('restore', <?= $item['id'] ?>)" title="Restore Item"><i class="fas fa-undo"></i></button>
                                        <button class="btn-icon delete" onclick="confirmAction('delete', <?= $item['id'] ?>)" title="Delete Permanently"><i class="fas fa-times"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="confirmModal">
        <div class="modal-card small">
            <div class="modal-header-icon" id="confirmIcon"></div>
            <h3 id="confirmTitle" style="font-family: var(--font-head); font-size: 1.5rem; margin-bottom: 10px;"></h3>
            <p id="confirmText" style="color: var(--text-muted); margin-bottom: 25px;"></p>
            <div class="btn-group">
                <button class="btn-friendly" style="background:var(--border-color); color:var(--text-main);" onclick="closeModal('confirmModal')">Cancel</button>
                <button class="btn-friendly" id="confirmBtnAction" style="color:white;"></button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="alertModal">
        <div class="modal-card small">
            <div class="modal-header-icon" id="alertIcon"></div>
            <h3 id="alertTitle" style="font-family: var(--font-head); font-size: 1.5rem; margin-bottom: 10px;"></h3>
            <p id="alertMessage" style="color: var(--text-muted); margin-bottom: 25px;"></p>
            <button class="btn-friendly" style="background:var(--text-main); color:var(--bg-body); width:100%;" onclick="closeModal('alertModal'); location.reload();">Okay</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggleBtn = document.getElementById('themeToggle');
            const themeIcon = themeToggleBtn.querySelector('i');
            if (localStorage.getItem('adminTheme') === 'dark') themeIcon.classList.replace('fa-moon', 'fa-sun');

            themeToggleBtn.addEventListener('click', () => {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('adminTheme', 'light');
                    themeIcon.classList.replace('fa-sun', 'fa-moon');
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('adminTheme', 'dark');
                    themeIcon.classList.replace('fa-moon', 'fa-sun');
                }
            });

            // Notifications Logic
            const notifBtn = document.getElementById('adminNotifBtn');
            const notifDropdown = document.getElementById('adminNotifDropdown');
            const notifBadge = document.getElementById('adminNotifBadge');
            const notifList = document.getElementById('adminNotifList');

            if(notifBtn) {
                notifBtn.addEventListener('click', (e) => { e.stopPropagation(); notifDropdown.classList.toggle('active'); });
                function fetchNotifications() {
                    fetch('fetch_notifications.php').then(res => res.json()).then(data => {
                        if (data.status === 'success') {
                            if (data.unread_count > 0) { notifBadge.innerText = data.unread_count; notifBadge.style.display = 'flex'; }
                            else { notifBadge.style.display = 'none'; }
                            notifList.innerHTML = '';
                            if (data.notifications.length === 0) notifList.innerHTML = '<li class="no-notif">No new notifications</li>';
                            else {
                                data.notifications.forEach(notif => {
                                    const li = document.createElement('li');
                                    li.className = `notif-item ${notif.is_read == 0 ? 'unread' : ''}`;
                                    li.innerHTML = `<div class="notif-msg">${notif.message}</div><span class="notif-time">${notif.created_at}</span><button class="btn-notif-close">&times;</button>`;
                                    li.addEventListener('click', () => {
                                        const fd = new FormData(); fd.append('id', notif.id);
                                        fetch('mark_as_read.php', { method: 'POST', body: fd }).then(() => fetchNotifications());
                                    });
                                    notifList.appendChild(li);
                                });
                            }
                        }
                    });
                }
                fetchNotifications();
                setInterval(fetchNotifications, 30000);
            }
            window.addEventListener('click', () => notifDropdown.classList.remove('active'));
        });

        // --- MODAL & ACTION LOGIC ---
        let actionCallback = null;
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        function showAlert(title, msg, type) {
            document.getElementById('alertTitle').innerText = title;
            document.getElementById('alertMessage').innerText = msg;
            const icon = document.getElementById('alertIcon');
            if (type === 'success') {
                icon.className = 'modal-header-icon restore-icon';
                icon.innerHTML = '<i class="fas fa-check"></i>';
            } else {
                icon.className = 'modal-header-icon delete-icon';
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            }
            document.getElementById('alertModal').classList.add('active');
        }

        function confirmAction(type, id) {
            const icon = document.getElementById('confirmIcon');
            const btn = document.getElementById('confirmBtnAction');
            if (type === 'restore') {
                document.getElementById('confirmTitle').innerText = 'Restore Item?';
                document.getElementById('confirmText').innerText = 'Move this item back to its original location.';
                icon.className = 'modal-header-icon restore-icon';
                icon.innerHTML = '<i class="fas fa-undo"></i>';
                btn.style.background = '#10b981'; btn.innerText = 'Restore';
                actionCallback = () => performRestore(id);
            } else {
                document.getElementById('confirmTitle').innerText = 'Delete Permanently?';
                document.getElementById('confirmText').innerText = 'This action cannot be undone.';
                icon.className = 'modal-header-icon delete-icon';
                icon.innerHTML = '<i class="fas fa-trash-alt"></i>';
                btn.style.background = '#ef4444'; btn.innerText = 'Delete';
                actionCallback = () => performDelete(id);
            }
            document.getElementById('confirmModal').classList.add('active');
        }

        document.getElementById('confirmBtnAction').addEventListener('click', () => {
            if (actionCallback) actionCallback();
            closeModal('confirmModal');
        });

        async function performRestore(id) {
            const fd = new FormData(); fd.append('id', id); fd.append('action', 'restore');
            const res = await fetch('restore_item.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.status === 'success') showAlert('Restored!', 'Item restored successfully.', 'success');
            else showAlert('Error', data.message, 'error');
        }

        async function performDelete(id) {
            const fd = new FormData(); fd.append('id', id); fd.append('action', 'permanent_delete');
            const res = await fetch('restore_item.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.status === 'success') showAlert('Deleted!', 'Item deleted permanently.', 'success');
            else showAlert('Error', data.message, 'error');
        }
    </script>
</body>
</html>