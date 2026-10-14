 <?php
// dashboard_admin.php
require_once '../includes/config.php';

// Vérifier si l'utilisateur est connecté ET administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Détection AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Déterminer quelle section afficher
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
$page_title = "Dashboard Admin - Mycksoft";
$message = '';
$message_type = '';

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Affiche les statistiques du dashboard
 */
function displayStats($stats) {
    if (!$stats) return;
    $stats_items = [
        //  ['icon' => 'fa-box', 'class' => 'product', 'value' => isset($stats['total_produits']) ? $stats['total_produits'] : 0, 'label' => 'Produits actifs'],
        //  ['icon' => 'fa-users', 'class' => 'client', 'value' => isset($stats['total_clients']) ? $stats['total_clients'] : 0, 'label' => 'Clients inscrits'],
        // ['icon' => 'fa-shopping-cart', 'class' => 'order', 'value' => isset($stats['total_commandes']) ? $stats['total_commandes'] : 0, 'label' => 'Commandes totales'],
        ['icon' => 'fa-money-bill-wave', 'class' => 'revenue', 'value' => number_format(isset($stats['chiffre_affaires']) ? $stats['chiffre_affaires'] : 0, 0, ',', ' ') . ' FCFA', 'label' => 'Chiffre d\'affaires'],
        // ['icon' => ' fa-tags', 'class' => 'category', 'value' => isset($stats['total_category']) ? $stats['total_categories'] : 0, 'label' => 'categories actifs'],
    ];
    
    foreach ($stats_items as $item): ?>
        <div class="stat-card">
            <div class="stat-icon <?php echo $item['class']; ?>"><i class="fas <?php echo $item['icon']; ?>"></i></div>
            <div class="stat-info"><h3><?php echo $item['value']; ?></h3><p><?php echo $item['label']; ?></p></div>
        </div>
    <?php endforeach;
}

/**
 * Génère un badge de statut
 */
function statusBadge($status, $type = 'default') {
    $badges = [
        'en attente' => 'badge-warning',
        'confirmee' => 'badge-info',
        'expediee' => 'badge-info',
        'livree' => 'badge-success',
        'annulee' => 'badge-danger',
        'paye' => 'badge-success',
        'echec' => 'badge-danger',
        'en preparation' => 'badge-warning',
        'nouveau' => 'badge-warning',
        'repondu' => 'badge-success',
        '1' => 'badge-success',
        '0' => 'badge-danger',
        'Actif' => 'badge-success',
        'Inactif' => 'badge-danger',
        'Approuvé' => 'badge-success',
        'En attente' => 'badge-warning'
    ];
    
    $class = isset($badges[$status]) ? $badges[$status] : 'badge-info';
    $label = is_numeric($status) ? ($status ? 'Actif' : 'Inactif') : ucfirst($status);
    
    return "<span class='badge $class'>" . htmlspecialchars($label) . "</span>";
}

/**
 * Génère les étoiles de notation
 */
function displayStars($note) {
    $output = '';
    for ($i = 1; $i <= 5; $i++) {
        $starClass = $i <= $note ? 'fas fa-star' : 'fas fa-star-o';
        $output .= "<i class='$starClass'></i>";
    }
    return $output . "<span style='color: #666; margin-left: 5px;'>$note/5</span>";
}

/**
 * Affiche l'image du produit ou un placeholder
 */
function productImage($pdo, $produit_id, $nom_produit = '') {
    if (!$pdo) return;
    $img_stmt = $pdo->prepare("SELECT url_image FROM image_produit WHERE id_produit = ? ORDER BY est_principale DESC LIMIT 1");
    $img_stmt->execute([$produit_id]);
    $image = $img_stmt->fetch();
    
    if ($image && file_exists('../' . $image['url_image'])): ?>
        <img src="../<?php echo $image['url_image']; ?>" alt="<?php echo htmlspecialchars($nom_produit); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
    <?php else: ?>
        <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #f0f0f0; border-radius: 8px;">
            <i class="fas fa-box" style="font-size: 24px; color: #ccc;"></i>
        </div>
    <?php endif;
}

/**
 * Génère un en-tête de section avec titre et boutons
 */
function sectionHeader($title, $icon, $subtitle = '', $buttons = []) { ?>
    <div class="section-header">
        <div>
            <h2 class="section-title"><i class="fas <?php echo $icon; ?>"></i> <?php echo $title; ?></h2>
            <?php if ($subtitle): ?>
                <div class="section-subtitle"><?php echo $subtitle; ?></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($buttons)): ?>
            <div>
                <?php foreach ($buttons as $btn): ?>
                    <?php if ($btn['type'] === 'button'): ?>
                        <button class="btn btn-<?php echo $btn['style']; ?>" onclick="<?php echo isset($btn['action']) ? $btn['action'] : ''; ?>">
                            <i class="fas <?php echo $btn['icon']; ?>"></i> <?php echo $btn['text']; ?>
                        </button>
                    <?php else: ?>
                        <!-- <a href="#" class="btn btn-<?php echo $btn['style']; ?>" onclick="<?php echo isset($btn['onclick']) ? $btn['onclick'] : 'return false;'; ?>"> -->
                            <i class="fas <?php echo $btn['icon']; ?>"></i> <?php echo $btn['text']; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php }

/**
 * Génère les actions sur les cartes
 */
function cardActions($actions) { ?>
    <div class="card-actions">
        <?php foreach ($actions as $action): ?>
            <?php if ($action['type'] === 'link'): ?>
                <a href="#" 
                   class="card-btn card-btn-<?php echo $action['style']; ?>"
                   onclick="<?php echo isset($action['onclick']) ? $action['onclick'] : 'return false;'; ?>"
                   title="<?php echo $action['title']; ?>">
                    <i class="fas <?php echo $action['icon']; ?>"></i>
                    <?php echo isset($action['text']) ? $action['text'] : ''; ?>
                </a>
            <?php elseif ($action['type'] === 'form'): ?>
                <form method="POST" style="display: inline;" onsubmit="submitForm(event)">
                    <?php foreach ($action['fields'] as $name => $value): ?>
                        <input type="hidden" name="<?php echo $name; ?>" value="<?php echo $value; ?>">
                    <?php endforeach; ?>
                    <button type="submit" name="<?php echo $action['submit_name']; ?>" 
                            class="card-btn card-btn-<?php echo $action['style']; ?>"
                            title="<?php echo $action['title']; ?>">
                        <i class="fas <?php echo $action['icon']; ?>"></i>
                        <?php echo isset($action['text']) ? $action['text'] : ''; ?>
                    </button>
                </form>
            <?php else: ?>
                <button class="card-btn card-btn-<?php echo $action['style']; ?>" 
                        onclick="<?php echo isset($action['onclick']) ? $action['onclick'] : ''; ?>"
                        title="<?php echo $action['title']; ?>">
                    <i class="fas <?php echo $action['icon']; ?>"></i>
                    <?php echo isset($action['text']) ? $action['text'] : ''; ?>
                </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php }

/**
 * Génère une carte produit
 */
function productCard($pdo, $produit) {
    if (!$produit) return;
    $stock = isset($produit['stock']) ? $produit['stock'] : 0;
    $stock_badge = $stock <= 5 ? 'badge-warning' : 'badge-success';
    $est_actif = isset($produit['est_actif']) ? $produit['est_actif'] : 0;
    $status_badge = $est_actif ? 'badge-success' : 'badge-danger';
    $status_label = $est_actif ? 'Actif' : 'Inactif';
    $id_produit = isset($produit['id_produit']) ? $produit['id_produit'] : 0;
    $nom_produit = isset($produit['nom_produit']) ? $produit['nom_produit'] : '';
    $nom_categorie = isset($produit['nom_categorie']) ? $produit['nom_categorie'] : '';
    $marque = isset($produit['marque']) ? $produit['marque'] : '';
    $modele = isset($produit['modele']) ? $produit['modele'] : '';
    $prix = isset($produit['prix']) ? $produit['prix'] : 0;
    $est_en_promotion = isset($produit['est_en_promotion']) ? $produit['est_en_promotion'] : 0;
    $prix_promotion = isset($produit['prix_promotion']) ? $produit['prix_promotion'] : 0;
    ?>
    
    <div class="card product-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-box"></i> <?php echo htmlspecialchars($nom_produit); ?></div>
            <span class="card-badge <?php echo $status_badge; ?>"><?php echo $status_label; ?></span>
        </div>
        
        <div class="product-image"><?php productImage($pdo, $id_produit, $nom_produit); ?></div>
        
        <div class="card-body">
            <div><strong>Catégorie:</strong> <?php echo htmlspecialchars($nom_categorie); ?></div>
            <div><strong>Marque/Modèle:</strong> <?php echo htmlspecialchars($marque . ' ' . $modele); ?></div>
            
            <div style="display: flex; justify-content: space-between; margin: 15px 0;">
                <div class="product-price"><?php echo number_format($prix, 0, ',', ' '); ?> FCFA</div>
                <div><span class="<?php echo $stock_badge; ?>">Stock: <?php echo $stock; ?></span></div>
            </div>
            
            <?php if ($est_en_promotion && $prix_promotion): ?>
            <div style="background: #fff3cd; padding: 8px; border-radius: 5px;">
                <div style="color: #856404; font-size: 12px;"><i class="fas fa-fire"></i> PROMOTION</div>
                <div style="font-size: 16px; font-weight: bold; color: #d63031;">
                    <?php echo number_format($prix_promotion, 0, ',', ' '); ?> FCFA
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card-footer">
            <?php cardActions([
                ['type' => 'button', 'style' => 'primary', 'icon' => 'fa-eye', 'title' => 'Voir', 'onclick' => "viewProduct($id_produit)"],
                ['type' => 'button', 'style' => 'warning', 'icon' => 'fa-edit', 'title' => 'Modifier', 'onclick' => "editProduct($id_produit)"],
                ['type' => 'button', 'style' => 'danger', 'icon' => 'fa-trash', 'title' => 'Supprimer', 
                 'onclick' => "deleteProduct($id_produit)"]
            ]); ?>
        </div>
    </div>
<?php }

/**
 * Génère une carte commande
 */
function orderCard($commande) { 
    if (!$commande) return;
    $id_commande = isset($commande['id_commande']) ? $commande['id_commande'] : 0;
    $numero_commande = isset($commande['numero_commande']) ? $commande['numero_commande'] : '';
    $statut = isset($commande['statut']) ? $commande['statut'] : '';
    $nom = isset($commande['nom']) ? $commande['nom'] : '';
    $prenom = isset($commande['prenom']) ? $commande['prenom'] : '';
    $date_commande = isset($commande['date_commande']) ? $commande['date_commande'] : date('Y-m-d H:i:s');
    $nb_produits = isset($commande['nb_produits']) ? $commande['nb_produits'] : 0;
    $montant_total = isset($commande['montant_total']) ? $commande['montant_total'] : 0;
    ?>
    <div class="card order-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-receipt"></i> Commande #<?php echo htmlspecialchars($numero_commande); ?></div>
            <?php echo statusBadge($statut); ?>
        </div>
        
        <div class="card-body">
            <div class="order-customer"><i class="fas fa-user"></i> <?php echo htmlspecialchars($nom . ' ' . $prenom); ?></div>
            <div style="margin: 10px 0; font-size: 14px;"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($date_commande)); ?></div>
            <div style="margin: 10px 0; font-size: 14px;"><i class="fas fa-box"></i> <?php echo $nb_produits; ?> produit(s)</div>
            <div class="order-amount"><?php echo number_format($montant_total, 0, ',', ' '); ?> FCFA</div>
        </div>
        
        <div class="card-footer">
            <form method="POST" style="width: 100%; display: flex; gap: 10px;" onsubmit="submitForm(event)">
                <input type="hidden" name="id_commande" value="<?php echo $id_commande; ?>">
                <select name="statut" class="form-control" onchange="this.form.submit()" style="font-size: 12px; flex: 1;">
                    <option value="en attente" <?php echo $statut == 'en attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="confirmee" <?php echo $statut == 'confirmee' ? 'selected' : ''; ?>>Confirmée</option>
                    <option value="expediee" <?php echo $statut == 'expediee' ? 'selected' : ''; ?>>Expédiée</option>
                    <option value="livree" <?php echo $statut == 'livree' ? 'selected' : ''; ?>>Livrée</option>
                    <option value="annulee" <?php echo $statut == 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                    <option value="payee" <?php echo $statut == 'payeee' ? 'selected' : ''; ?>>Payée</option>
                </select>
                <input type="hidden" name="changer_statut" value="1">
                <button type="button" class="card-btn card-btn-primary" onclick="viewOrder(<?php echo $id_commande; ?>)">
                    <i class="fas fa-eye"></i>
                </button>
            </form>
        </div>
    </div>
<?php }

/**
 * Génère une carte client
 */
