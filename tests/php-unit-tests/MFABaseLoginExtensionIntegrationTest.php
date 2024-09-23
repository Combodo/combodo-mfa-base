<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\AuthentToken\Helper\TokenAuthHelper;
use Combodo\iTop\AuthentToken\Hook\TokenLoginExtension;
use Combodo\iTop\HybridAuth\Test\Provider\ServiceProviderMock;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
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
		$oUser = $this->CreateContactlessUser("NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], $this->sPassword);

		$oActiveSetting1 = $this->CreateSetting("MFAUserSettingsTOTPApp", $oUser->GetKey(), "yes", [], true);

		if (!$bOneMfaModeConfigured) {
			$oActiveSetting2 = $this->CreateSetting("MFAUserSettingsTOTPMail", $oUser->GetKey(), "yes", []);
			$oActiveSetting3 = $this->CreateSetting("MFAUserSettingsRecoveryCodes", $oUser->GetKey(), "yes", []);
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
			foreach (["MFAUserSettingsTOTPMail", "MFAUserSettingsRecoveryCodes"] as $sMode){
				$sNeedle = "$sMfaSwitchModePattern-$sMode";
				$this->assertTrue(false !== strpos($sOutput, $sNeedle), "MFA mode switch form must be present ($sNeedle)" . PHP_EOL . PHP_EOL . $sOutput);
				$sNeedle = \Dict::Format("MFA:login:switch:label:$sMode");
				$this->assertTrue(false !== strpos($sOutput, $sNeedle), "MFA mode switch label must be present ($sNeedle)" . PHP_EOL . PHP_EOL . $sOutput);
			}
		}
	}

	public function testRestApiWithMfa() {
		MetaModel::GetConfig()->Set('secure_rest_services', true, 'auth-token');
		MetaModel::GetConfig()->Set('allow_rest_services_via_tokens', true, 'auth-token');
		MetaModel::GetConfig()->SetModuleSetting(TokenAuthHelper::MODULE_NAME, 'personal_tokens_allowed_profiles', ['Administrator', 'Service Desk Agent']);
		$this->InitLoginMode(TokenLoginExtension::LOGIN_TYPE);

		$oUser = $this->CreateContactlessUser("NoOrgAdminUser", ItopDataTestCase::$aURP_Profiles['Administrator'], $this->sPassword);
		$oPersonalToken = $this->createObject(\PersonalToken::class, [
			'user_id' => $oUser->GetKey(),
			'application' => "token application",
			'scope' => \ContextTag::TAG_REST
		]);

		$oReflectionClass = new \ReflectionClass(\AbstractPersonalToken::class);
		$oProperty = $oReflectionClass->getProperty('sToken');
		$oProperty->setAccessible(true);
		$sTokenCredential = $oProperty->getValue($oPersonalToken);

		$sJsonRequest = <<<QUERY
{
    "operation": "core/get",
    "class": "UserRequest",
    "key": "SELECT UserRequest",
    "output_fields": "operational_status,ref,org_id,org_name,caller_id",
    "limit": "1",
    "page": "1"
}
QUERY;

		$sOutput = $this->CallItopUrl("/webservices/rest.php",
			[ 'auth_token' => $sTokenCredential, 'json_data' => $sJsonRequest, 'version' => '1.3']);

		$this->assertTrue(false !== strpos($sOutput, "\"code\":0"), "API Call successfull");

	}

	protected function InitLoginMode($sLoginMode)
	{
		$aAllowedLoginTypes = MetaModel::GetConfig()->GetAllowedLoginTypes();
		if (!in_array($sLoginMode, $aAllowedLoginTypes)) {
			$aAllowedLoginTypes[] = $sLoginMode;
			MetaModel::GetConfig()->SetAllowedLoginTypes($aAllowedLoginTypes);
			$sConfigFile = APPROOT.'conf/'.\utils::GetCurrentEnvironment().'/config-itop.php';
			@chmod($sConfigFile, 0770); // Allow overwriting the file
			MetaModel::GetConfig()->WriteToFile();
		}
	}
}
