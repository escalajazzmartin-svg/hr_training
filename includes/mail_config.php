<?php

declare(strict_types=1);

/**
 * SMTP configuration for HR Training email verification and notifications.
 *
 * IMPORTANT:
 * - Keep this file private.
 * - Never commit your credentials to GitHub.
 * - Use a newly generated Google App Password.
 */

return [
    // Gmail SMTP server
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',

    // Gmail account used to send verification and setup emails
    'username'   => 'escalajazzmartin@gmail.com',

    // Replace with your NEW Google App Password.
    'password'   => 'umop rsws eqhi wadf',

    // Sender information — must match your Gmail account
    'from_email' => 'escalajazzmartin@gmail.com',
    'from_name'  => 'HR Training System',
];
