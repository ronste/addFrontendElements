<?php
/**
 * @file plugins/generic/addFrontendElements/AddFrontendElementsPlugin.inc.php
 *
 * Copyright (c) 2024 Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

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

				# hooks to handle review badge feature

				
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

		// add the new endpoint
		$endpoints['POST'][] = 
			[
				'pattern' => '/{contextPath}/api/{version}/contexts/{contextId}/addFrontendElementsSettings',
				'handler' => [$handler, 'saveFormData'],
				'roles' => array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER)
			];
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
				break;
			case 'Schema::get::context':
				$schema->properties->{"articleDetailsPageSettings"} = (object) [
					'type' => 'array',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'items' => (object) ['type' => 'string']
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
		$dispatcher = $request->getDispatcher();

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		$contextApiUrl = $dispatcher->url(
			$request,
			ROUTE_API,
			$context->getPath(),
			'contexts/' . $context->getId() . "/addFrontendElementsSettings"
		);

		// instantinate settings form
		$this->import('classes.components.form.addFrontendElementsSettingsForm');
		$addFrontendElementsSettingsForm = new AddFrontendElementsSettingsForm($contextApiUrl, $locales, $context);

		$state = $templateMgr->getTemplateVars('state');
		$state['components'][FORM_ADDFRONTENDELEMENTS_SETTINGS] = $addFrontendElementsSettingsForm->getConfig();
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
					'addCitation' => '<div>'.$output."</div>"
				));			
			}
			
			import('classes.file.PublicFileManager');
			$publicFileManager = new PublicFileManager();
			$baseUrl = $request->getBaseUrl();		
			$templateMgr->addHeader(
				'addCitation',
				"<link rel='stylesheet' href='".$baseUrl."/plugins/generic/addCitation/css/addCitation.css'>"
			);
		}
		return false;
	}		
	
	/**
	 * Insert in the publication tabs
	 */
	function addToPublicationForms($hookName, $params) {
		$context = Application::getRequest()->getContext();
		if (in_array('citations', $context->getData('articleDetailsPageSettings'))) {
			$smarty =& $params[1];
			$output =& $params[2];
			$submission = $smarty->getTemplateVars('submission');
			$smarty->assign([
				'submissionId' => $submission->getId(),
			]);

			$output .= sprintf(
				'<tab id="addCitation" label="%s">%s</tab>',
				__('plugins.generic.addFrontendElements.addCitations.tabTitle'),
				$smarty->fetch($this->getTemplateResource('metadataForm.tpl'))
			);
		}
		return false;
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

		$gridHandlerJs = $this->getJavaScriptURL($request, false) . DIRECTORY_SEPARATOR . 'AddCitationGridHandler.js';		
		$templateMgr->addJavaScript(
			'AddCitationGridHandlerJs',
			$gridHandlerJs,
			array('contexts' => 'backend')
		);

		return false;
	}

	// Review badge feature functions



	// global helper functions

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
