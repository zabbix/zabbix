<script type="text/x-jquery-tmpl" id="dcheckRowTPL">
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
<script type="text/x-jquery-tmpl" id="uniqRowTPL">
	<?=	(new CListItem([
			(new CInput('radio', 'uniqueness_criteria', '#{dcheckid}'))
				->addClass(ZBX_STYLE_CHECKBOX_RADIO)
				->setId('uniqueness_criteria_#{dcheckid}'),
			(new CLabel([new CSpan(), '#{name}'], 'uniqueness_criteria_#{dcheckid}'))
				->addClass(ZBX_STYLE_WORDWRAP)
		]))
			->setId('uniqueness_criteria_row_#{dcheckid}')
			->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="hostSourceRowTPL">
	<?=	(new CListItem([
			(new CInput('radio', 'host_source', '_#{dcheckid}'))
				->addClass(ZBX_STYLE_CHECKBOX_RADIO)
				->setAttribute('data-id', '#{dcheckid}')
				->setId('host_source_#{dcheckid}'),
			new CLabel([new CSpan(), '#{name}'], 'host_source_#{dcheckid}')
		]))
			->setId('host_source_row_#{dcheckid}')
			->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="nameSourceRowTPL">
	<?=	(new CListItem([
			(new CInput('radio', 'name_source', '_#{dcheckid}'))
				->addClass(ZBX_STYLE_CHECKBOX_RADIO)
				->setAttribute('data-id', '#{dcheckid}')
				->setId('name_source_#{dcheckid}'),
			new CLabel([new CSpan(), '#{name}'], 'name_source_#{dcheckid}')
		]))
			->setId('name_source_row_#{dcheckid}')
			->toString()
	?>
</script>

