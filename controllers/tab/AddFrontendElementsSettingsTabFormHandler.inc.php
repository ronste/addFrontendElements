<?php

/**
 * @file plugins/generic/addFrontendElements/controllers/tab/AddFrontendElementsSettingsTabFormHandler.inc.php
 *
 * Copyright (c) 2021 Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief File implemeting the addFrontendElements settings tab handler.
 */

import('pages/management/SettingsHandler');
import('lib.pkp.classes.validation.ValidatorFactory');

/**
 * @class AddFrontendElementsSettingsTabFormHandler
 * @brief Class implemeting the addFrontendElements settings tab handler.
 */
class AddFrontendElementsSettingsTabFormHandler extends SettingsHandler {

	function saveFormData(... $functionArgs) {

		$request = Application::getRequest();
		$context = $request->getContext();
		$args = $request->_requestVars;
		$response =& $functionArgs[1];

		$context->updateSetting('articleDetailsPageSettings', $args['articleDetailsPageSettings']);

		return $response->withStatus(200);
	}
}

?>