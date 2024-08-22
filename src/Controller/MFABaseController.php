<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;

class MFABaseController extends Controller
{

	public function OperationAction(): void
	{
		$aParams = [];

		$this->OperationConfigMFA();
	}

}