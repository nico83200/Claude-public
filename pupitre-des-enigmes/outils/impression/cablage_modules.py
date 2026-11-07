# Où se branche chaque fil de chaque module, depuis que les signaux partagés passent par la
# plaque D : sur A (les signaux propres à chaque module) ou sur un rail de D (5 V, masse,
# 3,3 V, MOSI, SCK, MISO). Utilisé par plaque_a.py et plaque_d.py.

# (module, [(broche du module, où, remarque)])
MODULES = [
    ("Liaison A ↔ D (6 fils Dupont, 10 cm)", [
        ("J16 MOSI", "D : rail MOSI", ""), ("J16 SCK", "D : rail SCK", ""), ("J16 MISO", "D : rail MISO", ""),
        ("J16 3,3 V", "D : rail 3,3 V", ""), ("J16 5 V", "D : rail 5 V", ""), ("J16 GND", "D : rail masse", "")]),
    ("Alimentation (22 AWG, embouts)", [
        ("Interrupteur général, borne 2", "D : J1 +", ""), ("Prise USB-C, GND (−)", "D : J1 −", "")]),
    ("Écran 3,5 pouces (fils Dupont, 15 cm)", [
        ("VCC", "D : rail 5 V", "jamais le 3,3 V"), ("GND", "D : rail masse", ""), ("CS", "A : J2 CS", ""),
        ("RESET", "A : J2 RESET", ""), ("DC/RS", "A : J2 DC", ""), ("SDI (MOSI)", "D : rail MOSI", ""),
        ("SCK", "D : rail SCK", ""), ("LED", "A : J2 LED", ""), ("SDO (MISO)", "—", "ne pas brancher"),
        ("T_CLK", "D : rail SCK", ""), ("T_CS", "A : J2 T_CS", ""), ("T_DIN", "D : rail MOSI", ""),
        ("T_DO", "D : rail MISO", ""), ("T_IRQ", "—", "ne pas brancher"),
        ("SD_CS", "A : J2 SD_CS", ""), ("SD_MOSI", "D : rail MOSI", ""), ("SD_MISO", "D : rail MISO", ""),
        ("SD_SCK", "D : rail SCK", "")]),
    ("Lecteur RFID de la fente, RC522 (fils Dupont, 20 cm)", [
        ("SDA", "A : J3 fente", "c'est sa sélection"), ("SCK", "D : rail SCK", ""), ("MOSI", "D : rail MOSI", ""),
        ("MISO", "D : rail MISO", ""), ("IRQ", "—", "ne pas brancher"), ("GND", "D : rail masse", ""),
        ("RST", "D : rail 3,3 V", ""), ("3.3V", "D : rail 3,3 V", "jamais le 5 V")]),
    ("Lecteur RFID à plat, RC522 (fils Dupont, 20 cm)", [
        ("SDA", "A : J3 à plat", ""), ("SCK, MOSI, MISO, GND, RST, 3.3V", "D : comme la fente", ""),
        ("IRQ", "—", "ne pas brancher")]),
    ("Plaque B, le son (câble J14, fils Dupont, 15 cm)", [
        ("B : J14-1 (5 V)", "D : rail 5 V", ""), ("B : J14-2 (masse)", "D : rail masse", ""),
        ("B : J14-3", "A : J14-3 (17)", ""), ("B : J14-4", "A : J14-4 (16)", ""),
        ("B : J14-5", "A : J14-5 (2)", ""), ("B : J14-6", "A : J14-6 (36)", "")]),
    ("Plaque C, le bus I2C (câble J15, fils Dupont, 20 cm)", [
        ("C : J15-1 (3,3 V)", "D : rail 3,3 V", ""), ("C : J15-2 (masse)", "D : rail masse", ""),
        ("C : J15-3 (SDA)", "A : J15 SDA (26)", ""), ("C : J15-4 (SCL)", "A : J15 SCL (27)", "")]),
    ("Molette KY-040 (fils Dupont, 25 cm)", [
        ("+", "D : rail 3,3 V", ""), ("GND", "D : rail masse", ""), ("CLK", "A : J8 CLK (34)", ""),
        ("DT", "A : J8 DT (35)", ""), ("SW", "A : J8 SW (32)", "")]),
    ("Interrupteur à clé (15 cm)", [
        ("Borne 1", "A : J8 clé (33)", ""), ("Borne 2", "D : rail masse", "")]),
    ("Gros bouton (25 cm)", [
        ("Contact NO", "A : J8 bouton (39)", "R4 est sur A"), ("Contact COM", "D : rail masse", "")]),
    ("Chaîne de LED, début : l'anneau (15 cm)", [
        ("5V", "D : J12 +", "22 AWG, embout"), ("GND", "D : J12 −", "22 AWG, embout"),
        ("DIN", "A : J7", "R3 est sur A")]),
    ("Servomoteur : le PCA9685 (20 cm)", [
        ("Bornier V+", "D : J12 +", "22 AWG ; avec le fil des LED dans la même vis"),
        ("Bornier GND", "D : J12 −", "22 AWG")]),
]


def table_modules():
    lignes = []
    for titre, fils in MODULES:
        lignes.append(f"<tr><td class='sous' colspan='3'>{titre}</td></tr>")
        for broche, ou, rem in fils:
            cl = " class='a'" if ou.startswith("A") else (" class='d'" if ou.startswith("D") else "")
            lignes.append(f"<tr><td>{broche}</td><td{cl}><b>{ou}</b></td><td class='g'>{rem}</td></tr>")
    return ("<table class='modules'><tr><th>Broche du module</th><th>Se branche sur</th><th></th></tr>"
            + "".join(lignes) + "</table>")


STYLE = """
  table.modules td.sous { background: #eef3ec; font-weight: 700; }
  table.modules td.a b { color: #2e6fd1; }
  table.modules td.d b { color: #a3392b; }
  .colonnes { column-count: 3; column-gap: 12px; }
  .colonnes table { break-inside: auto; }
"""
