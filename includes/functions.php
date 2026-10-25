<?php

declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function app_base_url(): string
{
    $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $scriptDir = rtrim($scriptDir, '/');
    if (preg_match('~/(admin|employee|trainer)$~', $scriptDir)) {
        $scriptDir = substr($scriptDir, 0, strrpos($scriptDir, '/'));
    }
    return $scriptDir === '/' ? '' : $scriptDir;
}

function app_page_map(): array
{
    return [
        'admin' => [
            'Dashboard' => 'dashboard.php',
            'Employees' => 'employees.php',
            'Employee Profiles' => 'employee-profile.php',
            'Competency Management' => 'competencies.php',
            'Training Needs Analysis' => 'training-needs.php',
            'Training Programs' => 'trainings.php',
            'Training Calendar' => 'calendar.php',
            'Employee Registration' => 'registrations.php',
            'Attendance Monitoring' => 'attendance.php',
            'Assessments & Evaluations' => 'evaluations.php',
            'Assessments' => 'evaluations.php',
            'Certificates' => 'certificates.php',
            'Training History' => 'history.php',
            'Reports' => 'reports.php',
            'Settings' => 'settings.php',
            'My Training' => 'my-training.php',
            'Available Training' => 'available-training.php',
            'Recommended Training' => 'recommended-training.php',
            'Training Results' => 'results.php',
        ],
        'employee' => [
            'Dashboard' => 'dashboard.php',
            'My Profile' => 'profile.php',
            'Recommended Training' => 'recommended-training.php',
            'Available Training' => 'available-training.php',
            'My Training' => 'my-training.php',
            'My Attendance' => 'attendance.php',
            'My Assessments' => 'evaluations.php',
            'Assessments' => 'evaluations.php',
            'Assessments & Evaluations' => 'evaluations.php',
            'My Certificates' => 'certificates.php',
            'Certificates' => 'certificates.php',
            'My Training History' => 'history.php',
            'Training History' => 'history.php',
        ],
        'trainer' => [
            'Dashboard' => 'dashboard.php',
            'My Training Programs' => 'trainings.php',
            'Training Programs' => 'trainings.php',
            'Attendees' => 'attendees.php',
            'Attendance Monitoring' => 'attendance.php',
            'Assessments' => 'tests.php',
            'Assessments & Evaluations' => 'tests.php',
            'Training Results' => 'results.php',
            'Certificates' => 'certificates.php',
        ],
    ];
}

function app_page_url(string $page, ?string $role = null, array $extra = []): string
{
    $current = current_user();
    $role = $role ?? (string)($current['role'] ?? 'admin');
    $map = app_page_map();
    $file = $map[$role][$page] ?? $map[$role]['Dashboard'];
    $url = app_base_url() . '/' . $role . '/' . $file;
    if ($extra) $url .= '?' . http_build_query($extra);
    return $url;
}

function redirect(string $page = 'Dashboard', array $extra = []): never
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . app_base_url() . '/index.php');
        exit;
    }
    header('Location: ' . app_page_url($page, (string)$user['role'], $extra));
    exit;
}

function rewrite_page_links(string $html, string $role): string
{
    $labels = [];
    foreach (app_page_map() as $roleMap) foreach (array_keys($roleMap) as $label) $labels[$label] = true;
    $labels = array_keys($labels);
    usort($labels, static fn($a, $b) => strlen($b) <=> strlen($a));

    return preg_replace_callback("~\\b(href|action)=([\"'])([^\"']*)\\2~i", static function ($m) use ($labels, $role) {
        $attr = $m[1];
        $quote = $m[2];
        $value = $m[3];
        if (!preg_match('~^(?:index\.php)?\?page=(.*)$~s', $value, $parts)) return $m[0];
        $target = $parts[1];
        foreach ($labels as $label) {
            if (strncmp($target, $label, strlen($label)) !== 0) continue;
            $tail = substr($target, strlen($label));
            if ($tail !== '' && $tail[0] !== '&') continue;
            $mappedRole = $role;
            if (!isset(app_page_map()[$mappedRole][$label])) {
                foreach (app_page_map() as $candidateRole => $candidateMap) {
                    if (isset($candidateMap[$label])) {
                        $mappedRole = $candidateRole;
                        break;
                    }
                }
            }
            $url = app_page_url($label, $mappedRole);
            if ($tail !== '') $url .= '?' . ltrim($tail, '&');
            return $attr . '=' . $quote . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . $quote;
        }
        return $m[0];
    }, $html) ?? $html;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . app_base_url() . '/index.php');
        exit;
    }
    return $user;
}
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page, and try again.');
    }
}
function write_app_log(string $message): void
{
    $logFile = dirname(__DIR__) . '/employee_registration_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$timestamp}] {$message}" . PHP_EOL, 3, $logFile);
}
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
function db_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    $allowed = ['users', 'employees', 'competencies', 'employee_competencies', 'training_programs', 'registrations', 'attendance', 'certificates', 'evaluations'];
    if (!in_array($table, $allowed, true)) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}
