<!-- Discovery Actions-->
<script type="text/x-jquery-tmpl" id="opGroupRowTPL">
<tr id="opGroupRow_#{groupid}">
<td>
	<input name="new_operation[opgroup][#{groupid}][groupid]" type="hidden" value="#{groupid}" />
	<span style="font-size: 1.1em; font-weight: bold;"> #{name} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpGroupRow(#{groupid});" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opTemplateRowTPL">
<tr id="opTemplateRow_#{templateid}">
<td>
	<input name="new_operation[optemplate][#{templateid}][templateid]" type="hidden" value="#{templateid}" />
	<span style="font-size: 1.1em; font-weight: bold;"> #{host} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpTemplateRow(#{templateid});" />
</td>
</tr>
</script>
<!-- Trigger Actions-->
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
<td><input name="new_operation[opcommand_grp][#{opcommand_grpid}][action]" type="hidden" value="#{action}" />
	<input name="new_operation[opcommand_grp][#{opcommand_grpid}][opcommand_grpid]" type="hidden" value="#{opcommand_grpid}" />
	<input name="new_operation[opcommand_grp][#{opcommand_grpid}][groupid]" type="hidden" value="#{groupid}" />
	<input name="new_operation[opcommand_grp][#{opcommand_grpid}][name]" type="hidden" value="#{name}" />
	#{objectCaption}
	<span class="bold"> #{name} </span>
</td>
<td>
	<textarea name="new_operation[opcommand_grp][#{opcommand_grpid}][command]" class="hidden">#{command}</textarea>
	<span class="italic" title="#{command}"> #{commandLine} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="edit" value="<?php print(_('Edit'));?>" onclick="javascript: showOpCmdForm(#{opcommand_grpid}, 'groupid');" />
	&nbsp;<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpCmdRow(#{opcommand_grpid}, 'groupid');" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opCmdHostRowTPL">
<tr id="opCmdHostRow_#{opcommand_hstid}">
<td>
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][action]" type="hidden" value="#{action}" />
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][opcommand_hstid]" type="hidden" value="#{opcommand_hstid}" />
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][hostid]" type="hidden" value="#{hostid}" />
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][host]" type="hidden" value="#{host}" />
	#{objectCaption}
	<span class="bold"> #{host} </span>
