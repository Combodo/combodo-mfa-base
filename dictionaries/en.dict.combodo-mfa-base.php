<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2013 XXXXX
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('EN US', 'English', 'English', array(
	'combodo-mfa-base/Operation:Action/Title' => '',
	'MFA:MFAUserSettings:Description' => 'Multi-factor authentication adds an additional layer of security to your account by requiring more than just a password to sign in.',

	'MyAccount:Tab:MFA' => 'Multi-Factor Authentication',
	'Menu:MFAAdminRule' => 'MFA admin rules',
	'Menu:MFAAdminRule+' => 'Multi-Factor Authentication rules',
	'Menu:MFAUserSettings' => 'MFA user settings',
	'Menu:MFAUserSettings+' => 'Multi-Factor Authentication user settings',

	'UI:WelcomePopup:Message:320_06_MFA:Title' => 'Enhance users\' security with iTop Multi-factor authentication!',
	'UI:WelcomePopup:Message:320_06_MFA:Description' => '<div>
Empower your team with enhanced security, offering an additional layer of protection for your users. Selectively enforce 2FA for specific profiles while granting flexibility to others. Choose from a range of authentication methods from TOTP by app or email to Web authentication and recovery codes, and maintain control over your iTop instance\'s security posture.<br>
To configure MFA, simply navigate to your Account settings and click on "Multi-Factor Authentication." <br>
To create rules for your customers, have a look to the "MFA admin rules" menu under "Administration".
</div>
<div>Start securing your iTop experience today! 🔐🌟</div>
<div><a href="%1$s" target="_blank">Read more</a></div>',


	'UI:MFA' => 'MFA user settings',
	'UI:MFA:Portal:Menu' => 'Multi-Factor Authentication',
	'UI:MFA:Modes'  => 'Multi-factor authentication user settings',
	'UI:MFA:Modes:Name' => 'Name',
	'UI:MFA:Modes:Activated' => 'Activated',
	'UI:MFA:Modes:Action' => 'Action',
	'UI:MFA:Modes:Action:Configure:ButtonTooltip' => 'Edit MFA mode',
	'UI:MFA:Modes:Action:Add:ButtonTooltip' => 'Create new MFA mode',
	'UI:MFA:Modes:Action:Delete:ButtonTooltip' => 'Remove MFA mode',
	'UI:MFA:Modes:Default:Title' => 'Default MFA Mode',
	'UI:MFA:Modes:Default' => 'Is default',
	'UI:MFA:Modes:Action:SetAsDefault:ButtonTooltip' => 'Set as default. This mode will be presented first on login screen',
	'UI:MFA:Modes:Action:UndoDelete:ButtonTooltip' => 'Reactivate previously deleted mode',

	'Login:MFA:Validation:Title' => 'Multi-Factor Authentication',
	'Login:MFA:UserWarningAboutMFAMode:Title' => 'Multi-Factor Authentication warning',
	'Login:MFA:UserWarningAboutMFAMode:Explain' => 'The Multi-Factor Authentication mode %1$s must be activated before %2$s. After this date it will be mandatory to configure this mode.',
	'Login:MFA:Continue' => 'Continue',
	'Login:MFA:Restart:Label' => 'Back to login',

	'UI:MFA:Error:FailedToConfigure' => 'Failed to configure MFA Modes',
	'UI:MFA:Error:FailedToSetDefaultMode' => 'Failed to set default MFA Modes',
	'UI:MFA:Error:PreferredModeCannotBeDenied' => 'Default mode cannot be included in denied modes',

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

	'Class:MFAAdminRule' => 'Multi-Factor Authentication rules',
	'Class:MFAAdminRule+' => 'Multi-Factor Authentication rules',
	'Class:MFAAdminRule/Attribute:name' => 'Name',
	'Class:MFAAdminRule/Attribute:denied_mfamodes_list' => 'Denied modes',
	'Class:MFAAdminRule/Attribute:denied_mfamodes_list+' => 'The list of MFA modes that are denied to the users',
	'Class:MFAAdminRule/Attribute:forced_activation_date' => 'Activation date',
	'Class:MFAAdminRule/Attribute:forced_activation_date+' => 'The activation date will be effective only if the operational state is "forced"',
	'Class:MFAAdminRule/Attribute:operational_state' => 'Operational state',
	'Class:MFAAdminRule/Attribute:operational_state/Value:forced' => 'Forced',
	'Class:MFAAdminRule/Attribute:operational_state/Value:optional' => 'Optional',
	'Class:MFAAdminRule/Attribute:orgs_list' => 'Organisations list',
	'Class:MFAAdminRule/Attribute:preferred_mfa_mode' => 'Default mode',
	'Class:MFAAdminRule/Attribute:preferred_mfa_mode+' => 'The default MFA mode suggested to users when the activation state is "forced" and MFA is not yet configured for them',
	'Class:MFAAdminRule/Attribute:profiles_list' => 'Profiles list',
	'Class:MFAAdminRule/Attribute:rank' => 'Rank',
	'Class:MFAAdminRule/Attribute:status' => 'Status',
	'Class:MFAAdminRule/Attribute:status/Value:active' => 'Active',
	'Class:MFAAdminRule/Attribute:status/Value:inactive' => 'Inactive',

));
