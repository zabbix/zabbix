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
	<?php print(_('Host group').': '); ?>
	<span style="font-size: 1.1em; font-weight: bold;"> #{name} </span>
</td>
<td>
	<input name="new_operation[opcommand_grp][#{opcommand_grpid}][command]" type="hidden" value="#{command}" />
	<span class="italic"> #{commandLine} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="edit" value="<?php print(_('Edit'));?>" onclick="javascript: showOpCmdForm(#{opcommand_grpid}, 'groupid');" />
	&nbsp;
	<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpCmdGroupRow(#{opcommand_grpid});" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opCmdHostRowTPL">
<tr id="opCmdHostRow_#{opcommand_hstid}">
<td>
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][opcommand_hstid]" type="hidden" value="#{opcommand_hstid}" />
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][hostid]" type="hidden" value="#{hostid}" />
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][host]" type="hidden" value="#{host}" />
	<?php print(_('Host').': '); ?>
	<span style="font-size: 1.1em; font-weight: bold;"> #{host} </span>
</td>
<td>
	<input name="new_operation[opcommand_hst][#{opcommand_hstid}][command]" type="hidden" value="#{command}" />
	<span class="italic"> #{commandLine} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="edit" value="<?php print(_('Edit'));?>" onclick="javascript: showOpCmdForm(#{opcommand_hstid}, 'hostid');" />
	&nbsp;
	<input type="button" class="input link_menu" name="remove" value="<?php print(_('Remove'));?>" onclick="javascript: removeOpCmdHostRow(#{opcommand_hstid});" />
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
</div>
</div>
</script>

<script type="text/javascript">
//<!--<![CDATA[
function addPopupValues(list){
	for(var i=0; i < list.values.length; i++){
		if(empty(list.values[i])) continue;
		var value = list.values[i];

SDI(value);
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

				if(jQuery("#opCmdGroupRow_"+value.opcommand_grpid).length)
					jQuery("#opCmdGroupRow_"+value.opcommand_grpid).replace(tpl.evaluate(value));
				else
					jQuery("#opCmdListFooter").before(tpl.evaluate(value));
				break;
			case 'hostid':
				var tpl = new Template(jQuery('#opCmdHostRowTPL').html());

				if(jQuery("#opCmdHostRow_"+value.opcommand_hstid).length)
					jQuery("#opCmdHostRow_"+value.opcommand_hstid).replace(tpl.evaluate(value));
				else
					jQuery("#opCmdListFooter").before(tpl.evaluate(value));
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

function showOpCmdForm(opCmdId, object){
	if(jQuery("#opcmdEditForm").length > 0){
		if(Confirm('Close current edited command without saving?')){
			jQuery("#opcmdEditForm").remove();
		}
		else{
			return true;
		}
	}

	var objectTPL = {};
	if(object == 'hostid'){
		var objectRow = jQuery('#opCmdHostRow_'+opCmdId);
//#new_operation[opcommand_hst][#{opcommand_hstid}][opcommand_hstid]')

		objectTPL.opcmdid = opCmdId;
		objectTPL.objectid = jQuery(objectRow).find('#new_operation[opcommand_hst]['+opCmdObjectId+'][hostid]').val();
		objectTPL.name = jQuery(objectRow).find('#new_operation[opcommand_hst]['+opCmdObjectId+'][host]').val();
		objectTPL.target = (objectTPL.objectid == 0) ? 0 : 1;
		objectTPL.comamnd = jQuery(objectRow).find('#new_operation[opcommand_hst]['+opCmdObjectId+'][command]').val();
		objectTPL.operationName = '<?php print(_('Update'));?>';
	}
	else if(object == 'groupid'){
		var objectRow = jQuery('#opCmdGroupRow_'+opCmdId);
//#new_operation[opcommand_hst][#{opcommand_hstid}][opcommand_hstid]')

		objectTPL.opcmdid = opCmdId;
		objectTPL.objectid = jQuery(objectRow).find('#new_operation[opcommand_grp]['+opCmdObjectId+'][groupid]').val();
		objectTPL.name = jQuery(objectRow).find('#new_operation[opcommand_grp]['+opCmdObjectId+'][name]').val();
		objectTPL.target = 2;
		objectTPL.comamnd = jQuery(objectRow).find('#new_operation[opcommand_grp]['+opCmdObjectId+'][command]').val();
		objectTPL.operationName = '<?php print(_('Update'));?>';
	}
	else{
// new
		objectTPL.opcmdid = 'new';
		objectTPL.objectid = 0;
		objectTPL.name = '';
		objectTPL.target = 0;
		objectTPL.comamnd = '';
		objectTPL.operationName = '<?php print(_('Add'));?>';
	}

	var tpl = new Template(jQuery('#opcmdEditFormTPL').html());
	jQuery("#opCmdList").after(tpl.evaluate(objectTPL));

// actions
	jQuery('#opcmdEditForm')
		.find('#opCmdTargetSelect').toggle((objectTPL.target != 0)).end()
		.find('input[name="save"]').click(saveOpCmdForm).end()
		.find('input[name="select"]').click(selectOpCmdTarget).end()
		.find('select[name="opCmdTarget"]').change(changeOpCmdTarget);
}


function saveOpCmdForm(){
	var objectForm = jQuery('#opcmdEditForm');

	var object = {};
	object.target = jQuery(objectForm).find('select[name="opCmdTarget"]').val();
	object.command = jQuery(objectForm).find('textarea[name="opCmdTargetObjectCommand"]').val();
	object.commandLine = object.command.split("\n")[0];

	if(object.target.toString() == '2'){
		object.object = 'groupid';
		object.opcommand_grpid = jQuery(objectForm).find('input[name="opCmdId"]').val();
		object.groupid = jQuery(objectForm).find('input[name="opCmdTargetObjectId"]').val();
		object.name = jQuery(objectForm).find('input[name="opCmdTargetObjectName"]').val();
	}
	else{
		object.object = 'hostid';
		object.opcommand_hstid = jQuery(objectForm).find('input[name="opCmdId"]').val();
		object.hostid = jQuery(objectForm).find('input[name="opCmdTargetObjectId"]').val();
		object.host = jQuery(objectForm).find('input[name="opCmdTargetObjectName"]').val();
	}

SDJ(object);
	addPopupValues({'object': object.object, 'values': [object]});
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
//]]> -->
</script>
