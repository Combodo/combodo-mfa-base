<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Test;

require_once 'AbstractMFATest.php';

use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use MetaModel;

class MFAGetAdminRuleByUserIdTest extends AbstractMFATest
{
	protected $org1;
	protected $org2;
	protected $sOrg3Id;

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-mfa-base/vendor/autoload.php');

		$this->org1 = $this->CreateOrganization('org1');
		$this->org2 = $this->CreateOrganization('org2');
		$this->sOrg3Id = $this->GivenObjectInDB('Organization', [
			'name' =>'org3',
			'parent_id' => $this->org2->GetKey(),
		]);

		$this->CleanupAdminRules();

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
	}

	public function testRuleWithoutRestrictionMatchesEverybody()
	{
		//Given
		$sNoOrgRule1Id = $this->GivenRuleInDB('RuleForEverybody', [], []);

		$sOrgLessAdminId = $this->GivenContactlessUserInDB(['Administrator']);
		$this->AssertUserMatchesRule($sNoOrgRule1Id, $sOrgLessAdminId, 'Contactless admin should match');

		$sOrgLessNonAdminId = $this->GivenContactlessUserInDB(['Service Desk Agent']);
		$this->AssertUserMatchesRule($sNoOrgRule1Id, $sOrgLessNonAdminId, 'Contactless non admin should match');

		$sOrg1AdminId = $this->GivenUserWithContactInDB($this->org1->GetKey(), ['Administrator']);
		$this->AssertUserMatchesRule($sNoOrgRule1Id, $sOrg1AdminId, 'Admin should match');

		$sOrg1NonAdminId = $this->GivenUserWithContactInDB($this->org1->GetKey(), ['Service Desk Agent']);
		$this->AssertUserMatchesRule($sNoOrgRule1Id, $sOrg1NonAdminId, 'Non admin should match');
	}

	public function testRulesCanBeFilteredOnProfile()
	{
		$sRuleId = $this->GivenRuleInDB('RuleForAgents', [], ['Service Desk Agent']);

		$sAdminId = $this->GivenContactlessUserInDB(['Administrator']);
		$this->AssertUserMatchesRule(null, $sAdminId, 'Admin should not match');

		$sAgentId = $this->GivenContactlessUserInDB(['Service Desk Agent']);
		$this->AssertUserMatchesRule($sRuleId, $sAgentId, 'Agent should match');

		$sAgentId = $this->GivenContactlessUserInDB(['Service Desk Agent', 'Portal user']);
		$this->AssertUserMatchesRule($sRuleId, $sAgentId, 'Agent should match');
	}

	public function testRulesCanBeFilteredOnMultipleProfiles()
	{
		$sRuleId = $this->GivenRuleInDB('RuleForAgentsAndPortalUsers', [], ['Service Desk Agent', 'Portal user']);

		$sAdminId = $this->GivenContactlessUserInDB(['Administrator']);
		$this->AssertUserMatchesRule(null, $sAdminId, 'Admin should not match');

		$sAgentId = $this->GivenContactlessUserInDB(['Service Desk Agent']);
		$this->AssertUserMatchesRule($sRuleId, $sAgentId, 'Agent should match');

		$sAgentId = $this->GivenContactlessUserInDB(['Portal user']);
		$this->AssertUserMatchesRule($sRuleId, $sAgentId, 'Portal users should match');
	}

	public function testRulesCanBeFilteredOnOrganization()
	{
		$sRuleId = $this->GivenRuleInDB('RuleForOrg1', [$this->org1->GetKey()], []);

		$sOrgLessUserId = $this->GivenContactlessUserInDB(['Service Desk Agent']);
		$this->AssertUserMatchesRule(null, $sOrgLessUserId, 'Contactless user should NOT match');

		$sOrg1UserId = $this->GivenUserWithContactInDB($this->org1->GetKey(), ['Service Desk Agent']);
		$this->AssertUserMatchesRule($sRuleId, $sOrg1UserId, 'User in filtered org should match');

		$sOrg2UserId = $this->GivenUserWithContactInDB($this->org2->GetKey(), ['Service Desk Agent']);
		$this->AssertUserMatchesRule(null, $sOrg2UserId, 'User NOT in filtered org should NOT match');

		$sOrg3UserId = $this->GivenUserWithContactInDB($this->sOrg3Id, ['Service Desk Agent']);
		$this->AssertUserMatchesRule(null, $sOrg3UserId, 'User in sub-organization should NOT match');
	}

	public function testRulesCanBeFilteredOnMultipleOrganizations()
	{
		$sRuleId = $this->GivenRuleInDB('RuleForOrg1', [$this->org1->GetKey(), $this->org2->GetKey()], []);

		$sOrgLessUserId = $this->GivenContactlessUserInDB(['Service Desk Agent']);
		$this->AssertUserMatchesRule(null, $sOrgLessUserId, 'Contactless user should NOT match');

		$sOrg2UserId = $this->GivenUserWithContactInDB($this->org2->GetKey(), ['Service Desk Agent']);
		$this->AssertUserMatchesRule($sRuleId, $sOrg2UserId, 'User in filtered org should match');

		$sOrg3UserId = $this->GivenUserWithContactInDB($this->sOrg3Id, ['Service Desk Agent']);
		$this->AssertUserMatchesRule(null, $sOrg3UserId, 'User NOT in filtered organization should NOT match');
	}

	public function testRulesCanBeFilteredOnProfilesAndOrganizations()
	{
		$sRuleId = $this->GivenRuleInDB('RuleForOrg1Agents', [$this->org1->GetKey()], ['Service Desk Agent']);

		$sOrg1PortalUserId = $this->GivenUserWithContactInDB($this->org1->GetKey(), ['Portal user']);
		$this->AssertUserMatchesRule(null, $sOrg1PortalUserId, 'User in filtered org but NOT in filtered profiles should NOT match');

		$sOrg2PortalUserId = $this->GivenUserWithContactInDB($this->org2->GetKey(), ['Portal user']);
		$this->AssertUserMatchesRule(null, $sOrg2PortalUserId, 'User NOT in filtered org but NOT in filtered profiles should NOT match');

		$sOrg2AgentId = $this->GivenUserWithContactInDB($this->org2->GetKey(), ['Service Desk Agent']);
		$this->AssertUserMatchesRule(null, $sOrg2AgentId, 'User in filtered profiles but NOT in filtered org should NOT match');

		$sOrg1AgentId = $this->GivenUserWithContactInDB($this->org1->GetKey(), ['Service Desk Agent']);
		$this->AssertUserMatchesRule($sRuleId, $sOrg1AgentId, 'User in filtered profiles AND in filtered org should match');
	}

	public function testFirstMatchingRuleByRankShouldBeSelected_1()
	{
		$sRuleId1 = $this->GivenRuleInDB('MatchingRule1', [], [], 10);
		$sRuleId2 = $this->GivenRuleInDB('MatchingRule1', [], [], 20);

		$sOrgLessUserId = $this->GivenContactlessUserInDB(['Service Desk Agent']);
		$this->AssertUserMatchesRule($sRuleId1, $sOrgLessUserId, 'Lowest rank should match first');
	}

	public function testFirstMatchingRuleByRankShouldBeSelected_2()
	{
		$sRuleId1 = $this->GivenRuleInDB('MatchingRule1', [], [], 20);
		$sRuleId2 = $this->GivenRuleInDB('MatchingRule1', [], [], 10);

		$sOrgLessUserId = $this->GivenContactlessUserInDB(['Service Desk Agent']);
		$this->AssertUserMatchesRule($sRuleId2, $sOrgLessUserId, 'Lowest rank should match first');
	}

	public function testNonMatchingRulesWithLowerRankShouldBeIgnored()
	{
		$sRuleId1 = $this->GivenRuleInDB('NonMatchingRule', [$this->org1->GetKey()], [], 10);
		$sRuleId2 = $this->GivenRuleInDB('MatchingRule', [], [], 20);

		$sOrgLessUserId = $this->GivenContactlessUserInDB(['Service Desk Agent']);
		$this->AssertUserMatchesRule($sRuleId2, $sOrgLessUserId, 'Lower rank non matching rules should be ignored');
	}

	protected function GivenRuleInDB($sName, array $aOrgs, array $aProfiles, int $iRank = 0)
	{
		$aProfileList = array_map(function($sProfileId) {
			return self::$aURP_Profiles[$sProfileId];
		}, $aProfiles);
		return $this->CreateRule($sName, 'MFAUserSettingsTOTPApp', 'optional', $aOrgs, $aProfileList, $iRank)->GetKey();
	}

	protected function AssertUserMatchesRule($sExpectedKey, $sUserId, $sMessage = '')
	{
		$oAdminRule = MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($sUserId);
		if (is_null($sExpectedKey)) {
			$this->assertNull($oAdminRule, $sMessage);
		} else {
			$this->assertNotNull($oAdminRule, $sMessage);
			$this->assertEquals($sExpectedKey, $oAdminRule->GetKey(), $sMessage);
		}
	}
}
