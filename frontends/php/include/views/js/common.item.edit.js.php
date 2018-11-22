<script type="text/x-jquery-tmpl" id="delayFlexRow">
	<tr class="form_row">
		<td>
			<ul class="<?= ZBX_STYLE_RADIO_SEGMENTED ?>" id="delay_flex_#{rowNum}_type">
				<li>
					<input type="radio" id="delay_flex_#{rowNum}_type_0" name="delay_flex[#{rowNum}][type]" value="0" checked="checked">
					<label for="delay_flex_#{rowNum}_type_0"><?= _('Flexible') ?></label>
				</li><li>
					<input type="radio" id="delay_flex_#{rowNum}_type_1" name="delay_flex[#{rowNum}][type]" value="1">
					<label for="delay_flex_#{rowNum}_type_1"><?= _('Scheduling') ?></label>
				</li>
			</ul>
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_delay" name="delay_flex[#{rowNum}][delay]" maxlength="255" placeholder="<?= ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT ?>">
			<input type="text" id="delay_flex_#{rowNum}_schedule" name="delay_flex[#{rowNum}][schedule]" maxlength="255" placeholder="<?= ZBX_ITEM_SCHEDULING_DEFAULT ?>" style="display: none;">
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_period" name="delay_flex[#{rowNum}][period]" maxlength="255" placeholder="<?= ZBX_DEFAULT_INTERVAL ?>">
		</td>
		<td>
			<button type="button" id="delay_flex_#{rowNum}_remove" name="delay_flex[#{rowNum}][remove]" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<?php
	$readonly = ($data['limited']
		|| (array_key_exists('item', $data) && array_key_exists('flags', $data['item'])
			&& $data['item']['flags'] == ZBX_FLAG_DISCOVERY_CREATED)
	);
?>
<?php if (!$data['is_discovery_rule'] && !$readonly) : ?>
	<script type="text/x-jquery-tmpl" id="preprocessing_steps_row">
	<?php
		$preproc_types_cbbox = new CComboBox('preprocessing[#{rowNum}][type]', '');

		foreach (get_preprocessing_types() as $group) {
			$cb_group = new COptGroup($group['label']);

			foreach ($group['types'] as $type => $label) {
				$cb_group->addItem(new CComboItem($type, $label));
			}

			$preproc_types_cbbox->addItem($cb_group);
		}

		echo (new CListItem([
			(new CDiv([
				(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON),
				(new CDiv($preproc_types_cbbox))->addClass(ZBX_STYLE_COLUMN_40),
				(new CDiv((new CTextBox('preprocessing[#{rowNum}][params][0]', ''))
					->setAttribute('placeholder', _('pattern'))
				))->addClass(ZBX_STYLE_COLUMN_20),
				(new CDiv((new CTextBox('preprocessing[#{rowNum}][params][1]', ''))
					->setAttribute('placeholder', _('output'))
				))->addClass(ZBX_STYLE_COLUMN_20),
				(new CDiv(new CCheckBox('preprocessing[#{rowNum}][on_fail]')))
					->addClass(ZBX_STYLE_COLUMN_10)
					->addStyle('justify-content: center;'),
				(new CDiv((new CButton('preprocessing[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
				))->addClass(ZBX_STYLE_COLUMN_10)
			]))
				->addClass(ZBX_STYLE_COLUMNS)
				->addClass('preprocessing-step'),
			(new CDiv([
				(new CDiv([
					new CDiv(new CLabel(_('Custom on fail'))),
					new CDiv(
						(new CRadioButtonList('preprocessing[#{rowNum}][error_handler]',
							ZBX_PREPROC_FAIL_DISCARD_VALUE
						))
							->addValue(_('Discard value'), ZBX_PREPROC_FAIL_DISCARD_VALUE)
							->addValue(_('Set value to'), ZBX_PREPROC_FAIL_SET_VALUE)
							->addValue(_('Set error to'), ZBX_PREPROC_FAIL_SET_ERROR)
							->setModern(true)
							->setEnabled(false)
					),
					new CDiv(
						(new CTextBox('preprocessing[#{rowNum}][error_handler_params]'))
							->setEnabled(false)
							->addStyle('display: none;')
					)
				]))->addClass(ZBX_STYLE_COLUMN_80)
			]))
				->addClass(ZBX_STYLE_COLUMNS)
				->addClass('on-fail-options')
				->addStyle('display: none;')
		]))
			->addClass('preprocessing-list-item')
			->addClass('sortable')
	?>
	</script>
<?php endif ?>
<script type="text/javascript">
	jQuery(function($) {
		$('#delayFlexTable').on('click', 'input[type="radio"]', function() {
			var rowNum = $(this).attr('id').split('_')[2];

			if ($(this).val() == <?= ITEM_DELAY_FLEXIBLE; ?>) {
				$('#delay_flex_' + rowNum + '_schedule').hide();
				$('#delay_flex_' + rowNum + '_delay').show();
				$('#delay_flex_' + rowNum + '_period').show();
			}
			else {
				$('#delay_flex_' + rowNum + '_delay').hide();
				$('#delay_flex_' + rowNum + '_period').hide();
				$('#delay_flex_' + rowNum + '_schedule').show();
			}
		});

		$('#delayFlexTable').dynamicRows({
			template: '#delayFlexRow'
		});

		<?php if (!$data['is_discovery_rule'] && !$readonly) : ?>
			var preproc_row_tpl = new Template($('#preprocessing_steps_row').html()),
				preprocessing = $('#preprocessing'),
				drag_icons = preprocessing.find('div.<?= ZBX_STYLE_DRAG_ICON ?>');

			preprocessing.sortable({
				disabled: (preprocessing.find('li.sortable').length < 2),
				items: 'li.sortable',
				axis: 'y',
				cursor: 'move',
				containment: 'parent',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				tolerance: 'pointer',
				opacity: 0.6
			});

			preprocessing
				.on('click', '.element-table-add', function() {
					var row = $(this).closest('.preprocessing-list-foot');
					row.before(preproc_row_tpl.evaluate({rowNum: preprocessing.find('li.sortable').length}));

					$('.preprocessing-list-head').show();

					if (preprocessing.find('li.sortable').length > 1) {
						preprocessing.sortable('enable');
						drag_icons.removeClass('<?= ZBX_STYLE_DISABLED ?>');
					}
				})
				.on('click', '.element-table-remove', function() {
					var sortable_count;

					$(this).closest('li.sortable').remove();
					sortable_count = preprocessing.find('li.sortable').length;

					if (sortable_count == 1) {
						preprocessing.sortable('disable');
						drag_icons.addClass('<?= ZBX_STYLE_DISABLED ?>');
					}
					else if (sortable_count == 0) {
						$('.preprocessing-list-head').hide();
					}
				})
				.on('change', 'select[name*="type"]', function() {
					var type = $(this).val(),
						params = $(this).closest('.preprocessing-step').find('[name*="params"]'),
						on_fail = $(this).closest('.preprocessing-step').find('[name*="on_fail"]');

					switch (type) {
						case '<?= ZBX_PREPROC_MULTIPLIER ?>':
							$(params[0])
								.attr('placeholder', <?= CJs::encodeJson(_('number')) ?>)
								.show();
							$(params[1]).hide();
							break;

						case '<?= ZBX_PREPROC_RTRIM ?>':
						case '<?= ZBX_PREPROC_LTRIM ?>':
						case '<?= ZBX_PREPROC_TRIM ?>':
							$(params[0])
								.attr('placeholder', <?= CJs::encodeJson(_('list of characters')) ?>)
								.show();
							$(params[1]).hide();
							break;

						case '<?= ZBX_PREPROC_XPATH ?>':
						case '<?= ZBX_PREPROC_JSONPATH ?>':
						case '<?= ZBX_PREPROC_ERROR_FIELD_JSON ?>':
						case '<?= ZBX_PREPROC_ERROR_FIELD_XML ?>':
							$(params[0])
								.attr('placeholder', <?= CJs::encodeJson(_('path')) ?>)
								.show();
							$(params[1]).hide();
							break;

						case '<?= ZBX_PREPROC_REGSUB ?>':
						case '<?= ZBX_PREPROC_ERROR_FIELD_REGEX ?>':
							$(params[0])
								.attr('placeholder', <?= CJs::encodeJson(_('pattern')) ?>)
								.show();
							$(params[1])
								.attr('placeholder', <?= CJs::encodeJson(_('output')) ?>)
								.show();
							break;

						case '<?= ZBX_PREPROC_BOOL2DEC ?>':
						case '<?= ZBX_PREPROC_OCT2DEC ?>':
						case '<?= ZBX_PREPROC_HEX2DEC ?>':
						case '<?= ZBX_PREPROC_DELTA_VALUE ?>':
						case '<?= ZBX_PREPROC_DELTA_SPEED ?>':
						case '<?= ZBX_PREPROC_THROTTLE_VALUE ?>':
							$(params[0]).hide();
							$(params[1]).hide();
							break;

						case '<?= ZBX_PREPROC_VALIDATE_RANGE ?>':
							$(params[0])
								.attr('placeholder', <?= CJs::encodeJson(_('min')) ?>)
								.show();
							$(params[1])
								.attr('placeholder', <?= CJs::encodeJson(_('max')) ?>)
								.show();
							break;

						case '<?= ZBX_PREPROC_VALIDATE_REGEX ?>':
						case '<?= ZBX_PREPROC_VALIDATE_NOT_REGEX ?>':
							$(params[0])
								.attr('placeholder', <?= CJs::encodeJson(_('pattern')) ?>)
								.show();
							$(params[1]).hide();
							break;

						case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
							$(params[0])
								.attr('placeholder', <?= CJs::encodeJson(_('seconds')) ?>)
								.show();
							$(params[1]).hide();
							break;
					}

					// Disable "Custom on fail" for some of the preprocessing types.
					switch (type) {
						case '<?= ZBX_PREPROC_RTRIM ?>':
						case '<?= ZBX_PREPROC_LTRIM ?>':
						case '<?= ZBX_PREPROC_TRIM ?>':
						case '<?= ZBX_PREPROC_ERROR_FIELD_JSON ?>':
						case '<?= ZBX_PREPROC_ERROR_FIELD_XML ?>':
						case '<?= ZBX_PREPROC_ERROR_FIELD_REGEX ?>':
						case '<?= ZBX_PREPROC_THROTTLE_VALUE ?>':
						case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
							on_fail
								.prop('checked', false)
								.prop('disabled', true)
								.trigger('change');
							break;

						default:
							on_fail.prop('disabled', false);
							break;
					}
				})
				.on('change', 'input[name*="params"]', function() {
					$(this).attr('title', $(this).val());
				})
				.on('change', 'input[name*="on_fail"]', function() {
					var on_fail_options = $(this).closest('.preprocessing-list-item').find('.on-fail-options');

					if ($(this).is(':checked')) {
						on_fail_options.find('input').prop('disabled', false);
						on_fail_options.show();
					}
					else {
						on_fail_options.find('input').prop('disabled', true);
						on_fail_options.hide();
					}
				})
				.on('change', 'input[name*="error_handler]"]', function() {
					var error_handler = $(this).val(),
						error_handler_params = $(this).closest('.on-fail-options').find('[name*="error_handler_params"]');

					if (error_handler == '<?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>') {
						error_handler_params
							.prop('disabled', true)
							.hide();
					}
					else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_VALUE ?>') {
						error_handler_params
							.prop('disabled', false)
							.attr('placeholder', <?= CJs::encodeJson(_('value')) ?>)
							.show();
					}
					else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_ERROR ?>') {
						error_handler_params
							.prop('disabled', false)
							.attr('placeholder', <?= CJs::encodeJson(_('error message')) ?>)
							.show();
					}
				});
		<?php endif ?>
	});
</script>
<?php

/*
 * Visibility
 */
$this->data['typeVisibility'] = [];
$i = 0;

foreach ($this->data['delay_flex'] as $delayFlex) {
	if (!isset($delayFlex['delay']) && !isset($delayFlex['period'])) {
		continue;
	}

	foreach ($this->data['types'] as $type => $label) {
		if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_ZABBIX_ACTIVE || $type == ITEM_TYPE_SNMPTRAP
				|| $type == ITEM_TYPE_DEPENDENT) {
			continue;
		}
		zbx_subarray_push($this->data['typeVisibility'], $type, 'delay_flex['.$i.'][delay]');
		zbx_subarray_push($this->data['typeVisibility'], $type, 'delay_flex['.$i.'][period]');
	}

	$i++;
}

if (!empty($this->data['interfaces'])) {
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_ZABBIX, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_ZABBIX, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_EXTERNAL, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_EXTERNAL, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPTRAP, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPTRAP, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_HTTPAGENT, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_HTTPAGENT, 'interfaceid');
}
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'row_snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'row_snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'row_snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'row_snmp_community');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmpv3_contextname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmpv3_contextname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmpv3_securityname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmpv3_securityname');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'snmpv3_securitylevel');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_snmpv3_securitylevel');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV1, 'row_port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV2C, 'row_port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMPV3, 'row_port');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'ipmi_sensor');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_IPMI, 'row_ipmi_sensor');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'authtype');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_authtype');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'row_username');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'jmx_endpoint');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'row_jmx_endpoint');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_JMX, 'row_password');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'label_executed_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'label_executed_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'label_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_CALCULATED, 'label_formula');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'params_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SSH, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'params_script');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TELNET, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'params_dbmonitor');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DB_MONITOR, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_CALCULATED, 'params_calculted');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_CALCULATED, 'row_params');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TRAPPER, 'trapper_hosts');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_TRAPPER, 'row_trapper_hosts');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_DEPENDENT, 'row_master_item');
$ui_rows = [
	ITEM_TYPE_HTTPAGENT => [
		'url_row', 'query_fields_row', 'request_method_row', 'timeout_row', 'post_type_row', 'posts_row', 'headers_row',
		'status_codes_row', 'follow_redirects_row', 'retrieve_mode_row', 'output_format_row', 'allow_traps_row',
		'request_method', 'http_proxy_row', 'http_authtype_row', 'http_authtype', 'verify_peer_row', 'verify_host_row',
		'ssl_key_file_row', 'ssl_cert_file_row', 'ssl_key_password_row', 'trapper_hosts', 'allow_traps'
	]
];
foreach ($ui_rows[ITEM_TYPE_HTTPAGENT] as $row) {
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_HTTPAGENT, $row);
}

