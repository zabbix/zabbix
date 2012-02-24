<?php

class CExportWriterFactory {
	const XML = 'xml';
	const JSON = 'json';

	public static function getWriter($type) {
		switch ($type) {
			case 'DOM':
				return new CDomExportWriter();
			case self::XML:
				return new CXmlExportWriterA();
			case self::JSON:
				return new CJsonExportWriter();
			default:
				throw new Exception('Incorrect export writer type.');
		}
	}
}
