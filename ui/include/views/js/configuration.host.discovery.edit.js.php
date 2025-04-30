<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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

include __DIR__.'/common.item.edit.js.php';
include __DIR__.'/item.preprocessing.js.php';
include __DIR__.'/itemtest.js.php';
include __DIR__.'/configuration.host.discovery.edit.overr.js.php';
?>
<script type="text/x-jquery-tmpl" id="condition-row">
	<?=
		(new CRow([[
				new CSpan('#{formulaId}'),
				new CVar('conditions[#{rowNum}][formulaid]', '#{formulaId}')
			],
			(new CTextBox('conditions[#{rowNum}][macro]', '', false, 64))
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->addClass(ZBX_STYLE_UPPERCASE)
				->addClass('macro')
				->setAttribute('placeholder', '{#MACRO}')
				->setAttribute('data-formulaid', '#{formulaId}'),
			(new CSelect('conditions[#{rowNum}][operator]'))
				->setValue(CONDITION_OPERATOR_REGEXP)
				->addClass('js-operator')
				->addOptions(CSelect::createOptionsFromArray([
					CONDITION_OPERATOR_REGEXP => _('matches'),
					CONDITION_OPERATOR_NOT_REGEXP => _('does not match'),
					CONDITION_OPERATOR_EXISTS => _('exists'),
					CONDITION_OPERATOR_NOT_EXISTS => _('does not exist')
				])),
			(new CDiv(
				(new CTextBox('conditions[#{rowNum}][value]', '', false, 255))
					->addClass('js-value')
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setAttribute('placeholder', _('regular expression'))
			))->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH),
			(new CCol(
				(new CButton('conditions_#{rowNum}_remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="lld_macro_path-row">
	<?= (new CRow([
			(new CCol(
				(new CTextAreaFlexible('lld_macro_paths[#{rowNum}][lld_macro]', '', [
					'add_post_js' => false,
					'maxlength' => DB::getFieldLength('lld_macro_path', 'lld_macro')
				]))
					->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->addClass(ZBX_STYLE_UPPERCASE)
					->setAttribute('placeholder', '{#MACRO}')
					->disableSpellcheck()
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CTextAreaFlexible('lld_macro_paths[#{rowNum}][path]', '', [
					'add_post_js' => false,
					'maxlength' => DB::getFieldLength('lld_macro_path', 'path')
				]))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setAttribute('placeholder', _('$.path.to.node'))
					->disableSpellcheck()
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CButton('lld_macro_paths[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))
			->addClass('form_row')
			->toString()
	?>
</script>

<script>
	const view = {
		form_name: null,
		context: null,

		init({form_name, counter, context, token, readonly, query_fields, headers}) {
			this.form_name = form_name;
			this.context = context;
			this.token = token;

			$('#conditions')
				.dynamicRows({
					template: '#condition-row',
					counter: counter,
					allow_empty: true,
					dataCallback: (data) => {
						data.formulaId = num2letter(data.rowNum);

						return data;
					}
				})
				.bind('tableupdate.dynamicRows', function(event, options) {
					$('#js-item-condition-field, #js-item-condition-label').toggle($(options.row, $(this)).length > 1);

					if ($('#evaltype').val() != <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
						view.updateExpression();
					}
				})
				.on('change', '.macro', function() {
					if ($('#evaltype').val() != <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
						view.updateExpression();
					}

					// Change value attribute to trigger MutationObserver event for tab indicator.
					$(this).attr('value', $(this).val());
				})
				.on('afteradd.dynamicRows', (event) => {
					[...event.currentTarget.querySelectorAll('.js-operator')]
						.pop()
						.addEventListener('change', view.toggleConditionValue);
				})
				.ready(() => {
					$('#js-item-condition-field, #js-item-condition-label')
						.toggle($('.form_row', $('#conditions')).length > 1);

					[...document.getElementById('conditions').querySelectorAll('.js-operator')].map((elem) => {
						elem.addEventListener('change', view.toggleConditionValue);
					});
				});

			$('#evaltype').change(function() {
				const show_formula = ($(this).val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>);

				$('#expression').toggle(!show_formula);
				$('#formula').toggle(show_formula);
				if (!show_formula) {
					view.updateExpression();
				}
			});

			$('#evaltype').trigger('change');

			$('#type').change(() => {
				const type = parseInt($('#type').val());

				if (type == <?= ITEM_TYPE_SSH ?> || type == <?= ITEM_TYPE_TELNET ?>) {
					$('label[for=username]').addClass('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
					$('input[name=username]').attr('aria-required', 'true');
				}
				else {
					$('label[for=username]').removeClass('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
					$('input[name=username]').removeAttr('aria-required');
				}
			}).trigger('change');

			$('#lld_macro_paths')
				.dynamicRows({template: '#lld_macro_path-row', allow_empty: true})
				.on('click', 'button.element-table-add', () => {
					$('#lld_macro_paths .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
				});

			let button = document.querySelector(`[name="${this.form_name}"] .js-execute-item`);

			if (button instanceof Element) {
				button.addEventListener('click', e => this.executeNow(e.target));
			}

			const updateSortOrder = (table, field_name) => {
				table.querySelectorAll('.form_row').forEach((row, index) => {
					for (const field of row.querySelectorAll(`[name^="${field_name}["]`)) {
						field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
					}
				});
			};

			jQuery('#query-fields-table')
				.dynamicRows({
					template: '#query-field-row-tmpl',
					rows: query_fields,
					allow_empty: true,
					sortable: true,
					sortable_options: {
						target: 'tbody',
						selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
						freeze_end: 1,
						enable_sorting: !readonly
					}
				})
				.on('tableupdate.dynamicRows', (e) => updateSortOrder(e.target, 'query_fields'));

			jQuery('#headers-table')
				.dynamicRows({
					template: '#item-header-row-tmpl',
					rows: headers,
					allow_empty: true,
					sortable: true,
					sortable_options: {
						target: 'tbody',
						selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
						freeze_end: 1,
						enable_sorting: !readonly
					}
				})
				.on('tableupdate.dynamicRows', (e) => updateSortOrder(e.target, 'headers'));

			document.querySelectorAll('#lifetime_type, #enabled_lifetime_type').forEach(element => {
				element.addEventListener('change', () => this.updateLostResourcesFields());
			});

			this.updateLostResourcesFields();
			this.initPopupListeners();
		},

		updateLostResourcesFields() {
			const lifetime_type = document.querySelector('[name="lifetime_type"]:checked').value;
			const enabled_lifetime_type = document.querySelector('[name="enabled_lifetime_type"]:checked').value;
			const delete_immediately = lifetime_type == <?= ZBX_LLD_DELETE_IMMEDIATELY ?>;

			document.getElementById('enabled_lifetime_type').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
				delete_immediately
			);
			document.getElementById('lifetime').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
				lifetime_type != <?= ZBX_LLD_DELETE_AFTER ?>
			);
			document.getElementById('enabled_lifetime').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
				delete_immediately || enabled_lifetime_type != <?= ZBX_LLD_DISABLE_AFTER ?>
			);
			document.getElementById('js-item-disable-resources-field').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
				delete_immediately
			);
			document.getElementById('js-item-disable-resources-label').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
				delete_immediately
			);
		},

		updateExpression() {
			const conditions = [];

			$('#conditions .macro').each((index, macroInput) => {
				macroInput = $(macroInput);
				macroInput.val(macroInput.val().toUpperCase());

				conditions.push({
					id: macroInput.data('formulaid'),
					type: macroInput.val()
				});
			});

			$('#expression').html(getConditionFormula(conditions, +$('#evaltype').val()));
		},

		toggleConditionValue(event) {
			const value = event.currentTarget.closest('.form_row').querySelector('.js-value');
			const show_value = (event.currentTarget.value == <?= CONDITION_OPERATOR_REGEXP ?>
				|| event.currentTarget.value == <?= CONDITION_OPERATOR_NOT_REGEXP ?>);

			value.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !show_value);

			if (!show_value) {
				value.value = '';
			}
		},

		executeNow(button) {
			button.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.execute');

			const data = {
				...this.token,
				itemids: [document.querySelector(`[name="${this.form_name}"] [name="itemid"]`).value],
				discovery_rule: 1
			};

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(data)
			})
				.then((response) => response.json())
				.then((response) => {
					clearMessages();

					/*
					 * Using postMessageError or postMessageOk would mean that those messages are stored in session
					 * messages and that would mean to reload the page and show them. Also postMessageError would be
					 * displayed right after header is loaded. Meaning message is not inside the page form like that is
					 * in postMessageOk case. Instead show message directly that comes from controller.
					 */
					if ('error' in response) {
						addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true, true));
					}
					else if('success' in response) {
						addMessage(makeMessageBox('good', [], response.success.title, true, false));
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					button.classList.remove('is-loading');

					// Deselect the "Execute now" button in both success and error cases, since there is no page reload.
					button.blur();
				});
		},

		refresh() {
			const url = new Curl('');
			const form = document.getElementsByName(this.form_name)[0];

			// Append overrides to main form.
			let hidden_form = form.querySelector('#hidden-form');

			hidden_form && hidden_form.remove();
			hidden_form = document.createElement('div');
			hidden_form.id = 'hidden-form';
			hidden_form.appendChild(lldoverrides.overrides.toFragment());

			form.appendChild(hidden_form);

			const fields = getFormFields(form);

			post(url.getUrl(), fields);
		},

		initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					if (data.submit.success.action === 'delete') {
						const url = new URL('host_discovery.php', location.href);

						url.searchParams.set('context', this.context);

						event.setRedirectUrl(url.href);
					}
					else {
						this.refresh();
					}
				}
			});
		}
	};
</script>
