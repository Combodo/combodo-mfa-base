<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Config;
use MetaModel;

require_once __DIR__.'/AbstractMFATest.php';

class MFABaseServiceTest extends AbstractMFATest {
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-mfa-base/vendor/autoload.php');

		$this->sConfigTmpBackupFile = tempnam(sys_get_temp_dir(), "config_");
		MetaModel::GetConfig()->WriteToFile($this->sConfigTmpBackupFile);

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

	public function testSetAsDefaultMode() {
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();

		$oActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "yes", ["secret" => "toto"], true);
		$oNotActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPMail", $sUserId, "no", ["secret" => "toto"]);
		$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $sUserId, "yes", []);

		$aUserSettings = MFAUserSettingsService::GetInstance()->GetMFASettingsObjects($sUserId);
		$aExpected = ["MFAUserSettingsTOTPApp" => 'yes', "MFAUserSettingsTOTPMail" => 'no', "MFAUserSettingsRecoveryCodes" => 'no' ];
		foreach ($aUserSettings as $oUserSettings){
			$expected = $aExpected[get_class($oUserSettings)] ?? "no implementation found";
			$this->assertEquals($expected, $oUserSettings->Get('is_default'), "class " . get_class($oUserSettings));
		}
		MFABaseService::GetInstance()->SetAsDefaultMode($sUserId, "MFAUserSettingsTOTPMail");

		$aUserSettings = MFAUserSettingsService::GetInstance()->GetMFASettingsObjects($sUserId);
		$aExpected = ["MFAUserSettingsTOTPApp" => 'no', "MFAUserSettingsTOTPMail" => 'yes', "MFAUserSettingsRecoveryCodes" => 'no' ];
		foreach ($aUserSettings as $oUserSettings){
			$expected = $aExpected[get_class($oUserSettings)] ?? "no implementation found";
			$this->assertEquals($expected, $oUserSettings->Get('is_default'), "class " . get_class($oUserSettings));
		}
	}
}
