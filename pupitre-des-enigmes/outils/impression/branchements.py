# Génère branchements.html : tous les branchements du pupitre, fil par fil, dans l'ordre de
# l'installation. Chaque étape se termine par un test : on n'avance que si le test est bon.
# Les plaques sont supposées soudées et testées (plaque-a.pdf à plaque-d.pdf).
#
#   python3 branchements.py        (écrit branchements.html à côté de ce fichier)
from pathlib import Path

ICI = Path(__file__).parent

# Couleurs des fils (les mêmes que le plan de câblage)
C = {"5V": "rouge", "33": "orange", "GND": "noir", "SPI": "blanc", "CS": "violet", "SDA": "jaune",
     "SCL": "vert", "LED": "bleu", "X": "gris"}

# Chaque étape : (titre, chapeau, [lignes], test, remarque)
# Une ligne : (n°, de, vers, couleur, longueur) ; ou ("—", texte) pour une consigne sans fil.
ETAPES = []


def etape(titre, chapeau, lignes, test, alerte=""):
    ETAPES.append((titre, chapeau, lignes, test, alerte))


etape("L'alimentation, jusqu'à la plaque D",
      "La prise USB-C et l'interrupteur général sont sur la paroi droite. Fil souple de 22 AWG, soudé sur la prise et "
      "l'interrupteur (gaine thermorétractable), serré dans le bornier J1 de D avec un embout. Rien d'autre n'est branché.",
      [("1", "Prise USB-C · VBUS (+)", "Interrupteur général · borne 1", "5V", "8 cm"),
       ("2", "Interrupteur général · borne 2", "D · J1 · plot +", "5V", "10 cm"),
       ("3", "Prise USB-C · GND (−)", "D · J1 · plot −", "GND", "10 cm")],
      "Bloc secteur branché, interrupteur sur marche : multimètre en V⎓ 20 V entre le rail 5 V et un rail de masse de D : "
      "<b>5,0 à 5,2 V</b>. Une valeur négative : les fils 2 et 3 sont inversés. Interrupteur sur arrêt : <b>0 V</b>.",
      "Une prise USB-C inversée détruit l'ESP32 : faites ce test <b>avant</b> l'étape 2.")

etape("La plaque A et sa liaison avec D",
      "Six fils Dupont femelle-femelle de 10 cm, en un bloc : J16 de A vers un rail de D chacun. Interrupteur coupé.",
      [("L1", "A · J16-1 (MOSI, GPIO 23)", "D · rail MOSI", "SPI", "10 cm"),
       ("L2", "A · J16-2 (SCK, GPIO 18)", "D · rail SCK", "SPI", "10 cm"),
       ("L3", "A · J16-3 (MISO, GPIO 19)", "D · rail MISO", "SPI", "10 cm"),
       ("L4", "A · J16-4 (3,3 V)", "D · rail 3,3 V", "33", "10 cm"),
       ("L5", "A · J16-5 (5 V, VIN)", "D · rail 5 V", "5V", "10 cm"),
       ("L6", "A · J16-6 (masse)", "D · rail masse", "GND", "10 cm"),
       ("—", "Enfichez l'ESP32 sur A, <b>port USB vers la droite</b>, interrupteur coupé.")],
      "Interrupteur sur marche : la LED rouge de l'ESP32 s'allume. Entre le rail 3,3 V et un rail de masse de D : "
      "<b>3,2 à 3,4 V</b>. Le programme se téléverse (installer-esp32-mac.pdf), le journal série affiche le démarrage.")

ECRAN = [("VCC", "D · rail 5 V", "5V"), ("GND", "D · rail masse", "GND"), ("CS", "A · J2-1 (CS)", "CS"),
         ("RESET", "A · J2-2 (RESET)", "X"), ("DC/RS", "A · J2-3 (DC)", "X"), ("SDI (MOSI)", "D · rail MOSI", "SPI"),
         ("SCK", "D · rail SCK", "SPI"), ("LED", "A · J2-4 (LED)", "X"), ("T_CLK", "D · rail SCK", "SPI"),
         ("T_CS", "A · J2-6 (T_CS)", "CS"), ("T_DIN", "D · rail MOSI", "SPI"), ("T_DO", "D · rail MISO", "SPI"),
         ("SD_CS", "A · J2-5 (SD_CS)", "CS"), ("SD_MOSI", "D · rail MOSI", "SPI"), ("SD_MISO", "D · rail MISO", "SPI"),
         ("SD_SCK", "D · rail SCK", "SPI")]
