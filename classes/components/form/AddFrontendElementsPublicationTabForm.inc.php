<?php

/**
 * @file plugins/generic/addFrontendElements/classes/components/form/AddFrontendElementsPublicationTabForm.inc.php
 *
 * Copyright (c) 2024 Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddFrontendElementsPublicationTabForm
 *
 * Form for adding/editing additional frontend elments data
 *
 */

import('lib.pkp.classes.form.Form');

class AddFrontendElementsPublicationTabForm extends Form {
	/** @var int Context ID */
	var $contextId;

	/** @var int Submission ID */
	var $submissionId;

	/** @var AddFrontendElementsPlugin */
	var $plugin;
	
	var $objectId;

	/**
	 * Constructor
	 * @param $addCitationPlugin AddCitationPlugin
	 * @param $contextId int Context ID
	 * @param $submissionId int Submission ID
	 * @param $objectId int 
	 */
	function __construct($addCitationPlugin, $contextId, $submissionId, $objectId) {
		parent::__construct($addCitationPlugin->getTemplateResource('editAddCitationForm.tpl'));

		$this->contextId = $contextId;
		$this->submissionId = $submissionId;
		$this->plugin = $addCitationPlugin;
		$this->objectId = $objectId;	

		// Add form checks
		$this->addCheck(new FormValidator($this, 'style', 'optional', 'plugins.generic.addFrontendElements.style'));
		$this->addCheck(new FormValidator($this, 'citation', 'required', 'plugins.generic.addFrontendElements.citation'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$this->setData('submissionId', $this->submissionId);
		$this->setData('objectId', $this->objectId);		
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($this->submissionId);
		$publicationDao = DAORegistry::getDAO('PublicationDAO');
		$publication = $publicationDao->getById($submission->getData('currentPublicationId'));
		$citationsAll = json_decode($publication->getData('citation'),true);
		
		if (isset($this->objectId)) {			
			$this->setData('style', $citationsAll[$this->objectId-1]['style']);
			$this->setData('citation', $citationsAll[$this->objectId-1]['citation']);
		}
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('style', 'citation'));
	}

	/**
	 * Save form values into the database
	 */
	function execute(...$functionArgs) {		
		parent::execute(...$functionArgs);
		
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($this->submissionId);
		$publicationDao = DAORegistry::getDAO('PublicationDAO');
		$publication = $publicationDao->getById($submission->getData('currentPublicationId'));
		
		$citationsAll = json_decode($publication->getData('citation'),true);
		$newCitation = array('style'=>$this->getData('style'),'citation'=>$this->getData('citation'));
		$citationsAll[] = $newCitation;

		if($this->objectId) {
			unset($citationsAll[$this->objectId-1]);
		}
		$citationsAll = array_values($citationsAll);
			
		$publication->setData('citation',json_encode($citationsAll));
		$publicationDao->updateObject($publication);
	}
}

?>
