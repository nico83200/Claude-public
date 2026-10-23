#!/usr/bin/env python3
"""
Poste d'encodage Tapikard — cartes NTAG 424 DNA avec SUN (URL signée à chaque scan).

Lecteur : ACR122U (ACS) ou compatible PC/SC (ex. GOTOP ACR122U).
Guide complet : docs/encodage-ntag424.md

Utilisation :
  python3 encodeur.py                 encoder des cartes (scannez ou tapez l'identifiant imprimé)
  python3 encodeur.py --lot LOT.csv   encoder les cartes d'un lot dans l'ordre du CSV du back-office
  python3 encodeur.py --verifier      lire une carte et vérifier sa signature, sans rien modifier
  python3 encodeur.py --lecteurs      lister les lecteurs détectés
  python3 encodeur.py --test          tests (vecteurs NXP + encodage sur carte émulée)
"""
from __future__ import annotations

import argparse
import csv
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Callable, Optional

import ntag424 as n

ICI = Path(__file__).resolve().parent
VERSION_CLES = 0x01


class EncodageError(Exception):
    pass


@dataclass
class Cles:
    k_meta: bytes       # clé 2 (SDMMetaRead), commune à toutes les cartes — SUN_K_META du serveur
    master_k1: bytes    # dont dérive la clé 1 (SDMFileRead) de chaque carte — SUN_MASTER_K1 du serveur
    master_k0: bytes    # dont dérivent les clés 0, 3 et 4 — uniquement sur le poste d'encodage

    def de_la_carte(self, uid: bytes) -> dict:
        return {
            0: n.diversify_key(self.master_k0, uid, 0),
            1: n.diversify_key(self.master_k1, uid, 1),
            2: self.k_meta,
            3: n.diversify_key(self.master_k0, uid, 3),
            4: n.diversify_key(self.master_k0, uid, 4),
        }


@dataclass
class Resultat:
    uid: bytes
    compteur: int
    reparation: bool
    url: str


def _ok(resp: bytes, quoi: str) -> bytes:
    data, sw = n.split(resp)
    if sw not in (0x9000, 0x9100):
        raise n.CardError(sw, quoi)
    return data


def lire_ndef(transmit: n.Transmit) -> str:
    """Lit l'URL comme le ferait un téléphone (déclenche le miroir SUN)."""
    _ok(transmit(n.iso_select_ndef_app()), "sélection de l'application NDEF")
    _ok(transmit(n.iso_select_file(n.NDEF_FILE_ID)), "sélection du fichier NDEF")
    nlen = int.from_bytes(_ok(transmit(n.iso_read_binary(0, 2)), "lecture NDEF"), "big")
    if nlen == 0 or nlen > 254:
        raise EncodageError("la carte ne contient pas d'URL")
    corps = _ok(transmit(n.iso_read_binary(2, nlen)), "lecture NDEF")
    return n.parse_uri_file(nlen.to_bytes(2, "big") + corps)


def parametres_sun(url: str) -> tuple[str, str]:
    q = urllib.parse.parse_qs(urllib.parse.urlparse(url).query)
    try:
        return q["e"][0], q["m"][0]
    except KeyError:
        raise EncodageError(f"URL sans signature SUN : {url}")


