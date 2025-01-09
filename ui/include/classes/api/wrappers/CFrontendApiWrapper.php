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


/**
 * This class can be used for any pre-processing required for API calls made from the frontend.
 */
class CFrontendApiWrapper extends CApiWrapper {

	/**
	 * Whether to enable debug mode.
	 *
	 * @var bool
	 */
	public $debug = false;

	/**
	 * The profiler class used for profiling API calls.
	 *
	 * @var CProfiler
	 */
	protected $profiler;

	/**
	 * Set the profiler class.
	 *
	 * @param CProfiler $profiler
	 */
	public function setProfiler(CProfiler $profiler) {
		$this->profiler = $profiler;
	}

	/**
	 * Call the API method with profiling.
	 *
	 * If the API call has been unsuccessful - return only the result value.
	 * If the API call has been unsuccessful - add an error message and return false, instead of an array.
	 *
	 * @param string 	$method
	 * @param array 	$params
	 *
	 * @return mixed
	 */
	protected function callMethod($method, array $params) {
		API::setWrapper();
		$response = parent::callMethod($method, $params);
		API::setWrapper($this);

		// call profiling
		if ($this->debug) {
			$this->profiler->profileApiCall($this->api, $method, $params, $response->data);
		}

		if (!$response->errorCode) {
			return $response->data;
		}
		else {
			// add an error message
			$trace = $response->errorMessage;
			if ($response->debug) {
				$trace .= ' ['.$this->profiler->formatCallStack($response->debug).']';
			}
			error($trace);

			return false;
		}
	}
}
