<script type="text/x-jquery-tmpl" id="mapElementFormTpl">
	<div class="dashbrd-widget-head">
		<h4 id="formDragHandler">Map element</h4>
	</div>
	<form id="selementForm" name="selementForm">
		<input type="hidden" id="elementid" name="elementid">
		<table id="elementFormTable" class="table-forms">
			<tbody>
			<tr>
				<td class="table-forms-td-left">
					<label for="elementType"><?= _('Type') ?></label>
				</td>
				<td class="table-forms-td-right">
					<select class="input select" name="elementtype" id="elementType">
						<option value="<?= SYSMAP_ELEMENT_TYPE_HOST ?>"><?= _('Host') ?></option>
						<option value="<?= SYSMAP_ELEMENT_TYPE_MAP ?>"><?= _('Map') ?></option>
						<option value="<?= SYSMAP_ELEMENT_TYPE_TRIGGER ?>"><?= _('Trigger') ?></option>
						<option value="<?= SYSMAP_ELEMENT_TYPE_HOST_GROUP ?>"><?= _('Host group') ?></option>
						<option value="<?= SYSMAP_ELEMENT_TYPE_IMAGE ?>"><?= _('Image') ?></option>
					</select>
				</td>
			</tr>
			<tr id="subtypeRow">
				<td class="table-forms-td-left"><?= _('Show') ?></td>
				<td class="table-forms-td-right">
					<div class="groupingContent">
						<input id="subtypeHostGroup" type="radio" class="input radio" name="elementsubtype" value="0" checked="checked">
						<label for="subtypeHostGroup"><?= _('Host group') ?></label>
						<br />
						<input id="subtypeHostGroupElements" type="radio" class="input radio" name="elementsubtype" value="1">
						<label for="subtypeHostGroupElements"><?= _('Host group elements') ?></label>
					</div>
				</td>
			</tr>
			<tr id="areaTypeRow">
				<td class="table-forms-td-left"><?= _('Area type') ?></td>
				<td class="table-forms-td-right">
					<div class="groupingContent">
						<input id="areaTypeAuto" type="radio" class="input radio" name="areatype" value="0" checked="checked">
						<label for="areaTypeAuto"><?= _('Fit to map') ?></label>
						<br />
						<input id="areaTypeCustom" type="radio" class="input radio" name="areatype" value="1">
						<label for="areaTypeCustom"><?= _('Custom size') ?></label>
					</div>
				</td>
			</tr>
			<tr id="areaSizeRow">
				<td class="table-forms-td-left"><?= _('Area size') ?></td>
				<td class="table-forms-td-right">
					<label for="areaSizeWidth"><?= _('Width') ?></label>
					<input id="areaSizeWidth" type="text" class="input text" name="width" value="200" style="width: <?= ZBX_TEXTAREA_TINY_WIDTH ?>px">
					<label for="areaSizeHeight"><?= _('Height') ?></label>
					<input id="areaSizeHeight" type="text" class="input text" name="height" value="200" style="width: <?= ZBX_TEXTAREA_TINY_WIDTH ?>px">
				</td>
			</tr>
			<tr id="areaPlacingRow">
				<td class="table-forms-td-left">
					<label for="areaPlacing"><?= _('Placing algorithm') ?></label>
				</td>
				<td class="table-forms-td-right">
					<select id="areaPlacing" class="input select">
						<option value="<?= SYSMAP_ELEMENT_AREA_VIEWTYPE_GRID ?>"><?= _('Grid') ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="table-forms-td-left">
					<label for="elementLabel"><?= _('Label') ?></label>
				</td>
				<td class="table-forms-td-right">
					<textarea id="elementLabel" cols="56" rows="4" name="label" class="input" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px"></textarea>
				</td>
			</tr>
			<tr>
				<td class="table-forms-td-left">
					<label for="label_location"><?= _('Label location') ?></label>
				</td>
				<td class="table-forms-td-right">
					<select id="label_location" class="input select" name="label_location">
						<option value="<?= MAP_LABEL_LOC_DEFAULT ?>"><?= _('Default') ?></option>
						<option value="<?= MAP_LABEL_LOC_BOTTOM ?>"><?= _('Bottom') ?></option>
						<option value="<?= MAP_LABEL_LOC_LEFT ?>"><?= _('Left') ?></option>
						<option value="<?= MAP_LABEL_LOC_RIGHT ?>"><?= _('Right') ?></option>
						<option value="<?= MAP_LABEL_LOC_TOP ?>"><?= _('Top') ?></option>
					</select>
				</td>
			</tr>
			<tr id="hostGroupSelectRow">
				<td class="table-forms-td-left"><?= _('Host group') ?></td>
				<td class="table-forms-td-right">
					<div id="elementNameHostGroup" class="multiselect" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px"></div>
				</td>
			</tr>
			<tr id="hostSelectRow">
				<td class="table-forms-td-left"><?= _('Host') ?></td>
				<td class="table-forms-td-right">
					<div id="elementNameHost" class="multiselect" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px"></div>
				</td>
			</tr>
			<tr id="triggerSelectRow">
				<td class="table-forms-td-left"><?= _('Trigger') ?></td>
				<td class="table-forms-td-right">
					<input type="hidden" id="elementExpressionTrigger" name="elementExpressionTrigger">
					<input readonly="readonly" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px" id="elementNameTrigger" name="elementName" class="text input" type="text">
					<button type="button" class="btn-grey" onclick="PopUp('popup.php?writeonly=1&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementNameTrigger&dstfld3=elementExpressionTrigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description&srcfld3=expression&with_triggers=1&real_hosts=1&noempty=1')"><?= _('Select') ?></button>
				</td>
			</tr>
			<tr id="mapSelectRow">
				<td class="table-forms-td-left"><?= _('Map') ?></td>
				<td class="table-forms-td-right">
					<input readonly="readonly" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px" id="elementNameMap" name="elementName" class="input" type="text">
					<button type="button" class="btn-grey" onclick='PopUp("popup.php?srctbl=sysmaps&srcfld1=sysmapid&srcfld2=name&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementNameMap&writeonly=1&excludeids[]=#{sysmapid}")'><?= _('Select') ?></button>
				</td>
			</tr>
			<tr id="application-select-row">
				<td class="table-forms-td-left"><?= _('Application') ?></td>
				<td class="table-forms-td-right">
					<input id="application" name="application" class="input text" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px" type="text">
					<button class="<?= ZBX_STYLE_BTN_GREY ?>" id="application-select" type="button"><?= _('Select') ?></button>
				</td>
			</tr>

			<tr>
				<td class="table-forms-td-right" colspan="2">
					<fieldset>
						<legend><?= _('Icons') ?></legend>
						<table>
							<tbody>
							<tr id="useIconMapRow">
								<td colspan="2">
									<label for="use_iconmap" id=use_iconmapLabel>
										<input type="checkbox" name="use_iconmap" id="use_iconmap" class="checkbox" value="1">
										<?= _('Automatic icon selection') ?>
									</label>
								</td>
							</tr>
							<tr>
								<td>
									<label for="iconid_off"><?= _('Default') ?></label>
									<select class="input select" name="iconid_off" id="iconid_off"></select>
								</td>
								<td id="iconProblemRow">
									<label for="iconid_on"><?= _('Problem') ?></label>
									<select class="input select" name="iconid_on" id="iconid_on"></select>
								</td>
							</tr>
							<tr>
								<td id="iconMainetnanceRow">
									<label for="iconid_maintenance"><?= _('Maintenance') ?></label>
									<select class="input select" name="iconid_maintenance" id="iconid_maintenance"></select>
								</td>
								<td id="iconDisabledRow">
									<label for="iconid_disabled"><?= _('Disabled') ?></label>
									<select class="input select" name="iconid_disabled" id="iconid_disabled"></select>
								</td>
							</tr>
							</tbody>
						</table>
					</fieldset>
				</td>
			</tr>

			<tr>
				<td class="table-forms-td-left"><?= _('Coordinates') ?></td>
				<td class="table-forms-td-right">
					<input id="x" type="number" maxlength="5" value="0" style="width: <?= ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH ?>px" name="x" class="input">
					<label for="x"><?= _('X') ?></label>
					<input type="number" maxlength="5" value="0" style="width: <?= ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH ?>px" id="y" name="y" class="input">
					<label for="y"><?= _('Y') ?></label>
				</td>
			</tr>

			<tr>
				<td class="table-forms-td-right" colspan="2">
					<fieldset>
						<legend><?= _('URLs') ?></legend>
						<table>
							<thead>
							<tr>
								<td><label><?= _('Name') ?></label></td>
								<td><label><?= _('URL') ?></label></td>
								<td></td>
							</tr>
							</thead>
							<tbody id="urlContainer"></tbody>
							<tfoot>
							<tr>
								<td colspan=3>
									<button class="<?= ZBX_STYLE_BTN_LINK ?>" type="button" id="newSelementUrl"><?= _('Add') ?></button>
								</td>
							</tr>
							</tfoot>
						</table>
					</fieldset>
				</td>
			</tr>
			</tbody>
			<tfoot>
			<tr class="footer">
				<td colspan="2" class="form_row_last">
					<button class="element-edit-control" id="elementApply" type="button"><?= _('Apply') ?></button>
					<button class="element-edit-control" id="elementRemove" type="button"><?= _('Remove') ?></button>
					<button id="elementClose" type="button"><?= _('Close') ?></button>
				</td>
			</tr>
			</tfoot>
		</table>
	</form>
