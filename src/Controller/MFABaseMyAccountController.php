<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Combodo\iTop\MFABase\Helper\MFABaseLog;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Dict;
use Exception;
use UserRights;
use utils;

class MFABaseMyAccountController extends Controller
{
	public function __construct($sViewPath = '', $sModuleName = 'core', $aAdditionalPaths = [])
	{
		MFABaseLog::Enable();
		parent::__construct($sViewPath, $sModuleName, $aAdditionalPaths);
	}

	public function OperationAction()
	{
		$aParams = [];

		try {
			$aParams['sURL'] = utils::GetAbsoluteUrlAppRoot().'pages/exec.php?exec_module=combodo-my-account&exec_page=index.php&exec_env=production#TwigBaseTabContainer=tab_MyAccountTabMFA';
			$sAction = utils::ReadPostedParam('Action', '', utils::ENUM_SANITIZATION_FILTER_CONTEXT_PARAM);
			$aItems = explode(':', $sAction);
			$sVerb = $aItems[0];
			$sModeClass = $aItems[1];
			$sUserId = UserRights::GetUserId();

			MFAUserSettingsService::GetInstance()->HandleAction($sUserId, $sModeClass, $sVerb, $aParams);
		} catch (Exception $e) {
			MFABaseLog::Error(__FUNCTION__.' Failed to configure MFA Modes', null, ['error' => $e->getMessage(), 'stack' => $e->getTraceAsString()]);
			$aParams['sError'] = Dict::S('UI:MFA:Error:FailedToConfigure');
		}

		$this->DisplayPage($aParams);
	}
}
