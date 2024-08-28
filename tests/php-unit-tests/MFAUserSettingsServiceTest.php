<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Config;
use MetaModel;
use MFAUserSettings;

class MFAUserSettingsServiceTest extends AbstractMFATest {
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

	public function Rule_ModuleConfig() {
		return [
			'module disabled' => [false],
			'module enabled' => [true],
		];
	}

	/**
	 * @dataProvider Rule_ModuleConfig
	 */
	public function testGetAllMFASettings_MFAEnableConf(bool $bModuleEnabled) {
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
		$oSetting = $this->CreateSetting("MFAUserSettingsTotpApp", $sUserId, "active", ["secret" => "toto"]);
		$MFAUserSettings = MFAUserSettingsService::GetInstance()->GetAllMFASettings($sUserId);
		if ($bModuleEnabled){
			$this->CheckSettings([$oSetting], $MFAUserSettings);
		} else {
			$this->assertEquals([], $MFAUserSettings);
		}
	}

	public function CheckSettings(array $aExpectedSettings, array $Settings) {
		$aExpectedRuleNames = [];
		foreach ($aExpectedSettings as $oRule){
			/** @var MFAUserSettings $oRule */
			$aExpectedRuleNames[get_class($oRule)]=$oRule->GetKey();
		}

		$RuleNames = [];
		foreach ($Settings as $sMode => $oRule){
			/** @var MFAUserSettings $oRule */
			$RuleNames[get_class($oRule)]=$oRule->GetKey();
		}

		$this->assertEquals($aExpectedRuleNames, $RuleNames);
	}
}
