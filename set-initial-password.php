<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/page_bootstrap.php';

$token = trim(
    (string) ($_GET['token'] ?? $_POST['token'] ?? '')
);

$error = '';
$success = false;
$tokenRow = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare(
        'SELECT t.id AS token_id, t.user_id
         FROM employee_password_setup_tokens t
         INNER JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ?
           AND t.expires_at > NOW()
         LIMIT 1'
    );

    $stmt->execute([hash('sha256', $token)]);

    $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$tokenRow) {
    $error = 'This password setup link is invalid, expired, or already used. Ask HR to issue a new link.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    $csrf = (string) ($_POST['csrf_token'] ?? '');

    if (
        $csrf === '' ||
        !hash_equals((string) csrf_token(), $csrf)
    ) {
        http_response_code(403);
        exit('Invalid security token. Reload the page and try again.');
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($password) < 10) {
        $error = 'Choose a password with at least 10 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The passwords do not match. Please re-enter them.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE users
                 SET password_hash = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                password_hash($password, PASSWORD_DEFAULT),
                (int) $tokenRow['user_id']
            ]);

            // Delete the token so it cannot be used again.
            $stmt = $pdo->prepare(
                'DELETE FROM employee_password_setup_tokens
                 WHERE user_id = ?'
            );

            $stmt->execute([
                (int) $tokenRow['user_id']
            ]);

            $pdo->commit();

            $success = true;
            $tokenRow = null;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Initial password setup failed: ' . $e->getMessage()
            );

            $error = 'Unable to save your password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Set Initial Password</title>

    <link rel="stylesheet" href="assets/style.css">

    <style>
        .password-setup-card {
            width: min(480px, calc(100% - 32px));
            margin: 8vh auto;
            padding: 28px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
        }

        .password-setup-card label {
            display: block;
            margin: 16px 0 6px;
            font-weight: 600;
        }

        .password-setup-card input {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
        }

        .password-setup-card button {
            margin-top: 20px;
            width: 100%;
        }

        .password-setup-error {
            color: #b91c1c;
        }

        .password-setup-success {
            color: #166534;
        }
    </style>
</head>

<body>

    <main class="password-setup-card">

        <h1>Set your initial password</h1>

        <?php if ($success): ?>

            <p class="password-setup-success">
                Your password has been set successfully. You can now sign in.
            </p>

            <p>
                <a class="btn" href="index.php">Go to login</a>
            </p>

        <?php else: ?>

            <?php if ($error !== ''): ?>
                <p class="password-setup-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <?php if ($tokenRow): ?>

                <p>
                    Create a password for your HR Training System account.
                    Use at least 10 characters.
                </p>

                <form method="post">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="password">New password</label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        minlength="10"
                        autocomplete="new-password"
                        required>

                    <label for="confirm_password">Confirm password</label>

                    <input
                        id="confirm_password"
                        name="confirm_password"
                        type="password"
                        minlength="10"
                        autocomplete="new-password"
                        required>

                    <button class="btn" type="submit">
                        Set password
                    </button>

                </form>

            <?php endif; ?>

        <?php endif; ?>

    </main>

</body>

</html>