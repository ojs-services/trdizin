<?php

/**
 * @file plugins/importexport/trdizin/TRDizinExportPlugin.inc.php
 *
 * TRDizin JSON Export Plugin for OJS
 *
 * @class TRDizinExportPlugin
 * @ingroup plugins_importexport_trdizin
 *
 * @brief TRDizin JSON export plugin
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

class TRDizinExportPlugin extends ImportExportPlugin {

	/** @var PubObjectCache */
	var $_cache;

	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'TRDizinExportPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.importexport.trdizin.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.importexport.trdizin.description');
	}

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Get the plugin cache.
	 * @return PubObjectCache
	 */
	function getCache() {
		if (!is_a($this->_cache, 'PubObjectCache')) {
			import('classes.plugins.PubObjectCache');
			$this->_cache = new PubObjectCache();
		}
		return $this->_cache;
	}

	/**
	 * @copydoc ImportExportPlugin::display()
	 */
	function display($args, $request) {
		parent::display($args, $request);
		$context = $request->getContext();
		$templateMgr = TemplateManager::getManager($request);

		switch (array_shift($args)) {
			case 'preview':
				return $this->displayPreview($request, $context, $templateMgr);
			case 'export':
				return $this->exportJson($request, $context);
			case 'index':
			case '':
			default:
				return $this->displayIndex($request, $context, $templateMgr);
		}
	}

	/**
	 * Display the main index page with issue list and settings.
	 */
	function displayIndex($request, $context, $templateMgr) {
		// Load global CSS
		$templateMgr->addStyleSheet(
			'trdizinGlobal',
			$request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/trdizin.css',
			array('contexts' => array('backend'))
		);

		// Get published issues
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issues = $issueDao->getPublishedIssues($context->getId());
		$issueData = array();
		while ($issue = $issues->next()) {
			// Count published articles in this issue using Services API
			$submissionsIterator = Services::get('submission')->getMany(array(
				'contextId' => $context->getId(),
				'issueIds' => $issue->getId(),
				'status' => STATUS_PUBLISHED,
			));
			$articleCount = count(iterator_to_array($submissionsIterator));

			$issueData[] = array(
				'id' => $issue->getId(),
				'title' => $issue->getIssueIdentification(),
				'volume' => $issue->getVolume(),
				'number' => $issue->getNumber(),
				'datePublished' => $issue->getDatePublished(),
				'numArticles' => $articleCount,
			);
		}
		$templateMgr->assign('issues', $issueData);
		$templateMgr->display($this->getTemplateResource('index.tpl'));
	}

	/**
	 * Display the preview page for an issue.
	 */
	function displayPreview($request, $context, $templateMgr) {
		$issueId = (int) $request->getUserVar('issueId');
		if (!$issueId) {
			$request->redirect(null, null, null, array('plugin', $this->getName()));
			return;
		}

		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->getById($issueId, $context->getId());
		if (!$issue) {
			$request->redirect(null, null, null, array('plugin', $this->getName()));
			return;
		}

		// Load settings
		$sectionMapping = json_decode($this->getSetting($context->getId(), 'sectionMapping') ?: '{}', true);
		$defaultSubjectIds = json_decode($this->getSetting($context->getId(), 'defaultSubjectIds') ?: '[]', true);

		// Get articles for this issue
		$articlesData = $this->getArticlesDataForIssue($request, $context, $issue, $sectionMapping);

		// Count total warnings
		$totalWarnings = 0;
		foreach ($articlesData as $article) {
			$totalWarnings += count($article['warnings']);
		}

		// Load CSS
		$baseStyleUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/';
		$templateMgr->addStyleSheet(
			'trdizinGlobal',
			$baseStyleUrl . 'trdizin.css',
			array('contexts' => array('backend'))
		);
		$templateMgr->addStyleSheet(
			'trdizinPreview',
			$baseStyleUrl . 'preview.css',
			array('contexts' => array('backend'))
		);

		$templateMgr->assign(array(
			'issue' => $issue,
			'articlesData' => $articlesData,
			'totalWarnings' => $totalWarnings,
			'publicationTypeOptions' => $this->getPublicationTypeOptions(),
			'trdizinSubjects' => $this->getTRDizinSubjects(),
			'defaultSubjectIds' => $defaultSubjectIds,
			'languageOptions' => $this->getLanguageOptions(),
			'pluginUrl' => $request->url(null, null, null, array('plugin', $this->getName())),
		));
		$templateMgr->display($this->getTemplateResource('preview.tpl'));
	}

	/**
	 * Extract all article data for a given issue.
	 * @param $request Request
	 * @param $context Context
	 * @param $issue Issue
	 * @param $sectionMapping array
	 * @return array
	 */
	function getArticlesDataForIssue($request, $context, $issue, $sectionMapping) {
		$articlesData = array();

		// Get submissions for this issue using Services API (efficient query)
		$submissionsIterator = Services::get('submission')->getMany(array(
			'contextId' => $context->getId(),
			'issueIds' => $issue->getId(),
			'status' => STATUS_PUBLISHED,
		));

		$citationDao = DAORegistry::getDAO('CitationDAO');
		$keywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');

		$supportedLocales = array_keys(AppLocale::getSupportedFormLocales());
		$articleIndex = 0;

		foreach ($submissionsIterator as $submission) {
			$publication = $submission->getCurrentPublication();
			if (!$publication) {
				continue;
			}

			$warnings = array();
			$locale = $publication->getData('locale');

			// Title and abstract per locale
			$titles = (array) $publication->getFullTitles();
			$abstracts = (array) $publication->getData('abstract');
			$keywords = $keywordDao->getKeywords($publication->getId(), $supportedLocales);

			// Check abstract
			if (empty($abstracts[$locale])) {
				$warnings[] = __('plugins.importexport.trdizin.warning.abstractMissing');
			}

			// Check keywords
			if (empty($keywords[$locale])) {
				$warnings[] = __('plugins.importexport.trdizin.warning.keywordsMissing');
			}

			// Pages
			$startPage = $publication->getStartingPage();
			$endPage = $publication->getEndingPage();
			if (empty($startPage)) {
				$warnings[] = __('plugins.importexport.trdizin.warning.pagesMissing');
			}

			// DOI
			$doi = $publication->getStoredPubId('doi');
			if (empty($doi)) {
				$warnings[] = __('plugins.importexport.trdizin.warning.doiMissing');
			}

			// Authors
			$authors = (array) $publication->getData('authors');
			$authorsData = array();
			foreach ($authors as $author) {
				$authorName = $author->getFullName(false);
				$affiliation = $author->getAffiliation($locale);
				$orcid = $author->getData('orcid');

				if (empty($orcid)) {
					$warnings[] = __('plugins.importexport.trdizin.warning.orcidMissing', array('authorName' => $authorName));
				}
				if (empty($affiliation)) {
					$warnings[] = __('plugins.importexport.trdizin.warning.affiliationMissing', array('authorName' => $authorName));
				}

				$authorsData[] = array(
					'name' => $authorName,
					'affiliation' => $affiliation,
					'orcid' => $orcid,
					'seq' => $author->getData('seq'),
				);
			}

			// PDF galley URL
			$pdfUrl = '';
			$galleys = $publication->getData('galleys');
			if ($galleys) {
				foreach ($galleys as $galley) {
					if ($galley->getFileType() == 'application/pdf') {
						$pdfUrl = $request->url(null, 'article', 'download',
							array($submission->getBestId(), $galley->getBestGalleyId()));
						break;
					}
				}
			}
			if (empty($pdfUrl)) {
				$warnings[] = __('plugins.importexport.trdizin.warning.pdfMissing');
			}

			// References
			$citations = $citationDao->getByPublicationId($publication->getId());
			$referencesData = array();
			if ($citations) {
				$citationArray = $citations->toAssociativeArray();
				$refOrder = 1;
				foreach ($citationArray as $citation) {
					$referencesData[] = array(
						'text' => $citation->getRawCitation(),
						'order' => $refOrder++,
					);
				}
			}
			if (empty($referencesData)) {
				$warnings[] = __('plugins.importexport.trdizin.warning.referencesMissing');
			}

			// Section -> publication type mapping
			$sectionId = $publication->getData('sectionId');
			$publicationType = isset($sectionMapping[$sectionId]) ? $sectionMapping[$sectionId] : 'RESEARCH';

			// Language
			$languageId = $this->getLanguageIdFromLocale($locale);

			// Build abstract contents per locale
			$abstractContents = array();
			foreach ($supportedLocales as $loc) {
				$title = isset($titles[$loc]) ? $titles[$loc] : null;
				$abstract = isset($abstracts[$loc]) ? $abstracts[$loc] : null;
				$kws = isset($keywords[$loc]) ? $keywords[$loc] : array();

				if (!empty($title) || !empty($abstract)) {
					$abstractContents[] = array(
						'locale' => $loc,
						'title' => $title ?: '',
						'abstract' => $abstract ? PKPString::html2text($abstract) : '',
						'keywords' => $kws,
						'languageId' => $this->getLanguageIdFromLocale($loc),
					);
				}
			}

			// Supporting agencies (funder)
			$supportingAgencies = $publication->getLocalizedData('supportingAgencies', $locale);

			$articlesData[] = array(
				'index' => $articleIndex,
				'submissionId' => $submission->getId(),
				'title' => $submission->getLocalizedTitle(),
				'locale' => $locale,
				'languageId' => $languageId,
				'startPage' => $startPage,
				'endPage' => $endPage,
				'doi' => $doi,
				'pdfUrl' => $pdfUrl,
				'authors' => $authorsData,
				'abstractContents' => $abstractContents,
				'references' => $referencesData,
				'publicationType' => $publicationType,
				'sectionId' => $sectionId,
				'supportingAgencies' => $supportingAgencies,
				'warnings' => $warnings,
			);
			$articleIndex++;
		}

		return $articlesData;
	}

	/**
	 * Export JSON for an issue.
	 */
	function exportJson($request, $context) {
		$issueId = (int) $request->getUserVar('issueId');
		if (!$issueId) {
			$request->redirect(null, null, null, array('plugin', $this->getName()));
			return;
		}

		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->getById($issueId, $context->getId());
		if (!$issue) {
			$request->redirect(null, null, null, array('plugin', $this->getName()));
			return;
		}

		// Get articles data from POST (user overrides for type and subjects)
		$articlesPost = $request->getUserVar('articles');
		if (!is_array($articlesPost)) $articlesPost = array();

		// Validate overrides: whitelist publicationType values, sanitize subjects
		$validTypes = array_keys($this->getPublicationTypeOptions());
		$validSubjectIds = array_keys($this->getTRDizinSubjects());
		foreach ($articlesPost as &$articlePost) {
			if (isset($articlePost['publicationType']) && !in_array($articlePost['publicationType'], $validTypes)) {
				unset($articlePost['publicationType']);
			}
			if (isset($articlePost['subjects']) && is_array($articlePost['subjects'])) {
				$articlePost['subjects'] = array_filter(
					array_map('intval', $articlePost['subjects']),
					function($id) use ($validSubjectIds) { return in_array($id, $validSubjectIds); }
				);
			}
		}
		unset($articlePost);

		// Get settings
		$sectionMapping = json_decode($this->getSetting($context->getId(), 'sectionMapping') ?: '{}', true);

		// Get article data from OJS
		$articlesData = $this->getArticlesDataForIssue($request, $context, $issue, $sectionMapping);

		// Build JSON output using shared buildArticleJson method
		$jsonOutput = array();
		foreach ($articlesData as $idx => $articleData) {
			$overrides = isset($articlesPost[$idx]) ? $articlesPost[$idx] : array();
			$jsonOutput[] = $this->buildArticleJson($articleData, $overrides);
		}

		// Generate filename (sanitize volume/number for Content-Disposition header)
		$volume = preg_replace('/[^a-zA-Z0-9_-]/', '', $issue->getVolume() ?: '0');
		$number = preg_replace('/[^a-zA-Z0-9_-]/', '', $issue->getNumber() ?: '0');
		$filename = 'trdizin_cilt' . $volume . '_sayi' . $number . '.json';

		// Send JSON file
		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: private');
		echo json_encode($jsonOutput, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		exit;
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		$user = $request->getUser();
		$context = $request->getContext();
		$notificationManager = new NotificationManager();

		$this->import('classes.form.TRDizinSettingsForm');
		$form = new TRDizinSettingsForm($this, $context->getId());

		switch ($request->getUserVar('verb')) {
			case 'save':
				$form->readInputData();
				if ($form->validate()) {
					$form->execute();
					$notificationManager->createTrivialNotification(
						$user->getId(),
						NOTIFICATION_TYPE_SUCCESS,
						array('contents' => __('plugins.importexport.trdizin.settings.saved'))
					);
					return new JSONMessage(true);
				} else {
					return new JSONMessage(true, $form->fetch($request));
				}
			case 'index':
				$form->initData();
				return new JSONMessage(true, $form->fetch($request));
			default:
				return parent::manage($args, $request);
		}
	}

	/**
	 * @copydoc ImportExportPlugin::pluginUrl()
	 * Override to support additional query parameters (e.g. issueId).
	 */
	function pluginUrl($params, $smarty) {
		$path = array('plugin', $this->getName());
		if (isset($params['path'])) {
			$path[] = $params['path'];
		}

		// Collect remaining params as URL query parameters
		$queryParams = array();
		foreach ($params as $key => $value) {
			if ($key !== 'path') {
				$queryParams[$key] = $value;
			}
		}

		$dispatcher = $this->_request->getDispatcher();
		return $dispatcher->url($this->_request, ROUTE_PAGE, null, 'management', 'importexport',
			$path, $queryParams
		);
	}

	/**
	 * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
	 */
	function getPluginSettingsPrefix() {
		return 'trdizin';
	}

	/**
	 * Get TRDizin language ID from OJS locale code.
	 * @param $locale string OJS locale (e.g. 'tr_TR')
	 * @return int TRDizin language ID
	 */
	function getLanguageIdFromLocale($locale) {
		$map = $this->getLanguageMap();
		$lang = substr($locale, 0, 2);
		return isset($map[$lang]) ? $map[$lang] : 9; // 9 = Other
	}

	/**
	 * Get OJS locale prefix to TRDizin language ID mapping.
	 * @return array
	 */
	function getLanguageMap() {
		return array(
			'fr' => 1,   // Français
			'tr' => 2,   // Türkçe
			'en' => 3,   // English
			'it' => 4,   // Italiano
			'es' => 5,   // Español
			'ru' => 6,   // Pусский
			'ar' => 7,   // العربية
			'de' => 8,   // Deutsch
			'kk' => 11,  // қазақша
			'ku' => 12,  // Kurdî
			'az' => 13,  // Azərbaycanca
		);
	}

	/**
	 * Get TRDizin language options for dropdowns.
	 * @return array
	 */
	function getLanguageOptions() {
		return array(
			1 => 'Français',
			2 => 'Türkçe',
			3 => 'English',
			4 => 'Italiano',
			5 => 'Español',
			6 => 'Pусский',
			7 => 'العربية',
			8 => 'Deutsch',
			9 => 'Other',
			11 => 'қазақша',
			12 => 'Kurdî',
			13 => 'Azərbaycanca',
		);
	}

	/**
	 * Get TRDizin publication type options.
	 * @return array code => locale key
	 */
	function getPublicationTypeOptions() {
		return array(
			'RESEARCH' => __('plugins.importexport.trdizin.type.RESEARCH'),
			'COMPILATION' => __('plugins.importexport.trdizin.type.COMPILATION'),
			'FACT_PRESENTATION' => __('plugins.importexport.trdizin.type.FACT_PRESENTATION'),
			'OTHER' => __('plugins.importexport.trdizin.type.OTHER'),
			'BOOK_PRESENTATION' => __('plugins.importexport.trdizin.type.BOOK_PRESENTATION'),
			'CORRECTION' => __('plugins.importexport.trdizin.type.CORRECTION'),
			'EDITORIAL' => __('plugins.importexport.trdizin.type.EDITORIAL'),
			'LETTER' => __('plugins.importexport.trdizin.type.LETTER'),
			'LETTER_TO_EDITOR' => __('plugins.importexport.trdizin.type.LETTER_TO_EDITOR'),
			'MEETING_SUMMARY' => __('plugins.importexport.trdizin.type.MEETING_SUMMARY'),
			'REPORT' => __('plugins.importexport.trdizin.type.REPORT'),
			'SHOR_REPORT' => __('plugins.importexport.trdizin.type.SHOR_REPORT'),
			'TRANSLATION' => __('plugins.importexport.trdizin.type.TRANSLATION'),
			'RETRACTED' => __('plugins.importexport.trdizin.type.RETRACTED'),
			'NOTE' => __('plugins.importexport.trdizin.type.NOTE'),
		);
	}

	/**
	 * Get the full list of TRDizin subject areas.
	 * @return array id => title
	 */
	function getTRDizinSubjects() {
		return array(
			11 => 'Acil Tıp',
			12 => 'Adli Tıp',
			13 => 'Aile Çalışmaları',
			14 => 'Akustik',
			15 => 'Alerji',
			16 => 'Anatomi ve Morfoloji',
			17 => 'Androloji',
			18 => 'Anestezi',
			19 => 'Antropoloji',
			20 => 'Arkeoloji',
			21 => 'Astronomi ve Astrofizik',
			22 => 'Asya Çalışmaları',
			23 => 'Bahçe Bitkileri',
			24 => 'Balıkçılık',
			25 => 'Beslenme ve Diyetetik',
			26 => 'Beşeri Bilimler',
			27 => 'Bilgi, Belge Yönetimi',
			28 => 'Bilgisayar Bilimleri, Bilgi Sistemleri',
			29 => 'Bilgisayar Bilimleri, Donanım ve Mimari',
			30 => 'Bilgisayar Bilimleri, Sibernitik',
			31 => 'Bilgisayar Bilimleri, Teori ve Metotlar',
			32 => 'Bilgisayar Bilimleri, Yapay Zeka',
			33 => 'Bilgisayar Bilimleri, Yazılım Mühendisliği',
			34 => 'Bilim Felsefesi ve Tarihi',
			35 => 'Bitki Bilimleri',
			36 => 'Biyofizik',
			37 => 'Biyokimya ve Moleküler Biyoloji',
			38 => 'Biyoloji',
			39 => 'Biyoloji Çeşitliliğinin Korunması',
			40 => 'Biyoteknoloji ve Uygulamalı Mikrobiyoloji',
			41 => 'Cerrahi',
			42 => 'Coğrafya',
			43 => 'Çevre Bilimleri',
			44 => 'Çevre Çalışmaları',
			45 => 'Çevre Mühendisliği',
			46 => 'Davranış Bilimleri',
			47 => 'Denizcilik',
			48 => 'Deniz ve Tatlı Su Biyolojisi',
			49 => 'Dermatoloji',
			50 => 'Dil ve Dil Bilim',
			51 => 'Din Bilimi',
			52 => 'Diş Hekimliği',
			53 => 'Edebi Teori ve Eleştiri',
			54 => 'Edebiyat',
			55 => 'Eğitim, Eğitim Araştırmaları',
			56 => 'Eğitim, Özel',
			57 => 'Ekoloji',
			58 => 'Endokrinoloji ve Metabolizma',
			59 => 'Endüstri Mühendisliği',
			60 => 'Enerji ve Yakıtlar',
			61 => 'Enfeksiyon Hastalıkları',
			62 => 'Entomoloji',
			63 => 'Ergonomi',
			64 => 'Etik',
			65 => 'Etnik Çalışmalar',
			66 => 'Farmakoloji ve Eczacılık',
			67 => 'Felsefe',
			68 => 'Film, Radyo, Televizyon',
			69 => 'Fizik, Akışkanlar ve Plazma',
			70 => 'Fizik, Atomik ve Moleküler Kimya',
			71 => 'Fizik, Katı Hal',
			72 => 'Fizik, Matematik',
			73 => 'Fizik, Nükleer',
			74 => 'Fizikokimya',
			75 => 'Fizik, Partiküller ve Alanlar',
			76 => 'Fizik, Uygulamalı',
			77 => 'Fizyoloji',
			78 => 'Folklor',
			79 => 'Gastroenteroloji ve Hepatoloji',
			80 => 'Genel ve Dahili Tıp',
			81 => 'Genetik ve Kalıtım',
			82 => 'Geriatri ve Gerontoloji',
			83 => 'Gıda Bilimi ve Teknolojisi',
			84 => 'Görüntüleme Bilimi ve Fotoğraf Teknolojisi',
			85 => 'Göz Hastalıkları',
			86 => 'Halkla İlişkiler',
			87 => 'Halk ve Çevre Sağlığı',
			88 => 'Hematoloji',
			89 => 'Hemşirelik',
			90 => 'Hukuk',
			91 => 'Hücre Biyolojisi',
			92 => 'Hücre ve Doku Mühendisliği',
			93 => 'İktisat',
			94 => 'İletişim',
			95 => 'İmalat Mühendisliği',
			96 => 'İmmünoloji',
			97 => 'İnşaat Mühendisliği',
			98 => 'İnşaat ve Yapı Teknolojisi',
			99 => 'İstatistik ve Olasılık',
			100 => 'İş',
			101 => 'İşletme',
			102 => 'İşletme Finans',
			103 => 'Jeokimya ve Jeofizik',
			104 => 'Jeoloji',
			105 => 'Kadın Araştırmaları',
			106 => 'Kadın Hastalıkları ve Doğum',
			107 => 'Kalp ve Kalp Damar Sistemi',
			108 => 'Kamu Yönetimi',
			109 => 'Kentsel Çalışmalar',
			110 => 'Kimya, Analitik',
			111 => 'Kimya, İnorganik ve Nükleer',
			112 => 'Kimya, Organik',
			113 => 'Kimya, Tıbbi',
			114 => 'Kimya, Uygulamalı',
			115 => 'Klinik Nöroloji',
			116 => 'Kriminoloji ve Ceza Bilimi',
			117 => 'Kulak, Burun, Boğaz',
			118 => 'Kuş Bilimi',
			119 => 'Kültürel Çalışmalar',
			120 => 'Limnoloji',
			121 => 'Madde Bağımlılığı',
			122 => 'Maden İşletme ve Cevher Hazırlama',
			123 => 'Malzeme Bilimleri, Biyomalzemeler',
			124 => 'Malzeme Bilimleri, Kâğıt ve Ahşap',
			125 => 'Malzeme Bilimleri, Kaplamalar ve Filmler',
			126 => 'Malzeme Bilimleri, Kompozitler',
			127 => 'Malzeme Bilimleri, Özellik ve Test',
			128 => 'Malzeme Bilimleri, Seramik',
			129 => 'Malzeme Bilimleri, Tekstil',
			130 => 'Mantar Bilimi',
			131 => 'Mantık',
			132 => 'Matematik',
			133 => 'Metalürji Mühendisliği',
			134 => 'Meteoroloji ve Atmosferik Bilimler',
			135 => 'Mikrobiyoloji',
			136 => 'Mikroskopi',
			137 => 'Mimarlık',
			138 => 'Mineraloji',
			139 => 'Mühendislik, Biyotıp',
			140 => 'Mühendislik, Deniz',
			141 => 'Mühendislik, Elektrik ve Elektronik',
			142 => 'Mühendislik, Hava ve Uzay',
			143 => 'Mühendislik, Jeoloji',
			144 => 'Mühendislik, Kimya',
			145 => 'Mühendislik, Makine',
			146 => 'Mühendislik, Petrol',
			147 => 'Müzik',
			148 => 'Nanobilim ve Nanoteknoloji',
			149 => 'Nörolojik Bilimler',
			150 => 'Nüfus İstatistikleri Bilimi',
			151 => 'Nükleer Bilim ve Teknolojisi',
			152 => 'Odyoloji ve Konuşma-Dil Patolojisi',
			153 => 'Onkoloji',
			154 => 'Optik',
			155 => 'Orman Mühendisliği',
			156 => 'Ortaçağ ve Rönesans Çalışmaları',
			157 => 'Ortopedi',
			158 => 'Oşinografi',
			159 => 'Otelcilik, Konaklama, Spor ve Turizm',
			160 => 'Paleontoloji',
			161 => 'Parazitoloji',
			162 => 'Patoloji',
			163 => 'Pediatri',
			164 => 'Periferik Damar Hastalıkları',
			165 => 'Polimer Bilimi',
			166 => 'Psikiyatri',
			167 => 'Psikoloji',
			168 => 'Radyoloji, Nükleer Tıp, Tıbbi Görüntüleme',
			169 => 'Rehabilitasyon',
			170 => 'Robotik',
			171 => 'Romatoloji',
			172 => 'Sağlık Bilimleri ve Hizmetleri',
			173 => 'Sağlık Politikaları ve Hizmetleri',
			174 => 'Sanat',
			175 => 'Savunma Bilimleri',
			176 => 'Siyasi Bilimler',
			177 => 'Solunum Sistemi',
			178 => 'Sosyal Çalışma',
			179 => 'Sosyoloji',
			180 => 'Spektroskopi',
			181 => 'Spor Bilimleri',
			182 => 'Su Kaynakları',
			183 => 'Tamamlayıcı ve Entegre Tıp',
			184 => 'Tarımsal Ekonomi ve Politika',
			185 => 'Tarih',
			186 => 'Taşınım',
			187 => 'Taşınım Bilimi ve Teknolojisi',
			188 => 'Telekomünikasyon',
			189 => 'Temel Sağlık Hizmetleri',
			190 => 'Termodinamik',
			191 => 'Tıbbi Araştırmalar Deneysel',
			192 => 'Tıbbi Etik',
			193 => 'Tıbbi İnformatik',
			194 => 'Tıbbi Laboratuar Teknolojisi',
			195 => 'Tiyatro',
			196 => 'Toksikoloji',
			197 => 'Transplantasyon',
			198 => 'Tropik Tıp',
			199 => 'Uluslararası İlişkiler',
			200 => 'Üroloji ve Nefroloji',
			201 => 'Veterinerlik',
			202 => 'Viroloji',
			203 => 'Yeşil, Sürdürülebilir Bilim ve Teknoloji',
			204 => 'Yoğun Bakım, Tıp',
			205 => 'Ziraat Mühendisliği',
			206 => 'Ziraat, Toprak Bilimi',
			207 => 'Zooloji',
		);
	}

	/**
	 * @copydoc ImportExportPlugin::executeCLI()
	 */
	function executeCLI($scriptName, &$args) {
		$command = array_shift($args);
		if ($command !== 'export') {
			$this->usage($scriptName);
			return;
		}

		$jsonFilename = array_shift($args);
		$journalPath = array_shift($args);
		$issueId = (int) array_shift($args);

		if (empty($jsonFilename) || empty($journalPath) || empty($issueId)) {
			$this->usage($scriptName);
			return;
		}

		$journalDao = DAORegistry::getDAO('JournalDAO');
		$journal = $journalDao->getByPath($journalPath);
		if (!$journal) {
			echo "Error: Journal not found with path: {$journalPath}\n";
			return;
		}

		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->getById($issueId, $journal->getId());
		if (!$issue) {
			echo "Error: Issue not found with ID: {$issueId}\n";
			return;
		}

		$sectionMapping = json_decode($this->getSetting($journal->getId(), 'sectionMapping') ?: '{}', true);
		$request = Application::get()->getRequest();
		$articlesData = $this->getArticlesDataForIssue($request, $journal, $issue, $sectionMapping);

		$jsonOutput = array();
		foreach ($articlesData as $articleData) {
			// For CLI, use defaults (no user overrides)
			$article = $this->buildArticleJson($articleData, array());
			$jsonOutput[] = $article;
		}

		$json = json_encode($jsonOutput, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		file_put_contents($jsonFilename, $json);
		echo "Exported " . count($jsonOutput) . " articles to {$jsonFilename}\n";
	}

	/**
	 * Build a single article JSON array from article data and overrides.
	 * @param $articleData array
	 * @param $overrides array
	 * @return array
	 */
	function buildArticleJson($articleData, $overrides) {
		$publicationType = !empty($overrides['publicationType']) ? $overrides['publicationType'] : $articleData['publicationType'];

		$article = array();
		$article['publicationType'] = $publicationType;

		if (!empty($articleData['startPage'])) {
			$article['startPage'] = (int) $articleData['startPage'];
		}
		if (!empty($articleData['endPage'])) {
			$article['endPage'] = (int) $articleData['endPage'];
		}
		if (!empty($articleData['pdfUrl'])) {
			$article['pdfFile'] = $articleData['pdfUrl'];
		}

		$article['publicationAbstractContents'] = array();
		foreach ($articleData['abstractContents'] as $content) {
			$article['publicationAbstractContents'][] = array(
				'title' => $content['title'],
				'abstractContent' => $content['abstract'],
				'keywords' => $content['keywords'],
				'publicationLanguage' => array('id' => $content['languageId']),
			);
		}

		if (!empty($articleData['supportingAgencies'])) {
			$funderName = is_array($articleData['supportingAgencies'])
				? implode('; ', $articleData['supportingAgencies'])
				: $articleData['supportingAgencies'];
			$article['funder'] = array('name' => $funderName);
		}

		if (!empty($overrides['subjects']) && is_array($overrides['subjects'])) {
			$article['publicationSubjects'] = array();
			foreach ($overrides['subjects'] as $subjectId) {
				$article['publicationSubjects'][] = array('id' => (int) $subjectId);
			}
		}

		if (!empty($articleData['references'])) {
			$article['publicationReferences'] = array();
			foreach ($articleData['references'] as $ref) {
				$article['publicationReferences'][] = array(
					'referenceFullText' => $ref['text'],
					'referenceOrder' => $ref['order'],
				);
			}
		}

		$article['publicationAuthorRelations'] = array();
		foreach ($articleData['authors'] as $authorIdx => $author) {
			$authorEntry = array(
				'inPublicationAuthorName' => $author['name'],
				'institutions' => array(),
				'authorType' => 'AUTHOR',
				'authorOrder' => $authorIdx + 1,
			);
			if (!empty($author['affiliation'])) {
				$authorEntry['institutions'][] = array(
					'inPublicationInstitutionName' => $author['affiliation'],
				);
			}
			if (!empty($author['orcid'])) {
				$orcid = $author['orcid'];
				if (strpos($orcid, 'orcid.org/') !== false) {
					$orcid = substr($orcid, strrpos($orcid, '/') + 1);
				}
				$authorEntry['orcid'] = $orcid;
			}
			$article['publicationAuthorRelations'][] = $authorEntry;
		}

		$article['publicationLanguage'] = array('id' => $articleData['languageId']);

		if (!empty($articleData['doi'])) {
			$article['publicationNumber'] = $articleData['doi'];
		}

		return $article;
	}

	/**
	 * @copydoc ImportExportPlugin::usage()
	 */
	function usage($scriptName) {
		echo __('plugins.importexport.trdizin.cliUsage', array(
			'scriptName' => $scriptName,
			'pluginName' => $this->getName()
		)) . "\n";
	}
}