foreach ($this->data['types'] as $type => $label) {
	switch ($type) {
		case ITEM_TYPE_DB_MONITOR:
			$defaultKey = $this->data['is_discovery_rule']
				? ZBX_DEFAULT_KEY_DB_MONITOR_DISCOVERY
				: ZBX_DEFAULT_KEY_DB_MONITOR;
			zbx_subarray_push($this->data['typeVisibility'], $type,
				['id' => 'key', 'defaultValue' => $defaultKey]
			);
			break;
		case ITEM_TYPE_SSH:
			zbx_subarray_push($this->data['typeVisibility'], $type,
				['id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_SSH]
			);
			break;
		case ITEM_TYPE_TELNET:
			zbx_subarray_push($this->data['typeVisibility'], $type,
				['id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_TELNET]
			);
			break;
		default:
			zbx_subarray_push($this->data['typeVisibility'], $type, ['id' => 'key', 'defaultValue' => '']);
	}
}
foreach ($this->data['types'] as $type => $label) {
	if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_ZABBIX_ACTIVE || $type == ITEM_TYPE_SNMPTRAP
			|| $type == ITEM_TYPE_DEPENDENT) {
		continue;
	}
	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_flex_intervals');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_new_delay_flex');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'new_delay_flex[delay]');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'new_delay_flex[period]');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'add_delay_flex');
}
foreach ($this->data['types'] as $type => $label) {
	if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_SNMPTRAP || $type == ITEM_TYPE_DEPENDENT) {
		continue;
	}
	zbx_subarray_push($this->data['typeVisibility'], $type, 'delay');
	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_delay');
}