def encoder_carte(transmit: n.Transmit, base_url: str, cles: Cles, uid_lecteur: Optional[bytes] = None,
                  log: Callable[[str], None] = lambda _m: None) -> Resultat:
    """
    Configure une carte NTAG 424 DNA pour Tapikard. Sûr à relancer : une carte déjà configurée
    (même partiellement, ex. retirée trop tôt) est reprise avec ses clés diversifiées.
    """
    gabarit = n.sun_template(base_url)

    # 1. URL : écriture libre sur une carte neuve (refusée si la carte est déjà verrouillée).
    _ok(transmit(n.iso_select_ndef_app()), "sélection de l'application NDEF")
    _ok(transmit(n.iso_select_file(n.NDEF_FILE_ID)), "sélection du fichier NDEF")
    _, sw = n.split(transmit(n.iso_update_binary(0, gabarit.file)))
    url_ecrite = sw == 0x9000
    if sw not in (0x9000, 0x6982, 0x6985, 0x6986):         # 69xx : écriture verrouillée (carte déjà configurée)
        raise n.CardError(sw, "écriture de l'URL")
    log("URL écrite" if url_ecrite else "carte déjà verrouillée : reprise avec les clés Tapikard")

    # 2. Authentification avec la clé 0 : d'usine (carte neuve) ou diversifiée (carte déjà configurée).
    _ok(transmit(n.iso_select_ndef_app()), "sélection de l'application NDEF")
    cle0 = n.FACTORY_KEY
    reparation = False
    try:
        s = n.authenticate_ev2_first(transmit, 0, cle0)
    except n.CardError as e:
        if e.sw != 0x91AE or uid_lecteur is None:
            raise
        cle0 = cles.de_la_carte(uid_lecteur)[0]
        try:
            s = n.authenticate_ev2_first(transmit, 0, cle0)
        except n.CardError as e2:
            if e2.sw == 0x91AE:
                raise EncodageError("carte configurée avec d'autres clés (pas une carte vierge ni une carte Tapikard)")
            raise
        reparation = True
    log("authentifiée (clé d'usine)" if not reparation else "authentifiée (clé Tapikard, mode reprise)")

    def reauth() -> n.Session:
        _ok(transmit(n.iso_select_ndef_app()), "sélection de l'application NDEF")
        return n.authenticate_ev2_first(transmit, 0, cle0)

    # 3. UID réel de la puce (canal chiffré) et clés propres à cette carte.
    uid = n.get_card_uid(s)
    if uid_lecteur and uid != uid_lecteur:
        raise EncodageError(f"UID incohérent (lecteur {uid_lecteur.hex()}, puce {uid.hex()}) : UID aléatoire activé ?")
    cibles = cles.de_la_carte(uid)
    log(f"UID {uid.hex().upper()}")

    # 4. Carte verrouillée : réécriture de l'URL avec WriteData (accès clé 0), puis nouvelle session.
    if not url_ecrite:
        f = gabarit.file
        cmd = bytes([n.NDEF_FILE_NO]) + (0).to_bytes(3, "little") + len(f).to_bytes(3, "little") + f
        _ok(transmit(n.native(0x8D, cmd)), "réécriture de l'URL")
        s = reauth()

    # 5. Miroir SUN : lecture libre, écriture et réglages réservés à la clé 0.
    n.change_file_settings(s, gabarit.sdm)
    log("SUN configuré")

    # 6. Clés 1 à 4. L'ancienne valeur est soit d'usine, soit déjà la bonne (reprise après interruption).
    for key_no in (1, 2, 3, 4):
        for ancienne in (n.FACTORY_KEY, cibles[key_no]):
            try:
                n.change_key(s, key_no, cibles[key_no], VERSION_CLES, old_key=ancienne)
                break
            except n.CardError as e:
                if e.sw != 0x911E:
                    raise
                s = reauth()                    # une erreur ferme la session
        else:
            raise EncodageError(f"clé {key_no} : valeur actuelle inconnue")
    log("clés 1 à 4 installées")

    # 7. Clé 0 en dernier (la session se termine).
    if cle0 != cibles[0]:
        n.change_key(s, 0, cibles[0], VERSION_CLES)
        log("clé 0 installée")

    # 8. Contrôle final, comme un téléphone puis comme le serveur.
    url = lire_ndef(transmit)
    if not url.startswith(base_url.rstrip("/") + "/c?"):
        raise EncodageError(f"URL inattendue sur la carte : {url}")
    e, m = parametres_sun(url)
    uid_lu, compteur = n.verify_sun(cles.k_meta, lambda u: n.diversify_key(cles.master_k1, u, 1), e, m)
    if uid_lu != uid:
        raise EncodageError("la signature ne correspond pas à cette puce")
    log(f"signature vérifiée (compteur {compteur})")
    return Resultat(uid, compteur, reparation, url)


# --- Configuration -----------------------------------------------------------------

def charger_config(chemin: Optional[str]) -> dict:
    conf = {}
    fichier = Path(chemin) if chemin else ICI / "encodeur.env"
    if fichier.exists():
        for ligne in fichier.read_text(encoding="utf-8").splitlines():
            ligne = ligne.strip()
            if ligne and not ligne.startswith("#") and "=" in ligne:
                k, v = ligne.split("=", 1)
                conf[k.strip()] = v.strip().strip('"').strip("'")
    for k in ("TAPIKARD_URL", "ENCODING_API_KEY", "SUN_K_META", "SUN_MASTER_K1", "SUN_MASTER_K0"):
        if os.environ.get(k):
            conf[k] = os.environ[k]
    return conf


