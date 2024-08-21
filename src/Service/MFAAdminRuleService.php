<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

class MFAAdminRuleService
{
	private static MFAAdminRuleService $oInstance;

	protected function __construct()
	{
	}

	final public static function GetInstance(): MFAAdminRuleService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new static();
		}

		return static::$oInstance;
	}

}