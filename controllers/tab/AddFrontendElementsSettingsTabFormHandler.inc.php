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

	public $_endpoints;

	public function setupEndpoints() {
        $roles = array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER);
		$this->_endpoints = array(
			'POST' => array(
				array(
					'pattern' => '/{contextPath}/api/{version}/contexts/{contextId}/addFrontendElementsSettings',
					'handler' => [$this, 'saveFormData'],
					'roles' => $roles
				),
			)
		);
        return $this->_endpoints;
    }
	function saveFormData(... $functionArgs) {

		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$args = $request->_requestVars;
		$response =& $functionArgs[1];

		Services::get('context')->edit($context, $args, $request);

		return $response->withStatus(200);
	}
}

?>