</script>

<script type="text/x-jquery-tmpl" id="mapMassFormTpl">
	<form id="massForm">
		<table class="formtable">
			<tbody>
			<tr class="header">
				<td id="massDragHandler" colspan="2" class="form_row_first move">
					<?= _('Mass update elements') ?>&nbsp;
					(<span id="massElementCount"></span>&nbsp;<?= _('elements') ?>)
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<?= _('Selected elements') ?>:
					<div id="elements-selected">
						<table class="tableinfo">
							<tbody id="massList"></tbody>
						</table>
					</div>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_label" id="chkboxLabel" class="checkbox">
					<label for="chkboxLabel"><?= _('Label') ?></label>
				</td>
				<td>
					<textarea id="massLabel" rows="4" name="label" class="input" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px"></textarea>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_label_location" id="chkboxLabelLocation" class="checkbox">
					<label for="chkboxLabelLocation"><?= _('Label location') ?></label>
				</td>
				<td>
					<select id="massLabelLocation" class="input select" name="label_location">
						<option value="<?= MAP_LABEL_LOC_DEFAULT ?>"><?= _('Default') ?></option>
						<option value="<?= MAP_LABEL_LOC_BOTTOM ?>"><?= _('Bottom') ?></option>
						<option value="<?= MAP_LABEL_LOC_LEFT ?>"><?= _('Left') ?></option>
						<option value="<?= MAP_LABEL_LOC_RIGHT ?>"><?= _('Right') ?></option>
						<option value="<?= MAP_LABEL_LOC_TOP ?>"><?= _('Top') ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_use_iconmap" id="chkboxMassUseIconmap" class="checkbox">
					<label for="chkboxMassUseIconmap"><?= _('Automatic icon selection') ?></label>
				</td>
				<td>
					<input type="checkbox" name="use_iconmap" id="massUseIconmap" class="checkbox" value="1">
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_iconid_off" id="chkboxMassIconidOff" class="checkbox">
					<label for="chkboxMassIconidOff"><?= _('Icon (default)') ?></label>
				</td>
				<td>
					<select class="input select" name="iconid_off" id="massIconidOff"></select>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_iconid_on" id="chkboxMassIconidOn" class="checkbox">
					<label for="chkboxMassIconidOn"><?= _('Icon (problem)') ?></label>
				</td>
				<td>
					<select class="input select" name="iconid_on" id="massIconidOn"></select>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_iconid_maintenance" id="chkboxMassIconidMaintenance" class="checkbox">
					<label for="chkboxMassIconidMaintenance"><?= _('Icon (maintenance)') ?></label>
				</td>
				<td>
					<select class="input select" name="iconid_maintenance" id="massIconidMaintenance"></select>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_iconid_disabled" id="chkboxMassIconidDisabled" class="checkbox">
					<label for="chkboxMassIconidDisabled"><?= _('Icon (disabled)') ?></label>
				</td>
				<td>
					<select class="input select" name="iconid_disabled" id="massIconidDisabled"></select>
				</td>
			</tr>
			<tr class="footer">
				<td colspan="2" class="form_row_last">
					<button class="element-edit-control" id="massApply" type="button"><?= _('Apply') ?></button>
					<button class="element-edit-control" id="massRemove" type="button"><?= _('Remove') ?></button>
					<button id="massClose" type="button"><?= _('Close') ?></button>
				</td>
			</tr>
			</tbody>
		</table>
	</form>
