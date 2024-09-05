<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Helper;

use utils;

class MFABaseHelper
{
	const MODULE_NAME = 'combodo-mfa-base';

	private static MFABaseHelper $oInstance;

	private function __construct()
	{
	}

	final public static function GetInstance(): MFABaseHelper
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new MFABaseHelper();
		}

		return static::$oInstance;
	}

	public static function GetSCSSFile(): string
	{
		return 'env-'.utils::GetCurrentEnvironment().'/'.self::MODULE_NAME.'/assets/css/MFABase.scss';
	}

	public static function GetJSFile(): string
	{
		return utils::GetAbsoluteUrlModulesRoot().self::MODULE_NAME.'/assets/js/MFABase.js';
	}
}