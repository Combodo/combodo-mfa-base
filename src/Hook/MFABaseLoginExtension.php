<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseException;
use Combodo\iTop\MFABase\Helper\MFABaseLog;
use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Exception;
use LoginWebPage;
use UserRights;

class MFABaseLoginExtension extends \AbstractLoginFSMExtension
{
	public function ListSupportedLoginModes()
	{
		return ['after'];
	}

	protected function OnCredentialsOK(&$iErrorCode)
	{
		MFABaseLog::Enable();

		// Control of mfa_configuration_validated to avoid double code validation
		if (!MFABaseConfig::GetInstance()->IsEnabled() || Session::IsSet('mfa_configuration_validated')) {
			MFABaseLog::Debug('MFA not triggered', null, ['IsEnabled' => MFABaseConfig::GetInstance()->IsEnabled(), 'mfa_configuration_validated' => Session::Get('mfa_configuration_validated', 'unset')]);
			Session::Unset('mfa_configuration_validated');

			return LoginWebPage::LOGIN_FSM_CONTINUE;
		}

		try {
			$sUserId = UserRights::GetUserId(Session::Get('auth_user'));

			$aUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
			MFABaseLog::Debug('Found UserSettings', null, ['count' => count($aUserSettings)]);

			if (count($aUserSettings) !== 0) {
				MFABaseLog::Debug('Calling ValidateLogin');
				if (MFABaseService::GetInstance()->ValidateLogin($sUserId, $aUserSettings)) {
					MFABaseLog::Debug('Validation OK');

					return LoginWebPage::LOGIN_FSM_CONTINUE;
				}
				MFABaseLog::Debug('Validation Failed');
				$iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;

				return LoginWebPage::LOGIN_FSM_ERROR;
			}

			$oMFAAdminRuleService = MFAAdminRuleService::GetInstance();
			$oMFAAdminRule = $oMFAAdminRuleService->GetAdminRuleByUserId($sUserId);
			if (is_null($oMFAAdminRule) || !$oMFAAdminRule->IsForced()) {
				MFABaseLog::Debug('No admin rule forced');

				return LoginWebPage::LOGIN_FSM_CONTINUE;
			}

			if ($oMFAAdminRuleService->IsForcedNow($oMFAAdminRule)) {
				MFABaseLog::Debug('Admin rule forced now');
				MFABaseLog::Debug('Calling ConfigureMFAModeOnLogin');
				if (MFABaseService::GetInstance()->ConfigureMFAModeOnLogin($sUserId, $oMFAAdminRule)) {
					MFABaseLog::Debug('Configuration OK');

					return LoginWebPage::LOGIN_FSM_CONTINUE;
				}
				MFABaseLog::Debug('Configuration Failed');
				$iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;

				return LoginWebPage::LOGIN_FSM_ERROR;
			}

			// MFA will be forced in the future
			MFABaseLog::Debug('Admin rule forced in the future');
			MFABaseService::GetInstance()->DisplayWarningOnMFAActivation($sUserId, $oMFAAdminRule);

			return LoginWebPage::LOGIN_FSM_CONTINUE;
		} catch (MFABaseException $e) {
			// Already logged
		} catch (Exception $e) {
			MFABaseLog::Error(__METHOD__.' Failed to check MFA', null, ['error' => $e->getMessage(), 'stack' => $e->getTraceAsString()]);
		}
		$iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;

		return LoginWebPage::LOGIN_FSM_ERROR;
	}

	protected function OnConnected(&$iErrorCode)
	{
		Session::Unset('selected_mfa_mode');

		return LoginWebPage::LOGIN_FSM_CONTINUE;
	}
}
