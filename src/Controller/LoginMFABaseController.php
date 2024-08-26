<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Combodo\iTop\MFABase\Page\LoginMFABaseWebPage;
use MFAAdminRule;
use utils;

class LoginMFABaseController extends Controller
{
	public function DisplayMfaLoginPage(MFAAdminRule $oRule)
	{
		$aParams['sMFAActivationDate'] = $oRule->Get('activation_date');
		$aParams['sResetLoginURL'] = utils::GetAbsoluteUrlAppRoot().'pages/logoff.php?operation=do_logoff';

		$oPage = new LoginMFABaseWebPage();
		//$oPage->add_saas(AuthentTwoFactorHelper::GetSCSSFile());
		$oPage->DisplayLoginPage($aParams, 'UserWarningAboutMissingMFAMode');
		$oPage->output();
	}

	public function DisplayUserWarningAboutMissingMFAMode(MFAAdminRule $oRule)
	{
		$aParams['sMFAActivationDate'] = $oRule->Get('forced_activation_date');
		$aParams['sMyAccountMenuLink'] = utils::GetAbsoluteUrlModulePage("combodo-my-account",'index.php') . "#TwigBaseTabContainer=tab_MyAccountTabMFA";

		$oPage = new LoginMFABaseWebPage();
		//$oPage->add_saas(AuthentTwoFactorHelper::GetSCSSFile());
		$oPage->DisplayLoginPage($aParams, 'UserWarningAboutMissingMFAMode');
		$oPage->output();
		exit();
	}

}