def cles_depuis(conf: dict) -> Cles:
    def cle(nom: str) -> bytes:
        v = conf.get(nom, "")
        if not re.fullmatch(r"[0-9a-fA-F]{32}", v):
            raise SystemExit(f"✗ {nom} manquante ou invalide (32 caractères hexadécimaux) dans encodeur.env")
        b = bytes.fromhex(v)
        if b == bytes(16):
            raise SystemExit(f"✗ {nom} vaut la clé d'usine (00…00) : générez une vraie clé")
        return b
    return Cles(k_meta=cle("SUN_K_META"), master_k1=cle("SUN_MASTER_K1"), master_k0=cle("SUN_MASTER_K0"))


# --- API du site -----------------------------------------------------------------------

class Api:
    def __init__(self, base: str, cle: str):
        self.base, self.cle = base.rstrip("/"), cle

    def _req(self, methode: str, chemin: str, corps: Optional[dict] = None) -> tuple[int, dict]:
        req = urllib.request.Request(self.base + chemin, method=methode,
                                     data=json.dumps(corps).encode() if corps is not None else None,
                                     headers={"Authorization": f"Bearer {self.cle}", "Content-Type": "application/json",
                                              "User-Agent": "Tapikard-Encodeur/1.0"})
        try:
            with urllib.request.urlopen(req, timeout=15) as r:
                return r.status, json.loads(r.read() or b"{}")
        except urllib.error.HTTPError as e:
            try:
                return e.code, json.loads(e.read() or b"{}")
            except ValueError:
                return e.code, {}

    def carte(self, card_id: str) -> tuple[int, dict]:
        return self._req("GET", "/api/encodage?cardId=" + urllib.parse.quote(card_id))

    def enregistrer(self, card_id: str, uid: str, compteur: int) -> tuple[int, dict]:
        return self._req("POST", "/api/encodage", {"cardId": card_id, "uid": uid, "counter": compteur})

    def scan_reconnu(self, url_carte: str) -> Optional[bool]:
        """Rejoue l'URL lue sur la carte auprès du site : True si le serveur reconnaît la signature."""
        class SansRedirection(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *a, **k):
                return None
        try:
            urllib.request.build_opener(SansRedirection).open(url_carte, timeout=15)
        except urllib.error.HTTPError as e:
            loc = e.headers.get("Location", "")
            return "/q/" in loc if e.code in (301, 302, 303, 307) else None
        except Exception:
            return None
        return None


def journaliser(card_id: str, uid: str, compteur: int, statut: str) -> None:
    f = ICI / "encodage-journal.csv"
    nouveau = not f.exists()
    with f.open("a", newline="", encoding="utf-8") as h:
        w = csv.writer(h, delimiter=";")
        if nouveau:
            w.writerow(["date", "card_id", "uid", "compteur", "statut"])
        w.writerow([datetime.now().isoformat(timespec="seconds"), card_id, uid, compteur, statut])


# --- Lecteur PC/SC (ACR122U) -----------------------------------------------------------

def lecteurs():
    try:
        from smartcard.System import readers
    except ImportError:
        raise SystemExit("✗ pyscard n'est pas installé : python3 -m pip install -r requirements.txt")
    return readers()


def choisir_lecteur(nom: Optional[str]):
    rs = lecteurs()
    if not rs:
        raise SystemExit("✗ Aucun lecteur détecté. Branchez l'ACR122U (voir « Dépannage » dans le guide).")
    for r in rs:
        if (nom and nom.lower() in str(r).lower()) or (not nom and ("ACR122" in str(r) or "PICC" in str(r))):
            return r
    return rs[0]


def attendre_carte(lecteur):
    from smartcard.Exceptions import CardConnectionException, NoCardException
    while True:
        conn = lecteur.createConnection()
        try:
            conn.connect()
            return conn
        except (NoCardException, CardConnectionException):
            time.sleep(0.25)


def attendre_retrait(lecteur):
    from smartcard.Exceptions import CardConnectionException, NoCardException
    while True:
        conn = lecteur.createConnection()
        try:
            conn.connect()
            conn.disconnect()
            time.sleep(0.25)
        except (NoCardException, CardConnectionException):
            return


def transport(conn) -> n.Transmit:
    def t(apdu: bytes) -> bytes:
        data, sw1, sw2 = conn.transmit(list(apdu))
        return bytes(data) + bytes([sw1, sw2])
    return t


