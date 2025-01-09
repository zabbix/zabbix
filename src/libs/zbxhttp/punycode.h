/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#ifndef ZABBIX_PUNYCODE_H
#define ZABBIX_PUNYCODE_H

#define PUNYCODE_BASE		36
#define PUNYCODE_BASE_MAX	35
#define PUNYCODE_TMIN		1
#define PUNYCODE_TMAX		26
#define PUNYCODE_SKEW		38
#define PUNYCODE_DAMP		700
#define PUNYCODE_INITIAL_N	128
#define PUNYCODE_INITIAL_BIAS	72
#define PUNYCODE_BIAS_LIMIT	(((PUNYCODE_BASE_MAX) * PUNYCODE_TMAX) / 2)
#define PUNYCODE_MAX_UINT32	((zbx_uint32_t)-1)

#endif
