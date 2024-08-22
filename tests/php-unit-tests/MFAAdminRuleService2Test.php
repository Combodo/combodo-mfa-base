<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use MFAAdminRule;

class MFAAdminRuleService2Test extends ItopDataTestCase {
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-mfa-base/vendor/autoload.php');

		$this->org1 = $this->CreateOrganization("org1");
		$this->org2 = $this->CreateOrganization("org2");
		$this->CleanupAdminRules();
	}

	public function CleanupAdminRules() {
		$oSearch = \DBObjectSearch::FromOQL("SELECT MFAAdminRule");
		$oSet = new \DBObjectSet($oSearch);
		while ($oRule = $oSet->Fetch()) {
			$oRule->DBDelete();
		}
	}

	public function CreateUserWithProfilesAndOrg(string $sLogin, int $iOrgId, $aProfiles=[]) {
		$oPerson = $this->CreatePerson("$sLogin", $iOrgId);

		$aProfileSet = null;
		if (count($aProfiles)!=0) {
			foreach ($aProfiles as $iProfId) {
				$oUserProfile = new \URP_UserProfile();
				$oUserProfile->Set('profileid', $iProfId);
				$oUserProfile->Set('reason', 'UNIT Tests');
				if (is_null($aProfileSet)){
					$aProfileSet = \DBObjectSet::FromObject($oUserProfile);
				} else {
					$aProfileSet->AddItem($oUserProfile);
				}
			}
		}

		$oUser = $this->createObject('UserLocal', array(
			'login' => $sLogin,
			'password' => "ABCdefg@12345#",
			'language' => 'EN US',
			'profile_list' => $aProfileSet,
			'contactid' => $oPerson->GetKey(),
		));
		return $oUser;
	}

	public function testNonExistingUser() {
		$this->CreateRule("rule", "MFAUserSettingsRecoveryCode", "forced");
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId(66666));
	}

	public function testNoExistingRule() {
		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", $this->org2->GetKey(), [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oPortalUserInOrg2->GetKey()));
	}

	public function CreateRule(string $sName, string $sMfaClass, $sState, $aOrgs=[], $aProfiles=[], $iRank=100) : MFAAdminRule {
		/** @var MFAAdminRule $oRule */
		$oRule = $this->createObject(MFAAdminRule::class, array(
			'name' => $sName,
			'mfa_mode' => $sMfaClass,
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
				$aOrgSet->AddItem(\MetaModel::NewObject('lnkMFAAdminRuleToOrganization', ['organization_id' => $iOrgId]));
			}
			$aParams['orgs_list'] = $aOrgSet;
		}

		if (count($aParams)!=0) {
			$oRule = $this->updateObject(MFAAdminRule::class, $oRule->GetKey(), $aParams);
		}

		return $oRule;
	}

	public function testNonMatchingRule() {
		$this->CreateRule("rule in org1", "MFAUserSettingsRecoveryCode", "forced", [ $this->org1->GetKey() ]);
		$this->CreateRule("rule with Administrator", "MFAUserSettingsRecoveryCode", "optional", [], [ItopDataTestCase::$aURP_Profiles['Administrator']]);

		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->CheckRules([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", $this->org2->GetKey(), [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oPortalUserInOrg2->GetKey()));
	}

	public function testMatchingRule_Org() {
		$i=0;
		$oRule1 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsTotpPMail", "forced", [ $this->org2->GetKey(), $this->org1->GetKey() ], [], 3);
		$oRule2 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsTotpPMail", "forced", [ $this->org2->GetKey(), $this->org1->GetKey() ], [], 2);
		$oRule3 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsTotpPMail", "forced", [ $this->org2->GetKey(), $this->org1->GetKey() ], [], 1);

		$oRule4 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsRecoveryCode", "forced", [ $this->org2->GetKey() ], [], 10);
		$oRule5 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsRecoveryCode", "optional", [ $this->org2->GetKey() ], [], 5);
		$oRule6 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsRecoveryCode", "denied", [ $this->org2->GetKey() ], [], 7);

		$oNoOrgRule1 = $this->CreateRule("testMatchingRule_NoOrg" . $i++, "MFAUserSettingsTotpApp", "optional", [], [], 7);
		$oNoOrgRule2 = $this->CreateRule("testMatchingRule_NoOrg" . $i++, "MFAUserSettingsTotpApp", "forced", [], [], 8);
		$oNoOrgRule3 = $this->CreateRule("testMatchingRule_NoOrg" . $i++, "MFAUserSettingsTotpApp", "denied", [], [], 9);

		$aExpectedRules = [$oNoOrgRule1];
		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->CheckRules($aExpectedRules, MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oOrgLessUser->GetKey()));

		$aExpectedRules = [$oRule3, $oRule5, $oNoOrgRule1];
		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg1", $this->org2->GetKey(), [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules($aExpectedRules, MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oPortalUserInOrg2->GetKey()));
	}

	public function testMatchingRule_ProfileAndOrg() {
		$i=0;
		$aWith2Orgs = [$this->org2->GetKey(), $this->org1->GetKey()];
		$oRule1 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTotpPMail", "forced", $aWith2Orgs, [ItopDataTestCase::$aURP_Profiles['Portal user']], 3);
		$oRule2 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTotpPMail", "forced", $aWith2Orgs, [ItopDataTestCase::$aURP_Profiles['Portal user']], 2);
		$oRule3 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTotpPMail", "forced", $aWith2Orgs, [ItopDataTestCase::$aURP_Profiles['Administrator']], 1);

		$oRule4 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsRecoveryCode", "forced", [ $this->org2->GetKey() ], [ItopDataTestCase::$aURP_Profiles['Portal user']], 10);
		$oRule5 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsRecoveryCode", "optional", [ $this->org2->GetKey() ], [ItopDataTestCase::$aURP_Profiles['Portal user']], 5);
		$oRule6 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsRecoveryCode", "denied", [ $this->org2->GetKey() ], [ItopDataTestCase::$aURP_Profiles['Portal user']], 7);

		$oNoProfileNoOrgRule1 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTotpApp", "optional", [], [], 7);
		$oNoProfileNoOrgRule2 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTotpApp", "forced", [], [], 8);
		$oNoProfileNoOrgRule3 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTotpApp", "denied", [], [], 9);

		$aExpectedRules = [$oNoProfileNoOrgRule1];
		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->CheckRules($aExpectedRules, MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oOrgLessUser->GetKey()));

		$aExpectedRules = [$oRule2, $oRule5, $oNoProfileNoOrgRule1];
		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg1", $this->org2->GetKey(), [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules($aExpectedRules, MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oPortalUserInOrg2->GetKey()));
	}

	public function testMatchingRule_Profile() {
		$i=0;
		$aWith2profiles = [ItopDataTestCase::$aURP_Profiles['Administrator'], ItopDataTestCase::$aURP_Profiles['Portal user']];
		$oRule1 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTotpPMail", "forced", [], $aWith2profiles, 3);
		$oRule2 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTotpPMail", "forced", [], $aWith2profiles, 2);
		$oRule3 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTotpPMail", "forced", [], $aWith2profiles, 1);


		$aWith1profiles = [ItopDataTestCase::$aURP_Profiles['Portal user']];
		$oRule4 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsRecoveryCode", "forced", [], $aWith1profiles, 10);
		$oRule5 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsRecoveryCode", "optional", [], $aWith1profiles, 5);
		$oRule6 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsRecoveryCode", "denied", [], $aWith1profiles, 7);

		$oNoProfileRule1 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTotpApp", "optional", [], [], 7);
		$oNoProfileRule2 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTotpApp", "forced", [], [], 8);
		$oNoProfileRule3 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTotpApp", "denied", [], [], 9);

		$aExpectedRules = [$oNoProfileRule1];
		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->CheckRules($aExpectedRules, MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oOrgLessUser->GetKey()));

		$aExpectedRules = [$oRule3, $oRule5, $oNoProfileRule1];
		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg1", $this->org2->GetKey(), [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules($aExpectedRules, MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oPortalUserInOrg2->GetKey()));
	}


	public function CheckRules(array $aExpectedRules, array $aRules) {
		$aExpectedRuleNames = [];
		foreach ($aExpectedRules as $oRule){
			/** @var MFAAdminRule $oRule */
			$aExpectedRuleNames[]=$oRule->Get('name');
		}

		$RuleNames = [];
		foreach ($aRules as $oRule){
			/** @var MFAAdminRule $oRule */
			$RuleNames[]=$oRule->Get('name');
		}

		$this->assertEquals($aExpectedRuleNames, $RuleNames);
	}
}
