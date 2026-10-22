"""
Protocole NTAG 424 DNA (NXP) : authentification EV2, messagerie sécurisée et commandes
nécessaires à la configuration SUN d'une carte Tapikard.

Références : NXP NT4H2421Gx (datasheet), AN12196 (exemples chiffrés), AN10922 (diversification).
Chaque primitive est vérifiée octet par octet contre les exemples de l'AN12196 (voir test_ntag424.py).
Ce module ne dépend d'aucun lecteur : il échange des APDU via une fonction `transmit(bytes) -> bytes`.
"""
from __future__ import annotations

import os
import zlib
from dataclasses import dataclass, field
from typing import Callable, Optional

from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.primitives.cmac import CMAC

Transmit = Callable[[bytes], bytes]

NDEF_AID = bytes.fromhex("D2760000850101")
NDEF_FILE_ID = 0xE104
NDEF_FILE_NO = 0x02
FACTORY_KEY = bytes(16)

STATUS = {
    0x9000: "OK", 0x9100: "OK", 0x91AF: "trame suivante attendue",
    0x911C: "commande non autorisée", 0x911E: "erreur d'intégrité (CRC/padding)", 0x9140: "clé inexistante",
    0x917E: "erreur de longueur", 0x919D: "permission refusée", 0x919E: "paramètre invalide",
    0x91AE: "erreur d'authentification (mauvaise clé)", 0x91BE: "hors limites", 0x91CA: "commande interrompue",
    0x91EE: "erreur mémoire", 0x6700: "longueur incorrecte", 0x6982: "accès refusé (sécurité)",
    0x6A82: "fichier introuvable", 0x6A86: "paramètres P1/P2 incorrects",
}


class CardError(Exception):
    def __init__(self, sw: int, what: str):
        self.sw = sw
        super().__init__(f"{what} : SW {sw:04X} — {STATUS.get(sw, 'erreur inconnue')}")


class IntegrityError(Exception):
    """La réponse de la carte n'est pas authentique (MAC ou preuve d'authentification invalide)."""


# --- Primitives AES ------------------------------------------------------------

def _aes(key: bytes, mode) -> Cipher:
    return Cipher(algorithms.AES(key), mode)


def aes_ecb_encrypt(key: bytes, block: bytes) -> bytes:
    e = _aes(key, modes.ECB()).encryptor()
    return e.update(block) + e.finalize()


def aes_cbc_encrypt(key: bytes, iv: bytes, data: bytes) -> bytes:
    e = _aes(key, modes.CBC(iv)).encryptor()
    return e.update(data) + e.finalize()


def aes_cbc_decrypt(key: bytes, iv: bytes, data: bytes) -> bytes:
    d = _aes(key, modes.CBC(iv)).decryptor()
    return d.update(data) + d.finalize()


def cmac(key: bytes, data: bytes) -> bytes:
    c = CMAC(algorithms.AES(key))
    c.update(data)
    return c.finalize()


def mac_truncate(full: bytes) -> bytes:
    """MAC tronqué NTAG 424 : les 8 octets d'index impair du CMAC."""
    return full[1::2]


def pad(data: bytes) -> bytes:
    """Bourrage ISO/IEC 9797-1 méthode 2 (toujours au moins un octet)."""
    data = data + b"\x80"
    return data + bytes(-len(data) % 16)


def unpad(data: bytes) -> bytes:
    i = data.rstrip(b"\x00")
    if not i or i[-1] != 0x80:
        raise IntegrityError("bourrage invalide")
    return i[:-1]


def crc32nk(data: bytes) -> bytes:
    """CRC32 de la nouvelle clé (JAMCRC : CRC32 standard sans inversion finale), petit-boutiste."""
    return ((zlib.crc32(data) ^ 0xFFFFFFFF) & 0xFFFFFFFF).to_bytes(4, "little")


def diversify_key(master: bytes, uid: bytes, key_no: int) -> bytes:
    """Clé propre à chaque carte (AN10922) — identique à lib/sun.ts et tools/sun_verify.py."""
    return cmac(master, bytes([0x01]) + uid + bytes([key_no]) + b"Tapikard")


def rotl(b: bytes) -> bytes:
    return b[1:] + b[:1]


# --- APDU ------------------------------------------------------------------------

def native(ins: int, data: bytes = b"") -> bytes:
    """Commande native NTAG 424 encapsulée ISO 7816 (CLA 90)."""
    return bytes([0x90, ins, 0x00, 0x00]) + (bytes([len(data)]) + data if data else b"") + b"\x00"


def split(resp: bytes) -> tuple[bytes, int]:
    if len(resp) < 2:
        raise CardError(0, "réponse vide")
    return resp[:-2], int.from_bytes(resp[-2:], "big")


