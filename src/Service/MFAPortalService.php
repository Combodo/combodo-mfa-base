<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use MFAUserSettings;
use utils;

class MFAPortalService
{
	private static MFAPortalService $oInstance;

	protected function __construct()
	{
	}

	final public static function GetInstance(): MFAPortalService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new static();
		}

		return static::$oInstance;
	}

	public function IsUserSettingsConfigurationRequired(string $sUserSettingsClass): bool
	{

		$sClass = $this->GetClass();

		if ($sClass !== $sUserSettingsClass) {
			return false;
		}
		$sAction = $this->GetSelectedAction();

		return in_array($sAction, ['add', 'configure']);
	}

	public function GetSelectedAction(): string
	{
		return utils::ReadPostedParam('action', '', utils::ENUM_SANITIZATION_FILTER_CONTEXT_PARAM);
	}

	private function GetClass(): string
	{
		return utils::ReadPostedParam('class', '', utils::ENUM_SANITIZATION_FILTER_CONTEXT_PARAM);
	}
}