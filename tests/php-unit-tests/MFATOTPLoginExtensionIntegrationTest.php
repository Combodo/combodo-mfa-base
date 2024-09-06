<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\HybridAuth\Test\Provider\ServiceProviderMock;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\MFATotp\Service\OTPService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Dict;
use MetaModel;
use MFAAdminRule;
use User;

require_once __DIR__ . "/AbstractMFATest.php";

/**
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 *
 */
class MFATOTPLoginExtensionIntegrationTest extends AbstractMFATest {
	//iTop called from outside
	//users need to be persisted in DB
	const USE_TRANSACTION = false;

	protected string $sConfigTmpBackupFile;
	protected string $sPassword;
	protected User $oUser;

	protected function setUp(): void {
		parent::setUp();

		$sConfigPath = MetaModel::GetConfig()->GetLoadedFile();

		clearstatcache();
		echo sprintf("rights via ls on %s:\n %s \n", $sConfigPath, exec("ls -al $sConfigPath"));
		$sFilePermOutput = substr(sprintf('%o', fileperms('/etc/passwd')), -4);
		echo sprintf("rights via fileperms on %s:\n %s \n", $sConfigPath, $sFilePermOutput);

		$this->sConfigTmpBackupFile = tempnam(sys_get_temp_dir(), "config_");
		MetaModel::GetConfig()->WriteToFile($this->sConfigTmpBackupFile);

		$this->sUniqId = "MFABASE" . uniqid();
		$this->CleanupAdminRules();
		$this->CleanupMFASettings();
		$this->sPassword = "abCDEF12345@";
		/** @var User oUser */
		$this->oUser = $this->CreateContactlessUser('login' . uniqid(),
			ItopDataTestCase::$aURP_Profiles['Service Desk Agent'],
			$this->sPassword
		);

		$this->oiTopConfig = new \Config($sConfigPath);
		$this->oiTopConfig->SetModuleSetting('combodo-mfa-base', 'enabled', true);
		$this->SaveItopConfFile();
	}

	protected function tearDown(): void {
		parent::tearDown();

		if (! is_null($this->sConfigTmpBackupFile) && is_file($this->sConfigTmpBackupFile)){
			//put config back
			$sConfigPath = $this->oiTopConfig->GetLoadedFile();
			@chmod($sConfigPath, 0770);
			$oConfig = new \Config($this->sConfigTmpBackupFile);
			$oConfig->WriteToFile($sConfigPath);
			@chmod($sConfigPath, 0440);
		}

		$_SESSION = [];
	}


	public function testTOTPAppValidationScreenDisplay()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sOutput = $this->CallItopUrl('/pages/UI.php', ['auth_user' => $this->oUser->Get('login'), 'auth_pwd' => $this->sPassword]);

		// Assert
		$sTitle = Dict::S('MFATOTP:App:Validation:Title');
		$this->AssertStringContains($sTitle, $sOutput, 'The page should be the TOTP App code validation screen');
		$this->AssertStringContains('<input type="text" id="totp_code" name="totp_code" value="" size="6"', $sOutput, 'The page should have a code input form');
	}

	public function testTOTPAppValidationCodeFailed()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'totp_code' => 'WrongCode',
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringNotContains(Dict::S('MFATOTP:App:Validation:Title'), $sOutput, 'The page should NOT be the TOTP App code validation screen');
		$this->AssertStringContains(Dict::S('UI:Login:Welcome'), $sOutput, 'The page should be the initial login page');
	}

	public function testTOTPAppValidationCodeOK()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$oTOTP = new OTPService($oActiveSetting1);
		$sCode = $oTOTP->GetCode();
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'totp_code' => $sCode,
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringNotContains(Dict::S('MFATOTP:App:Validation:Title'), $sOutput, 'The page should NOT be the TOTP App code validation screen');
		$this->AssertStringContains(Dict::S('UI:WelcomeToITop'), $sOutput, 'The page should be the welcome page');
		$sLoggedInAsMessage = Dict::Format('UI:LoggedAsMessage', '', $this->oUser->Get('login'));
		$this->AssertStringContains($sLoggedInAsMessage, $sOutput, 'The proper user should be connected');
	}

	public function testTOTPAppConfigurationScreenDisplay()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPApp', 'forced', [], [], 70);

		// Act
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page');
	}

	public function testTOTPAppConfigurationCodeFailed()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPApp', 'forced', [], [], 70);

		// Act
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'totp_code' => 'Wrong Code',
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page');
	}

	public function testTOTPAppConfigurationCodeOK()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPApp', 'forced', [], [], 70);
		// Ask for configuration and generate UserSettings
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword]);

		// Act
		$oActiveSetting1 = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPApp');
		$oTOTP = new OTPService($oActiveSetting1);
		$sCode = $oTOTP->GetCode();
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'totp_code' => $sCode,
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword]);

		var_export($sOutput);

		// Assert
		$this->AssertStringNotContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page');
		$this->AssertStringContains(Dict::S('UI:WelcomeToITop'), $sOutput, 'The page should be the welcome page');
		$sLoggedInAsMessage = Dict::Format('UI:LoggedAsMessage', '', $this->oUser->Get('login'));
		$this->AssertStringContains($sLoggedInAsMessage, $sOutput, 'The proper user should be connected');
	}
}
