<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


use PHPUnit\Framework\TestCase;

class CUrlValidatorTest extends TestCase {

	public function dataProvider(): array {
		return [
			// Valid URLs.
			['http',													[],															null],
			['http://zabbix.com',										[],															null],
			['https://zabbix.com',										[],															null],
			['http://localhost',										[],															null],
			['http://192.168.1.1',										[],															null],
			['http://localhost/file.php',								[],															null],
			['http://localhost/file.html',								[],															null],
			['http://localhost/file',									[],															null],
			['http://zabbix.php',										[],															null],
			['http://hello/world/hosts.html?abc=123',					[],															null],
			['http:/zabbix.php',										[],															null], // Because we allow tel:1-111-111-1111 and "/zabbix.php" is a valid path which falls in same category.
			['http:localost',											[],															null], // Because we allow tel:1-111-111-1111 and "localost" is a valid path which falls in same category.
			['http:/localost',											[],															null], // Because we allow tel:1-111-111-1111 and "/localost" is a valid path which falls in same category.
			['http/',													[],															null], // Because "http/" is a valid relative path.
			['http:/localhost/zabbix.php',								[],															null], // Because we allow tel:1-111-111-1111 and "/localhost/zabbix.php" is a valid path which falls in same category.
			['http:myhost/zabbix.php',									[],															null], // Because we allow tel:1-111-111-1111 and "myhost/zabbix.php" is a valid path which falls in same category.
			['localhost',												[],															null],
			['notzabbix.php',											[],															null],
			['zabbix.php',												[],															null],
			['hosts.html',												[],															null],
			['/secret/.htaccess',										[],															null], // No file type restrictions.
			['/zabbix.php',												[],															null],
			['subdir/zabbix.php',										[],															null],
			['subdir/hosts/id/10084',									[],															null],
			['subdir/'.'/100500/',										[],															null], // Comment hook does not allow "//".
			['zabbix.php/..',											[],															null],
			['hosts/..php',												[],															null],
			['subdir1/../subdir2/../subdir3/',							[],															null],
			['subdir1/subdir2/zabbix.php',								[],															null],
			['192.168.1.1.',											[],															null], // Not a valid IP, but it is accepted as "path".
			['zabbix.php?a=1',											[],															null],
			['zabbix.php?action=image.list',							[],															null],
			['chart_bar.php?a=1&b=2',									[],															null],
			['mailto:example@example.com',								[],															null],
			['file://localhost/path',									[],															null],
			['tel:1-111-111-1111',										[],															null],
			['ssh://username@hostname:/path ',							[],															null],
			['/chart_bar.php?a=1&b=2',									[],															null],
			['http://localhost:{$PORT}',								['user_macro' => true],										null], // Macros allowed.
			['http://localhost:{MANUALINPUT}',							['manualinput_macro' => true],								null], // Manual input macro allowed.
			['http://{$INVALID!MACRO}',									['user_macro' => true],										null], // Macros allowed, but it's not a valid macro.
			['/',														[],															null], // "/" is a valid path to home directory.
			['/../',													[],															null],
			['../',														[],															null],
			['/..',														[],															null],
			['../././not_so_zabbix',									[],															null],
			['jav&#x09;ascript:alert(1];',								[],															null], // "jav" is a valid path with everything else in "fragment".
			['ftp://user@host:21',										[],															null],
			['ftp://somehost',											[],															null],
			['ftp://user@host',											[],															null],
			['{$USER_URL_MACRO}',										['user_macro' => true],										null],
			['{$USER_URL_MACRO}?a=1',									['user_macro' => true],										null],
			['http://{$USER_URL_MACRO}?a=1',							['user_macro' => true],										null],
			['http://{$USER_URL_MACRO}',								['user_macro' => true],										null],
			['http://{{$M}.regsub("(.*)", \1)}',						['user_macro' => true],										null],
			['http://{{{$USER_URL_MACRO}',								['user_macro' => true],										null],
			['http://{$MACRO{$MACRO}}',									['user_macro' => true],										null],
			['{$MACRO{',												['user_macro' => true],										null],
			["\x00\x20https://zabbix.com\x1F\x20",						[],															null], // Leading and trailing C0 control and space characters are ignored by browsers.
			["h\tt\rt\nps://zabbix.com",								[],															null], // CR, LF and TAB characters are ignored by browsers.
			['ht tps://zabbix.com',										[],															null], // URL with spaces in schema is treated as a path.
			// Inventory macros are going to be considered as "path".
			['{INVENTORY.URL.A}',										['inventory_macro' => INVENTORY_URL_MACRO_NONE],			null],
			['{INVENTORY.URL.A}',										['inventory_macro' => INVENTORY_URL_MACRO_HOST],			null],
			['{INVENTORY.URL.A}',										['inventory_macro' => INVENTORY_URL_MACRO_TRIGGER],			null],
			['{INVENTORY.URL.A1}',										['inventory_macro' => INVENTORY_URL_MACRO_HOST],			null],
			['{INVENTORY.URL.A1}',										['inventory_macro' => INVENTORY_URL_MACRO_TRIGGER],			null],
			['{{INVENTORY.URL.B}.regsub("(\d+)", \1)}',					['inventory_macro' => INVENTORY_URL_MACRO_HOST],			null],
			// Event tag macros are going to be considered as "path".
			['text{EVENT.TAGS."JIRAID"}text',							[],															null],
			['text{EVENT.TAGS."JIRAID"}text',							['event_tags_macro' => true],								null],
			// Macros not allowed.
			['http://{$USER_URL_MACRO}',								[],															null], // User macros not allowed, but it's a host.
			['{$USER_URL_MACRO}',										[],															null],
			['{INVENTORY.URL.A}',										[],															null],
			['http://localhost/{$USER_URL_MACRO}/',						[],															null], // User macros not allowed, but it's a subdir.
			['http://localhost/zabbix.php?hostid={$ID}',				[],															null], // User macros not allowed, but it's in query.
			['http://localhost/zabbix.php?hostid=1#comment={$COMMENT}',	[],															null],
			['http://localhost/{NOT_AUSER_MACRO}/',						[],															null], // User macros not allowed, but it's not a macro.
			['http://localhost?host={HOST.NAME}',						[],															null],
			// Custom schemes.
			['relative_path',											['schemes' => ['http', 'https']],							null], // Scheme is not present in the URL, therefore, not validated.
			['https://zabbix.com',										['schemes' => ['http', 'https']],							null],
			['http://localhost',										['schemes' => ['http', 'https']],							null],
			['javascript:alert()',										['schemes' => null],										null],
			['file:/',													[],															null], // Allowed scheme with a path.
			['//example.com/path',										[],															null], // Scheme is not present in the URL, therefore, not validated.
			// Invalid URLs.
			['http:?abc',												[],															'unacceptable URL'], // Scheme with no host.
			['http://',													[],															'unacceptable URL'], // url_parse() returns false.
			['http:///',												[],															'unacceptable URL'], // url_parse() returns false.
			['http:',													[],															'unacceptable URL'], // Scheme with no host.
			['http://?',												[],															'unacceptable URL'], // url_parse() returns false.
			["\x00\x20javascript:alert(1)\x1F\x20",						['schemes' => ['http', 'https']],							'unacceptable URL scheme'], // Invalid scheme. Leading and trailing C0 control and space characters are ignored by browsers.
			["ja\tva\rsc\nript:alert(1)",								['schemes' => ['http', 'https']],							'unacceptable URL scheme'], // Invalid scheme. CR, LF and TAB characters are ignored by browsers.
			['javascript:alert(]',										['schemes' => ['http', 'https']],							'unacceptable URL scheme'], // Invalid scheme.
			['protocol://{$INVALID!MACRO}',								['user_macro' => true, 'schemes' => ['http', 'https']],		'unacceptable URL scheme'], // Invalid scheme. Also macro is not valid, but that's secondary.
			['',														[],															'unacceptable URL'], // Cannot be empty.
			['ftp://user@host:port',									[],															'unacceptable URL'], // Invalid port number.
			['vbscript:msgbox(]',										['schemes' => ['http', 'https']],							'unacceptable URL scheme'], // Invalid scheme.
			['notexist://localhost',									['schemes' => ['http', 'https']],							'unacceptable URL scheme'], // Invalid scheme.
			['http://localhost:{$PORT}',								[],															'unacceptable URL'], // User macro not allowed.
			['http://localhost:{MANUALINPUT}',							[],															'unacceptable URL'], // Manual input macro not allowed.
			['javascript:alert()',										['schemes' => ['http', 'https']],							'unacceptable URL scheme'], // Invalid scheme.
			['ftp://user@host:port',									['schemes' => ['http', 'https']],							'unacceptable URL'] // Invalid scheme.
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testUrlValidator($url, $options, $expected_error) {
		$validator = new CUrlValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($url));
		$this->assertSame($expected_error, $validator->getError());
	}
}