etape("L'écran 3,5 pouces, sa dalle tactile et son lecteur SD",
      "Seize fils Dupont de 15 cm. Six vont sur J2 de A (les sélections et les commandes), les dix autres sur les rails de D, "
      "sur n'importe quelle broche libre du bon rail. Interrupteur coupé.",
      [(f"É{k + 1}", f"Écran · {b}", v, c, "15 cm") for k, (b, v, c) in enumerate(ECRAN)]
      + [("—", "<b>Ne branchez pas</b> SDO (MISO) de l'écran, ni T_IRQ."),
         ("—", "Glissez la carte micro-SD de l'écran (préparée par preparer_sd.py) dans son lecteur.")],
      "Interrupteur sur marche : le logo du pupitre s'affiche, puis l'écran d'accueil. Un doigt sur l'écran pendant le logo "
      "lance l'étalonnage de la dalle tactile.",
      "Le VCC de l'écran va sur le <b>5 V</b>, jamais sur le 3,3 V : il s'afficherait, puis décrocherait au démarrage du Wi-Fi.")

RFID = [("SDA", None, "CS"), ("SCK", "D · rail SCK", "SPI"), ("MOSI", "D · rail MOSI", "SPI"),
        ("MISO", "D · rail MISO", "SPI"), ("GND", "D · rail masse", "GND"), ("RST", "D · rail 3,3 V", "33"),
        ("3.3V", "D · rail 3,3 V", "33")]
lignes = []
for k, (nom, j3) in enumerate([("de la fente", "A · J3-1 (fente, GPIO 13)"), ("à plat", "A · J3-2 (à plat, GPIO 22)")]):
    for i, (b, v, c) in enumerate(RFID):
        lignes.append((f"RF{k * 7 + i + 1}", f"RC522 {nom} · {b}", v or j3, c, "20 cm"))
lignes.append(("—", "<b>Ne branchez pas</b> IRQ. J3-3 reste libre (réserve pour un troisième lecteur)."))
etape("Les deux lecteurs RFID",
      "Sept fils Dupont de 20 cm par lecteur. Seul SDA (la sélection) va sur A ; le reste sur les rails de D. Interrupteur coupé.",
      lignes,
      "Interrupteur sur marche : le journal série annonce les deux lecteurs. Une carte Monde glissée dans la fente charge le "
      "monde ; un objet posé sur le lecteur à plat apparaît au journal.",
      "Les RC522 se branchent sur le <b>3,3 V</b>, jamais sur le 5 V.")

etape("Le son : la plaque B, le haut-parleur et le casque",
      "Le câble J14 de B se partage : ses broches 1 et 2 viennent des rails de D, ses broches 3 à 6 de J14 de A. "
      "Fils Dupont de 15 cm ; haut-parleur en 22 AWG soudé sur ses cosses ; casque en fil fin soudé sur l'embase jack.",
      [("S1", "D · rail 5 V", "B · J14-1 (+ 5 V)", "5V", "15 cm"),
       ("S2", "D · rail masse", "B · J14-2 (masse)", "GND", "15 cm"),
       ("S3", "A · J14-3 (GPIO 17)", "B · J14-3 (RX du DFPlayer haut-parleur)", "X", "15 cm"),
       ("S4", "A · J14-4 (GPIO 16)", "B · J14-4 (TX du DFPlayer haut-parleur)", "X", "15 cm"),
       ("S5", "A · J14-5 (GPIO 2)", "B · J14-5 (RX du DFPlayer casque)", "X", "15 cm"),
       ("S6", "A · J14-6 (GPIO 36)", "B · J14-6 (TX du DFPlayer casque)", "X", "15 cm"),
       ("30", "B · J5 plot 1 (SPK_1)", "Haut-parleur · borne + (point rouge)", "5V", "12 cm"),
       ("31", "B · J5 plot 2 (SPK_2)", "Haut-parleur · borne −", "GND", "12 cm"),
       ("32", "B · J6 plot 1 (DAC_L)", "Prise jack · pointe", "X", "35 cm"),
       ("33", "B · J6 plot 2 (DAC_R)", "Prise jack · anneau", "X", "35 cm"),
       ("34", "B · J6 plot 3 (GND)", "Prise jack · manchon", "GND", "35 cm"),
       ("—", "Enfichez les deux DFPlayer, <b>fente micro-SD vers le bas</b>, chacun avec sa carte micro-SD.")],
      "Avant d'enficher les DFPlayer : entre VCC et GND de leurs barrettes, <b>5,0 à 5,2 V</b>. Puis, interrupteur sur marche : "
      "le jingle du pupitre sort du haut-parleur ; un message « au casque » s'entend dans le casque.",
      "Si le téléversement du programme échoue désormais, débranchez S5 (GPIO 2) le temps de flasher.")

