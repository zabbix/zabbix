<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CXmlTagPreprocessing extends CXmlTagAbstract
{
	protected $tag = 'preprocessing';

	public function __construct(array $schema = [])
	{
		$schema += [
			'params' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'type' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED,
				'range' => [
					CXmlDefine::MULTIPLIER => 'MULTIPLIER',
					CXmlDefine::RTRIM => 'RTRIM',
					CXmlDefine::LTRIM => 'LTRIM',
					CXmlDefine::TRIM => 'TRIM',
					CXmlDefine::REGEX => 'REGEX',
					CXmlDefine::BOOL_TO_DECIMAL => 'BOOL_TO_DECIMAL',
					CXmlDefine::OCTAL_TO_DECIMAL => 'OCTAL_TO_DECIMAL',
					CXmlDefine::HEX_TO_DECIMAL => 'HEX_TO_DECIMAL',
					CXmlDefine::SIMPLE_CHANGE => 'SIMPLE_CHANGE',
					CXmlDefine::CHANGE_PER_SECOND => 'CHANGE_PER_SECOND',
					CXmlDefine::XMLPATH => 'XMLPATH',
					CXmlDefine::JSONPATH => 'JSONPATH',
					CXmlDefine::IN_RANGE => 'IN_RANGE',
					CXmlDefine::MATCHES_REGEX => 'MATCHES_REGEX',
					CXmlDefine::NOT_MATCHES_REGEX => 'NOT_MATCHES_REGEX',
					CXmlDefine::CHECK_JSON_ERROR => 'CHECK_JSON_ERROR',
					CXmlDefine::CHECK_XML_ERROR => 'CHECK_XML_ERROR',
					CXmlDefine::CHECK_REGEX_ERROR => 'CHECK_REGEX_ERROR',
					CXmlDefine::DISCARD_UNCHANGED => 'DISCARD_UNCHANGED',
					CXmlDefine::DISCARD_UNCHANGED_HEARTBEAT => 'DISCARD_UNCHANGED_HEARTBEAT',
					CXmlDefine::JAVASCRIPT => 'JAVASCRIPT',
					CXmlDefine::PROMETHEUS_PATTERN => 'PROMETHEUS_PATTERN',
					CXmlDefine::PROMETHEUS_TO_JSON => 'PROMETHEUS_TO_JSON'
				]
			],
			'error_handler' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::ORIGINAL_ERROR,
				'range' => [
					CXmlDefine::ORIGINAL_ERROR => 'ORIGINAL_ERROR',
					CXmlDefine::DISCARD_VALUE => 'DISCARD_VALUE',
					CXmlDefine::CUSTOM_VALUE => 'CUSTOM_VALUE',
					CXmlDefine::CUSTOM_ERROR => 'CUSTOM_ERROR'
				]
			],
			'error_handler_params' => [
				'type' => CXmlDefine::STRING
			]
		];

		$this->schema = $schema;
	}
}
