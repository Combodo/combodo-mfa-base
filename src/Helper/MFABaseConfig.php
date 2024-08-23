<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Helper;

use MetaModel;

class MFABaseConfig
{
	private static MFABaseConfig $oInstance;

	private function __construct()
	{
	}

	final public static function GetInstance(): MFABaseConfig
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new MFABaseConfig();
		}

		return static::$oInstance;
	}

	public function Get(string $sParamName, $default = null)
	{
		return MetaModel::GetConfig()->GetModuleSetting(MFABaseUtils::MODULE_NAME, $sParamName, $default);
	}

	public function GetBoolean(string $sParamName, $default = null): bool
	{
		$res = $this->Get($sParamName, $default);

		return boolval($res);
	}

	public function IsEnabled(): bool
	{
		return $this->GetBoolean('enabled', false);
	}

	public function IsDebugEnabled(): bool
	{
		return $this->GetBoolean('debug', false);
	}

	public function GetMFAMethods(): array
	{
		return MetaModel::GetModuleSetting(MFABaseUtils::MODULE_NAME, 'methods', []);
	}

	public function GetMFALoginModes(): array
	{
		return MetaModel::GetModuleSetting(MFABaseUtils::MODULE_NAME, 'allowed-login-types', ['form', 'basic', 'url', 'external']);
	}

}