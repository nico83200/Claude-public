<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Les styles du portrait IA. Chaque consigne commence par la même exigence de ressemblance
 * (forme de la tête, robe, marques) : seul le rendu artistique change d'un style à l'autre.
 */
final class PortraitStyles
{
    public const DEFAULT = 'aquarelle';

    /** Exigence commune : le cheval doit rester reconnaissable. */
    public const IDENTITY = 'Redraw this photo as a work of art showing this exact horse. Keep the horse perfectly recognizable: '
        . 'same head shape and proportions, ears, eyes, nostrils and muzzle, face markings (blaze, star, snip) with their exact shape and position, '
        . 'coat color and pattern, mane and forelock. Remove the halter, bridle, lead rope and any rider, person, fence or background object. '
        . 'Composition: head and upper neck only, centered, facing the same way as in the photo, with generous empty space around. ';

    public const ENDING = ' Strictly no text, letters, numbers, signature, logo, frame or border.';

    /**
     * name : nom court · title : nom complet · tagline : rendu · pick : pour qui · slug : page dédiée
     * steps : messages d'attente · style : consigne de rendu (après IDENTITY)
     */
    public const STYLES = [
        'aquarelle' => [
            'name'    => 'Aquarelle',
            'title'   => 'Aquarelle « Encre & Poudre »',
            'tagline' => 'Trait fin bordeaux et aquarelle poudrée',
            'pick'    => 'Notre signature, douce et romantique',
            'slug'    => 'aquarelle',
            'steps'   => ['Tracé à l’encre bordeaux…', 'Lavis d’aquarelle poudrée…'],
            'style'   => null, // consigne historique complète : AiPortrait::STYLE_PROMPT
        ],
        'trait' => [
            'name'    => 'D’un seul trait',
            'title'   => 'D’un seul trait',
            'tagline' => 'Une ligne noire continue, minimaliste',
            'pick'    => 'Pour une déco épurée et moderne',
            'slug'    => 'un-seul-trait',
            'steps'   => ['Recherche de la ligne parfaite…', 'Un seul trait, sans lever le crayon…'],
            'style'   => 'Style: minimalist continuous line drawing (one-line art): a single unbroken, thin, confident black ink line (#2B2523) '
                . 'tracing the outline of the head and ears, the eye, the nostril, the flow of the mane and the edges of the face markings, '
                . 'elegant and sparse, with a very subtle pale wash of the real coat color in a few areas only. '
                . 'Background: plain warm off-white paper (#F9F7F3), nothing else. Mood: modern Scandinavian wall art, airy and refined.',
        ],
        'popart' => [
            'name'    => 'Pop art',
            'title'   => 'Pop art',
            'tagline' => 'Aplats de couleurs vives, contours marqués',
            'pick'    => 'Pour une déco joyeuse et colorée',
            'slug'    => 'pop-art',
            'steps'   => ['Choix des couleurs pop…', 'Aplats et contours…'],
            'style'   => 'Style: bold contemporary pop art illustration in the spirit of Andy Warhol screen prints: flat areas of saturated color, '
                . 'crisp dark outlines, simplified graphic shapes for light and shadow. The real coat color stays recognizable but boosted, '
                . 'and the white face markings stay clearly white. Background: plain off-white (#F9F7F3) with one large flat bold color circle '
                . '(fuchsia, turquoise or sunflower yellow, contrasting with the coat) behind the head. Mood: joyful, punchy, graphic.',
        ],
        'fusain' => [
            'name'    => 'Fusain',
            'title'   => 'Croquis au fusain',
            'tagline' => 'Croquis réaliste en noir et gris',
            'pick'    => 'Le plus fidèle, comme un portrait d’artiste',
            'slug'    => 'fusain',
            'steps'   => ['Esquisse au fusain…', 'Ombres estompées du bout des doigts…'],
            'style'   => 'Style: realistic charcoal and graphite sketch on textured drawing paper: expressive hatching, soft smudged shading, '
                . 'precisely detailed eyes; the coat rendered in black and grey values whose darkness matches the photo, the white markings left as bare paper, '
                . 'loose unfinished strokes fading out at the neck. Background: plain off-white paper (#F6F3EE) with a subtle grain, nothing else. '
                . 'Mood: classic, timeless, an artist\'s study.',
        ],
        'gravure' => [
            'name'    => 'Gravure vintage',
            'title'   => 'Gravure vintage',
            'tagline' => 'Hachures fines sur papier ancien',
            'pick'    => 'Pour un charme d’autrefois',
            'slug'    => 'gravure-vintage',
            'steps'   => ['Gravure des hachures…', 'Vieillissement du papier…'],
            'style'   => 'Style: vintage 19th-century engraving, like an antique equestrian book plate: fine cross-hatching and stippling '
                . 'in dark sepia ink (#3B2A20), light and shade built only with line hatching, white markings left as bare paper. '
                . 'Background: plain aged cream paper (#F3EBDC) with very subtle foxing, nothing else. Mood: elegant antique print, heritage.',
        ],
    ];

