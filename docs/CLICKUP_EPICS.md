# Plugin Moodle NaaS — EPICs 2026 et tickets ClickUp

Référence branches locales (`mod_naas`), HEAD : `feat/change_search_icon` (`fa39a28`).

Noms Git réels : `security/hardening_php_side` (pas `security/php_hardening`), `chore/small_enhancements_2025` (pas `chore/small_enhancements`).

`chore/small_enhancements_2025` part de `origin/feat/change_search_icon`, pas du HEAD local (page admin).

---

## Empilement

```
master
 └─ fix/display_with_u_url                         ← hors fiches 2026.stepN
     └─ feat/output_api                            ← 2026.step1
         └─ chore/vue3_migration                   ← 2026.step2
             └─ feat/global_enhancements
                ├─ requêtes NaaS API
                ├─ 2026.step3 Nugget
                └─ 2026.step4 Recherche
                 └─ security/hardening_php_side    ← 2026.step5
                     └─ feat/change_search_icon    ← suite step3/step4 + page admin
                         └─ chore/small_enhancements_2025
                             ╌╌ (proposé, pas de commit)
                               ├─ 2026.step6 Métadonnées / affichage Nugget
                               ├─ 2026.step7 Notes LTI et audit
                               ├─ 2026.step8 Pédagogie (tentatives, outcomes)
                               └─ 2026.step9 Insertion hash_code
```

Légende tickets : **dans l’EPIC** = porté par ces branches. **EPIC proposée** = ticket encore OPEN, aucun commit dans la pile ; regroupé pour ClickUp, pas encore de branche.

---

## 1. Plugin Moodle 2026.step1 — Basculer sur Output API et les templates mustache

**Branche :** `feat/output_api`

### Commits

- `1e0167e` — Renderer Moodle du plugin
- `c22f659` — Classe renderable pour la page de vue
- `2fb0d51` — Classe renderable pour l’index
- `12e1398` — Classe renderable pour le formulaire LTI
- `a300436` — Suppression du `<script>` inline `NAAS=…`
- `2a754f5` — Module AMD d’init du widget Vue
- `81fb34c` — Template Mustache du point de montage widget
- `7a53b0f` — Erreurs LTI via notification Moodle
- `f25ee15` — Template Mustache du POST LTI
- `d970d16` — `view` / `index` / AMD passés en Mustache
- `345efcf` — Correctifs autoload renderer, nom AMD, auto-submit LTI
- `6cd574d` — Correctif completion xAPI
- `3c64f30` — Rename `aboutButton` (eslint)
- `d9a69d6` — Typage PHPStan du renderer
- `83f16f1` — Conformité phpcs Moodle
- `c4bbefd` — Contextes d’exemple pour le lint Mustache
- `54a43c7` — Rebuild AMD Grunt
- `fc95849` — Rebuild AMD Grunt Moodle 4.1 (CI)
- `e444da9` — Chargement du bundle Vue via `wwwroot`

### Tickets dans cette EPIC

| Ticket | Statut |
|--------|--------|
| Moodle Review - Transition to Templates and Output API #57 | dans l’EPIC |
| Implémentation du fichier de base pour les rendus | dans l’EPIC |
| Implémentation des classes de rendus | dans l’EPIC |
| Implémentation du wrapper de module AMD et modification des injections de scripts en ligne | dans l’EPIC |
| Plugin Moodle NaaS - Déclaration AMD | dans l’EPIC |
| Création des templates mustache | dans l’EPIC |
| Refonte des notifications d’erreur vers `$OUTPUT` | dans l’EPIC |

---

## 2. Plugin Moodle 2026.step2 — Migration Vuetify

**Branche :** `chore/vue3_migration`

Ce n’est **pas** Vuetify. C’est Vue 2 → Vue 3 + Vite 5 + TypeScript.

### Commits

- `1cee2e5` — Vue CLI remplacé par Vite 5 et TypeScript
- `e736b48` — Adapter webservice Moodle, types TS, bootstrap Vue 3
- `98c4324` — Composables vue / recherche / xAPI / entités
- `442f442` — Composants migrés en `<script setup>`
- `316cae0` — Bundle Vue 3 livré + bump de version
- `84487c3` — Fichier index oublié
- `5e357c1` — Suppression des sources Vue 2
- `f73995a` — Compatibilité CI Moodle 4.1
- `21e3a2e` — Type hint renderer retiré pour Moodle 4.1 et 4.5+

### Tickets dans cette EPIC

