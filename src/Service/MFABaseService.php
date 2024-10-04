<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\MFABase\Helper\MFABaseException;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Combodo\iTop\MFABase\Helper\MFABaseLog;
use Combodo\iTop\MFABase\View\MFATwigRenderer;
use Dict;
use Exception;
use LoginWebPage;
use MetaModel;
use MFAAdminRule;
use MFAUserSettings;
use UserRights;
use utils;

class MFABaseService
{
	const SELECTED_MFA_MODE = 'selected_mfa_mode';
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
	final public static function SetInstance(MFABaseService $oInstance): void
	{
		self::$oInstance = $oInstance;
	}

	/**
	 * Test purpose only
	 */
	final public static function ResetInstance(): void
	{
		self::$oInstance = new static();
	}

	/**
	 * @return array
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function GetMFAUserSettingsDataTable(): array
	{
		try {
			$aColumns = [
				'name' => ['label' => Dict::S('UI:MFA:Modes:Name')],
				'validated' => ['label' => Dict::S('UI:MFA:Modes:Activated')],
				'is_default' => ['label' => Dict::S('UI:MFA:Modes:Default')],
				'action' => ['label' => Dict::S('UI:MFA:Modes:Action')],
			];

			$aData = [];
			$sUserId = UserRights::GetUserId();
			$aMFAUserSettingsModes = MFAUserSettingsService::GetInstance()->GetAllAllowedMFASettings($sUserId);

			foreach ($aMFAUserSettingsModes as $oMFAUserSettings) {
				$aDatum = [];
				// Name
				$sMFAUserSettingsClass = get_class($oMFAUserSettings);
				$aDatum['name'] = MetaModel::GetName($sMFAUserSettingsClass);
				// Status
				/** @var \MFAUserSettings $oMFAUserSettings */
				$aDatum['validated'] = $oMFAUserSettings->GetEditValue('validated');;
				$aDatum['is_default'] = $oMFAUserSettings->GetEditValue('is_default');
				$aButtonToolbar = [];

				if ($oMFAUserSettings->Get('validated') !== 'no') {
					$aButtonToolbar[] = ['fas fa-pen',
						Dict::S('UI:MFA:Modes:Action:Configure:ButtonTooltip'),
						'configure',
						$sMFAUserSettingsClass,
						];

					if ($oMFAUserSettings->CanBeDefault() && $oMFAUserSettings->Get('is_default') === 'no'){
						$aButtonToolbar[] = ['fas fa-check-square',
							Dict::S('UI:MFA:Modes:Action:SetAsDefault:ButtonTooltip'),
							'set_as_default',
							$sMFAUserSettingsClass,
						];
					}

					$aButtonToolbar[] = ['fas fa-trash',
						Dict::S('UI:MFA:Modes:Action:Delete:ButtonTooltip'),
						'delete',
						$sMFAUserSettingsClass,
						'ibo-is-danger',
					];
				} else {
					$aButtonToolbar[] = ['fas fa-plus',
						Dict::S('UI:MFA:Modes:Action:Add:ButtonTooltip'),
						'add',
						$sMFAUserSettingsClass,
					];

					if ($oMFAUserSettings->Get('configured') === 'yes') {
						$aButtonToolbar[] = ['fas fa-undo',
							Dict::S('UI:MFA:Modes:Action:UndoDelete:ButtonTooltip'),
							'undo_delete',
							$sMFAUserSettingsClass,
						];
					}
				}

