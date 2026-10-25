<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Réglages éditables depuis l'administration (table `settings`).
 * Les clés des intégrations sont stockées dans la même table (préfixe « config. ») : voir App\Core\Secrets.
 */
final class Settings
{
    public const DEFAULTS = [
        // Boutique
        'shop_name'        => 'Crinière Club',
        'shop_tagline'     => 'Le club des passionnées',
        'announcement'     => 'Nouveau : son portrait aquarelle en quelques secondes ✦|Livraison offerte dès 69 € d\'achat ♡|Personnalisation offerte : son nom, sa photo, son portrait|Chaque pièce est imprimée rien que pour vous',
        'hero_eyebrow'     => 'Bienvenue au club',
        'hero_title'       => 'Pour celles qui vivent *au rythme du galop*',
        'hero_subtitle'    => 'Vêtements, accessoires et cadeaux pour cavalières, à porter fièrement ou à personnaliser avec le nom, la photo ou le portrait de votre cheval.',
        'usp_marquee'      => 'Le club des passionnées|Son nom, sa photo, son portrait|Imprimé à la demande en Europe|Livraison offerte dès 69 €|Paiement 100 % sécurisé',
        'home_meta_description' => 'Crinière Club, le club des passionnées : vêtements et accessoires d\'équitation pour cavalières, cadeaux personnalisés avec le nom, la photo ou le portrait de votre cheval.',
        'gift_max_price'   => '3000',
        'contact_email'    => '',
        'contact_phone'    => '',
        'company_name'     => '[RAISON SOCIALE]',
        'company_address'  => '[ADRESSE DU SIÈGE]',
        'company_siret'    => '[SIRET]',
        'company_vat'      => '[N° TVA INTRACOMMUNAUTAIRE]',
        'publisher_name'   => '[DIRECTEUR DE LA PUBLICATION]',
        'mediator'         => '[NOM ET COORDONNÉES DU MÉDIATEUR DE LA CONSOMMATION]',

        // Réseaux sociaux
        'social_instagram' => '',
        'social_facebook'  => '',
        'social_tiktok'    => '',
        'social_pinterest' => '',

        // Livraison (montants en centimes, TTC)
        'shipping_standard_label' => 'Livraison standard suivie',
        'shipping_standard_price' => '490',
        'shipping_standard_min'   => '4',
        'shipping_standard_max'   => '8',
        'shipping_free_threshold' => '6900',
        'shipping_express_label'  => 'Livraison express',
        'shipping_express_price'  => '1290',
        'shipping_express_min'    => '2',
        'shipping_express_max'    => '4',

        // Délais de fabrication (jours ouvrés) pour la date de livraison estimée
        'production_min'        => '2',
        'production_max'        => '5',

        // Popup de bienvenue (code promo à créer dans Stripe)
        'popup_enabled'    => '1',
        'popup_delay'      => '15',
        'ai_prompt'        => '',      // vide = prompt d'origine du style « Encre & Poudre »
        'cart_recovery_enabled' => '1', // e-mail « votre panier vous attend » (avec consentement)
        'welcome_amount'   => '500',   // cadeau de bienvenue en centimes : code personnel à usage unique créé dans Stripe
        'welcome_code'     => '',
        'welcome_discount' => '5 € offerts',
        'popup_title'      => 'Rejoignez le club ♡',

        // Avis clients
        'reviews_enabled'      => '1',
        'review_request_days'  => '10',
        'reviews_auto_publish' => '0',

        // Paiement en plusieurs fois (à activer d'abord dans Stripe : Klarna)
        'show_installments' => '0',

        // Commandes
        'order_prefix'          => 'CC',
        'pending_order_ttl_hours' => '48',
        'packing_slip_message'  => 'Merci d\'avoir rejoint le club ! Votre article a été imprimé rien que pour vous. À très vite aux écuries ♡ Crinière Club',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            try {
                foreach (Database::all('SELECT name, value FROM settings') as $row) {
                    self::$cache[$row['name']] = $row['value'];
                }
            } catch (\Throwable) {
                // Base pas encore installée : valeurs par défaut.
            }
        }
        return self::$cache;
    }

    /** Oublie les valeurs en mémoire (après une modification directe en base). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        if (Database::value('SELECT COUNT(*) FROM settings WHERE name = ?', [$key])) {
            Database::update('settings', ['value' => $value], 'name = :name', ['name' => $key]);
        } else {
            Database::insert('settings', ['name' => $key, 'value' => $value]);
        }
        self::$cache = null;
    }
}
