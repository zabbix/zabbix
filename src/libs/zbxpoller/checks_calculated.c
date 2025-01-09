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

#include "checks_calculated.h"

#include "zbxexpression.h"
#include "zbxeval.h"
#include "zbxtime.h"
#include "zbxvariant.h"
#include "zbxdbwrap.h"

int	get_value_calculated(zbx_dc_item_t *dc_item, AGENT_RESULT *result)
{
	int			ret = NOTSUPPORTED;
	char			*error = NULL;
	zbx_eval_context_t	ctx;
	zbx_variant_t		value;
	zbx_timespec_t		ts;
	zbx_expression_eval_t	eval;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' expression:'%s'", __func__, dc_item->key_orig, dc_item->params);

	um_handle = zbx_dc_open_user_macros();

	if (NULL == dc_item->formula_bin)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() serialized formula is not set", __func__);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot evaluate calculated item:"
				" serialized formula is not set"));
		goto out;
	}

	zbx_eval_deserialize(&ctx, dc_item->params, ZBX_EVAL_PARSE_CALC_EXPRESSION, dc_item->formula_bin);

	if (SUCCEED != zbx_eval_substitute_macros(&ctx, &error, zbx_db_trigger_recovery_user_and_func_macro_eval_resolv,
			um_handle, &dc_item->host.hostid, 1))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot evaluate calculated item: %s", error));
		zbx_free(error);
		zbx_eval_clear(&ctx);
		goto out;
	}

	zbx_timespec(&ts);

	zbx_expression_eval_init(&eval, ZBX_EXPRESSION_AGGREGATE, &ctx);
	zbx_expression_eval_resolve_item_hosts(&eval, dc_item);
	zbx_expression_eval_resolve_filter_macros(&eval, dc_item);

	if (SUCCEED != zbx_expression_eval_execute(&eval, &ts, &value, &error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() error:%s", __func__, error);
		SET_MSG_RESULT(result, error);
		error = NULL;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() value:%s", __func__, zbx_variant_value_desc(&value));

		switch (value.type)
		{
			case ZBX_VARIANT_DBL:
				SET_DBL_RESULT(result, value.data.dbl);
				break;
			case ZBX_VARIANT_UI64:
				SET_UI64_RESULT(result, value.data.ui64);
				break;
			case ZBX_VARIANT_STR:
				SET_TEXT_RESULT(result, value.data.str);
				break;
			default:
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "unsupported calculated value result \"%s\""
						" of type \"%s\"", zbx_variant_value_desc(&value),
						zbx_variant_type_desc(&value)));
				zbx_variant_clear(&value);
				break;
		}

		if (ZBX_VARIANT_NONE != value.type)
		{
			zbx_variant_set_none(&value);
			ret = SUCCEED;
		}
	}

	zbx_expression_eval_clear(&eval);
	zbx_eval_clear(&ctx);
	zbx_variant_clear(&value);
out:
	zbx_dc_close_user_macros(um_handle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
