# Origina Client

Le frontend de la solution Origina : le système intégré de détection de plagiat et de gestion académique (IBAM / MIAGE). 

## 🎨 Design System : Midnight Obsidian
L'interface est entièrement pensée et structurée sur mesure avec le design system **Midnight Obsidian**.
- **Stack** : React + Vite
- **Styling** : Vanillla CSS pur (pas de Tailwind CSS ou autre surcouche) afin de garder le contrôle total sur l'esthétique.
- **Thèmes** : Prise en charge d'un basculement dynamique complet **Light / Dark mode** (sauvegardé via localStorage). L'expérience sombre est conçue comme un Command Center (tons Slate & "blue glow").
- **Icônes & Police** : Google Material Symbols et famille de polices variable "Inter".
- **Composants** :
  - **Landing Page** avec dégradés fluides et animations au survol
  - **Dashboard** organisé avec Sidebar, KPI cards personnalisées, indicateurs de gravité (pilules rouge/jaune/verte)

## 🚀 Démarrage Rapide

1. Installer les dépendances :
```bash
npm install
```

2. Démarrer le serveur de développement local :
```bash
npm run dev
```

3. Construire et préparer une version de production :
```bash
npm run build
```

## 🏗 Structure du Code
- `src/App.jsx` : L'application principale gérant le routage natif, les vues et les requêtes (API).
- `src/index.css` : Configuration root (fonts, corps de base).
- `src/App.css` : Le cœur du système visuel **Midnight Obsidian** contenant à la fois la logique du Dark mode (défaut) et les variables CSS du Light mode (`[data-theme="light"]`), ainsi que toute l'architecture structurelle (login, topbar, dashboard layout).
