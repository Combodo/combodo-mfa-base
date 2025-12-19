<?php

namespace Combodo\iTop\MFABase\Test\Integration;

use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\MFABase\Test\AbstractMFATest;
use Combodo\iTop\MFABase\Test\MFAAbstractConfigurationTestInterface;
use Combodo\iTop\MFABase\Test\MFAAbstractValidationTestInterface;
use Combodo\iTop\MFATotp\Service\MFATOTPMailService;
use Combodo\iTop\MFATotp\Service\OTPService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Dict;
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
class MfaLoginTotpMailIntegrationTest extends AbstractMFATest implements MFAAbstractValidationTestInterface, MFAAbstractConfigurationTestInterface {
	//iTop called from outside
	//users need to be persisted in DB
	const USE_TRANSACTION = false;

	protected string $sPassword;
	protected User $oUser;
	protected string $sUniqId;

	protected function setUp(): void {
		parent::setUp();

		$this->sUniqId = "MFABASE" . uniqid();
		$this->CleanupAdminRules();
		$this->CleanupMFASettings();
		$this->sPassword = "abCDEF12345@";
		/** @var User oUser */
		$this->oUser = $this->CreateContactlessUser('login' . uniqid(),
			ItopDataTestCase::$aURP_Profiles['Service Desk Agent'],
			$this->sPassword
		);
	}

	protected function tearDown(): void {
		\UserRights::Logoff();
		parent::tearDown();

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
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPMail', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sOutput = $this->CallItopUri('pages/UI.php', ['auth_user' => $this->oUser->Get('login'), 'auth_pwd' => $this->sPassword]);

		// Assert
		$sTitle = Dict::S('MFATOTP:Mail:Validation:Title');
		$this->AssertStringContains($sTitle, $sOutput, 'The page should be the TOTP Mail code validation screen');
		$this->AssertStringContains('<input type="text" id="totp_code" name="totp_code" value="" size="6"', $sOutput, 'The page should have a code input form');

		$this->CheckThereIsAReturnToLoginPageLink($sOutput);

	}