def uid_pcsc(t: n.Transmit) -> Optional[bytes]:
    data, sw = n.split(t(bytes.fromhex("FFCA000000")))     # GET DATA (UID) — commande du lecteur PC/SC
    return data if sw == 0x9000 and data else None


def probleme_uid(uid: Optional[bytes]) -> Optional[str]:
    """Une NTAG 424 DNA a un UID de 7 octets commençant par 04 (fabricant NXP)."""
    if uid is None or (len(uid) == 7 and uid[0] == 0x04):
        return None
    if len(uid) == 4:
        return (f"carte non compatible : UID de 4 octets ({uid.hex().upper()}). Ce n'est pas une NTAG 424 DNA, "
                "mais probablement une MIFARE Classic (souvent fournie avec les lecteurs ACR122U) : "
                "illisible par les iPhone et sans signature SUN. Utilisez des cartes NTAG 424 DNA.")
    return f"carte non compatible : UID {uid.hex().upper()} ({len(uid)} octets) — une NTAG 424 DNA a un UID de 7 octets commençant par 04."


# --- Interface ------------------------------------------------------------------------------

VERT, ROUGE, JAUNE, GRAS, FIN = "\033[32m", "\033[31m", "\033[33m", "\033[1m", "\033[0m"


def id_depuis_saisie(s: str) -> Optional[str]:
    s = s.strip()
    m = re.search(r"/q/([A-Za-z0-9]{6,20})", s) or re.fullmatch(r"([A-Za-z0-9]{6,20})", s)
    return m.group(1) if m else None


def encoder_une(card_id: str, lecteur, conf: dict, cles: Cles, api: Optional[Api]) -> bool:
    base = conf["TAPIKARD_URL"].rstrip("/")
    if api:
        code, rep = api.carte(card_id)
        if code == 401:
            raise SystemExit("✗ ENCODING_API_KEY refusée par le site")
        if code == 404:
            print(f"{ROUGE}✗ Carte {card_id} inconnue du site.{FIN}")
            return False
        if code != 200:
            print(f"{ROUGE}✗ Site injoignable ({code}). Utilisez --hors-ligne pour encoder quand même.{FIN}")
            return False
        statut = rep.get("card", {}).get("status")
        if statut != "provisioned":
            print(f"{JAUNE}! Carte {card_id} déjà au statut « {statut} » (UID {rep['card'].get('uid')}) : reprise possible avec la même puce.{FIN}")

    print(f"→ Posez la carte {GRAS}{card_id}{FIN} sur le lecteur…")
    conn = attendre_carte(lecteur)
    t = transport(conn)
    try:
        uid_lecteur = uid_pcsc(t)
        probleme = probleme_uid(uid_lecteur)
        if probleme:
            raise EncodageError(probleme)
        r = encoder_carte(t, base, cles, uid_lecteur=uid_lecteur, log=lambda m: print(f"   · {m}"))
    except (n.CardError, n.IntegrityError, EncodageError) as e:
        print(f"{ROUGE}✗ Échec : {e}{FIN}\n   Mettez cette carte de côté ou recommencez (l'opération peut être relancée sans risque).")
        journaliser(card_id, "", 0, f"échec : {e}")
        attendre_retrait(lecteur)
        return False
    uid = r.uid.hex().upper()
    statut = "hors ligne"
    if api:
        code, rep = api.enregistrer(card_id, uid, r.compteur)
        if code != 200:
            print(f"{ROUGE}✗ Carte encodée mais refusée par le site : {rep.get('error', code)}{FIN}")
            journaliser(card_id, uid, r.compteur, f"refus site : {rep.get('error', code)}")
            attendre_retrait(lecteur)
            return False
        reconnu = api.scan_reconnu(r.url)
        if reconnu is False:
            print(f"{ROUGE}✗ Le site ne reconnaît pas la signature : clés SUN différentes entre ce poste et le site !{FIN}")
            journaliser(card_id, uid, r.compteur, "clés différentes du site")
            raise SystemExit("Arrêt : corrigez SUN_K_META / SUN_MASTER_K1 avant de continuer.")
        statut = "enregistrée" + (" et scan validé par le site" if reconnu else "")
    journaliser(card_id, uid, r.compteur, statut)
    print(f"{VERT}✓ Carte {card_id} prête — UID {uid} — {statut}.{FIN} Retirez-la.")
    attendre_retrait(lecteur)
    return True


