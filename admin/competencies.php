<?php
declare(strict_types=1);
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_competencies(PDO $pdo): string {
    $emps=employees_for($pdo);
    $comps=$pdo->query('SELECT * FROM competencies ORDER BY name')->fetchAll();
    $records=$pdo->query('SELECT ec.*,e.employee_code,e.first_name,e.last_name,e.department,c.name competency FROM employee_competencies ec JOIN '
        . 'employees e ON e.id=ec.employee_id JOIN competencies c ON c.id=ec.competency_id ORDER BY e.last_name,c.name')->fetchAll();
    $rows=[];
    foreach($records as $r){
        $rows[]=['<div class="person-cell"><span class="avatar">'
        . h(initials($r['first_name'].' '.$r['last_name']))
        . '</span><span><b>'
        . h($r['first_name'].' '.$r['last_name'])
        . '</b><small>'
        . h($r['employee_code'])
        . '</small></span></div>',
            h($r['department']),
            h($r['competency']),
            '<span class="gap-value">'
        . (int)$r['current_score']
        . '%</span>',
            '<span class="progress table-progress"><i class="'
        . ($r['current_score']<$r['target_score']?'warn':'').'" style="width:'.(int)$r['current_score']
        . '%"></i></span>',
            (int)$r['target_score'].'%',
            h($r['last_assessed']?:'Not assessed')];
    }
    $html=page_intro('Competency Management','');
    $html.='<section class="card" style="margin-bottom:1rem">'
    . section_head('Record or update an assessment','Choose a faculty member and specialization competency')
    . '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="'
    . h(csrf_token())
    . '"><input type="hidden" name="action" value="save_competency"><div class="form-field"><label>Faculty member *</label>'
    .  '<select name="employee_id" required><option value="">Select faculty</option>';
    foreach($emps as $e)$html.='<option value="'.(int)$e['id'].'">'.h($e['employee_code'].' · '.$e['first_name']
    . ' '.$e['last_name'])
    . '</option>';
    $html.='</select></div><div class="form-field"><label>Competency *</label><select name="competency_id" required><option value="">Select competency</option>';
    foreach($comps as $c)$html.='<option value="'.(int)$c['id'].'">'.h($c['name'].' · '.$c['category'])
    . '</option>';
    $html.='</select></div><div class="form-field"><label>Current score (0–100) *</label><input name="current_score" type="number" min="0" max="100" value="70" required>'
    .  '</div><div class="form-field"><label>Target score (0–100) *</label><input name="target_score" type="number" min="1" max="100" value="80" required>'
    .  '</div><div class="form-field full"><label>Assessment notes</label><textarea name="notes" rows="2" placeholder="Describe the competency strengths or training needs...">'
    .  '</textarea></div><div class="form-field full"><button class="btn" type="submit">Save assessment '
    . icon('check')
    . '</button></div></form></section>';
    return $html
    . '<section class="card">'
    . section_head('Faculty competency records','Latest available score and target comparison').render_table(['Faculty member',
        'Department',
        'Competency',
        'Score',
        'Readiness',
        'Target',
        'Last assessed'],$rows,'No competency assessments have been recorded yet.')
    . '</section>';
}

$page = 'Competency Management';
$content = render_competencies($pdo);
render_app($pdo, $user, $page, $flash, $content);
