<?php

declare(strict_types=1);

$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_settings(PDO $pdo, array $user): string
{
    $displayName = user_display($user);
    $email = (string) ($user['email'] ?? '');
    $role = (string) ($user['role'] ?? '');

    $html = page_intro(
        'Settings',
        'Manage your account profile and workspace information.'
    );

    $html .= '<div class="settings-page">'
        . '<div class="dashboard-grid">'
        . '<section class="card settings-profile-card span-2">'
        . section_head(
            'Account profile',
            'Update the name displayed in the workspace'
        )
        . '<form method="post" class="form-grid settings-profile-form">'
        . '<input type="hidden" name="csrf_token" value="'
        . h(csrf_token())
        . '">'
        . '<input type="hidden" name="action" value="update_profile">'

        . '<div class="form-field full">'
        . '<label for="settings-display-name">Display name</label>'
        . '<input id="settings-display-name" name="display_name" value="'
        . h($displayName)
        . '" autocomplete="name" required>'
        . '<small class="small-muted">This name appears in your workspace.</small>'
        . '</div>'

        . '<div class="form-field full">'
        . '<label for="settings-email">Email address</label>'
        . '<input id="settings-email" type="email" name="email" value="'
        . h($email)
        . '" autocomplete="email" required>'
        . '<small class="small-muted">This email address is used to sign in to your account.</small>'
        . '</div>'

        . '<div class="form-field full">'
        . '<label for="settings-role">Workspace role</label>'
        . '<input id="settings-role" value="'
        . h(role_label($role))
        . '" disabled>'
        . '<small class="small-muted">Your workspace role is managed by the system.</small>'
        . '</div>'

        . '<div class="form-field full settings-form-actions">'
        . '<button class="btn" type="submit">'
        . icon('check')
        . '<span>Save profile</span>'
        . '</button>'
        . '</div>'
        . '</form>'
        . '</section>'

        . '<section class="card settings-system-card">'
        . section_head(
            'System information',
            'Local development setup'
        )
        . '<div class="settings-info-list">'
        . '<div class="settings-info-item">'
        . '<span class="settings-info-label">Application</span>'
        . '<b>HR Training &amp; Development</b>'
        . '</div>'
        . '<div class="settings-info-item">'
        . '<span class="settings-info-label">Database</span>'
        . '<b>MySQL / MariaDB</b>'
        . '</div>'
        . '<div class="settings-info-item">'
        . '<span class="settings-info-label">Environment</span>'
        . '<b>Local XAMPP</b>'
        . '</div>'
        . '<div class="settings-info-item">'
        . '<span class="settings-info-label">Session security</span>'
        . '<b>Role-based access</b>'
        . '</div>'
        . '</div>'
        . '</section>'
        . '</div>'
        . '</div>';

    return $html;
}

$page = 'Settings';
$content = render_settings($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
