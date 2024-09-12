<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use AbstractPortalUIExtension;
use Symfony\Component\DependencyInjection\Container;

class PortalUIExtension extends AbstractPortalUIExtension
{
	public function GetJSInline(Container $oContainer)
	{
		return <<<JS
$(document).ready(function(){
	
});
JS;
	}

}