<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À propos – Mycksoft</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #2c79b3ff;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        h1 {
            text-align: center;
            color: #222;
            font-size: 28px;
            margin-bottom: 20px;
        }

        h2 {
            color: #0e0d0dff;
            margin-top: 25px;
        }

        p {
            line-height: 1.7;
            color: #1c1b1bff;
            font-size: 16px;
        }

        /* Responsive design */
        @media(max-width: 768px) {
            h1 { font-size: 24px; }
            p { font-size: 15px; }
            .container { padding: 15px; }
        }

        @media(max-width: 480px) {
            h1 { font-size: 22px; }
            p { font-size: 14px; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>À propos de Mycksoft</h1>

    <p>
        <strong>Mycksoft</strong> est une boutique en ligne créée pour offrir une expérience 
        d’achat simple, rapide et totalement sécurisée.  
        Nous proposons une gamme variée de produits de qualité à des prix accessibles, 
        avec un service professionnel et fiable.
    </p>

    <h2>Notre mission</h2>
    <p>
        Offrir à nos clients une plateforme e-commerce moderne, intuitive et disponible 24h/24.  
        Nous nous engageons à fournir des produits de qualité et à garantir une expérience 
        d’achat agréable et sans stress.
    </p>

    <h2>Nos valeurs</h2>
    <p>
        ✔ <strong>Qualité :</strong> sélection rigoureuse de nos produits <br>
        ✔ <strong>Fiabilité :</strong> un service client réactif et à l’écoute <br>
        ✔ <strong>Transparence :</strong> des informations claires et honnêtes <br>
        ✔ <strong>Accessibilité :</strong> des prix adaptés à tous <br>
        ✔ <strong>Innovation :</strong> une plateforme évolutive et performante <br>
    </p>

    <h2>Pourquoi choisir Mycksoft ?</h2>
    <p>
        • Interface simple et fluide <br>
        • Produits variés et régulièrement mis à jour <br>
        • Paiements sécurisés <br>
        • Livraison rapide et fiable <br>
        • Assistance disponible pour vous accompagner <br>
    </p>

    <h2>Notre histoire</h2>
    <p>
        Mycksoft a été fondé avec l’objectif de moderniser l’e-commerce et de proposer 
        une plateforme accessible à tous.  
        Grâce à notre passion pour la technologie, nous améliorons constamment nos services 
        pour répondre aux besoins de nos clients.
    </p>
</div>

</body>
</html>

<?php 
require_once __DIR__ .'/footer.php';
?>
