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

use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldControlledVocab;
use \PKP\components\forms\FieldRichTextarea;

define('FORM_ADDFRONTENDELEMENTS_PUBLICATION_TAB', 'addFrontendElementsPublicationTab');

class AddFrontendElementsPublicationTabForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_ADDFRONTENDELEMENTS_PUBLICATION_TAB;

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
	public function __construct($action, $locales, $context, $publication, $suggestionUrlBase, $imageUploadUrl) {
		$this->action = $action;
		$this->locales = $locales;

		$this->addGroup([
			'id' => 'addfrontendelementspublicationtabarticlepagegroup',
			// 'label' => __('plugins.generic.addFrontendElements.articleDetails.groupLabel'),
		]);
		if (in_array('articleBadges', $context->getData('articleDetailsPageSettings'))) {
			$this->addField(new FieldControlledVocab('articleBadges', [
				'label' => __('plugins.generic.addFrontendElements.articleBadges.label'),
				'tooltip' => __('plugins.generic.addFrontendElements.articleBadges.description'),
				'isMultilingual' => true,
				// 'apiUrl' => $suggestionUrlBase,
				'locales' => $locales,
				'value' => (array) $publication->getData('articleBadges')?:[],
				'selected' => (array) $publication->getData('articleBadges')?:[],
				'groupId' => 'addfrontendelementspublicationtabarticlepagegroup'
			]));
		}

		if (in_array('customHTMLContent', $context->getData('articleDetailsPageSettings'))) {
			$this->addField(new FieldRichTextarea('customHTMLContent', [
				'label' => __('plugins.generic.addFrontendElements.customHTMLContent.label'),
				'isMultilingual' => true,
				'value' => $publication->getData('customHTMLContent'),
				'tooltip' => __('plugins.generic.addFrontendElements.customHTMLContent.tooltip'),
				'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
				'plugins' => 'paste,link,lists,image,code',
				'uploadUrl' => $imageUploadUrl,
				'groupId' => 'addfrontendelementspublicationtabarticlepagegroup'
			]));
		}
	}
}

?>