</script>

<script type="text/x-jquery-tmpl" id="mapMassFormListRow">
	<tr>
		<td>#{elementType}</td>
		<td>#{elementName}</td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="linkFormTpl">
	<div id="mapLinksContainer">
		<table id="element-links" class="tableinfo element-links">
			<caption><?= _('Links for the selected element') ?></caption>
			<thead>
			<tr class="header">
				<td></td>
				<td><?= _('Element name') ?></td>
				<td><?= _('Link indicators') ?></td>
			</tr>
			</thead>
			<tbody></tbody>
		</table>
		<table id="mass-element-links" class="tableinfo element-links">
			<caption><?= _('Links between the selected elements') ?></caption>
			<thead>
			<tr class="header">
				<td></td>
				<td><?= _('From') ?></td>
				<td><?= _('To') ?></td>
				<td><?= _('Link indicators') ?></td>
			</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
	<form id="linkForm" name="linkForm">
		<input type="hidden" name="selementid1">

		<table class="formtable">
			<tbody>
			<tr>
				<td>
					<label for="linklabel"><?= _('Label') ?></label>
				</td>
				<td>
					<textarea cols="48" rows="4" name="label" id="linklabel" class="input" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px"></textarea>
				</td>
			</tr>
			<tr id="link-connect-to">
				<td>
					<label for="selementid2"><?= _('Connect to') ?></label>
				</td>
				<td>
					<select class="input select" name="selementid2" id="selementid2"></select>
				</td>
			</tr>
			<tr>
				<td>
					<label for="drawtype"><?= _('Type (OK)') ?></label>
				</td>
				<td>
					<select class="input select" name="drawtype" id="drawtype">
						<option value="<?= GRAPH_ITEM_DRAWTYPE_LINE ?>"><?= _('Line') ?></option>
						<option value="<?= GRAPH_ITEM_DRAWTYPE_BOLD_LINE ?>"><?= _('Bold line') ?></option>
						<option value="<?= GRAPH_ITEM_DRAWTYPE_DOT ?>"><?= _('Dot') ?></option>
						<option value="<?= GRAPH_ITEM_DRAWTYPE_DASHED_LINE ?>"><?= _('Dashed line') ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label for="color"><?= _('Colour (OK)') ?></label>
				</td>
				<td>
					<div class="<?= ZBX_STYLE_INPUT_COLOR_PICKER ?>">
						<div name="lbl_color" id="lbl_color" style="background: #{color}" title="#{color}" onclick="javascript: show_color_picker('color')"></div>
						<input id="color" name="color" value="#{color}" class="input colorpicker" maxlength="6" style="width: <?= ZBX_TEXTAREA_COLOR_WIDTH ?>px" onchange="set_color_by_name('color', this.value)" type="text">
					</div>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<fieldset>
						<legend><?= _('Link indicators') ?></legend>
						<table>
							<thead>
							<tr>
								<td><?= _('Triggers') ?></td>
								<td><?= _('Type') ?></td>
								<td><?= _('Colour') ?></td>
								<td></td>
							</tr>
							</thead>
							<tbody id="linkTriggerscontainer"></tbody>
							<tfoot>
							<tr>
								<td colspan="4">
									<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" onclick="PopUp('popup.php?srctbl=triggers&srcfld1=triggerid&real_hosts=1&reference=linktrigger&multiselect=1&writeonly=1&with_triggers=1&noempty=1');"><?= _('Add') ?></button>
								</td>
							</tr>
							</tfoot>
						</table>
					</fieldset>
				</td>
			</tr>
			<tr class="footer">
				<td colspan="2" class="form_row_last">
					<button id="formLinkApply" type="button"><?= _('Apply') ?></button>
					<button id="formLinkRemove" type="button"><?= _('Remove') ?></button>
					<button id="formLinkClose" type="button"><?= _('Close') ?></button>
				</td>
			</tr>
			</tbody>
		</table>
	</form>
