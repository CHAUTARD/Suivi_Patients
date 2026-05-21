# 🔐 Guide de Sécurité — SELARL La Vespalienne

## Vue d'ensemble

Ce document décrit les mesures de sécurité implémentées dans l'application pour protéger les données des utilisateurs et prévenir les attaques courantes.

---

## 1. Protection contre les injections SQL

### Mesure : Requêtes préparées (Prepared Statements)

**Toutes les requêtes SQL utilisent des paramètres liés**, ce qui est la meilleure défense contre les injections SQL.

#### ✅ Sécurisé (implémenté)
```php
$stmt = $db->prepare('SELECT * FROM utilisateur WHERE login = ?');
$stmt->execute([$login]);  // ← Paramètre lié, sécurisé
```

#### ❌ Dangereux (NEP JAMAIS FAIRE)
```php
$sql = "SELECT * FROM utilisateur WHERE login = '" . $login . "'";
$db->query($sql);  // INJECTION SQL POSSIBLE !
```

### Fichiers concernés
- `includes/auth.php` — Vérification de login
- `admin/utilisateurs.php` — CRUD utilisateurs
- `admin/piliers.php` — CRUD piliers
- `admin/actions.php` — CRUD actions
- `api/get_recap.php` — Requêtes récapitulatif
- `delete_after_install.php` — Création compte admin

**Aucune concaténation directe de variables dans les requêtes SQL n'existe.**

---

## 2. Encodage sécurisé des mots de passe

### Mesure : Hachage BCRYPT

Les mots de passe sont **hachés avec BCRYPT**, l'algorithme recommandé par OWASP.

#### Caractéristiques de BCRYPT
- **Lent intentionnellement** : ~200ms par hachage (protège contre les attaques par force brute)
- **Salé automatiquement** : chaque hash est unique, même pour le même mot de passe
- **Itérations adaptatives** : coût = 12 (4096 itérations)
- **Accepté par OWASP** : "Approved algorithm for password hashing"

#### Implémentation
```php
// Hachage (delete_after_install.php, admin/utilisateurs.php, changer_pwd.php)
$hash = hashPassword($pwd);  // Utilise BCRYPT avec coût 12

// Vérification (includes/auth.php)
if (verifyPassword($pwd, $hash)) {
    // Mot de passe correct
}
```

#### Base de données
- Colonne `pwd` en **VARCHAR(255)** pour accueillir les hashes BCRYPT (~60 caractères)
- Ne stocke JAMAIS le mot de passe en clair

---

## 3. Validation stricte des inputs

### Fonction centralisée : `includes/security.php`

#### Login
```php
validateLogin($login)  // Valide et retourne le login ou null

// Règles :
// - Longueur : 3-30 caractères
// - Format : [a-zA-Z0-9_-] uniquement (alphanumériques, tiret, underscore)
// - Exemple valide : "dentiste_01", "user-02"
// - Exemple invalide : "ab" (trop court), "user@domain.fr" (caractères spéciaux)
```

#### Mot de passe
```php
validatePassword($pwd)  // Valide et retourne le mot de passe ou null

// Règles :
// - Longueur : 6-128 caractères
// - Aucune restriction sur les caractères (permet spéciaux, accents, etc.)
// - Exemple valide : "MdpS3cur!€", "açéè123"
```

#### Filtre utilisateur (Admin API)
```php
validateUserFilter($id, $currentId, $isAdmin)

// - Dentiste : retourne son ID, ne peut pas voir les autres données
// - Admin : peut voir tous les ID, ou filtre 0 = tous les dentistes
```

### Fichiers utilisant la validation
- `delete_after_install.php` — Création compte admin
- `includes/auth.php` — Login
- `changer_pwd.php` — Changement mot de passe
- `admin/utilisateurs.php` — CRUD utilisateurs

---

## 4. Protection CSRF (Cross-Site Request Forgery)

### Mesure : Token CSRF

Chaque utilisateur reçoit un token unique et éphémère.

#### ✅ Token placé dans
- Meta tag HTML → Récupéré par jQuery
- Local storage sessions PHP
- Automatiquement envoyé dans chaque requête AJAX via header `X-CSRF-Token`

#### Implémentation
```php
// Générer (includes/auth.php)
$token = generateCsrfToken();

// Vérifier (includes/functions.php)
checkCsrf();  // Lève une exception si invalid

// Vérifier manuellement
if (!verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    jsonError(403, 'Token invalide');
}
```

