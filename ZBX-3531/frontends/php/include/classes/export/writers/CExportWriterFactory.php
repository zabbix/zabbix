<?php

class CExportWriterFactory {
	const XML = 'XmlWriter';
	const JSON = 'JsonWriter';

	public static function getWriter($type) {
		switch ($type) {
			case 'DOM':
				return new CDomExportWriter();
			case 'XmlWriter':
				return new CXmlExportWriterA();
			case 'JsonWriter':
				return new CJsonExportWriter();
			default:
				throw new Exception('Incorrect export writer type.');
		}
	}
}
