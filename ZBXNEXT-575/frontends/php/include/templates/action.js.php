<script type="text/x-jquery-tmpl" id="opmsgUsrgrpRowTPL">
<tr id="opmsgUsrgrpRow_#{usrgrpid}">
<td>
	<input name="new_operation[opmessage_grp][#{usrgrpid}][usrgrpid]" type="hidden" value="#{usrgrpid}" />
	<span style="font-size: 1.1em; font-weight: bold;"> #{name} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpmsgUsrgrpRow(#{usrgrpid});" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opmsgUserRowTPL">
<tr id="opmsgUserRow_#{userid}">
<td>
	<input name="new_operation[opmessage_usr][#{userid}][userid]" type="hidden" value="#{userid}" />
	<span style="font-size: 1.1em; font-weight: bold;"> #{alias} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpmsgUserRow(#{userid});" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opCmdGroupRowTPL">
<tr id="opCmdGroupRow_#{opcommand_grpid}">
<td>
	<input name="new_operation[opcommand_grp][#{opcommand_grpid}][opcommand_grpid]" type="hidden" value="#{opcommand_grpid}" />
	<input name="new_operation[opcommand_grp][#{opcommand_grpid}][groupid]" type="hidden" value="#{groupid}" />
	<input name="new_operation[opcommand_grp][#{opcommand_grpid}][name]" type="hidden" value="#{name}" />
	#{objectCaption}
	<span class="bold"> #{name} </span>
</td>
<td>
	<textarea name="new_operation[opcommand_grp][#{opcommand_grpid}][command]" class="hidden"> #{command} </textarea>
	<span class="italic" title="#{command}"> #{commandLine} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="edit" value="<?php print(_('Edit'));?>" onclick="javascript: showOpCmdForm(#{opcommand_grpid}, 'groupid');" />
	&nbsp;
	<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpCmdGroupRow(#{opcommand_grpid}, 'groupid');" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opCmdHostRowTPL">
<tr id="opCmdHostRow_#{opcommand_hstid}">
<td>
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][opcommand_hstid]" type="hidden" value="#{opcommand_hstid}" />
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][hostid]" type="hidden" value="#{hostid}" />
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][host]" type="hidden" value="#{host}" />
	#{objectCaption}
	<span class="bold"> #{host} </span>
