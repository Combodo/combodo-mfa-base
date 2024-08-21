<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

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
		if ($this->count($aProfiles)!=0) {
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
}
