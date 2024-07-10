<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Service;

use Combodo\iTop\Application\UI\Base\Component\Button\ButtonUIBlockFactory;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;
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
		$aUserMFAMethods = $this->GetUserMFAMethods();

		foreach ($aConfigMethods as $sUserMFAClass => $aConfigMethod) {
			if ($sUserMFAClass !== 'UserMFARecoveryCodes') {
				continue;
			}
			$aDatum = [];
			if ($aConfigMethod['active']) {
				$aDatum[] = MetaModel::GetName($sUserMFAClass);
				$aUserMFAMethod = $aUserMFAMethods[$sUserMFAClass] ?? null;
				if (!is_null($aUserMFAMethod)) {
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
		$aUserMFAMethods = $this->GetUserMFAMethods();

		foreach ($aConfigMethods as $sUserMFAClass => $aConfigMethod) {
			if ($sUserMFAClass === 'UserMFARecoveryCodes') {
				continue;
			}
			if ($aConfigMethod['active']) {
				$aDatum = [];
				// Name
				$aDatum[] = MetaModel::GetName($sUserMFAClass);
				// Status
				$aUserMFAMethod = $aUserMFAMethods[$sUserMFAClass] ?? null;
				if (!is_null($aUserMFAMethod)) {
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
					"$sDataAction:$sUserMFAClass",
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

	public function GetUserMFAMethods(): array
	{
		$oUser = UserRights::GetUserObject();
		$oSet = new DBObjectSet(DBSearch::FromOQL('SELECT UserMFA WHERE user_id = :id'), [], ['id' => $oUser->GetKey()]);
		$aConfiguredMethods = [];
		while ($oUserMFA = $oSet->Fetch()) {
			$aConfiguredMethods[$oUserMFA->Get('finalclass')] = $oUserMFA;
		}

		return $aConfiguredMethods;
	}

}