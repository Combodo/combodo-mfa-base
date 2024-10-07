<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use Combodo\iTop\Application\UI\Base\Component\Button\ButtonUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\Toolbar\ToolbarUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Layout\UIContentBlockUIBlockFactory;
use Combodo\iTop\MFABase\Helper\MFABaseConfig;
use Combodo\iTop\MFABase\Helper\MFABaseException;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Combodo\iTop\MFABase\Service\MFAUserSettingsService;
use Combodo\iTop\MyAccount\Hook\iMyAccountTabContentExtension;
use Combodo\iTop\Renderer\BlockRenderer;
use Exception;
use utils;

class MyAccountTabContentExtension implements iMyAccountTabContentExtension
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
	public function GetTemplatePath(): string
	{
		return utils::GetAbsoluteModulePath(MFABaseHelper::MODULE_NAME).'templates/my_account';

	}

	/**
	 * @inheritDoc
	 */
	public function GetTabCode(): string
	{
		return 'MyAccount:Tab:MFA';
	}

	/**
	 * @inheritDoc
	 */
	public function GetTemplateName(): string
	{
		return 'ConfigMFA';
	}

	/**
	 * @inheritDoc
	 */
	public function GetSectionCallback(): callable
	{
		return [$this, 'GetConfigMFAParams'];
	}

	/**
	 * @inheritDoc
	 */
	public function GetSectionRank(): float
	{
		return 0;
	}

	public function GetConfigMFAParams(): array
	{
		try {
			$aParams = [];
			$aMFAParams = MFAUserSettingsService::GetInstance()->GetMFAUserSettingsTableWithActions();

			foreach($aMFAParams['aData'] as $iRow =>$aRow) {
				$aActions = $aRow['action'];
				$sTooltip = $aRow['name']['tooltip'];
				$sName = $aRow['name']['label'];
				$oUIBlock = UIContentBlockUIBlockFactory::MakeStandard();
				$oUIBlock->AddHtml('<span data-tooltip-content="'.$sTooltip.'" data-tooltip-placement="right">'.$sName.'</span>');
				$oRenderer = new BlockRenderer($oUIBlock);
				$aMFAParams['aData'][$iRow]['name'] = $oRenderer->RenderHtml();

				$oButtonToolbar = ToolbarUIBlockFactory::MakeStandard();
				foreach ($aActions as $aAction) {
					$sIconClass = $aAction[0];
					$sTooltip = $aAction[1];
					$sAction = $aAction[2];
					$sClass = $aAction[3];
					$sCSSClass = $aAction[4] ?? null;
					$oButton = ButtonUIBlockFactory::MakeIconAction($sIconClass,
						$sTooltip,
						'Action',
						"$sAction:$sClass",
						true
					);
					$oButton->SetTooltip($sTooltip);
					if (!is_null($sCSSClass)) {
						$oButton->AddCSSClass($sCSSClass);
						$oButton->RemoveCSSClass('ibo-is-neutral');
					}
					$oButtonToolbar->AddSubBlock($oButton);
				}
				$oRenderer = new BlockRenderer($oButtonToolbar);
				$sButtonToolbar = $oRenderer->RenderHtml();

				$aMFAParams['aData'][$iRow]['action'] = $sButtonToolbar;
			}

			$aParams['aMFAUserSettings'] = $aMFAParams;
			$aParams['sTransactionId'] = utils::GetNewTransactionId();

			return $aParams;
		} catch (Exception $e) {
			throw new MFABaseException(__FUNCTION__.' failed', 0, $e);
		}
	}
}