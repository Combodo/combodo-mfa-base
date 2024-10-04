<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Combodo\iTop\MFABase\Service\MFAPortalService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\Portal\Hook\iPortalTabContentExtension;
use Combodo\iTop\Portal\Twig\PortalBlockExtension;
use Combodo\iTop\Portal\Twig\PortalTwigContext;
use UserRights;

class MFAPortalTabContentExtension implements iPortalTabContentExtension
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

	/**
	 * Handle actions based on posted vars
	 */
	public function HandlePortalForm(array &$aData): void
	{
		$sVerb = MFAPortalService::GetInstance()->GetSelectedAction();
		$sUserSettingsClass = MFAPortalService::GetInstance()->GetClass();
		if (strlen($sUserSettingsClass) != 0){
			$sUserId = UserRights::GetUserId();
			MFAUserSettingsService::GetInstance()->HandleAction($sUserId, $sUserSettingsClass, $sVerb);
		}
	}

	/**
	 * List twigs and variables for the tab content per block
	 *
	 * @return PortalTwigContext
	 */
	public function GetPortalTabContentTwigs(): PortalTwigContext
	{
		$oPortalTwigContext = new PortalTwigContext();
		$sPath = MFABaseHelper::MODULE_NAME.'/templates/portal/UserSettingsList.html.twig';

		$aData = ['aUserSettings' => MFAUserSettingsService::GetInstance()->GetMFAUserSettingsTableWithActions()];

		$oPortalTwigContext->AddBlockExtension('html', new PortalBlockExtension($sPath, $aData));

		return $oPortalTwigContext;
	}
}
