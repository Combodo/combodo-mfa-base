<?php

namespace Combodo\iTop\MFABase\Test\Integration;

use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\MFABase\Test\AbstractMFATest;
use Combodo\iTop\MFABase\Test\MFAAbstractConfigurationTestInterface;
use Combodo\iTop\MFATotp\Service\OTPService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Dict;
use MetaModel;
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
class MfaMyAccountTotpAppIntegrationTest extends AbstractMFATest implements MFAAbstractConfigurationTestInterface {
	//iTop called from outside
	//users need to be persisted in DB
	const USE_TRANSACTION = false;

	protected string $sConfigTmpBackupFile;
	protected string $sPassword;
	protected string $sMfaMyAccountConfigurationUri;
	protected User $oUser;
	protected string $sUniqId;

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
		$aData = array(
			'org_id' => $this->CreateOrganization($this->sUniqId),
			'first_name' => 'Jesus',
			'name' => 'Deus',
			'email' => 'guru@combodo.com',
		);
		$iPerson = $this->CreateObject('Person', $aData);
		$this->oUser = $this->CreateUser('login' . uniqid(),
			ItopDataTestCase::$aURP_Profiles['Service Desk Agent'],
			$this->sPassword,
			$iPerson
		);

		$this->oiTopConfig = new \Config($sConfigPath);
		$this->oiTopConfig->SetModuleSetting('combodo-mfa-base', 'enabled', true);
		$this->oiTopConfig->Set(\LogAPI::ENUM_CONFIG_PARAM_FILE, ['MFA' => 'Debug']);

		//$this->oiTopConfig->Set('transactions_enabled', false);
		//$this->oiTopConfig->Set('log_transactions', true);
		$this->SaveItopConfFile();

		$this->sMfaMyAccountConfigurationUri = '/pages/exec.php?exec_module=combodo-mfa-totp&exec_page=index.php&exec_env=production&operation=MFATOTPAppConfig';
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

	public function testConfigurationFirstScreenDisplay()
	{
		// Act
		$sOutput = $this->CallItopUrl($this->sMfaMyAccountConfigurationUri, [
			'auth_user' => $this->oUser->Get('login'),
			'auth_pwd' => $this->sPassword,
			'Action' => "add:" . \MFAUserSettingsTOTPApp::class,
			]
		);

		// Assert
		$this->AssertStringContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page');
		$this->AssertStringContains("<input type=\"text\" id=\"totp_code\"", $sOutput, 'The page should contain form to validate TOTP App code');
	}

	public function testConfigurationForceReturnToLoginPage()
	{
		$this->markTestSkipped("makes no sense");
	}

	public function testConfigurationFailDueToInvalidTransactionId()
	{
		// Act
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPApp');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));
		$oTOTP = new OTPService($oActiveSetting);
		$sCode = $oTOTP->GetCode();
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUrl($this->sMfaMyAccountConfigurationUri, [
			'transaction_id' => '753951',
			'totp_code' => $sCode,
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword,
			'Action' => "add:" . \MFAUserSettingsTOTPApp::class,
		]);

		// Assert
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPApp');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));
		$this->AssertStringContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page. ' . var_export($this->PrintQRStuff($oActiveSetting, $sOutput), true));
	}

	public function testConfigurationFailed()
	{
		// Act
		$sLogin = $this->oUser->Get('login');
		$sOutput = $this->CallItopUrl($this->sMfaMyAccountConfigurationUri, [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => 'Wrong Code',
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword,
			'Action' => "add:" . \MFAUserSettingsTOTPApp::class,
		]);

		// Assert
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPApp');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));
		$this->AssertStringContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page. ' . var_export($this->PrintQRStuff($oActiveSetting, $sOutput), true));
	}

	public function testConfigurationOK()
	{
		// Ask for configuration and generate UserSettings
		$sLogin = $this->oUser->Get('login');
		$oActiveSetting = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($this->oUser->GetKey(), 'MFAUserSettingsTOTPApp');
		$this->assertEquals('no', $oActiveSetting->Get('validated'));

		// Act
		$oTOTP = new OTPService($oActiveSetting);
		$sCode = $oTOTP->GetCode();
		$sOutput = $this->CallItopUrl($this->sMfaMyAccountConfigurationUri, [
			'transaction_id' => $this->GetNewGeneratedTransId($sLogin),
			'totp_code' => $sCode,
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword,
			'Action' => "add:" . \MFAUserSettingsTOTPApp::class,
		]);

		// Assert
		$oActiveSetting->Reload();
		$this->assertEquals('yes', $oActiveSetting->Get('validated'), var_export($this->PrintQRStuff($oActiveSetting, $sOutput), true));
		$this->AssertStringContains(Dict::S('MFATOTP:App:Config:Title'), $sOutput, 'The page should be the welcome page');
	}

	private function GetNewGeneratedTransId(string $sLogin) {
		\UserRights::Login($sLogin);
		$sTransId = \utils::GetNewTransactionId();
		\UserRights::_ResetSessionCache();

		return $sTransId;
	}
}
