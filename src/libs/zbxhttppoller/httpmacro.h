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

#ifndef ZABBIX_HTTPMACRO_H
#define ZABBIX_HTTPMACRO_H

#include "zbxdbhigh.h"

#include "zbxalgo.h"

typedef struct
{
	zbx_db_httptest		httptest;
	char			*headers;
	zbx_vector_ptr_pair_t	variables;
	/* httptest macro cache consisting of (key, value) pair array */
	zbx_vector_ptr_pair_t	macros;
}
zbx_httptest_t;

typedef struct
{
	zbx_db_httpstep		*httpstep;
	zbx_httptest_t		*httptest;

	char			*url;
	char			*headers;
	char			*posts;

	zbx_vector_ptr_pair_t	variables;
}
zbx_httpstep_t;

void	http_variable_urlencode(const char *source, char **result);
int	http_substitute_variables(const zbx_httptest_t *httptest, char **data);
int	http_process_variables(zbx_httptest_t *httptest, zbx_vector_ptr_pair_t *variables, const char *data,
		char **err_str);

#endif
