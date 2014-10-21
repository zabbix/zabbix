<script type="text/x-jquery-tmpl" id="mapElementFormTpl">
	<form id="selementForm" name="selementForm">
		<input type="hidden" id="elementid" name="elementid">
		<table id="elementFormTable" class="formtable">
			<thead>
			<tr class="header">
				<td id="formDragHandler" colspan="2" class="form_row_first move"><?php echo _('Edit map element'); ?></td>
			</tr>
			</thead>
			<tbody>
			<tr>
				<td>
					<label for="elementType"><?php echo _('Type'); ?></label>
				</td>
				<td>
					<select size="1" class="input select" name="elementtype" id="elementType">
						<option value="<?php echo SYSMAP_ELEMENT_TYPE_HOST; ?>"><?php echo _('Host'); ?></option>
						<option value="<?php echo SYSMAP_ELEMENT_TYPE_MAP; ?>"><?php echo _('Map'); ?></option>
						<option value="<?php echo SYSMAP_ELEMENT_TYPE_TRIGGER; ?>"><?php echo _('Trigger'); ?></option>
						<option value="<?php echo SYSMAP_ELEMENT_TYPE_HOST_GROUP; ?>"><?php echo _('Host group'); ?></option>
						<option value="<?php echo SYSMAP_ELEMENT_TYPE_IMAGE; ?>"><?php echo _('Image'); ?></option>
					</select>
				</td>
			</tr>
			<tr id="subtypeRow">
				<td><?php echo _('Show'); ?></td>
				<td>
					<div class="groupingContent">
						<input id="subtypeHostGroup" type="radio" class="input radio" name="elementsubtype" value="0" checked="checked">
						<label for="subtypeHostGroup"><?php echo _('Host group'); ?></label>
						<br />
						<input id="subtypeHostGroupElements" type="radio" class="input radio" name="elementsubtype" value="1">
						<label for="subtypeHostGroupElements"><?php echo _('Host group elements'); ?></label>
					</div>
				</td>
			</tr>
			<tr id="areaTypeRow">
				<td><?php echo _('Area type'); ?></td>
				<td>
					<div class="groupingContent">
						<input id="areaTypeAuto" type="radio" class="input radio" name="areatype" value="0" checked="checked">
						<label for="areaTypeAuto"><?php echo _('Fit to map'); ?></label>
						<br />
						<input id="areaTypeCustom" type="radio" class="input radio" name="areatype" value="1">
						<label for="areaTypeCustom"><?php echo _('Custom size'); ?></label>
					</div>
				</td>
			</tr>
			<tr id="areaSizeRow">
				<td><?php echo _('Area size'); ?></td>
				<td>
					<label for="areaSizeWidth"><?php echo _('Width'); ?></label>
					<input id="areaSizeWidth" type="text" class="input text" name="width" value="200" size="5">
					<label for="areaSizeHeight"><?php echo _('Height'); ?></label>
					<input id="areaSizeHeight" type="text" class="input text" name="height" value="200" size="5">
				</td>
			</tr>
			<tr id="areaPlacingRow">
				<td>
					<label for="areaPlacing"><?php echo _('Placing algorithm'); ?></label>
				</td>
				<td>
					<select id="areaPlacing" class="input select">
						<option value="<?php echo SYSMAP_ELEMENT_AREA_VIEWTYPE_GRID; ?>"><?php echo _('Grid'); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label for="elementLabel"><?php echo _('Label'); ?></label>
				</td>
				<td>
					<textarea id="elementLabel" cols="56" rows="4" name="label" class="input textarea_standard"></textarea>
				</td>
			</tr>
			<tr>
				<td>
					<label for="label_location"><?php echo _('Label location'); ?></label>
				</td>
				<td>
					<select id="label_location" class="input select" name="label_location">
						<option value="<?php echo MAP_LABEL_LOC_DEFAULT; ?>"><?php echo _('Default'); ?></option>
						<option value="<?php echo MAP_LABEL_LOC_BOTTOM; ?>"><?php echo _('Bottom'); ?></option>
						<option value="<?php echo MAP_LABEL_LOC_LEFT; ?>"><?php echo _('Left'); ?></option>
						<option value="<?php echo MAP_LABEL_LOC_RIGHT; ?>"><?php echo _('Right'); ?></option>
						<option value="<?php echo MAP_LABEL_LOC_TOP; ?>"><?php echo _('Top'); ?></option>
					</select>
				</td>
			</tr>
			<tr id="hostGroupSelectRow">
				<td><?php echo _('Host group'); ?></td>
				<td>
					<div id="elementNameHostGroup" class="multiselect" style="width: 312px;"></div>
				</td>
			</tr>
			<tr id="hostSelectRow">
				<td><?php echo _('Host'); ?></td>
				<td>
					<div id="elementNameHost" class="multiselect" style="width: 312px;"></div>
				</td>
			</tr>
			<tr id="triggerSelectRow">
				<td><?php echo _('Trigger'); ?></td>
				<td>
					<input readonly="readonly" size="50" id="elementNameTrigger" name="elementName" class="input">
					<input type="hidden" id="elementExpressionTrigger" name="elementExpressionTrigger">
					<span class="link" onclick="PopUp('popup.php?writeonly=1&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementNameTrigger&dstfld3=elementExpressionTrigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description&srcfld3=expression&with_triggers=1&real_hosts=1&noempty=1',450,450)"><?php echo _('Select'); ?></span>
				</td>
			</tr>
			<tr id="mapSelectRow">
				<td><?php echo _('Map'); ?></td>
				<td>
					<input readonly="readonly" size="50" id="elementNameMap" name="elementName" class="input">
					<span class="link" onclick='PopUp("popup.php?srctbl=sysmaps&srcfld1=sysmapid&srcfld2=name&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementNameMap&writeonly=1&excludeids[]=#{sysmapid}", 450, 450)'><?php echo _('Select'); ?></span>
				</td>
			</tr>
			<tr id="application-select-row">
				<td><?php echo _('Application'); ?></td>
				<td>
					<input size="50" id="application" name="application" class="input text"><input id="application-select" type="button" class="input link_menu select-popup" value="<?php echo _('Select'); ?>">
				</td>
			</tr>

			<tr>
				<td colspan="2">
					<fieldset>
						<legend><?php echo _('Icons'); ?></legend>
						<table>
							<tbody>
							<tr id="useIconMapRow">
								<td colspan="2">
									<label for="use_iconmap" id=use_iconmapLabel><?php echo _('Automatic icon selection'); ?></label>
									<input type="checkbox" name="use_iconmap" id="use_iconmap" class="checkbox" value="1">
								</td>
							</tr>
							<tr>
								<td>
									<label for="iconid_off"><?php echo _('Default'); ?></label>
									<br />
									<select class="input select" name="iconid_off" id="iconid_off"></select>
								</td>
								<td id="iconProblemRow">
									<label for="iconid_on"><?php echo _('Problem'); ?></label>
									<br />
									<select class="input select" name="iconid_on" id="iconid_on"></select>
								</td>
							</tr>
							<tr>
								<td id="iconMainetnanceRow">
									<label for="iconid_maintenance"><?php echo _('Maintenance'); ?></label>
									<br />
									<select class="input select" name="iconid_maintenance" id="iconid_maintenance"></select>
								</td>
								<td id="iconDisabledRow">
									<label for="iconid_disabled"><?php echo _('Disabled'); ?></label>
									<br />
									<select class="input select" name="iconid_disabled" id="iconid_disabled"></select>
								</td>
							</tr>
							</tbody>
						</table>
					</fieldset>
				</td>
			</tr>

			<tr>
				<td><?php echo _('Coordinates'); ?></td>
				<td>
					<label for="x"><?php echo _('X'); ?></label>:
					<input id="x" maxlength="5" value="0" size="5" name="x" class="input">
					<label for="y"><?php echo _('Y'); ?></label>:
					<input maxlength="5" value="0" size="5" id="y" name="y" class="input">
				</td>
			</tr>

			<tr>
				<td colspan="2">
					<fieldset>
						<legend><?php echo _('URLs'); ?></legend>
						<table>
							<thead>
							<tr>
								<td><?php echo _('Name'); ?></td>
								<td><?php echo _('URL'); ?></td>
								<td></td>
							</tr>
							</thead>
							<tbody id="urlContainer"></tbody>
							<tfoot>
							<tr>
								<td colspan="3"><span id="newSelementUrl" class="link_menu"><?php echo _('Add'); ?></span></td>
							</tr>
							</tfoot>
						</table>
					</fieldset>
				</td>
			</tr>
			<tr class="footer">
				<td colspan="2" class="form_row_last">
					<input id="elementApply" class="element-edit-control jqueryinput" type="button" name="apply" value="<?php echo CHtml::encode(_('Apply')); ?>">
					<input id="elementRemove" class="element-edit-control jqueryinput" type="button" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>">
					<input id="elementClose" class="jqueryinput" type="button" name="close" value=<?php echo CHtml::encode(_('Close')); ?>>
				</td>
			</tr>
			</tbody>
		</table>
	</form>
