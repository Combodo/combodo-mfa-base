<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Helper;

use IssueLog;
use LogAPI;

class MFABaseLog extends LogAPI
{
	const CHANNEL_DEFAULT = 'MFABaseLog';
	protected static $m_oFileLog = null;


	public static function Enable($sTargetFile = null)
	{
		if (empty($sTargetFile))
		{
			$sTargetFile = APPROOT.'log/MFABase.log';
		}
		parent::Enable($sTargetFile);
	}

	public static function Error($sMessage, $sChannel = null, $aContext = [])
	{
		IssueLog::Error($sMessage, self::CHANNEL_DEFAULT, $aContext);
		parent::Error($sMessage, self::CHANNEL_DEFAULT, $aContext);
	}

	public static function Info($sMessage, $sChannel = null, $aContext = [])
	{
		parent::Info($sMessage, self::CHANNEL_DEFAULT, $aContext);
	}

}
