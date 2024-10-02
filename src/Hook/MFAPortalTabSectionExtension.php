<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\MFABase\Service\MFAPortalService;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\Portal\Hook\iPortalTabSectionExtension;
use Combodo\iTop\Portal\Twig\PortalBlockExtension;
use Combodo\iTop\Portal\Twig\PortalTwigContext;
use UserRights;

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

		$sVerb = MFAPortalService::GetInstance()->GetSelectedAction();
		$sUserSettingsClass = MFAPortalService::GetInstance()->GetClass();
		if (strlen($sUserSettingsClass) != 0){
			$sUserId = UserRights::GetUserId();
			MFAUserSettingsService::GetInstance()->HandleAction($sUserId, $sUserSettingsClass, $sVerb);
		}

		$aData = ['aUserSettings' => MFABaseService::GetInstance()->GetMFAUserSettingsDataTable()];

		$oPortalTwigContext->AddBlockExtension('html', new PortalBlockExtension($sPath, $aData));

		return $oPortalTwigContext;
	}
}
