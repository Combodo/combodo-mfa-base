<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Helper;

class MFABaseUtils
{
	const MODULE_NAME = 'combodo-mfa-light';

	private static MFABaseUtils $oInstance;

	private function __construct()
	{
	}

	final public static function GetInstance(): MFABaseUtils
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new MFABaseUtils();
		}

		return static::$oInstance;
	}
}