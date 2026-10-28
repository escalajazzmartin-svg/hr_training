<?php

declare(strict_types=1);

function render_login(?array $flash): void
{
?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Sign in | WIS HR Training and Development</title>
        <link rel="stylesheet" href="assets/style.css">
        <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    </head>

    <body>
        <main class="login-page">
            <section class="login-brand">
                <div class="brand-seal large">
                    <span>WIS</span>
                </div>
                <div class="eyebrow light">Westmead International School</div>
                <h1>Building people.<br>
                    <em>Strengthening education.</em>
                </h1>
                <p>A centralized platform for faculty competency, professional development, and training excellence.</p>
                <div class="trust-row">
                    <span><?= icon('shield') ?> Secure access</span>
                    <span><?= icon('users') ?> Role-based workspace</span>
                </div>
            </section>
            <section class="login-panel">
                <div class="login-box">
                    <div class="mobile-logo">
                        <div class="brand-seal">
                            <span>WIS</span>
                        </div>
                        <b>HR Training and Development Management System</b>
                    </div>
                    <span class="eyebrow">Welcome back</span>
                    <h2>Sign in to your workspace</h2>

                    <?php if ($flash): ?>
                        <div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>">
                            <?= h($flash['message']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="login">

                        <label class="field-label" for="login-email">Email address</label>
                        <input
                            class="login-field"
                            id="login-email"
                            name="email"
                            type="email"
                            required
                            autocomplete="username"
                            aria-label="Email address">

                        <label class="field-label" for="login-password">Password</label>
                        <div class="password-field">
                            <input
                                class="login-field"
                                id="login-password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password">
                            <button
                                type="button"
                                class="icon-btn"
                                id="toggle-password"
                                aria-label="Show password"><?= icon('eye') ?></button>
                        </div>

                        <div class="form-meta">
                            <label>
                                <input type="checkbox"> Remember me
                            </label>
                            <span class="small-muted">Forgot password? Contact your HR officer.</span>
                        </div>

                        <button class="btn" type="submit">Sign in securely <?= icon('arrow') ?></button>

                        <p class="privacy"><?= icon('shield', 15) ?> Your information is protected by the school's data privacy policy.</p>
                    </form>
                </div>
            </section>
        </main>

        <script>
            document.getElementById('toggle-password').addEventListener('click', () => {
                const password = document.getElementById('login-password');
                const showing = password.type === 'password';

                password.type = showing ? 'text' : 'password';
                document.getElementById('toggle-password').setAttribute(
                    'aria-label',
                    showing ? 'Hide password' : 'Show password'
                );
            });
        </script>
    </body>

    </html>
<?php
}
