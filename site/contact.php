<?php
/**
 * Formulaire de contact de jackcie.fr : envoie le message à contact@jackcie.fr (fonction mail() de l'hébergement).
 * Protections : méthode POST uniquement, champ piège, délai minimal de saisie, 5 envois par heure et par adresse IP,
 * en-têtes nettoyés (pas d'injection), longueurs limitées. Aucune donnée n'est conservée sur le serveur.
 */
declare(strict_types=1);

const TO = 'contact@jackcie.fr';
const FROM = 'Site Jack&Cie <contact@jackcie.fr>';

function back(string $query): never
{
    header('Location: /contact?' . $query . '#formulaire', true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /contact', true, 303);
    exit;
}

$field = static fn (string $k, int $max): string => trim(mb_substr((string) ($_POST[$k] ?? ''), 0, $max));
$line = static fn (string $v): string => trim(preg_replace('/[\r\n\t]+/', ' ', $v) ?? '');

// Robots : champ piège rempli ou formulaire envoyé en moins de 3 secondes → on fait comme si tout allait bien.
$ts = (int) ($_POST['ts'] ?? 0);
if ($field('website', 200) !== '' || ($ts > 0 && time() - $ts < 3)) {
    back('envoye=1');
}

$name = $line($field('name', 120));
$email = $line($field('email', 190));
$phone = $line($field('phone', 30));
$profile = $line($field('profile', 80));
$subjects = ['question' => 'Question', 'ecurie' => 'Démonstration écurie', 'compte' => 'Compte / abonnement', 'partenariat' => 'Partenariat', 'presse' => 'Presse', 'autre' => 'Autre'];
$subject = $subjects[$_POST['subject'] ?? ''] ?? 'Question';
$message = $field('message', 5000);

if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 10) {
    back('erreur=champs');
}

// 5 messages par heure et par adresse IP (fichier temporaire, condensé de l'adresse).
$ip = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . __FILE__);
$file = sys_get_temp_dir() . '/jackcie-contact-' . $ip;
$hits = array_filter(is_file($file) ? array_map('intval', file($file, FILE_IGNORE_NEW_LINES) ?: []) : [], static fn (int $t): bool => $t > time() - 3600);
if (count($hits) >= 5) {
    back('erreur=envoi');
}
$hits[] = time();
@file_put_contents($file, implode("\n", $hits));

$body = "Message envoyé depuis le formulaire de jackcie.fr\n\n"
    . "Nom : $name\nE-mail : $email\n" . ($phone !== '' ? "Téléphone : $phone\n" : '') . "Profil : $profile\nSujet : $subject\n\n"
    . str_replace("\r\n", "\n", $message) . "\n\n---\nRépondez directement à cet e-mail pour écrire à $name.\n";

$headers = [
    'From' => FROM,
    'Reply-To' => mb_encode_mimeheader(trim(preg_replace('/[<>"\\(),:;@\[\]]+/', ' ', $name) ?? '') ?: 'Contact', 'UTF-8') . " <$email>",
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => '8bit',
    'X-Mailer' => 'jackcie.fr',
];
$ok = mail(TO, mb_encode_mimeheader("[jackcie.fr] $subject — $name", 'UTF-8'), $body, $headers, '-f contact@jackcie.fr');

back($ok ? 'envoye=1' : 'erreur=envoi');
