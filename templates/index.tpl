{**
 * plugins/importexport/trdizin/templates/index.tpl
 *
 * TRDizin JSON Export Plugin for OJS
 * Main plugin interface with tabs for settings and export
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}

<div class="trd-wrap">
	{* Page Header *}
	<div class="trd-page-hdr">
		<div class="trd-page-hdr-icon">&#128230;</div>
		<div>
			<h1>{$pageTitle}</h1>
			<p>{translate key="plugins.importexport.trdizin.description"}</p>
		</div>
	</div>

	<script type="text/javascript">
		$(function() {ldelim}
			$('#trdizinTabs').pkpHandler('$.pkp.controllers.TabHandler');
		{rdelim});
	</script>

	<div id="trdizinTabs">
		<ul>
			<li><a href="#settings-tab"><span class="trd-tab-ico">&#9881;</span> {translate key="plugins.importexport.trdizin.settings"}</a></li>
			<li><a href="#export-tab"><span class="trd-tab-ico">&#128230;</span> {translate key="plugins.importexport.trdizin.export"}</a></li>
		</ul>

		{* Settings Tab *}
		<div id="settings-tab">
			<div class="trd-content-card">
				{capture assign=trdizinSettingsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="TRDizinExportPlugin" category="importexport" verb="index" escape=false}{/capture}
				{load_url_in_div id="trdizinSettingsGridContainer" url=$trdizinSettingsGridUrl}
			</div>
		</div>

		{* Export Tab *}
		<div id="export-tab">
			<div class="trd-content-card">
				<h3>{translate key="plugins.importexport.trdizin.issues.title"}</h3>

				{if !empty($issues)}
					<table class="pkpTable">
						<thead>
							<tr>
								<th>{translate key="plugins.importexport.trdizin.issues.issue"}</th>
								<th>{translate key="plugins.importexport.trdizin.issues.volume"}</th>
								<th>{translate key="plugins.importexport.trdizin.issues.number"}</th>
								<th>{translate key="plugins.importexport.trdizin.issues.datePublished"}</th>
								<th>{translate key="plugins.importexport.trdizin.issues.numArticles"}</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$issues item=issue}
								<tr>
									<td>{$issue.title|escape}</td>
									<td>{$issue.volume|escape}</td>
									<td>{$issue.number|escape}</td>
									<td>{$issue.datePublished|date_format:"%Y-%m-%d"}</td>
									<td>{$issue.numArticles|escape}</td>
									<td>
										<a href="{plugin_url path="preview" issueId=$issue.id}" class="pkp_button">
											{translate key="plugins.importexport.trdizin.issues.preview"}
										</a>
									</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				{else}
					<p>{translate key="plugins.importexport.trdizin.issues.noIssues"}</p>
				{/if}
			</div>
		</div>
	</div>

	{* Footer *}
	<div class="trd-footer">
		{translate key="plugins.importexport.trdizin.footer"}
	</div>
</div>

{/block}
