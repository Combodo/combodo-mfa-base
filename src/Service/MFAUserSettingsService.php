<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
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
	 * Return active user settings by user. By default is first. Otherwise ordered by Admin rules rank.
	 * When no admin rules found for this user, all rules are optional.
	 *
	 * @return MFAUserSettings[]
	 */
	public function GetActiveMFASettings(string $sUserId): array
	{
		if (!MFABaseConfig::GetInstance()->IsEnabled()) {
			return [];
		}

		$oAdminRule = self::$oMFAAdminRuleService->GetAdminRuleByUserId($sUserId);
		$aDeniedMfaModes = self::$oMFAAdminRuleService->GetDeniedModes($oAdminRule);

		$oSearch = DBObjectSearch::FromOQL(
			'SELECT MFAUserSettings WHERE user_id=:user_id AND status="active"', ['user_id' => $sUserId]);
		$oSet = new DBObjectSet($oSearch);

		$aSettings = [];
		while ($oSettings = $oSet->Fetch()) {
			if (in_array(get_class($oSettings), $aDeniedMfaModes)) {
				continue;
			}

			$aSettings[] = $oSettings;
		}

		return $aSettings;
	}

}
