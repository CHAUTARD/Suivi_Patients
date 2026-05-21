# SELARL La Vespalienne — Suivi des Actes

Application web de suivi des actes dentaires : saisie quotidienne, récapitulatifs mensuels, plans de traitement et tableau de bord.

**Stack** : PHP 8.0+ · MySQL/MariaDB · Bootstrap 5 · jQuery · Flatpickr

---

## Fonctionnalités

### Tableau de bord (`home.php`)
- KPI du mois en cours : jours saisis, plans, taux d'acceptation, montant devis
- Accès rapide aux principales pages
- Vue comparatif par dentiste (admin)

### Saisie des actes (`saisie.php`)
- Sélecteur de date avec Flatpickr (max : aujourd'hui)
- Tableau structuré par piliers et actions
- Lignes calculées automatiquement (`=1+2+3`)
- Indicateur visuel et badge `!` si modifications non enregistrées
- Surlignage jaune des lignes modifiées par rapport aux données chargées
- Raccourci **Ctrl+S** pour enregistrer
- Avertissement navigateur à la fermeture si données non sauvegardées
- Saisie des plans de traitement du jour (patient, devis, acceptation, montant)
- **Admin** : combobox obligatoire pour choisir le dentiste cible

### Récapitulatif mensuel (`recap.php`)
- Tableau pivot (actions × jours du mois)
- Mise en évidence week-ends et jours fériés français
- Filtrage par dentiste (admin)
- Export CSV (BOM UTF-8)

### Récapitulatif plans de traitement (`recap_plans.php`)
- Liste des plans du mois avec statistiques globales
- Colonnes triables (clic en-tête)
- **Modification en ligne** via modale déplaçable (Accepté, Date acceptation, Montant)
- Comparatif mensuel par dentiste (admin)
- Export CSV

### Administration (`admin/`)
- Gestion des **utilisateurs** : CRUD, rôles Dentiste / Administrateur, mots de passe BCRYPT
- Gestion des **piliers** : CRUD
- Gestion des **actions** : CRUD avec colonne Formule et tri par ordre

### UX transverse
- Modales Bootstrap déplaçables (glisser depuis l'en-tête)
- Notifications toast (succès / erreur / avertissement)
- Toutes les requêtes en AJAX — pas de rechargement de page

---

## Captures d'écran

> *(à compléter)*

---

## Installation

### Prérequis

| Composant   | Version minimale   |
|-------------|--------------------|
| PHP         | 8.0                |
| MySQL       | 5.7 / MariaDB 10.3 |
| Serveur web | Apache 2.4 / Nginx |

### 1. Cloner le dépôt

```bash
git clone https://github.com/<votre-compte>/selarl-vespalienne.git
cd selarl-vespalienne
```

### 2. Configurer la base de données

Copier et adapter le fichier de configuration :

```bash
cp includes/config.php.example includes/config.php
```

Éditer `includes/config.php` :

```php
define('DB_HOST',    '127.0.0.1');
define('DB_PORT',    '3306');
define('DB_NAME',    'suivi_activite');
define('DB_USER',    'root');
define('DB_PASS',    'votre_mot_de_passe');
define('DB_CHARSET', 'utf8mb4');
```

> **`includes/config.php` ne doit jamais être commité** — voir `.gitignore`.

### 3. Importer la base de données

```bash
mysql -u root -p suivi_activite < 20260514.sql
```

Si une migration de schéma est disponible :

```bash
mysql -u root -p suivi_activite < migration_formule.sql
```

### 4. Créer le compte administrateur

```
http://localhost/<dossier>/delete_after_install.php
```

Remplir le formulaire puis **supprimer `delete_after_install.php` immédiatement**.

### 5. Accéder à l'application

```
http://localhost/<dossier>/
```

---

## Structure du projet

```
selarl-vespalienne/
│
├── index.php                        ← Connexion (redirige vers home.php)
├── home.php                         ← Tableau de bord
├── saisie.php                       ← Saisie des actes
├── recap.php                        ← Récapitulatif mensuel
├── recap_plans.php                  ← Récapitulatif plans de traitement
├── changer_pwd.php                  ← Changement de mot de passe
├── logout.php                       ← Déconnexion
├── delete_after_install.php                        ← ⚠ SUPPRIMER après installation
│
├── includes/
│   ├── config.php                   ← ⚠ NE PAS COMMITER (credentials)
│   ├── db.php                       ← Connexion PDO (singleton)
│   ├── auth.php                     ← Session, login, logout, CSRF
│   ├── security.php                 ← Validation inputs, hachage
│   ├── functions.php                ← Helpers JSON, validation dates
│   ├── header.php                   ← Template HTML + navbar
│   ├── footer.php                   ← Fermeture HTML + chargement JS
│   └── .htaccess                    ← Accès direct interdit
│
├── api/
│   ├── get_dashboard.php            ← GET  stats tableau de bord
│   ├── get_saisie.php               ← GET  données saisie d'un jour
│   ├── save_saisie.php              ← POST enregistrement saisies
│   ├── get_recap.php                ← GET  synthèse mensuelle actes
│   ├── get_recap_plans.php          ← GET  synthèse mensuelle plans
│   ├── get_plan_traitement.php      ← GET  plans d'un jour
│   ├── save_plan_traitement.php     ← POST enregistrement plans (journée)
│   └── update_plan_traitement.php   ← POST mise à jour d'un plan
│
├── admin/
│   ├── index.php                    ← Dashboard admin
│   ├── utilisateurs.php             ← CRUD utilisateurs
│   ├── piliers.php                  ← CRUD piliers
│   └── actions.php                  ← CRUD actions (avec formule et ordre)
│
└── assets/
    ├── css/
    │   ├── bootstrap.min.css
    │   ├── bootstrap-icons.min.css
    │   ├── flatpickr.min.css
    │   ├── style.css                ← Styles custom
    │   └── fonts/
    └── js/
        ├── jquery.min.js
        ├── bootstrap.bundle.min.js
        ├── flatpickr.min.js
        ├── flatpickr.fr.js
        ├── app.js                   ← Utilitaires globaux, Ctrl+S, modales drag
        ├── saisie.js                ← Saisie actes + plans
        ├── recap.js                 ← Récapitulatif mensuel
        ├── recap_plans.js           ← Récapitulatif plans
        └── admin.js                 ← Pages d'administration
```

---

## Modèle de données

```
role (id_role, role)
    ↑
utilisateur (id_utilisateur, id_role, login, pwd)
    ↑                  ↑
nombre                plan_traitement
(id_nombre,           (id_plan,
 id_action,            id_utilisateur,
 id_utilisateur,       date,
 date,                 patient,
 nombre)               montant_devis,
    ↑                  accepter,
action                 date_acceptation,
(id_action,            montant)
 id_pilier,
 action,
 formule,
 ord)
    ↑
pilier (id_pilier, Pilier)
```

**Contrainte d'unicité** : `(id_action, id_utilisateur, date)` dans `nombre`.

### Champ `formule`

Une action peut avoir une formule de cumul (`=1+2+3`). Elle est alors calculée automatiquement à partir des actions référencées — la ligne est en lecture seule dans la saisie.

---

## API

Toutes les requêtes nécessitent une session authentifiée.  
Les requêtes `POST` envoient le token CSRF dans le header `X-CSRF-Token` (configuré globalement dans `app.js`).

| Endpoint | Méthode | Paramètres | Description |
|----------|---------|------------|-------------|
| `api/get_dashboard.php` | GET | — | Stats KPI du mois en cours |
| `api/get_saisie.php` | GET | `date`, `id_utilisateur`* | Données saisie d'un jour |
| `api/save_saisie.php` | POST | JSON `{date, data[], id_utilisateur*}` | Enregistre les saisies |
| `api/get_recap.php` | GET | `mois`, `id_utilisateur`* | Tableau pivot mensuel |
| `api/get_recap_plans.php` | GET | `mois`, `id_utilisateur`* | Plans du mois + stats |
| `api/get_plan_traitement.php` | GET | `date`, `id_utilisateur`* | Plans d'un jour |
| `api/save_plan_traitement.php` | POST | JSON `{date, plans[], id_utilisateur*}` | Sauvegarde les plans du jour |
| `api/update_plan_traitement.php` | POST | JSON `{id_plan, accepter, date_acceptation, montant}` | Met à jour un plan |

*`id_utilisateur` : paramètre admin uniquement, vérifié côté serveur (`id_role = 1`).

---

## Sécurité

| Protection            | Implémentation                                    |
|-----------------------|---------------------------------------------------|
| Injection SQL         | Requêtes préparées PDO                            |
| XSS                   | `htmlspecialchars()` systématique, `escHtml()` JS |
| CSRF                  | Token par session, vérifié sur tous les POST      |
| Mots de passe         | BCRYPT coût 12                                    |
| Sessions              | HttpOnly, SameSite=Strict                         |
| Accès direct includes | `.htaccess` deny all                              |
| Autorisation          | Chaque API vérifie le rôle avant d'agir           |

Voir [`SECURITE.md`](SECURITE.md) pour le détail complet.

---

## .gitignore recommandé

```gitignore
# Configuration sensible
includes/config.php

# Fichier d'installation
delete_after_install.php

# Dépendances (si gérées via Composer)
vendor/

# IDE
.idea/
.vscode/

# OS
.DS_Store
Thumbs.db

# Logs
*.log
```

---

## Checklist mise en production

- [ ] HTTPS activé (certificat SSL)
- [ ] `delete_after_install.php` supprimé
- [ ] `config.php` absent du dépôt git
- [ ] Mot de passe base de données fort
- [ ] Sauvegardes automatisées
- [ ] Logs PHP et serveur activés
- [ ] Headers de sécurité HTTP (`Content-Security-Policy`, `X-Frame-Options`…)
- [ ] Rate limiting sur la page de connexion
- [ ] `display_errors = Off` en production

---

## Licence

Propriétaire — SELARL La Vespalienne © 2026

---

*Dernière mise à jour : Mai 2026*
