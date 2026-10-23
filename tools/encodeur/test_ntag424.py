"""Tests du protocole contre les exemples chiffrés de NXP AN12196, puis encodage complet sur carte émulée.

Lancer : python3 -m unittest test_ntag424.py   (depuis tools/encodeur)
"""
import unittest

import ntag424 as n

H = bytes.fromhex
ZERO = bytes(16)


class Scripted:
    """Transport rejouant des échanges APDU attendus (AN12196)."""

    def __init__(self, pairs):
        self.pairs = list(pairs)

    def __call__(self, apdu):
        expected, resp = self.pairs.pop(0)
        assert apdu == expected, f"\nAPDU envoyé  : {apdu.hex().upper()}\nAPDU attendu : {expected.hex().upper()}"
        return resp


AN_RND_A = H("13C5DB8A5930439FC3DEF9A4C675360F")
AN_RND_B = H("B9E2FC789B64BF237CCCAA20EC7E6E48")
AN_AUTH = [
    (H("9071000002000000"), H("A04C124213C186F22399D33AC2A3021591AF")),
    (H("90AF00002035C3E05A752E0144BAC0DE51C1F22C56B34408A23D8AEA266CAB947EA8E0118D00"),
     H("3FA64DB5446D1F34CD6EA311167F5E4985B89690C04A05F17FA7AB2F081206639100")),
]


class AN12196(unittest.TestCase):
    def test_vecteurs_de_session(self):  # Table 14
        sv1, sv2 = n.session_vectors(AN_RND_A, AN_RND_B)
        self.assertEqual(sv1, H("A55A0001008013C56268A548D8FBBF237CCCAA20EC7E6E48C3DEF9A4C675360F"))
        self.assertEqual(sv2, H("5AA50001008013C56268A548D8FBBF237CCCAA20EC7E6E48C3DEF9A4C675360F"))

    def test_authenticate_ev2_first(self):  # Table 14
        s = n.authenticate_ev2_first(Scripted(AN_AUTH), 0, ZERO, rnd_a=AN_RND_A)
        self.assertEqual(s.ti, H("9D00C4DF"))
        self.assertEqual(s.k_enc, H("1309C877509E5A215007FF0ED19CA564"))
        self.assertEqual(s.k_mac, H("4C6626F5E72EA694202139295C7A7FC7"))

    def test_change_file_settings(self):  # Table 18 (CmdCtr = 1)
        cfg = n.SdmConfig(picc_offset=0x20, mac_input_offset=0x43, mac_offset=0x43, ctr_ret_key=1)
        self.assertEqual(cfg.encode(), H("4000E0C1F121200000430000430000"))
        t = Scripted(AN_AUTH + [(H("905F0000190261B6D97903566E84C3AE5274467E89EAD799B7C1A0EF7A0400"), H("57BFF87B1241E93D9100"))])
        s = n.authenticate_ev2_first(t, 0, ZERO, rnd_a=AN_RND_A)
        s.cmd_ctr = 1
        n.change_file_settings(s, cfg)
        self.assertEqual(s.cmd_ctr, 2)

    def test_mac_de_reponse_falsifie(self):
        t = Scripted(AN_AUTH + [(H("905F0000190261B6D97903566E84C3AE5274467E89EAD799B7C1A0EF7A0400"), H("57BFF87B1241E93E9100"))])
        s = n.authenticate_ev2_first(t, 0, ZERO, rnd_a=AN_RND_A)
        s.cmd_ctr = 1
        with self.assertRaises(n.IntegrityError):
            n.change_file_settings(s, n.SdmConfig(0x20, 0x43, 0x43, ctr_ret_key=1))

    def test_get_file_settings_mac(self):  # Table 7
        t = Scripted([(H("90F5000009026597A457C8CD442C00"),
                       H("0040EEEE000100D1FE001F00004400004400002000006A00002A474282E7A479869100"))])
        s = n.Session(t, H("7A21085E"), ZERO, H("8248134A386E86EB7FAF54A52E536CB6"))
        self.assertEqual(n.get_file_settings(s), H("0040EEEE000100D1FE001F00004400004400002000006A0000"))

    def test_crc32nk(self):
        self.assertEqual(n.crc32nk(H("F3847D627727ED3BC9C4CC050489B966")), H("789DFADC"))

    def test_change_key_autre_cle(self):  # Table 25
        apdu = H("90C4000029022CF362B7BF4311FF3BE1DAA295E8C68DE09050560D19B9E16C2393AE9CD1FAC75D0CE20BCD1D06E600")
        s = n.Session(Scripted([(apdu, H("203BB55D1089D5879100"))]), H("7614281A"),
                      H("4CF3CB41A22583A61E89B158D252FC53"), H("5529860B2FC5FB6154B7F28361D30BF9"), cmd_ctr=2)
        n.change_key(s, 2, H("F3847D627727ED3BC9C4CC050489B966"), 0x01, old_key=ZERO)
        self.assertEqual(s.cmd_ctr, 3)

    def test_change_key_cle_de_session(self):  # Table 26
        apdu = H("90C400002900C0EB4DEEFEDDF0B513A03A95A75491818580503190D4D05053FF75668A01D6FDA6610234BDED643200")
        s = n.Session(Scripted([(apdu, H("9100"))]), H("7614281A"),
                      H("4CF3CB41A22583A61E89B158D252FC53"), H("5529860B2FC5FB6154B7F28361D30BF9"), cmd_ctr=3)
        n.change_key(s, 0, H("5004BF991F408672B1EF00F08F9E8647"), 0x01)

    def test_get_card_uid(self):  # Table 28
        s = n.Session(Scripted([(H("90510000088E2C155ADDA99BE300"),
                                 H("70756055688505B52A5E26E59E329CD6595F672298EA41B79100"))]),
                      H("DF055522"), H("2B4D963C014DC36F24F69A50A394F875"), H("379D32130CE61705DD5FD8C36B95D764"))
        self.assertEqual(n.get_card_uid(s), H("04958CAA5C5E80"))

    def test_gabarit_ndef(self):  # §5.7 : mêmes positions que l'exemple NXP
        t = n.sun_template("https://choose.url.com/ntag424", "e", "c")
        url = "https://choose.url.com/ntag424/c?e=" + "0" * 32 + "&c=" + "0" * 16
        self.assertEqual(t.url, url)
        # L'exemple NXP n'a pas « /c » : on décale de 2 caractères.
        self.assertEqual((t.sdm.picc_offset, t.sdm.mac_offset), (0x20 + 2, 0x43 + 2))
        self.assertEqual(n.parse_uri_file(t.file), url)

    def test_verification_sun(self):  # vecteur SDM de l'AN12196
        uid, ctr = n.verify_sun(ZERO, lambda _u: ZERO, "EF963FF7828658A599F3041510671E88", "94EED9EE65337086")
        self.assertEqual((uid.hex().upper(), ctr), ("04DE5F1EACC040", 61))


