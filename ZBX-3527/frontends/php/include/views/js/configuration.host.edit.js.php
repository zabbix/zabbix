<script type="text/x-jquery-tmpl" id="hostInterfaceRow">

<tr class="interfaceRow" id="hostInterfaceRow_#{interfaceid}">
<td>
	<input type="hidden" name="interfaces[#{interfaceid}][new]" value="#{newValue}" />
	<input type="hidden" id="interface_id_#{interfaceid}" name="interfaces[#{interfaceid}][interfaceid]" value="#{interfaceid}" />
	<input class="input text" id="interface_ip_#{interfaceid}" name="interfaces[#{interfaceid}][ip]" type="text" size="24" value="#{ip}" />
</td>
<td>
	<input class="input text" id="interface_dns_#{interfaceid}" name="interfaces[#{interfaceid}][dns]" type="text" size="30" value="#{dns}" />
</td>
<td>
	<div class="jqueryinputset">
		<input type="radio" id="radio_ip_#{interfaceid}" name="interfaces[#{interfaceid}][useip]" value="1" #{*checked_ip} />
		<label for="radio_ip_#{interfaceid}"><?php echo _('IP'); ?></label>

		<input type="radio" id="radio_dns_#{interfaceid}" name="interfaces[#{interfaceid}][useip]" value="0" #{*checked_dns} />
		<label for="radio_dns_#{interfaceid}"><?php echo _('DNS'); ?></label>
	</div>
</td>
<td>
	<input class="input text" id="port_#{interfaceid}" name="interfaces[#{interfaceid}][port]" type="text" size="15" value="#{port}" />
</td>
<td>
	<div id="interface_type_#{interfaceid}" class="jqueryinputset interfaceTypes">
		<input type="radio" id="radio_agent_#{interfaceid}" name="interfaces[#{interfaceid}][type]" value="<?php print(INTERFACE_TYPE_AGENT);?>" #{*checked_agent} #{*lock_agent}/>
		<label for="radio_agent_#{interfaceid}"><?php echo _('Agent'); ?></label>

		<input type="radio" id="radio_snmp_#{interfaceid}" name="interfaces[#{interfaceid}][type]" value="<?php print(INTERFACE_TYPE_SNMP);?>" #{*checked_snmp} #{*lock_snmp} />
		<label for="radio_snmp_#{interfaceid}"><?php echo _('SNMP'); ?></label>

		<input type="radio" id="radio_jmx_#{interfaceid}" name="interfaces[#{interfaceid}][type]" value="<?php print(INTERFACE_TYPE_JMX);?>" #{*checked_jmx} #{*lock_jmx} />
		<label for="radio_jmx_#{interfaceid}"><?php echo _('JMX'); ?></label>

		<input type="radio" id="radio_ipmi_#{interfaceid}" name="interfaces[#{interfaceid}][type]" value="<?php print(INTERFACE_TYPE_IPMI);?>" #{*checked_ipmi} #{*lock_ipmi} />
		<label for="radio_ipmi_#{interfaceid}"><?php echo _('IPMI'); ?></label>
	</div>
</td>
<td>
	<input type="checkbox" id="interface_main_#{interfaceid}" name="interfaces[#{interfaceid}][main]" value="1" #{*checked_main} />
	<label class="checkboxLikeLabel" for="interface_main_#{interfaceid}" style="height: 16px; width: 16px;"></label>
</td>
<td>
	<input #{*disabled} id="removeInterface_#{interfaceid}" type="button" class="link_menu" name="remove" value="<?php echo _('Remove'); ?>" />
</td>
</tr>
</script>

<script type="text/javascript">

