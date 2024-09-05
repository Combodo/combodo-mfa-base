<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use MetaModel;
use UserRights;
use utils;

class MFABaseController extends Controller
{

	public function OperationAction()
	{
		$aParams = [];

		$sAction = utils::ReadPostedParam('Action', '', utils::ENUM_SANITIZATION_FILTER_CONTEXT_PARAM);
		$aItems = explode(':', $sAction);
		$sVerb = $aItems[0];
		$sModeClass = $aItems[1];
		$sUserId = UserRights::GetUserId();

		if ($sVerb === 'delete') {
			$oUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sModeClass);
			$oUserSettings->AllowDelete();
			$oUserSettings->DBDelete();
			$aParams['sURL'] = utils::GetAbsoluteUrlAppRoot().'pages/exec.php?exec_module=combodo-my-account&exec_page=index.php&exec_env=production#TwigBaseTabContainer=tab_MyAccountTabMFA';
		} else {
			$oUserSettings = MetaModel::NewObject($sModeClass, ['user_id' => $sUserId]);
			$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();
		}

		$this->DisplayPage($aParams);
	}

	public function OperationSetAsDefaultMode()
	{
		// Ajax
		$aParams = [];

		$sClass = utils::ReadPostedParam('class', '', utils::ENUM_SANITIZATION_FILTER_CLASS);
		$sUserId = UserRights::GetUserId();

		MFABaseService::GetInstance()->SetAsDefaultMode($sUserId, $sClass);

		$aParams['code'] = 0;
		$sClassName = MetaModel::GetName($sClass);
		$aParams['message'] = \Dict::Format('UI:MFA:Modes:Default:Done', $sClassName);

		$this->DisplayJSONPage($aParams);
	}
}