<?php

use PKP\components\forms\FieldText;
/**
 * @file plugins/generic/addFrontendElements/AddFrontendElementsPlugin.inc.php
 *
 * Copyright (c) 2024 Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.classes.plugins.GenericPlugin');
use \PKP\components\forms\FieldControlledVocab;

/**
 * @class AddFrontendElements
 * 
 * @brief Enables display of custom citations on the article page.
 */
class AddFrontendElementsPlugin extends GenericPlugin {
	/**
	 * Register the plugin.
	 * @param $category string
	 * @param $path string
	 */
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {	
	
		if (parent::register($category, $path, $mainContextId)) {
			if ($this->getEnabled($mainContextId)) {

				# hooks used to handle plugin settings
				HookRegistry::register('Schema::get::publication', array($this, 'addToSchema')); // to add variables to publication schema
				HookRegistry::register('Schema::get::context', array($this, 'addToSchema')); // to add plugin settings to context schema
				HookRegistry::register('APIHandler::endpoints', array($this, 'callbackSetupEndpoints')); //to setup endpoint for ComponentForm submission via REST API
				HookRegistry::register('Template::Settings::website::appearance', array($this, 'callbackAppearanceTab')); //to enable display of plugin settings tab
			
				# hooks to handle citation feature
				HookRegistry::register('LoadComponentHandler', array($this, 'setupGridHandler')); //to load (old style) grid handler 
				HookRegistry::register('Template::Workflow::Publication', array($this, 'addToPublicationForms'));
				HookRegistry::register('ArticleHandler::view', array($this, 'getArticleTemplateData'));
				HookRegistry::register('TemplateResource::getFilename', array($this, '_overridePluginTemplates'));
				HookRegistry::register('TemplateManager::display',array($this, 'addJs'));

				# hooks to handle article badge feature
				HookRegistry::register('Form::config::before', array($this, 'addArticleBadgeFormField'));
				
			}
			return true;
		}
		return false;
	}

	# add our API endpoints to handle form data
	function callbackSetupEndpoints($hook, $args) {
		$endpoints =& $args[0];

		import('plugins.generic.addFrontendElements.controllers.tab.AddFrontendElementsSettingsTabFormHandler');
		$handler = new AddFrontendElementsSettingsTabFormHandler();
		$endpoints = array_merge_recursive($endpoints, $handler->setupEndpoints());

		$this->import('controllers.tab.AddFrontendElementsPublicationTabFormHandler');
		$handler = new AddFrontendElementsPublicationTabFormHandler();
		$endpoints = array_merge_recursive($endpoints, $handler->setupEndpoints());
	}

	# add plugin variables to the appropriate schema
	public function addToSchema($hookName, $params) {
		$schema =& $params[0];
		switch ($hookName) {
			case 'Schema::get::publication':
				$schema->properties->{"citation"} = (object) [
					'type' => 'string',
					'multilingual' => false
				];
				$schema->properties->{"articleBadges"} = (object) [
					'type' => 'array',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'multilingual' => true,
					'items' => (object) ['type' => 'string']
				];
				$schema->properties->{"customHTMLContent"} = (object) [
					'type' => 'string',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'multilingual' => true,
				];	
				$schema->properties->{"coverImageCaption"} = (object) [
					'type' => 'string',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'multilingual' => true,
				];
				break;
			case 'Schema::get::context':
				$schema->properties->{"articleDetailsPageSettings"} = (object) [
					'type' => 'array',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'items' => (object) ['type' => 'string']
				];
				$schema->properties->{"customHTMLContentPosition"} = (object) [
					'type' => 'string',
					'apiSummary' => true,
					'validation' => ['nullable'],
				];

				break;
		}

		return false;
	}

