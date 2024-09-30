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

	protected function OnStart(&$iErrorCode)
	{
		MFABaseService::GetInstance()->ClearContext(Session::Get(MFABaseService::SELECTED_MFA_MODE));

		return LoginWebPage::LOGIN_FSM_CONTINUE;
	}

	protected function OnCredentialsOK(&$iErrorCode)
	{
		MFABaseLog::Enable();

		if (!MFABaseConfig::GetInstance()->IsEnabled()){
			MFABaseLog::Debug(__FUNCTION__.': MFA not enabled');
			return LoginWebPage::LOGIN_FSM_CONTINUE;
		}

		$sLoginMode = Session::Get('login_mode');
		if (!MFABaseConfig::GetInstance()->IsLoginModeApplicable($sLoginMode)){
			MFABaseLog::Debug(__FUNCTION__.': MFA not applicable', null, ['login_mode' => $sLoginMode]);
			return LoginWebPage::LOGIN_FSM_CONTINUE;
		}

		// Control of mfa_configuration_validated to avoid double code validation
		if (Session::IsSet('mfa_configuration_validated')) {
			MFABaseLog::Debug(__FUNCTION__.': MFA configuration done');
			Session::Unset('mfa_configuration_validated');

			return LoginWebPage::LOGIN_FSM_CONTINUE;
		}

		try {
			$sUserId = UserRights::GetUserId(Session::Get('auth_user'));

			$aUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
			MFABaseLog::Debug(__FUNCTION__.': Found UserSettings', null, ['count' => count($aUserSettings)]);

			if (count($aUserSettings) !== 0) {
				if (false === \utils::ReadPostedParam("mfa_restart_login", false)){
					$iOnExit = LoginWebPage::getIOnExit();
					if ($iOnExit === LoginWebPage::EXIT_RETURN)
					{
						$iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;
						return LoginWebPage::LOGIN_FSM_RETURN; // Error, exit FSM
					}

					MFABaseLog::Debug(__FUNCTION__.': Calling ValidateLogin');
					MFABaseService::GetInstance()->ValidateLogin($aUserSettings);
					MFABaseLog::Debug(__FUNCTION__.': Validation OK');

					return LoginWebPage::LOGIN_FSM_CONTINUE;
				}

				//go to login page
				unset($_POST['mfa_restart_login']);
				MFABaseLog::Debug(__FUNCTION__.': Restart Login');
				$iErrorCode = LoginWebPage::EXIT_CODE_OK;

				return LoginWebPage::LOGIN_FSM_ERROR;
			}

			$oMFAAdminRuleService = MFAAdminRuleService::GetInstance();
			$oMFAAdminRule = $oMFAAdminRuleService->GetAdminRuleByUserId($sUserId);
			if (is_null($oMFAAdminRule) || !$oMFAAdminRule->IsForced()) {
				MFABaseLog::Debug(__FUNCTION__.': No admin rule forced');

				return LoginWebPage::LOGIN_FSM_CONTINUE;
			}

			if ($oMFAAdminRuleService->IsForcedNow($oMFAAdminRule)) {
				MFABaseLog::Debug(__FUNCTION__.': Admin rule forced now, Calling ConfigureMFAModeOnLogin');
				if (false === \utils::ReadPostedParam("mfa_restart_login", false)){
					$iOnExit = LoginWebPage::getIOnExit();
					if ($iOnExit === LoginWebPage::EXIT_RETURN)
					{
						$iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;
						return LoginWebPage::LOGIN_FSM_RETURN; // Error, exit FSM
					}

					MFABaseService::GetInstance()->ConfigureMFAModeOnLogin($sUserId, $oMFAAdminRule);
					MFABaseLog::Debug(__FUNCTION__.': Configuration OK');

					return LoginWebPage::LOGIN_FSM_CONTINUE;
				}

				unset($_POST['mfa_restart_login']);
				MFABaseLog::Debug(__FUNCTION__.': Restart Login');
				$iErrorCode = LoginWebPage::EXIT_CODE_OK;

				return LoginWebPage::LOGIN_FSM_ERROR;
			}

			// MFA will be forced in the future
			MFABaseLog::Debug(__FUNCTION__.': Admin rule forced in the future');
			MFABaseService::GetInstance()->DisplayWarningOnMFAActivation($sUserId, $oMFAAdminRule);

			return LoginWebPage::LOGIN_FSM_CONTINUE;
		} catch (MFABaseException $e) {
			// Already logged
		} catch (Exception $e) {
			MFABaseLog::Error(__FUNCTION__.': Failed to check MFA', null, ['error' => $e->getMessage(), 'stack' => $e->getTraceAsString()]);
		}
		$iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;

		return LoginWebPage::LOGIN_FSM_ERROR;
	}

	protected function OnUsersOK(&$iErrorCode)
	{
		MFABaseService::GetInstance()->ClearContext(Session::Get(MFABaseService::SELECTED_MFA_MODE));

		return LoginWebPage::LOGIN_FSM_CONTINUE;
	}
}
