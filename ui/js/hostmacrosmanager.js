/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * JavaScript class to manage host macros.
 */
class HostMacrosManager {

	constructor(options) {
		// nodes
		this.$container = $('#macros_container .table-forms-td-right');

		// defines
		for (let [prop, value] of Object.entries({...options.properties, ...options.defines})) {
			this[prop] = value;
		}
	}

	load(show_inherited_macros = 0, templateids = []) {
		let url = new Curl('zabbix.php');
		url.setArgument('action', 'hostmacros.list');

		$.ajax(url.getUrl(), {
			data: {
				macros: this.getMacros(),
				show_inherited_macros: +show_inherited_macros,
				templateids: templateids,
				readonly: +this.readonly
			},
			dataType: 'json',
			method: 'POST',
			beforeSend: () => {
				this.loaderStart();
			}
		})
			.done((response) => {
				if (typeof response === 'object' && 'errors' in response) {
					this.$container.append(response.errors);
				}
				else {
					if (typeof response.messages !== 'undefined') {
						this.$container.append(response.messages);
					}

					this.$container.append(response.body);

					// Initialize macros.
					if (this.readonly) {
						$('.' + this.ZBX_STYLE_TEXTAREA_FLEXIBLE, this.getMacroTable()).textareaFlexible();
					}
					else {
						this.initMacroTable(show_inherited_macros);
					}

					// Display debug after loaded content if it is enabled for user.
					if (typeof response.debug !== 'undefined') {
						this.$container.append(response.debug);

						// Override margin for inline usage.
						$('.debug-output', this.$container).css('margin', '10px 0');
					}
				}
			})
			.always(() => {
				$('.debug-output', this.$container).css('margin', '10px 0');
				this.loaderStop();
			});
	}

	/*
	 * Get macros from UI.
	 */
	getMacros() {
		var $macros = jQuery('input[name^="macros"], textarea[name^="macros"]', this.$container).not(':disabled'),
			macros = {};

		// Find the correct macro inputs and prepare to submit them via AJAX.
		$macros.each(function() {
			var $this = $(this);
			const [, macro_num, field] = $this.attr('name').match(/macros\[(\d+)\]\[(\w+)\]/);

			if (!macros.hasOwnProperty(macro_num)) {
				macros[macro_num] = new Object();
			}

			macros[macro_num][field] = $this.val();
		});

		return macros;
	}

	initMacroTable(show_inherited_macros) {
		var $parent = this.getMacroTable(),
			macros_manager = this;

		show_inherited_macros = +show_inherited_macros;

		$parent
			.dynamicRows({
				remove_next_sibling: show_inherited_macros,
				template: show_inherited_macros ? '#macro-row-tmpl-inherited' : '#macro-row-tmpl'
			})
			.on('click', 'button.element-table-add', () => {
				this.initMacroFields($parent);
			})
			.on('click', 'button.element-table-change', function () {
				const macro_num = $(this).attr('id').split('_')[1];

				/**
				* References elements of current macro's scope (row).
				*
				* @param {string} id_postfix Postfix of element ID for current macro.
				*
				* @return {JQuerySelector} Element/input related to current macro.
				*/
				function macro_child(id_postfix) {
					return $('#macros_' + macro_num + '_' + id_postfix);
				}

				if (macro_child('inherited_type').val() & macros_manager.ZBX_PROPERTY_OWN) {
					const macro_type = macro_child('inherited_macro_type').val(),
						$dropdown_btn = macro_child('type_button'),
						dropdown_btn_classes = {
							[macros_manager.ZBX_MACRO_TYPE_TEXT]: macros_manager.ZBX_STYLE_ICON_TEXT,
							[macros_manager.ZBX_MACRO_TYPE_SECRET]: macros_manager.ZBX_STYLE_ICON_INVISIBLE,
							[macros_manager.ZBX_MACRO_TYPE_VAULT]: macros_manager.ZBX_STYLE_ICON_SECRET_TEXT
						};

					macro_child('inherited_type')
						.val(macro_child('inherited_type').val() & (~macros_manager.ZBX_PROPERTY_OWN));
					macro_child('description')
						.prop('readonly', true)
						.val(macro_child('inherited_description').val())
						.trigger('input');
					$dropdown_btn
						.removeClass()
						.addClass(['btn-alt', 'btn-dropdown-toggle', dropdown_btn_classes[macro_type]].join(' '))
						.prop('disabled', true)
						.attr({'aria-haspopup': false});
					$('input[type=hidden]', $dropdown_btn.parent())
						.val(macro_type)
						.trigger('change');
					macro_child('value')
						.prop('readonly', true)
						.prop('disabled', true)
						.val(macro_child('inherited_value').val())
						.trigger('input')
							.closest('.input-group')
							.find('.btn-undo')
							.hide();
					macro_child('value_btn').prop('disabled', true);
					macro_child('change').text(t('S_CHANGE'));
				}
				else {
					macro_child('inherited_type')
						.val(macro_child('inherited_type').val() | macros_manager.ZBX_PROPERTY_OWN);
					macro_child('value')
						.prop('readonly', false)
						.prop('disabled', false)
						.focus()
							.closest('.input-group')
							.find('.btn-undo')
							.hide();
					macro_child('value_btn').prop('disabled', false);
					macro_child('description').prop('readonly', false);
					macro_child('type_button')
						.prop('disabled', false)
						.attr({'aria-haspopup': 'true'});
					macro_child('change').text(t('Remove'));
				}
			})
			.on('afteradd.dynamicRows', function() {
				$('.input-group').macroValue();
			});

		this.initMacroFields($parent);
	}

	initMacroFields($parent) {
		$('.' + this.ZBX_STYLE_TEXTAREA_FLEXIBLE, $parent).not('.initialized-field').each((index, textarea) => {
			const $textarea = $(textarea);

			if ($textarea.hasClass('macro')) {
				$textarea.on('change keydown', e => {
					if (e.type === 'change' || e.which === 13) {
						this.macroToUpperCase($textarea);
						$textarea.textareaFlexible();
					}
				});
			}

			$textarea
				.addClass('initialized-field')
				.textareaFlexible();
		});

		// Init tab indicator observer.
		const macro_indicator = new MacrosTabIndicatorItem,
			tab = document.querySelector('#tab_macros-tab');

		if (tab) {
			macro_indicator.initObserver(tab);
			macro_indicator.addAttributes(tab);
		}
	}

	getMacroTable() {
		return $('.inherited-macros-table, .host-macros-table', this.$container).eq(0);
	}

	loaderStart() {
		this.$preloader = $('<span>', {class: 'is-loading'});
		this.$container
			.empty()
			.append(this.$preloader);
	}

	loaderStop() {
		this.$preloader.remove();
	}

	macroToUpperCase($element) {
		var macro = $element.val(),
			end = macro.indexOf(':');

		if (end == -1) {
			$element.val(macro.toUpperCase());
		}
		else {
			var macro_part = macro.substr(0, end),
				context_part = macro.substr(end, macro.length);

			$element.val(macro_part.toUpperCase() + context_part);
		}
	}
};
