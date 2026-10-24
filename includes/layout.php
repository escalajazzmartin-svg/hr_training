<?php

declare(strict_types=1);

function render_app(PDO $pdo, array $user, string $page, ?array $flash, string $content): void
{
    $role = $user['role'];
    $name = user_display($user);
    $roleText = $role === 'admin' ? 'HR MANAGEMENT' : ($role === 'employee' ? 'FACULTY PORTAL' : 'TRAINER PORTAL');
    $headerRole = $role === 'admin' ? 'HR Management' : ($role === 'employee' ? 'Faculty Portal' : 'Trainer Portal');
    $initial = initials($name);
    $mainClass = 'content';

    if ($page === 'Dashboard') {
        $mainClass .= ' dashboard-content dashboard-content-' . $role;
    }

    $globalSearch = trim((string)($_GET['global_search'] ?? ''));
    $searchResults = [];

    if ($globalSearch !== '') {
        foreach (nav_for($role) as [$label, $iconName]) {
            if (stripos($label, $globalSearch) !== false) {
                $searchResults[] = [
                    'type' => 'Page',
                    'label' => $label,
                    'detail' => 'Open page',
                    'url' => app_page_url($label, $role)
                ];
            }
        }

        $like = '%' . $globalSearch . '%';

        if ($role === 'admin') {
            $stmt = $pdo->prepare(
                'SELECT id, employee_code, first_name, last_name, department, email
                 FROM employees
                 WHERE employee_code LIKE ?
                    OR first_name LIKE ?
                    OR last_name LIKE ?
                    OR email LIKE ?
                    OR department LIKE ?
                    OR specialization LIKE ?
                 ORDER BY last_name, first_name
                 LIMIT 8'
            );
            $stmt->execute([$like, $like, $like, $like, $like, $like]);

            foreach ($stmt->fetchAll() as $employee) {
                $searchResults[] = [
                    'type' => 'Employee',
                    'label' => $employee['first_name'] . ' ' . $employee['last_name'],
                    'detail' => $employee['employee_code'] . ' · ' . $employee['department'],
                    'url' => app_page_url('Employee Profiles', $role, [
                        'employee_id' => (int)$employee['id']
                    ])
                ];
            }
        }

        if ($role === 'admin') {
            $stmt = $pdo->prepare(
                'SELECT title, specialization, start_date
                 FROM training_programs
                 WHERE title LIKE ? OR specialization LIKE ?
                 ORDER BY start_date DESC
                 LIMIT 6'
            );
            $stmt->execute([$like, $like]);
            $programPage = 'Training Programs';
        } elseif ($role === 'trainer') {
            $stmt = $pdo->prepare(
                'SELECT title, specialization, start_date
                 FROM training_programs
                 WHERE trainer_user_id = ?
                   AND (title LIKE ? OR specialization LIKE ?)
                 ORDER BY start_date DESC
                 LIMIT 6'
            );
            $stmt->execute([(int)$user['id'], $like, $like]);
            $programPage = 'My Training Programs';
        } else {
            $stmt = $pdo->prepare(
                "SELECT title, specialization, start_date
                 FROM training_programs
                 WHERE status IN ('Upcoming', 'Ongoing')
                   AND (title LIKE ? OR specialization LIKE ?)
                 ORDER BY start_date
                 LIMIT 6"
            );
            $stmt->execute([$like, $like]);
            $programPage = 'Available Training';
        }

        foreach ($stmt->fetchAll() as $program) {
            $searchResults[] = [
                'type' => 'Training',
                'label' => $program['title'],
                'detail' => $program['specialization'] . ' · ' . date('M j, Y', strtotime($program['start_date'])),
                'url' => app_page_url($programPage, $role)
            ];
        }

        $searchResults = array_slice($searchResults, 0, 15);
    }

    $notifications = [];
    $notificationTotal = 0;

    $addNotification = function (
        int $count,
        string $title,
        string $detail,
        string $destination
    ) use (&$notifications, &$notificationTotal): void {
        if ($count < 1) {
            return;
        }

        $notifications[] = [
            'count' => $count,
            'title' => $title,
            'detail' => $detail,
            'url' => $destination
        ];

        $notificationTotal += $count;
    };

    if ($role === 'admin' || $role === 'trainer') {
        $trainerCondition = $role === 'trainer'
            ? ' AND p.trainer_user_id = ?'
            : '';

        $trainerParams = $role === 'trainer'
            ? [(int)$user['id']]
            : [];

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM registrations r
             JOIN training_programs p ON p.id = r.program_id
             LEFT JOIN attendance a ON a.registration_id = r.id
             WHERE r.status <> 'Withdrawn'
               AND p.start_date <= CURDATE()
               AND (
                   a.id IS NULL
                   OR a.attendance_status IS NULL
                   OR a.attendance_status = ''
               )"
                . $trainerCondition
        );
        $stmt->execute($trainerParams);

        $addNotification(
            (int)$stmt->fetchColumn(),
            'Attendance needs recording',
            'Training participants without an attendance record',
            app_page_url('Attendance Monitoring', $role)
        );

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM registrations r
             JOIN training_programs p ON p.id = r.program_id
             LEFT JOIN evaluations ev ON ev.registration_id = r.id
             WHERE r.status <> 'Withdrawn'
               AND p.start_date <= CURDATE()
               AND ev.id IS NULL"
                . $trainerCondition
        );
        $stmt->execute($trainerParams);

        $addNotification(
            (int)$stmt->fetchColumn(),
            'Assessments pending',
            'Training participants without a recorded evaluation',
            app_page_url(
                $role === 'trainer' ? 'Training Results' : 'Assessments & Evaluations',
                $role
            )
        );

        if ($role === 'admin') {
            $stmt = $pdo->query(
                "SELECT COUNT(*)
                 FROM registrations r
                 JOIN evaluations ev ON ev.registration_id = r.id
                 LEFT JOIN certificates c ON c.registration_id = r.id
                 WHERE r.status = 'Completed'
                   AND ev.score >= 75
                   AND c.id IS NULL"
            );

            $addNotification(
                (int)$stmt->fetchColumn(),
                'Certificates awaiting issuance',
                'Completed participants with a passing score',
                app_page_url('Certificates', $role)
            );
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM training_programs p
             WHERE p.start_date >= CURDATE()
               AND p.start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND p.status IN ('Upcoming', 'Ongoing')"
                . $trainerCondition
        );
        $stmt->execute($trainerParams);

        $addNotification(
            (int)$stmt->fetchColumn(),
            'Upcoming training',
            'Programs scheduled within the next 7 days',
            app_page_url(
                $role === 'admin' ? 'Training Calendar' : 'My Training Programs',
                $role
            )
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM registrations r
             JOIN training_programs p ON p.id = r.program_id
             WHERE r.employee_id = ?
               AND r.status <> 'Withdrawn'
               AND p.start_date >= CURDATE()
               AND p.start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        );
        $stmt->execute([(int)($user['employee_id'] ?? 0)]);

        $addNotification(
            (int)$stmt->fetchColumn(),
            'Upcoming training',
            'Your registered sessions over the next 7 days',
            app_page_url('My Training', $role)
        );
    }

    $accountPage = $role === 'admin'
        ? 'Settings'
        : ($role === 'employee' ? 'My Profile' : 'My Training Programs');

    $accountLabel = $role === 'admin'
        ? 'Account settings'
        : ($role === 'employee' ? 'My profile' : 'My training programs');