// disable dropdown items for calculated and aggregate items
foreach ([ITEM_TYPE_CALCULATED, ITEM_TYPE_AGGREGATE] as $type) {
	// set to disable character, log and text items in value type
	zbx_subarray_push($this->data['typeDisable'], $type, [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT], 'value_type');
}

$this->data['securityLevelVisibility'] = [];
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_privprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privprotocol');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_privpassphrase');
zbx_subarray_push($this->data['securityLevelVisibility'], ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privpassphrase');

$this->data['authTypeVisibility'] = [];
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'publickey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'row_publickey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'privatekey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'row_privatekey');

?>
<script type="text/javascript">
	function setAuthTypeLabel() {
		if (jQuery('#authtype').val() == <?php echo CJs::encodeJson(ITEM_AUTHTYPE_PUBLICKEY); ?>
				&& jQuery('#type').val() == <?php echo CJs::encodeJson(ITEM_TYPE_SSH); ?>) {
			jQuery('#row_password label').html(<?php echo CJs::encodeJson(_('Key passphrase')); ?>);
		}
		else {
			jQuery('#row_password label').html(<?php echo CJs::encodeJson(_('Password')); ?>);
		}
	}

	jQuery(document).ready(function() {
		<?php
		if (!empty($this->data['authTypeVisibility'])) { ?>
			var authTypeSwitcher = new CViewSwitcher('authtype', 'change',
				<?php echo zbx_jsvalue($this->data['authTypeVisibility'], true); ?>);
		<?php }
		if (!empty($this->data['typeVisibility'])) { ?>
			var typeSwitcher = new CViewSwitcher('type', 'change',
				<?php echo zbx_jsvalue($this->data['typeVisibility'], true); ?>,
				<?php echo zbx_jsvalue($this->data['typeDisable'], true); ?>);
		<?php } ?>
		if (jQuery('#http_authtype').length) {
			new CViewSwitcher('http_authtype', 'change', <?= zbx_jsvalue([
				HTTPTEST_AUTH_BASIC => ['http_username_row', 'http_password_row'],
				HTTPTEST_AUTH_NTLM => ['http_username_row', 'http_password_row']
			], true) ?>);
		}
		<?php
		if (!empty($this->data['securityLevelVisibility'])) { ?>
			var securityLevelSwitcher = new CViewSwitcher('snmpv3_securitylevel', 'change',
				<?php echo zbx_jsvalue($this->data['securityLevelVisibility'], true); ?>);
		<?php } ?>

		if (jQuery('#allow_traps').length) {
			new CViewSwitcher('allow_traps', 'change', <?= zbx_jsvalue([
				HTTPCHECK_ALLOW_TRAPS_ON => ['row_trapper_hosts']
			], true) ?>);
		}

		jQuery('#type')
			.change(function() {
				// update the interface select with each item type change
				var itemInterfaceTypes = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
				organizeInterfaces(itemInterfaceTypes[parseInt(jQuery(this).val())]);

				setAuthTypeLabel();
			})
			.trigger('change');

		jQuery('#authtype').bind('change', function() {
			setAuthTypeLabel();
		});

		var $ = jQuery,
			editableTable = function (elm, tmpl, tmpl_defaults) {
			var table,
				row_template,
				row_default_values,
				insert_point,
				row_index = 0,
				table_row_class = 'editable_table_row';

			table = $(elm);
			insert_point = table.find('tbody tr[data-insert-point]');
			row_template = new Template($(tmpl).html());
			row_default_values = tmpl_defaults;

			table.sortable({
				disabled: true,
				items: 'tbody tr.sortable',
				axis: 'y',
				containment: 'parent',
				cursor: 'move',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				tolerance: 'pointer',
				opacity: 0.6,
				helper: function(e, ui) {
					ui.children('td').each(function() {
						$(this).width($(this).width());
					});

					return ui;
				},
				start: function(e, ui) {
					// Fix placeholder not to change height while object is being dragged.
					$(ui.placeholder).height($(ui.helper).height());
				}
			});

			table.on('click', '[data-row-action]', function (e) {
				e.preventDefault();

				switch ($(e.currentTarget).data('row-action')) {
					case 'remove_row' :
						removeRow($(e.currentTarget).closest('.' + table_row_class));
						break;

					case 'add_row' :
						var row_data = $(e.currentTarget).data('values'),
							new_row = addRow(row_data || {});

						if (!row_data) {
							new_row.find('[type="text"]').val('');
						}
						break;
				}
			});

			/**
			 * Enable or disable table rows sorting according to rows count. At least 2 rows should exists to be able
			 * sort rows using drag and drop.
			 */
			function setSortableState() {
				var allow_sort = table.find('.' + table_row_class).length < 2;
				table.sortable('option', 'disabled', allow_sort);
			}

			/**
			 * Add table row. Returns new added row DOM node.
			 *
			 * @param {object}  Object with data for added row.
			 *
			 * @return {object}
			 */
			function addRow(values) {
				row_index += 1;
				values.index = row_index;

				var new_row = $(row_template.evaluate(values))
					.addClass(table_row_class)
					.addClass('sortable')
					.data('values', values)
					.insertBefore(insert_point);

				setSortableState();
				return new_row;
			}

			/**
			 * Add multiple rows to table.
			 *
			 * @param {array} rows_values  Array of objects for every added row.
			 */
			function addRows(rows_values) {
				$.each(rows_values, function(index, values) {
					addRow(values);
				});
			}

			/**
			 * Remove table row.
			 *
			 * @param {object} row_node Table row DOM node to be removed.
			 */
			function removeRow(row_node) {
				row_node.remove();
				setSortableState();
			}

			return {
				addRow: function(values) {
					return addRow(values);
				},
				addRows: function(rows_values) {
					addRows(rows_values);
					return table;
				},
				removeRow: function(row_node) {
					removeRow(row_node);
				},
				getTableRows: function() {
					return table.find('.' + table_row_class);
				}
			};
		};

		$('[data-sortable-pairs-table]').each(function() {
			var t = $(this),
				table = t.find('table'),
				data = JSON.parse(t.find('[type="text/json"]').text()),
				template = t.find('[type="text/x-jquery-tmpl"]'),
				container = new editableTable(table, template);

			container.addRows(data);

			if (t.data('sortable-pairs-table') != 1) {
				table.sortable('option', 'disabled', true);
			}

			t.data('editableTable', container);
		});

		$('[data-action="parse_url"]').click(function() {
			var url_node = $(this).siblings('[name="url"]'),
				table = $('#query_fields_pairs').data('editableTable'),
				url = parseUrlString(url_node.val())

			if (typeof url === 'object') {
				if (url.pairs.length > 0) {
					table.addRows(url.pairs);
					table.getTableRows().map(function() {
						var empty = $(this).find('input[type="text"]').map(function() {
							return $(this).val() == '' ? this : null;
						});

						return empty.length == 2 ? this : null;
					}).map(function() {
						table.removeRow(this);
					});
				}

				url_node.val(url.url);
			}
			else {
				overlayDialogue({
					'title': <?= CJs::encodeJson(_('Error')); ?>,
					'content': $('<span>').html(<?=
						CJs::encodeJson(_('Failed to parse URL.').'<br><br>'._('URL is not properly encoded.'));
					?>),
					'buttons': [
						{
							title: <?= CJs::encodeJson(_('Ok')); ?>,
							class: 'btn-alt',
							focused: true,
							action: function() {}
						}
					]
				});
			}
		});

		$('#request_method').change(function() {
			if ($(this).val() == <?= HTTPCHECK_REQUEST_HEAD ?>) {
				$(':radio', '#retrieve_mode')
					.filter('[value=<?= HTTPTEST_STEP_RETRIEVE_MODE_HEADERS ?>]').click()
					.end()
					.attr('disabled', 'disabled');
			}
			else {
				$(':radio', '#retrieve_mode').removeAttr('disabled');
			}
		});
	});
</script>
