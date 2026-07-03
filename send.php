<?php
// Kontaktformular-Versand — XXCellence GmbH
// Versand über Microsoft Graph (M365-Tenant), Zugangsdaten in graph-config.php
// (liegt nur auf dem Webspace, nicht im Git-Repository).
header('Content-Type: application/json; charset=utf-8');

$cfg = require __DIR__ . '/graph-config.php';
$TO      = 'kontakt@xxcellence-gmbh.de';
$MAILBOX = 'kontakt@xxcellence-gmbh.de'; // Absender-Postfach im Tenant

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

function graph_post($url, $fields, $headers, $isJson) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $isJson ? json_encode($fields) : http_build_query($fields),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, $body];
}

[$code, $body] = graph_post(
    "https://login.microsoftonline.com/{$cfg['tenant']}/oauth2/v2.0/token",
    ['client_id' => $cfg['client_id'], 'client_secret' => $cfg['client_secret'],
     'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials'],
    ['Content-Type: application/x-www-form-urlencoded'], false
);
$token = json_decode($body, true)['access_token'] ?? null;
if ($code !== 200 || !$token) fail('Versand fehlgeschlagen. Bitte schreiben Sie direkt an ' . $TO . '.', 502);

$mail = [
    'message' => [
        'subject' => 'Anfrage über die Website von ' . $name,
        'body' => [
            'contentType' => 'Text',
            'content' => "Neue Anfrage über das Kontaktformular xxcellence.eu:\n\n"
                       . "Name:    $name\nE-Mail:  $email\n\nNachricht:\n$message\n",
        ],
        'toRecipients' => [['emailAddress' => ['address' => $TO]]],
        'replyTo' => [['emailAddress' => ['address' => $email, 'name' => $name]]],
    ],
    'saveToSentItems' => false,
];

[$code, ] = graph_post(
    'https://graph.microsoft.com/v1.0/users/' . rawurlencode($MAILBOX) . '/sendMail',
    $mail, ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], true
);

if ($code === 202) echo json_encode(['success' => true]);
else fail('Versand fehlgeschlagen. Bitte schreiben Sie direkt an ' . $TO . '.', 502);
