<?php

declare(strict_types=1);

function render_recommendations(PDO $pdo, array $user): string
{
    $eid = (int)($user['employee_id'] ?? 0);
    $st = $pdo->prepare('SELECT p.*, (SELECT COUNT(*) FROM registrations r WHERE r.program_id=p.id AND r.employee_id=?) existing FROM '
        . 'training_programs p WHERE p.status IN (\'Upcoming\',\'Ongoing\') ORDER BY CASE WHEN p.specialization=(SELECT specialization FROM '
        . 'employees WHERE id=?) THEN 0 ELSE 1 END,p.start_date LIMIT 6');
    $st->execute([
        $eid,
        $eid
    ]);
    $progs = $st->fetchAll();
    $html = '<div class="program-grid">';
    foreach ($progs as $p) {
        $html .= '<article class="program-card recommendation-card"><div class="program-top"><span class="program-icon">'
            . icon('book', 22)
            . '</span>'
            . badge($p['status'])
            . '</div><h3>'
            . h($p['title'])
            . '</h3><p>'
            . h($p['description'] ?: 'Professional training to strengthen faculty competency.')
            . '</p><div><span>'
            . icon('target', 14) . ' ' . h($p['specialization'])
            . '</span><span>'
            . icon('calendar', 14) . ' ' . h(date('M j, Y', strtotime($p['start_date'])))
            . '</span><span>'
            . icon('users', 14) . ' Capacity: ' . (int)$p['capacity']
            . '</span></div>';
        if ((int)$p['existing']) $html .= '<span class="badge badge-success">Already registered</span>';
        else $html .= '<form method="post" action="index.php?page=Available Training"><input type="hidden" name="csrf_token" value="'
            . h(csrf_token())
            . '"><input type="hidden" name="action" value="register_training"><input type="hidden" name="program_id" value="'
            . (int)$p['id']
            . '"><button class="btn" type="submit">Register '
            . icon('arrow', 14)
            . '</button></form>';
        $html .= '</article>';
    }
    return $html
        . '</div>';
}
