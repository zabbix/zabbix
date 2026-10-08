<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


/**
 * A helper class for working with Elasticsearch.
 */
class CElasticsearchHelper {

	const MAX_RESULT_WINDOW = 10000;
	const KEEP_CONTEXT_PERIOD = '10s';
	const DATE_FORMAT = 'epoch_second';

	private static $scroll_id;
	private static $scrolls;

	/**
	 * Perform request to Elasticsearch.
	 * Return empty string for failed request.
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

		$request = $request ? json_encode($request) : '{}';
		$options['http']['content'] = $request;

		$result = file_get_contents($endpoint, false, stream_context_create($options));

		CProfiler::getInstance()->profileElasticsearch(microtime(true) - $time_start,
			parse_url($endpoint, PHP_URL_PATH).' '.$request
		);

		return $result === false ? '' : $result;
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

		for ($i = 0; $i < 2; $i++) {
			if (($pos = strrpos($url, '/')) !== false) {
				$url = substr($url, 0, $pos);
			}
			else {
				// Endpoint is in different format, no way to get scroll API url.
				error(_s('Elasticsearch error: %1$s.', _('cannot perform Scroll API request, data could be truncated')),
					true
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
		$is_search = str_ends_with($endpoint, '/_search');
		$parse_as = ELASTICSEARCH_RESPONSE_PLAIN;

		// For non-search requests no additional parsing is done.
		if ($is_search) {
			$parse_as = ELASTICSEARCH_RESPONSE_DOCUMENTS;

			if (is_array($request) && array_key_exists('aggs', $request)) {
				$parse_as = (array_key_exists('size', $request) && $request['size'] == 0)
						? ELASTICSEARCH_RESPONSE_AGGREGATION : ELASTICSEARCH_RESPONSE_PLAIN;
			}
		}

		if ($is_search && is_array($request)
				&& (!array_key_exists('size', $request) || $request['size'] > self::MAX_RESULT_WINDOW)) {
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
			error(_s('Elasticsearch error: %1$s.', _('failed to parse JSON')), true);

			return [];
		}

		if (array_key_exists('error', $result)) {
			$error = (is_array($result['error']) && array_key_exists('reason', $result['error']))
					? $result['error']['reason']
					: _('Unknown error');

			error(_s('Elasticsearch error: %1$s.', $error), true);

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
						if (!array_key_exists('_source', $row) && !array_key_exists('fields', $row)) {
							continue;
						}

						if (array_key_exists('fields', $row)) {
							foreach ($row['fields'] as $name => $value) {
								// Elasticsearch returns field values as arrays and first element holds the value.
								$row['_source'][$name] = $value[0];
							}
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
	 * @param array $schema   DB schema
	 * @param array $query    Elasticsearch query.
	 * @param array $options  Filtering options.
	 *
	 * @return array    Elasticsearch query with added filtering
	 */
	public static function addFilter($schema, $query, $options) {
		$clauses = [];

		foreach ($options['filter'] as $field => $value) {
			if ($schema['fields'][$field]['type'] & (DB::FIELD_TYPE_CHAR | DB::FIELD_TYPE_TEXT)) {
				continue;
			}

			$clauses[] = ['terms' => [$field => $value]];
		}

		if ($clauses) {
			$query['query']['bool']['must'][] = $options['searchByAny']
				? ['bool' => ['should' => $clauses, 'minimum_should_match' => 1]]
				: ['bool' => ['must' => $clauses]];
		}

		return $query;
	}

	/**
	 * Add search criteria to Elasticsearch query.
	 *
	 * @param string $table    Table name
	 * @param array  $query    Elasticsearch query
	 * @param array  $options  search options
	 *
	 * @return array    Elasticsearch query with added search criteria
	 */
	public static function addSearch($table, $query, $options) {
		$schema = DB::getSchema($table);
		$start = $options['startSearch'] ? '' : '*';
		$clauses = [];

		foreach ($options['search'] as $field => $value) {
			if (($schema['fields'][$field]['type'] & (DB::FIELD_TYPE_CHAR | DB::FIELD_TYPE_TEXT)) == 0) {
				continue;
			}

			if ($field === 'value' && in_array($table, ['history_str', 'history_text', 'history_log'])) {
				$field .= '.analyzed';
			}

			foreach ($value as $phrase) {
				$phrase = str_replace('?', '\\?', $phrase);

				if (!$options['searchWildcardsEnabled']) {
					$phrase = $start.str_replace('*', '\\*', $phrase).'*';
				}

				$clauses[] = ['wildcard' => [$field => mb_strtolower($phrase)]];
			}
		}

		if ($clauses) {
			$query['query']['bool'][$options['excludeSearch'] ? 'must_not' : 'must'][] = $options['searchByAny']
				? ['bool' => ['should' => $clauses, 'minimum_should_match' => 1]]
				: ['bool' => ['must' => $clauses]];
		}

		return $query;
	}

	/**
	 * Add sorting criteria to Elasticsearch query.
	 *
	 * @param array $query    Elasticsearch query.
	 * @param array $options  Sorting options.
	 *
	 * @return array    Elasticsearch query with added sorting options
	 */
	public static function addSort($query, $options) {
		foreach ($options['sortfield'] as $i => $sortfield) {
			$query['sort'][$sortfield] = is_array($options['sortorder'])
				? $options['sortorder'][$i] ?? ZBX_SORT_UP
				: $options['sortorder'];
		}

		return $query;
	}

	/**
	 * Convert numeric values of an Elasticsearch document to strings as they are returned by SQL databases.
	 * The "value" field of float items is converted to the shortest representation that preserves the value, because
	 * Zabbix server stores it in Elasticsearch with 17 significant digits.
	 *
	 * @param array $document    Document fields.
	 * @param int   $value_type  Item value type.
	 *
	 * @return array
	 */
	public static function normalizeDocument(array $document, int $value_type): array {
		foreach ($document as $field => &$value) {
			if ($field === 'value' && $value_type == ITEM_VALUE_TYPE_FLOAT) {
				$value = self::floatToString((float) $value);
			}
			elseif (is_int($value) || is_float($value)) {
				$value = (string) $value;
			}
		}
		unset($value);

		return $document;
	}

	/**
	 * Convert a floating point number to the shortest string that preserves the value.
	 *
	 * @param float $value
	 *
	 * @return string
	 */
	public static function floatToString(float $value): string {
		return json_encode($value);
	}

	/**
	 * Returns an Elasticsearch Painless script that truncates a string field
	 * to the given number of Unicode code points.
	 *
	 * @param string $field   Source field name.
	 * @param int    $length  Maximum length in code points.
	 *
	 * @return array Script configuration for Elasticsearch.
	 */
	public static function getSubstring($field, $length): array {
		return [
			'script' => [
				'lang' => 'painless',
				'source' => <<<'SCRIPT'
					def string;
					string = params._source[params.field].toString();
					int string_length = string.codePointCount(0, string.length());

					if (string_length > params.len) {
						return string.substring(0, string.offsetByCodePoints(0, params.len));
					} else {
						return string;
					}
				SCRIPT,
				'params' => [
					'len' => (int) $length,
					'field' => $field
				]
			]
		];
	}
}