def mode_verifier(lecteur, conf: dict, cles: Cles):
    print("Mode vérification : posez une carte (Ctrl+C pour quitter).")
    while True:
        conn = attendre_carte(lecteur)
        t = transport(conn)
        try:
            probleme = probleme_uid(uid_pcsc(t))
            if probleme:
                raise EncodageError(probleme)
            url = lire_ndef(t)
            print(f"  URL : {url}")
            e, m = parametres_sun(url)
            uid, ctr = n.verify_sun(cles.k_meta, lambda u: n.diversify_key(cles.master_k1, u, 1), e, m)
            print(f"{VERT}  ✓ Signature valide — UID {uid.hex().upper()} — scan n°{ctr}{FIN}")
        except Exception as ex:
            print(f"{ROUGE}  ✗ {ex}{FIN}")
        attendre_retrait(lecteur)


def main():
    p = argparse.ArgumentParser(description="Poste d'encodage Tapikard (NTAG 424 DNA, lecteur ACR122U)")
    p.add_argument("--config", help="fichier de configuration (défaut : encodeur.env à côté du script)")
    p.add_argument("--lecteur", help="partie du nom du lecteur à utiliser")
    p.add_argument("--lot", help="CSV d'un lot téléchargé depuis le back-office : encode les cartes dans l'ordre")
    p.add_argument("--hors-ligne", action="store_true", help="ne pas contacter le site (journal local uniquement)")
    p.add_argument("--verifier", action="store_true", help="lire et vérifier des cartes sans les modifier")
    p.add_argument("--lecteurs", action="store_true", help="lister les lecteurs détectés")
    p.add_argument("--test", action="store_true", help="lancer les tests (sans lecteur)")
    a = p.parse_args()

    if a.test:
        import unittest
        sys.path.insert(0, str(ICI))
        res = unittest.TextTestRunner(verbosity=2).run(unittest.defaultTestLoader.loadTestsFromName("test_ntag424"))
        sys.exit(0 if res.wasSuccessful() else 1)
    if a.lecteurs:
        for r in lecteurs():
            print(f"  - {r}")
        return

    conf = charger_config(a.config)
    cles = cles_depuis(conf)
    if not conf.get("TAPIKARD_URL", "").startswith("https://"):
        raise SystemExit("✗ TAPIKARD_URL doit être l'adresse https de votre site (ex. https://nfc.nlapps.fr)")
    lecteur = choisir_lecteur(a.lecteur)
    print(f"Lecteur : {lecteur}\nSite    : {conf['TAPIKARD_URL']}\n")
    if a.verifier:
        return mode_verifier(lecteur, conf, cles)

    api = None
    if not a.hors_ligne:
        if not conf.get("ENCODING_API_KEY"):
            raise SystemExit("✗ ENCODING_API_KEY manquante (ou utilisez --hors-ligne)")
        api = Api(conf["TAPIKARD_URL"], conf["ENCODING_API_KEY"])

    ok = ko = 0
    try:
        if a.lot:
            with open(a.lot, encoding="utf-8-sig") as h:
                lignes = [l for l in csv.DictReader(h, delimiter=";") if l.get("statut", "provisioned") == "provisioned"]
            print(f"{len(lignes)} carte(s) à encoder dans le lot.\n")
            for i, l in enumerate(lignes, 1):
                print(f"[{i}/{len(lignes)}] {l.get('nom_imprime') or ''} ({l.get('modele')}, {l.get('matiere')})")
                if encoder_une(l["card_id"], lecteur, conf, cles, api):
                    ok += 1
                else:
                    ko += 1
        else:
            print("Scannez le QR code imprimé sur la carte (ou tapez son identifiant), puis Entrée. Ctrl+C pour quitter.")
            while True:
                card_id = id_depuis_saisie(input("\nCarte : "))
                if not card_id:
                    print("Identifiant non reconnu.")
                    continue
                if encoder_une(card_id, lecteur, conf, cles, api):
                    ok += 1
                else:
                    ko += 1
    except (KeyboardInterrupt, EOFError):
        print()
    print(f"\nTerminé : {ok} carte(s) encodée(s), {ko} échec(s). Journal : {ICI / 'encodage-journal.csv'}")


if __name__ == "__main__":
    main()
