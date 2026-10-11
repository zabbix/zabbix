/*
** Copyright (C) 2001-2026 Zabbix SIA
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

/* zbx_close_inactive_host() and zbx_delete_inactive_ipmi_hosts() are static/exported respectively, and     */
/* checks_ipmi.c is included directly to reach the static one and the 'hosts' list they share.              */
/*                                                                                                          */
/* Every host's domain-close attempt goes through ipmi_domain_iterate_domains()/ipmi_domain_close(),        */
/* wrapped below so 'in.domain_found' can drive both branches without a live OpenIPMI domain:               */
/* "no" leaves __wrap_ipmi_domain_iterate_domains() a no-op, exactly matching a real process with zero      */
/* open domains - the "domain not found" branch zbx_close_inactive_host() itself documents as legitimate.   */
/* "yes" makes it report a match, driving the full zbx_get_domain_id_by_name_cb() -> ipmi_domain_pointer_cb */
/* () -> zbx_domain_close_cb() -> ipmi_domain_close() -> zbx_domain_closed_cb() chain end to end. That      */
/* chain only completes if zbx_get_domain_id_by_name_cb() treats its cb_data as the domain name being       */
/* searched for (not as a host token), and zbx_domain_close_cb()/zbx_domain_closed_cb() can still resolve   */
/* their ZBX_IPMI_HOST_CB_DATA() token to the host - i.e. the host must still be linked into 'hosts' while  */
/* its domain is being closed. Otherwise the OpenIPMI domain is silently leaked instead of closed.          */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

void	__wrap_ipmi_domain_iterate_domains(ipmi_domain_ptr_cb handler, void *cb_data);
int	__wrap_ipmi_domain_get_name(ipmi_domain_t *domain, char *name, int length);
ipmi_domain_id_t	__wrap_ipmi_domain_convert_to_id(ipmi_domain_t *domain);
int	__wrap_ipmi_domain_pointer_cb(ipmi_domain_id_t id, ipmi_domain_ptr_cb handler, void *cb_data);
int	__wrap_ipmi_domain_close(ipmi_domain_t *domain, ipmi_domain_close_done_cb close_done, void *cb_data);

static void	*wrap_iterate_cb_data;
static int	domain_found;
static int	domain_pointer_cb_calls;
static int	domain_close_calls;

void	__wrap_ipmi_domain_iterate_domains(ipmi_domain_ptr_cb handler, void *cb_data)
{
	wrap_iterate_cb_data = cb_data;

	/* a real process with zero open domains never invokes 'handler' at all - see the file header comment */
	if (0 != domain_found)
		handler((ipmi_domain_t *)0x1, cb_data);
}

int	__wrap_ipmi_domain_get_name(ipmi_domain_t *domain, char *name, int length)
{
	ZBX_UNUSED(domain);

	/* only reached when domain_found is set, so echoing back the exact name being searched for            */
	/* (ipmi_domain_iterate_domains()'s own cb_data) simulates "found"                                     */
	zbx_strlcpy(name, (const char *)wrap_iterate_cb_data, (size_t)length);

	return (int)strlen(name);
}

ipmi_domain_id_t	__wrap_ipmi_domain_convert_to_id(ipmi_domain_t *domain)
{
	ipmi_domain_id_t	id;

	ZBX_UNUSED(domain);
	memset(&id, 0, sizeof(id));

	return id;
}

int	__wrap_ipmi_domain_pointer_cb(ipmi_domain_id_t id, ipmi_domain_ptr_cb handler, void *cb_data)
{
	ZBX_UNUSED(id);

	domain_pointer_cb_calls++;
	handler((ipmi_domain_t *)0x1, cb_data);

	return 0;
}

int	__wrap_ipmi_domain_close(ipmi_domain_t *domain, ipmi_domain_close_done_cb close_done, void *cb_data)
{
	ZBX_UNUSED(domain);

	domain_close_calls++;

	/* invoke the completion callback synchronously so zbx_perform_openipmi_ops() sees h->done already set */
	/* and never touches the (uninitialized, in this test process) os_hnd event loop                       */
	close_done(cb_data);

	return 0;
}

static void	allocate_host_from_yaml(zbx_mock_handle_t hhost, time_t now)
{
	zbx_ipmi_host_t	*h;

	h = zbx_allocate_ipmi_host(zbx_mock_get_object_member_string(hhost, "ip"), 623, 2, 4, "user", "pass");
	h->lastaccess = now - zbx_mock_get_object_member_int(hhost, "lastaccess_hours_ago") * SEC_PER_HOUR;
}

