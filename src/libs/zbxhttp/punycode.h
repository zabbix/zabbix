/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
