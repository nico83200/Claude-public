<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Illustration manga IA (t-shirt) : la cavalière et son cheval, d'après deux photos, de face ou de dos.
 * Le dessin passe par un modèle qui accepte plusieurs images de référence (Replicate google/nano-banana) ;
 * une illustration de référence facultative fixe le style de la collection.
 */
final class MangaIllustration
{
    public const STYLE = 'manga';
    public const DEFAULT_MODEL = 'google/nano-banana';

    public const VIEWS = [
        'face' => 'De face',
        'dos'  => 'De dos',
    ];

    public const BASE_PROMPT = <<<'TXT'
CRÉATION D'UNE ILLUSTRATION MANGA ÉQUESTRE PERSONNALISÉE

Rôle et objectif

Tu es un illustrateur professionnel spécialisé dans les illustrations équestres de style manga japonais, avec un rendu semi-réaliste, chaleureux, détaillé et cinématographique.

Ta mission est de créer une illustration personnalisée représentant un cheval et son cavalier ou sa cavalière, à partir des photographies fournies par l'utilisateur.

Le résultat doit être une véritable illustration artistique, et non une photographie simplement filtrée. Le style graphique doit être élégant, cohérent et suffisamment réaliste pour que les deux sujets restent immédiatement reconnaissables.

1. Photos de référence

L'utilisateur fournit les photographies suivantes :

- Photo 1 — Cavalier ou cavalière : référence pour l'apparence physique, la silhouette, la morphologie, la coiffure, la couleur des cheveux, les vêtements et les caractéristiques distinctives visibles.
- Photo 2 — Cheval : référence pour la robe, la morphologie, la taille, la tête, les oreilles, la crinière, la queue, les balzanes, les marques blanches et les particularités physiques.

Des photographies supplémentaires peuvent être fournies pour montrer le visage du cavalier ou de la cavalière, la tête du cheval, son profil ou d'autres détails importants.

Toutes les photographies doivent être utilisées comme références visuelles prioritaires. Ne remplace pas les sujets par des personnages génériques.

Si plusieurs photos montrent le même sujet sous différents angles, considère-les comme des références complémentaires d'un seul et même sujet.

Ne mélange pas les caractéristiques de plusieurs photos si elles correspondent à des sujets différents.

2. Fidélité et personnalisation

Le cheval

Reproduis aussi fidèlement que possible :

- La couleur exacte et les nuances de sa robe.
- Sa morphologie et ses proportions.
- La forme et la couleur de sa crinière et de sa queue.
- Les marques blanches sur la tête et les membres.
- Les balzanes, les épis et les autres particularités visibles.
- La forme de sa tête, ses oreilles et son encolure.
- Son équipement, si celui-ci est visible sur les photos de référence.

Ne modifie pas arbitrairement sa robe, ses marques distinctives ou sa morphologie.

Le cavalier ou la cavalière

Reproduis aussi fidèlement que possible :

- La silhouette et les proportions corporelles.
- La coiffure et la couleur des cheveux.
- Les vêtements visibles et leur style.
- Les chaussures et les accessoires reconnaissables.
- Les traits du visage lorsque l'angle de vue permet de les voir.

Si certaines caractéristiques ne sont pas visibles sur les photos, adopte une interprétation sobre et cohérente. N'invente pas de détails personnels distinctifs.

3. Direction artistique

Crée une illustration de style manga japonais semi-réaliste, avec une finition soignée et une ambiance cinématographique.

Le rendu doit combiner :

- Des contours nets, précis et élégants.
- Un dessin anatomique détaillé.
- Des ombrages peints et des dégradés subtils.
- Des textures travaillées pour le pelage, les crins, les vêtements et les équipements.
- Une lumière naturelle, douce et chaleureuse.
- Des couleurs harmonieuses et une profondeur visuelle réaliste.

Évite le style chibi, les proportions caricaturales, les traits trop enfantins et les effets 3D artificiels.

L'illustration doit conserver une apparence dessinée, avec une identité manga immédiatement reconnaissable.

4. Décor et ambiance

Sauf indication contraire dans la partie variable, représente les sujets dans un environnement champêtre paisible.

Le décor comprend :

- Un chemin naturel en terre ou un sol de campagne.
- Une clôture en bois.
- De la végétation et des arbres.
- Des collines ou un paysage naturel à l'arrière-plan.
- Un ciel bleu doux avec quelques nuages.
- Une lumière dorée de fin d'après-midi.

Utilise une palette chaleureuse composée de bruns, de caramel, de beige, de verts naturels et de bleu ciel.

Les ombres doivent être cohérentes avec la direction de la lumière. Le décor doit créer de la profondeur sans détourner l'attention du cheval et de son humain.

L'ambiance générale doit évoquer la complicité, la confiance, la sérénité et le lien entre l'humain et son cheval.

5. Relation entre les deux sujets

Le cavalier ou la cavalière accompagne le cheval à pied et le tient en longe.

La longe doit être clairement visible, attachée de façon cohérente à un licol ou à l'équipement adapté. Sa forme, sa tension et sa position doivent être naturelles.

Les gestes, la posture et la distance entre les deux sujets doivent être crédibles.

Le cheval doit conserver une anatomie équine réaliste : quatre membres correctement dessinés, des articulations cohérentes, des sabots bien formés et une posture naturelle.

Le cavalier ou la cavalière doit présenter une anatomie humaine crédible, avec des membres correctement proportionnés.

Les deux sujets doivent sembler évoluer dans la même scène, avec une perspective et une échelle cohérentes.

6. Qualité de l'image

Produis une illustration de haute qualité, détaillée et visuellement équilibrée.

Privilégie :

- Une composition claire et immédiatement lisible.
- Une silhouette reconnaissable de chaque sujet.
- Une bonne visibilité du cheval et de son humain.
- Une perspective naturelle.
- Des détails nets sur les éléments importants.
- Un arrière-plan suffisamment détaillé, mais légèrement moins contrasté que les sujets principaux.

Sauf indication contraire dans la partie variable, utilise un format vertical, avec les deux sujets visibles en pied, du haut de la tête jusqu'aux pieds ou aux sabots.

Ne coupe pas accidentellement les oreilles, les membres, les pieds, les sabots ou la queue.

7. Cohérence artistique

Toutes les illustrations générées avec ce prompt doivent appartenir à la même collection visuelle.

Conserve le même style manga semi-réaliste, le même niveau de finition, la même palette chaleureuse et le même traitement de la lumière, même lorsque l'angle de vue ou la composition change.

Les caractéristiques physiques du cheval et du cavalier ou de la cavalière doivent rester fidèles aux photographies fournies.

8. Consignes finales

Génère directement l'illustration finale correspondant à la partie variable fournie à la suite de ce prompt.

N'ajoute aucun texte, aucune signature, aucun logo ni aucun filigrane.

Ne crée pas de planche de croquis, de collage de plusieurs angles ou de présentation avant/après.

Produis une seule illustration finale, sauf demande explicite de plusieurs images.
TXT;

