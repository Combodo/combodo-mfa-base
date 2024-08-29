<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\Application\UI\Base\Component\Button\ButtonUIBlockFactory;
use Combodo\iTop\MFABase\Helper\MFABaseException;
use Combodo\iTop\MFABase\Helper\MFABaseLog;
use Combodo\iTop\MFABase\View\MFATwigRenderer;
use Combodo\iTop\Renderer\BlockRenderer;
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

	public function GetConfigMFAParams(): array
	{
		$aParams = [];

		$aParams['aMFAUserSettings'] = $this->GetMFAUserSettings();

		return $aParams;
	}

	public function GetMFAUserSettings(): array
	{
		$aColumns = [
			['label' => Dict::S('UI:MFA:Modes:Name')],
			['label' => Dict::S('UI:MFA:Modes:Status')],
			['label' => Dict::S('UI:MFA:Modes:Action')],
		];

		$aData = [];
		$sUserId = UserRights::GetUserId();
		$aMFAUserSettingsModes = MFAUserSettingsService::GetInstance()->GetAllAllowedMFASettings($sUserId);

		foreach ($aMFAUserSettingsModes as $oMFAUserSettings) {
			$aDatum = [];
			// Name
			$sMFAUserSettingsClass = get_class($oMFAUserSettings);
			$aDatum[] = MetaModel::GetName($sMFAUserSettingsClass);
			// Status
			/** @var \cmdbAbstractObject $oMFAUserSettings */
			$sStatus = $oMFAUserSettings->GetEditValue('status');
			$aDatum[] = $sStatus;
			if ($oMFAUserSettings->Get('status') !== 'not_configured') {
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
		$oChosenUserSettings = null;
		$sChosenUserSettings = utils::ReadPostedParam('selected_mfa_mode', null);
		foreach ($aUserSettings as $oUserSettings) {
			if ((is_null($sChosenUserSettings) && $oUserSettings->Get('is_default') === 'yes')
				|| (get_class($oUserSettings) === $sChosenUserSettings)) {
				$oChosenUserSettings = $oUserSettings;
				break;
			}
		}

		if (is_null($oChosenUserSettings)) {
			foreach ($aUserSettings as $oUserSettings) {
				if ($oUserSettings->CanBeDefault()) {
					$oChosenUserSettings = $oUserSettings;
					break;
				}
			}
		}

		if (is_null($oChosenUserSettings)) {
			throw new MFABaseException("No default MFA possible for user $sUserId");
		}

		$oMFATwigRenderer = new MFATwigRenderer();
		if ($oChosenUserSettings->HasToDisplayValidation()) {
			// Display validation screen for chosen mode and a link for all other modes
			// This is to get the 2FA user input
			$oLoginContext = $oChosenUserSettings->GetTwigContextForLoginValidation();
			$oMFATwigRenderer->RegisterTwigLoaders($oLoginContext);

			// Add the contexts for Mode change in the MFA screen
			$aSwitchData = [];
			foreach ($aUserSettings as $oUserSettings) {
				if ($oUserSettings === $oChosenUserSettings) {
					continue;
				}

				$aSwitchData[] = get_class($oUserSettings);
			}

			// Render the MFA validation screen
			$oMFATwigRenderer->Render(new LoginWebPage(), 'MFALogin.html.twig', [
				'aSwitchData' => $aSwitchData
			]);
			exit();
		}

		// Validate 2FA user input
		return $oChosenUserSettings->ValidateLogin($sUserId);
	}

	public function ConfigureMFAModeOnLogin(string $sUserId, MFAAdminRule $oMFAAdminRule): bool
	{
		if (Session::IsSet('mfa-configuration-validated')) {
			return true;
		}

		$sPreferredModeClass =  $oMFAAdminRule->Get('preferred_mfa_mode');
		$oMFAUserSettings = MetaModel::NewObject($sPreferredModeClass, ['user_id' => $sUserId]);
		$oMFATwigRenderer = new MFATwigRenderer();
		// Display validation screen for chosen mode and a link for all other modes
		$oLoginContext = $oMFAUserSettings->GetTwigContextForConfiguration();
		$oMFATwigRenderer->RegisterTwigLoaders($oLoginContext);
		$oMFATwigRenderer->Render(new LoginWebPage(), 'MFALogin.html.twig');
		exit();

	}

	public function DisplayWarningOnMFAActivation(string $sUserId, MFAAdminRule $oMFAAdminRule): void
	{
		if (!is_null(utils::ReadPostedParam('skip-mfa-warning', null))) {
			return;
		}

		$aParams = [];
		$aParams['sMFAActivationDate'] = $oMFAAdminRule->Get('forced_activation_date');

		$oMFATwigRenderer = new MFATwigRenderer();
		$oMFATwigRenderer->Render(new LoginWebPage(), 'UserWarningAboutMissingMFAMode.html.twig', $aParams);
		exit();
	}
}
