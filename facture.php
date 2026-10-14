<?php
require_once 'includes/config.php';
require_once 'classes/Panier.php';


// ============ FONCTIONS DE GESTION DES FACTURES ============

// Générer un numéro de facture unique
function genererNumeroFacture($pdo) {
    $annee = date('Y');
    $mois = date('m');
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM factures WHERE YEAR(date_creation) = YEAR(CURDATE())");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $numero = $row['total'] + 1;
    return "FACT-" . $annee . $mois . str_pad($numero, 4, '0', STR_PAD_LEFT);
}

// Récupérer toutes les factures
function getAllFactures($pdo) {
    // $stmt = $pdo->query("
        // SELECT f.*, c.nom as client_nom
        // FROM factures f
        // LEFT JOIN clients c ON f.client_id = c.id
        // ORDER BY f.date_emission DESC
    // ");
    // return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer une facture avec ses détails
function getFactureById($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT f.*, c.nom as client_nom, c.email, c.telephone, c.adresse
        FROM factures f
        LEFT JOIN clients c ON f.client_id = c.id
        WHERE f.id = ?
    ");

    $facture = $stmt->fetch(PDO::FETCH_ASSOC);

    if($facture) {
        $stmt = $pdo->prepare("SELECT * FROM lignes_facture WHERE facture_id = ? ORDER BY id");
        $stmt->execute([$id]);
        $facture['lignes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $facture;
}

// Récupérer les données d'une commande pour générer la facture
function getCommandeFactureData($pdo, $commande_id, $user_id = null) {
    $sql = "
        SELECT c.*, u.nom, u.prenom, u.email, u.telephone, u.adresse,
               p.reference_paiement, p.date_paiement, p.mode_paiement
        FROM commande c
        JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
        LEFT JOIN paiement p ON c.id_commande = p.id_commande
        WHERE c.id_commande = ?
    ";
    $params = [$commande_id];

    // Ne filtrer par user_id que si l'utilisateur n'est pas administrateur
    if ($user_id && !isAdmin()) {
        $sql .= " AND c.id_utilisateur = ?";
        $params[] = $user_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($commande) {
        // Récupérer les articles de la commande
        $stmt_articles = $pdo->prepare("
            SELECT lc.*, p.nom_produit, p.marque, p.modele, p.prix,
                   (SELECT ip.url_image
                    FROM image_produit ip
                    WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1
                    LIMIT 1) as image_url
            FROM ligne_commande lc
            JOIN produit p ON lc.id_produit = p.id_produit
            WHERE lc.id_commande = ?
        ");
        $stmt_articles->execute([$commande_id]);
        $commande['articles'] = $stmt_articles->fetchAll(PDO::FETCH_ASSOC);
    }

    return $commande;
}

// Générer le HTML de la facture pour une commande
function genererFactureCommandeHTML($commande) {
    $numero_facture = "CMD-" . str_pad($commande['id_commande'], 6, '0', STR_PAD_LEFT);
    $date_facture = $commande['date_paiement'] ? date('d/m/Y', strtotime($commande['date_paiement'])) : date('d/m/Y');
    $nom_client = $commande['prenom'] . ' ' . $commande['nom'];

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Facture <?php echo $numero_facture; ?> - Mycksoft</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 20px;
                background: #f5f5f5;
                color: #333;
                line-height: 1.6;
            }
            .facture-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 300;
            }
            .header .subtitle {
                margin-top: 10px;
                opacity: 0.9;
                font-size: 16px;
            }
            .content {
                padding: 40px;
            }
            .facture-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 40px;
                padding-bottom: 20px;
                border-bottom: 2px solid #667eea;
            }
            .entreprise-info, .client-info {
                flex: 1;
            }
            .entreprise-info {
                padding-right: 20px;
            }
            .client-info {
                padding-left: 20px;
                text-align: right;
            }
            .info-title {
                font-weight: bold;
                margin-bottom: 10px;
                color: #667eea;
            }
            .facture-details {
                margin-bottom: 30px;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
            }
            .detail-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
            }
            .detail-label {
                font-weight: 500;
            }
            .detail-value {
                font-weight: bold;
            }
            .articles-table {
                width: 100%;
                border-collapse: collapse;
                margin: 30px 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .articles-table th {
                background: #667eea;
                color: white;
                padding: 15px;
                text-align: left;
                font-weight: 600;
            }
            .articles-table td {
                padding: 15px;
                border-bottom: 1px solid #e9ecef;
            }
            .articles-table tbody tr:hover {
                background: #f8f9fa;
            }
            .article-image {
                width: 50px;
                height: 50px;
                object-fit: cover;
                border-radius: 4px;
            }
            .total-section {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin-top: 30px;
            }
            .total-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid #e9ecef;
            }
            .total-row.final {
                border-bottom: none;
                border-top: 2px solid #667eea;
                padding-top: 20px;
                font-size: 18px;
                font-weight: bold;
                color: #667eea;
            }
            .footer {
                background: #343a40;
                color: white;
                padding: 30px;
                text-align: center;
                margin-top: 40px;
            }
            .footer p {
                margin: 5px 0;
                font-size: 14px;
            }
            .print-button {
                display: none;
            }
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                .facture-container {
                    box-shadow: none;
                    border-radius: 0;
                }
                .print-button {
                    display: none !important;
                }
            }
        </style>
    </head>
    <body>
        <div class="facture-container">
            <div class="header">
                <h1>FACTURE</h1>
                <div class="subtitle">Mycksoft Informatique - Solutions Technologiques</div>
            </div>

            <div class="content">
                <div class="facture-info">
                    <div class="entreprise-info">
                        <div class="info-title">Émetteur</div>
                        <strong>Mycksoft Informatique</strong><br>
                        Yaoundé, Cameroun<br>
                        Tél: +237 6XX XXX XXX<br>
                        Email: contact@mycksoft.cm<br>
                        Site: www.mycksoft.cm
                    </div>
                    <div class="client-info">
                        <div class="info-title">Client</div>
                        <strong><?php echo htmlspecialchars($nom_client); ?></strong><br>
                        <?php echo htmlspecialchars($commande['adresse'] ?? 'Adresse non spécifiée'); ?><br>
                        Tél: <?php echo htmlspecialchars($commande['telephone'] ?? 'N/A'); ?><br>
                        Email: <?php echo htmlspecialchars($commande['email']); ?>
                    </div>
                </div>

                <div class="facture-details">
                    <div class="detail-row">
                        <span class="detail-label">N° de Facture:</span>
                        <span class="detail-value"><?php echo $numero_facture; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">N° de Commande:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($commande['numero_commande']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date d'émission:</span>
                        <span class="detail-value"><?php echo $date_facture; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Mode de paiement:</span>
                        <span class="detail-value"><?php echo ucfirst($commande['mode_paiement'] ?? 'Mobile Money'); ?></span>
                    </div>
                    <?php if ($commande['reference_paiement']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Référence paiement:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($commande['reference_paiement']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <table class="articles-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Produit</th>
                            <th>Quantité</th>
                            <th>Prix Unit.</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_general = 0;
                        foreach ($commande['articles'] as $article):
                            $total_article = $article['prix'] * $article['quantite'];
                            $total_general += $total_article;
                        ?>
                        <tr>
                            <td>
                                <?php if ($article['image']): ?>
                                    <img src="../uploads/produits/<?php echo htmlspecialchars($article['image']); ?>"
                                         alt="<?php echo htmlspecialchars($article['nom_produit']); ?>"
                                         class="article-image">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                        <span style="font-size: 20px;">📦</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($article['nom_produit']); ?></strong>
                                <?php if ($article['marque'] || $article['modele']): ?>
                                    <br><small style="color: #666;">
                                        <?php echo htmlspecialchars($article['marque'] . ' ' . $article['modele']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $article['quantite']; ?></td>
                            <td><?php echo number_format($article['prix'], 0, ',', ' '); ?> FCFA</td>
                            <td><strong><?php echo number_format($total_article, 0, ',', ' '); ?> FCFA</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total-section">
                    <div class="total-row final">
                        <span>MONTANT TOTAL TTC:</span>
                        <span><?php echo number_format($total_general, 0, ',', ' '); ?> FCFA</span>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 40px;">
                    <button onclick="window.print()" class="print-button" style="background: #667eea; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px;">
                        🖨️ Imprimer la facture
                    </button>
                </div>
            </div>

            <div class="footer">
                <p><strong>Mycksoft Informatique</strong> - Votre partenaire technologique de confiance</p>
                <p>Merci pour votre confiance et à bientôt !</p>
                <p style="font-size: 12px; opacity: 0.8;">
                    Cette facture a été générée automatiquement le <?php echo date('d/m/Y à H:i'); ?>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Créer une nouvelle facture
function creerFacture($pdo, $data, $lignes) {
    $pdo->beginTransaction();
    try {
        $numero_facture = genererNumeroFacture($pdo);
        
        $stmt = $pdo->prepare("
            INSERT INTO factures (client_id, numero_facture, date_emission, date_echeance, notes, statut) 
            VALUES (?, ?, ?, ?, ?, 'impayée')
        ");
        $stmt->execute([
            $data['client_id'],
            $numero_facture,
            $data['date_emission'],
            $data['date_echeance'],
            $data['notes']
        ]);
        $facture_id = $pdo->lastInsertId();
        
        $total_ht = 0;
        $total_ttc = 0;
        
        foreach($lignes as $ligne) {
            $total_ligne_ht = $ligne['quantite'] * $ligne['prix_unitaire'];
            $total_ligne_ttc = $total_ligne_ht * (1 + ($ligne['tva'] / 100));
            
            $total_ht += $total_ligne_ht;
            $total_ttc += $total_ligne_ttc;
            
            $stmt = $pdo->prepare("
                INSERT INTO lignes_facture (facture_id, description, quantite, prix_unitaire_ht, tva, total_ht, total_ttc)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $facture_id,
                $ligne['description'],
                $ligne['quantite'],
                $ligne['prix_unitaire'],
                $ligne['tva'],
                $total_ligne_ht,
                $total_ligne_ttc
            ]);
        }
        
        $tva_total = $total_ttc - $total_ht;
        $stmt = $pdo->prepare("UPDATE factures SET total_ht = ?, tva = ?, total_ttc = ? WHERE id = ?");
        $stmt->execute([$total_ht, $tva_total, $total_ttc, $facture_id]);
        
        $pdo->commit();
        return ['success' => true, 'id' => $facture_id, 'numero' => $numero_facture];
    } catch(Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Modifier une facture
function modifierFacture($pdo, $id, $data, $lignes) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE factures 
            SET client_id = ?, date_emission = ?, date_echeance = ?, notes = ?, statut = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['client_id'],
            $data['date_emission'],
            $data['date_echeance'],
            $data['notes'],
            $data['statut'],
            $id
        ]);
        
        // Supprimer les anciennes lignes
        $stmt = $pdo->prepare("DELETE FROM lignes_facture WHERE facture_id = ?");
        $stmt->execute([$id]);
        
        $total_ht = 0;
        $total_ttc = 0;
        
        foreach($lignes as $ligne) {
            $total_ligne_ht = $ligne['quantite'] * $ligne['prix_unitaire'];
            $total_ligne_ttc = $total_ligne_ht * (1 + ($ligne['tva'] / 100));
            
            $total_ht += $total_ligne_ht;
            $total_ttc += $total_ligne_ttc;
            
            $stmt = $pdo->prepare("
                INSERT INTO lignes_facture (facture_id, description, quantite, prix_unitaire_ht, tva, total_ht, total_ttc)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id,
                $ligne['description'],
                $ligne['quantite'],
                $ligne['prix_unitaire'],
                $ligne['tva'],
                $total_ligne_ht,
                $total_ligne_ttc
            ]);
        }
        
        $tva_total = $total_ttc - $total_ht;
        $stmt = $pdo->prepare("UPDATE factures SET total_ht = ?, tva = ?, total_ttc = ? WHERE id = ?");
        $stmt->execute([$total_ht, $tva_total, $total_ttc, $id]);
        
        $pdo->commit();
        return ['success' => true];
    } catch(Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Supprimer une facture
function supprimerFacture($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM factures WHERE id = ?");
    return $stmt->execute([$id]);
}

// Changer le statut d'une facture
function changerStatutFacture($pdo, $id, $statut) {
    $stmt = $pdo->prepare("UPDATE factures SET statut = ? WHERE id = ?");
    return $stmt->execute([$statut, $id]);
}

// Obtenir les statistiques des factures
function getStatistiquesFactures($pdo) {
    $stats = [];
    
    // $stmt = $pdo->query("SELECT COUNT(*) as total FROM factures");
    // $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // $stmt = $pdo->query("SELECT SUM(total_ttc) as total_montant FROM factures");
    // $stats['total_montant'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_montant'] ?? 0;
    
    // $stmt = $pdo->query("SELECT COUNT(*) as total_payee FROM factures WHERE statut = 'payée'");
    // $stats['payee'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_payee'];
    // 
    // $stmt = $pdo->query("SELECT COUNT(*) as total_impayee FROM factures WHERE statut = 'impayée'");
    // $stats['impayee'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_impayee'];
    
    // $stmt = $pdo->query("SELECT SUM(total_ttc) as montant_impaye FROM factures WHERE statut = 'impayée'");
    // $stats['montant_impaye'] = $stmt->fetch(PDO::FETCH_ASSOC)['montant_impaye'] ?? 0;
    
    return $stats;
}

// Rechercher des factures
function rechercherFactures($pdo, $search) {
    $search = "%$search%";
    $stmt = $pdo->prepare("
        SELECT f.*, c.nom as client_nom 
        FROM factures f
        LEFT JOIN clients c ON f.client_id = c.id
        WHERE f.numero_facture LIKE ? 
           OR c.nom LIKE ? 
           OR f.statut LIKE ?
        ORDER BY f.date_emission DESC
    ");
    $stmt->execute([$search, $search, $search]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Exporter une facture en PDF (HTML version)
function exporterFactureHTML($pdo, $id) {
    $facture = getFactureById($pdo, $id);
    if(!$facture) return false;
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Facture <?php echo $facture['numero_facture']; ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 40px;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 40px;
                border-bottom: 2px solid #667eea;
                padding-bottom: 20px;
            }
            .header h1 {
                color: #667eea;
                margin: 0;
            }
            .entreprise {
                float: left;
                width: 50%;
            }
            .facture-info {
                float: right;
                width: 50%;
                text-align: right;
            }
            .clear {
                clear: both;
            }
            .client-info {
                margin: 40px 0;
                padding: 20px;
                background: #f7fafc;
                border-radius: 5px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th {
                background: #667eea;
                color: white;
                padding: 12px;
                text-align: left;
            }
            td {
                padding: 10px;
                border-bottom: 1px solid #e2e8f0;
            }
            .total {
                text-align: right;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px solid #667eea;
            }
            .footer {
                margin-top: 50px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
            .statut {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 5px;
                font-weight: bold;
            }
            .statut-payee {
                background: #c6f6d5;
                color: #22543d;
            }
            .statut-impayee {
                background: #fed7d7;
                color: #742a2a;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>FACTURE</h1>
        </div>
        
        <div class="entreprise">
            <strong>VOTRE ENTREPRISE</strong><br>
            123 Rue du Commerce<br>
            75001 Paris<br>
            Tél: 01 23 45 67 89<br>
            Email: contact@entreprise.com<br>
            SIRET: 123 456 789 00012
        </div>
        
        <div class="facture-info">
            <strong>Facture N° :</strong> <?php echo $facture['numero_facture']; ?><br>
            <strong>Date d'émission :</strong> <?php echo date('d/m/Y', strtotime($facture['date_emission'])); ?><br>
            <strong>Date d'échéance :</strong> <?php echo date('d/m/Y', strtotime($facture['date_echeance'])); ?><br>
            <strong>Statut :</strong> 
            <span class="statut statut-<?php echo $facture['statut'] == 'payée' ? 'payee' : 'impayee'; ?>">
                <?php echo strtoupper($facture['statut']); ?>
            </span>
        </div>
        
        <div class="clear"></div>
        
        <div class="client-info">
            <strong>Facturé à :</strong><br>
            <?php echo $facture['client_nom']; ?><br>
            <?php echo $facture['adresse']; ?><br>
            Email: <?php echo $facture['email']; ?><br>
            Tél: <?php echo $facture['telephone']; ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantité</th>
                    <th>Prix unitaire HT</th>
                    <th>TVA</th>
                    <th>Total HT</th>
                    <th>Total TTC</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($facture['lignes'] as $ligne): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ligne['description']); ?></td>
                    <td><?php echo $ligne['quantite']; ?></td>
                    <td><?php echo number_format($ligne['prix_unitaire_ht'], 2); ?> €</td>
                    <td><?php echo $ligne['tva']; ?>%</td>
                    <td><?php echo number_format($ligne['total_ht'], 2); ?> €</td>
                    <td><?php echo number_format($ligne['total_ttc'], 2); ?> €</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total">
            <p><strong>Total HT :</strong> <?php echo number_format($facture['total_ht'], 2); ?> €</p>
            <p><strong>TVA :</strong> <?php echo number_format($facture['tva'], 2); ?> €</p>
            <p><strong style="font-size: 1.2em;">Total TTC :</strong> <strong style="font-size: 1.2em; color: #667eea;"><?php echo number_format($facture['total_ttc'], 2); ?> €</strong></p>
        </div>
        
        <?php if($facture['notes']): ?>
        <div style="margin-top: 30px;">
            <strong>Notes :</strong><br>
            <?php echo nl2br(htmlspecialchars($facture['notes'])); ?>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Merci de votre confiance !</p>
            <p>En cas de retard de paiement, une pénalité de 10% sera appliquée.</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// ============ TRAITEMENT DES ACTIONS ============

// Vérifier si on demande l'affichage d'une facture de commande
if (isset($_GET['id']) && !isset($_GET['action_facture'])) {
    // Affichage de la facture d'une commande pour les clients
    $commande_id = intval($_GET['id']);
    $user_id = isLoggedIn() ? getUserId() : null;

    $commande = getCommandeFactureData($pdo, $commande_id, $user_id);

    if (!$commande) {
        die("Commande non trouvée ou accès non autorisé.");
    }

    // Vérifier que la commande est payée
    if ($commande['statut'] !== 'payee') {
        die("Cette commande n'est pas encore payée.");
    }

    // Générer et afficher la facture
    echo genererFactureCommandeHTML($commande);
    exit;
}

// Récupérer l'action à traiter
$action_facture = $_GET['action_facture'] ?? $_POST['action_facture'] ?? 'liste';
$message = '';
$error = '';

// Action: Créer une facture
if($action_facture == 'creer' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'client_id' => $_POST['client_id'],
        'date_emission' => $_POST['date_emission'],
        'date_echeance' => $_POST['date_echeance'],
        'notes' => $_POST['notes'] ?? ''
    ];
    
    $lignes = [];
    for($i = 0; $i < count($_POST['description']); $i++) {
        $lignes[] = [
            'description' => $_POST['description'][$i],
            'quantite' => $_POST['quantite'][$i],
            'prix_unitaire' => $_POST['prix_unitaire'][$i],
            'tva' => $_POST['tva'][$i]
        ];
    }
    
    $resultat = creerFacture($pdo, $data, $lignes);
    if($resultat['success']) {
        $message = "Facture créée avec succès ! Numéro : " . $resultat['numero'];
        $action_facture = 'liste';
    } else {
        $error = "Erreur : " . $resultat['error'];
    }
}

// Action: Modifier une facture
if($action_facture == 'modifier' && isset($_POST['submit_modification'])) {
    $data = [
        'client_id' => $_POST['client_id'],
        'date_emission' => $_POST['date_emission'],
        'date_echeance' => $_POST['date_echeance'],
        'notes' => $_POST['notes'] ?? '',
        'statut' => $_POST['statut']
    ];
    
    $lignes = [];
    for($i = 0; $i < count($_POST['description']); $i++) {
        $lignes[] = [
            'description' => $_POST['description'][$i],
            'quantite' => $_POST['quantite'][$i],
            'prix_unitaire' => $_POST['prix_unitaire'][$i],
            'tva' => $_POST['tva'][$i]
        ];
    }
    
    $resultat = modifierFacture($pdo, $_POST['facture_id'], $data, $lignes);
    if($resultat['success']) {
        $message = "Facture modifiée avec succès !";
        $action_facture = 'liste';
    } else {
        $error = "Erreur : " . $resultat['error'];
    }
}

// Action: Supprimer une facture
if($action_facture == 'supprimer' && isset($_GET['id'])) {
    if(supprimerFacture($pdo, $_GET['id'])) {
        $message = "Facture supprimée avec succès !";
    } else {
        $error = "Erreur lors de la suppression";
    }
    $action_facture = 'liste';
}

// Action: Changer le statut
if($action_facture == 'changer_statut' && isset($_POST['id'])) {
    if(changerStatutFacture($pdo, $_POST['id'], $_POST['statut'])) {
        $message = "Statut mis à jour !";
    } else {
        $error = "Erreur lors de la mise à jour";
    }
    $action_facture = 'liste';
}

// Action: Exporter PDF
if($action_facture == 'exporter_pdf' && isset($_GET['id'])) {
    $html = exporterFactureHTML($pdo, $_GET['id']);
    if($html) {
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}

// Action: Rechercher
$search_results = null;
if($action_facture == 'rechercher' && isset($_GET['search'])) {
    $search_results = rechercherFactures($pdo, $_GET['search']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Factures</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .factures-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .factures-header {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .factures-header h2 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .factures-nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .factures-nav a {
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            background: #f0f0f0;
            color: #333;
            transition: all 0.3s;
        }
        
        .factures-nav a:hover, .factures-nav a.active {
            background: #667eea;
            color: white;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card h4 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        
        .table-responsive {
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-payée {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-impayée {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-annulée {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
            margin: 2px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .search-bar {
            margin-bottom: 20px;
        }
        
        .search-bar input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .factures-container {
                padding: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn-sm {
                display: inline-block;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="factures-container">
        <div class="factures-header">
            <h2>📄 Gestion des Factures</h2>
            <div class="factures-nav">
                <a href="?action_facture=tableau_bord" class="<?php echo $action_facture == 'tableau_bord' ? 'active' : ''; ?>">Tableau de bord</a>
                <a href="?action_facture=liste" class="<?php echo $action_facture == 'liste' ? 'active' : ''; ?>">Liste des factures</a>
                <a href="?action_facture=ajouter" class="<?php echo $action_facture == 'ajouter' ? 'active' : ''; ?>">Nouvelle facture</a>
            </div>
        </div>
        
        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($action_facture == 'tableau_bord'): ?>
            <?php $stats = getStatistiquesFactures($pdo); ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Factures</h4>
                    <!-- <div class="stat-value"><?php echo $stats['total']; ?></div> -->
                </div>
                <div class="stat-card">
                    <h4>Montant Total</h4>
                    <!-- <div class="stat-value"><?php echo number_format($stats['total_montant'], 0, ',', ' '); ?> €</div> -->
                </div>
                <div class="stat-card">
                    <h4>Factures Payées</h4>
                    <!-- <div class="stat-value"><?php echo $stats['payee']; ?></div> -->
                </div>
                <div class="stat-card">
                    <h4>Factures Impayées</h4>
                    <!-- <div class="stat-value"><?php echo $stats['impayee']; ?></div> -->
                </div>
                <div class="stat-card">
                    <h4>Montant Impayé</h4>
                    <!-- <div class="stat-value"><?php echo number_format($stats['montant_impaye'], 0, ',', ' '); ?> €</div> -->
                </div>
            <!-- </div> -->
            
            <div class="card">
                <h3>Dernières factures</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Facture</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Total TTC</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- <?php $factures = getAllFactures($pdo); ?> -->
                            <!-- <?php $i = 0; foreach($factures as $facture): if($i++ >= 5) break; ?> -->
                            <tr>
                                <td><strong><?php echo $facture['numero_facture']; ?></strong></td>
                                <td><?php echo $facture['client_nom']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($facture['date_emission'])); ?></td>
                                <td><?php echo number_format($facture['total_ttc'], 2, ',', ' '); ?> €</td>
                                <td>
                                    <span class="badge badge-<?php echo $facture['statut']; ?>">
                                        <?php echo strtoupper($facture['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?action_facture=voir&id=<?php echo $facture['id']; ?>" class="btn btn-sm btn-primary">Voir</a>
                                    <a href="?action_facture=exporter_pdf&id=<?php echo $facture['id']; ?>" class="btn btn-sm btn-success" target="_blank">PDF</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php elseif($action_facture == 'liste'): ?>
            <div class="search-bar">
                <form method="GET">
                    <input type="hidden" name="action_facture" value="rechercher">
                    <input type="text" name="search" placeholder="Rechercher par numéro, client ou statut..." value="<?php echo $_GET['search'] ?? ''; ?>">
                </form>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>N° Facture</th>
                            <th>Client</th>
                            <th>Date émission</th>
                            <th>Date échéance</th>
                            <th>Total HT</th>
                            <th>Total TTC</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $factures = $search_results !== null ? $search_results : getAllFactures($pdo);
                        // foreach($factures as $facture): 
                        ?>
                        <tr>
                            <!-- <td><?php echo $facture['numero_facture']; ?></td> -->
                            <!-- <td><?php echo $facture['client_nom']; ?></td> -->
                            <!-- <td><?php echo date('d/m/Y', strtotime($facture['date_emission'])); ?></td> -->
                            <!-- <td><?php echo date('d/m/Y', strtotime($facture['date_echeance'])); ?></td> -->
                            <!-- <td><?php echo number_format($facture['total_ht'], 2, ',', ' '); ?> €</td> -->
                            <!-- <td><?php echo number_format($facture['total_ttc'], 2, ',', ' '); ?> €</td> -->
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action_facture" value="changer_statut">
                                    <input type="hidden" name="id" value="<?php echo $facture['id']; ?>">
                                    <select name="statut" onchange="this.form.submit()" class="badge badge-<?php echo $facture['statut']; ?>">
                                        <!-- <option value="payée" <?php echo $facture['statut'] == 'payée' ? 'selected' : ''; ?>>Payée</option> -->
                                        <!-- <option value="impayée" <?php echo $facture['statut'] == 'impayée' ? 'selected' : ''; ?>>Impayée</option> -->
                                        <!-- <option value="annulée" <?php echo $facture['statut'] == 'annulée' ? 'selected' : ''; ?>>Annulée</option> -->
                                    </select>
                                </form>
                            </td>
                            <td>
                                <a href="?action_facture=voir&id=<?php echo $facture['id']; ?>" class="btn btn-sm btn-primary">Voir</a>
                                <a href="?action_facture=modifier&id=<?php echo $facture['id']; ?>" class="btn btn-sm btn-success">Modifier</a>
                                <a href="?action_facture=exporter_pdf&id=<?php echo $facture['id']; ?>" class="btn btn-sm" style="background:#17a2b8; color:white;" target="_blank">PDF</a>
                                <a href="?action_facture=supprimer&id=<?php echo $facture['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette facture ?')">Suppr</a>
                            </td>
                        <!-- </tr> -->
                        
                        <!-- <?php if(empty($factures)): ?> -->
                        <!-- <tr> -->
                            <td colspan="8" style="text-align: center;">Aucune facture trouvée</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif($action_facture == 'ajouter'): ?>
            <div class="card">
                <h3>➕ Nouvelle facture</h3>
                <form method="POST">
                    <input type="hidden" name="action_facture" value="creer">
                    
                    <div class="form-group">
                        <label>Client *</label>
                        <select name="client_id" required>
                            <option value="">Sélectionner un client</option>
                            <?php 
                            $stmt = $pdo->query("SELECT id, nom FROM clients ORDER BY nom");
                            while($client = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['nom']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date d'émission *</label>
                            <input type="date" name="date_emission" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Date d'échéance *</label>
                            <input type="date" name="date_echeance" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        </div>
                    </div>
                    
                    <div id="lignes-container">
                        <h4>Lignes de facture</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="description[]" required>
                            </div>
                            <div class="form-group">
                                <label>Quantité</label>
                                <input type="number" name="quantite[]" value="1" step="1" required>
                            </div>
                            <div class="form-group">
                                <label>Prix unitaire HT</label>
                                <input type="number" name="prix_unitaire[]" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>TVA (%)</label>
                                <input type="number" name="tva[]" value="20" step="0.1">
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" onclick="ajouterLigne()" class="btn btn-success" style="margin-bottom: 15px;">+ Ajouter une ligne</button>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3" placeholder="Notes optionnelles..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Créer la facture</button>
                </form>
            </div>
            
        <?php elseif($action_facture == 'modifier' && isset($_GET['id'])): ?>
            <?php $facture = getFactureById($pdo, $_GET['id']); ?>
            <?php if($facture): ?>
            <div class="card">
                <h3>✏️ Modifier la facture <?php echo $facture['numero_facture']; ?></h3>
                <form method="POST">
                    <input type="hidden" name="action_facture" value="modifier">
                    <input type="hidden" name="facture_id" value="<?php echo $facture['id']; ?>">
                    
                    <div class="form-group">
                        <label>Client</label>
                        <select name="client_id" required>
                            <?php 
                            $stmt = $pdo->query("SELECT id, nom FROM clients ORDER BY nom");
                            while($client = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client['id'] == $facture['client_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['nom']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date d'émission</label>
                            <input type="date" name="date_emission" value="<?php echo $facture['date_emission']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Date d'échéance</label>
                            <input type="date" name="date_echeance" value="<?php echo $facture['date_echeance']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Statut</label>
                            <select name="statut">
                                <option value="payée" <?php echo $facture['statut'] == 'payée' ? 'selected' : ''; ?>>Payée</option>
                                <option value="impayée" <?php echo $facture['statut'] == 'impayée' ? 'selected' : ''; ?>>Impayée</option>
                                <option value="annulée" <?php echo $facture['statut'] == 'annulée' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="lignes-container">
                        <h4>Lignes de facture</h4>
                        <?php foreach($facture['lignes'] as $index => $ligne): ?>
                        <div class="form-row ligne-item">
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="description[]" value="<?php echo htmlspecialchars($ligne['description']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Quantité</label>
                                <input type="number" name="quantite[]" value="<?php echo $ligne['quantite']; ?>" step="1" required>
                            </div>
                            <div class="form-group">
                                <label>Prix unitaire HT</label>
                                <input type="number" name="prix_unitaire[]" value="<?php echo $ligne['prix_unitaire_ht']; ?>" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>TVA (%)</label>
                                <input type="number" name="tva[]" value="<?php echo $ligne['tva']; ?>" step="0.1">
                            </div>
                            <?php if($index > 0): ?>
                            <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger" style="height: 40px; margin-top: 28px;">×</button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" onclick="ajouterLigne()" class="btn btn-success" style="margin-bottom: 15px;">+ Ajouter une ligne</button>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3"><?php echo htmlspecialchars($facture['notes']); ?></textarea>
                    </div>
                    
                    <button type="submit" name="submit_modification" class="btn btn-primary">Enregistrer les modifications</button>
                    <a href="?action_facture=voir&id=<?php echo $facture['id']; ?>" class="btn">Annuler</a>
                </form>
            </div>
            <?php else: ?>
                <div class="alert alert-error">Facture non trouvée</div>
            <?php endif; ?>
            
        <?php elseif($action_facture == 'voir' && isset($_GET['id'])): ?>
            <?php $facture = getFactureById($pdo, $_GET['id']); ?>
            <?php if($facture): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <h3>Facture <?php echo $facture['numero_facture']; ?></h3>
                    <div>
                        <a href="?action_facture=modifier&id=<?php echo $facture['id']; ?>" class="btn btn-primary">✏️ Modifier</a>
                        <a href="?action_facture=exporter_pdf&id=<?php echo $facture['id']; ?>" class="btn btn-success" target="_blank">📄 PDF</a>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                    <div>
                        <strong>VOTRE ENTREPRISE</strong><br>
                        123 Rue du Commerce<br>
                        75001 Paris<br>
                        Tél: 01 23 45 67 89
                    </div>
                    <div style="text-align: right;">
                        <strong>Date :</strong> <?php echo date('d/m/Y', strtotime($facture['date_emission'])); ?><br>
                        <strong>Échéance :</strong> <?php echo date('d/m/Y', strtotime($facture['date_echeance'])); ?><br>
                        <strong>Statut :</strong> 
                        <span class="badge badge-<?php echo $facture['statut']; ?>">
                            <?php echo strtoupper($facture['statut']); ?>
                        </span>
                    </div>
                </div>
                
                <div style="margin-bottom: 30px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                    <strong>Facturé à :</strong><br>
                    <?php echo htmlspecialchars($facture['client_nom']); ?><br>
                    <?php echo nl2br(htmlspecialchars($facture['adresse'])); ?><br>
                    Email: <?php echo $facture['email']; ?><br>
                    Tél: <?php echo $facture['telephone']; ?>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Qté</th>
                                <th>Prix HT</th>
                                <th>TVA</th>
                                <th>Total HT</th>
                                <th>Total TTC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($facture['lignes'] as $ligne): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ligne['description']); ?></td>
                                <td><?php echo $ligne['quantite']; ?></td>
                                <td><?php echo number_format($ligne['prix_unitaire_ht'], 2); ?> €</td>
                                <td><?php echo $ligne['tva']; ?>%</td>
                                <td><?php echo number_format($ligne['total_ht'], 2); ?> €</td>
                                <td><?php echo number_format($ligne['total_ttc'], 2); ?> €</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f9f9f9;">
                                <td colspan="4" style="text-align: right;"><strong>Totaux :</strong></td>
                                <td><strong><?php echo number_format($facture['total_ht'], 2); ?> €</strong></td>
                                <td><strong><?php echo number_format($facture['total_ttc'], 2); ?> €</strong></td>
                            </tr>
                            <tr>
                                <td colspan="5" style="text-align: right;"><strong>TVA :</strong></td>
                                <td><?php echo number_format($facture['tva'], 2); ?> €</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <?php if($facture['notes']): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                    <strong>Notes :</strong><br>
                    <?php echo nl2br(htmlspecialchars($facture['notes'])); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <div class="alert alert-error">Facture non trouvée</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        let ligneCount = <?php echo isset($facture) ? count($facture['lignes']) : 1; ?>;
        
        function ajouterLigne() {
            ligneCount++;
            const container = document.getElementById('lignes-container');
            const newRow = document.createElement('div');
            newRow.className = 'form-row';
            newRow.innerHTML = `
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description[]" required>
                </div>
                <div class="form-group">
                    <label>Quantité</label>
                    <input type="number" name="quantite[]" value="1" step="1" required>
                </div>
                <div class="form-group">
                    <label>Prix unitaire HT</label>
                    <input type="number" name="prix_unitaire[]" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>TVA (%)</label>
                    <input type="number" name="tva[]" value="20" step="0.1">
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger" style="height: 40px; margin-top: 28px;">✖</button>
            `;
            container.appendChild(newRow);
        }
    </script>
</body>
</html>