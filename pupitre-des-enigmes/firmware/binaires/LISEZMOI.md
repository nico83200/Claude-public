# Le programme du pupitre, déjà compilé

Pour programmer l'ESP32 **sans rien installer**, avec un simple navigateur.

| Fichier | Quand s'en servir |
|---|---|
| `pupitre-complet.bin` | **La première fois**, par le câble USB. Il contient tout (démarrage, table des partitions, programme). |
| `pupitre-mise-a-jour.bin` | **Ensuite**, par le Wi-Fi du pupitre : http://192.168.4.1, onglet « Mise à jour ». |

## La première fois, par l'USB

1. Sur un ordinateur, ouvrez **Google Chrome** ou **Microsoft Edge** (Firefox et Safari ne
   savent pas parler au port USB) à l'adresse **https://espressif.github.io/esptool-js/**,
   l'outil officiel d'Espressif, le fabricant de l'ESP32.
2. Branchez la carte avec un câble **USB-A vers USB-C** qui transporte les données.
3. Cliquez sur **Connect**, choisissez le port de la carte (« USB-SERIAL CH340 »…).
   Si rien n'apparaît : le pilote CH340 manque (macOS, anciens Windows : pilote « CH34x »
   du fabricant WCH), ou le câble ne fait que charger.
4. Dans **Flash Address**, laissez **0x0** ; choisissez le fichier `pupitre-complet.bin`.
5. Cliquez sur **Program** et attendez la fin (une minute environ). Si la connexion échoue,
   maintenez le bouton **BOOT** de la carte pendant « Connecting… ».
6. Débranchez, rebranchez : le logo s'affiche.

## Les fois suivantes, par le Wi-Fi

Connectez le téléphone ou l'ordinateur au Wi-Fi « Pupitre-des-Enigmes » (mot de passe
`enigmes123`), ouvrez http://192.168.4.1, onglet **Mise à jour**, et envoyez
`pupitre-mise-a-jour.bin`. Le pupitre redémarre tout seul.

Ces fichiers correspondent au code de ce dépôt au moment où ils ont été produits. Après
une modification du code, il faut les refaire (PlatformIO, voir `firmware/README.md`).
