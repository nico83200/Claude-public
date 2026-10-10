<x-field name="product" label="Produit" :value="$t->product" required />
<x-field name="dosage" label="Posologie prescrite" :value="$t->dosage" help="Telle qu'indiquée par le vétérinaire." />
<x-field name="frequency" label="Fréquence" :value="$t->frequency" placeholder="ex. matin et soir" />
<x-field name="times_per_day" type="number" label="Prises par jour" :value="$t->times_per_day" min="1" max="24" />
<x-field name="starts_on" type="date" label="Début" :value="$t->starts_on?->format('Y-m-d')" required />
<x-field name="ends_on" type="date" label="Fin" :value="$t->ends_on?->format('Y-m-d')" />
<x-select name="responsible_user_id" label="Responsable" :options="$users" :value="$t->responsible_user_id" placeholder="—" />
<div class="flex items-end"><x-checkbox name="track_administrations" label="Suivre chaque administration" :checked="$t->track_administrations" /></div>
<x-textarea name="instructions" label="Instructions" :value="$t->instructions" class="sm:col-span-2" />