    /** Textes de la page dédiée à chaque style : titre (avec <em>), accroche, titre et description pour Google, points forts. */
    public const PAGES = [
        'aquarelle' => [
            'h1'    => 'Son portrait aquarelle, <em>en quelques secondes</em>',
            'lead'  => 'Envoie sa plus belle photo : notre IA la redessine au trait fin bordeaux et à l\'aquarelle poudrée. Son nom, son année de naissance, un joli cadre… et toute votre complicité s\'affiche au mur.',
            'title' => 'Portrait aquarelle de ton cheval, créé en quelques secondes',
            'meta'  => 'Envoie la photo de ton cheval : notre IA la redessine au trait fin et à l\'aquarelle, avec son nom et son année de naissance. Aperçu offert, affiche encadrée prête à accrocher.',
            'points' => [],
        ],
        'trait' => [
            'h1'    => 'Son portrait <em>d\'un seul trait</em>',
            'lead'  => 'Une seule ligne noire, sans lever le crayon, pour dessiner sa tête, son regard et ses marques. Minimaliste, moderne, et pourtant c\'est bien lui.',
            'title' => 'Portrait d\'un seul trait de ton cheval (line art), encadré',
            'meta'  => 'Le portrait de ton cheval en line art : une ligne continue qui dessine sa tête et ses marques, d\'après ta photo. Aperçu offert, encadré, avec son nom.',
            'points' => [['Une ligne continue', 'Un trait unique et fluide, comme un dessin d\'artiste fait d\'un geste.'], ['Ses marques soulignées', 'Liste, étoile, épis : la ligne suit les contours qui le rendent unique.'], ['Une déco épurée', 'Noir sur papier crème : il s\'accorde à tous les intérieurs, du scandinave au plus chic.']],
        ],
        'popart' => [
            'h1'    => 'Son portrait <em>pop art</em>',
            'lead'  => 'Des aplats de couleurs vives, des contours bien marqués : ton cheval façon Warhol, pour une déco qui pétille. Sa robe et ses marques restent bien à lui.',
            'title' => 'Portrait pop art de ton cheval, encadré, d\'après ta photo',
            'meta'  => 'Ton cheval façon pop art : couleurs vives et contours graphiques, d\'après sa photo. Aperçu offert, affiche encadrée avec son nom, prête à accrocher.',
            'points' => [['Des couleurs qui claquent', 'Fuchsia, turquoise ou jaune soleil : un fond vif pour faire ressortir sa robe.'], ['Un style graphique', 'Contours francs et aplats nets, dans l\'esprit des sérigraphies pop.'], ['Toujours reconnaissable', 'Sa robe est sublimée, ses marques blanches restent bien visibles.']],
        ],
        'fusain' => [
            'h1'    => 'Son portrait <em>au fusain</em>',
            'lead'  => 'Un croquis réaliste en noir et gris, aux ombres estompées et au regard travaillé : le portrait le plus fidèle, comme dessiné par une artiste dans son atelier.',
            'title' => 'Portrait au fusain de ton cheval, encadré, d\'après ta photo',
            'meta'  => 'Le portrait réaliste de ton cheval au fusain, d\'après sa photo : ombres estompées, regard précis. Aperçu offert, encadré, avec son nom et son année.',
            'points' => [['Le plus fidèle', 'Proportions, regard, nuances de sa robe : un croquis réaliste.'], ['Des ombres estompées', 'Le grain du papier et le fusain donnent du relief et de la douceur.'], ['Intemporel', 'Noir et gris sur papier blanc cassé : un classique qui ne se démode pas.']],
        ],
        'gravure' => [
            'h1'    => 'Son portrait <em>en gravure vintage</em>',
            'lead'  => 'Des hachures fines à l\'encre sépia, sur un papier qui a du vécu : ton cheval devient une planche d\'un ancien traité d\'équitation.',
            'title' => 'Portrait de ton cheval en gravure vintage, encadré',
            'meta'  => 'Ton cheval en gravure ancienne : hachures fines à l\'encre sépia sur papier vieilli, d\'après sa photo. Aperçu offert, encadré, avec son nom.',
            'points' => [['Des hachures fines', 'Lumière et ombre dessinées trait par trait, comme une vraie gravure.'], ['Un papier vieilli', 'Un crème patiné qui donne tout son charme d\'autrefois.'], ['Un esprit héritage', 'Parfait avec une déco classique, du bois et des tons chauds.']],
        ],
    ];

