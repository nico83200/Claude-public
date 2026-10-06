<?php
declare(strict_types=1);

/** Données indispensables : catégories et paramètres par défaut. */
function install_base_data(): void
{
    if ((int)val('SELECT COUNT(*) FROM categories') === 0) {
        $cats = [
            ['Consommables médicaux', 'syringe', '#6366f1'],
            ['Hygiène & désinfection', 'droplet', '#06b6d4'],
            ['Protection (EPI)', 'shield', '#10b981'],
            ['Pansements & soins', 'bandage', '#ec4899'],
            ['Kinésithérapie', 'activity', '#f97316'],
            ['Petit matériel médical', 'stethoscope', '#8b5cf6'],
            ['Fournitures de bureau', 'pen', '#3b82f6'],
            ['Impression & papeterie', 'printer2', '#0ea5e9'],
            ['Entretien & ménage', 'droplet', '#14b8a6'],
            ['Accueil & salle de pause', 'coffee', '#f59e0b'],
        ];
        foreach ($cats as $i => [$n, $ic, $c]) {
            insert('categories', ['name' => $n, 'icon' => $ic, 'color' => $c, 'position' => $i + 1]);
        }
    }
    set_setting('db_version', trim((string)@file_get_contents(dirname(__DIR__) . '/VERSION')) ?: '1.0.0');
    foreach (['show_prices' => '1', 'allow_registration' => '1', 'ai_enabled' => '1', 'ai_model' => 'claude-opus-5-5', 'ai_max_products' => '1500'] as $k => $v) {
        if (val('SELECT COUNT(*) FROM settings WHERE skey = ?', [$k]) == 0) {
            insert('settings', ['skey' => $k, 'svalue' => $v]);
        }
    }
}

