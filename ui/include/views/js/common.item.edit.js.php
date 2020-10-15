<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

<script type="text/x-jquery-tmpl" id="delayFlexRow">
	<tr class="form_row">
		<td>
			<ul class="<?= CRadioButtonList::ZBX_STYLE_CLASS ?>" id="delay_flex_#{rowNum}_type">
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
	});
</script>
<?php

/*
 * Visibility
 */
$this->data['typeVisibility'] = [];

if ($data['display_interfaces']) {
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_ZABBIX, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_ZABBIX, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SIMPLE, 'interfaceid');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMP, 'interface_row');
	zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMP, 'interfaceid');
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
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMP, 'snmp_oid');
zbx_subarray_push($this->data['typeVisibility'], ITEM_TYPE_SNMP, 'row_snmp_oid');
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
	],
	ITEM_TYPE_SCRIPT => [
		'parameters_row', 'script_row', 'timeout_row'
	]
];
foreach ($ui_rows as $type => $rows) {
	foreach ($rows as $row) {
		zbx_subarray_push($this->data['typeVisibility'], $type, $row);
	}
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
	if ($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_SNMPTRAP || $type == ITEM_TYPE_DEPENDENT) {
		continue;
	}

	zbx_subarray_push($this->data['typeVisibility'], $type, 'row_flex_intervals');
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

$this->data['authTypeVisibility'] = [];
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'publickey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'row_publickey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'privatekey');
zbx_subarray_push($this->data['authTypeVisibility'], ITEM_AUTHTYPE_PUBLICKEY, 'row_privatekey');

?>
<script type="text/javascript">
	function setAuthTypeLabel() {
		if (jQuery('#authtype').val() == <?= json_encode(ITEM_AUTHTYPE_PUBLICKEY) ?>
				&& jQuery('#type').val() == <?= json_encode(ITEM_TYPE_SSH) ?>) {
			jQuery('#row_password label').html(<?= json_encode(_('Key passphrase')) ?>);
		}
		else {
			jQuery('#row_password label').html(<?= json_encode(_('Password')) ?>);
		}
	}

	function updateItemFormElements() {
		// test button
		var testable_item_types = <?= json_encode(CControllerPopupItemTest::getTestableItemTypes($this->data['hostid'])) ?>,
			type = parseInt(jQuery('#type').val()),
			key = jQuery('#key').val();

		if (type == <?= ITEM_TYPE_SIMPLE ?> && (key.substr(0, 7) === 'vmware.' || key.substr(0, 8) === 'icmpping')) {
			jQuery('#test_item').prop('disabled', true);
		}
		else {
			jQuery('#test_item').prop('disabled', (testable_item_types.indexOf(type) == -1));
		}

		// delay field
		if (type == <?= ITEM_TYPE_ZABBIX_ACTIVE ?>) {
			if (key.substr(0, 8) === 'mqtt.get') {
				globalAllObjForViewSwitcher['type'].hideObj(<?= json_encode(['id' => 'delay']) ?>);
				globalAllObjForViewSwitcher['type'].hideObj(<?= json_encode(['id' => 'row_delay']) ?>);
				globalAllObjForViewSwitcher['type'].hideObj(<?= json_encode(['id' => 'row_flex_intervals']) ?>);
			}
			else {
				globalAllObjForViewSwitcher['type'].showObj(<?= json_encode(['id' => 'delay']) ?>);
				globalAllObjForViewSwitcher['type'].showObj(<?= json_encode(['id' => 'row_delay']) ?>);
				globalAllObjForViewSwitcher['type'].showObj(<?= json_encode(['id' => 'row_flex_intervals']) ?>);
			}
		}
	}

	jQuery(document).ready(function($) {
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
		if ($('#http_authtype').length) {
			new CViewSwitcher('http_authtype', 'change', <?= zbx_jsvalue([
				HTTPTEST_AUTH_BASIC => ['http_username_row', 'http_password_row'],
				HTTPTEST_AUTH_NTLM => ['http_username_row', 'http_password_row'],
				HTTPTEST_AUTH_KERBEROS => ['http_username_row', 'http_password_row'],
				HTTPTEST_AUTH_DIGEST => ['http_username_row', 'http_password_row']
			], true) ?>);
		}

		if ($('#allow_traps').length) {
			new CViewSwitcher('allow_traps', 'change', <?= zbx_jsvalue([
				HTTPCHECK_ALLOW_TRAPS_ON => ['row_trapper_hosts']
			], true) ?>);
		}

		$("#key").on('keyup change', updateItemFormElements);

		$('#parameters_table').dynamicRows({template: '#parameters_table_row'});

		$('#type')
			.change(function() {
				var item_interface_types = <?= json_encode(itemTypeInterface()) ?>,
					interface_ids_by_types = <?= json_encode($interface_ids_by_types) ?>;

				updateItemFormElements();
				organizeInterfaces(interface_ids_by_types, item_interface_types, parseInt($(this).val()));

				setAuthTypeLabel();
			})
			.trigger('change');

		$('#test_item').on('click', function() {
			var step_nums = [];
			$('select[name^="preprocessing"][name$="[type]"]', $('#preprocessing')).each(function() {
				var str = $(this).attr('name');
				step_nums.push(str.substr(14, str.length - 21));
			});

			openItemTestDialog(step_nums, true, true, this, -2);
		});

		$('#authtype').bind('change', function() {
			setAuthTypeLabel();
		});

		$('[data-action="parse_url"]').click(function(e) {
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
					'title': <?= json_encode(_('Error')); ?>,
					'content': $('<span>').html(<?=
						json_encode(_('Failed to parse URL.').'<br><br>'._('URL is not properly encoded.'));
					?>),
					'buttons': [
						{
							title: <?= json_encode(_('Ok')); ?>,
							class: 'btn-alt',
							focused: true,
							action: function() {}
						}
					]
				}, e.target);
			}
		});

		$('#request_method').change(function() {
			if ($(this).val() == <?= HTTPCHECK_REQUEST_HEAD ?>) {
				$(':radio', '#retrieve_mode')
					.filter('[value=<?= HTTPTEST_STEP_RETRIEVE_MODE_HEADERS ?>]').click()
					.end()
					.prop('disabled', true);
			}
			else {
				$(':radio', '#retrieve_mode').prop('disabled', false);
			}
		});
	});
</script>
