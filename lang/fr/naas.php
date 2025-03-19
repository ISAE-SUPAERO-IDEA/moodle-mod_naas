<?php
// This file is part of Moodle - http://moodle.org
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * French messages.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
$string["about"] = "À Propos";
$string["about_author"] = "À propos de l'auteur";
$string["advanced"] = "Avancé";
$string["attemptfirst"] = "Première tentative";
$string["attemptlast"] = "Dernière tentative";
$string["authors"] = "Auteurs";
$string["back_to_course"] = "Retour à l’index du cours";
$string["beginner"] = "Débutant";
$string["cgu_agreement"] = "J’ai lu et j’accepte les <a target=\"_blank\" href=\"https://doc.clickup.com/2594656/p/h/2f5v0-13342/fff6a689cd78033\">Conditions Générales d’Utilisation de la plateforme NaaS</a>";
$string["clear_filters"] = "Effacer les filtres";
$string["click_to_replace"] = "Remplacer le nugget sélectionné";
$string["complete_nugget"] = "J’ai terminé ma consultation du Nugget";
$string["completiondetail:passgrade"] = "Compléter avec la note de passage";
$string["completiondetail:passorexhaust"] = "Compléter sur un succès ou épuisement des tentatives";
$string["completionminattempts"] = "Nombre minimum de tentatives :";
$string["completionminattemptsdesc"] = 'Nombre minimum de tentatives requises : {$a}';
$string["completionminattemptserror"] = "Le nombre minimum de tentatives doit être inférieur ou égal au nombre de tentatives autorisées.";
$string["completionminattemptsgroup"] = "Tentatives requises";
$string["completionpass"] = "Nécessité d’une note de passage";
$string["completionpassdesc"] = "L’élève doit obtenir la note de passage pour réaliser cette activité.";
$string["completionpasshelp"] = "Si cette option est activée, cette activité est considérée comme terminée lorsque l’étudiant obtient une note de passage (telle que spécifiée dans la section Note des paramètres du nugget) ou une note supérieure.";
$string["de"] = "Allemand";
$string["demo"] = "Démo";
$string["description"] = "Description";
$string["duration"] = "Durée";
$string["en"] = "Anglais";
$string["error:must_be_strictly_positive"] = "Ce doit être un nombre strictement positif.";
$string['error:unsupported_grade_method'] = 'La méthode de notation "{$a}" n\'est pas supportée.';
$string["es"] = "Espagnol";
$string["field_of_study"] = "Domaine";
$string["fr"] = "Français";
$string["grade_method"] = "Stratégie de notation";
$string["grade_method_help"] = "<p>Lorsque plusieurs tentatives sont autorisées, les méthodes suivantes sont disponibles pour calculer la note finale du nugget :<ul><li>Note la plus élevée de toutes les tentatives</li><li>Note moyenne de toutes les tentatives</li><li>Première tentative (toutes les autres tentatives sont ignorées)</li><li>Dernière tentative (toutes les autres tentatives sont ignorées)</li></ul></p>";
$string["grade_type"] = "Type de note";
$string["gradehighest"] = "Note la plus élevée";
$string["gradetopassmustbeset"] = "La note à obtenir ne peut pas être zéro, car la méthode d’achèvement de ce nugget est réglée pour exiger la note de passage. Veuillez indiquer une valeur différente de zéro.";
$string["gradetopassnotset"] = "Ce nugget n’a pas encore de note de passage définie. Elle peut être définie dans la section Note des paramètres du nugget.";
$string["hide_authors"] = "Cacher";
$string["in_brief"] = "En Bref";
$string["intermediate"] = "Intermédiaire";
$string["it"] = "Italien";
$string["language"] = "Langue";
$string["learning_outcomes"] = "Objectifs d'apprentissage";
$string["learning_outcomes_desc"] = "Vous avez terminé ce nugget dont les objectifs étaient :";
$string["lesson"] = "Leçon";
$string["level"] = "Niveau";
$string["loading"] = "Chargement…";
$string["max_grade"] = "Note maximum";
$string["mod_naas:addinstance"] = "Peut ajouter une activité Nugget au cours";
$string["mod_naas:view"] = "Peut visualiser un Nugget";
$string["moduleintro"] = "Introduction au module";
$string["modulename"] = "Nugget";
$string["modulename_help"] = "<p>Le Plugin Moodle Nugget permet à un enseignant d’intégrer un micro-contenu provenant du serveur NaaS.<br/>L’enseignant peut autoriser l’exercice Nugget à être tenté plusieurs fois. Une limite de temps peut être fixée.<br/>Chaque tentative est notée automatiquement et la note est enregistrée dans le carnet de notes.<br/>Les Nuggets peuvent être utilisés<ul><li>* En tant qu’examens de cours</li><li>* En tant que mini-tests de relecture à la fin d’un sujet</li><li>* En tant qu’entraînement à l’examen en utilisant des questions d’examens antérieurs</li><li>* En tant qu’auto-évaluation</li></ul></p>";
$string["modulename_link"] = "mod/naas/view";
$string["modulenameplural"] = "Nuggets";
$string["naas:addinstance"] = "Ajouter un module Nugget";
$string["naas_settings"] = "Configuration NaaS";
$string["naas_settings_css"] = "NaaS CSS";
$string["naas_settings_css_help"] = "Lien vers un fichier CSS à appliquer aux ressources NaaS";
$string["naas_settings_endpoint"] = "Point d’accès de l’API NaaS";
$string["naas_settings_endpoint_help"] = "Entrez le point d’accès à l’API NaaS";
$string["naas_settings_feedback"] = "Retours utilisateurs";
$string["naas_settings_feedback_help"] = "Activer l’option permet aux apprenants de signaler un problème sur les activités du Nugget.";
$string["naas_settings_filter"] = "Filtre de recherche";
$string["naas_settings_filter_help"] = "Une requête permettant de filtrer les résultats";
$string["naas_settings_information"] = "La documentation de l’API NaaS peut fournir des informations sur la façon d’obtenir le point d’accès et les informations d’identification de l’API. Vous pouvez consulter leur documentation pour plus d’informations ou contacter l’équipe de support du NaaS.";
$string["naas_settings_password"] = "Mot de passe NaaS API";
$string["naas_settings_password_help"] = "Mot de passe NaaS API";
$string["naas_settings_privacy"] = "NaaS Vie Privée";
$string["naas_settings_privacy_information"] = "Le Plugin Moodle Nugget nécessite la collecte et le stockage de données personnelles telles que le nom et l’adresse de courriel d’un utilisateur afin d’améliorer l’expérience utilisateur par une analyse statistique anonyme des données. Les données collectées sont stockées sur le serveur NaaS et ne sont jamais partagées avec des tiers.";
$string["naas_settings_privacy_learner_mail"] = "Collecter le courriel des apprenants";
$string["naas_settings_privacy_learner_mail_help"] = "Envoyer le courriel des apprenants lors de la connexion au NaaS afin d’améliorer l’expérience des utilisateurs au travers de statistiques anonymes";
$string["naas_settings_privacy_learner_name"] = "Collecter le nom des apprenants";
$string["naas_settings_privacy_learner_name_help"] = "Envoyer le nom des apprenants au server NaaS afin d’améliorer l’expérience utilisateur";
$string["naas_settings_structure_id"] = "ID d’institut NaaS API";
$string["naas_settings_structure_id_help"] = "Entrez l’identifiant de votre institution";
$string["naas_settings_timeout"] = "Délai d’expiration des requêtes NaaS API";
$string["naas_settings_timeout_help"] = "Nombre de secondes à attendre avant annulation d’une requête à l’API NaaS (0 pour désactiver)";
$string["naas_settings_username"] = "Nom d’utilisateur de l’API NaaS";
$string["naas_settings_username_help"] = "Entrez le nom d’utilisateur de l’API NaaS";
$string["naas_unable_connect"] = "Impossible de contacter le serveur NaaS";
$string["name_display"] = "Nom à afficher";
$string["name_help"] = "Nom du nugget qui apparaitra dans l’espace de cours ";
$string["next_unit"] = "Unité suivante";
$string["no_nugget"] = "Aucun nugget trouvé";
$string["nugget"] = "Nugget";
$string["nugget_search"] = "Rechercher des nuggets";
$string["nugget_search_here"] = "Pour commencer, entrez un mot-clé";
$string["nugget_search_no_result"] = "La recherche n’a donné aucun résultat, veuillez utiliser un autre mot-clé.";
$string["pl"] = "Polonais";
$string["pluginadministration"] = "";
$string["pluginname"] = "Nugget";
$string["prerequisites"] = "Prérequis";
$string["preview"] = "Aperçu : ";
$string["preview_button"] = "Aperçu";
$string["privacy:metadata:core_completion"] = "Le plugin Nugget enregistre la complétion d'une activité Nugget.";
$string["privacy:metadata:core_grades"] = "Une fois terminé, le NaaS renverra au plugin une note pour la session de l'utilisateur sur l'activité Nugget.";
$string["privacy:metadata:naas"] = "L'activité Nugget communique avec la plateforme NaaS pour récupérer le Nugget, suivre la réalisation du Nugget et renvoyer une note. Les traces d'apprentissage liées à l'exécution des Nuggets sont collectées de manière anonyme sur la plateforme NaaS. Lorsque la collecte de l'email de l'utilisateur est activée pour le plugin, ces traces seront associées à l'email, permettant de personnaliser l'expérience de l'utilisateur. Les données collectées sont stockées sur le serveur de NaaS et ne sont pas partagées avec des tiers.";
$string["privacy:metadata:naas:context_id"] = "L'identifiant du Nugget ";
$string["privacy:metadata:naas:lis_outcome_service_url"] = "L'URL que le NaaS utilisera pour communiquer l'achèvement du Nugget et la note obtenue.";
$string["privacy:metadata:naas:lis_person_contact_email_primary"] = "L'email de l'utilisateur. Ces informations peuvent être utilisées pour personnaliser l'expérience de l'utilisateur en associant ses différentes sessions sur différents Nuggets. L'administrateur peut désactiver la transmission de ces informations.";
$string["privacy:metadata:naas:lis_person_name_full"] = "Le nom et le prénom de l'utilisateur. Ces informations sont utilisées pour améliorer l'expérience de l'utilisateur. L'administrateur peut désactiver la transmission de ces informations.";
$string["privacy:metadata:naas:lis_result_sourcedid"] = "L'identifiant de la session d'activité Nugget.";
$string["privacy:metadata:naas:oauth_consumer_key"] = "Un identifiant de partenaire NaaS, permettant d'appliquer des droits d'accès aux différents Nuggets disponibles. Les Nuggets publiés en tant que ressources éducatives libres sont automatiquement disponibles pour tout partenaire.";
$string["privacy:metadata:naas:oauth_timestamp"] = "Horodatage utilisé pour établir l'authentification OAuth.";
$string["privacy:metadata:naas_activity_outcome"] = "Le plugin Nugget crée une session chaque fois qu'un utilisateur s'engage dans une activité Nugget. Cette session va permettre d'associer la note fournie par le Nugget à l'activité sans nécessiter de transmettre d'information personnelle.";
$string["privacy:metadata:naas_activity_outcome:activity_id"] = "L'identifiant de l'activité Nugget.";
$string["privacy:metadata:naas_activity_outcome:date_added"] = "La date de démarrage de la session.";
$string["privacy:metadata:naas_activity_outcome:id"] = "L'identifiant interne de la session utilisateur sur une instance particulière d'une activité Nugget.";
$string["privacy:metadata:naas_activity_outcome:sourced_id"] = "L'identifiant de la session. Il est créé par le plugin Nugget. Chaque session a un identifiant unique. Il sera envoyé au NaaS pour associer les résultats de Nugget à cette session.";
$string["privacy:metadata:naas_activity_outcome:user_id"] = "L'identifiant de l'utilisateur qui accède à l'activité Nugget.";
$string["privacy:metadata:naastable"] = "Le plugin Nugget crée une entrée pour chaque activité Nugget afin de stocker sa configuration.";
$string["privacy:metadata:naastable:allowofflineattempts"] = "Autoriser ou non les tentatives hors ligne dans l'application mobile.";
$string["privacy:metadata:naastable:attempts"] = "Nombre maximum de tentatives autorisées pour un étudiant.";
$string["privacy:metadata:naastable:cgu_agreement"] = "Les conditions générales d'utilisation de la plateforme NaaS doivent être acceptées par le créateur de l'activité Nugget.";
$string["privacy:metadata:naastable:completionattemptsexhausted"] = "Marque l'activité comme terminée lorsque toutes les tentatives disponibles ont été épuisées.";
$string["privacy:metadata:naastable:completionminattempts"] = "Exige un nombre minimum de tentatives avant que l'activité ne soit considérée comme terminée.";
$string["privacy:metadata:naastable:completionpass"] = "Définit l'activité comme terminée uniquement si l'utilisateur la passe avec succès.";
$string["privacy:metadata:naastable:course"] = "L'identifiant du cours contenant l'activité Nugget.";
$string["privacy:metadata:naastable:grade_method"] = "La stratégie de notation (une des valeurs NAAS_GRADEHIGHEST, NAAS_ATTEMPTFIRST ou NAAS_ATTEMPTLAST).";
$string["privacy:metadata:naastable:id"] = "L'identifiant de cette configuration d'activité Nugget.";
$string["privacy:metadata:naastable:intro"] = "L'introduction générale de l'activité Nugget.";
$string["privacy:metadata:naastable:introformat"] = "Le format de l'introduction (MOODLE, HTML, MARKDOWN...).";
$string["privacy:metadata:naastable:name"] = "Le nom de l'activité";
$string["privacy:metadata:naastable:nugget_id"] = "L'identifiant du Nugget de cette activité.";
$string["privacy:metadata:naastable:timecreated"] = "La date de création de cette activité Nugget.";
$string["privacy:metadata:naastable:timemodified"] = "La date de dernière modification de cette activité Nugget.";
$string["privacy:metadata:naas_xapi"] = "L'activité Nugget communique avec la plateforme NaaS pour envoyer des traces d'apprentissage xAPI. Les traces d'apprentissage liées à l'exécution des Nuggets sont collectées de manière anonyme sur la plateforme NaaS. Lorsque la collecte de l'email de l'utilisateur est activée pour le plugin, ces traces seront associées à l'email, permettant de personnaliser l'expérience de l'utilisateur. Les données collectées sont stockées sur le serveur de NaaS et ne sont pas partagées avec des tiers.";
$string["privacy:metadata:naas_xapi:body"] = "Le contenu de la trace d'apprentissage xAPI.";
$string["privacy:metadata:naas_xapi:email"] = "L'email de l'utilisateur. Ces informations peuvent être utilisées pour personnaliser l'expérience de l'utilisateur en associant ses différentes sessions sur différents Nuggets. L'administrateur peut désactiver la transmission de ces informations.";
$string["privacy:metadata:naas_xapi:name"] = "Le nom et le prénom de l'utilisateur. Ces informations sont utilisées pour améliorer l'expérience de l'utilisateur. L'administrateur peut désactiver la transmission de ces informations.";
$string["privacy:metadata:naas_xapi:resource_link_id"] = "L'identifiant de la session d'activité Nugget.";
$string["privacy:metadata:naas_xapi:verb"] = "Le verbe xAPI décrivant l'action de l'utilisateur.";
$string["privacy:metadata:naas_xapi:version_id"] = "L'identifiant de la version du Nugget.";
$string["producers"] = "Producteurs";
$string["publication_date"] = "Date de publication";
$string["rating_description"] = "Votre note servira à améliorer notre contenu";
$string["rating_send"] = "Envoyer";
$string["rating_sent"] = "Envoyé ✔";
$string["rating_title"] = "Évaluez ce nugget";
$string["references"] = "Réferences";
$string["select_button"] = "Sélectionner";
$string["show_more_authors"] = "Afficher plus";
$string["show_more_nugget_button"] = "Afficher plus…";
$string["structure_id"] = "Structure";
$string["sv"] = "Suédois";
$string["tags"] = "Mots clés";
$string["test_connection"] = "Test de connexion";
$string["test_connection_information"] = "Cette action teste la communication avec la plateforme Nugget as a Service. Les paramètres suivants doivent être définis et sauvegardés avant d'effectuer ce test.";
$string["tutorial"] = "Tutoriel";
$string["type"] = "Type";
$string["unlimited"] = "Illimité";
