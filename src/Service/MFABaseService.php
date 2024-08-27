<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\Application\UI\Base\Component\Button\ButtonUIBlockFactory;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseException;
use Combodo\iTop\MFABase\Helper\MFABaseLog;
use Combodo\iTop\MFABase\View\MFATwigRenderer;
use Combodo\iTop\Renderer\BlockRenderer;
use DBObjectSet;
use DBSearch;
use Dict;
use LoginWebPage;
use MetaModel;
use MFAAdminRule;
use MFAUserSettings;
use UserRights;
use utils;

class MFABaseService
{
	private static MFABaseService $oInstance;

	private function __construct()
	{
		MFABaseLog::Enable();
	}

	final public static function GetInstance(): MFABaseService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new MFABaseService();
		}

		return static::$oInstance;
	}


	public function IsLoginModeApplicable($sLoginMode): bool
	{
		return in_array($sLoginMode, MFABaseConfig::GetInstance()->GetMFALoginModes());
	}

	public function GetConfigMFAParams(): array
	{
		$aParams = [];

		$aParams['aMFAUserSettings'] = $this->GetMFAUserSettings();
		$aParams['RecoveryOptionMethods'] = $this->GetRecoveryOptionMethods();

		return $aParams;
	}

	public function GetRecoveryOptionMethods(): array
	{
		$aColumns = [
			['label' => Dict::S('UI:MFA:Methods:Name')],
			['label' => Dict::S('UI:MFA:Methods:Status')],
			['label' => Dict::S('UI:MFA:Methods:Action')],
		];
		$aData = [];

		$aConfigMethods = MFABaseConfig::GetInstance()->GetMFAMethods();
		$aMFAUserSettingsMethods = $this->GetMFAUserSettingsModes();

		foreach ($aConfigMethods as $sMFAUserSettingsClass => $aConfigMethod) {
			if ($sMFAUserSettingsClass !== 'MFAUserSettingsRecoveryCodes') {
				continue;
			}
			$aDatum = [];
			if ($aConfigMethod['active']) {
				$aDatum[] = MetaModel::GetName($sMFAUserSettingsClass);
				$aMFAUserSettingsMethod = $aMFAUserSettingsMethods[$sMFAUserSettingsClass] ?? null;
				if (!is_null($aMFAUserSettingsMethod)) {
					$aDatum[] = Dict::S('UI:MFA:Methods:Status:Configured');
				} else {
					$aDatum[] = '';
				}
				$aDatum[] = '';
			}
			$aData[] = $aDatum;
		}

		if (empty($aData)) {
			return [];
		}

		return ['aColumns' => $aColumns, 'aData' => $aData];
	}

	public function GetMFAUserSettings(): array
	{
		$aColumns = [
			['label' => Dict::S('UI:MFA:Modes:Name')],
			['label' => Dict::S('UI:MFA:Modes:Status')],
			['label' => Dict::S('UI:MFA:Modes:Action')],
		];

		$aData = [];

		$aMFAUserSettingsModes = $this->GetMFAUserSettingsModes();

		foreach ($aMFAUserSettingsModes as $sMFAUserSettingsClass => $oMFAUserSettings) {
			$aDatum = [];
			// Name
			$aDatum[] = MetaModel::GetName($sMFAUserSettingsClass);
			// Status
			/** @var \cmdbAbstractObject $oMFAUserSettings */
			$sStatus = $oMFAUserSettings->GetEditValue('status');
			$aDatum[] = $sStatus;
			if ($sStatus !== 'not_configured') {
				$sActionLabel = Dict::S('UI:MFA:Modes:Action:Configure');
				$sActionTooltip = Dict::S('UI:MFA:Modes:Action:Configure:ButtonTooltip');
				$sDataAction = 'configure';
			} else {
				$sActionLabel = Dict::S('UI:MFA:Modes:Action:Add');
				$sActionTooltip = Dict::S('UI:MFA:Modes:Action:Add:ButtonTooltip');
				$sDataAction = 'add';
			}
			// Action
			$oButton = ButtonUIBlockFactory::MakeForSecondaryAction(
				$sActionLabel,
				'Action',
				"$sDataAction:$sMFAUserSettingsClass",
				true
			);
			$oButton->SetTooltip($sActionTooltip);
			$oRenderer = new BlockRenderer($oButton);
			$sButton = $oRenderer->RenderHtml();
			$aDatum[] = $sButton;
			$aData[] = $aDatum;
		}

		if (empty($aData)) {
			return [];
		}

		return ['aColumns' => $aColumns, 'aData' => $aData];
	}

	public function GetMFAUserSettingsModes(): array
	{
		$aModes = [];
		$oUser = UserRights::GetUserObject();
		$aMFAModes = MetaModel::EnumChildClasses('MFAUserSettings');
		foreach ($aMFAModes as $sModeClass) {
			if (MetaModel::IsAbstract($sModeClass)) {
				continue;
			}
			$oSet = new DBObjectSet(DBSearch::FromOQL("SELECT $sModeClass WHERE user_id = :id"), [], ['id' => $oUser->GetKey()]);
			$oMode = $oSet->Fetch() ?? MetaModel::NewObject($sModeClass, ['user_id' => $oUser->GetKey()]);
			$aModes[$sModeClass] = $oMode;
		}

		return $aModes;
	}

	/**
	 * Display the screen to enter the code, and validate the code entered by the user
	 * Use selected_mfa_mode posted var to choose the mode of MFA to validate
	 *
	 * @param string $sUserId the user wanting to log in
	 * @param MFAUserSettings[] $aUserSettings The MFA modes configured by the user
	 *
	 * @return bool
	 */
	public function ValidateLogin(string $sUserId, array $aUserSettings): bool
	{
		$oChosenMode = null;
		$sChosenMode = utils::ReadPostedParam('selected_mfa_mode', null);
		foreach ($aUserSettings as $oUserSettings) {
			if ((is_null($sChosenMode) && $oUserSettings->Get('is_default') === 'yes')
				|| (get_class($oUserSettings) === $sChosenMode)) {
				$oChosenMode = $oUserSettings;
				break;
			}
		}

		if (is_null($oChosenMode)) {
			foreach ($aUserSettings as $oUserSettings) {
				if ($oUserSettings->CanBeDefault()) {
					$oChosenMode = $oUserSettings;
					break;
				}
			}
		}

		if (is_null($oChosenMode)) {
			throw new MFABaseException("No default MFA possible for user $sUserId");
		}

		$oMFATwigRenderer = new MFATwigRenderer();
		if ($oChosenMode->HasToDisplayValidation()) {
			// Display validation screen for chosen mode and a link for all other modes
			$aTwigLoaders = [];
			$oLoginContext = $oChosenMode->GetTwigContextForLoginValidation();
			$oMFATwigRenderer->RegisterTwigLoaders($oLoginContext);

			// Add the contexts for Mode change in the MFA screen
			foreach ($aUserSettings as $oUserSettings) {
				if ($oUserSettings === $oChosenMode) {
					continue;
				}

				$oLoginContext = $oUserSettings->GetTwigContextForModeSwitch();
				$oMFATwigRenderer->RegisterTwigLoaders($oLoginContext);
			}

			// Render the MFA validation screen
			$oMFATwigRenderer->Render(new LoginWebPage(), 'MFALogin.html.twig');
			exit();
		}

		return $oChosenMode->ValidateLogin($sUserId);
	}

	public function ConfigureMFAModeOnLogin(string $sUserId, MFAAdminRule $oMFAAdminRule): bool
	{
		return true;
	}

	public function DisplayWarningOnMFAActivation(string $sUserId, MFAAdminRule $oMFAAdminRule): void
	{
		$aParams = [];
		$aParams['sMFAActivationDate'] = $oMFAAdminRule->Get('forced_activation_date');

		$oMFATwigRenderer = new MFATwigRenderer();
		$oMFATwigRenderer->Render(new LoginWebPage(), 'UserWarningAboutMissingMFAMode.html.twig', $aParams);
		exit();
	}
}