	# add plugin settings to the website appearance tab
	function callbackAppearanceTab($hookName, $args) {		

		# prepare data
		$templateMgr =& $args[1];
		$output =& $args[2];
		$request =& Registry::get('request');
		$context = $request->getContext();

		$locales = $this->getLocales($context);
		$contextApiUrl = $this->getAPIUrl($request);

		// instantinate settings form
		$this->import('classes.components.form.AddFrontendElementsSettingsForm');
		$addFrontendElementsSettingsForm = new AddFrontendElementsSettingsForm($contextApiUrl, $locales, $context);

		$state = $templateMgr->getTemplateVars('state');
		$state['components'][FORM_ADDFRONTENDELEMENTS_SETTINGS] = $addFrontendElementsSettingsForm->getConfig();
		
		$currentTheme = $request->getContext()->getData('themePluginPath');
		switch ($currentTheme) {
			case str_contains('immersion', $$currentTheme):
				$customHTMLContentPositionOptions = [
					['value' => 'top', 'label' => __('plugins.generic.addFrontendElements.settings.customHTMLContentPosition.top')],
				];
				break;
			default:
				$customHTMLContentPositionOptions = [
					['value' => 'top', 'label' => __('plugins.generic.addFrontendElements.settings.customHTMLContentPosition.top')],
					['value' => 'bottom', 'label' => __('plugins.generic.addFrontendElements.settings.customHTMLContentPosition.bottom')],
				];
		}
		// hardcoding field index is not nice !!!
		$state['components'][FORM_ADDFRONTENDELEMENTS_SETTINGS]['fields'][1]['options'] = $customHTMLContentPositionOptions;
		
		$templateMgr->assign('state', $state);

		$templateMgr->setConstants([
			'FORM_ADDFRONTENDELEMENTS_SETTINGS',
		]);
		$output .= $templateMgr->fetch($this->getTemplateResource('appearanceTab.tpl'));

		// Permit other plugins to continue interacting with this hook
		return false;
	}
	
	// Citation feature functions 
	/**
	 * Retrieve citation information for the article details template. This
	 * method is hooked in before a template displays.
	 *
	 * @see ArticleHandler::view()
	 * @param $hookname string
	 * @param $args array
	 * @return false
	 */
	public function getArticleTemplateData($hookName, $args) {
		$request = $args[0];
		$issue = $args[1];
		$article = $args[2];
		$publication = $args[3];
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;
		$templateMgr = TemplateManager::getManager($request);
		$baseUrl = $request->getBaseUrl();

		$templateMgr->addHeader(
			'addCitation',
			"<link rel='stylesheet' href='".$baseUrl."/".$this->getPluginPath()."/css/addFrontendElements.css'>"
		);

		if (in_array('citations', $context->getData('articleDetailsPageSettings'))) {

			$citationsAll = json_decode($publication->getData('citation'),true);				
			$output = "";
			if ($citationsAll) {
				foreach($citationsAll as $citation) {
					$output .= '<div class="addCitationItem">';
					if ($citation['style']) {
						$output .= "<span>".$citation['style'].":</span>";
					}
					$output .= $citation['citation'].'</div>';			
				}
			}

			if ($output) {
				$templateMgr->assign(array(
					'addCitation' => '<div>'.$output."</div>",
				));
			}

		}
		if (in_array('articleBadges', $context->getData('articleDetailsPageSettings'))) {
			$templateMgr->assign(array(
				'articleBadges' => $publication->getLocalizedData('articleBadges')
			));
		}
		if (in_array('customHTMLContent', $context->getData('articleDetailsPageSettings'))) {
			$templateMgr->assign(array(
				'customHTMLContent' => $publication->getLocalizedData('customHTMLContent'),
				'customHTMLContentPosition' => $context->getData('customHTMLContentPosition')
			));
		}
		if (in_array('coverImageCaption', $context->getData('articleDetailsPageSettings'))) {
			$templateMgr->assign(array(
				'coverImageCaption' => $publication->getLocalizedData('coverImageCaption')
			));
		}
		return false;
	}
	
