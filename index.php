<?php

/**
 * @defgroup plugins_importexport_trdizin TRDizin Export Plugin
 */

/**
 * @file plugins/importexport/trdizin/index.php
 *
 * TRDizin JSON Export Plugin for OJS
 *
 * @ingroup plugins_importexport_trdizin
 * @brief Wrapper for TRDizin JSON export plugin.
 */

require_once('TRDizinExportPlugin.inc.php');

return new TRDizinExportPlugin();
