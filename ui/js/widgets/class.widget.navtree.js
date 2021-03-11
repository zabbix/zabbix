/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CWidgetNavTree extends CWidget {

	_processUpdateResponse(response) {
		super._processUpdateResponse(response);

		if (response.navtree_data !== undefined) {
			this._$target.zbx_navtree({
				problems: response.navtree_data.problems,
				severity_levels: response.navtree_data.severity_levels,
				navtree: response.navtree_data.navtree,
				navtree_items_opened: response.navtree_data.navtree_items_opened,
				navtree_item_selected: response.navtree_data.navtree_item_selected,
				maps_accessible: response.navtree_data.maps_accessible,
				show_unavailable: response.navtree_data.show_unavailable,
				initial_load: response.navtree_data.initial_load,
				uniqueid: this._uniqueid,
				max_depth: response.navtree_data.max_depth
			}, this);
		}
	}

		// jQuery(function($) {'.
		// 	'$("#'.$this->getId().'").zbx_navtree({'.
		// 	'problems: '.json_encode($this->data['problems']).','.
		// 	'severity_levels: '.json_encode($this->data['severity_config']).','.
		// 	'navtree: '.json_encode($this->data['navtree']).','.
		// 	'navtree_items_opened: "'.implode(',', $this->data['navtree_items_opened']).'",'.
		// 	'navtree_item_selected: '.intval($this->data['navtree_item_selected']).','.
		// 	'maps_accessible: '.json_encode(array_map('strval', $this->data['maps_accessible'])).','.
		// 	'show_unavailable: '.$this->data['show_unavailable'].','.
		// 	'initial_load: '.$this->data['initial_load'].','.
		// 	'uniqueid: "'.$this->data['uniqueid'].'",'.
		// 	'max_depth: '.WIDGET_NAVIGATION_TREE_MAX_DEPTH.
		// 	'});'.
		// 	'});'
		// : '';
}