function addInterfaceRow(hostInterface) {
	var tpl = new Template(jQuery('#hostInterfaceRow').html());

	if (!isset("new", hostInterface)) {
		hostInterface.newValue = "update";
	}
	else {
		hostInterface.newValue = hostInterface["new"];
	}

	if (!isset("interfaceid", hostInterface)) {

		hostInterface.interfaceid = jQuery("#hostInterfaces tr[id^=hostInterfaceRow]").length;
		while (jQuery("#interface_id_"+hostInterface.interfaceid).length) {
			hostInterface.interfaceid++;
		}

		hostInterface.newValue = "create";
	}

	hostInterface.disabled = '';
	if (isset("items", hostInterface) && (hostInterface.items > 0)) {
		hostInterface.disabled = 'disabled="disabled"';
	}

	if (!isset("ip", hostInterface) && !isset("dns", hostInterface)) {
		if (jQuery("#hostInterfaces input[type=radio]:checked").first().val() == "0") {
			hostInterface.useip = 0;
			hostInterface.dns = jQuery("#hostInterfaces input[id^=interface_dns]").first().val();
		}
		else {
			hostInterface.useip = 1;
			hostInterface.ip = jQuery("#hostInterfaces input[id^=interface_ip]").first().val();
		}
	}

	if (isset("useip", hostInterface)) {
		if(hostInterface.useip == 0)
			hostInterface.checked_dns = 'checked="checked"';
		else
			hostInterface.checked_ip = 'checked="checked"';
	}
//SDJ(hostInterface);
	if (!isset('port', hostInterface)) {
		hostInterface.port = '10050';
	}

	hostInterface.checked_agent = 'checked="checked"';
	if (isset("type", hostInterface)) {
		hostInterface.checked_agent = '';
		switch (hostInterface.type.toString()) {
			case '<?php print(INTERFACE_TYPE_SNMP);?>': hostInterface.checked_snmp = 'checked="checked"'; break;
			case '<?php print(INTERFACE_TYPE_IPMI);?>': hostInterface.checked_ipmi = 'checked="checked"'; break;
			case '<?php print(INTERFACE_TYPE_JMX);?>': hostInterface.checked_jmx = 'checked="checked"'; break;
			case '<?php print(INTERFACE_TYPE_AGENT);?>':
			default: hostInterface.checked_agent = 'checked="checked"'; break;
		}
	}

	if (hostInterface.locked) {
		hostInterface.lock_snmp = 'disabled="disabled"';
		hostInterface.lock_agent = 'disabled="disabled"';
		hostInterface.lock_jmx = 'disabled="disabled"';
		hostInterface.lock_ipmi = 'disabled="disabled"';
		switch (hostInterface.type.toString()) {
			case '<?php print(INTERFACE_TYPE_SNMP);?>':
				hostInterface.lock_snmp = '';
				break;
			case '<?php print(INTERFACE_TYPE_IPMI);?>':
				hostInterface.lock_ipmi = '';
				break;
			case '<?php print(INTERFACE_TYPE_JMX);?>':
				hostInterface.lock_jmx = '';
				break;
			case '<?php print(INTERFACE_TYPE_AGENT);?>':
				hostInterface.lock_agent = '';
				break;
		}
	}

	if (hostInterface.main == 1) {
		hostInterface.checked_main = 'checked="checked"';
		var mainInterfaceIcon = {icons: {primary: 'ui-icon-check'}};
	}
	else {
		hostInterface.checked_main = '';
		var mainInterfaceIcon = {};
	}

	jQuery("#hostIterfacesFooter").before(tpl.evaluate(hostInterface));

	// remove interface buttonnot
	jQuery('#removeInterface_'+hostInterface.interfaceid).click(function() {
		removeInterfaceRow(hostInterface.interfaceid);
		toggleMainInterfaceSwitches();
	});

	jQuery("#interface_main_"+hostInterface.interfaceid)
			.button(mainInterfaceIcon)
			.click(function() {
				var jThis = jQuery(this);
				var currentInterfaceid = jThis.attr('id').match(/^interface_main_(\d+)$/);
				currentInterfaceid = currentInterfaceid[1];
				var interfaces = getIntefacesByType();

				// find current interface type
				outer:
				for (var interfaceType in interfaces) {
					for (var interfaceid in interfaces[interfaceType].interfaces) {
						if (interfaceid == currentInterfaceid) {
							var currentInterfaceType = interfaceType;
							break outer;
						}
					}
				}

				// uncheck main for all interfaces of found type
				for (var interfaceid in interfaces[currentInterfaceType].interfaces) {
					jQuery('#interface_main_'+interfaceid)
							.prop('checked', false)
							.button('option', 'icons', {})
							.button('refresh');
				}


				// check main for current interface
				jThis.prop('checked', true)
						.button('option', 'icons', {primary: 'ui-icon-check'})
						.button('refresh');
			});

	if (!hostInterface.locked) {
		jQuery("#hostInterfaceRow_"+hostInterface.interfaceid)
			.find("div.jqueryinputset")
			.buttonset()
			.end()
			.find("#interface_type_"+hostInterface.interfaceid)
			.find("label")
			.click({"hostInterface": hostInterface}, function(event){
				var portInput = jQuery("#port_"+event.data.hostInterface.interfaceid)[0];

				if(empty(portInput.value) || !(portInput.value == "10050" || portInput.value == "161" || portInput.value == "623" || portInput.value == "12345")) return true;

				var interfaceTypeId = event.currentTarget.htmlFor.toLowerCase();
				switch(true){
					case (interfaceTypeId.indexOf('agent') > -1): portInput.value = "10050"; break;
					case (interfaceTypeId.indexOf('snmp') > -1): portInput.value = "161"; break;
					case (interfaceTypeId.indexOf('ipmi') > -1): portInput.value = "623"; break;
					case (interfaceTypeId.indexOf('jmx') > -1): portInput.value = "12345"; break;
				}
			});


		jQuery("#hostInterfaceRow_"+hostInterface.interfaceid+" .interfaceTypes input[type=radio]").click(function(event) {
			jQuery('#interface_main_'+hostInterface.interfaceid)
					.prop('checked', false)
					.button('refresh')
					.button('option', 'icons', {});
			toggleMainInterfaceSwitches();
		});
	}
}

