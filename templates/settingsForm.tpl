{**
 * plugins/importexport/trdizin/templates/settingsForm.tpl
 *
 * TRDizin JSON Export Plugin for OJS
 * Settings form template
 *}
<script type="text/javascript">
	$(function() {ldelim}
		$('#trdizinSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="trdizinSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" plugin="TRDizinExportPlugin" category="importexport" verb="save"}">
	{csrf}

	{fbvFormArea id="trdizinSettingsFormArea"}
		{* Section to Publication Type Mapping *}
		{fbvFormSection title="plugins.importexport.trdizin.settings.sectionMapping" description="plugins.importexport.trdizin.settings.sectionMappingDescription"}
			{if !empty($sections)}
				<table class="pkpTable">
					<thead>
						<tr>
							<th>{translate key="plugins.importexport.trdizin.settings.columnJournalSections"}</th>
							<th>{translate key="plugins.importexport.trdizin.settings.columnTrdizinTypes"}</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$sections item=section}
							<tr>
								<td>{$section.title|escape}</td>
								<td>
									<select name="sectionMapping[{$section.id}]" class="pkp_form_select">
										{foreach from=$publicationTypeOptions key=code item=label}
											<option value="{$code|escape}"{if isset($sectionMappingValues[$section.id]) && $sectionMappingValues[$section.id] == $code} selected="selected"{/if}>{$label|escape}</option>
										{/foreach}
									</select>
								</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
			{else}
				<p>{translate key="plugins.importexport.trdizin.issues.noIssues"}</p>
			{/if}
		{/fbvFormSection}

		{* Default Subject Areas *}
		{fbvFormSection title="plugins.importexport.trdizin.settings.defaultSubjects" description="plugins.importexport.trdizin.settings.defaultSubjectsDescription"}
			<select name="defaultSubjectIds[]" multiple="multiple" size="10" class="pkp_form_select" style="width:100%; min-height: 200px;">
				{foreach from=$trdizinSubjects key=id item=title}
					<option value="{$id|escape}"{if in_array($id, $defaultSubjectIdValues)} selected="selected"{/if}>{$title|escape}</option>
				{/foreach}
			</select>
			<p class="pkp_help">{translate key="plugins.importexport.trdizin.preview.subjectAreasHelp"}</p>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
