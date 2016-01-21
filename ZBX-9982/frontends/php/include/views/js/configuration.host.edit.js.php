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
	<td class="interface_data">
		<input name="interfaces[#{iface.interfaceid}][dns]" type="text" style="width: <?= ZBX_TEXTAREA_INTERFACE_DNS_WIDTH ?>px" maxlength="64" value="#{iface.dns}">
	</td>
	<?= (new CCol(
			(new CRadioButtonList('interfaces[#{iface.interfaceid}][useip]', null))
				->addValue(_('IP'), INTERFACE_USE_IP, 'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_IP.']')
				->addValue(_('DNS'), INTERFACE_USE_DNS,
					'interfaces[#{iface.interfaceid}][useip]['.INTERFACE_USE_DNS.']'
				)
				->setModern(true)
		))
			->addClass('interface_data')
			->toString()
	?>
	<td class="interface_data">
		<input name="interfaces[#{iface.interfaceid}][port]" type="text" style="width: <?= ZBX_TEXTAREA_INTERFACE_PORT_WIDTH ?>px" maxlength="64" value="#{iface.port}">
	</td>
	<td class="interface_data">
		<input class="mainInterface" type="radio" id="interface_main_#{iface.interfaceid}" name="mainInterfaces[#{iface.type}]" value="#{iface.interfaceid}">
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
				<?= INTERFACE_TYPE_AGENT ?>: 10050,
				<?= INTERFACE_TYPE_SNMP ?>: 161,
				<?= INTERFACE_TYPE_JMX ?>: 12345,
				<?= INTERFACE_TYPE_IPMI ?>: 623
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
				addDraggable(domRow);
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

		/**
		 * Enable and do dragging to selected elements.
		 *
		 * @param object domElement
		 */
		function addDraggable(domElement) {
			var dragInterfaceType,
				interfaceid = domElement.data('interfaceid');
			jQuery('div.<?= ZBX_STYLE_DRAG_ICON ?>', domElement).on('mousedown', function() {
				jQuery('input', domElement).blur();
			});
			domElement.draggable({
				helper: function() {
					var row = jQuery(this).clone();
					jQuery('input', row).removeAttr('name');

					return row;
				},
				axis: 'y',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				revert: function(event, ui) {
					return (event !== false && dragInterfaceType != jQuery(this).data('type')) ? false : true;
				},
				start: function(event, ui) {
					dragInterfaceType = jQuery(this).data('type');

					ui.helper.addClass('<?= ZBX_STYLE_CURSOR_MOVE ?>');
					ui.helper.css({'z-index': '1000'});
					jQuery('.interface_name', ui.helper).remove();
					jQuery('.interface_data', this).css({'visibility': 'hidden'});
				},
				stop: function(event, ui) {
					var hostInterface = allHostInterfaces[interfaceid];
					resetMainInterfaces();
					jQuery('.interface_data', this).css({'visibility': ''});
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

		/*
		 * Enable droppable for selected elements.
		 *
		 * @param objects domElements
		 */
		function addDroppable(domElements) {
			domElements.droppable({
				tolerance: 'pointer',
				drop: function(event, ui) {
					var interfaceid = ui.draggable.data('interfaceid'),
						dragInterfaceType = allHostInterfaces[interfaceid].type,
						dropInterfaceType = jQuery(this).data('type'),
						objInterfaceName = jQuery('.interface_name', '#hostInterfaceRow_' + interfaceid);

					if (dragInterfaceType == dropInterfaceType) {
						return;
					}

					if (dropInterfaceType == <?= INTERFACE_TYPE_SNMP ?>) {
						if (jQuery('.interface-bulk', jQuery('#hostInterfaceRow_' + interfaceid)).length == 0) {
							var bulkDiv = jQuery('<div>', {
								'class': 'interface-bulk'
							});

							// append checkbox
							bulkDiv.append(jQuery('<input>', {
								id: 'interfaces[' + interfaceid + '][bulk]',
								'class': 'input checkbox pointer',
								type: 'checkbox',
								name: 'interfaces[' + interfaceid + '][bulk]',
								value: 1,
								checked: true
							}));

							// append label
							bulkDiv.append(jQuery('<label>', {
								'for': 'interfaces[' + interfaceid + '][bulk]',
								text: '<?= _('Use bulk requests') ?>'
							}));

							jQuery('.interface-ip', jQuery('#hostInterfaceRow_' + interfaceid)).append(bulkDiv);
						}
					}
					else {
						jQuery('.interface-bulk', jQuery('#hostInterfaceRow_' + interfaceid)).remove();
					}

					decreaseRowspan(jQuery('.interface_type_' + dragInterfaceType).first());
					increaseRowspan(jQuery('.interface_type_' + dropInterfaceType));

					moveRowToAnotherTypeTable(interfaceid, dropInterfaceType);
					hostInterfacesManager.setType(interfaceid, dropInterfaceType);
					hostInterfacesManager.resetMainInterfaces();

					moveInterfaceName(dropInterfaceType);
					if (objInterfaceName.length != 0) {
						moveInterfaceName(dragInterfaceType, objInterfaceName);
					}
				},
				activate: function(event, ui) {
					var interfaceid = ui.draggable.data('interfaceid');
					jQuery('.interface_row, .interface_add').not('.interface_type_' + allHostInterfaces[interfaceid].type)
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
					throw new Error('Unknown host interface type');
			}

			return footerRowId;
		}

		function createNewHostInterface(hostInterfaceType) {
			var newInterface = {
				isNew: true,
				useip: 1,
				type: parseInt(hostInterfaceType),
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
			var newDomId = getDomIdForRowInsert(type);

			jQuery('#interface_main_' + hostInterfaceId).attr('name', 'mainInterfaces[' + type + ']');
			jQuery('#interface_main_' + hostInterfaceId).prop('checked', false);
			jQuery('#interface_type_' + hostInterfaceId).val(type);
			jQuery('#hostInterfaceRow_' + hostInterfaceId).insertBefore(newDomId);

			jQuery('#hostInterfaceRow_' + hostInterfaceId).data('type', type);
			jQuery('#hostInterfaceRow_' + hostInterfaceId)
				.removeClass('interface_type_' + allHostInterfaces[hostInterfaceId].type);
			jQuery('#hostInterfaceRow_' + hostInterfaceId).addClass('interface_type_' + type);

		}

		/*
		 * Increase rowspan of row.
		 *
		 * @param objects domRows
		 */
		function increaseRowspan(domRows) {
			jQuery('.interface_name', domRows).attr('rowspan', function(i, value) {
				return parseInt(value) + 1;
			});
		}

		/*
		 * Decreases rowspan of row.
		 *
		 * @param object domRow
		 */
		function decreaseRowspan(domRow) {
			jQuery('.interface_name', domRow).attr('rowspan', function(i, value) {
				return parseInt(value) - 1;
			});
		}

		/*
		 * Prepend domElement to first interface with matched type
		 *
		 * @param int type
		 * @param object domElement
		 */
		function moveInterfaceName(type, domElement) {
			domElement = (typeof domElement !== 'undefined')
				? domElement
				: jQuery('.interface_name', jQuery('.interface_type_' + type));

			jQuery('.interface_type_' + type).first().prepend(domElement);
		}

		return {
			add: function(hostInterfaces) {
				for (var i = 0; i < hostInterfaces.length; i++) {
					addHostInterface(hostInterfaces[i]);
					renderHostInterfaceRow(hostInterfaces[i]);
					increaseRowspan(jQuery('.interface_type_' + hostInterfaces[i].type));
					moveInterfaceName(hostInterfaces[i].type);
				}
				resetMainInterfaces();
				addDroppable(jQuery('.interface_row'));
			},

			addNew: function(type) {
				var hostInterface = createNewHostInterface(type);

				allHostInterfaces[hostInterface.interfaceid] = hostInterface;
				renderHostInterfaceRow(hostInterface);
				increaseRowspan(jQuery('.interface_type_' + type));
				moveInterfaceName(type)
				resetMainInterfaces();
				addDroppable(jQuery('#hostInterfaceRow_' + hostInterface.interfaceid));
			},

			remove: function(hostInterfaceId) {
				var type = allHostInterfaces[hostInterfaceId].type,
					rows = jQuery('.interface_type_' + type);
				decreaseRowspan(rows.first());

				if (jQuery('.interface_name', '#hostInterfaceRow_' + hostInterfaceId).length != 0) {
					jQuery('#hostInterfaceRow_' + hostInterfaceId).remove();
					moveInterfaceName(type, jQuery('.interface_name', rows));
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

			disable: function() {
				jQuery('.interface-drag-control, .interface-control').html('');
				jQuery('.interface_row').find('input')
					.removeAttr('id')
					.removeAttr('name');
				jQuery('.interface_row').find('input[type="text"]').attr('readonly', true);
				jQuery('.interface_row').find('input[type="radio"], input[type="checkbox"]').attr('disabled', true);
			},

			addDroppable: function(domElements) {
				addDroppable(domElements);
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
			hostInterfacesManager.addNew(<?= INTERFACE_TYPE_AGENT ?>);
		});
		jQuery('#addSNMPInterface').on('click', function() {
			hostInterfacesManager.addNew(<?= INTERFACE_TYPE_SNMP ?>);
		});
		jQuery('#addJMXInterface').on('click', function() {
			hostInterfacesManager.addNew(<?= INTERFACE_TYPE_JMX ?>);
		});
		jQuery('#addIPMIInterface').on('click', function() {
			hostInterfacesManager.addNew(<?= INTERFACE_TYPE_IPMI ?>);
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

</script>
