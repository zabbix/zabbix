<script type="text/x-jquery-tmpl" id="hostInterfaceRow">

<tr class="interfaceRow" id="hostInterfaceRow_#{iface.interfaceid}" data-interfaceid="#{iface.interfaceid}">
	<td style="width: 2em;">
		<input type="hidden" name="interfaces[#{iface.interfaceid}][isNew]" value="#{iface.isNew}" />
		<input type="hidden" name="interfaces[#{iface.interfaceid}][interfaceid]" value="#{iface.interfaceid}" />
		<input type="hidden" id="interface_type_#{iface.interfaceid}" name="interfaces[#{iface.interfaceid}][type]" value="#{iface.type}" />
	</td>
	<td style="width: 16em;">
		<input class="input text" name="interfaces[#{iface.interfaceid}][ip]" type="text" size="24" value="#{iface.ip}" />
	</td>
	<td style="width: 19em;">
		<input class="input text" name="interfaces[#{iface.interfaceid}][dns]" type="text" size="30" value="#{iface.dns}" />
	</td>
	<td style="width: 9em;">
		<div class="jqueryinputset">
			<input type="radio" id="radio_ip_#{iface.interfaceid}" name="interfaces[#{iface.interfaceid}][useip]" value="1" #{*attrs.checked_ip} />
			<label for="radio_ip_#{iface.interfaceid}"><?php echo _('IP'); ?></label>
			<input type="radio" id="radio_dns_#{iface.interfaceid}" name="interfaces[#{iface.interfaceid}][useip]" value="0" #{*attrs.checked_dns} />
			<label for="radio_dns_#{iface.interfaceid}"><?php echo _('DNS'); ?></label>
		</div>
	</td>
	<td style="width: 10em;">
		<input class="input text" name="interfaces[#{iface.interfaceid}][port]" type="text" size="15" value="#{iface.port}" />
	</td>
	<td style="width: 4em;">
		<input class="mainInterface" type="radio" id="interface_main_#{iface.interfaceid}" name="main_#{iface.type}" value="#{iface.interfaceid}" />
		<label class="checkboxLikeLabel" for="interface_main_#{iface.interfaceid}" style="height: 16px; width: 16px;"></label>
	</td>
	<td  style="width: 4em;">
		<button type="button" id="removeInterface_#{iface.interfaceid}" data-interfaceid="#{iface.interfaceid}" class="link_menu remove" #{*attrs.disabled} ><?php echo _('Remove'); ?></button>
	</td>
</tr>

</script>

<script type="text/javascript">

