<?php declare(strict_types = 1);
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


/**
 * Class to manage tab filters.
 */
class CTabFilter extends CDiv {

	const ZBX_STYLE_CLASS = 'tabfilter-container';
	const CSS_TAB_SELECTED = 'selected';
	const CSS_TAB_EXPANDED = 'expanded';
	const CSS_TAB_SORTABLE_CONTAINER = 'ui-sortable-container';
	const CSS_ID_PREFIX = 'tabfilter_';
	const CSS_TABFILTER_ICON_FILTER = 'icon-filter';
	const CSS_TABFILTER_ITEM = 'tabfilter-item-label';

	/**
	 * Array of arrays for tabs data. Single element contains: tab label object, tab content object or null and tab data
	 * array.
	 *
	 * @var array $tabs
	 */
	public $tabs = [];

	/**
	 * Tab options array.
	 * idx                  - namespace used to get/update filter related data in profiles table.
	 * selected             - zero based index of selected filter.
	 * expanded             - is selected filter expanded or not.
	 * support_custom_time  - can filters define custom time range or not.
	 * data                 - array of filters data arrays.
	 * page                 - current page number used by selected tab for pagination.
	 * timeselector         - array of timeselector data, can be set with addTimeselector or passed as array.
	 */
	public $options = [
		'idx' => '',
		'selected' => 0,
		'expanded' => false,
		'support_custom_time' => 1,
		'data' => [],
		'page' => null
	];

	/**
	 * Tab form available buttons node. Will be initialized during __construct but can be overwritten if needed.
	 */
	public $buttons = null;

