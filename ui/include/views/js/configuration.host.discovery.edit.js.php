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

include __DIR__.'/common.item.edit.js.php';
include __DIR__.'/item.preprocessing.js.php';
include __DIR__.'/editabletable.js.php';
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
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CTextAreaFlexible('lld_macro_paths[#{rowNum}][path]', '', [
					'add_post_js' => false,
					'maxlength' => DB::getFieldLength('lld_macro_path', 'path')
				]))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setAttribute('placeholder', _('$.path.to.node'))
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

		init({form_name, counter}) {
			this.form_name = form_name;

			$('#conditions')
				.dynamicRows({
					template: '#condition-row',
					counter: counter,
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
				.dynamicRows({template: '#lld_macro_path-row'})
				.on('click', 'button.element-table-add', () => {
					$('#lld_macro_paths .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
				});
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

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		refresh() {
			const url = new Curl('', false);
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

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				view.refresh();
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				const curl = new Curl('zabbix.php', false);
				curl.setArgument('action', 'host.list');

				location.href = curl.getUrl();
			}
		}
	};
</script>
