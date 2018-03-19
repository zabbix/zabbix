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
<?php if (!$data['is_discovery_rule']) : ?>
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

		echo (new CRow([
			$readonly
				? null
				: (new CCol(
					(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
				))->addClass(ZBX_STYLE_TD_DRAG_ICON),
				$preproc_types_cbbox,
				(new CTextBox('preprocessing[#{rowNum}][params][0]', ''))->setAttribute('placeholder', _('pattern')),
				(new CTextBox('preprocessing[#{rowNum}][params][1]', ''))->setAttribute('placeholder', _('output')),
				(new CButton('preprocessing[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
		]))
			->addClass('sortable')
			->toString()
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

		<?php if (!$data['is_discovery_rule']) : ?>
			var preproc_row_tpl = new Template($('#preprocessing_steps_row').html()),
				preprocessing = $('#preprocessing');

			preprocessing.sortable({
				disabled: (preprocessing.find('tr.sortable').length < 2),
				items: 'tr.sortable',
				axis: 'y',
				cursor: 'move',
				containment: 'parent',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				tolerance: 'pointer',
				opacity: 0.6,
				helper: function(e, ui) {
					ui.children().each(function() {
						var td = $(this);

						td.width(td.width());
					});

					return ui;
				},
				start: function(e, ui) {
					$(ui.placeholder).height($(ui.helper).height());
				}
			});

			preprocessing
				.on('click', '.element-table-add', function() {
					var row = $(this).parent().parent();
					row.before(preproc_row_tpl.evaluate({rowNum: preprocessing.find('tr.sortable').length}));

					if (preprocessing.find('tr.sortable').length > 1) {
						preprocessing.sortable('enable');
					}
				})
				.on('click', '.element-table-remove', function() {
					var row = $(this).parent().parent();
					row.remove();

					if (preprocessing.find('tr.sortable').length < 2) {
						preprocessing.sortable('disable');
					}
				})
				.on('change', 'select[name*="type"]', function() {
					var inputs = $(this).parent().parent().find('[name*="params"]');

					switch ($(this).val()) {
						case '<?= ZBX_PREPROC_MULTIPLIER ?>':
							$(inputs[0])
								.show()
								.attr('placeholder', <?= CJs::encodeJson(_('number')) ?>);
							$(inputs[1]).hide();
							break;

						case '<?= ZBX_PREPROC_RTRIM ?>':
						case '<?= ZBX_PREPROC_LTRIM ?>':
						case '<?= ZBX_PREPROC_TRIM ?>':
							$(inputs[0])
								.show()
								.attr('placeholder', <?= CJs::encodeJson(_('list of characters')) ?>);
							$(inputs[1]).hide();
							break;

						case '<?= ZBX_PREPROC_XPATH ?>':
						case '<?= ZBX_PREPROC_JSONPATH ?>':
							$(inputs[0])
								.show()
								.attr('placeholder', <?= CJs::encodeJson(_('path')) ?>);
							$(inputs[1]).hide();
							break;

						case '<?= ZBX_PREPROC_REGSUB ?>':
							$(inputs[0])
								.show()
								.attr('placeholder', <?= CJs::encodeJson(_('pattern')) ?>);
							$(inputs[1]).show();
							break;

						case '<?= ZBX_PREPROC_BOOL2DEC ?>':
						case '<?= ZBX_PREPROC_OCT2DEC ?>':
						case '<?= ZBX_PREPROC_HEX2DEC ?>':
						case '<?= ZBX_PREPROC_DELTA_VALUE ?>':
						case '<?= ZBX_PREPROC_DELTA_SPEED ?>':
							$(inputs[0]).hide();
							$(inputs[1]).hide();
							break;
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
		'http_proxy_row', 'http_authtype_row', 'http_authtype', 'verify_peer_row', 'verify_host_row', 'ssl_key_file_row',
		'ssl_cert_file_row', 'ssl_key_password_row'
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

$data['http_auth_switcher'] = [];
zbx_subarray_push($data['http_auth_switcher'], HTTPTEST_AUTH_BASIC, 'http_username_row');
zbx_subarray_push($data['http_auth_switcher'], HTTPTEST_AUTH_BASIC, 'http_password_row');
zbx_subarray_push($data['http_auth_switcher'], HTTPTEST_AUTH_NTLM, 'http_username_row');
zbx_subarray_push($data['http_auth_switcher'], HTTPTEST_AUTH_NTLM, 'http_password_row');

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
			new CViewSwitcher('http_authtype', 'change', <?= zbx_jsvalue($data['http_auth_switcher'], true); ?>);
		}
		<?php
		if (!empty($this->data['securityLevelVisibility'])) { ?>
			var securityLevelSwitcher = new CViewSwitcher('snmpv3_securitylevel', 'change',
				<?php echo zbx_jsvalue($this->data['securityLevelVisibility'], true); ?>);
		<?php } ?>

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
				rows = 0,
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
				var row_node = $(e.currentTarget).closest('.'+table_row_class);

				e.preventDefault();

				switch ($(e.currentTarget).data('row-action')) {
					case 'remove_row' :
						removeRow($(e.currentTarget).closest('.'+table_row_class));
						break;

					case 'add_row' :
						var row_data = $(e.currentTarget).data('values'),
							new_row = addRow(row_data||{});

						if (!row_data) {
							new_row.find('[type="text"]').val('');
						}
						break;
				}
			});

			function addRow(values) {
				rows += 1;
				values.index = rows;
				table.sortable('option', 'disabled', rows < 2);

				return $(row_template.evaluate(values))
					.addClass(table_row_class)
					.addClass('sortable')
					.data('values', values)
					.insertBefore(insert_point);
			}

			function addRows(rows_values) {
				$.each(rows_values, function(index, values) {
					addRow(values);
				});
			}

			function removeRow(row_node) {
				rows -= 1;
				row_node.remove();
				table.sortable('option', 'disabled', rows < 2);
			}

			return {
				addRow: function(values) {
					return addRow(values);
				},
				addRows: function(rows_values) {
					addRows(rows_values);
					return table;
				},
				clearTable: function() {
					table.find('.'+table_row_class).remove();
					return table;
				},
				getTableRows: function() {
					return table.find('.'+table_row_class);
				}
			}
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
			var url = $(this).siblings('[name="url"]'),
				table = $('#query_fields_pairs').data('editableTable'),
				pos = url.val().indexOf('?');

			if (pos != -1) {
				var host = url.val().substring(0, pos),
					query = url.val().substring(pos + 1),
					pairs = {},
					index,
					valid = true;

				$.each(query.split('&'), function(i, pair) {
					if ($.trim(pair)) {
						pair = pair.split('=', 2);
						pair.push('');

						try {
							if (/%[01]/.match(pair[0]) || /%[01]/.match(pair[1]) ) {
								// Non-printable characters in URL.
								throw null;
							}

							index = decodeURIComponent(pair[0].replace(/\+/g, ' '));
							pairs[index] = {
								'key': index,
								'value': decodeURIComponent(pair[1].replace(/\+/g, ' '))
							}
						}
						catch( e ) {
							valid = false;
						}
					}
				});

				if (valid) {
					$.each(table.getTableRows(), function(index, row_node) {
						var key = $('[name*="[key]"]', row_node),
							index = key.val();

						if (index === '') {
							index = Object.keys(pairs)[0];
							key.val(index);
						}

						if (typeof pairs[index] !== 'undefined') {
							$('[name*="[value]"]', row_node).val(pairs[index].value);
							delete pairs[index];
						}
					});

					$.each(pairs, function(index, row) {
						table.addRow(row);
					});

					url.val(host);
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
			}
		});
	});
</script>
