<?php

/**
 * @defgroup plugins_importexport_trdizin TRDizin export plugin
 */

/**
 * @file plugins/importexport/trdizin/TRDizinExportDeployment.inc.php
 *
 * TRDizin JSON Export Plugin for OJS
 *
 * @class TRDizinExportDeployment
 * @ingroup plugins_importexport_trdizin
 *
 * @brief Base class configuring the TRDizin export process.
 */

class TRDizinExportDeployment {
	/** @var Context The current import/export context */
	var $_context;

	/** @var Plugin The current import/export plugin */
	var $_plugin;

	/**
	 * Constructor
	 * @param $context Context
	 * @param $plugin ImportExportPlugin
	 */
	function __construct($context, $plugin) {
		$this->setContext($context);
		$this->setPlugin($plugin);
	}

	/**
	 * Get the plugin cache
	 * @return PubObjectCache
	 */
	function getCache() {
		return $this->_plugin->getCache();
	}

	/**
	 * Set the import/export context.
	 * @param $context Context
	 */
	function setContext($context) {
		$this->_context = $context;
	}

	/**
	 * Get the import/export context.
	 * @return Context
	 */
	function getContext() {
		return $this->_context;
	}

	/**
	 * Set the import/export plugin.
	 * @param $plugin ImportExportPlugin
	 */
	function setPlugin($plugin) {
		$this->_plugin = $plugin;
	}

	/**
	 * Get the import/export plugin.
	 * @return ImportExportPlugin
	 */
	function getPlugin() {
		return $this->_plugin;
	}
}
