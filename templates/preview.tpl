{**
 * plugins/importexport/trdizin/templates/preview.tpl
 *
 * TRDizin JSON Export Plugin for OJS
 * Preview/edit page for articles before JSON export
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}

<div class="trd">
	{* Back Link *}
	<a href="{$pluginUrl}" class="trd-back">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
		{translate key="plugins.importexport.trdizin.preview.backToList"}
	</a>

	{* Page Header *}
	<div class="trd-hdr">
		<h1>{translate key="plugins.importexport.trdizin.preview.title"}</h1>
		<p>{$issue->getIssueIdentification()|escape}</p>
	</div>

	{* Stats *}
	<div class="trd-stats">
		<div class="trd-st">
			<div class="trd-st-ico trd-st-ico--a">&#128196;</div>
			<div>
				<div class="trd-st-val">{$articlesData|@count}</div>
				<div class="trd-st-lbl">{translate key="plugins.importexport.trdizin.issues.numArticles"}</div>
			</div>
		</div>
		{if $totalWarnings > 0}
			<div class="trd-st trd-st--w">
				<div class="trd-st-ico trd-st-ico--w">&#9888;</div>
				<div>
					<div class="trd-st-val">{$totalWarnings}</div>
					<div class="trd-st-lbl">{translate key="plugins.importexport.trdizin.preview.warningCount" count=$totalWarnings}</div>
				</div>
			</div>
		{else}
			<div class="trd-st trd-st--s">
				<div class="trd-st-ico trd-st-ico--s">&#10003;</div>
				<div>
					<div class="trd-st-val">0</div>
					<div class="trd-st-lbl">{translate key="plugins.importexport.trdizin.preview.noWarnings"}</div>
				</div>
			</div>
		{/if}
	</div>

	{if empty($articlesData)}
		<div class="trd-empty">
			<p>{translate key="plugins.importexport.trdizin.preview.noArticles"}</p>
		</div>
	{else}
		<form method="post" action="{plugin_url path="export"}">
			{csrf}
			<input type="hidden" name="issueId" value="{$issue->getId()}" />

			{foreach from=$articlesData item=article}
				<div class="trd-card">
					{* Card Header *}
					<div class="trd-card-hd">
						<div class="trd-card-n">{$article.index + 1}</div>
						<div class="trd-card-tw">
							<h3 class="trd-card-t">{$article.title|escape}</h3>
						</div>
					</div>

					{* Meta Pills *}
					<div class="trd-meta">
						{if $article.doi}
							<a href="https://doi.org/{$article.doi|escape}" target="_blank" rel="noopener" class="trd-pill">DOI: <b>{$article.doi|escape}</b></a>
						{/if}
						{if $article.startPage}
							<span class="trd-pill">{translate key="plugins.importexport.trdizin.preview.pages"}: <b>{$article.startPage|escape}{if $article.endPage}-{$article.endPage|escape}{/if}</b></span>
						{/if}
						<span class="trd-pill">{translate key="plugins.importexport.trdizin.preview.language"}: <b>{$languageOptions[$article.languageId]|escape}</b></span>
					</div>

					<div class="trd-card-bd">
						{* Warnings *}
						{if !empty($article.warnings)}
							<div class="trd-warns">
								<div class="trd-warns-hd">&#9888; {$article.warnings|@count} {translate key="plugins.importexport.trdizin.preview.warningCount" count=$article.warnings|@count}</div>
								{foreach from=$article.warnings item=warning}
									<div class="trd-wi">&#8226; {$warning|escape}</div>
								{/foreach}
							</div>
						{/if}

						{* Abstract *}
						<div class="trd-sec">
							<div class="trd-sec-hd">
								<div class="trd-sec-ico trd-sec-ico--ab">&#128221;</div>
								<span class="trd-sec-lbl">{translate key="plugins.importexport.trdizin.preview.abstract"}</span>
							</div>
							{foreach from=$article.abstractContents item=content}
								<div class="trd-ab-blk">
									<div class="trd-ab-lang">
										<span class="trd-loc">{$content.locale|substr:0:2|upper}</span>
										<span class="trd-ab-ttl">{$content.title|escape|truncate:120:"..."}</span>
									</div>
									{if $content.abstract}
										<div class="trd-ab-txt">{$content.abstract|escape}</div>
									{/if}
								</div>
							{/foreach}
						</div>

						{* Keywords *}
						{assign var="hasKw" value=false}
						{foreach from=$article.abstractContents item=content}
							{if !empty($content.keywords)}
								{assign var="hasKw" value=true}
							{/if}
						{/foreach}
						{if $hasKw}
							<div class="trd-sec">
								<div class="trd-sec-hd">
									<div class="trd-sec-ico trd-sec-ico--kw">&#128278;</div>
									<span class="trd-sec-lbl">{translate key="plugins.importexport.trdizin.preview.keywords"}</span>
								</div>
								{foreach from=$article.abstractContents item=content}
									{if !empty($content.keywords)}
										<div class="trd-kw-blk">
											<div class="trd-kw-lang">
												<span class="trd-loc">{$content.locale|substr:0:2|upper}</span>
											</div>
											<div class="trd-kw-list">
												{foreach from=$content.keywords item=keyword}
													<span class="trd-kw-tag">{$keyword|escape}</span>
												{/foreach}
											</div>
										</div>
									{/if}
								{/foreach}
							</div>
						{/if}

						{* Authors *}
						<div class="trd-sec">
							<div class="trd-sec-hd">
								<div class="trd-sec-ico trd-sec-ico--au">&#128100;</div>
								<span class="trd-sec-lbl">{translate key="plugins.importexport.trdizin.preview.authors"}</span>
								<span class="trd-sec-cnt">{$article.authors|@count}</span>
							</div>
							<table class="trd-au">
								<thead>
									<tr>
										<th style="width:30px">#</th>
										<th>{translate key="user.name"}</th>
										<th>{translate key="plugins.importexport.trdizin.preview.institution"}</th>
										<th>ORCID</th>
									</tr>
								</thead>
								<tbody>
									{foreach from=$article.authors item=author name=authorLoop}
										<tr>
											<td style="text-align:center;color:#7f8c8d">{$smarty.foreach.authorLoop.iteration}</td>
											<td class="trd-au-nm">{$author.name|escape}</td>
											<td>
												{if $author.affiliation}
													{$author.affiliation|escape}
												{else}
													<span class="trd-b-no">&#10007; {translate key="plugins.importexport.trdizin.preview.institution"}</span>
												{/if}
											</td>
											<td>
												{if $author.orcid}
													<a href="{if strpos($author.orcid, 'http') === 0}{$author.orcid|escape}{else}https://orcid.org/{$author.orcid|escape}{/if}" target="_blank" rel="noopener" class="trd-b-ok">&#10003; {$author.orcid|escape}</a>
												{else}
													<span class="trd-b-no">&#10007; ORCID</span>
												{/if}
											</td>
										</tr>
									{/foreach}
								</tbody>
							</table>
						</div>

						{* Type & Subjects *}
						<div class="trd-sec">
							<div class="trd-sec-hd">
								<div class="trd-sec-ico trd-sec-ico--st">&#9881;</div>
								<span class="trd-sec-lbl">{translate key="plugins.importexport.trdizin.preview.articleType"} &amp; {translate key="plugins.importexport.trdizin.preview.subjectAreas"}</span>
							</div>
							<div class="trd-fg">
								<div class="trd-fd">
									<label class="trd-fd-lbl">{translate key="plugins.importexport.trdizin.preview.articleType"}</label>
									<select name="articles[{$article.index}][publicationType]" class="trd-sel">
										{foreach from=$publicationTypeOptions key=code item=label}
											<option value="{$code|escape}"{if $article.publicationType == $code} selected="selected"{/if}>{$label|escape}</option>
										{/foreach}
									</select>
								</div>
								<div class="trd-fd">
									<label class="trd-fd-lbl">
										{translate key="plugins.importexport.trdizin.preview.subjectAreas"}
										<span class="trd-fd-hint">({translate key="plugins.importexport.trdizin.preview.subjectAreasHelp"})</span>
									</label>
									<select name="articles[{$article.index}][subjects][]" multiple="multiple" class="trd-sel trd-sel--m">
										{foreach from=$trdizinSubjects key=id item=title}
											<option value="{$id|escape}"{if in_array($id, $defaultSubjectIds)} selected="selected"{/if}>{$title|escape}</option>
										{/foreach}
									</select>
								</div>
							</div>
						</div>

						<input type="hidden" name="articles[{$article.index}][submissionId]" value="{$article.submissionId}" />

						{* DOI & PDF *}
						<div class="trd-sec">
							<div class="trd-sec-hd">
								<div class="trd-sec-ico trd-sec-ico--in">&#128279;</div>
								<span class="trd-sec-lbl">{translate key="plugins.importexport.trdizin.preview.doi"} &amp; PDF</span>
							</div>
							<div class="trd-ig">
								<div class="trd-ib">
									<span class="trd-ib-lbl">{translate key="plugins.importexport.trdizin.preview.doi"}</span>
									<div class="trd-ib-val">
										{if $article.doi}
											<a href="https://doi.org/{$article.doi|escape}" target="_blank" rel="noopener">{$article.doi|escape}</a>
										{else}
											<span class="trd-ib-empty">&mdash;</span>
										{/if}
									</div>
								</div>
								<div class="trd-ib">
									<span class="trd-ib-lbl">{translate key="plugins.importexport.trdizin.preview.pdfUrl"}</span>
									<div class="trd-ib-val">
										{if $article.pdfUrl}
											<a href="{$article.pdfUrl|escape}" target="_blank" rel="noopener">{$article.pdfUrl|escape|truncate:55:"..."}</a>
										{else}
											<span class="trd-ib-empty">&mdash;</span>
										{/if}
									</div>
								</div>
							</div>
						</div>

						{* References *}
						{if !empty($article.references)}
							<div class="trd-sec">
								<div class="trd-sec-hd">
									<div class="trd-sec-ico trd-sec-ico--rf">&#128218;</div>
									<span class="trd-sec-lbl">{translate key="plugins.importexport.trdizin.preview.references"}</span>
									<span class="trd-sec-cnt">{$article.references|@count}</span>
								</div>
								<div class="trd-refs">
									<ol>
										{foreach from=$article.references item=ref}
											<li>{$ref.text|escape}</li>
										{/foreach}
									</ol>
								</div>
							</div>
						{/if}
					</div>
				</div>
			{/foreach}

			{* Download Bar *}
			<div class="trd-dl">
				<span class="trd-dl-info">{$articlesData|@count} {translate key="plugins.importexport.trdizin.issues.numArticles"}</span>
				<button type="submit" class="trd-dl-btn">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
					{translate key="plugins.importexport.trdizin.preview.downloadJson"}
				</button>
			</div>
		</form>
	{/if}

	{* Footer *}
	<div class="trd-footer">
		{translate key="plugins.importexport.trdizin.footer"}
	</div>
</div>

{/block}
