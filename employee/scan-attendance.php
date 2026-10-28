<?php

declare(strict_types=1);
$requiredRole = 'employee';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_scan_attendance(PDO $pdo, array $user): string
{
    $employeeId = (int)($user['employee_id'] ?? 0);
    $programStmt = $pdo->prepare(
        'SELECT p.id, p.title, p.start_date, p.start_time, p.venue, r.status AS registration_status
         FROM registrations r
         JOIN training_programs p ON p.id = r.program_id
         WHERE r.employee_id = ? AND r.status <> "Withdrawn"
         ORDER BY p.start_date DESC, p.start_time DESC
         LIMIT 5'
    );
    $programStmt->execute([$employeeId]);
    $programs = $programStmt->fetchAll(PDO::FETCH_ASSOC);

    $programOptions = '';
    foreach ($programs as $program) {
        $id = (int)($program['id'] ?? 0);
        $programOptions .= '<option value="' . h((string)$id) . '">' . h((string)($program['title'] ?? '')) . ' · ' . h((string)($program['start_date'] ?? '')) . '</option>';
    }

    $rows = '';
    foreach ($programs as $program) {
        $rows .= '<li class="scan-item">'
            . '<strong>' . h((string)($program['title'] ?? '')) . '</strong>'
            . '<span>' . h((string)($program['start_date'] ?? '')) . ' · ' . h((string)($program['start_time'] ?? '')) . '</span>'
            . '</li>';
    }

    return page_intro('', '')
        . '<section class="card qr-scan-card">'
        . '<div class="qr-scan-header">'
        . '<div><span class="eyebrow">QR attendance</span><h3>Scan a training QR code</h3></div>'
        . '</div>'
        . '<form method="post" class="qr-scan-form" id="qr-scan-form">'
        . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="record_qr_attendance">'
        . '<input type="hidden" name="employee_id" value="' . h((string)$employeeId) . '">'
        . '<label class="field-label" for="program_id">Training session</label>'
        . '<select id="program_id" name="program_id" class="field-input">'
        . '<option value="">Select a training session</option>'
        . $programOptions
        . '</select>'
        . '<div class="qr-scan-camera-wrap">'
        . '<video id="qr-video" autoplay playsinline muted></video>'
        . '<canvas id="qr-canvas" hidden></canvas>'
        . '</div>'
        . '<div class="qr-scan-actions">'
        . '<button type="button" class="btn" id="start-camera-btn">Scan QR Code</button>'
        . '<button type="submit" class="btn btn-secondary" id="manual-submit-btn">Submit scanned token</button>'
        . '</div>'
        . '<label class="field-label" for="qr_token">Or paste the QR token</label>'
        . '<input id="qr_token" name="qr_token" type="text" class="field-input" placeholder="Paste the token shown on the trainer screen" autocomplete="off">'
        . '</form>'
        . '<div class="qr-scan-help">'
        . '<h4>Instructions</h4>'
        . '<ol><li>Use the trainer-provided QR code for the session.</li><li>Scan the TIME-IN QR when you arrive.</li><li>Scan the TIME-OUT QR when you finish the session.</li></ol>'
        . '</div>'
        . '<div class="qr-scan-list">'
        . '<h4>Recent training sessions</h4>'
        . '<ul>' . $rows . '</ul>'
        . '</div>'
        . '</section>'
        . '<script>'
        . '(function () {'
        . 'var video = document.getElementById("qr-video");'
        . 'var startBtn = document.getElementById("start-camera-btn");'
        . 'var tokenInput = document.getElementById("qr_token");'
        . 'var programSelect = document.getElementById("program_id");'
        . 'var form = document.getElementById("qr-scan-form");'
        . 'if (!startBtn || !video) return;'
        . 'var stream = null;'
        . 'var stopCamera = function () {'
        . 'if (stream) { stream.getTracks().forEach(function (track) { track.stop(); }); stream = null; }'
        . '};'
        . 'var syncProgramFromQr = function (code) {'
        . 'if (!code || !programSelect) return;'
        . 'var sections = (String(code)).split(":");'
        . 'if (sections.length !== 3) return;'
        . 'var programId = parseInt(sections[1], 10);'
        . 'if (!Number.isFinite(programId) || programId <= 0) return;'
        . 'var option = Array.prototype.slice.call(programSelect.options).find(function (item) { return item.value === String(programId); });'
        . 'if (option) { programSelect.value = String(programId); }'
        . '};'
        . 'tokenInput.addEventListener("change", function () { syncProgramFromQr(tokenInput.value); });'
        . 'startBtn.addEventListener("click", function () {'
        . 'if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {'
        . 'tokenInput.focus();'
        . 'return;'
        . '}'
        . 'navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } }).then(function (mediaStream) {'
        . 'stream = mediaStream;'
        . 'video.srcObject = mediaStream;'
        . 'video.play();'
        . 'var detector = window.BarcodeDetector || null;'
        . 'if (!detector) {'
        . 'tokenInput.placeholder = "Camera not supported in this browser. Paste the token manually instead.";'
        . 'return;'
        . '}'
        . 'var interval = setInterval(function () {'
        . 'if (!video.videoWidth) return;'
        . 'if (tokenInput.value) { clearInterval(interval); stopCamera(); return; }'
        . 'detector.detect(video).then(function (results) {'
        . 'if (!results || !results.length) return;'
        . 'var code = results[0] && results[0].rawValue ? results[0].rawValue : "";'
        . 'if (!code) return;'
        . 'tokenInput.value = code;'
        . 'syncProgramFromQr(code);'
        . 'clearInterval(interval); stopCamera();'
        . 'form.submit();'
        . '}).catch(function () {});'
        . '}, 800);'
        . '}).catch(function () {'
        . 'tokenInput.focus();'
        . '});'
        . '});'
        . 'window.addEventListener("beforeunload", stopCamera);'
        . '})();'
        . '</script>'
        ;
}

$page = 'Scan QR Attendance';
$content = render_scan_attendance($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