    public const VIEW_PROMPTS = [
        'dos'  => "ANGLE DE VUE : DE DOS\nFORMAT : Portrait\nConserve le même style manga semi-réaliste, la fidélité aux deux photos et l'ambiance champêtre de l'illustration de référence.",
        'face' => "ANGLE DE VUE : DE FACE\nFORMAT : Portrait\nConserve le même style manga semi-réaliste, la fidélité aux deux photos et l'ambiance champêtre de l'illustration de référence.",
    ];

    /** Précision ajoutée quand une illustration de référence est jointe (image 3). */
    public const REFERENCE_NOTE = "Image 3 — Illustration de référence : elle sert uniquement de référence de style (trait, palette, lumière, décor). Ne reprends ni son cheval ni son personnage : les sujets viennent exclusivement des photos 1 et 2.";

    public static function basePrompt(): string
    {
        $custom = trim((string) setting('ai_manga_prompt', ''));
        return $custom !== '' ? $custom : self::BASE_PROMPT;
    }

    public static function viewPrompt(string $view): string
    {
        $view = isset(self::VIEWS[$view]) ? $view : 'face';
        $custom = trim((string) setting('ai_manga_' . $view, ''));
        return $custom !== '' ? $custom : self::VIEW_PROMPTS[$view];
    }

    /** Consigne complète : base, partie variable (angle de vue), puis précision sur l'illustration de référence. */
    public static function prompt(string $view, bool $withReference): string
    {
        return self::basePrompt() . "\n\n" . self::viewPrompt($view) . ($withReference ? "\n\n" . self::REFERENCE_NOTE : '');
    }

