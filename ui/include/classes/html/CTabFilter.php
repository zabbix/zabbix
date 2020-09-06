<?php

class CTabFilter extends CDiv {

	const ZBX_STYLE_CLASS = 'tabfilter-container';
	const CSS_TAB_SELECTED = 'selected';
	const CSS_TAB_EXPANDED = 'expanded';
	const CSS_TAB_SORTABLE_CONTAINER = 'ui-sortable-container';
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
		'idx' => '',
		'can_toggle' => true,
		'selected' => 0,
		'expanded' => false,
		'support_custom_time' => true,
		'data' => [],
		'src_url' => ''
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
				(new CSubmitButton(_('Save as'), 'filter_new', 1))
					->addClass(ZBX_STYLE_BTN_ALT)
			)
			->addItem(
				(new CSubmitButton(_('Update'), 'filter_update', 1))
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
	 * Set tabfilter options used by javascript.
	 *
	 * @param array $options  Array of options.
	 */
	public function setOptions(array $options) {
		$this->options = $options;

		return $this;
	}

	/**
	 * Set selected tab uses zero based index for selected tab.
	 *
	 * @param int $value  Index of selected tab.
	 */
	public function setSelected(int $value) {
		$this->options['selected'] = $value;

		return $this;
	}

	/**
	 * Update expanded or collapsed state of selected tab.
	 *
	 * @param bool $value  Expanded when true, collapsed otherwise.
	 */
	public function setExpanded(bool $value) {
		$this->options['expanded'] = $value;

		return $this;
	}

	/**
	 * Set status of custom time range support, when disabled will not allow to set customt time range for all
	 * tab filter tabs.
	 *
	 * @param bool $value  Supported when true.
	 */
	public function setSupportCustomTime(bool $value) {
		$this->options['support_custom_time'] = $value;

		return $this;
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
	 * Set idx namespace used by tab filter.
	 *
	 * @param string $idx  Idx string without ending dot.
	 */
	public function setIdx(string $idx) {
		$this->options['idx'] = $idx;

		return $this;
	}

	/**
	 * Add tab item, is tab dynamic or no is decided by $content.
	 *
	 * @param CTag|string $label    String or CTag as tab label element. Content node if it is not null will have
	 *                              id attribute equal [data-target] attribute of $label.
	 * @param CTag|null   $content  Tab content node or null is dynamic tab is added.
	 * @param array       $data     Array of data used by tab and additional flags:
	 *                              bool 'filter_sortable'      tab will be sortable in frontend.
	 *                              bool 'filter_configurable'  tab will have gear icon when activated.
	 */
	public function addTab($label, $content, array $data = []) {
		$tab_index = count($this->labels);
		$targetid = static::CSS_ID_PREFIX.$tab_index;

		if ($content !== null && method_exists($content, 'getId') && $content->getId()) {
			$targetid = $content->getId();
		}

		if (!is_a($label, CTag::class)) {
			if ($tab_index == 0) {
				$label = (new CLink(''))->addClass('icon-home');
				$data += [
					'filter_sortable' => false,
					'filter_configurable' => false
				];
			}
			else {
				$label = new CLink($label);
			}
		}

		if ($content) {
			$content->setId($targetid);
		}

		$this->labels[] = (new CListItem($label->addClass('tabfilter-item-link')))
			->setAttribute('data-target', $targetid)
			->addClass('tabfilter-item-label');
		$this->contents[] = $content;
		$this->options['data'][] = $data + [
			'filter_sortable' => true,
			'filter_configurable' => true
		];

		return $this;
	}

	/**
	 * Add time range selector tab. Time range selcetor have static [data-target] equal 'tabfilter_timeselector'.
	 *
	 * @param array  $timerange    Time range data array, array keys: from, to, idx, idx2
	 */
	public function addTimeselector(array $timerange) {
		$timerange += [
			'format' => ZBX_FULL_DATE_TIME,
			'from' => 'now-'.CSettingsHelper::get(CSettingsHelper::PERIOD_DEFAULT),
			'to' => 'now',
			'disabled' => false
		];
		$data = $timerange + [
			'label' => relativeDateToText($timerange['from'], $timerange['to'])
		];

		$content = (new CDiv(new CPartial('timeselector.filter', $data)))->setId(static::CSS_ID_PREFIX.'timeselector');
		$this
			->addClass('filter-space')
			->setAttribute('data-disable-initial-check', 1)
			->setAttribute('data-accessible', 1)
			->setAttribute('data-profile-idx', $timerange['idx'])
			->setAttribute('data-profile-idx2', $timerange['idx2']);
		$link = (new CLink($data['label']))
			->addClass(ZBX_STYLE_BTN_TIME)
			->addClass($timerange['disabled'] ? ZBX_STYLE_DISABLED : null);

		return $this->addTab($link, $content, $data + [
			'filter_sortable' => false,
			'filter_configurable' => false
		]);
	}

	/**
	 * Add tab for render on browser side using passed $data. Selected and expanded tab will be pre-rendered on server
	 * side to prevent screen flickering during page initial load.
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
		$sortable = [];
		$static = [];

		foreach ($this->labels as $index => $label) {
			if ($this->options['data'][$index]['filter_sortable']) {
				$sortable[$index] = $label;
			}
			else {
				$static[$index] = $label;
			}
		}

		// Last static tab is timeselector.
		$timeselector = end($static);
		$index = key($static);
		// First dynamic tab is 'Home' tab and cannot be sorted.
		array_unshift($sortable, array_shift($static));

		if (is_a($this->contents[$index], CTag::class)
				&& $this->contents[$index]->getId() === static::CSS_ID_PREFIX.'timeselector') {
			$timeselector = array_pop($static);
		}
		else {
			$timeselector = null;
		}

		$nav_list = (new CList([
			(new CSimpleButton())
				->setAttribute('data-action', 'toggleTabsList')
				->addClass('btn-widget-expand'),
			(new CSimpleButton())
				->setAttribute('data-action', 'selectNextTab')
				->addClass('btn-iterator-page-next')
		]));

		if ($timeselector) {
			$timeselector_data = end($this->options['data']);
			$tab = $this->options['data'][$this->options['selected']] + ['filter_custom_time' => 0];
			$enabled = !$tab['filter_custom_time'] && !$timeselector_data['disabled'];
			$timeselector->addClass($enabled ? null : ZBX_STYLE_DISABLED);
			array_map([$nav_list, 'addItem'], [
				$timeselector,
				(new CSimpleButton())
					->setEnabled($enabled)
					->addClass(ZBX_STYLE_BTN_TIME_LEFT),
				(new CSimpleButton(_('Zoom out')))
					->setEnabled($enabled)
					->addClass(ZBX_STYLE_BTN_TIME_OUT),
				(new CSimpleButton())
					->setEnabled($enabled)
					->addClass(ZBX_STYLE_BTN_TIME_RIGHT)
			]);
		}

		$nav = [
			(new CSimpleButton())
				->setAttribute('data-action', 'selectPrevTab')
				->addClass('btn-iterator-page-previous'),
			$sortable ? (new CList($sortable))->addClass(static::CSS_TAB_SORTABLE_CONTAINER) : null,
			$static ? $static : null,
			$nav_list
		];

		return new CTag('nav', true , new CList($nav));
	}

	public function bodyToString() {
		$selected = $this->options['selected'];
		$this->labels[$selected]->addClass(static::CSS_TAB_SELECTED);

		if ($this->options['expanded']) {
			if ($this->contents[$selected] === null) {
				$tab_data = $this->options['data'][$selected];
				$tab_data['render_html'] = true;
				$this->labels[$selected]->addClass(static::CSS_TAB_EXPANDED);
				$this->contents[$selected] = (new CDiv([new CPartial($tab_data['tab_view'], $tab_data)]))
					->setId($this->labels[$selected]->getAttribute('data-target'));
			}
		}

		foreach ($this->contents as $index => $content) {
			if (is_a($content, CTag::class)) {
				$content->addClass($index == $selected ? null : 'display-none');
			}
		}

		$templates = '';

		foreach ($this->templates as $template) {
			$templates .= $template->getOutput();
		}

		return implode('', [
			$this->getNavigation(),
			(new CDiv([
				(new CDiv($this->contents))->addClass('tabfilter-tabs-container'),
				$this->buttons,
			]))
				->addClass('tabfilter-content-container')
				->addClass($this->options['expanded'] ? null : 'display-none'),
			$templates,
		]);
	}
}
