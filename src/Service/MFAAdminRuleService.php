<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseException;
use CoreException;
use DBObjectSearch;
use DBObjectSet;
use Dict;
use Exception;
use MetaModel;
use MFAAdminRule;
use ormLinkSet;
use User;
use UserRights;

class MFAAdminRuleService
{
	private static MFAAdminRuleService $oInstance;

	protected function __construct()
	{
	}

	final public static function GetInstance(): MFAAdminRuleService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new static();
		}

		return static::$oInstance;
	}

	/**
	 * Test purpose only
	 */
	final public static function SetInstance(MFAAdminRuleService $oInstance)
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

	/**
	 * Return MFA admin rules ordered by rank.
	 *
	 * @param string $sUserId
	 *
	 * @return MFAAdminRule|null
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function GetAdminRuleByUserId(string $sUserId): ?MFAAdminRule
	{
		if (!MFABaseConfig::GetInstance()->IsEnabled()) {
			return null;
		}

		try {
			/** @var User $oUser */
			$oUser = MetaModel::GetObject(User::class, $sUserId, true, true);
			$aUserOrgIds = $this->GetUserOrgs($oUser);
			$aUserProfiles = $this->GetUserProfiles($oUser);
		} catch (CoreException $e) {
			return null;
		}

		try {
			$oSearch = DBObjectSearch::FromOQL("SELECT MFAAdminRule WHERE status='active'");
			$oSearch->AllowAllData();
			$oSet = new DBObjectSet($oSearch, ['rank' => true]);
			/** @var MFAAdminRule $oRule */
			while ($oRule = $oSet->Fetch()) {
				$bProfileOk = false;

				/** @var ormLinkSet $aProfileSet */
				$aProfileSet = $oRule->Get('profiles_list');
				if ($aProfileSet->count() == 0) {
					$bProfileOk = true;
				} else {
					while ($oProfile = $aProfileSet->Fetch()) {
						if (in_array($oProfile->Get('profile_id'), $aUserProfiles)) {
							$bProfileOk = true;
							break;
						}
					}
				}

				if ($bProfileOk) {
					if (count($aUserOrgIds) === 0) {
						return $oRule;
					}

					/** @var ormLinkSet $aOrgSet */
					$aOrgSet = $oRule->Get('orgs_list');

					if ($aOrgSet->count() === 0) {
						return $oRule;
					} else {
						$aRuleOrgIds = $aOrgSet->GetColumnAsArray('org_id');
						$aIntersection = array_intersect($aUserOrgIds, $aRuleOrgIds);
						if (count($aIntersection) !== 0) {
							return $oRule;
						}
					}
				}
			}
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
		}

		return null;
	}

	public function OnCheckToWrite(?MFAAdminRule $oAdminRule)
	{
		$sPreferredMode = $oAdminRule->Get('preferred_mfa_mode');
		$aDeniedModes = $this->GetDeniedModes($oAdminRule);
		if (in_array($sPreferredMode, $aDeniedModes)) {
			$oAdminRule->AddCheckIssue(Dict::S('UI:MFA:Error:PreferredModeCannotBeDenied'));
		}
	}

	/**
	 * @param \MFAAdminRule|null $oAdminRule
	 *
	 * @return array
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function GetDeniedModes(?MFAAdminRule $oAdminRule): array
	{
		if (is_null($oAdminRule)) {
			return [];
		}

		try {
			$oDeniedLinkset = $oAdminRule->Get('denied_mfamodes_list');

			return $oDeniedLinkset->GetColumnAsArray("mfamode_id_friendlyname");
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
		}
	}

	/**
	 * @param \User $oUser
	 *
	 * @return array
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	private function GetUserProfiles(User $oUser): array
	{
		try {
			/** @var ormLinkSet $aProfileSet */
			$aProfileSet = $oUser->Get('profile_list');
			if ($aProfileSet->count() == 0) {
				return [];
			}

			$aRes = [];
			while ($oProfile = $aProfileSet->Fetch()) {
				$aRes[] = $oProfile->Get('profileid');
			}

			return $aRes;
		} catch (Exception $e) {
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
		}
	}

	/**
	 * @param \User $oUser
	 *
	 * @return array
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	private function GetUserOrgs(User $oUser): array
	{
		try {
			$oAddon = UserRights::GetModuleInstance();

			return $oAddon->GetUserOrgs($oUser, '');
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
		}
	}

	/**
	 * @param \MFAAdminRule $oMFAAdminRule
	 *
	 * @return bool
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function IsForcedNow(MFAAdminRule $oMFAAdminRule): bool
	{
		if (!$oMFAAdminRule->IsForced()) {
			return false;
		}

		try {
			$oSearch = DBObjectSearch::FromOQL("SELECT MFAAdminRule WHERE forced_activation_date <= NOW() OR ISNULL(forced_activation_date)");
			$oSearch->AllowAllData();
			$oSearch->AddCondition('id', $oMFAAdminRule->GetKey(), '=');
			$oForcedAdminRuleSet = new DBObjectSet($oSearch);

			return $oForcedAdminRuleSet->CountExceeds(0);
		} catch (MFABaseException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new MFABaseException(__METHOD__.' failed', 0, $e);
		}
	}
}
