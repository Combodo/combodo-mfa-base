<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Combodo\iTop\MFABase\Service\MFABaseService;

class MFABaseController extends Controller
{

	public function OperationConfigMFA(): void
	{
		$aParams = [];

		$aParams['aMethods'] = MFABaseService::GetInstance()->GetMFAMethods();

		$this->DisplayAjaxPage($aParams, 'ConfigMFA');
	}

	public function OperationAction(): void
	{
		$aParams = [];

		$this->OperationConfigMFA();
	}

}