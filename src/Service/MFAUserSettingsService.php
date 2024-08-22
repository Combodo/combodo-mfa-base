<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use MFAUserSettings;
use MFAAdminRule;
use DBObjectSearch;
use DBObjectSet;
use Combodo\iTop\MFABase\Helper\MFABaseLog;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;

class MFAUserSettingsService
{
	private static MFAUserSettingsService $oInstance;
	private static MFAAdminRuleService $oMFAAdminRuleService;

	protected function __construct()
	{
	}

	/**
	 * Test purpose only
	 */
	final public static function SetMFAAdminRuleService(MFAAdminRuleService $oMFAAdminRuleService) {
		self::$oMFAAdminRuleService = $oMFAAdminRuleService;
	}

	private static function GetMFAAdminRuleService() : MFAAdminRuleService {
		if (! isset(self::$oMFAAdminRuleService)){
			self::$oMFAAdminRuleService = MFAAdminRuleService::GetInstance();
		}

		return self::$oMFAAdminRuleService;
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
	 * @param MFAAdminRule[]|null $aAdminRules
	 *
	 * Return user settings by user. By default is first. Otherwise ordered by Admin rules rank.
	 * When no admin rules found for this user, all rules are optional.
	 *
	 * @return MFAUserSettings[]
	 */
	public function GetAllMFASettings(string $sUserId, ?array $aAdminRules=null) : array {
		if (! MFABaseConfig::GetInstance()->IsEnabled()){
			return [];
		}

		if (is_null($aAdminRules)){
			$aAdminRules = MFAUserSettingsService::GetMFAAdminRuleService()->GetAdminRulesByUserId($sUserId);;
		}
		$bAll = count($aAdminRules) == 0;

		$oSearch = DBObjectSearch::FromOQL(
			"SELECT MFAUserSettings WHERE user_id=:user_id", ['user_id' => $sUserId]);
		$oSet = new DBObjectSet($oSearch);

		$aSettings=[];
		while ($oSettings = $oSet->Fetch()) {
			if (! MFABaseConfig::GetInstance()->IsMFAMethodEnabled(get_class($oSettings))) {
				continue;
			}

			if ($bAll){
				$aSettings[] = $oSettings;
				continue;
			}

			/** @var MFAAdminRule $oAdminRule */
			$oAdminRule = $aAdminRules[get_class($oSettings)] ?? null;
			if (! is_null($oAdminRule)){
				$aSettings[]=$oSettings;
			}
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
	public function GetActiveMFASettings(string $sUserId) : array {
		if (! MFABaseConfig::GetInstance()->IsEnabled()){
			return [];
		}

		$aAdminRules = MFAUserSettingsService::GetMFAAdminRuleService()->GetAdminRulesByUserId($sUserId);
		$bAll = count($aAdminRules) == 0;
		$aSettings = $this->GetAllMFASettings($sUserId, $aAdminRules);

		$aRes=[];
		foreach ($aSettings as $oSettings){
			/** @var MFAUserSettings $oSettings */
			if ($oSettings->Get('') != "active"){
				continue;
			}

			if ($bAll){
				$aRes[] = $oSettings;
				continue;
			}

			/** @var MFAAdminRule $oAdminRule */
			$oAdminRule = $aAdminRules[get_class($oSettings)] ?? null;
			if (! is_null($oAdminRule) && $oAdminRule->Get('operational_state') !== "denied"){
				$aRes[]=$oSettings;
			}
		}

		return $aRes;
	}

	/**
	 * after application date
	 * @param string $sUserId
	 *
	 * @return MFAAdminRule[]
	 */
	public function GetNotConfiguredMandatoryMFAAdminRules(string $sUserId) : array {
		return [];
	}

	/**
	 * before application date
	 * @param string $sUserId
	 *
	 * @return array
	 */
	public function GetFutureMandatoryMFAAdminRules(string $sUserId) : array {
		return [];
	}
}