function removeInterfaceRow(hostInterfaceId) {
	jQuery('#hostInterfaceRow_'+hostInterfaceId).remove();
	jQuery("#hostIterfaces").accordion('resize');
}

function getIntefacesByType() {
	var mainIntefaces = {
		agent: {
			count: 0,
			interfaces: {}
		},
		snmp: {
			count: 0,
			interfaces: {}
		},
		jmx: {
			count: 0,
			interfaces: {}
		},
		ipmi: {
			count: 0,
			interfaces: {}
		}
	};

	jQuery('#hostInterfaces .interfaceRow').each(function() {
		var interfaceRow = jQuery(this);
		// get interfaceid from id attribute
		var interfaceid = interfaceRow.attr('id').match(/^hostInterfaceRow_(\d+)$/);
		interfaceid = interfaceid[1];

		var isMain = jQuery('#interface_main_'+interfaceid, interfaceRow).prop('checked');

		for (var interfaceType in mainIntefaces) {
			if (jQuery('#radio_'+interfaceType+'_'+interfaceid, interfaceRow).prop('checked')) {
				mainIntefaces[interfaceType].count++;
				mainIntefaces[interfaceType].interfaces[interfaceid] = isMain;
			}
		}
	});

	return mainIntefaces;
}


/**
 * Check and disable interface which is only one for type, enable those which are multiple for type.
 */
function toggleMainInterfaceSwitches() {
	var interfaces = getIntefacesByType();

	for (var interfaceType in interfaces) {
		var typeHasMain = false;

		// set main if one for type
		if (interfaces[interfaceType].count === 1) {
			for (var interfaceid in interfaces[interfaceType].interfaces) {
				jQuery('#interface_main_'+interfaceid)
						.prop('checked', true)
						.button('option', 'icons', {primary: 'ui-icon-check'})
						.button('refresh');
			}
		}

		// check if at least one is set as main
		for (var interfaceid in interfaces[interfaceType].interfaces) {
			if (interfaces[interfaceType].interfaces[interfaceid] == 1) {
				typeHasMain = true;
			}
		}


		// if no main for type, set as main first random
		if (!typeHasMain) {
			for (var interfaceid in interfaces[interfaceType].interfaces) {
				jQuery('#interface_main_'+interfaceid)
						.prop('checked', true)
						.button('option', 'icons', {primary: 'ui-icon-check'})
						.button('refresh');
				break;
			}
		}
	}
}


jQuery(document).ready(function() {
	jQuery('#addInterfaceRow').click(function() {
		addInterfaceRow({});
		toggleMainInterfaceSwitches();
	});

	// radio button of inventory modes was clicked
	jQuery("div.jqueryinputset input[name=inventory_mode]").click(function() {
		// action depending on which button was clicked
		var inventoryFields = jQuery("#inventorylist :input:gt(2)");
		switch(jQuery(this).val()) {
			case '<?php echo HOST_INVENTORY_DISABLED ?>':
				inventoryFields.attr("disabled", "disabled"); // disabling all input fields
				jQuery('.populating_item').hide();
			break;
			case '<?php echo HOST_INVENTORY_MANUAL ?>':
				inventoryFields.removeAttr("disabled"); // enabling all input fields (if they were disabled)
				jQuery('.populating_item').hide();
			break;
			case '<?php echo HOST_INVENTORY_AUTOMATIC ?>':
				// disabling all input fields
				inventoryFields.removeAttr("disabled");
				inventoryFields.filter('.linked_to_item').attr("disabled", "disabled"); // disabling all input fields
				jQuery('.populating_item').show();
			break;
		}

	});

	jQuery('#name').focus();
});


</script>
