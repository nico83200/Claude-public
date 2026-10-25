<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Services\AiPortrait;
use App\Services\Catalog;
use App\Services\DesignLibrary;
use App\Services\Personalizer;
use App\Services\Printful;
use App\Services\Upload;

final class ProductsController extends BaseController
{
    public function index(): void
    {
        $q = trim((string) input('q', ''));
        $params = [];
        $where = '1=1';
        if ($q !== '') {
            $where = 'p.name LIKE ?';
            $params[] = "%$q%";
        }
        $this->render('products/index', [
            'pageTitle' => 'Produits',
            'q'         => $q,
            'products'  => array_map([Catalog::class, 'hydrate'], Database::all(
                "SELECT p.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count,
                    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.printful_variant_id IS NULL AND v.printful_sync_variant_id IS NULL) AS unlinked
                 FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $where ORDER BY p.id DESC",
                $params
            )),
        ]);
    }

    public function create(): void
    {
        $this->form(['id' => null, 'name' => '', 'slug' => '', 'category_id' => null, 'short_description' => '', 'description' => '',
            'price' => 0, 'compare_at_price' => null, 'images' => '[]', 'featured' => 0, 'active' => 1, 'personalizable' => 0,
            'personalization' => null, 'design_file' => null, 'print_placement' => 'front', 'printful_sync_product_id' => null,
            'seo_title' => '', 'seo_description' => ''], []);
    }

    public function edit(string $id): void
    {
        $product = Database::one('SELECT * FROM products WHERE id = ?', [(int) $id]) ?? abort(404);
        $this->form($product, Catalog::variants((int) $id, false));
    }

    private function form(array $product, array $variants): void
    {
        $this->render('products/form', [
            'pageTitle'  => $product['id'] ? 'Modifier « ' . $product['name'] . ' »' : 'Nouveau produit',
            'product'    => $product,
            'variants'   => $variants,
            'categories' => Catalog::categories(false),
            'productCategories' => $product['id'] ? Catalog::productCategoryIds((int) $product['id']) : [],
            'perso'      => Personalizer::config($product['personalization']),
            'library'    => DesignLibrary::all(),
        ]);
    }

    public function store(): void
    {
        $id = $this->save(null);
        flash('success', 'Produit créé.');
        redirect('admin/produits/' . $id);
    }

    public function update(string $id): void
    {
        Database::one('SELECT id FROM products WHERE id = ?', [(int) $id]) ?? abort(404);
        $this->save((int) $id);
        if (!isset($_SESSION['_flash']['success'])) { // le message du recalcul des prix prime
            flash('success', 'Produit enregistré.');
        }
        redirect('admin/produits/' . $id);
    }

    private function save(?int $id): int
    {
        $existing = $id ? Database::one('SELECT * FROM products WHERE id = ?', [$id]) : null;
        $name = trim((string) input('name'));
        if ($name === '') {
            flash('error', 'Le nom du produit est obligatoire.');
            redirect($id ? 'admin/produits/' . $id : 'admin/produits/nouveau');
        }

        // Images : une par ligne (chemin local ou URL), + nouveaux fichiers téléversés
        $images = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) input('images', '')))));
        try {
            foreach (Upload::files('image_files') as $file) {
                $images[] = Upload::productImage($file);
            }
            $designFile = $existing['design_file'] ?? null;
            if (input('remove_design') === '1') {
                $designFile = null;
            }
            foreach (Upload::files('design_file') as $file) {
                $designFile = Upload::design($file);
            }
            // Visuel choisi dans la bibliothèque Crinière Club (prioritaire sur le téléversement)
            $library = null;
            if (preg_match('/^([a-z0-9-]+)\|(bordeaux|creme)$/', (string) input('library_design'), $m)) {
                $library = DesignLibrary::install($m[1], $m[2]);
                $designFile = $library['file'];
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect($id ? 'admin/produits/' . $id : 'admin/produits/nouveau');
        }

        $p = (array) ($_POST['perso'] ?? []);
        $personalization = [
            'mode'          => array_key_exists((string) ($p['mode'] ?? ''), Personalizer::MODES) ? $p['mode'] : 'text',
            'photo_shape'   => array_key_exists((string) ($p['photo_shape'] ?? ''), Personalizer::SHAPES) ? $p['photo_shape'] : 'arch',
            'photo_x'       => self::ratio($p['photo_x'] ?? 0.2),
            'photo_y'       => self::ratio($p['photo_y'] ?? 0.08),
            'photo_w'       => self::ratio($p['photo_w'] ?? 0.6),
            'photo_h'       => self::ratio($p['photo_h'] ?? 0.62),
            'preview_photo_x' => self::ratio($p['preview_photo_x'] ?? 0.3),
            'preview_photo_y' => self::ratio($p['preview_photo_y'] ?? 0.22),
            'preview_photo_w' => self::ratio($p['preview_photo_w'] ?? 0.4),
            'preview_photo_h' => self::ratio($p['preview_photo_h'] ?? 0.38),
            'extra_days'    => max(0, min(30, (int) ($p['extra_days'] ?? 0))),
            'preview_image' => mb_substr(trim((string) ($p['preview_image'] ?? '')), 0, 500),
            'label'         => mb_substr(trim((string) ($p['label'] ?? '')) ?: Personalizer::DEFAULTS['label'], 0, 80),
            'max_length'    => max(1, min(40, (int) ($p['max_length'] ?? 18))),
            'required'      => !empty($p['required']),
            'font'          => array_key_exists((string) ($p['font'] ?? ''), Personalizer::FONTS) ? $p['font'] : 'serif',
            'color'         => preg_match('/^#[0-9a-f]{6}$/i', (string) ($p['color'] ?? '')) ? $p['color'] : '#1f2a44',
            'uppercase'     => !empty($p['uppercase']),
            'x'             => self::ratio($p['x'] ?? 0.5),
            'y'             => self::ratio($p['y'] ?? 0.82),
            'size'          => self::ratio($p['size'] ?? 0.1),
            'max_width'     => self::ratio($p['max_width'] ?? 0.85),
            'preview_x'     => self::ratio($p['preview_x'] ?? 0.5),
            'preview_y'     => self::ratio($p['preview_y'] ?? 0.62),
            'preview_size'  => self::ratio($p['preview_size'] ?? 0.045),
            'preview_color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($p['preview_color'] ?? '')) ? $p['preview_color'] : '',
            'year_enabled'  => !empty($p['year_enabled']),
            'year_label'    => mb_substr(trim((string) ($p['year_label'] ?? '')) ?: Personalizer::DEFAULTS['year_label'], 0, 60),
            'print_w'       => max(1200, min(9000, (int) ($p['print_w'] ?? 3600))),
            'print_h'       => max(1600, min(12000, (int) ($p['print_h'] ?? 4800))),
        ];

        $personalizable = input('personalizable') ? 1 : 0;
        if (!empty($library['preset'])) {
            // Réglage de la zone du nom fourni avec le visuel ; les réglages d'aperçu restent ceux du formulaire.
            $personalization = array_replace($personalization, $library['preset']);
            $personalizable = 1;
        }

        $slug = slugify((string) (input('slug') ?: $name));
        if (Database::value('SELECT id FROM products WHERE slug = ? AND id <> ?', [$slug, (int) $id])) {
            $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }

        $data = [
            'name'              => mb_substr($name, 0, 190),
            'slug'              => $slug,
            'category_id'       => (int) input('category_id') ?: null,
            'short_description' => mb_substr((string) input('short_description'), 0, 300),
            'description'       => \App\Services\RichText::render((string) input('description')),
            'price'             => to_cents(input('price')),
            'compare_at_price'  => input('compare_at_price') !== '' ? to_cents(input('compare_at_price')) : null,
            'images'            => json_encode($images, JSON_UNESCAPED_SLASHES),
            'featured'          => input('featured') ? 1 : 0,
            'audience'          => in_array(input('audience'), ['elle', 'lui', 'tous'], true) ? (string) input('audience') : Catalog::guessAudience($name),
            'active'            => input('active') ? 1 : 0,
            'personalizable'    => $personalizable,
            'personalization'   => json_encode($personalization, JSON_UNESCAPED_UNICODE),
            'design_file'       => $designFile,
            'print_placement'   => preg_replace('/[^a-z0-9_]/', '', (string) input('print_placement', 'front')) ?: 'front',
            'seo_title'         => mb_substr((string) input('seo_title'), 0, 190),
            'seo_description'   => mb_substr((string) input('seo_description'), 0, 300),
            'updated_at'        => now(),
        ];

        if ($id) {
            Database::update('products', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = Database::insert('products', $data + ['created_at' => now()]);
        }
        $this->saveVariants($id, (array) ($_POST['variants'] ?? []));
        Catalog::setCategories($id, (int) input('category_id') ?: null, (array) ($_POST['extra_categories'] ?? []));
        // Prix calculés depuis le coût Printful : TVA 20 % puis marge 50 %, arrondi aux 10 centimes inférieurs
        if (input('apply_pricing') === '1') {
            $base = \App\Services\Pricing::applyToProduct($id);
            flash('success', $base !== null ? 'Prix recalculés (TVA 20 % + marge 50 %) : à partir de ' . money($base) . '.' : 'Aucune variante n\'a de coût Printful : renseignez la colonne « Coût € ».');
        }
        return $id;
    }

    private static function ratio(mixed $v): float
    {
        return max(0.0, min(1.0, (float) str_replace(',', '.', (string) $v)));
    }

    private function saveVariants(int $productId, array $rows): void
    {
        $position = 0;
        foreach ($rows as $row) {
            $variantId = (int) ($row['id'] ?? 0);
            if (!empty($row['delete'])) {
                if ($variantId) {
                    Database::query('DELETE FROM product_variants WHERE id = ? AND product_id = ?', [$variantId, $productId]);
                }
                continue;
            }
            $color = trim((string) ($row['color'] ?? ''));
            $size = trim((string) ($row['size'] ?? ''));
            $name = trim((string) ($row['name'] ?? '')) ?: trim(($color !== '' ? $color . ' / ' : '') . $size, ' /');
            if ($name === '') {
                continue;
            }
            $data = [
                'name'       => mb_substr($name, 0, 190),
                'color'      => $color ?: null,
                'color_hex'  => preg_match('/^#[0-9a-f]{6}$/i', (string) ($row['color_hex'] ?? '')) ? $row['color_hex'] : null,
                'size'       => $size ?: null,
                'sku'        => mb_substr(trim((string) ($row['sku'] ?? '')), 0, 100) ?: null,
                'price'      => trim((string) ($row['price'] ?? '')) !== '' ? to_cents($row['price']) : null,
                'cost'       => trim((string) ($row['cost'] ?? '')) !== '' ? to_cents($row['cost']) : null,
                'printful_variant_id'      => (int) ($row['printful_variant_id'] ?? 0) ?: null,
                'printful_sync_variant_id' => (int) ($row['printful_sync_variant_id'] ?? 0) ?: null,
                'image'      => trim((string) ($row['image'] ?? '')) ?: null,
                'active'     => !empty($row['active']) ? 1 : 0,
                'position'   => ++$position,
            ];
            if ($variantId) {
                Database::update('product_variants', $data, 'id = :id AND product_id = :pid', ['id' => $variantId, 'pid' => $productId]);
            } else {
                Database::insert('product_variants', $data + ['product_id' => $productId]);
            }
        }
    }

    public function destroy(string $id): void
    {
        $hasOrders = (int) Database::value('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [(int) $id]);
        if ($hasOrders) {
            Database::update('products', ['active' => 0, 'updated_at' => now()], 'id = :id', ['id' => (int) $id]);
            flash('success', 'Ce produit a déjà été commandé : il a été désactivé plutôt que supprimé.');
        } else {
            Database::query('DELETE FROM products WHERE id = ?', [(int) $id]);
            flash('success', 'Produit supprimé.');
        }
        redirect('admin/produits');
    }

    /** Aperçu du fichier d'impression personnalisé avec un texte d'exemple. */
    public function printPreview(string $id): void
    {
        $product = Database::one('SELECT * FROM products WHERE id = ?', [(int) $id]) ?? abort(404);
        $text = Personalizer::sanitize((string) input('texte', 'Tornado'), 40) ?: 'Tornado';
        $cfg = Personalizer::config($product['personalization']);
        if ($product['personalizable'] && $cfg['mode'] === 'ai_portrait') {
            // Affiche du portrait IA composée avec le dessin d'exemple
            $target = STORAGE_PATH . '/cache/apercu-portrait-ia.png';
            AiPortrait::compose(PUBLIC_PATH . '/assets/img/products/portrait-ia-exemple.jpg', $text, '2014', $cfg, $target);
            header('Content-Type: image/png');
            header('Cache-Control: no-store');
            readfile($target);
            exit;
        }
        if (!$product['design_file']) {
            abort(404, 'Aucun visuel d\'impression pour ce produit.');
        }
        $file = Personalizer::render($product['design_file'], $text, $cfg, $cfg['mode'] === 'photo' ? self::samplePhoto() : null);
        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        readfile(PUBLIC_PATH . '/' . $file);
        exit;
    }

    /** Téléchargement du fichier d'impression (pour le déposer dans Printful). */
    public function downloadDesign(string $id): void
    {
        $product = Database::one('SELECT name, design_file FROM products WHERE id = ?', [(int) $id]) ?? abort(404);
        $path = $product['design_file'] ? STORAGE_PATH . '/designs/' . basename($product['design_file']) : null;
        if (!$path || !is_file($path)) {
            abort(404);
        }
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . slugify($product['name']) . '-impression.png"');
        readfile($path);
        exit;
    }

    /** Photo d'exemple (dégradé et repère de cadrage) pour tester la zone photo. */
    private static function samplePhoto(): string
    {
        $path = STORAGE_PATH . '/cache/photo-exemple.jpg';
        if (!is_file($path)) {
            $img = imagecreatetruecolor(1600, 1200);
            for ($y = 0; $y < 1200; $y++) {
                $c = imagecolorallocate($img, 140 + (int) ($y / 12), 122 + (int) ($y / 14), 107 + (int) ($y / 16));
                imageline($img, 0, $y, 1599, $y, $c);
            }
            $light = imagecolorallocate($img, 249, 247, 243);
            imagesetthickness($img, 6);
            imageline($img, 800, 0, 800, 1199, $light);
            imageline($img, 0, 600, 1599, 600, $light);
            imageellipse($img, 800, 600, 700, 700, $light);
            imagejpeg($img, $path, 85);
        }
        return $path;
    }

    /** Recherche dans le catalogue Printful pour trouver les ID variantes (articles personnalisés). */
    public function printfulCatalog(): void
    {
        $search = mb_substr(trim((string) input('q', '')), 0, 80);
        $productId = (int) input('produit', 0);
        $products = [];
        $product = null;
        $error = null;
        try {
            if ($productId > 0) {
                $product = Printful::fromConfig()->catalogProduct($productId);
            } elseif ($search !== '') {
                $products = array_slice(Printful::fromConfig()->catalogProducts($search), 0, 60);
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $this->render('products/catalogue', [
            'pageTitle' => 'Catalogue Printful',
            'search'    => $search,
            'products'  => $products,
            'product'   => $product,
            'error'     => $error,
        ]);
    }

    public function printfulList(): void
    {
        $items = [];
        $error = null;
        $offset = max(0, (int) input('offset', 0));
        $total = 0;
        try {
            $res = Printful::fromConfig()->listSyncProducts($offset, 50);
            $items = $res['items'];
            $total = $res['total'];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $imported = array_column(Database::all('SELECT id, printful_sync_product_id FROM products WHERE printful_sync_product_id IS NOT NULL'), 'id', 'printful_sync_product_id');
        $this->render('products/printful', [
            'pageTitle'  => 'Importer depuis Printful',
            'items'      => $items,
            'imported'   => $imported,
            'error'      => $error,
            'offset'     => $offset,
            'total'      => $total,
            'categories' => Catalog::categories(false),
        ]);
    }

    /**
     * Importe (ou met à jour) un produit synchronisé Printful : variantes, prix de vente, visuels.
     */
    public function printfulImport(string $syncId): void
    {
        try {
            $pf = Printful::fromConfig();
            $data = $pf->getSyncProduct($syncId);
            $sp = $data['sync_product'];
            $syncVariants = array_values(array_filter($data['sync_variants'] ?? [], static fn ($v) => empty($v['is_ignored'])));

            // Couleurs hexadécimales depuis le catalogue Printful (facultatif)
            $hexByVariant = [];
            $catalogId = $syncVariants[0]['product']['product_id'] ?? null;
            if ($catalogId) {
                try {
                    foreach ($pf->request('GET', 'products/' . (int) $catalogId)['result']['variants'] ?? [] as $cv) {
                        $hexByVariant[(int) $cv['id']] = $cv['color_code'] ?? null;
                    }
                } catch (\Throwable) {
                }
            }

            $images = [];
            foreach ($syncVariants as $v) {
                foreach ($v['files'] ?? [] as $f) {
                    if (($f['type'] ?? '') === 'preview' && !empty($f['preview_url'])) {
                        $images[] = $f['preview_url'];
                    }
                }
            }
            if (!empty($sp['thumbnail_url'])) {
                $images[] = $sp['thumbnail_url'];
            }
            $images = array_values(array_unique($images));

            $prices = array_map(static fn ($v) => to_cents($v['retail_price'] ?? 0), $syncVariants) ?: [0];
            $basePrice = min($prices);
            $existing = Database::one('SELECT * FROM products WHERE printful_sync_product_id = ?', [(int) $sp['id']]);

            $productData = [
                'printful_sync_product_id' => (int) $sp['id'],
                'updated_at'               => now(),
            ];
            if ($existing) {
                $productId = (int) $existing['id'];
                if (!json_field($existing['images'])) {
                    $productData['images'] = json_encode(array_slice($images, 0, 8), JSON_UNESCAPED_SLASHES);
                }
                Database::update('products', $productData, 'id = :id', ['id' => $productId]);
            } else {
                $slug = slugify($sp['name']);
                if (Database::value('SELECT id FROM products WHERE slug = ?', [$slug])) {
                    $slug .= '-' . (int) $sp['id'];
                }
                $productId = Database::insert('products', $productData + [
                    'name'        => mb_substr($sp['name'], 0, 190),
                    'audience'    => Catalog::guessAudience((string) $sp['name']),
                    'slug'        => $slug,
                    'category_id' => (int) input('category_id') ?: null,
                    'price'       => $basePrice,
                    'images'      => json_encode(array_slice($images, 0, 8), JSON_UNESCAPED_SLASHES),
                    'active'      => 0, // à relire avant publication
                    'description' => '',
                    'created_at'  => now(),
                ]);
                Catalog::setCategories($productId, (int) input('category_id') ?: null, []);
            }

            $position = 0;
            foreach ($syncVariants as $v) {
                [$color, $size] = self::colorSize($v, $sp['name']);
                $variantImage = null;
                foreach ($v['files'] ?? [] as $f) {
                    if (($f['type'] ?? '') === 'preview' && !empty($f['preview_url'])) {
                        $variantImage = $f['preview_url'];
                    }
                }
                $price = to_cents($v['retail_price'] ?? 0);
                $row = [
                    'name'                     => trim(($color ? $color . ' / ' : '') . ($size ?? ''), ' /') ?: $v['name'],
                    'color'                    => $color,
                    'color_hex'                => $hexByVariant[(int) ($v['variant_id'] ?? 0)] ?? null,
                    'size'                     => $size,
                    'sku'                      => $v['sku'] ?? null,
                    'price'                    => $price !== $basePrice ? $price : null,
                    'printful_variant_id'      => (int) ($v['variant_id'] ?? 0) ?: null,
                    'printful_sync_variant_id' => (int) $v['id'],
                    'image'                    => $variantImage,
                    'position'                 => ++$position,
                    'active'                   => 1,
                ];
                $vid = Database::value('SELECT id FROM product_variants WHERE printful_sync_variant_id = ? AND product_id = ?', [(int) $v['id'], $productId]);
                $vid ? Database::update('product_variants', $row, 'id = :id', ['id' => $vid])
                     : Database::insert('product_variants', $row + ['product_id' => $productId]);
            }

            flash('success', ($existing ? 'Produit mis à jour' : 'Produit importé (désactivé : complétez la description puis publiez-le)') . ' — ' . count($syncVariants) . ' variante(s).');
            redirect('admin/produits/' . $productId);
        } catch (\Throwable $e) {
            flash('error', 'Import impossible : ' . $e->getMessage());
            redirect('admin/produits/printful');
        }
    }

    /** Extrait couleur et taille d'une variante synchronisée. */
    private static function colorSize(array $v, string $productName): array
    {
        $color = $v['color'] ?? null;
        $size = $v['size'] ?? null;
        if ($color === null && $size === null) {
            $suffix = trim(str_replace($productName, '', (string) $v['name']), " -/");
            $parts = array_map('trim', explode('/', $suffix));
            if (count($parts) >= 2) {
                [$color, $size] = [$parts[0], $parts[1]];
            } elseif ($parts[0] !== '') {
                $size = $parts[0];
            }
        }
        return [$color ?: null, $size ?: null];
    }
}
