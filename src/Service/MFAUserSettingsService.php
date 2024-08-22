<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use MFAUserSettings;
use MFAAdminRule;

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

	/**
	 * @param string $sUserId
	 *
	 * Return user settings by user. By default is first. Otherwise ordered by Admin rules rank.
	 * When no admin rules found for this user, all rules are optional.
	 *
	 * @return MFAUserSettings[]
	 */
	public function GetAllMFASettings(string $sUserId) : array {
		return [];
	}

	/**
	 * @param string $sUserId
	 *
	 * Return active user settings by user. By default is first. Otherwise ordered by Admin rules rank.
	 * When no admin rules found for this user, all rules are optional.
	 *
	 * @return MFAUserSettings[]
	 */
	public function GetActiveMFASettings(string $sUserId) : array {
		return [];
	}

	/**
	 * @param string $sUserId
	 *
	 * @return MFAAdminRule[]
	 */
	public function GetNotConfiguredMandatoryMFAAdminRules(string $sUserId) : array {
		return [];
	}
}
