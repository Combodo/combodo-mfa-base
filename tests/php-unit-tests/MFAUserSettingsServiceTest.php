<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Config;
use MetaModel;
use MFAUserSettings;

require_once __DIR__.'/AbstractMFATest.php';

class MFAUserSettingsServiceTest extends AbstractMFATest
{
	private $sConfigTmpBackupFile;
	/** @var MFAAdminRuleService $oMFAAdminRuleService */
	private $oMFAAdminRuleService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-mfa-base/vendor/autoload.php');

		$this->sConfigTmpBackupFile = tempnam(sys_get_temp_dir(), "config_");
		MetaModel::GetConfig()->WriteToFile($this->sConfigTmpBackupFile);

		$this->oMFAAdminRuleService = $this->createMock(MFAAdminRuleService::class);
		MFAUserSettingsService::SetMFAAdminRuleService($this->oMFAAdminRuleService);

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

	public function Rule_ModuleConfig()
	{
		return [
			'module disabled' => [false],
			'module enabled' => [true],
		];
	}

	/**
	 * @dataProvider Rule_ModuleConfig
	 */
	public function testGetValidatedMFASettings(bool $bModuleEnabled)
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		if ($bModuleEnabled) {
			$this->oMFAAdminRuleService->expects($this->exactly(1))
				->method("GetAdminRuleByUserId")
				->willReturn(null)
				->with($sUserId);
		} else {
			$this->oMFAAdminRuleService->expects($this->exactly(0))
				->method("GetAdminRuleByUserId");
		}

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', $bModuleEnabled);
		$oActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "yes", ["secret" => "toto"]);
		$oNotActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPMail", $sUserId, "no", ["secret" => "toto"]);
		$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $sUserId, "yes", []);
		$MFAUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
		if ($bModuleEnabled) {
			$this->CheckSettings([$oActiveSetting, $oActiveSetting2], $MFAUserSettings);
		} else {
			$this->assertEquals([], $MFAUserSettings);
		}
	}

	public function testGetValidatedMFASettings_OrderRespectPreferredMode()
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn(null)
			->with($sUserId);

		$oActiveSetting1 = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "yes", []);
		$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsTOTPMail", $sUserId, "yes", []);
		$oActiveSetting3 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $sUserId, "yes", []);
		$aMFAUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
		$this->assertEquals(["MFAUserSettingsTOTPApp", "MFAUserSettingsTOTPMail", "MFAUserSettingsRecoveryCodes"],
			$this->GetUserSettingsClasses($aMFAUserSettings));

		$this->oMFAAdminRuleService = $this->createMock(MFAAdminRuleService::class);
		MFAUserSettingsService::SetMFAAdminRuleService($this->oMFAAdminRuleService);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn($this->CreateRule('MFAUserSettingsTOTPMail', 'MFAUserSettingsTOTPMail', 'optional'))
			->with($sUserId);
		$aMFAUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
		$this->assertEquals(["MFAUserSettingsTOTPMail", "MFAUserSettingsTOTPApp", "MFAUserSettingsRecoveryCodes"],
			$this->GetUserSettingsClasses($aMFAUserSettings));
	}

	public function testGetValidatedMFASettings_OrderRespectDefault()
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn(null)
			->with($sUserId);

		$oActiveSetting1 = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "yes", []);
		$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsTOTPMail", $sUserId, "yes", []);
		$oActiveSetting3 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $sUserId, "yes", []);
		$aMFAUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
		$this->assertEquals(["MFAUserSettingsTOTPApp", "MFAUserSettingsTOTPMail", "MFAUserSettingsRecoveryCodes"],
			$this->GetUserSettingsClasses($aMFAUserSettings));

		//set default
		$this->updateObject('MFAUserSettingsTOTPMail', $oActiveSetting2->GetKey(), ['is_default' => "yes"]);
		$this->oMFAAdminRuleService = $this->createMock(MFAAdminRuleService::class);
		MFAUserSettingsService::SetMFAAdminRuleService($this->oMFAAdminRuleService);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn(null)
			->with($sUserId);
		$aMFAUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
		$this->assertEquals(["MFAUserSettingsTOTPMail", "MFAUserSettingsTOTPApp", "MFAUserSettingsRecoveryCodes"],
			$this->GetUserSettingsClasses($aMFAUserSettings));

		$this->oMFAAdminRuleService = $this->createMock(MFAAdminRuleService::class);
		MFAUserSettingsService::SetMFAAdminRuleService($this->oMFAAdminRuleService);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn($this->CreateRule('MFAUserSettingsTOTPApp', 'MFAUserSettingsTOTPApp', 'optional'))
			->with($sUserId);
		$aMFAUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
		$this->assertEquals(["MFAUserSettingsTOTPMail", "MFAUserSettingsTOTPApp", "MFAUserSettingsRecoveryCodes"],
			$this->GetUserSettingsClasses($aMFAUserSettings));
	}

	public function testGetValidatedMFASettings_AtLeastOneModeCanBeDefaultMustExist()
	{
		$oUser = $this->CreateContactlessUser('NoOrgUser', ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], 'ABCdefg@12345#');
		$sUserId = $oUser->GetKey();
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method('GetAdminRuleByUserId')
			->willReturn(null)
			->with($sUserId);

		$oActiveSetting3 = $this->CreateSetting('MFAUserSettingsRecoveryCodes', $sUserId, 'yes', []);
		$aMFAUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
		$this->assertEquals([], $aMFAUserSettings);
	}

	private function GetUserSettingsClasses($aMFAUserSettings)
	{
		$res = [];
		foreach ($aMFAUserSettings as $obj) {
			$res[] = get_class($obj);
		}

		return $res;
	}

	public function testGetValidatedMFASettings_AdminRuleSetWithoutDeniedMode()
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPMail", "forced");
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn($oRule)
			->with($sUserId);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetDeniedModes")
			->with($oRule)
			->willReturn([]);

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
		$oActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "yes", ["secret" => "toto"]);
		$oNotActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPMail", $sUserId, "no", ["secret" => "toto"]);
		$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $sUserId, "yes", []);
		$MFAUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
		$this->CheckSettings([$oActiveSetting, $oActiveSetting2], $MFAUserSettings);
	}

	public function testGetValidatedMFASettings_AdminRuleSetWithDeniedMode()
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		$aDeniedModes = [\MFAUserSettingsRecoveryCodes::class];
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPMail", "forced", [], [], 1, $aDeniedModes);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->with($sUserId)
			->willReturn($oRule);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetDeniedModes")
			->with($oRule)
			->willReturn($aDeniedModes);

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
		$oActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "yes", ["secret" => "toto"]);
		$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsTOTPMail", $sUserId, "yes", ["secret" => "toto"]);
		$oActiveSetting3 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $sUserId, "yes", []);
		$MFAUserSettings = MFAUserSettingsService::GetInstance()->GetValidatedMFASettings($sUserId);
		$this->CheckSettings([$oActiveSetting, $oActiveSetting2], $MFAUserSettings);
	}

	public function GetAllAllowedMFASettings_ModuleConfig()
	{
		return [
			'module disabled' => [false],
			'module enabled' => [true],
		];
	}

	/**
	 * @dataProvider Rule_ModuleConfig
	 */
	public function testGetAllAllowedMFASettings(bool $bModuleEnabled)
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		if ($bModuleEnabled) {
			$this->oMFAAdminRuleService->expects($this->exactly(1))
				->method("GetAdminRuleByUserId")
				->willReturn(null)
				->with($sUserId);
		} else {
			$this->oMFAAdminRuleService->expects($this->exactly(0))
				->method("GetAdminRuleByUserId");
		}

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', $bModuleEnabled);
		$oActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "yes", ["secret" => "toto"]);
		$oNotActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPMail", $sUserId, "no", ["secret" => "toto"]);

		$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $sUserId, "yes", []);
		$aExpectedSettings = [$oActiveSetting, $oNotActiveSetting, $oActiveSetting2];
		if (class_exists(\MFAUserSettingsWebAuthn::class)) {
			$aExpectedSettings[] = $this->CreateSetting("MFAUserSettingsWebAuthn", $sUserId, "no");
		}

		$MFAUserSettings = MFAUserSettingsService::GetInstance()->GetAllAllowedMFASettings($sUserId);
		if ($bModuleEnabled) {
			$this->CheckSettings($aExpectedSettings, $MFAUserSettings);
		} else {
			$this->assertEquals([], $MFAUserSettings);
		}
	}

	public function testGetAllAllowedMFASettings_AdminRuleSetWithoutDeniedMode()
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPMail", "forced");
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn($oRule)
			->with($sUserId);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetDeniedModes")
			->with($oRule)
			->willReturn([]);

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
		$oActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "no", ["secret" => "toto"]);
		$oNotActiveSetting = MetaModel::NewObject("MFAUserSettingsTOTPMail", ['user_id' => $sUserId]);
		$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $sUserId, "no", []);

		$aExpectedSettings = [$oActiveSetting, $oNotActiveSetting, $oActiveSetting2];
		if (class_exists(\MFAUserSettingsWebAuthn::class)) {
			$aExpectedSettings[] = $this->CreateSetting("MFAUserSettingsWebAuthn", $sUserId, "no");
		}
		$MFAUserSettings = MFAUserSettingsService::GetInstance()->GetAllAllowedMFASettings($sUserId);
		$this->CheckSettings($aExpectedSettings, $MFAUserSettings);
	}

	public function CheckSettings(array $aExpectedSettings, array $Settings)
	{
		$RuleNames = [];
		foreach ($Settings as $sMode => $oRule) {
			/** @var MFAUserSettings $oRule */
			$RuleNames[get_class($oRule)] = $oRule->GetKey();
		}

		$aExpectedRuleNames = [];
		foreach ($aExpectedSettings as $oRule) {
			/** @var MFAUserSettings $oRule */
			if ($oRule->GetKey() < 0) {
				//no need to compare GetKey()
				$aExpectedRuleNames[get_class($oRule)] = $RuleNames[get_class($oRule)] ?? -1;
			} else {
				$aExpectedRuleNames[get_class($oRule)] = $oRule->GetKey();
			}
		}


		$this->assertEquals($aExpectedRuleNames, $RuleNames);
	}

	public function testGetAllAllowedMFASettings_AdminRuleSetWithDeniedMode()
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		$aDeniedModes = [\MFAUserSettingsTOTPApp::class];
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPMail", "forced", [], [], 1, $aDeniedModes);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->with($sUserId)
			->willReturn($oRule);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetDeniedModes")
			->with($oRule)
			->willReturn($aDeniedModes);

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
		$oActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "no", ["secret" => "toto"]);
		$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsTOTPMail", $sUserId, "no", ["secret" => "toto"]);
		$oActiveSetting3 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $sUserId, "no", []);


		$aExpectedSettings = [$oActiveSetting2, $oActiveSetting3];
		if (class_exists(\MFAUserSettingsWebAuthn::class)) {
			$aExpectedSettings[] = $this->CreateSetting("MFAUserSettingsWebAuthn", $sUserId, "no");
		}

		$MFAUserSettings = MFAUserSettingsService::GetInstance()->GetAllAllowedMFASettings($sUserId);
		$this->CheckSettings($aExpectedSettings, $MFAUserSettings);
	}

	public function testGetMFAUserSettings_ReloadDbObject()
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		$aDeniedModes = [\MFAUserSettingsTOTPApp::class];
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPMail", "forced", [], [], 1, $aDeniedModes);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->with($sUserId)
			->willReturn($oRule);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetDeniedModes")
			->with($oRule)
			->willReturn([]);

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
		$oActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPMail", $sUserId, "no", ["secret" => "toto"]);
		$MFAUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, "MFAUserSettingsTOTPMail");
		$this->CheckSettings([$oActiveSetting], [$MFAUserSettings]);
	}

	public function testGetMFAUserSettings_CreateObject()
	{
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();
		$aDeniedModes = [\MFAUserSettingsTOTPApp::class];
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPMail", "forced", [], [], 1, $aDeniedModes);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->with($sUserId)
			->willReturn($oRule);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetDeniedModes")
			->with($oRule)
			->willReturn([]);

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
		$MFAUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, "MFAUserSettingsTOTPMail");
		$this->assertFalse($MFAUserSettings->IsNew());
		$this->assertEquals('MFAUserSettingsTOTPMail', get_class($MFAUserSettings));
	}

	public function testSetAsDefaultMode()
	{
		$oUser = $this->CreateContactlessUser('NoOrgUser', ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], 'ABCdefg@12345#');
		$sUserId = $oUser->GetKey();

		$oActiveSetting = $this->CreateSetting('MFAUserSettingsTOTPApp', $sUserId, 'yes', ['secret' => 'toto'], true);
		$oNotActiveSetting = $this->CreateSetting('MFAUserSettingsTOTPMail', $sUserId, 'no', ['secret' => 'toto']);
		$oActiveSetting2 = $this->CreateSetting('MFAUserSettingsRecoveryCodes', $sUserId, 'yes', []);

		$aUserSettings = MFAUserSettingsService::GetInstance()->GetMFASettingsObjects($sUserId);
		$aExpected = ['MFAUserSettingsTOTPApp' => 'yes', 'MFAUserSettingsTOTPMail' => 'no', 'MFAUserSettingsRecoveryCodes' => 'no'];
		foreach ($aUserSettings as $oUserSettings) {
			$expected = $aExpected[get_class($oUserSettings)] ?? 'no implementation found';
			$this->assertEquals($expected, $oUserSettings->Get('is_default'), 'class '.get_class($oUserSettings));
		}
		MFAUserSettingsService::GetInstance()->SetAsDefaultMode($sUserId, 'MFAUserSettingsTOTPMail');

		$aUserSettings = MFAUserSettingsService::GetInstance()->GetMFASettingsObjects($sUserId);
		$aExpected = ['MFAUserSettingsTOTPApp' => 'no', 'MFAUserSettingsTOTPMail' => 'yes', 'MFAUserSettingsRecoveryCodes' => 'no'];
		foreach ($aUserSettings as $oUserSettings) {
			$expected = $aExpected[get_class($oUserSettings)] ?? 'no implementation found';
			$this->assertEquals($expected, $oUserSettings->Get('is_default'), 'class '.get_class($oUserSettings));
		}
	}
}
