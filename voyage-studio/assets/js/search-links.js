// Liens de recherche pré-remplis vers des sites publics (aucune clé API, aucune extraction automatique :
// l'agent consulte le site lui-même, puis importe le résultat par capture d'écran).

/**
 * Répartit les voyageurs dans les chambres : adultes équitablement, enfants dans les premières chambres.
 * @returns {{adults: number[], children: number[][]}}
 */
export function splitRooms(adults, childAges, rooms) {
  rooms = Math.max(1, Math.min(rooms || 1, Math.max(1, adults)));
  const a = Array.from({ length: rooms }, (_, i) => Math.floor(adults / rooms) + (i < adults % rooms ? 1 : 0));
  const c = Array.from({ length: rooms }, () => []);
  childAges.forEach((age, i) => c[i % rooms].push(age));
  return { adults: a, children: c };
}

/** URL de recherche hotels.com (France). */
export function hotelsComUrl({ destination, checkin, checkout, adults = 2, childAges = [], rooms = 1 }) {
  const { adults: a, children: c } = splitRooms(Math.max(1, +adults || 1), childAges, rooms);
  const params = new URLSearchParams();
  params.set('destination', destination || '');
  if (checkin) params.set('startDate', checkin);
  if (checkout) params.set('endDate', checkout);
  params.set('rooms', String(a.length));
  params.set('adults', a.join(','));
  const kids = c.flatMap((ages, i) => ages.map((age) => `${i + 1}_${age}`));
  if (kids.length) params.set('children', kids.join(','));
  return `https://fr.hotels.com/Hotel-Search?${params.toString()}`;
}

export function parseAges(text, count) {
  const ages = String(text || '').split(/[\s,;]+/).map((x) => parseInt(x, 10)).filter((n) => n >= 0 && n <= 17);
  while (ages.length < count) ages.push(8); // âge par défaut si non précisé
  return ages.slice(0, count);
}
