<?php

namespace Combodo\iTop\MFABase\Test\Integration;

use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\MFABase\Test\AbstractMFATest;
use Combodo\iTop\MFABase\Test\MFAAbstractConfigurationTestInterface;
use Combodo\iTop\MFABase\Test\MFAAbstractValidationTestInterface;
use Combodo\iTop\MFATotp\Service\OTPService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Dict;
use MetaModel;
use MFAAdminRule;
use User;

require_once dirname(__DIR__) . "/AbstractMFATest.php";
require_once __DIR__ . "/MFAAbstractValidationTestInterface.php";
require_once __DIR__ . "/MFAAbstractConfigurationTestInterface.php";

/**
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 *
 */
class MfaLoginTotpAppIntegrationTest extends AbstractMFATest implements MFAAbstractValidationTestInterface, MFAAbstractConfigurationTestInterface {
	//iTop called from outside
	//users need to be persisted in DB
	const USE_TRANSACTION = false;

	protected string $sConfigTmpBackupFile;
	protected string $sPassword;
	protected User $oUser;
	protected string $sUniqId;

	protected function setUp(): void {
		parent::setUp();
		ItopDataTestCase::$DEBUG_UNIT_TEST =true;

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
		//$this->oiTopConfig->Set('transactions_enabled', false);
		//$this->oiTopConfig->Set('log_transactions', true);
		$this->SaveItopConfFile();
	}

	protected function tearDown(): void {
		\UserRights::Logoff();

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

	public function CheckThereIsAReturnToLoginPageLink($sOutput) {
		$sForceRestartLoginLabelLink = Dict::S('Login:MFA:Restart:Label');
		$sHtml = <<<HTML
<a onclick="$('#mfa_restart_login_form').submit();">$sForceRestartLoginLabelLink</a></div>
HTML;
		$this->AssertStringContains($sHtml, $sOutput, 'The page should be contain a link to return to login page');
	}

	public function testValidationFirstScreenDisplay()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sOutput = $this->CallItopUrl('/pages/UI.php', ['auth_user' => $this->oUser->Get('login'), 'auth_pwd' => $this->sPassword]);

		// Assert
		$sTitle = Dict::S('MFATOTP:App:Validation:Title');
		$this->AssertStringContains($sTitle, $sOutput, 'The page should be the TOTP App code validation screen');
		$this->AssertStringContains('<input type="text" id="totp_code" name="totp_code" value="" size="6"', $sOutput, 'The page should have a code input form');

		$this->CheckThereIsAReturnToLoginPageLink($sOutput);

	}

