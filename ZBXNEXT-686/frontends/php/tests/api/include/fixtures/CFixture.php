<?php

/**
 * Base class for loading fixtures.
 */
abstract class CFixture {

	/**
	 * @param array $params
	 *
	 * @return array
	 *
	 * @throws InvalidArgumentException if the feature could not be loaded due to misconfiguration
	 * @throws UnexpectedValueException if the feature could not be loaded due to external reasons
	 */
	public abstract function load(array $params);

	/**
	 * Checks that all required parameters are present in $params.
	 *
	 * @param array $params		array of given parameters
	 * @param array $required	array of required parameters
	 *
	 * @throws InvalidArgumentException	if a parameter is missing
	 */
	protected function checkMissingParams(array $params, array $required) {
		foreach ($required as $param) {
			if (!isset($params[$param])) {
				throw new InvalidArgumentException(sprintf('Missing "%1$s" parameter.', $param));
			}
		}
	}

}
