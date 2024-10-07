<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2013 XXXXX
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('FR FR', 'French', 'Français', array(
	'combodo-mfa-base/Operation:Action/Title' => '',
	'MFA:MFAUserSettings:Description' => 'Multi-factor authentication adds an additional layer of security to your account by requiring more than just a password to sign in.',

	'MyAccount:Tab:MFA' => 'Multi-Factor Authentication',
	'Class:MFAAdminRule' => 'Multi-Factor Authentication rules',
	'Class:MFAAdminRule+' => 'Multi-Factor Authentication rules',
	'Menu:MFAAdminRule' => 'MFA admin rules',
	'Menu:MFAAdminRule+' => 'Multi-Factor Authentication rules',
	'Menu:MFAUserSettings' => 'MFA user settings',
	'Menu:MFAUserSettings+' => 'Multi-Factor Authentication user settings',
	'UI:CopyValue' => 'Copy value',
	'UI:CopyValue:Done' => 'Copy value done',

	'UI:MFA' => 'MFA user settings',
	'UI:MFA:Portal:Menu' => 'Multi-Factor Authentication',
	'UI:MFA:Modes'  => 'MFA modes',
	'UI:MFA:Modes:Name' => 'Name',
	'UI:MFA:Modes:Activated' => 'Activated',
	'UI:MFA:Modes:Action' => 'Action',
	'UI:MFA:Modes:Action:Configure:ButtonTooltip' => 'Edit MFA mode',
	'UI:MFA:Modes:Action:Add:ButtonTooltip' => 'Create new MFA mode',
	'UI:MFA:Modes:Action:Delete:ButtonTooltip' => 'Remove MFA mode',
	'UI:MFA:Modes:Default:Title' => 'Default MFA Mode',
	'UI:MFA:Modes:Default' => 'Is default',
	'UI:MFA:Modes:Action:SetAsDefault:ButtonTooltip' => 'Set as default. This mode will be presented first during login',
	'UI:MFA:Modes:Default:Done' => '%1$s is set as default',
	'UI:MFA:Modes:Deleted' => '%1$s has been deleted',
	'UI:MFA:Modes:Action:UndoDelete:ButtonTooltip' => 'Reactivate previously deleted mode',

	'Login:MFA:UserWarningAboutMFAMode:Title' => 'Multi-Factor Authentication warning',
	'Login:MFA:UserWarningAboutMFAMode:Explain' => 'The Multi-Factor Authentication mode %1$s must be activated before %2$s. After this date it will be mandatory to to configure this mode.',
	'Login:MFA:Continue' => 'Continue',
	'Login:MFA:Restart:Label' => 'Back to login',

	'UI:MFA:Error:FailedToConfigure' => 'Failed to configure MFA Modes',
	'UI:MFA:Error:FailedToSetDefaultMode' => 'Failed to set default MFA Modes',
	'UI:MFA:Error:PreferredModeCannotBeDenied' => 'Preferred mode cannot be denied',

	'Class:MFAUserSettings' => 'MFA user settings',
	'Class:MFAUserSettings/Attribute:validated' => 'Validated',
	'Class:MFAUserSettings/Attribute:validated/Value:yes' => 'Yes',
	'Class:MFAUserSettings/Attribute:validated/Value:no' => 'No',
	'Class:MFAUserSettings/Attribute:configured' => 'Configured',
	'Class:MFAUserSettings/Attribute:configured/Value:yes' => 'Yes',
	'Class:MFAUserSettings/Attribute:configured/Value:no' => 'No',
	'Class:MFAUserSettings/Attribute:is_default' => 'Default',
	'Class:MFAUserSettings/Attribute:is_default/Value:yes' => 'Yes',
	'Class:MFAUserSettings/Attribute:is_default/Value:no' => 'No',
	'Class:MFAUserSettings/Attribute:user_id' => 'User',
));
