<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Helper;

use Exception;
use Throwable;

class MFABaseException extends Exception
{
	public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
	{
		if (!is_null($previous)) {
			$sStack = $previous->getTraceAsString();
		} else {
			$e = new Exception('');
			$sStack = $e->getTraceAsString();
		}
		MFABaseLog::Error($message, null, ['stack' => $sStack]);
		parent::__construct($message, $code, $previous);
	}
}