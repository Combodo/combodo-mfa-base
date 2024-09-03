<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use LoginWebPage;
use UserRights;

class MFABaseLoginExtension extends \AbstractLoginFSMExtension
{
	public function ListSupportedLoginModes()
	{
		return ['before'];
	}

	protected function OnCredentialsOK(&$iErrorCode)
	{
		// Control of mfa-configuration-validated to avoid double code validation
		if (! MFABaseConfig::GetInstance()->IsEnabled() || Session::IsSet('mfa-configuration-validated')) {
			Session::Unset('mfa-configuration-validated');
			return LoginWebPage::LOGIN_FSM_CONTINUE;
		}

		$sUserId =  UserRights::GetUserId(Session::Get('auth_user'));
<<<<<<< Updated upstream
		$aUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
=======
		if (is_null($sUserId)){
			return LoginWebPage::LOGIN_FSM_CONTINUE;
		}
		$aUserSettings = MFAUserSettingsService::GetInstance()->GetActiveMFASettings($sUserId);
>>>>>>> Stashed changes
		if (count($aUserSettings) !== 0) {
			if (MFABaseService::GetInstance()->ValidateLogin($sUserId, $aUserSettings)) {
				return LoginWebPage::LOGIN_FSM_CONTINUE;
			}
			$iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;

			return LoginWebPage::LOGIN_FSM_ERROR;
		}

		$oMFAAdminRuleService = MFAAdminRuleService::GetInstance();
		$oMFAAdminRule = $oMFAAdminRuleService->GetAdminRuleByUserId($sUserId);
		if (is_null($oMFAAdminRule) || !$oMFAAdminRule->IsForced()) {
			return LoginWebPage::LOGIN_FSM_CONTINUE;
		}

		if ($oMFAAdminRuleService->IsForcedNow($oMFAAdminRule)) {
			if (MFABaseService::GetInstance()->ConfigureMFAModeOnLogin($sUserId, $oMFAAdminRule)) {
				return LoginWebPage::LOGIN_FSM_CONTINUE;
			}
			$iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;

			return LoginWebPage::LOGIN_FSM_ERROR;
		}

		// MFA will be forced in the future
		MFABaseService::GetInstance()->DisplayWarningOnMFAActivation($sUserId, $oMFAAdminRule);

		return LoginWebPage::LOGIN_FSM_CONTINUE;
	}
}