?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#800000">
        <title><?= h($page) ?> | Westmead International School</title>
        <link rel="stylesheet" href="<?= h(app_base_url() . '/assets/style.css') ?>">
        <link rel="stylesheet" href="<?= h(app_base_url() . '/assets/header-controls.css') ?>">
        <link rel="icon" href="<?= h(app_base_url() . '/assets/favicon.svg') ?>" type="image/svg+xml">
    </head>

    <body>
        <div class="app-shell">
            <div class="sidebar-wrap" id="sidebar-wrap">
                <aside class="sidebar">
                    <div class="side-brand">
                        <div class="brand-seal"><span>WIS</span></div>
                        <div>
                            <b>Westmead International School</b>
                            <small>Training &amp; Development</small>
                        </div>
                        <button class="mobile-close" type="button" id="mobile-close" aria-label="Close menu"><?= icon('close') ?></button>
                    </div>

                    <div class="side-role"><?= h($roleText) ?></div>

                    <nav>
                        <?php foreach (nav_for($role) as [$label, $ico]): ?>
                            <a class="<?= $page === $label ? 'active' : '' ?>" href="<?= h(app_page_url($label, $role)) ?>">
                                <?= icon($ico) ?>
                                <span><?= h($label) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="side-user">
                        <span class="avatar avatar-gold"><?= h($initial) ?></span>
                        <div>
                            <b><?= h($name) ?></b>
                            <small><?= h(role_label($role)) ?></small>
                        </div>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="logout">
                            <button title="Sign out" aria-label="Sign out" type="submit"><?= icon('logout') ?></button>
                        </form>
                    </div>
                </aside>
            </div>

            <button class="scrim" id="scrim" hidden aria-label="Close menu"></button>

            <div class="main-shell">
                <header class="topbar">
                    <button class="hamburger" type="button" id="hamburger" aria-label="Open menu"><?= icon('menu') ?></button>

                    <div>
                        <span>Workspace / <?= h($headerRole) ?></span>
                        <h1><?= h($page) ?></h1>
                    </div>

                    <div class="header-actions">
                        <button
                            class="icon-circle"
                            type="button"
                            id="search-toggle"
                            title="Search"
                            aria-label="Open search"
                            aria-expanded="<?= $globalSearch !== '' ? 'true' : 'false' ?>"
                            aria-controls="global-search-panel"><?= icon('search') ?></button>

                        <button
                            class="icon-circle notification"
                            type="button"
                            id="notifications-toggle"
                            title="Notifications"
                            aria-label="Open notifications"
                            aria-expanded="false"
                            aria-controls="notifications">
                            <?= icon('bell') ?>
                            <?php if ($notificationTotal > 0): ?>
                                <i data-notification-indicator></i>
                                <span class="header-notification-count" data-notification-total><?= min($notificationTotal, 99) ?></span>
                            <?php endif; ?>
                        </button>

                        <button
                            class="header-profile"
                            type="button"
                            id="profile-toggle"
                            aria-label="Open account menu"
                            aria-expanded="false"
                            aria-controls="profile-menu">
                            <span class="avatar"><?= h($initial) ?></span>
                            <span>
                                <?= h($name) ?>
                                <small><?= h(role_label($role)) ?></small>
                            </span>
                            <?= icon('chevron', 14) ?>
                        </button>

                        <div id="global-search-panel" class="header-popover header-search-panel" <?= $globalSearch === '' ? 'hidden' : '' ?>>
                            <h3>Search workspace</h3>
                            <p class="popover-subtitle">Find pages, employees, and training programs.</p>

                            <form method="get" action="<?= h((string)($_SERVER['PHP_SELF'] ?? '')) ?>">
                                <input
                                    class="search-input"
                                    type="search"
                                    name="global_search"
                                    value="<?= h($globalSearch) ?>"
                                    placeholder="Type a name, page, or program..."
                                    autocomplete="off"
                                    aria-label="Search the workspace">
                                <button class="btn" type="submit"><?= icon('search') ?> Search</button>
                            </form>

                            <?php if ($globalSearch !== ''): ?>
                                <div class="header-search-results">
                                    <?php if (!$searchResults): ?>
                                        <p class="popover-empty">No matching pages, employees, or training programs found.</p>
                                    <?php else: ?>
                                        <?php foreach ($searchResults as $result): ?>
                                            <a class="header-search-result" href="<?= h($result['url']) ?>">
                                                <span class="header-result-type"><?= h($result['type']) ?></span>
                                                <b><?= h($result['label']) ?></b>
                                                <small><?= h($result['detail']) ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="notifications" class="header-popover header-notifications-panel" hidden>
                            <h3>
                                Notifications
                                <?php if ($notificationTotal > 0): ?>
                                    <span class="header-notification-count" data-notification-total><?= min($notificationTotal, 99) ?></span>
                                <?php endif; ?>
                            </h3>

                            <p class="popover-subtitle">Updates based on your current workspace and saved records.</p>

                            <?php if (!$notifications): ?>
                                <p class="popover-empty">You're all caught up. No pending items were found.</p>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <?php
                                    $notificationKey = strtolower(
                                        trim(
                                            preg_replace('/[^a-z0-9]+/i', '-', $notification['title']),
                                            '-'
                                        )
                                    );
                                    ?>
                                    <a
                                        class="header-notification-item"
                                        href="<?= h($notification['url']) ?>"
                                        data-notification-key="<?= h($notificationKey) ?>"
                                        data-notification-count="<?= $notification['count'] ?>">
                                        <b>
                                            <?= h($notification['title']) ?>
                                            <span class="header-notification-count"><?= min($notification['count'], 99) ?></span>
                                        </b>
                                        <small><?= h($notification['detail']) ?></small>
                                    </a>
                                <?php endforeach; ?>

                                <p class="popover-empty" data-no-visible-notifications hidden>
                                    You've cleared these notifications for now.
                                </p>
                            <?php endif; ?>
                        </div>

                        <div id="profile-menu" class="header-popover header-profile-menu" hidden>
                            <div class="account-heading">
                                <b><?= h($name) ?></b>
                                <small><?= h($user['email'] ?? '') ?></small>
                                <small><?= h(role_label($role)) ?></small>
                            </div>

                            <a class="header-menu-link" href="<?= h(app_page_url($accountPage, $role)) ?>">
                                <?= h($accountLabel) ?>
                            </a>

                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="action" value="logout">
                                <button class="header-menu-link logout" type="submit">Sign out <?= icon('logout', 14) ?></button>
                            </form>
                        </div>
                    </div>
                </header>

                <main class="<?= h($mainClass) ?>">
                    <?php if ($flash): ?>
                        <div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= h($flash['message']) ?></div>
                    <?php endif; ?>

                    <?= rewrite_page_links($content, $role) ?>

                    <footer>
                        <span>Westmead International School · HR Training &amp; Development System</span>
                        <span>Academic year 2026–2027 </span>
                    </footer>
                </main>
            </div>
        </div>

        <script>
            const wrap = document.getElementById('sidebar-wrap');
            const scrim = document.getElementById('scrim');
            const searchToggle = document.getElementById('search-toggle');
            const searchPanel = document.getElementById('global-search-panel');
            const searchInput = searchPanel.querySelector('input[name="global_search"]');
            const notificationsToggle = document.getElementById('notifications-toggle');
            const notificationsPanel = document.getElementById('notifications');
            const profileToggle = document.getElementById('profile-toggle');
            const profileMenu = document.getElementById('profile-menu');

            document.querySelectorAll('form[method=post]').forEach(form => {
                if (!form.querySelector('[name=return_page]')) {
                    const field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = 'return_page';
                    field.value = <?= json_encode($page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
                    form.appendChild(field);
                }
            });

            function closeHeaderPanels() {
                searchPanel.hidden = true;
                notificationsPanel.hidden = true;
                profileMenu.hidden = true;

                searchToggle.setAttribute('aria-expanded', 'false');
                notificationsToggle.setAttribute('aria-expanded', 'false');
                profileToggle.setAttribute('aria-expanded', 'false');
            }

            searchToggle.addEventListener('click', () => {
                const shouldOpen = searchPanel.hidden;

                closeHeaderPanels();
                searchPanel.hidden = !shouldOpen;
                searchToggle.setAttribute('aria-expanded', String(shouldOpen));

                if (shouldOpen) {
                    searchInput.focus();
                }
            });

            notificationsToggle.addEventListener('click', () => {
                const shouldOpen = notificationsPanel.hidden;

                closeHeaderPanels();
                notificationsPanel.hidden = !shouldOpen;
                notificationsToggle.setAttribute('aria-expanded', String(shouldOpen));
            });

            profileToggle.addEventListener('click', () => {
                const shouldOpen = profileMenu.hidden;

                closeHeaderPanels();
                profileMenu.hidden = !shouldOpen;
                profileToggle.setAttribute('aria-expanded', String(shouldOpen));
            });

            document.addEventListener('click', event => {
                if (
                    !event.target.closest('#global-search-panel') &&
                    !event.target.closest('#search-toggle') &&
                    !event.target.closest('#notifications') &&
                    !event.target.closest('#notifications-toggle') &&
                    !event.target.closest('#profile-menu') &&
                    !event.target.closest('#profile-toggle')
                ) {
                    closeHeaderPanels();
                }
            });

            const dismissedStorageKey = <?= json_encode(
                                            'wis-dismissed-header-notifications-' . $role . '-' . (string)($user['id'] ?? '0'),
                                            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                                        ) ?>;

            let dismissedNotifications = {};

            try {
                dismissedNotifications = JSON.parse(
                    localStorage.getItem(dismissedStorageKey) || '{}'
                );

                if (
                    !dismissedNotifications ||
                    typeof dismissedNotifications !== 'object' ||
                    Array.isArray(dismissedNotifications)
                ) {
                    dismissedNotifications = {};
                }
            } catch (error) {
                dismissedNotifications = {};
            }

            const notificationItems = Array.from(
                document.querySelectorAll('.header-notification-item')
            );

            const activeNotificationKeys = new Set(
                notificationItems.map(item => item.dataset.notificationKey)
            );

            Object.keys(dismissedNotifications).forEach(key => {
                if (!activeNotificationKeys.has(key)) {
                    delete dismissedNotifications[key];
                }
            });

            notificationItems.forEach(item => {
                const key = item.dataset.notificationKey;
                const count = Number(item.dataset.notificationCount);

                if (Number(dismissedNotifications[key]) === count) {
                    item.hidden = true;
                } else if (Object.prototype.hasOwnProperty.call(dismissedNotifications, key)) {
                    delete dismissedNotifications[key];
                }
            });

            function saveDismissedNotifications() {
                try {
                    localStorage.setItem(
                        dismissedStorageKey,
                        JSON.stringify(dismissedNotifications)
                    );
                } catch (error) {}
            }

            function updateNotificationTotals() {
                const visibleItems = notificationItems.filter(item => !item.hidden);

                const remainingTotal = visibleItems.reduce((total, item) => {
                    return total + Number(item.dataset.notificationCount || 0);
                }, 0);

                document.querySelectorAll('[data-notification-total]').forEach(badge => {
                    badge.textContent = String(Math.min(remainingTotal, 99));
                    badge.hidden = remainingTotal === 0;
                });

                const indicator = document.querySelector('[data-notification-indicator]');

                if (indicator) {
                    indicator.hidden = remainingTotal === 0;
                }

                const emptyMessage = document.querySelector('[data-no-visible-notifications]');

                if (emptyMessage) {
                    emptyMessage.hidden = visibleItems.length !== 0;
                }
            }

            notificationItems.forEach(item => {
                item.addEventListener('click', () => {
                    const key = item.dataset.notificationKey;
                    const count = Number(item.dataset.notificationCount);

                    dismissedNotifications[key] = count;
                    saveDismissedNotifications();

                    item.hidden = true;
                    updateNotificationTotals();
                    closeHeaderPanels();
                });
            });

            saveDismissedNotifications();
            updateNotificationTotals();

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    closeHeaderPanels();
                    closeNav();
                }
            });

            function openNav() {
                wrap.classList.add('open');
                scrim.hidden = false;
            }

            function closeNav() {
                wrap.classList.remove('open');
                scrim.hidden = true;
            }

            document.getElementById('hamburger')?.addEventListener('click', openNav);
            document.getElementById('mobile-close')?.addEventListener('click', closeNav);
            scrim?.addEventListener('click', closeNav);

            window.addEventListener('resize', () => {
                if (window.innerWidth > 760) {
                    closeNav();
                }
            });
        </script>
    </body>

    </html>
<?php
}
