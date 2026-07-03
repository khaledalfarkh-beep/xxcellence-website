<?php
// Kontaktformular-Versand — XXCellence GmbH (Strato Webspace)
header('Content-Type: application/json; charset=utf-8');

$TO = 'kontakt@xxcellence-gmbh.de';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Nur POST erlaubt.', 405);

// Honeypot: echte Besucher aktivieren diese unsichtbare Checkbox nie
if (!empty($_POST['botcheck'] ?? '')) { echo json_encode(['success' => true]); exit; }

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '') fail('Bitte alle Felder ausfüllen.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Bitte eine gültige E-Mail-Adresse angeben.');
if (mb_strlen($message) > 5000) fail('Nachricht zu lang.');

// Header-Injection verhindern
$name  = str_replace(["\r", "\n"], ' ', $name);
$email = str_replace(["\r", "\n"], '', $email);

$subject = 'Anfrage über die Website von ' . $name;
$body    = "Neue Anfrage über das Kontaktformular xxcellence.eu:\n\n"
         . "Name:    $name\n"
         . "E-Mail:  $email\n\n"
         . "Nachricht:\n$message\n";

$headers = "From: XXCellence Website <noreply@xxcellence.eu>\r\n"
         . "Reply-To: $name <$email>\r\n"
         . "Content-Type: text/plain; charset=utf-8\r\n";

if (@mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers)) {
    echo json_encode(['success' => true]);
} else {
    fail('Versand fehlgeschlagen. Bitte schreiben Sie direkt an ' . $TO . '.', 500);
}
