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
use MetaModel;
use MFAAdminRule;
use MFAUserSettings;

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
	final public static function SetInstance(MFAUserSettingsService $oInstance)
	{
		self::$oInstance = $oInstance;
	}

	/**
	 * Test purpose only
	 */
	final public static function SetMFAAdminRuleService(MFAAdminRuleService $oMFAAdminRuleService)
	{
		self::$oMFAAdminRuleService = $oMFAAdminRuleService;
	}

	/**
	 * Test purpose only
	 */
	final public static function ResetInstance()
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
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function GetAllAllowedMFASettings(string $sUserId): array
	{
		if (!MFABaseConfig::GetInstance()->IsEnabled()) {
			return [];
		}

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
			$oSet = new DBObjectSet(DBSearch::FromOQL("SELECT $sModeClass WHERE user_id = :id"), [], ['id' => $sUserId]);
			$oMode = $oSet->Fetch() ?? MetaModel::NewObject($sModeClass, ['user_id' => $sUserId]);
			$aSettings[] = $oMode;
		}

		usort($aSettings, function (MFAUserSettings $a, MFAUserSettings $b) {
			if ($a->CanBeDefault() && $b->CanBeDefault()) return 0;
			if ($a->CanBeDefault()) return -1;
			if ($b->CanBeDefault()) return 1;
			return 0;
		});

		return $aSettings;
	}

	/**
	 * @param string $sUserId
	 *
	 * @return MFAUserSettings[]
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function GetMFASettingsObjects(string $sUserId): array
	{
		$oSearch = DBObjectSearch::FromOQL('SELECT MFAUserSettings WHERE user_id=:user_id', ['user_id' => $sUserId]);
		$oSet = new DBObjectSet($oSearch);

		$aSettings = [];
		while ($oSettings = $oSet->Fetch()) {
			$aSettings[] = $oSettings;
		}

		return $aSettings;
	}

	/**
	 * @param \MFAUserSettings $oSettings
	 * @param bool $bIsDefault
	 *
	 * @return void
	 */
	public function SetMFASettingsAsDefault(MFAUserSettings $oSettings, bool $bIsDefault)
	{
		$oSettings->Set('is_default', $bIsDefault ? 'yes' : 'no');
		$oSettings->AllowWrite();
		$oSettings->DBUpdate();
	}

	/**
	 * @param string $sUserId
	 *
	 * Return active user settings by user. By default is first. Otherwise ordered by Admin rules rank.
	 * When no admin rules found for this user, all rules are optional.
	 *
	 * @return MFAUserSettings[]
	 */
	public function GetValidatedMFASettings(string $sUserId): array
	{
		if (!MFABaseConfig::GetInstance()->IsEnabled()) {
			return [];
		}

		$oAdminRule = self::$oMFAAdminRuleService->GetAdminRuleByUserId($sUserId);
		$sPreferredMFAMode = '';
		if (!is_null($oAdminRule)) {
			$sPreferredMFAMode = $oAdminRule->Get('preferred_mfa_mode');
		}
		$aDeniedMfaModes = self::$oMFAAdminRuleService->GetDeniedModes($oAdminRule);

		$oSearch = DBObjectSearch::FromOQL('SELECT MFAUserSettings WHERE user_id=:user_id AND validated="yes"', ['user_id' => $sUserId]);
		$oSet = new DBObjectSet($oSearch);

		$aSettings = [];
		while ($oSettings = $oSet->Fetch()) {
			if (in_array(get_class($oSettings), $aDeniedMfaModes)) {
				continue;
			}

			$aSettings[] = $oSettings;
		}

		usort($aSettings, function ($a, $b) use ($sPreferredMFAMode) {
			if ($a->Get('is_default') === 'yes') return -1;
			if ($b->Get('is_default') === 'yes') return 1;
			if (get_class($a) === $sPreferredMFAMode) return -1;
			if (get_class($b) === $sPreferredMFAMode) return 1;

			return 0;
		});

		return $aSettings;
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
	}

	public function SetIsValid(MFAUserSettings $oUserSettings, bool $bIsValid = true): void
	{
		$oUserSettings->Set('validated', $bIsValid ? 'yes' : 'no');
		$oUserSettings->AllowWrite();
		$oUserSettings->DBUpdate();
	}


	public function IsValid(MFAUserSettings $oUserSettings): bool
	{
		return $oUserSettings->Get('validated') === 'yes';
	}
}
