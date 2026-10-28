<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

function attendance_qr_ensure_schema(PDO $pdo): void
{
    $check = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'time_in'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN time_in DATETIME NULL AFTER marked_at");
        $pdo->exec("ALTER TABLE attendance ADD COLUMN time_out DATETIME NULL AFTER time_in");
    }

    $exists = $pdo->query("SHOW TABLES LIKE 'attendance_qr_tokens'");
    if ($exists->rowCount() === 0) {
        $pdo->exec(
            "CREATE TABLE attendance_qr_tokens (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                program_id INT UNSIGNED NOT NULL,
                action ENUM('time_in','time_out') NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_by INT UNSIGNED NOT NULL,
                used_by INT UNSIGNED NULL,
                used_at DATETIME NULL,
                revoked_at DATETIME NULL,
                revoked_reason VARCHAR(100) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_qr_token_hash (token_hash),
                KEY idx_qr_program_action (program_id, action),
                KEY idx_qr_expires_at (expires_at),
                CONSTRAINT fk_qr_program FOREIGN KEY (program_id) REFERENCES training_programs (id) ON DELETE CASCADE,
                CONSTRAINT fk_qr_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_qr_user FOREIGN KEY (used_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
}

function attendance_qr_valid_actions(): array
{
    return ['time_in', 'time_out'];
}

function attendance_qr_hash(string $token): string
{
    return hash('sha256', $token);
}

function attendance_qr_current_url(string $token, string $action, int $programId): string
{
    return $action . ':' . (string)$programId . ':' . $token;
}

function attendance_qr_payload(string $token, string $action, int $programId): string
{
    return attendance_qr_current_url($token, $action, $programId);
}

function attendance_qr_decode(string $payload): array
{
    $payload = trim($payload);
    if ($payload === '') {
        throw new InvalidArgumentException('The QR code is empty.');
    }

    $parts = explode(':', $payload);
    if (count($parts) !== 3) {
        throw new InvalidArgumentException('The QR code payload is invalid.');
    }

    [$action, $programId, $token] = $parts;
    $action = strtolower(trim($action));
    $programId = (int)trim($programId);
    $token = trim($token);

    if (!in_array($action, attendance_qr_valid_actions(), true)) {
        throw new InvalidArgumentException('The QR code action is invalid.');
    }

    if ($programId <= 0 || $token === '') {
        throw new InvalidArgumentException('The QR code payload is incomplete.');
    }

    return ['action' => $action, 'program_id' => $programId, 'token' => $token];
}

function attendance_qr_generate(PDO $pdo, int $trainerUserId, int $programId, string $action, int $ttlMinutes = 180): array
{
    $action = strtolower(trim($action));
    if (!in_array($action, attendance_qr_valid_actions(), true)) {
        throw new InvalidArgumentException('Invalid QR attendance action.');
    }

    $programStmt = $pdo->prepare(
        'SELECT id, trainer_user_id, title, status
         FROM training_programs
         WHERE id = ? AND trainer_user_id = ? LIMIT 1'
    );
    $programStmt->execute([$programId, $trainerUserId]);
    $program = $programStmt->fetch(PDO::FETCH_ASSOC);

    if (!$program) {
        throw new RuntimeException('This training program is not assigned to your account.');
    }

    if (in_array((string)($program['status'] ?? ''), ['Cancelled'], true)) {
        throw new RuntimeException('Cancelled training sessions cannot generate QR attendance codes.');
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+' . $ttlMinutes . ' minutes')
        ->format('Y-m-d H:i:s');
    $hash = attendance_qr_hash($token);

    $pdo->beginTransaction();

    $invalidateStmt = $pdo->prepare(
        'UPDATE attendance_qr_tokens
         SET revoked_at = UTC_TIMESTAMP(), revoked_reason = ?
         WHERE program_id = ? AND action = ? AND revoked_at IS NULL AND used_at IS NULL'
    );
    $invalidateStmt->execute([
        'regenerated',
        $programId,
        $action,
    ]);

    $insertStmt = $pdo->prepare(
        'INSERT INTO attendance_qr_tokens
         (program_id, action, token_hash, expires_at, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $insertStmt->execute([$programId, $action, $hash, $expiresAt, $trainerUserId]);

    $pdo->commit();

    return [
        'token' => $token,
        'action' => $action,
        'program_id' => $programId,
        'expires_at' => $expiresAt,
        'qr_payload' => attendance_qr_payload($token, $action, $programId),
    ];
}

function attendance_qr_latest_for_program(PDO $pdo, int $programId, string $action): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM attendance_qr_tokens
         WHERE program_id = ? AND action = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([$programId, $action]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return $row;
}

function attendance_qr_validate(PDO $pdo, string $token): ?array
{
    $token = trim((string)$token);
    if ($token === '') {
        return null;
    }

    $tokenHash = attendance_qr_hash($token);
    $stmt = $pdo->prepare(
        'SELECT *
         FROM attendance_qr_tokens
         WHERE token_hash = ?
         AND revoked_at IS NULL
         AND used_at IS NULL
         AND expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    return $record ?: null;
}

function attendance_qr_status_text(string $action): string
{
    return $action === 'time_in' ? 'Time-in' : 'Time-out';
}

function attendance_qr_record(PDO $pdo, int $employeeUserId, int $employeeId, int $programId, string $token, ?string $expectedAction = null): array
{
    $record = attendance_qr_validate($pdo, $token);
    if (!$record) {
        throw new RuntimeException('This QR code is expired, revoked, invalid, or already used.');
    }

    $resolvedProgramId = $programId > 0 ? $programId : (int)$record['program_id'];
    if ($programId > 0 && (int)$record['program_id'] !== $programId) {
        throw new RuntimeException('This QR code does not belong to the selected training session.');
    }

    if ($expectedAction !== null && strtolower((string)$expectedAction) !== strtolower((string)$record['action'])) {
        throw new RuntimeException('This QR code is for a different attendance action.');
    }

    $action = (string)$record['action'];
    $programStmt = $pdo->prepare(
        'SELECT p.id, p.title, p.start_date, p.start_time, p.end_date, p.status, r.id AS registration_id
         FROM training_programs p
         LEFT JOIN registrations r ON r.program_id = p.id AND r.employee_id = ?
         WHERE p.id = ?
         LIMIT 1'
    );
    $programStmt->execute([$employeeId, $resolvedProgramId]);
    $program = $programStmt->fetch(PDO::FETCH_ASSOC);

    if (!$program) {
        throw new RuntimeException('You are not registered for this training session.');
    }

    if (in_array((string)($program['status'] ?? ''), ['Cancelled', 'Completed'], true)) {
        throw new RuntimeException('This training session is closed and cannot accept attendance.');
    }

    $registrationId = (int)($program['registration_id'] ?? 0);
    if ($registrationId <= 0) {
        throw new RuntimeException('You are not registered for this training session.');
    }

    $programId = $resolvedProgramId;

    $attendanceStmt = $pdo->prepare(
        'SELECT * FROM attendance WHERE registration_id = ? LIMIT 1'
    );
    $attendanceStmt->execute([$registrationId]);
    $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

    if ($action === 'time_in') {
        if ($attendance && $attendance['time_in'] !== null && $attendance['time_in'] !== '') {
            throw new RuntimeException('You have already recorded a time-in for this session.');
        }

        $status = 'Present';
        if (!empty($program['start_time'])) {
            $expected = new DateTimeImmutable(
                (string)$program['start_date'] . ' ' . (string)$program['start_time'],
                new DateTimeZone('UTC')
            );
            $current = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            if ($current > $expected->modify('+15 minutes')) {
                $status = 'Late';
            }
        }

        if ($attendance) {
            $updateStmt = $pdo->prepare(
                'UPDATE attendance
                 SET time_in = UTC_TIMESTAMP(),
                     marked_by = ?,
                     marked_at = UTC_TIMESTAMP(),
                     attendance_status = ?,
                     remarks = COALESCE(remarks, "Time-in recorded via QR")
                 WHERE registration_id = ?'
            );
            $updateStmt->execute([$employeeUserId, $status, $registrationId]);
        } else {
            $insertStmt = $pdo->prepare(
                'INSERT INTO attendance
                 (registration_id, attendance_status, remarks, marked_by, time_in, marked_at)
                 VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $insertStmt->execute([$registrationId, $status, 'Time-in recorded via QR', $employeeUserId]);
        }

        $markUsed = $pdo->prepare('UPDATE attendance_qr_tokens SET used_at = UTC_TIMESTAMP(), used_by = ? WHERE id = ?');
        $markUsed->execute([$employeeUserId, (int)$record['id']]);

        $time = $pdo->prepare('SELECT time_in FROM attendance WHERE registration_id = ? LIMIT 1');
        $time->execute([$registrationId]);
        $value = $time->fetchColumn();

        return [
            'success' => true,
            'action' => 'time_in',
            'message' => 'Time-in recorded successfully at ' . date('g:i A', strtotime((string)$value)),
        ];
    }

    if ($action === 'time_out') {
        if (!$attendance || empty($attendance['time_in'])) {
            throw new RuntimeException('You must record a valid time-in before you can time out.');
        }

        if (!empty($attendance['time_out'])) {
            throw new RuntimeException('You have already recorded a time-out for this session.');
        }

        $updateStmt = $pdo->prepare(
            'UPDATE attendance
             SET time_out = UTC_TIMESTAMP(),
                 marked_by = ?,
                 marked_at = UTC_TIMESTAMP(),
                 attendance_status = COALESCE(NULLIF(attendance_status, ""), "Present")
             WHERE registration_id = ?'
        );
        $updateStmt->execute([$employeeUserId, $registrationId]);

        $markUsed = $pdo->prepare('UPDATE attendance_qr_tokens SET used_at = UTC_TIMESTAMP(), used_by = ? WHERE id = ?');
        $markUsed->execute([$employeeUserId, (int)$record['id']]);

        $time = $pdo->prepare('SELECT time_out FROM attendance WHERE registration_id = ? LIMIT 1');
        $time->execute([$registrationId]);
        $value = $time->fetchColumn();

        return [
            'success' => true,
            'action' => 'time_out',
            'message' => 'Time-out recorded successfully at ' . date('g:i A', strtotime((string)$value)),
        ];
    }

    throw new RuntimeException('Unsupported QR attendance action.');
}
