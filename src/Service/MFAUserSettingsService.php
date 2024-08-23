<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use DBObjectSearch;
use DBObjectSet;
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
	final public static function SetMFAAdminRuleService(MFAAdminRuleService $oMFAAdminRuleService)
	{
		self::$oMFAAdminRuleService = $oMFAAdminRuleService;
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
	 * Return user settings by user. By default is first. Otherwise ordered by Admin rules rank.
	 * When no admin rules found for this user, all rules are optional.
	 *
	 * @return MFAUserSettings[]
	 */
	public function GetAllMFASettings(string $sUserId): array
	{
		if (!MFABaseConfig::GetInstance()->IsEnabled()) {
			return [];
		}

		$oAdminRule = self::$oMFAAdminRuleService->GetAdminRuleByUserId($sUserId);
		$aDeniedMfaModes = self::$oMFAAdminRuleService->GetDeniedModes($oAdminRule);

		$oSearch = DBObjectSearch::FromOQL(
			"SELECT MFAUserSettings WHERE user_id=:user_id", ['user_id' => $sUserId]);
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

		$aSettings = $this->GetAllMFASettings($sUserId);

		$aRes = [];
		foreach ($aSettings as $oSettings) {
			/** @var MFAUserSettings $oSettings */
			if ($oSettings->Get('status') === "active") {
				$aRes[] = $oSettings;
			}
		}

		return $aRes;
	}

}
