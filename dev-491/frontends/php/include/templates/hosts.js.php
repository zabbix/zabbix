<script type="text/x-jquery-tmpl" id="hostInterfaceRow">

<tr id="hostInterfaceRow_#{interfaceid}">
<td>
	<input type="hidden" name="interfaces[#{interfaceid}][new]" value="#{newValue}">
	<input type="hidden" name="interfaces[#{interfaceid}][interfaceid]" value="#{interfaceid}">
	<input class="input" name="interfaces[#{interfaceid}][ip]" type="text" size="39" value="#{ip}">
</td>
<td>
	<input class="input" name="interfaces[#{interfaceid}][dns]" type="text" size="30" value="#{dns}">
</td>
<td>
	<input class="input" name="interfaces[#{interfaceid}][port]" type="text" size="5" value="#{port}" maxlength="5" onkeypress=" var c = (window.event) ? event.keyCode : event.which; if(event.ctrlKey || c &lt;= 31 || (c &gt;= 48 &amp;&amp; c &lt;= 57) || (c &gt;= 37 &amp;&amp; c &lt;= 40) || c==46 || c==35 || c==36) return true; else return false; " onchange=" if(isNaN(parseInt(this.value,10))) this.value = 0;  else this.value = parseInt(this.value,10);">
</td>
<td>
	<select name="interfaces[#{interfaceid}][useip]" id="hostInterfaceRow_#{interfaceid}_useip" class="input select">
		<option value="0"><?php print(S_DNS_NAME);?></option>
		<option value="1"><?php print(S_IP_ADDRESS);?></option>
	</select>
</td>
<td>
	<input type="button" class="link_menu" name="remove" value="<?php print(S_REMOVE);?>" onclick="$('hostInterfaceRow_#{interfaceid}').remove();" />
</td>
</tr>
</script>

<script type="text/javascript">
function addInterfaceRow(hostInterface){
	var tpl = new Template(jQuery('#hostInterfaceRow').html());

	if(!isset("newValue", hostInterface)) hostInterface.newValue = "update";

	if(!isset("interfaceid", hostInterface)){
		hostInterface.interfaceid = $("hostInterfaces").select("tr[id^=hostInterfaceRow]").length;
		hostInterface.newValue = "create";
	}

	$("hostIterfacesFooter").insert({"before" : tpl.evaluate(hostInterface)});

	if(isset("useip", hostInterface)){
		var useipSelect = $("hostInterfaces").select("select[id^=hostInterfaceRow_"+hostInterface.interfaceid+"_useip]");
		if(!empty(useipSelect)) useipSelect[0].selectedIndex = hostInterface.useip;
	}
}
</script>