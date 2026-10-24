<?php
declare(strict_types=1);
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_settings(PDO $pdo,array $user): string {
    return page_intro('Settings','Manage your account profile and workspace information.')
    . '<div class="dashboard-grid"><section class="card span-2">'
    . section_head('Account profile','Update the name displayed in the workspace')
    . '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="'
    . h(csrf_token())
    . '"><input type="hidden" name="action" value="update_profile"><div class="form-field full"><label>Display name</label>'
    .  '<input name="display_name" value="'.h(user_display($user))
       . '" required></div><div class="form-field full"><label for="settings-email">Email address</label><input id="settings-email" type="email" name="email" value="'
    . h($user['email'])
    . '" required><small class="small-muted">This email address is used to sign in to your account.</small>'
    . '</div><div class="form-field full"><label>Workspace role</label><input value="'
    . h(role_label($user['role']))
    . '" disabled></div><div class="form-field full"><button class="btn" type="submit">Save profile '
    . icon('check')
    . '</button></div></form></section><section class="card">'
    . section_head('System information','Local development setup')
    . '<div class="sample-records"><div><span>Application</span><span><b>HR Training & Development</b></span></div>'
    .  '<div><span>Database</span><span><b>MySQL / MariaDB</b></span></div><div><span>Environment</span><span><b>Local XAMPP</b>'
    .  '</span></div><div><span>Session security</span><span><b>Role-based access</b></span></div></div></section>'
    .  '</div>';
}

$page = 'Settings';
$content = render_settings($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
