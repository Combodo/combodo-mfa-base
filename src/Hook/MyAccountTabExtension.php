<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Service\MFAAdminRuleService;
use Combodo\iTop\MyAccount\Hook\iMyAccountTabExtension;
use Dict;
use UserRights;

class MyAccountTabExtension implements iMyAccountTabExtension
{

	public function IsTabPresent(): bool
	{
		if (MFABaseConfig::GetInstance()->IsEnabled()) {
			$sUserId = UserRights::GetUserId();
			$oAdminRule = MFAAdminRuleService::GetInstance()->GetAdminRuleByUserId($sUserId);
			return is_null($oAdminRule) || ! $oAdminRule->IsDenied();
		}
		return false;
	}

	public function GetTabCode(): string
	{
		return 'MyAccount:Tab:MFA';
	}

	public function GetTabIsCached(): bool
	{
		return false;
	}

	public function GetTabLabel(): string
	{
		return Dict::S('MyAccount:Tab:MFA');
	}

	public function GetTabRank(): float
	{
		return 20;
	}
}