<?php

namespace Combodo\iTop\MFABase\Test\Integration;

use Combodo\iTop\MFABase\Test\AbstractMFATest;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Dict;
use MetaModel;
use User;

require_once dirname(__DIR__) . "/AbstractMFATest.php";

/**
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 *
 */
class MFAMyAccountIntegrationTest extends AbstractMFATest {
	//iTop called from outside
	//users need to be persisted in DB
	const USE_TRANSACTION = false;

	protected string $sConfigTmpBackupFile;
	protected string $sPassword;
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


	public function testMyAccountMfaTab_DisplayMFATab()
	{
		$sOutput = $this->CallItopUrl('/pages/exec.php?exec_module=combodo-my-account&exec_page=index.php&exec_env=production#TwigBaseTabContainer=tab_MyAccountTabMFA', ['auth_user' => $this->oUser->Get('login'), 'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringContains(Dict::S('UI:MyAccount'), $sOutput, 'The page should display my account page title');
		$this->AssertStringContains(Dict::S('MyAccount:Tab:MFA'), $sOutput, 'The page should display MFA tab title');
	}

	public function testMyAccountMfaTab_DisplayMFATabContent()
	{
		// Arrange
		//$oActiveSetting1 = $this->CreateSetting('MFAUserSettingsTOTPApp', $this->oUser->GetKey(), 'yes', [], true);
		//$oActiveSetting2 = $this->CreateSetting('MFAUserSettingsTOTPMail', $this->oUser->GetKey(), 'yes', [], true);
		//$oActiveSetting3 = $this->CreateSetting(\MFAUserSettingsRecoveryCodes::class, $this->oUser->GetKey(), 'yes', [], true);

		// Act
		$sOutput = $this->CallItopUrl('/pages/exec.php?exec_module=combodo-my-account&exec_page=index.php&operation=MyAccountTab&exec_env=production&tab=MyAccount%3ATab%3AMFA',
			['auth_user' => $this->oUser->Get('login'), 'auth_pwd' => $this->sPassword]);

		// Assert
		$this->AssertStringContains(Dict::S('UI:MFA:Modes'), $sOutput, 'The page should display MFA tab content');
		$this->AssertStringContains(Dict::S('Class:MFAUserSettingsTOTPApp'), $sOutput, 'The page should display TOTP App line');
		$this->AssertStringContains(Dict::S('Class:MFAUserSettingsTOTPMail'), $sOutput, 'The page should display TOTP Mail line');
		$this->AssertStringContains(Dict::S('Class:MFAUserSettingsRecoveryCodes'), $sOutput, 'The page should display Recovery code line');
	}
}
