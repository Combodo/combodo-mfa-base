<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Config;
use MFAAdminRule;
use MFAMode;
use MFAUserSettings;

class AbstractMFATest extends ItopDataTestCase
{
	protected Config $oiTopConfig;

	public function CleanupAdminRules()
	{
		$oSearch = \DBObjectSearch::FromOQL("SELECT MFAAdminRule");
		$oSet = new \DBObjectSet($oSearch);
		while ($oRule = $oSet->Fetch()) {
			$oRule->DBDelete();
		}
	}

	public function CleanupMFASettings()
	{
		$oSearch = \DBObjectSearch::FromOQL("SELECT MFAUserSettings");
		$oSet = new \DBObjectSet($oSearch);
		while ($oObj = $oSet->Fetch()) {
			$oObj->DBDelete();
		}
	}

	public function CreateSetting($sUserSettingClass, $sUserId, string $sValidated, $aAdditionFields = [], bool $bIsDefault = false): MFAUserSettings
	{
		/** @var MFAUserSettings $oSettings */
		$aParams = array_merge($aAdditionFields, [
			'validated' => $sValidated,
			'is_default' => $bIsDefault ? "yes" : "no",
			'user_id' => $sUserId,
		]);
		$oSettings = $this->createObject($sUserSettingClass, $aParams);

		return $oSettings;
	}

	public function CreateUserWithProfilesAndOrg(string $sLogin, array $aOrgIds, $aProfiles = [])
	{
		$iOrgId = reset($aOrgIds);
		$oPerson = $this->CreatePerson("$sLogin", $iOrgId);

		$oProfileLinkSet = new \ormLinkSet(\User::class, 'profile_list', \DBObjectSet::FromScratch(\URP_UserProfile::class));
		if (count($aProfiles) != 0) {
			foreach ($aProfiles as $iProfId) {
				$oUserProfile = new \URP_UserProfile();
				$oUserProfile->Set('profileid', $iProfId);
				$oUserProfile->Set('reason', 'UNIT Tests');
				$oProfileLinkSet->AddItem($oUserProfile);
			}
		}

		$oAllowedOrgSet = new \ormLinkSet(\User::class, 'allowed_org_list', \DBObjectSet::FromScratch(\URP_UserOrg::class));
		foreach ($aOrgIds as $iOrgId) {
			$oObject = new \URP_UserOrg();
			$oObject->Set("allowed_org_id", $iOrgId);
			$oAllowedOrgSet->AddItem($oObject);
		}
		$oUser = $this->createObject('UserLocal', [
			'login' => $sLogin,
			'password' => "ABCdefg@12345#",
			'language' => 'EN US',
			'profile_list' => $oProfileLinkSet,
			'contactid' => $oPerson->GetKey(),
			'allowed_org_list' => $oAllowedOrgSet,
		]);

		return $oUser;
	}

	public function CreateRule(string $sName, string $sMfaClass, $sState, $aOrgs = [], $aProfiles = [], $iRank = 100, $aDeniedModes = []): MFAAdminRule
	{
		/** @var MFAAdminRule $oRule */
		$oRule = $this->createObject(MFAAdminRule::class, [
			'name' => $sName,
			'preferred_mfa_mode' => $sMfaClass,
			'operational_state' => $sState,
			'rank' => $iRank,
		]);

		$aParams = [];
		if (count($aProfiles) != 0) {
			/** @var \ormLinkSet $aProfileSet */
			$aProfileSet = $oRule->Get('profiles_list');
			foreach ($aProfiles as $iProfId) {
				$aProfileSet->AddItem(\MetaModel::NewObject('lnkMFAAdminRuleToProfile', ['profile_id' => $iProfId]));
			}

			$aParams = ['profiles_list' => $aProfileSet];
		}

		if (count($aOrgs) != 0) {
			/** @var \ormLinkSet $aProfileSet */
			$aOrgSet = $oRule->Get('orgs_list');
			foreach ($aOrgs as $iOrgId) {
				$aOrgSet->AddItem(\MetaModel::NewObject('lnkMFAAdminRuleToOrganization', ['org_id' => $iOrgId]));
			}
			$aParams['orgs_list'] = $aOrgSet;
		}

		if (count($aDeniedModes) != 0) {
			/** @var \ormLinkSet $oDeniedLinkset */
			$oDeniedLinkset = $oRule->Get('denied_mfamodes_list');
			foreach ($aDeniedModes as $sMfaMode) {
				/** @var MFAMode $oMfaMode */
				$oMfaMode = $this->createObject(MFAMode::class, [
					'name' => $sMfaMode,
				]);

				$oDeniedLinkset->AddItem(\MetaModel::NewObject('lnkMFAAdminRuleToMFAMode', ['mfamode_id' => $oMfaMode]));
			}
			$aParams['denied_mfamodes_list'] = $oDeniedLinkset;
		}

		if (count($aParams) != 0) {
			$oRule = $this->updateObject(MFAAdminRule::class, $oRule->GetKey(), $aParams);
		}

		return $oRule;
	}

	protected function CallItopUrl($sUri, ?array $aPostFields = null, $bXDebugEnabled = false)
	{
		$ch = curl_init();
		if ($bXDebugEnabled) {
			curl_setopt($ch, CURLOPT_COOKIE, 'XDEBUG_SESSION=phpstorm');
		}

		$sUrl = $this->oiTopConfig->Get('app_root_url')."/$sUri";
		curl_setopt($ch, CURLOPT_URL, $sUrl);
		curl_setopt($ch, CURLOPT_POST, 1);// set post data to true
		curl_setopt($ch, CURLOPT_POSTFIELDS, $aPostFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$sOutput = curl_exec($ch);
		//echo "$sUrl error code:".curl_error($ch);
		curl_close($ch);

		return $sOutput;
	}

	protected function SaveItopConfFile()
	{
		@chmod($this->oiTopConfig->GetLoadedFile(), 0770);
		$this->oiTopConfig->WriteToFile();
		@chmod($this->oiTopConfig->GetLoadedFile(), 0440);
	}

	protected function AssertStringContains($sNeedle, $sHaystack, $sMessage): void
	{
		$this->assertNotNull($sNeedle, $sMessage);
		$this->assertNotNull($sHaystack, $sMessage);

		$this->assertTrue(false !== strpos($sHaystack, $sNeedle), $sMessage . PHP_EOL . "needle: '$sNeedle' not found in content below:" . PHP_EOL . PHP_EOL . $sHaystack);
	}

	protected function AssertStringNotContains($sNeedle, $sHaystack, $sMessage): void
	{
		$this->assertNotNull($sNeedle, $sMessage);
		$this->assertNotNull($sHaystack, $sMessage);

		$this->assertFalse(false !== strpos($sHaystack, $sNeedle), $sMessage. PHP_EOL . "needle: '$sNeedle' should not be found in content below:" . PHP_EOL . PHP_EOL . $sHaystack);
	}
}