    public static function model(): string
    {
        $model = trim((string) setting('ai_manga_model', ''));
        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    /** Illustration de référence (style de la collection), stockée hors du web. */
    public static function referencePath(): ?string
    {
        foreach ([AiPortrait::DIR . '/reference-manga.jpg', PUBLIC_PATH . '/' . self::BUNDLED_EXAMPLE] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /** Illustration d'exemple fournie avec le site (de dos) : référence de style et visuel par défaut. */
    public const BUNDLED_EXAMPLE = 'assets/img/manga/exemple-dos.jpg';

    public static function view(?string $view): string
    {
        return isset(self::VIEWS[$view]) ? (string) $view : 'face';
    }

    /** Exemples générés depuis l'administration (« face » et « dos »), affichés dans la galerie du t-shirt. */
    public static function examples(): array
    {
        $map = json_decode((string) setting('ai_manga_examples', ''), true) ?: [];
        $map = array_filter($map, static fn ($p) => is_string($p) && is_file(PUBLIC_PATH . '/' . strtok($p, '?')));
        return $map + ['dos' => self::BUNDLED_EXAMPLE]; // la vue de dos fournie tant qu'aucune n'est générée
    }

    public static function setExample(string $view, string $relative): void
    {
        $map = json_decode((string) setting('ai_manga_examples', ''), true) ?: [];
        $map[self::view($view)] = $relative . '?v=' . time();
        \App\Core\Settings::set('ai_manga_examples', json_encode($map, JSON_UNESCAPED_SLASHES));
    }

    public const PRODUCT_SLUG = 't-shirt-manga-toi-et-ton-cheval';

    /**
     * Crée le t-shirt « illustration manga » à partir d'un t-shirt existant (tailles, couleurs et variantes
     * Printful catalogue reprises). Hors ligne par défaut : à vérifier puis publier depuis l'administration.
     * @return int|null id du produit créé (null si déjà présent ou sans t-shirt de départ)
     */
    public static function createProduct(bool $active = false): ?int
    {
        if (\App\Core\Database::value('SELECT id FROM products WHERE slug = ?', [self::PRODUCT_SLUG])) {
            return null;
        }
        $source = \App\Core\Database::one(
            "SELECT p.* FROM products p WHERE (LOWER(p.name) LIKE '%t-shirt%' OR LOWER(p.name) LIKE '%t shirt%' OR LOWER(p.name) LIKE '%tee%')
               AND EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1)
             ORDER BY EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.printful_variant_id IS NOT NULL) DESC,
                      (p.audience = 'elle') DESC, (LOWER(p.name) LIKE '%femme%') DESC, p.id LIMIT 1"
        );
        if (!$source) {
            return null;
        }
        $category = \App\Core\Database::value("SELECT id FROM categories WHERE slug IN ('tenues-de-cavaliere', 'mon-cheval-ma-fierte') ORDER BY slug = 'tenues-de-cavaliere' DESC LIMIT 1") ?: $source['category_id'];
        $id = \App\Core\Database::insert('products', [
            'category_id'       => $category,
            'name'              => 'T-shirt manga « Toi et ton cheval »',
            'slug'              => self::PRODUCT_SLUG,
            'short_description' => 'Toi et ton cheval dessinés en style manga, d\'après vos photos, de face ou de dos.',
            'description'       => '<p>Envoie une photo de toi et une de ton cheval : notre IA vous dessine tous les deux en <strong>illustration manga semi-réaliste</strong>, dans un décor champêtre baigné de lumière dorée, toi menant ton cheval en longe. Sa robe, ses marques, ta silhouette et ta coiffure sont respectées pour que vous soyez reconnaissables au premier coup d\'œil.</p><ul><li>Vue de face ou de dos, aperçu offert avant de commander</li><li>Illustration imprimée en haute définition sur le devant</li><li>Fabriqué à la commande, rien que pour vous deux</li></ul>',
            'price'             => max(3490, (int) $source['price']),
            'images'            => json_encode([self::BUNDLED_EXAMPLE]),
            'featured'          => 0,
            'active'            => $active ? 1 : 0,
            'personalizable'    => 1,
            'audience'          => 'elle',
            'personalization'   => json_encode([
                'mode' => 'ai_manga', 'label' => 'Prénom', 'max_length' => 16, 'required' => false,
                'photo_shape' => 'rect', 'photo_x' => 0, 'photo_y' => 0, 'photo_w' => 1, 'photo_h' => 1,
                'preview_photo_x' => 0.33, 'preview_photo_y' => 0.25, 'preview_photo_w' => 0.34, 'preview_photo_h' => 0.46,
            ]),
            'print_placement'   => 'front',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
        foreach (\App\Core\Database::all('SELECT * FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY position, id', [$source['id']]) as $i => $v) {
            \App\Core\Database::insert('product_variants', [
                'product_id' => $id, 'name' => $v['name'], 'color' => $v['color'], 'color_hex' => $v['color_hex'], 'size' => $v['size'],
                'price' => null, 'printful_variant_id' => $v['printful_variant_id'], 'position' => $i, 'active' => 1,
            ]);
        }
        return $id;
    }

    /**
     * Fichier d'impression : l'illustration posée dans la zone prévue du visuel d'impression s'il y en a un,
     * sinon l'illustration seule (Printful l'ajuste à la zone d'impression de l'emplacement choisi).
     * @return string chemin public relatif
     */
    public static function printFile(array $row, ?string $designFile, string $text, array $cfg): string
    {
        $art = AiPortrait::artworkPath($row) ?? throw new \RuntimeException('Illustration introuvable.');
        if ($designFile) {
            return Personalizer::render($designFile, $text, $cfg, $art);
        }
        $relative = 'uploads/print/manga-' . sha1(config('app.key') . '|' . $row['token'] . '|' . basename($art)) . '.png';
        if (!is_file(PUBLIC_PATH . '/' . $relative)) {
            if (!is_dir(PUBLIC_PATH . '/uploads/print')) {
                mkdir(PUBLIC_PATH . '/uploads/print', 0755, true);
            }
            copy($art, PUBLIC_PATH . '/' . $relative);
        }
        return $relative;
    }
}
