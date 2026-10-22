"""Vérification côté serveur d'un scan NTAG 424 DNA (SUN / Secure Dynamic Messaging).

URL écrite dans la puce :
    https://tapikard.com/c?e=<PICCData chiffré, 32 hex>&m=<CMAC tronqué, 16 hex>

Références : NXP AN12196 (SDM), NXP AN10922 (diversification de clés).
"""
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.primitives.cmac import CMAC


def aes_cmac(key: bytes, data: bytes) -> bytes:
    c = CMAC(algorithms.AES(key))
    c.update(data)
    return c.finalize()


def decrypt_picc_data(k_meta: bytes, e_hex: str) -> tuple[bytes, int]:
    """Déchiffre PICCData (AES-128-CBC, IV nul) -> (UID 7 octets, compteur de lecture)."""
    dec = Cipher(algorithms.AES(k_meta), modes.CBC(bytes(16))).decryptor()
    data = dec.update(bytes.fromhex(e_hex)) + dec.finalize()
    tag = data[0]
    if tag & 0x80 == 0 or tag & 0x40 == 0 or tag & 0x0F != 7:
        raise ValueError("PICCData invalide (mauvaise clé meta ?)")
    uid = data[1:8]
    counter = int.from_bytes(data[8:11], "little")
    return uid, counter


def diversify_key(master: bytes, uid: bytes, key_no: int) -> bytes:
    """Clé propre à chaque carte : AN10922 AES-128, CMAC(master, 0x01 || UID || n° de clé || constante).

    Le padding 0x80 00.. + sous-clé K2 de l'AN10922 est exactement celui d'AES-CMAC standard.
    """
    return aes_cmac(master, bytes([0x01]) + uid + bytes([key_no]) + b"Tapikard")


def expected_cmac(k_file: bytes, uid: bytes, counter: int, mac_input: bytes = b"") -> bytes:
    """CMAC SDM tronqué (8 octets) attendu pour ce UID et ce compteur."""
    sv2 = bytes.fromhex("3CC300010080") + uid + counter.to_bytes(3, "little")
    k_ses = aes_cmac(k_file, sv2)
    return aes_cmac(k_ses, mac_input)[1::2]


def verify_scan(k_meta: bytes, k_file_for_uid, e_hex: str, m_hex: str, last_counter: int):
    """Retourne (uid_hex, counter) si le scan est authentique et non rejoué, sinon lève ValueError.

    k_file_for_uid : fonction uid -> clé SDMFileRead de cette carte (ex. diversify_key(master, uid, 1)).
    last_counter   : dernier compteur accepté pour cette carte (stocké en base).
    """
    uid, counter = decrypt_picc_data(k_meta, e_hex)
    if expected_cmac(k_file_for_uid(uid), uid, counter) != bytes.fromhex(m_hex):
        raise ValueError("CMAC invalide : carte clonée ou URL falsifiée")
    if counter <= last_counter:
        raise ValueError("Compteur déjà vu : URL rejouée")
    return uid.hex().upper(), counter


if __name__ == "__main__":
    # Vecteur de test NXP AN12196 (clés d'usine = 16 octets à zéro).
    zero = bytes(16)
    uid, ctr = verify_scan(zero, lambda _uid: zero,
                           "EF963FF7828658A599F3041510671E88", "94EED9EE65337086", last_counter=0)
    assert (uid, ctr) == ("04DE5F1EACC040", 61), (uid, ctr)
    print("OK", uid, ctr)