def iso_select_ndef_app() -> bytes:
    return bytes.fromhex("00A4040C07") + NDEF_AID + b"\x00"


def iso_select_file(file_id: int) -> bytes:
    return bytes.fromhex("00A4000C02") + file_id.to_bytes(2, "big") + b"\x00"


def iso_update_binary(offset: int, data: bytes) -> bytes:
    # Sans Le : la forme avec Le de l'AN12196 est refusée (6700) par le silicium.
    return bytes([0x00, 0xD6, offset >> 8, offset & 0xFF, len(data)]) + data


def iso_read_binary(offset: int, length: int) -> bytes:
    return bytes([0x00, 0xB0, offset >> 8, offset & 0xFF, length & 0xFF])


# --- Authentification EV2 et messagerie sécurisée --------------------------------

def session_vectors(rnd_a: bytes, rnd_b: bytes) -> tuple[bytes, bytes]:
    tail = rnd_a[0:2] + bytes(x ^ y for x, y in zip(rnd_a[2:8], rnd_b[0:6])) + rnd_b[6:16] + rnd_a[8:16]
    return bytes.fromhex("A55A00010080") + tail, bytes.fromhex("5AA500010080") + tail


@dataclass
class Session:
    transmit: Transmit
    ti: bytes
    k_enc: bytes
    k_mac: bytes
    cmd_ctr: int = 0
    key_no: int = 0

    def _ctr(self) -> bytes:
        return self.cmd_ctr.to_bytes(2, "little")

    def _iv(self, label: bytes) -> bytes:
        return aes_ecb_encrypt(self.k_enc, label + self.ti + self._ctr() + bytes(8))

    def mac_of(self, prefix: int, header: bytes) -> bytes:
        return mac_truncate(cmac(self.k_mac, bytes([prefix]) + self._ctr() + self.ti + header))

    def command_apdu(self, ins: int, header: bytes, data: bytes, full: bool) -> bytes:
        body = aes_cbc_encrypt(self.k_enc, self._iv(b"\xA5\x5A"), pad(data)) if (full and data) else data
        mac = self.mac_of(ins, header + body)
        return native(ins, header + body + mac)

    def send(self, ins: int, header: bytes = b"", data: bytes = b"", full: bool = True, what: str = "") -> bytes:
        """Commande en CommMode.Full (full=True) ou CommMode.MAC ; renvoie les données de réponse déchiffrées."""
        apdu = self.command_apdu(ins, header, data, full)
        rdata, sw = split(self.transmit(apdu))
        if sw not in (0x9100, 0x9000):
            raise CardError(sw, what or f"commande {ins:02X}")
        self.cmd_ctr += 1
        if not rdata:  # ex. ChangeKey de la clé de session : pas de MAC, la session est close
            return b""
        body, mac = rdata[:-8], rdata[-8:]
        if mac != self.mac_of(0x00, body):
            raise IntegrityError(f"MAC de réponse invalide ({what or hex(ins)})")
        if full and body:
            return unpad(aes_cbc_decrypt(self.k_enc, self._iv(b"\x5A\xA5"), body))
        return body


def authenticate_ev2_first(transmit: Transmit, key_no: int, key: bytes, rnd_a: Optional[bytes] = None) -> Session:
    data, sw = split(transmit(native(0x71, bytes([key_no, 0x00]))))
    if sw != 0x91AF or len(data) != 16:
        raise CardError(sw, f"authentification clé {key_no} (étape 1)")
    rnd_b = aes_cbc_decrypt(key, bytes(16), data)
    rnd_a = rnd_a or os.urandom(16)
    data, sw = split(transmit(native(0xAF, aes_cbc_encrypt(key, bytes(16), rnd_a + rotl(rnd_b)))))
    if sw != 0x9100 or len(data) != 32:
        raise CardError(sw, f"authentification clé {key_no} (étape 2)")
    plain = aes_cbc_decrypt(key, bytes(16), data)
    if plain[4:20] != rotl(rnd_a):
        raise IntegrityError("la carte n'a pas prouvé qu'elle connaît la clé")
    sv1, sv2 = session_vectors(rnd_a, rnd_b)
    return Session(transmit, ti=plain[0:4], k_enc=cmac(key, sv1), k_mac=cmac(key, sv2), key_no=key_no)


# --- Commandes ----------------------------------------------------------------------

def get_card_uid(s: Session) -> bytes:
    return s.send(0x51, what="GetCardUID")[:7]


def change_key(s: Session, key_no: int, new_key: bytes, version: int, old_key: Optional[bytes] = None) -> None:
    """ChangeKey : pour la clé de session, nouvelle clé en clair ; sinon XOR avec l'ancienne + CRC32."""
    if key_no == s.key_no:
        data = new_key + bytes([version])
    else:
        if old_key is None:
            raise ValueError("l'ancienne clé est nécessaire pour changer une autre clé que celle de session")
        data = bytes(a ^ b for a, b in zip(new_key, old_key)) + bytes([version]) + crc32nk(new_key)
    s.send(0xC4, bytes([key_no]), data, what=f"ChangeKey {key_no}")


