<script type="text/x-jquery-tmpl" id="hostInterfaceRow">
<tr class="interfaceRow" id="hostInterfaceRow_#{iface.interfaceid}" data-interfaceid="#{iface.interfaceid}">
	<td class="interface-drag-control">
		<input type="hidden" name="interfaces[#{iface.interfaceid}][isNew]" value="#{iface.isNew}" />
		<input type="hidden" name="interfaces[#{iface.interfaceid}][interfaceid]" value="#{iface.interfaceid}" />
		<input type="hidden" id="interface_type_#{iface.interfaceid}" name="interfaces[#{iface.interfaceid}][type]" value="#{iface.type}" />
	</td>
	<td class="interface-ip">
		<input class="input text" name="interfaces[#{iface.interfaceid}][ip]" type="text" size="24" maxlength="64" value="#{iface.ip}" />
	</td>
	<td class="interface-dns">
		<input class="input text" name="interfaces[#{iface.interfaceid}][dns]" type="text" size="30" maxlength="64" value="#{iface.dns}" />
	</td>
	<td class="interface-connect-to">
		<div class="jqueryinputset">
			<input class="interface-useip" type="radio" id="radio_ip_#{iface.interfaceid}" name="interfaces[#{iface.interfaceid}][useip]" value="1" #{*attrs.checked_ip} />
			<input class="interface-useip" type="radio" id="radio_dns_#{iface.interfaceid}" name="interfaces[#{iface.interfaceid}][useip]" value="0" #{*attrs.checked_dns} />
			<label for="radio_ip_#{iface.interfaceid}"><?php echo _('IP'); ?></label><label for="radio_dns_#{iface.interfaceid}"><?php echo _('DNS'); ?></label>
		</div>
	</td>
	<td class="interface-port">
		<input class="input text" name="interfaces[#{iface.interfaceid}][port]" type="text" size="15" maxlength="64" value="#{iface.port}" />
	</td>
	<td class="interface-default">
		<input class="mainInterface" type="radio" id="interface_main_#{iface.interfaceid}" name="mainInterfaces[#{iface.type}]" value="#{iface.interfaceid}" />
		<label class="checkboxLikeLabel" for="interface_main_#{iface.interfaceid}" style="height: 16px; width: 16px;"></label>
	</td>
	<td class="interface-control">
		<button type="button" id="removeInterface_#{iface.interfaceid}" data-interfaceid="#{iface.interfaceid}" class="link_menu remove" #{*attrs.disabled}><?php echo _('Remove'); ?></button>
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

			domRow = jQuery('#hostInterfaceRow_' + hostInterface.interfaceid);
			jQuery('.jqueryinputset', domRow).buttonset();

			if (hostInterface.locked) {
				addNotDraggableIcon(domRow);
			}
			else {
				addDraggableIcon(domRow);
			}
		}

		function resetMainInterfaces() {
			var hostInterface,
				typeInterfaces,
				hostInterfaces = getMainInterfacesByType();

			for (var hostInterfaceType in hostInterfaces) {
				typeInterfaces = hostInterfaces[hostInterfaceType];

				if (!typeInterfaces.main && typeInterfaces.all.length) {
					for (var i = 0; i < typeInterfaces.all.length; i++) {
						if (allHostInterfaces[typeInterfaces.all[i]].main === '1') {
							typeInterfaces.main = allHostInterfaces[typeInterfaces.all[i]].interfaceid;
						}
					}
					if (!typeInterfaces.main) {
						typeInterfaces.main = typeInterfaces.all[0];
						allHostInterfaces[typeInterfaces.main].main = '1';
					}
				}
			}

			for (var hostInterfaceType in hostInterfaces){
				typeInterfaces = hostInterfaces[hostInterfaceType];

				if (typeInterfaces.main) {
					jQuery('#interface_main_' + typeInterfaces.main).prop('checked', true);
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

			for (var hostInterfaceId in allHostInterfaces) {
				hostInterface = allHostInterfaces[hostInterfaceId];

				types[hostInterface.type].all.push(hostInterfaceId);
				if (hostInterface.main === '1') {
					if (types[hostInterface.type].main !== null) {
						throw new Error('Multiple default interfaces for same type.');
					}
					types[hostInterface.type].main = hostInterfaceId;
				}
			}
			return types;
		}

		function addDraggableIcon(domElement) {
			domElement.children().first().append('<span class="ui-icon ui-icon-arrowthick-2-n-s move"></span>');
			domElement.draggable({
				helper: 'clone',
				handle: 'span.ui-icon-arrowthick-2-n-s',
				revert: 'invalid',
				stop: function(event, ui) {
					var hostInterfaceId = jQuery(this).data('interfaceid');
					resetMainInterfaces();
					resetUseipInterface(hostInterfaceId)
				}
			});
		}

		function addNotDraggableIcon(domElement) {
			domElement.children().first().append('<span class="ui-icon ui-icon-arrowthick-2-n-s state-disabled"></span>');
			jQuery('.ui-icon', domElement).hover(
				function (event) {
					jQuery('<div>' + <?php echo CJs::encodeJson(_('Interface is used by items that require this type of the interface.')); ?> + '</div>')
						.css({position: 'absolute', opacity: 1, padding: '2px'})
						.addClass('ui-state-highlight')
						.appendTo(event.target.parentNode);
				},
				function (event) {
					jQuery(event.target).next().remove();
				}
			)
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

			switch (typeName) {
				case 'agent':
					typeNum = '<?php echo INTERFACE_TYPE_AGENT; ?>';
					break;
				case 'snmp':
					typeNum = '<?php echo INTERFACE_TYPE_SNMP; ?>';
					break;
				case 'jmx':
					typeNum = '<?php echo INTERFACE_TYPE_JMX; ?>';
					break;
				case 'ipmi':
					typeNum = '<?php echo INTERFACE_TYPE_IPMI; ?>';
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
				port: ports[hostInterfaceType],
				ip: '127.0.0.1'
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

			jQuery('#interface_main_' + hostInterfaceId).attr('name', 'mainInterfaces[' + newHostInterfaceType + ']');
			jQuery('#interface_main_' + hostInterfaceId).prop('checked', false);
			jQuery('#interface_type_' + hostInterfaceId).val(newHostInterfaceType);
			jQuery('#hostInterfaceRow_' + hostInterfaceId).insertBefore(newDomId);
		}

		function resetUseipInterface(hostInterfaceId) {
			var useip = allHostInterfaces[hostInterfaceId].useip;
			if (useip == 0) {
				jQuery('#radio_dns_' + hostInterfaceId).prop('checked', true);
			}
			else {
				jQuery('#radio_ip_' + hostInterfaceId).prop('checked', true);
			}
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

				if (hostInterfaceId !== oldMainInterfaceId) {
					allHostInterfaces[hostInterfaceId].main = '1';
					allHostInterfaces[oldMainInterfaceId].main = '0';
				}
			},

			setUseipForInterface: function(hostInterfaceId, useip) {
				allHostInterfaces[hostInterfaceId].useip = useip;
			},

			disable: function() {
				jQuery('.interface-drag-control, .interface-control').html('');
				jQuery('.interfaceRow').find('input').attr('readonly', true);
				jQuery('.interfaceRow').find('input[type="radio"]').attr('disabled', true);
				jQuery('.interface-connect-to').find('input').button('disable');
			}
		}
	}());

	jQuery(document).ready(function() {
		'use strict';

		jQuery('#hostlist').on('click', 'button.remove', function() {
			var interfaceId = jQuery(this).data('interfaceid');
			jQuery('#hostInterfaceRow_' + interfaceId).remove();
			hostInterfacesManager.remove(interfaceId);
			hostInterfacesManager.resetMainInterfaces();
		});

		jQuery('#hostlist').on('click', 'input[type=radio].mainInterface', function() {
			var interfaceId = jQuery(this).val();
			hostInterfacesManager.setMainInterface(interfaceId);
		});

		// when we start dragging row, all radio buttons are unchecked for some reason, we store radio buttons values
		// to restore them when drag is ended
		jQuery('#hostlist').on('click', 'input[type=radio].interface-useip', function() {
			var interfaceId = jQuery(this).attr('id').match(/\d+/);
			hostInterfacesManager.setUseipForInterface(interfaceId[0], jQuery(this).val());
		});

		jQuery('#agentInterfaces, #SNMPInterfaces, #JMXInterfaces, #IPMIInterfaces').parent().droppable({
			tolerance: 'pointer',
			drop: function(event, ui) {
				var hostInterfaceTypeName = jQuery('.formElementTable', this).data('type'),
					hostInterfaceId = ui.draggable.data('interfaceid');

				ui.helper.remove();

				hostInterfacesManager.setType(hostInterfaceId, hostInterfaceTypeName);
				hostInterfacesManager.resetMainInterfaces();
			},
			activate: function(event, ui) {
				if (!jQuery(this).find(ui.draggable).length) {
					jQuery(this).addClass('dropArea');
					jQuery('span.dragHelpText', this).toggle();
				}
			},
			deactivate: function(event, ui) {
				jQuery(this).removeClass('dropArea');
				jQuery('span.dragHelpText', this).toggle(false);
			}
		});

		jQuery('#addAgentInterface').on('click', function() {
			hostInterfacesManager.addNew('agent');
		});
		jQuery('#addSNMPInterface').on('click', function() {
			hostInterfacesManager.addNew('snmp');
		});
		jQuery('#addJMXInterface').on('click', function() {
			hostInterfacesManager.addNew('jmx');
		});
		jQuery('#addIPMIInterface').on('click', function() {
			hostInterfacesManager.addNew('ipmi');
		});

		// radio button of inventory modes was clicked
		jQuery('div.jqueryinputset input[name=inventory_mode]').click(function() {
			// action depending on which button was clicked
			var inventoryFields = jQuery('#inventorylist :input:gt(2)');

			switch (jQuery(this).val()) {
				case '<?php echo HOST_INVENTORY_DISABLED; ?>':
					inventoryFields.prop('disabled', true);
					jQuery('.populating_item').hide();
					break;
				case '<?php echo HOST_INVENTORY_MANUAL; ?>':
					inventoryFields.prop('disabled', false);
					jQuery('.populating_item').hide();
					break;
				case '<?php echo HOST_INVENTORY_AUTOMATIC; ?>':
					inventoryFields.prop('disabled', false);
					inventoryFields.filter('.linked_to_item').prop('disabled', true);
					jQuery('.populating_item').show();
					break;
			}
		});

		/**
		 * Mass update
		 */
		jQuery('#mass_replace_tpls').on('change', function() {
			jQuery('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();
	});

	function removeTemplate(templateid) {
		jQuery('#templates_' + templateid).remove();
		jQuery('#template_row_' + templateid).remove();
	}

</script>
