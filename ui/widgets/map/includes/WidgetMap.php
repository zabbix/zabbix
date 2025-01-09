<?php
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


namespace Widgets\Map\Includes;

use CDiv,
	CLink,
	CSpan,
	CTableInfo;

use Widgets\Map\Widget;

/**
 * Dashboard Map widget class. Creates all widget specific JavaScript and HTML content for map widget's view.
 */
class WidgetMap extends CDiv
{
	/**
	 * Map that will be linked to 'go back to [previous map name]' link in dashboard map widget.
	 * Array must contain at least integer value 'sysmapid' and string 'name'.
	 */
	private ?array $previous_map;

	/**
	 * Response array of CMapHelper::get() that represents currently opened map.
	 */
	private array $sysmap_data;

	/**
	 * Requested sysmapid.
	 */
	private ?int $current_sysmapid;

	/**
	 * Represents either this is initial or repeated load of map widget.
	 * Allowed values are 0 and 1.
	 */
	private int $initial_load;

	/**
	 * The error message displayed in map widget.
	 */
	private ?string $error;

	/**
	 * @param array       $sysmap_data      An array of requested map in the form created by CMapHelper::get() method.
	 * @param array       $widget_settings  An array contains widget settings.
	 *        string|null $widget_settings['error']                   A string of error message or null in case
	 *                                                                if error is not detected.
	 *        int         $widget_settings['current_sysmapid']        An integer of requested sysmapid.
	 *        array|null  $widget_settings['previous_map']            Sysmapid and name of map linked as previous.
	 *        int         $widget_settings['initial_load']            Integer represents either this is initial load
	 *                                                                or repeated.
	 */
	public function __construct(array $sysmap_data, array $widget_settings) {
		parent::__construct();

		$this->error = $widget_settings['error'];
		$this->sysmap_data = $sysmap_data;
		$this->current_sysmapid = $widget_settings['current_sysmapid'];
		$this->previous_map = $widget_settings['previous_map'];
		$this->initial_load = $widget_settings['initial_load'];
	}

	/**
	 * A javascript that is used as widget's script_inline parameter.
	 */
	public function getScriptData(): array {
		$map_data = [
			'current_sysmapid' => null,
			'map_options' => null
		];

		if ($this->current_sysmapid !== null && $this->initial_load) {
			$map_data['current_sysmapid'] = $this->current_sysmapid;
		}

		if ($this->sysmap_data && $this->error === null) {
			$map_data['map_options'] = $this->sysmap_data;
		}
		elseif ($this->error !== null) {
			$map_data['error_msg'] = (new CTableInfo())
				->setNoDataMessage($this->error)
				->toString();
		}

		return $map_data;
	}

	public function toString($destroy = true): string {
		$this->build();

		return parent::toString($destroy);
	}

	private function build(): void {
		$this->addClass(ZBX_STYLE_SYSMAP);
		$this->setId(uniqid('', true));

		if ($this->error === null) {
			if ($this->previous_map) {
				$go_back_div = (new CDiv())
					->addClass(ZBX_STYLE_BTN_BACK_MAP_CONTAINER)
					->addItem(
						(new CLink(
							(new CSpan())
								->addClass(ZBX_STYLE_BTN_BACK_MAP)
								->addItem(
									(new CDiv())
										->addClass(ZBX_STYLE_BTN_BACK_MAP_ICON)
										->addClass(ZBX_ICON_CHEVRON_LEFT)
								)
								->addItem(
									(new CDiv())
										->addClass(ZBX_STYLE_BTN_BACK_MAP_CONTENT)
										->addItem(_s('Go back to %1$s', $this->previous_map['name']))
								),
							'#'
						))->addClass('js-previous-map')
					);

				$this->addItem($go_back_div);
			}

			$map_div = (new CDiv(
				(new CDiv($this->sysmap_data['aria_label']))->addClass(ZBX_STYLE_SYSMAP_CAPTION))
			)->addClass('sysmap-widget-container');

			$this->addStyle('position:relative;');
			$this->addItem($map_div);
		}
		else {
			$this->addItem(
				(new CTableInfo())->setNoDataMessage($this->error)
			);
		}
	}
}
