<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\MFATotp\Service\OTPService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Config;
use MFAAdminRule;
use MFAUserSettings;
use Organization;

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

		$oUser = $this->createObject('UserLocal', [
			'login' => $sLogin,
			'password' => "ABCdefg@12345#",
			'language' => 'EN US',
			'profile_list' => $oProfileLinkSet,
			'contactid' => $oPerson->GetKey(),
		]);

		return $oUser;
	}

	public function CreateRule(string $sName, string $sMfaClass, $sState, $aOrgs = [], $aProfiles = [], $iRank = 100, $aDeniedModes = []): MFAAdminRule
	{
		/** @var MFAAdminRule $oRule */
		$oRule = $this->createObject(MFAAdminRule::class, [
			'name' => $sName . uniqid(),
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
			/** @var \ormSet $oDeniedModes */
			$oDeniedModes = $oRule->Get('denied_mfamodes');
			if ($oDeniedModes === null) {
				$oDeniedModes = new \ormSet(get_class($oRule), 'denied_mfamodes');
			}
			foreach ($aDeniedModes as $sMfaMode) {
				$oDeniedModes->Add($sMfaMode);
			}
			$aParams['denied_mfamodes'] = $oDeniedModes;
		}

		if (count($aParams) != 0) {
			$oRule = $this->updateObject(MFAAdminRule::class, $oRule->GetKey(), $aParams);
		}

		return $oRule;
	}

	protected function CallItopUrl($sUri, ?array $aPostFields = null, $bIsPost=true)
	{
		$ch = curl_init();

		$sUrl = $this->oiTopConfig->Get('app_root_url')."/$sUri";
		curl_setopt($ch, CURLOPT_URL, $sUrl);
		curl_setopt($ch, CURLOPT_POST, $bIsPost ? 1 : 0);// set post data to true
		if (! is_null($aPostFields)){
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($aPostFields));
		}
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

	/**
	 * @param string $sLogin
	 * @param array $aProfiles array of profile names
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function GivenContactlessUserInDB(array $aProfiles): string
	{
		$sLogin = 'demo_test_'.uniqid(__CLASS__, true);
		$sPassword = 'ABCdefg@12345#';

		$aProfileList = array_map(function($sProfileId) {
			return 'profileid:'.self::$aURP_Profiles[$sProfileId];
		}, $aProfiles);

		$iUser = $this->GivenObjectInDB('UserLocal', [
			'login' => $sLogin,
			'password' => $sPassword,
			'language' => 'EN US',
			'profile_list' => $aProfileList,
		]);
		return $iUser;
	}

	protected function GivenUserWithContactInDB($sOrgId, array $aProfiles)
	{
		static $i = 0;

		$iPersonId = $this->GivenPersonInDB($i++, $sOrgId);
		$sLogin = 'demo_test_'.uniqid(__CLASS__, true);
		$sPassword = 'ABCdefg@12345#';

		$aProfileList = array_map(function($sProfileId) {
			return 'profileid:'.self::$aURP_Profiles[$sProfileId];
		}, $aProfiles);

		$iUser = $this->GivenObjectInDB('UserLocal', [
			'login' => $sLogin,
			'password' => $sPassword,
			'language' => 'EN US',
			'profile_list' => $aProfileList,
			'contactid' => $iPersonId,
		]);

		return $iUser;
	}

	protected function PrintQRStuff(\MFAUserSettingsTOTP $oMFAUserSettings, string $sOutput) : array {
		$oOTPService = new OTPService($oMFAUserSettings);
		$aData = [
			'GetProvisioningUri' => $oOTPService->GetProvisioningUri(),
			'GetQRCodeData' => urldecode($oOTPService->GetProvisioningUri()),
			'sOutput' => $sOutput
		];
		//\IssueLog::Info('PrintQRStuff', null, $aData);
		return $aData;
	}

}
