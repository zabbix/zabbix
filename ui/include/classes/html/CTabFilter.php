<?php

class CTabFilter extends CDiv {

	const ZBX_STYLE_CLASS = 'tabfilter-container';
	const CSS_TAB_ACTIVE = 'active';
	const CSS_TAB_SORTABLE_CONTAINER = 'ui-sortable';
	const CSS_ID_PREFIX = 'tabfilter_';

	/**
	 * Array of arrays for tabs data. Single element contains: tab label object, tab content object or null and tab data
	 * array.
	 *
	 * @var array $tabs
	 */
	public $tabs = [];

	/**
	 * Tab options array.
	 */
	public $options = [
		// allow to reorder tabs
		'sortable' => true,
		// allow to collapse tab
		'can_toggle' => true,
		// active/selected tab
		'active_tab' => 1,
		// tabs custom data
		'data' => [],
	];

	/**
	 * Tab form available buttons node. Will be initialized during __construct but can be overwritten if needed.
	 */
	public $buttons = null;

	/**
	 * Array of CPartial elements used as tab content rendering templates.
	 *
	 * @var array $template
	 */
	protected $templates = [];

	/**
	 * Array of CTag tab label elements.
	 */
	protected $labels = [];

	/**
	 * Array of CTag or null of tab content elements.
	 */
	protected $contents = [];

	public function __construct($items = null) {
		parent::__construct($items);

		$this
			->setId(uniqid(static::CSS_ID_PREFIX))
			->addClass(ZBX_STYLE_FILTER_CONTAINER)
			->addClass(static::ZBX_STYLE_CLASS);

		$this->buttons = (new CDiv())
			->addClass(ZBX_STYLE_FILTER_FORMS)
			->addItem(
				(new CSubmitButton(_('Save as'), 'save_as', 1))
					->addClass(ZBX_STYLE_BTN_ALT)
			)
			->addItem(
				(new CSubmitButton(_('Update'), 'filter_set', 1))
			)
			->addItem(
				(new CSubmitButton(_('Apply'), 'filter_apply', 1))
					->addClass(ZBX_STYLE_BTN_ALT)
			)
			->addItem(
				(new CSubmitButton(_('Reset'), 'filter_reset', 1))
					->addClass(ZBX_STYLE_BTN_ALT)
			)
			->addClass('form-buttons');
	}

	/**
	 * Add template for browser side rendered tabs.
	 *
	 * @param CPartial $template  Template object.
	 */
	public function addTemplate(CPartial $template) {
		$this->templates[$template->getName()] = $template;

		return $this;
	}

	/**
	 * Add tab item, is tab dynamic or no is decided by $content.
	 *
	 * @param CTag|string $label    String or CTag as tab label element. Content node if it is not null will have
	 *                              id attribute equal [data-target] attribute of $label.
	 * @param CTag|null   $content  Tab content node or null is dynamic tab is added.
	 * @param array       $data     Array of data used by tab.
	 */
	public function addTab($label, $content, array $data = []) {
		$tab_index = count($this->labels);
		$targetid = static::CSS_ID_PREFIX.$tab_index;

		if ($content !== null && method_exists($content, 'getId') && $content->getId()) {
			$targetid = $content->getId();
		}

		if (!is_a($label, CTag::class)) {
			// Temporary fix.
			$label = ($tab_index == 0) ? 'Home' : $label;

			$label = (new CLink($label))->setAttribute('data-target', $targetid);
		}

		if ($content) {
			$content->setId($targetid);
			$content->addClass('display-none');
		}

		$this->labels[] = $label;
		$this->contents[] = $content;
		$this->options['data'][] = $data;

		return $this;
	}

	/**
	 * Add tab for render on browser side using passed $data. Active (expanded) tab will be pre-rendered on server side
	 * to prevent screen flickering during page initial load.
	 *
	 * @param string|CTag $label            Tab label.
	 * @param string      $target_selector  CSS selector for tab content node to be shown/hidden.
	 * @param array       $data             Tab data associative array.
	 *
	 * @return CTabFilter
	 */
	public function addTemplatedTab($label, array $data): CTabFilter {
		return $this->addTab($label, null, $data + ['uniqid' => uniqid()]);
	}

	/**
	 * Return top navigation markup.
	 */
	protected function getNavigation() {
		$home = reset($this->labels);
		$sortable = (new CList(array_slice($this->labels, 1, -1)))->addClass(static::CSS_TAB_SORTABLE_CONTAINER);
		$timetablabel = end($this->labels);
		$nav = [
			(new CSimpleButton())
				->setAttribute('data-action', 'selectPrevTab')
				->addClass('btn-iterator-page-previous'),
			$home, $sortable, $timetablabel,
			(new CSimpleButton())
				->setAttribute('data-action', 'toggleTabsList')
				->addClass('btn-widget-expand'),
			(new CSimpleButton())
				->setAttribute('data-action', 'selectNextTab')
				->addClass('btn-iterator-page-next'),
			(new CSimpleButton())
				->setEnabled(false)
				->addClass(ZBX_STYLE_BTN_TIME_LEFT),
			(new CSimpleButton(_('Zoom out')))
				->setEnabled(false)
				->addClass(ZBX_STYLE_BTN_TIME_OUT),
			(new CSimpleButton())
				->setEnabled(false)
				->addClass(ZBX_STYLE_BTN_TIME_RIGHT)
		];

		return new CTag('nav', true , new CList($nav));
	}

	public function bodyToString() {
		$tab_active = 0;
		$is_expanded = 1;
		$this->labels[$tab_active]->addClass(static::CSS_TAB_ACTIVE);

		if ($is_expanded) {
			if ($this->contents[$tab_active] === null) {
				$tab_data = $this->options['data'][$tab_active];
				$tab_data['render_html'] = true;
				$this->contents[$tab_active] = (new CForm('get'))
					->addItem(new CPartial($tab_data['template'], $tab_data))
					->setId($this->labels[$tab_active]->getAttribute('data-target'));
			}
		}

		foreach ($this->contents as $index => $content) {
			if (is_a($content, CTag::class)) {
				$content->addClass($index == $tab_active ? null : 'display-none');
			}
		}

		$templates = '';

		foreach ($this->templates as $template) {
			$templates .= $template->getOutput();
		}

		return implode('', [
			$this->getNavigation(),
			(new CDiv($this->contents))->addClass('tabfilter-tabs-container'),
			$this->buttons,
			$templates,
		]);
	}
}
