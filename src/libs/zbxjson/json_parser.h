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

#ifndef ZABBIX_JSON_PARSER_H
#define ZABBIX_JSON_PARSER_H

#include "jsonobj.h"

#include "zbxjson.h"

zbx_int64_t	zbx_json_validate(const char *start, char **error);

zbx_int64_t	json_parse_value(const char *start, zbx_jsonobj_t *obj, int depth, char **error);

zbx_int64_t	json_error(const char *message, const char *ptr, char **error);

zbx_int64_t	json_parse_object(const char *start, zbx_jsonobj_t *obj, int depth, char **error);
zbx_int64_t	json_parse_array(const char *start, zbx_jsonobj_t *obj, int depth, char **error);

#endif
