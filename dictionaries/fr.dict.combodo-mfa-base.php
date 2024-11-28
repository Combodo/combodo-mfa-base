<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2013 XXXXX
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('FR FR', 'French', 'Français', array(
	'combodo-mfa-base/Operation:Action/Title' => '',
	'MFA:MFAUserSettings:Description' => 'L\'authentification multifacteur ajoute une couche de sécurité supplémentaire à votre compte en exigeant plus qu\'un simple mot de passe pour vous connecter.',

	'MyAccount:Tab:MFA' => 'Authentification multifacteur (MFA)',
	'Menu:MFAAdminRule' => 'Règles d\'administration MFA',
	'Menu:MFAAdminRule+' => 'Règles d\'authentification',
	'Menu:MFAUserSettings' => 'Réglages utilisateur MFA',
	'Menu:MFAUserSettings+' => 'Réglages utilisateur de l\'authentification multifacteur',

	'UI:WelcomePopup:Message:320_06_MFA:Title' => 'Améliorez la sécurité de vos utilisateurs avec l\'authentification multifacteur iTop !',
	'UI:WelcomePopup:Message:320_06_MFA:Description' => '<div> Renforcez la sécurité de votre équipe en offrant une couche de protection supplémentaire à vos utilisateurs. 
Appliquez sélectivement l\'authentification à deux facteurs pour des profils spécifiques tout en conservant la possibilité de l\'appliquer à d\'autres. 
Choisissez parmi une sélection de méthodes d\'authentification, de TOTP par application ou par e-mail à l\'authentification Web et aux codes de récupération, et maintenez le contrôle sur la sécurité de votre instance iTop. <br>
Pour configurer l\'authentification multifacteur, il vous suffit de vous rendre dans vos paramètres de compte et de cliquer sur "Authentification multifacteur". <br>
</div>
<div>Commencez à sécuriser votre expérience iTop dès aujourd\'hui ! 🔐🌟</div>
<div><a href="%1$s" target="_blank">En savoir plus</a></div>',


	'UI:MFA' => 'Réglages utilisateur',
	'UI:MFA:Portal:Menu' => 'Authentification multifacteur',
	'UI:MFA:Modes'  => 'Réglages utilisateur de l\'authentification multifacteur',
	'UI:MFA:Modes:Name' => 'Nom',
	'UI:MFA:Modes:Activated' => 'Activé',
	'UI:MFA:Modes:Action' => 'Action',
	'UI:MFA:Modes:Action:Configure:ButtonTooltip' => 'Configurer le mode MFA',
	'UI:MFA:Modes:Action:Add:ButtonTooltip' => 'Créer un nouveau mode MFA',
	'UI:MFA:Modes:Action:Delete:ButtonTooltip' => 'Supprimer le mode MFA',
	'UI:MFA:Modes:Default:Title' => 'Mode MFA par défaut',
	'UI:MFA:Modes:Default' => 'Par défaut',
	'UI:MFA:Modes:Action:SetAsDefault:ButtonTooltip' => 'Définir comme mode par défaut. Ce mode sera présenté en premier sur l\'écran de connexion',
	'UI:MFA:Modes:Action:UndoDelete:ButtonTooltip' => 'Réactiver le mode précédemment supprimé',

	'Login:MFA:Validation:Title' => 'Authentification multifacteur',
	'Login:MFA:UserWarningAboutMFAMode:Title' => 'Avertissement',
	'Login:MFA:UserWarningAboutMFAMode:Explain' => 'Le mode d\'authentification multifacteur %1$s doit être activé avant %2$s. Après cette date, la configuration MFA sera obligatoire. Il est possible de configurer MFA dans l\'écran "Mon compte".',
	'Login:MFA:Continue' => 'Continuer',
	'Login:MFA:Restart:Label' => 'Retour à la page de connexion',

	'UI:MFA:Error:FailedToConfigure' => 'Échec de la configuration des modes MFA',
	'UI:MFA:Error:FailedToSetDefaultMode' => 'Échec de la configuration du mode MFA par défaut',
	'UI:MFA:Error:PreferredModeCannotBeDenied' => 'Le mode par défaut ne peut pas être inclus dans les modes refusés',
	'UI:MFA:Error:PreferredModeIsMandatoryWhenRuleIsForced' => 'Le mode par défaut est obligatoire lorsque la règle est forcée',

	'Class:MFAUserSettings' => 'Réglages utilisateur MFA',
	'Class:MFAUserSettings/Attribute:validated' => 'Validé',
	'Class:MFAUserSettings/Attribute:validated/Value:yes' => 'Oui',
	'Class:MFAUserSettings/Attribute:validated/Value:no' => 'Non',
	'Class:MFAUserSettings/Attribute:configured' => 'Configuré',
	'Class:MFAUserSettings/Attribute:configured/Value:yes' => 'Oui',
	'Class:MFAUserSettings/Attribute:configured/Value:no' => 'Non',
	'Class:MFAUserSettings/Attribute:is_default' => 'Par défaut',
	'Class:MFAUserSettings/Attribute:is_default/Value:yes' => 'Oui',
	'Class:MFAUserSettings/Attribute:is_default/Value:no' => 'Non',
	'Class:MFAUserSettings/Attribute:user_id' => 'Utilisateur',

	'Class:MFAAdminRule' => 'Règles d\'administration MFA',
	'Class:MFAAdminRule+' => 'Règles d\'administration de l\'authentification multifacteur',
	'Class:MFAAdminRule/Attribute:name' => 'Nom',
	'Class:MFAAdminRule/Attribute:denied_mfamodes' => 'Modes refusés',
	'Class:MFAAdminRule/Attribute:denied_mfamodes+' => 'Liste des modes refusés qui ne seront pas disponibles pour les utilisateurs concernés',
	'Class:MFAAdminRule/Attribute:forced_activation_date' => 'Date d\'activation',
	'Class:MFAAdminRule/Attribute:forced_activation_date+' => 'La date d\'activation ne sera effective que si l\'état opérationnel est "forcé"',
	'Class:MFAAdminRule/Attribute:operational_state' => 'État opérationnel',
	'Class:MFAAdminRule/Attribute:operational_state+' => 'Si l\'état opérationnel est "forcé", les utilisateurs concernés devront activer l\'authentification multifacteur, si l\'état est "optionnel", les utilisateurs pourront activer l\'authentification multifacteur, si l\'état est "refusé", les utilisateurs ne pourront pas activer l\'authentification multifacteur',
	'Class:MFAAdminRule/Attribute:operational_state/Value:forced' => 'Forcé',
	'Class:MFAAdminRule/Attribute:operational_state/Value:optional' => 'Optionnel',
	'Class:MFAAdminRule/Attribute:operational_state/Value:denied' => 'Interdit',
	'Class:MFAAdminRule/Attribute:orgs_list' => 'Liste des organisations',
	'Class:MFAAdminRule/Attribute:preferred_mfa_mode' => 'Mode par défaut',
	'Class:MFAAdminRule/Attribute:preferred_mfa_mode+' => 'Le mode MFA par défaut proposé aux utilisateurs lorsque l\'état d\'activation est "forcé" et que leur authentification multifacteur n\'est pas encore configurée',
	'Class:MFAAdminRule/Attribute:profiles_list' => 'Liste des profils',
	'Class:MFAAdminRule/Attribute:profiles_list+' => 'En ne sélectionnant aucun profil, la règle s\'appliquera à tous les profils',
	'Class:MFAAdminRule/Attribute:rank' => 'Ordre',
	'Class:MFAAdminRule/Attribute:status' => 'Statut',
	'Class:MFAAdminRule/Attribute:status/Value:active' => 'Actif',
	'Class:MFAAdminRule/Attribute:status/Value:inactive' => 'Inactif',

));
