<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\HybridAuth\Test\Provider\ServiceProviderMock;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Config;
use DateTime;
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
class MFABaseLoginExtensionIntegrationTest extends AbstractMFATest {
	//iTop called from outside
	//users need to be persisted in DB
	const USE_TRANSACTION = false;

	protected string $sConfigTmpBackupFile;
	protected Config $oiTopConfig;
	protected string $sPassword;
	protected User $oUser;

	protected function setUp(): void {
		parent::setUp();
		$this->RequireOnceItopFile('env-production/combodo-mfa-base/vendor/autoload.php');

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

	private function SaveItopConfFile(){
		@chmod($this->oiTopConfig->GetLoadedFile(), 0770);
		$this->oiTopConfig->WriteToFile();
		@chmod($this->oiTopConfig->GetLoadedFile(), 0440);
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


	protected function CallItopUrl($sUri, ?array $aPostFields=null, $bXDebugEnabled=false){
		$ch = curl_init();
		if ($bXDebugEnabled){
			curl_setopt($ch, CURLOPT_COOKIE, "XDEBUG_SESSION=phpstorm");
		}

		$sUrl = $this->oiTopConfig->Get('app_root_url') . "/$sUri";
		curl_setopt($ch, CURLOPT_URL, $sUrl);
		curl_setopt($ch, CURLOPT_POST, 1);// set post data to true
		curl_setopt($ch, CURLOPT_POSTFIELDS, $aPostFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$sOutput = curl_exec($ch);
		//\IssueLog::Info("$sUrl error code:", null, ['error' => curl_error($ch)]);
		curl_close ($ch);

		return $sOutput;
	}

	public function testDisplayWarningOnMFAActivation_MFAForceRuleInTheFuture() {
		$oForceActivateDatetimeInTheFuture = new DateTime("now + 1 day");
		$oRule = $this->CreateRule("rule", "MFAUserSettingsTOTPApp", "forced", [], [], 70);
		$sMFAActivationDate = $oForceActivateDatetimeInTheFuture->format('Y-m-d');
		$this->updateObject(MFAAdminRule::class, $oRule->GetKey(), ['forced_activation_date' => $sMFAActivationDate]);

		$sLogin = $this->oUser->Get('login');
		$aPostFields = [
			'auth_user' => $sLogin,
			'auth_pwd' => $this->sPassword,
		];
		$sOutput = $this->CallItopUrl("/pages/UI.php", $aPostFields);

		$sExpectedMessage = \Dict::Format('Login:MFA:UserWarningAboutMFAMode:Explain',
			"", //\MetaModel::GetName(get_class($oRule)),
			$sMFAActivationDate);
		var_export($sOutput);
		$this->assertTrue(false !== strpos($sOutput, $sExpectedMessage), "user should be connected and an intermediate warning MFA page is displayed with message : " . PHP_EOL . $sExpectedMessage . PHP_EOL . PHP_EOL . $sOutput);
	}

	public function MfaValidationWithSwitchLinksProvider(){
		return [
			'on mfa mode only' => [true],
			'further mfa modes + switch forms proposed' => [false],
		]	;
	}

	/**
	 * @dataProvider MfaValidationWithSwitchLinksProvider
	 */
	public function testLoginPage_MfaValidationWithSwitchLinks($bOneMfaModeConfigured=true) {
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");

		$oActiveSetting1 = $this->CreateSetting("MFAUserSettingsTOTPApp", $oUser->GetKey(), "yes", [], true);

		if ($bOneMfaModeConfigured) {
			$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsTOTPMail", $oUser->GetKey(), "yes", []);
			$oActiveSetting3 = $this->CreateSetting("MFAUserSettingsRecoveryCode", $oUser->GetKey(), "yes", []);
		}

		$sOutput = $this->CallItopUrl("/pages/UI.php",
			[ 'auth_user' => $oUser->Get('login'), 'auth_pwd' => $this->sPassword]);

		$this->assertNotNull($sOutput);
		var_dump($sOutput);

		$sMfaSwitchModePattern = '<form id="switch-form';
		if ($bOneMfaModeConfigured){
			$sNeedle = $sMfaSwitchModePattern;
			$this->assertFalse(strpos($sOutput, $sNeedle), "No switch MFA mode form in the login page" . PHP_EOL . PHP_EOL . $sOutput);
		} else {
			foreach (["MFAUserSettingsTOTPMail", "MFAUserSettingsRecoveryCode"] as $sMode){
				$sNeedle = "$sMfaSwitchModePattern-$sMode";
				$this->assertTrue(false !== strpos($sOutput, $sNeedle), "MFA mode switch form must be present ($sNeedle)" . PHP_EOL . PHP_EOL . $sOutput);
				$sNeedle = \Dict::Format("MFA:login:switch:label:$sMode");
				$this->assertTrue(false !== strpos($sOutput, $sNeedle), "MFA mode switch label must be present ($sNeedle)" . PHP_EOL . PHP_EOL . $sOutput);
			}
		}
	}
}
