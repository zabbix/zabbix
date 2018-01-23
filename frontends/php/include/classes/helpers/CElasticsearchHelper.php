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

/**
 * A helper class for working with Elasticsearch.
 */
class CElasticsearchHelper {

	const MAX_RESULT_WINDOW = 10000;
	const KEEP_CONTEXT_PERIOD = '10s';

	private static $scroll_id;
	private static $scrolls;

	/**
	 * Perform request to Elasticsearch.
	 *
	 * @param string $method      HTTP method to be used to perform request
	 * @param string $endpoint    requested url
	 * @param mixed  $request     data to be sent
	 *
	 * @return string    result
	 */
	private static function request($method, $endpoint, $request = null) {
		$time_start = microtime(true);
		$options = [
			'http' => [
				'header'  => "Content-Type: application/json; charset=UTF-8",
				'method'  => $method,
				'ignore_errors' => true // To get error messages from Elasticsearch.
			]
		];

		if ($request) {
			$request = json_encode($request);
			$options['http']['content'] = $request;
		}

		try {
			$result = file_get_contents($endpoint, false, stream_context_create($options));
		}
		catch (Exception $e) {
			error($e->getMessage());
		}

		CProfiler::getInstance()->profileElasticsearch(microtime(true) - $time_start, $method, $endpoint, $request);

		return $result;
	}

	/**
	 * Get Elasticsearch endpoint for scroll API requests.
	 * Endpoint should be in following format: <Elasticsearch url>/<indices>/<values>/<action><query string>.
	 *
	 * @param string $endpoint    endpoint of the initial request
	 *
	 * @return array    parsed result
	 */
	private static function getScrollApiEndpoint($endpoint) {
		$url = $endpoint;

		for ($i = 0; $i < 3; $i++) {
			if (($pos = strrpos($url, '/')) !== false) {
				$url = substr($url, 0, $pos);
			}
			else {
				// Endpoint is in different format, no way to get scroll API url.
				error(_s('Elasticsearch error: %1$s.',
						_('cannot perform Scroll API request, data could be truncated'))
				);

				return null;
			}
		}

		return $url.'/_search/scroll';
	}

	/**
	 * Perform request(s) to Elasticsearch and parse the results.
	 *
	 * @param string $method      HTTP method to be used to perform request
	 * @param string $endpoint    requested url
	 * @param mixed  $request     data to be sent
	 *
	 * @return array    parsed result
	 */
	public static function query($method, $endpoint, $request = null) {
		$parse_as = ELASTICSEARCH_RESPONSE_PLAIN;

		// For non-search requests no additional parsing is done.
		if (substr($endpoint, -strlen('/_search')) === '/_search') {
			$parse_as = ELASTICSEARCH_RESPONSE_DOCUMENTS;

			if (is_array($request) && array_key_exists('aggs', $request)) {
				$parse_as = (array_key_exists('size', $request) && $request['size'] == 0)
						? ELASTICSEARCH_RESPONSE_AGGREGATION : ELASTICSEARCH_RESPONSE_PLAIN;
			}
		}

		if (is_array($request) && (!array_key_exists('size', $request) || $request['size'] > self::MAX_RESULT_WINDOW)) {
			// Scroll API should be used to retrieve all data.
			$results = [];
			$limit = array_key_exists('size', $request) ? $request['size'] : null;
			$request['size'] = self::MAX_RESULT_WINDOW;
			self::$scroll_id = null;
			self::$scrolls = [];

			$scroll_endpoint = self::getScrollApiEndpoint($endpoint);
			if ($scroll_endpoint !== null) {
				$endpoint .= '?scroll='.self::KEEP_CONTEXT_PERIOD;
			}

			$slice = self::parseResult(self::request($method, $endpoint, $request), $parse_as);
			$results = array_merge($results, $slice);

			if (self::$scroll_id === null) {
				$slice = null; // Reset slice if there is no scroll_id.
			}

			$endpoint = $scroll_endpoint;

			while ($slice) {
				if (count($slice) < self::MAX_RESULT_WINDOW) {
					// No need to continue as there are no more data.
					break;
				}

				$scroll = [
					'scroll' => self::KEEP_CONTEXT_PERIOD,
					'scroll_id' => self::$scroll_id
				];

				$slice = self::parseResult(self::request($method, $endpoint, $scroll), $parse_as);
				$results = array_merge($results, $slice);

				if ($limit !== null && count($results) >= $limit) {
					$results = array_slice($results, 0, $limit);

					// No need to perform additional queries as limit is reached.
					break;
				}
			}

			// Scrolls should be deleted when they are not required anymore.
			if (count(self::$scrolls) > 0) {
				self::request('DELETE', $endpoint, ['scroll_id' => array_keys(self::$scrolls)]);
			}

			return $results;
		}

		return self::parseResult(self::request($method, $endpoint, $request), $parse_as);
	}