etape("Les commandes : la molette, le gros bouton et la clé",
      "Molette : fils Dupont sur sa barrette. Gros bouton et clé : fils soudés sur leurs cosses, sous gaine. Leur signal va sur "
      "J8 de A, leur masse (et le + de la molette) sur les rails de D.",
      [("56", "Molette KY-040 · +", "D · rail 3,3 V", "33", "25 cm"),
       ("57", "Molette KY-040 · GND", "D · rail masse", "GND", "25 cm"),
       ("58", "Molette KY-040 · CLK", "A · J8-1 (GPIO 34)", "X", "25 cm"),
       ("59", "Molette KY-040 · DT", "A · J8-2 (GPIO 35)", "X", "25 cm"),
       ("60", "Molette KY-040 · SW", "A · J8-3 (GPIO 32)", "X", "25 cm"),
       ("61", "Gros bouton · contact NO", "A · J8-5 (GPIO 39)", "X", "25 cm"),
       ("62", "Gros bouton · contact COM", "D · rail masse", "GND", "25 cm"),
       ("63", "Interrupteur à clé · borne 1", "A · J8-4 (GPIO 33)", "X", "15 cm"),
       ("64", "Interrupteur à clé · borne 2", "D · rail masse", "GND", "15 cm")],
      "Interrupteur sur marche : la molette fait défiler le menu, son appui valide ; le gros bouton et la clé s'inscrivent au "
      "journal série.",
      "La molette prend le <b>3,3 V</b> : sur le 5 V, elle enverrait 5 V dans l'ESP32.")

LED = [("35", "D · J12 · plot +", "Anneau de 12 LED · 5V", "5V", "15 cm"),
       ("36", "D · J12 · plot −", "Anneau de 12 LED · GND", "GND", "15 cm"),
       ("37", "A · J7 (DIN, par R3)", "Anneau de 12 LED · DIN", "LED", "15 cm"),
       ("—", "Condensateur de 1000 µF soudé à l'entrée de l'anneau : + sur 5V, la bande « − » sur GND.")]
MAILLONS = [("38", "Anneau", "LED de la fente", "10 cm"), ("41", "LED de la fente", "LED du plateau", "15 cm"),
            ("44", "LED du plateau", "Voyants 1 à 5", "25 cm"), ("47", "Voyants 1 à 5 (fin)", "Voyants 6 à 10", "10 cm"),
            ("50", "Voyants 6 à 10 (fin)", "Ruban sous la boîte", "25 cm"), ("53", "Ruban sous la boîte (fin)", "LED du tiroir secret", "30 cm")]
for n, a, b, lg in MAILLONS:
    n = int(n)
    LED += [(str(n), f"{a} · 5V", f"{b} · 5V", "5V", lg), (str(n + 1), f"{a} · GND", f"{b} · GND", "GND", lg),
            (str(n + 2), f"{a} · DOUT", f"{b} · DIN", "LED", lg)]
    if n == 50:
        LED.append(("—", "Coupez ce maillon (50 à 52) par un petit connecteur Dupont de 3 broches, juste au-dessus du fond : "
                         "c'est le seul fil à débrancher pour retirer le fond."))
etape("La chaîne de LED",
      "Une seule chaîne : le 5 V et la masse viennent de J12 de D, le signal de J7 de A. Ensuite, chaque maillon a trois fils "
      "soudés sur les pastilles : la sortie <b>DOUT</b> d'une LED va dans l'entrée <b>DIN</b> de la suivante (la flèche "
      "imprimée sur la LED montre le sens). Fil de 22 AWG pour les fils 35 et 36, avec embouts.",
      LED,
      "Interrupteur sur marche : l'anneau s'allume au démarrage, puis la fente, le plateau, les voyants, le dessous et le tiroir. "
      "Une LED reste éteinte : regardez le maillon juste avant elle (DOUT → DIN, et le sens de la flèche).")

