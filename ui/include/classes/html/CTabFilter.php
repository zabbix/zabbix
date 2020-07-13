<?php

class CTabFilter extends CDiv {

	/**
	 * HTML Content of filter active tab.
	 *
	 * @var string
	 */
	protected $active_html = '';

	/**
	 * Array of CPartial elements used as tab content rendering templates.
	 *
	 * @var array $template
	 */
	protected $templates = [];

	protected $tabs_data = [];

	public function __construct($items = null) {
		parent::__construct($items);

		$this
			->setId(uniqid('tabfilter_'))
			->addClass(ZBX_STYLE_FILTER_CONTAINER);
	}

	// public function bodyToString() {
	// 	$tabs = new CDiv(array_column($this->items, 1));
	// 	$labels = (new CList())->addClass('ui-sortable');
	// 	array_map([$labels, 'addItem'], array_column($this->items, 0));
	// 	$templates = '';

	// 	foreach ($this->templates as $template) {
	// 		$templates .= $template->getOutput();
	// 	}

	// 	return implode('', [$labels, $tabs, $this->getFilterButtons(), $templates, $this->getJS()]);
	// }

	public function addTabData(array $data): CTabFilter {
		$this->tabs_data[] = $data;

		return $this;
	}

	/**
	 * Add tab item. First element is tab item label node and second is tab item node itself or null.
	 * Tab node should have Id attribute to be set, if tab node is null tab item label node should have data-target
	 * attribute equal to id of HTML node element to be shown when tab item label is activated.
	 *
	 * @param array|null $item    Tab item array.
	 * @param CTag       $item[]  Tab item label node.
	 * @param CTag|null  $item[]  Tab item content node.
	 */
	public function addItem($item) {
		if (!is_array($item)) {
			return $this;
		}

		[$label, $content] = $item;
		$tabid = is_a($content, CTag::class) ? $content->getId() : uniqid('tabfiltertab_');

		if (!is_a($label, CTag::class)) {
			$label = new CLink($label);
		}

		if (is_a($content, CPartial::class)) {
			$content = (new CDiv($content))->setId($tabid);
		}

		if (is_a($content, CTag::class) && $label->getAttribute('data-target') !== $tabid) {
			$label->setAttribute('data-target', '#'.$tabid);
		}

		$this->items[] = [$label, $content];

		return $this;
	}

	public function addTemplate($template) {
		if (is_a($template, CPartial::class)) {
			$this->templates[$template->getName()] = $template;
		}

		return $this;
	}

	public function setData(array $data) {
		$this->tabs_data = $data;
	}



	/**
	 * Array of arrays for tabs data. Single element contains: tab label object, tab content object or null and tab data
	 * array.
	 *
	 * @var array $tabs
	 */
	public $tabs = [];

	/**
	 * Add tab for render on browser side using passed $data. Active (expanded) tab will be pre-rendered on server side
	 * to prevent screen flickering during page initial load.
	 *
	 * @param string|CTag $label            Tab label.
	 * @param string      $target_selector  CSS selector for tab content node to be shown/hidden.
	 * @param array       $data             Array of tab data.
	 *
	 * @return CTabFilter
	 */
	public function addTemplatedTab($label, string $target_selector, array $data): CTabFilter {
		if (!is_a($label, CTag::class)) {
			$label = new CLink($label);
		}

		$label->setAttribute('data-target', $target_selector);
		$this->tabs[] = [$label, null, $data];

		return $this;
	}

	/**
	 * Add pre-rendered tab.
	 *
	 * @param string|CTag   $label    Tab label.
	 * @param CPartial|CTag $content  Tab content object.
	 *
	 * @return CTabFilter
	 */
	public function addSimpleTab($label, $content): CTabFilter {
		if (is_a($content, CPartial::class)) {
			$content = (new CDiv($content))->setId(uniqid('tabfilter_'));
		}

		$targetid = $content->getId();

		if (!strlen($targetid)) {
			$targetid = uniqid('tabfilter_');
			$content->setId($targetid);
		}

		if (!is_a($label, CTag::class)) {
			$label = new CLink($label);
		}

		$label->setAttribute('data-target', '#'.$targetid);
		$this->tabs[] = [$label, $content, []];

		return $this;
	}

	public function bodyToString() {
		$tab_active = 0;
		$is_expanded = 1;

		$labels = [];
		$contents = [];
		$data = [];

		foreach ($this->tabs as $tab_index => $tab) {
			[$tab_label, $tab_content, $tab_data] = $tab;

			if ($tab_content === null && $tab_active == $tab_index && $is_expanded) {
				//$tab
			}
		}
		// $tabs = new CDiv(array_column($this->items, 1));
		// $labels = (new CList())->addClass('ui-sortable');
		// array_map([$labels, 'addItem'], array_column($this->items, 0));
		// $templates = '';

		// foreach ($this->templates as $template) {
		// 	$templates .= $template->getOutput();
		// }

		// return implode('', [$labels, $tabs, $this->getFilterButtons(), $templates, $this->getJS()]);
	}
	/**
	 * Return javascript code for filter initialization. Page should include script 'class.tabs.js' to work properly.
	 *
	 * @return CScriptTag
	 */
	protected function getJS(): CScriptTag {
		$id = $this->getId();

		$data = [
			// allow to reorder tabs
			'sortable' => true,
			// allow to collapse tab
			'can_toggle' => true,
			// active/selected tab
			'active_tab' => 1,
			// tabs custom data
			'data' => $this->tabs_data,
		];

		// TODO: double json_encode for performance on browser side.
		return (new CScriptTag('new CTabFilter($("#'.$id.'")[0], '.json_encode($data).')'))->setOnDocumentReady(true);
	}

	/**
	 * Get filter action buttons with CDiv wrapper container.
	 *
	 * @return CDiv
	 */
	protected function getFilterButtons(): CDiv {
		return (new CDiv())
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
				(new CRedirectButton(_('Reset'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'host.view')
						->setArgument('filter_rst', 1)
						->getUrl()
				))
					->addClass(ZBX_STYLE_BTN_ALT)
			)
			->addClass('form-buttons');
	}
}