#### Fichiers concernés
- Tous les endpoints API (`api/*.php`)
- Toutes les pages contenant un formulaire (`admin/*.php`)

---

## 5. Sécurité des sessions

### Configuration cookies (includes/auth.php)

```php
session_set_cookie_params([
    'lifetime' => 0,        // Cookie de session (fermé à la fermeture du navigateur)
    'path'     => '/',      // Valide sur tout le site
    'secure'   => false,    // ⚠️ À passer à true en HTTPS production
    'httponly' => true,     // ✓ Inaccessible à JavaScript (XSS mitigation)
    'samesite' => 'Strict', // ✓ Protège contre les requêtes cross-site
]);
```

### Régénération de session
```php
session_regenerate_id(true);  // Changé à chaque login
```

### Déconnexion
- Toutes les variables de session supprimées
- Cookie session détruit
- Redirections forcées vers login

---

## 6. Protection XSS (Cross-Site Scripting)

### Mesure : Échappement HTML

Toutes les variables utilisateur sont échappées avant affichage.

#### ✅ Sécurisé
```php
// PHP
<?= htmlspecialchars($user_input) ?>

// JavaScript
const text = $('<div>').text(userInput).html();
```

#### ❌ Dangereux
```php
<?= $user_input ?>  // ← XSS possible !
```

### Fichiers utilisant l'échappement
- Tous les templates HTML (includes/header.php, includes/footer.php, etc.)
- Tous les fichiers page (index.php, saisie.php, etc.)
- Tous les fichiers admin (admin/utilisateurs.php, etc.)

---

## 7. Protection `.htaccess`

### Fichier : `includes/.htaccess`

```apache
Deny from all
```

**Empêche l'accès direct** aux fichiers includes via le navigateur :
- `http://localhost/...path.../includes/auth.php` → 403 Forbidden
- Seules les inclusions PHP côté serveur sont autorisées

---

## 8. Recommandations pour la production

### 🔴 CRITIQUE

1. **Supprimez `delete_after_install.php`** après création du premier compte admin
   - OU renommez-le (ex: `delete_after_install.php.bak`)
   - OU protégez-le avec un `.htaccess`

2. **HTTPS obligatoire**
   - Changez `'secure' => false` à `'secure' => true` dans `includes/auth.php`
   - Certificat SSL gratuit via Let's Encrypt

3. **Varfier la configuration MySQL**
   - Utilisateur BD avec droits minimaux
   - Mot de passe MySQL fort
   - Base de données distante ou localhost uniquement

### 🟡 IMPORTANT

4. **Headers de sécurité** (ajouter à `.htaccess` ou `config.php`)
   ```php
   header('X-Content-Type-Options: nosniff');
   header('X-Frame-Options: DENY');
   header('X-XSS-Protection: 1; mode=block');
   header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
   ```

5. **Logs et monitoring**
   - Journaliser les tentatives de login
   - Alerter sur les modifications admin
   - Monitorer les tentatives d'injection

6. **Bakcup réguliers**
   - Sauvegardes quotidiennes de la base de données
   - Stockage hors site

### 🟢 RECOMMANDÉ

7. **Rate limiting** sur le login (anti-brute force)
8. **2FA** (authentification double facteur)
9. **Audit des accès** administrateur
10. **WAF** (Web Application Firewall)

---

## 9. Checklist de sécurité

- [ ] ✅ Toutes les requêtes SQL utilisent des paramètres liés
- [ ] ✅ Tous les mots de passe sont hachés en BCRYPT
- [ ] ✅ Tous les inputs sont validés strictement
- [ ] ✅ Token CSRF implémenté
- [ ] ✅ Sessions sécurisées (HttpOnly, SameSite)
- [ ] ✅ XSS : échappement HTML systématique
- [ ] ✅ Includes protégés par `.htaccess`
- [ ] ⚠️ HTTPS configuré en production
- [ ] ⚠️ `delete_after_install.php` supprimé en production
- [ ] ⚠️ Headers de sécurité ajoutés

---

## 10. Références

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
- [PHP Data Objects (PDO)](https://www.php.net/manual/en/book.pdo.php)
- [BCRYPT](https://en.wikipedia.org/wiki/Bcrypt)
- [Session Security](https://www.php.net/manual/en/session.security.php)

---

**Dernière mise à jour** : 20 avril 2026
