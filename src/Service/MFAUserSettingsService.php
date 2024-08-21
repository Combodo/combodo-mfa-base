<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

class MFAUserSettingsService
{
	private static MFAUserSettingsService $oInstance;

	protected function __construct()
	{
	}

	final public static function GetInstance(): MFAUserSettingsService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new static();
		}

		return static::$oInstance;
	}

}