var hostInterfacesManager = (function() {
	'use strict';

	var rowTemplate = new Template(jQuery('#hostInterfaceRow').html()),
		ports = {
			agent: 10050,
			snmp: 161,
			jmx: 12345,
			ipmi: 623
		},
		allHostInterfaces = {};


	function renderHostInterfaceRow(hostInterface) {
		var domAttrs = getDomElementsAttrsForInterface(hostInterface),
			domId = getDomIdForRowInsert(hostInterface.type),
			domRow;


		jQuery(domId).before(rowTemplate.evaluate({iface: hostInterface, attrs: domAttrs}));

		if (!hostInterface.locked) {
			domRow = jQuery('#hostInterfaceRow_'+hostInterface.interfaceid);
			makeHostInterfaceRowDraggable(domRow);
		}


		/* // remove interface button not
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
			.buttonset();


			jQuery("#hostInterfaceRow_"+hostInterface.interfaceid+" .interfaceTypes input[type=radio]").click(function(event) {
			jQuery('#interface_main_'+hostInterface.interfaceid)
			.prop('checked', false)
			.button('refresh')
			.button('option', 'icons', {});
			toggleMainInterfaceSwitches();
			});
			}
		*/

	}

	function resetMainInterfaces() {
		var hostInterface,
			typeInterfaces,
			hostInterfaces = getMainInterfacesByType();

		for (var hostInterfaceType in hostInterfaces) {
			typeInterfaces = hostInterfaces[hostInterfaceType];

			if (!typeInterfaces.main && typeInterfaces.all.length) {
				for (var i=0; i<typeInterfaces.all.length; i++) {
					if (allHostInterfaces[typeInterfaces.all[i]].main === '1') {
						typeInterfaces.main = allHostInterfaces[typeInterfaces.all[i]].interfaceid;
					}
				}
				if (!typeInterfaces.main) {
					typeInterfaces.main = typeInterfaces.all[0];
					console.log(typeInterfaces.all);
					allHostInterfaces[typeInterfaces.main].main = '1';
				}
			}
		}

		for (var hostInterfaceType in hostInterfaces){
			typeInterfaces = hostInterfaces[hostInterfaceType];

			if (typeInterfaces.main) {
				jQuery('#interface_main_'+typeInterfaces.main).prop('checked', true);
			}
		}
	}

	function getMainInterfacesByType() {
		var hostInterface,
			types = {};
		types[getHostInterfaceNumericType('agent')] = {main: null, all: []};
		types[getHostInterfaceNumericType('snmp')] = {main: null, all: []};
		types[getHostInterfaceNumericType('jmx')] = {main: null, all: []};
		types[getHostInterfaceNumericType('ipmi')] = {main: null, all: []};


		for (var hostInterfaceId in allHostInterfaces){
			hostInterface = allHostInterfaces[hostInterfaceId];

			types[hostInterface.type].all.push(hostInterfaceId);
			if (hostInterface.main === "1") {
				if (types[hostInterface.type].main !== null) {
					throw new Error('Multiple main interfaces for same type.');
				}
				types[hostInterface.type].main = hostInterfaceId;
			}
		}

		return types;
	}


	function makeHostInterfaceRowDraggable(domElement) {
		domElement.children().first().html('<span class="ui-icon ui-icon-arrowthick-2-n-s move"></span>');
		domElement.draggable({
			helper: 'clone',
			handle: 'span.ui-icon-arrowthick-2-n-s',
			revert: 'invalid',
			stop: function() {
				resetMainInterfaces();
			}
		});
	}

	function getDomElementsAttrsForInterface(hostInterface) {
		var attrs = {
			disabled: '',
			checked_dns: '',
			checked_ip: '',
			checked_main: ''
		};

		if (hostInterface.items) {
			attrs.disabled = 'disabled="disabled"';
		}


		if (hostInterface.useip == 0) {
			attrs.checked_dns = 'checked="checked"';
		}
		else {
			attrs.checked_ip = 'checked="checked"';
		}

		if (hostInterface.main) {
			attrs.checked_main = 'checked="checked"';
		}

		if (hostInterface.locked) {
			// TODO: if locked disable dragging
		}

		return attrs;
	}

	function getDomIdForRowInsert(hostInterfaceType) {
		var footerRowId;
		switch (hostInterfaceType) {
			case getHostInterfaceNumericType('agent'):
				footerRowId = '#agentIterfacesFooter';
				break;
			case getHostInterfaceNumericType('snmp'):
				footerRowId = '#SNMPIterfacesFooter';
				break;
			case getHostInterfaceNumericType('jmx'):
				footerRowId = '#JMXIterfacesFooter';
				break;
			case getHostInterfaceNumericType('ipmi'):
				footerRowId = '#IPMIIterfacesFooter';
				break;
			default:
				throw new Error('Unknown host interface type.');
		}

		return footerRowId;
	}

	function getHostInterfaceNumericType(typeName) {
		var typeNum;

		switch(typeName) {
			case 'agent':
				typeNum = '<?php print(INTERFACE_TYPE_AGENT); ?>';
				break;
			case 'snmp':
				typeNum = '<?php print(INTERFACE_TYPE_SNMP); ?>';
				break;
			case 'jmx':
				typeNum = '<?php print(INTERFACE_TYPE_JMX); ?>';
				break;
			case 'ipmi':
				typeNum = '<?php print(INTERFACE_TYPE_IPMI); ?>';
				break;
			default:
				throw new Error('Unknown host interface type name.');
		}

		return typeNum;
	}

	function createNewHostInterface(hostInterfaceType) {
		var newInterface = {
			isNew: true,
			useip: 1,
			type: getHostInterfaceNumericType(hostInterfaceType),
			port: ports[hostInterfaceType]
		};

		newInterface.interfaceid = 1;
		while (allHostInterfaces[newInterface.interfaceid] !== void(0)) {
			newInterface.interfaceid++;
		}

		addHostInterface(newInterface);

		return newInterface;
	}

	function addHostInterface(hostInterface) {
		allHostInterfaces[hostInterface.interfaceid] = hostInterface;
	}

	function moveRowToAnotherTypeTable(hostInterfaceId, newHostInterfaceType) {
		var newDomId = getDomIdForRowInsert(newHostInterfaceType);

		jQuery('#interface_main_'+hostInterfaceId).attr('name', 'main_'+newHostInterfaceType);
		jQuery('#interface_main_'+hostInterfaceId).prop('checked', false);

		jQuery('#hostInterfaceRow_'+hostInterfaceId).insertBefore(newDomId);
		jQuery('#interface_type_'+hostInterfaceId).val(newHostInterfaceType);
	}

	return {
		add: function(hostInterfaces) {
			for (var hostInterfaceId in hostInterfaces) {
				addHostInterface(hostInterfaces[hostInterfaceId]);
				renderHostInterfaceRow(hostInterfaces[hostInterfaceId]);
			}
			resetMainInterfaces();
		},

		addNew: function(type) {
			var hostInterface = createNewHostInterface(type);

			allHostInterfaces[hostInterface.interfaceid] = hostInterface;
			renderHostInterfaceRow(hostInterface);
			resetMainInterfaces();
		},

		remove: function(hostInterfaceId) {
			delete allHostInterfaces[hostInterfaceId];
		},

		setType: function(hostInterfaceId, typeName) {
			var newTypeNum = getHostInterfaceNumericType(typeName);

			if (allHostInterfaces[hostInterfaceId].type !== newTypeNum) {
				moveRowToAnotherTypeTable(hostInterfaceId, newTypeNum);
				allHostInterfaces[hostInterfaceId].type = newTypeNum;
				allHostInterfaces[hostInterfaceId].main = '0';
			}
		},

		resetMainInterfaces: function() {
			resetMainInterfaces();
		},

		setMainInterface: function(hostInterfaceId) {
			var interfacesByType = getMainInterfacesByType(),
				newMainInterfaceType = allHostInterfaces[hostInterfaceId].type,
				oldMainInterfaceId = interfacesByType[newMainInterfaceType].main;

			allHostInterfaces[hostInterfaceId].main = '1';
			allHostInterfaces[oldMainInterfaceId].main = '0';
		}
	}

}());