I2C = [("a", "65", "PCF8574 du clavier", "VCC", "15 cm"), ("b", "69", "PCA9685 du tiroir", "VCC", "20 cm"),
       ("c", "73", "PCF8574 des boutons", "VCC", "20 cm"), ("d", "77", "Forge BME280", "VIN", "15 cm"),
       ("e", "81", "Enclume QMC5883L", "VCC", "10 cm"), ("f", "85", "Horloge HT16K33", "VCC", "10 cm"),
       ("g", "89", "Œil APDS-9960", "VCC", "10 cm"), ("h", "93", "Capteur de couleur TCS34725", "VIN", "25 cm")]
lignes = [("I1", "D · rail 3,3 V", "C · J15-1 (3,3 V)", "33", "20 cm"),
          ("I2", "D · rail masse", "C · J15-2 (masse)", "GND", "20 cm"),
          ("I3", "A · J15 SDA (GPIO 26)", "C · J15-3 (SDA)", "SDA", "20 cm"),
          ("I4", "A · J15 SCL (GPIO 27)", "C · J15-4 (SCL)", "SCL", "20 cm")]
for d, n, nom, vcc, lg in I2C:
    n = int(n)
    for k, (broche, mod, c) in enumerate([("1", vcc, "33"), ("2", "GND", "GND"), ("3", "SDA", "SDA"), ("4", "SCL", "SCL")]):
        lignes.append((str(n + k), f"C · J11{d}-{broche}", f"{nom} · {mod}", c, lg))
lignes += [("—", "Adresses : PCF8574 du clavier, cavaliers A0, A1, A2 sur GND (0x20) ; PCF8574 des boutons, A0 sur VCC, "
                 "A1 et A2 sur GND (0x21). BME280 : SDO libre. Les autres modules ont une adresse fixe."),
           ("—", "J11i et J11j restent libres : la réserve pour de futurs modules.")]
etape("Le bus I2C : la plaque C et ses huit modules",
      "La plaque C est sous le couvercle. Son câble J15 se partage : 3,3 V et masse depuis les rails de D, SDA et SCL depuis "
      "J15 de A. Puis un câble de 4 fils Dupont par module, en étoile depuis C : rien à souder sur les modules.",
      lignes,
      "Interrupteur sur marche : au démarrage, le journal série liste les modules I2C qui répondent (0x20, 0x21, 0x29, 0x39, "
      "0x40, 0x70, 0x76, 0x0D). Un module absent : son câble, puis son adresse.",
      "Tous les modules I2C sur le <b>3,3 V</b>, jamais sur le 5 V.")

etape("Les boutons de couleur et le clavier",
      "Sur les modules PCF8574, pas sur les plaques : fils soudés sur les boutons, nappe du clavier sur la barrette du module.",
      [("101", "PCF8574 des boutons · P0", "Bouton rouge · contact NO", "X", "10 cm"),
       ("102", "PCF8574 des boutons · P1", "Bouton bleu · contact NO", "X", "10 cm"),
       ("103", "PCF8574 des boutons · P2", "Bouton jaune · contact NO", "X", "10 cm"),
       ("104", "PCF8574 des boutons · P3", "Bouton vert · contact NO", "X", "10 cm"),
       ("105", "PCF8574 des boutons · GND", "Bouton rouge · COM", "GND", "5 cm"),
       ("106", "Bouton rouge · COM", "Bouton bleu · COM", "GND", "5 cm"),
       ("107", "Bouton bleu · COM", "Bouton jaune · COM", "GND", "5 cm"),
       ("108", "Bouton jaune · COM", "Bouton vert · COM", "GND", "5 cm"),
       ("109–116", "PCF8574 du clavier · P0 à P7", "Nappe du clavier · fils 1 à 8 (lignes 1-4, puis colonnes 1-4)", "X", "nappe")],
      "Interrupteur sur marche : chaque bouton et chaque touche s'inscrit au journal série (« Clavier : 7 »). Touches mélangées : "
      "la nappe est à l'envers, ou corrigez le tableau TOUCHES de broches.h.")

