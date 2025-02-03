<?php

/**
 * @file plugins/generic/addFrontendElements/controllers/tab/AddFrontendElementsPublicationTabFormHandler.inc.php
 *
 * Copyright (c) 2021 Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief File implemeting the addFrontendElements Publication tab handler.
 */

/**
 * @class AddFrontendElementsPublicationTabFormHandler
 * @brief Class implemeting the addFrontendElements Publication tab handler.
 */

 import('pages/management/SettingsHandler');
 
class AddFrontendElementsPublicationTabFormHandler extends SettingsHandler {

	public $_endpoints;

	public $_handlerPath;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'addFrontendElements';
		parent::__construct();
	}

    public function setupEndpoints() {
        $roles = array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER);
		$this->_endpoints = array(
			'GET' => array (
				array(
					'pattern' => $this->getEndpointPattern().'/vocabs',
					'handler' => array($this, 'getControlledVocab'),
					'roles' => $roles
				),
			),
			'POST' => array(
				array(
					'pattern' => $this->getEndpointPattern().'/{submission_id}/{publication_id}',
					'handler' => array($this, 'saveFormData'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern(),
					'handler' => array($this, 'saveFormData'),
					'roles' => $roles
				),
			)
		);
        return $this->_endpoints;
    }

    // As a plugin we need to overwrite this because the API router otherwise searches our handler file in the api folder
    function getEndpointPattern() {
		$context = Application::get()->getRequest()->getContext();
        return '/{contextPath}/api/{version}/contexts/' . $context->getId() . '/' . $this->_handlerPath;
    }

	function saveFormData(... $functionArgs) {
		$request = Application::get()->getRequest();
		$args = $request->_requestVars;
		$response =& $functionArgs[1];
		$params = $functionArgs[2];

		$args = $request->_router->_handler->convertStringsToSchema(SCHEMA_PUBLICATION, $args);
		$publication = Services::get('publication')->get($params['publication_id']);
		Services::get('publication')->edit($publication, $args, $request);

		return $response->withStatus(200);
	}

	function getControlledVocab(... $functionArgs) {
		$response =& $functionArgs[1];
		$params = $functionArgs[2];
		// we would require a class dereived from ControlledVocabDAO to implement this correctly
		return $response->withJson([
			'articleBadges' => [],
		], 200);
	}
}

?>