    /** Signes particuliers proposés à la cliente, ajoutés à la consigne pour les robes difficiles. */
    public const MARKS = [
        'liste'   => ['Liste', 'a white blaze running down the face'],
        'etoile'  => ['Étoile', 'a white star on the forehead'],
        'ladre'   => ['Bout du nez rose', 'a pink snip on the muzzle'],
        'vairon'  => ['Œil bleu', 'a blue eye'],
        'crins'   => ['Crins clairs', 'a light flaxen mane and forelock'],
        'pie'     => ['Robe pie', 'a pinto coat with large white patches exactly placed as in the photo'],
    ];

    /** Styles proposés aux clientes (Admin › Styles du portrait), dans l'ordre d'affichage. */
    public static function enabled(): array
    {
        $keys = array_filter(array_map('trim', explode(',', (string) setting('ai_styles', implode(',', array_keys(self::STYLES))))));
        $styles = array_intersect_key(self::STYLES, array_flip($keys));
        return $styles ?: [self::DEFAULT => self::STYLES[self::DEFAULT]];
    }

    public static function valid(?string $key): string
    {
        return is_string($key) && isset(self::enabled()[$key]) ? $key : array_key_first(self::enabled());
    }

    /** Style présélectionné : celui demandé (clé ou adresse, ex. ?style=pop-art), sinon le premier proposé. */
    public static function selected(?string $given = null): string
    {
        $given ??= isset($_GET['style']) ? (string) $_GET['style'] : null;
        return self::valid(self::bySlug((string) $given) ?? $given);
    }

    public static function bySlug(string $slug): ?string
    {
        foreach (self::enabled() as $key => $style) {
            if ($style['slug'] === $slug) {
                return $key;
            }
        }
        return null;
    }

    public static function name(?string $key): string
    {
        return self::STYLES[$key ?: self::DEFAULT]['title'] ?? self::STYLES[self::DEFAULT]['title'];
    }

    /** Consigne d'origine d'un style. */
    public static function defaultPrompt(string $key): string
    {
        $style = self::STYLES[$key]['style'] ?? null;
        return $style === null ? AiPortrait::STYLE_PROMPT : self::IDENTITY . $style . self::ENDING;
    }

    public static function settingKey(string $key): string
    {
        return $key === self::DEFAULT ? 'ai_prompt' : 'ai_prompt_' . $key;
    }

    /** Consigne envoyée à l'IA : celle des réglages si elle a été personnalisée, plus les signes particuliers. */
    public static function prompt(?string $key, array $marks = []): string
    {
        $key = isset(self::STYLES[$key]) ? $key : self::DEFAULT;
        $custom = trim((string) setting(self::settingKey($key), ''));
        $prompt = $custom !== '' ? $custom : self::defaultPrompt($key);
        $features = array_values(array_filter(array_map(static fn ($m) => self::MARKS[$m][1] ?? null, $marks)));
        if ($features) {
            $prompt .= ' Distinctive features of this horse that must clearly appear: ' . implode(', ', $features) . '.';
        }
        return $prompt;
    }

    /** Signes particuliers valides, au format enregistré (« liste,etoile »). */
    public static function cleanMarks(mixed $marks): string
    {
        return implode(',', array_values(array_intersect(array_keys(self::MARKS), array_map('strval', (array) $marks))));
    }

    /** Image d'exemple du style (générée depuis l'administration), sinon null. */
    public static function example(string $key): ?string
    {
        $map = json_decode((string) setting('ai_style_examples', ''), true) ?: [];
        if (!empty($map[$key]) && is_file(PUBLIC_PATH . '/' . strtok($map[$key], '?'))) {
            return $map[$key];
        }
        if ($key === self::DEFAULT && is_file(PUBLIC_PATH . '/assets/img/portrait-ia/exemple-bai.jpg')) {
            return 'assets/img/portrait-ia/exemple-bai.jpg';
        }
        return null;
    }

    public static function setExample(string $key, string $relative): void
    {
        $map = json_decode((string) setting('ai_style_examples', ''), true) ?: [];
        $map[$key] = $relative . '?v=' . time();
        \App\Core\Settings::set('ai_style_examples', json_encode($map, JSON_UNESCAPED_SLASHES));
    }
}