@dataclass
class SdmConfig:
    """Configuration SUN du fichier NDEF (fichier 02)."""
    picc_offset: int
    mac_input_offset: int
    mac_offset: int
    meta_read_key: int = 2       # chiffre e (UID + compteur)
    file_read_key: int = 1       # signe m (CMAC)
    ctr_ret_key: int = 0xF       # compteur non lisible en clair
    read: int = 0xE              # lecture libre (téléphones)
    write: int = 0x0             # écriture réservée à la clé 0
    read_write: int = 0x0
    change: int = 0x0
    sdm_options: int = 0xC1      # miroir UID + miroir compteur + encodage ASCII

    def encode(self) -> bytes:
        o3 = lambda v: v.to_bytes(3, "little")
        return (
            bytes([0x40])                                                  # SDM activé, CommMode.Plain
            + bytes([(self.read_write << 4) | self.change, (self.read << 4) | self.write])
            + bytes([self.sdm_options])
            + bytes([0xF0 | self.ctr_ret_key, (self.meta_read_key << 4) | self.file_read_key])
            + o3(self.picc_offset) + o3(self.mac_input_offset) + o3(self.mac_offset)
        )


def change_file_settings(s: Session, cfg: SdmConfig) -> None:
    s.send(0x5F, bytes([NDEF_FILE_NO]), cfg.encode(), what="ChangeFileSettings")


def get_file_settings(s: Session) -> bytes:
    return s.send(0xF5, bytes([NDEF_FILE_NO]), full=False, what="GetFileSettings")


# --- NDEF ------------------------------------------------------------------------

URI_PREFIXES = {"https://": 0x04, "http://": 0x03}


@dataclass
class NdefTemplate:
    file: bytes          # contenu du fichier NDEF (NLEN + message)
    url: str
    sdm: SdmConfig


def sun_template(base_url: str, e_param: str = "e", m_param: str = "m") -> NdefTemplate:
    """Fichier NDEF contenant <base_url>/c?e=000…&m=000… et les positions du miroir SUN."""
    url = f"{base_url.rstrip('/')}/c?{e_param}={'0' * 32}&{m_param}={'0' * 16}"
    prefix = next((p for p in URI_PREFIXES if url.startswith(p)), None)
    if prefix is None:
        raise ValueError("l'URL doit commencer par https:// (ou http:// en test)")
    rest = url[len(prefix):].encode()
    payload = bytes([URI_PREFIXES[prefix]]) + rest
    if len(payload) > 255:
        raise ValueError("URL trop longue")
    record = bytes([0xD1, 0x01, len(payload), 0x55]) + payload        # MB|ME|SR, TNF bien connu, type « U »
    file = len(record).to_bytes(2, "big") + record
    head = 2 + 4 + 1                                                   # NLEN + en-tête + code préfixe
    e_at = head + rest.index(f"{e_param}=".encode()) + len(e_param) + 1
    m_at = head + rest.index(f"&{m_param}=".encode()) + len(m_param) + 2
    return NdefTemplate(file, url, SdmConfig(picc_offset=e_at, mac_input_offset=m_at, mac_offset=m_at))


def parse_uri_file(file: bytes) -> str:
    nlen = int.from_bytes(file[:2], "big")
    rec = file[2:2 + nlen]
    if len(rec) < 5 or rec[3] != 0x55:
        raise ValueError("le fichier NDEF ne contient pas d'URL")
    payload = rec[4:4 + rec[2]]
    prefix = {v: k for k, v in URI_PREFIXES.items()}.get(payload[0], "")
    return prefix + payload[1:].decode()


# --- Vérification SUN (comme le serveur) -----------------------------------------

def verify_sun(k_meta: bytes, k_file_for_uid: Callable[[bytes], bytes], e_hex: str, m_hex: str) -> tuple[bytes, int]:
    picc = aes_cbc_decrypt(k_meta, bytes(16), bytes.fromhex(e_hex))
    if picc[0] & 0xC0 != 0xC0 or picc[0] & 0x0F != 7:
        raise IntegrityError("PICCData indéchiffrable (clé SUN_K_META différente ?)")
    uid, ctr = picc[1:8], int.from_bytes(picc[8:11], "little")
    sv2 = bytes.fromhex("3CC300010080") + uid + ctr.to_bytes(3, "little")
    expected = mac_truncate(cmac(cmac(k_file_for_uid(uid), sv2), b""))
    if expected != bytes.fromhex(m_hex):
        raise IntegrityError("signature m invalide (clé SUN_MASTER_K1 différente ?)")
    return uid, ctr
