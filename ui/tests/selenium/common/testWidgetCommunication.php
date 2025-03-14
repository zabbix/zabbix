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


require_once dirname(__FILE__).'/../common/testWidgets.php';

class testWidgetCommunication extends testWidgets {

	/**
	 * Attach Widget behavior to the test.
	 */
	public function getBehaviors() {
		return [CWidgetBehavior::class];
	}

	protected static $entityids;

	const BROADCASTER_REFERENCES = [
		'Map hostgroup broadcaster' => 'NRDLG._hostgroupids',
		'Problem hosts hostgroup broadcaster' => 'EKBHR._hostgroupids',
		'Problems by severity hostgroup broadcaster' => 'ZYWLY._hostgroupids',
		'Web monitoring hostgroup broadcaster' => 'XTPSV._hostgroupids',
		'Geomap host broadcaster' => 'JRVYU._hostids',
		'Honeycomb host broadcaster' => 'RICVX._hostids',
		'Map host broadcaster' => 'BFSOY._hostids',
		'Top hosts host broadcaster' => 'ACGKU._hostids',
		'Host navigator broadcaster' => 'HSTNV._hostids',
		'Honeycomb item broadcaster' => 'QFWQX._itemid',
		'Item history item broadcaster' => 'ZNLUI._itemid',
		'Item navigator broadcaster' => 'ITMNV._itemid',
		'Navigation tree map broadcaster' => 'TAPOK._mapid'
	];

	const GEOMAP_ICON_INDEXES = [
		self::FIRST_HOST_NAME => 3,
		self::SECOND_HOST_NAME => 2,
		self::THIRD_HOST_NAME => 1
	];

	const FIRST_HOST_NAME = '1st host for widgets';
	const SECOND_HOST_NAME = '2nd host for widgets';
	const THIRD_HOST_NAME = '3rd host for widgets';
	const FIRST_HOSTGROUP_NAME = '1st hostgroup for widgets';
	const SECOND_HOSTGROUP_NAME = '2nd hostgroup for widgets';
	const THIRD_HOSTGROUP_NAME = '3rd hostgroup for widgets';
	const FIRST_HOST_TRIGGER = 'trigger on host 1';
	const SECOND_HOST_TRIGGER = 'trigger on host 2';
	const THIRD_HOST_TRIGGER = 'trigger on host 3';
	const MAP_NAME = 'Map for testing feedback';
	const SUBMAP_NAME = 'Map for widget communication test';

	/**
	 * Write IDs of all entities created by WidgetCommunication data source into a variable.
	 */
	public static function getCreatedIds() {
		self::$entityids = CDataHelper::get('WidgetCommunication');
	}

	/**
	 * Return the element on widget that needs to be selected.
	 *
	 * @param string			$identifier		text or selector part that is used to locate the element
	 * @param CWidgetElement	$widget			widget where the element is located
	 *
	 * @return CElement
	 */
	protected function getWidgetElement($identifier, $widget) {
		$widget_type = $this->getWidgetType($widget);

		switch ($widget_type) {
			case 'map':
				$element = $widget->query('xpath:.//*[@class="map-elements"]//*[text()='
						.CXPathHelper::escapeQuotes($identifier).']/../../preceding::*[1]'
				);
				break;

			case 'problemhosts':
			case 'problemsbysv':
			case 'web':
			case 'tophosts':
				$element = $widget->query('xpath:.//a[text()='.CXPathHelper::escapeQuotes($identifier).']/../..');
				break;

			case 'navtree':
				$element = $widget->query('xpath:.//a[text()='.CXPathHelper::escapeQuotes($identifier).']');
				break;

			case 'geomap':
				$element = $widget->query('xpath:.//img[contains(@class,"leaflet-marker-icon")]['.$identifier.']');
				break;

			case 'honeycomb':
				$element = $widget->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($identifier).']');
				break;

			case 'itemhistory':
				$element = $widget->query('xpath:.//td[text()='.CXPathHelper::escapeQuotes($identifier.
						': Trapper item').']'
				);
				break;

			case 'hostnavigator':
				$element = $widget->query('xpath:.//span[@title='.CXPathHelper::escapeQuotes($identifier).']');
				break;

			case 'itemnavigator':
				$itemid = CDataHelper::get('WidgetCommunication.itemids')[$identifier.':trap.widget.communication'];
				$element = $widget->query('xpath:.//div[@data-id='.$itemid.']');
				break;
		}

		return $element->waitUntilClickable()->one();
	}
}
