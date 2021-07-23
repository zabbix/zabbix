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
		// Nodes.
		this.$container = $('#macros_container .table-forms-td-right');

		// Defines.
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
			.done(response => {
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

		// Find the correct macro inputs and prepare to submit them via AJAX. matches[1] - index, matches[2] field name.
		$macros.each(function() {
			var $this = $(this),
				matches = $this.attr('name').match(/macros\[(\d+)\]\[(\w+)\]/);

			if (!macros.hasOwnProperty(matches[1])) {
				macros[matches[1]] = new Object();
			}

			macros[matches[1]][matches[2]] = $this.val();
		});

		return macros;
	}

	initMacroTable(show_inherited_macros) {
		var $parent = this.getMacroTable();

		$parent
			.dynamicRows({
				remove_next_sibling: show_inherited_macros,
				template: show_inherited_macros ? '#macro-row-tmpl-inherited' : '#macro-row-tmpl'
			})
			.on('click', 'button.element-table-add', () => {
				this.initMacroFields($parent);
			})
			.on('click', 'button.element-table-change', function() {
				const macro_num = $(this).attr('id').split('_')[1];

				if ($('#macros_' + macro_num + '_inherited_type').val() & this.ZBX_PROPERTY_OWN) {
					const macro_type = $('#macros_' + macro_num + '_inherited_macro_type').val();

					$('#macros_' + macro_num + '_inherited_type')
						.val($('#macros_' + macro_num + '_inherited_type').val() & (~this.ZBX_PROPERTY_OWN));

					$('#macros_' + macro_num + '_description')
						.prop('readonly', true)
						.val($('#macros_' + macro_num + '_inherited_description').val())
						.trigger('input');

					const $dropdown_btn = $('#macros_' + macro_num + '_type_btn');

					const dropdown_btn_classes = {
						[this.ZBX_MACRO_TYPE_TEXT]: this.ZBX_STYLE_ICON_TEXT,
						[this.ZBX_MACRO_TYPE_SECRET]: this.ZBX_STYLE_ICON_INVISIBLE,
						[this.ZBX_MACRO_TYPE_VAULT]: this.ZBX_STYLE_ICON_SECRET_TEXT
					};

					$dropdown_btn
						.removeClass()
						.addClass(['btn-alt', 'btn-dropdown-toggle', dropdown_btn_classes[macro_type]].join(' '));

					$('input[type=hidden]', $dropdown_btn.parent())
						.val(macro_type)
						.trigger('change');

					$dropdown_btn
						.prop('disabled', true)
						.attr({'aria-haspopup': false});

					$('#macros_' + macro_num + '_value')
						.prop('readonly', true)
						.val($('#macros_' + macro_num + '_inherited_value').val())
						.trigger('input');

					if (macro_type == this.ZBX_MACRO_TYPE_SECRET) {
						$('#macros_' + macro_num + '_value').prop('disabled', true);
					}

					$('#macros_' + macro_num + '_value_btn')
						.prop('disabled', true)
					$('#macros_' + macro_num + '_value')
						.closest('.input-group')
						.find('.btn-undo')
						.hide();

					$('#macros_' + macro_num + '_change').text(t('S_CHANGE'));
				}
				else {
					$('#macros_' + macro_num + '_inherited_type')
						.val($('#macros_' + macro_num + '_inherited_type').val() | this.ZBX_PROPERTY_OWN);
					$('#macros_' + macro_num + '_value')
						.prop('readonly', false)
						.focus();
					$('#macros_' + macro_num + '_value_btn').prop('disabled', false);
					$('#macros_' + macro_num + '_description').prop('readonly', false);
					$('#macros_' + macro_num + '_type_btn')
						.prop('disabled', false)
						.attr({'aria-haspopup': 'true'});
					$('#macros_' + macro_num + '_change').text(t('Remove'));
				}
			})
			.on('afteradd.dynamicRows', function() {
				$('.input-group').macroValue();
			});

		this.initMacroFields($parent);
	}

	initMacroFields($parent) {
		$('.' + this.ZBX_STYLE_TEXTAREA_FLEXIBLE, $parent).not('.initialized-field').each((i, obj) => {
			const $obj = $(obj);
			$obj.addClass('initialized-field');

			if ($obj.hasClass('macro')) {
				$obj.on('change keydown', e => {
					if (e.type === 'change' || e.which === 13) {
						this.macroToUpperCase($obj);
						$obj.textareaFlexible();
					}
				});
			}

			$obj.textareaFlexible();
		});

		// Init tab indicator observer.
		const macro_indicator = new MacrosTabIndicatorItem;

		// Tab element.
		const tab = document.querySelector('#tab_macroTab');
		if (tab) {
			macro_indicator.initObserver(tab);
		}
	}

	getMacroTable() {
		return $('.host-macros-table', this.$container);
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
