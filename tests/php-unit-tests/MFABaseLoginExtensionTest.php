<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\MFABase\Hook\MFABaseLoginExtension;
use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Config;
use DateTime;
use LoginWebPage;
use MetaModel;
use MFAAdminRule;

require_once __DIR__.'/AbstractMFATest.php';

class MFABaseLoginExtensionTest extends AbstractMFATest {
	private $sConfigTmpBackupFile;

	/** @var MFAAdminRuleService $oMFAAdminRuleService */
	private $oMFAAdminRuleService;

	/** @var MFABaseService $oMFABaseService */
	private $oMFABaseService;

	/** @var MFAUserSettingsService $oMFAUserSettingsService */
	private $oMFAUserSettingsService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-mfa-base/vendor/autoload.php');

		$this->sConfigTmpBackupFile = tempnam(sys_get_temp_dir(), "config_");
		MetaModel::GetConfig()->WriteToFile($this->sConfigTmpBackupFile);

		$this->oMFAAdminRuleService = $this->createMock(MFAAdminRuleService::class);
		MFAAdminRuleService::SetInstance($this->oMFAAdminRuleService);

		$this->oMFAUserSettingsService = $this->createMock(MFAUserSettingsService::class);
		MFAUserSettingsService::SetInstance($this->oMFAUserSettingsService);

		$this->oMFABaseService = $this->createMock(MFABaseService::class);
		MFABaseService::SetInstance($this->oMFABaseService);

		$this->CleanupAdminRules();

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		MFAAdminRuleService::ResetInstance();
		MFAUserSettingsService::ResetInstance();
		MFABaseService::ResetInstance();

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

	public function testOnCredentialsOK_MfaDisabled() {
		$this->oMFAUserSettingsService->expects($this->exactly(0))
			->method("GetActiveMFASettings");
		$this->oMFAAdminRuleService->expects($this->exactly(0))
			->method("GetAdminRuleByUserId");

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', false);

		$oLoginExtension = new MFABaseLoginExtension();
		$iErrorCode = 666;
		$oLoginExtension->LoginAction(LoginWebPage::LOGIN_STATE_CREDENTIALS_OK, $iErrorCode);

		$this->assertEquals(666, $iErrorCode);
	}

	public function ValidateLoginProvider() {
		return [
			'login validated' => [true],
			'module NOT validated' => [false],
		];
	}

	/**
	 * @dataProvider ValidateLoginProvider
	 */
	public function testOnCredentialsOK_ValidateLogin($bLoginValidated) {
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();

		$oActiveSetting = $this->CreateSetting("MFAUserSettingsTOTPApp", $sUserId, "active", ["secret" => "toto"]);
		$this->oMFAUserSettingsService->expects($this->exactly(1))
			->method("GetActiveMFASettings")
			->willReturn([$oActiveSetting]);

		$this->oMFAAdminRuleService->expects($this->exactly(0))
			->method("GetAdminRuleByUserId");


		$this->oMFABaseService->expects($this->exactly(1))
			->method("ValidateLogin")
			->with($sUserId, [$oActiveSetting])
			->willReturn($bLoginValidated);

		$_SESSION=[];
		Session::Set("auth_user", $oUser->Get('login'));

		$oLoginExtension = new MFABaseLoginExtension();
		$iErrorCode = 666;
		$res = $oLoginExtension->LoginAction(LoginWebPage::LOGIN_STATE_CREDENTIALS_OK, $iErrorCode);

		if ($bLoginValidated){
			$this->assertEquals(LoginWebPage::LOGIN_FSM_CONTINUE, $res);
			$this->assertEquals(666, $iErrorCode);
		} else {
			$this->assertEquals(LoginWebPage::LOGIN_FSM_ERROR, $res);
			$this->assertEquals(LoginWebPage::EXIT_CODE_WRONGCREDENTIALS, $iErrorCode);
		}
	}

	public function testOnCredentialsOK_NoUserSettingsAndAdminRule() {
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$sUserId = $oUser->GetKey();

		$this->oMFAUserSettingsService->expects($this->exactly(1))
			->method("GetActiveMFASettings")
			->willReturn([]);

		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn(null);

		$this->oMFABaseService->expects($this->exactly(0))
			->method("ValidateLogin");

		$_SESSION=[];
		Session::Set("auth_user", $oUser->Get('login'));

		$oLoginExtension = new MFABaseLoginExtension();
		$iErrorCode = 666;
		$res = $oLoginExtension->LoginAction(LoginWebPage::LOGIN_STATE_CREDENTIALS_OK, $iErrorCode);

		$this->assertEquals(LoginWebPage::LOGIN_FSM_CONTINUE, $res);
		$this->assertEquals(666, $iErrorCode);
	}

