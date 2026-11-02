<?php
/**
 * user_notification_bell.php — Component กระดิ่งแจ้งเตือนสำหรับผู้ใช้ทั่วไป (User)
 * รองรับทั้ง Desktop และ Mobile Responsive อย่างสมบูรณ์
 */
if (!isset($_SESSION['user_id'])) {
    return;
}

$bell_user_id = (int)$_SESSION['user_id'];
$bell_unread_count = 0;

if (isset($pdo) && function_exists('count_unread_notifications')) {
    $bell_unread_count = count_unread_notifications($pdo, $bell_user_id);
} elseif (isset($pdo)) {
    try {
        $stmt_bell_cnt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND (is_read = 0 OR is_read IS FALSE)");
        $stmt_bell_cnt->execute([$bell_user_id]);
        $bell_unread_count = (int)$stmt_bell_cnt->fetchColumn();
    } catch (Exception $e) {
        $bell_unread_count = 0;
    }
}
?>

<div class="user-notif-wrapper" style="position: relative; display: inline-flex; align-items: center;">
    <button type="button" class="user-bell-btn" id="userBellBtn" onclick="toggleUserNotifDropdown(event)" aria-label="การแจ้งเตือน" title="การแจ้งเตือน">
        <i class="fa-solid fa-bell"></i>
        <?php if ($bell_unread_count > 0): ?>
            <span class="user-bell-badge" id="user-bell-badge"><?php echo $bell_unread_count > 99 ? '99+' : $bell_unread_count; ?></span>
        <?php endif; ?>
    </button>

    <div class="user-notif-dropdown" id="userNotifDropdown">
        <div class="user-notif-header">
            <div style="display: flex; align-items: center; gap: 7px;">
                <i class="fa-solid fa-bell text-amber-600"></i>
                <span style="font-weight: 700; color: #451a03;">การแจ้งเตือน</span>
            </div>
            <button type="button" class="user-mark-all-btn" onclick="markAllUserNotifRead(event)">อ่านทั้งหมด</button>
        </div>
        <div class="user-notif-list" id="userNotifList">
            <div style="padding: 24px 16px; text-align: center; color: #857b74; font-size: 13px;">กำลังโหลด...</div>
        </div>
        <div class="user-notif-footer">
            <a href="my_bookings.php">ดูรายการจองและการแจ้งเตือนทั้งหมด <i class="fa-solid fa-chevron-right" style="font-size: 9px; margin-left: 3px;"></i></a>
        </div>
    </div>
</div>

<style>
.user-notif-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
    font-family: 'Sarabun', 'Prompt', sans-serif;
    vertical-align: middle;
}
.user-bell-btn {
    position: relative;
    background: #fff;
    border: 1px solid rgba(217, 119, 6, 0.3);
    border-radius: 9999px;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #92400e;
    cursor: pointer;
    font-size: 15px;
    transition: all 0.2s ease;
    padding: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.user-bell-btn:hover {
    background-color: #fef3c7;
    color: #78350f;
    border-color: #d97706;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(217, 119, 6, 0.18);
}
.user-bell-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    background: #ef4444;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    min-width: 17px;
    height: 17px;
    line-height: 17px;
    padding: 0 4px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}
.user-notif-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 320px;
    max-width: calc(100vw - 24px);
    background: #ffffff;
    border: 1px solid #f3e8d2;
    border-radius: 16px;
    box-shadow: 0 14px 40px -4px rgba(40, 20, 5, 0.18);
    z-index: 99999;
    display: none;
    text-align: left;
    overflow: hidden;
    animation: userNotifFadeIn 0.18s ease-out;
}
@keyframes userNotifFadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
.user-notif-dropdown.show {
    display: block;
}
.user-notif-header {
    padding: 12px 16px;
    background: #fffdfa;
    border-bottom: 1px solid #f5ede1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    font-weight: 700;
    color: #451a03;
}
.user-mark-all-btn {
    background: none;
    border: none;
    color: #b45309;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: background 0.15s;
    font-family: inherit;
}
.user-mark-all-btn:hover {
    background: #fef3c7;
    color: #78350f;
}
.user-notif-list {
    max-height: 320px;
    overflow-y: auto;
    overscroll-behavior: contain;
}
.user-notif-item {
    padding: 12px 16px;
    border-bottom: 1px solid #fbf7f0;
    font-size: 13px;
    cursor: pointer;
    color: #374151;
    line-height: 1.45;
    transition: background 0.15s;
}
.user-notif-item:hover {
    background: #fdfaf4;
}
.user-notif-item.unread {
    background: #fffbeb;
    border-left: 3px solid #d97706;
    font-weight: 500;
    color: #1f2937;
}
.user-notif-item .notif-time {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
}
.user-notif-footer {
    padding: 10px 16px;
    text-align: center;
    background: #fffdfa;
    border-top: 1px solid #f5ede1;
    font-size: 12px;
}
.user-notif-footer a {
    color: #b45309;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: inherit;
}
.user-notif-footer a:hover {
    color: #78350f;
    text-decoration: underline;
}