| Ticket | Statut |
|--------|--------|
| Configuration du build : vite / typescript | dans l’EPIC |
| Remplacer axios par le webservice Moodle `$core/ajax` | dans l’EPIC |
| Création des composables nécessaires | dans l’EPIC |
| Main.vue - migration | dans l’EPIC |
| NuggetView.vue - migration | dans l’EPIC |
| NuggetSearchWidget.vue - migration | dans l’EPIC |
| NuggetSearchFilter.vue - migration | dans l’EPIC |
| NuggetPost.vue - migration | dans l’EPIC |
| NuggetAboutModal.vue - migration | dans l’EPIC |
| NuggetCompletionModal.vue - migration | dans l’EPIC |
| NuggetViewModal.vue - migration | dans l’EPIC |
| Loading.vue - migration | dans l’EPIC |
| RelatedDomain.vue - migration | dans l’EPIC |
| Nettoyage des fichiers obsolètes ou inutilisés et compilation de production | dans l’EPIC |

---

## 3. Améliorer la gestion des requêtes NaaS API

**Branche :** `feat/global_enhancements` (tranche API)

### Commits

- `ac1099a` — Cache Moodle 24 h sur domain / structure / person
- `564087f` — Préchargement de l’iframe LTI dès le `cm_id`

### Tickets

| Ticket | Statut |
|--------|--------|
| Plugin Moodle - La recherche de Nuggets déclenche beaucoup trop de requêtes | **partiel** : cache vocabulaire livré (`ac1099a`) ; N+1 filtres traité en local, **pas de commit** → reste dans cette EPIC |
| Plugin Nugget - Améliorer le format des réponses de `proxy_naas_api` | **partiel** : ré-encodage JSON dans step5 ; `_returns()` encore en `PARAM_RAW` → reste dans cette EPIC (aucun commit pour le typage) |

---

## 4. Plugin Moodle 2026.step3 — Refonte ergonomique de l’affichage du Nugget

**Branches :** `feat/global_enhancements` → `feat/change_search_icon` → `chore/small_enhancements_2025`

### Commits

**`feat/global_enhancements`**

- `9ca1be2` — Tokens CSS, styles scoped, chrome des modales
- `4697c33` — Skeleton de la vue Nugget + bandeau d’erreur / retry
- `ef112fc` — Bandeau d’erreur avec retry sur NuggetView
- `564087f` — xAPI `experienced` retardé de 10 s
- `3c7c0fc` — Cartes Nugget + chargement CSS
- `10ed243` — Résumé affiché en texte, plus de `v-html`
- `9206c08` — Extraction du résumé via DOMParser

**`feat/change_search_icon` (`350560f`)**

- Toolbar langue en pill
- Modales À propos / Completion / Preview retravaillées
- Badges durée/niveau, image lazy, tooltip titre

**`chore/small_enhancements_2025` (`4234b85`)**

- Licence affichée (carte + À propos)
- Titre d’activité auto (nom, auteurs, producteurs, durée)
- Nugget obligatoire à la validation du formulaire

### Tickets dans cette EPIC

| Ticket | Statut |
|--------|--------|
| Plugin Moodle - Ajouter un skeleton au chargement du Nugget | dans l’EPIC |
| Plugin Moodle - Génération du nom du Nugget | dans l’EPIC (`small_enhancements`) |
| Plugin Moodle - Intégrer la licence sur les vignettes des Nuggets | dans l’EPIC (`small_enhancements`) |
| Plugin Moodle - Gestion d’erreur quand le serveur ne répond pas | dans l’EPIC (bandeau retry) + déjà sur develop pour le client PHP |
| Plugin Nugget - Erreur 404 de l’API NaaS | dans l’ancestry develop (`fix/handle-nugget-loading-error`) |
| Affichage statique d’un message lorsqu’un Nugget est dépublié | dans l’ancestry develop (`error_nugget_not_found`) |

Les tickets About / dépublication / intro / auteur ne sont **pas** dans cette EPIC : aucun commit Vue 3. Voir **2026.step6**.

---

## 5. Plugin Moodle 2026.step4 — Refonte ergonomique de la page Recherche

**Branches :** `feat/global_enhancements` → `feat/change_search_icon` → `chore/small_enhancements_2025`

### Commits

**`feat/global_enhancements`**

- `9ca1be2` — Barre de recherche tokenisée
- `fa13ec5` — Skeleton du panneau de filtres
- `4697c33` — Bandeau d’erreur / retry sur la recherche
- `5abbc2d` — Pagination Précédent/Suivant + navigation clavier
- `6bcd431` — Chips de filtres + tout effacer
- `3c7c0fc` — UX filtres / grille
- `5f3e81d` — Correctifs recherche, sélection, chargement

**`feat/change_search_icon`**

