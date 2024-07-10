<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\MyAccount\Hook\iMyAccountTabExtension;
use Dict;

class MyAccountTabExtension implements iMyAccountTabExtension
{

	public function IsTabPresent(): bool
	{
		return true;
	}

	public function GetTabCode(): string
	{
		return 'MyAccount:Tab:Security';
	}

	public function GetTabIsCached(): bool
	{
		return false;
	}

	public function GetTabLabel(): string
	{
		return Dict::S('MyAccount:Tab:Security');
	}

	public function GetTabRank(): float
	{
		return 20;
	}
}