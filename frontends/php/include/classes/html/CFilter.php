<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CFilter extends CDiv {

	// Filter form object.
	private $form;
	// Filter form object name and id attribute.
	private $name = 'zbx_filter';
	// Visibility of 'Apply', 'Reset' from buttons. Visibility is set to all tabs.
	private $show_buttons = true;
	// Array of filter tab headers. Every header is mapped to it content via href(header) and id(content) attribute.
	protected $headers = [];
	// Array of filter tab content.
	protected $tabs = [];
	// jQuery.tabs disabled tabs list.
	protected $tabs_disabled = [];
	// jQuery.tabs initialization options.
	protected $tabs_options = [
		'collapsible' => true,
		'active' => false
	];
	// Profile data associated with filter object.
	protected $idx = null;
	protected $idx2 = 0;

	/**
	 * List of predefined time ranges. Start and end of time range are separated by semicolon.
	 */
	protected $time_ranges = [
		['now-2d/d:now', 'now-7d/d:now', 'now-30d/d:now', 'now-3M/M:now', 'now-6M/M:now', 'now-1y:now',
			'now-2y:now'
		],
		['now-1d/d:now-1d/d', 'now-2d/d:now-2d/d', 'now-1w/d:now-1w/d', 'now-1w/w:now-1w/w', 'now-1M/M:now-1M/M',
			'now-1y/y:now-1y/y'
		],
		['now/d:now/d', 'now/d:now', 'now/w:now/w', 'now/w:now', 'now/M:now/M', 'now/M:now', 'now/y:now/y', 'now/y:now'],
		['now-5m:now', 'now-15m:now', 'now-30m:now', 'now-1h:now', 'now-3h:now', 'now-6h:now', 'now-12h:now',
			'now-24h:now'
		]
	];

	public function __construct() {
		parent::__construct();

		$this->setId('filter-space');

		$this->form = (new CForm('get'))
			->cleanItems()
			->setAttribute('name', $this->name)
			->setId('id', $this->name);
	}

	public function getName() {
		return $this->name;
	}

	/**
	 * Add variable to filter form.
	 *
	 * @param string $name      Variable name.
	 * @param string $value     Variable value.
	 *
	 * @return CFilter
	 */
	public function addVar($name, $value) {
		$this->form->addVar($name, $value);

		return $this;
	}

	/**
	 * Hide filter tab buttons. Should be called before addFilterTab.
	 */
	public function hideFilterButtons() {
		$this->show_buttons = false;

		return $this;
	}

	/**
	 * Set profile 'idx' and 'idx2' data. Set current expanded tab from profile value '{$idx}.active'.
	 *
	 * @param string $idx
	 * @param int    $idx2
	 *
	 * @return CFilter
	 */
	public function setProfile($idx, $idx2) {
		$this->idx = $idx;
		$this->idx2 = $idx2;

		$this->setActiveTab(CProfile::get($idx.'.active', 1));
		$this->setAttribute('data-profile-idx', $idx);
		$this->setAttribute('data-profile-idx2', $idx2);


		return $this;
	}

	/**
	 * Adds an item inside the form object.
	 *
	 * @param mixed $item  An item to add inside the form object.
	 *
	 * @return \CFilter
	 */
	public function addFormItem($item) {
		$this->form->addItem($item);

		return $this;
	}

	/**
	 * Set active tab.
	 *
	 * @param int $tab  1 based index of active tab. If set to 0 all tabs will be collapsed.
	 *
	 * @return CFilter
	 */
	public function setActiveTab($tab) {
		$this->tabs_options['active'] = $tab > 0 ? $tab - 1 : false;

		return $this;
	}

	/**
	 * Add tab with filter form.
	 *
	 * @param string $header    Tab header title string.
	 * @param array  $columns   Array of filter columns markup.
	 * @param array  $footer    Additional markup objects for filter tab, default null.
	 *
	 * @return CFilter
	 */
	public function addFilterTab($header, $columns, $footer = null) {
		$row = (new CDiv())->addClass(ZBX_STYLE_ROW);
		$body = [];
		$anchor = 'tab_'.count($this->tabs);

		foreach ($columns as $column) {
			$row->addItem((new CDiv($column))->addClass(ZBX_STYLE_CELL));
		}

		$body[] = (new CDiv())
			->addClass(ZBX_STYLE_TABLE)
			->addClass(ZBX_STYLE_FILTER_FORMS)
			->addItem($row);

		if ($this->show_buttons) {
			$url = (new CUrl())
				->removeArgument('filter_set')
				->removeArgument('ddreset')
				->setArgument('filter_rst', 1);

			$body[] = (new CDiv())
				->addClass(ZBX_STYLE_FILTER_FORMS)
				->addItem(
					(new CSubmitButton(_('Apply'), 'filter_set', 1))
						->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();')
				)
				->addItem(
					(new CRedirectButton(_('Reset'), $url->getUrl()))
						->addClass(ZBX_STYLE_BTN_ALT)
						->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();')
				);
		}

		if ($footer !== null) {
			$body[] = $footer;
		}

		return $this->addTab(
			(new CLink($header, '#'.$anchor))->addClass(ZBX_STYLE_FILTER_TRIGGER),
			(new CDiv($body))
				->addClass(ZBX_STYLE_FILTER_CONTAINER)
				->setId($anchor)
		);
	}

	/**
	 * Add time selector specific tab. Should be called before any tab is added. Adds two tabs:
	 * - time selector range change buttons: back, zoom out, forward.
	 * - time selector range change form with predefined ranges.
	 *
	 * @param string $from      Start date. (can be in relative time format, example: now-1w)
	 * @param string $to        End date. (can be in relative time format, example: now-1w)
	 *
	 * @return CFilter
	 */
	public function addTimeSelector($from, $to) {
		$header = relativeDateToText($from, $to);

		$this->addTab(new CDiv([
			(new CSimpleButton())->addClass('btn-time-left'),
			(new CSimpleButton(_('Zoom out')))->addClass('btn-time-out'),
			(new CSimpleButton())->addClass('btn-time-right')
		]), null);

		$predefined_ranges = [];

		foreach ($this->time_ranges as $column_ranges) {
			$column = (new CList())->addClass('time-quick');

			foreach ($column_ranges as $range) {
				list($range_from, $range_to) = explode(':', $range);
				$label = relativeDateToText($range_from, $range_to);
				$is_selected = parseRelativeDate($from, true) == parseRelativeDate($range_from, true)
					&& parseRelativeDate($to, false) == parseRelativeDate($range_to, false);

				$column->addItem((new CLink($label))
					->setAttribute('data-from', $range_from)
					->setAttribute('data-to', $range_to)
					->setAttribute('data-label', $label)
					->addClass($is_selected ? ZBX_STYLE_SELECTED : null)
				);
			}

			$predefined_ranges[] = (new CDiv($column))->addClass(ZBX_STYLE_CELL);
		}

		$anchor = 'tab_'.count($this->tabs);

		$this->addTab(
			(new CLink($header, '#'.$anchor))->addClass('btn-time'),
			(new CDiv([
				(new CDiv(
					(new CList([
						new CLabel(_('From:'), 'from'), new CTextBox('from', $from),
						(new CButton('from_calendar'))->addClass(ZBX_STYLE_ICON_CAL),
						new CLabel(_('To:'), 'to'), new CTextBox('to', $to),
						(new CButton('to_calendar'))->addClass(ZBX_STYLE_ICON_CAL),
						(new CButton('apply', _('Apply')))
					]))->addClass(ZBX_STYLE_TABLE_FORMS)
				))->addClass('time-input'),
				(new CDiv($predefined_ranges))->addClass('time-quick-range')
			]))
				->addClass(ZBX_STYLE_FILTER_CONTAINER)
				->addClass('time-selection-container')
				->setId($anchor)
		);

		return $this;
	}

	/**
	 * Add tab.
	 *
	 * @param string|CTag $header    Tab header title string or CTag contaier.
	 * @param array  $body           Array of body elements.
	 *
	 * @return CFilter
	 */
	public function addTab($header, $body) {
		$this->headers[] = $header;
		$this->tabs[] = $body;

		return $this;
	}

	/**
	 * Return javascript code for jquery-ui initialization.
	 *
	 * @return string
	 */
	public function getJS() {
		$id = '#' . $this->getId();
		$js = 'var multiselects = jQuery("'.$id.'").tabs('.
			CJs::encodeJson(array_merge($this->tabs_options, ['disabled' => $this->tabs_disabled])).
		').find(".multiselect");'.
		'if (multiselects.length) {'.
		'	multiselects.multiSelect("resize");'.
		'}';

		if ($this->idx !== null && $this->idx !== '') {
			$idx = $this->idx . '.active';
			$js .= 'jQuery("'.$id.'").on("tabsactivate", function(e, ui) {'.
			'	var active = ui.newPanel.length ? jQuery(this).tabs("option", "active") + 1 : 0,'.
			'		multiselects = jQuery(".multiselect", ui.newPanel);'.
			'	updateUserProfile("'.$idx.'", active, []);'.

			'	if (multiselects.length) {'.
			'		multiselects.multiSelect("resize");'.
			'	}'.

			'	if (active) {'.
			'		jQuery("[autofocus=autofocus]", ui.newPanel).focus();'.
			'	}'.
			'});';
		}

		return $js;
	}

	/**
	 * Render current CFilter object as HTML string.
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$headers = (new CList())->addClass(ZBX_STYLE_FILTER_BTN_CONTAINER);

		foreach ($this->headers as $index => $header) {
			$headers->addItem($header);

			if ($this->tabs[$index] !== null && $index !== $this->tabs_options['active']) {
				$this->tabs[$index]->addStyle('display: none');
			}
		}

		$this->form->addItem($this->tabs);

		$this
			->addItem($headers)
			->addItem($this->form)
			->setAttribute('aria-label', _('Filter'));

		zbx_add_post_js($this->getJS());

		return parent::toString($destroy);
	}
}
