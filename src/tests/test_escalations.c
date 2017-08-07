#include "zbxtests.h"

#include <scripts.h>
#include <escalator.h>
#include <actions.h>

#include "../zabbix_server/actions.h"
#include "../zabbix_server/poller/checks_agent.h"

#define ZBX_ESCALATION_SOURCE_TRIGGER	2

int	CONFIG_PREPROCMAN_FORKS		= 0;
int	CONFIG_PREPROCESSOR_FORKS	= 0;

#define MAX_DBROWS	16

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
	DB_ROW		dbrow_esc, dbrow_act, dbrow_evt, dbrow_tri, dbrow_opr, dbrow_usr, dbrow_cnd,
			dbrow_prm, dbrow_pr2, dbrow_pr3, dbrow_med, dbrow_ext;
	int		ret, nextcheck = time(NULL),
			*dc_trigger_errcodes, *dc_function_errcodes, *dc_item_errcodes;
	DC_TRIGGER	*dc_triggers;
	DC_FUNCTION	*dc_functions;
	DC_ITEM		*dc_items;

	/* get escalation from db */
	dbrow_esc = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_esc[0] = "1";		/* escalationid */
	dbrow_esc[1] = "1";		/* actionid */
	dbrow_esc[2] = "1";		/* triggerid */
	dbrow_esc[3] = "1";		/* eventid */
	dbrow_esc[4] = "0";		/* r_eventid */
	dbrow_esc[5] = "1501594532";	/* nextcheck */
	dbrow_esc[6] = "0";		/* esc_step
	dbrow_esc[7] = "0";		/* status (0 - ESCALATION_STATUS_ACTIVE) */
	dbrow_esc[8] = "1";		/* itemid */
	dbrow_esc[9] = "0";		/* acknowledgeid */
	will_return(__wrap_zbx_db_fetch, dbrow_esc);
	will_return(__wrap_zbx_db_fetch, NULL);

	/* get action from db */
	dbrow_act = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_act[0] = "1";		/* actionid */
	dbrow_act[1] = "action name";	/* name */
	dbrow_act[2] = "0";		/* status (0 - ACTION_STATUS_ACTIVE) */
	dbrow_act[3] = "0";		/* eventsource (0 - EVENT_SOURCE_TRIGGERS) */
	dbrow_act[4] = "60s";		/* esc_period */
	dbrow_act[5] = "msg subject";	/* def_shortdata */
	dbrow_act[6] = "msg body";	/* def_longdata */
	dbrow_act[7] = "rec subject";	/* r_shortdata */
	dbrow_act[8] = "rec body";	/* r_longdata */
	dbrow_act[9] = "0";		/* maintenance_mode (0 - ACTION_MAINTENANCE_MODE_NORMAL) */
	dbrow_act[10] = "ack subject";	/* ack_shortdata */
	dbrow_act[11] = "ack body";	/* ack_longdata */
	will_return(__wrap_zbx_db_fetch, dbrow_act);
	will_return(__wrap_zbx_db_fetch, NULL);

	/* mark actions if these have rec operations */
	will_return(__wrap_zbx_db_fetch, NULL);

	/* get event from db */
	dbrow_evt = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_evt[0] = "1";		/* eventid */
	dbrow_evt[1] = "0";		/* source (0 - EVENT_SOURCE_TRIGGERS) */
	dbrow_evt[2] = "0";		/* object (0 - EVENT_OBJECT_TRIGGER) */
	dbrow_evt[3] = "1";		/* objectid */
	dbrow_evt[4] = "1501594532";	/* clock */
	dbrow_evt[5] = "1";		/* value (1 - TRIGGER_VALUE_PROBLEM) */
	dbrow_evt[6] = "0";		/* acknowledged (0 - EVENT_NOT_ACKNOWLEDGED) */
	dbrow_evt[7] = "100";		/* Nanoseconds */
	will_return(__wrap_zbx_db_fetch, dbrow_evt);
	will_return(__wrap_zbx_db_fetch, NULL);

	/* get tags from db */
	will_return(__wrap_zbx_db_fetch, NULL);

	/* get trigger from db */
	dbrow_tri = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_tri[0] = "1";		/* triggerid */
	dbrow_tri[1] = "";		/* description */
	dbrow_tri[2] = "{1}=0";		/* expression */
	dbrow_tri[3] = "2";		/* priority (2 - TRIGGER_SEVERITY_WARNING) */
	dbrow_tri[4] = "1501594532";	/* clock */
	dbrow_tri[5] = "";		/* url */
	dbrow_tri[6] = "";		/* recovery_expression */
	dbrow_tri[7] = "0";		/* recovery_mode (0 - ZBX_RECOVERY_MODE_EXPRESSION) */
	will_return(__wrap_zbx_db_fetch, dbrow_tri);
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
	dbrow_opr = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_opr[0] = "1";		/* operations.operationid */
	dbrow_opr[1] = "0";		/* operationtype (0 - OPERATION_TYPE_MESSAGE) */
	dbrow_opr[2] = "0";		/* esc_period */
	dbrow_opr[3] = "0";		/* evaltype (0 - ACTION_EVAL_TYPE_AND_OR) */
	dbrow_opr[4] = "1";		/* opmessage.operationid */
	dbrow_opr[5] = "0";		/* default_msg */
	dbrow_opr[6] = "";		/* subject */
	dbrow_opr[7] = "";		/* message */
	dbrow_opr[8] = "1";		/* mediatypeid */
	will_return(__wrap_zbx_db_fetch, dbrow_opr);

	/* get operation conditions from db */
	dbrow_cnd = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_cnd[0] = "0";		/* conditiontype */
	dbrow_cnd[1] = "0";		/* operator (0 - CONDITION_OPERATOR_EQUAL) */
	dbrow_cnd[2] = "0";		/* value (0 - EVENT_NOT_ACKNOWLEDGED) */
	will_return(__wrap_zbx_db_fetch, dbrow_cnd);
	will_return(__wrap_zbx_db_fetch, NULL);  /* END get operation conditions from db */

	/* get userid from db */
	dbrow_usr = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_usr[0] = "1";		/* userid */
	will_return(__wrap_zbx_db_fetch, dbrow_usr);
	will_return(__wrap_zbx_db_fetch, NULL);

	/* checking user permissions to access system (check_perm2system) */
	dbrow_prm = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_prm[0] = "1";		/* count(*)
	will_return(__wrap_zbx_db_fetch, dbrow_prm);

	/* check user permissions for access to trigger (get_trigger_permission) */
	dbrow_pr2 = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_pr2[0] = "1";		/* hostid
	will_return(__wrap_zbx_db_fetch, dbrow_pr2);

	/* check user permissions for access to the host (get_host_permission) */
	dbrow_pr3 = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_pr3[0] = "3";		/* type (3 - USER_TYPE_SUPER_ADMIN)
	will_return(__wrap_zbx_db_fetch, dbrow_pr3);

	will_return(__wrap_zbx_db_fetch, NULL); /* END check user permissions for access to trigger */

	will_return(__wrap_zbx_db_fetch, NULL); /* END get userid from db */
	will_return(__wrap_zbx_db_fetch, NULL); /* END get operations from db */

	/** get media from db */
	dbrow_med = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));
	dbrow_med[0] = "1";		/* mediatypeid */
	dbrow_med[1] = "zbx@zbx.com";	/* sendto */
	dbrow_med[2] = "2";		/* severity (2 - TRIGGER_SEVERITY_WARNING) */
	dbrow_med[3] = "1-7,00:00-23:59";/* period */
	dbrow_med[4] = "0";		/*status (0 - MEDIA_TYPE_STATUS_ACTIVE) */
	will_return(__wrap_zbx_db_fetch, dbrow_med);
	will_return(__wrap_zbx_db_fetch, NULL);

	ret = process_escalations(time(NULL), &nextcheck, ZBX_ESCALATION_SOURCE_TRIGGER);

	assert_int_equal(ret, 0);
}