function clientCard($client) { 
    if (!$client) return;
    $id_utilisateur = isset($client['id_utilisateur']) ? $client['id_utilisateur'] : 0;
    $nom = isset($client['nom']) ? $client['nom'] : '';
    $prenom = isset($client['prenom']) ? $client['prenom'] : '';
    $email = isset($client['email']) ? $client['email'] : '';
    $telephone = isset($client['telephone']) ? $client['telephone'] : '';
    $date_inscription = isset($client['date_inscription']) ? $client['date_inscription'] : date('Y-m-d');
    $est_actif = isset($client['est_actif']) ? $client['est_actif'] : 1;
    $nb_commandes = isset($client['nb_commandes']) ? $client['nb_commandes'] : 0;
    
    $initiales = strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));
    $badge_color = $est_actif ? 'badge-success' : 'badge-danger';
    $badge_text = $est_actif ? 'Actif' : 'Inactif';
    ?>
    
    <div class="card client-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-user"></i> <?php echo htmlspecialchars($prenom . ' ' . $nom); ?></div>
            <span class="card-badge <?php echo $badge_color; ?>"><?php echo $badge_text; ?></span>
        </div>
        
        <div class="client-info">
            <div class="client-avatar"><?php echo $initiales; ?></div>
            <div>
                <div class="client-name"><?php echo htmlspecialchars($prenom . ' ' . $nom); ?></div>
                <div class="client-email"><?php echo htmlspecialchars($email); ?></div>
            </div>
        </div>
        
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                <div>
                    <div style="font-size: 12px; color: #666;">Téléphone</div>
                    <div><?php echo $telephone ? htmlspecialchars($telephone) : 'Non renseigné'; ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #666;">Commandes</div>
                    <div><span class="badge <?php echo $nb_commandes > 0 ? 'badge-success' : 'badge-info'; ?>"><?php echo $nb_commandes; ?> commande(s)</span></div>
                </div>
            </div>
            <div style="font-size: 12px; color: #666;"><i class="fas fa-calendar"></i> Inscrit le: <?php echo date('d/m/Y', strtotime($date_inscription)); ?></div>
        </div>
        
        <div class="card-footer">
            <?php cardActions([
                ['type' => 'button', 'style' => 'primary', 'icon' => 'fa-eye', 'text' => 'Profil', 'title' => 'Voir profil', 'onclick' => "viewClient($id_utilisateur)"],
                ['type' => 'button', 'style' => 'warning', 'icon' => 'fa-edit', 'text' => 'Modifier', 'title' => 'Modifier', 'onclick' => "editClient($id_utilisateur)"],
                ['type' => 'button', 'style' => 'danger', 'icon' => 'fa-power-off', 'text' => $badge_text, 'title' => $badge_text, 
                 'onclick' => "toggleClientStatus($id_utilisateur, $est_actif)"]
            ]); ?>
        </div>
    </div>
<?php }

/**
 * Génère une carte catégorie
 */
function categoryCard($cat) { 
    if (!$cat) return;
    $id_categorie = isset($cat['id_categorie']) ? $cat['id_categorie'] : 0;
    $nom_categorie = isset($cat['nom_categorie']) ? $cat['nom_categorie'] : '';
    $description = isset($cat['description']) ? $cat['description'] : '';
    $image_categorie = isset($cat['image_categorie']) ? $cat['image_categorie'] : '';
    $nb_produits = isset($cat['nb_produits']) ? $cat['nb_produits'] : 0;
    $nb_sous_categories = isset($cat['nb_sous_categories']) ? $cat['nb_sous_categories'] : 0;
    ?>
    <div class="card category-card">
        <div class="category-icon">
            <?php if (!empty($image_categorie)): ?>
                <img src="../<?php echo $image_categorie; ?>" alt="<?php echo htmlspecialchars($nom_categorie); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
            <?php else: ?>
                <i class="fas fa-folder"></i>
            <?php endif; ?>
        </div>
        
        <div class="category-name"><?php echo htmlspecialchars($nom_categorie); ?></div>
        <div class="product-count">
            <i class="fas fa-box"></i> <?php echo $nb_produits; ?> produits
            <?php if ($nb_sous_categories > 0): ?>
                <span style="margin-left: 15px;"><i class="fas fa-sitemap"></i> <?php echo $nb_sous_categories; ?> sous-catégories</span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($description)): ?>
            <div style="margin-top: 10px; color: #666; font-size: 14px;">
                <?php echo htmlspecialchars(substr($description, 0, 100)); ?><?php echo strlen($description) > 100 ? '...' : ''; ?>
            </div>
        <?php endif; ?>
        
        <div class="card-footer">
            <?php cardActions([
                ['type' => 'button', 'style' => 'warning', 'icon' => 'fa-edit', 'text' => 'Modifier', 'title' => 'Modifier', 'onclick' => "editCategory($id_categorie)"],
                ['type' => 'button', 'style' => 'danger', 'icon' => 'fa-trash', 'text' => 'Supprimer', 'title' => 'Supprimer',
                 'onclick' => "deleteCategory($id_categorie)"]
            ]); ?>
        </div>
    </div>
<?php }

/**
 * Génère une carte avis
 */
function reviewCard($avis_item) { 
    if (!$avis_item) return;
    $id_avis = isset($avis_item['id_avis']) ? $avis_item['id_avis'] : 0;
    $nom = isset($avis_item['nom']) ? $avis_item['nom'] : '';
    $prenom = isset($avis_item['prenom']) ? $avis_item['prenom'] : '';
    $nom_produit = isset($avis_item['nom_produit']) ? $avis_item['nom_produit'] : '';
    $note = isset($avis_item['note']) ? $avis_item['note'] : 0;
    $commentaire = isset($avis_item['commentaire']) ? $avis_item['commentaire'] : '';
    $date_avis = isset($avis_item['date_avis']) ? $avis_item['date_avis'] : date('Y-m-d H:i:s');
    $est_approuve = isset($avis_item['est_approuve']) ? $avis_item['est_approuve'] : 0;
    ?>
    <div class="card review-card">
        <div class="review-header">
            <div>
                <div class="review-author"><?php echo htmlspecialchars($nom . ' ' . $prenom); ?></div>
                <div style="font-size: 12px; color: #666;">Sur: <?php echo htmlspecialchars($nom_produit); ?></div>
            </div>
            <div class="review-stars"><?php echo displayStars($note); ?></div>
        </div>
        
        <div class="review-text"><?php echo htmlspecialchars($commentaire); ?></div>
        
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($date_avis)); ?>
        </div>
        
        <div class="card-footer">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <?php echo statusBadge($est_approuve ? 'Approuvé' : 'En attente'); ?>
                
                <?php 
                $actions = [];
                if (!$est_approuve) {
                    $actions[] = [
                        'type' => 'form', 'style' => 'success', 'icon' => 'fa-check', 'title' => 'Approuver',
                        'fields' => ['id_avis' => $id_avis], 'submit_name' => 'approuver_avis'
                    ];
                }
                $actions[] = [
                    'type' => 'button', 'style' => 'danger', 'icon' => 'fa-trash', 'title' => 'Supprimer',
                    'onclick' => "deleteReview($id_avis)"
                ];
                cardActions($actions);
                ?>
            </div>
        </div>
    </div>
<?php }

/**
 * Génère une carte message
 */
function messageCard($message_item) { 
    if (!$message_item) return;
    $id_message = isset($message_item['id_message']) ? $message_item['id_message'] : 0;
    $nom = isset($message_item['nom']) ? $message_item['nom'] : '';
    $email = isset($message_item['email']) ? $message_item['email'] : '';
    $sujet = isset($message_item['sujet']) ? $message_item['sujet'] : '';
    $contenu = isset($message_item['contenu']) ? $message_item['contenu'] : '';
    $statut = isset($message_item['statut']) ? $message_item['statut'] : '';
    $date_message = isset($message_item['date_message']) ? $message_item['date_message'] : date('Y-m-d H:i:s');
    ?>
    <div class="card message-card">
        <div class="message-header">
            <div>
                <div class="message-sender"><?php echo htmlspecialchars($nom); ?></div>
                <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($email); ?></div>
            </div>
            <?php echo statusBadge($statut); ?>
        </div>
        
        <div class="message-subject"><?php echo htmlspecialchars($sujet); ?></div>
        
        <div class="message-excerpt">
            <?php echo nl2br(htmlspecialchars(substr($contenu, 0, 150) . '...')); ?>
        </div>
        
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($date_message)); ?>
        </div>
        
        <div class="card-footer">
            <?php cardActions([
                ['type' => 'button', 'style' => 'primary', 'icon' => 'fa-eye', 'text' => 'Lire', 'title' => 'Lire', 'onclick' => "viewMessage($id_message)"],
                ['type' => 'button', 'style' => 'danger', 'icon' => 'fa-trash', 'text' => 'Supprimer', 'title' => 'Supprimer', 'onclick' => "deleteMessage($id_message)"]
            ]); ?>
        </div>
    </div>
<?php }

// ============================================
// NOUVELLES FONCTIONS POUR LES TABLEAUX
// ============================================

/**
 * Génère un tableau de produits
 */
