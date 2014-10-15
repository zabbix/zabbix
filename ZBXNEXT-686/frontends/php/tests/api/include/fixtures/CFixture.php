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

}
