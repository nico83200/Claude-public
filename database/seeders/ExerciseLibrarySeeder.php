<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\ExerciseTag;
use Illuminate\Database\Seeder;

/**
 * Bibliothèque d'exercices par défaut (contenu pédagogique générique, modifiable
 * par duplication). Idempotent : identifié par le nom pour le périmètre "default".
 */
class ExerciseLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'warmup' => 'Échauffement', 'transitions' => 'Transitions', 'flatwork' => 'Travail sur le plat',
            'figures' => 'Figures de manège', 'straightness' => 'Rectitude', 'balance' => 'Équilibre',
            'impulsion' => 'Impulsion', 'suppling' => 'Assouplissement', 'lateral' => 'Mobilité latérale',
            'jumping' => 'Obstacle', 'poles' => 'Barres au sol', 'groundwork' => 'Travail à pied',
            'lunging' => 'Longe', 'outdoor' => 'Extérieur', 'cooldown' => 'Retour au calme',
        ];
        $i = 0;
        foreach ($categories as $key => $name) {
            ExerciseCategory::updateOrCreate(['key' => $key], ['name' => $name, 'sort_order' => $i++]);
        }
        $cat = ExerciseCategory::pluck('id', 'key');

        $exercises = [
            ['warmup', 'Pas rênes longues', 'all', 'all', 10, null, 'Détendre le cheval et l\'amener à s\'étirer.', 'Marcher au pas actif sur les deux mains, rênes longues, en laissant le cheval étendre l\'encolure.', 'Cheval qui avance d\'un pas régulier, encolure basse et détendue.', 'Ne pas laisser le pas devenir traînant.', ['détente']],
            ['warmup', 'Trot enlevé sur grandes courbes', 'all', 'all', 8, null, 'Activer la circulation et trouver un rythme régulier.', 'Trot enlevé sur la piste et de grands cercles, changements de main fréquents par la diagonale.', 'Rythme constant, cheval disponible aux aides.', 'Éviter les cercles trop petits en début de séance.', ['rythme']],
            ['transitions', 'Transitions pas-trot', 'flat', 'beginner', 10, 10, 'Améliorer la réactivité aux aides et l\'engagement.', 'Enchaîner des transitions montantes et descendantes tous les 10 à 15 foulées, en préparant par une demi-parade.', 'Transitions franches, sans résistance de la bouche.', 'Ne pas tirer pour descendre : utiliser l\'assiette.', ['réactivité']],
            ['transitions', 'Transitions dans l\'allure', 'flat', 'intermediate', 10, 6, 'Développer l\'équilibre et la poussée des postérieurs.', 'Allonger puis raccourcir le trot sur les grands côtés, en gardant la cadence.', 'Variation d\'amplitude sans précipitation.', 'Cheval qui se précipite au lieu de s\'allonger.', ['équilibre']],
            ['figures', 'Cercles de 20 m', 'flat', 'beginner', 8, 4, 'Travailler l\'incurvation et la régularité.', 'Décrire un cercle de 20 m en touchant les 4 points de tangence, aux trois allures.', 'Cercle rond, incurvation constante.', 'Épaule extérieure qui s\'échappe.', ['incurvation']],
            ['figures', 'Serpentine 3 boucles', 'flat', 'intermediate', 8, 2, 'Changer d\'incurvation avec fluidité.', 'Serpentine de 3 boucles au trot, en redressant le cheval à chaque passage de la ligne du milieu.', 'Boucles égales, changement d\'incurvation net.', 'Ne pas anticiper le changement de pli.', ['souplesse']],
            ['straightness', 'Lignes droites sur la ligne du milieu', 'flat', 'all', 6, 6, 'Vérifier et améliorer la rectitude.', 'Entrer sur la ligne du milieu en A, rester droit jusqu\'en C, alterner les mains.', 'Hanches dans l\'alignement des épaules.', 'Corriger avec les jambes plutôt qu\'avec les mains.', ['rectitude']],
            ['balance', 'Arrêts et reculers', 'flat', 'intermediate', 6, 5, 'Améliorer l\'engagement et l\'écoute.', 'Arrêt carré, immobilité 3 secondes, 3 à 4 pas de reculer, repartir au pas.', 'Arrêt droit, reculer diagonal et décontracté.', 'Ne jamais forcer le reculer à la main.', ['équilibre']],
            ['impulsion', 'Départs au galop', 'flat', 'intermediate', 10, 8, 'Obtenir des départs calmes et justes.', 'Départs au galop dans les coins, depuis le trot puis depuis le pas selon le niveau.', 'Départ sur le bon pied, sans précipitation.', 'Préparer par une demi-parade et un léger pli.', ['galop']],
            ['suppling', 'Spirale (cercle qui se resserre)', 'flat', 'intermediate', 8, 2, 'Assouplir et engager le postérieur intérieur.', 'Resserrer progressivement un cercle de 20 m à 10 m puis l\'élargir par la jambe intérieure.', 'Le cheval s\'écarte de la jambe intérieure sans perdre le rythme.', 'Pas de cercle trop petit pour un jeune cheval.', ['souplesse']],
            ['lateral', 'Cession à la jambe', 'dressage', 'intermediate', 8, 4, 'Introduire le déplacement latéral.', 'Depuis la ligne du quart, céder vers la piste au pas puis au trot, le cheval légèrement plié à l\'opposé.', 'Croisement régulier des membres, rythme conservé.', 'Les épaules ne doivent pas précéder les hanches de trop.', ['latéral']],
            ['lateral', 'Épaule en dedans', 'dressage', 'advanced', 10, 4, 'Engager le postérieur intérieur et assouplir.', 'Sur la piste, amener les épaules vers l\'intérieur (3 pistes), pli régulier, sortie sur un cercle.', 'Angle constant, rythme et cadence conservés.', 'Excès d\'encolure sans déplacement des épaules.', ['latéral', 'engagement']],
            ['poles', 'Barres au sol au trot', 'jumping', 'all', 10, 6, 'Régulariser la foulée et l\'attention.', 'Ligne de 4 barres espacées de 1,30 m environ (à adapter au cheval), au trot enlevé.', 'Passage régulier sans toucher les barres.', 'Adapter les distances à l\'amplitude du cheval.', ['rythme', 'attention']],
            ['poles', 'Barres en éventail', 'jumping', 'intermediate', 10, 6, 'Travailler l\'incurvation et l\'amplitude.', 'Barres disposées en éventail sur un cercle : passer au centre, à l\'intérieur ou à l\'extérieur pour varier l\'amplitude.', 'Trajectoire tenue, foulées régulières.', 'Bien regarder la trajectoire.', ['incurvation']],
            ['jumping', 'Croisillon d\'échauffement', 'jumping', 'beginner', 8, 6, 'Préparer le saut en douceur.', 'Croisillon abordé au trot, réception au galop puis retour au trot.', 'Abord centré et calme.', 'Respecter la progressivité des hauteurs.', ['saut']],
            ['jumping', 'Gymnastique en ligne', 'jumping', 'intermediate', 12, 4, 'Développer le geste et la technique.', 'Croisillon - 1 foulée - vertical - 1 foulée - oxer, distances adaptées au cheval.', 'Cheval équilibré, cavalier en suspension stable.', 'Distances à valider par un enseignant.', ['technique']],
            ['groundwork', 'Céder aux hanches à pied', 'groundwork', 'all', 8, 6, 'Améliorer le respect et la mobilité.', 'À pied, au licol, demander au cheval de déplacer les hanches par une pression progressive.', 'Le cheval croise les postérieurs calmement.', 'Rester hors de la zone de frappe.', ['respect']],
            ['groundwork', 'Arrêts et reculers à pied', 'groundwork', 'all', 6, 6, 'Développer l\'écoute et la légèreté.', 'Marcher, s\'arrêter avec le cheval, demander quelques pas de reculer par la longe.', 'Le cheval reste à sa place et réagit à une demande légère.', 'Ne pas tirer en continu.', ['écoute']],
            ['lunging', 'Longe : transitions aux trois allures', 'groundwork', 'all', 15, null, 'Travailler l\'équilibre sans le poids du cavalier.', 'Cercle de 15 à 20 m, transitions à la voix, changements de main réguliers.', 'Cheval détendu, cercle régulier.', 'Limiter la durée au galop sur le cercle.', ['équilibre']],
            ['outdoor', 'Travail en côte au pas', 'outdoor', 'all', 15, null, 'Renforcer la musculature dorsale et l\'arrière-main.', 'Montées et descentes au pas actif, cavalier léger, cheval libre de son encolure.', 'Pas actif et régulier dans les montées.', 'Adapter au terrain et à la condition physique.', ['musculation']],
            ['outdoor', 'Balade d\'habituation', 'outdoor', 'beginner', 30, null, 'Habituer le cheval à l\'extérieur.', 'Parcours connu, idéalement accompagné d\'un cheval expérimenté, au pas.', 'Cheval attentif mais détendu.', 'Prévoir gilet, téléphone et informer un tiers du parcours.', ['confiance']],
            ['cooldown', 'Retour au calme au pas', 'all', 'all', 10, null, 'Récupération et étirement.', 'Pas rênes longues sur les deux mains jusqu\'à respiration normale.', 'Respiration calme, cheval sec ou presque.', 'Ne pas rentrer le cheval essoufflé.', ['récupération']],
            ['cooldown', 'Étirements encolure basse au trot', 'flat', 'intermediate', 5, null, 'Étirer la ligne du dessus.', 'Trot enlevé, laisser filer les rênes progressivement en gardant le contact.', 'Le cheval cherche la main vers le bas.', 'Éviter que le cheval tombe sur les épaules.', ['étirement']],
        ];

        foreach ($exercises as [$catKey, $name, $discipline, $level, $minutes, $reps, $objective, $instructions, $success, $vigilance, $tags]) {
            $exercise = Exercise::firstOrNew(['scope' => 'default', 'name' => $name]);
            $exercise->forceFill([
                'scope' => 'default',
                'exercise_category_id' => $cat[$catKey] ?? null,
                'discipline' => $discipline,
                'level' => $level,
                'duration_minutes' => $minutes,
                'repetitions' => $reps,
                'objective' => $objective,
                'description' => $objective,
                'instructions' => $instructions,
                'success_criteria' => $success,
                'vigilance' => $vigilance,
            ])->save();
            $exercise->tags()->sync(collect($tags)->map(fn ($t) => ExerciseTag::firstOrCreate(['name' => $t])->id));
        }
    }
}
