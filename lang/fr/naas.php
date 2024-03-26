<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software] = you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 3 of the License; or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful;
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not; see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'url'; language 'en'; branch 'MOODLE_20_STABLE'
 *
 * @package    mod_naas
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Nugget';
$string['modulename'] = 'Nugget';
$string['modulename_help'] = 'Le Plugin Moodle Nugget permet à un enseignant d\'intégrer un micro-contenu provenant du serveur NaaS.

L\'enseignant peut autoriser l\'exercice Nugget à être tenté plusieurs fois. Une limite de temps peut être fixée.

Chaque tentative est notée automatiquement et la note est enregistrée dans le carnet de notes.

Les Nuggets peuvent être utilisées

* En tant qu\'examens de cours
* En tant que mini-tests de relecture à la fin d\'un sujet
* En tant qu\'entraînement à l\'examen en utilisant des questions d\'examens antérieurs
* En tant qu\'auto-évaluation';
$string['modulename_link'] = 'mod/naas/view';
$string['modulenameplural'] = 'Nuggets';
$string['pluginadministration'] = '';
$string['nugget'] = 'Nugget';

$string['naas_settings'] = 'Configuration NaaS';
$string['naas_settings_information'] = 'La documentation de l\'API NaaS peut fournir des informations sur la façon d\'obtenir le point d\'accès et les informations d\'identification de l\'API. Vous pouvez consulter leur documentation pour plus d\'informations ou contacter l\'équipe de support du NaaS.';
$string['naas_settings_endpoint'] = 'Point d\'accès de l\'API NaaS';
$string['naas_settings_endpoint_help'] = 'Entrez le point d\'accès à l\'API NaaS';
$string['naas_settings_username'] = 'Nom d\'utilisateur de l\'API NaaS';
$string['naas_settings_username_help'] = 'Entrez le nom d\'utilisateur de l\'API NaaS';
$string['naas_settings_structure_id'] = 'ID d\'institut NaaS API';
$string['naas_settings_structure_id_help'] = 'Entrez l\'identifiant de votre institution';
$string['naas_settings_password'] = 'Mot de passe NaaS API';
$string['naas_settings_password_help'] = 'Mot de passe NaaS API';
$string['naas_settings_timeout'] = 'Délai d\'expiration des requêtes NaaS API';
$string['naas_settings_timeout_help'] = 'Nombre de secondes à attendre avant annulaion d\'une requête à l\'API NaaS (0 pour désactiver)';
$string['naas_settings_css'] = 'NaaS CSS';
$string['naas_settings_css_help'] = 'Lien vers un fichier CSS à appliqure aux ressources NaaS)';
$string['naas_settings_filter'] = 'Filter de recherche';
$string['naas_settings_filter_help'] = 'Une requête permettant de filter les résultats';
$string['naas_settings_feedback'] = 'Retours utilisateurs';
$string['naas_settings_feedback_help'] = 'Activer l\'option permet aux apprenants de signaler un problème sur les activités du Nugget.';

$string['naas_settings_privacy'] = 'NaaS Vie Privée';
$string['naas_settings_privacy_information'] = 'Le Plugin Moodle Nugget nécessite la collecte et le stockage de données personnelles telles que le nom et l\'adresse mail d\'un utilisateur afin d\'améliorer l\'expérience utilisateur par une analyse statistique anonyme des données. Les données collectées sont stockées sur le serveur NaaS et ne sont jamais partagées avec des tiers.';
$string['naas_settings_privacy_learner_mail'] = 'Collecter l\'email des apprenants';
$string['naas_settings_privacy_learner_mail_help'] = 'Envoyer l`email des apprenants lors de la connexion au NaaS afin d\'améliorer l\'expérience des utilisateurs au travers de statistiques anonymes';
$string['naas_settings_privacy_learner_name'] = 'Collecter le nom des apprenants';
$string['naas_settings_privacy_learner_name_help'] = 'Envoyer le nom des apprenants au server NaaS afin d\'améliorer l\'expérience utilisateur';

// Moodle Form
$string['naas_unable_connect'] = 'Impossible de contacter le serveur NaaS';

$string['name_help'] = 'Nom de la nugget qui apparaitra dans l\'espace de cours ';
$string['name_display'] = 'Nom à afficher';
$string['cgu_agreement'] = 'J\'ai lu et accepte les <a target="_blank" href="https://doc.clickup.com/2594656/p/h/2f5v0-13342/fff6a689cd78033">Conditions Générales d\'Utilisation de la platforme NaaS</a>';

