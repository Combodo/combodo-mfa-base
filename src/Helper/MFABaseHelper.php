<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Helper;

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
}