# PolyPlag

Système intégré de détection de plagiat et de gestion académique.

PolyPlag est une solution web conçue pour garantir l'intégrité académique au sein des institutions d'enseignement supérieur (type IBAM/MIAGE). L'application couvre tout le cycle de vie d'un travail de recherche, de la validation du thème jusqu'à la délibération finale après analyse de similarité.

## Objectifs

- Vérifier l'originalité des travaux soumis.
- Assister les acteurs académiques dans la prise de décision.
- Centraliser les analyses, rapports et délibérations dans une seule plateforme.

## Fonctionnalités principales

### 1. Gestion des utilisateurs et rôles

- Étudiant:
  - soumission de thème,
  - auto-test de document,
  - dépôt final.
- Enseignant (Chef de Département):
  - validation ou rejet des thèmes,
  - lancement des analyses de plagiat.
- DA (Direction des Études):
  - consultation des rapports,
  - gestion des délibérations.
- Commission VAR (Validation des Acquis et Résultats):
  - décision finale en cas de fraude.

### 2. Moteur de détection multi-niveaux

Le système ne se limite pas au texte brut. Il analyse:

- plagiat direct (copier-coller),
- paraphrase (analyse sémantique),
- traduction,
- plagiat complet (reprise intégrale de travaux existants).

### 3. Analyse intelligente et anti-triche

- Détection d'IA: analyse de perplexité pour identifier les contenus générés par des LLM.
- Nettoyage de texte: suppression des caractères invisibles/homoglyphes.
- Segmentation: découpage intelligent du document pour une comparaison granulaire.

### 4. Rapports et visualisation

- Tableau de bord avec graphes d'interprétation (sources, taux d'originalité, etc.).
- Rapport de similarité avec surlignage des passages suspects et liens vers les sources.

## Pile technologique

| Composant           | Technologie                                     |
| ------------------- | ----------------------------------------------- |
| Frontend            | React.js (Tailwind CSS, Lucide Icons, Chart.js) |
| Backend             | Laravel 10/11 (PHP)                             |
| Base de données     | MySQL                                           |
| Traitement NLP      | Python (scripts via `shell_exec` ou API Flask)  |
| Gestion de fichiers | Laravel Storage (Local/S3)                      |

## Workflow fonctionnel

1. Soumission du thème par l'étudiant.
2. Vérification d'unicité automatique (existence du thème en base).
3. Validation humaine par le Chef de Département.
4. Dépôt du document puis analyse de similarité.
5. Comparaison avec internet, bases académiques et travaux antérieurs.
6. Production du rapport et délibération (DA/VAR).

Si le thème est rejeté, le processus s'arrête.

## Structure du projet

```bash
/polyplag-root
│
├── /polyplag-api            # Backend Laravel
│   ├── app/Models           # Theme, Document, Report, User
│   ├── app/Http/Controllers # Logique de validation et workflow
│   ├── database/migrations  # Schéma MySQL (acteurs, documents, résultats)
│   └── routes/api.php       # Endpoints API pour le frontend
│
├── /polyplag-client         # Frontend React
│   ├── src/components       # Upload, graphes, visualiseurs de rapports
│   ├── src/pages            # Dashboards Étudiant/Enseignant/DA
│   └── src/hooks            # Appels API via Axios
│
└── /scripts-python          # Algorithmes NLP (similarité, détection IA)
```

## Installation

### Prérequis

- PHP 8.2+ et Composer
- Node.js et npm
- MySQL
- Python 3.x

### Étapes

#### 1. Cloner le dépôt

```bash
git clone https://github.com/votre-compte/polyplag.git
cd polyplag-root
```

#### 2. Configurer le backend

```bash
cd polyplag-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Configurer ensuite les variables de base de données dans le fichier `.env`.

#### 3. Configurer le frontend

```bash
cd ../polyplag-client
npm install
npm start
```

## Schéma de base de données (résumé)

- `users`: informations utilisateurs et rôles (Student, Teacher, DA).
- `themes`: titre, description et statut de validation.
- `documents`: chemins de fichiers et métadonnées techniques.
- `similarity_reports`: score global, score IA et données JSON pour les visualisations.

## Avertissement

Ce logiciel est un outil d'aide à la décision. Les rapports de similarité doivent toujours être interprétés par un enseignant qualifié avant toute sanction.
