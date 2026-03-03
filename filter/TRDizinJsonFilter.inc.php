<?php

/**
 * @file plugins/importexport/trdizin/filter/TRDizinJsonFilter.inc.php
 *
 * TRDizin JSON Export Plugin for OJS
 *
 * @class TRDizinJsonFilter
 * @ingroup plugins_importexport_trdizin
 *
 * @brief Class that converts a Submission to a TRDizin JSON array element.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportExportFilter');

class TRDizinJsonFilter extends NativeImportExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('TRDizin JSON export');
		parent::__construct($filterGroup);
	}

	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'plugins.importexport.trdizin.filter.TRDizinJsonFilter';
	}

	/**
	 * @see Filter::process()
	 * @param $pubObject Submission
	 * @return string JSON string
	 */
	function &process(&$pubObject) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$plugin = $deployment->getPlugin();
		$cache = $plugin->getCache();

		$publication = $pubObject->getCurrentPublication();
		$locale = $publication->getData('locale');

		// Get issue
		$issueId = $publication->getData('issueId');
		if ($cache->isCached('issues', $issueId)) {
			$issue = $cache->get('issues', $issueId);
		} else {
			$issueDao = DAORegistry::getDAO('IssueDAO');
			$issue = $issueDao->getById($issueId, $context->getId());
			if ($issue) $cache->add($issue, null);
		}

		$article = array();

		// Publication type (default to RESEARCH)
		$article['publicationType'] = 'RESEARCH';

		// Pages
		$startPage = $publication->getStartingPage();
		$endPage = $publication->getEndingPage();
		if (!empty($startPage)) {
			$article['startPage'] = (int) $startPage;
		}
		if (!empty($endPage)) {
			$article['endPage'] = (int) $endPage;
		}

		// PDF URL
		$request = Application::get()->getRequest();
		$galleys = $publication->getData('galleys');
		if ($galleys) {
			foreach ($galleys as $galley) {
				if ($galley->getFileType() == 'application/pdf') {
					$article['pdfFile'] = $request->url(null, 'article', 'download',
						array($pubObject->getBestId(), $galley->getBestGalleyId()));
					break;
				}
			}
		}

		// Multi-language abstract contents
		$supportedLocales = array_keys(AppLocale::getSupportedFormLocales());
		$titles = (array) $publication->getFullTitles();
		$abstracts = (array) $publication->getData('abstract');
		$dao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$keywords = $dao->getKeywords($publication->getId(), $supportedLocales);

		$article['publicationAbstractContents'] = array();
		foreach ($supportedLocales as $loc) {
			$title = isset($titles[$loc]) ? $titles[$loc] : null;
			$abstract = isset($abstracts[$loc]) ? $abstracts[$loc] : null;

			if (!empty($title) || !empty($abstract)) {
				$kws = isset($keywords[$loc]) ? $keywords[$loc] : array();
				$article['publicationAbstractContents'][] = array(
					'title' => $title ?: '',
					'abstractContent' => $abstract ? PKPString::html2text($abstract) : '',
					'keywords' => $kws,
					'publicationLanguage' => array('id' => $plugin->getLanguageIdFromLocale($loc)),
				);
			}
		}

		// Funder
		$supportingAgencies = $publication->getLocalizedData('supportingAgencies', $locale);
		if (!empty($supportingAgencies)) {
			$funderName = is_array($supportingAgencies) ? implode('; ', $supportingAgencies) : $supportingAgencies;
			$article['funder'] = array('name' => $funderName);
		}

		// References
		$citationDao = DAORegistry::getDAO('CitationDAO');
		$citations = $citationDao->getByPublicationId($publication->getId());
		if ($citations) {
			$article['publicationReferences'] = array();
			$citationArray = $citations->toAssociativeArray();
			$refOrder = 1;
			foreach ($citationArray as $citation) {
				$article['publicationReferences'][] = array(
					'referenceFullText' => $citation->getRawCitation(),
					'referenceOrder' => $refOrder++,
				);
			}
		}

		// Authors
		$article['publicationAuthorRelations'] = array();
		$authors = (array) $publication->getData('authors');
		foreach ($authors as $authorIdx => $author) {
			$authorEntry = array(
				'inPublicationAuthorName' => $author->getFullName(false),
				'institutions' => array(),
				'authorType' => 'AUTHOR',
				'authorOrder' => $authorIdx + 1,
			);

			$affiliation = $author->getAffiliation($locale);
			if (!empty($affiliation)) {
				$authorEntry['institutions'][] = array(
					'inPublicationInstitutionName' => $affiliation,
				);
			}

			$orcid = $author->getData('orcid');
			if (!empty($orcid)) {
				if (strpos($orcid, 'orcid.org/') !== false) {
					$orcid = substr($orcid, strrpos($orcid, '/') + 1);
				}
				$authorEntry['orcid'] = $orcid;
			}

			$article['publicationAuthorRelations'][] = $authorEntry;
		}

		// Publication language
		$article['publicationLanguage'] = array('id' => $plugin->getLanguageIdFromLocale($locale));

		// DOI as publication number
		$doi = $publication->getStoredPubId('doi');
		if (!empty($doi)) {
			$article['publicationNumber'] = $doi;
		}

		$json = json_encode($article, JSON_UNESCAPED_UNICODE);
		return $json;
	}
}
