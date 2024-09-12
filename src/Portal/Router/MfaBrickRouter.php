<?php

namespace Combodo\iTop\MFABase\Portal\Router;

use Combodo\iTop\Portal\Routing\ItopExtensionsExtraRoutes;

/** @noinspection PhpUnhandledExceptionInspection */
ItopExtensionsExtraRoutes::AddControllersClasses(
	array(
		'Combodo\\iTop\\MFABase\\Portal\\Controller\\MfaBrickController',
	)
);

/** @noinspection PhpUnhandledExceptionInspection */
ItopExtensionsExtraRoutes::AddRoutes(array(
    array(
        'pattern' => '/mfa_brick',
        'callback' => 'Combodo\\iTop\\MFABase\\Portal\\Controller\\MfaBrickController::DisplayAction',
        'bind' => 'p_mfa_brick'
    ),
));
