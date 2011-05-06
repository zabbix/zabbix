<script type="text/x-jquery-tmpl" id="mapElementForm">
	<form id="selementForm" name="selementForm" method="post">
		<input type="hidden" id="selementid" name="selementid">
		<input type="hidden" id="elementid" name="elementid">
		<table class="formtable">
			<tbody>
			<tr class="header">
				<td id="formDragHandler" colspan="2" class="form_row_first move"><?php echo _('Edit map element'); ?></td>
			</tr>
			<tr>
				<td><label for="elementLabel"><?php echo _('Type'); ?></label></td>
				<td>
					<select size="1" class="input" name="elementtype" id="elementtype">
						<option value="0" selected="selected"><?php echo _('Host'); ?></option>
						<option value="1"><?php echo _('Map'); ?></option>
						<option value="2"><?php echo _('Trigger'); ?></option>
						<option value="3"><?php echo _('Host group'); ?></option>
						<option value="4"><?php echo _('Image'); ?></option>
					</select>
				</td>
			</tr>
			<tr id="subtypeRow">
				<td><?php echo _('Show'); ?></td>
				<td>
					<div class="objectgroup border_dotted">
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
					<div class="objectgroup border_dotted">
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
					<input id="areaSizeWidth" type="text" class="input text" name="areasizewidth" value="200">
					<label for="areaSizeHeight"><?php echo _('Height'); ?></label>
					<input id="areaSizeHeight" type="text" class="input text" name="areasizeheight" value="200">
				</td>
			</tr>
			<tr id="areaPlacingRow">
				<td><label for="areaPlacing"><?php echo _('Placing algorithm'); ?></label></td>
				<td>
					<select id="areaPlacing" class="input">
						<option value="0"><?php echo _('Grid'); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td><label for="elementLabel"><?php echo _('Label'); ?></label></td>
				<td><textarea id="elementLabel" cols="56" rows="4" name="label" class="input"></textarea></td>
			</tr>
			<tr>
				<td><label for="label_location"><?php echo _('Label location'); ?></label></td>
				<td><select id="label_location" class="input" name="label_location">
					<option value="-1">-</option>
					<option value="0"><?php echo _('Bottom'); ?></option>
					<option value="1"><?php echo _('Left'); ?></option>
					<option value="2"><?php echo _('Right'); ?></option>
					<option value="3"><?php echo _('Top'); ?></option>
				</select></td>
			</tr>
			<tr id="hostGroupSelectRow">
				<td><?php echo _('Host group'); ?></td>
				<td>
					<input readonly="readonly" size="56" id="elementNameHostGroup" name="elementName" class="input">
					<span class="link" onclick="PopUp('popup.php?writeonly=1&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementName&srctbl=host_group&srcfld1=groupid&srcfld2=name',450,450)"><?php echo _('Select'); ?></span>
				</td>
			</tr>
			<tr id="hostSelectRow">
				<td><?php echo _('Host'); ?></td>
				<td>
					<input readonly="readonly" size="56" id="elementNameHost" name="elementName" class="input">
					<span class="link" onclick="PopUp('popup.php?writeonly=1&real_hosts=1&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementNameHost&srctbl=hosts&srcfld1=hostid&srcfld2=host',450,450)"><?php echo _('Select'); ?></span>
				</td>
			</tr>
			<tr id="triggerSelectRow">
				<td><?php echo _('Trigger'); ?></td>
				<td>
					<input readonly="readonly" size="56" id="elementNameTrigger" name="elementName" class="input">
					<span class="link" onclick="PopUp('popup.php?writeonly=1&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementName&srctbl=triggers&srcfld1=triggerid&srcfld2=description',450,450)"><?php echo _('Select'); ?></span>
				</td>
			</tr>
			<tr id="mapSelectRow">
				<td><?php echo _('Map'); ?></td>
				<td>
					<input readonly="readonly" size="56" id="elementNameMap" name="elementName" class="input">
					<span class="link" onclick="PopUp('popup.php?writeonly=1&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementNameMap&srctbl=sysmaps&srcfld1=sysmapid&srcfld2=name&excludeids[]=#{sysmapid}',450,450)"><?php echo _('Select'); ?></span>
				</td>
			</tr>
			<tr>
				<td><label for="iconid_off"><?php echo _('Icon (default)'); ?></label></td>
				<td>
					<select class="input" name="iconid_off" id="iconid_off"></select>
				</td>
			</tr>
			<tr>
				<td><label for="advanced_icons"><?php echo _('Use advanced icons'); ?></label></td>
				<td><input type="checkbox" name="advanced_icons" id="advanced_icons" class="checkbox"></td>
			</tr>
			<tr id="iconProblemRow">
				<td><label for="iconid_on"><?php echo _('Icon (problem)'); ?></label></td>
				<td>
					<select class="input" name="iconid_on" id="iconid_on"></select>
				</td>
			</tr>
			<tr id="iconMainetnanceRow">
				<td><label for="iconid_maintenance"><?php echo _('Icon (maintenance)'); ?></label></td>
				<td>
					<select class="input" name="iconid_maintenance" id="iconid_maintenance"></select>
				</td>
			</tr>
			<tr id="iconDisabledRow">
				<td><label for="iconid_disabled"><?php echo _('Icon (disabled)'); ?></label></td>
				<td>
					<select class="input" name="iconid_disabled" id="iconid_disabled">
					</select>
				</td>
			</tr>
			<tr>
				<td><label for="x"><?php echo _('Coordinate X'); ?></label></td>
				<td><input id="x" onchange="if(isNaN(parseInt(this.value,10))) this.value = 0;" style="text-align: right;" maxlength="5" value="0" size="5" name="x" class="input"></td>
			</tr>
			<tr>
				<td><label for="y"><?php echo _('Coordinate Y'); ?></label></td>
				<td><input onchange="if(isNaN(parseInt(this.value,10))) this.value = 0;" style="text-align: right;" maxlength="5" value="0" size="5" id="y" name="y" class="input"></td>
			</tr>
			<tr>
				<td><?php echo _('Links'); ?></td>
				<td>
					<table>
						<tbody id="urlContainer">
						<tr class="header">
							<td><?php echo _('Name'); ?></td>
							<td><?php echo _('URL'); ?></td>
							<td></td>
						</tr>
						<tr id="urlfooter">
							<td colspan="3"><span id="newSelementUrl" class="link_menu" title="Add">Add</span></td>
						</tr>
						</tbody>
					</table>
				</td>
			</tr>
			<tr class="footer">
				<td colspan="2" class="form_row_last">
					<input id="elementApply" type="button" name="apply" value="Apply">
					<input id="elementRemove" type="button" name="remove" value="Remove">
					<input id="elementClose" type="button" name="close" value="Close">
				</td>
			</tr>
			</tbody>
		</table>
	</form>
</script>

<script type="text/x-jquery-tmpl" id="mapElementsList">

</script>

<script type="text/x-jquery-tmpl" id="selementFormUrls">
	<tr id="urlrow_#{selementurlid}">
	<td><input class="input" name="urls[#{selementurlid}][name]" type="text" size="16" value="#{name}"></td>
	<td><input class="input" name="urls[#{selementurlid}][url]" type="text" size="32" value="#{url}"></td>
	<td><span class="link_menu" onclick="jQuery('#urlrow_#{selementurlid}').remove();"><?php echo _('Remove'); ?></span></td>
	</tr>
</script>

<script type="text/javascript">
	jQuery(document).ready(function(){

		(function($){
			$.fn.hideDisable = function(show){
				if(show){
					this.find(':input').removeAttr('disabled');
					this.find(':input:checked').click();
					this.find('select').change();
				}
				else{
					this.find(':input').attr('disabled', 'disabled');
				}

				return this.toggle(show);
			};
		})(jQuery);
	});
</script>
