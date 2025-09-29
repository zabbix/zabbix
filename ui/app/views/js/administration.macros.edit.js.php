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
?>

<script type="text/x-jquery-tmpl" id="macro-row-tmpl">
	<?= (new CRow([
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
					->addClass('macro')
					->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->setAttribute('placeholder', '{$MACRO}')
					->disableSpellcheck()
					->setErrorContainer('macro_#{rowNum}_error_container')
					->setErrorLabel(_('Macro'))
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false))
					->setErrorContainer('macro_#{rowNum}_error_container')
					->setErrorLabel(_('Value'))
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setMaxlength(DB::getFieldLength('globalmacro' , 'description'))
					->setAttribute('placeholder', _('description'))
					->setErrorContainer('macro_#{rowNum}_error_container')
					->setErrorLabel(_('Description'))
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CButton('macros[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString().
		(new CRow([
			(new CCol())
				->setId('macro_#{rowNum}_error_container')
				->addClass(ZBX_STYLE_ERROR_CONTAINER)
				->setColSpan(4)
		]))->toString()
	?>
</script>

<script type="text/javascript">
	$(function() {
		const table = $('#tbl_macros');
		let removed = 0;
		const form_element = document.forms.macrosForm;
		const form = new CForm(form_element, <?= json_encode($data['js_validation_rules']) ?>);

		form_element.addEventListener('submit', (e) => {
			e.preventDefault();
			clearMessages();

			const fields = form.getAllValues();
			const curl = new Curl(form_element.action);

			form.validateSubmit(fields)
				.then(result => {
					if (result) {
						let empty_removed = 0;

						fields.macros = Object.values(fields.macros).filter(macro => {
							if (macro.macro === '' && macro.globalmacroid) {
								empty_removed += 1;
							}

							return macro.macro !== '';
						});

						if (removed || empty_removed) {
							return confirm(<?= json_encode(_('Are you sure you want to delete?')) ?> + ' '
								+ (removed + empty_removed) + ' '
								+ <?= json_encode(_('macro(s)')) ?> + '?'
							);
						}
					}

					return result;
				})
				.then(result => {
					if (!result) {
						return;
					}

					fetch(curl.getUrl(), {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify(fields)
					})
						.then(response => response.json())
						.then(response => {
							if ('error' in response) {
								throw {error: response.error};
							}

							if ('form_errors' in response) {
								form.setErrors(response.form_errors, true, true);
								form.renderErrors();

								for (const element of form_element.parentNode.children) {
									if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
										element.parentNode.removeChild(element);
									}
								}

								return;
							}

							postMessageOk(response.success.title);
							location.href = location.href;
						})
						.catch(exception => {
							for (const element of form_element.parentNode.children) {
								if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
									element.parentNode.removeChild(element);
								}
							}

							let title;
							let	messages;

							if (typeof exception === 'object' && 'error' in exception) {
								title = exception.error.title;
								messages = exception.error.messages;
							}
							else {
								messages = [<?= json_encode(_('Unexpected server error.')) ?>];
							}

							addMessage(makeMessageBox('bad', messages, title)[0]);
						});
				});
		});

		table
			.on('click', 'button.element-table-remove', function() {
				// Check if the macro has a hidden ID element. If it does - increment the deleted macro counter.
				removed += $('#macros_' + $(this).attr('id').split('_')[1] + '_globalmacroid').length;
			})
			.dynamicRows({template: '#macro-row-tmpl', allow_empty: true, counter: <?= count($data['macros']) ?>})
			.on('afteradd.dynamicRows', function() {
				$('.macro-input-group', table).macroValue();
				$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', table).textareaFlexible();
			})
			.find('.macro-input-group')
			.macroValue();

		table
			.on('change keydown', '.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>.macro', function(event) {
				if (event.type === 'change' || event.which === 13) {
					$(this)
						.val($(this).val().replace(/([^:]+)/, (value) => value.toUpperCase('$1')))
						.textareaFlexible();
				}
			})
			.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
			.textareaFlexible();
	});
</script>
