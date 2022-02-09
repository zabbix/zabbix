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
		if (jQuery('#authtype').val() == <?= json_encode(ITEM_AUTHTYPE_PUBLICKEY) ?>
				&& jQuery('#type').val() == <?= json_encode(ITEM_TYPE_SSH) ?>) {
			jQuery('#row_password label').html(<?= json_encode(_('Key passphrase')) ?>);
		}
		else {
			jQuery('#row_password label').html(<?= json_encode(_('Password')) ?>);
		}
	}

	const item_form = {
		init({interfaces, key_type_suggestions, testable_item_types, field_switches, interface_types}) {
			this.interfaces = interfaces;
			this.testable_item_types = testable_item_types;
			this.field_switches = field_switches;
			this.interface_types = interface_types;

			if (typeof key_type_suggestions !== 'undefined') {
				item_type_lookup.init(key_type_suggestions);
			}
		}
	}

	function updateItemFormElements() {
		// test button
		var testable_item_types = item_form.testable_item_types,
			type = parseInt(jQuery('#type').val(), 10),
			key = jQuery('#key').val(),
			is_http_agent_type = (type == <?= ITEM_TYPE_HTTPAGENT ?>);

		if (type == <?= ITEM_TYPE_SIMPLE ?> && (key.substr(0, 7) === 'vmware.' || key.substr(0, 8) === 'icmpping')) {
			jQuery('#test_item').prop('disabled', true);
		}
		else {
			jQuery('#test_item').prop('disabled', (testable_item_types.indexOf(type) == -1));
		}

		// delay field
		if (type == <?= ITEM_TYPE_ZABBIX_ACTIVE ?>) {
			const toggle_fields = [
				'delay',
				'js-item-delay-label',
				'js-item-delay-field',
				'js-item-flex-intervals-label',
				'js-item-flex-intervals-field'
			];
			const set_hidden = (key.substr(0, 8) === 'mqtt.get'),
				object_switcher = globalAllObjForViewSwitcher['type'];

			toggle_fields.forEach((element_id) =>
				object_switcher[set_hidden ? 'hideObj' : 'showObj']({id: element_id})
			);
		}

		$('label[for=interfaceid]').toggleClass('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', !is_http_agent_type);
		$('input[name=interfaceid]').prop('aria-required', !is_http_agent_type);
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

		$('#delayFlexTable').dynamicRows({template: '#delayFlexRow'});

		new CViewSwitcher('authtype', 'change', item_form.field_switches.for_authtype);

		new CViewSwitcher('type', 'change', item_form.field_switches.for_type);

		if ($('#http_authtype').length) {
			new CViewSwitcher('http_authtype', 'change', item_form.field_switches.for_http_auth_type);
		}

		if ($('#allow_traps').length) {
			new CViewSwitcher('allow_traps', 'change', item_form.field_switches.for_traps);
		}

		$("#key").on('keyup change', updateItemFormElements);

		$('#parameters_table').dynamicRows({template: '#parameters_table_row'});

		const item_interface_types = item_form.interface_types;
		const interface_ids_by_types = {};

		for (const interface of Object.values(item_form.interfaces)) {
			if (typeof interface_ids_by_types[interface.type] === 'undefined') {
				interface_ids_by_types[interface.type] = [];
			}

			interface_ids_by_types[interface.type].push(interface.interfaceid);
		}

		$('#type')
			.change(function() {
				updateItemFormElements();
				organizeInterfaces(interface_ids_by_types, item_interface_types, parseInt(this.value, 10));

				setAuthTypeLabel();
			})
			.trigger('change');

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
			const table = $('#query_fields_pairs').data('editableTable');
			const url = parseUrlString(url_node.val());

			if (typeof url === 'object') {
				if (url.pairs.length > 0) {
					table.addRows(url.pairs);
					table.getTableRows()
						.map(function() {
							const empty = $(this).find('input[type="text"]').map(function() {
								return ($(this).val() === '') ? this : null;
							});

							return (empty.length == 2) ? this : null;
						})
						.map(function() {
							table.removeRow(this);
						});
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
					.prop('disabled', true);
			}
			else {
				$(':radio', '#retrieve_mode').prop('disabled', false);
			}
		});
	});

	const item_type_lookup = {
		key_type_suggestions: [],
		preprocessing_active: false,
		form: null,
		key_field: null,
		item_tab_type_field: null,
		preprocessing_tab_type_field: null,
		last_lookup: '',
		inferred_type: null,

		init(key_type_suggestions) {
			this.key_type_suggestions = key_type_suggestions;
			this.form = document.querySelector('#item-form, #item-prototype-form');
			this.key_field = this.form.querySelector('[name=key]');
			this.item_tab_type_field = this.form.querySelector('[name=value_type]');
			this.preprocessing_tab_type_field = this.form.querySelector('[name=value_type_steps]');

			this.preprocessing_tab_type_field.addEventListener('change', (e) => {
				this.item_tab_type_field.value = this.preprocessing_tab_type_field.value;
			});

			this.item_tab_type_field.addEventListener('change', (e) => {
				this.preprocessing_tab_type_field.value = this.item_tab_type_field.value;

				this.updateHintDisplay();

				// 'Do not keep trends' for Calculated with string-types of information is forced on Item save.
				if (this.form.querySelector('[name=type]').value == <?=ITEM_TYPE_CALCULATED ?>) {
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

			this.form.querySelector('[name=type]').addEventListener('change', () => {
				this.updateHintDisplay();
			});

			this.updatePreprocessingState();

			this.lookup(this.key_field.value, false);
		},

		updateHintDisplay() {
			this.form.querySelector('#js-item-type-hint')
				.classList.toggle(<?= json_encode(ZBX_STYLE_DISPLAY_NONE) ?>, (
					this.form.querySelector('[name=type]').value == <?=ITEM_TYPE_CALCULATED ?>
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
		}
	};
</script>
