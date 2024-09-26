<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use ApplicationMenu;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use ModuleHandlerAPI;
use OQLMenuNode;
use ResourceMFAMenu;
use UserRights;

class MFABaseMenu extends ModuleHandlerAPI
{

	public static function OnMenuCreation()
	{
		if (MFABaseConfig::GetInstance()->IsEnabled() && UserRights::IsActionAllowed(ResourceMFAMenu::class, UR_ACTION_MODIFY)) {
			new OQLMenuNode(
				'MFAAdminRule',
				"SELECT MFAAdminRule",
				ApplicationMenu::GetMenuIndexById('UserManagement'),
				40,
				true);
			new OQLMenuNode(
				'MFAUserSettings',
				"SELECT MFAUserSettings WHERE validated='yes'",
				ApplicationMenu::GetMenuIndexById('UserManagement'),
				50,
				true);
		}
	}
}