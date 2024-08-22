<?php
/**
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Page;

use Combodo\iTop\MFABase\Helper\MFABaseUtils;
use LoginTwigRenderer;
use LoginWebPage;
use Twig\Loader\FilesystemLoader;
use utils;

/**
 * Allow to use LoginWebPage for additional 2FA pages
 */
class LoginMFABaseWebPage extends LoginWebPage
{
	protected static $sHandlerClass = __class__;

	public function DisplayLoginPage(array $aParams, string $sTemplateName)
	{
		$oTwigContext = new LoginTwigRenderer();
		/** @var \Twig\Loader\ChainLoader $oTwigLoader */
		$oTwigLoader = $oTwigContext->GetTwig()->getLoader();
		$oTwigLoader->addLoader(new FilesystemLoader(utils::GetAbsoluteModulePath(MFABaseUtils::MODULE_NAME).'templates/login'));
		$aVars = array_merge($oTwigContext->GetDefaultVars(), $aParams);

		$oTwigContext->Render($this, "$sTemplateName.html.twig", $aVars);
	}
}