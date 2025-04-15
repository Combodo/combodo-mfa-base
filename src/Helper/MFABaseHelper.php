<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\Helper;

use Dict;
use utils;

class MFABaseHelper
{
	const MODULE_NAME = 'combodo-mfa-base';

	private static MFABaseHelper $oInstance;

	private function __construct()
	{
	}

	final public static function GetInstance(): MFABaseHelper
	{
		if (!isset(static::$oInstance)) {
			static::$oInstance = new MFABaseHelper();
		}

		return static::$oInstance;
	}

	public static function GetSCSSFile(): string
	{
		return 'env-'.utils::GetCurrentEnvironment().'/'.self::MODULE_NAME.'/assets/css/MFABase.scss';
	}

	public static function GetJSFile(): string
	{
		return utils::GetAbsoluteUrlModulesRoot().self::MODULE_NAME.'/assets/js/MFABase.js';
	}

	/**
	 * @return void
	 * @throws \Combodo\iTop\MFABase\Helper\MFABaseException
	 */
	public function ValidateTransactionId(): void {
		if (empty($_POST)){
			return;
		}

		$sTransactionId = utils::ReadPostedParam('transaction_id', null, utils::ENUM_SANITIZATION_FILTER_TRANSACTION_ID);
		MFABaseLog::Debug(__FUNCTION__.": Transaction [$sTransactionId]");
		if (empty($sTransactionId) || !utils::IsTransactionValid($sTransactionId, false)) {
			throw new MFABaseException(Dict::S("iTopUpdate:Error:InvalidToken"));
		}
	}

	public function PassPostedParams(array &$aParams, string $sPostedParamVarname='aPostedParams')
	{
		$aPostParams=[];
		foreach ($_POST as $sPostedKey => $postedValue){
			if (is_array($postedValue))
			{
				\IssueLog::Error(__METHOD__, null, $postedValue);
				foreach($postedValue as $sKey => $sValue)
				{
					$sName = "{$sPostedKey}[{$sKey}]";
					$aPostParams[$sName] = $sValue;
				}
			}
			else
			{
				$aPostParams[$sPostedKey] = $postedValue;
			}
		}

		$aParams[$sPostedParamVarname]=$aPostParams;
	}
}
