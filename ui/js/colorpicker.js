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


const ZBX_TEXTAREA_COLOR_WIDTH = 96;

(function($) {
	var overlay,
		colorbox,
		input,
		$overlay_input,
		$overlay_colorbox,
		$button_use_default,
		defaults = {
			palette: [
				['FF0000', 'FF0080', 'BF00FF', '4000FF', '0040FF', '0080FF', '00BFFF', '00FFFF', '00FFBF', '00FF00', '80FF00', 'BFFF00', 'FFFF00', 'FFBF00', 'FF8000', 'FF4000', 'CC6600', '666699'],
				['000000', '0F0F0F', '1E1E1E', '2D2D2D', '3C3C3C', '4B4B4B', '5A5A5A', '696969', '787878', '878787', '969696', 'A5A5A5', 'B4B4B4', 'C3C3C3', 'D2D2D2', 'E1E1E1', 'F0F0F0', 'FFFFFF'],
				['FFEBEE', 'FCE4EC', 'F3E5F5', 'EDE7F6', 'E8EAF6', 'E3F2FD', 'E1F5FE', 'E0F7FA', 'E0F2F1', 'E8F5E9', 'F1F8E9', 'F9FBE7', 'FFFDE7', 'FFF8E1', 'FFF3E0', 'FBE9E7', 'EFEBE9', 'ECEFF1'],
				['FFCDD2', 'F8BBD0', 'E1BEE7', 'D1C4E9', 'C5CAE9', 'BBDEFB', 'B3E5FC', 'B2EBF2', 'B2DFDB', 'C8E6C9', 'DCEDC8', 'F0F4C3', 'FFF9C4', 'FFECB3', 'FFE0B2', 'FFCCBC', 'D7CCC8', 'CFD8DC'],
				['EF9A9A', 'F48FB1', 'CE93D8', 'B39DDB', '9FA8DA', '90CAF9', '81D4FA', '80DEEA', '80CBC4', 'A5D6A7', 'C5E1A5', 'E6EE9C', 'FFF59D', 'FFE082', 'FFCC80', 'FFAB91', 'BCAAA4', 'B0BEC5'],
				['E57373', 'F06292', 'BA68C8', '9575CD', '7986CB', '64B5F6', '4FC3F7', '4DD0E1', '4DB6AC', '81C784', 'AED581', 'DCE775', 'FFF176', 'FFD54F', 'FFB74D', 'FF8A65', 'A1887F', '90A4AE'],
				['EF5350', 'EC407A', 'AB47BC', '7E57C2', '5C6BC0', '42A5F5', '29B6F6', '26C6DA', '26A69A', '66BB6A', '9CCC65', 'D4E157', 'FFEE58', 'FFCA28', 'FFA726', 'FF7043', '8D6E63', '78909C'],
				['F44336', 'E91E63', '9C27B0', '673AB7', '3F51B5', '2196F3', '03A9F4', '00BCD4', '009688', '4CAF50', '8BC34A', 'CDDC39', 'FFEB3B', 'FFC107', 'FF9800', 'FF5722', '795548', '607D8B'],
				['E53935', 'D81B60', '8E24AA', '5E35B1', '3949AB', '1E88E5', '039BE5', '00ACC1', '00897B', '43A047', '7CB342', 'C0CA33', 'FDD835', 'FFB300', 'FB8C00', 'F4511E', '6D4C41', '546E7A'],
				['D32F2F', 'C2185B', '7B1FA2', '512DA8', '303F9F', '1976D2', '0288D1', '0097A7', '00796B', '388E3C', '689F38', 'AFB42B', 'FBC02D', 'FFA000', 'F57C00', 'E64A19', '5D4037', '455A64'],
				['C62828', 'AD1457', '6A1B9A', '4527A0', '283593', '1565C0', '0277BD', '00838F', '00695C', '2E7D32', '558B2F', '9E9D24', 'F9A825', 'FF8F00', 'EF6C00', 'D84315', '4E342E', '37474F'],
				['B71C1C', '880E4F', '4A148C', '311B92', '1A237E', '0D47A1', '01579B', '006064', '004D40', '1B5E20', '33691E', '827717', 'F57F17', 'FF6F00', 'E65100', 'BF360C', '3E2723', '263238'],
				['891515', '660A3B', '370F69', '24146D', '131A5E', '093578', '044174', '00484B', '003930', '144618', '264E16', '615911', 'B75F11', 'BF5300', 'AC3C00', '8F2809', '2E1D1A', '1C252A'],
				['5B0E0E', '440727', '250A46', '180D49', '0D113F', '062350', '002B4D', '003032', '002620', '0D2F10', '19340F', '413B0B', '7A3F0B', '7F3700', '732800', '5F1B06', '1F1311', '13191C'],
				['2D0707', '220313', '120523', '0C0624', '06081F', '031128', '001526', '001819', '00131D', '061708', '0C1A07', '201D05', '3D1F05', '3F1B00', '391400', '2F0D03', '0F0908', '090C0E'],
			],
			appendTo: 'body',
			use_default: false,
			onUpdate: null
		},
		/**
		 * Click handler for every colorpicker cell.
		 *
		 * @param {string} color
		 */
		setColorHandler = function (color) {
			methods.set_color(color);
			input.trigger('change');
			methods.hide();
		},

		/**
		 * Set color to the overlay input colorbox.
		 *
		 * @param {string} color
		 */
		setPreviewColor = function (color) {
			color = $.trim(color).toUpperCase();

			if (input.data('use_default') && color.length == 0) {
				$overlay_colorbox
					.css({'background': ''})
					.attr('title', t('Use default'))
					.addClass('use-default');
			}
			else if (/^[0-9A-F]{6}$/i.test(color)) {
				$overlay_colorbox
					.css({'background': '#' + color})
					.attr('title', '#' + color)
					.removeClass('use-default');
			}
			else {
				$overlay_colorbox
					.css({'background': ''})
					.attr('title', t('Use default'))
					.removeClass('use-default');
			}
		},

		/**
		 * Calculates top and left position for colorpicker overlay element.
		 */
		getOverlayPosition = function(id) {
			var colorbox = $('#lbl_' + id),
				pos = colorbox.offset(),
				dialog = colorbox.closest('.overlay-dialogue'),
				overlay = $('#color_picker'),
				min_outline = 10,
				frame_dims = {
					top: 0,
					left: 0,
					bottom: window.screen.height,
					right: window.screen.width
				},
				left = pos.left + colorbox.outerWidth(),
				top = pos.top;

			// If colorpicker is located in dialog, use dialog as a frame.
			if (overlay.parents('.overlay-dialogue').length) {
				frame_dims.left = dialog.offset().left;
				frame_dims.top = dialog.offset().top;
				frame_dims.bottom = dialog.outerHeight() + frame_dims.top;
				frame_dims.right = dialog.outerWidth() + frame_dims.left;
			}

			// Make sure that overlay is inside frame.
			if (top + overlay.outerHeight() + min_outline > frame_dims.bottom) {
				top = frame_dims.bottom - overlay.outerHeight() - min_outline;
			}

			if (left + overlay.outerWidth() + min_outline > frame_dims.right) {
				left = frame_dims.right - overlay.outerWidth() - min_outline;
			}

			return {
				top: top - frame_dims.top,
				left: left - frame_dims.left
			};
		},
		methods = {
			/**
			 * Initialization of colorpicker overlay.
			 *
			 * @param object         options
			 * @param array          options.palette   Every nested array contains hex color for one cell.
			 * @param string|object  options.appendTo  Target element where overlay should be appended.
			 */
			init: function(options) {

				$overlay_input = $('<input>', {
					type: 'text',
					autofocus: 'autofocus',
					maxlength: 6
				})
					.css('width', ZBX_TEXTAREA_COLOR_WIDTH + 'px')
					.on('input keydown paste', (e) => {
						const color = e.target.value;

						if (color.length == 0 || color.length == 6) {
							setPreviewColor(color);
						}
					})
					.on('keypress', (e) => {
						const color = e.target.value;

						if (e.keyCode == KEY_ENTER && (color.length == 0 || color.length == 6)) {
							setColorHandler(e.target.value);
						}
					});

				const $close = $('<button>', {
					type: 'button',
					class: 'overlay-close-btn',
					title: t('S_CLOSE')
				})
					.on('click', (e) => {
						let color = $overlay_input.val();
						if (color.length == 0 || color.length == 6) {
							setColorHandler(color);
						}
						else {
							methods.hide();
						}
					});

				$overlay_colorbox = $('<div>', {
					class: 'color-picker-preview',
					'data-use-default': t('D')
				});

				$button_use_default = $('<button>', {
					type: 'button',
					class: 'btn-alt',
					title: t('Use default'),
					'aria-label': t('Use default')
				})
					.html(t('Use default'))
					.on('click', () => setColorHandler(''));

				overlay = $('<div>', {
					class: 'overlay-dialogue color-picker-dialogue',
					id: 'color_picker'
				})
					.append($close)
					.append($('<div>').append([
						$('<div>', {class: 'color-picker-input'}).append([$overlay_colorbox, $overlay_input]),
						$button_use_default
					]))
					.append(
						$.map(options.palette, (colors) => {
							return $('<div>').append(
								$.map(colors, (color) => {
									return $('<button>', {'title': '#' + color, type: 'button', 'data-color': color})
										.css('background', '#' + color)
								})
							);
						})
					)
					.on('click', '[data-color]', function () {
						setColorHandler($(this).data('color'));
					});

				overlay.appendTo($(options.appendTo));

				methods.hide();
			},
			/**
			 * Hide colorpicker overlay.
			 */
			hide: function() {
				colorbox = null;
				input = null;

				overlay.css({
					'zIndex': 1000,
					'display': 'none',
					'left': '-' + (overlay.width() ? overlay.width() : 100) + 'px'
				});

				removeFromOverlaysStack('color_picker');
			},
			/**
			 * Show colorpicker for specific element.
			 *
			 * @param string id      Id of input element which will be associated with opened colorpicker instance.
			 * @param object target  jQuery element (colorbox) which triggered show action.
			 */
			show: function(id, target) {
				input = $('#' + id);
				colorbox = $('#lbl_' + id);

				if (input.is(':disabled,[readonly]')) {
					return;
				}

				const pos = getOverlayPosition(id);

				overlay.css({
					'left': pos.left + 'px',
					'top': pos.top + 'px',
					'display': 'block'
				});

				if (input.data('use_default')) {
					$button_use_default.show();
				}
				else {
					$button_use_default.hide();
				}

				$overlay_input.val(input.val());

				setPreviewColor(input.val());

				addToOverlaysStack('color_picker', target, 'color_picker');

				Overlay.prototype.recoverFocus.call({'$dialogue': overlay});
				Overlay.prototype.containFocus.call({'$dialogue': overlay});
			},
			/**
			 * Set color as background color value of colorbox and as value for input.
			 *
			 * @param string color    Desired hex color.
			 */
			set_color: function(color) {
				color = $.trim(color).toUpperCase();

				if (input.data('use_default') && color.length == 0) {
					colorbox
						.css({'background': ''})
						.attr('title', t('Use default'))
						.addClass('use-default');
				}
				else if (/^[0-9A-F]{6}$/i.test(color)) {
					colorbox
						.css({'background': '#' + color})
						.attr('title', '#' + color)
						.removeClass('use-default');
				}
				else {
					colorbox
						.css({'background': ''})
						.attr('title', t('Use default'))
						.removeClass('use-default');
				}

				input.val(color);
			},
			/**
			 * Set desired color to input element and colorbox associated with input element.
			 *
			 * @param string id       Id of input element.
			 * @param string color    Hex color value.
			 */
			set_color_by_id: function(id, color) {
				colorbox = $('#lbl_' + id);
				input = $('#' + id);
				methods.set_color(color);
			}
		};

	$.colorpicker = function(method) {
		if (methods[method]) {
			if (!overlay) {
				methods.init();
			}

			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}
		else if (typeof method === 'object' || !method) {
			return methods.init.apply(this, arguments);
		}
		else {
			$.error('Invalid method "' +  method + '".');
		}
	}

	$.fn.colorpicker = function(options) {
		options = $.extend({}, defaults, options || {});

		/**
		 * Initialize colorpicker overlay if it is not initialized.
		 */
		if (!overlay || !$('#color_picker').length) {
			methods.init(options);
		}

		/*
		 * Initialization of each separate colorpicker element.
		 *
		 * @param {object}    options
		 * @param {bool}      options.use_default  Target element where overlay should be appended.
		 * @param {callable}  options.onUpdate     Callback function to execute once color has changed.
		 */
		return this.each(function (_, element) {
			const id = $(element).attr('id');

			if ($('#lbl_' + id).length) {
				return;
			}

			$('<button>', {
				id: 'lbl_' + id,
				type: 'button',
				class: 'color-picker-preview',
				title: element.value ? '#' + element.value : '',
				'data-use-default': t('D')
			})
				.on('keydown', function (e) {
					if (e.keyCode == KEY_ENTER || e.keyCode == KEY_SPACE) {
						e.preventDefault();
						methods.show(id, e.target);
					}
				})
				.on('click', function (e) {
					methods.show(id, e.target);
				})
				.insertAfter(element);

			$(element)
				.data('use_default', options.use_default)
				.change(function () {
					methods.set_color_by_id($(element).attr('id'), this.value);
					if (options.onUpdate !== null) {
						options.onUpdate.call(element, this.value);
					}
				});

			methods.set_color_by_id(id, element.value);
		});
	}
})(jQuery);
