/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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

#ifndef JSON_PARSER_H_
#define JSON_PARSER_H_

#define STRIP_WHITESPACE(src) \
	while ('\0' != *src && NULL != strchr(ZBX_WHITESPACE, *src)) src++;

int	json_parse_object(const char **start, const char **end, char **error);
int	json_parse_string(const char **start, const char **end, char **error);
int	json_parse_array(const char **start, const char **end, char **error);
int	json_parse_number(const char **start, const char **end, char **error);
int	json_parse_literal(const char **start, const char **end, const char *text, char **error);
int	json_parse_value(const char **start, const char **end, char **error);


#endif /* JSON_PARSER_H_ */
