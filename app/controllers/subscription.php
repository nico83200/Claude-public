<?php
declare(strict_types=1);

/**
 * Abonnement de l'établissement (plateforme multi-clients, administrateur) : état de la licence, paiement en ligne
 * (carte ou prélèvement SEPA, via Stripe), espace de gestion Stripe, codes d'accès gratuit et bons de réduction.
 * Licence expirée : l'administrateur est conduit ici dès la connexion pour régler et retrouver l'accès.
 */
function admin_subscription(): void
{
    $u = require_superadmin();
    if (!licence_platform()) {
        redirect('admin/settings');
    }
    $slug = (string)current_instance();
    $who = trim($u['first_name'] . ' ' . $u['last_name']) . ' <' . $u['email'] . '>';
    if (is_post()) {
        try {
            switch ((string)input('action')) {
                case 'pay':
                    // Retour de Stripe : l'adresse de cette page dans l'espace
                    header('Location: ' . platform_billing_session_url($slug, app_base_url() . 'index.php?r=admin/subscription'), true, 303);
                    exit;
                case 'code':
                    $msg = platform_code_redeem($slug, (string)input('code'), $who);
                    audit('Code d\'abonnement utilisé', 'settings', null, $msg);
                    flash('success', $msg);
                    break;
            }
        } catch (Throwable $e) {
            error_log('[abonnement] ' . $e->getMessage());
            flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Paiement en ligne momentanément indisponible : réessayez dans quelques minutes.');
        }
        redirect('admin/subscription');
    }
    if (input('done') !== null) {
        flash('success', 'Merci ! Votre abonnement est enregistré : la licence est prolongée dès la confirmation du paiement (quelques secondes ; prélèvement SEPA : quelques jours).');
        redirect('admin/subscription');
    }
    $lines = platform_billing_lines($slug);
    $ht = array_sum(array_column($lines, 'amount'));
    render('admin/subscription', [
        'title' => 'Abonnement',
        'lic' => platform_licence($slug),
        'row' => platform_licence_row($slug),
        'lines' => $lines,
        'ht' => $ht,
        'discount' => platform_discount($slug),
        'discountHt' => platform_discount_amount($slug, $ht),
        'vat' => platform_vat(),
        'online' => platform_stripe_ready(),
        'auto' => platform_billing_active($slug),
        'events' => array_slice(platform_billing_events($slug), 0, 24),
    ]);
}
