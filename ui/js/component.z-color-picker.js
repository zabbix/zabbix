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


class ZColorPicker extends HTMLElement {

	static ZBX_STYLE_CLASS =				'color-picker';
	static ZBX_STYLE_BOX =					'color-picker-box';
	static ZBX_STYLE_DIALOG =				'color-picker-dialog';
	static ZBX_STYLE_DIALOG_ID =			'color_picker';
	static ZBX_STYLE_TABS =					'color-picker-tabs';
	static ZBX_STYLE_TAB =					'color-picker-tab';
	static ZBX_STYLE_TAB_SELECTED =			'color-picker-tab-selected';
	static ZBX_STYLE_TAB_SOLID =			'color-picker-tab-solid';
	static ZBX_STYLE_TAB_PALETTE =			'color-picker-tab-palette';
	static ZBX_STYLE_CONTENTS =				'color-picker-contents';
	static ZBX_STYLE_CONTENT =				'color-picker-content';
	static ZBX_STYLE_CONTENT_SELECTED =		'color-picker-content-selected';
	static ZBX_STYLE_CONTENT_SOLID =		'color-picker-content-solid';
	static ZBX_STYLE_CONTENT_PALETTE =		'color-picker-content-palette';
	static ZBX_STYLE_COLORS =				'color-picker-colors';
	static ZBX_STYLE_COLOR =				'color-picker-color';
	static ZBX_STYLE_COLOR_SELECTED =		'color-picker-color-selected';
	static ZBX_STYLE_CONTROLS =				'color-picker-controls';
	static ZBX_STYLE_INPUT_WRAP =			'color-picker-input-wrap';
	static ZBX_STYLE_INPUT =				'color-picker-input';
	static ZBX_STYLE_PREVIEW =				'color-picker-preview';
	static ZBX_STYLE_BUTTON =				'color-picker-button';
	static ZBX_STYLE_BUTTON_APPLY =			'color-picker-button-apply';
	static ZBX_STYLE_BUTTON_DEFAULT =		'color-picker-button-default';
	static ZBX_STYLE_BUTTON_CLEAR =			'color-picker-button-clear';
	static ZBX_STYLE_DEFAULT_COLOR =		'color-picker-default-color';
	static ZBX_STYLE_NO_COLOR =				'color-picker-no-color';
	static ZBX_STYLE_IS_PALETTE =			'color-picker-is-palette';
	static ZBX_STYLE_PALETTE_ICON =			'color-picker-palette-icon';
	static ZBX_STYLE_PALETTE_ICON_PART =	'color-picker-palette-icon-part';
	static ZBX_STYLE_PALETTE_ROW =			'color-picker-palette-row';
	static ZBX_STYLE_PALETTE_INPUT =		'color-picker-palette-input';

	static SOLID_COLORS = [
		['FF0000', 'FF0080', 'BF00FF', '4000FF', '0040FF', '0080FF', '00BFFF', '00FFFF', '00FFBF', '00FF00', '80FF00', 'BFFF00', 'FFFF00', 'FFBF00', 'FF8000', 'FF4000', 'CC6600', '666699'],
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
		['5B0E0E', '440727', '250A46', '180D49', '0D113F', '062350', '002B4D', '003032', '002620', '0D2F10', '19340F', '413B0B', '7A3F0B', '7F3700', '732800', '5F1B06', '1F1311', '13191C']
	];

