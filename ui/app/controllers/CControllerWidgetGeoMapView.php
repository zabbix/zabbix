<?php declare(strict_types = 1);
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


class CControllerWidgetGeoMapView extends CControllerWidget {

	const NO_PROBLEMS_MARKER_COLOR = '#009900';

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_GEOMAP);
		$this->setValidationRules([
			'name' => 'string',
			'initial_load' => 'in 0,1',
			'widgetid' => 'db widget.widgetid',
			'unique_id' => 'required|string',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$data = [
			'name' => $this->getInput('name', $this->getDefaultName()),
			'hosts' => self::convertToRFC7946($this->getHosts()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'unique_id' => $this->getInput('unique_id')
		];

		if ($this->getInput('initial_load', 0)) {
			[$center, $view_set] = $this->getMapCenter();

			$data['config'] = self::getMapConfig() + [
				'center' => $center,
				'view_set' => $view_set,
				'filter' => $this->getUserProfileFilter(),
				'colors' => self::getSeveritySettings()
			];
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * Create an array of problem severity colors.
	 *
	 * @static
	 *
	 * @return array
	 */
	protected static function getSeveritySettings(): array {
		$severity_config = [
			-1 => self::NO_PROBLEMS_MARKER_COLOR
		];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severity_config[$severity] = '#'.CSeverityHelper::getColor($severity);
		}

		return $severity_config;
	}

	/**
	 * Get hosts and their properties to show on the map as markers.
	 *
	 * @return array
	 */
	protected function getHosts(): array {
		$fields = $this->getForm()->getFieldsData();
		$filter_groupids = $fields['groupids'] ? getSubGroups($fields['groupids']) : null;

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectInventory' => ['location_lat', 'location_lon'],
			'groupids' => $filter_groupids,
			'hostids' => $fields['hostids'] ? $fields['hostids'] : null,
			'evaltype' => $fields['evaltype'],
			'tags' => $fields['tags'],
			'filter' => [
				'inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]
			],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		$hosts = array_filter($hosts, function ($host) {
			return ($host['inventory']['location_lat'] !== '' && $host['inventory']['location_lon'] !== '');
		});

		// Get triggers.
		$triggers = API::Trigger()->get([
			'output' => [],
			'selectHosts' => ['hostid'],
			'hostids' => array_keys($hosts),
			'filter' => [
				'value' => TRIGGER_VALUE_TRUE
			],
			'monitored' => true,
			'preservekeys' => true
		]);

		// Get problems.
		$problems = API::Problem()->get([
			'output' => ['objectid', 'severity'],
			'selectHosts' => [],
			'hostids' => array_keys($hosts),
			'objectids' => array_keys($triggers)
		]);

		// Group problems by hosts.
		$problems_by_host = [];
		foreach ($problems as $problem) {
			foreach ($triggers[$problem['objectid']]['hosts'] as $trigger_host) {
				if (!array_key_exists($trigger_host['hostid'], $problems_by_host)) {
					$problems_by_host[$trigger_host['hostid']] = [
						TRIGGER_SEVERITY_DISASTER => 0,
						TRIGGER_SEVERITY_HIGH => 0,
						TRIGGER_SEVERITY_AVERAGE => 0,
						TRIGGER_SEVERITY_WARNING => 0,
						TRIGGER_SEVERITY_INFORMATION => 0,
						TRIGGER_SEVERITY_NOT_CLASSIFIED => 0
					];
				}

				$problems_by_host[$trigger_host['hostid']][$problem['severity']]++;
			}
		}

		// Filter hosts by severity filter.
		$widgetid = $this->getInput('widgetid', 0);
		$severity_filter = CProfile::get('web.dashboard.widget.geomap.severity_filter', '', $widgetid);
		$severity_filter = $severity_filter ? array_flip(explode(',', $severity_filter)) : [];

		if ($severity_filter && count($severity_filter) != 7) {
			$hosts = array_filter($hosts, function ($host) use ($severity_filter, $problems_by_host) {
				return array_key_exists($host['hostid'], $problems_by_host)
					? (bool) array_intersect_key(array_filter($problems_by_host[$host['hostid']]), $severity_filter)
					: array_key_exists(-1, $severity_filter);
			});
		}

		$hosts = array_map(function ($host) use ($problems_by_host) {
			return $host + ['problems' => array_key_exists($host['hostid'], $problems_by_host)
				? $problems_by_host[$host['hostid']]
				: [
					TRIGGER_SEVERITY_DISASTER => 0,
					TRIGGER_SEVERITY_HIGH => 0,
					TRIGGER_SEVERITY_AVERAGE => 0,
					TRIGGER_SEVERITY_WARNING => 0,
					TRIGGER_SEVERITY_INFORMATION => 0,
					TRIGGER_SEVERITY_NOT_CLASSIFIED => 0
				]
			];
		}, $hosts);

		return $hosts;
	}

	/**
	 * Get initial map center point, zoom level and state if widget has set default view.
	 *
	 * @return array
	 */
	protected function getMapCenter(): array {
		$widgetid = $this->getInput('widgetid', 0);
		$fields = $this->getForm()->getFieldsData();
		$geoloc_parser = new CGeomapCoordinatesParser();

		$max_zoom = (CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_PROVIDER) !== '')
			? getTileProviders()[CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_PROVIDER)]['geomaps_max_zoom']
			: CSettingsHelper::get(CSettingsHelper::GEOMAPS_MAX_ZOOM);

		$defaults = [
			'latitude' => 0,
			'longitude' => 0,
			'zoom' => 1
		];

		$user_default_view = CProfile::get('web.dashboard.widget.geomap.default_view', '', $widgetid);
		if ($user_default_view !== '' && $geoloc_parser->parse($user_default_view) == CParser::PARSE_SUCCESS) {
			return [$geoloc_parser->result, true];
		}

		if (array_key_exists('default_view', $fields)
				&& $fields['default_view'] !== ''
				&& $geoloc_parser->parse($fields['default_view']) == CParser::PARSE_SUCCESS) {
			return [$geoloc_parser->result + ['zoom' => ceil(($max_zoom - 1) / 2)], true];
		}

		return [$defaults, false];
	}

	/**
	 * Get global map configuration.
	 *
	 * @static
	 *
	 * @return array
	 */
	protected static function getMapConfig(): array {
		if (CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_PROVIDER) === '') {
			$config = [
				'tile_url' => CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_URL),
				'max_zoom' => CSettingsHelper::get(CSettingsHelper::GEOMAPS_MAX_ZOOM),
				'attribution' => CSettingsHelper::get(CSettingsHelper::GEOMAPS_ATTRIBUTION)
			];
		}
		else {
			$tile_provider = getTileProviders()[CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_PROVIDER)];

			$config = [
				'tile_url' => $tile_provider['geomaps_tile_url'],
				'max_zoom' => $tile_provider['geomaps_max_zoom'],
				'attribution' => $tile_provider['geomaps_attribution']
			];
		}

		return $config;
	}

	protected function getUserProfileFilter(): array {
		$widgetid = $this->getInput('widgetid', 0);

		return [
			'severity' => CProfile::get('web.dashboard.widget.geomap.severity_filter', [], $widgetid)
		];
	}

	/**
	 * Convert array of hosts to valid GeoJSON (RFC7946) object.
	 *
	 * @static
	 *
	 * @param array  $hosts
	 *
	 * @return array
	 */
	protected static function convertToRFC7946(array $hosts) : array {
		$hosts = array_values(array_map(function ($host) {
			$problems = array_filter($host['problems']);
			$severities = array_keys($problems);
			$top_severity = reset($severities);

			return [
				'type' => 'Feature',
				'geometry' => [
					'type' => 'Point',
					'coordinates' => [
						$host['inventory']['location_lon'],
						$host['inventory']['location_lat'],
						0
					]
				],
				'properties' => [
					'hostid' => $host['hostid'],
					'name' => $host['name'],
					'severity' => ($top_severity === false) ? -1 : $top_severity,
					'problems' => $problems
				]
			];
		}, $hosts));

		return $hosts;
	}
}
