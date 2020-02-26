<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CMacroValue extends CDiv {

	/**
	 * Container class.
	 */
	public const ZBX_STYLE_INPUT_GROUP = 'input-group';

	/**
	 * Button class for undo.
	 */
	public const ZBX_STYLE_BTN_UNDO = 'btn-undo';

	/**
	 * Options array.
	 *
	 * @var array
	 */
	protected $options = [
		'readonly' => false,
		'add_post_js' => true
	];

	/**
	 * Values array.
	 *
	 * @var array
	 */
	protected $values = [];

	/**
	 * Element name.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Class constructor.
	 *
	 * @param array  $macro    Macro array.
	 * @param string $name     Input name.
	 * @param array  $options  Options.
	 * @param bool   $options['readonly']
	 * @param bool   $options['add_post_js']
	 */
	public function __construct(array $macro, string $name, array $options = []) {
		$this->options = array_merge($this->options, $options);

		parent::__construct();

		$this->values = $macro;
		$this->name = $name;
	}

	/**
	 * Get content of all Javascript code.
	 *
	 * @return string  Javascript code.
	 */
	public function getPostJS(): string {
		return 'jQuery("#'.$this->getId().'").macroValue();';
	}

	/**
	 * Render object.
	 *
	 * @param boolean $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {

		$dropdown_options = [
			'title' => _('Change type'),
			'active_class' => ($this->values['type'] == ZBX_MACRO_TYPE_TEXT) ? ZBX_STYLE_ICON_TEXT : ZBX_STYLE_ICON_SECRET_TEXT,
			'disabled' => $this->options['readonly'],
			'items' => [
				['label' => _('Text'), 'value' => ZBX_MACRO_TYPE_TEXT, 'class' => ZBX_STYLE_ICON_TEXT],
				['label' => _('Secret text'), 'value' => ZBX_MACRO_TYPE_SECRET, 'class' => ZBX_STYLE_ICON_SECRET_TEXT]
			]
		];

		$value_input = ($this->values['type'] == ZBX_MACRO_TYPE_TEXT)
			? (new CTextAreaFlexible($this->name.'[value]', CMacrosResolverGeneral::getMacroValue($this->values),
				['add_post_js' => $this->options['add_post_js']]
			))
				->setAttribute('placeholder', _('value'))
			: new CInputSecret($this->name.'[value]', _('value'), ['disabled' => $this->options['readonly'],
				'add_post_js' => $this->options['add_post_js']
			]);

		if ($this->values['type'] == ZBX_MACRO_TYPE_TEXT && $this->options['readonly']) {
			$value_input->setAttribute('readonly', 'readonly');
		}

		// Macro value input group.
		$this
			->addClass(self::ZBX_STYLE_INPUT_GROUP)
			->setId(uniqid('macro-value-'))
			->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
			->addItem([
				$value_input,
				($this->values['type'] == ZBX_MACRO_TYPE_SECRET)
					? (new CButton(null))
						->setAttribute('title', _('Revert changes'))
						->addClass(ZBX_STYLE_BTN_ALT)
						->addClass(self::ZBX_STYLE_BTN_UNDO)
					: null,
				new CButtonDropdown($this->name.'[type]', (string) $this->values['type'], $dropdown_options)
			]);

		zbx_add_post_js($this->getPostJS());

		return parent::toString($destroy);
	}
}
