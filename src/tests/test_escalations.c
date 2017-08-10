/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "zbxtests.h"

#include <scripts.h>
#include <escalator.h>
#include <actions.h>

#include "../zabbix_server/actions.h"
#include "../zabbix_server/poller/checks_agent.h"

#define ZBX_ESCALATION_SOURCE_TRIGGER	2

int	CONFIG_PREPROCMAN_FORKS		= 0;
int	CONFIG_PREPROCESSOR_FORKS	= 0;

DB_RESULT __wrap_zbx_db_vselect(const char *fmt, va_list args)
{
	return NULL;
}

DB_ROW __wrap_zbx_db_fetch(DB_RESULT result)
{
	return (DB_ROW) mock();
}

int	__wrap___zbx_DBexecute(const char *fmt, ...)
{
	return 0;
}

void	__wrap_DBbegin(void)
{
}

void	__wrap_DBcommit(void)
{
}

int	__wrap_DBexecute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids)
{
	return SUCCEED;
}

void	__wrap_DCconfig_get_triggers_by_triggerids(DC_TRIGGER *triggers, const zbx_uint64_t *triggerids, int *errcode,
		size_t num)
{
	DC_TRIGGER 	*in_trigger = mock_ptr_type(DC_TRIGGER *);
	int		*in_errcode = mock_ptr_type(int *);

	triggers[0] = *in_trigger;
	errcode[0] = *in_errcode;
}

void	__wrap_DCconfig_get_functions_by_functionids(DC_FUNCTION *functions, zbx_uint64_t *functionids, int *errcodes,
		size_t num)
{
	DC_FUNCTION	*in_function = mock_ptr_type(DC_FUNCTION *);
	int		*in_errcode = mock_ptr_type(int *);

	functions[0] = *in_function;
	errcodes[0] = *in_errcode;
}

void	__wrap_DCconfig_get_items_by_itemids(DC_ITEM *items, const zbx_uint64_t *itemids, int *errcodes, size_t num,
		zbx_uint64_t flags)
{
	DC_ITEM		*in_item = mock_ptr_type(DC_ITEM *);
	int		*in_errcode = mock_ptr_type(int *);

	items[0] = *in_item;
	errcodes[0] = *in_errcode;
}

int	__wrap_DCconfig_check_trigger_dependencies(zbx_uint64_t triggerid)
{
	return SUCCEED;
}

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, char **data, int macro_type, char *error, int maxerrlen)
{
	return SUCCEED;
}

int	__wrap_check_action_condition(const DB_EVENT *event, DB_CONDITION *condition)
{
	return SUCCEED;
}

void test_successful_process_escalations()
{
	const char	*case_name = "test_successful_process_escalations";

	DB_ROW		dbrow_usr, dbrow_cnd, dbrow_prm, dbrow_pr2, dbrow_pr3, dbrow_med, dbrow_ext;
	int		ret, nextcheck = time(NULL),
			*dc_trigger_errcodes, *dc_function_errcodes, *dc_item_errcodes;
	DC_TRIGGER	*dc_triggers;
	DC_FUNCTION	*dc_functions;
	DC_ITEM		*dc_items;

	/* get escalation from db */
	will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "escalations"));
	will_return(__wrap_zbx_db_fetch, NULL);

	/* get action from db */
	will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "actions"));
	will_return(__wrap_zbx_db_fetch, NULL);

	/* mark actions if these have rec operations */
	will_return(__wrap_zbx_db_fetch, NULL);

	/* get event from db */
	will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "events"));
	will_return(__wrap_zbx_db_fetch, NULL);

	/* get tags from db */
	will_return(__wrap_zbx_db_fetch, NULL);

	/* get trigger from db */
	will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "triggers"));
	will_return(__wrap_zbx_db_fetch, NULL);

	/* get trigger from cache */
	dc_triggers = (DC_TRIGGER *)zbx_malloc(NULL, sizeof(DC_TRIGGER));
	dc_triggers[0].triggerid = 1;
	dc_triggers[0].status = TRIGGER_STATUS_ENABLED;
	dc_triggers[0].expression_orig = zbx_strdup(NULL, "{1}=0");
	dc_trigger_errcodes = (int *)zbx_malloc(NULL, sizeof(int));
	dc_trigger_errcodes[0] = SUCCEED;
	will_return(__wrap_DCconfig_get_triggers_by_triggerids, dc_triggers);
	will_return(__wrap_DCconfig_get_triggers_by_triggerids, dc_trigger_errcodes);

	/* get function from cache */
	dc_functions = (DC_FUNCTION *)zbx_malloc(NULL, sizeof(DC_FUNCTION));
	dc_functions[0].functionid = 1;
	dc_functions[0].itemid = 1;
	dc_functions[0].function = zbx_strdup(NULL, "last");
	dc_functions[0].parameter = zbx_strdup(NULL, "");
	dc_function_errcodes = (int *)zbx_malloc(NULL, sizeof(int));
	dc_function_errcodes[0] = SUCCEED;
	will_return(__wrap_DCconfig_get_functions_by_functionids, dc_functions);
	will_return(__wrap_DCconfig_get_functions_by_functionids, dc_function_errcodes);

	/* get item from cache */
	dc_items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM));
	dc_items[0].itemid = 1;
	dc_items[0].status = ITEM_STATUS_ACTIVE;
	dc_items[0].host.status = HOST_STATUS_MONITORED;
	dc_item_errcodes = (int *)zbx_malloc(NULL, sizeof(int));
	dc_item_errcodes[0] = SUCCEED;
	will_return(__wrap_DCconfig_get_items_by_itemids, dc_items);
	will_return(__wrap_DCconfig_get_items_by_itemids, dc_item_errcodes);

	/* get operations from db */
	will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "operations_opmessage"));

	/* get operation conditions from db */
	will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "opmessage_usr"));
	will_return(__wrap_zbx_db_fetch, NULL);  /* END get operation conditions from db */

	/* get userid from db */
	will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "userid"));
	will_return(__wrap_zbx_db_fetch, NULL);

	/* checking user permissions to access system (check_perm2system) */
	will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "user_permissions_access_system"));

		/* check user permissions for access to trigger (get_trigger_permission) */
		will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "user_permissions_access_trigger"));

			/* check user permissions for access to the host (get_host_permission) */
			will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "user_permissions_access_host"));
			will_return(__wrap_zbx_db_fetch, NULL); /* END check user permissions for access to trigger */

		will_return(__wrap_zbx_db_fetch, NULL); /* END get userid from db */

	will_return(__wrap_zbx_db_fetch, NULL); /* END get operations from db */

	/** get media from db */
	will_return(__wrap_zbx_db_fetch, get_db_data(case_name, "media"));
	will_return(__wrap_zbx_db_fetch, NULL);

	ret = process_escalations(time(NULL), &nextcheck, ZBX_ESCALATION_SOURCE_TRIGGER);

	assert_int_equal(ret, 0);
}
