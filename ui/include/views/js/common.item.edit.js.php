<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
<script>
	function setAuthTypeLabel() {
		if (document.getElementById('type').value == <?= ITEM_TYPE_SSH ?>) {
			document.getElementById('js-item-password-label').innerText =
					document.getElementById('authtype').value == <?= ITEM_AUTHTYPE_PUBLICKEY ?>
				? <?= json_encode(_('Key passphrase')) ?>
				: <?= json_encode(_('Password')) ?>;
		}
		else {
			document.getElementById('js-item-password-label').innerText = <?= json_encode(_('Password')) ?>;
		}
	}

	const item_form = {
		init({interfaces, value_type_by_keys, keys_by_item_type, testable_item_types, field_switches, interface_types,
				discovered_item, inherited_timeouts}) {
			this.interfaces = interfaces;
			this.testable_item_types = testable_item_types;
			this.field_switches = field_switches;
			this.interface_types = interface_types;
			this.discovered_item = discovered_item === undefined ? false : discovered_item;
			this.type = document.getElementById('type');
			this.custom_timeout = document.getElementById('custom_timeout');
			this.inherited_timeout = document.getElementById('inherited_timeout');
			this.timeout = document.getElementById('timeout');
			this.inherited_timeouts = inherited_timeouts;

			if (typeof value_type_by_keys !== 'undefined' && typeof keys_by_item_type !== 'undefined') {
				item_type_lookup.init(value_type_by_keys, keys_by_item_type, this.discovered_item);
			}
		}
	}

	function updateItemFormElements() {
		// test button
		var testable_item_types = item_form.testable_item_types,
			type = parseInt(jQuery('#type').val(), 10),
			key = jQuery('#key').val(),
			interface_optional = <?= json_encode(
					array_keys(itemTypeInterface(), INTERFACE_TYPE_OPT)
				) ?>.indexOf(type) != -1;

		if (type == <?= ITEM_TYPE_SIMPLE ?>
				&& (key.substring(0, 7) === 'vmware.' || key.substring(0, 8) === 'icmpping')) {
			jQuery('#test_item').prop('disabled', true);
		}
		else {
			jQuery('#test_item').prop('disabled', (testable_item_types.indexOf(type) == -1));
		}

		if (type == <?= ITEM_TYPE_SIMPLE ?>) {
			const toggle_fields = [
				'js-item-timeout-label',
				'js-item-timeout-field'
			];
			const set_hidden = key.substring(0, 8) === 'icmpping' || key.substring(0, 7) === 'vmware.';
			const object_switcher = globalAllObjForViewSwitcher['type'];

			toggle_fields.forEach((element_id) =>
				object_switcher[set_hidden ? 'hideObj' : 'showObj']({id: element_id})
			);
		}
		else if (type == <?= ITEM_TYPE_ZABBIX_ACTIVE ?>) {
			const toggle_fields = [
				'delay',
				'js-item-delay-label',
				'js-item-delay-field',
				'js-item-flex-intervals-label',
				'js-item-flex-intervals-field'
			];
			const set_hidden = key.substring(0, 8) === 'mqtt.get';
			const object_switcher = globalAllObjForViewSwitcher['type'];

			toggle_fields.forEach((element_id) =>
				object_switcher[set_hidden ? 'hideObj' : 'showObj']({id: element_id})
			);
		}
		else if (type == <?= ITEM_TYPE_SNMP ?>) {
			const toggle_fields = [
				'js-item-timeout-label',
				'js-item-timeout-field'
			];
			const snmp_oid = document.getElementById('snmp_oid').value;
			const set_hidden = snmp_oid.substring(0, 4) !== 'get[' && snmp_oid.substring(0, 5) !== 'walk[';
			const object_switcher = globalAllObjForViewSwitcher['type'];

			toggle_fields.forEach((element_id) =>
				object_switcher[set_hidden ? 'hideObj' : 'showObj']({id: element_id})
			);
		}

		item_form.inherited_timeout.value = item_form.inherited_timeouts[type] || '';

		if (item_form.custom_timeout.querySelector(':checked').value == <?= ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED ?>) {
			item_form.timeout.value = item_form.inherited_timeout.value;
		}

		$('label[for=interfaceid]').toggleClass('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', !interface_optional);
		$('input[name=interfaceid]').prop('aria-required', !interface_optional);

		$('z-select[name="value_type"]').trigger('change');
	}

	jQuery(document).ready(function($) {
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

		$('#delayFlexTable').dynamicRows({template: '#delayFlexRow', allow_empty: true});

		new CViewSwitcher('authtype', 'change', item_form.field_switches.for_authtype);

		new CViewSwitcher('type', 'change', item_form.field_switches.for_type);

		if ($('#http_authtype').length) {
			new CViewSwitcher('http_authtype', 'change', item_form.field_switches.for_http_auth_type);
		}

		if ($('#allow_traps').length) {
			new CViewSwitcher('allow_traps', 'change', item_form.field_switches.for_traps);
		}

		$("#key, #snmp_oid").on('keyup change', updateItemFormElements);

		$('#parameters_table').dynamicRows({template: '#parameters_table_row', allow_empty: true});

		const item_interface_types = item_form.interface_types;
		const interface_ids_by_types = {};

		for (const interface of Object.values(item_form.interfaces)) {
			if (typeof interface_ids_by_types[interface.type] === 'undefined') {
				interface_ids_by_types[interface.type] = [];
			}

			interface_ids_by_types[interface.type].push(interface.interfaceid);
		}

		$('z-select[name="value_type"]').change(function() {
			const ITEM_VALUE_TYPE_BINARY = <?= ITEM_VALUE_TYPE_BINARY?>,
				binary_selected = this.value == ITEM_VALUE_TYPE_BINARY,
				disable_binary = $('#type').val() != <?= ITEM_TYPE_DEPENDENT ?>;

			this.getOptionByValue(ITEM_VALUE_TYPE_BINARY).hidden = disable_binary;
			document.querySelector('z-select[name="value_type_steps"]')
				.getOptionByValue(ITEM_VALUE_TYPE_BINARY)
				.hidden = disable_binary;

			if (binary_selected && disable_binary) {
				this.value = this.getOptions().find((option) => option.value != ITEM_VALUE_TYPE_BINARY).value;
				$('#type').trigger('change');

				return false;
			}
		});

		$('#type')
			.change(function() {
				updateItemFormElements();
				organizeInterfaces(interface_ids_by_types, item_interface_types, parseInt(this.value, 10));

				setAuthTypeLabel();

				if (item_type_lookup.form !== null) {
					item_type_lookup.update();
				}
			})
			.trigger('change');

		item_form.custom_timeout.addEventListener('change', () => {
			if (item_form.custom_timeout.querySelector(':checked').value == <?= ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED ?>) {
				item_form.timeout.disabled = false;
				item_form.timeout.style.display = '';
				item_form.inherited_timeout.style.display = 'none';
			}
			else {
				item_form.timeout.disabled = true;
				item_form.timeout.style.display = 'none';
				item_form.inherited_timeout.style.display = '';
			}
		});

		item_form.custom_timeout.dispatchEvent(new Event('change'));

		$('#test_item').on('click', function() {
			var step_nums = [];
			$('z-select[name^="preprocessing"][name$="[type]"]', $('#preprocessing')).each(function() {
				var str = $(this).attr('name');
				step_nums.push(str.substr(14, str.length - 21));
			});

			openItemTestDialog(step_nums, true, true, this, -2);
		});

		$('#authtype').bind('change', function() {
			setAuthTypeLabel();
		});

		$('[data-action="parse_url"]').click(function(e) {
			const url_node = $(this).siblings('[name="url"]');
			const table = $('#query-fields-table').data('dynamicRows');
			const url = parseUrlString(url_node.val());

			if (typeof url === 'object') {
				if (url.pairs.length > 0) {
					table.addRows(url.pairs);
					table.removeRows(row =>
						[...row.querySelectorAll('[name^="query_fields"]')]
							.filter(field => field.value === '')
							.length == 2
					);
				}

				url_node.val(url.url);
			}
			else {
				overlayDialogue({
					'title': <?= json_encode(_('Error')); ?>,
					'class': 'modal-popup position-middle',
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
					.prop('readonly', true);
			}
			else {
				$(':radio', '#retrieve_mode')
					.prop('readonly', false);
			}
		});
	});

	const item_type_lookup = {
		value_type_by_keys: [],
		key_type_suggestions: {},
		keys_by_item_type: [],
		preprocessing_active: false,
		form: null,
		key_field: null,
		item_tab_type_field: null,
		preprocessing_tab_type_field: null,
		last_lookup: '',
		inferred_type: null,
		item_type: null,

		init(value_type_by_keys, keys_by_item_type, discovered_item) {
			this.value_type_by_keys = value_type_by_keys;
			this.keys_by_item_type = keys_by_item_type;
			this.form = document.querySelector('#item-form, #item-prototype-form');
			this.key_field = this.form.querySelector('[name=key]');
			this.item_tab_type_field = this.form.querySelector('[name=value_type]');
			this.preprocessing_tab_type_field = this.form.querySelector('[name=value_type_steps]');
			this.item_type = this.form.querySelector('[name=type]');
			this.discovered_item = discovered_item;

			this.updateKeyTypeSuggestions();

			this.item_tab_type_field.addEventListener('change', (e) => {
				this.preprocessing_tab_type_field.value = this.item_tab_type_field.value;

				this.updateHintDisplay();

				// 'Do not store' trends for Calculated with string-types of information is forced on Item save.
				if (this.item_type.value == <?=ITEM_TYPE_CALCULATED ?> && !this.discovered_item) {
					if (e.target.value == <?= ITEM_VALUE_TYPE_FLOAT ?>
							|| e.target.value == <?= ITEM_VALUE_TYPE_UINT64 ?>) {
						this.form.querySelector('#trends_mode_1').disabled = false;
					}
					else {
						this.form.querySelector('#trends_mode_0').checked = true;
						this.form.querySelector('#trends_mode_1').disabled = true;
					}
				}
			});

			this.preprocessing_tab_type_field.addEventListener('change', (e) => {
				this.item_tab_type_field.value = this.preprocessing_tab_type_field.value;
				this.item_tab_type_field.dispatchEvent(new Event('change'));
			});

			['change', 'input', 'help_items.paste'].forEach((event_type) => {
				this.key_field.addEventListener(event_type, (e) => {
					if (this.preprocessing_active) {
						return this.lookup(this.key_field.value, false);
					}

					this.lookup(this.key_field.value);
				});
			});

			this.form.querySelector('#preprocessing').addEventListener('item.preprocessing.change', () => {
				this.updatePreprocessingState();
			});

			this.item_type.addEventListener('change', () => {
				this.updateHintDisplay();
				this.updateKeyTypeSuggestions();
			});

			this.updatePreprocessingState();

			this.lookup(this.key_field.value, false);
		},

		updateHintDisplay() {
			this.form.querySelector('#js-item-type-hint')
				.classList.toggle(<?= json_encode(ZBX_STYLE_DISPLAY_NONE) ?>, (
					this.item_type.value == <?=ITEM_TYPE_CALCULATED ?>
						|| this.preprocessing_active || this.inferred_type === null
						|| this.item_tab_type_field.value == this.inferred_type
				));
		},

		updatePreprocessingState() {
			const last_state_active = this.preprocessing_active;
			const change_event = new CustomEvent('change');

			this.preprocessing_active = (this.form.querySelector('.preprocessing-step') !== null);

			if (last_state_active && !this.preprocessing_active) {
				this.last_lookup = '';
				this.key_field.dispatchEvent(change_event);
			}

			this.form.querySelectorAll('.js-item-preprocessing-type').forEach((element) =>
				element.classList.toggle(<?= json_encode(ZBX_STYLE_DISPLAY_NONE) ?>, !this.preprocessing_active)
			);

			this.item_tab_type_field.dispatchEvent(change_event);
		},

		updateKeyTypeSuggestions() {
			this.key_type_suggestions = {};

			if (this.item_type.value in this.keys_by_item_type) {
				for (let [key, value] of Object.entries(this.value_type_by_keys)) {
					if (this.keys_by_item_type[this.item_type.value].includes(key)) {
						this.key_type_suggestions[key] = value;
					}
				}
			}
		},

		/**
		 * Infer expected Item value type from (partial) key name.
		 *
		 * Best case scenario a direct match to a key name is found.
		 * Otherwise a check is performed to see that key names matching so far are of the same type.
		 * Else type is undetermined (null).
		 *
		 * @param {string}  key_part           Key name part entered.
		 * @param {boolean} set_to_field=true  Pass False to perform background lookup, not updating input states.
		 * @return {void}
		 */
		lookup(key_part, set_to_field = true) {
			key_part = key_part
				.split('[')
				.shift()
				.trimLeft()
				.toLowerCase();

			if (key_part === this.last_lookup) {
				return;
			}

			this.last_lookup = key_part;
			this.inferred_type = null;

			if (key_part === '') {
				return;
			}

			if (this.last_lookup in this.key_type_suggestions) {
				this.inferred_type = this.key_type_suggestions[this.last_lookup];
			}
			else {
				const matches = Object.entries(this.key_type_suggestions).filter(([key_name, value_type]) => {
					return key_name.startsWith(this.last_lookup);
				});

				if (matches.length > 0) {
					const sample_type = matches[0][1];

					if (matches.length == 1 || matches.every(([key_name, value_type]) => value_type === sample_type)) {
						this.inferred_type = sample_type;
					}
				}
			}

			if (this.inferred_type === null) {
				this.item_tab_type_field.dispatchEvent(new CustomEvent('change'));
				return;
			}

			if (set_to_field) {
				this.item_tab_type_field.value = this.inferred_type;
			}

			this.item_tab_type_field.dispatchEvent(new CustomEvent('change'));
		},

		update() {
			this.inferred_type = null;
			this.last_lookup = '';
			this.lookup(this.key_field.value, false);
		}
	};
</script>