	/**
	 * Parse result based on request data.
	 *
	 * @param string $data        result as a string
	 * @param int    $parse_as    result type
	 *
	 * @return array    parsed result
	 */
	private static function parseResult($data, $parse_as) {
		$result = json_decode($data, TRUE);
		if (!is_array($result)) {
			error(_s('Elasticsearch error: %1$s.', _('failed to parse JSON')));

			return [];
		}

		if (array_key_exists('error', $result)) {
			$error = (is_array($result['error']) && array_key_exists('reason', $result['error']))
					? $result['error']['reason']
					: _('Unknown error');

			error(_s('Elasticsearch error: %1$s.', $error));

			return [];
		}

		if (array_key_exists('_scroll_id', $result)) {
			self::$scroll_id = $result['_scroll_id'];
			self::$scrolls[self::$scroll_id] = true;
		}

		switch ($parse_as) {
			// Return aggregations only.
			case ELASTICSEARCH_RESPONSE_AGGREGATION:
				if (array_key_exists('aggregations', $result) && is_array($result['aggregations'])) {
					return $result['aggregations'];
				}
				break;

			// Return documents only.
			case ELASTICSEARCH_RESPONSE_DOCUMENTS:
				if (array_key_exists('hits', $result) && array_key_exists('hits', $result['hits'])) {
					$values = [];

					foreach ($result['hits']['hits'] as $row) {
						if (!array_key_exists('_source', $row)) {
							continue;
						}

						$values[] = $row['_source'];
					}

					return $values;
				}
				break;

			// Return result "as is".
			case ELASTICSEARCH_RESPONSE_PLAIN:
				return $result;
		}

		return [];
	}

	/**
	 * Add filters to Elasticsearch query.
	 *
	 * @param array $schema     DB schema
	 * @param array $query      Elasticsearch query
	 * @param array $options    filtering options
	 *
	 * @return array    Elasticsearch query with added filtering
	 */
	public static function addFilter($schema, $query, $options) {
		foreach ($options['filter'] as $field => $value) {
			// Skip missing fields, textual fields (different mapping is needed for exact matching) and empty values.
			if (!array_key_exists($field, $schema['fields']) || !$value
					|| in_array($schema['fields'][$field]['type'], [DB::FIELD_TYPE_TEXT, DB::FIELD_TYPE_CHAR])) {
				continue;
			}

			zbx_value2array($value);

			if ($options['searchByAny']) {
				$type = 'should';
				$query["minimum_should_match"] = 1;
			}
			else {
				$type = 'must';
			}

			$query['query']['bool'][$type][] = [
				"terms" => [
					$field => array_values($value)
				]
			];
		}

		return $query;
	}

	/**
	 * Add search criteria to Elasticsearch query.
	 *
	 * @param array $schema     DB schema
	 * @param array $query      Elasticsearch query
	 * @param array $options    search options
	 *
	 * @return array    Elasticsearch query with added search criteria
	 */
	public static function addSearch($schema, $query, $options) {
		$start = $options['startSearch'] ? '' : '*';
		$exclude = $options['excludeSearch'] ? 'must_not' : 'must';

		if ($options['searchByAny']) {
			if (!$options['excludeSearch']) {
				$exclude = 'should';
			}

			$query["minimum_should_match"] = 1;
		}

		foreach ($options['search'] as $field => $value) {
			// Skip missing fields, non textual fields and empty values.
			if (!array_key_exists($field, $schema['fields']) || !$value
					|| !in_array($schema['fields'][$field]['type'], [DB::FIELD_TYPE_TEXT, DB::FIELD_TYPE_CHAR])) {
				continue;
			}

			zbx_value2array($value);

			foreach ($value as $phrase) {
				// Skip non scalar values.
				if (!is_scalar($phrase)) {
					continue;
				}

				$phrase = str_replace('?', '\\?', $phrase);

				if (!$options['searchWildcardsEnabled']) {
					$phrase = str_replace('*', '\\*', $phrase);
					$criteria = [
						"wildcard" => [
							$field => $start.$phrase.'*'
						]
					];
				}
				else {
					$criteria = [
						"wildcard" => [
							$field => $phrase
						]
					];
				}

				if ($options['excludeSearch'] && $options['searchByAny']) {
					$query['query']['bool']['must_not']['bool']['should'][] = $criteria;
				}
				else {
					$query['query']['bool'][$exclude][] = $criteria;
				}
			}
		}

		return $query;
	}

	/**
	 * Add sorting criteria to Elasticsearch query.
	 *
	 * @param array $columns    columns that can (are allowed) be used for sorting
	 * @param array $query      Elasticsearch query
	 * @param array $options    sorting options
	 *
	 * @return array    Elasticsearch query with added sorting options
	 */
	public static function addSort($columns, $query, $options) {
		$options['sortfield'] = is_array($options['sortfield'])
				? array_unique($options['sortfield'])
				: [$options['sortfield']];

		foreach ($options['sortfield'] as $i => $sortfield) {
			if (!str_in_array($sortfield, $columns)) {
				throw new APIException(ZBX_API_ERROR_INTERNAL, _s('Sorting by field "%1$s" not allowed.', $sortfield));
			}

			// Add sort field to order.
			$sortorder = '';
			if (is_array($options['sortorder']) && array_key_exists($i, $options['sortorder'])) {
				$sortorder = ($options['sortorder'][$i] == ZBX_SORT_DOWN) ? ZBX_SORT_DOWN : '';
			}
			else {
				$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN) ? ZBX_SORT_DOWN : '';
			}

			if ($sortorder !== '') {
				$query['sort'][$sortfield] = $sortorder;
			}
			else {
				$query['sort'][] = $sortfield;
			}
		}

		return $query;
	}
}
