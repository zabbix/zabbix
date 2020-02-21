<?php

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

		$readonly = $this->options['readonly'];
		$add_post_js = $this->options['add_post_js'];

		parent::__construct();

		$dropdown_options = [
			'title' => _('Change type'),
			'active_class' => ($macro['type'] == ZBX_MACRO_TYPE_TEXT) ? ZBX_STYLE_ICON_TEXT : ZBX_STYLE_ICON_SECRET_TEXT,
			'disabled' => $readonly,
			'items' => [
				['label' => _('Text'), 'value' => ZBX_MACRO_TYPE_TEXT, 'class' => ZBX_STYLE_ICON_TEXT],
				['label' => _('Secret text'), 'value' => ZBX_MACRO_TYPE_SECRET, 'class' => ZBX_STYLE_ICON_SECRET_TEXT]
			]
		];

		$value_input = ($macro['type'] == ZBX_MACRO_TYPE_TEXT)
			? (new CTextAreaFlexible($name.'[value]', CMacrosResolverGeneral::getMacroValue($macro),
				['add_post_js' => $add_post_js]
			))
				->setAttribute('placeholder', _('value'))
			: (new CInputSecret($name.'[value]', ZBX_MACRO_SECRET_MASK, _('value'), [
				'disabled' => $readonly,
				'add_post_js' => $add_post_js
			]));

		if ($macro['type'] == ZBX_MACRO_TYPE_TEXT && $readonly) {
			$value_input->setAttribute('readonly', 'readonly');
		}

		// Macro value input group.
		$this
			->addClass(self::ZBX_STYLE_INPUT_GROUP)
			->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
			->addItem([
				$value_input,
				($macro['type'] == ZBX_MACRO_TYPE_SECRET)
					? (new CButton(null))
						->setAttribute('title', _('Revert changes'))
						->addClass(ZBX_STYLE_BTN_ALT.' '.self::ZBX_STYLE_BTN_UNDO)
					: null,
				new CButtonDropdown($name.'[type]', $macro['type'], $dropdown_options)
			]);
	}
}