				$aDatum['action'] = $aButtonToolbar;
				$aData[] = $aDatum;
			}

			if (empty($aData)) {
				return [];
			}

			return ['aColumns' => $aColumns, 'aData' => $aData];
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}


	/**
	 * Display the screen to enter the code, and validate the code entered by the user
	 * Use selected_mfa_mode posted var to choose the mode of MFA to validate
	 *
	 * @param MFAUserSettings[] $aUserSettings The MFA modes configured by the user
	 *
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function ValidateLogin(array $aUserSettings): void
	{
		try {
			$oChosenUserSettings = null;
			$sChosenUserSettings = utils::ReadPostedParam(self::SELECTED_MFA_MODE, null);
			if (!is_null($sChosenUserSettings)) {
				MFABaseService::GetInstance()->ClearContext(Session::Get(self::SELECTED_MFA_MODE));
				Session::Set(self::SELECTED_MFA_MODE, $sChosenUserSettings);
			}
			$sChosenUserSettings = Session::Get(self::SELECTED_MFA_MODE);
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
				throw new MFABaseException('No default MFA possible');
			}

			MFABaseLog::Debug(__FUNCTION__, null, ['ChosenUserSettings' => get_class($oChosenUserSettings)]);
			Session::Set(self::SELECTED_MFA_MODE, get_class($oChosenUserSettings));
			$oMFATwigRenderer = new MFATwigRenderer();
			if ($oChosenUserSettings->HasToDisplayValidation()) {
				$this->DisplayValidation($oChosenUserSettings, $oMFATwigRenderer, $aUserSettings);
			}

			// Validate 2FA user input
			if (!$oChosenUserSettings->ValidateLogin()) {
				$this->DisplayValidation($oChosenUserSettings, $oMFATwigRenderer, $aUserSettings);
			}
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * @param \MFAUserSettings $oChosenUserSettings
	 * @param \Combodo\iTop\MFABase\View\MFATwigRenderer $oMFATwigRenderer
	 * @param array $aUserSettings
	 *
	 * @return void
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	private function DisplayValidation(MFAUserSettings $oChosenUserSettings, MFATwigRenderer $oMFATwigRenderer, array $aUserSettings): void
	{
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

	/**
	 * This function normally exit
	 *
	 * @param string $sUserId
	 * @param \MFAAdminRule $oMFAAdminRule
	 *
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function ConfigureMFAModeOnLogin(string $sUserId, MFAAdminRule $oMFAAdminRule): void
	{
		try {
			$sPreferredModeClass = $oMFAAdminRule->Get('preferred_mfa_mode');
			Session::Set(self::SELECTED_MFA_MODE, $sPreferredModeClass);
			$oMFAUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sPreferredModeClass);

			$oMFATwigRenderer = new MFATwigRenderer();
			// Display validation screen for chosen mode and a link for all other modes
			$oLoginContext = $oMFAUserSettings->GetTwigContextForConfiguration();
			$oMFATwigRenderer->RegisterTwigLoaders($oLoginContext);
			$oPage = new LoginWebPage();
			$oPage->add_saas(MFABaseHelper::GetSCSSFile());
			$oMFATwigRenderer->Render($oPage, 'MFALogin.html.twig');
			exit();
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * This function normally exit
	 *
	 * @param string $sUserId
	 * @param \MFAAdminRule $oMFAAdminRule
	 *
	 * @return void
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function DisplayWarningOnMFAActivation(string $sUserId, MFAAdminRule $oMFAAdminRule): void
	{
		try {
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
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * This function normally exit
	 *
	 * @param $sUserId
	 * @param string $sMFAUserSettingsClass
	 *
	 * @return void
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function SetAsDefaultMode($sUserId, string $sMFAUserSettingsClass): void
	{
		try {
			$aUserSettings = MFAUserSettingsService::GetInstance()->GetMFASettingsObjects($sUserId);

			foreach ($aUserSettings as $oUserSettings) {
				MFAUserSettingsService::GetInstance()->SetMFASettingsAsDefault($oUserSettings, $oUserSettings instanceof $sMFAUserSettingsClass);
			}
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * @param string|null $sMFAUserSettingsClass
	 *
	 * @return void
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function ClearContext(?string $sMFAUserSettingsClass): void
	{
		if (is_null($sMFAUserSettingsClass)) {
			return;
		}

		if (!MetaModel::IsValidClass($sMFAUserSettingsClass)) {
			throw new MFABaseException(__FUNCTION__." Class not valid: $sMFAUserSettingsClass");
		}

		$oReflectionClass = new \ReflectionClass($sMFAUserSettingsClass);
		if (!$oReflectionClass->isSubclassOf(\MFAUserSettings::class)) {
			throw new MFABaseException(__FUNCTION__." Class not a MFAUserSettings: $sMFAUserSettingsClass");
		}

		call_user_func([$sMFAUserSettingsClass, 'ClearContext']);
	}
}
