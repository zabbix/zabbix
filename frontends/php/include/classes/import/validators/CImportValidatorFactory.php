<?php

class CImportValidatorFactory extends CRegistryFactory {

	public function __construct($format) {
		parent::__construct([
			'1.0' => function () use ($format) {
				return new C10XmlValidator($format);
			},
			'2.0' => function () use ($format) {
				return new C20XmlValidator($format);
			},
			'3.0' => function () use ($format) {
				return new C30XmlValidator($format);
			},
			'3.2' => function () use ($format) {
				return new C32XmlValidator($format);
			},
			'3.4' => function () use ($format) {
				return new C34XmlValidator($format);
			},
			'4.0' => function () use ($format) {
				return new C40XmlValidator($format);
			},
			'4.2' => function () use ($format) {
				return new C42XmlValidator($format);
			},
			'4.4' => function () use ($format) {
				return new C44XmlValidator($format);
			}
		]);
	}
}
