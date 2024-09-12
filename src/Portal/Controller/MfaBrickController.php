<?php

namespace Combodo\iTop\MFABase\Portal\Controller;

use Combodo\iTop\Extension\SaaSSupportPortal\Brick\InstanceBrick;
use Combodo\iTop\MFABase\Helper\MFABaseHelper;
use Combodo\iTop\Portal\Controller\BrickController;
use Symfony\Component\HttpFoundation\Request;

class MfaBrickController extends BrickController {
	/**
	 * @param \Symfony\Component\HttpFoundation\Request $oRequest
	 * @param                                           $sBrickId
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @throws \Combodo\iTop\Portal\Brick\BrickNotFoundException
	 */
	public function DisplayAction(Request $oRequest)
	{
		$aData =[];
		return $this->render(MFABaseHelper::MODULE_NAME . '/templates/portal/brick.html.twig', $aData);
	}
}
