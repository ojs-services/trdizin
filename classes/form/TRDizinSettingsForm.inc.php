<?php

/**
 * @file plugins/importexport/trdizin/classes/form/TRDizinSettingsForm.inc.php
 *
 * TRDizin JSON Export Plugin for OJS
 *
 * @class TRDizinSettingsForm
 * @ingroup plugins_importexport_trdizin
 *
 * @brief Form for journal managers to setup TRDizin plugin
 */

import('lib.pkp.classes.form.Form');

class TRDizinSettingsForm extends Form {

	/** @var integer */
	var $_contextId;

	/** @var TRDizinExportPlugin */
	var $_plugin;

	/**
	 * Constructor
	 * @param $plugin TRDizinExportPlugin
	 * @param $contextId integer
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$plugin = $this->_plugin;
		$contextId = $this->_contextId;

		$this->setData('sectionMapping', $plugin->getSetting($contextId, 'sectionMapping') ?: '{}');
		$this->setData('defaultSubjectIds', $plugin->getSetting($contextId, 'defaultSubjectIds') ?: '[]');
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('sectionMapping', 'defaultSubjectIds'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$plugin = $this->_plugin;
		$contextId = $this->_contextId;

		// Get journal sections
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sectionsIterator = $sectionDao->getByContextId($contextId);
		$sections = array();
		while ($section = $sectionsIterator->next()) {
			$sections[] = array(
				'id' => $section->getId(),
				'title' => $section->getLocalizedTitle(),
			);
		}

		// Parse current section mapping values
		$sectionMappingRaw = $this->getData('sectionMapping');
		$sectionMappingValues = is_string($sectionMappingRaw) ? json_decode($sectionMappingRaw, true) : $sectionMappingRaw;
		if (!is_array($sectionMappingValues)) $sectionMappingValues = array();

		// Parse current default subject IDs
		$defaultSubjectIdsRaw = $this->getData('defaultSubjectIds');
		$defaultSubjectIdValues = is_string($defaultSubjectIdsRaw) ? json_decode($defaultSubjectIdsRaw, true) : $defaultSubjectIdsRaw;
		if (!is_array($defaultSubjectIdValues)) $defaultSubjectIdValues = array();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'sections' => $sections,
			'sectionMappingValues' => $sectionMappingValues,
			'publicationTypeOptions' => $plugin->getPublicationTypeOptions(),
			'trdizinSubjects' => $plugin->getTRDizinSubjects(),
			'defaultSubjectIdValues' => $defaultSubjectIdValues,
			'pluginName' => $plugin->getName(),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$plugin = $this->_plugin;
		$contextId = $this->_contextId;
		parent::execute(...$functionArgs);

		// Process section mapping from individual selects
		$sectionMappingRaw = $this->getData('sectionMapping');
		if (is_array($sectionMappingRaw)) {
			$plugin->updateSetting($contextId, 'sectionMapping', json_encode($sectionMappingRaw), 'string');
		} else {
			$plugin->updateSetting($contextId, 'sectionMapping', $sectionMappingRaw, 'string');
		}

		// Process default subject IDs
		$defaultSubjectIds = $this->getData('defaultSubjectIds');
		if (is_array($defaultSubjectIds)) {
			$plugin->updateSetting($contextId, 'defaultSubjectIds', json_encode(array_map('intval', $defaultSubjectIds)), 'string');
		} else {
			$plugin->updateSetting($contextId, 'defaultSubjectIds', $defaultSubjectIds, 'string');
		}
	}
}
