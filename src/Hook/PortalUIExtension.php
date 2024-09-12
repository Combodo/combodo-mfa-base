<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use AbstractPortalUIExtension;
use Combodo\iTop\Application\TwigBase\Twig\TwigHelper;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Symfony\Component\DependencyInjection\Container;
use utils;

class PortalUIExtension extends AbstractPortalUIExtension
{
	public function GetJSInline(Container $oContainer)
	{
		if (!MFABaseConfig::GetInstance()->IsEnabled()) {
			return null;
		}


		/** @var \Combodo\iTop\Portal\Routing\UrlGenerator $oUrlGenerator */
		$oUrlGenerator = $oContainer->get('url_generator');
		$sURL = $oUrlGenerator->generate('p_user_profile_brick');
		$oTWIGEnvironment = TwigHelper::GetTwigEnvironment(utils::GetAbsoluteModulePath(MFABaseHelper::MODULE_NAME) . 'templates/portal/');
		$sHTML = $oTWIGEnvironment->render('MenuLink.html.twig', ['sURL' => $sURL]);

		return <<<JS
$(document).ready(function(){
	$(".user_options").prepend('$sHTML');
});
JS;
	}

}