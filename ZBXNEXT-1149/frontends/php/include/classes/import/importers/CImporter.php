<?php

abstract class CImporter {

	/**
	 * @var CImportReferencer
	 */
	protected $referencer;

	/**
	 * @var array
	 */
	protected $options = array();

	/**
	 * @param array             $options
	 * @param CImportReferencer $referencer
	 */
	public function __construct(array $options, CImportReferencer $referencer) {
		$this->options = $options;
		$this->referencer = $referencer;
	}

	/**
	 * @abstract
	 *
	 * @param array $elements
	 *
	 * @return mixed
	 */
	abstract public function import(array $elements);
}
