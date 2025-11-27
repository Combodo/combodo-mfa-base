<?php

namespace Combodo\iTop\MFABase\Test;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\MFABase\Helper\MFABaseException;
use Combodo\iTop\MFABase\Service\MFABaseLoginService;
use Config;
use MetaModel;

require_once __DIR__.'/AbstractMFATest.php';

class MFABaseServiceTest extends AbstractMFATest {
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/combodo-mfa-base/vendor/autoload.php');

		MetaModel::GetConfig()->SetModuleSetting('combodo-mfa-base', 'enabled', true);
	}

	/**
	 * @dataProvider ClearContextFailProvider
	 */
	public function testClearContextFail(string $sClass)
	{
		$this->expectException(MFABaseException::class);
		MFABaseLoginService::GetInstance()->ClearContext($sClass);
	}

	public function ClearContextFailProvider()
	{
		return [
			'NotADatamodelClass' => ['NotADatamodelClass'],
			'UserRequest' => ['UserRequest'],
			'MFAUserSettings' => [\MFAUserSettings::class],
		];
	}

	public function testClearContext_MFAUserSettingsWebAuthn() {
		if (! class_exists(\MFAUserSettingsWebAuthn::class)){
			$this->markTestSkipped("MFAUserSettingsWebAuthn does not exist");
		}

		$this->ClearContextValidate(\MFAUserSettingsWebAuthn::class, ['selected_mfa_mode', 'WebAuthnChallenge', 'mfa_webauthn_manual_validation']);
	}

	public function testClearContext_MFAUserSettingsTOTPApp() {
		if (! class_exists(\MFAUserSettingsTOTPApp::class)){
			$this->markTestSkipped("MFAUserSettingsTOTPApp does not exist");
		}

		$this->ClearContextValidate(\MFAUserSettingsTOTPApp::class, ['selected_mfa_mode']);
	}

	/**
	 * @dataProvider ClearContextProvider
	 */
	public function ClearContextValidate(string $sClass, array $aKeysToClear)
	{
		foreach ($aKeysToClear as $sKey) {
			Session::Set($sKey, 'Test');
		}
		MFABaseLoginService::GetInstance()->ClearContext($sClass);
		foreach ($aKeysToClear as $sKey) {
			$this->assertFalse(Session::IsSet($sKey), "The key $sKey should have been removed from session");
		}
	}
}
