<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\MFABase\Controller\MFABaseMyAccountController;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;

require_once(APPROOT.'application/startup.inc.php');

$oController = new MFABaseMyAccountController(__DIR__.'/templates/my_account', MFABaseHelper::MODULE_NAME);
$oController->SetDefaultOperation('ConfigMFA');
$oController->HandleOperation();