	static PALETTE_COLORS = [
		['F48485', 'CA767B', 'FF78D9', 'CA76BE', '8867B9', '3B97FF', '4876B1', '6DBCCD', '7AD9CC', '619F3A', '92E79A', 'BBBC39', 'FCC95A', 'F69F89', 'E37E23', '7E574D', 'B89B93', '7E7E7E'],
		['C75566', 'BF7FCB', 'B34FB7', '91497D', '7F3FA1', '8775BB', '646AD0', '68AFE4', '5188B9', '598530', '486C4E', '75CAC8', '67B08A', 'BCC239', 'A1A441', 'F0B338', 'EB9A39', 'DB6D47'],
		['DD530E', 'F64C68', 'ED5AAD', 'DB4FEE', '9E7DF7', '6A45FC', '6A71F6', '3370FF', '3290FB', '0AABF0', '05ACD1', '66A22A', '62C51B', '15BC9E', '14B86B', 'EDB007', 'F16E22', 'A19AA2'],
		['EFECFE', 'E6E1FE', 'D9D2FE', 'CFC3FE', 'C7B9FE', 'AF97FC', '9F83FB', '946CF9', '895DF8', '8247F0', '7534EF', '6D2CDD', '6724DB', '5320AC', '471B93', '411985', '2C115A', '220D45'],
		['3975EC', '4173ED', '4571ED', '4A70EE', '4E6FEE', '546DEF', '596BEF', '5D6AEF', '6268EF', '6767F0', '6C66F1', '7064F1', '7563F1', '7A62F2', '7F60F2', '845FF3', '895EF3', '8C5CF3'],
		['B8DFFF', '8ACAFF', '58B0FE', '4198FB', '3290FB', '2778F1', '196FF0', '1D64E7', '175BD9', '1A4DBC', '1848AF', '1C4797', '19418A', '19428F', '163A7E', '153675', '102A5B', '0C2045'],
		['CCE7F0', 'A9D6E5', '89C2D9', '61A5C2', '59A1BF', '468FAF', '4489A7', '2C7DA0', '2A7798', '2A6F97', '266488', '014F86', '014B7F', '01497C', '01416F', '013A63', '012A4A', '00060A'],
		['3D77EA', '557FD6', '6E88C1', '8992AB', 'A59C94', 'C5A77A', 'D1AC6F', 'E7B45D', 'FCBC4C', 'FCB54A', 'FCAA47', 'FC9F43', 'FC923F', 'FC893C', 'FD7C39', 'FC7436', 'FC6C33', 'FC6030'],
		['FEF3CD', 'FEE38F', 'FFCB52', 'FEB939', 'FEB225', 'F99B1F', 'F9920B', 'F77402', 'E36B02', 'D95408', 'BB4907', 'AF410E', '97380C', '8F350F', '81300E', '6E280C', '531E09', '451908'],
		['FFE8D1', 'FFD8B3', 'FE9F6C', 'FE7F4D', 'FE6D34', 'FE541A', 'FE480B', 'F22F03', 'E22C03', 'CC1C05', 'B81A05', 'A1180C', '93160B', '811B0E', '73180C', '65160B', '5C140A', '531209'],
		['24C56B', '45C55D', '6BC64C', '83C53E', '9BC42F', 'B4C222', 'C7BE1E', 'DDBA19', 'EAB317', 'F4A917', 'FA9D14', 'F8930D', 'F48302', 'F17501', 'EE6601', 'EC5A01', 'E84A01', 'E43403'],
		['D7F9E2', 'ACF1C7', '77E4A8', '75DBA7', '40CE85', '17C571', '15B769', '0A9F59', '099554', '09814C', '087847', '0A7044', '09623B', '095837', '084F31', '074B2F', '053320', '031C11']
	];

