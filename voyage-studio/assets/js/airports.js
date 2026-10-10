// Principaux aéroports : code IATA | ville / aéroport | pays | fuseau IANA.
// Sert à l'autocomplétion et au calcul automatique des durées de vol (décalage horaire).
const RAW = `
CDG|Paris Charles-de-Gaulle|France|Europe/Paris
ORY|Paris Orly|France|Europe/Paris
BVA|Paris Beauvais|France|Europe/Paris
LYS|Lyon Saint-Exupéry|France|Europe/Paris
MRS|Marseille Provence|France|Europe/Paris
NCE|Nice Côte d'Azur|France|Europe/Paris
TLS|Toulouse Blagnac|France|Europe/Paris
BOD|Bordeaux Mérignac|France|Europe/Paris
NTE|Nantes Atlantique|France|Europe/Paris
MPL|Montpellier|France|Europe/Paris
LIL|Lille Lesquin|France|Europe/Paris
SXB|Strasbourg|France|Europe/Paris
BSL|Bâle-Mulhouse|France|Europe/Paris
BIQ|Biarritz|France|Europe/Paris
AJA|Ajaccio|France|Europe/Paris
BIA|Bastia|France|Europe/Paris
RNS|Rennes|France|Europe/Paris
BES|Brest|France|Europe/Paris
PUF|Pau|France|Europe/Paris
CFE|Clermont-Ferrand|France|Europe/Paris
PTP|Pointe-à-Pitre (Guadeloupe)|France|America/Guadeloupe
FDF|Fort-de-France (Martinique)|France|America/Martinique
RUN|Saint-Denis (La Réunion)|France|Indian/Reunion
CAY|Cayenne (Guyane)|France|America/Cayenne
PPT|Papeete (Tahiti)|France|Pacific/Tahiti
NOU|Nouméa (Nouvelle-Calédonie)|France|Pacific/Noumea
DZA|Mayotte Dzaoudzi|France|Indian/Mayotte
SXM|Saint-Martin Princess Juliana|Sint Maarten|America/Lower_Princes
GVA|Genève|Suisse|Europe/Zurich
ZRH|Zurich|Suisse|Europe/Zurich
BRU|Bruxelles|Belgique|Europe/Brussels
LUX|Luxembourg|Luxembourg|Europe/Luxembourg
AMS|Amsterdam Schiphol|Pays-Bas|Europe/Amsterdam
LHR|Londres Heathrow|Royaume-Uni|Europe/London
LGW|Londres Gatwick|Royaume-Uni|Europe/London
STN|Londres Stansted|Royaume-Uni|Europe/London
EDI|Édimbourg|Royaume-Uni|Europe/London
DUB|Dublin|Irlande|Europe/Dublin
FRA|Francfort|Allemagne|Europe/Berlin
MUC|Munich|Allemagne|Europe/Berlin
BER|Berlin|Allemagne|Europe/Berlin
VIE|Vienne|Autriche|Europe/Vienna
CPH|Copenhague|Danemark|Europe/Copenhagen
OSL|Oslo|Norvège|Europe/Oslo
ARN|Stockholm Arlanda|Suède|Europe/Stockholm
HEL|Helsinki|Finlande|Europe/Helsinki
KEF|Reykjavik Keflavík|Islande|Atlantic/Reykjavik
RVN|Rovaniemi|Finlande|Europe/Helsinki
TOS|Tromsø|Norvège|Europe/Oslo
MAD|Madrid Barajas|Espagne|Europe/Madrid
BCN|Barcelone El Prat|Espagne|Europe/Madrid
PMI|Palma de Majorque|Espagne|Europe/Madrid
IBZ|Ibiza|Espagne|Europe/Madrid
AGP|Malaga|Espagne|Europe/Madrid
SVQ|Séville|Espagne|Europe/Madrid
VLC|Valence|Espagne|Europe/Madrid
TFS|Tenerife Sud|Espagne|Atlantic/Canary
LPA|Gran Canaria|Espagne|Atlantic/Canary
ACE|Lanzarote|Espagne|Atlantic/Canary
FUE|Fuerteventura|Espagne|Atlantic/Canary
LIS|Lisbonne|Portugal|Europe/Lisbon
OPO|Porto|Portugal|Europe/Lisbon
FAO|Faro|Portugal|Europe/Lisbon
FNC|Funchal (Madère)|Portugal|Atlantic/Madeira
PDL|Ponta Delgada (Açores)|Portugal|Atlantic/Azores
FCO|Rome Fiumicino|Italie|Europe/Rome
MXP|Milan Malpensa|Italie|Europe/Rome
LIN|Milan Linate|Italie|Europe/Rome
VCE|Venise|Italie|Europe/Rome
NAP|Naples|Italie|Europe/Rome
FLR|Florence|Italie|Europe/Rome
PSA|Pise|Italie|Europe/Rome
CTA|Catane|Italie|Europe/Rome
PMO|Palerme|Italie|Europe/Rome
OLB|Olbia|Italie|Europe/Rome
CAG|Cagliari|Italie|Europe/Rome
BRI|Bari|Italie|Europe/Rome
MLA|Malte|Malte|Europe/Malta
ATH|Athènes|Grèce|Europe/Athens
HER|Héraklion (Crète)|Grèce|Europe/Athens
JTR|Santorin|Grèce|Europe/Athens
JMK|Mykonos|Grèce|Europe/Athens
RHO|Rhodes|Grèce|Europe/Athens
CFU|Corfou|Grèce|Europe/Athens
SKG|Thessalonique|Grèce|Europe/Athens
LCA|Larnaca|Chypre|Asia/Nicosia
DBV|Dubrovnik|Croatie|Europe/Zagreb
SPU|Split|Croatie|Europe/Zagreb
ZAG|Zagreb|Croatie|Europe/Zagreb
TIV|Tivat|Monténégro|Europe/Podgorica
PRG|Prague|Tchéquie|Europe/Prague
BUD|Budapest|Hongrie|Europe/Budapest
WAW|Varsovie|Pologne|Europe/Warsaw
KRK|Cracovie|Pologne|Europe/Warsaw
OTP|Bucarest|Roumanie|Europe/Bucharest
SOF|Sofia|Bulgarie|Europe/Sofia
IST|Istanbul|Turquie|Europe/Istanbul
SAW|Istanbul Sabiha Gökçen|Turquie|Europe/Istanbul
AYT|Antalya|Turquie|Europe/Istanbul
DLM|Dalaman|Turquie|Europe/Istanbul
BJV|Bodrum|Turquie|Europe/Istanbul
CMN|Casablanca|Maroc|Africa/Casablanca
RAK|Marrakech|Maroc|Africa/Casablanca
AGA|Agadir|Maroc|Africa/Casablanca
FEZ|Fès|Maroc|Africa/Casablanca
TNG|Tanger|Maroc|Africa/Casablanca
ESU|Essaouira|Maroc|Africa/Casablanca
TUN|Tunis Carthage|Tunisie|Africa/Tunis
DJE|Djerba|Tunisie|Africa/Tunis
MIR|Monastir|Tunisie|Africa/Tunis
ALG|Alger|Algérie|Africa/Algiers
CAI|Le Caire|Égypte|Africa/Cairo
HRG|Hurghada|Égypte|Africa/Cairo
SSH|Charm el-Cheikh|Égypte|Africa/Cairo
LXR|Louxor|Égypte|Africa/Cairo
RMF|Marsa Alam|Égypte|Africa/Cairo
DSS|Dakar|Sénégal|Africa/Dakar
ABJ|Abidjan|Côte d'Ivoire|Africa/Abidjan
SID|Sal|Cap-Vert|Atlantic/Cape_Verde
BVC|Boa Vista|Cap-Vert|Atlantic/Cape_Verde
NBO|Nairobi|Kenya|Africa/Nairobi
MBA|Mombasa|Kenya|Africa/Nairobi
JRO|Kilimandjaro|Tanzanie|Africa/Dar_es_Salaam
ZNZ|Zanzibar|Tanzanie|Africa/Dar_es_Salaam
DAR|Dar es Salaam|Tanzanie|Africa/Dar_es_Salaam
ADD|Addis-Abeba|Éthiopie|Africa/Addis_Ababa
JNB|Johannesburg|Afrique du Sud|Africa/Johannesburg
CPT|Le Cap|Afrique du Sud|Africa/Johannesburg
WDH|Windhoek|Namibie|Africa/Windhoek
VFA|Victoria Falls|Zimbabwe|Africa/Harare
MRU|Maurice|Maurice|Indian/Mauritius
SEZ|Mahé (Seychelles)|Seychelles|Indian/Mahe
TNR|Antananarivo|Madagascar|Indian/Antananarivo
NOS|Nosy Be|Madagascar|Indian/Antananarivo
MLE|Malé (Maldives)|Maldives|Indian/Maldives
DXB|Dubaï|Émirats arabes unis|Asia/Dubai
AUH|Abu Dhabi|Émirats arabes unis|Asia/Dubai
DOH|Doha|Qatar|Asia/Qatar
MCT|Mascate|Oman|Asia/Muscat
AMM|Amman|Jordanie|Asia/Amman
AQJ|Aqaba|Jordanie|Asia/Amman
TLV|Tel Aviv|Israël|Asia/Jerusalem
BEY|Beyrouth|Liban|Asia/Beirut
RUH|Riyad|Arabie saoudite|Asia/Riyadh
JED|Djeddah|Arabie saoudite|Asia/Riyadh
DEL|Delhi|Inde|Asia/Kolkata
BOM|Mumbai|Inde|Asia/Kolkata
GOI|Goa|Inde|Asia/Kolkata
BLR|Bangalore|Inde|Asia/Kolkata
MAA|Chennai|Inde|Asia/Kolkata
CMB|Colombo|Sri Lanka|Asia/Colombo
KTM|Katmandou|Népal|Asia/Kathmandu
BKK|Bangkok Suvarnabhumi|Thaïlande|Asia/Bangkok
DMK|Bangkok Don Mueang|Thaïlande|Asia/Bangkok
HKT|Phuket|Thaïlande|Asia/Bangkok
USM|Koh Samui|Thaïlande|Asia/Bangkok
CNX|Chiang Mai|Thaïlande|Asia/Bangkok
KBV|Krabi|Thaïlande|Asia/Bangkok
SGN|Hô Chi Minh-Ville|Vietnam|Asia/Ho_Chi_Minh
HAN|Hanoï|Vietnam|Asia/Ho_Chi_Minh
DAD|Da Nang|Vietnam|Asia/Ho_Chi_Minh
PQC|Phu Quoc|Vietnam|Asia/Ho_Chi_Minh
REP|Siem Reap|Cambodge|Asia/Phnom_Penh
PNH|Phnom Penh|Cambodge|Asia/Phnom_Penh
LPQ|Luang Prabang|Laos|Asia/Vientiane
RGN|Yangon|Myanmar|Asia/Yangon
KUL|Kuala Lumpur|Malaisie|Asia/Kuala_Lumpur
LGK|Langkawi|Malaisie|Asia/Kuala_Lumpur
SIN|Singapour Changi|Singapour|Asia/Singapore
CGK|Jakarta|Indonésie|Asia/Jakarta
DPS|Bali Denpasar|Indonésie|Asia/Makassar
MNL|Manille|Philippines|Asia/Manila
CEB|Cebu|Philippines|Asia/Manila
HKG|Hong Kong|Chine|Asia/Hong_Kong
MFM|Macao|Chine|Asia/Macau
PEK|Pékin Capital|Chine|Asia/Shanghai
PKX|Pékin Daxing|Chine|Asia/Shanghai
PVG|Shanghai Pudong|Chine|Asia/Shanghai
CAN|Canton|Chine|Asia/Shanghai
TPE|Taipei|Taïwan|Asia/Taipei
ICN|Séoul Incheon|Corée du Sud|Asia/Seoul
NRT|Tokyo Narita|Japon|Asia/Tokyo
HND|Tokyo Haneda|Japon|Asia/Tokyo
KIX|Osaka Kansai|Japon|Asia/Tokyo
OKA|Okinawa Naha|Japon|Asia/Tokyo
CTS|Sapporo|Japon|Asia/Tokyo
SYD|Sydney|Australie|Australia/Sydney
MEL|Melbourne|Australie|Australia/Melbourne
BNE|Brisbane|Australie|Australia/Brisbane
PER|Perth|Australie|Australia/Perth
CNS|Cairns|Australie|Australia/Brisbane
AKL|Auckland|Nouvelle-Zélande|Pacific/Auckland
CHC|Christchurch|Nouvelle-Zélande|Pacific/Auckland
NAN|Nadi|Fidji|Pacific/Fiji
BOB|Bora-Bora|France|Pacific/Tahiti
JFK|New York JFK|États-Unis|America/New_York
EWR|New York Newark|États-Unis|America/New_York
BOS|Boston|États-Unis|America/New_York
IAD|Washington Dulles|États-Unis|America/New_York
MIA|Miami|États-Unis|America/New_York
MCO|Orlando|États-Unis|America/New_York
ATL|Atlanta|États-Unis|America/New_York
ORD|Chicago O'Hare|États-Unis|America/Chicago
DFW|Dallas|États-Unis|America/Chicago
IAH|Houston|États-Unis|America/Chicago
MSY|La Nouvelle-Orléans|États-Unis|America/Chicago
DEN|Denver|États-Unis|America/Denver
PHX|Phoenix|États-Unis|America/Phoenix
LAS|Las Vegas|États-Unis|America/Los_Angeles
LAX|Los Angeles|États-Unis|America/Los_Angeles
SFO|San Francisco|États-Unis|America/Los_Angeles
SEA|Seattle|États-Unis|America/Los_Angeles
HNL|Honolulu|États-Unis|Pacific/Honolulu
ANC|Anchorage|États-Unis|America/Anchorage
YUL|Montréal|Canada|America/Toronto
YQB|Québec|Canada|America/Toronto
YYZ|Toronto|Canada|America/Toronto
YVR|Vancouver|Canada|America/Vancouver
YYC|Calgary|Canada|America/Edmonton
MEX|Mexico|Mexique|America/Mexico_City
CUN|Cancún|Mexique|America/Cancun
HAV|La Havane|Cuba|America/Havana
VRA|Varadero|Cuba|America/Havana
PUJ|Punta Cana|Rép. dominicaine|America/Santo_Domingo
SDQ|Saint-Domingue|Rép. dominicaine|America/Santo_Domingo
MBJ|Montego Bay|Jamaïque|America/Jamaica
NAS|Nassau|Bahamas|America/Nassau
AUA|Aruba|Aruba|America/Aruba
SJO|San José|Costa Rica|America/Costa_Rica
LIR|Liberia|Costa Rica|America/Costa_Rica
PTY|Panama|Panama|America/Panama
BOG|Bogota|Colombie|America/Bogota
CTG|Carthagène|Colombie|America/Bogota
UIO|Quito|Équateur|America/Guayaquil
GPS|Galápagos Baltra|Équateur|Pacific/Galapagos
LIM|Lima|Pérou|America/Lima
CUZ|Cuzco|Pérou|America/Lima
LPB|La Paz|Bolivie|America/La_Paz
SCL|Santiago du Chili|Chili|America/Santiago
IPC|Île de Pâques|Chili|Pacific/Easter
EZE|Buenos Aires Ezeiza|Argentine|America/Argentina/Buenos_Aires
FTE|El Calafate|Argentine|America/Argentina/Rio_Gallegos
USH|Ushuaïa|Argentine|America/Argentina/Ushuaia
GRU|São Paulo|Brésil|America/Sao_Paulo
GIG|Rio de Janeiro|Brésil|America/Sao_Paulo
SSA|Salvador de Bahia|Brésil|America/Bahia
`;

export const AIRPORTS = RAW.trim().split('\n').map((l) => {
  const [code, name, country, tz] = l.split('|');
  return { code, name, country, tz };
});

const BY_CODE = Object.fromEntries(AIRPORTS.map((a) => [a.code, a]));

export function airport(code) {
  return code ? BY_CODE[String(code).trim().toUpperCase().slice(0, 3)] || null : null;
}

export function searchAirports(q, limit = 8) {
  q = (q || '').trim().toLowerCase();
  if (!q) return [];
  const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  const nq = norm(q);
  return AIRPORTS.filter((a) => a.code.toLowerCase() === q || norm(a.name).includes(nq) || norm(a.country).includes(nq))
    .sort((a, b) => (b.code.toLowerCase() === q) - (a.code.toLowerCase() === q))
    .slice(0, limit);
}