- `ea6fc14` — Icône recherche en bouton, aligné sur les filtres
- `350560f` — Champ recherche « pill » + bouton effacer
- `90c7db8` — Stylelint sur la feuille du plugin

**`chore/small_enhancements_2025`**

- Filtres licence et visibilité (public / privé)
- Bouton « Clear filters » dans le panneau

### Tickets dans cette EPIC

| Ticket | Statut |
|--------|--------|
| Plugin Moodle - Améliorer le layout de recherche des Nuggets | dans l’EPIC |
| Recherche automatique | dans l’EPIC (debounce 500 ms) |
| Ajouter un filtre Type de licence | dans l’EPIC (`small_enhancements`) |
| Plugin Moodle - Filtre « type de licence » + « is_public » | dans l’EPIC (`small_enhancements`) |

---

## 6. Plugin Moodle 2026.step5 — Hardening PHP code

**Branche :** `security/hardening_php_side`

### Commits

- `0bfefe4` — Validation UUID/slug sur les IDs proxy
- `990b9bd` — Allowlist des verbes xAPI + limite de taille
- `885234c` — Contrôle d’inscription au cours
- `45a914c` — Mot de passe via env + vérif SSL par défaut
- `48af937` — Ré-encodage JSON des réponses NaaS
- `ed4a604` — Remplacement de `v-html` par du texte interpolé
- `af5359e` — Chaînes de langue erreurs / SSL
- `b214c74` — HTML de la modale À propos via DOMParser
- `47347db` / `07a553f` — Placeholders Moodle `{$a}` en quotes simples

### Tickets dans cette EPIC

Aucun des tickets ClickUp listés n’est le titre exact de cette EPIC. Le travail correspond au hardening proxy / xAPI / SSL.

`outcome.php` et l’audit LTI n’ont **aucun commit** ici : voir **2026.step7**. Le typage `_returns()` de `proxy_naas_api` reste dans l’EPIC API (section 3).

---

## EPICs manquantes (même pile)

### Corriger l’affichage direct via `?u=`

**Branche :** `fix/display_with_u_url`

- `b57c07d` — Récupération de l’instance sur `view.php?u=`
- `7a97702` — Espacement phpcs dans `view.php`

### Outillage / DX du widget Vue

**Branche :** `feat/global_enhancements` (début)

- `0810ed2` — Suppression de Pinia
- `185e3c5` — Hook Vue DevTools en dev
- `e844b74` — Script de sync de version du bundle
- `fa40434` — Rebuild des fichiers built
- `b7a7df3` — Stylelint CSS Moodle

### Refonte de la page d’administration du plugin

**Branche :** `feat/change_search_icon` (HEAD)

- `fa39a28` — Réorg des settings, test de connexion accessible

---

## EPICs proposées — aucun commit dans la pile

Pas de branche. À créer au-dessus de `chore/small_enhancements_2025` (ou en parallèle). Ne pas coller dans step1–step5.

### 7. Plugin Moodle 2026.step6 — Métadonnées et affichage Nugget

Suite de step3 : régressions Vue 3 et champs Moodle encore vides.