</td>
<td>
	<textarea name="new_operation[opcommand_hst][#{opcommand_hstid}][command]" class="hidden"> #{command} </textarea>
	<span class="italic" title="#{command}"> #{commandLine} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="edit" value="<?php print(_('Edit'));?>" onclick="javascript: showOpCmdForm(#{opcommand_hstid}, 'hostid');" />
	&nbsp;
	<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpCmdHostRow(#{opcommand_hstid}, 'hostid');" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opcmdEditFormTPL">
<div id="opcmdEditForm" class="objectgroup border_dotted ui-corner-all">
<table class="formElementTable"><tbody>
<tr>
	<td> <?php print(_('Target')); ?> </td>
	<td>
		<select name="opCmdTarget" class="input select">
			<option value="0"><?php print(_('Current Host')); ?></option>
			<option value="1"><?php print(_('Host')); ?></option>
			<option value="2"><?php print(_('Host group')); ?></option>
		</select>
		<div id="opCmdTargetSelect" class="inlineblock">
			<input name="opCmdId" type="hidden" value="#{opcmdid}" />
			<input name="opCmdTargetObjectId" id="opCmdTargetObjectId" type="hidden" value="#{objectid}" />
			<input name="opCmdTargetObjectName" id="opCmdTargetObjectName" type="text" class="input text" value="#{name}" readonly="readonly" size="30"/>
			<input type="button" class="input link_menu" name="select" value="<?php print(_('select'));?>" />
		</div>
	</td>
</tr>
<tr>
	<td> <?php print(_('Command')); ?> </td>
	<td><textarea name="opCmdTargetObjectCommand" class="input textarea" style="width: 320px; height: 60px;">#{command}</textarea></td>
</tr>
</tbody></table>
<div>
	<input type="button" class="input link_menu" name="save" value="#{operationName}" />
	&nbsp;
	<input type="button" class="input link_menu" name="cancel" value="<?php print(_('Cancel')); ?>" />
</div>
</div>
</script>

<script type="text/javascript">
//<!--<![CDATA[
function addPopupValues(list){
	for(var i=0; i < list.values.length; i++){
		if(empty(list.values[i])) continue;
		var value = list.values[i];

		switch(list.object){
			case 'userid':
				if(jQuery("#opmsgUserRow_"+value.userid).length) continue;

				var tpl = new Template(jQuery('#opmsgUserRowTPL').html());
				jQuery("#opmsgUserListFooter").before(tpl.evaluate(value));
				break;
			case 'usrgrpid':
				if(jQuery("#opmsgUsrgrpRow_"+value.usrgrpid).length) continue;

				var tpl = new Template(jQuery('#opmsgUsrgrpRowTPL').html());
				jQuery("#opmsgUsrgrpListFooter").before(tpl.evaluate(value));
				break;
			case 'groupid':
				var tpl = new Template(jQuery('#opCmdGroupRowTPL').html());

				value.commandLine = value.command.split("\n")[0].toString();
				if(value.commandLine.length > 48) value.commandLine = value.commandLine.substr(0,45) + '...';

				if(jQuery("#opCmdDraft").length){
					value.newValue = "update";
					jQuery("#opCmdDraft").replaceWith(tpl.evaluate(value));
				}
				else{
					value.opcommand_grpid = jQuery("#opCmdList tr[id^=opCmdGroupRow_]").length;
					while(jQuery("#opCmdGroupRow_"+value.opcommand_grpid).length){
						value.opcommand_grpid++;
					}

					value.newValue = "create";
					jQuery("#opCmdListFooter").before(tpl.evaluate(value));
				}
				break;
			case 'hostid':
				var tpl = new Template(jQuery('#opCmdHostRowTPL').html());

				value.commandLine = value.command.split("\n")[0].toString();
				if(value.commandLine.length > 48) value.commandLine = value.commandLine.substr(0,45) + '...';

				if(jQuery("#opCmdDraft").length){
					value.newValue = "update";
					jQuery("#opCmdDraft").replaceWith(tpl.evaluate(value));
				}
				else{
					value.opcommand_hstid = jQuery("#opCmdList tr[id^=opCmdHostRow_]").length;
					while(jQuery("#opCmdHostRow_"+value.opcommand_hstid).length){
						value.opcommand_hstid++;
					}

					value.newValue = "create";
					jQuery("#opCmdListFooter").before(tpl.evaluate(value));
				}
				break;
		}
	}
}

function removeOpmsgUsrgrpRow(usrgrpid){
	jQuery('#opmsgUsrgrpRow_'+usrgrpid).remove();
}
function removeOpmsgUserRow(userid){
	jQuery('#opmsgUserRow_'+userid).remove();
}

function removeOpCmdHostRow(opCmdRowId, object){
	if(object == 'groupid'){
		jQuery('#opCmdGroupRow_'+opCmdId).remove();
	}
	else{
		jQuery('#opCmdHostRow_'+opCmdId).remove();
	}
}

function showOpCmdForm(opCmdId, object){
	if(jQuery("#opcmdEditForm").length > 0){
		if(!closeOpCmdForm()) return true;
	}

	var objectTPL = {};
	if(object == 'hostid'){
		var objectRow = jQuery('#opCmdHostRow_'+opCmdId);
		objectRow.attr('origid', objectRow.attr('id'));
		objectRow.attr('id', 'opCmdDraft');
//#new_operation[opcommand_hst][#{opcommand_hstid}][opcommand_hstid]')

		objectTPL.opcmdid = opCmdId;
		objectTPL.objectid = jQuery(objectRow).find('input[name="new_operation[opcommand_hst]['+opCmdId+'][hostid]"]').val();
		objectTPL.name = jQuery(objectRow).find('input[name="new_operation[opcommand_hst]['+opCmdId+'][host]"]').val();
		objectTPL.target = (objectTPL.objectid == 0) ? 0 : 1;
		objectTPL.command = jQuery(objectRow).find('textarea[name="new_operation[opcommand_hst]['+opCmdId+'][command]"]').val();
		objectTPL.operationName = '<?php print(_('Update'));?>';
	}
	else if(object == 'groupid'){
		var objectRow = jQuery('#opCmdGroupRow_'+opCmdId);
		objectRow.attr('origid', objectRow.attr('id'));
		objectRow.attr('id', 'opCmdDraft');
//#new_operation[opcommand_hst][#{opcommand_hstid}][opcommand_hstid]')

		objectTPL.opcmdid = opCmdId;
		objectTPL.objectid = jQuery(objectRow).find('input[name="new_operation[opcommand_grp]['+opCmdId+'][groupid]"]').val();
		objectTPL.name = jQuery(objectRow).find('input[name="new_operation[opcommand_grp]['+opCmdId+'][name]"]').val();
		objectTPL.target = 2;
		objectTPL.command = jQuery(objectRow).find('textarea[name="new_operation[opcommand_grp]['+opCmdId+'][command]"]').val();
		objectTPL.operationName = '<?php print(_('Update'));?>';
	}
	else{
// new
		objectTPL.opcmdid = 'new';
		objectTPL.objectid = 0;
		objectTPL.name = '';
		objectTPL.target = 0;
		objectTPL.command = '';
		objectTPL.operationName = '<?php print(_('Add'));?>';
	}

	var tpl = new Template(jQuery('#opcmdEditFormTPL').html());
	jQuery("#opCmdList").after(tpl.evaluate(objectTPL));

// actions
	jQuery('#opcmdEditForm')
		.find('#opCmdTargetSelect').toggle((objectTPL.target != 0)).end()
		.find('input[name="save"]').click(saveOpCmdForm).end()
		.find('input[name="cancel"]').click(closeOpCmdForm).end()
		.find('input[name="select"]').click(selectOpCmdTarget).end()
		.find('select[name="opCmdTarget"]').val(objectTPL.target).change(changeOpCmdTarget);
}


function saveOpCmdForm(){
	var objectForm = jQuery('#opcmdEditForm');

	var object = {};
	object.target = jQuery(objectForm).find('select[name="opCmdTarget"]').val();
	object.command = jQuery(objectForm).find('textarea[name="opCmdTargetObjectCommand"]').val();

	if(empty(object.command)){
		alert("<?php print(_('Command field is empty. Please provide some extructions for operation.')); ?>");
		return true;
	}

	if(object.target.toString() == '2'){
		object.objectCaption = "<?php print(_('Host group').': '); ?>";
		object.object = 'groupid';
		object.opcommand_grpid = jQuery(objectForm).find('input[name="opCmdId"]').val();
		object.groupid = jQuery(objectForm).find('input[name="opCmdTargetObjectId"]').val();
		object.name = jQuery(objectForm).find('input[name="opCmdTargetObjectName"]').val();

		if(empty(object.name)){
			alert("<?php print(_('You did not specified host group for operation.')); ?>");
			return true;
		}
	}
	else{
		object.object = 'hostid';
		object.opcommand_hstid = jQuery(objectForm).find('input[name="opCmdId"]').val();
		object.hostid = jQuery(objectForm).find('input[name="opCmdTargetObjectId"]').val();
		object.host = jQuery(objectForm).find('input[name="opCmdTargetObjectName"]').val();

		if(object.target.toString() != '0'){
			object.objectCaption = "<?php print(_('Host').': '); ?>";

			if(empty(object.host)){
				alert("<?php print(_('You did not specified host for operation.')); ?>");
				return true;
			}
		}
		else{
			object.objectCaption = "<?php print(_('Current host')); ?>";
		}
	}

	addPopupValues({'object': object.object, 'values': [object]});
	jQuery(objectForm).remove();
}

function selectOpCmdTarget(){
	var target = jQuery('#opcmdEditForm select[name="opCmdTarget"]').val();
	if(target.toString() == '2')
		PopUp("popup.php?dstfrm=web.action.edit.php&srctbl=host_group&srcfld1=groupid&srcfld2=name&dstfld1=opCmdTargetObjectId&dstfld2=opCmdTargetObjectName",480,480);
	else
		PopUp("popup.php?dstfrm=web.action.edit.php&srctbl=hosts&srcfld1=hostid&srcfld2=host&dstfld1=opCmdTargetObjectId&dstfld2=opCmdTargetObjectName",780,480);
}

function changeOpCmdTarget(){
	jQuery('#opcmdEditForm')
		.find('#opCmdTargetSelect').toggle((jQuery('#opcmdEditForm select[name="opCmdTarget"]').val() > 0)).end()
		.find('input[name="opCmdTargetObjectId"]').val(0).end()
		.find('input[name="opCmdTargetObjectName"]').val('').end();
}

function closeOpCmdForm(){
	if(Confirm("<?php print(_('Close currently opened remote command details without saving?')); ?>")){
		jQuery('#opCmdDraft').attr('id', jQuery('#opCmdDraft').attr('origid'));
		jQuery("#opcmdEditForm").remove();
		return true;
	}
	return false;
}
//]]> -->
</script>
