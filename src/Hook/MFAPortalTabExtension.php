<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\Portal\Hook\iUserProfileTabExtension;
use Dict;

if (interface_exists(iUserProfileTabExtension::class)) {

	class MFAPortalTabExtension implements iUserProfileTabExtension
	{
		public function IsTabPresent(): bool
		{
			return MFABaseConfig::GetInstance()->IsEnabled();
		}

		public function GetTabCode(): string
		{
			return 'MyAccount-Tab-MFA';
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

}
