<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MFABase\View;


use Combodo\iTop\Application\Branding;
use Combodo\iTop\Application\TwigBase\Twig\Extension;
use Combodo\iTop\Application\WebPage\NiceWebPage;
use Combodo\iTop\MFABase\Helper\MFABaseUtils;
use Dict;
use LoginWebPage;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use utils;

/**
 * Used by MFA Base to display the MFA screen
 */
class MFATwigRenderer
{
	private $aLoginContext;
	private $oTwig;
	private array $aTwigLoaders;

	public function __construct()
	{
		$this->aTwigLoaders = [];
		$this->aLoginContext = [];
		$this->InitBaseTwig();
	}

	public function RegisterTwigLoaders($oLoginContext): void
	{
		$this->aLoginContext[] = $oLoginContext;
		$sTwigLoaderPath = $oLoginContext->GetTwigLoaderPath();
		if ($sTwigLoaderPath != null) {
			$oExtensionLoader = new FilesystemLoader();
			$oExtensionLoader->setPaths($sTwigLoaderPath);
			$this->aTwigLoaders[] = $oExtensionLoader;
		}
	}

	private function InitBaseTwig(): void
	{
		$aLoginPluginList = LoginWebPage::GetLoginPluginList('iLoginUIExtension', false);
		foreach ($aLoginPluginList as $oLoginPlugin) {
			/** @var \iLoginUIExtension $oLoginPlugin */
			$oLoginContext = $oLoginPlugin->GetTwigContext();
			if (is_null($oLoginContext)) {
				continue;
			}
			$this->RegisterTwigLoaders($oLoginContext);
		}
	}

	public function GetDefaultVars()
	{
		$sVersionShort = Dict::Format('UI:iTopVersion:Short', ITOP_APPLICATION, ITOP_VERSION);
		$sIconUrl = utils::GetConfig()->Get('app_icon_url');
		$sDisplayIcon = Branding::GetLoginLogoAbsoluteUrl();

		$aVars = [
			'sAppRootUrl' => utils::GetAbsoluteUrlAppRoot(),
			'aPluginFormData' => $this->GetLoginContext(),
			'sItopVersion' => ITOP_VERSION,
			'sVersionShort' => $sVersionShort,
			'sIconUrl' => $sIconUrl,
			'sDisplayIcon' => $sDisplayIcon,
		];

		return $aVars;
	}

	public function Render(NiceWebPage $oPage, $sTwigFile, $aVars = [])
	{
		$oMFABaseLoader = new FilesystemLoader([], APPROOT.'templates');
		$aMFABaseTemplatesPaths = ['pages/login', utils::GetAbsoluteModulePath(MFABaseUtils::MODULE_NAME).'templates/login'];
		$oMFABaseLoader->setPaths($aMFABaseTemplatesPaths);
		$this->aTwigLoaders[] = $oMFABaseLoader;

		$oLoader = new ChainLoader($this->aTwigLoaders);
		$this->oTwig = new Environment($oLoader);
		Extension::RegisterTwigExtensions($this->oTwig);

		$aVars = array_merge($this->GetDefaultVars(), $aVars);
		$oTemplate = $this->GetTwig()->load($sTwigFile);
		$oPage->add($oTemplate->renderBlock('body', $aVars));
		$oPage->add_script($oTemplate->renderBlock('script', $aVars));
		$oPage->add_ready_script($oTemplate->renderBlock('ready_script', $aVars));
		$oPage->add_style($oTemplate->renderBlock('css', $aVars));

		// Render CSS links
		foreach ($this->aLoginContext as $oLoginContext) {
			/** @var \LoginTwigContext $oLoginContext */
			$aCSSFiles = $oLoginContext->GetCSSFiles();
			foreach ($aCSSFiles as $sCSSFile) {
				$oPage->LinkStylesheetFromURI($sCSSFile);
			}
			$aJsFiles = $oLoginContext->GetJsFiles();
			foreach ($aJsFiles as $sJsFile) {
				$oPage->LinkScriptFromURI($sJsFile);

			}
		}
		$oPage->output();
	}

	/**
	 * @return array
	 */
	public function GetLoginContext()
	{
		return $this->aLoginContext;
	}

	/**
	 * @return \Twig\Environment
	 */
	public function GetTwig()
	{
		return $this->oTwig;
	}
}
