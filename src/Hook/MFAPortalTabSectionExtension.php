<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\Portal\Hook\iPortalTabSectionExtension;
use Combodo\iTop\Portal\Twig\PortalBlockExtension;
use Combodo\iTop\Portal\Twig\PortalTwigContext;

class MFAPortalTabSectionExtension implements iPortalTabSectionExtension
{

	/**
	 * @inheritDoc
	 */
	public function IsActive(): bool
	{
		return MFABaseConfig::GetInstance()->IsEnabled();
	}

	/**
	 * @inheritDoc
	 */
	public function GetTabCode(): string
	{
		return 'MyAccount-Tab-MFA';
	}

	/**
	 * @inheritDoc
	 */
	public function GetSectionRank(): float
	{
		return 0;
	}

	public function GetTarget(): string
	{
		return 'p_user_profile_brick';
	}

	public function GetPortalTwigContext(): PortalTwigContext
	{
		$oPortalTwigContext = new PortalTwigContext();
		$sPath = MFABaseHelper::MODULE_NAME.'/templates/portal/UserSettingsList.html.twig';

		$aMFAParams = MFABaseService::GetInstance()->GetMFAUserSettingsDataTable();

		foreach($aMFAParams['aData'] as $iRow =>$aRow) {
			$aActions = $aRow['action'];

			$sButtonToolbar = '';
			foreach ($aActions as $aAction) {
				$sIconClass = $aAction[0];
				$sTooltip = $aAction[1];
				$sValue = $aAction[2];
				$sCSSClass = $aAction[3] ?? null;

				$sButtonToolbar .= <<<HTML
<a href="">
	<span class='$sIconClass' data-tooltip-content='$sTooltip'></span>
</a>
HTML;
			}

			$aMFAParams['aData'][$iRow]['action'] = $sButtonToolbar;
		}

		$aData = ['aUserSettings' => $aMFAParams];

		$oPortalTwigContext->AddBlockExtension('html', new PortalBlockExtension($sPath, $aData));

		return $oPortalTwigContext;
	}
}
