<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
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
		$oUserSettings = MetaModel::NewObject($sModeClass, ['user_id' => $sUserId]);
		$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();

		$this->DisplayPage($aParams);
	}

}