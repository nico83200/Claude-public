<?php
$images = json_field($product['images']);
$action = $product['id'] ? url('admin/produits/' . $product['id']) : url('admin/produits/nouveau');
$blankRows = $variants ? 2 : 4;
?>
<p><a href="<?= e(url('admin/produits')) ?>">← Produits</a><?php if ($product['id'] && $product['active']): ?> · <a href="<?= e(url('produit/' . $product['slug'])) ?>" target="_blank">Voir en boutique ↗</a><?php endif; ?></p>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="grid-main">
    <div>
      <section class="card">
        <label>Nom <input name="name" required value="<?= e($product['name']) ?>"></label>
        <label>Accroche (courte) <input name="short_description" maxlength="300" value="<?= e($product['short_description']) ?>"></label>
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="12" data-rich-editor><?= e(rich_text($product['description'])) ?></textarea>
      </section>

      <section class="card">
        <h2>Images</h2>
        <?php if ($images): ?><div class="img-row"><?php foreach ($images as $img): ?><img src="<?= e(image_url($img)) ?>" alt="" width="90" height="90"><?php endforeach; ?></div><?php endif; ?>
        <label>Liste des images (une par ligne, la première est l'image principale — réordonnez ou supprimez des lignes)
          <textarea name="images" rows="4" class="mono"><?= e(implode("\n", $images)) ?></textarea></label>
        <label>Ajouter des images (JPG, PNG, WebP — 8 Mo max chacune) <input type="file" name="image_files[]" accept="image/*" multiple></label>
      </section>

      <section class="card">
        <h2>Variantes</h2>
        <p class="muted small">Chaque variante doit être reliée à Printful : <strong>ID variante synchronisée</strong> (produit créé dans Printful avec son visuel) ou <strong>ID variante catalogue</strong> + visuel d'impression ci-contre (obligatoire pour la personnalisation). Prix vide = prix de base. <a href="<?= e(url('admin/produits/catalogue-printful')) ?>" target="_blank">Trouver un ID variante catalogue ↗</a></p>
        <div class="table-scroll">
        <table class="table variants">
          <thead><tr><th>Couleur</th><th>Hex</th><th>Taille</th><th>Prix €</th><th title="Coût Printful hors taxes, pour le calcul automatique du prix">Coût €</th><th>SKU</th><th>ID catalogue</th><th>ID synchro</th><th>Image</th><th>Actif</th><th>Suppr.</th></tr></thead>
          <tbody>
          <?php $i = 0; foreach (array_merge($variants, array_fill(0, $blankRows, null)) as $v): $n = "variants[$i]"; $i++; ?>
            <tr>
              <td><input type="hidden" name="<?= $n ?>[id]" value="<?= (int) ($v['id'] ?? 0) ?>"><input name="<?= $n ?>[color]" value="<?= e($v['color'] ?? '') ?>" size="10"></td>
              <td><input name="<?= $n ?>[color_hex]" value="<?= e($v['color_hex'] ?? '') ?>" size="7" placeholder="#1f2a44"></td>
              <td><input name="<?= $n ?>[size]" value="<?= e($v['size'] ?? '') ?>" size="6"></td>
              <td><input name="<?= $n ?>[price]" value="<?= isset($v['price']) ? e(number_format($v['price'] / 100, 2, ',', '')) : '' ?>" size="6"></td>
              <td><input name="<?= $n ?>[cost]" value="<?= isset($v['cost']) ? e(number_format($v['cost'] / 100, 2, ',', '')) : '' ?>" size="5"></td>
              <td><input name="<?= $n ?>[sku]" value="<?= e($v['sku'] ?? '') ?>" size="10"></td>
              <td><input name="<?= $n ?>[printful_variant_id]" value="<?= e($v['printful_variant_id'] ?? '') ?>" size="7"></td>
              <td><input name="<?= $n ?>[printful_sync_variant_id]" value="<?= e($v['printful_sync_variant_id'] ?? '') ?>" size="10"></td>
              <td><input name="<?= $n ?>[image]" value="<?= e($v['image'] ?? '') ?>" size="10" placeholder="URL"></td>
              <td><input type="checkbox" name="<?= $n ?>[active]" value="1" <?= !$v || $v['active'] ? 'checked' : '' ?>></td>
              <td><?php if ($v): ?><input type="checkbox" name="<?= $n ?>[delete]" value="1"><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <p class="muted small">Pour ajouter plus de variantes, enregistrez : de nouvelles lignes vides apparaîtront.</p>
        <label class="checkbox"><input type="checkbox" name="apply_pricing" value="1"> À l'enregistrement, calculer les prix depuis la colonne « Coût € » : coût + TVA 20 %, puis + 50 % de marge, arrondi aux 10 centimes inférieurs (la variante la moins chère devient le prix de base).</label>
      </section>

      <section class="card">
        <h2>Personnalisation</h2>
        <label class="checkbox"><input type="checkbox" name="personalizable" value="1" <?= $product['personalizable'] ? 'checked' : '' ?>> Article personnalisable (collection « Mon cheval, ma fierté »)</label>
        <div class="form-grid">
          <label>Type de personnalisation
            <select name="perso[mode]"><?php foreach (App\Services\Personalizer::MODES as $k => $label): ?><option value="<?= $k ?>" <?= $perso['mode'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label>Délai de fabrication en plus (jours ouvrés)<input name="perso[extra_days]" type="number" min="0" max="30" value="<?= (int) $perso['extra_days'] ?>"></label>
        </div>
        <p class="muted small">« Portrait illustré » : la cliente envoie sa photo, la commande attend que vous déposiez le portrait final (PNG HD) dans la commande avant de partir chez Printful. Pensez à prévoir le délai de création ci-dessus.</p>
        <p class="muted small">« Portrait IA » : la cliente crée elle-même son portrait « Encre &amp; Poudre » sur la fiche produit, puis le fichier d'impression (dessin + nom + année) est composé automatiquement à la commande. Reliez les variantes à l'<strong>affiche encadrée</strong> Printful (ID variante catalogue, emplacement « default ») et choisissez le service d'IA dans <a href="<?= e(url('admin/integrations')) ?>#cles">Intégrations</a>.</p>
        <p class="muted small">« Illustration manga » (t-shirt) : la cliente envoie sa photo et celle de son cheval, choisit la vue de face ou de dos, et l'IA crée l'illustration (consignes dans <a href="<?= e(url('admin/illustration-manga')) ?>">Illustration manga</a>). À la commande, le fichier d'impression (illustration aux bords fondus, prénom de la cavalière et nom du cheval dessous, dans la couleur d'impression ci-dessous) est composé automatiquement et envoyé à Printful (emplacement « front » conseillé). L'aperçu sur la fiche utilise la zone « Aperçu » ci-dessous (posez une image de t-shirt vierge comme image d'aperçu).</p>
        <div class="form-grid four">
          <label class="checkbox"><input type="checkbox" name="perso[year_enabled]" value="1" <?= !empty($perso['year_enabled']) ? 'checked' : '' ?>> Portrait IA : année sous le nom</label>
          <label>Libellé de l'année <input name="perso[year_label]" value="<?= e($perso['year_label']) ?>"></label>
          <label>Fichier d'impression : largeur (px) <input name="perso[print_w]" type="number" min="1200" max="9000" value="<?= (int) $perso['print_w'] ?>"></label>
          <label>Hauteur (px) <input name="perso[print_h]" type="number" min="1600" max="12000" value="<?= (int) $perso['print_h'] ?>"></label>
        </div>
        <div class="form-grid">
          <label>Libellé du champ <input name="perso[label]" value="<?= e($perso['label']) ?>"></label>
          <label>Longueur max. <input name="perso[max_length]" type="number" min="1" max="40" value="<?= (int) $perso['max_length'] ?>"></label>
          <label>Police
            <select name="perso[font]"><option value="serif" <?= $perso['font'] === 'serif' ? 'selected' : '' ?>>Playfair Display (élégante)</option><option value="script" <?= $perso['font'] === 'script' ? 'selected' : '' ?>>Allura (manuscrite)</option><option value="sans" <?= $perso['font'] === 'sans' ? 'selected' : '' ?>>Montserrat (moderne)</option></select></label>
          <label>Couleur d'impression <input name="perso[color]" type="color" value="<?= e($perso['color']) ?>"></label>
          <label class="checkbox"><input type="checkbox" name="perso[uppercase]" value="1" <?= $perso['uppercase'] ? 'checked' : '' ?>> En majuscules</label>
          <label class="checkbox"><input type="checkbox" name="perso[required]" value="1" <?= !empty($perso['required']) ? 'checked' : '' ?>> Obligatoire</label>
        </div>
        <h3>Placement sur le fichier d'impression (valeurs de 0 à 1)</h3>
        <div class="form-grid four">
          <label>Centre X <input name="perso[x]" value="<?= e($perso['x']) ?>"></label>
          <label>Ligne de base Y <input name="perso[y]" value="<?= e($perso['y']) ?>"></label>
          <label>Hauteur police <input name="perso[size]" value="<?= e($perso['size']) ?>"></label>
          <label>Largeur max. <input name="perso[max_width]" value="<?= e($perso['max_width']) ?>"></label>
        </div>
        <h3>Zone de la photo du cheval (modes photo — valeurs de 0 à 1)</h3>
        <div class="form-grid four">
          <label>Forme <select name="perso[photo_shape]"><?php foreach (App\Services\Personalizer::SHAPES as $k => $label): ?><option value="<?= $k ?>" <?= $perso['photo_shape'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label>Gauche <input name="perso[photo_x]" value="<?= e($perso['photo_x']) ?>"></label>
          <label>Haut <input name="perso[photo_y]" value="<?= e($perso['photo_y']) ?>"></label>
          <label>Largeur <input name="perso[photo_w]" value="<?= e($perso['photo_w']) ?>"></label>
          <label>Hauteur <input name="perso[photo_h]" value="<?= e($perso['photo_h']) ?>"></label>
          <label>Aperçu : gauche <input name="perso[preview_photo_x]" value="<?= e($perso['preview_photo_x']) ?>"></label>
          <label>Aperçu : haut <input name="perso[preview_photo_y]" value="<?= e($perso['preview_photo_y']) ?>"></label>
          <label>Aperçu : largeur <input name="perso[preview_photo_w]" value="<?= e($perso['preview_photo_w']) ?>"></label>
          <label>Aperçu : hauteur <input name="perso[preview_photo_h]" value="<?= e($perso['preview_photo_h']) ?>"></label>
        </div>
        <label>Image vierge pour l'aperçu en direct (chemin ou URL, facultatif — sinon l'image principale)
          <input name="perso[preview_image]" value="<?= e($perso['preview_image']) ?>" placeholder="assets/img/products/mon-article-vierge.jpg"></label>
        <h3>Aperçu du texte sur la photo produit (valeurs de 0 à 1)</h3>
        <div class="form-grid four">
          <label>Centre X <input name="perso[preview_x]" value="<?= e($perso['preview_x']) ?>"></label>
          <label>Centre Y <input name="perso[preview_y]" value="<?= e($perso['preview_y']) ?>"></label>
          <label>Taille <input name="perso[preview_size]" value="<?= e($perso['preview_size']) ?>"></label>
          <label>Couleur aperçu <input name="perso[preview_color]" value="<?= e($perso['preview_color']) ?>" placeholder="(couleur d'impression)"></label>
        </div>
        <?php if ($product['id'] && ($product['design_file'] || $perso['mode'] === 'ai_portrait')): ?>
          <p><a class="btn" target="_blank" href="<?= e(url('admin/produits/' . $product['id'] . '/apercu-impression', ['texte' => 'Tornado'])) ?>">Tester le fichier d'impression ↗</a></p>
        <?php endif; ?>
      </section>
    </div>

    <div>
      <section class="card">
        <label class="checkbox"><input type="checkbox" name="active" value="1" <?= $product['active'] ? 'checked' : '' ?>> En ligne</label>
        <label class="checkbox"><input type="checkbox" name="featured" value="1" <?= $product['featured'] ? 'checked' : '' ?>> Mis en avant sur l'accueil</label>
        <label>Pour qui ? (filtres « Pour elle / Pour lui »)
          <select name="audience">
            <?php $aud = $product['audience'] ?? ($product['id'] ? 'tous' : ''); ?>
            <?php if (!$product['id']): ?><option value="">Deviner d'après le nom</option><?php endif; ?>
            <option value="tous" <?= $aud === 'tous' ? 'selected' : '' ?>>Pour tous</option>
            <option value="elle" <?= $aud === 'elle' ? 'selected' : '' ?>>Pour elle</option>
            <option value="lui" <?= $aud === 'lui' ? 'selected' : '' ?>>Pour lui</option>
          </select></label>
        <label>Catégorie principale <small class="muted">(fil d'Ariane, articles similaires)</small>
          <select name="category_id"><option value="">—</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $product['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select></label>
        <fieldset class="extra-cats">
          <legend>Aussi dans</legend>
          <?php foreach ($categories as $c): if ((int) $c['id'] === (int) $product['category_id']) continue; ?>
            <label class="checkbox"><input type="checkbox" name="extra_categories[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $productCategories ?? [], true) ? 'checked' : '' ?> style="width:auto"> <?= e($c['name']) ?><?= $c['active'] ? '' : ' <small class="muted">(masquée)</small>' ?></label>
          <?php endforeach; ?>
        </fieldset>
        <label>Prix de base TTC (€) <input name="price" required value="<?= e(number_format($product['price'] / 100, 2, ',', '')) ?>"></label>
        <label>Prix barré (€) <input name="compare_at_price" value="<?= $product['compare_at_price'] ? e(number_format($product['compare_at_price'] / 100, 2, ',', '')) : '' ?>"></label>
        <button class="btn btn-primary btn-block" type="submit">Enregistrer</button>
      </section>

      <section class="card">
        <h2>Fichier d'impression</h2>
        <?php if (!empty($library)): ?>
          <label>Choisir dans la bibliothèque Crinière Club
            <select name="library_design">
              <option value="">— Aucun (fichier téléversé ci-dessous) —</option>
              <?php foreach ($library as $key => $d): ?>
                <optgroup label="<?= e($d['title']) ?><?= $d['personalizable'] ? ' ✦ personnalisable' : '' ?>">
                  <?php foreach (App\Services\DesignLibrary::COLORS as $color => [$label]): $file = 'bibliotheque-' . $key . '-' . $color . '.png'; ?>
                    <option value="<?= e($key . '|' . $color) ?>" <?= $product['design_file'] === $file ? 'selected' : '' ?>><?= e($d['title'] . ' — ' . $label) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select></label>
          <p class="muted small">Un visuel personnalisable active la personnalisation et règle automatiquement la zone du nom (police, position, taille). Téléchargez le même fichier dans Printful pour créer le produit.</p>
        <?php endif; ?>
        <p class="muted small">PNG transparent haute définition (≥ 150 dpi à la taille d'impression, ex. 3600 × 4800 px pour un t-shirt). Stocké hors du site public.</p>
        <?php if ($product['design_file']): ?>
          <p class="small">Actuel : <code><?= e($product['design_file']) ?></code><?php if ($product['id']): ?> · <a href="<?= e(url('admin/produits/' . $product['id'] . '/fichier-impression')) ?>">Télécharger</a><?php endif; ?></p>
          <label class="checkbox"><input type="checkbox" name="remove_design" value="1"> Retirer</label>
        <?php endif; ?>
        <input type="file" name="design_file" accept="image/png">
        <label>Emplacement Printful
          <select name="print_placement">
            <?php foreach (['front' => 'Devant', 'back' => 'Dos', 'default' => 'Par défaut (mug, poster…)', 'embroidery_front' => 'Broderie devant (casquette)', 'embroidery_chest_left' => 'Broderie cœur', 'sleeve_left' => 'Manche gauche', 'label_outside' => 'Étiquette extérieure'] as $k => $label): ?>
              <option value="<?= $k ?>" <?= $product['print_placement'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select></label>
      </section>

      <section class="card">
        <h2>Référencement</h2>
        <label>Adresse (slug) <input name="slug" value="<?= e($product['slug']) ?>" placeholder="généré automatiquement"></label>
        <label>Titre SEO <input name="seo_title" value="<?= e($product['seo_title']) ?>"></label>
        <label>Méta-description <textarea name="seo_description" rows="3" maxlength="300"><?= e($product['seo_description']) ?></textarea></label>
        <?php if ($product['printful_sync_product_id']): ?><p class="muted small">Produit Printful #<?= (int) $product['printful_sync_product_id'] ?></p><?php endif; ?>
      </section>
    </div>
  </div>
</form>

<?php if ($product['id']): ?>
<form method="post" action="<?= e(url('admin/produits/' . $product['id'] . '/supprimer')) ?>" data-confirm="Supprimer définitivement ce produit ?">
  <?= csrf_field() ?><button class="btn btn-danger">Supprimer le produit</button>
</form>
<?php endif; ?>
