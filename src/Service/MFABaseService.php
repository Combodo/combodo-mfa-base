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

		$aParams['aMethods'] = $this->GetMFAMethods();
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
		$aMFAUserSettingsMethods = $this->GetMFAUserSettingsMethods();

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

	public function GetMFAMethods(): array
	{

		$aColumns = [
			['label' => Dict::S('UI:MFA:Methods:Name')],
			['label' => Dict::S('UI:MFA:Methods:Status')],
			['label' => Dict::S('UI:MFA:Methods:Action')],
		];

		$aData = [];

		$aConfigMethods = MFABaseConfig::GetInstance()->GetMFAMethods();
		$aMFAUserSettingsMethods = $this->GetMFAUserSettingsMethods();

		foreach ($aConfigMethods as $sMFAUserSettingsClass => $aConfigMethod) {
			if ($sMFAUserSettingsClass === 'MFAUserSettingsRecoveryCodes') {
				continue;
			}
			if ($aConfigMethod['active']) {
				$aDatum = [];
				// Name
				$aDatum[] = MetaModel::GetName($sMFAUserSettingsClass);
				// Status
				$aMFAUserSettingsMethod = $aMFAUserSettingsMethods[$sMFAUserSettingsClass] ?? null;
				if (!is_null($aMFAUserSettingsMethod)) {
					$aDatum[] = Dict::S('UI:MFA:Methods:Status:Configured');
					$sActionLabel = Dict::S('UI:MFA:Methods:Action:Configure');
					$sActionTooltip = Dict::S('UI:MFA:Methods:Action:Configure:ButtonTooltip');
					$sDataAction = 'configure';
				} else {
					$aDatum[] = '';
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
		}

		if (empty($aData)) {
			return [];
		}

		return ['aColumns' => $aColumns, 'aData' => $aData];
	}

	public function GetMFAUserSettingsMethods(): array
	{
		$oUser = UserRights::GetUserObject();
		$oSet = new DBObjectSet(DBSearch::FromOQL('SELECT MFAUserSettings WHERE user_id = :id'), [], ['id' => $oUser->GetKey()]);
		$aConfiguredMethods = [];
		while ($oMFAUserSettings = $oSet->Fetch()) {
			$aConfiguredMethods[$oMFAUserSettings->Get('finalclass')] = $oMFAUserSettings;
		}

		return $aConfiguredMethods;
	}

}