	public function testValidationFailed()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPMail', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUri('pages/UI.php', [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => 'WrongCode',
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringContains(Dict::S('MFATOTP:Mail:Validation:Title'), $sOutput, 'The page should be the TOTP Mail code validation screen');
		$this->AssertStringNotContains(Dict::S('UI:Login:Welcome'), $sOutput, 'The page should NOT be the initial login page');
		$this->CheckThereIsAReturnToLoginPageLink($sOutput);
	}

	public function testValidationForceReturnToLoginPage()
	{
		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPMail', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sOutput = $this->CallItopUri('pages/UI.php', [
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
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPMail', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUri('pages/preferences.php', [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => $this->GetCodeFromSentEmail($oActiveSetting1),
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringNotContains(Dict::S('MFATOTP:Mail:Validation:Title'), $sOutput, 'The page should NOT be the TOTP Mail code validation screen');
		$this->AssertStringContains(Dict::S("UI:Preferences:Title"), $sOutput, 'The page should propose a redirection content to finish login');
		//$this->AssertStringContains(Dict::S("MFATOTP:Redirection:Title"), $sOutput, 'The page should propose a redirection content to finish login');
	}

	public function testValidationFailDueToInvalidTransactionId()
	{
		$this->SkipTestWhenNoTransactionConfigured();

		// Arrange
		$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPMail', $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$oTOTP = new OTPService($oActiveSetting1);
		$sCode = $oTOTP->GetCode();
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUri('pages/UI.php', [
			'transaction_id' => "WrongID",
			'totp_code' => $this->GetCodeFromSentEmail($oActiveSetting1),
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringNotContains(Dict::S('MFATOTP:Mail:Validation:Title'), $sOutput, 'The page should NOT be the TOTP Mail code validation screen');
		$this->AssertStringContains(Dict::S('UI:Login:Welcome'), $sOutput, 'The page should be the initial login page');
	}

	public function testConfigurationFirstScreenDisplay()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPMail', 'forced', [], [], 70);

		// Act
		$aPostFields = [
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd'  => $this->sPassword
		];
		$sOutput = $this->CallItopUri('pages/UI.php', $aPostFields);

		// Assert
		$this->AssertStringContains(Dict::S('MFATOTP:Mail:Validation:Title'), $sOutput, 'The page should be the welcome page');

		$sSearchedHtml=<<<HTML
<form id="totp_form" method="post">
HTML;
		$iStart = strpos($sOutput, $sSearchedHtml);
		$sFormOutput = substr($sOutput, $iStart);

		foreach ($aPostFields as $sKey => $sVal) {
			$sExpected = <<<HTML
<input type="hidden" value="$sVal" name="$sKey">
HTML;
			$this->assertTrue(false !== strpos($sFormOutput, $sExpected), "switch form should contain param to post $sKey with his value: $sFormOutput");
		}
		$this->CheckThereIsAReturnToLoginPageLink($sOutput);
	}

	public function testConfigurationForceReturnToLoginPage()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPMail', 'forced', [], [], 70);

		// Act
		$sOutput = $this->CallItopUri('pages/UI.php', [
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword,
			'mfa_restart_login' => 'true']);

		// Assert
		$this->AssertStringContains(Dict::S('UI:Login:Welcome'), $sOutput, 'The page should be the initial login page');

	}

	public function testConfigurationFailDueToInvalidTransactionId()
	{
		$this->SkipTestWhenNoTransactionConfigured();

		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPMail', 'forced', [], [], 70);

		// Act
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPMail');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));
		$oTOTP = new OTPService($oActiveSetting);
		$sCode = $oTOTP->GetCode();
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUri('pages/UI.php', [
			'transaction_id' => '753951',
			'totp_code' => $this->GetCodeFromSentEmail($oActiveSetting),
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPMail');
		$this->assertEquals('no', $oActiveSetting->Get('validated'), var_export($this->PrintQRStuff($oActiveSetting, $sOutput), true));
		$this->AssertStringContains(Dict::S('iTopUpdate:Error:InvalidToken'), $sOutput, 'The page should be the welcome page');
		$this->CheckThereIsAReturnToLoginPageLink($sOutput);
	}

	public function testConfigurationFailed()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPMail', 'forced', [], [], 70);

		// Act
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUri('pages/UI.php', [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => 'Wrong Code',
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPMail');
		$this->assertEquals('no', $oActiveSetting->Get('validated'), var_export($this->PrintQRStuff($oActiveSetting, $sOutput), true));
		$this->AssertStringContains(Dict::S('MFATOTP:Mail:Validation:Title'), $sOutput, 'The page should be the welcome page');
		$this->CheckThereIsAReturnToLoginPageLink($sOutput);
	}

	public function testConfigurationOK()
	{
		// Arrange
		$oRule = $this->CreateRule('rule', 'MFAUserSettingsTOTPMail', 'forced', [], [], 70);

		// Ask for configuration and generate UserSettings
		$sLogin = $this->oUser->Get('login');

		// Act
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPMail');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));

		$oTOTP = new OTPService($oActiveSetting);
		$sCode = $oTOTP->GetCode();
		$sOutput = $this->CallItopUri('pages/preferences.php', [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => $this->GetCodeFromSentEmail($oActiveSetting),
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword]);

		// Assert
		$oActiveSetting->Reload();
		$this->assertEquals('yes', $oActiveSetting->Get('validated'), var_export($this->PrintQRStuff($oActiveSetting, $sOutput), true));
		$this->AssertStringNotContains(Dict::S('MFATOTP:Mail:Config:Title'), $sOutput, 'The page should be the welcome page');

		$this->AssertStringContains(Dict::S('MFATOTP:Redirection:Title'), $sOutput, 'The page should contain redirection title');
		$this->AssertStringContains(sprintf('window.location = "%s";', \utils::GetAbsoluteUrlAppRoot()), $sOutput, 'The page should contain a redirection link');
		$this->AssertStringContains('pages/preferences.php', $sOutput, 'The page should contain a redirection link');

		//$sLoggedInAsMessage = Dict::Format('UI:LoggedAsMessage', '', $this->oUser->Get('login'));
		//$this->AssertStringContains($sLoggedInAsMessage, $sOutput, 'The proper user should be connected');
	}

	private function GetNewGeneratedTransId(string $sLogin) {
		\UserRights::Login($sLogin);
		$sTransId = \utils::GetNewTransactionId();
		\UserRights::_ResetSessionCache();

		return $sTransId;
	}

	private function GetCodeFromSentEmail(\MFAUserSettingsTOTPMail $oUserSettings) : string {
		try{
			MFATOTPMailService::GetInstance()->SetEmail($this->createMock(\Email::class));
			MFATOTPMailService::GetInstance()->SendCodeByEmail($oUserSettings);
		}catch (\Exception $e){
			var_dump($e);
		}

		$oUserSettings->Reload();
		$oTOTP = new OTPService($oUserSettings);
		$sCode = $oTOTP->GetCode();
		echo "Mail Totp generated code: $sCode" . PHP_EOL;
		return $sCode;
	}
}
