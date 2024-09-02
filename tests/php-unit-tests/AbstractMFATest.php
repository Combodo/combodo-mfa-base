<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use MFAAdminRule;
use MFAMode;
use MFAUserSettings;

class AbstractMFATest extends ItopDataTestCase {
	public function CleanupAdminRules() {
		$oSearch = \DBObjectSearch::FromOQL("SELECT MFAAdminRule");
		$oSet = new \DBObjectSet($oSearch);
		while ($oRule = $oSet->Fetch()) {
			$oRule->DBDelete();
		}
	}

	public function CleanupMFASettings() {
		$oSearch = \DBObjectSearch::FromOQL("SELECT MFAUserSettings");
		$oSet = new \DBObjectSet($oSearch);
		while ($oObj = $oSet->Fetch()) {
			$oObj->DBDelete();
		}
	}

	public function CreateSetting($sUserSettingClass, $sUserId, string $sValidated, $aAdditionFields=[], bool $bIsDefault=false) : MFAUserSettings {
		/** @var MFAUserSettings $oSettings */
		$aParams = array_merge($aAdditionFields, [
			'validated' => $sValidated,
			'is_default' => $bIsDefault ? "yes" : "no",
			'user_id' => $sUserId,
		]);
		$oSettings  = $this->createObject($sUserSettingClass, $aParams);
		return $oSettings;
	}

	public function CreateUserWithProfilesAndOrg(string $sLogin, array $aOrgIds, $aProfiles=[]) {
		$iOrgId = reset($aOrgIds);
		$oPerson = $this->CreatePerson("$sLogin", $iOrgId);

		$oProfileLinkSet = new \ormLinkSet(\User::class, 'profile_list', \DBObjectSet::FromScratch(\URP_UserProfile::class));
		if (count($aProfiles)!=0) {
			foreach ($aProfiles as $iProfId) {
				$oUserProfile = new \URP_UserProfile();
				$oUserProfile->Set('profileid', $iProfId);
				$oUserProfile->Set('reason', 'UNIT Tests');
				$oProfileLinkSet->AddItem($oUserProfile);
			}
		}

		$oAllowedOrgSet = new \ormLinkSet(\User::class, 'allowed_org_list', \DBObjectSet::FromScratch(\URP_UserOrg::class));
		foreach ($aOrgIds as $iOrgId){
			$oObject = new \URP_UserOrg();
			$oObject->Set("allowed_org_id", $iOrgId);
			$oAllowedOrgSet->AddItem($oObject);
		}
		$oUser = $this->createObject('UserLocal', array(
			'login' => $sLogin,
			'password' => "ABCdefg@12345#",
			'language' => 'EN US',
			'profile_list' => $oProfileLinkSet,
			'contactid' => $oPerson->GetKey(),
			'allowed_org_list' => $oAllowedOrgSet,
		));
		return $oUser;
	}

	public function CreateRule(string $sName, string $sMfaClass, $sState, $aOrgs=[], $aProfiles=[], $iRank=100, $aDeniedModes=[]) : MFAAdminRule {
		/** @var MFAAdminRule $oRule */
		$oRule = $this->createObject(MFAAdminRule::class, array(
			'name' => $sName,
			'preferred_mfa_mode' => $sMfaClass,
			'operational_state' => $sState,
			'rank' => $iRank,
		));

		$aParams = [];
		if (count($aProfiles)!=0) {
			/** @var \ormLinkSet $aProfileSet */
			$aProfileSet = $oRule->Get('profiles_list');
			foreach ($aProfiles as $iProfId) {
				$aProfileSet->AddItem(\MetaModel::NewObject('lnkMFAAdminRuleToProfile', ['profile_id' => $iProfId]));
			}

			$aParams = ['profiles_list' => $aProfileSet];
		}

		if (count($aOrgs)!=0) {
			/** @var \ormLinkSet $aProfileSet */
			$aOrgSet = $oRule->Get('orgs_list');
			foreach ($aOrgs as $iOrgId) {
				$aOrgSet->AddItem(\MetaModel::NewObject('lnkMFAAdminRuleToOrganization', ['org_id' => $iOrgId]));
			}
			$aParams['orgs_list'] = $aOrgSet;
		}

		if (count($aDeniedModes)!=0) {
			/** @var \ormLinkSet $oDeniedLinkset */
			$oDeniedLinkset = $oRule->Get('denied_mfamodes_list');
			foreach ($aDeniedModes as $sMfaMode) {
				/** @var MFAMode $oMfaMode */
				$oMfaMode = $this->createObject(MFAMode::class, array(
					'name' => $sMfaMode,
				));

				$oDeniedLinkset->AddItem(\MetaModel::NewObject('lnkMFAAdminRuleToMFAMode', ['mfamode_id' => $oMfaMode]));
			}
			$aParams['denied_mfamodes_list'] = $oDeniedLinkset;
		}

		if (count($aParams)!=0) {
			$oRule = $this->updateObject(MFAAdminRule::class, $oRule->GetKey(), $aParams);
		}

		return $oRule;
	}
}
