@php $log = $log ?? null; @endphp
<x-field name="logged_at" type="datetime-local" label="Date et heure" :value="($log?->logged_at ?? now())->format('Y-m-d\TH:i')" />
<x-select name="appetite" label="Appétit" :options="\App\Models\DailyLog::APPETITE" :value="$log?->appetite ?? 'good'" />
<x-select name="general_state" label="État général" :options="\App\Models\DailyLog::STATE" :value="$log?->general_state ?? 'good'" />
<x-field name="behavior" label="Comportement" :value="$log?->behavior" />
<x-field name="activity" label="Activité" placeholder="Paddock, marcheur, repos…" :value="$log?->activity" />
<x-textarea name="observations" label="Observations" :value="$log?->observations" />
<x-textarea name="anomalies" label="Anomalies constatées" :value="$log?->anomalies" />
