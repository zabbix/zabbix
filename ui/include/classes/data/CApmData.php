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


class CApmData {

	public static function getClickHouseDbSchema(): array {
		return [
			'otel_traces' => [
				'Timestamp'				=> ['type' => 'DateTime64(9)'],
				'TraceId'				=> ['type' => 'String'],
				'SpanId'				=> ['type' => 'String'],
				'ParentSpanId'			=> ['type' => 'String'],
				'TraceState'			=> ['type' => 'String'],
				'SpanName'				=> ['type' => 'String'],
				'SpanKind'				=> ['type' => 'String'],
				'ServiceName'			=> ['type' => 'String'],
				'ResourceAttributes'	=> ['type' => 'Map'],
				'ScopeName'				=> ['type' => 'String'],
				'ScopeVersion'			=> ['type' => 'String'],
				'SpanAttributes'		=> ['type' => 'Map'],
				'Duration'				=> ['type' => 'Int64'],
				'StatusCode'			=> ['type' => 'String'],
				'StatusMessage'			=> ['type' => 'String'],
				'Events.Timestamp'		=> ['type' => 'Array'],
				'Events.Name'			=> ['type' => 'Array'],
				'Events.Attributes'		=> ['type' => 'Array'],
				'Links.TraceId'			=> ['type' => 'Array'],
				'Links.SpanId'			=> ['type' => 'Array'],
				'Links.TraceState'		=> ['type' => 'Array'],
				'Links.Attributes'		=> ['type' => 'Array']
			]
		];
	}
}
