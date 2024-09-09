<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\Application\UI\Base\Component\Button\ButtonUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\Toolbar\ToolbarUIBlockFactory;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
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

	/**
	 * Test purpose only
	 */
	final public static function SetInstance(MFABaseService $oInstance)
	{
		self::$oInstance = $oInstance;
	}

	/**
	 * Test purpose only
	 */
	final public static function ResetInstance()
	{
		self::$oInstance = new static();
	}

	public function GetConfigMFAParams(): array
	{
		$aParams = [];

		$aParams['aMFAUserSettings'] = $this->GetMFAUserSettingsDataTable();

		return $aParams;
	}

	public function GetMFAUserSettingsDataTable(): array
	{
		$aColumns = [
			['label' => Dict::S('UI:MFA:Modes:Name')],
			['label' => Dict::S('UI:MFA:Modes:Validated')],
			['label' => Dict::S('UI:MFA:Modes:Default')],
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
			$aDatum[] = $oMFAUserSettings->GetEditValue('validated');;
			$aDatum[] = $oMFAUserSettings->GetEditValue('is_default');
			$oButtonToolbar = ToolbarUIBlockFactory::MakeStandard();
			if ($oMFAUserSettings->Get('validated') !== 'no') {
				$sActionTooltip = Dict::S('UI:MFA:Modes:Action:Configure:ButtonTooltip');
				$sDataAction = 'configure';
				// Action
				$oButton = ButtonUIBlockFactory::MakeIconAction('fas fa-pen',
					$sActionTooltip,
					'Action',
					"$sDataAction:$sMFAUserSettingsClass",
					true
				);
				$oButton->SetTooltip($sActionTooltip);
				$oButtonToolbar->AddSubBlock($oButton);

				$sActionTooltip = Dict::S('UI:MFA:Modes:Action:Delete:ButtonTooltip');
				$sDataAction = 'delete';
				// Action
				$oButton = ButtonUIBlockFactory::MakeIconAction('fas fa-times',
					$sActionTooltip,
					'Action',
					"$sDataAction:$sMFAUserSettingsClass",
					true
				);
				$oButton->SetTooltip($sActionTooltip);
				$oButtonToolbar->AddSubBlock($oButton);
			} else {
				$sActionTooltip = Dict::S('UI:MFA:Modes:Action:Add:ButtonTooltip');
				$sDataAction = 'add';
				// Action
				$oButton = ButtonUIBlockFactory::MakeIconAction('fas fa-plus',
					$sActionTooltip,
					'Action',
					"$sDataAction:$sMFAUserSettingsClass",
					true
				);
				$oButton->SetTooltip($sActionTooltip);
				$oButtonToolbar->AddSubBlock($oButton);

				if ($oMFAUserSettings->Get('configured') === 'yes') {
					$sActionTooltip = Dict::S('UI:MFA:Modes:Action:UndoDelete:ButtonTooltip');
					$sDataAction = 'undo_delete';
					// Action
					$oButton = ButtonUIBlockFactory::MakeIconAction('fas fa-undo',
						$sActionTooltip,
						'Action',
						"$sDataAction:$sMFAUserSettingsClass",
						true
					);
					$oButton->SetTooltip($sActionTooltip);
					$oButtonToolbar->AddSubBlock($oButton);
				}
			}

			$oRenderer = new BlockRenderer($oButtonToolbar);
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
		if (!is_null($sChosenUserSettings)) {
			Session::Set('selected_mfa_mode', $sChosenUserSettings);
		}
		$sChosenUserSettings = Session::Get('selected_mfa_mode');
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
			MFABaseLog::Debug("No default MFA possible", null, ['UserId' => $sUserId]);
			return true;
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
			$oPage = new LoginWebPage();
			$oPage->add_saas(MFABaseHelper::GetSCSSFile());
			$oMFATwigRenderer->Render($oPage, 'MFALogin.html.twig', ['aSwitchData' => $aSwitchData]);
			exit();
		}

		// Validate 2FA user input
		return $oChosenUserSettings->ValidateLogin($sUserId);
	}

	public function ConfigureMFAModeOnLogin(string $sUserId, MFAAdminRule $oMFAAdminRule): bool
	{
		$sPreferredModeClass =  $oMFAAdminRule->Get('preferred_mfa_mode');
		$oMFAUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sPreferredModeClass);

		$oMFATwigRenderer = new MFATwigRenderer();
		// Display validation screen for chosen mode and a link for all other modes
		$oLoginContext = $oMFAUserSettings->GetTwigContextForConfiguration();
		$oMFATwigRenderer->RegisterTwigLoaders($oLoginContext);
		$oPage = new LoginWebPage();
		$oPage->add_saas(MFABaseHelper::GetSCSSFile());
		$oMFATwigRenderer->Render($oPage, 'MFALogin.html.twig');
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
		$oPage = new LoginWebPage();
		$oPage->add_saas(MFABaseHelper::GetSCSSFile());
		$oMFATwigRenderer->Render($oPage, 'UserWarningAboutMissingMFAMode.html.twig', $aParams);
		exit();
	}

	public function SetAsDefaultMode($sUserId, string $sMFAUserSettingsClass)
	{
		$aUserSettings = MFAUserSettingsService::GetInstance()->GetMFASettingsObjects($sUserId);

		foreach ($aUserSettings as $oUserSettings) {
			MFAUserSettingsService::GetInstance()->SetMFASettingsAsDefault($oUserSettings, $oUserSettings instanceof $sMFAUserSettingsClass);
		}
	}
}
