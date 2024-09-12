<?php

namespace Combodo\iTop\MFABase\Portal\Controller;

use Combodo\iTop\MFABase\Helper\MFABaseException;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Combodo\iTop\MFABase\Service\MFABaseService;
use Combodo\iTop\Portal\Controller\BrickController;
use Symfony\Component\HttpFoundation\Request;

class MfaBrickController extends BrickController {
	/**
	 * @param \Symfony\Component\HttpFoundation\Request $oRequest
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @throws \Combodo\iTop\Portal\Brick\BrickNotFoundException
	 */
	public function DisplayAction(Request $oRequest)
	{
		$aData =[];
		$aData['aMFAUserSettings'] = MFABaseService::GetInstance()->GetMFAUserSettingsDataTable();

		return $this->render(MFABaseHelper::MODULE_NAME . '/templates/portal/brick.html.twig', $aData);
	}
}
