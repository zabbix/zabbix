<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script type="text/x-jquery-tmpl" id="dcheck-row-tmpl">
	<?= (new CRow([
			(new CCol(
				(new CDiv('#{name}'))->addClass(ZBX_STYLE_WORDWRAP)
			))->setId('dcheckCell_#{dcheckid}'),
			(new CHorList([
				(new CButton(null, _('Edit')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->setAttribute('data-action', 'edit'),
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick("removeDCheckRow('#{dcheckid}');")
			]))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('dcheckRow_#{dcheckid}')
			->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="unique-row-tmpl">
	<?= (new CListItem([
			(new CInput('radio', 'uniqueness_criteria', '#{dcheckid}'))
				->addClass(ZBX_STYLE_CHECKBOX_RADIO)
				->setId('uniqueness_criteria_#{dcheckid}'),
			(new CLabel([new CSpan(), '#{name}'], 'uniqueness_criteria_#{dcheckid}'))->addClass(ZBX_STYLE_WORDWRAP)
		]))
			->setId('uniqueness_criteria_row_#{dcheckid}')
			->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="host-source-row-tmpl">
	<?= (new CListItem([
			(new CInput('radio', 'host_source', '_#{dcheckid}'))
				->addClass(ZBX_STYLE_CHECKBOX_RADIO)
				->setAttribute('data-id', '#{dcheckid}')
				->setId('host_source_#{dcheckid}'),
			(new CLabel([new CSpan(), '#{name}'], 'host_source_#{dcheckid}'))->addClass(ZBX_STYLE_WORDWRAP)
		]))
			->setId('host_source_row_#{dcheckid}')
			->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="name-source-row-tmpl">
	<?= (new CListItem([
			(new CInput('radio', 'name_source', '_#{dcheckid}'))
				->addClass(ZBX_STYLE_CHECKBOX_RADIO)
				->setAttribute('data-id', '#{dcheckid}')
				->setId('name_source_#{dcheckid}'),
			(new CLabel([new CSpan(), '#{name}'], 'name_source_#{dcheckid}'))->addClass(ZBX_STYLE_WORDWRAP)
		]))
			->setId('name_source_row_#{dcheckid}')
			->toString()
	?>
</script>

<script type="text/javascript">
	var ZBX_CHECKLIST = {};

	function addDCheck(list) {
		var available_device_types = [<?= SVC_AGENT ?>, <?= SVC_SNMPv1 ?>, <?= SVC_SNMPv2c ?>, <?= SVC_SNMPv3 ?>];

		var addNewValue = function(value) {
			ZBX_CHECKLIST[value.dcheckid] = value;

			jQuery('#dcheckListFooter').before(new Template(jQuery('#dcheck-row-tmpl').html()).evaluate(value));

			for (var field_name in value) {
				if (value.hasOwnProperty(field_name)) {
					var $input = jQuery('<input>', {
						name: 'dchecks[' + value.dcheckid + '][' + field_name + ']',
						type: 'hidden',
						value: value[field_name]
					});

					jQuery('#dcheckCell_' + value.dcheckid).append($input);
				}
			}
		}

		var updateNewValue = function(value) {
			ZBX_CHECKLIST[value.dcheckid] = value;

			var ignore_names = ['druleid', 'dcheckid', 'name', 'ports', 'type', 'uniq'];

			// Clean values.
			jQuery('#dcheckCell_' + value.dcheckid + ' input').each(function(i, item) {
				var $item = jQuery(item);

				var name = $item
					.attr('name')
					.replace('dchecks[' + value.dcheckid + '][', '');
				name = name.substring(0, name.length - 1);

				if (jQuery.inArray(name, ignore_names) == -1) {
					$item.remove();
				}
			});

			// Set values.
			for (var field_name in value) {
				if (value.hasOwnProperty(field_name)) {
					var $obj = jQuery('input[name="dchecks[' + value.dcheckid + '][' + field_name + ']"]');

					// If the input exists, update the value or create it otherwise.
					if ($obj.length) {
						$obj.val(value[field_name]);
					}
					else {
						var $input = jQuery('<input>', {
							name: 'dchecks[' + value.dcheckid + '][' + field_name + ']',
							type: 'hidden',
							value: value[field_name]
						});

						jQuery('#dcheckCell_' + value.dcheckid).append($input);
					}
				}
			}

			// Update check name.
			jQuery('#dcheckCell_' + value.dcheckid + ' .wordwrap').text(value['name']);
		}

		for (var i = 0; i < list.length; i++) {
			if (empty(list[i])) {
				continue;
			}

			var value = list[i];

			if (typeof value.dcheckid === 'undefined') {
				for (;;) {
					value.dcheckid = getUniqueId();

					if (typeof ZBX_CHECKLIST[value.dcheckid] === 'undefined') {
						break;
					}
				}
			}

			if (typeof ZBX_CHECKLIST[value.dcheckid] === 'undefined') {
				addNewValue(value);
			}
			else {
				updateNewValue(value);
			}

			var elements = {
				uniqueness_criteria: ['ip',  new Template(jQuery('#unique-row-tmpl').html()).evaluate(value)],
				host_source: ['chk_dns', new Template(jQuery('#host-source-row-tmpl').html()).evaluate(value)],
				name_source: ['chk_host', new Template(jQuery('#name-source-row-tmpl').html()).evaluate(value)]
			};

			jQuery.each(elements, function(key, param) {
				var $obj = jQuery('#' + key + '_row_' + value.dcheckid);

				if (jQuery.inArray(parseInt(value.type, 10), available_device_types) > -1) {
					var new_obj = param[1];
					if ($obj.length) {
						var checked_id = jQuery('input:radio[name=' + key + ']:checked').attr('id');
						$obj.replaceWith(new_obj);
						jQuery('#' + checked_id).prop('checked', true);
					}
					else {
						jQuery('#' + key).append(new_obj);
					}
				}
				else {
					if ($obj.length) {
						$obj.remove();
						jQuery('#' + key + '_' + param[0]).prop('checked', true);
					}
				}
			});
		}
	}

	function removeDCheckRow(dcheckid) {
		jQuery('#dcheckRow_' + dcheckid).remove();

		delete(ZBX_CHECKLIST[dcheckid]);

		var elements = {
			uniqueness_criteria_: 'ip',
			host_source_: 'chk_dns',
			name_source_: 'chk_host'
		};

		jQuery.each(elements, function(key, def) {
			var $obj = jQuery('#' + key + dcheckid);

			if ($obj.length) {
				if ($obj.is(':checked')) {
					jQuery('#' + key + def).prop('checked', true);
				}
				jQuery('#' + key + 'row_' + dcheckid).remove();
			}
		});
	}

	jQuery(function() {
		addDCheck(<?= json_encode(array_values($data['drule']['dchecks'])) ?>);

		jQuery('input:radio[name="uniqueness_criteria"][value=<?= json_encode($data['drule']['uniqueness_criteria']) ?>]').attr('checked', 'checked');
		jQuery('input:radio[name="host_source"][value=<?= json_encode($data['drule']['host_source']) ?>]').attr('checked', 'checked');
		jQuery('input:radio[name="name_source"][value=<?= json_encode($data['drule']['name_source']) ?>]').attr('checked', 'checked');

		jQuery('#clone').click(function() {
			jQuery('#update')
				.text(t('Add'))
				.val('discovery.create')
				.attr({id: 'add'});
			jQuery('#druleid, #delete, #clone').remove();
			jQuery('#form').val('clone');
			jQuery('#name').focus();
		});

		jQuery('#host_source, #name_source').on('change', 'input', function() {
			var $elem = jQuery(this),
				name = $elem.attr('name');

			if ($elem.data('id')) {
				jQuery('[name^=dchecks][name$="[' + name + ']"]')
					.val((name === 'name_source') ? <?= ZBX_DISCOVERY_UNSPEC ?> : <?= ZBX_DISCOVERY_DNS ?>);
				jQuery('[name="dchecks[' + $elem.data('id') + '][' + name + ']"]').val(<?= ZBX_DISCOVERY_VALUE ?>);
			}
			else {
				jQuery('[name^=dchecks][name$="[' + name + ']"]').val($elem.val());
			}
		});

		jQuery('#dcheckList').on('click', '[data-action]', function() {
			var $btn = jQuery(this),
				$rows = jQuery('#dcheckList > table > tbody > tr'),
				params;

			switch ($btn.data('action')) {
				case 'add':
					PopUp('popup.discovery.check', {}, {
						dialogue_class: 'modal-popup-medium',
						trigger_element: this
					});
					break;

				case 'edit':
					var $row = $btn.closest('tr');

					params = {
						update: 1
					};

					$row.find('input[type="hidden"]').each(function() {
						var $input = jQuery(this),
							name = $input.attr('name').match(/\[([^\]]+)]$/);

						if (name) {
							params[name[1]] = $input.val();
						}
					});

					PopUp('popup.discovery.check', params, {
						dialogue_class: 'modal-popup-medium',
						trigger_element: this
					});
					break;
			}
		});
	});

	/**
	 * Returns a default port number for the specified discovery check type.
	 *
	 * @param {string} dcheck_type  Discovery check type.
	 *
	 * @returns {string}
	 */
	function getDCheckDefaultPort(dcheck_type) {
		var default_ports = {
			<?= SVC_SSH ?>: '22',
			<?= SVC_LDAP ?>: '389',
			<?= SVC_SMTP ?>: '25',
			<?= SVC_FTP ?>:  '21',
			<?= SVC_HTTP ?>: '80',
			<?= SVC_POP ?>: '110',
			<?= SVC_NNTP ?>: '119',
			<?= SVC_IMAP ?>: '143',
			<?= SVC_AGENT ?>: '10050',
			<?= SVC_SNMPv1 ?>: '161',
			<?= SVC_SNMPv2c ?>: '161',
			<?= SVC_SNMPv3 ?>: '161',
			<?= SVC_HTTPS ?>: '443',
			<?= SVC_TELNET ?>: '23'
		};

		return default_ports.hasOwnProperty(dcheck_type) ? default_ports[dcheck_type] : '0';
	}

	/**
	 * Set default discovery check port to input.
	 *
	 * @return {object}
	 */
	function setDCheckDefaultPort() {
		return jQuery('#ports').val(getDCheckDefaultPort(jQuery('#type-select').val()));
	}

	/**
	 * Sends discovery check form data to the server for validation before adding it to the main form.
	 *
	 * @param {Overlay} overlay
	 */
	function submitDCheck(overlay) {
		var $form = overlay.$dialogue.find('form');

		$form.trimValues([
			'#ports', '#key_', '#snmp_community', '#snmp_oid', '#snmpv3_contextname', '#snmpv3_securityname',
			'#snmpv3_authpassphrase', '#snmpv3_privpassphrase'
		]);

		var data = $form
				.find('#type, #ports, input[type=hidden], input[type=text]:visible, input[type=radio]:checked:visible')
				.serialize(),
			dialogueid = $form
				.closest("[data-dialogueid]")
				.data('dialogueid');

		if (!dialogueid) {
			return false;
		}

		overlay.setLoading();
		overlay.xhr = sendAjaxData('zabbix.php', {
			data: data,
			dataType: 'json',
			method: 'POST',
			complete: function() {
				overlay.unsetLoading();
			}
		}).done(function(response) {
			$form
				.parent()
				.find('.<?= ZBX_STYLE_MSG_BAD ?>')
				.remove();

			if (typeof response.errors !== 'undefined') {
				return jQuery(response.errors).insertBefore($form);
			}
			else {
				var dcheck = response.params;

				if (typeof dcheck.ports !== 'undefined' && dcheck.ports != getDCheckDefaultPort(dcheck.type)) {
					dcheck.name += ' (' + dcheck.ports + ')';
				}
				if (dcheck.key_) {
					dcheck.name += ' "' + dcheck.key_ + '"';
				}
				dcheck.host_source = jQuery('[name="host_source"]:checked:not([data-id])').val()
					|| '<?= ZBX_DISCOVERY_DNS ?>';
				dcheck.name_source = jQuery('[name="name_source"]:checked:not([data-id])').val()
					|| '<?= ZBX_DISCOVERY_UNSPEC ?>';

				if (hasDCheckDuplicates()) {
					jQuery(makeMessageBox('bad', <?= json_encode(_('Check already exists.')) ?>, null, true, false))
						.insertBefore($form);

					return false;
				}

				addDCheck([dcheck]);
				overlayDialogueDestroy(overlay.dialogueid);
			}
		});
	}

	/**
	 * Check for duplicates.
	 *
	 * @return {boolean}
	 */
	function hasDCheckDuplicates() {
		var $form = jQuery(document.forms['dcheck_form']),
			dcheckid = jQuery('#dcheckid').val(),
			dcheck = $form
				.find('#ports, >input[type=hidden], input[type=text]:visible, input[type=radio]:checked:visible')
				.serializeJSON(),
			fields = ['type', 'ports', 'snmp_community', 'key_', 'snmpv3_contextname', 'snmpv3_securityname',
				'snmpv3_securitylevel', 'snmpv3_authprotocol', 'snmpv3_authpassphrase', 'snmpv3_privprotocol',
				'snmpv3_privpassphrase'
			];

		dcheck['type'] = $form.find('z-select').val();
		dcheck.dcheckid = dcheckid ? dcheckid : getUniqueId();

		if (dcheck['type'] == <?= SVC_SNMPv1 ?> || dcheck['type'] == <?= SVC_SNMPv2c ?>
				|| dcheck['type'] == <?= SVC_SNMPv3 ?>) {
			dcheck['key_'] = dcheck['snmp_oid'];
		}

		for (var zbx_dcheckid in ZBX_CHECKLIST) {
			if (ZBX_CHECKLIST[zbx_dcheckid]['type'] !== dcheck['type']) {
				continue;
			}

			if (typeof dcheckid === 'undefined' || dcheckid != zbx_dcheckid) {
				var duplicate_fields = fields
					.map(function(value) {
						return typeof dcheck[value] === 'undefined'
							|| dcheck[value] === ''
							|| ZBX_CHECKLIST[zbx_dcheckid][value] === dcheck[value];
					})
					.filter(function(value) {
						return !!value;
					});

				if (duplicate_fields.length === fields.length) {
					return true;
				}
			}
		}

		return false;
	}

	$(() => {
		const $form = $(document.forms['discoveryForm']);
		$form.on('submit', () => $form.trimValues(['#name', '#iprange', '#delay']));
	});
</script>