etape("Le tiroir secret",
      "Le PCA9685 est vissé dans la face arrière, à gauche, au-dessus du fourreau du tiroir. Sa puissance vient de J12 de D, "
      "dans la même vis que le 5 V des LED. Fil de 22 AWG avec embouts.",
      [("97", "D · J12 · plot + (avec le fil 35)", "PCA9685 · bornier V+", "5V", "20 cm"),
       ("98", "D · J12 · plot − (avec le fil 36)", "PCA9685 · bornier GND", "GND", "20 cm"),
       ("—", "Condensateur de 470 µF sur le bornier du PCA9685 : + sur V+."),
       ("—", "Servomoteur sur la voie 0 du PCA9685 : marron sur GND, rouge sur V+, orange sur PWM."),
       ("99", "PCF8574 des boutons · P4", "Microrupteur de fin de course · NO", "X", "25 cm"),
       ("100", "PCF8574 des boutons · GND", "Microrupteur de fin de course · COM", "GND", "25 cm")],
      "Interrupteur sur marche, une carte Monde dans la fente, menu <b>Tiroir secret</b> : le tiroir sort et sa LED s'allume ; "
      "rangez-y une carte, poussez le tiroir au fond et validez au bouton vert : le microrupteur confirme qu'il est fermé.")


def ligne(l):
    if l[0] == "—":
        return f"<tr class='consigne'><td class='case'>☐</td><td class='n'></td><td colspan='4'>{l[1]}</td></tr>"
    n, de, vers, c, lg = l
    return (f"<tr><td class='case'>☐</td><td class='n'>{n}</td><td>{de}</td><td class='fl'>→</td><td><b>{vers}</b></td>"
            f"<td class='lg'><span class='c {C[c]}'></span>{lg}</td></tr>")


