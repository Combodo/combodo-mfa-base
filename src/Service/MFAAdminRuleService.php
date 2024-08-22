<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use MetaModel;
use User;
use CoreException;
use DBObjectSearch;
use ormLinkSet;
use DBObjectSet;
use MFAAdminRule;
use Combodo\iTop\MFABase\Helper\MFABaseLog;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;

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
	 * @return MFAAdminRule[]
	 */
	public function GetAdminRulesByUserId(string $sUserId) : array {
		if (! MFABaseConfig::GetInstance()->IsEnabled()){
			return [];
		}

		try{
			/** @var User $oUser */
			$oUser = MetaModel::GetObject(User::class, $sUserId);
			$oOrgs = $this->GetUserOrgs($oUser);
			$aUserProfiles = $this->GetUserProfiles($oUser);
		} catch(CoreException $e){
			return [];
		}

		$aRes = [];
		$oSearch = DBObjectSearch::FromOQL("SELECT MFAAdminRule");
		$oSet = new DBObjectSet($oSearch, ['rank' => true ]);
		while ($oRule = $oSet->Fetch()) {
			$bProfileOk=false;

			/** @var ormLinkSet $aProfileSet */
			$aProfileSet = $oRule->Get('profiles_list');
			if ($aProfileSet->count()==0){
				$bProfileOk=true;
			} else{
				while ($oProfile = $aProfileSet->Fetch()) {
					if (in_array($oProfile->Get('profile_id'), $aUserProfiles)){
						$bProfileOk=true;
						break;
					}
				}
			}

			if ($bProfileOk){
				/** @var ormLinkSet $aOrgSet */
				$aOrgSet = $oRule->Get('orgs_list');
				$sMfaMode = $oRule->Get('mfa_mode');

				if ($aOrgSet->count()==0){
					if (! array_key_exists($sMfaMode, $aRes)){
						if (MFABaseConfig::GetInstance()->IsMFAMethodEnabled($oRule->Get('mfa_mode'))){
							$aRes["$sMfaMode"]=$oRule;
						} else {
							MFABaseLog::Info("Found disabled admin rule.", null, [$sMfaMode]);
						}
					}
				} else{
					while ($oProfile = $aOrgSet->Fetch()) {
						if (in_array($oProfile->Get('organization_id'), $oOrgs)){
							if (! array_key_exists($sMfaMode, $aRes)) {
								if (MFABaseConfig::GetInstance()->IsMFAMethodEnabled($oRule->Get('mfa_mode'))) {
									$aRes["$sMfaMode"] = $oRule;
								} else {
									MFABaseLog::Info("Found disabled admin rule.", null, [$sMfaMode]);
								}
								break;
							}
						}
					}
				}
			}

		}

		return $aRes;
	}

	private function GetUserProfiles(User $oUser) : array {
		/** @var ormLinkSet $aProfileSet */
		$aProfileSet = $oUser->Get('profile_list');
		if ($aProfileSet->count()==0){
			return [];
		}

		$aRes=[];
		while ($oProfile = $aProfileSet->Fetch()) {
			$aRes[]=$oProfile->Get('profileid');
		}
		return $aRes;
	}

	private function GetUserOrgs(User $oUser) : array {
		if (empty($oUser->Get('org_id'))){
			return [];
		}

		$aUserOrgs = [$oUser->Get('org_id')];
		$sHierarchicalKeyCode = MetaModel::IsHierarchicalClass('Organization');
		if ($sHierarchicalKeyCode !== false) {
			$sOrgQuery = 'SELECT Org FROM Organization AS Org JOIN Organization AS Root ON Org.'.$sHierarchicalKeyCode.' ABOVE Root.id WHERE Root.id = :id';
			$oOrgSet = new DBObjectSet(DBObjectSearch::FromOQL_AllData($sOrgQuery), [], ['id' => $oUser->Get('org_id')]);
			while ($aRow = $oOrgSet->FetchAssoc()) {
				$oOrg = $aRow['Org'];
				$aUserOrgs[] = $oOrg->GetKey();
			}
		}

		return $aUserOrgs;
	}
}
