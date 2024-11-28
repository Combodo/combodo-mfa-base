<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Hook;

use AbstractWelcomePopupExtension;
use Combodo\iTop\Application\WelcomePopup\MessageFactory;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Dict;
use UserRights;
use utils;

class MFAWelcomePopupExtension extends AbstractWelcomePopupExtension
{

	/**
	 * @inheritDoc
	 */
	public function GetIconRelPath(): string
	{
		return 'env-'.utils::GetCurrentEnvironment().'/'.MFABaseHelper::MODULE_NAME.'/assets/img/illustrations/thumbnail_square.jpg';
	}
	
	/**
	 * @inheritDoc
	 */
	public function GetMessages(): array
	{
		$aMessages = [];
		if (UserRights::IsAdministrator()) {
			if (class_exists('MFAUserSettingsWebAuthn')) {
				// MFA Extended
				$aMessages[] = MessageFactory::MakeForLeftIllustrationAsSVGMarkupRightTexts(
					'320_06_MFA',
					Dict::S('UI:WelcomePopup:Message:320_06_MFA:Title'),
					Dict::Format('UI:WelcomePopup:Message:320_06_MFA:Description', 'https://www.itophub.io/wiki/page?id=extensions:combodo-mfa-extended'),
					utils::GetAbsoluteUrlModulesRoot().MFABaseHelper::MODULE_NAME.'/assets/img/illustrations/undraw_two_factor_authentication.svg'
				);
			} else {
				$aMessages[] = MessageFactory::MakeForLeftIllustrationAsSVGMarkupRightTexts(
					'320_06_MFA',
					Dict::S('UI:WelcomePopup:Message:320_06_MFA:Title'),
					Dict::Format('UI:WelcomePopup:Message:320_06_MFA:Description', 'https://www.itophub.io/wiki/page?id=extensions:combodo-mfa-light'),
					utils::GetAbsoluteUrlModulesRoot().MFABaseHelper::MODULE_NAME.'/assets/img/illustrations/undraw_two_factor_authentication.svg'
				);
			}
		}
		return $aMessages;
	}
}