def html():
    total = sum(1 for e in ETAPES for l in e[2] if l[0] != "—")
    som = "".join(f"<li><b>Étape {i}</b> · {t}</li>" for i, (t, *_) in enumerate(ETAPES, 1))
    corps = []
    for i, (titre, chapeau, lignes, test, alerte) in enumerate(ETAPES, 1):
        nb = sum(1 for l in lignes if l[0] != "—")
        corps.append(f"""<section class="etape"><div class="tete">
<h2><span class="num">{i}</span> {titre} <span class="g">· {nb} fils</span></h2>
<p class="chapeau">{chapeau}</p>
{f'<div class="alerte">{alerte}</div>' if alerte else ''}</div>
<table><thead><tr><th></th><th>N°</th><th>Départ</th><th></th><th>Arrivée</th><th>Fil</th></tr></thead><tbody>
{''.join(ligne(l) for l in lignes)}</tbody></table>
<div class="test"><b>✔ Test de l'étape {i}</b> · {test}</div>
</section>""")
    return f"""<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Les branchements, dans l'ordre</title>
<link rel="stylesheet" href="commun.css">
<style>
  @page {{ size: A4; margin: 12mm 13mm 14mm; }}
  body {{ font-size: 9.4pt; }}
  .couverture {{ background: var(--vert); color: #fff; border-radius: 10px; padding: 18px 22px; margin-bottom: 10px; }}
  .couverture h1 {{ color: #fff; margin: 0; font-size: 24pt; }}
  .couverture p {{ color: #f2d9a6; margin: 4px 0 0; }}
  h2 {{ font-size: 13.5pt; margin: 12px 0 4px; break-after: avoid; }}
  h2 .num {{ display: inline-block; background: var(--vert); color: #fff; border-radius: 50%; width: 22px; height: 22px;
            text-align: center; line-height: 22px; font-size: 11pt; margin-right: 4px; }}
  h2 .g {{ color: var(--gris); font-size: 9.5pt; font-family: "Source Sans", sans-serif; font-weight: 400; }}
  .etape {{ margin-top: 10px; }}
  .tete {{ break-inside: avoid; break-after: avoid; }}
  thead {{ display: table-header-group; }}
  .chapeau {{ margin: 2px 0 5px; }}
  table {{ width: 100%; border-collapse: collapse; font-size: 8.6pt; }}
  th {{ background: var(--vert); color: #fff; text-align: left; padding: 3px 5px; }}
  td {{ border-bottom: 1px solid var(--filet); padding: 2.4px 5px; vertical-align: top; }}
  tr {{ break-inside: avoid; }}
  td.case {{ width: 16px; font-size: 11pt; color: var(--gris); padding-top: 0; }}
  td.n {{ width: 46px; color: var(--gris); white-space: nowrap; }}
  td.fl {{ width: 12px; color: var(--or); font-weight: 700; }}
  td.lg {{ width: 62px; white-space: nowrap; color: var(--gris); }}
  tr.consigne td {{ background: #fbf7ee; font-style: italic; }}
  .c {{ display: inline-block; width: 9px; height: 9px; border-radius: 2px; border: 1px solid #0003; vertical-align: -1px; margin-right: 4px; }}
  .rouge {{ background: #d23b2e; }} .noir {{ background: #222; }} .orange {{ background: #f08a24; }} .jaune {{ background: #f2c230; }}
  .vert {{ background: #2f9a4a; }} .bleu {{ background: #2e6fd1; }} .violet {{ background: #7b4bb3; }} .gris {{ background: #9a9a9a; }}
  .blanc {{ background: #fff; }}
  .test {{ background: #e3ebe2; border-left: 4px solid var(--vert); padding: 6px 10px; margin: 8px 0 4px; font-size: 9pt; break-inside: avoid; }}
  .alerte {{ background: #fbeceb; border-left: 4px solid var(--rouge); padding: 5px 10px; margin: 4px 0 6px; font-size: 8.8pt; }}
  .note {{ background: #fbf7ee; border-left: 4px solid var(--or); padding: 6px 10px; margin: 6px 0; font-size: 9pt; }}
  ol.sommaire {{ columns: 2; margin: 4px 0 8px 18px; padding: 0; }}
</style></head><body>
<div class="couverture"><h1>Les branchements, dans l'ordre</h1>
<p>Tous les fils du pupitre ({total} branchements), de la prise d'alimentation jusqu'au tiroir secret : une étape après l'autre, chacune terminée par un test.</p></div>

<h2>Avant de commencer</h2>
<div class="note">
<b>Les quatre plaques sont soudées et testées</b> (plaque-a.pdf à plaque-d.pdf) et vissées à leur place : A, D et B contre la face
arrière, C sous le couvercle (plan-cablage.pdf, § 2). <b>Aucun module n'est enfiché</b> : ni l'ESP32, ni les DFPlayer.<br>
<b>Toujours interrupteur coupé</b> pour brancher, débrancher ou enficher quoi que ce soit ; on ne le remet sur marche que pour le test.<br>
<b>Départ → arrivée</b> : « A · J8-1 » est la broche 1 du connecteur J8 de la plaque A (la broche 1 porte un point de peinture) ;
« D · rail masse » est n'importe quelle broche libre d'un des deux rails de masse de D. Chaque fil porte son numéro sur un drapeau
d'adhésif, à chaque bout ; cochez la case ☐ une fois le fil posé. Les longueurs sont indicatives : mesurez sur la boîte et ajoutez 3 cm.
</div>
<p style="margin:4px 0"><span class="c rouge"></span>+ 5 V · <span class="c orange"></span>3,3 V · <span class="c noir"></span>masse ·
<span class="c blanc"></span>SPI · <span class="c violet"></span>sélection (CS) · <span class="c jaune"></span>SDA · <span class="c vert"></span>SCL ·
<span class="c bleu"></span>données des LED · <span class="c gris"></span>autres signaux</p>
<ol class="sommaire">{som}</ol>
<div class="alerte"><b>Une seule règle de tension</b> : l'écran, les DFPlayer, les LED et le PCA9685 (V+) prennent le <b>5 V</b> ; les lecteurs RFID, la molette et tous
les modules I2C prennent le <b>3,3 V</b>. Un module 3,3 V branché sur le 5 V peut détruire l'ESP32.</div>

{''.join(corps)}

<h2 style="break-before:page">Le contrôle final</h2>
<table>
<tr><td class="case">☐</td><td>Chaque bloc de fils Dupont tient par une goutte de colle chaude, une fois tous les tests réussis.</td></tr>
<tr><td class="case">☐</td><td>Aucun fil ne frotte contre le tiroir ni ne passe sous une entretoise ; les fils du couvercle ont assez de mou pour l'ouvrir en grand.</td></tr>
<tr><td class="case">☐</td><td>Le maillon 50-52 (le ruban sous la boîte) se débranche : le fond se retire.</td></tr>
<tr><td class="case">☐</td><td>La mission <b>Banc d'essai</b> (carte du professeur Lumen) valide tous les organes, un par un : c'est le test complet du pupitre.</td></tr>
</table>
<div class="note"><b>Si l'ESP32 ne démarre plus une fois tout branché</b> : débranchez J2 (la GPIO 12 du rétroéclairage doit être basse au démarrage),
puis le câble J14 du son (la GPIO 2). <b>Si des modules I2C répondent mal</b>, passez la vitesse du bus de 400 à 100 kHz
(<code>firmware/src/commandes.cpp</code>).</div>
</body></html>"""


if __name__ == "__main__":
    (ICI / "branchements.html").write_text(html(), encoding="utf-8")
    print("✓ branchements.html")
