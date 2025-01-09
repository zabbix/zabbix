<?php declare(strict_types = 0);
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


namespace Widgets\Geomap\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CGeomapCoordinatesParser,
	CParser,
	CProfile,
	CSettingsHelper,
	CSeverityHelper;

class WidgetView extends CControllerDashboardWidgetView {

	private const NO_PROBLEMS_MARKER_COLOR = '#009900';

	protected string $widgetid;

	/**
	 * Global geomap configuration.
	 *
	 * @param array
	 */
	protected array $geomap_config;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'initial_load' => 'in 0,1',
			'widgetid' => 'db widget.widgetid',
			'unique_id' => 'required|string'
		]);
	}

	protected function doAction(): void {
		$this->widgetid = $this->getInput('widgetid', 0);

		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
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
				'severities' => self::getSeveritySettings()
			];
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * Get hosts and their properties to show on the map as markers.
	 */
	private function getHosts(): array {
		if ($this->isTemplateDashboard()) {
			if ($this->fields_values['override_hostid']) {
				$hosts = API::Host()->get([
					'output' => ['hostid', 'name'],
					'selectInventory' => ['location_lat', 'location_lon'],
					'hostids' => $this->fields_values['override_hostid'],
					'filter' => [
						'inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]
					],
					'monitored_hosts' => true,
					'preservekeys' => true
				]);
			}
			else {
				return [];
			}
		}
		else {
			$filter_groupids = $this->fields_values['groupids'] ? getSubGroups($this->fields_values['groupids']) : null;

			$hosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'selectInventory' => ['location_lat', 'location_lon'],
				'groupids' => $filter_groupids,
				'hostids' => $this->fields_values['hostids'] ?: null,
				'evaltype' => $this->fields_values['evaltype'],
				'tags' => $this->fields_values['tags'],
				'filter' => [
					'inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]
				],
				'monitored_hosts' => true,
				'preservekeys' => true
			]);
		}

		$hosts = array_filter($hosts, static function ($host) {
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
			'objectids' => array_keys($triggers),
			'symptom' => false
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
			$hosts = array_filter($hosts, static function ($host) use ($severity_filter, $problems_by_host) {
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
	 */
	private function getMapCenter(): array {
		$geoloc_parser = new CGeomapCoordinatesParser();
		$home_coords = [];
		$center = [];

		$user_default_view = CProfile::get('web.dashboard.widget.geomap.default_view', '', $this->widgetid);
		if ($user_default_view !== '' && $geoloc_parser->parse($user_default_view) == CParser::PARSE_SUCCESS) {
			$home_coords['default'] = true;
			$center = $geoloc_parser->result;
			$center['zoom'] = min($this->geomap_config['max_zoom'], $center['zoom']);
		}

		if (array_key_exists('default_view', $this->fields_values)
				&& $this->fields_values['default_view'] !== ''
				&& $geoloc_parser->parse($this->fields_values['default_view']) == CParser::PARSE_SUCCESS) {
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
			'center' => $center ?: $defaults,
			'home_coords' => $home_coords
		];
	}

	private function getUserProfileFilter(): array {
		return [
			'severity' => CProfile::get('web.dashboard.widget.geomap.severity_filter', [], $this->widgetid)
		];
	}

	/**
	 * Get global map configuration.
	 */
	private static function getMapConfig(): array {
		if (CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_PROVIDER) === '') {
			return [
				'tile_url' => CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_URL),
				'max_zoom' => CSettingsHelper::get(CSettingsHelper::GEOMAPS_MAX_ZOOM),
				'attribution' => htmlspecialchars(CSettingsHelper::get(CSettingsHelper::GEOMAPS_ATTRIBUTION),
					ENT_NOQUOTES, 'UTF-8'
				)
			];
		}

		$tile_provider = getTileProviders()[CSettingsHelper::get(CSettingsHelper::GEOMAPS_TILE_PROVIDER)];

		return [
			'tile_url' => $tile_provider['geomaps_tile_url'],
			'max_zoom' => $tile_provider['geomaps_max_zoom'],
			'attribution' => $tile_provider['geomaps_attribution']
		];
	}

	/**
	 * Get severity-related settings.
	 */
	private static function getSeveritySettings(): array {
		$severity_config = [
			-1 => [
				'name' => _('No problems'),
				'color' => self::NO_PROBLEMS_MARKER_COLOR,
				'style' => ''
			]
		];

		$severities = CSeverityHelper::getSeverities();

		foreach ($severities as $severity) {
			$severity_config[$severity['value']] = [
				'name' => $severity['label'],
				'color' => '#'.CSeverityHelper::getColor($severity['value']),
				'style' => $severity['style']
			];
		}

		return $severity_config;
	}

	/**
	 * Convert array of hosts to valid GeoJSON (RFC7946) object.
	 */
	private static function convertToRFC7946(array $hosts) : array {
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
