<?php
/**
 * @file classes/components/form/addFrontendElementsSettingsForm.inc.php
 *
 * Copyright (c) 2021 Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 * 
 * @brief File implemnting addFrontendElementsSettingsForm
 */

use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;
use \PKP\components\forms\FieldOptions;

define('FORM_ADDFRONTENDELEMENTS_SETTINGS', 'addFrontendElementsSettings');

/**
 * A form for implementing addFrontendElements settings.
 * 
 * @class AddFrontendElementsSettingsForm
 * @brief Class implemnting AddFrontendElementsSettingsForm
 */
class AddFrontendElementsSettingsForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_ADDFRONTENDELEMENTS_SETTINGS;

	/** @copydoc FormComponent::$method */
	public $method = 'POST';

	/**
	 * Constructor
	 *
	 * @param string $action string URL to submit the form to
	 * @param array $locales array Supported locales
	 * @param object $context Context Journal or Press to change settings for
	 * @param string $temporaryFileApiUrl string URL to upload files to
	 * @param string $imageUploadUrl string The API endpoint for images uploaded through the rich text field
	 * @param string $publicUrl url to the frontend page
	 * @param array $data settings for form initialization
	 */
	public function __construct($action, $locales, $context) {
		$this->action = $action;
		$this->locales = $locales;
		$plugin = PluginRegistry::getPlugin('generic', 'addfrontendelementsplugin');

		$this->addGroup([
			'id' => 'addfrontendelementssettings',
			// 'label' => __('plugins.generic.addFrontendElements.articleDetails.groupLabel'),
		], [])
		->addField(new FieldOptions('articleDetailsPageSettings', [
			'label' => __('plugins.generic.addFrontendElements.articleDetails.boxLabel'),
			'options' => [
				['value' => 'citations', 'label' => __('plugins.generic.addFrontendElements.addCitations.settings.description')],
				['value' => 'articleBadges', 'label' => __('plugins.generic.addFrontendElements.articleBadges.settings.description')],
				['value' => 'customHTMLBox', 'label' => __('plugins.generic.addFrontendElements.settings.customHTMLBox.description')]
			],
			'value' => $context->getData('articleDetailsPageSettings') ?: [],
			'groupId' => 'addfrontendelementssettings'
		]));
	}

}