</script>

<script type="text/x-jquery-tmpl" id="mapMassFormTpl">
	<form id="massForm">
		<table class="formtable">
			<tbody>
			<tr class="header">
				<td id="massDragHandler" colspan="2" class="form_row_first move">
					<?php echo _('Mass update elements'); ?>&nbsp;
					(<span id="massElementCount"></span>&nbsp;<?php echo _('elements'); ?>)
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<?php echo _('Selected elements'); ?>:
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
					<label for="chkboxLabel"><?php echo _('Label'); ?></label>
				</td>
				<td>
					<textarea id="massLabel" cols="56" rows="4" name="label" class="input textarea_standard"></textarea>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_label_location" id="chkboxLabelLocation" class="checkbox">
					<label for="chkboxLabelLocation"><?php echo _('Label location'); ?></label>
				</td>
				<td>
					<select id="massLabelLocation" class="input select" name="label_location">
						<option value="<?php echo MAP_LABEL_LOC_DEFAULT; ?>"><?php echo _('Default'); ?></option>
						<option value="<?php echo MAP_LABEL_LOC_BOTTOM; ?>"><?php echo _('Bottom'); ?></option>
						<option value="<?php echo MAP_LABEL_LOC_LEFT; ?>"><?php echo _('Left'); ?></option>
						<option value="<?php echo MAP_LABEL_LOC_RIGHT; ?>"><?php echo _('Right'); ?></option>
						<option value="<?php echo MAP_LABEL_LOC_TOP; ?>"><?php echo _('Top'); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_use_iconmap" id="chkboxMassUseIconmap" class="checkbox">
					<label for="chkboxMassUseIconmap"><?php echo _('Automatic icon selection'); ?></label>
				</td>
				<td>
					<input type="checkbox" name="use_iconmap" id="massUseIconmap" class="checkbox" value="1">
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_iconid_off" id="chkboxMassIconidOff" class="checkbox">
					<label for="chkboxMassIconidOff"><?php echo _('Icon (default)'); ?></label>
				</td>
				<td>
					<select class="input select" name="iconid_off" id="massIconidOff"></select>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_iconid_on" id="chkboxMassIconidOn" class="checkbox">
					<label for="chkboxMassIconidOn"><?php echo _('Icon (problem)'); ?></label>
				</td>
				<td>
					<select class="input select" name="iconid_on" id="massIconidOn"></select>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_iconid_maintenance" id="chkboxMassIconidMaintenance" class="checkbox">
					<label for="chkboxMassIconidMaintenance"><?php echo _('Icon (maintenance)'); ?></label>
				</td>
				<td>
					<select class="input select" name="iconid_maintenance" id="massIconidMaintenance"></select>
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="chkbox_iconid_disabled" id="chkboxMassIconidDisabled" class="checkbox">
					<label for="chkboxMassIconidDisabled"><?php echo _('Icon (disabled)'); ?></label>
				</td>
				<td>
					<select class="input select" name="iconid_disabled" id="massIconidDisabled"></select>
				</td>
			</tr>
			<tr class="footer">
				<td colspan="2" class="form_row_last">
					<input id="massApply" class="element-edit-control jqueryinput" type="button" name="apply" value="<?php echo CHtml::encode(_('Apply')); ?>">
					<input id="massRemove" class="element-edit-control jqueryinput" type="button" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>">
					<input id="massClose" class="jqueryinput" type="button" name="close" value=<?php echo CHtml::encode(_('Close')); ?>>
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
			<caption><?php echo _('Links for the selected element'); ?></caption>
			<thead>
			<tr class="header">
				<td></td>
				<td><?php echo _('Element name'); ?></td>
				<td><?php echo _('Link indicators'); ?></td>
			</tr>
			</thead>
			<tbody></tbody>
		</table>
		<table id="mass-element-links" class="tableinfo element-links">
			<caption><?php echo _('Links between the selected elements'); ?></caption>
			<thead>
			<tr class="header">
				<td></td>
				<td><?php echo _('From'); ?></td>
				<td><?php echo _('To'); ?></td>
				<td><?php echo _('Link indicators'); ?></td>
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
					<label for="linklabel"><?php echo _('Label'); ?></label>
				</td>
				<td>
					<textarea cols="48" rows="4" name="label" id="linklabel" class="input textarea_standard"></textarea>
				</td>
			</tr>
			<tr id="link-connect-to">
				<td>
					<label for="selementid2"><?php echo _('Connect to'); ?></label>
				</td>
				<td>
					<select class="input select" name="selementid2" id="selementid2"></select>
				</td>
			</tr>
			<tr>
				<td>
					<label for="drawtype"><?php echo _('Type (OK)'); ?></label>
				</td>
				<td>
					<select size="1" class="input select" name="drawtype" id="drawtype">
						<option value="<?php echo GRAPH_ITEM_DRAWTYPE_LINE; ?>"><?php echo _('Line'); ?></option>
						<option value="<?php echo GRAPH_ITEM_DRAWTYPE_BOLD_LINE; ?>"><?php echo _('Bold line'); ?></option>
						<option value="<?php echo GRAPH_ITEM_DRAWTYPE_DOT; ?>"><?php echo _('Dot'); ?></option>
						<option value="<?php echo GRAPH_ITEM_DRAWTYPE_DASHED_LINE; ?>"><?php echo _('Dashed line'); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label for="color"><?php echo _('Colour (OK)'); ?></label>
				</td>
				<td>
					<input maxlength="6" size="7" id="color" name="color" class="input colorpicker">
					<div id="lbl_color" class="pointer colorpickerLabel">&nbsp;&nbsp;&nbsp;</div>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<fieldset>
						<legend><?php echo _('Link indicators'); ?></legend>
						<table>
							<thead>
							<tr>
								<td><?php echo _('Triggers'); ?></td>
								<td><?php echo _('Type'); ?></td>
								<td><?php echo _('Colour'); ?></td>
								<td></td>
							</tr>
							</thead>
							<tbody id="linkTriggerscontainer"></tbody>
							<tfoot>
							<tr>
								<td colspan="4">
									<span class="link_menu" onclick="PopUp('popup.php?srctbl=triggers&srcfld1=triggerid&real_hosts=1&reference=linktrigger&multiselect=1&writeonly=1&with_triggers=1&noempty=1');"><?php echo _('Add'); ?></span>
								</td>
							</tr>
							</tfoot>
						</table>
					</fieldset>
				</td>
			</tr>
			<tr class="footer">
				<td colspan="2" class="form_row_last">
					<input id="formLinkApply" type="button" value="<?php echo CHtml::encode(_('Apply')); ?>">
					<input id="formLinkRemove" type="button" value="<?php echo CHtml::encode(_('Remove')); ?>">
					<input id="formLinkClose" type="button" value="<?php echo CHtml::encode(_('Close')); ?>">
				</td>
			</tr>
			</tbody>
		</table>
	</form>