| Ticket | Pourquoi aucun commit |
|--------|------------------------|
| Afficher le producteur et le « with partners » dans le about du plugin Moodle | Fait en Vue 2 (PR #114) ; **absent du About Vue 3** |
| Plugin Moodle - Carte Nugget affiche les infos de dépublication | `displayinfo` retiré des cartes dans `350560f` |
| Intégrer des données du Nugget dans le champ Description (Moodle) | Le `intro` Moodle n’est pas prérempli |
| Communication avec l’auteur d’un nugget | Bio affichée, pas de contact / mailto |

### 8. Plugin Moodle 2026.step7 — Notes LTI et audit

Suite de step5 : le hardening proxy / xAPI / SSL est livré ; notes et traces LTI non.

| Ticket | Pourquoi aucun commit |
|--------|------------------------|
| Plugin Moodle - Sécurisation de la remontée des notes | `outcome.php` n’est pas durci dans `security/hardening_php_side` |
| Plugin Moodle Nugget - Auditabilité des échanges LTI | Seulement `db/log.php` vue/add/update ; pas d’audit LTI |

### 9. Plugin Moodle 2026.step8 — Pédagogie (tentatives, outcomes)

Réglages d’activité Moodle, hors affichage Vue.

| Ticket | Pourquoi aucun commit |
|--------|------------------------|
| Plugin Moodle - Nombre de tentatives | Pas de champ « tentatives autorisées » dans `mod_form` (seulement completion min attempts) |
| Plugin Moodle - Learning Outcomes - Alignement | Les LO s’affichent ; pas d’alignement Moodle Outcomes |

### 10. Plugin Moodle 2026.step9 — Insertion par hash_code

| Ticket | Pourquoi aucun commit |
|--------|------------------------|
| Feature de validation dans Moodle permettant d’entrer et valider le hash_code | Étude seulement (`HASHED_INSERTION_CODE.md`) |

### Reste OPEN dans une EPIC déjà ouverte (pas de nouvelle fiche)

| Ticket | EPIC | Reste à committer |
|--------|------|-------------------|
| Plugin Moodle - La recherche de Nuggets déclenche beaucoup trop de requêtes | **3. API** | N+1 filtres + paint search : code local, pas de commit |
| Plugin Nugget - Améliorer le format des réponses de `proxy_naas_api` | **3. API** (+ step5 partiel) | `_returns()` encore `PARAM_RAW` |

---

## Tickets déjà livrés hors pile 2026 (master / develop)

Pas dans `feat/output_api` … `feat/change_search_icon` comme travail nouveau. Si le ticket ClickUp est encore OPEN, le fermer ou le pointer vers la release concernée.

| Ticket | Où |
|--------|-----|
| Moodle Review - Update Ajax implementation to External Services | master 2.4.1 (`db/services.php`) |
| Moodle Review - Privacy API missing add_external_location_link #60 | master 2.4.1 |
| Moodle Review - Missing Header and Copyright Information in JS File #59 | master 2.4.1 |
| Moodle Review - Hard-coded language string #58 | master 2.4.1 |
| Moodle Review - Don't call curl_init directly #55 | master 2.4.1 (`new \curl`) |
| Plugin Nugget - Activité plutôt que Ressource | master 2.4.6 (`FEATURE_MOD_ARCHETYPE` → null) |
| Liste des espaces d’intégration des Nuggets | master 2.4.1 (`index.php`) |
| Plugin in Moodle « Bouton Back to course » | déjà dans `view.php` / `index.php` |
| Gestion d’erreur en cas d’ID d’institut invalide | develop PR #117 |
| Envoi de la langue dans les requêtes LTI du plugin Moodle | develop PR #116 (`launch_presentation_locale`) |
| Supprimer les boutons Retour en haut et en bas de l’affichage d’un Nugget | develop PR #122 (popup rating) ; la modale Vue 3 a encore back/next — à confirmer |

---

## Tableau récap tickets → EPIC

| Ticket | EPIC / pile |
|--------|-------------|
| Moodle Review - Transition to Templates and Output API #57 | **step1** |
| Implémentation du fichier de base pour les rendus | **step1** |
| Implémentation des classes de rendus | **step1** |
| Implémentation du wrapper AMD + scripts en ligne | **step1** |
| Plugin Moodle NaaS - Déclaration AMD | **step1** |
| Création des templates mustache | **step1** |
| Refonte des notifications d’erreur vers `$OUTPUT` | **step1** |
| Configuration du build : vite / typescript | **step2** |
| Remplacer axios par `$core/ajax` | **step2** |
| Création des composables nécessaires | **step2** |
| Main.vue … RelatedDomain.vue - migration (11 tickets) | **step2** |
| Nettoyage fichiers obsolètes + compilation prod | **step2** |
| Skeleton chargement Nugget | **step3** |
| Génération du nom du Nugget | **step3** |
| Licence sur les vignettes | **step3** |
| Gestion d’erreur serveur ne répond pas | **step3** (+ develop) |
| Erreur 404 API NaaS | **step3** (ancestry develop) |
| Message Nugget dépublié | **step3** (ancestry develop) |
| Layout recherche des Nuggets | **step4** |
| Recherche automatique | **step4** |
| Filtre Type de licence | **step4** |
| Filtre licence + is_public | **step4** |
| Trop de requêtes recherche | **3. API** (partiel, N+1 local non commité) |
| Format réponses `proxy_naas_api` | **3. API** (partiel) + step5 JSON |
| Producteur / with partners (About) | **step6** proposée |
| Infos de dépublication sur la carte | **step6** proposée |
| Description Moodle (`intro`) | **step6** proposée |
| Communication avec l’auteur | **step6** proposée |
| Sécurisation remontée des notes | **step7** proposée |
| Auditabilité des échanges LTI | **step7** proposée |
| Nombre de tentatives | **step8** proposée |
| Learning Outcomes - Alignement | **step8** proposée |
| Validation `hash_code` | **step9** proposée |
| Tickets Moodle Review #55 #58 #59 #60, activité vs ressource, index, back to course, institut, locale LTI | déjà livrés hors pile 2026 |
