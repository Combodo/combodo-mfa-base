<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\Application\UI\Base\Component\Button\ButtonUIBlockFactory;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseLog;
use Combodo\iTop\Renderer\BlockRenderer;
use DBObjectSet;
use DBSearch;
use Dict;
use MetaModel;
use UserRights;

class MFABaseService
{
	private static MFABaseService $oInstance;

	private function __construct()
	{
		MFABaseLog::Enable();
	}

	final public static function GetInstance(): MFABaseService
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new MFABaseService();
		}

		return static::$oInstance;
	}

	public function GetConfigMFAParams(): array
	{
		$aParams = [];

		$aParams['aMethods'] = $this->GetMFAUserSettings();
		$aParams['RecoveryOptionMethods'] = $this->GetRecoveryOptionMethods();

		return $aParams;
	}

	public function GetRecoveryOptionMethods(): array
	{
		$aColumns = [
			['label' => Dict::S('UI:MFA:Methods:Name')],
			['label' => Dict::S('UI:MFA:Methods:Status')],
			['label' => Dict::S('UI:MFA:Methods:Action')],
		];
		$aData = [];

		$aConfigMethods = MFABaseConfig::GetInstance()->GetMFAMethods();
		$aMFAUserSettingsMethods = $this->GetMFAUserSettingsModes();

		foreach ($aConfigMethods as $sMFAUserSettingsClass => $aConfigMethod) {
			if ($sMFAUserSettingsClass !== 'MFAUserSettingsRecoveryCodes') {
				continue;
			}
			$aDatum = [];
			if ($aConfigMethod['active']) {
				$aDatum[] = MetaModel::GetName($sMFAUserSettingsClass);
				$aMFAUserSettingsMethod = $aMFAUserSettingsMethods[$sMFAUserSettingsClass] ?? null;
				if (!is_null($aMFAUserSettingsMethod)) {
					$aDatum[] = Dict::S('UI:MFA:Methods:Status:Configured');
				} else {
					$aDatum[] = '';
				}
				$aDatum[] = '';
			}
			$aData[] = $aDatum;
		}

		if (empty($aData)) {
			return [];
		}

		return ['aColumns' => $aColumns, 'aData' => $aData];
	}

	public function GetMFAUserSettings(): array
	{

		$aColumns = [
			['label' => Dict::S('UI:MFA:Methods:Name')],
			['label' => Dict::S('UI:MFA:Methods:Status')],
			['label' => Dict::S('UI:MFA:Methods:Action')],
		];

		$aData = [];

		$aMFAUserSettingsMethods = $this->GetMFAUserSettingsModes();

		foreach ($aMFAUserSettingsMethods as $sMFAUserSettingsClass => $oMFAUserSettings) {
			$aDatum = [];
			// Name
			$aDatum[] = MetaModel::GetName($sMFAUserSettingsClass);
			// Status
			$sStatus = $oMFAUserSettings->Get('status');
			$aDatum[] = $sStatus;
			if ($sStatus !== 'not_configured') {
				$sActionLabel = Dict::S('UI:MFA:Methods:Action:Configure');
				$sActionTooltip = Dict::S('UI:MFA:Methods:Action:Configure:ButtonTooltip');
				$sDataAction = 'configure';
			} else {
				$sActionLabel = Dict::S('UI:MFA:Methods:Action:Add');
				$sActionTooltip = Dict::S('UI:MFA:Methods:Action:Add:ButtonTooltip');
				$sDataAction = 'add';
			}
			// Action
			$oButton = ButtonUIBlockFactory::MakeForSecondaryAction(
				$sActionLabel,
				'Action',
				"$sDataAction:$sMFAUserSettingsClass",
				true
			);
			$oButton->SetTooltip($sActionTooltip);
			$oRenderer = new BlockRenderer($oButton);
			$sButton = $oRenderer->RenderHtml();
			$aDatum[] = $sButton;
			$aData[] = $aDatum;
		}

		if (empty($aData)) {
			return [];
		}

		return ['aColumns' => $aColumns, 'aData' => $aData];
	}

	public function GetMFAUserSettingsModes(): array
	{
		$aModes = [];
		$oUser = UserRights::GetUserObject();
		$aMFAModes = MetaModel::EnumChildClasses('MFAUserSettings');
		foreach ($aMFAModes as $sModeClass) {
			if (MetaModel::IsAbstract($sModeClass)) {
				continue;
			}
			$oSet = new DBObjectSet(DBSearch::FromOQL("SELECT $sModeClass WHERE user_id = :id"), [], ['id' => $oUser->GetKey()]);
			$oMode = $oSet->Fetch() ??  MetaModel::NewObject($sModeClass, ['user_id' => $oUser->GetKey()]);
			$aModes[$sModeClass] = $oMode;
		}

		return $aModes;
	}

}