	/**
	 * Subfilter node.
	 */
	public $subfilter = null;

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
			->addItem([
				(new CSubmitButton(_('Update'), 'filter_update', 1))->addClass(ZBX_STYLE_BTN_ALT),
				(new CSubmitButton(_('Save as'), 'filter_new', 1))->addClass(ZBX_STYLE_BTN_ALT),
				new CSubmitButton(_('Apply'), 'filter_apply', 1),
				(new CSubmitButton(_('Reset'), 'filter_reset', 1))->addClass(ZBX_STYLE_BTN_ALT)
			])
			->addClass(ZBX_STYLE_FILTER_FORMS)
			->addClass('form-buttons');
	}

	/**
	 * Set tabfilter options used by javascript.
	 *
	 * @param array $options  Array of options.
	 */
	public function setOptions(array $options) {
		$this->options = $options;

		if (array_key_exists('timeselector', $options)) {
			$this->addTimeselector($this->options['timeselector']);
		}

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
	 * Add subfilter.
	 *
	 * @param CPartial $subfilter  Rendered subfilter.
	 */
	public function addSubfilter(CPartial $subfilter) {
		$this->subfilter = $subfilter;

		return $this;
	}

	/**
	 * Add single tab filter.
	 *
	 * @param CTag|string $label                          String or CTag as tab label element.
	 * @param CTag|null   $content                        Tab content node or null if tab is dynamic.
	 * @param array       $data
	 * @param bool        $data['filter_sortable']        Make tab sortable.
	 * @param bool        $data['filter_configurable']    Make tab configurable.
	 * @param mixed       $data[<any>]                    Tab filter fields.
	 */
	public function addTab($label, ?CTag $content, array $data = []) {
		$tab_index = count($this->labels);
		$targetid = static::CSS_ID_PREFIX.$tab_index;

		if ($content !== null && method_exists($content, 'getId') && $content->getId()) {
			$targetid = $content->getId();
		}

		if (!is_a($label, CTag::class)) {
			if ($tab_index == 0) {
				$label = (new CLink(''))
					->setAttribute('aria-label', _('Home'))
					->addClass(self::CSS_TABFILTER_ICON_FILTER);
				$data += [
					'filter_sortable' => false,
					'filter_configurable' => false
				];
			}
			else {
				$label = new CLink($label);
			}
		}

		if (is_a($label, CLink::class)) {
			// Disable navigation by TAB, javascript code will modify this attribute.
			$label->setAttribute('tabindex', -1);
		}

		if ($content) {
			$content->setId($targetid);
		}

		$this->labels[] = (new CListItem($label->addClass('tabfilter-item-link')))
			->setAttribute('data-target', $targetid)
			->addClass(self::CSS_TABFILTER_ITEM);
		$this->contents[] = $content;
		$this->options['data'][] = $data + [
			'filter_sortable' => true,
			'filter_configurable' => true
		];

		return $this;
	}

	/**
	 * Add time range selector tab.
	 *
	 * @param array   $timerange
	 * @param string  $timerange['from']  Time range start time.
	 * @param string  $timerange['to']    Time range end time.
	 * @param string  $timerange['idx']
	 * @param int     $timerange['idx2']
	 */
	public function addTimeselector(array $timerange) {
		$this->options['timeselector'] = $timerange + [
			'format' => ZBX_FULL_DATE_TIME,
			'from' => 'now-'.CSettingsHelper::get(CSettingsHelper::PERIOD_DEFAULT),
			'to' => 'now',
			'disabled' => true
		];

		return $this;
	}

	/**
	 * Add tab for render on browser side using passed $data.
	 * Selected and expanded tab will be pre-rendered server side to prevent screen flickering during page initial load.
	 *
	 * @param string|CTag $label            Tab label.
	 * @param array       $data             Tab data associative array.
	 *
	 * @return CTabFilter
	 */
	public function addTemplatedTab($label, array $data) {
		$uniqid = count($this->labels);

		return $this->addTab($label, null, $data + ['uniqid' => $uniqid]);
	}

	/**
	 * Return HTML of tab filter body element.
	 *
	 * @return string
	 */
	public function bodyToString(): string {
		$timeselector = array_key_exists('timeselector', $this->options) ? $this->options['timeselector'] : [];
		$selected = $this->options['selected'];
		$this->labels[$selected]->addClass(static::CSS_TAB_SELECTED);

		if ($this->options['expanded'] && $this->contents[$selected] === null) {
			$tab_data = $this->options['data'][$selected];
			$tab_data['render_html'] = true;
			$this->labels[$selected]->addClass(static::CSS_TAB_EXPANDED);
			$this->contents[$selected] = (new CDiv([new CPartial($tab_data['tab_view'], $tab_data)]))
				->setId($this->labels[$selected]->getAttribute('data-target'));
		}

		if ($timeselector) {
			$data = $timeselector + [
				'label' => relativeDateToText($timeselector['from'], $timeselector['to']),
				'filter_timeselector' => true,
				'filter_sortable' => false,
				'filter_configurable' => false
			];
			$this->contents[] = (new CDiv(new CPartial('timeselector.filter', $data)))
				->setId(static::CSS_ID_PREFIX.'timeselector');
			$this->options['data'][] = $data;
		}

		foreach ($this->contents as $index => $content) {
			if (is_a($content, CTag::class)) {
				$content->addClass($index == $selected ? null : ZBX_STYLE_DISPLAY_NONE);
			}
		}

		$templates = '';

		foreach ($this->templates as $template) {
			$templates .= $template->getOutput();
		}

		$tabfilter_container_classes = 'tabfilter-content-container';
		if (!$this->options['expanded']) {
			$tabfilter_container_classes .= ' tabfilter-collapsed';
			if (!$this->subfilter) {
				$tabfilter_container_classes .= ' display-none';
			}
		}

		return implode('', [
			$this->getNavigation(),
			(new CDiv([
				(new CDiv($this->contents))->addClass('tabfilter-tabs-container'),
				$this->buttons,
				$this->subfilter
			]))
				->addClass($tabfilter_container_classes),
			$templates
		]);
	}

	public function toString($destroy = true) {
		if (array_key_exists('timeselector', $this->options)) {
			$this
				->addClass(ZBX_STYLE_FILTER_SPACE)
				->setAttribute('data-disable-initial-check', 1)
				->setAttribute('data-accessible', 1)
				->setAttribute('data-profile-idx', $this->options['idx'])
				->setAttribute('data-profile-idx2', 0);
		}

		return parent::toString($destroy);
	}

	/**
	 * Get time selector navigation buttons as array.
	 *
	 * @return array
	 */
	protected function getTimeselectorNavigation(): array {
		$data = $this->options['timeselector'];
		$selected = $this->options['data'][$this->options['selected']] + ['filter_custom_time' => 0];
		$enabled = (!$selected['filter_custom_time'] && !$data['disabled']);
		// Disable navigation by TAB, if timeselector is disabled.
		$link = (new CLink(relativeDateToText($data['from'], $data['to'])))
			->addClass('tabfilter-item-link')
			->setAttribute('tabindex', $enabled ? 0 : -1)
			->addClass(ZBX_STYLE_BTN_TIME)
			->addClass($data['disabled'] ? ZBX_STYLE_DISABLED : null);

		return [
			(new CListItem($link))
				->setAttribute('data-target', static::CSS_ID_PREFIX.'timeselector')
				->addClass(self::CSS_TABFILTER_ITEM)
				->addClass($enabled ? null : ZBX_STYLE_DISABLED),
			(new CSimpleButton())
				->setEnabled($enabled)
				->addClass(ZBX_STYLE_BTN_TIME_LEFT),
			(new CSimpleButton(_('Zoom out')))
				->setEnabled($enabled)
				->addClass(ZBX_STYLE_BTN_TIME_OUT),
			(new CSimpleButton())
				->setEnabled($enabled)
				->addClass(ZBX_STYLE_BTN_TIME_RIGHT)
		];
	}

	/**
	 * Generate tabs navigation layout.
	 *
	 * @return CTag
	 */
	protected function getNavigation(): CTag {
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

		// First dynamic tab is 'Home' tab and cannot be sorted.
		array_unshift($sortable, array_shift($static));

		$nav_list = new CList([
			(new CSimpleButton())
				->setAttribute('data-action', 'toggleTabsList')
				->addClass('btn-widget-expand'),
			(new CSimpleButton())
				->setAttribute('data-action', 'selectNextTab')
				->addClass('btn-iterator-page-next')
		]);

		if (array_key_exists('timeselector', $this->options)) {
			array_map([$nav_list, 'addItem'], $this->getTimeselectorNavigation());
		}

		return new CTag('nav', true , new CList([
			(new CSimpleButton())
				->setAttribute('data-action', 'selectPrevTab')
				->addClass('btn-iterator-page-previous'),
			$sortable ? (new CList($sortable))->addClass(static::CSS_TAB_SORTABLE_CONTAINER) : null,
			$static ? $static : null,
			$nav_list
		]));
	}
}
