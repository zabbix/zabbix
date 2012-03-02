<?php

class CExportWriterFactory {
	const XML = 'xml';
	const JSON = 'json';

	public static function getWriter($type) {
		switch ($type) {
			case self::XML:
				return new CXmlExportWriter();

			case self::JSON:
				return new CJsonExportWriter();

			default:
				throw new Exception('Incorrect export writer type.');
		}
	}
}
