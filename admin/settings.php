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
        '',
        ''
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
        . '<b>HR Training &amp; Development Management System</b>'
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

        . '<section class="card settings-security-card">'
        . '<div class="settings-security-header">'
        . '<span class="settings-security-icon" aria-hidden="true">'
        . icon('shield')
        . '</span>'
        . '<div>'
        . '<h3>Account security</h3>'
        . '<p>Change your password to keep your account secure.</p>'
        . '</div>'
        . '</div>'
        . '<button class="btn settings-security-button" type="button" data-open-modal="reset-password-modal">'
        . 'Change password'
        . '</button>'
        . '</section>'
        . '</div>'
        . '</div>'
        . '<div class="modal-overlay" id="reset-password-modal"'
        . ' role="dialog" aria-modal="true"'
        . ' aria-labelledby="reset-password-title">'

        . '<div class="modal-panel reset-password-panel">'

        // Modal header
        . '<div class="modal-header">'
        . '<div>'
        . '<h2 id="reset-password-title">Reset password</h2>'
        . '<p>Update your current password securely.</p>'
        . '</div>'
        . '<button type="button" class="modal-close"'
        . ' aria-label="Close password reset modal">&times;</button>'
        . '</div>'

        // Password form
        . '<form method="post" class="reset-password-form">'

        . '<input type="hidden" name="csrf_token" value="'
        . h(csrf_token()) . '">'

        . '<input type="hidden" name="action" value="change_password">'

        . '<input type="hidden" name="return_page" value="Settings">'

        // Current password
        . '<div class="form-field password-field">'
        . '<label for="settings-current-password">Current password</label>'
        . '<div class="password-input-wrapper">'
        . '<input id="settings-current-password"'
        . ' name="current_password" type="password"'
        . ' autocomplete="current-password" required>'
        . '<button type="button" class="toggle-password"'
        . ' aria-label="Show current password" aria-pressed="false"'
        . ' data-password-target="settings-current-password">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true">'
        . '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>'
        . '<circle cx="12" cy="12" r="3"></circle>'
        . '</svg>'
        . '</button>'
        . '</div>'
        . '</div>'

        // New password
        . '<div class="form-field password-field">'
        . '<label for="settings-new-password">New password</label>'
        . '<div class="password-input-wrapper">'
        . '<input id="settings-new-password"'
        . ' name="new_password" type="password"'
        . ' minlength="8" autocomplete="new-password" required>'
        . '<button type="button" class="toggle-password"'
        . ' aria-label="Show new password" aria-pressed="false"'
        . ' data-password-target="settings-new-password">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true">'
        . '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>'
        . '<circle cx="12" cy="12" r="3"></circle>'
        . '</svg>'
        . '</button>'
        . '</div>'
        . '</div>'

        // Confirm new password
        . '<div class="form-field password-field">'
        . '<label for="settings-confirm-password">Confirm new password</label>'
        . '<div class="password-input-wrapper">'
        . '<input id="settings-confirm-password"'
        . ' name="confirm_password" type="password"'
        . ' minlength="8" autocomplete="new-password" required>'
        . '<button type="button" class="toggle-password"'
        . ' aria-label="Show confirmation password" aria-pressed="false"'
        . ' data-password-target="settings-confirm-password">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true">'
        . '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>'
        . '<circle cx="12" cy="12" r="3"></circle>'
        . '</svg>'
        . '</button>'
        . '</div>'
        . '</div>'

        // Modal footer
        . '<div class="modal-footer reset-password-actions">'
        . '<button class="btn btn-secondary modal-close" type="button">'
        . 'Cancel'
        . '</button>'
        . '<button class="btn" type="submit">'
        . 'Update password'
        . '</button>'
        . '</div>'

        . '</form>'
        . '</div>'
        . '</div>'

        // Modal and password visibility JavaScript
        . '<script>'
        . '(function () {'
        . 'const modal = document.getElementById("reset-password-modal");'
        . 'if (!modal) return;'

        // Open and close modal
        . 'function setModalOpen(open) {'
        . 'modal.classList.toggle("is-open", open);'
        . 'document.body.style.overflow = open ? "hidden" : "";'
        . 'if (open) {'
        . 'const firstInput = modal.querySelector("input[type=password]");'
        . 'if (firstInput) firstInput.focus();'
        . '}'
        . '}'

        . 'document.querySelectorAll('
        . '"[data-open-modal=\\"reset-password-modal\\"]"'
        . ').forEach(function (trigger) {'
        . 'trigger.addEventListener("click", function (event) {'
        . 'event.preventDefault();'
        . 'setModalOpen(true);'
        . '});'
        . '});'

        . 'modal.addEventListener("click", function (event) {'
        . 'if (event.target === modal) setModalOpen(false);'
        . '});'

        . 'modal.querySelectorAll(".modal-close").forEach(function (button) {'
        . 'button.addEventListener("click", function () {'
        . 'setModalOpen(false);'
        . '});'
        . '});'

        . 'document.addEventListener("keydown", function (event) {'
        . 'if (event.key === "Escape" && modal.classList.contains("is-open")) {'
        . 'setModalOpen(false);'
        . '}'
        . '});'

        // Toggle password visibility
        . 'modal.querySelectorAll(".toggle-password").forEach(function (button) {'
        . 'button.addEventListener("click", function () {'
        . 'const input = document.getElementById(button.dataset.passwordTarget);'
        . 'if (!input) return;'

        . 'const show = input.type === "password";'
        . 'input.type = show ? "text" : "password";'

        . 'button.setAttribute("aria-pressed", String(show));'

        . 'const label = input.id === "settings-current-password"'
        . ' ? "current password"'
        . ' : input.id === "settings-new-password"'
        . ' ? "new password" : "confirmation password";'

        . 'button.setAttribute("aria-label",'
        . ' (show ? "Hide " : "Show ") + label);'
        . '});'
        . '});'

        // Reset visibility whenever the modal closes
        . 'modal.querySelectorAll(".toggle-password").forEach(function (button) {'
        . 'button.addEventListener("click", function () {'
        . 'const input = document.getElementById(button.dataset.passwordTarget);'
        . 'if (input && input.type === "password") {'
        . 'button.setAttribute("aria-pressed", "false");'
        . '}'
        . '});'
        . '});'

        . '})();'
        . '</script>';

    return $html;
}

$page = 'Settings';
$content = render_settings($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
