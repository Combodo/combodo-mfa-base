<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\MyAccount\Hook\iMyAccountSectionExtension;
use utils;

class MyAccountSectionExtension implements iMyAccountSectionExtension
{

	/**
	 * @inheritDoc
	 */
	public function IsActive(): bool
	{
		return MFABaseConfig::GetInstance()->IsEnabled();
	}

	/**
	 * @inheritDoc
	 */
	public function GetTemplatePath(): string
	{
		return utils::GetAbsoluteModulePath(MFABaseHelper::MODULE_NAME).'templates';

	}

	/**
	 * @inheritDoc
	 */
	public function GetTabCode(): string
	{
		return 'MyAccount:Tab:MFA';
	}

	/**
	 * @inheritDoc
	 */
	public function GetTemplateName(): string
	{
		return 'ConfigMFA';
	}

	/**
	 * @inheritDoc
	 */
	public function GetSectionCallback(): callable
	{
		return [MFABaseService::GetInstance(), 'GetConfigMFAParams'];
	}

	/**
	 * @inheritDoc
	 */
	public function GetSectionRank(): float
	{
		return 0;
	}
}