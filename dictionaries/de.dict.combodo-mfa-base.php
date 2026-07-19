<?php

/**
 * Localized data
 *
 * @copyright   Copyright (C) 2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 * @author      Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
 */

Dict::Add('DE DE', 'German', 'Deutsch', [
	'combodo-mfa-base/Operation:Action/Title' => '',
	'MFA:MFAUserSettings:Description' => 'Multi-Faktor-Authentifizierung führt eine zusätzliche Sicherheitschicht für Ihr Benutzerkonto ein, indem sie mehr als nur ein Passwort erfordert, um sich anzumelden.',
	'MyAccount:Tab:MFA' => 'Multi-Faktor-Authentifizierung',
	'Menu:MFAAdminRule' => 'MFA Adminregeln',
	'Menu:MFAAdminRule+' => 'Multi-Faktor-Authentifizierungsregeln',
	'Menu:MFAUserSettings' => 'MFA Benutzereinstellungen',
	'Menu:MFAUserSettings+' => 'Einstellungen für Multi-Faktor-Authentifizierung des Benutzers',

	'UI:WelcomePopup:Message:999_06_MFA:Title' => 'Verbessern Sie die Sicherheit Ihrer Benutzer mit iTop Multi-Faktor-Authentifizierung!',
	'UI:WelcomePopup:Message:999_06_MFA:Description' => '<div>
Bereichern Sie Ihre Teammitglieder mit erweitertem Sicherheitsmaßnahmen, indem Sie mehr als nur ein Passwort benötigen, um sich anzumelden. Wählen Sie aus, 2FA für bestimmte Profile zu erzwingen, während anderen Benutzern Flexibilität gewährt wird. Wählen Sie aus einer Vielzahl von Authentifizierungsmethoden wie TOTP über App oder E-Mail, Web-Authentifizierung und Recovery-Codes und behalten Sie die Kontrolle über die Sicherheit Ihrer iTop-Instanz.<br>
Um MFA zu konfigurieren, navigieren Sie einfach zu Ihren Account-Einstellungen und klicken auf "Multi-Faktor-Authentifizierung." <br>
Um Regeln für Ihre Kunden zu erstellen, sehen Sie sich das Menü "MFA Adminregeln" unter "Administration" an. 
</div>
<div>Beginnen Sie heute, Ihre iTop-Erfahrung sicherer zu machen! 🔐🌟</div>
<div><a href="%1$s" target="_blank">Weitere Informationen lesen</a></div>',

	'UI:MFA' => 'MFA Benutzereinstellungen',
	'UI:MFA:Portal:Menu' => 'Multi-Faktor-Authentifizierung',
	'UI:MFA:Modes'  => 'Einstellungen für Multi-Faktor-Authentifizierung des Benutzers',
	'UI:MFA:Modes:Name' => 'Name',
	'UI:MFA:Modes:Activated' => 'Aktiviert',
	'UI:MFA:Modes:Action' => 'Aktion',
	'UI:MFA:Modes:Action:Configure:ButtonTooltip' => 'MFA-Methode bearbeiten',
	'UI:MFA:Modes:Action:Add:ButtonTooltip' => 'Neue MFA-Methode erstellen',
	'UI:MFA:Modes:Action:Delete:ButtonTooltip' => 'MFA-Methode entfernen',
	'UI:MFA:Modes:Default:Title' => 'Standard-MFA-Methode',
	'UI:MFA:Modes:Default' => 'Standard',
	'UI:MFA:Modes:Action:SetAsDefault:ButtonTooltip' => 'Als Standard festlegen. Diese Methode wird auf der Anmeldeseite zuerst angezeigt',
	'UI:MFA:Modes:Action:UndoDelete:ButtonTooltip' => 'Vorher entfernte MFA-Methode wiederherstellen',

	'Login:MFA:Validation:Title' => 'Multi-Faktor-Authentifizierung',
	'Login:MFA:Validation:Error' => 'Validierung fehlgeschlagen',
	'Login:MFA:UserWarningAboutMFAMode:Title' => 'Achtung zur Multi-Faktor-Authentifizierung',
	'Login:MFA:UserWarningAboutMFAMode:Explain' => 'Die Multi-Faktor-Authentifizierungsmethode %1$s muss vor dem %2$s aktiviert sein. Danach wird es erforderlich sein, diese Methode zu konfigurieren. Es ist möglich, MFA im Bildschirm "Mein Konto" zu konfigurieren.',
	'Login:MFA:Continue' => 'Fortsetzen',
	'Login:MFA:Restart:Label' => 'Zurück zur Anmeldung',

	'UI:MFA:Error:FailedToConfigure' => 'Konfiguration der MFA-Methode fehlgeschlagen',
	'UI:MFA:Error:FailedToSetDefaultMode' => 'Setzen der Standard-MFA-Methode fehlgeschlagen',
	'UI:MFA:Error:PreferredModeCannotBeDenied' => 'Standardmethode kann nicht in verbotenen Methoden enthalten sein',
	'UI:MFA:Error:PreferredModeIsMandatoryWhenRuleIsForced' => 'Standardmethode ist erforderlich, wenn die Regel erzwungen wird',

	'Class:MFAUserSettings' => 'MFA Benutzereinstellungen',
	'Class:MFAUserSettings/Attribute:validated' => 'Validiert',
	'Class:MFAUserSettings/Attribute:validated/Value:yes' => 'Ja',
	'Class:MFAUserSettings/Attribute:validated/Value:no' => 'Nein',
	'Class:MFAUserSettings/Attribute:configured' => 'Konfiguriert',
	'Class:MFAUserSettings/Attribute:configured/Value:yes' => 'Ja',
	'Class:MFAUserSettings/Attribute:configured/Value:no' => 'Nein',
	'Class:MFAUserSettings/Attribute:is_default' => 'Standard',
	'Class:MFAUserSettings/Attribute:is_default/Value:yes' => 'Ja',
	'Class:MFAUserSettings/Attribute:is_default/Value:no' => 'Nein',
	'Class:MFAUserSettings/Attribute:user_id' => 'Benutzer',

	'Class:MFAAdminRule' => 'Multi-Faktor-Authentifizierungsregeln',
	'Class:MFAAdminRule+' => 'Multi-Faktor-Authentifizierungsregeln',
	'Class:MFAAdminRule/Attribute:name' => 'Name',
	'Class:MFAAdminRule/Attribute:denied_mfamodes' => 'Verbotene MFA-Methoden',
	'Class:MFAAdminRule/Attribute:denied_mfamodes+' => 'Die Liste der Multi-Faktor-Authentifizierungsmethoden, die den Benutzern verboten sind',
	'Class:MFAAdminRule/Attribute:forced_activation_date' => 'Aktivierungstermin',
	'Class:MFAAdminRule/Attribute:forced_activation_date+' => 'Der Aktivierungstermin wird nur wirksam sein, wenn die Betriebsart "erzwingen" ist',
	'Class:MFAAdminRule/Attribute:operational_state' => 'Betriebsart',
	'Class:MFAAdminRule/Attribute:operational_state+' => 'When optional, the user can choose to activate MFA or not. When forced, the user must activate MFA. When denied, the user cannot activate MFA.~~',
	'Class:MFAAdminRule/Attribute:operational_state/Value:forced' => 'Erzwingen',
	'Class:MFAAdminRule/Attribute:operational_state/Value:optional' => 'Optional',
	'Class:MFAAdminRule/Attribute:operational_state/Value:denied' => 'Verboten',
	'Class:MFAAdminRule/Attribute:orgs_list' => 'Organisationsliste',
	'Class:MFAAdminRule/Attribute:preferred_mfa_mode' => 'Standardmodus',
	'Class:MFAAdminRule/Attribute:preferred_mfa_mode+' => 'Die vorgeschlagene Multi-Faktor-Authentifizierungsmethode für Benutzer, wenn die Betriebsart "erzwingen" ist und MFA noch nicht konfiguriert wurde',
	'Class:MFAAdminRule/Attribute:profiles_list' => 'Profil-Liste',
	'Class:MFAAdminRule/Attribute:profiles_list+' => 'Wenn kein Profil ausgewählt ist, wird die Regel auf alle Profile angewendet',
	'Class:MFAAdminRule/Attribute:rank' => 'Reihenfolge',
	'Class:MFAAdminRule/Attribute:status' => 'Status',
	'Class:MFAAdminRule/Attribute:status/Value:active' => 'Aktiv',
	'Class:MFAAdminRule/Attribute:status/Value:inactive' => 'Inaktiv',

]);