// Vue Form
$string['nugget_search'] = 'Rechercher des nuggets';
$string['nugget_search_here'] = 'Pour commencer, entrez un mot-clé';
$string['nugget_search_no_result'] = 'La recherche n\'a donné aucun résultat, veuillez utiliser un autre mot-clé.';
$string['click_to_modify'] = 'Modifier la nugget selectionnée';
$string['no_nugget'] = 'Aucun nugget trouvé';
$string['clear_filters'] = 'Effacer les filtres';
$string['show_more_authors'] = 'Afficher plus';
$string['hide_authors'] = 'Cacher';

$string['about'] = 'À Propos';
$string['back_to_course'] = 'Retour à l\'index du cours';
$string['next_unit'] = 'Unité suivante';
$string['show_more_nugget_button'] = 'Afficher plus ...';

$string['select_button'] = 'Selectionner';
$string['preview_button'] = 'Aperçu';
$string['loading'] = 'Chargement...';

/* Metadata */
$string["preview"] = "Aperçu : ";
$string["description"] = "Description";
$string["in_brief"] = "En Bref";
$string["about_author"] = "À propos de l'auteur";
$string["learning_outcomes"] = "Objectifs d'apprentissage";
$string["prerequisites"] = "Prérequis";
$string["references"] = "Réferences";
$string["field_of_study"] = "Domaine";
$string["authors"] = "Auteurs";
$string["producers"] = "Producteurs";
$string["language"] = "Langue";
$string["duration"] = "Durée";
$string["level"] = "Niveau";
$string["structure_id"] = "Structure";
$string["advanced"] = "Avancé";
$string["intermediate"] = "Intermédiaire";
$string["beginner"] = "Débutant";
$string["tags"] = "Mots clés";
$string["type"] = "Type";
$string["lesson"] = "Lesson";
$string["tutorial"] = "Tutorial";
$string["demo"] = "Démo";
$string["en"] = "Anglais";
$string["fr"] = "Français";
$string["de"] = "Allemand";
$string["es"] = "Espagnol";
$string["it"] = "Italien";
$string["pl"] = "Polonais";
$string["sv"] = "Suedois";
$string["publication_date"] = "Date de publication";

/* Rating */
$string['rating_title'] = 'Évaluez ce nugget';
$string['rating_description'] = 'Votre note servira à améliorer notre contenu';
$string['rating_send'] = 'Envoyer';
$string['rating_sent'] = 'Envoyé ✔';

$string['learning_outcomes_desc'] = 'Vous avez terminé ce nugget dont les objectifs étaient :';
$string['complete_nugget'] = 'J\'ai terminé ma consultation du Nugget';


/* LTI */
/* Grade section */
$string['grade_type'] = 'Type de note';
$string['attempts_allowed'] = 'Tentatives autorisées';
$string['grade_method'] = 'Méthode de passage';
$string['gradehighest'] = 'Note la plus élevée';
$string['gradeaverage'] = 'Note moyenne';
$string['attemptfirst'] = 'Première tentative';
$string['attemptlast'] = 'Dernière tentative';
$string['grade_method_help'] = 'Lorsque plusieurs tentatives sont autorisées, les méthodes suivantes sont disponibles pour calculer la note finale du nugget :

* Note la plus élevée de toutes les tentatives
* Note moyenne de toutes les tentatives
* Première tentative (toutes les autres tentatives sont ignorées)
* Dernière tentative (toutes les autres tentatives sont ignorées)';

/* Activity Completion section */
$string['completionpass'] = 'Nécessité d\'une note de passage';
$string['completionpassdesc'] = 'L\'élève doit obtenir la note de passage pour réaliser cette activité.';
$string['completionpasshelp'] = 'Si cette option est activée, cette activité est considérée comme terminée lorsque l\'étudiant obtient une note de passage (telle que spécifiée dans la section Grade des paramètres du nugget) ou une note supérieure.';

$string['completionattemptsexhausted'] = 'Ou toutes les tentatives disponibles ont été complété';
$string['completionattemptsexhaustedhelp'] = 'Marquer le nugget comme terminé lorsque l\'étudiant a épuisé le nombre maximum de tentatives.';

$string['completionminattempts'] = 'Nombre minimum de tentatives :';
$string['completionminattemptsdesc'] = 'Nombre minimum de tentatives requises : {$a}';
$string['completionminattemptsgroup'] = 'Tentatives requises';
$string['completionminattemptserror'] = 'Le nombre minimum de tentatives doit être inférieur ou égal au nombre de tentatives autorisées.';

/* Activity Completion section error messages */
$string['gradetopassnotset'] = 'Ce nugget n\'a pas encore de note de passage définie. Elle peut être définie dans la section Grade des paramètres du nugget.';
$string['gradetopassmustbeset'] = 'La note à obtenir ne peut pas être zéro, car la méthode d\'achèvement de ce nugget est réglée pour exiger la note de passage. Veuillez indiquer une valeur différente de zéro.';
