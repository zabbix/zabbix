<script type="text/x-jquery-tmpl" id="hostInterfaceRow">

<tr id="hostInterfaceRow_#{interfaceid}">
<td>
	<input type="hidden" name="interfaces[#{interfaceid}][new]" value="#{newValue}" />
	<input type="hidden" name="interfaces[#{interfaceid}][interfaceid]" value="#{interfaceid}" />
	<input class="input" name="interfaces[#{interfaceid}][ip]" type="text" size="24" value="#{ip}" />
</td>
<td>
	<input class="input" name="interfaces[#{interfaceid}][dns]" type="text" size="30" value="#{dns}" />
</td>
<td>
	<div class="jqueryinputset">
		<input type="radio" id="radio_ip_#{interfaceid}" name="interfaces[#{interfaceid}][useip]" value="1" #{*checked_ip} />
		<label for="radio_ip_#{interfaceid}"><?php print(S_IP);?></label>

		<input type="radio" id="radio_dns_#{interfaceid}" name="interfaces[#{interfaceid}][useip]" value="0" #{*checked_dns} />
		<label for="radio_dns_#{interfaceid}"><?php print(S_DNS);?></label>
	</div>
</td>
<td>
	<input class="input" name="interfaces[#{interfaceid}][port]" type="text" size="10" value="#{port}" />
</td>
<td>
	<div class="jqueryinputset">
		<input type="radio" id="radio_agent_#{interfaceid}" name="interfaces[#{interfaceid}][type]" value="<?php print(INTERFACE_TYPE_AGENT);?>" #{*checked_agent} />
		<label for="radio_agent_#{interfaceid}"><?php print(S_AGENT);?></label>

		<input type="radio" id="radio_snmp_#{interfaceid}" name="interfaces[#{interfaceid}][type]" value="<?php print(INTERFACE_TYPE_SNMP);?>" #{*checked_snmp} />
		<label for="radio_snmp_#{interfaceid}"><?php print(S_SNMP);?></label>

		<input type="radio" id="radio_ipmi_#{interfaceid}" name="interfaces[#{interfaceid}][type]" value="<?php print(INTERFACE_TYPE_IPMI);?>" #{*checked_ipmi} />
		<label for="radio_ipmi_#{interfaceid}"><?php print(S_IPMI);?></label>
	</div>
</td>
<td>
	<input #{*disabled} type="button" class="link_menu" name="remove" value="<?php print(S_REMOVE);?>" onclick="javascript: removeInterfaceRow(#{interfaceid});" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="hostInterfaceRowRemove">

</script>



<script type="text/javascript">
function addInterfaceRow(hostInterface){
	var tpl = new Template(jQuery('#hostInterfaceRow').html());

	if(!isset("newValue", hostInterface)) hostInterface.newValue = "update";

	if(!isset("interfaceid", hostInterface)){
		hostInterface.interfaceid = $("hostInterfaces").select("tr[id^=hostInterfaceRow]").length;
		hostInterface.newValue = "create";
	}

	hostInterface.disabled = '';//'<?php print(S_INTERFACE_IS_USED);?>';
	if(isset("items", hostInterface) && (hostInterface.items > 0)){
		hostInterface.disabled = 'disabled="disabled"';
	}

	hostInterface.checked_ip = 'checked="checked"';
	hostInterface.checked_dns = '';
	if(isset("useip", hostInterface)){
		if(hostInterface.useip == 0){
			hostInterface.checked_ip = '';
			hostInterface.checked_dns = 'checked="checked"';
		}
	}
//SDJ(hostInterface);
	hostInterface.checked_agent = 'checked="checked"';
	hostInterface.checked_snmp = '';
	hostInterface.checked_ipmi = '';
	if(isset("type", hostInterface)){
		hostInterface.checked_agent = '';
		switch(hostInterface.type){
			case '<?php print(INTERFACE_TYPE_SNMP);?>': hostInterface.checked_snmp = 'checked="checked"'; break;
			case '<?php print(INTERFACE_TYPE_IPMI);?>': hostInterface.checked_ipmi = 'checked="checked"'; break;
			case '<?php print(INTERFACE_TYPE_AGENT);?>':
			default: hostInterface.checked_agent = 'checked="checked"'; break;
		}
	}

	jQuery("#hostIterfacesFooter").before(tpl.evaluate(hostInterface));
	jQuery("#hostInterfaceRow_"+hostInterface.interfaceid).find("div[class=jqueryinputset]").buttonset();
	jQuery("#hostIterfaces").accordion('resize');
}

function removeInterfaceRow(hostInterfaceId){
	jQuery('#hostInterfaceRow_'+hostInterfaceId).remove();
	jQuery("#hostIterfaces").accordion('resize');
}

jQuery(document).ready(function() {
	jQuery("#useipmi").button();
	jQuery("#useipmi").change(function(){
		if(this.checked){
			jQuery("#useipmi").button("option", "label", "<?php print(S_DISABLE_IPMI);?>");
			jQuery("#ipmilist :input:gt(0)").removeAttr("disabled");
		}
		else{
			jQuery("#useipmi").button("option", "label", "<?php print(S_ENABLE_IPMI);?>");
			jQuery("#ipmilist :input:gt(0)").attr("disabled", "disabled");
		}

	});
	jQuery("#useipmi").change();
});


</script>
