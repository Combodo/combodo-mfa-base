<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2013 XXXXX
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('EN US', 'English', 'English', array(
	'MyAccount:Tab:MFA' => 'Multi-Factor authentication',
	'UI:MFA' => 'MFA User settings',
	'Class:MFAAdminRule' => 'Multi-Factor authentication rules',
	'Class:MFAAdminRule+' => 'Multi-Factor authentication rules',
	'Menu:MFAAdminRule' => 'MFA rules',
	'Menu:MFAAdminRule+' => 'Multi-Factor authentication rules',

	'UI:MFA:Modes'  => 'MFA Modes',
	'UI:MFA:Modes:Name' => 'Name',
	'UI:MFA:Modes:Status' => 'Status',
	'UI:MFA:Modes:Action' => 'Action',
	'UI:MFA:Modes:Action:Configure' => 'Configure',
	'UI:MFA:Modes:Action:Configure:ButtonTooltip' => 'Configure the MFA Mode',
	'UI:MFA:Modes:Action:Add' => 'Add',
	'UI:MFA:Modes:Action:Add:ButtonTooltip' => 'Add a new MFA Mode',

	'Login:MFA:UserWarningAboutMFAMode:Title' => 'Multi-Factor authentication warning',
	'Login:MFA:UserWarningAboutMFAMode:Explain' => 'The Multi-Factor authentication mode %1$s must be activated before %2$s. After this date it will be mandatory to to configure this mode.',
	'Login:MFA:Continue' => 'Continue',

	'Class:MFAUserSettings' => 'MFA user settings',
	'Class:MFAUserSettings/Attribute:status/Value:active' => 'Active',
	'Class:MFAUserSettings/Attribute:status/Value:inactive' => 'Inactive',
	'Class:MFAUserSettings/Attribute:status/Value:not_configured' => 'Not configured',

	'Class:MFAUserSettingsTotpApp' => 'MFA TOTP by application',
	'Class:MFAUserSettingsTotpMail' => 'MFA TOTP by mail',
	'Class:MFAUserSettingsRecoveryCode' => 'MFA Recovery codes',
));
