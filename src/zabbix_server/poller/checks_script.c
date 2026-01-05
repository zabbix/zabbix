/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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
#include "log.h"

int	get_value_script(DC_ITEM *item, AGENT_RESULT *result)
{
	char		*error = NULL, *script_bin = NULL, *output = NULL;
	int		script_bin_sz, timeout_seconds, ret = NOTSUPPORTED;
	zbx_es_t	es_engine;

	if (FAIL == is_time_suffix(item->timeout, &timeout_seconds, strlen(item->timeout)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid timeout: %s", item->timeout));
		return ret;
	}

	zbx_es_init(&es_engine);

	if (SUCCEED != zbx_es_init_env(&es_engine, &error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot initialize scripting environment: %s", error));
		return ret;
	}

	if (SUCCEED != zbx_es_compile(&es_engine, item->params, &script_bin, &script_bin_sz, &error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot compile script: %s", error));
		goto err;
	}

	zbx_es_set_timeout(&es_engine, timeout_seconds);

	if (SUCCEED != zbx_es_execute(&es_engine, NULL, script_bin, script_bin_sz, item->script_params, &output,
			&error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot execute script: %s", error));
		goto err;
	}

	ret = SUCCEED;
	SET_TEXT_RESULT(result, NULL != output ? output : zbx_strdup(NULL, ""));
err:
	zbx_es_destroy(&es_engine);
	zbx_free(script_bin);
	zbx_free(error);

	return ret;
}
