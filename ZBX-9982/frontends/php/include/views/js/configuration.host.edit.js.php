<script type="text/x-jquery-tmpl" id="hostInterfaceRow">
<tr class="interface_row interface_type_#{iface.type}" id="hostInterfaceRow_#{iface.interfaceid}" data-type="#{iface.type}" data-interfaceid="#{iface.interfaceid}">
	<td class="interface-drag-control interface_data <?= ZBX_STYLE_TD_DRAG_ICON ?>">
		<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
		<input type="hidden" name="interfaces[#{iface.interfaceid}][items]" value="#{iface.items}" />
		<input type="hidden" name="interfaces[#{iface.interfaceid}][locked]" value="#{iface.locked}" />
	</td>
	<td class="interface-ip interface_data">
		<input type="hidden" name="interfaces[#{iface.interfaceid}][isNew]" value="#{iface.isNew}">
		<input type="hidden" name="interfaces[#{iface.interfaceid}][interfaceid]" value="#{iface.interfaceid}">
		<input type="hidden" id="interface_type_#{iface.interfaceid}" name="interfaces[#{iface.interfaceid}][type]" value="#{iface.type}">
		<input name="interfaces[#{iface.interfaceid}][ip]" type="text" style="width: <?= ZBX_TEXTAREA_INTERFACE_IP_WIDTH ?>px" maxlength="64" value="#{iface.ip}">
		<div class="interface-bulk">
			<input type="checkbox" id="interfaces[#{iface.interfaceid}][bulk]" name="interfaces[#{iface.interfaceid}][bulk]" value="1" #{*attrs.checked_bulk}>
			<label for="interfaces[#{iface.interfaceid}][bulk]"><?= _('Use bulk requests') ?></label>
		</div>
	</td>
	<td class="interface-dns interface_data">
		<input name="interfaces[#{iface.interfaceid}][dns]" type="text" style="width: <?= ZBX_TEXTAREA_INTERFACE_DNS_WIDTH ?>px" maxlength="64" value="#{iface.dns}">
	</td>
	<?= (new CCol(
			(new CRadioButtonList('interfaces[#{iface.interfaceid}][useip]', null))
				->addValue(_('IP'), INTERFACE_USE_IP, 'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_IP.']')
				->addValue(_('DNS'), INTERFACE_USE_DNS,
					'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_DNS.']'
				)
				->setModern(true)
		))->addClass('interface_data')->toString()
	?>
	<td class="interface-port interface_data">
		<input name="interfaces[#{iface.interfaceid}][port]" type="text" style="width: <?= ZBX_TEXTAREA_INTERFACE_PORT_WIDTH ?>px" maxlength="64" value="#{iface.port}">
	</td>
	<td class="interface-default interface_data">
		<input class="mainInterface" type="radio" id="interface_main_#{iface.interfaceid}" name="mainInterfaces[#{iface.type}]" value="#{iface.interfaceid}">
		<label class="checkboxLikeLabel" for="interface_main_#{iface.interfaceid}" style="height: 16px; width: 16px;"></label>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP?> interface-control interface_data">
		<button class="<?= ZBX_STYLE_BTN_LINK ?> remove" type="button" id="removeInterface_#{iface.interfaceid}" data-interfaceid="#{iface.interfaceid}" #{*attrs.disabled}><?= _('Remove') ?></button>
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

			if (hostInterface.type != <?= INTERFACE_TYPE_SNMP ?>) {
				jQuery('.interface-bulk', domRow).remove();
			}

			jQuery('#interfaces_' + hostInterface.interfaceid + '_useip_' + hostInterface.useip).prop('checked', true);

			if (hostInterface.locked > 0) {
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
			types[<?= INTERFACE_TYPE_AGENT ?>] = {main: null, all: []};
			types[<?= INTERFACE_TYPE_SNMP ?>] = {main: null, all: []};
			types[<?= INTERFACE_TYPE_JMX ?>] = {main: null, all: []};
			types[<?= INTERFACE_TYPE_IPMI ?>] = {main: null, all: []};

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
			domElement.draggable({
				helper: 'clone',
				axis: 'y',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				revert: 'invalid',
				start: function(event, ui) {
					ui.helper.css({'z-index': '1000'});
					ui.helper.addClass('<?= ZBX_STYLE_CURSOR_MOVE ?>');
					jQuery('.interface_name', ui.helper).remove();
					jQuery('.interface_data', this).css({'visibility': 'hidden'});
				},
				stop: function(event, ui) {
					var hostInterfaceId = jQuery(this).data('interfaceid'),
						hostInterface = allHostInterfaces[hostInterfaceId];
					resetMainInterfaces();

					jQuery('.interface_data', this).css({'visibility': ''});
					jQuery(ui.helper).css({'z-index': ''});

					jQuery('#interfaces_' + hostInterfaceId + '_useip_' + hostInterface.useip).prop('checked', true);
				}
			});
		}

		function addNotDraggableIcon(domElement) {
			jQuery('td.<?= ZBX_STYLE_TD_DRAG_ICON ?> div.<?= ZBX_STYLE_DRAG_ICON ?>', domElement)
				.addClass('<?= ZBX_STYLE_DISABLED ?>')
				.hover(
					function (event) {
						hintBox.showHint(event, this,
							<?= CJs::encodeJson(_('Interface is used by items that require this type of the interface.')) ?>
						);
					},
					function (event) {
						hintBox.hideHint(event, this);
					}
				);
		}

		function setDroppable() {
			jQuery('.interface_row, .interface_add').droppable({
				tolerance: 'pointer',
				drop: function(event, ui) {
					var interfaceId = ui.draggable.data('interfaceid'),
						dragInterfaceType = allHostInterfaces[interfaceId].type,
						dropInterfaceType = jQuery(this).data('type'),
						dragRow = jQuery('#hostInterfaceRow_' + interfaceId),
						objInterfaceName = jQuery('.interface_name', dragRow);

					if (dragInterfaceType == dropInterfaceType) {
						return;
					}

					ui.helper.remove();
					if (dropInterfaceType == <?= INTERFACE_TYPE_SNMP ?>) {
						if (jQuery('.interface-bulk', jQuery('#hostInterfaceRow_' + interfaceId)).length == 0) {
							var bulkDiv = jQuery('<div>', {
								'class': 'interface-bulk'
							});

							// append checkbox
							bulkDiv.append(jQuery('<input>', {
								id: 'interfaces[' + interfaceId + '][bulk]',
								'class': 'input checkbox pointer',
								type: 'checkbox',
								name: 'interfaces[' + interfaceId + '][bulk]',
								value: 1,
								checked: true
							}));

							// append label
							bulkDiv.append(jQuery('<label>', {
								'for': 'interfaces[' + interfaceId + '][bulk]',
								text: '<?= _('Use bulk requests') ?>'
							}));

							jQuery('.interface-ip', jQuery('#hostInterfaceRow_' + interfaceId)).append(bulkDiv);
						}
					}
					else {
						jQuery('.interface-bulk', jQuery('#hostInterfaceRow_' + interfaceId)).remove();
					}

					decreaseRowspan('.interface_type_' + dragInterfaceType);
					increaseRowspan('.interface_type_' + dropInterfaceType);

					moveRowToAnotherTypeTable(interfaceId, dropInterfaceType);
					hostInterfacesManager.setType(interfaceId, dropInterfaceType);
					hostInterfacesManager.resetMainInterfaces();

					moveRowspan(dropInterfaceType);
					if (objInterfaceName.length != 0) {
						moveRowspan(dragInterfaceType, objInterfaceName);
					}
				},
				activate: function(event, ui) {
					var interfaceId = ui.draggable.data('interfaceid');
					jQuery('.interface_row:not(.interface_type_' + allHostInterfaces[interfaceId].type + '), .interface_add:not(.interface_type_' + allHostInterfaces[interfaceId].type + ')')
						.addClass('<?= ZBX_STYLE_DRAG_DROP_AREA ?>');
				},
				deactivate: function(event, ui) {
					jQuery('.interface_row, .interface_add').removeClass('<?= ZBX_STYLE_DRAG_DROP_AREA ?>');
				}
			});
		}

		function getDomElementsAttrsForInterface(hostInterface) {
			var attrs = {
				disabled: ''
			};

			if (hostInterface.items > 0) {
				attrs.disabled = 'disabled="disabled"';
			}

			if (hostInterface.type == <?= INTERFACE_TYPE_SNMP ?>) {
				if (hostInterface.bulk == 1) {
					attrs.checked_bulk = 'checked="checked"';
				}
				else {
					attrs.checked_bulk = '';
				}
			}

			return attrs;
		}

		function getDomIdForRowInsert(hostInterfaceType) {
			var footerRowId;

			switch (parseInt(hostInterfaceType)) {
				case <?= INTERFACE_TYPE_AGENT ?>:
					footerRowId = '#agentInterfacesFooter';
					break;
				case <?= INTERFACE_TYPE_SNMP ?>:
					footerRowId = '#SNMPInterfacesFooter';
					break;
				case <?= INTERFACE_TYPE_JMX ?>:
					footerRowId = '#JMXInterfacesFooter';
					break;
				case <?= INTERFACE_TYPE_IPMI ?>:
					footerRowId = '#IPMIInterfacesFooter';
					break;
				default:
					throw new Error('Unknown host interface type ' + hostInterfaceType);
			}
			return footerRowId;
		}

		function createNewHostInterface(hostInterfaceType) {
			var newInterface = {
				isNew: true,
				useip: 1,
				type: getHostInterfaceNumericType(hostInterfaceType),
				port: ports[hostInterfaceType],
				ip: '127.0.0.1'
			};

			if (newInterface.type == <?= INTERFACE_TYPE_SNMP ?>) {
				newInterface.bulk = 1;
			}

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

		function moveRowToAnotherTypeTable(hostInterfaceId, type) {
			var newDomId = getDomIdForRowInsert(type),
				lastClassName = 'interface_type_' + allHostInterfaces[hostInterfaceId].type;

			jQuery('#interface_main_' + hostInterfaceId).attr('name', 'mainInterfaces[' + type + ']');
			jQuery('#interface_main_' + hostInterfaceId).prop('checked', false);
			jQuery('#interface_type_' + hostInterfaceId).val(type);
			jQuery('#hostInterfaceRow_' + hostInterfaceId).insertBefore(newDomId);

			jQuery('#hostInterfaceRow_' + hostInterfaceId).data('type', type);
			jQuery('#hostInterfaceRow_' + hostInterfaceId).removeClass(lastClassName);
			jQuery('#hostInterfaceRow_' + hostInterfaceId).addClass('interface_type_' + type);

		}

		/*
		 * Increase rowspan for given html DOM element object
		 *
		 * @param domRow array of DOM element objects
		 */
		function increaseRowspan(domRow) {
			jQuery('.interface_name', domRow).attr('rowspan', function(i, value) {
				return parseInt(value) + 1;
			});
		}

		/*
		 * Decreases rowspan for given html DOM element object
		 *
		 * @param domRow DOM element object
		 */
		function decreaseRowspan(domRow) {
			jQuery('.interface_name', domRow).attr('rowspan', function(i, value) {
				return parseInt(value) - 1;
			});
		}

		function moveRowspan(type, domElement) {
			domElement = (typeof domElement !== 'undefined')
				? domElement
				: jQuery('.interface_name', jQuery('.interface_type_' + type));

			jQuery('.interface_type_' + type).first().prepend(domElement);
			console.log(domElement);
		}

		return {
			add: function(hostInterfaces) {
				for (var i = 0; i < hostInterfaces.length; i++) {
					addHostInterface(hostInterfaces[i]);
					renderHostInterfaceRow(hostInterfaces[i]);
					increaseRowspan('.interface_type_' + hostInterfaces[i].type);
					moveRowspan(hostInterfaces[i].type);
				}
				resetMainInterfaces();
				setDroppable();
			},

			addNew: function(type) {
				var hostInterface = createNewHostInterface(type);

				allHostInterfaces[hostInterface.interfaceid] = hostInterface;
				renderHostInterfaceRow(hostInterface);
				increaseRowspan('.interface_type_' + hostInterface.type);
				moveRowspan(hostInterface.type)
				resetMainInterfaces();
				setDroppable();
			},

			remove: function(hostInterfaceId) {
				var rows = jQuery('.interface_type_' + allHostInterfaces[hostInterfaceId].type);
				decreaseRowspan(rows.first());

				if (jQuery('.interface_name', '#hostInterfaceRow_' + hostInterfaceId).length != 0) {
					jQuery('#hostInterfaceRow_' + hostInterfaceId).remove();
					moveRowspan(rows.data('type'), jQuery('.interface_name', rows));
				}
				else {
					jQuery('#hostInterfaceRow_' + hostInterfaceId).remove();
				}
				delete allHostInterfaces[hostInterfaceId];
			},

			setType: function(hostInterfaceId, type) {
				allHostInterfaces[hostInterfaceId].type = type;
				allHostInterfaces[hostInterfaceId].main = '0';
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
				jQuery('.interface_row').find('input')
					.removeAttr('id')
					.removeAttr('name');
				jQuery('.interface_row').find('input[type="text"]').attr('readonly', true);
				jQuery('.interface_row').find('input[type="radio"], input[type="checkbox"]').attr('disabled', true);
			}
		}
	}());

	jQuery(document).ready(function() {
		'use strict';

		jQuery('#hostlist').on('click', 'button.remove', function() {
			var interfaceId = jQuery(this).data('interfaceid');

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

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			// If certificate is selected or checked.
			if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| jQuery('#tls_in_cert').is(':checked')) {
				jQuery('#tls_issuer, #tls_subject').closest('li').show();
			}
			else {
				jQuery('#tls_issuer, #tls_subject').closest('li').hide();
			}

			// If PSK is selected or checked.
			if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					|| jQuery('#tls_in_psk').is(':checked')) {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').show();
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').hide();
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
		jQuery('input[name=inventory_mode]').click(function() {
			// action depending on which button was clicked
			var inventoryFields = jQuery('#inventorylist :input:gt(2)');

			switch (jQuery(this).val()) {
				case '<?= HOST_INVENTORY_DISABLED ?>':
					inventoryFields.prop('disabled', true);
					jQuery('.populating_item').hide();
					break;
				case '<?= HOST_INVENTORY_MANUAL ?>':
					inventoryFields.prop('disabled', false);
					jQuery('.populating_item').hide();
					break;
				case '<?= HOST_INVENTORY_AUTOMATIC ?>':
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

		// Refresh field visibility on document load.
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			jQuery('#tls_in_none').prop('checked', true);
		}
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			jQuery('#tls_in_psk').prop('checked', true);
		}
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
			jQuery('#tls_in_cert').prop('checked', true);
		}

		jQuery('input[name=tls_connect]').trigger('change');

		// Depending on checkboxes, create a value for hidden field 'tls_accept'.
		jQuery('#hostForm').submit(function() {
			var tls_accept = 0x00;

			if (jQuery('#tls_in_none').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
			}
			if (jQuery('#tls_in_psk').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
			}
			if (jQuery('#tls_in_cert').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_CERTIFICATE ?>;
			}

			jQuery('#tls_accept').val(tls_accept);
		});
	});

	function getHostInterfaceNumericType(typeName) {
		var typeNum;

		switch (typeName) {
			case 'agent':
				typeNum = <?= INTERFACE_TYPE_AGENT ?>;
				break;
			case 'snmp':
				typeNum = <?= INTERFACE_TYPE_SNMP ?>;
				break;
			case 'jmx':
				typeNum = <?= INTERFACE_TYPE_JMX ?>;
				break;
			case 'ipmi':
				typeNum = <?= INTERFACE_TYPE_IPMI ?>;
				break;
			default:
				throw new Error('Unknown host interface type name.');
		}
		return typeNum;
	}
</script>
