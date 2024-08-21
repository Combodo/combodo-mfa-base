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

	public function testNoExistingRule() {
		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", $this->org2->GetKey(), [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oPortalUserInOrg2->GetKey()));
	}

	public function CreateRule(string $sName, string $sMfaClass, $sState, $aOrgs=[], $aProfiles=[]) : MFAAdminRule {
		/** @var MFAAdminRule $oRule */
		$oRule = $this->createObject(MFAAdminRule::class, array(
			'name' => $sName,
			'mfa_mode' => $sMfaClass,
			'operational_state' => $sState,
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
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", $this->org2->GetKey(), [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oPortalUserInOrg2->GetKey()));
	}
}
