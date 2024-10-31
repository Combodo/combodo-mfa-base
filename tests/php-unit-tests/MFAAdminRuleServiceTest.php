<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use DateTime;
use MetaModel;
use MFAAdminRule;

require_once __DIR__.'/AbstractMFATest.php';

class MFAAdminRuleServiceTest extends AbstractMFATest
{
	protected $org1;
	protected $org2;
	protected $org3;

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-mfa-base/vendor/autoload.php');

		$this->org1 = $this->CreateOrganization("org1");
		$this->org2 = $this->CreateOrganization("org2");
		$this->org3 = $this->CreateOrganization("org3");
		$this->CleanupAdminRules();

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
	}

	public function testNonExistingUserDoNotHaveRules()
	{
		$this->CreateRule("rule", "MFAUserSettingsTOTPMail", "forced");
		$this->assertEquals(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId(66666));
	}

	public function testGetRuleWontFailWhenNoRuleDefined()
	{
		$oOrgLessUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$this->assertEquals(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oOrgLessUser->GetKey()));

		$oPortalUserInOrg2 = $this->CreateUserWithProfilesAndOrg("PortalUserInOrg2", [$this->org2->GetKey()], [ItopDataTestCase::$aURP_Profiles['Portal user']]);
		$this->assertEquals(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oPortalUserInOrg2->GetKey()));
	}

	public function testInactiveRuleShouldBeIgnored()
	{
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPMail', 'forced');
		$this->updateObject(MFAAdminRule::class, $oRule->GetKey(), ['status' => 'inactive']);
		$oOrgLessUser = $this->CreateContactlessUser('NoOrgUser', ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], 'ABCdefg@12345#');
		$this->assertEquals(null, MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($oOrgLessUser->GetKey()), 'Invalid rule should NOT be found');
	}

	protected function AssertRulesAreIdentical($oExpectedRule, ?MFAAdminRule $oRule, $sMessage = ''): void
	{
		if (is_null($oExpectedRule)) {
			$this->assertNull($oRule, $sMessage);
		} else {
			$this->assertNotNull($oRule, $sMessage);
			$this->assertEquals($oExpectedRule->GetKey(), $oRule->GetKey(), $sMessage);
		}
	}

	public function testGetDeniedModes_null()
	{
		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetDeniedModes(null));
	}

	public function testGetDeniedModes_nodenymode()
	{
		$oRule = $this->CreateRule("rule without any deny mode", "MFAUserSettingsTOTPApp", "optional", [], [], 70);

		$this->assertEquals([], MFAAdminRuleService::GetInstance()->GetDeniedModes($oRule));
	}

	public function testGetDeniedModes()
	{
		$aDeniedModes = [\MFAUserSettingsRecoveryCodes::class, \MFAUserSettingsTOTPMail::class];
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPApp", "optional", [], [], 70, $aDeniedModes);

		$this->assertEquals($aDeniedModes, array_values(MFAAdminRuleService::GetInstance()->GetDeniedModes($oRule)));
	}

	public function IsForcedNowProvider()
	{
		$oToday = new DateTime("now - 1 day");
		$sToday = $oToday->format('Y-m-d');
		$oForceActivateDatetimeExpired = new DateTime("now - 1 day");
		$sForceActivateDatetimeExpired = $oForceActivateDatetimeExpired->format('Y-m-d');
		$oForceActivateDatetimeInTheFuture = new DateTime("now + 1 day");
		$sForceActivateDatetimeInTheFuture = $oForceActivateDatetimeInTheFuture->format('Y-m-d');

		return [
			'forced + action date in the past' => ['forced', $sForceActivateDatetimeExpired, true],
			'forced + action date in the future' => ['forced', $sForceActivateDatetimeInTheFuture, false],
			'forced + action date today' => ['forced', $sToday, true],
			'optional + action date in the past' => ['optional', $sForceActivateDatetimeExpired, false],
			'optional + action date in the future' => ['optional', $sForceActivateDatetimeInTheFuture, false],
			'forced + no action date' => ['forced', null, true],
			'optional + no action date' => ['optional', null, false],
		];
	}

	/**
	 * @dataProvider IsForcedNowProvider
	 */
	public function testIsForcedNow(string $sState, ?string $sForcedActivationDate, bool $bExpectedForcedNow)
	{
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPApp", $sState, [], [], 70);

		$oNow = new DateTime();
		$sNow = $oNow->format('Y-m-d');
		if (!is_null($sForcedActivationDate)) {
			$oRule = $this->updateObject(MFAAdminRule::class, $oRule->GetKey(), ['forced_activation_date' => $sForcedActivationDate]);

		}
		$sComment = "sNow: $sNow sForcedActivationDate: $sForcedActivationDate obj forced_activation_date : ".$oRule->Get('forced_activation_date');
		$this->assertEquals($bExpectedForcedNow, MFAAdminRuleService::GetInstance()->IsForcedNow($oRule), $sComment);
	}
}