<script type="text/javascript">
	var ZBX_CHECKLIST = {};

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		var ZBX_SVC = {
			ssh: <?= SVC_SSH ?>,
			ldap: <?= SVC_LDAP ?>,
			smtp: <?= SVC_SMTP ?>,
			ftp: <?= SVC_FTP ?>,
			http: <?= SVC_HTTP ?>,
			pop: <?= SVC_POP ?>,
			nntp: <?= SVC_NNTP ?>,
			imap: <?= SVC_IMAP ?>,
			tcp: <?= SVC_TCP ?>,
			agent: <?= SVC_AGENT ?>,
			snmpv1: <?= SVC_SNMPv1 ?>,
			snmpv2: <?= SVC_SNMPv2c ?>,
			snmpv3: <?= SVC_SNMPv3 ?>,
			icmp: <?= SVC_ICMPPING ?>,
			https: <?= SVC_HTTPS ?>,
			telnet: <?= SVC_TELNET ?>
		},
			availableDeviceTypes = [ZBX_SVC.agent, ZBX_SVC.snmpv1, ZBX_SVC.snmpv2, ZBX_SVC.snmpv3];

		var addNewValue = function(value) {
			ZBX_CHECKLIST[value.dcheckid] = value;

			jQuery('#dcheckListFooter').before(new Template(jQuery('#dcheckRowTPL').html()).evaluate(value));

			value.host_source = jQuery('[name=host_source]:checked:not([data-id])').val()
					|| '<?= ZBX_DISCOVERY_DNS ?>';
			value.name_source = jQuery('[name=name_source]:checked:not([data-id])').val()
					|| '<?= ZBX_DISCOVERY_UNSPEC ?>';

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

					// If input exist update value.
					if ($obj.length) {
						$obj.val(value[field_name]);
					}
					else { // If not exists create input.
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
				value.dcheckid = getUniqueId();
			}

			// Add.
			if (typeof ZBX_CHECKLIST[value.dcheckid] === 'undefined') {
				addNewValue(value);
			}
			else { // Update.
				updateNewValue(value);
			}

			var elements = {
				uniqueness_criteria: ['ip',  new Template(jQuery('#uniqRowTPL').html()).evaluate(value)],
				host_source: ['chk_dns', new Template(jQuery('#hostSourceRowTPL').html()).evaluate(value)],
				name_source: ['chk_host', new Template(jQuery('#nameSourceRowTPL').html()).evaluate(value)]
			};

			jQuery.each(elements, function(key, param) {
				var	$obj = jQuery('#' + key + '_row_' + value.dcheckid);

				if (jQuery.inArray(parseInt(value.type, 10), availableDeviceTypes) > -1) {
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
			var obj = jQuery('#' + key + dcheckid);

			if (obj.length) {
				if (obj.is(':checked')) {
					jQuery('#' + key + def).prop('checked', true);
				}
				jQuery('#' + key + 'row_' + dcheckid).remove();
			}
		});
	}

	jQuery(function() {
		addPopupValues(<?= zbx_jsvalue(array_values($this->data['drule']['dchecks'])) ?>);

		jQuery("input:radio[name='uniqueness_criteria'][value=<?= zbx_jsvalue($this->data['drule']['uniqueness_criteria']) ?>]").attr('checked', 'checked');
		jQuery("input:radio[name='host_source'][value=<?= zbx_jsvalue($this->data['drule']['host_source']) ?>]").attr('checked', 'checked');
		jQuery("input:radio[name='name_source'][value=<?= zbx_jsvalue($this->data['drule']['name_source']) ?>]").attr('checked', 'checked');

		jQuery('#clone').click(function() {
			jQuery('#update')
				.text(<?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});
			jQuery('#druleid, #delete, #clone').remove();
			jQuery('#form').val('clone');
			jQuery('#name').focus();
		});

		jQuery('#host_source,#name_source').on('change', 'input', function() {
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
				params = {};

			switch ($btn.data('action')) {
				case 'add':
					params['index'] = $rows.length;

					PopUp('popup.discovery.check.edit', params, null, $btn);
					break;

				case 'edit':
					var $row = $btn.closest('tr');

					params['index'] = $rows.index($row);

					$row.find('input[type="hidden"]').each(function() {
						var $input = jQuery(this),
							name = $input.attr('name').match(/\[([^\]]+)]$/);

						if (name) {
							params[name[1]] = $input.val();
						}
					});

					PopUp('popup.discovery.check.edit', params, null, $btn);
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
		return jQuery('#ports').val(getDCheckDefaultPort(jQuery('#type').val()));
	}

	/**
	 * Sends discovery check form data to the server for validation before adding it to the main form.
	 *
	 * @param {string} form_name  Form name that is sent to the server for validation.
	 */
	function submitDCheck(form_name) {
		var $form = jQuery(document.forms['dcheck_form']),
			dialogueid = $form
				.closest("[data-dialogueid]")
				.data('dialogueid');

		$form
			.parent()
			.find(".<?= ZBX_STYLE_MSG_BAD ?>, .<?= ZBX_STYLE_MSG_GOOD ?>")
			.remove();

		var formData = jQuery(document.forms['dcheck_form'])
			.find('#type, #ports, input[type=hidden], input[type=text]:visible, select:visible, input[type=radio]:checked:visible')
			.serialize();

		sendAjaxData('zabbix.php', {
			data: formData,
			dataType: 'json',
			method: 'POST',
		}).done(function(response) {
			if (typeof response.errors !== 'undefined') {
				return jQuery(response.errors).insertBefore($form);
			}
			else {
				if (validateDCheckDuplicate()) {
					return false;
				}

				var dcheck = response.params,
					$host_source = jQuery('[name="host_source"]:checked:not([data-id])'),
					$name_source = jQuery('[name="name_source"]:checked:not([data-id])');

				if (typeof dcheck.ports !== 'undefined' && dcheck.ports != getDCheckDefaultPort(dcheck.type)) {
					dcheck.name += ' (' + dcheck.ports + ')';
				}
				if (dcheck.key_) {
					dcheck.name += ' "' + dcheck.key_ + '"';
				}
				dcheck.host_source = $host_source ? $host_source.val() : '<?= ZBX_DISCOVERY_DNS ?>';
				dcheck.name_source = $name_source ? $name_source.val() : '<?= ZBX_DISCOVERY_UNSPEC ?>';

				addPopupValues([dcheck]);
				overlayDialogueDestroy(dialogueid);
			}
		});
	}

	/**
	 * Check for duplicates.
	 *
	 * @return {boolean}
	 */
	function validateDCheckDuplicate() {
		var $form = jQuery(document.forms['dcheck_form']),
			dcheckId = jQuery('#dcheckid').val(),
			dCheck = $form
				.find('#type, #ports, input[type=hidden], input[type=text]:visible, select:visible, input[type=radio]:checked:visible')
				.serializeJSON(),
			fields_name = ['key_', 'type', 'ports', 'snmp_community', 'snmpv3_authprotocol',
				'snmpv3_authpassphrase', 'snmpv3_privprotocol', 'snmpv3_privpassphrase', 'snmpv3_securitylevel',
				'snmpv3_securityname', 'snmpv3_contextname'
			];

		dCheck.dcheckid = dcheckId ? dcheckId : getUniqueId();

		for (var zbxDcheckId in ZBX_CHECKLIST) {
			if (ZBX_CHECKLIST[zbxDcheckId]['type'] !== dCheck['type']) {
				continue;
			}

			if (typeof dcheckId === 'undefined' || (typeof dcheckId !== 'undefined') && dcheckId != zbxDcheckId) {
				var duplicate_fields = fields_name
					.map(function(value) { // Check if field is undefined or empty or values equels with exist checks.
						return typeof dCheck[value] === 'undefined'
							|| dCheck[value] === ''
							|| ZBX_CHECKLIST[zbxDcheckId][value] === dCheck[value];
					})
					.filter(function(value) { // Remove false value from array.
						return !!value;
					});

				if (duplicate_fields.length === fields_name.length) { // If all fields return true for checks.
					jQuery(createErrorMsg("<?= _('Check already exists.') ?>")).insertBefore($form);
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Create error message jQuery element.
	 *
	 * @param {string} msg  Error message.
	 *
	 * @return {object}
	 */
	function createErrorMsg(msg) {
		return jQuery('<output/>')
			.addClass('<?= ZBX_STYLE_MSG_BAD ?>')
			.attr({role: 'contentinfo', 'aria-label': t('Error message')})
			.append(
				jQuery('<div/>')
					.addClass('<?= ZBX_STYLE_MSG_DETAILS ?>')
					.append(
						jQuery('<ul/>')
							.append(
								jQuery('<li/>').append(msg)
							)
					),
				jQuery('<button/>')
					.addClass('<?= ZBX_STYLE_OVERLAY_CLOSE_BTN ?>')
					.attr({title: t('Close'), onclick: "jQuery(this).closest('.<?= ZBX_STYLE_MSG_BAD ?>').remove();"})
			);
	}
</script>
