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
					->setAttribute('data-action', 'remove')
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
	};

	var ZBX_CHECKLIST = {};

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		// templates
		var dcheckRowTpl = new Template(jQuery('#dcheckRowTPL').html()),
			uniqRowTpl = new Template(jQuery('#uniqRowTPL').html()),
			hostSourceRowTPL = new Template(jQuery('#hostSourceRowTPL').html()),
			nameSourceRowTPL = new Template(jQuery('#nameSourceRowTPL').html());

		for (var i = 0; i < list.length; i++) {
			if (empty(list[i])) {
				continue;
			}

			var value = list[i];

			if (typeof value.dcheckid === 'undefined') {
				value.dcheckid = getUniqueId();
			}

			// add
			if (typeof ZBX_CHECKLIST[value.dcheckid] === 'undefined') {
				ZBX_CHECKLIST[value.dcheckid] = value;

				jQuery('#dcheckListFooter').before(dcheckRowTpl.evaluate(value));

				for (var fieldName in value) {
					if (typeof value[fieldName] === 'string') {
						var input = jQuery('<input>', {
							name: 'dchecks[' + value.dcheckid + '][' + fieldName + ']',
							type: 'hidden',
							value: value[fieldName]
						});

						jQuery('#dcheckCell_' + value.dcheckid).append(input);
					}
				}
			}

			// update
			else {
				ZBX_CHECKLIST[value.dcheckid] = value;

				var ignoreNames = ['druleid', 'dcheckid', 'name', 'ports', 'type', 'uniq'];

				// clean values
				jQuery('#dcheckCell_' + value.dcheckid + ' input').each(function(i, item) {
					var itemObj = jQuery(item);

					var name = itemObj.attr('name').replace('dchecks[' + value.dcheckid + '][', '');
					name = name.substring(0, name.length - 1);

					if (jQuery.inArray(name, ignoreNames) == -1) {
						itemObj.remove();
					}
				});

				// set values
				for (var fieldName in value) {
					if (typeof value[fieldName] === 'string') {
						var obj = jQuery('input[name="dchecks[' + value.dcheckid + '][' + fieldName + ']"]');

						if (obj.length) {
							obj.val(value[fieldName]);
						}
						else {
							var input = jQuery('<input>', {
								name: 'dchecks[' + value.dcheckid + '][' + fieldName + ']',
								type: 'hidden',
								value: value[fieldName]
							});

							jQuery('#dcheckCell_' + value.dcheckid).append(input);
						}
					}
				}

				// update check name
				jQuery('#dcheckCell_' + value.dcheckid + ' .wordwrap').text(value['name']);
			}

			var availableDeviceTypes = [ZBX_SVC.agent, ZBX_SVC.snmpv1, ZBX_SVC.snmpv2, ZBX_SVC.snmpv3],
				elements = {
					uniqueness_criteria: ['ip', uniqRowTpl.evaluate(value)],
					host_source: ['chk_dns', hostSourceRowTPL.evaluate(value)],
					name_source: ['chk_host', nameSourceRowTPL.evaluate(value)]
				};

			jQuery.each(elements, function(key, param) {
				var	obj = jQuery('#' + key + '_row_' + value.dcheckid);

				if (jQuery.inArray(parseInt(value.type, 10), availableDeviceTypes) > -1) {
					var new_obj = param[1];
					if (obj.length) {
						var checked_id = jQuery('input:radio[name=' + key + ']:checked').attr('id');
						obj.replaceWith(new_obj);
						jQuery('#' + checked_id).prop('checked', true);
					}
					else {
						jQuery('#' + key).append(new_obj);
					}
				}
				else {
					if (obj.length) {
						obj.remove();
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

	jQuery(function($) {
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
			var elm = jQuery(this),
				name = elm.attr('name');

			if (elm.data('id')) {
				jQuery('[name^=dchecks][name$="[' + name + ']"]')
					.val((name === 'name_source') ? <?= ZBX_DISCOVERY_UNSPEC ?> : <?= ZBX_DISCOVERY_DNS ?>);
				jQuery('[name="dchecks[' + elm.data('id') + '][' + name + ']"]').val(<?= ZBX_DISCOVERY_VALUE ?>);
			}
			else {
				jQuery('[name^=dchecks][name$="[' + name + ']"]').val(elm.val());
			}
		});

		$('#dcheckList').on('click', '[data-action]', function() {
			var $btn = $(this),
				$rows = $('#dcheckList > table > tbody > tr'),
				params = {};

			params['types'] = $rows.find('input[name$="[type]"]').map(function() {
				return this.value;
			}).get();

			switch ($btn.data('action')) {
				case 'add':
					params['index'] = $rows.length;

					PopUp('popup.discovery.check.edit', params, null, $btn);
					break;

				case 'edit':
					var $row = $btn.closest('tr');

					params['update'] = 1;
					params['index'] = $rows.index($row);

					$row.find('input[type="hidden"]').each(function() {
						var $input = $(this),
							name = $input.attr('name').match(/\[([^\]]+)]$/);

						if (name) {
							params[name[1]] = $input.val();
						}
					});

					PopUp('popup.discovery.check.edit', params, null, $btn);
					break;

				case 'remove':
					removeDCheckRow($btn.closest('tr').find('input[name$="[dcheckid]"]').val());
					break;
			}
		});
	});
</script>
