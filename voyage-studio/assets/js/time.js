// Outils de dates / fuseaux horaires (sans dépendance, via Intl).

const DAY = 86400000;

/** Décalage (minutes) du fuseau `tz` à l'instant UTC `utcMs`. */
export function tzOffsetMin(utcMs, tz) {
  try {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone: tz, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', second: '2-digit',
    }).formatToParts(new Date(utcMs));
    const p = Object.fromEntries(parts.map((x) => [x.type, x.value]));
    const asUtc = Date.UTC(+p.year, +p.month - 1, +p.day, +p.hour % 24, +p.minute, +p.second);
    return Math.round((asUtc - utcMs) / 60000);
  } catch {
    return 0;
  }
}

export function isValidTz(tz) {
  if (!tz) return false;
  try {
    new Intl.DateTimeFormat('en-US', { timeZone: tz });
    return true;
  } catch {
    return false;
  }
}

/** Convertit une date/heure locale d'un fuseau en timestamp UTC (ms). */
export function zonedToUtc(date, time, tz) {
  if (!date) return null;
  const [y, m, d] = date.split('-').map(Number);
  const [hh, mm] = (time || '00:00').split(':').map(Number);
  const guess = Date.UTC(y, m - 1, d, hh, mm);
  if (!isValidTz(tz)) return guess;
  let utc = guess - tzOffsetMin(guess, tz) * 60000;
  // second passage pour les changements d'heure
  utc = guess - tzOffsetMin(utc, tz) * 60000;
  return utc;
}

/** Durée (min) entre deux instants locaux exprimés dans leurs fuseaux respectifs. */
export function durationBetween(d1, t1, tz1, d2, t2, tz2) {
  if (!d1 || !t1 || !t2) return null;
  const a = zonedToUtc(d1, t1, tz1 || tz2);
  const b = zonedToUtc(d2 || d1, t2, tz2 || tz1);
  if (a == null || b == null) return null;
  let diff = Math.round((b - a) / 60000);
  // date d'arrivée non saisie et arrivée « le lendemain »
  if (!d2 && diff < 0) diff += 1440;
  return diff;
}

/** Durée d'une prestation : saisie manuelle prioritaire, sinon calcul depuis les horaires. */
export function itemDuration(item) {
  if (item.duree_min) return item.duree_min;
  if (!['vol', 'train', 'transfert', 'croisiere', 'activite'].includes(item.type)) return null;
  const d = durationBetween(item.date_debut, item.heure_debut, item.tz_depart, item.date_fin, item.heure_fin, item.tz_arrivee);
  return d != null && d > 0 ? d : null;
}

export function itemStartUtc(item) {
  return item.date_debut ? zonedToUtc(item.date_debut, item.heure_debut || '00:00', item.tz_depart) : null;
}

export function itemEndUtc(item) {
  const date = item.date_fin || item.date_debut;
  if (!date) return null;
  if (!item.heure_fin && item.heure_debut && item.duree_min) return itemStartUtc(item) + item.duree_min * 60000;
  return zonedToUtc(date, item.heure_fin || item.heure_debut || '00:00', item.tz_arrivee || item.tz_depart);
}

export function fmtDuration(min) {
  if (min == null || isNaN(min)) return '—';
  const h = Math.floor(min / 60);
  const m = Math.round(min % 60);
  return h ? `${h} h ${String(m).padStart(2, '0')}` : `${m} min`;
}

export function daysBetween(a, b) {
  if (!a || !b) return null;
  return Math.round((Date.parse(b + 'T00:00:00Z') - Date.parse(a + 'T00:00:00Z')) / DAY);
}

export function addDays(date, n) {
  const d = new Date(Date.parse(date + 'T00:00:00Z') + n * DAY);
  return d.toISOString().slice(0, 10);
}

export function todayIso() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** Différence horaire entre deux fuseaux à une date donnée (heures, signe : tz2 - tz1). */
export function tzDiffHours(tz1, tz2, date) {
  const t = date ? Date.parse(date + 'T12:00:00Z') : Date.now();
  return (tzOffsetMin(t, tz2) - tzOffsetMin(t, tz1)) / 60;
}

export function localTimeIn(tz) {
  try {
    return new Intl.DateTimeFormat('fr-FR', { timeZone: tz, hour: '2-digit', minute: '2-digit', weekday: 'short' }).format(new Date());
  } catch {
    return '—';
  }
}

/**
 * Tri chronologique des prestations. Sans heure saisie, on déduit une heure plausible :
 * transfert après l'arrivée d'un vol du jour (ou avant son départ), hébergement en fin de journée…
 */
export function sortChrono(items) {
  const transports = items.filter((i) => ['vol', 'train'].includes(i.type) && i.date_debut)
    .sort((a, b) => `${a.date_debut} ${a.heure_debut || ''}`.localeCompare(`${b.date_debut} ${b.heure_debut || ''}`));
  const first = transports[0];
  const key = (i) => {
    let t = i.heure_debut;
    if (!t) {
      const d = i.date_debut;
      if (i.type === 'transfert' || i.type === 'location') {
        // Arrivée à destination (premier trajet du voyage) : transfert après ; sinon avant le départ du jour.
        const leaving = transports.find((f) => f.date_debut === d && f.heure_debut && f !== first);
        const arriving = transports.find((f) => (f.date_fin || f.date_debut) === d && f.heure_fin && f !== leaving);
        if (leaving) t = leaving.heure_debut.replace(/^(\d\d)/, (h) => String(Math.max(0, +h - 3)).padStart(2, '0'));
        else t = arriving ? arriving.heure_fin + ':30' : '12:00';
      } else if (i.type === 'hebergement') t = '21:00';
      else if (i.type === 'croisiere') t = '16:00';
      else if (i.type === 'activite') t = '10:00';
      else t = '23:59';
    }
    return `${i.date_debut || '9999-99-99'} ${t}`;
  };
  return [...items].sort((a, b) => key(a).localeCompare(key(b)) || (a.ordre || 0) - (b.ordre || 0) || a.id - b.id);
}
