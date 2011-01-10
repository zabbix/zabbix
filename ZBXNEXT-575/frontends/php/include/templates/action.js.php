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

<script type="text/x-jquery-tmpl" id="opcmdEditFormTPL">
<div id="opcmdEditForm" class="objectgroup inlineblock border_dotted ui-corner-all">
<table class="formElementTable"><tbody>
<tr>
	<td> <?php print(_('Target')); ?> </td>
	<td></td>
</tr>
<tr>
	<td> <?php print(_('Command')); ?> </td>
	<td></td>
</tr>
</tbody></table>
<div>
	<input type="button" name="save" value="{#operationName}" id="opcmdEditFormAction" />
</div>
</div>
</script>

<script type="text/javascript">
//<!--<![CDATA[
function addPopupValues(list){
	for(var i=0; i < list.values.length; i++){
		if(empty(list.values[i])) continue;
		var value = list.values[i];

//SDI(value);
		if(list.object == 'userid'){
			if(jQuery("#opmsgUserRow_"+value.userid).length) continue;

			var tpl = new Template(jQuery('#opmsgUserRowTPL').html());
			jQuery("#opmsgUserListFooter").before(tpl.evaluate(value));

		}
		else if(list.object == 'usrgrpid'){
			if(jQuery("#opmsgUsrgrpRow_"+value.usrgrpid).length) continue;

			var tpl = new Template(jQuery('#opmsgUsrgrpRowTPL').html());
			jQuery("#opmsgUsrgrpListFooter").before(tpl.evaluate(value));
		}
	}
}

function removeOpmsgUsrgrpRow(usrgrpid){
	jQuery('#opmsgUsrgrpRow_'+usrgrpid).remove();
}
function removeOpmsgUserRow(userid){
	jQuery('#opmsgUserRow_'+userid).remove();
}

//]]> -->
</script>