	/**
	 * Insert in the publication tabs
	 */
	function addToPublicationForms($hookName, $params) {
		$templateMgr =& $params[1];
		$output =& $params[2];
		$request =& Registry::get('request');
		$context = $request->getContext();
		$locales = $this->getLocales($context);

		if (in_array('citations', $context->getData('articleDetailsPageSettings'))) {

			$submission = $templateMgr->getTemplateVars('submission');
			$templateMgr->assign([
				'submissionId' => $submission->getId(),
			]);

			$output .= sprintf(
				'<tab id="addCitation" label="%s">%s</tab>',
				__('plugins.generic.addFrontendElements.addCitations.tabTitle'),
				$templateMgr->fetch($this->getTemplateResource('metadataForm.tpl'))
			);
		}

		if (in_array('articleBadges', $context->getData('articleDetailsPageSettings')) or
			in_array('customHTMLContent', $context->getData('articleDetailsPageSettings'))) {
			# add tab to publication tab
			# get the current publication object
			$handler = $request->getRouter()->getHandler();
			$submission = $handler->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
			if (!$submission || !$context || $context->getId() != $submission->getContextId()) {
				return;
			} // submission id could not be found
			$publication = $submission->getCurrentPublication();
			$dispatcher = $request->getDispatcher();

			$contextApiUrl = $dispatcher->url(
				$request,
				ROUTE_API,
				$context->getPath(),
				'contexts/' . $context->getId() . "/addFrontendElements/" . $submission->getId() . "/". $publication->getId()
			);

			$suggestionUrlBase = $dispatcher->url(
				$request,
				ROUTE_API,
				$context->getPath(),
				'contexts/' . $context->getId() . "/addFrontendElements/vocabs?vocab=articleBadges"
			);

			$imageUplaodApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), '_uploadPublicFile');

			// instantinate publication tab form
			$this->import('classes.components.form.AddFrontendElementsPublicationTabForm');
			$addFrontendElementsPublicationTabForm = new AddFrontendElementsPublicationTabForm($contextApiUrl, $locales, $context, $publication, $suggestionUrlBase, $imageUplaodApiUrl);

			$state = $templateMgr->getTemplateVars('state');
			$state['components'][FORM_ADDFRONTENDELEMENTS_PUBLICATION_TAB] = $addFrontendElementsPublicationTabForm->getConfig();
			$templateMgr->assign('state', $state);

			$templateMgr->setConstants([
				'FORM_ADDFRONTENDELEMENTS_PUBLICATION_TAB',
			]);
			

			$output .= $templateMgr->fetch($this->getTemplateResource('publicationTab.tpl'));
		}

		return false;
	}

	// add to issue form
	function addArticleBadgeFormField($hookName, $args) {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if (in_array('coverImageCaption', $context->getData('articleDetailsPageSettings'))) {
			
			$form = $args;

			import('classes.components.forms.publication.IssueEntryForm');
			if ($args->id == FORM_ISSUE_ENTRY) {
				$handler = $request->getRouter()->getHandler();
				$submission = $handler->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

				if (!$submission || !$context || $context->getId() != $submission->getContextId()) {
					return;
				} // submission id could not be found

				$locales = $this->getLocales($context);
				$publication = $submission->getCurrentPublication();

				$form->addField(new FieldText('coverImageCaption', [
					'label' => __('plugins.generic.addFrontendElements.coverImageCaption.label'),
					'isMultilingual' => true,
					'size' => 'large',
					'locales' => $locales,
				]), [FIELD_POSITION_AFTER, 'coverImage']);
			}
		}
    }
		
	/**
	 * Set up handler
	 */
	function setupGridHandler($hookName, $params) {
		
		$component =& $params[0];
		if ($component == 'plugins.generic.addFrontendElements.controllers.grid.AddCitationGridHandler') {			
			import($component);
			AddCitationGridHandler::setPlugin($this);
			return true;
		}
		return false;
	}

	/**
	 * Add custom js 
	 */
	function addJs($hookName, $params) {
		$templateMgr = $params[0];
		$template =& $params[1];
		$request = Application::get()->getRequest();

		$gridHandlerJs = $this->getJavaScriptURL() . DIRECTORY_SEPARATOR . 'AddCitationGridHandler.js';		
		$templateMgr->addJavaScript(
			'AddCitationGridHandlerJs',
			$gridHandlerJs,
			array('contexts' => 'backend')
		);

		return false;
	}

	// global helper functions

	// get API Url for our settings form
	function getAPIUrl($request) {
		$context = $request->getContext();
		$dispatcher = $request->getDispatcher();
		return $dispatcher->url(
			$request,
			ROUTE_API,
			$context->getPath(),
			'contexts/' . $context->getId() . "/addFrontendElementsSettings"
		);
	}

	function getLocales($context) {
		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		return array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);
	}

	/**
	 * Get the JavaScript URL for this plugin.
	 */
	function getJavaScriptURL() {
		$request = Application::get()->getRequest();		
		return $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . DIRECTORY_SEPARATOR . 'js';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.addFrontendElements.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.addFrontendElements.description');
	}

}

?>
