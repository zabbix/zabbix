<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * This class can be used for any pre-processing required for API calls made from the frontend.
 */
class CFrontendApiWrapper extends CApiWrapper {

	/**
	 * Currently used API.
	 *
	 * @var string
	 */
	public $api;

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
	 * A magic method for calling the public methods of the API client.
	 *
	 * @param string 	$method		API method name
	 * @param array 	$params		API method parameters
	 *
	 * @return CApiResponse
	 */
	public function __call($method, array $params) {
		$response = $this->callMethod($this->api.'.'.$method, reset($params));

		if ($response->isError()) {
			return false;
		}

		return $response->getResult();
	}

	/**
	 * Call the API method with profiling.
	 *
	 * If the API call has been unsuccessful - return only the result value.
	 * If the API call has been unsuccessful - add an error message and return false, instead of an array.
	 */
	public function callMethod($method, array $params) {
		API::setWrapper();
		$response = parent::callMethod($method, $params);
		API::setWrapper($this);

		// call profiling
		if ($this->debug) {
			$this->profiler->profileApiCall($method, $params, $response->getResponseData());
		}

		if ($response->isError()) {
			// add an error message
			$trace = $response->getErrorData();
			if ($response->getDebug()) {
				$trace .= ' ['.$this->profiler->formatCallStack($response->getDebug()).']';
			}
			error($trace);
		}

		return $response;
	}
}
