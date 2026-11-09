# SimpleAI

SimpleAI est une bibliothèque PHP pour utiliser plusieurs fonctions de l'API OpenAI : conversation, génération et modification d'images, analyse d'images et synthèse vocale.

SimpleAI est indépendante et n'est pas affiliée à OpenAI. Son utilisation nécessite une clé API OpenAI et peut entraîner une facturation distincte d'un abonnement ChatGPT.

## Prérequis

- PHP 8.1 ou supérieur
- Composer
- Une clé API OpenAI avec accès aux modèles et fonctions utilisés

## Installation

```bash
composer require matthieuthuetdev/simple-ai
```

## Configuration

La bibliothèque reçoit la clé API dans chaque appel. Elle ne lit pas automatiquement les fichiers `.env`.

```php
$apiKey = getenv('OPENAI_API_KEY');
```

Ne placez jamais une clé réelle dans le code source ni dans un dépôt partagé.

## Conversation

```php
use SimpleAI\OpenAI;

$answer = OpenAI::ask(
    'Explique-moi le fonctionnement de Symfony.',
    'gpt-5',
    $apiKey,
);
```

Le modèle est transmis tel quel à OpenAI. Sa disponibilité dépend de votre compte.

## Générer une image à partir d'un texte

`generateImage()` renvoie les octets de l'image PNG. Vous pouvez les écrire dans un fichier :

```php
$image = OpenAI::generateImage(
    'Un renard roux lisant un livre dans une bibliothèque, style aquarelle',
    $apiKey,
);

file_put_contents(__DIR__ . '/renard.png', $image);
```

Le modèle par défaut est `gpt-image-2.6-flare`. Vous pouvez choisir un autre modèle compatible en troisième argument.

## Modifier une image avec un texte

`editImage()` prend le chemin d'une image locale et une consigne de modification. Il accepte les fichiers PNG, JPEG et WEBP de moins de 50 Mo, puis renvoie les octets PNG de l'image modifiée.

```php
$editedImage = OpenAI::editImage(
    __DIR__ . '/photo.png',
    'Remplace le ciel par un coucher de soleil rose',
    $apiKey,
);

file_put_contents(__DIR__ . '/photo-modifiee.png', $editedImage);
```

## Analyser une image et obtenir du texte

`analyzeImage()` répond à une question à partir d'une image. L'image peut être un chemin local, une URL HTTP(S) ou une URL de données base64. Le modèle doit accepter les images en entrée.

```php
$description = OpenAI::analyzeImage(
    __DIR__ . '/photo.png',
    'Décris cette image en deux phrases.',
    'gpt-5',
    $apiKey,
);
```

Les images envoyées à un modèle de vision peuvent entraîner des frais de jetons d'entrée.

## Convertir du texte en voix

`availableVoices()` affiche les voix intégrées disponibles. `textToSpeech()` reçoit le texte, le nom de voix et la clé API, puis renvoie les octets audio (MP3 par défaut).

```php
print_r(OpenAI::availableVoices());

$audio = OpenAI::textToSpeech(
    'Bonjour, ceci est un exemple de synthèse vocale.',
    'coral',
    $apiKey,
);

file_put_contents(__DIR__ . '/bonjour.mp3', $audio);
```

Les voix intégrées sont : `alloy`, `ash`, `ballad`, `coral`, `echo`, `fable`, `nova`, `onyx`, `sage`, `shimmer`, `verse`, `marin` et `cedar`. Elles sont optimisées pour l'anglais, mais le modèle accepte plusieurs langues. Indiquez clairement aux utilisateurs que la voix entendue est générée par une IA.

Pour contrôler le ton, vous pouvez fournir les instructions en sixième argument :

```php
$audio = OpenAI::textToSpeech(
    'Bonjour, ceci est un exemple de synthèse vocale.',
    'coral',
    $apiKey,
    'gpt-4o-mini-tts',
    'mp3',
    'Parle avec un ton chaleureux et calme.',
);
```

### Générer un échantillon pour chaque voix

`generateVoiceSamples()` prend la phrase de test en paramètre et renvoie un tableau associant chaque nom de voix à ses octets audio. Cette méthode effectue **13 requêtes facturables**, une par voix.

```php
$samples = OpenAI::generateVoiceSamples(
    'Bonjour, ceci est un test de voix.',
    $apiKey,
);

foreach ($samples as $voice => $audio) {
    file_put_contents(__DIR__ . '/voix-' . $voice . '.mp3', $audio);
}
```

> **À prévoir :** OpenAI a annoncé l'arrêt de l'endpoint `/v1/audio/speech` et de ses modèles actuels le 6 janvier 2027. Consultez le [guide TTS officiel](https://developers.openai.com/api/docs/guides/text-to-speech) avant de l'utiliser après cette date.

## Erreurs

Les paramètres obligatoires invalides provoquent une `InvalidArgumentException`. Les erreurs réseau, les réponses HTTP en erreur et les réponses OpenAI non exploitables provoquent une `RuntimeException`. Les messages ne contiennent pas la clé API ni le contenu détaillé renvoyé par le serveur.

## Coûts

Les coûts dépendent des modèles, des images, du nombre de jetons et de la durée audio. Consultez les [tarifs API actuels](https://openai.com/api/pricing/) avant tout déploiement.

## Vérification du style

```bash
composer install
composer style
```
