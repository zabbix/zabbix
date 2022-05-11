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
	static ZBX_PROPERTY_OWN = 0x02;
	static ZBX_MACRO_TYPE_TEXT = 0;
	static ZBX_MACRO_TYPE_SECRET = 1;
	static ZBX_MACRO_TYPE_VAULT = 2;
	static ZBX_STYLE_ICON_TEXT = 'icon-text';
	static ZBX_STYLE_ICON_INVISIBLE = 'icon-invisible';
	static ZBX_STYLE_ICON_SECRET_TEXT = 'icon-secret';
	static ZBX_STYLE_TEXTAREA_FLEXIBLE = 'textarea-flexible';

	constructor({readonly, parent_hostid}) {
		this.readonly = readonly;
		this.parent_hostid = parent_hostid ?? null;
		this.$container = $('#macros_container .table-forms-td-right');
	}

	load(show_inherited_macros, templateids) {
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'hostmacros.list');

		const post_data = {
			macros: this.getMacros(),
			show_inherited_macros: show_inherited_macros ? 1 : 0,
			templateids: templateids,
			readonly: this.readonly ? 1 : 0
		};

		if (this.parent_hostid !== null) {
			post_data.parent_hostid = this.parent_hostid;
		}

		$.ajax(url.getUrl(), {
			data: post_data,
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
						$('.' + HostMacrosManager.ZBX_STYLE_TEXTAREA_FLEXIBLE, this.getMacroTable()).textareaFlexible();
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
				this.loaderStop();
			});
	}

	/*
	 * Get macros from UI.
	 */
	getMacros() {
		const $macros = $('input[name^="macros"], textarea[name^="macros"]', this.$container).not(':disabled');
		const macros = {};

		// Find the correct macro inputs and prepare to submit them via AJAX.
		$macros.each(function() {
			const $this = $(this);
			const [, macro_num, field] = $this.attr('name').match(/macros\[(\d+)\]\[(\w+)\]/);

			if (!macros.hasOwnProperty(macro_num)) {
				macros[macro_num] = new Object();
			}

			macros[macro_num][field] = $this.val();
		});

		return macros;
	}

	initMacroTable(show_inherited_macros) {
		const $parent = this.getMacroTable();

		$parent
			.dynamicRows({
				remove_next_sibling: show_inherited_macros,
				template: show_inherited_macros ? '#macro-row-tmpl-inherited' : '#macro-row-tmpl'
			})
			.on('click', 'button.element-table-add', () => {
				this.initMacroFields($parent);
			})
			.on('click', 'button.element-table-change', (e) => {
				const macro_num = e.target.id.split('_')[1];
				const inherited_type = $('#macros_'+macro_num+'_inherited_type').val();
				const macro_type = $('#macros_'+macro_num+'_inherited_macro_type').val();

				if (inherited_type & HostMacrosManager.ZBX_PROPERTY_OWN) {
					const dropdown_btn_classes = {
						[HostMacrosManager.ZBX_MACRO_TYPE_TEXT]: HostMacrosManager.ZBX_STYLE_ICON_TEXT,
						[HostMacrosManager.ZBX_MACRO_TYPE_SECRET]: HostMacrosManager.ZBX_STYLE_ICON_INVISIBLE,
						[HostMacrosManager.ZBX_MACRO_TYPE_VAULT]: HostMacrosManager.ZBX_STYLE_ICON_SECRET_TEXT
					};

					$('#macros_'+macro_num+'_inherited_type').val(inherited_type & ~HostMacrosManager.ZBX_PROPERTY_OWN);
					$('#macros_'+macro_num+'_description')
						.prop('readonly', true)
						.val($('#macros_'+macro_num+'_inherited_description').val())
						.trigger('input');
					$('#macros_'+macro_num+'_type_button')
						.removeClass()
						.addClass(['btn-alt', 'btn-dropdown-toggle', dropdown_btn_classes[macro_type]].join(' '))
						.prop('disabled', true)
						.attr({'aria-haspopup': false});
					$('input[type=hidden]', $('#macros_'+macro_num+'_type_button').parent())
						.val(macro_type)
						.trigger('change');
					$('#macros_'+macro_num+'_value')
						.prop('readonly', true)
						.val($('#macros_'+macro_num+'_inherited_value').val())
						.trigger('input');
					if (macro_type == HostMacrosManager.ZBX_MACRO_TYPE_SECRET) {
						jQuery('#macros_'+macro_num+'_value').prop('disabled', true);
					}
					$('#macros_'+macro_num+'_value')
						.closest('.macro-input-group')
						.find('.btn-undo')
						.hide();
					$('#macros_'+macro_num+'_value_btn').prop('disabled', true);
					$('#macros_'+macro_num+'_change').text(t('Change'));
				}
				else {
					$('#macros_'+macro_num+'_inherited_type').val(inherited_type | HostMacrosManager.ZBX_PROPERTY_OWN);
					$('#macros_'+macro_num+'_value')
						.prop('readonly', false)
						.focus();
					$('#macros_'+macro_num+'_value_btn').prop('disabled', false);
					$('#macros_'+macro_num+'_description').prop('readonly', false);
					$('#macros_'+macro_num+'_type_button')
						.prop('disabled', false)
						.attr({'aria-haspopup': true});
					$('#macros_'+macro_num+'_change').text(t('Remove'));
				}
			})
			.on('afteradd.dynamicRows', function() {
				$('.macro-input-group').macroValue();
			});

		this.initMacroFields($parent);
	}

	initMacroFields($parent) {
		$('.'+HostMacrosManager.ZBX_STYLE_TEXTAREA_FLEXIBLE, $parent).not('.initialized-field')
				.each((index, textarea) => {
			const $textarea = $(textarea);

			if ($textarea.hasClass('macro')) {
				$textarea.on('change keydown', (e) => {
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
		const tab = document.querySelector('#tab_macros-tab, #tab_macroTab');

		if (tab) {
			new MacrosTabIndicatorItem().initObserver(tab);
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
}