	public function testOnCredentialsOK_NoUserSettingsAndNotForcedAdminRule() {
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");

		$this->oMFAUserSettingsService->expects($this->exactly(1))
			->method("GetActiveMFASettings")
			->willReturn([]);

		$oRule = $this->CreateRule("Rule", "MFAUserSettingsTOTPApp", "optional", [], [], 70);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn($oRule);

		$this->oMFABaseService->expects($this->exactly(0))
			->method("ValidateLogin");

		$this->oMFABaseService->expects($this->exactly(0))
			->method("DisplayWarningOnMFAActivation");

		$_SESSION=[];
		Session::Set("auth_user", $oUser->Get('login'));

		$oLoginExtension = new MFABaseLoginExtension();
		$iErrorCode = 666;
		$res = $oLoginExtension->LoginAction(LoginWebPage::LOGIN_STATE_CREDENTIALS_OK, $iErrorCode);

		$this->assertEquals(LoginWebPage::LOGIN_FSM_CONTINUE, $res);
		$this->assertEquals(666, $iErrorCode);
	}

	public function testOnCredentialsOK_ForcedRuleInTheFuture() {
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");

		$this->oMFAUserSettingsService->expects($this->exactly(1))
			->method("GetActiveMFASettings")
			->willReturn([]);

		$oForceActivateDatetimeInTheFuture = new DateTime("now + 1 day");
		$sForcedActivationDate = $oForceActivateDatetimeInTheFuture->format('Y-m-d');
		$oRule = $this->CreateRule("Rule", "MFAUserSettingsTOTPApp", "forced", [], [], 70);
		$oRule = $this->updateObject(MFAAdminRule::class, $oRule->GetKey(), ['forced_activation_date' => $sForcedActivationDate]);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn($oRule);

		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("IsForcedNow")
			->willReturn(false);

		$this->oMFABaseService->expects($this->exactly(0))
			->method("ValidateLogin");

		$this->oMFABaseService->expects($this->exactly(1))
			->method("DisplayWarningOnMFAActivation")
			->with($oUser->GetKey(), $oRule);

		$_SESSION=[];
		Session::Set("auth_user", $oUser->Get('login'));

		$oLoginExtension = new MFABaseLoginExtension();
		$iErrorCode = 666;
		$res = $oLoginExtension->LoginAction(LoginWebPage::LOGIN_STATE_CREDENTIALS_OK, $iErrorCode);

		$this->assertEquals(LoginWebPage::LOGIN_FSM_CONTINUE, $res);
		$this->assertEquals(666, $iErrorCode);
	}

	public function ConfigurationForcedNowProvider() {
		return [
			'configuration ok' => [true],
			'configuration NOT ok' => [false],
		];
	}

	/**
	 * @dataProvider ConfigurationForcedNowProvider
	 */
	public function testOnCredentialsOK_ConfigurationForcedNow($bConfigureOk) {
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");

		$this->oMFAUserSettingsService->expects($this->exactly(1))
			->method("GetActiveMFASettings")
			->willReturn([]);

		$oNow = new DateTime("now - 1 day");
		$sForcedActivationDate = $oNow->format('Y-m-d');
		$oRule = $this->CreateRule("Rule", "MFAUserSettingsTOTPApp", "forced", [], [], 70);
		$oRule = $this->updateObject(MFAAdminRule::class, $oRule->GetKey(), ['forced_activation_date' => $sForcedActivationDate]);
		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("GetAdminRuleByUserId")
			->willReturn($oRule);

		$this->oMFAAdminRuleService->expects($this->exactly(1))
			->method("IsForcedNow")
			->willReturn(true);

		$this->oMFABaseService->expects($this->exactly(0))
			->method("ValidateLogin");

		$this->oMFABaseService->expects($this->exactly(0))
			->method("DisplayWarningOnMFAActivation");

		$this->oMFABaseService->expects($this->exactly(1))
			->method("ConfigureMFAModeOnLogin")
			->with($oUser->GetKey(), $oRule)
			->willReturn($bConfigureOk);

		$_SESSION=[];
		Session::Set("auth_user", $oUser->Get('login'));

		$oLoginExtension = new MFABaseLoginExtension();
		$iErrorCode = 666;
		$res = $oLoginExtension->LoginAction(LoginWebPage::LOGIN_STATE_CREDENTIALS_OK, $iErrorCode);

		if ($bConfigureOk){
			$this->assertEquals(LoginWebPage::LOGIN_FSM_CONTINUE, $res);
			$this->assertEquals(666, $iErrorCode);
		} else {
			$this->assertEquals(LoginWebPage::LOGIN_FSM_ERROR, $res);
			$this->assertEquals(LoginWebPage::EXIT_CODE_WRONGCREDENTIALS, $iErrorCode);
		}
	}
}
