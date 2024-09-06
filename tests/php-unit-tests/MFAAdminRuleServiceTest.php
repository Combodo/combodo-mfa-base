<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Config;
use DateTime;
use MetaModel;
use MFAAdminRule;

require_once __DIR__.'/AbstractMFATest.php';

class MFAAdminRuleServiceTest extends AbstractMFATest {
	private $sConfigTmpBackupFile;

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-mfa-base/vendor/autoload.php');

		$this->sConfigTmpBackupFile = tempnam(sys_get_temp_dir(), "config_");
		MetaModel::GetConfig()->WriteToFile($this->sConfigTmpBackupFile);

		$this->org1 = $this->CreateOrganization("org1");
		$this->org2 = $this->CreateOrganization("org2");
		$this->org3 = $this->CreateOrganization("org3");
		$this->CleanupAdminRules();

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		if (!is_null($this->sConfigTmpBackupFile) && is_file($this->sConfigTmpBackupFile)) {
			//put config back
			$sConfigPath = MetaModel::GetConfig()->GetLoadedFile();
			@chmod($sConfigPath, 0770);
			$oConfig = new Config($this->sConfigTmpBackupFile);
			$oConfig->WriteToFile($sConfigPath);
			@chmod($sConfigPath, 0440);
			@unlink($this->sConfigTmpBackupFile);
		}
	}

	public function testNonExistingUserDoNotHaveRules() {
		$this->CreateRule("rule", "MFAUserSettingsRecoveryCodes", "forced");
		$this->assertEquals(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId(66666));
	}

	public function testGetRuleWontFailWhenNoRuleDefined() {
		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->assertEquals(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", [$this->org2->GetKey()], [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->assertEquals(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oPortalUserInOrg2->GetKey()));
	}

	public function Rule_ModuleConfig() {
		return [
			'module disabled' => [false],
			'module enabled' => [true],
		];
	}

	public function testNonMatchingRule() {
		$this->CreateRule("rule in org1", "MFAUserSettingsRecoveryCodes", "forced", [ $this->org1->GetKey() ], [ItopDataTestCase::$aURP_Profiles['Portal user']], 1);
		$this->CreateRule("rule with Administrator", "MFAUserSettingsRecoveryCodes", "optional", [], [ItopDataTestCase::$aURP_Profiles['Administrator']], 2);

		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->CheckRules(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg1 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg1", [$this->org2->GetKey()], [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oPortalUserInOrg1->GetKey()));

		$oAgentInOrg1 = $this->CreateUserWithProfilesAndOrg("AgentInOrg1", [$this->org1->GetKey()], [ItopDataTestCase::$aURP_Profiles['Service Desk Agent']]);
		$this->CheckRules(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oAgentInOrg1->GetKey()));
	}

	public function testMatchingRule_Org() {
		$i=0;
		$oRule1 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsTOTPMail", "forced", [ $this->org2->GetKey(), $this->org1->GetKey() ], [], 30);
		$oRule2 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsTOTPMail", "forced", [ $this->org2->GetKey(), $this->org1->GetKey() ], [], 20);
		$oRule3 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsTOTPMail", "forced", [ $this->org2->GetKey(), $this->org1->GetKey() ], [], 10);

		$oRule4 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsRecoveryCodes", "forced", [ $this->org2->GetKey() ], [], 10);
		$oRule5 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsRecoveryCodes", "optional", [ $this->org2->GetKey() ], [], 5);
		$oRule6 = $this->CreateRule("testMatchingRule_Org" . $i++, "MFAUserSettingsRecoveryCodes", "forced", [ $this->org2->GetKey() ], [], 7);

		$oNoOrgRule1 = $this->CreateRule("testMatchingRule_NoOrg" . $i++, "MFAUserSettingsTOTPApp", "optional", [], [], 70);
		$oNoOrgRule2 = $this->CreateRule("testMatchingRule_NoOrg" . $i++, "MFAUserSettingsTOTPApp", "forced", [], [], 80);
		$oNoOrgRule3 = $this->CreateRule("testMatchingRule_NoOrg" . $i++, "MFAUserSettingsTOTPApp", "forced", [], [], 90);

		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->CheckRules($oRule5, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", [$this->org2->GetKey()], [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules($oRule5, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oPortalUserInOrg2->GetKey()));

		$oPortalUserInOrg1 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg1", [$this->org1->GetKey()], [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules($oRule3, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oPortalUserInOrg1->GetKey()));

		$oPortalUserInOrg1 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg1Org3", [$this->org1->GetKey(), $this->org3->GetKey()], [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules($oRule3, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oPortalUserInOrg1->GetKey()));
	}

	public function testMatchingRule_ProfileAndOrg() {
		$i=0;
		$aWith2Orgs = [$this->org2->GetKey(), $this->org1->GetKey()];
		$oRule1 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTOTPMail", "forced", $aWith2Orgs, [ItopDataTestCase::$aURP_Profiles['Portal user']], 30);
		$oRule2 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTOTPMail", "forced", $aWith2Orgs, [ItopDataTestCase::$aURP_Profiles['Portal user']], 20);
		$oRule3 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTOTPMail", "forced", $aWith2Orgs, [ItopDataTestCase::$aURP_Profiles['Administrator']], 10);

		$oRule4 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsRecoveryCodes", "forced", [ $this->org2->GetKey() ], [ItopDataTestCase::$aURP_Profiles['Portal user']], 10);
		$oRule5 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsRecoveryCodes", "optional", [ $this->org2->GetKey() ], [ItopDataTestCase::$aURP_Profiles['Portal user']], 5);
		$oRule6 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsRecoveryCodes", "optional", [ $this->org2->GetKey() ], [ItopDataTestCase::$aURP_Profiles['Portal user']], 7);

		$oNoProfileNoOrgRule1 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTOTPApp", "optional", [], [], 70);
		$oNoProfileNoOrgRule2 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTOTPApp", "forced", [], [], 80);
		$oNoProfileNoOrgRule3 = $this->CreateRule("testMatchingRule_ProfileAndOrg" . $i++, "MFAUserSettingsTOTPApp", "optional", [], [], 90);

		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->CheckRules($oNoProfileNoOrgRule1, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", [$this->org2->GetKey()], [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules($oRule5, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oPortalUserInOrg2->GetKey()));

		$oPortalUserInOrg1 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg1", [$this->org1->GetKey()], [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules($oRule2, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oPortalUserInOrg1->GetKey()));
	}

	public function testMatchingRule_Profile() {
		$i=0;
		$aWith2profiles = [ItopDataTestCase::$aURP_Profiles['Administrator'], ItopDataTestCase::$aURP_Profiles['Portal user']];
		$oRule1 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTOTPMail", "forced", [], $aWith2profiles, 30);
		$oRule2 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTOTPMail", "forced", [], $aWith2profiles, 20);
		$oRule3 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTOTPMail", "forced", [], $aWith2profiles, 10);

		$aWith1profiles = [ItopDataTestCase::$aURP_Profiles['Portal user']];
		$oRule4 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsRecoveryCodes", "forced", [], $aWith1profiles, 10);
		$oRule5 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsRecoveryCodes", "optional", [], $aWith1profiles, 5);
		$oRule6 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsRecoveryCodes", "optional", [], $aWith1profiles, 7);

		$oNoProfileRule1 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTOTPApp", "optional", [], [], 70);
		$oNoProfileRule2 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTOTPApp", "forced", [], [], 80);
		$oNoProfileRule3 = $this->CreateRule("testMatchingRule_Profile" . $i++, "MFAUserSettingsTOTPApp", "optional", [], [], 90);

		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->CheckRules($oNoProfileRule1, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oOrgLessUser->GetKey()));

		$oPortalUser = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", [$this->org2->GetKey()], [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->CheckRules($oRule5, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oPortalUser->GetKey()));

		$oAdminUser = $this->CreateUserWithProfilesAndOrg("adminUser" . $i++, [$this->org2->GetKey()], [ItopDataTestCase::$aURP_Profiles['Administrator']]);
		$this->CheckRules($oRule3, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oAdminUser->GetKey()));

		$oAdminUser = $this->CreateUserWithProfilesAndOrg("adminUser" . $i++, [$this->org2->GetKey()], [ItopDataTestCase::$aURP_Profiles['Administrator'], ItopDataTestCase::$aURP_Profiles['Service Desk Agent']]);
		$this->CheckRules($oRule3, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oAdminUser->GetKey()));
	}

	public function CheckRules($aExpectedRule, ?MFAAdminRule $oRule) {
		if (is_null($aExpectedRule)){
			$this->assertNull($oRule);
		} else {
			$this->assertEquals($aExpectedRule->Get('preferred_mfa_mode'), $oRule->Get('preferred_mfa_mode'));
			$this->assertEquals($aExpectedRule->Get('name'), $oRule->Get('name'));
		}
	}

	public function testGetDeniedModes_null() {
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetDeniedModes(null));
	}

	public function testGetDeniedModes_nodenymode() {
		$oRule = $this->CreateRule("rule without any deny mode", "MFAUserSettingsTOTPApp", "optional", [], [], 70);

		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetDeniedModes($oRule));
	}

	public function testGetDeniedModes() {
		$aDeniedModes=[\MFAUserSettingsTOTPApp::class, \MFAUserSettingsTOTPMail::class];
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPApp", "optional", [], [], 70, $aDeniedModes);

		$this->assertEquals($aDeniedModes, array_values(MFAAdminRuleService::GetInstance()->GetDeniedModes($oRule)));
	}

	public function IsForcedNowProvider() {
		$oToday = new DateTime("now - 1 day");
		$sToday = $oToday->format('Y-m-d');
		$oForceActivateDatetimeExpired = new DateTime("now - 1 day");
		$sForceActivateDatetimeExpired = $oForceActivateDatetimeExpired->format('Y-m-d');
		$oForceActivateDatetimeInTheFuture = new DateTime("now + 1 day");
		$sForceActivateDatetimeInTheFuture = $oForceActivateDatetimeInTheFuture->format('Y-m-d');

		return [
			'forced + action date in the past' => [ 'forced', $sForceActivateDatetimeExpired, true ],
			'forced + action date in the future' => [ 'forced', $sForceActivateDatetimeInTheFuture, false ],
			'forced + action date today' => [ 'forced', $sToday, true ],
			'optional + action date in the past' => [ 'optional', $sForceActivateDatetimeExpired, false ],
			'optional + action date in the future' => [ 'optional', $sForceActivateDatetimeInTheFuture, false ],
			'forced + no action date' => [ 'forced', null, true ],
			'optional + no action date' => [ 'optional', null, false ],
		];
	}

	/**
	 * @dataProvider IsForcedNowProvider
	 */
	public function testIsForcedNow(string $sState, ?string $sForcedActivationDate, bool $bExpectedForcedNow) {
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPApp", $sState, [], [], 70);

		$oNow = new DateTime();
		$sNow = $oNow->format('Y-m-d');
		if (! is_null($sForcedActivationDate)){
			$oRule = $this->updateObject(MFAAdminRule::class, $oRule->GetKey(), ['forced_activation_date' => $sForcedActivationDate]);

		}
		$sComment = "sNow: $sNow sForcedActivationDate: $sForcedActivationDate obj forced_activation_date : " . $oRule->Get('forced_activation_date');
		$this->assertEquals($bExpectedForcedNow, MFAAdminRuleService::GetInstance()->IsForcedNow($oRule), $sComment);
	}
}
