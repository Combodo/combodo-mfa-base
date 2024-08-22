<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\Application\Helper\Session;
use Combodo\iTop\MFABase\Service\MFABaseService;
use LoginWebPage;
use utils;

class LoginMFABase extends \AbstractLoginFSMExtension
{
	public function ListSupportedLoginModes()
	{
		return ['before'];
	}

	protected function OnCredentialsOK(&$iErrorCode)
	{
		$sViewFlag = utils::ReadPostedParam('view_flag');
		if (MFABaseService::GetInstance()->ValidateLogin(Session::Get('auth_user'), Session::Get('login_mode'), $sViewFlag)) {
			return LoginWebPage::LOGIN_FSM_CONTINUE;
		}

		$iErrorCode = LoginWebPage::EXIT_CODE_WRONGCREDENTIALS;

		return LoginWebPage::LOGIN_FSM_ERROR;
	}
}