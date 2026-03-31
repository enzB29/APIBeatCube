# Tests & CI Setup

## Structure des fichiers

```
.github/
  workflows/
    ci.yml                          ← Pipeline CI GitHub Actions
phpunit.xml                         ← Configuration PHPUnit
tests/
  Controller/
    AuthControllerTest.php          ← Tests AuthController (20 tests)
    MusiqueControllerTest.php       ← Tests MusiqueController (22 tests)
    UtilisateurControllerTest.php   ← Tests UtilisateurController (18 tests)
    UtilisateurMusiqueControllerTest.php ← Tests UtilisateurMusiqueController (32 tests)
    SuccessControllerTest.php       ← Tests SuccessController + UtilisateurSuccessController (19 tests)
```

## Lancer les tests localement

```bash
# Tous les tests
php bin/phpunit --no-coverage

# Un fichier spécifique
php bin/phpunit tests/Controller/AuthControllerTest.php

# Mode verbeux
php bin/phpunit --testdox
```

## Ce que testent les suites

### AuthControllerTest (20 tests)
- `signin` : champs manquants, email invalide, username/email déjà pris, succès
- `login` : identifiant/mdp manquants, user introuvable, mauvais mdp, ban temporaire/permanent, succès par username et email
- `logout` : toujours 200, sans token
- `me` : header manquant, token invalide, user introuvable, succès, header malformé
- `updateUsername` : token manquant/invalide, username trop court, conflit, succès, trim whitespace, exception service

### MusiqueControllerTest (22 tests)
- `upload` : token manquant/invalide, pas de fichier, extension invalide, metadata manquante, année non numérique, succès, exception service
- `UploadByUserId` : token manquant/invalide, non-admin, succès, liste vide
- `MyUploads` : token manquant, succès, liste vide
- `download` : fichier introuvable, fichier existant | **attention ce test est commenté car le test est considéré comme risqué.**
- `delete` : token manquant, non-admin, not found, succès, exception
- `allMusics` : liste vide, liste remplie

### UtilisateurControllerTest (18 tests)
- `AllUsers` : liste vide, liste remplie, statut banni, champs présents
- `ban` : token manquant/invalide, non-admin, user not found, raison par défaut, raison custom + jours, ban permanent, champs réponse
- `unban` : token manquant/invalide, non-admin, user not found, succès, userId dans réponse

### UtilisateurMusiqueControllerTest (32 tests)
- `save` : token manquant/invalide, uuid manquant, score/accuracy/fullCombo invalides, score 0 valide, succès, service error, exception
- `topScores/topAccuracy/topFullCombo` : limite invalide/négative, succès, liste vide, exception
- `ScoresFromUserId` : not found, succès avec structure
- `NumberOfGames` : zéro, count correct
- `BestScoreFromUserId` : structure réponse
- `AverageAccuracy` : valeur normale, null
- `NumberOfFullCombo` / `TotalScore` : valeurs correctes
- `leaderboard` : limit par défaut, custom, cap 500, exception
- `userRank` : not found, succès, exception

### SuccessControllerTest + UtilisateurSuccessControllerTest (19 tests)
- `AllSuccesses` : liste vide, liste remplie, structure
- `SuccessByUserId` : liste vide, liste remplie, structure complète
- `UsersBySuccessId` : liste vide, liste remplie, plusieurs users
- `saveSuccess` : token manquant/invalide, user/success not found, déjà obtenu, succès, erreur inconnue → 400, exception → 500