</script>

<script type="text/x-jquery-tmpl" id="elementLinkTableRowTpl">
	<tr>
		<td><span class="link_menu openlink" data-linkid="#{linkid}"><?php echo _('Edit'); ?></span></td>
		<td>#{toElementName}</td>
		<td class="pre">#{linktriggers}</td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="massElementLinkTableRowTpl">
	<tr>
		<td><span class="link_menu openlink" data-linkid="#{linkid}"><?php echo _('Edit'); ?></span></td>
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
				<option value="<?php echo GRAPH_ITEM_DRAWTYPE_LINE; ?>"><?php echo _('Line'); ?></option>
				<option value="<?php echo GRAPH_ITEM_DRAWTYPE_BOLD_LINE; ?>"><?php echo _('Bold line'); ?></option>
				<option value="<?php echo GRAPH_ITEM_DRAWTYPE_DOT; ?>"><?php echo _('Dot'); ?></option>
				<option value="<?php echo GRAPH_ITEM_DRAWTYPE_DASHED_LINE; ?>"><?php echo _('Dashed line'); ?></option>
			</select>
		</td>
		<td>
			<input maxlength="6" value="#{color}" size="7" id="linktrigger_#{linktriggerid}_color" name="linktrigger_#{linktriggerid}_color" class="input colorpicker">
			<div id="lbl_linktrigger_#{linktriggerid}_color" class="pointer colorpickerLabel">&nbsp;&nbsp;&nbsp;</div>
		</td>
		<td>
			<span class="link_menu triggerRemove" data-linktriggerid="#{linktriggerid}""><?php echo _('Remove'); ?></span>
		</td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="selementFormUrls">
	<tr id="urlrow_#{selementurlid}" class="even_row">
		<td><input class="input" name="url_#{selementurlid}_name" type="text" size="16" value="#{name}"></td>
		<td><input class="input" name="url_#{selementurlid}_url" type="text" size="32" value="#{url}"></td>
		<td><span class="link_menu" onclick="jQuery('#urlrow_#{selementurlid}').remove();"><?php echo _('Remove'); ?></span></td>
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