function displayProductsTable($produits, $pdo) { ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Nom</th>
                    <th>Catégorie</th>
                    <th>Marque/Modèle</th>
                    <th>Prix</th>
                    <th>Stock</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produits as $produit): ?>
                <tr>
                    <td><?php productImage($pdo, $produit['id_produit'], $produit['nom_produit']); ?></td>
                    <td><?php echo htmlspecialchars($produit['nom_produit']); ?></td>
                    <td><?php echo htmlspecialchars($produit['nom_categorie'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($produit['marque'] ?? '') . ' ' . ($produit['modele'] ?? '')); ?></td>
                    <td><?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA</td>
                    <td>
                        <span class="badge <?php echo $produit['stock'] <= 5 ? 'badge-warning' : 'badge-success'; ?>">
                            <?php echo $produit['stock']; ?>
                        </span>
                    </td>
                    <td><?php echo statusBadge($produit['est_actif']); ?></td>
                    <td>
                        <div class="table-actions">
                            <button class="table-btn table-btn-view" onclick="viewProduct(<?php echo $produit['id_produit']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="table-btn table-btn-edit" onclick="editProduct(<?php echo $produit['id_produit']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="table-btn table-btn-delete" onclick="deleteProduct(<?php echo $produit['id_produit']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php }

/**
 * Génère un tableau de commandes
 */
function displayOrdersTable($commandes) { ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>N° Commande</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Montant</th>
                    <th>Produits</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commandes as $commande): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($commande['numero_commande'] ?? $commande['id_commande']); ?></td>
                    <td><?php echo htmlspecialchars(($commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? '')); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></td>
                    <td><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</td>
                    <td><?php echo $commande['nb_produits'] ?? 0; ?></td>
                    <td><?php echo statusBadge($commande['statut']); ?></td>
                    <td>
                        <div class="table-actions">
                            <form method="POST" style="display: inline;" onsubmit="submitForm(event)">
                                <input type="hidden" name="id_commande" value="<?php echo $commande['id_commande']; ?>">
                                <select name="statut" onchange="this.form.submit()" style="padding: 5px; border-radius: 5px; border: 1px solid #ddd;">
                                    <option value="en attente" <?php echo $commande['statut'] == 'en attente' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="confirmee" <?php echo $commande['statut'] == 'confirmee' ? 'selected' : ''; ?>>Confirmée</option>
                                    <option value="expediee" <?php echo $commande['statut'] == 'expediee' ? 'selected' : ''; ?>>Expédiée</option>
                                    <option value="livree" <?php echo $commande['statut'] == 'livree' ? 'selected' : ''; ?>>Livrée</option>
                                    <option value="annulee" <?php echo $commande['statut'] == 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                                </select>
                                <input type="hidden" name="changer_statut" value="1">
                            </form>
                            <button class="table-btn table-btn-view" onclick="viewOrder(<?php echo $commande['id_commande']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php }

/**
 * Génère un tableau de clients
 */
function displayClientsTable($clients) { ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Inscription</th>
                    <th>Commandes</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                <tr>
                    <td><?php echo htmlspecialchars(($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($client['email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($client['telephone'] ?? 'Non renseigné'); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($client['date_inscription'])); ?></td>
                    <td><span class="badge badge-info"><?php echo $client['nb_commandes'] ?? 0; ?></span></td>
                    <td><?php echo statusBadge($client['est_actif'] ?? 1); ?></td>
                    <td>
                        <div class="table-actions">
                            <button class="table-btn table-btn-view" onclick="viewClient(<?php echo $client['id_utilisateur']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="table-btn table-btn-edit" onclick="editClient(<?php echo $client['id_utilisateur']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="table-btn table-btn-delete" onclick="toggleClientStatus(<?php echo $client['id_utilisateur']; ?>, <?php echo $client['est_actif'] ?? 1; ?>)">
                                <i class="fas fa-power-off"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php }

/**
 * Génère un tableau de catégories
 */
function displayCategoriesTable($categories) { ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Nom</th>
                    <th>Description</th>
                    <th>Produits</th>
                    <th>Sous-catégories</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td>
                        <?php if (!empty($cat['image_categorie'])): ?>
                            <img src="../<?php echo htmlspecialchars($cat['image_categorie']); ?>" alt="<?php echo htmlspecialchars($cat['nom_categorie']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #f0f0f0; border-radius: 8px;">
                                <i class="fas fa-folder" style="font-size: 24px; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($cat['nom_categorie']); ?></td>
                    <td><?php echo htmlspecialchars(substr($cat['description'] ?? '', 0, 50)) . '...'; ?></td>
                    <td><span class="badge badge-info"><?php echo $cat['nb_produits'] ?? 0; ?></span></td>
                    <td><span class="badge badge-warning"><?php echo $cat['nb_sous_categories'] ?? 0; ?></span></td>
                    <td>
                        <div class="table-actions">
                            <button class="table-btn table-btn-edit" onclick="editCategory(<?php echo $cat['id_categorie']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="table-btn table-btn-delete" onclick="deleteCategory(<?php echo $cat['id_categorie']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php }

/**
 * Génère un tableau d'avis
 */
function displayReviewsTable($avis) { ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Produit</th>
                    <th>Note</th>
                    <th>Avis</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($avis as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($a['nom_produit'] ?? ''); ?></td>
                    <td><div class="stars"><?php echo displayStars($a['note']); ?></div></td>
                    <td><?php echo htmlspecialchars(substr($a['commentaire'] ?? '', 0, 30)) . '...'; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($a['date_avis'])); ?></td>
                    <td><?php echo statusBadge($a['est_approuve'] ? 'Approuvé' : 'En attente'); ?></td>
                    <td>
                        <div class="table-actions">
                            <?php if (!$a['est_approuve']): ?>
                            <form method="POST" style="display: inline;" onsubmit="submitForm(event)">
                                <input type="hidden" name="id_avis" value="<?php echo $a['id_avis']; ?>">
                                <button type="submit" name="approuver_avis" class="table-btn table-btn-approve">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <button class="table-btn table-btn-delete" onclick="deleteReview(<?php echo $a['id_avis']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php }

/**
 * Génère un tableau de messages
 */
function displayMessagesTable($messages) { ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Expéditeur</th>
                    <th>Email</th>
                    <th>Sujet</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $msg): ?>
                <tr>
                    <td><?php echo htmlspecialchars($msg['nom'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($msg['email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($msg['sujet'] ?? ''); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($msg['date_message'])); ?></td>
                    <td><?php echo statusBadge($msg['statut'] ?? 'nouveau'); ?></td>
                    <td>
                        <div class="table-actions">
                            <button class="table-btn table-btn-view" onclick="viewMessage(<?php echo $msg['id_message']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="table-btn table-btn-delete" onclick="deleteMessage(<?php echo $msg['id_message']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php }

// ============================================
// NOUVELLES FONCTIONS POUR LE CHAT
// ============================================

/**
 * Récupère les messages non lus pour l'admin
 */
function getUnreadMessagesCount($pdo, $admin_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM message 
            WHERE id_destinataire = ? AND lu = 0
        ");
        $stmt->execute([$admin_id]);
        return $stmt->fetch()['total'];
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Récupère les conversations récentes
 */
function getRecentConversations($pdo, $admin_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                u.id_utilisateur,
                u.nom,
                u.prenom,
                u.email,
                u.photo_profil,
                (
                    SELECT message 
                    FROM message 
                    WHERE (id_expediteur = u.id_utilisateur AND id_destinataire = ?)
                       OR (id_expediteur = ? AND id_destinataire = u.id_utilisateur)
                    ORDER BY date_envoi DESC 
                    LIMIT 1
                ) as dernier_message,
                (
                    SELECT date_envoi 
                    FROM message 
                    WHERE (id_expediteur = u.id_utilisateur AND id_destinataire = ?)
                       OR (id_expediteur = ? AND id_destinataire = u.id_utilisateur)
                    ORDER BY date_envoi DESC 
                    LIMIT 1
                ) as date_dernier_message,
                (
                    SELECT COUNT(*) 
                    FROM message 
                    WHERE id_expediteur = u.id_utilisateur 
                    AND id_destinataire = ? 
                    AND lu = 0
                ) as non_lus,
                uc.est_en_ligne
            FROM utilisateur u
            LEFT JOIN utilisateur_connexion uc ON u.id_utilisateur = uc.id_utilisateur
            WHERE u.id_utilisateur IN (
                SELECT DISTINCT id_expediteur FROM message WHERE id_destinataire = ?
                UNION
                SELECT DISTINCT id_destinataire FROM message WHERE id_expediteur = ?
            )
            AND u.id_utilisateur != ?
            ORDER BY date_dernier_message DESC
            LIMIT 10
        ");
        $stmt->execute([$admin_id, $admin_id, $admin_id, $admin_id, $admin_id, $admin_id, $admin_id, $admin_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Récupère les derniers messages reçus
 */
function getRecentMessages($pdo, $admin_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   u.nom, u.prenom, u.email, u.photo_profil
            FROM message m
            JOIN utilisateur u ON m.id_expediteur = u.id_utilisateur
            WHERE m.id_destinataire = ?
            ORDER BY m.date_envoi DESC
            LIMIT 10
        ");
        $stmt->execute([$admin_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Affiche une carte conversation pour le chat
 */
function conversationCard($conv, $admin_id) { ?>
    <a href="?section=chat&user=<?php echo $conv['id_utilisateur']; ?>" class="conversation-item <?php echo (isset($_GET['user']) && $_GET['user'] == $conv['id_utilisateur']) ? 'active' : ''; ?>" data-user-id="<?php echo $conv['id_utilisateur']; ?>">
        <div class="conv-avatar">
            <?php if (!empty($conv['photo_profil'])): ?>
                <img src="../<?php echo htmlspecialchars($conv['photo_profil']); ?>" alt="Avatar">
            <?php else: ?>
                <div class="avatar-placeholder">
                    <?php echo strtoupper(substr($conv['prenom'] ?? '', 0, 1) . substr($conv['nom'] ?? '', 0, 1)); ?>
                </div>
            <?php endif; ?>
            <?php if ($conv['est_en_ligne'] ?? false): ?>
                <span class="online-dot"></span>
            <?php endif; ?>
        </div>
        <div class="conv-info">
            <h4><?php echo htmlspecialchars(($conv['prenom'] ?? '') . ' ' . ($conv['nom'] ?? '')); ?></h4>
            <p class="conv-preview">
                <?php echo htmlspecialchars(substr($conv['dernier_message'] ?? 'Nouvelle conversation', 0, 30)) . '...'; ?>
            </p>
        </div>
        <?php if (($conv['non_lus'] ?? 0) > 0): ?>
        <div class="conv-badge"><?php echo $conv['non_lus']; ?></div>
        <?php endif; ?>
    </a>
<?php }

/**
 * Affiche la liste des conversations
 */
function displayConversationsList($conversations, $admin_id) { ?>
    <div class="conversations-section">
        <div class="conversations-header">
            <h3><i class="fas fa-comments"></i> Conversations</h3>
            <div class="unread-badge" id="globalUnreadBadge">
                <?php 
                $total_unread = array_sum(array_column($conversations, 'non_lus'));
                if ($total_unread > 0): ?>
                    <span class="badge badge-danger"><?php echo $total_unread; ?> non lu(s)</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="conversations-search">
            <input type="text" class="search-input" placeholder="Rechercher une conversation..." id="conversationSearch">
        </div>
        
        <div class="conversations-list" id="conversationsList">
            <?php if (empty($conversations)): ?>
                <p class="no-conversations">Aucune conversation pour le moment</p>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <?php conversationCard($conv, $admin_id); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php }

/**
 * Affiche la zone de chat active
 */
function displayActiveChat($interlocuteur, $messages, $admin_id, $csrf_token) { ?>
    <div class="active-chat">
        <div class="chat-header">
            <div class="chat-user-info">
                <div class="chat-user-avatar">
                    <?php if (!empty($interlocuteur['photo_profil'])): ?>
                        <img src="../<?php echo htmlspecialchars($interlocuteur['photo_profil']); ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($interlocuteur['prenom'] ?? '', 0, 1) . substr($interlocuteur['nom'] ?? '', 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="chat-user-details">
                    <h3><?php echo htmlspecialchars(($interlocuteur['prenom'] ?? '') . ' ' . ($interlocuteur['nom'] ?? '')); ?></h3>
                    <span class="user-status <?php echo ($interlocuteur['est_en_ligne'] ?? false) ? 'online' : 'offline'; ?>">
                        <?php echo ($interlocuteur['est_en_ligne'] ?? false) ? 'En ligne' : 'Hors ligne'; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="messages-area" id="messages-area">
            <?php foreach ($messages as $msg): ?>
            <div class="message <?php echo ($msg['id_expediteur'] == $admin_id) ? 'sent' : 'received'; ?>">
                <?php if ($msg['id_expediteur'] != $admin_id): ?>
                <div class="message-avatar">
                    <?php if (!empty($interlocuteur['photo_profil'])): ?>
                        <img src="../<?php echo htmlspecialchars($interlocuteur['photo_profil']); ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="avatar-placeholder tiny">
                            <?php echo strtoupper(substr($interlocuteur['prenom'] ?? '', 0, 1) . substr($interlocuteur['nom'] ?? '', 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="message-bubble">
                    <?php if ($msg['type_message'] === 'image'): ?>
                        <img src="../<?php echo htmlspecialchars($msg['message']); ?>" alt="Image" class="message-image" onclick="window.open(this.src)">
                    <?php elseif ($msg['type_message'] === 'file'): ?>
                        <a href="../<?php echo htmlspecialchars($msg['message']); ?>" target="_blank" class="file-link">
                            <i class="fas fa-file"></i> Télécharger le fichier
                        </a>
                    <?php else: ?>
                        <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                    <?php endif; ?>
                    <span class="message-time"><?php echo date('H:i', strtotime($msg['date_envoi'])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="message-input-area">
            <form method="POST" action="?section=chat&user=<?php echo $interlocuteur['id_utilisateur']; ?>" id="messageForm" enctype="multipart/form-data" onsubmit="sendMessage(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="destinataire_id" value="<?php echo $interlocuteur['id_utilisateur']; ?>">
                <input type="hidden" name="type_message" value="text" id="messageType">
                
                <div class="input-wrapper">
                    <textarea name="message" id="messageInput" placeholder="Écrivez votre message..."></textarea>
                    
                    <div class="input-actions">
                        <label for="fileUpload" class="btn-file">
                            <i class="fas fa-paperclip"></i>
                        </label>
                        <input type="file" id="fileUpload" name="fichier" style="display: none;" accept="image/*,.pdf,.doc,.docx,.txt">
                        
                        <button type="submit" name="send_message" class="btn-send">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
                
                <div id="filePreview" class="file-preview" style="display: none;">
                    <span id="fileName"></span>
                    <button type="button" onclick="removeFile()"><i class="fas fa-times"></i></button>
                </div>
            </form>
        </div>
    </div>
<?php }

/**
 * Affiche la section chat complète
 */
function displayChatSection($pdo, $admin_id, $csrf_token) {
    $conversation_user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
    $messages = [];
    $interlocuteur = null;
    
    // Récupérer les conversations
    $conversations = getRecentConversations($pdo, $admin_id);
    
    // Si une conversation est sélectionnée
    if ($conversation_user_id > 0) {
        // Récupérer les infos de l'interlocuteur
        $stmt = $pdo->prepare("SELECT id_utilisateur, nom, prenom, email, photo_profil FROM utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([$conversation_user_id]);
        $interlocuteur = $stmt->fetch();
        
        if ($interlocuteur) {
            // Marquer les messages comme lus
            $stmt = $pdo->prepare("
                UPDATE message 
                SET lu = 1 
                WHERE id_expediteur = ? AND id_destinataire = ? AND lu = 0
            ");
            $stmt->execute([$conversation_user_id, $admin_id]);
            
            // Récupérer les messages
            $stmt = $pdo->prepare("
                SELECT m.*, 
                       u.nom as expediteur_nom, 
                       u.prenom as expediteur_prenom
                FROM message m
                JOIN utilisateur u ON m.id_expediteur = u.id_utilisateur
                WHERE (m.id_expediteur = ? AND m.id_destinataire = ?)
                   OR (m.id_expediteur = ? AND m.id_destinataire = ?)
                ORDER BY m.date_envoi ASC
                LIMIT 50
            ");
            $stmt->execute([$admin_id, $conversation_user_id, $conversation_user_id, $admin_id]);
            $messages = $stmt->fetchAll();
            
            // Récupérer l'état en ligne
            $stmt = $pdo->prepare("SELECT est_en_ligne FROM utilisateur_connexion WHERE id_utilisateur = ?");
            $stmt->execute([$conversation_user_id]);
            $connexion = $stmt->fetch();
            $interlocuteur['est_en_ligne'] = $connexion['est_en_ligne'] ?? false;
        }
    }
    ?>
    
    <div class="chat-interface">
        <!-- Liste des conversations -->
        <?php displayConversationsList($conversations, $admin_id); ?>
        
        <!-- Zone de chat active -->
        <?php if ($conversation_user_id > 0 && $interlocuteur): ?>
            <?php displayActiveChat($interlocuteur, $messages, $admin_id, $csrf_token); ?>
        <?php else: ?>
            <div class="no-chat-selected">
                <i class="fas fa-comments"></i>
                <h3>Sélectionnez une conversation</h3>
                <p>Choisissez un client dans la liste pour commencer à discuter</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Styles du chat -->
    <style>
    .chat-interface {
        display: grid;
        grid-template-columns: 350px 1fr;
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        min-height: 600px;
    }
    
    .conversations-section {
        background: #f8fafc;
        border-right: 1px solid #edf2f7;
        display: flex;
        flex-direction: column;
    }
    
    .conversations-header {
        padding: 20px;
        border-bottom: 1px solid #edf2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .conversations-header h3 {
        font-size: 18px;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .conversations-search {
        padding: 15px 20px;
        border-bottom: 1px solid #edf2f7;
    }
    
    .conversations-list {
        flex: 1;
        overflow-y: auto;
        max-height: 550px;
    }
    
    .conversation-item {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        text-decoration: none;
        color: inherit;
        border-bottom: 1px solid #edf2f7;
        transition: all 0.3s;
        position: relative;
    }
    
    .conversation-item:hover {
        background: white;
        transform: translateX(5px);
    }
    
    .conversation-item.active {
        background: linear-gradient(90deg, #e6f7ff, white);
        border-left: 4px solid var(--primary);
    }
    
    .conv-avatar {
        position: relative;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 15px;
        border: 2px solid var(--primary);
    }
    
    .conv-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .online-dot {
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 12px;
        height: 12px;
        background: var(--success);
        border-radius: 50%;
        border: 2px solid white;
    }
    
    .conv-info {
        flex: 1;
        min-width: 0;
    }
    
    .conv-info h4 {
        font-size: 15px;
        margin-bottom: 5px;
        color: #1e293b;
        font-weight: 600;
    }
    
    .conv-preview {
        font-size: 13px;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .conv-badge {
        background: var(--primary);
        color: white;
        border-radius: 20px;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: 600;
        min-width: 22px;
        text-align: center;
    }
    
    .active-chat {
        display: flex;
        flex-direction: column;
        background: white;
    }
    
    .active-chat .chat-header {
        padding: 15px 20px;
        border-bottom: 1px solid #edf2f7;
        background: white;
    }
    
    .chat-user-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .chat-user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid var(--primary);
    }
    
    .chat-user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .chat-user-details h3 {
        font-size: 16px;
        margin-bottom: 3px;
        color: #1e293b;
    }
    
    .user-status {
        font-size: 13px;
    }
    
    .user-status.online {
        color: var(--success);
    }
    
    .user-status.offline {
        color: #94a3b8;
    }
    
    .messages-area {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        max-height: 450px;
        background: #f8fafc;
    }
    
    .message {
        display: flex;
        margin-bottom: 15px;
        align-items: flex-end;
    }
    
    .message.sent {
        justify-content: flex-end;
    }
    
    .message-avatar {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 10px;
        border: 2px solid var(--primary);
    }
    
    .message-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .message-bubble {
        max-width: 60%;
        padding: 12px 15px;
        border-radius: 18px;
        position: relative;
        font-size: 14px;
        line-height: 1.5;
    }
    
    .message.sent .message-bubble {
        background: var(--primary);
        color: white;
        border-bottom-right-radius: 5px;
    }
    
    .message.received .message-bubble {
        background: white;
        color: #1e293b;
        border-bottom-left-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .message-image {
        max-width: 200px;
        max-height: 200px;
        border-radius: 10px;
        cursor: pointer;
    }
    
    .file-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 15px;
        background: #f1f5f9;
        color: #1e293b;
        text-decoration: none;
        border-radius: 20px;
        font-size: 13px;
    }
    
    .file-link:hover {
        background: var(--primary);
        color: white;
    }
    
    .message-time {
        font-size: 10px;
        margin-top: 5px;
        display: block;
        opacity: 0.7;
    }
    
    .message-input-area {
        padding: 15px 20px;
        border-top: 1px solid #edf2f7;
        background: white;
    }
    
    .input-wrapper {
        display: flex;
        gap: 10px;
        align-items: center;
        background: #f8fafc;
        border-radius: 25px;
        padding: 5px;
        border: 1px solid #e2e8f0;
    }
    
    .input-wrapper textarea {
        flex: 1;
        padding: 10px 15px;
        border: none;
        background: transparent;
        resize: none;
        font-family: inherit;
        font-size: 14px;
        max-height: 80px;
    }
    
    .input-wrapper textarea:focus {
        outline: none;
    }
    
    .input-actions {
        display: flex;
        gap: 5px;
        margin-right: 5px;
    }
    
    .btn-file, .btn-send {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        background: var(--primary);
        color: white;
    }
    
    .btn-file:hover, .btn-send:hover {
        transform: scale(1.1);
    }
    
    .btn-file {
        background: var(--info);
    }
    
    .file-preview {
        margin-top: 10px;
        padding: 10px;
        background: #f8fafc;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid #e2e8f0;
    }
    
    .no-chat-selected {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #94a3b8;
        text-align: center;
        padding: 20px;
    }
    
    .no-chat-selected i {
        font-size: 60px;
        margin-bottom: 15px;
        color: var(--primary);
        opacity: 0.5;
    }
    
    .no-chat-selected h3 {
        font-size: 20px;
        margin-bottom: 8px;
        color: #1e293b;
    }
    
    .avatar-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--primary), var(--green-dark));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .avatar-placeholder.tiny {
        font-size: 14px;
    }
    
    @media (max-width: 768px) {
        .chat-interface {
            grid-template-columns: 1fr;
        }
        
        .conversations-section {
            display: none;
        }
    }
    </style>
<?php }

// ============================================
// FONCTION POUR AFFICHER LES CARTES DE LIENS DANS LE DASHBOARD
// ============================================
function displayDashboardLinks($stats) { ?>
    <div class="dashboard-links-grid">
        <!-- Produits -->
        <div class="dashboard-link-card" onclick="loadSection('produits')">
            <div class="link-icon" style="background: linear-gradient(135deg, var(--primary), var(--green-dark));">
                <i class="fas fa-box"></i>
            </div>
            <div class="link-info">
                <h3>PRODUITS</h3>
                <p><?php echo isset($stats['total_produits']) ? $stats['total_produits'] : 0; ?> produits actifs</p>
            </div>
            <div class="link-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- Commandes -->
        <div class="dashboard-link-card" onclick="loadSection('commandes')">
            <div class="link-icon" style="background: linear-gradient(135deg, var(--warning), #d97706);">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="link-info">
                <h3>COMMANDES</h3>
                <p><?php echo isset($stats['total_commandes']) ? $stats['total_commandes'] : 0; ?> commandes totales</p>
            </div>
            <div class="link-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- Clients -->
        <div class="dashboard-link-card" onclick="loadSection('clients')">
            <div class="link-icon" style="background: linear-gradient(135deg, var(--success), #0d9488);">
                <i class="fas fa-users"></i>
            </div>
            <div class="link-info">
                <h3>CLIENTS</h3>
                <p><?php echo isset($stats['total_clients']) ? $stats['total_clients'] : 0; ?> clients inscrits</p>
            </div>
            <div class="link-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- Catégories -->
        <div class="dashboard-link-card" onclick="loadSection('categories')">
            <div class="link-icon" style="background: linear-gradient(135deg, var(--info), #2563eb);">
                <i class="fas fa-tags"></i>
            </div>
            <div class="link-info">
                <h3>CATEGORIE</h3>
                <p>Gérer les catégories</p>
            </div>
            <div class="link-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- Support Client (Chat) -->
        <div class="dashboard-link-card" onclick="loadSection('chat')">
            <div class="link-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                <i class="fas fa-comments"></i>
            </div>
            <div class="link-info">
                <h3>SUPPORT CLIENTS</h3>
                <p><?php echo isset($stats['messages_non_lus']) && $stats['messages_non_lus'] > 0 ? '<span class="badge badge-danger">' . $stats['messages_non_lus'] . ' non lu(s)</span>' : 'Aucun nouveau message'; ?></p>
            </div>
            <div class="link-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- Avis clients -->
        <div class="dashboard-link-card" onclick="loadSection('avis')">
            <div class="link-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-star"></i>
            </div>
            <div class="link-info">
                <h3>AVIS CLIENTS</h3>
                <p><?php echo isset($stats['avis_en_attente']) && $stats['avis_en_attente'] > 0 ? '<span class="badge badge-warning">' . $stats['avis_en_attente'] . ' en attente</span>' : 'Tous approuvés'; ?></p>
            </div>
            <div class="link-arrow">
                <i class="fas fa-arrow-right"></i
            </div>
        </div>
        

        <!-- Messages -->
        <!-- <div class="dashboard-link-card" onclick="loadSection('messages')">
            <div class="link-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="link-info">
                <h3>Messages</h3>
                <p>Messages de contact</p>
            </div>
            <div class="link-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div> -->

        <!-- Paramètres -->
        <!-- <div class="dashboard-link-card" onclick="loadSection('parametres')">
            <div class="link-icon" style="background: linear-gradient(135deg, #64748b, #475569);">
                <i class="fas fa-cog"></i>
            </div>
            <div class="link-info">
                <h3>Paramètres</h3>
                <p>Configuration du site</p>
            </div>
            <div class="link-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>
        </div>
    </div> -->

    <style>
    .dashboard-links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-top: 30px;
    }

    .dashboard-link-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: var(--shadow-md);
        display: flex;
        align-items: center;
        gap: 20px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 1px solid #edf2f7;
        position: relative;
        overflow: hidden;
    }

    .dashboard-link-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
        border-color: transparent;
    }

    .dashboard-link-card:hover .link-arrow {
        transform: translateX(5px);
        opacity: 1;
    }

    .link-icon {
        width: 70px;
        height: 70px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
        box-shadow: var(--shadow-sm);
        flex-shrink: 0;
    }

    .link-info {
        flex: 1;
    }

    .link-info h3 {
        font-size: 20px;
        color: #1e293b;
        margin-bottom: 8px;
        font-weight: 700;
    }

    .link-info p {
        color: #64748b;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .link-arrow {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        transition: all 0.3s ease;
        opacity: 0.7;
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        .dashboard-links-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
<?php }

// ============================================
// TRAITEMENTS
// ============================================

$admin_id = $_SESSION['user_id'];

// Traitement de l'envoi de message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Token CSRF invalide';
        $message_type = 'error';
    } else {
        $destinataire_id = intval($_POST['destinataire_id']);
        $message_text = trim($_POST['message'] ?? '');
        $type_message = $_POST['type_message'] ?? 'text';
        
        // Validation du message
        $message_text = strip_tags($message_text);
        $message_text = substr($message_text, 0, 2000);
        
        if (empty($message_text) && empty($_FILES['fichier'])) {
            $message = 'Message vide';
            $message_type = 'error';
        } else {
            try {
                // Gestion de l'upload de fichier
                if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
                    $fichier = $_FILES['fichier'];
                    $types_autorises = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'application/pdf' => 'pdf',
                        'application/msword' => 'doc',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                        'text/plain' => 'txt'
                    ];
                    $taille_max = 10 * 1024 * 1024; // 10MB
                    
                    if (!array_key_exists($fichier['type'], $types_autorises)) {
                        throw new Exception('Type de fichier non autorisé');
                    }
                    
                    if ($fichier['size'] > $taille_max) {
                        throw new Exception('Fichier trop volumineux (max 10MB)');
                    }
                    
                    // Créer le dossier uploads s'il n'existe pas
                    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Mycksoft/uploads/chat/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Générer un nom unique
                    $extension = $types_autorises[$fichier['type']];
                    $nom_fichier = uniqid() . '_' . date('Ymd_His') . '.' . $extension;
                    $chemin_fichier = 'uploads/chat/' . $nom_fichier;
                    
                    if (move_uploaded_file($fichier['tmp_name'], $upload_dir . $nom_fichier)) {
                        $type_message = strpos($fichier['type'], 'image/') === 0 ? 'image' : 'file';
                        $message_contenu = $chemin_fichier;
                    } else {
                        throw new Exception('Erreur lors de l\'upload du fichier');
                    }
                } else {
                    $message_contenu = $message_text;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO message (id_expediteur, id_destinataire, message, type_message, date_envoi, lu)
                    VALUES (?, ?, ?, ?, NOW(), 0)
                ");
                $stmt->execute([$admin_id, $destinataire_id, $message_contenu, $type_message]);
                
                if ($isAjax) {
                    echo json_encode(['success' => true]);
                    exit;
                }
                
                $message = 'Message envoyé avec succès';
                $message_type = 'success';
                
            } catch (Exception $e) {
                if ($isAjax) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $message = 'Erreur : ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Initialiser PDO et les variables
try {
    // Récupération des catégories pour les formulaires
    $all_categories_stmt = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie");
    $all_categories = $all_categories_stmt->fetchAll();
} catch (Exception $e) {
    $all_categories = [];
}

// Ajouter produit
if (isset($_POST['ajouter_produit'])) {
    try {
        $nom = trim($_POST['nom_produit']);
        $description = trim($_POST['description']);
        $description_courte = isset($_POST['description_courte']) ? trim($_POST['description_courte']) : '';
        $prix = floatval($_POST['prix']);
        $prix_promotion = !empty($_POST['prix_promotion']) ? floatval($_POST['prix_promotion']) : null;
        $stock = intval($_POST['stock']);
        $id_categorie = intval($_POST['id_categorie']);
        $marque = isset($_POST['marque']) ? trim($_POST['marque']) : '';
        $modele = isset($_POST['modele']) ? trim($_POST['modele']) : '';
        $est_en_promotion = isset($_POST['est_en_promotion']) ? 1 : 0;
        
        if (empty($nom) || empty($description) || $prix <= 0 || $stock < 0) {
            throw new Exception('Données invalides');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO produit (
                nom_produit, description, description_courte, prix, prix_promotion, 
                stock, id_categorie, marque, modele, est_en_promotion, date_ajout
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $nom, $description, $description_courte, $prix, $prix_promotion,
            $stock, $id_categorie, $marque, $modele, $est_en_promotion
        ]);
        
        $produit_id = $pdo->lastInsertId();
        
        if (!empty($_FILES['image']['name'][0])) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Mycksoft/uploads/produits/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['image']['name'] as $key => $name) {
                if ($_FILES['image']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['image']['tmp_name'][$key];
                    $extension = pathinfo($name, PATHINFO_EXTENSION);
                    $filename = 'produit_' . $produit_id . '_' . uniqid() . '.' . $extension;
                    $destination = $upload_dir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $destination)) {
                        $est_principale = ($key === 0) ? 1 : 0;
                        $stmt_img = $pdo->prepare("
                            INSERT INTO image_produit (id_produit, url_image, description_image, est_principale, ordre, date_ajout)
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $url_image = 'uploads/produits/' . $filename;
                        $stmt_img->execute([$produit_id, $url_image, '', $est_principale, $key]);
                    }
                }
            }
        }
        
        $message = "Produit ajouté avec succès !";
        $message_type = "success";
        $section = 'produits';
        
    } catch (Exception $e) {
        $message = "Erreur lors de l'ajout du produit : " . $e->getMessage();
        $message_type = "error";
    }
}

// Mettre à jour produit
if (isset($_POST['modifier_produit'])) {
    try {
        $id_produit = intval($_POST['id_produit']);
        $nom = trim($_POST['nom_produit']);
        $description = trim($_POST['description']);
        $prix = floatval($_POST['prix']);
        $stock = intval($_POST['stock']);
        $id_categorie = intval($_POST['id_categorie']);
        $est_actif = isset($_POST['est_actif']) ? 1 : 0;
        
        $stmt = $pdo->prepare("
            UPDATE produit 
            SET nom_produit = ?, description = ?, description_courte = ?, prix = ?, prix_promotion = ?, stock = ?, 
                id_categorie = ?, marque = ?, modele = ?, est_en_promotion = ?, est_actif = ?, date_modification = NOW()
            WHERE id_produit = ?
        ");
        
        $stmt->execute([$nom, $description, $description_courte, $prix, $prix_promotion, $stock, $id_categorie, $marque, $modele, $est_en_promotion, $est_actif, $id_produit]);
        
        $message = "Produit modifié avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur lors de la modification du produit";
        $message_type = "error";
    }
}

// Mettre à jour client
if (isset($_POST['modifier_client'])) {
    try {
        $id_utilisateur = intval($_POST['id_utilisateur']);
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $telephone = trim($_POST['telephone'] ?? '');
        $est_actif = isset($_POST['est_actif']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE utilisateur SET nom = ?, prenom = ?, email = ?, telephone = ?, est_actif = ? WHERE id_utilisateur = ?");
        $stmt->execute([$nom, $prenom, $email, $telephone, $est_actif, $id_utilisateur]);

        $message = "Client modifié avec succès !";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Erreur lors de la modification du client";
        $message_type = "error";
    }
}

// Supprimer produit (désactiver)
if (isset($_GET['supprimer_produit'])) {
    try {
        $id_produit = intval($_GET['supprimer_produit']);
        $stmt = $pdo->prepare("UPDATE produit SET est_actif = 0 WHERE id_produit = ?");
        $stmt->execute([$id_produit]);
        $message = "Produit désactivé avec succès !";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Erreur lors de la suppression du produit";
        $message_type = "error";
    }
}

// Basculer le statut d'un client
if (isset($_GET['toggle_client'])) {
    try {
        $client_id = intval($_GET['toggle_client']);
        $stmt = $pdo->prepare("UPDATE utilisateur SET est_actif = CASE WHEN est_actif = 1 THEN 0 ELSE 1 END WHERE id_utilisateur = ?");
        $stmt->execute([$client_id]);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        $message = "Statut du client mis à jour avec succès !";
        $message_type = "success";
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $message = "Erreur lors de la mise à jour du statut du client";
        $message_type = "error";
    }
}

// Récupérer les détails d'un produit
if (isset($_GET['get_product'])) {
    $product_id = intval($_GET['get_product']);
    $stmt = $pdo->prepare("SELECT p.*, c.nom_categorie FROM produit p LEFT JOIN categorie c ON p.id_categorie = c.id_categorie WHERE p.id_produit = ?");
    $stmt->execute([$product_id]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    if ($produit) {
        echo json_encode(['success' => true, 'produit' => $produit]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Produit introuvable']);
    }
    exit;
}

// Récupérer les détails d'un client
if (isset($_GET['get_client'])) {
    $client_id = intval($_GET['get_client']);
    $stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM commande c WHERE c.id_utilisateur = u.id_utilisateur) as nb_commandes FROM utilisateur u WHERE u.id_utilisateur = ? AND u.role = 'client'");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    if ($client) {
        echo json_encode(['success' => true, 'client' => $client]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Client introuvable']);
    }
    exit;
}

// Changer statut commande
if (isset($_POST['changer_statut'])) {
    try {
        $id_commande = intval($_POST['id_commande']);
        $statut = $_POST['statut'];
        
        $stmt = $pdo->prepare("UPDATE commande SET statut = ? WHERE id_commande = ?");
        $stmt->execute([$statut, $id_commande]);
        
        $stmt_info = $pdo->prepare("
            SELECT u.id_utilisateur, c.numero_commande 
            FROM commande c 
            JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur 
            WHERE c.id_commande = ?
        ");
        $stmt_info->execute([$id_commande]);
        $commande_info = $stmt_info->fetch();
        
        if ($commande_info) {
            $stmt_notif = $pdo->prepare("
                INSERT INTO notification (id_utilisateur, titre, message, type, date_notification)
                VALUES (?, 'Mise à jour commande', ?, 'info', NOW())
            ");
            $message_notif = "Votre commande #" . $commande_info['numero_commande'] . " est maintenant " . $statut;
            $stmt_notif->execute([$commande_info['id_utilisateur'], $message_notif]);
        }
        
        $message = "Statut de commande mis à jour !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur lors de la mise à jour du statut";
        $message_type = "error";
    }
}

// Approuver un avis
if (isset($_POST['approuver_avis'])) {
    try {
        $id_avis = intval($_POST['id_avis']);
        $stmt = $pdo->prepare("UPDATE avis SET est_approuve = 1 WHERE id_avis = ?");
        $stmt->execute([$id_avis]);
        $message = "Avis approuvé avec succès !";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Erreur lors de l'approbation de l'avis";
        $message_type = "error";
    }
}

// Ajouter catégorie
if (isset($_POST['ajouter_categorie'])) {
    try {
        $nom = trim($_POST['nom_categorie']);
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $id_parent = !empty($_POST['id_categorie_parent']) ? intval($_POST['id_categorie_parent']) : null;
        
        $stmt = $pdo->prepare("INSERT INTO categorie (nom_categorie, description, id_categorie_parent) VALUES (?, ?, ?)");
        $stmt->execute([$nom, $description, $id_parent]);
        
        $categorie_id = $pdo->lastInsertId();
        
        if (!empty($_FILES['image_categorie']['name'])) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Mycksoft/uploads/categories/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $extension = pathinfo($_FILES['image_categorie']['name'], PATHINFO_EXTENSION);
            $filename = 'categorie_' . $categorie_id . '_' . uniqid() . '.' . $extension;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image_categorie']['tmp_name'], $destination)) {
                $url_image = 'uploads/categories/' . $filename;
                $stmt_img = $pdo->prepare("UPDATE categorie SET image_categorie = ? WHERE id_categorie = ?");
                $stmt_img->execute([$url_image, $categorie_id]);
            }
        }
        
        $message = "Catégorie ajoutée avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur lors de l'ajout de la catégorie";
        $message_type = "error";
    }
}

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================

try {
    // Initialiser toutes les variables avec des valeurs par défaut
    $stats = null;
    $produits_recent = array();
    $commandes_recent = array();
    $avis_attente = array();
    $messages_recent = array();
    $produits = array();
    $commandes = array();
    $clients = array();
    $categories = array();
    $avis = array();
    $messages = array();

    // Statistiques générales
    $stats_sql = "
        SELECT 
            (SELECT COUNT(*) FROM produit WHERE est_actif = 1) as total_produits,
            (SELECT COUNT(*) FROM utilisateur WHERE role = 'client') as total_clients,
            (SELECT COUNT(*) FROM commande) as total_commandes,
            COALESCE((SELECT SUM(montant_total) FROM commande WHERE statut IN ('livree', 'expediee')), 0) as chiffre_affaires,
            (SELECT COUNT(*) FROM commande WHERE DATE(date_commande) = CURDATE()) as commandes_aujourdhui,
            (SELECT COUNT(*) FROM avis WHERE est_approuve = 0) as avis_en_attente,
            (SELECT COUNT(*) FROM commande WHERE statut = 'en attente') as commandes_en_attente,
            (SELECT COUNT(*) FROM message WHERE id_destinataire = ? AND lu = 0) as messages_non_lus
    ";
    $stmt_stats = $pdo->prepare($stats_sql);
    $stmt_stats->execute([$admin_id]);
    $stats = $stmt_stats->fetch();

    switch($section) {
        case 'produits':
            $produits = $pdo->query("
                SELECT p.*, c.nom_categorie 
                FROM produit p 
                LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
                WHERE p.est_actif = 1 
                ORDER BY p.date_ajout DESC 
                LIMIT 50
            ")->fetchAll();
            $page_title = "Gestion des produits - Mycksoft";
            break;
            
        case 'commandes':
            $commandes = $pdo->query("
                SELECT c.*, u.nom, u.prenom, 
                       (SELECT COUNT(*) FROM ligne_commande lc WHERE lc.id_commande = c.id_commande) as nb_produits
                FROM commande c
                JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
                ORDER BY c.date_commande DESC
                LIMIT 50
            ")->fetchAll();
            $page_title = "Gestion des commandes - Mycksoft";
            break;
            
        case 'clients':
            $clients = $pdo->query("
                SELECT *, 
                       (SELECT COUNT(*) FROM commande c WHERE c.id_utilisateur = u.id_utilisateur) as nb_commandes
                FROM utilisateur u 
                WHERE u.role = 'client'
                ORDER BY u.date_inscription DESC 
                LIMIT 50
            ")->fetchAll();
            $page_title = "Gestion des clients - Mycksoft";
            break;
            
        case 'categories':
            $categories = $pdo->query("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM produit p WHERE p.id_categorie = c.id_categorie) as nb_produits,
                       (SELECT COUNT(*) FROM categorie c2 WHERE c2.id_categorie_parent = c.id_categorie) as nb_sous_categories
                FROM categorie c 
                ORDER BY c.nom_categorie
            ")->fetchAll();
            $page_title = "Gestion des catégories - Mycksoft";
            break;
            
        case 'avis':
            $avis = $pdo->query("
                SELECT a.*, p.nom_produit, u.nom, u.prenom 
                FROM avis a 
                JOIN produit p ON a.id_produit = p.id_produit 
                JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur 
                ORDER BY a.date_avis DESC
            ")->fetchAll();
            $page_title = "Gestion des avis - Mycksoft";
            break;
            
        // case 'messages':
             $messages = $pdo->query("
                SELECT * FROM message_contact 
                ORDER BY date_message DESC
            ")->fetchAll();
            $page_title = "Messages de contact - Mycksoft";
            break;
            
        case 'chat':
            $page_title = "Support client - Mycksoft";
            break;
            
        case 'parametres':
            $page_title = "Paramètres - Mycksoft";
            break;
            
        case 'dashboard':
        default:
            // Pour le dashboard, on ne charge que les statistiques, pas les données superflues
            // Les variables sont laissées vides car elles ne sont plus utilisées
            $produits_recent = [];
            $commandes_recent = [];
            $avis_attente = [];
            $messages_recent = [];
            break;
    }

} catch (Exception $e) {
    $message = "Erreur lors du chargement des données : " . $e->getMessage();
    $message_type = "error";
}

// ============================================
// SI C'EST UNE REQUÊTE AJAX - RENVOYER UNIQUEMENT LE CONTENU
// ============================================
if ($isAjax) {
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // S'assurer que toutes les variables existent
    if (!isset($stats)) $stats = null;
    if (!isset($produits_recent)) $produits_recent = array();
    if (!isset($commandes_recent)) $commandes_recent = array();
    if (!isset($avis_attente)) $avis_attente = array();
    if (!isset($messages_recent)) $messages_recent = array();
    if (!isset($produits)) $produits = array();
    if (!isset($commandes)) $commandes = array();
    if (!isset($clients)) $clients = array();
    if (!isset($categories)) $categories = array();
    if (!isset($avis)) $avis = array();
    if (!isset($messages)) $messages = array();
    if (!isset($all_categories)) $all_categories = array();
    
    ?>
    <?php if ($message): ?>
        <div class="notification notification-<?php echo $message_type; ?>" id="notification">
            <span><i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></span>
            <button class="notification-close" onclick="closeNotification()">&times;</button>
        </div>
    <?php endif; ?>
    
    <div class="dynamic-content">
        <?php 
        if ($section === 'chat') {
            displayChatSection($pdo, $admin_id, $_SESSION['csrf_token']);
        } else {
            includeSection($section, $pdo, $stats, $produits_recent, $commandes_recent, $avis_attente, $messages_recent, $produits, $commandes, $clients, $categories, $avis, $messages, $all_categories);
        }
        ?>
    </div>
    
    <script>
        if (typeof initSectionScripts === 'function') {
            initSectionScripts('<?php echo $section; ?>');
        }
        if (typeof closeNotification === 'function') {
            setTimeout(closeNotification, 5000);
        }
        <?php if ($section === 'chat'): ?>
        initChatScripts();
        <?php endif; ?>
    </script>
    <?php
    exit();
}

// ============================================
// FONCTION POUR INCLURE LE CONTENU DES SECTIONS (CORRIGÉE)
// ============================================
function includeSection($section, $pdo, $stats, $produits_recent, $commandes_recent, $avis_attente, $messages_recent, $produits, $commandes, $clients, $categories, $avis, $messages, $all_categories) {
    
    if ($section === 'dashboard'): ?>
        <!-- STATISTIQUES -->
        <?php if ($stats): ?>
            <div class="stats-grid">
                <?php displayStats($stats); ?>
            </div>
        <?php endif; ?>
        
        <!-- LIENS DU DASHBOARD SOUS FORME DE CARTES -->
        <?php displayDashboardLinks($stats); ?>
        
    <?php elseif ($section === 'produits'): ?>
        <!-- SECTION PRODUITS -->
        <div class="section">
            <?php 
            sectionHeader('Gestion des produits', 'fa-box', count($produits) . ' produits trouvés', array(
                array('type' => 'button', 'style' => 'primary', 'icon' => 'fa-plus', 'text' => 'Ajouter un produit', 'action' => "openModal('modalAjouter')"),
                array('type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;')
            )); 
            ?>
            
            <!-- Filtres et recherche -->
            <div class="card" style="margin-bottom: 25px;">
                <div class="card-body">
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 250px;">
                            <input type="text" class="form-control" placeholder="Rechercher un produit..." id="searchProduct">
                        </div>
                        <div style="min-width: 200px;">
                            <select class="form-control" id="filterCategory">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($all_categories as $cat): ?>
                                    <option value="<?php echo $cat['id_categorie']; ?>"><?php echo htmlspecialchars($cat['nom_categorie']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="min-width: 150px;">
                            <select class="form-control" id="filterStatus">
                                <option value="">Tous les statuts</option>
                                <option value="1">Actif</option>
                                <option value="0">Inactif</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" onclick="filterProducts()"><i class="fas fa-filter"></i> Filtrer</button>
                    </div>
                </div>
            </div>
            
            <!-- Tableau de produits -->
            <?php displayProductsTable($produits, $pdo); ?>
        </div>
        
    <?php elseif ($section === 'commandes'): ?>
        <div class="section">
            <?php 
            sectionHeader('Gestion des commandes', 'fa-shopping-cart', count($commandes) . ' commandes trouvées', array(
                array('type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;')
            )); 
            ?>
            <?php displayOrdersTable($commandes); ?>
        </div>
        
    <?php elseif ($section === 'clients'): ?>
        <div class="section">
            <?php 
            sectionHeader('Gestion des clients', 'fa-users', count($clients) . ' clients inscrits', array(
                array('type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;')
            )); 
            ?>
            <?php displayClientsTable($clients); ?>
        </div>
        
    <?php elseif ($section === 'categories'): ?>
        <div class="section">
            <?php 
            sectionHeader('Gestion des catégories', 'fa-tags', count($categories) . ' catégories créées', array(
                array('type' => 'button', 'style' => 'primary', 'icon' => 'fa-plus', 'text' => 'Nouvelle catégorie', 'action' => "openModal('modalAjouterCategorie')"),
                array('type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;')
            )); 
            ?>
            <?php displayCategoriesTable($categories); ?>
        </div>
        
    <?php elseif ($section === 'avis'): ?>
        <div class="section">
            <?php 
            sectionHeader('Gestion des avis', 'fa-star', count($avis) . ' avis clients', array(
                array('type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;')
            )); 
            ?>
            <?php displayReviewsTable($avis); ?>
        </div>
        
    <?php elseif ($section === 'messages'): ?>
        <div class="section">
            <?php 
            sectionHeader('Messages de contact', 'fa-envelope', count($messages) . ' messages reçus', array(
                array('type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;')
            )); 
            ?>
            <?php displayMessagesTable($messages); ?>
        </div>
        
    <?php elseif ($section === 'factures'): ?>
        <div class="section">
            <?php 
            sectionHeader('Gestion des factures', 'fa-file-invoice-dollar', 'Factures des commandes clients', array(
                array('type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;')
            )); 
            ?>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° Facture</th>
                            <th>Client</th>
                            <th>Commande</th>
                            <th>Date paiement</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Récupérer les commandes payées avec informations clients
                        $stmt_factures = $pdo->query("
                            SELECT c.id_commande, c.numero_commande, c.montant_total, c.statut, c.date_paiement,
                                   u.nom, u.prenom, u.email
                            FROM commande c
                            JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
                            WHERE c.statut = 'payee'
                            ORDER BY c.date_paiement DESC
                            LIMIT 50
                        ");
                        $factures = $stmt_factures->fetchAll();
                        
                        foreach ($factures as $facture):
                            $numero_facture = "CMD-" . str_pad($facture['id_commande'], 6, '0', STR_PAD_LEFT);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($numero_facture); ?></td>
                            <td><?php echo htmlspecialchars($facture['prenom'] . ' ' . $facture['nom']); ?></td>
                            <td><?php echo htmlspecialchars($facture['numero_commande']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($facture['date_paiement'])); ?></td>
                            <td><?php echo number_format($facture['montant_total'], 0, ',', ' '); ?> FCFA</td>
                            <td><?php echo statusBadge('payée'); ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="../facture.php?id=<?php echo $facture['id_commande']; ?>" 
                                       class="table-btn table-btn-view" target="_blank">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                    <button class="table-btn table-btn-success" 
                                            onclick="window.open('../facture.php?id=<?php echo $facture['id_commande']; ?>', '_blank').print()">
                                        <i class="fas fa-print"></i> Imprimer
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($factures)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">Aucune facture trouvée</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($section === 'parametres'): ?>
        <div class="section">
            <?php sectionHeader('Paramètres du site', 'fa-cog'); ?>
            
            <div class="form-grid">
                <div class="form-group"><label>Nom du site</label><input type="text" class="form-control" value="Mycksoft" id="site_name"></div>
                <div class="form-group"><label>Email de contact</label><input type="email" class="form-control" value="contact@mycksoft.com" id="contact_email"></div>
                <div class="form-group"><label>Téléphone</label><input type="text" class="form-control" value="+237 6XX XX XX XX" id="phone"></div>
                <div class="form-group"><label>Adresse</label><input type="text" class="form-control" value="Yaoundé, Cameroun" id="address"></div>
                <div class="form-group form-full"><label>Description du site</label><textarea class="form-control" rows="3" id="site_description">Votre partenaire informatique au Cameroun</textarea></div>
                <div class="form-group">
                    <label>Maintenance</label>
                    <div style="display: flex; gap: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px;"><input type="radio" name="maintenance" value="0" checked id="maintenance_off"> Site actif</label>
                        <label style="display: flex; align-items: center; gap: 8px;"><input type="radio" name="maintenance" value="1" id="maintenance_on"> Mode maintenance</label>
                    </div>
                </div>
                <div class="form-group form-full">
                    <button class="btn btn-primary" onclick="saveSettings()">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </div>
        </div>
    <?php endif;
}

// ============================================
// PAGE COMPLÈTE (CHARGEMENT INITIAL)
// ============================================

// Dans la partie TRAITEMENTS, après les autres traitements
// ============================================
// TRAITEMENT ENVOI NOTIFICATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_notification') {
        $titre = trim($_POST['titre'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $lien = trim($_POST['lien'] ?? '');
        $destinataire = $_POST['destinataire'] ?? '';
        $client_id = $_POST['client_id'] ?? '';
        
        //$notification = new Notification($pdo);
        $success = false;
        
        if (empty($titre) || empty($message)) {
            $message_erreur = "Le titre et le message sont obligatoires";
        } else {
            switch ($destinataire) {
                case 'all':
                    // $success = $notification->createForAllClients($titre, $message, $type, $lien);
                    $message_success = "Notification envoyée à tous les clients";
                    break;
                    
                case 'single':
                    if (!empty($client_id)) {
                        // $success = $notification->create($client_id, $titre, $message, $type, $lien);
                        // Récupérer le nom du client pour le message
                        $stmt = $pdo->prepare("SELECT nom, prenom FROM utilisateur WHERE id_utilisateur = ?");
                        $stmt->execute([$client_id]);
                        $client = $stmt->fetch();
                        $message_success = "Notification envoyée à " . htmlspecialchars($client['prenom'] . ' ' . $client['nom']);
                    } else {
                        $message_erreur = "Veuillez sélectionner un client";
                    }
                    break;
                    
                case 'multiple':
                    if (!empty($_POST['client_ids']) && is_array($_POST['client_ids'])) {
                        // $success = $notification->createForMultipleUsers($_POST['client_ids'], $titre, $message, $type, $lien);
                        $message_success = "Notification envoyée à " . count($_POST['client_ids']) . " clients";
                    } else {
                        $message_erreur = "Veuillez sélectionner au moins un client";
                    }
                    break;
                    
                default:
                    $message_erreur = "Type de destinataire invalide";
            }
        }
        
        if ($success) {
            $message = $message_success;
            $message_type = "success";
        } else if (!isset($message_erreur)) {
            $message_erreur = "Erreur lors de l'envoi de la notification";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- STYLE COMPLET DU DASHBOARD ADMIN -->
    <style>
        :root {
            --green-cm: #2d3d48;
            --red-cm: #ce1126;
            --yellow-cm: #fcd116;
            --green-dark: #1e2c34;
            --primary: #2d3d48;
            --secondary: #3f37c9;
            --success: #10b981;
            --danger: #ce1126;
            --warning: #f59e0b;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 260px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            --shadow-md: 0 5px 15px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f0f2f5;
            color: white;
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary);
            box-shadow: var(--shadow-md);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .logo {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid #edf2f7;
            background: var(--primary);
            color: white;
        }

        .logo h2 { font-size: 24px; margin-bottom: 5px; font-weight: 700; letter-spacing: 1px; }
        .logo p { font-size: 13px; opacity: 0.9; font-weight: 400; }

        .nav-menu { padding: 25px 0; flex: 1; }
        
        .nav-item {
            padding: 14px 25px;
            display: flex;
            align-items: center;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            font-weight: 500;
            margin: 4px 0;
            cursor: pointer;
        }

        .nav-item i {
            width: 25px;
            margin-right: 12px;
            font-size: 18px;
            color: white;
        }

        .nav-item:hover {
            background: linear-gradient(90deg, #f7fafc, white);
            color: var(--primary);
            border-left-color: var(--primary);
        }

        .nav-item:hover i { color: white }

        .nav-item.active {
            background: linear-gradient(90deg, #e6f7ff, white);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 600;
        }

        .nav-item.active i { color: var(--primary); }

        .user-info {
            padding: 20px 25px;
            background: #f8fafc;
            border-top: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            margin-top: auto;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--green-dark));
            color: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            box-shadow: var(--shadow-sm);
        }

        .user-name { font-weight: 700; color: var(--dark); font-size: 15px; }
        .user-role {
            font-size: 12px;
            color: white;
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px 35px;
            min-height: 100vh;
            background: var(--primary);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid white;
        }

        .header h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
    
        }


        .header h1 i { color: white; font-size: 32px; }

        /* LOADER */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(3px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* NOTIFICATIONS */
        .notification {
            position: fixed;
            top: 25px;
            right: 25px;
            padding: 16px 25px;
            border-radius: 12px;
            color: white;
            z-index: 9999;
            min-width: 350px;
            animation: slideIn 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-lg);
            font-weight: 500;
        }

        .notification-success { background: linear-gradient(135deg, var(--success), #0d9488); }
        .notification-error { background: linear-gradient(135deg, var(--danger), #b91c1c); }
        .notification-info { background: linear-gradient(135deg, var(--info), #2563eb); }
        .notification-warning { background: linear-gradient(135deg, var(--warning), #d97706); }

        .notification-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            cursor: pointer;
            font-size: 20px;
            margin-left: 15px;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .notification-close:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }

        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }

        /* STATISTIQUES */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 28px;
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .stat-icon.product { background: linear-gradient(135deg, var(--primary), var(--green-dark)); }
        .stat-icon.client { background: linear-gradient(135deg, var(--success), #0d9488); }
        .stat-icon.order { background: linear-gradient(135deg, var(--warning), #d97706); }
        .stat-icon.revenue { background: linear-gradient(135deg, var(--danger), #b91c1c); }

        .stat-info h3 { font-size: 32px; margin-bottom: 5px; color: #1e293b; font-weight: 700; }
        .stat-info p { color: #64748b; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }

        /* SECTIONS */
        .section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
        }

        .dashboard-section { margin-bottom: 45px; }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 22px;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }

        .section-title i { color: var(--primary); font-size: 24px; }
        .section-subtitle { font-size: 14px; color: #64748b; margin-top: 5px; font-weight: 400; }

        /* CARDS GRID */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            border: 1px solid #edf2f7;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: transparent;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #edf2f7;
        }

        .card-title {
            font-size: 18px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .card-title i { color: var(--primary); }
        .card-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .card-body { color: #4a5568; flex: 1; }
        .card-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #edf2f7;
        }

        /* BADGES */
        .badge {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.3px;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }

        /* TABLEAUX */
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .data-table th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #edf2f7;
            color: #4a5568;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .table-btn {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
            color: white;
            text-decoration: none;
        }

        .table-btn-view { background: var(--info); }
        .table-btn-edit { background: var(--warning); }
        .table-btn-delete { background: var(--danger); }
        .table-btn-approve { background: var(--success); }

        .table-btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .stars {
            color: #fbbf24;
        }

        /* CARD ACTIONS */
        .card-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .card-btn {
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            font-weight: 600;
            text-decoration: none;
            color: white;
        }

        .card-btn-primary { background: var(--primary); }
        .card-btn-success { background: var(--success); }
        .card-btn-warning { background: var(--warning); }
        .card-btn-danger { background: var(--danger); }
        .card-btn-info { background: var(--info); }

        .card-btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
            box-shadow: var(--shadow-md);
        }

        /* PRODUIT CARD */
        .product-image {
            width: 100%;
            height: 200px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 15px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #edf2f7;
        }

        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-name { font-size: 17px; font-weight: 700; color: #1e293b; margin-bottom: 5px; }
        .product-category { color: #64748b; font-size: 13px; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
        .product-price { font-size: 20px; font-weight: 800; color: var(--primary); margin: 10px 0; }

        /* COMMANDE CARD */
        .order-customer { font-size: 15px; font-weight: 600; color: #1e293b; margin-bottom: 8px; }
        .order-amount { font-size: 22px; font-weight: 800; color: var(--success); margin: 12px 0; }

        /* CLIENT CARD */
        .client-info {
            display: flex;
            align-items: center;
            margin-bottom: 18px;
        }

        .client-avatar {
            width: 55px;
            height: 55px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), var(--green-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            margin-right: 18px;
            box-shadow: var(--shadow-sm);
        }

        .client-name { font-weight: 700; color: #1e293b; font-size: 16px; }
        .client-email { color: #64748b; font-size: 13px; margin-top: 3px; }

        /* CATEGORIE CARD */
        .category-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary), var(--green-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            margin-bottom: 18px;
            box-shadow: var(--shadow-sm);
        }

        .category-name { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .product-count { color: #64748b; font-size: 14px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

        /* AVIS CARD */
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .review-author { font-weight: 700; color: #1e293b; font-size: 16px; }
        .review-stars { color: #fbbf24; margin: 8px 0; font-size: 14px; }
        .review-text { color: #4a5568; font-size: 14px; line-height: 1.6; margin: 10px 0; }

        /* MESSAGE CARD */
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .message-sender { font-weight: 700; color: #1e293b; font-size: 16px; }
        .message-subject { font-weight: 600; margin: 8px 0; color: #2c3e50; font-size: 15px; }
        .message-excerpt { color: #64748b; font-size: 13px; line-height: 1.5; }

        /* FORMULAIRES */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(45, 61, 72, 0.1);
        }

        .form-full { grid-column: 1 / -1; }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            overflow: auto;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 40px auto;
            padding: 35px;
            border-radius: 24px;
            width: 90%;
            max-width: 850px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalSlideIn 0.4s ease;
        }

        @keyframes modalSlideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #edf2f7;
        }

        .modal-header h2 { color: #1e293b; font-size: 26px; font-weight: 700; }
        .modal-close {
            background: #f1f5f9;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #64748b;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .modal-close:hover {
            background: #e2e8f0;
            color: var(--danger);
            transform: rotate(90deg);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #edf2f7;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .sidebar { width: 90px; }
            .main-content { margin-left: 90px; padding: 25px; }
            .logo h2, .nav-item span, .user-info .user-details { display: none; }
            .nav-item { justify-content: center; padding: 18px; }
            .nav-item i { margin-right: 0; font-size: 22px; }
            .user-info { justify-content: center; }
            .user-avatar { margin-right: 0; }
        }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .cards-grid { grid-template-columns: 1fr; }
            .main-content { padding: 20px; }
            .section { padding: 20px; }
        }
    </style>
</head>
<body>
    <!-- LOADER GLOBAL -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>Mycksoft</h2>
            <p>Administration</p>
        </div>
        
        <div class="nav-menu">
            <?php 
            $menu_items = array(
                'dashboard' => array('fa-tachometer-alt', 'Tableau de bord'),
                'produits' => array('fa-box', 'Produits'),
                'commandes' => array('fa-shopping-cart', 'Commandes'),
                'clients' => array('fa-users', 'Clients'),
                'categories' => array('fa-tags', 'Catégories'),
                'factures' => array('fa-file-invoice-dollar', 'Factures'),
                'chat' => array('fa-comments', 'Support Client'),
                // 'messages' => array('fa-envelope', 'Messages'),
                'avis' => array('fa-star', 'Avis clients'),
                'parametres' => array('fa-cog', 'Paramètres')
            );
            
            foreach ($menu_items as $key => $item): 
                $active = ($section === $key) ? 'active' : ''; ?>
                <a href="#" class="nav-item <?php echo $active; ?>" onclick="loadSection('<?php echo $key; ?>'); return false;">
                    <i class="fas <?php echo $item[0]; ?>"></i>
                    <span><?php echo $item[1]; ?></span>
                    <?php if ($key === 'chat' && ($stats['messages_non_lus'] ?? 0) > 0): ?>
                        <span class="badge badge-danger" style="margin-left: auto; font-size: 10px;"><?php echo $stats['messages_non_lus']; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="user-info">
            <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                <div class="user-role"><i class="fas fa-circle" style="font-size: 8px;"></i> Administrateur</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php 
        $icons = array(
            'dashboard' => 'fa-tachometer-alt', 'produits' => 'fa-box', 'commandes' => 'fa-shopping-cart',
            'clients' => 'fa-users', 'categories' => 'fa-tags', 'factures' => 'fa-file-invoice-dollar',
            'chat' => 'fa-comments', 'messages' => 'fa-envelope', 'avis' => 'fa-star', 'parametres' => 'fa-cog'
        );
        ?>
        
        <div class="header">
            <h1><i class="fas <?php echo isset($icons[$section]) ? $icons[$section] : 'fa-tachometer-alt'; ?>"></i> <span id="pageTitle"><?php echo $page_title; ?></span></h1>
            <a href="?logout=1" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?');">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
        
        <!-- Zone de contenu dynamique -->
        <div id="dynamicContent">
            <?php 
            if ($section === 'chat') {
                displayChatSection($pdo, $admin_id, $_SESSION['csrf_token']);
            } else {
                includeSection($section, $pdo, $stats, $produits_recent, $commandes_recent, $avis_attente, $messages_recent, $produits, $commandes, $clients, $categories, $avis, $messages, $all_categories);
            }
            ?>
        </div>
    </div>
    
    <!-- Modal Ajouter Produit -->
    <div id="modalAjouter" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle" style="color: var(--primary); margin-right: 10px;"></i> Ajouter un produit</h2>
                <button class="modal-close" onclick="closeModal('modalAjouter')">&times;</button>
            </div>
            
            <form id="formAjouterProduit" method="POST" enctype="multipart/form-data" onsubmit="submitForm(event)">
                <input type="hidden" name="ajouter_produit" value="1">
                <div class="form-grid">
                    <div class="form-group"><label>Nom du produit *</label><input type="text" name="nom_produit" class="form-control" required></div>
                    <div class="form-group"><label>Marque</label><input type="text" name="marque" class="form-control"></div>
                    <div class="form-group"><label>Modèle</label><input type="text" name="modele" class="form-control"></div>
                    <div class="form-group"><label>Description courte</label><input type="text" name="description_courte" class="form-control" maxlength="255"></div>
                    <div class="form-group">
                        <label>Catégorie *</label>
                        <select name="id_categorie" class="form-control" required>
                            <option value="">Sélectionnez une catégorie</option>
                            <?php foreach ($all_categories as $categorie): ?>
                                <option value="<?php echo $categorie['id_categorie']; ?>"><?php echo htmlspecialchars($categorie['nom_categorie']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Prix (FCFA) *</label><input type="number" name="prix" class="form-control" step="0.01" min="0" required></div>
                    <div class="form-group"><label>Prix promotionnel</label><input type="number" name="prix_promotion" class="form-control" step="0.01" min="0"></div>
                    <div class="form-group"><label>Stock *</label><input type="number" name="stock" class="form-control" min="0" required></div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="est_en_promotion" value="1" style="width: 18px; height: 18px;"> 
                            <span>En promotion</span>
                        </label>
                    </div>
                    <div class="form-group form-full"><label>Description complète *</label><textarea name="description" class="form-control" rows="5" required></textarea></div>
                    <div class="form-group form-full">
                        <label>Images du produit</label>
                        <input type="file" name="image[]" class="form-control" multiple accept="image/*" style="padding: 12px;">
                        <small style="color: #64748b; margin-top: 8px; display: block;"><i class="fas fa-info-circle"></i> Sélectionnez une ou plusieurs images. La première image sera l'image principale.</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="closeModal('modalAjouter')">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Ajouter le produit</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Ajouter Catégorie -->
    <div id="modalAjouterCategorie" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle" style="color: var(--primary); margin-right: 10px;"></i> Ajouter une catégorie</h2>
                <button class="modal-close" onclick="closeModal('modalAjouterCategorie')">&times;</button>
            </div>
            
            <form id="formAjouterCategorie" method="POST" enctype="multipart/form-data" onsubmit="submitForm(event)">
                <input type="hidden" name="ajouter_categorie" value="1">
                <div class="form-grid">
                    <div class="form-group"><label>Nom de la catégorie *</label><input type="text" name="nom_categorie" class="form-control" required></div>
                    <div class="form-group"><label>Catégorie parente</label>
                        <select name="id_categorie_parent" class="form-control">
                            <option value="">Aucune (catégorie principale)</option>
                            <?php foreach ($all_categories as $categorie): ?>
                                <option value="<?php echo $categorie['id_categorie']; ?>"><?php echo htmlspecialchars($categorie['nom_categorie']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-full"><label>Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <div class="form-group form-full">
                        <label>Image de la catégorie</label>
                        <input type="file" name="image_categorie" class="form-control" accept="image/*">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="closeModal('modalAjouterCategorie')">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Ajouter la catégorie</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Voir Produit -->
    <div id="modalVoirProduit" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-eye" style="color: var(--primary); margin-right: 10px;"></i> Détails du produit</h2>
                <button class="modal-close" onclick="closeModal('modalVoirProduit')">&times;</button>
            </div>
            <div class="modal-body" id="productDetailsContainer">
                <p>Chargement...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="closeModal('modalVoirProduit')">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Modal Modifier Produit -->
    <div id="modalModifierProduit" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit" style="color: var(--primary); margin-right: 10px;"></i> Modifier le produit</h2>
                <button class="modal-close" onclick="closeModal('modalModifierProduit')">&times;</button>
            </div>
            <form id="formModifierProduit" method="POST" onsubmit="submitForm(event)">
                <input type="hidden" name="modifier_produit" value="1">
                <input type="hidden" name="id_produit" id="editProductId" value="">
                <div class="form-grid">
                    <div class="form-group"><label>Nom du produit *</label><input type="text" name="nom_produit" id="editProductName" class="form-control" required></div>
                    <div class="form-group"><label>Marque</label><input type="text" name="marque" id="editProductBrand" class="form-control"></div>
                    <div class="form-group"><label>Modèle</label><input type="text" name="modele" id="editProductModel" class="form-control"></div>
                    <div class="form-group"><label>Description courte</label><input type="text" name="description_courte" id="editProductShortDescription" class="form-control"></div>
                    <div class="form-group"><label>Catégorie *</label>
                        <select name="id_categorie" id="editProductCategory" class="form-control" required>
                            <option value="">Sélectionnez une catégorie</option>
                            <?php foreach ($all_categories as $categorie): ?>
                                <option value="<?php echo $categorie['id_categorie']; ?>"><?php echo htmlspecialchars($categorie['nom_categorie']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Prix (FCFA) *</label><input type="number" name="prix" id="editProductPrice" class="form-control" step="0.01" min="0" required></div>
                    <div class="form-group"><label>Prix promotionnel</label><input type="number" name="prix_promotion" id="editProductPromoPrice" class="form-control" step="0.01" min="0"></div>
                    <div class="form-group"><label>Stock *</label><input type="number" name="stock" id="editProductStock" class="form-control" min="0" required></div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="est_en_promotion" id="editProductOnSale" value="1" style="width: 18px; height: 18px;"> 
                            <span>En promotion</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="est_actif" id="editProductActive" value="1" style="width: 18px; height: 18px;"> 
                            <span>Produit actif</span>
                        </label>
                    </div>
                    <div class="form-group form-full"><label>Description complète *</label><textarea name="description" id="editProductDescription" class="form-control" rows="5" required></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="closeModal('modalModifierProduit')">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Voir Client -->
    <div id="modalVoirClient" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user" style="color: var(--primary); margin-right: 10px;"></i> Détails du client</h2>
                <button class="modal-close" onclick="closeModal('modalVoirClient')">&times;</button>
            </div>
            <div class="modal-body" id="clientDetailsContainer">
                <p>Chargement...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="closeModal('modalVoirClient')">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Modal Modifier Client -->
    <div id="modalModifierClient" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit" style="color: var(--primary); margin-right: 10px;"></i> Modifier le client</h2>
                <button class="modal-close" onclick="closeModal('modalModifierClient')">&times;</button>
            </div>
            <form id="formModifierClient" method="POST" onsubmit="submitForm(event)">
                <input type="hidden" name="modifier_client" value="1">
                <input type="hidden" name="id_utilisateur" id="editClientId" value="">
                <div class="form-grid">
                    <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" id="editClientFirstName" class="form-control" required></div>
                    <div class="form-group"><label>Nom *</label><input type="text" name="nom" id="editClientLastName" class="form-control" required></div>
                    <div class="form-group"><label>Email *</label><input type="email" name="email" id="editClientEmail" class="form-control" required></div>
                    <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" id="editClientPhone" class="form-control"></div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="est_actif" id="editClientActive" value="1" style="width: 18px; height: 18px;"> 
                            <span>Client actif</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="closeModal('modalModifierClient')">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ========== CONFIGURATION AJAX ==========
        var ADMIN_URL = window.location.pathname;
        var currentSection = '<?php echo $section; ?>';
        
        // ========== GESTIONNAIRE DE CHARGEMENT ==========
        function showLoader() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoader() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // ========== NAVIGATION AJAX ==========
        function loadSection(section) {
            if (section === currentSection) return;
            
            showLoader();
            
            var navItems = document.querySelectorAll('.nav-item');
            for (var i = 0; i < navItems.length; i++) {
                navItems[i].classList.remove('active');
            }
            
            for (var i = 0; i < navItems.length; i++) {
                var onclick = navItems[i].getAttribute('onclick');
                if (onclick && onclick.includes(section)) {
                    navItems[i].classList.add('active');
                }
            }
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', ADMIN_URL + '?section=' + section, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    document.getElementById('dynamicContent').innerHTML = xhr.responseText;
                    updatePageTitle(section);
                    currentSection = section;
                    history.pushState({section: section}, '', '?section=' + section);
                    hideLoader();
                    
                    if (section === 'chat' && typeof initChatScripts === 'function') {
                        initChatScripts();
                    }
                } else {
                    console.error('Erreur:', xhr.status);
                    showNotification('Erreur lors du chargement de la page', 'error');
                    hideLoader();
                }
            };
            
            xhr.onerror = function() {
                console.error('Erreur réseau');
                showNotification('Erreur lors du chargement de la page', 'error');
                hideLoader();
            };
            
            xhr.send();
        }
        
        // ========== MISE À JOUR DU TITRE ==========
        function updatePageTitle(section) {
            var titles = {
                'dashboard': 'Dashboard Admin - Mycksoft',
                'produits': 'Gestion des produits - Mycksoft',
                'commandes': 'Gestion des commandes - Mycksoft',
                'clients': 'Gestion des clients - Mycksoft',
                'categories': 'Gestion des catégories - Mycksoft',
                'chat': 'Support client - Mycksoft',
                'avis': 'Gestion des avis - Mycksoft',
                'messages': 'Messages de contact - Mycksoft',
                'parametres': 'Paramètres - Mycksoft'
            };
            document.title = titles[section] || 'Dashboard Admin - Mycksoft';
            var pageTitleEl = document.getElementById('pageTitle');
            if (pageTitleEl) pageTitleEl.textContent = titles[section] || 'Dashboard Admin - Mycksoft';
        }
        
        // ========== GESTION DES NOTIFICATIONS ==========
        function showNotification(message, type) {
            if (!type) type = 'success';
            
            var oldNotifications = document.querySelectorAll('.notification');
            for (var i = 0; i < oldNotifications.length; i++) {
                oldNotifications[i].remove();
            }
            
            var notification = document.createElement('div');
            notification.className = 'notification notification-' + type;
            
            var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            notification.innerHTML = '<span><i class="fas ' + icon + '"></i> ' + message + '</span>' +
                '<button class="notification-close" onclick="this.parentElement.remove()">&times;</button>';
            
            document.body.appendChild(notification);
            
            setTimeout(function() {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOut 0.3s ease';
                    setTimeout(function() { 
                        if (notification.parentNode) notification.remove(); 
                    }, 280);
                }
            }, 5000);
        }
        
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        
        function closeNotification() {
            var notification = document.getElementById('notification');
            if (notification) {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(function() { 
                    if (notification.parentNode) notification.remove(); 
                }, 280);
            }
        }
        
        // ========== GESTION DES MODALS ==========
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // ========== SOUMISSION DE FORMULAIRES EN AJAX ==========
        function submitForm(event) {
            event.preventDefault();
            var form = event.target;
            var formData = new FormData(form);
            var modal = form.closest('.modal');
            
            showLoader();
            if (modal) closeModal(modal.id);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ADMIN_URL, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    var xhr2 = new XMLHttpRequest();
                    xhr2.open('GET', ADMIN_URL + '?section=' + currentSection, true);
                    xhr2.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    
                    xhr2.onload = function() {
                        if (xhr2.status >= 200 && xhr2.status < 400) {
                            document.getElementById('dynamicContent').innerHTML = xhr2.responseText;
                            showNotification('Opération réussie !', 'success');
                            hideLoader();
                            
                            if (currentSection === 'chat' && typeof initChatScripts === 'function') {
                                initChatScripts();
                            }
                        }
                    };
                    
                    xhr2.send();
                } else {
                    console.error('Erreur:', xhr.status);
                    showNotification('Erreur lors de l\'opération', 'error');
                    hideLoader();
                }
            };
            
            xhr.onerror = function() {
                console.error('Erreur réseau');
                showNotification('Erreur lors de l\'opération', 'error');
                hideLoader();
            };
            
            xhr.send(formData);
        }
        
        // ========== FONCTIONS DU CHAT ==========
        function initChatScripts() {
            const messagesArea = document.getElementById('messages-area');
            if (messagesArea) {
                scrollToBottom();
            }
            
            // Recherche de conversations
            const searchInput = document.getElementById('conversationSearch');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchText = this.value.toLowerCase();
                    const items = document.querySelectorAll('.conversation-item');
                    
                    items.forEach(item => {
                        const name = item.querySelector('h4')?.textContent.toLowerCase() || '';
                        if (name.includes(searchText)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
            
            // Upload de fichier
            const fileUpload = document.getElementById('fileUpload');
            if (fileUpload) {
                fileUpload.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        document.getElementById('fileName').textContent = file.name;
                        document.getElementById('filePreview').style.display = 'flex';
                        document.getElementById('messageType').value = file.type.startsWith('image/') ? 'image' : 'file';
                    }
                });
            }
        }
        
        function scrollToBottom() {
            const messagesArea = document.getElementById('messages-area');
            if (messagesArea) {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
        }
        
        function removeFile() {
            document.getElementById('fileUpload').value = '';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('messageType').value = 'text';
        }
        
        function sendMessage(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('send_message', '1');
            
            const messageInput = document.getElementById('messageInput');
            const fileUpload = document.getElementById('fileUpload');
            
            if (!messageInput.value.trim() && !fileUpload.value) {
                alert('Veuillez écrire un message ou sélectionner un fichier');
                return;
            }
            
            fetch(ADMIN_URL + '?section=chat&user=<?php echo isset($_GET['user']) ? $_GET['user'] : 0; ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recharger les messages
                    loadSection('chat&user=<?php echo isset($_GET['user']) ? $_GET['user'] : 0; ?>');
                    messageInput.value = '';
                    removeFile();
                } else {
                    alert('Erreur: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'envoi du message');
            });
        }
        
        // ========== ACTIONS SUR LES PRODUITS ==========
        function viewProduct(id) {
            showLoader();
            fetch(ADMIN_URL + '?get_product=' + id, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => response.json())
            .then(data => {
                hideLoader();
                if (!data.success) {
                    showNotification(data.error || 'Produit introuvable', 'error');
                    return;
                }
                var p = data.produit;
                var html = '<div style="display: grid; gap: 15px;">';
                html += '<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;"><div style="flex: 1;"><strong>Nom :</strong> ' + escapeHtml(p.nom_produit) + '</div>';
                html += '<div><strong>Catégorie :</strong> ' + escapeHtml(p.nom_categorie || 'Non définie') + '</div></div>';
                html += '<div><strong>Prix :</strong> ' + Number(p.prix).toLocaleString('fr-FR') + ' FCFA';
                if (p.prix_promotion) html += ' <span style="color: #d63031;">(' + Number(p.prix_promotion).toLocaleString('fr-FR') + ' FCFA promo)</span>';
                html += '</div>';
                html += '<div><strong>Stock :</strong> ' + Number(p.stock) + '</div>';
                html += '<div><strong>Statut :</strong> ' + (p.est_actif ? 'Actif' : 'Inactif') + '</div>';
                html += '<div><strong>Description courte :</strong> ' + escapeHtml(p.description_courte || '-') + '</div>';
                html += '<div><strong>Description complète :</strong><div style="margin-top: 8px; padding: 14px; background: #f8fafc; border-radius: 8px; color: #1e293b;">' + escapeHtml(p.description || '-') + '</div></div>';
                html += '</div>';
                document.getElementById('productDetailsContainer').innerHTML = html;
                openModal('modalVoirProduit');
            })
            .catch(function() {
                hideLoader();
                showNotification('Erreur lors du chargement du produit', 'error');
            });
        }
        
        function editProduct(id) {
            showLoader();
            fetch(ADMIN_URL + '?get_product=' + id, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => response.json())
            .then(data => {
                hideLoader();
                if (!data.success) {
                    showNotification(data.error || 'Produit introuvable', 'error');
                    return;
                }
                var p = data.produit;
                document.getElementById('editProductId').value = p.id_produit;
                document.getElementById('editProductName').value = p.nom_produit || '';
                document.getElementById('editProductBrand').value = p.marque || '';
                document.getElementById('editProductModel').value = p.modele || '';
                document.getElementById('editProductShortDescription').value = p.description_courte || '';
                document.getElementById('editProductCategory').value = p.id_categorie || '';
                document.getElementById('editProductPrice').value = p.prix || 0;
                document.getElementById('editProductPromoPrice').value = p.prix_promotion || '';
                document.getElementById('editProductStock').value = p.stock || 0;
                document.getElementById('editProductOnSale').checked = parseInt(p.est_en_promotion) === 1;
                document.getElementById('editProductActive').checked = parseInt(p.est_actif) === 1;
                document.getElementById('editProductDescription').value = p.description || '';
                openModal('modalModifierProduit');
            })
            .catch(function() {
                hideLoader();
                showNotification('Erreur lors du chargement du produit', 'error');
            });
        }
        
        function deleteProduct(id) {
            if (confirm('🗑️ Voulez-vous vraiment désactiver ce produit ?')) {
                showLoader();
                
                var xhr = new XMLHttpRequest();
                xhr.open('GET', ADMIN_URL + '?section=produits&supprimer_produit=' + id, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        loadSection('produits');
                        showNotification('Produit désactivé avec succès', 'success');
                    } else {
                        showNotification('Erreur lors de la désactivation', 'error');
                        hideLoader();
                    }
                };
                
                xhr.onerror = function() {
                    showNotification('Erreur lors de la désactivation', 'error');
                    hideLoader();
                };
                
                xhr.send();
            }
        }
        
        // ========== FILTRES PRODUITS ==========
        function filterProducts() {
            var searchInput = document.getElementById('searchProduct');
            var searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            
            var productCards = document.querySelectorAll('.product-card');
            for (var i = 0; i < productCards.length; i++) {
                var card = productCards[i];
                var show = true;
                
                if (searchTerm) {
                    var titleEl = card.querySelector('.card-title');
                    var productName = titleEl ? titleEl.textContent.toLowerCase() : '';
                    show = show && productName.indexOf(searchTerm) !== -1;
                }
                
                card.style.display = show ? 'block' : 'none';
            }
        }
        
        // ========== INITIALISATION DES SCRIPTS PAR SECTION ==========
        function initSectionScripts(section) {
            if (section === 'produits') {
                var searchInput = document.getElementById('searchProduct');
                if (searchInput) {
                    searchInput.addEventListener('keyup', filterProducts);
                }
                
                var filterCategory = document.getElementById('filterCategory');
                if (filterCategory) {
                    filterCategory.addEventListener('change', filterProducts);
                }
                
                var filterStatus = document.getElementById('filterStatus');
                if (filterStatus) {
                    filterStatus.addEventListener('change', filterProducts);
                }
            } else if (section === 'chat') {
                initChatScripts();
            }
        }
        
        // ========== GESTION DU BOUTON PRÉCÉDENT ==========
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.section) {
                loadSection(event.state.section);
            }
        });
        
        // ========== INITIALISATION AU CHARGEMENT ==========
        document.addEventListener('DOMContentLoaded', function() {
            initSectionScripts('<?php echo $section; ?>');
            setTimeout(closeNotification, 5000);
            
            // Rafraîchir les messages non lus toutes les 30 secondes
            <?php if ($section === 'chat'): ?>
            setInterval(function() {
                fetch(ADMIN_URL + '?section=chat&check_unread=1', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Mettre à jour les badges
                    const unreadCount = data.total_unread || 0;
                    const badge = document.querySelector('.nav-item[onclick*="chat"] .badge');
                    if (badge) {
                        if (unreadCount > 0) {
                            badge.textContent = unreadCount;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });
            }, 30000);
            <?php endif; ?>
        });
        
        // ========== FONCTIONS DE GESTION DES COMMANDES ==========
        function viewOrder(id) {
            alert('📦 Affichage de la commande #' + id);
        }
        
        // ========== FONCTIONS DE GESTION DES CLIENTS ==========
        function viewClient(id) {
            showLoader();
            fetch(ADMIN_URL + '?get_client=' + id, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => response.json())
            .then(data => {
                hideLoader();
                if (!data.success) {
                    showNotification(data.error || 'Client introuvable', 'error');
                    return;
                }
                var u = data.client;
                var html = '<div style="display: grid; gap: 15px;">';
                html += '<div><strong>Nom :</strong> ' + escapeHtml((u.prenom || '') + ' ' + (u.nom || '')) + '</div>';
                html += '<div><strong>Email :</strong> ' + escapeHtml(u.email || '-') + '</div>';
                html += '<div><strong>Téléphone :</strong> ' + escapeHtml(u.telephone || '-') + '</div>';
                html += '<div><strong>Date d\'inscription :</strong> ' + escapeHtml(u.date_inscription || '-') + '</div>';
                html += '<div><strong>Nombre de commandes :</strong> ' + Number(u.nb_commandes || 0) + '</div>';
                html += '<div><strong>Statut :</strong> ' + (u.est_actif ? 'Actif' : 'Inactif') + '</div>';
                html += '</div>';
                document.getElementById('clientDetailsContainer').innerHTML = html;
                openModal('modalVoirClient');
            })
            .catch(function() {
                hideLoader();
                showNotification('Erreur lors du chargement du client', 'error');
            });
        }
        
        function editClient(id) {
            showLoader();
            fetch(ADMIN_URL + '?get_client=' + id, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(response => response.json())
            .then(data => {
                hideLoader();
                if (!data.success) {
                    showNotification(data.error || 'Client introuvable', 'error');
                    return;
                }
                var u = data.client;
                document.getElementById('editClientId').value = u.id_utilisateur;
                document.getElementById('editClientFirstName').value = u.prenom || '';
                document.getElementById('editClientLastName').value = u.nom || '';
                document.getElementById('editClientEmail').value = u.email || '';
                document.getElementById('editClientPhone').value = u.telephone || '';
                document.getElementById('editClientActive').checked = parseInt(u.est_actif) === 1;
                openModal('modalModifierClient');
            })
            .catch(function() {
                hideLoader();
                showNotification('Erreur lors du chargement du client', 'error');
            });
        }
        
        function toggleClientStatus(clientId, currentStatus) {
            var action = currentStatus ? 'Désactiver' : 'Activer';
            if (confirm(action + ' ce client ?')) {
                showLoader();
                
                fetch(ADMIN_URL + '?section=clients&toggle_client=' + clientId, {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(response => response.json())
                .then(data => {
                    hideLoader();
                    if (data.success) {
                        loadSection('clients');
                        showNotification('Client ' + action.toLowerCase() + ' avec succès', 'success');
                    } else {
                        showNotification(data.error || 'Erreur lors de l\'opération', 'error');
                    }
                })
                .catch(function() {
                    hideLoader();
                    showNotification('Erreur lors de l\'opération', 'error');
                });
            }
        }
        
        // ========== FONCTIONS DE GESTION DES CATÉGORIES ==========
        function editCategory(id) {
            alert('✏️ Modification de la catégorie #' + id);
        }
        
        function deleteCategory(id) {
            if (confirm('🗑️ Voulez-vous vraiment supprimer cette catégorie ?')) {
                showLoader();
                
                var xhr = new XMLHttpRequest();
                xhr.open('GET', ADMIN_URL + '?section=categories&supprimer_categorie=' + id, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        loadSection('categories');
                        showNotification('Catégorie supprimée avec succès', 'success');
                    } else {
                        showNotification('Erreur lors de la suppression', 'error');
                        hideLoader();
                    }
                };
                
                xhr.onerror = function() {
                    showNotification('Erreur lors de la suppression', 'error');
                    hideLoader();
                };
                
                xhr.send();
            }
        }
        
        // ========== FONCTIONS DE GESTION DES AVIS ==========
        function deleteReview(id) {
            if (confirm('🗑️ Voulez-vous vraiment supprimer cet avis ?')) {
                showLoader();
                
                var xhr = new XMLHttpRequest();
                xhr.open('GET', ADMIN_URL + '?section=avis&delete_review=' + id, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        loadSection('avis');
                        showNotification('Avis supprimé avec succès', 'success');
                    } else {
                        showNotification('Erreur lors de la suppression', 'error');
                        hideLoader();
                    }
                };
                
                xhr.onerror = function() {
                    showNotification('Erreur lors de la suppression', 'error');
                    hideLoader();
                };
                
                xhr.send();
            }
        }
        
        // ========== FONCTIONS DE GESTION DES MESSAGES ==========
        function viewMessage(id) {
            alert('📧 Affichage du message #' + id);
        }
        
        function deleteMessage(id) {
            if (confirm('🗑️ Voulez-vous vraiment supprimer ce message ?')) {
                showLoader();
                
                var xhr = new XMLHttpRequest();
                xhr.open('GET', ADMIN_URL + '?section=messages&delete_message=' + id, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        loadSection('messages');
                        showNotification('Message supprimé avec succès', 'success');
                    } else {
                        showNotification('Erreur lors de la suppression', 'error');
                        hideLoader();
                    } 
                };
                
                xhr.onerror = function() {
                    showNotification('Erreur lors de la suppression', 'error');
                    hideLoader();
                };
                
                xhr.send();
            }
        }
        
        // ========== FONCTIONS DE GESTION DES PARAMÈTRES ==========
        function saveSettings() {
            var siteName = document.getElementById('site_name');
            var contactEmail = document.getElementById('contact_email');
            var phone = document.getElementById('phone');
            var address = document.getElementById('address');
            var description = document.getElementById('site_description');
            var maintenanceRadios = document.querySelectorAll('input[name="maintenance"]:checked');
            
            var settings = {
                site_name: siteName ? siteName.value : 'Mycksoft',
                contact_email: contactEmail ? contactEmail.value : '',
                phone: phone ? phone.value : '',
                address: address ? address.value : '',
                description: description ? description.value : '',
                maintenance: maintenanceRadios.length > 0 ? maintenanceRadios[0].value : '0'
            };
            
            showLoader();
            
            setTimeout(function() {
                showNotification('Paramètres enregistrés avec succès', 'success');
                hideLoader();
            }, 1000);
        }
    </script>
</body>
</html>
