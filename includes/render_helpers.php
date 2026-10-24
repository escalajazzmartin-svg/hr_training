<?php
declare(strict_types=1);

function render_table(array $headers, array $rows, string $empty='No records found.'): string {
    $html='<div class="table-wrap"><table><thead><tr>';
    foreach($headers as $header)$html.='<th>'
    . h($header)
    . '</th>';
    $html.='</tr></thead><tbody>';
    if (!$rows) $html.='<tr><td colspan="'
    . count($headers).'" style="text-align:center;padding:2rem;color:var(--muted)">'.h($empty)
    . '</td></tr>';
    else foreach($rows as $row) {
        $html.='<tr>';
        foreach($row as $cell)$html.='<td>'
        . $cell
        . '</td>';
        $html.='</tr>';
    }
    return $html
    . '</tbody></table></div>';
}

function page_intro(string $title,string $description,string $action=''): string {
    return '<div class="page-intro"><div><span class="eyebrow">Training & Development</span><h2>'
    . h($title)
    . '</h2><p>'
    . h($description)
    . '</p></div>'
    . $action
    . '</div>';
}

function card_open(string $title,string $sub=''): string {
    return '<section class="card">'
    . section_head($title,$sub);
}

function program_select(PDO $pdo): array {
    return $pdo->query("SELECT id,title,start_date,status FROM training_programs WHERE status <> 'Cancelled' ORDER BY start_date DESC")->fetchAll();
}

function employees_for(PDO $pdo): array {
    return $pdo->query('SELECT id,employee_code,first_name,last_name,department,specialization FROM employees WHERE employment_status=\'Active\' ORDER '
    . 'BY last_name,first_name')->fetchAll();
}

