<?php
$company = setting('company_name') ?: 'Votre structure';
$c = support_contact();
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fiche RGPD · <?= e(app_name()) ?></title>
<link rel="icon" href="assets/brand/approvia-mark.svg">
<style>
  @page { size: A4; margin: 16mm 15mm; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 10.5pt/1.5 Inter, "Segoe UI", Roboto, Arial, sans-serif; color: #1e2335; background: #eef0f6; }
  .bar { position: sticky; top: 0; background: #fff; border-bottom: 1px solid #dde1ea; padding: 10px 16px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; z-index: 2; }
  .bar a, .bar button { font: 600 14px system-ui; border: 1px solid #cfd4e0; background: #fff; color: #111; padding: 8px 14px; border-radius: 10px; cursor: pointer; text-decoration: none; }
  .bar .primary { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border: 0; }
  .bar form { display: flex; gap: 8px; flex-wrap: wrap; flex: 1; min-width: 260px; }
  .bar input { font: 14px system-ui; padding: 7px 9px; border: 1px solid #cfd4e0; border-radius: 9px; flex: 1; min-width: 160px; }
  .doc { max-width: 210mm; margin: 20px auto 40px; background: #fff; padding: 18mm 16mm; box-shadow: 0 6px 24px rgba(15, 23, 42, .12); }
  h1 { font-size: 19pt; margin: 0 0 2mm; } h2 { font-size: 12.5pt; margin: 7mm 0 2mm; color: #312e81; border-bottom: 1px solid #e0e3f0; padding-bottom: 1mm; }
  .sub { color: #5b6478; margin: 0 0 4mm; }
  .key { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 3mm 4mm; margin: 4mm 0; }
  .warn { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 3mm 4mm; margin: 3mm 0; }
  table { width: 100%; border-collapse: collapse; margin: 2mm 0; font-size: 9.5pt; }
  th, td { text-align: left; vertical-align: top; border-bottom: 1px solid #e5e7eb; padding: 1.6mm 2mm; }
  th { background: #f5f6fb; font-weight: 700; width: 30%; }
  ul { margin: 1mm 0 2mm; padding-left: 5mm; } li { margin: .6mm 0; }
  .foot { margin-top: 8mm; color: #6b7290; font-size: 8.5pt; }
  .ok { color: #047857; font-weight: 700; } .no { color: #b45309; font-weight: 700; }
  @media print { body { background: #fff; } .bar { display: none; } .doc { margin: 0; padding: 0; box-shadow: none; max-width: none; } h2 { break-after: avoid; } table, .key { break-inside: avoid; } }
  @media (max-width: 700px) { .doc { padding: 16px; margin: 0; } th { width: 38%; } }
</style></head>
<body>
<div class="bar">
  <a href="<?= url('admin/settings') ?>">← Retour</a>
  <form method="post"><?= csrf_field() ?>
    <input name="rgpd_controller" value="<?= e((string)setting('rgpd_controller', '')) ?>" placeholder="Responsable du traitement (ex : Directeur général)">
    <input name="rgpd_dpo" value="<?= e((string)setting('rgpd_dpo', '')) ?>" placeholder="Délégué à la protection des données (DPO)">
    <input name="rgpd_hosting" value="<?= e((string)setting('rgpd_hosting', '')) ?>" placeholder="Hébergeur et pays (ex : Hostinger, serveurs UE)">
    <a href="#" onclick="this.closest('form').submit();return false">Enregistrer</a>
  </form>
  <button class="primary" onclick="window.print()">🖨 Imprimer / PDF</button>
</div>
<article class="doc">
  <h1>Fiche RGPD et sécurité — <?= e(app_name()) ?></h1>
  <p class="sub"><?= e($company) ?> · logiciel d'achats et de gestion des stocks des centres · édité par <?= e($c['editor']) ?> · fiche générée le <?= date('d/m/Y') ?></p>

  <div class="key"><strong>En résumé :</strong> <?= e(app_name()) ?> gère des <strong>commandes de fournitures</strong> et des <strong>stocks</strong>. Il ne contient <strong>aucune donnée de santé ni aucune donnée patient</strong> : seules les coordonnées professionnelles des salariés qui l'utilisent sont traitées. L'hébergement agréé de données de santé (HDS) <strong>n'est donc pas requis</strong>.</div>

  <h2>1. Le traitement</h2>
  <table>
    <tr><th>Finalité</th><td>Demandes d'achat des salariés, validation, bons de commande aux fournisseurs, réceptions, factures, suivi des stocks et des budgets des centres.</td></tr>
    <tr><th>Responsable du traitement</th><td><?= e($company) ?><?= setting('rgpd_controller') ? ' — ' . e((string)setting('rgpd_controller')) : '' ?></td></tr>
    <tr><th>Délégué à la protection des données</th><td><?= e((string)setting('rgpd_dpo', '') ?: 'à compléter') ?></td></tr>
    <tr><th>Base légale</th><td>Intérêt légitime (organisation des achats) et exécution des contrats avec les fournisseurs.</td></tr>
    <tr><th>Personnes concernées</th><td>Salariés utilisateurs (<?= (int)$stats['users'] ?> comptes actifs, <?= (int)$stats['centers'] ?> centres) ; contacts professionnels des fournisseurs.</td></tr>
  </table>

  <h2>2. Données traitées</h2>
  <table>
    <tr><th>Utilisateurs</th><td>Nom, prénom, e-mail et téléphone professionnels, fonction, centres de rattachement, mot de passe (haché, jamais lisible), journal des connexions et des actions.</td></tr>
    <tr><th>Activité</th><td>Demandes d'articles, bons de commande, réceptions, factures fournisseurs, mouvements de stock, budgets.</td></tr>
    <tr><th>Fournisseurs</th><td>Raison sociale, coordonnées de commande, tarifs.</td></tr>
    <tr><th>Assistance</th><td>Messages échangés avec l'assistance <?= e($c['editor']) ?> et contexte technique (version, page) — sans donnée patient.</td></tr>
    <tr><th>Exclues</th><td><strong>Aucune donnée patient, aucune donnée de santé</strong>, aucune donnée bancaire.</td></tr>
  </table>
  <div class="warn">Consigne aux utilisateurs : ne jamais saisir de nom de patient ni d'information médicale dans les commentaires, motifs ou messages (les champs libres sont prévus pour des informations logistiques).</div>

  <h2>3. Durées de conservation</h2>
  <table>
    <tr><th>Comptes utilisateurs</th><td>Pendant la durée d'emploi ; à la suppression, le compte est anonymisé (nom, e-mail, téléphone effacés) si des commandes y sont liées, sinon effacé.</td></tr>
    <tr><th>Commandes, factures</th><td>Durée légale de conservation comptable (10 ans), à l'appréciation de la structure.</td></tr>
    <tr><th>Journaux techniques</th><td>Tentatives de connexion : 30 jours · e-mails envoyés : 30 jours · notifications lues : 12 mois · cache de l'assistant IA : 30 jours.</td></tr>
    <tr><th>Sauvegardes</th><td>Sauvegarde quotidienne de la base, conservée <?= (int)$backupDays ?> jours.</td></tr>
  </table>

  <h2>4. Hébergement et sous-traitants</h2>
  <table>
    <tr><th>Hébergement</th><td><?= e((string)setting('rgpd_hosting', '') ?: 'à compléter (hébergeur, pays des serveurs)') ?></td></tr>
    <tr><th>Éditeur — maintenance</th><td><?= e($c['editor']) ?> (<?= e($c['email']) ?>)<?= $nlapps ? ' : licence, mises à jour et assistance via son centre d\'assistance. Seules des statistiques d\'usage anonymes (nombre d\'utilisateurs, de centres, de commandes) et la version sont transmises ; les conversations d\'assistance sont initiées par l\'utilisateur.' : ' : non connecté à cette installation.' ?></td></tr>
    <tr><th>Assistant IA (option)</th><td><?= $ai ? 'Activé : Anthropic (Claude), via son API. Données envoyées : recherches d\'articles, catalogue, factures fournisseurs à lire. Aucune donnée patient. Selon les conditions commerciales d\'Anthropic, les données transmises par l\'API ne servent pas, par défaut, à entraîner ses modèles.' : 'Désactivé : aucune donnée n\'est transmise.' ?></td></tr>
    <tr><th>Envoi d'e-mails</th><td><?= $mail ? 'Activé : serveur d\'envoi configuré dans les paramètres (notifications, bons de commande aux fournisseurs).' : 'Désactivé.' ?></td></tr>
  </table>

  <h2>5. Mesures de sécurité</h2>
  <ul>
    <li>Connexion chiffrée (HTTPS) : <?= $https ? '<span class="ok">oui</span>' : '<span class="no">à activer sur l\'hébergement</span>' ?>.</li>
    <li>Mots de passe hachés (bcrypt), blocage après plusieurs échecs de connexion (15 minutes).</li>
    <li>Double authentification (code à 6 chiffres) : <?= (int)$admins2fa[0] ?> administrateur(s) sur <?= (int)$admins2fa[1] ?> l'ont activée<?= admin_2fa_required() ? ' — <span class="ok">obligatoire pour les administrateurs</span>' : ' — <span class="no">recommandé de la rendre obligatoire</span> (Paramètres)' ?>.</li>
    <li>Droits par rôle (salarié, responsable de centre, administrateur) et par centre ; validation des demandes par le responsable au-delà d'un seuil.</li>
    <li>Secrets (clé API, mots de passe d'envoi) chiffrés en base ; jetons anti-falsification sur tous les formulaires.</li>
    <li>Journal d'activité des actions sensibles (paramètres, suppressions, corrections de stock, mises à jour).</li>
    <li>Sauvegardes quotidiennes automatiques ; mise à jour avec sauvegarde préalable et retour arrière possible.</li>
  </ul>

  <h2>6. Droits des personnes</h2>
  <p>Les utilisateurs peuvent consulter et corriger leurs informations dans « Mon profil ». Les demandes d'accès, de rectification ou d'effacement sont adressées au responsable du traitement<?= setting('rgpd_dpo') ? ' ou au DPO (' . e((string)setting('rgpd_dpo')) . ')' : '' ?> ; un administrateur peut supprimer ou anonymiser un compte depuis « Utilisateurs ».</p>

  <p class="foot">Document d'information établi à partir de la configuration de l'installation au <?= date('d/m/Y') ?>. À intégrer au registre des traitements de <?= e($company) ?>. <?= e(app_name()) ?> — <?= e($c['editor']) ?> · <?= e($c['site'] ?? 'nlapps.fr') ?></p>
</article>
</body></html>
