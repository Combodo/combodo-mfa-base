<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Combodo\iTop\MFABase\Helper\MFABaseLog;
use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Dict;
use Exception;
use MetaModel;
use UserRights;
use utils;

class MFABaseController extends Controller
{
	public function __construct($sViewPath = '', $sModuleName = 'core', $aAdditionalPaths = [])
	{
		MFABaseLog::Enable();
		parent::__construct($sViewPath, $sModuleName, $aAdditionalPaths);
	}

	public function OperationAction()
	{
		$aParams = [];
		$aParams['sURL'] = utils::GetAbsoluteUrlAppRoot().'pages/exec.php?exec_module=combodo-my-account&exec_page=index.php&exec_env=production#TwigBaseTabContainer=tab_MyAccountTabMFA';

		try {
			$sAction = utils::ReadPostedParam('Action', '', utils::ENUM_SANITIZATION_FILTER_CONTEXT_PARAM);
			$aItems = explode(':', $sAction);
			$sVerb = $aItems[0];
			$sModeClass = $aItems[1];
			$sUserId = UserRights::GetUserId();

			switch ($sVerb) {
				case 'undo_delete':
					$oUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sModeClass);
					$oUserSettings->Set('validated', 'yes');
					$oUserSettings->AllowWrite();
					$oUserSettings->DBUpdate();
					$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();
					break;

				case 'delete':
					$oUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sModeClass);
					$oUserSettings->Set('validated', 'no');
					$oUserSettings->Set('is_default', 'no');
					$oUserSettings->AllowWrite();
					$oUserSettings->DBUpdate();
					$aParams['sURL'] = utils::GetAbsoluteUrlAppRoot().'pages/exec.php?exec_module=combodo-my-account&exec_page=index.php&exec_env=production#TwigBaseTabContainer=tab_MyAccountTabMFA';
					break;

				case 'add':
					// Delete previously added mode
					$oUserSettings = MFAUserSettingsService::GetInstance()->GetMFAUserSettings($sUserId, $sModeClass);
					$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();
					$oUserSettings->AllowDelete();
					$oUserSettings->DBDelete();
					break;

				default:
					$oUserSettings = MetaModel::NewObject($sModeClass, ['user_id' => $sUserId]);
					$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();
					break;
			}
		} catch (Exception $e) {
			$sError = Dict::S('Failed to configure MFA Modes');
			MFABaseLog::Error($sError, null, ['Exception' => $e->getMessage(), 'Stack' => $e->getTraceAsString()]);
			$aParams['sError'] = $sError;
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

		$oUserSettings = MetaModel::NewObject($sClass, ['user_id' => $sUserId]);
		$aParams['sURL'] = $oUserSettings->GetConfigurationURLForMyAccountRedirection();

		$this->m_sOperation = 'Action';
		$this->DisplayPage($aParams);
	}
}