class DetectionCarte(unittest.TestCase):
    def test_uid_4_octets_refuse(self):
        from encodeur import probleme_uid
        self.assertIn("MIFARE Classic", probleme_uid(H("8A30CB4A")))
        self.assertIsNone(probleme_uid(H("04DE5F1EACC040")))
        self.assertIsNone(probleme_uid(None))
        self.assertIn("7 octets", probleme_uid(H("1122334455667788")))


class EncodageSurCarteEmulee(unittest.TestCase):
    def test_encodage_complet_puis_reparation(self):
        from carte_emulee import CarteEmulee
        from encodeur import Cles, encoder_carte
        cles = Cles(k_meta=H("11" * 16), master_k1=H("22" * 16), master_k0=H("33" * 16))
        carte = CarteEmulee(uid=H("04A1B2C3D4E5F6"))
        r = encoder_carte(carte.transmit, "https://nfc.exemple.fr", cles, uid_lecteur=carte.uid)
        self.assertEqual(r.uid, carte.uid)
        self.assertFalse(r.reparation)
        # La carte n'accepte plus les clés d'usine et signe avec les clés diversifiées.
        with self.assertRaises(n.CardError):
            n.authenticate_ev2_first(carte.transmit, 0, ZERO)
        url = carte.lire_comme_un_telephone()
        e, m = url.split("e=")[1].split("&m=")
        uid, ctr = n.verify_sun(cles.k_meta, lambda u: n.diversify_key(cles.master_k1, u, 1), e, m)
        self.assertEqual(uid, carte.uid)
        self.assertGreater(ctr, r.compteur)
        # Ré-encodage d'une carte déjà configurée (mode réparation) : idempotent.
        r2 = encoder_carte(carte.transmit, "https://nfc.exemple.fr", cles, uid_lecteur=carte.uid)
        self.assertTrue(r2.reparation)

    def test_reprise_apres_interruption(self):
        """Carte dont l'encodage a été interrompu après le changement des clés 1 à 4 (clé 0 encore d'usine)."""
        from carte_emulee import CarteEmulee
        from encodeur import Cles, encoder_carte
        cles = Cles(k_meta=H("44" * 16), master_k1=H("55" * 16), master_k0=H("66" * 16))
        carte = CarteEmulee(uid=H("04010203040506"))
        carte.cles[1] = n.diversify_key(cles.master_k1, carte.uid, 1)
        carte.cles[2] = cles.k_meta
        r = encoder_carte(carte.transmit, "https://nfc.exemple.fr", cles, uid_lecteur=carte.uid)
        self.assertEqual(r.uid, carte.uid)

    def test_carte_d_un_tiers_refusee(self):
        from carte_emulee import CarteEmulee
        from encodeur import Cles, EncodageError, encoder_carte
        cles = Cles(k_meta=H("44" * 16), master_k1=H("55" * 16), master_k0=H("66" * 16))
        carte = CarteEmulee(uid=H("04010203040507"))
        carte.cles[0] = H("99" * 16)
        with self.assertRaises(EncodageError):
            encoder_carte(carte.transmit, "https://nfc.exemple.fr", cles, uid_lecteur=carte.uid)


if __name__ == "__main__":
    unittest.main()
