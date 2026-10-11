{{-- Champs d'un exercice (bibliothèque personnelle et administration). --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Nom" :value="$exercise->name" required class="sm:col-span-2" />
                <x-select name="exercise_category_id" label="Catégorie" :options="$categories" :value="$exercise->exercise_category_id" placeholder="—" />
                <x-select name="discipline" label="Discipline" :options="['all' => 'Toutes', 'flat' => 'Travail sur le plat'] + \App\Models\Horse::DISCIPLINES" :value="$exercise->discipline" placeholder="—" />
                <x-select name="level" label="Niveau recommandé" :options="\App\Models\Exercise::LEVELS" :value="$exercise->level" required />
                <x-field name="objective" label="Objectif pédagogique" :value="$exercise->objective" />
                <x-field name="duration_minutes" type="number" label="Durée indicative (min)" :value="$exercise->duration_minutes" min="1" max="300" />
                <x-field name="repetitions" type="number" label="Répétitions" :value="$exercise->repetitions" min="1" max="500" />
                <x-textarea name="description" label="Description" :value="$exercise->description" class="sm:col-span-2" />
                <x-textarea name="instructions" label="Consignes" :value="$exercise->instructions" class="sm:col-span-2" />
                <x-textarea name="steps_text" label="Étapes (une par ligne)" :value="implode(PHP_EOL, $exercise->steps ?? [])" rows="4" class="sm:col-span-2" />
                <x-textarea name="equipment" label="Matériel" :value="$exercise->equipment" />
                <x-textarea name="prerequisites" label="Prérequis" :value="$exercise->prerequisites" />
                <x-textarea name="common_mistakes" label="Erreurs fréquentes" :value="$exercise->common_mistakes" />
                <x-textarea name="vigilance" label="Points de vigilance" :value="$exercise->vigilance" />
                <x-textarea name="success_criteria" label="Critères de réussite" :value="$exercise->success_criteria" class="sm:col-span-2" />
                <x-field name="media_url" type="url" label="Lien vers une image ou vidéo autorisée (https)" :value="$exercise->media_url" class="sm:col-span-2" />
                <x-field name="tags_text" label="Tags (séparés par des virgules)" :value="$exercise->tags?->pluck('name')->implode(', ')" class="sm:col-span-2" />
            </div>
