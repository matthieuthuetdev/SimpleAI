# SimpleAI

SimpleAI est une petite librairie PHP qui envoie une question à l'API Responses d'OpenAI et retourne directement le texte généré. Elle prend en charge la requête HTTP, l'authentification, le JSON et l'extraction du texte.

SimpleAI est une librairie indépendante, non affiliée à OpenAI. Son utilisation nécessite une clé API OpenAI et entraîne une facturation API distincte d'un abonnement ChatGPT.

## Prérequis

- PHP 8.1 ou supérieur
- Composer
- Une clé API OpenAI et l'accès à un modèle compatible avec l'API Responses

## Installation

```bash
composer require matthieuthuetdev/simple-ai
```

## Configuration

Créez une clé sur la plateforme OpenAI. Pour le client de démonstration, renseignez `OPENAI_API_KEY` et `AI_MODEL` dans `.env.local`. Ce fichier est ignoré par Git. `.env.example` fournit un modèle sans clé réelle. La librairie elle-même ne charge pas automatiquement les fichiers `.env`.

Ne placez jamais une clé réelle dans le code source ni dans un dépôt partagé.

## Utilisation

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use SimpleAI\OpenAI;

$question = 'Explique-moi le fonctionnement de Symfony.';
$model = 'gpt-5';
$apiKey = getenv('OPENAI_API_KEY');

if ($apiKey === false || $apiKey === '') {
    throw new RuntimeException('La variable OPENAI_API_KEY est requise.');
}

try {
    $reponse = OpenAI::ask($question, $model, $apiKey);
    echo $reponse;
} catch (Throwable $e) {
    echo 'Erreur : ' . $e->getMessage();
}
```

`$model` est transmis tel quel à OpenAI. Aucun modèle n'est supposé être disponible pour tous les comptes.

## Démonstration dans le terminal

Renseignez `OPENAI_API_KEY` et un modèle autorisé pour votre compte dans `.env.local`, puis lancez :

```bash
php chat.php
```

Saisissez une question à chaque invite. Entrez `q`, `quit` ou `exit` pour terminer. Le client affiche la réponse, puis propose une nouvelle question.

## Erreurs

`OpenAI::ask()` lève une `InvalidArgumentException` si la question, le modèle ou la clé est vide, et une `RuntimeException` dans les cas suivants :

- erreur réseau ou délai d'attente;
- identifiants ou permissions refusés (HTTP 401/403);
- requête ou modèle invalide (HTTP 400/404);
- limite de débit ou quota atteint (HTTP 429);
- erreur serveur (HTTP 5xx) ou autre statut HTTP d'erreur;
- JSON invalide, réponse incomplète ou absence de texte exploitable.

Les messages d'erreur ne reprennent pas la clé API ni le contenu détaillé retourné par le serveur.

## Modèles et coûts

Les modèles accessibles et leurs fonctionnalités dépendent du compte OpenAI. Consultez le [catalogue des modèles](https://developers.openai.com/api/docs/models) et vérifiez les droits du projet qui détient la clé. Un modèle non accessible fera l'objet d'une erreur API.

L'utilisation de l'API est facturée séparément de ChatGPT. Les tarifs varient selon le modèle, les jetons d'entrée et de sortie et les fonctionnalités utilisées; vérifiez les [tarifs API actuels](https://openai.com/api/pricing/) avant tout déploiement. Cette librairie ne met pas en place de nouvelles tentatives automatiques.

## Vérification du style

Depuis une copie du dépôt :

```bash
composer install
composer style
```

## Publication sur Packagist

1. Publiez le dépôt Git contenant `composer.json` et vérifiez le nom `matthieuthuetdev/simple-ai`.
2. Créez une version en ajoutant un tag Git, par exemple `git tag v1.0.0`, puis poussez le tag.
3. Soumettez le dépôt sur Packagist, ou activez sa mise à jour automatique via le webhook Packagist.
4. Vérifiez la page du package et validez l'installation avec `composer require matthieuthuetdev/simple-ai`.