	public function testValidationFailed()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => 'WrongCode',
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringContains(Dict::S('MFATOTP:App:Validation:Title'), $sOutput, 'The page should be the TOTP App code validation screen');
		$this->AssertStringNotContains(Dict::S('UI:Login:Welcome'), $sOutput, 'The page should NOT be the initial login page');
		$this->CheckThereIsAReturnToLoginPageLink($sOutput);
	}

	/*public function testDBDelete()
	{
		$this->assertTrue(true);
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);


		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => 'WrongCode',
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		foreach (MFAUserSettingsService::GetInstance()->GetMFASettingsObjects($this->oUser->GetKey()) as $oObject){
			//$oObject->AllowWrite();
			$oObject->AllowDelete();
			$oObject->DBDelete();
		}

		$oObject = $this->oUser;
		$sClass = get_class($oObject);
		$iKey = $oObject->GetKey();
		$this->debug("Removing $sClass::$iKey");
		$oObject->AllowWrite();
		$oObject->AllowDelete();
		$oObject->DBDelete();
	}*/


	public function testValidationForceReturnToLoginPage()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword,
			'mfa_restart_login' => 'true',
			]
		);

		// Assert
		$this->AssertStringContains(Dict::S('UI:Login:Welcome'), $sOutput, 'The page should be the initial login page');
	}

	public function testValidationOK()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$oTOTP = new OTPService($oActiveSetting1);
		$sCode = $oTOTP->GetCode();
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => $sCode,
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringNotContains(Dict::S('MFATOTP:App:Validation:Title'), $sOutput, 'The page should NOT be the TOTP App code validation screen');
		$sWelcomeWithoutIopApplicationName = str_replace(ITOP_APPLICATION, "", Dict::S('UI:WelcomeToITop'));
		$this->AssertStringContains($sWelcomeWithoutIopApplicationName, $sOutput, 'The page should be the welcome page');
		$sLoggedInAsMessage = Dict::Format('UI:LoggedAsMessage', '', $sLogin);
		$this->AssertStringContains($sLoggedInAsMessage, $sOutput, 'The proper user should be connected');
	}

	public function testValidationFailDueToInvalidTransactionId()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$oTOTP = new OTPService($oActiveSetting1);
		$sCode = $oTOTP->GetCode();
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'transaction_id' => "WrongID",
			'totp_code' => $sCode,
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringNotContains(Dict::S('MFATOTP:App:Validation:Title'), $sOutput, 'The page should NOT be the TOTP App code validation screen');
		$this->AssertStringContains(Dict::S('UI:Login:Welcome'), $sOutput, 'The page should be the initial login page');
	}

	public function testConfigurationFirstScreenDisplay()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPApp', 'forced', [], [], 70);

		// Act
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page');
		$this->CheckThereIsAReturnToLoginPageLink($sOutput);
	}

	public function testConfigurationForceReturnToLoginPage()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPApp', 'forced', [], [], 70);

		// Act
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword,
			'mfa_restart_login' => 'true']);

		// Assert
		$this->AssertStringContains(Dict::S('UI:Login:Welcome'), $sOutput, 'The page should be the initial login page');

	}

	public function testConfigurationFailDueToInvalidTransactionId()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPApp', 'forced', [], [], 70);

		// Act
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPApp');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));
		$oTOTP = new OTPService($oActiveSetting);
		$sCode = $oTOTP->GetCode();
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'transaction_id' => '753951',
			'totp_code' => $sCode,
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPApp');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));
		$this->AssertStringContains(Dict::S('MFATOTP:App:Configuration:Error'), $sOutput, 'The page should be the welcome page');
		$this->CheckThereIsAReturnToLoginPageLink($sOutput);
	}

	public function testConfigurationFailed()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPApp', 'forced', [], [], 70);

		// Act
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => 'Wrong Code',
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPApp');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));
		$this->AssertStringContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page');
		$this->CheckThereIsAReturnToLoginPageLink($sOutput);
	}

	public function testConfigurationOK()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPApp', 'forced', [], [], 70);

		// Ask for configuration and generate UserSettings
		$sLogin = $this->oUser->Get('login');

		// Act
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPApp');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));

		$oTOTP = new OTPService($oActiveSetting);
		$sCode = $oTOTP->GetCode();
		$sOutput = $this->CallItopUrl('/pages/UI.php', [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => $sCode,
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$oActiveSetting->Reload();
		$this->assertEquals('yes', $oActiveSetting->Get('validated'));
		$this->AssertStringNotContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page');

		$this->AssertStringContains(Dict::S('MFATOTP:Redirection:Title'), $sOutput, 'The page should contain redirection title');
		$this->AssertStringContains(sprintf('window.location = "%s";', \utils::GetAbsoluteUrlAppRoot()), $sOutput, 'The page should contain a redirection link');

		//$this->AssertStringContains(Dict::S('UI:WelcomeToITop'), $sOutput, 'The page should be the welcome page');
		//$sLoggedInAsMessage = Dict::Format('UI:LoggedAsMessage', '', $this->oUser->Get('login'));
		//$this->AssertStringContains($sLoggedInAsMessage, $sOutput, 'The proper user should be connected');
	}

	private function GetNewGeneratedTransId(string $sLogin) {
		\UserRights::Login($sLogin);
		$sTransId = \utils::GetNewTransactionId();
		\UserRights::_ResetSessionCache();

		return $sTransId;
	}
}
