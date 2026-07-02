<?php
// Kontaktformular-Versand — XXCellence GmbH
// Funktioniert auf jedem PHP-Hosting (z. B. IONOS Webspace).
header('Content-Type: application/json; charset=utf-8');

$TO = 'kontakt@xxcellence-gmbh.de';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Nur POST erlaubt.', 405);

// Honeypot: echte Besucher füllen dieses unsichtbare Feld nie aus
if (!empty($_POST['website'] ?? '')) { echo json_encode(['ok' => true]); exit; }

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
$body    = "Neue Anfrage über das Kontaktformular:\n\n"
         . "Name:    $name\n"
         . "E-Mail:  $email\n\n"
         . "Nachricht:\n$message\n";

$domain  = $_SERVER['SERVER_NAME'] ?? 'xxcellence-gmbh.de';
$headers = "From: Website <noreply@" . preg_replace('/^www\./', '', $domain) . ">\r\n"
         . "Reply-To: $name <$email>\r\n"
         . "Content-Type: text/plain; charset=utf-8\r\n";

if (@mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers)) {
    echo json_encode(['ok' => true]);
} else {
    fail('Versand fehlgeschlagen. Bitte später erneut versuchen oder direkt an ' . $TO . ' schreiben.', 500);
}
