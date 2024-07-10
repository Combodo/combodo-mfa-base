<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\MFABase\Controller\MFABaseController;
use Combodo\iTop\MFABase\Helper\MFABaseUtils;

require_once(APPROOT.'application/startup.inc.php');

$oController = new MFABaseController(__DIR__.'/templates', MFABaseUtils::MODULE_NAME);
$oController->SetDefaultOperation('ConfigMFA');
$oController->HandleOperation();
