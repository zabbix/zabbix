/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

#include "checks_script.h"
#include "zbxembed.h"

int	get_value_script(DC_ITEM *item, AGENT_RESULT *result)
{
	char		*error = NULL, *script_bin = NULL, *output = NULL;
	int		script_bin_sz, ret;
	zbx_es_t	es;

	SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set follow redirects: %s", error));
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize scripting environment"));

	zbx_es_init(&es);

	if (SUCCEED != zbx_es_init_env(&es, &error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot initialize scripting environment: %s", error));
		return FAIL;
	}

	if (SUCCEED != (ret = zbx_es_compile(&es, item->params, &script_bin, &script_bin_sz, &error)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot compile script: %s", error));
		goto err;
	}

	zbx_es_set_timeout(&es, atoi(item->timeout));

	if (SUCCEED != (ret = zbx_es_execute(&es, NULL, script_bin, script_bin_sz, item->script_params, &output,
			&error)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot execute script: %s", error));
		goto err;
	}

	SET_TEXT_RESULT(result, output);
err:
	zbx_free(script_bin);
	zbx_es_destroy(&es);

	return ret;
}