void	zbx_mock_test_entry(void **state)
{
	const char		*op = zbx_mock_get_parameter_string("in.op");
	time_t			now = time(NULL);
	zbx_mock_handle_t	hhosts, hhost;
	zbx_ipmi_host_t		*h;

	ZBX_UNUSED(state);

	domain_found = (0 == strcmp(zbx_mock_get_parameter_string("in.domain_found"), "yes")) ? 1 : 0;
	domain_pointer_cb_calls = 0;
	domain_close_calls = 0;

	hhosts = zbx_mock_get_parameter_handle("in.hosts");
	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(hhosts, &hhost))
		allocate_host_from_yaml(hhost, now);

	if (0 == strcmp(op, "close_single"))
	{
		int	ret;

		h = hosts;

		if (NULL == h || NULL != h->next)
			fail_msg("'close_single' expects exactly one host in 'in.hosts'");

		/* 'h' must stay in 'hosts' for the duration of the call: zbx_domain_close_cb() (reached         */
		/* synchronously below when domain_found is "yes") resolves its cb_data back to a host via       */
		/* RETURN_IF_CB_DATA_INVALID(), which looks 'h' up in 'hosts' by domain_nr                       */
		ret = zbx_close_inactive_host(h);	/* frees h on SUCCEED - see comment below */

		zbx_mock_assert_int_eq("zbx_close_inactive_host() return value",
				zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.close_ret")), ret);
		zbx_mock_assert_int_eq("ipmi_domain_pointer_cb() call count",
				zbx_mock_get_parameter_int("out.domain_pointer_cb_calls"), domain_pointer_cb_calls);
		zbx_mock_assert_int_eq("ipmi_domain_close() call count",
				zbx_mock_get_parameter_int("out.domain_close_calls"), domain_close_calls);

		/* zbx_close_inactive_host() never unlinks 'h' from 'hosts' itself - that is the caller's job    */
		/* (zbx_delete_inactive_ipmi_hosts() does it after a SUCCEED). On SUCCEED 'h' is already freed,  */
		/* so the now-dangling list head is dropped here without being dereferenced                      */
		hosts = NULL;

		if (SUCCEED != ret)
			zbx_free_ipmi_host(h);
	}
	else if (0 == strcmp(op, "sweep"))
	{
		zbx_mock_handle_t	hips, hip;
		zbx_vector_str_t	exp_ips;
		int			hosts_num = 0;

		zbx_vector_str_create(&exp_ips);
		hips = zbx_mock_get_parameter_handle("out.surviving_ips");
		while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(hips, &hip))
		{
			const char	*ip;

			zbx_mock_string(hip, &ip);
			zbx_vector_str_append(&exp_ips, zbx_strdup(NULL, ip));
		}

		zbx_delete_inactive_ipmi_hosts(now);

		for (h = hosts; NULL != h; h = h->next)
		{
			hosts_num++;

			if (FAIL == zbx_vector_str_search(&exp_ips, h->ip, ZBX_DEFAULT_STR_COMPARE_FUNC))
				fail_msg("host '%s' survived the sweep but was not expected to", h->ip);
		}

		zbx_mock_assert_int_eq("hosts remaining in 'hosts'", exp_ips.values_num, hosts_num);

		/* the sweep can close a stale host's domain too - assert the same call counts as 'close_single' */
		/* whenever the test case cares to specify them, so 'domain_found: yes' proves the domain was    */
		/* actually closed here as well, not just that the host left 'hosts' (which a "domain not found" */
		/* result causes too, without any domain ever being closed)                                      */
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.domain_pointer_cb_calls"))
		{
			zbx_mock_assert_int_eq("ipmi_domain_pointer_cb() call count",
					zbx_mock_get_parameter_int("out.domain_pointer_cb_calls"),
					domain_pointer_cb_calls);
		}
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.domain_close_calls"))
		{
			zbx_mock_assert_int_eq("ipmi_domain_close() call count",
					zbx_mock_get_parameter_int("out.domain_close_calls"), domain_close_calls);
		}

		for (int i = 0; i < exp_ips.values_num; i++)
			zbx_free(exp_ips.values[i]);
		zbx_vector_str_destroy(&exp_ips);

		/* free whatever the sweep left behind */
		while (NULL != (h = hosts))
		{
			hosts = h->next;
			zbx_free_ipmi_host(h);
		}
	}
	else
		fail_msg("unknown 'in.op' value: %s", op);
}