jQuery(document).ready(function() {
	'use strict';

	jQuery('#hostlist').on('click', 'button.remove', function() {
		var interfaceId = jQuery(this).data('interfaceid');
		jQuery('#hostInterfaceRow_'+interfaceId).remove();
		hostInterfacesManager.remove(interfaceId);
		hostInterfacesManager.resetMainInterfaces();
	});

	jQuery('#hostlist').on('click', 'input[type=radio].mainInterface', function() {
		var interfaceId = jQuery(this).val();
		hostInterfacesManager.setMainInterface(interfaceId);
	});

	jQuery("#agentInterfaces, #SNMPInterfaces, #JMXInterfaces, #IPMIInterfaces").droppable({
		tolerance: 'pointer',
		activeClass: 'dropArea',
		drop: function(event, ui) {
			var hostInterfaceTypeName = jQuery(this).data('type'),
				hostInterfaceId = ui.draggable.data('interfaceid');

			hostInterfacesManager.setType(hostInterfaceId, hostInterfaceTypeName);
			hostInterfacesManager.resetMainInterfaces();
		}
	});


	// radio button of inventory modes was clicked
	jQuery("div.jqueryinputset input[name=inventory_mode]").click(function() {
		// action depending on which button was clicked
		var inventoryFields = jQuery("#inventorylist :input:gt(2)");

		switch(jQuery(this).val()) {
			case '<?php echo HOST_INVENTORY_DISABLED ?>':
				inventoryFields.prop('disabled', true);
				jQuery('.populating_item').hide();
			break;
			case '<?php echo HOST_INVENTORY_MANUAL ?>':
				inventoryFields.prop('disabled', false);
				jQuery('.populating_item').hide();
			break;
			case '<?php echo HOST_INVENTORY_AUTOMATIC ?>':
				inventoryFields.prop('disabled', false);
				inventoryFields.filter('.linked_to_item').prop('disabled', true);
				jQuery('.populating_item').show();
			break;
		}
	});

	jQuery('#name').focus();
});


</script>

