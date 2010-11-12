<script type="text/x-jquery-tmpl" id="userMacroRow">
<tr id="userMacroRow_#{macroid}">
	<td>
		<input type="hidden" name="macros[#{interfaceid}][new]" value="#{newValue}">
		<input type="hidden" name="macros[#{interfaceid}][macroid]" value="#{macroid}">
		<input class="biginput" name="macros[#{macroid}][macro]" type="text" size="30" value="#{macro}" placeholder="{$MACRO}">
	</td>
	<td><span style="vertical-align:top;">⇒</span></td>
	<td>
		<input class="biginput" name="macros[#{macroid}][value]" type="text" size="40" value="#{value}" placeholder="&lt;Value&gt;">
	</td>
	<td>
		<input type="button" class="link_menu" name="remove" value="<?php print(S_REMOVE);?>" onclick="$('userMacroRow_#{macroid}').remove();" />
	</td>
</tr>
</script>

<script type="text/javascript">
function addMacroRow(userMacro){
	var tpl = new Template(jQuery('#userMacroRow').html());

	if(!isset("newValue", userMacro)) userMacro.newValue = "update";

	if(!isset("interfaceid", userMacro)){
		userMacro.interfaceid = $("userMacros").select("tr[id^=userMacroRow]").length;
		userMacro.newValue = "create";
	}

	$("userMacroFooter").insert({"before" : tpl.evaluate(userMacro)});
}
</script>