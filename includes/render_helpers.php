<?php

declare(strict_types=1);

/**
 * Render a reusable HTML table.
 *
 * Cell values are treated as already-prepared HTML so callers can pass badges
 * or links. Escape any untrusted cell content with h() before passing it here.
 */
function render_table(
    array $headers,
    array $rows,
    string $empty = 'No records found.'
): string {
    $columnCount = count($headers);

    $html = '<div class="table-wrap">'
        . '<table class="data-table">'
        . '<thead><tr>';

    foreach ($headers as $header) {
        $html .= '<th scope="col">' . h((string)$header) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    if ($columnCount === 0) {
        return $html . '</tbody></table></div>';
    }

    if ($rows === []) {
        $html .= '<tr class="table-empty-row"><td colspan="'
            . $columnCount
            . '"><span>' . h($empty) . '</span></td></tr>';
    } else {
        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach (array_slice(array_values($row), 0, $columnCount) as $cell) {
                $html .= '<td>' . (string)$cell . '</td>';
            }

            // Keep every row aligned with the header count if a cell is missing.
            $missingCells = $columnCount - count($row);
            for ($i = 0; $i < $missingCells; $i++) {
                $html .= '<td></td>';
            }

            $html .= '</tr>';
        }
    }

    return $html . '</tbody></table></div>';
}

function page_intro(string $title, string $description, string $action = ''): string
{
    return '<div class="page-intro">'
        . '<div class="page-intro-copy">'
        . '<span class="eyebrow">Training &amp; Development</span>'
        . '<h2>' . h($title) . '</h2>'
        . '<p>' . h($description) . '</p>'
        . '</div>'
        . ($action !== '' ? '<div class="page-intro-actions">' . $action . '</div>' : '')
        . '</div>';
}

function card_open(string $title, string $sub = ''): string
{
    return '<section class="card">' . section_head($title, $sub);
}

function program_select(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, title, start_date, status
         FROM training_programs
         WHERE status <> 'Cancelled'
         ORDER BY start_date DESC"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function employees_for(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, employee_code, first_name, last_name, department, specialization
         FROM employees
         WHERE employment_status = 'Active'
         ORDER BY last_name, first_name"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
