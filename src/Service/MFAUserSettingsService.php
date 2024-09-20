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
use Exception;
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
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
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
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
		}
	}

	/**
	 * @param \MFAUserSettings $oSettings
	 * @param bool $bIsDefault
	 *
	 * @return void
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function SetMFASettingsAsDefault(MFAUserSettings $oSettings, bool $bIsDefault): void
	{
		try {
			$oSettings->Set('is_default', $bIsDefault ? 'yes' : 'no');
			$oSettings->AllowWrite();
			$oSettings->DBUpdate();
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
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
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
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
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
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
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
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
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
		}
	}
}