	static #hidden_input_template = `
		<input type="hidden">
	`;

	static #box_template = `
		<button type="button" class="${ZColorPicker.ZBX_STYLE_BOX} ${ZColorPicker.ZBX_STYLE_PREVIEW}" data-default-symbol="${t('D')}">
			<div class="${ZColorPicker.ZBX_STYLE_PALETTE_ICON}">
				<div class="${ZColorPicker.ZBX_STYLE_PALETTE_ICON_PART}"></div>
				<div class="${ZColorPicker.ZBX_STYLE_PALETTE_ICON_PART}"></div>
				<div class="${ZColorPicker.ZBX_STYLE_PALETTE_ICON_PART}"></div>
			</div>
		</button>
	`;

	static #dialog_template = `
		<div class="${ZColorPicker.ZBX_STYLE_DIALOG} ${ZBX_STYLE_OVERLAY_DIALOGUE}" id="${ZColorPicker.ZBX_STYLE_DIALOG_ID}">
			<ul class="${ZColorPicker.ZBX_STYLE_TABS}">
				<li class="${ZColorPicker.ZBX_STYLE_TAB} ${ZColorPicker.ZBX_STYLE_TAB_SOLID}" data-content="${ZColorPicker.ZBX_STYLE_CONTENT_SOLID}">
					<input type="radio" id="${ZColorPicker.ZBX_STYLE_TAB_SOLID}" name="${ZColorPicker.ZBX_STYLE_TAB}"/>
					<label for="${ZColorPicker.ZBX_STYLE_TAB_SOLID}">${t('Solid color')}</label>
				</li>
				<li class="${ZColorPicker.ZBX_STYLE_TAB} ${ZColorPicker.ZBX_STYLE_TAB_PALETTE}" data-content="${ZColorPicker.ZBX_STYLE_CONTENT_PALETTE}">
					<input type="radio" id="${ZColorPicker.ZBX_STYLE_TAB_PALETTE}" name="${ZColorPicker.ZBX_STYLE_TAB}"/>
					<label for="${ZColorPicker.ZBX_STYLE_TAB_PALETTE}">${t('Palette')}</label>
				</li>
			</ul>
			<div class="${ZColorPicker.ZBX_STYLE_CONTENTS}">
				<div class="${ZColorPicker.ZBX_STYLE_CONTENT} ${ZColorPicker.ZBX_STYLE_CONTENT_SOLID}">
					<div class="${ZColorPicker.ZBX_STYLE_COLORS}"></div>
					<div class="${ZColorPicker.ZBX_STYLE_CONTROLS}">
						<div class="${ZColorPicker.ZBX_STYLE_INPUT_WRAP}">
							<input type="text" class="${ZColorPicker.ZBX_STYLE_INPUT}" maxlength="6"/>
							<div class="${ZColorPicker.ZBX_STYLE_PREVIEW}" data-default-symbol="${t('D')}"></div>
						</div>
						<button type="button" class="${ZColorPicker.ZBX_STYLE_BUTTON} ${ZColorPicker.ZBX_STYLE_BUTTON_APPLY} ${ZBX_STYLE_BTN}" title="${t('Apply')}" aria-label="${t('Apply')}">${t('Apply')}</button>
						<button type="button" class="${ZColorPicker.ZBX_STYLE_BUTTON} ${ZColorPicker.ZBX_STYLE_BUTTON_DEFAULT} ${ZBX_STYLE_BTN_ALT}" title="${t('Use default')}" aria-label="${t('Use default')}">${t('Use default')}</button>
						<button type="button" class="${ZColorPicker.ZBX_STYLE_BUTTON} ${ZColorPicker.ZBX_STYLE_BUTTON_CLEAR} ${ZBX_STYLE_BTN_ALT}" title="${t('Clear')}" aria-label="${t('Clear')}">${t('Clear')}</button>
					</div>
				</div>
				<div class="${ZColorPicker.ZBX_STYLE_CONTENT} ${ZColorPicker.ZBX_STYLE_CONTENT_PALETTE}">
					<ul class="${ZColorPicker.ZBX_STYLE_COLORS}"></ul>
				</div>
			</div>
		</div>
	`;

	static #solid_row_template = `
		<div></div>
	`;

	static #solid_column_template = `
		<button type="button" class="${ZColorPicker.ZBX_STYLE_COLOR}" title="##{color}" data-color="#{color}" style="background: ##{color}"></button>
	`;

	static #palette_row_template = `
		<li class="${ZColorPicker.ZBX_STYLE_PALETTE_ROW}">
			<input type="radio" class="${ZBX_STYLE_CHECKBOX_RADIO}" id="${ZColorPicker.ZBX_STYLE_PALETTE_INPUT}-#{value}" name="${ZColorPicker.ZBX_STYLE_PALETTE_INPUT}" value="#{value}"/>
			<label for="${ZColorPicker.ZBX_STYLE_PALETTE_INPUT}-#{value}">
				<span></span>
				<div></div>
			</label>
		</li>
	`;

	static #palette_column_template = `
		<div style="background: ##{color}"></div>
	`;

	/**
	 * Elements of color picker.
	 */
	#box;
	#hidden_input;
	#dialog;
	#input;
	#preview;

	/**
	 * Attributes of color picker.
	 */
	#color_field_name;
	#palette_field_name;
	#color;
	#palette;
	#has_default;
	#has_palette;
	#disabled;
	#readonly;
	#allow_empty;

	#events;

	#is_connected = false;
	#is_dialog_open = false;

	constructor() {
		super();
	}

	connectedCallback() {
		if (!this.#is_connected) {
			this.#is_connected = true;

			this.classList.add(ZColorPicker.ZBX_STYLE_CLASS);

			this.#color_field_name = this.hasAttribute('color-field-name') ? this.getAttribute('color-field-name') : '';
			this.#palette_field_name = this.hasAttribute('palette-field-name')
				? this.getAttribute('palette-field-name') : '';
			this.#color = this.hasAttribute('color')
				? encodeURIComponent(this.getAttribute('color').toUpperCase()) : null;
			this.#palette = this.hasAttribute('palette') ? Number(this.getAttribute('palette')) : null;
			this.#has_default = this.hasAttribute('has-default');
			this.#has_palette = this.hasAttribute('palette-field-name');
			this.#disabled = this.hasAttribute('disabled');
			this.#readonly = this.hasAttribute('readonly');
			this.#allow_empty = this.hasAttribute('allow-empty') || this.#has_default;

			if (this.#color === null && this.#palette === null) {
				this.#color = '';
			}

			this.#hidden_input = new Template(ZColorPicker.#hidden_input_template).evaluateToElement();
			this.#box = new Template(ZColorPicker.#box_template).evaluateToElement();

			this.appendChild(this.#hidden_input);
			this.appendChild(this.#box);

			this.#dialog = this.#createDialog();
			this.#input = this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_INPUT}`);
			this.#preview = this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_PREVIEW}`);
		}

		this.#refresh();
		this.#registerEvents();
	}

	disconnectedCallback() {
		this.#unregisterEvents();
	}

	static get observedAttributes() {
		return ['color-field-name', 'palette-field-name', 'color', 'palette', 'has-default', 'disabled', 'readonly',
			'allow-empty'
		];
	}

	attributeChangedCallback(name, old_value, new_value) {
		if (!this.#is_connected) {
			return;
		}

		if (this.#is_dialog_open) {
			this.#closeDialog();
		}

		switch (name) {
			case 'color-field-name':
				this.#color_field_name = new_value;
				break;

			case 'palette-field-name':
				this.#palette_field_name = new_value;
				this.#has_palette = new_value !== null;
				break;

			case 'color':
				this.#color = new_value !== null ? encodeURIComponent(new_value.toUpperCase()) : null;
				break;

			case 'palette':
				this.#palette = new_value !== null ? Number(new_value) : null;
				break;

			case 'has-default':
				this.#has_default = new_value !== null;
				break;

			case 'disabled':
				this.#disabled = new_value !== null;
				break;

			case 'readonly':
				this.#readonly = new_value !== null;
				break;

			case 'allow-empty':
				this.#allow_empty = new_value !== null || this.#has_default;
				break;

			default:
				return;
		}

		this.#refresh();
	}

	#refresh() {
		const name = this.#palette !== null ? this.#palette_field_name : this.#color_field_name;
		const value = this.#palette !== null ? this.#palette : this.#color;

		this.#hidden_input.name = name;
		this.#hidden_input.value = value;

		this.#box.id = `lbl_${name.replaceAll('[', '_').replaceAll(']', '')}`;
		this.#box.title = this.#getTitle();
		this.#box.classList.toggle(ZColorPicker.ZBX_STYLE_IS_PALETTE, this.#palette !== null);
		this.#box.classList.remove(ZColorPicker.ZBX_STYLE_DEFAULT_COLOR, ZColorPicker.ZBX_STYLE_NO_COLOR);

		if (this.#color === '') {
			this.#box.classList.add(this.#has_default
				? ZColorPicker.ZBX_STYLE_DEFAULT_COLOR
				: ZColorPicker.ZBX_STYLE_NO_COLOR
			);
		}

		if (isColorHex(`#${this.#color}`)) {
			this.#box.style.background = `#${this.#color}`;
		}
		else if (this.#color === '' || this.#color === null) {
			this.#box.style.background = '';
		}

		if (this.#palette !== null) {
			const icon_parts = this.#box.querySelectorAll(`.${ZColorPicker.ZBX_STYLE_PALETTE_ICON_PART}`);

			icon_parts[0].style.setProperty('--color', `#${ZColorPicker.PALETTE_COLORS[this.#palette][0]}`);
			icon_parts[1].style.setProperty('--color', `#${ZColorPicker.PALETTE_COLORS[this.#palette][8]}`);
			icon_parts[2].style.setProperty('--color', `#${ZColorPicker.PALETTE_COLORS[this.#palette][17]}`);
		}

		this.#hidden_input.toggleAttribute('readonly', this.#readonly);
		this.#hidden_input.toggleAttribute('disabled', this.#disabled);
		this.#box.toggleAttribute('disabled', this.#disabled);
	}

	#registerEvents() {
		this.#events = {
			dialogClick: e => {
				const tab = e.target.closest(`.${ZColorPicker.ZBX_STYLE_TAB}`);

				if (tab !== null) {
					e.preventDefault();

					this.#selectTab(tab);

					return;
				}

				const selected_tab = this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_TAB_SELECTED}`);

				if (selected_tab.classList.contains(ZColorPicker.ZBX_STYLE_TAB_SOLID)) {
					const color = e.target.closest(`.${ZColorPicker.ZBX_STYLE_COLOR}`);
					const button = e.target.closest(`.${ZColorPicker.ZBX_STYLE_BUTTON}`);

					if (color !== null) {
						this.#selectColor(color.dataset.color);
					}
					else if (button !== null) {
						this.#selectButton(button);
					}
				}
				else if (selected_tab.classList.contains(ZColorPicker.ZBX_STYLE_TAB_PALETTE)) {
					const palette = e.target.closest(`.${ZColorPicker.ZBX_STYLE_PALETTE_ROW}`);

					if (palette !== null && e.detail !== 0) {
						this.#selectPalette(palette);
					}
				}
			},

			dialogKeydown: e => {
				switch (e.key) {
					case 'Enter':
						const selected_tab = this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_TAB_SELECTED}`);

						if (selected_tab.classList.contains(ZColorPicker.ZBX_STYLE_TAB_SOLID)) {
							const color = e.target.closest(`.${ZColorPicker.ZBX_STYLE_COLOR}`);

							if (color !== null) {
								e.preventDefault();

								this.#selectColor(color.dataset.color);
							}
							else if (e.target.closest(`.${ZColorPicker.ZBX_STYLE_INPUT}`) === this.#input) {
								e.preventDefault();

								this.#selectColor(this.#input.value);
							}
						}
						else if (selected_tab.classList.contains(ZColorPicker.ZBX_STYLE_TAB_PALETTE)) {
							const palette = e.target.closest(`.${ZColorPicker.ZBX_STYLE_PALETTE_ROW}`);

							if (palette !== null) {
								e.preventDefault();

								this.#selectPalette(palette);
							}
						}

						break;

					case 'Tab':
						const focusables = Array.from(
							this.#dialog.querySelectorAll(`
								input:not([tabindex^="-"]):not([disabled]),
								button:not([tabindex^="-"]):not([disabled]),
								[tabindex]:not([tabindex^="-"]):not([disabled])
							`)
						// Take only visible elements.
						).filter(element => element.offsetParent !== null);

						const first_focusable = focusables[0];
						const last_focusable = focusables[focusables.length - 1];

						// shift + tab
						if (e.shiftKey) {
							if (document.activeElement === first_focusable) {
								e.preventDefault();

								last_focusable.focus();
							}
						}
						// tab
						else {
							if (document.activeElement === last_focusable) {
								e.preventDefault();

								first_focusable.focus();
							}
						}

						break;
				}
			},

			dialogKeyup: e => {
				switch (e.key) {
					case 'ArrowDown':
					case 'ArrowLeft':
					case 'ArrowRight':
					case 'ArrowUp':
						if (e.target.name === ZColorPicker.ZBX_STYLE_PALETTE_INPUT) {
							this.#dialog.querySelectorAll(`input[name="${ZColorPicker.ZBX_STYLE_PALETTE_INPUT}"]`)
								.forEach(input => input.setAttribute('tabindex', '-1'));

							e.target.setAttribute('tabindex', '0');
						}

						break;
				}
			},

			inputChange: e => {
				this.#color = encodeURIComponent(e.target.value.toUpperCase());

				this.#updatePreview();
				this.#updateHighlight();
				this.#updateApplyButton();
			},

			documentClick: e => {
				const box = e.target.closest(`.${ZColorPicker.ZBX_STYLE_BOX}`);

				if (box !== null) {
					if (box === this.#box) {
						if (this.#is_dialog_open) {
							this.#closeDialog();
						}
						else if (!this.#readonly) {
							this.#openDialog();
						}
					}
					else {
						if (this.#is_dialog_open) {
							this.#closeDialog();
						}
					}
				}
			},

			documentKeydown: e => {
				if (e.key === 'Escape') {
					e.stopPropagation();

					this.#closeDialog();
				}
			},

			documentMousedown: e => {
				if (e.target.closest(`.${ZColorPicker.ZBX_STYLE_BOX}`) === null
						&& e.target.closest(`.${ZColorPicker.ZBX_STYLE_DIALOG}`) === null && this.#is_dialog_open) {
					this.#closeDialog();
				}
			},

			windowResize: () => {
				this.#closeDialog();
			},

			windowScroll: () => {
				this.#closeDialog();
			}
		};

		document.addEventListener('click', this.#events.documentClick);
	}

	#unregisterEvents() {
		document.removeEventListener('click', this.#events.documentClick);
	}

	#createDialog() {
		const dialog = new Template(ZColorPicker.#dialog_template).evaluateToElement();

		const solid_row_template = new Template(ZColorPicker.#solid_row_template);
		const solid_column_template = new Template(ZColorPicker.#solid_column_template);

		for (const row of ZColorPicker.SOLID_COLORS) {
			let columns_html = '';

			for (const color of row) {
				columns_html += solid_column_template.evaluate({color});
			}

			const row_element = solid_row_template.evaluateToElement();

			row_element.innerHTML = columns_html;

			dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_CONTENT_SOLID} .${ZColorPicker.ZBX_STYLE_COLORS}`)
				.appendChild(row_element);
		}

		const palette_row_template = new Template(ZColorPicker.#palette_row_template);
		const palette_column_template = new Template(ZColorPicker.#palette_column_template);

		for (let i = 0; i < ZColorPicker.PALETTE_COLORS.length; i++) {
			let columns_html = '';

			for (const color of ZColorPicker.PALETTE_COLORS[i]) {
				columns_html += palette_column_template.evaluate({color});
			}

			const row_element = palette_row_template.evaluateToElement({value: i});

			row_element.querySelector('div').innerHTML = columns_html;

			dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_CONTENT_PALETTE} .${ZColorPicker.ZBX_STYLE_COLORS}`)
				.appendChild(row_element);
		}

		return dialog;
	}

	#openDialog() {
		document.body.appendChild(this.#dialog);

		this.#is_dialog_open = true;

		this.#updatePreview();
		this.#updateHighlight();
		this.#updateApplyButton();

		this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_TABS}`)
			.style.display = this.#has_palette ? '' : 'none';

		this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_BUTTON_DEFAULT}`)
			.style.display = this.#has_default ? '' : 'none';
		this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_BUTTON_CLEAR}`)
			.style.display = !this.#has_default && this.#allow_empty ? '' : 'none';

		this.#dialog.querySelectorAll(`input[name="${ZColorPicker.ZBX_STYLE_PALETTE_INPUT}"]`)
			.forEach(input => {
				input.checked = false;
				input.setAttribute('tabindex', '-1');
			});

		if (this.#palette !== null) {
			const tab = this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_TAB_PALETTE}`);

			if (tab !== null) {
				this.#selectTab(tab);
			}

			this.#input.value = '';

			const input = this.#dialog.querySelector(
				`input[name="${ZColorPicker.ZBX_STYLE_PALETTE_INPUT}"][value="${this.#palette}"]`
			);

			if (input !== null) {
				input.checked = true;
				input.setAttribute('tabindex', '0');
				input.focus();
			}
		}
		else if (isColorHex(`#${this.#color}`) || this.#color === '' || this.#color === null) {
			const tab = this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_TAB_SOLID}`);

			if (tab !== null) {
				this.#selectTab(tab);
			}

			this.#input.value = this.#color || '';

			this.#input.focus();
		}

		if (this.#dialog.querySelector(`input[name="${ZColorPicker.ZBX_STYLE_PALETTE_INPUT}"]:checked`) === null) {
			this.#dialog.querySelector(`input[name="${ZColorPicker.ZBX_STYLE_PALETTE_INPUT}"]`)
				?.setAttribute('tabindex', '0');
		}

		this.#positionDialog();

		this.#dialog.addEventListener('keydown', this.#events.dialogKeydown);
		this.#dialog.addEventListener('keyup', this.#events.dialogKeyup);
		this.#dialog.addEventListener('click', this.#events.dialogClick);

		this.#input.addEventListener('input', this.#events.inputChange);

		document.addEventListener('keydown', this.#events.documentKeydown, {capture: true});
		document.addEventListener('mousedown', this.#events.documentMousedown);

		addEventListener('resize', this.#events.windowResize);
		addEventListener('scroll', this.#events.windowScroll, {capture: true});
	}

	/**
	 * Close color dialog.
	 *
	 * @param {boolean} save_changes  Whether to save or discard changes made in color dialog.
	 */
	#closeDialog(save_changes = false) {
		if (!this.#is_dialog_open) {
			return;
		}

		if (!save_changes) {
			if (this.#palette !== null) {
				this.#palette = this.palette;
				this.#color = null;
			}
			else {
				this.#color = this.color;
				this.#palette = null;
			}
		}

		this.#dialog.removeEventListener('keydown', this.#events.dialogKeydown);
		this.#dialog.removeEventListener('keyup', this.#events.dialogKeyup);
		this.#dialog.removeEventListener('click', this.#events.dialogClick);

		this.#input.removeEventListener('input', this.#events.inputChange);

		document.removeEventListener('keydown', this.#events.documentKeydown, {capture: true});
		document.removeEventListener('mousedown', this.#events.documentMousedown);

		removeEventListener('resize', this.#events.windowResize);
		removeEventListener('scroll', this.#events.windowScroll, {capture: true});

		this.#dialog.remove();

		this.#is_dialog_open = false;

		if (document.activeElement === document.body) {
			// Focus only if other focusable element in document was not clicked.
			this.#box.focus();
		}
	}

	#positionDialog() {
		const wrapper_rect = document.querySelector('.wrapper').getBoundingClientRect();
		const box_rect = this.#box.getBoundingClientRect();
		const dialog_rect = this.#dialog.getBoundingClientRect();
		const dialog_max_width = this.#has_palette ? 402 : 382;
		const dialog_max_height = this.#has_palette ? 344 : 303;

		const space_right = wrapper_rect.width + wrapper_rect.left - box_rect.left - box_rect.width;
		const space_left = box_rect.left - wrapper_rect.left;
		const space_below = wrapper_rect.height - box_rect.top - box_rect.width;
		const space_above = box_rect.top;

		const fits_right = dialog_max_width <= space_right;
		const fits_left = dialog_max_width <= space_left;
		const fits_below = dialog_max_height <= space_below;
		const fits_above = dialog_max_height <= space_above;

		const pos = {
			left: null,
			right: null,
			top: null
		};

		if (fits_right) {
			pos.left = box_rect.left + box_rect.width;
		}
		else if (fits_left) {
			pos.right = wrapper_rect.width + wrapper_rect.left - box_rect.left;
		}
		else {
			pos.left = box_rect.left + box_rect.width;
		}

		if (fits_below) {
			pos.top = box_rect.top;
		}
		else if (fits_above) {
			pos.top = box_rect.top + box_rect.height - dialog_rect.height;
		}
		else {
			pos.top = box_rect.top - (dialog_max_height - space_below);
		}

		Object.entries(pos).forEach(([key, value]) => this.#dialog.style[key] = value !== null ? `${value}px` : null);
	}

	/**
	 * Update color preview inside dialog.
	 */
	#updatePreview() {
		let changed = true;

		if (isColorHex(`#${this.#color}`)) {
			this.#preview.style.background = `#${this.#color}`;
		}
		else if (this.#color === '' || this.#color === null) {
			this.#preview.style.background = '';
		}
		else {
			changed = false;
		}

		if (changed) {
			this.#preview.title = this.#getTitle(false);
			this.#preview.classList.remove(ZColorPicker.ZBX_STYLE_DEFAULT_COLOR, ZColorPicker.ZBX_STYLE_NO_COLOR);

			if (this.#color === '' || this.#color === null) {
				this.#preview.classList.add(this.#has_default
					? ZColorPicker.ZBX_STYLE_DEFAULT_COLOR
					: ZColorPicker.ZBX_STYLE_NO_COLOR
				);
			}
		}
	}

	/**
	 * Update color highlighting - mark selected color if it matches one of given colors.
	 */
	#updateHighlight() {
		this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_COLOR_SELECTED}`)
			?.classList.remove(ZColorPicker.ZBX_STYLE_COLOR_SELECTED);

		const color = this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_COLOR}[data-color="${this.#color}"]`);

		if (color !== null) {
			color.classList.add(ZColorPicker.ZBX_STYLE_COLOR_SELECTED);
		}
	}

	/**
	 * Update "Apply" button - enable or disable it based on color value.
	 */
	#updateApplyButton() {
		this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_BUTTON_APPLY}`).disabled =
			!(isColorHex(`#${this.#color}`) || ((this.#color === '' || this.#color === null) && this.#allow_empty));
	}

	/**
	 * Select tab.
	 *
	 * @param {Element} tab
	 */
	#selectTab(tab) {
		if (tab.classList.contains(ZColorPicker.ZBX_STYLE_TAB_SELECTED)) {
			return;
		}

		this.#dialog.querySelectorAll(`.${ZColorPicker.ZBX_STYLE_TAB}`)
			.forEach(t => {
				t.classList.remove(ZColorPicker.ZBX_STYLE_TAB_SELECTED);
				t.querySelector('input')?.setAttribute('tabindex', '-1');
			});

		tab.classList.add(ZColorPicker.ZBX_STYLE_TAB_SELECTED);
		tab.querySelector('input')?.setAttribute('tabindex', '0');

		this.#dialog.querySelector(`.${ZColorPicker.ZBX_STYLE_CONTENT_SELECTED}`)
			?.classList.remove(ZColorPicker.ZBX_STYLE_CONTENT_SELECTED);

		this.#dialog.querySelector(`.${tab.dataset.content}`)
			?.classList.add(ZColorPicker.ZBX_STYLE_CONTENT_SELECTED);
	}

	/**
	 * Select solid color.
	 *
	 * @param {string} color
	 */
	#selectColor(color) {
		if (isColorHex(`#${color}`) || color === '' && this.#allow_empty) {
			this.color = color;

			this.#closeDialog(true);

			this.dispatchEvent(new Event('change', {bubbles: true}));
		}
	}

	/**
	 * Select one of given buttons.
	 *
	 * @param {Element} button
	 */
	#selectButton(button) {
		let color = '';

		if (button.classList.contains(ZColorPicker.ZBX_STYLE_BUTTON_APPLY)) {
			color = this.#input.value;
		}

		this.#selectColor(color);
	}

	/**
	 * Select chosen palette.
	 *
	 * @param {Element} palette
	 */
	#selectPalette(palette) {
		this.palette = palette.querySelector('input')?.value;

		this.#closeDialog(true);

		this.dispatchEvent(new Event('change', {bubbles: true}));
	}

	/**
	 * Determine displayable title of box and preview.
	 *
	 * @param {boolean} is_palette_allowed
	 *
	 * @returns {string}
	 */
	#getTitle(is_palette_allowed = true) {
		if (is_palette_allowed && this.#palette !== null) {
			return t('Palette %1$d').replace('%1$d', (this.#palette + 1).toString());
		}
		else if (isColorHex(`#${this.#color}`)) {
			return `#${this.#color}`;
		}
		else if (this.#color === '' || this.#color === null) {
			return this.#has_default ? t('Use default') : t('No color');
		}
		else {
			return '';
		}
	}

	get colorFieldName() {
		return this.hasAttribute('color-field-name') ? this.getAttribute('color-field-name') : '';
	}

	set colorFieldName(name) {
		this.setAttribute('color-field-name', name);
	}

	get paletteFieldName() {
		return this.hasAttribute('palette-field-name') ? this.getAttribute('palette-field-name') : '';
	}

	set paletteFieldName(name) {
		this.setAttribute('palette-field-name', name);
	}

	get color() {
		return this.hasAttribute('color') ? encodeURIComponent(this.getAttribute('color').toUpperCase()) : null;
	}

	set color(color) {
		this.setAttribute('color', color);
		this.removeAttribute('palette');
	}

	get palette() {
		return this.hasAttribute('palette') ? Number(this.getAttribute('palette')) : null;
	}

	set palette(palette) {
		this.setAttribute('palette', palette);
		this.removeAttribute('color');
	}

	get hasDefault() {
		return this.hasAttribute('has-default');
	}

	set hasDefault(has_default) {
		this.toggleAttribute('has-default', has_default);
	}

	get disabled() {
		return this.hasAttribute('disabled');
	}

	set disabled(disabled) {
		this.toggleAttribute('disabled', disabled);
	}

	get readonly() {
		return this.hasAttribute('readonly');
	}

	set readonly(readonly) {
		this.toggleAttribute('readonly', readonly);
	}

	get allowEmpty() {
		return this.hasAttribute('allow-empty');
	}

	set allowEmpty(allow_empty) {
		this.toggleAttribute('allow-empty', allow_empty);
	}
}

customElements.define('z-color-picker', ZColorPicker);
