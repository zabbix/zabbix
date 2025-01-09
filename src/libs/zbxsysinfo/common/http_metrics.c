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

#include "http.h"
#include "http_metrics.h"

static zbx_metric_t	parameters_common_http[] =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
{
	{"web.page.get",	CF_HAVEPARAMS,	web_page_get,		"localhost,,80"},
	{"web.page.perf",	CF_HAVEPARAMS,	web_page_perf,		"localhost,,80"},
	{"web.page.regexp",	CF_HAVEPARAMS,	web_page_regexp,	"localhost,,80,OK"},
	{0}
};

zbx_metric_t	*get_parameters_common_http(void)
{
	return &parameters_common_http[0];
}
