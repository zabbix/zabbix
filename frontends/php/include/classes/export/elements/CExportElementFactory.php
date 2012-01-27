<?php

class CExportElementFactory {

	public static function getElement($type) {
		switch ($type) {
			case 'xml':
				return new CDomExportWriter();
			default:
				throw new InvalidArgumentException('Incorrect export element type.');
		}
	}
}
