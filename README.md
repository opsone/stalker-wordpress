# OPSONE - WordPress - Liste des dépendances

## Introduction
Ce module WordPress sert à lister toutes les informations sur les dépendences utilisées par un projet WordPress.

Pour accéder au JSON de sortie, il faut aller à `adresseRacineDuProjet/stalker/depepndencies` depuis l'une des adresses ip autorisée.

## Prérequis
- Avoir installé le module sur le site
- Avoir activé le module dans les paramètres Drupal
- Avoir ajouté la ligne de configuration au fichier `wp-config.php`du projet

## Installation
- Télécharger le zip du plugin depuis le dépôt git
- Dézipper le dossier téléchargé
- Aller dans le dossier `wp-content/plugins` de votre projet WordPress
- Déposer le dossier dézippé dans le dossier `plugins`
- Préfixer le nom du dossier par "opsone-"
- Activer le plugin depuis l'interface d'administration de WordPress
- Ajouter la ligne de configuration au fichier `wp-config.php` du projet

### Ligne de configuration à ajouter au fichier `wp-config.php`
```php
define( 'OPS_ALLOWED_IP', ['ip1', 'ip2', ...]);
```
#### **<span style="color: #dc3545">Attention : S'il n'y a qu'une adresse ip qui est authorisée, veillez bien à la renseigner dans un tableau</span>**

### Si les binaires n'utilisent pas le chemin par défaut
- Ajouter les ligne de configuration suivante au fichier `settings.php` du projet
```php
define( 'OPS_NODE_BIN', '/path/to/node');
define( 'OPS_NPM_BIN', '/path/to/npm');
define( 'OPS_PHP_BIN', '/path/to/php');
define( 'OPS_COMPOSER_BIN', '/path/to/composer');
define( 'OPS_ELASTIC_SEARCH_API_URL', 'elastic_api_url');
```

#### Chaque ligne de configuration est indépendente l'une de l'autre, si elle n'est pas renseignée, le chemin par défaut sera utilisé.