function role_label(string $role): string
{
    return ['admin' => 'HR Officer', 'employee' => 'Faculty Member', 'trainer' => 'Trainer'][$role] ?? ucfirst($role);
}
function user_display(array $user): string
{
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['name'] ?? 'User');
}
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(substr($parts[0] ?? 'U', 0, 1) . (count($parts ?? []) > 1 ? substr($parts[count($parts) - 1], 0, 1) : ''));
}
function nav_for(string $role): array
{
    $all = [
        'admin' => [['Dashboard', 'grid'], ['Employees', 'users'], ['Employee Profiles', 'user'], ['Competency Management', 'target'], ['Training Needs Analysis', 'search'], ['Training Programs', 'book'], ['Training Calendar', 'calendar'], ['Employee Registration', 'clipboard'], ['Attendance Monitoring', 'check'], ['Assessments & Evaluations', 'chart'], ['Certificates', 'award'], ['Training History', 'history'], ['Reports', 'chart'], ['Settings', 'settings']],
        'employee' => [['Dashboard', 'grid'], ['My Profile', 'user'], ['Recommended Training', 'target'], ['Available Training', 'book'], ['My Training', 'calendar'], ['My Attendance', 'check'], ['My Assessments', 'chart'], ['My Certificates', 'award'], ['My Training History', 'history']],
        'trainer' => [['Dashboard', 'grid'], ['My Training Programs', 'book'], ['Attendees', 'users'], ['Attendance Monitoring', 'check'], ['Assessments', 'clipboard'], ['Training Results', 'chart']],
    ];
    return $all[$role] ?? $all['employee'];
}
function icon(string $name, int $size = 18): string
{
    $p = [
        'alert-triangle' => '<path d="M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 3h.01"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5h6a1.5 1.5 0 0 0-1.5-1.5h-3A1.5 1.5 0 0 0 9 4.5ZM8 11h8M8 15h8"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="m8.2 13-1 8L12 18l4.8 3-1-8"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 14 4-4 4 3 5-7"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.6-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8l-1.4-1.1 1.4-2.4 1.7.6a8 8 0 0 1 1.5-.9l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.5.9l1.7-.6 1.4 2.4-1.4 1.1a7 7 0 0 1 0 1.7Z"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'location' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/>',
        'filter' => '<path d="M4 6h16M7 12h10m-7 6h4"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'close' => '<path d="m18 6-12 12M6 6l12 12"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? $p['grid']) . '</svg>';
}
function badge(string $value): string
{
    $v = strtolower($value);
    $class = 'badge-neutral';
    if (str_contains($v, 'complete') || str_contains($v, 'competent') || str_contains($v, 'present') || str_contains($v, 'approved') || str_contains($v, 'active')) $class = 'badge-success';
    elseif (str_contains($v, 'required') || str_contains($v, 'absent') || str_contains($v, 'overdue') || str_contains($v, 'declined')) $class = 'badge-danger';
    elseif (str_contains($v, 'improve') || str_contains($v, 'pending') || str_contains($v, 'late') || str_contains($v, 'upcoming')) $class = 'badge-warn';
    return '<span class="badge ' . $class . '">' . h($value) . '</span>';
}
function metric(string $label, string|int $value, string $note, string $iconName = 'users', string $tone = ''): string
{
    return '<div class="metric-card"><div class="metric-icon ' . h($tone) . '">' . icon($iconName) . '</div><div class="metric-main"><span>' . h($label) . '</span><strong>' . h($value) . '</strong><small>' . h($note) . '</small></div></div>';
}
function section_head(string $title, string $sub = '', string $action = ''): string
{
    return '<div class="section-head"><div><h2>' . h($title) . '</h2>' . ($sub !== '' ? '<p>' . h($sub) . '</p>' : '') . '</div>' . $action . '</div>';
}

function post_string(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function signed_in_user(PDO $pdo, string $role): array
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('You do not have permission to perform that action.');
    }
    return $user;
}