/** Jeu de démonstration réaliste. */
function install_demo_data(int $adminId): void
{
    $now = now();
    $cat = array_column(all('SELECT id, name FROM categories'), 'id', 'name');

    $centers = [
        ['Centre de santé Les Tilleuls', 'TIL', '12 rue des Tilleuls', '83000 Toulon', '04 94 00 00 01', '#6366f1', 'Livraisons du lundi au vendredi, 8h30-12h. Sonner à l\'accueil.'],
        ['Centre médical du Port', 'PORT', '3 quai Cronstadt', '83000 Toulon', '04 94 00 00 02', '#ec4899', 'Accès livraison par la rue arrière.'],
        ['Maison de santé La Garde', 'LG', '45 avenue de la République', '83130 La Garde', '04 94 00 00 03', '#10b981', null],
    ];
    $centerIds = [];
    foreach ($centers as [$n, $code, $addr, $city, $tel, $col, $info]) {
        $centerIds[] = insert('centers', ['name' => $n, 'code' => $code, 'address' => $addr, 'city' => $city, 'phone' => $tel, 'color' => $col, 'delivery_info' => $info, 'active' => 1, 'created_at' => $now]);
    }

    $suppliers = [
        'medi' => ['MédiDistrib', 'Sophie Martin', 'commandes@medidistrib.example', '04 91 11 22 33', 150, 12.9, 300, '48 h', 'Site web', '#6366f1', 1],
        'hyg'  => ['Hygiène Pro Sud', 'Karim Benali', 'contact@hygienepro.example', '04 91 22 33 44', 100, 9.5, 200, '3 jours ouvrés', 'E-mail', '#06b6d4', 1],
        'bur'  => ['Bureau Express', 'Service client', 'pro@bureauexpress.example', '09 70 00 00 00', 50, 6.9, 89, '24 h', 'Site web', '#3b82f6', 1],
        'kine' => ['KinéSport Équipement', 'Julien Roux', 'julien@kinesport.example', '04 94 55 66 77', 200, 15, 400, '5 jours', 'Commercial', '#f97316', 0],
        'cafe' => ['Pause Café Services', 'Agence Var', 'var@pausecafe.example', '04 94 88 99 00', 80, 0, 0, '1 semaine', 'Téléphone', '#f59e0b', 1],
    ];
    $sup = [];
    foreach ($suppliers as $k => [$n, $contact, $mail, $tel, $min, $ship, $franco, $delay, $method, $col, $all]) {
        $sup[$k] = insert('suppliers', [
            'name' => $n, 'contact_name' => $contact, 'email' => $mail, 'phone' => $tel, 'customer_number' => 'CL-' . random_int(10000, 99999),
            'min_order_amount' => $min, 'shipping_fee' => $ship, 'free_shipping_from' => $franco, 'delivery_delay' => $delay,
            'order_method' => $method, 'all_centers' => $all, 'color' => $col, 'active' => 1, 'created_at' => $now,
        ]);
    }
    // Le fournisseur kiné ne dessert que 2 centres
    insert('supplier_centers', ['supplier_id' => $sup['kine'], 'center_id' => $centerIds[0]]);
    insert('supplier_centers', ['supplier_id' => $sup['kine'], 'center_id' => $centerIds[2]]);

    $products = [
        // fournisseur, catégorie, réf, nom, description, conditionnement, catalogue, négocié, mots-clés
        ['medi', 'Protection (EPI)', 'GN-S', 'Gants d\'examen nitrile non poudrés — taille S', 'Gants ambidextres, sans latex, texturés au bout des doigts. Conformes EN 455.', 'Boîte de 100', 8.90, 6.40, 'gant nitrile examen sans latex petit'],
        ['medi', 'Protection (EPI)', 'GN-M', 'Gants d\'examen nitrile non poudrés — taille M', 'Gants ambidextres, sans latex, texturés au bout des doigts. Conformes EN 455.', 'Boîte de 100', 8.90, 6.40, 'gant nitrile examen sans latex moyen'],
        ['medi', 'Protection (EPI)', 'GN-L', 'Gants d\'examen nitrile non poudrés — taille L', 'Gants ambidextres, sans latex, texturés au bout des doigts. Conformes EN 455.', 'Boîte de 100', 8.90, 6.40, 'gant nitrile examen sans latex grand'],
        ['medi', 'Protection (EPI)', 'MSQ-IIR', 'Masques chirurgicaux type IIR', 'Masques 3 plis à élastiques, haute filtration bactérienne.', 'Boîte de 50', 7.50, 5.20, 'masque chirurgical protection'],
        ['medi', 'Protection (EPI)', 'FFP2', 'Masques FFP2 sans valve', 'Protection respiratoire, pliables.', 'Boîte de 20', 14.00, 10.90, 'masque ffp2 respiratoire'],
        ['medi', 'Protection (EPI)', 'SURB-01', 'Surblouses de protection à usage unique', 'Polypropylène, poignets élastiqués.', 'Paquet de 10', 9.80, null, 'blouse tablier protection'],
        ['medi', 'Consommables médicaux', 'SER-2.5', 'Seringues 2,5 ml Luer', 'Seringues stériles 3 pièces, embout Luer.', 'Boîte de 100', 11.50, 8.90, 'seringue injection'],
        ['medi', 'Consommables médicaux', 'AIG-25G', 'Aiguilles hypodermiques 25G orange', 'Aiguilles stériles 0,5 x 16 mm.', 'Boîte de 100', 6.20, 4.80, 'aiguille injection sous-cutanée vaccin'],
        ['medi', 'Consommables médicaux', 'DASRI-1L', 'Collecteur d\'aiguilles DASRI 1 L', 'Collecteur pour objets piquants coupants tranchants, fermeture définitive.', 'Unité', 3.40, 2.60, 'dasri collecteur piquant aiguille déchet'],
        ['medi', 'Consommables médicaux', 'ABL-100', 'Abaisse-langues en bois non stériles', 'Abaisse-langues adulte, bouleau naturel.', 'Boîte de 100', 3.90, 2.95, 'abaisse langue spatule examen gorge'],
        ['medi', 'Consommables médicaux', 'DRAP-50', 'Drap d\'examen gaufré 2 plis 50 x 38 cm', 'Rouleau prédécoupé pour table d\'examen, ouate de cellulose.', 'Carton de 9 rouleaux', 32.00, 24.50, 'drap examen papier rouleau divan table protège'],
        ['medi', 'Petit matériel médical', 'THERM-IR', 'Thermomètre infrarouge sans contact', 'Mesure frontale en 1 seconde, mémoire 32 mesures.', 'Unité', 39.00, 29.90, 'thermomètre température fièvre frontal'],
        ['medi', 'Petit matériel médical', 'TENS-BRAS', 'Tensiomètre électronique au bras', 'Brassard universel 22-42 cm, validé cliniquement.', 'Unité', 59.00, 45.00, 'tensiomètre tension artérielle brassard'],
        ['medi', 'Petit matériel médical', 'OTO-EMB', 'Embouts d\'otoscope à usage unique 4 mm', 'Spéculums auriculaires compatibles otoscopes standards.', 'Sachet de 250', 12.00, null, 'otoscope spéculum oreille embout'],
        ['medi', 'Consommables médicaux', 'BAND-URI', 'Bandelettes urinaires 10 paramètres', 'Lecture visuelle en 60 secondes.', 'Flacon de 100', 24.00, 19.50, 'bandelette urine analyse test'],
        ['medi', 'Consommables médicaux', 'LANC-28', 'Lancettes de sécurité 28G', 'Autopiqueurs à usage unique pour glycémie capillaire.', 'Boîte de 200', 16.50, 13.20, 'lancette glycémie diabète piqûre'],
        ['medi', 'Pansements & soins', 'COMP-7.5', 'Compresses de gaze stériles 7,5 x 7,5 cm', '8 plis, 17 fils, sachets de 2.', 'Boîte de 50 sachets', 6.90, 4.90, 'compresse gaze stérile'],
        ['medi', 'Pansements & soins', 'PANS-ASS', 'Pansements adhésifs assortis', 'Tailles assorties, hypoallergéniques.', 'Boîte de 100', 4.50, 3.40, 'pansement sparadrap strip plaie'],
        ['medi', 'Pansements & soins', 'SPAR-2.5', 'Sparadrap microporeux 2,5 cm x 9,1 m', 'Ruban adhésif chirurgical hypoallergénique.', 'Boîte de 12 rouleaux', 13.00, 9.90, 'sparadrap adhésif ruban'],
        ['medi', 'Pansements & soins', 'BISEP-250', 'Antiseptique Biseptine 250 ml', 'Solution pour application cutanée.', 'Flacon', 5.90, 4.60, 'biseptine antiseptique désinfection plaie peau'],
        ['medi', 'Pansements & soins', 'COTON-500', 'Coton hydrophile 500 g', 'Rouleau de coton 100 % pur.', 'Rouleau', 7.20, null, 'coton ouate'],
        ['hyg', 'Hygiène & désinfection', 'SHA-500', 'Gel hydroalcoolique 500 ml avec pompe', 'Friction hydroalcoolique des mains, EN 1500, EN 14476.', 'Flacon pompe', 6.80, 4.95, 'gel hydroalcoolique sha mains désinfection'],
        ['hyg', 'Hygiène & désinfection', 'SHA-5L', 'Gel hydroalcoolique 5 L (recharge)', 'Bidon de recharge pour flacons pompe.', 'Bidon', 34.00, 26.00, 'gel hydroalcoolique recharge bidon'],
        ['hyg', 'Hygiène & désinfection', 'LING-DES', 'Lingettes désinfectantes surfaces & dispositifs médicaux', 'Sans alcool, bactéricides, virucides. Idéales pour tables d\'examen et matériel.', 'Boîte de 100', 9.50, 7.20, 'lingette désinfectante surface table examen nettoyer'],
        ['hyg', 'Hygiène & désinfection', 'SPRAY-DES', 'Spray détergent désinfectant surfaces 750 ml', 'Prêt à l\'emploi, virucide en 5 minutes.', 'Flacon', 7.90, 5.90, 'spray désinfectant détergent surface nettoyant'],
        ['hyg', 'Hygiène & désinfection', 'SAV-1L', 'Savon doux pour les mains 1 L', 'Usage fréquent, pH neutre.', 'Flacon', 4.20, 3.10, 'savon lavage mains'],
        ['hyg', 'Entretien & ménage', 'ESS-ZZ', 'Essuie-mains papier pliés en Z', 'Ouate blanche 2 plis.', 'Carton de 3 750', 38.00, 29.00, 'essuie mains papier distributeur'],
        ['hyg', 'Entretien & ménage', 'SAC-50L', 'Sacs poubelle 50 L noirs', 'Haute densité, résistants.', 'Rouleau de 25', 4.90, 3.60, 'sac poubelle déchet'],
        ['hyg', 'Entretien & ménage', 'PH-PRO', 'Papier toilette professionnel', 'Rouleaux 2 plis.', 'Colis de 48', 22.00, 17.50, 'papier toilette wc'],
        ['bur', 'Impression & papeterie', 'A4-80', 'Ramettes papier A4 80 g', 'Papier blanc multifonction.', 'Carton de 5 ramettes', 24.00, 19.90, 'ramette papier a4 impression imprimante feuille'],
        ['bur', 'Impression & papeterie', 'TON-HP26', 'Toner HP 26A noir', 'Compatible LaserJet Pro M402/M426.', 'Unité', 89.00, 72.00, 'toner cartouche imprimante hp encre'],
        ['bur', 'Impression & papeterie', 'ENV-C5', 'Enveloppes C5 blanches à fenêtre', 'Auto-adhésives, 162 x 229 mm.', 'Boîte de 500', 29.00, 23.50, 'enveloppe courrier pli'],
        ['bur', 'Fournitures de bureau', 'BIC-BL', 'Stylos bille bleus', 'Pointe moyenne.', 'Boîte de 50', 12.00, 9.00, 'stylo bic bille écrire'],
        ['bur', 'Fournitures de bureau', 'SURL-4', 'Surligneurs assortis', '4 couleurs fluo.', 'Pochette de 4', 4.50, null, 'surligneur feutre fluo'],
        ['bur', 'Fournitures de bureau', 'AGR-26', 'Agrafes 26/6', 'Pour agrafeuses standards.', 'Boîte de 5 000', 2.20, 1.70, 'agrafe agrafeuse'],
        ['bur', 'Fournitures de bureau', 'POST-IT', 'Notes repositionnables 76 x 76 mm', 'Jaune.', 'Lot de 12 blocs', 9.90, 7.90, 'post-it notes bloc'],
        ['kine', 'Kinésithérapie', 'TAPE-5', 'Bande de taping neuromusculaire 5 cm x 5 m', 'Coton élastique, adhésif acrylique hypoallergénique.', 'Rouleau', 8.50, 6.90, 'kinesio tape taping strapping bande'],
        ['kine', 'Kinésithérapie', 'STRAP-3.8', 'Bande de strapping rigide 3,8 cm', 'Contention non élastique, blanc.', 'Boîte de 32 rouleaux', 48.00, 39.00, 'strapping contention bande rigide'],
        ['kine', 'Kinésithérapie', 'HUILE-1L', 'Huile de massage neutre 1 L', 'Sans parfum, hypoallergénique.', 'Flacon', 18.00, 14.50, 'huile massage'],
        ['kine', 'Kinésithérapie', 'GEL-ECHO', 'Gel de contact échographie / ultrasons 5 L', 'Gel conducteur incolore.', 'Bidon', 15.00, 11.90, 'gel échographie ultrason contact'],
        ['kine', 'Kinésithérapie', 'ELEC-50', 'Électrodes auto-adhésives 50 x 50 mm', 'Pour électrostimulation / TENS.', 'Sachet de 4', 7.80, 6.20, 'électrode électrostimulation tens'],
        ['kine', 'Kinésithérapie', 'COLD-PK', 'Poche de froid instantané', 'Usage unique, activation par pression.', 'Carton de 24', 26.00, 21.00, 'froid glace cryothérapie poche'],
        ['cafe', 'Accueil & salle de pause', 'CAF-CAPS', 'Capsules de café compatibles', 'Intensité 8.', 'Boîte de 100', 28.00, 23.00, 'café capsule dosette'],
        ['cafe', 'Accueil & salle de pause', 'GOB-20', 'Gobelets carton 20 cl', 'Compostables.', 'Paquet de 50', 3.90, null, 'gobelet café'],
        ['cafe', 'Accueil & salle de pause', 'SUC-1KG', 'Sucre en morceaux 1 kg', '', 'Boîte', 2.10, null, 'sucre café thé'],
    ];
    $pid = [];
    $ean = function (int $i): string {
        $base = '376' . str_pad((string)(4521000 + $i * 37), 9, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($base) as $k => $d) {
            $sum += (int)$d * ($k % 2 ? 3 : 1);
        }
        return $base . ((10 - $sum % 10) % 10);
    };
    foreach ($products as $i => [$s, $c, $ref, $n, $d, $u, $cp, $np, $kw]) {
        $pid[$ref] = insert('products', [
            'barcode' => $ean($i),
            'supplier_id' => $sup[$s], 'category_id' => $cat[$c] ?? null, 'reference' => $ref, 'name' => $n,
            'description' => $d ?: null, 'unit' => $u, 'catalog_price' => $cp, 'negotiated_price' => $np,
            'vat_rate' => in_array($c, ['Accueil & salle de pause'], true) ? 5.5 : 20, 'keywords' => $kw,
            'min_qty' => 1, 'active' => 1, 'created_at' => $now,
        ]);
    }

    // Utilisateurs (mot de passe : demo1234)
    $hash = password_hash('demo1234', PASSWORD_DEFAULT);
    $users = [
        ['claire.secretaire@demo.fr', 'Claire', 'Dubois', 'Secrétaire', [0, 1]],
        ['dr.morel@demo.fr', 'Antoine', 'Morel', 'Médecin', [0]],
        ['lea.kine@demo.fr', 'Léa', 'Fabre', 'Kinésithérapeute', [0, 2]],
        ['nadia.idec@demo.fr', 'Nadia', 'Haddad', 'Infirmier(e)', [1]],
        ['marc.accueil@demo.fr', 'Marc', 'Lopez', 'Secrétaire', [2]],
    ];
    $uid = [];
    foreach ($users as [$email, $f, $l, $job, $cs]) {
        $id = insert('users', ['email' => $email, 'password_hash' => $hash, 'first_name' => $f, 'last_name' => $l, 'job' => $job, 'role' => 'user', 'status' => 'active', 'created_at' => $now]);
        foreach ($cs as $ci) {
            insert('user_centers', ['user_id' => $id, 'center_id' => $centerIds[$ci]]);
        }
        $uid[] = $id;
    }
    insert('users', ['email' => 'nouveau@demo.fr', 'password_hash' => $hash, 'first_name' => 'Paul', 'last_name' => 'Girard', 'job' => 'Podologue', 'role' => 'user', 'status' => 'pending', 'requested_centers' => (string)$centerIds[1], 'created_at' => $now]);

    $price = fn(string $ref) => (float)(one('SELECT COALESCE(negotiated_price, catalog_price) p FROM products WHERE id = ?', [$pid[$ref]])['p']);
    $supOf = fn(string $ref) => (int)val('SELECT supplier_id FROM products WHERE id = ?', [$pid[$ref]]);

    $mkRequest = function (int $userId, int $centerId, array $lines, string $date, bool $urgent = false, ?string $comment = null) use ($pid, $price, $supOf) {
        $rid = insert('requests', ['center_id' => $centerId, 'user_id' => $userId, 'comment' => $comment, 'urgent' => $urgent ? 1 : 0, 'created_at' => $date]);
        $ids = [];
        foreach ($lines as $ref => $qty) {
            $ids[] = insert('request_lines', [
                'request_id' => $rid, 'center_id' => $centerId, 'product_id' => $pid[$ref], 'supplier_id' => $supOf($ref),
                'qty' => $qty, 'unit_price' => $price($ref), 'status' => 'pending', 'created_at' => $date,
            ]);
        }
        return $ids;
    };

    // Historique : bons reçus sur les 10 derniers mois (alimente les graphiques)
    $_SESSION['uid'] = $adminId;
    $seq = 0;
    for ($m = 10; $m >= 1; $m--) {
        foreach ([0, 1, 2] as $ci) {
            if (($m + $ci) % 3 === 0) {
                continue;
            }
            $date = date('Y-m-d H:i:s', strtotime("-$m months +" . (3 + $ci * 4) . ' days'));
            $set = [
                ['GN-M' => 4 + $ci, 'COMP-7.5' => 3, 'DRAP-50' => 2, 'SER-2.5' => 2],
                ['SHA-500' => 6, 'LING-DES' => 4, 'ESS-ZZ' => 1, 'SAV-1L' => 3],
                ['A4-80' => 2, 'BIC-BL' => 1, 'ENV-C5' => 1],
            ][($m + $ci) % 3];
            $ids = $mkRequest($uid[$ci === 1 ? 3 : ($ci === 2 ? 4 : 0)], $centerIds[$ci], $set, $date);
            $line = one('SELECT supplier_id FROM request_lines WHERE id = ?', [$ids[0]]);
            $poId = po_create($centerIds[$ci], (int)$line['supplier_id'], $ids);
            $ordered = date('Y-m-d H:i:s', strtotime($date . ' +2 days'));
            update('purchase_orders', ['status' => 'commande', 'ordered_at' => $ordered, 'ordered_by' => $adminId, 'created_at' => $date, 'supplier_reference' => 'WEB-' . (40000 + ++$seq)], 'id = ?', [$poId]);
            q('UPDATE purchase_order_lines SET qty_received = qty, received_at = ?, received_by = ? WHERE purchase_order_id = ?', [date('Y-m-d H:i:s', strtotime($ordered . ' +3 days')), $uid[0], $poId]);
            po_refresh_reception_status($poId);
            update('purchase_orders', ['received_at' => date('Y-m-d H:i:s', strtotime($ordered . ' +3 days'))], 'id = ?', [$poId]);
            q('UPDATE po_history SET created_at = ? WHERE purchase_order_id = ?', [$date, $poId]);
        }
    }

    // Commande en cours de livraison (centre 1), partiellement reçue (centre 2)
    $ids = $mkRequest($uid[1], $centerIds[0], ['GN-S' => 3, 'GN-L' => 2, 'MSQ-IIR' => 4, 'THERM-IR' => 1, 'ABL-100' => 2], date('Y-m-d H:i:s', strtotime('-6 days')));
    $po1 = po_create($centerIds[0], $sup['medi'], $ids);
    update('purchase_orders', ['status' => 'commande', 'ordered_at' => date('Y-m-d H:i:s', strtotime('-4 days')), 'ordered_by' => $adminId, 'supplier_reference' => 'WEB-48211', 'expected_date' => date('Y-m-d', strtotime('+1 day'))], 'id = ?', [$po1]);
    po_log($po1, 'Commandé', 'Réf. fournisseur : WEB-48211');

    $ids = $mkRequest($uid[3], $centerIds[1], ['SHA-5L' => 2, 'SPRAY-DES' => 6, 'SAC-50L' => 10, 'PH-PRO' => 2], date('Y-m-d H:i:s', strtotime('-9 days')));
    $po2 = po_create($centerIds[1], $sup['hyg'], $ids);
    update('purchase_orders', ['status' => 'commande', 'ordered_at' => date('Y-m-d H:i:s', strtotime('-7 days')), 'ordered_by' => $adminId], 'id = ?', [$po2]);
    $first = one('SELECT id, qty FROM purchase_order_lines WHERE purchase_order_id = ? ORDER BY id LIMIT 1', [$po2]);
    update('purchase_order_lines', ['qty_received' => $first['qty'], 'received_at' => now(), 'received_by' => $uid[3]], 'id = ?', [$first['id']]);
    po_refresh_reception_status($po2);
    po_log($po2, 'Réception', 'Livraison partielle');

    // Bon prêt à être commandé
    $ids = $mkRequest($uid[0], $centerIds[0], ['A4-80' => 4, 'TON-HP26' => 2, 'POST-IT' => 1], date('Y-m-d H:i:s', strtotime('-3 days')));
    po_create($centerIds[0], $sup['bur'], $ids, 'Toner pour l\'imprimante de l\'accueil');

    // Demandes en attente (à traiter par l'administrateur)
    $mkRequest($uid[2], $centerIds[0], ['TAPE-5' => 6, 'HUILE-1L' => 2, 'ELEC-50' => 5], date('Y-m-d H:i:s', strtotime('-2 days')), false, 'Stock de bandes presque épuisé');
    $mkRequest($uid[2], $centerIds[2], ['GEL-ECHO' => 1, 'COLD-PK' => 1, 'STRAP-3.8' => 1], date('Y-m-d H:i:s', strtotime('-1 day')));
    $mkRequest($uid[1], $centerIds[0], ['GN-M' => 5, 'COMP-7.5' => 4, 'BISEP-250' => 3, 'DASRI-1L' => 4], date('Y-m-d H:i:s', strtotime('-1 day')), true, 'Besoin avant la vacation de jeudi');
    $mkRequest($uid[3], $centerIds[1], ['GN-M' => 3, 'LANC-28' => 2, 'BAND-URI' => 1, 'SER-2.5' => 1], date('Y-m-d H:i:s', strtotime('-5 hours')));
    $mkRequest($uid[4], $centerIds[2], ['LING-DES' => 3, 'SHA-500' => 4], date('Y-m-d H:i:s', strtotime('-3 hours')));
    $mkRequest($uid[0], $centerIds[1], ['CAF-CAPS' => 2, 'GOB-20' => 4, 'SUC-1KG' => 2], date('Y-m-d H:i:s', strtotime('-2 hours')));

    // Favoris
    foreach (['GN-M', 'COMP-7.5', 'DRAP-50', 'SHA-500'] as $ref) {
        insert('favorites', ['user_id' => $uid[0], 'product_id' => $pid[$ref]]);
    }

    // Dates limites
    $dl = [
        ['Commande mensuelle consommables médicaux', '+2 days 12:00', $sup['medi'], null, 'Regroupez vos besoins en gants, compresses et seringues.'],
        ['Commande hygiène & entretien', '+6 days 17:00', $sup['hyg'], null, null],
        ['Fournitures de bureau', '+13 days 12:00', $sup['bur'], null, null],
        ['Commande matériel kiné', '+20 days 12:00', $sup['kine'], $centerIds[0], 'Pensez au taping pour le trimestre.'],
    ];
    foreach ($dl as [$t, $when, $s, $c, $d]) {
        insert('deadlines', ['title' => $t, 'deadline_at' => date('Y-m-d H:i:s', strtotime($when)), 'supplier_id' => $s, 'center_id' => $c, 'description' => $d, 'created_by' => $adminId, 'created_at' => $now]);
    }
    // Stocks (centre Les Tilleuls) et budgets de l'année
    $_SESSION['uid'] = $uid[0];
    foreach (['GN-S' => [6, 3], 'GN-M' => [2, 4], 'GN-L' => [5, 3], 'COMP-7.5' => [8, 4], 'SHA-500' => [3, 4], 'LING-DES' => [5, 2],
              'DRAP-50' => [1, 2], 'A4-80' => [4, 2], 'SER-2.5' => [3, 1], 'BISEP-250' => [0, 2], 'ABL-100' => [6, 2], 'MSQ-IIR' => [7, 3]] as $ref => [$q, $alert]) {
        stock_set_alert($centerIds[0], $pid[$ref], $alert);
        stock_count($centerIds[0], $pid[$ref], $q, 'Inventaire initial');
    }
    stock_move($centerIds[0], $pid['GN-S'], -2, 'sortie', 'Salle de soins');
    stock_move($centerIds[0], $pid['COMP-7.5'], -3, 'sortie', 'Cabinet 2');
    foreach ([[$centerIds[0], 1500], [$centerIds[1], 900], [$centerIds[2], 600]] as [$cid, $amount]) {
        insert('budgets', ['center_id' => $cid, 'year' => (int)date('Y'), 'amount' => $amount, 'alert_pct' => 80, 'alert_sent' => 0]);
    }
    q('DELETE FROM notifications');
    insert('settings', ['skey' => 'company_name', 'svalue' => 'Groupe de Centres de Santé (démo)']);
    insert('settings', ['skey' => 'company_address', 'svalue' => "Service achats\n1 place de la Liberté\n83000 Toulon"]);
    unset($_SESSION['uid']);
}
