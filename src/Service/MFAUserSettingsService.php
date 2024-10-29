<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseException;
use DBObjectSearch;
use DBObjectSet;
use DBSearch;
use Dict;
use Exception;
use MetaModel;
use MFAUserSettings;
use UserRights;

class MFAUserSettingsService
{
	private static MFAUserSettingsService $oInstance;
	private static MFAAdminRuleService $oMFAAdminRuleService;

	protected function __construct()
	{
		if (!isset(self::$oMFAAdminRuleService)) {
			self::$oMFAAdminRuleService = MFAAdminRuleService::GetInstance();
		}
	}

	/**
	 * Test purpose only
	 */
	final public static function SetInstance(MFAUserSettingsService $oInstance): void
	{
		self::$oInstance = $oInstance;
	}

	/**
	 * Test purpose only
	 */
	final public static function SetMFAAdminRuleService(MFAAdminRuleService $oMFAAdminRuleService): void
	{
		self::$oMFAAdminRuleService = $oMFAAdminRuleService;
	}

	/**
	 * Test purpose only
	 */
	final public static function ResetInstance(): void
	{
		self::$oInstance = new static();
	}

	final public static function GetInstance(): MFAUserSettingsService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new static();
		}

		return static::$oInstance;
	}

	/**
	 * @param string $sUserId
	 * Return user settings by user (from DB and not yet configured in DB).
	 * The denied modes are filtered.
	 *
	 * @return MFAUserSettings[]
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function GetAllAllowedMFASettings(string $sUserId): array
	{
		if (!MFABaseConfig::GetInstance()->IsEnabled()) {
			return [];
		}

		try {
			$oAdminRule = self::$oMFAAdminRuleService->GetAdminRuleByUserId($sUserId);
			$aDeniedMfaModes = self::$oMFAAdminRuleService->GetDeniedModes($oAdminRule);

			$aConfiguredMFAModes = MetaModel::EnumChildClasses(MFAUserSettings::class);
			$aSettings = [];
			foreach ($aConfiguredMFAModes as $sModeClass) {
				if (MetaModel::IsAbstract($sModeClass)) {
					continue;
				}
				if (in_array($sModeClass, $aDeniedMfaModes)) {
					continue;
				}
				$oSearch = DBSearch::FromOQL("SELECT $sModeClass WHERE user_id = :id");
				$oSearch->AllowAllData();
				$oSet = new DBObjectSet($oSearch, [], ['id' => $sUserId]);
				$oMode = $oSet->Fetch() ?? MetaModel::NewObject($sModeClass, ['user_id' => $sUserId]);
				$aSettings[] = $oMode;
			}

			usort($aSettings, function (MFAUserSettings $a, MFAUserSettings $b) {
				if ($a->CanBeDefault() && $b->CanBeDefault()) {
					return 0;
				}
				if ($a->CanBeDefault()) {
					return -1;
				}
				if ($b->CanBeDefault()) {
					return 1;
				}

				return 0;
			});

			return $aSettings;
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * @param string $sUserId
	 *
	 * @return MFAUserSettings[]
	 *
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function GetMFASettingsObjects(string $sUserId): array
	{
		try {
			$oSearch = DBObjectSearch::FromOQL('SELECT MFAUserSettings WHERE user_id=:user_id', ['user_id' => $sUserId]);
			$oSearch->AllowAllData();
			$oSet = new DBObjectSet($oSearch);

			$aSettings = [];
			while ($oSettings = $oSet->Fetch()) {
				$aSettings[] = $oSettings;
			}

			return $aSettings;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * @param string $sUserId
	 *
	 * Return active user settings by user. By default is first. Otherwise ordered by Admin rules rank.
	 * When no admin rules found for this user, all rules are optional.
	 *
	 * @return MFAUserSettings[]
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function GetValidatedMFASettings(string $sUserId): array
	{
		if (!MFABaseConfig::GetInstance()->IsEnabled()) {
			return [];
		}

		try {
			$oAdminRule = self::$oMFAAdminRuleService->GetAdminRuleByUserId($sUserId);
			$sPreferredMFAMode = '';
			if (!is_null($oAdminRule)) {
				$sPreferredMFAMode = $oAdminRule->Get('preferred_mfa_mode');
			}
			$aDeniedMfaModes = self::$oMFAAdminRuleService->GetDeniedModes($oAdminRule);

			$oSearch = DBObjectSearch::FromOQL('SELECT MFAUserSettings WHERE user_id=:user_id AND validated="yes"', ['user_id' => $sUserId]);
			$oSearch->AllowAllData();
			$oSet = new DBObjectSet($oSearch);

			$aSettings = [];
			while ($oSettings = $oSet->Fetch()) {
				if (in_array(get_class($oSettings), $aDeniedMfaModes)) {
					continue;
				}

				$aSettings[] = $oSettings;
			}

			if (count($aSettings) === 0) {
				return [];
			}

			usort($aSettings, function ($a, $b) use ($sPreferredMFAMode) {
				if ($a->Get('is_default') === 'yes') {
					return -1;
				}
				if ($b->Get('is_default') === 'yes') {
					return 1;
				}
				if (get_class($a) === $sPreferredMFAMode) {
					return -1;
				}
				if (get_class($b) === $sPreferredMFAMode) {
					return 1;
				}
				if (!$a->CanBeDefault()) {
					return 1;
				}
				if (!$b->CanBeDefault()) {
					return -1;
				}

				return 0;
			});

			// Allow modes that cannot be default only if a mode that can be default is already validated
			// for example, having only Recovery Codes validated is not allowed
			$oSettings = reset($aSettings);
			if (!$oSettings->CanBeDefault()) {
				return [];
			}

			return $aSettings;
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * Get MFAUserSettings for a user and class.
	 * If not yet in DB, the UserSettings are created.
	 *
	 * Warning, Mandatory fields must be added in BEFORE_WRITE event.
	 *
	 * @param string $sUserId
	 * @param string $sMFAUserSettingsClass
	 *
	 * @return \MFAUserSettings
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function GetMFAUserSettings(string $sUserId, string $sMFAUserSettingsClass): MFAUserSettings
	{
		try {
			$aSettings = MFAUserSettingsService::GetInstance()->GetAllAllowedMFASettings($sUserId);
			$oSettings = null;
			foreach ($aSettings as $oSettings) {
				if (get_class($oSettings) === $sMFAUserSettingsClass) {
					break;
				}
			}

			if (is_null($oSettings)) {
				throw new MFABaseException('No MFA Mode allowed to be configured');
			}

			if ($oSettings->IsNew()) {
				$oSettings->AllowWrite();
				$oSettings->DBInsert();
			} else {
				// To get all the fields of the leaf class
				$oSettings->Reload();
			}

			return $oSettings;
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * @param \MFAUserSettings $oUserSettings
	 * @param bool $bIsValid
	 *
	 * @return void
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function SetIsValid(MFAUserSettings $oUserSettings, bool $bIsValid = true): void
	{
		try {
			$oUserSettings->Set('validated', $bIsValid ? 'yes' : 'no');
			$oUserSettings->AllowWrite();
			$oUserSettings->DBUpdate();
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * @param \MFAUserSettings $oUserSettings
	 *
	 * @return bool
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function IsValid(MFAUserSettings $oUserSettings): bool
	{
		try {
			return $oUserSettings->Get('validated') === 'yes';
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	public function HandleAction(string $sUserId, string $sModeClass, string $sVerb, array &$aParams=[]) : void
	{
		switch ($sVerb) {
			case 'undo_delete':
				$oUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sModeClass);
				$oUserSettings->Set('validated', 'yes');
				$oUserSettings->AllowWrite();
				$oUserSettings->DBUpdate();
				$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();
				break;

			case 'delete':
				$oUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sModeClass);
				$oUserSettings->Set('validated', 'no');
				$oUserSettings->Set('is_default', 'no');
				$oUserSettings->AllowWrite();
				$oUserSettings->DBUpdate();
				$aParams['sURL'] = \utils::GetAbsoluteUrlAppRoot().'pages/exec.php?exec_module=combodo-my-account&exec_page=index.php&exec_env=production#TwigBaseTabContainer=tab_MyAccountTabMFA';
				break;

			case 'add':
				// Delete previously added mode
				$oUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sModeClass);
				$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();
				$oUserSettings->AllowDelete();
				$oUserSettings->DBDelete();
				break;

			case 'set_as_default':
				// Set current mode as default
				$this->SetAsDefaultMode($sUserId, $sModeClass);
				$oUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sModeClass);
				//$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();
				break;

			default:
				$oUserSettings = MetaModel::NewObject($sModeClass, ['user_id' => $sUserId]);
				$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();
				break;
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
			$oUserSettings = $this->GetMFAUserSettings($sUserId, $sMFAUserSettingsClass);
			$oUserSettings->Set('is_default', 'yes');
			$oUserSettings->AllowWrite();
			$oUserSettings->DBUpdate();
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}

	/**
	 * @return array
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function GetMFAUserSettingsTableWithActions(): array
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
				$aDatum['name'] = [
					'tooltip' => Dict::S("MFA:$sMFAUserSettingsClass:Description"),
					'label' => MetaModel::GetName($sMFAUserSettingsClass),
				];
				// Status
				/** @var \MFAUserSettings $oMFAUserSettings */
				$aDatum['validated'] = $oMFAUserSettings->GetAsHTML('validated');;
				$aDatum['is_default'] = $oMFAUserSettings->GetAsHTML('is_default');
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

}