/* Mobile responsive */
@media (max-width: 640px) {
    .user-notif-dropdown {
        position: fixed;
        top: 60px;
        right: 12px;
        left: 12px;
        width: auto;
        max-width: calc(100vw - 24px);
    }
}
</style>

<script>
(function() {
    if (window.__userNotifInit) return;
    window.__userNotifInit = true;

    window.toggleUserNotifDropdown = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const dd = document.getElementById('userNotifDropdown');
        if (!dd) return;
        dd.classList.toggle('show');
        if (dd.classList.contains('show')) {
            updateUserBadge(0);
            fetch('get_notifications.php?action=mark_all_read').catch(() => {});
            loadUserNotifications();
        }
    };

    window.loadUserNotifications = function() {
        fetch('get_notifications.php?action=list')
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('userNotifList');
                if (!list) return;
                const items = data.notifications || data.items || [];
                const cnt = data.unread_count !== undefined ? data.unread_count : (data.count || 0);

                if (items.length === 0) {
                    list.innerHTML = '<div style="padding:28px 16px;text-align:center;color:#9ca3af;font-size:13px;"><i class="fa-regular fa-bell-slash text-xl mb-2 block text-gray-300"></i>ไม่มีการแจ้งเตือนในขณะนี้</div>';
                    updateUserBadge(0);
                    return;
                }
                list.innerHTML = items.map(n => `
                    <div class="user-notif-item ${n.is_read ? '' : 'unread'}" onclick="clickUserNotif(${n.id})">
                        <div>${n.message}</div>
                        <div class="notif-time"><i class="fa-regular fa-clock" style="font-size:10px; margin-right:4px;"></i>${n.time_ago}</div>
                    </div>`).join('');
                updateUserBadge(cnt);
            })
            .catch(() => {
                const list = document.getElementById('userNotifList');
                if (list) list.innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;font-size:13px;">ไม่สามารถโหลดรายการแจ้งเตือนได้</div>';
            });
    };

    window.markAllUserNotifRead = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        updateUserBadge(0);
        document.querySelectorAll('.user-notif-item.unread').forEach(el => el.classList.remove('unread'));
        fetch('get_notifications.php?action=mark_all_read').then(() => {
            loadUserNotifications();
        }).catch(() => {});
    };

    window.clickUserNotif = function(id) {
        updateUserBadge(0);
        fetch('get_notifications.php?action=mark_read&id=' + id)
            .then(() => {
                window.location.href = 'my_bookings.php';
            })
            .catch(() => {
                window.location.href = 'my_bookings.php';
            });
    };

    window.updateUserBadge = function(cnt) {
        let badge = document.getElementById('user-bell-badge');
        if (cnt > 0) {
            if (!badge) {
                const btn = document.getElementById('userBellBtn');
                if (btn) {
                    badge = document.createElement('span');
                    badge.className = 'user-bell-badge';
                    badge.id = 'user-bell-badge';
                    btn.appendChild(badge);
                }
            }
            if (badge) {
                badge.textContent = cnt > 99 ? '99+' : cnt;
                badge.style.display = 'inline-flex';
            }
        } else {
            if (badge) {
                badge.style.display = 'none';
                badge.textContent = '0';
            }
        }
    };

    document.addEventListener('click', function(e) {
        const dd = document.getElementById('userNotifDropdown');
        const btn = document.getElementById('userBellBtn');
        if (dd && dd.classList.contains('show')) {
            if (!dd.contains(e.target) && (!btn || !btn.contains(e.target))) {
                dd.classList.remove('show');
            }
        }
    });

    // ตรวจสอบแจ้งเตือนใหม่ทุก 60 วินาที
    setInterval(() => {
        fetch('get_notifications.php?action=list')
            .then(r => r.json())
            .then(data => updateUserBadge(data.unread_count || 0))
            .catch(() => {});
    }, 60000);
})();
</script>