</script>

<script type="text/x-jquery-tmpl" id="elementLinkTableRowTpl">
	<tr>
		<td>
			<button class="<?= ZBX_STYLE_BTN_LINK ?> openlink" type="button" data-linkid="#{linkid}"><?= _('Edit') ?></button>
		</td>
		<td>#{toElementName}</td>
		<td class="pre">#{linktriggers}</td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="massElementLinkTableRowTpl">
	<tr>
		<td>
			<button class="<?= ZBX_STYLE_BTN_LINK ?> openlink" type="button" data-linkid="#{linkid}"><?= _('Edit') ?></button>
		</td>
		<td>#{fromElementName}</td>
		<td>#{toElementName}</td>
		<td class="pre">#{linktriggers}</td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="linkTriggerRow">
	<tr id="linktrigger_#{linktriggerid}">
		<td>#{desc_exp}</td>
		<td>
			<input type="hidden" name="linktrigger_#{linktriggerid}_desc_exp" value="#{desc_exp}" />
			<input type="hidden" name="linktrigger_#{linktriggerid}_triggerid" value="#{triggerid}" />
			<input type="hidden" name="linktrigger_#{linktriggerid}_linktriggerid" value="#{linktriggerid}" />
			<select id="linktrigger_#{linktriggerid}_drawtype" name="linktrigger_#{linktriggerid}_drawtype" class="input select">
				<option value="<?= GRAPH_ITEM_DRAWTYPE_LINE ?>"><?= _('Line') ?></option>
				<option value="<?= GRAPH_ITEM_DRAWTYPE_BOLD_LINE ?>"><?= _('Bold line') ?></option>
				<option value="<?= GRAPH_ITEM_DRAWTYPE_DOT ?>"><?= _('Dot') ?></option>
				<option value="<?= GRAPH_ITEM_DRAWTYPE_DASHED_LINE ?>"><?= _('Dashed line') ?></option>
			</select>
		</td>
		<td>
			<div class="<?= ZBX_STYLE_INPUT_COLOR_PICKER ?>">
				<div name="lbl_linktrigger_#{linktriggerid}_color" id="lbl_linktrigger_#{linktriggerid}_color" style="background: #{color}" title="#{color}" onclick="javascript: show_color_picker('linktrigger_#{linktriggerid}_color')"></div>
				<input id="linktrigger_#{linktriggerid}_color" name="linktrigger_#{linktriggerid}_color" value="#{color}" class="input colorpicker" maxlength="6" style="width: <?= ZBX_TEXTAREA_COLOR_WIDTH ?>px" onchange="set_color_by_name('linktrigger_#{linktriggerid}_color', this.value)" type="text">
			</div>
		</td>
		<td>
			<button class="<?= ZBX_STYLE_BTN_LINK ?> triggerRemove" type="button" data-linktriggerid="#{linktriggerid}"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="selementFormUrls">
	<tr id="urlrow_#{selementurlid}" class="even_row">
		<td><input class="input" name="url_#{selementurlid}_name" type="text" style="width: <?= ZBX_TEXTAREA_SMALL_WIDTH ?>px" value="#{name}"></td>
		<td>
			<input class="input" name="url_#{selementurlid}_url" type="text" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px" value="#{url}">
			<button class="<?= ZBX_STYLE_BTN_LINK ?>" type="button" onclick="jQuery('#urlrow_#{selementurlid}').remove();"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>

<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery('.print-link').click(function () {
		ZABBIX.apps.map.object.updateImage();

		jQuery('div.printless').unbind('click').click(function () {
			printLess(false);
			ZABBIX.apps.map.object.updateImage();

			return false;
		});

		return false;
	});
})

/**
 * @see init.js add.popup event
 */
function addPopupValues(data) {
	if (data.object === 'name') {
		jQuery('#application').val(data.values[0].name);
	}
	else if (data.object === 'linktrigger') {
		ZABBIX.apps.map.object.linkForm.addNewTriggers(data.values);
	}
}
</script>
