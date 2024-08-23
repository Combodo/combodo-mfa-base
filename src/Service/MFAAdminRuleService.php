<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use CoreException;
use DBObjectSearch;
use DBObjectSet;
use MetaModel;
use MFAAdminRule;
use ormLinkSet;
use User;

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
	 * Return MFA admin rules ordered by rank.
	 *
	 * @param string $sUserId
	 *
	 * @return MFAAdminRule|null
	 */
	public function GetAdminRuleByUserId(string $sUserId): ?MFAAdminRule
	{
		if (!MFABaseConfig::GetInstance()->IsEnabled()) {
			return null;
		}

		try {
			/** @var User $oUser */
			$oUser = MetaModel::GetObject(User::class, $sUserId);
			$aUserOrgIds = $this->GetUserOrgs($oUser);
			$aUserProfiles = $this->GetUserProfiles($oUser);
		} catch (CoreException $e) {
			return null;
		}

		$oSearch = DBObjectSearch::FromOQL("SELECT MFAAdminRule");
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

					/*while ($oCurrentOrg = $aOrgSet->Fetch()) {
						if (in_array($oCurrentOrg->Get('organization_id'), $aOrgIds)) {
							return $oRule;
						}
					}*/
				}
			}
		}

		return null;
	}

	private function GetUserProfiles(User $oUser): array
	{
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
	}

	private function GetUserOrgs(User $oUser): array
	{
		if (empty($oUser->Get('org_id'))) {
			return [];
		}

		$aOrgSet = $oUser->Get('allowed_org_list');
		return $aOrgSet->GetColumnAsArray('allowed_org_id');



		/*$sHierarchicalKeyCode = MetaModel::IsHierarchicalClass('Organization');
		if ($sHierarchicalKeyCode !== false) {
			$sOrgQuery = 'SELECT Org FROM Organization AS Org JOIN Organization AS Root ON Org.'.$sHierarchicalKeyCode.' ABOVE Root.id WHERE Root.id = :id';
			$oOrgSet = new DBObjectSet(DBObjectSearch::FromOQL_AllData($sOrgQuery), [], ['id' => $oUser->Get('org_id')]);
			while ($aRow = $oOrgSet->FetchAssoc()) {
				$oOrg = $aRow['Org'];
				$aUserOrgs[] = $oOrg->GetKey();
			}
		}*/

		//return $aUserOrgs;
	}

	public function IsForcedNow(MFAAdminRule $oMFAAdminRule): bool
	{
		if (!$oMFAAdminRule->IsForced()) {
			return false;
		}

		return true;
	}
}