</td>
<td>
	<textarea name="new_operation[opcommand_hst][#{opcommand_hstid}][command]" class="hidden">#{command}</textarea>
	<span class="italic" title="#{command}"> #{commandLine} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="edit" value="<?php print(_('Edit'));?>" onclick="javascript: showOpCmdForm(#{opcommand_hstid}, 'hostid');" />
	&nbsp;<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpCmdRow(#{opcommand_hstid}, 'hostid');" />
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
			<option value="0"><?php print(_('Current host')); ?></option>
			<option value="1"><?php print(_('Host')); ?></option>
			<option value="2"><?php print(_('Host group')); ?></option>
		</select>
		<div id="opCmdTargetSelect" class="inlineblock">
			<input name="action" type="hidden" value="#{action}" />
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
	&nbsp;<input type="button" class="input link_menu" name="cancel" value="<?php print(_('Cancel')); ?>" />
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
			case 'dsc_groupid':
				if(jQuery("#opGroupRow_"+value.groupid).length) continue;

				var tpl = new Template(jQuery('#opGroupRowTPL').html());
				jQuery("#opGroupListFooter").before(tpl.evaluate(value));
				break;
			case 'dsc_templateid':
				if(jQuery("#opTemplateRow_"+value.templateid).length) continue;

				var tpl = new Template(jQuery('#opTemplateRowTPL').html());
				jQuery("#opTemplateListFooter").before(tpl.evaluate(value));
				break;
			case 'groupid':
				var tpl = new Template(jQuery('#opCmdGroupRowTPL').html());

				value.objectCaption = "<?php print(_('Host group').': '); ?>";

				if(!isset('action', value))
					value.action = isset('opcommand_grpid', value) ? 'update' : 'create';

				value.commandLine = value.command;
				var cmdLines = value.command.split("\n");

				if((value.command.length > 48) || (cmdLines.length > 1))
					value.commandLine = cmdLines[0].toString().substr(0,45) + '...';

				if(jQuery("#opCmdDraft").length){
					jQuery("#opCmdDraft").replaceWith(tpl.evaluate(value));
				}
				else{
					if(!isset('opcommand_grpid', value)){
						value.opcommand_grpid = jQuery("#opCmdList tr[id^=opCmdGroupRow_]").length;
						while(jQuery("#opCmdGroupRow_"+value.opcommand_grpid).length){
							value.opcommand_grpid++;
						}
					}

					value.newValue = "create";
					jQuery("#opCmdListFooter").before(tpl.evaluate(value));
				}
				break;
			case 'hostid':
				var tpl = new Template(jQuery('#opCmdHostRowTPL').html());

				if(value.hostid.toString() != '0')
					value.objectCaption = "<?php print(_('Host').': '); ?>";
				else
					value.host = "<?php print(_('Current host')); ?>";

				if(!isset('action', value))
					value.action = isset('opcommand_hstid', value) ? 'update' : 'create';

				value.commandLine = value.command;
				var cmdLines = value.command.split("\n");

				if((value.command.length > 48) || (cmdLines.length > 1))
					value.commandLine = cmdLines[0].toString().substr(0,45) + '...';

				if(jQuery("#opCmdDraft").length){
					jQuery("#opCmdDraft").replaceWith(tpl.evaluate(value));
				}
				else{
					if(!isset('opcommand_hstid', value)){
						value.opcommand_hstid = jQuery("#opCmdList tr[id^=opCmdHostRow_]").length;
						while(jQuery("#opCmdHostRow_"+value.opcommand_hstid).length){
							value.opcommand_hstid++;
						}
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
function removeOpGroupRow(groupid){
	jQuery('#opGroupRow_'+groupid).remove();
}
function removeOpTemplateRow(tplid){
	jQuery('#opTemplateRow_'+tplid).remove();
}

function removeOpCmdRow(opCmdRowId, object){
	if(object == 'groupid'){
		jQuery('#opCmdGroupRow_'+opCmdRowId).remove();
	}
	else{
		jQuery('#opCmdHostRow_'+opCmdRowId).remove();
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
		objectTPL.action = jQuery(objectRow).find('input[name="new_operation[opcommand_hst]['+opCmdId+'][action]"]').val();

		objectTPL.opcmdid = opCmdId;
		objectTPL.objectid = jQuery(objectRow).find('input[name="new_operation[opcommand_hst]['+opCmdId+'][hostid]"]').val();
		objectTPL.name = jQuery(objectRow).find('input[name="new_operation[opcommand_hst]['+opCmdId+'][host]"]').val();
		objectTPL.target = (objectTPL.objectid == 0) ? 0 : 1;
		objectTPL.command = jQuery(objectRow).find('textarea[name="new_operation[opcommand_hst]['+opCmdId+'][command]"]').val();
		objectTPL.operationName = '<?php print(_('Update'));?>';
	}
	else if(object == 'groupid'){
		objectTPL.action = jQuery(objectRow).find('input[name="new_operation[opcommand_hst]['+opCmdId+'][action]"]').val();

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
		objectTPL.action = 'create';
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
	object.action = jQuery(objectForm).find('input[name="action"]').val();
	object.target = jQuery(objectForm).find('select[name="opCmdTarget"]').val();
	object.command = jQuery(objectForm).find('textarea[name="opCmdTargetObjectCommand"]').val();

	if(empty(jQuery.trim(object.command))){
		alert("<?php print(_('Command field is empty. Please provide some instructions for operation.')); ?>");
		return true;
	}

	if(object.target.toString() == '2'){
		object.object = 'groupid';
		object.opcommand_grpid = jQuery(objectForm).find('input[name="opCmdId"]').val();
		object.groupid = jQuery(objectForm).find('input[name="opCmdTargetObjectId"]').val();
		object.name = jQuery(objectForm).find('input[name="opCmdTargetObjectName"]').val();

		if(empty(object.name)){
			alert("<?php print(_('You did not specify host group for operation.')); ?>");
			return true;
		}

		if(object.opcommand_grpid == 'new') delete(object["opcommand_grpid"]);
	}
	else{
		object.object = 'hostid';
		object.opcommand_hstid = jQuery(objectForm).find('input[name="opCmdId"]').val();
		object.hostid = jQuery(objectForm).find('input[name="opCmdTargetObjectId"]').val();
		object.host = jQuery(objectForm).find('input[name="opCmdTargetObjectName"]').val();

		if((object.target.toString() != '0') && empty(object.host)){
			alert("<?php print(_('You did not specify host for operation.')); ?>");
			return true;
		}

		if(object.opcommand_hstid == 'new') delete(object["opcommand_hstid"]);
	}

	addPopupValues({'object': object.object, 'values': [object]});
	jQuery(objectForm).remove();
}

function selectOpCmdTarget(){
	var target = jQuery('#opcmdEditForm select[name="opCmdTarget"]').val();
	if(target.toString() == '2')
		PopUp("popup.php?dstfrm=action.edit.php&srctbl=host_group&srcfld1=groupid&srcfld2=name&dstfld1=opCmdTargetObjectId&dstfld2=opCmdTargetObjectName&writeonly=1",480,480);
	else
		PopUp("popup.php?dstfrm=action.edit.php&srctbl=hosts&srcfld1=hostid&srcfld2=host&dstfld1=opCmdTargetObjectId&dstfld2=opCmdTargetObjectName&writeonly=1",780,480);
}

function changeOpCmdTarget(){
	jQuery('#opcmdEditForm')
		.find('#opCmdTargetSelect').toggle((jQuery('#opcmdEditForm select[name="opCmdTarget"]').val() > 0)).end()
		.find('input[name="opCmdTargetObjectId"]').val(0).end()
		.find('input[name="opCmdTargetObjectName"]').val('').end();
}

function closeOpCmdForm(){
//	if(Confirm("<?php print(_('Close currently opened remote command details without saving?')); ?>")){
		jQuery('#opCmdDraft').attr('id', jQuery('#opCmdDraft').attr('origid'));
		jQuery("#opcmdEditForm").remove();
		return true;
//	}
	return false;
}

jQuery(document).ready(function(){
	setTimeout(function(){jQuery("#name").focus()}, 10);
//	jQuery("#name").focus();

// Clone button
	jQuery("#clone").click(function(){
		jQuery("#actionid, #delete, #clone").remove();

		jQuery("#cancel").addClass('ui-corner-left');
		jQuery("#name").focus();
	});
});

//]]> -->
</script>
