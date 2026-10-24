<?php

declare(strict_types=1);

function render_assessments(PDO $pdo, array $user): string
{
    $sql = 'SELECT r.id registration_id,r.status,e.employee_code,e.first_name,e.last_name,p.title,ev.score,ev.feedback FROM '
        . 'registrations r JOIN employees e ON e.id=r.employee_id JOIN training_programs p ON p.id=r.program_id LEFT JOIN evaluations '
        . 'ev ON ev.registration_id=r.id';
    $params = [];
    if ($user['role'] === 'trainer') {
        $sql .= ' WHERE p.trainer_user_id=?';
        $params[] = $user['id'];
    } elseif ($user['role'] === 'employee') {
        $sql .= ' WHERE r.employee_id=?';
        $params[] = (int)$user['employee_id'];
    }
    $sql .= ' ORDER BY p.start_date DESC,e.last_name';
    $s = $pdo->prepare($sql);
    $s->execute($params);
    $data = $s->fetchAll();
    $html = page_intro($user['role'] === 'trainer' ? 'Assessments' : 'Assessments & Evaluations', 'Record a training score and feedback for each participant.');
    if ($user['role'] === 'employee') {
        $rows = [];
        foreach ($data as $r) $rows[] = [
            h($r['title']),
            badge($r['score'] === null ? 'Pending' : 'Scored'),
            $r['score'] === null ? '—' : h(number_format(
                (float)$r['score'],
                1
            ) . '%'),
            h($r['feedback'] ?: 'No feedback recorded')
        ];
        return $html
            . '<section class="card">'
            . section_head('My assessment results', 'Scores and feedback recorded by your trainer.') . render_table([
                'Training program',
                'Status',
                'Score',
                'Feedback'
            ], $rows, 'No assessment results have been recorded yet.')
            . '</section>';
    }
    $html .= '<div class="dashboard-grid">';
    foreach ($data as $r) {
        $html .= '<section class="card span-1"><div class="program-top"><span class="program-icon">'
            . icon('chart')
            . '</span>'
            . badge($r['score'] === null ? 'Pending' : 'Scored')
            . '</div><h3>'
            . h($r['first_name'] . ' ' . $r['last_name'])
            . '</h3><p class="small-muted">'
            . h($r['employee_code']) . ' · ' . h($r['title'])
            . '</p><form method="post"><input type="hidden" name="csrf_token" value="'
            . h(csrf_token())
            . '"><input type="hidden" name="action" value="save_evaluation"><input type="hidden" name="registration_id" value="'
            . (int)$r['registration_id']
            . '"><div class="form-field"><label>Assessment score (0–100)</label><input type="number" name="score" min="0" max="100" step="0.5" value="'
            . h($r['score'] ?? '')
            . '" required></div><div class="form-field" style="margin:10px 0"><label>Feedback</label><textarea name="feedback" rows="3">'
            . h($r['feedback'] ?? '')
            . '</textarea></div><button class="btn" type="submit">Save assessment '
            . icon('check', 14)
            . '</button></form></section>';
    }
    $html .= '</div>';
    if (!$data) $html .= '<section class="card"><div class="empty-module"><h3>No participants to assess</h3><p>Training registrations will appear here when faculty are registered.</p>'
        .  '</div></section>';
    return $html;
}
