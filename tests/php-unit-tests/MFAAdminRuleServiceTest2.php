<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

class MFAAdminRuleServiceTest2 extends ItopDataTestCase {
	public function setUp() : void {
		parent:$this->setUp();

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

		$aProfileSet = [];
		foreach ($aProfiles as $iProfId){
			$aProfileSet[]="profileid:$iProfId";
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
		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent']);
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", $this->org2->GetKey(), ItopDataTestCase::$aURP_Profiles['Portal user']);
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetAdminRulesByUserId($oPortalUserInOrg2->GetKey()));
	}
}
