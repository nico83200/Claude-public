<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Réglages éditables depuis l'administration (table `settings`).
 * Les secrets restent dans config/config.php.
 */
final class Settings
{
    public const DEFAULTS = [
        // Boutique
        'shop_name'        => 'Crinière & Co',
        'shop_tagline'     => 'L\'univers équestre imprimé à la demande',
        'announcement'     => 'Livraison offerte dès 69 € d\'achat · Chaque pièce est imprimée pour vous',
        'hero_title'       => 'Portez votre passion du cheval',
        'hero_subtitle'    => 'Sweats, t-shirts, accessoires et déco d\'écurie, imprimés à la demande et personnalisables au nom de votre cheval.',
        'home_meta_description' => 'Boutique en ligne de vêtements et accessoires d\'équitation imprimés à la demande : sweats, t-shirts, mugs, posters personnalisés au nom de votre cheval.',
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

        // Commandes
        'order_prefix'          => 'CC',
        'pending_order_ttl_hours' => '48',
        'packing_slip_message'  => 'Merci pour votre commande ! Votre article a été imprimé spécialement pour vous. À bientôt aux écuries 🐴',
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
