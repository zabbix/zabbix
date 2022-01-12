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


class CControllerWidgetGeoMapView extends CControllerWidget {

	const NO_PROBLEMS_MARKER_COLOR = '#009900';

	/**
	 * Widget id.
	 *
	 * @param string
	 */
	protected $widgetid;

	/**
	 * Widget fields.
	 *
	 * @param array
	 */
	protected $fields;

	/**
	 * Global geomap configuration.
	 *
	 * @param array
	 */
	protected $geomap_config;

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
		$this->widgetid = $this->getInput('widgetid', 0);
		$this->fields = $this->getForm()->getFieldsData();

		$data = [
			'name' => $this->getInput('name', $this->getDefaultName()),
			'hosts' => self::convertToRFC7946($this->getHosts()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'unique_id' => $this->getInput('unique_id')
		];

		if ($this->getInput('initial_load', 0)) {
			$this->geomap_config = self::getMapConfig();

			$data['config'] = $this->geomap_config + $this->getMapCenter() + [
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
		$filter_groupids = $this->fields['groupids'] ? getSubGroups($this->fields['groupids']) : null;

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectInventory' => ['location_lat', 'location_lon'],
			'groupids' => $filter_groupids,
			'hostids' => $this->fields['hostids'] ? $this->fields['hostids'] : null,
			'evaltype' => $this->fields['evaltype'],
			'tags' => $this->fields['tags'],
			'filter' => [
				'inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]
			],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		$hosts = array_filter($hosts, function ($host) {
			$lat = $host['inventory']['location_lat'];
			$lng = $host['inventory']['location_lon'];

			return (is_numeric($lat) && $lat >= GEOMAP_LAT_MIN && $lat <= GEOMAP_LAT_MAX
				&& is_numeric($lng) && $lng >= GEOMAP_LNG_MIN && $lng <= GEOMAP_LNG_MAX);
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
			'selectHosts' => ['hostid'],
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
		$severity_filter = CProfile::get('web.dashboard.widget.geomap.severity_filter', '', $this->widgetid);
		$severity_filter = ($severity_filter !== '') ? array_flip(explode(',', $severity_filter)) : [];

		if ($severity_filter && count($severity_filter) != 7) {
			$hosts = array_filter($hosts, function ($host) use ($severity_filter, $problems_by_host) {
				return array_key_exists($host['hostid'], $problems_by_host)
					? (bool) array_intersect_key(array_filter($problems_by_host[$host['hostid']]), $severity_filter)
					: array_key_exists(-1, $severity_filter);
			});
		}

		$result_hosts = [];
		foreach ($hosts as $host) {
			$problems = array_key_exists($host['hostid'], $problems_by_host)
				? $problems_by_host[$host['hostid']]
				: [
					TRIGGER_SEVERITY_DISASTER => 0,
					TRIGGER_SEVERITY_HIGH => 0,
					TRIGGER_SEVERITY_AVERAGE => 0,
					TRIGGER_SEVERITY_WARNING => 0,
					TRIGGER_SEVERITY_INFORMATION => 0,
					TRIGGER_SEVERITY_NOT_CLASSIFIED => 0
				];

			$result_hosts[] = $host + ['problems' => $problems];
		}

		return $result_hosts;
	}

	/**
	 * Get initial map center point, zoom level and coordinates to center when clicking on navigate home button.
	 *
	 * @return array
	 */
	protected function getMapCenter(): array {
		$geoloc_parser = new CGeomapCoordinatesParser();
		$home_coords = [];
		$center = [];

		$user_default_view = CProfile::get('web.dashboard.widget.geomap.default_view', '', $this->widgetid);
		if ($user_default_view !== '' && $geoloc_parser->parse($user_default_view) == CParser::PARSE_SUCCESS) {
			$home_coords['default'] = true;
			$center = $geoloc_parser->result;
			$center['zoom'] = min($this->geomap_config['max_zoom'], $center['zoom']);
		}

		if (array_key_exists('default_view', $this->fields)
				&& $this->fields['default_view'] !== ''
				&& $geoloc_parser->parse($this->fields['default_view']) == CParser::PARSE_SUCCESS) {
			$initial_view = $geoloc_parser->result;

			if (array_key_exists('zoom', $initial_view)) {
				$initial_view['zoom'] = min($this->geomap_config['max_zoom'], $initial_view['zoom']);
			}
			else {
				$initial_view['zoom'] = ceil($this->geomap_config['max_zoom'] / 2);
			}

			$home_coords['initial'] = $initial_view;
			if (!$center) {
				$center = $home_coords['initial'];
			}
		}

		$defaults = [
			'latitude' => 0,
			'longitude' => 0,
			'zoom' => 1
		];

		return [
			'center' => $center ? $center : $defaults,
			'home_coords' => $home_coords
		];
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
		return [
			'severity' => CProfile::get('web.dashboard.widget.geomap.severity_filter', [], $this->widgetid)
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
		$geo_json = [];
		foreach ($hosts as $host) {
			$problems = array_filter($host['problems']);
			$severities = array_keys($problems);
			$top_severity = reset($severities);

			$geo_json[] = [
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
		}

		return $geo_json;
	}
}
