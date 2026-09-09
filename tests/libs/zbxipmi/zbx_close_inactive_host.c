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

/* zbx_close_inactive_host() and zbx_delete_inactive_ipmi_hosts() are static/exported respectively, and    */
/* checks_ipmi.c is included directly to reach the static one and the 'hostset' they share.                */
/*                                                                                                          */
/* Every host's domain-close attempt goes through ipmi_domain_iterate_domains()/ipmi_domain_close(),        */
/* wrapped below so 'in.domain_found' can drive both branches without a live OpenIPMI domain:               */
/* "no" leaves __wrap_ipmi_domain_iterate_domains() a no-op, exactly matching a real process with zero      */
/* open domains - the "domain not found" branch zbx_close_inactive_host() itself documents as legitimate.   */
/* "yes" makes it report a match, driving the full zbx_get_domain_id_by_name_cb() -> ipmi_domain_pointer_cb */
/* () -> zbx_domain_close_cb() -> ipmi_domain_close() chain end to end - the exact path a                   */
/* RETURN_IF_CB_DATA_INVALID() misuse in zbx_get_domain_id_by_name_cb() used to skip, silently leaking the  */
/* OpenIPMI domain instead of closing it.                                                                  */
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

	/* only reached when domain_found is set, so echoing back the exact name being searched for */
	/* (ipmi_domain_iterate_domains()'s own cb_data) simulates "found" */
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
	/* and never touches the (uninitialized, in this test process) os_hnd event loop */
	close_done(cb_data);

	return 0;
}

static zbx_ipmi_host_t	*allocate_host_from_yaml(zbx_mock_handle_t hhost, time_t now)
{
	zbx_ipmi_host_t	*h;

	h = zbx_allocate_ipmi_host(zbx_mock_get_object_member_string(hhost, "ip"), 623, 2, 4, "user", "pass");
	h->lastaccess = now - zbx_mock_get_object_member_int(hhost, "lastaccess_hours_ago") * SEC_PER_HOUR;

	return h;
}

void	zbx_mock_test_entry(void **state)
{
	const char		*op = zbx_mock_get_parameter_string("in.op");
	time_t			now = time(NULL);
	zbx_mock_handle_t	hhosts, hhost;
	zbx_vector_ptr_t	hosts;

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, zbx_ipmi_hostset_hash_func, zbx_ipmi_hostset_compare_func);
	zbx_vector_ptr_create(&hosts);

	domain_found = (0 == strcmp(zbx_mock_get_parameter_string("in.domain_found"), "yes")) ? 1 : 0;
	domain_pointer_cb_calls = 0;
	domain_close_calls = 0;

	hhosts = zbx_mock_get_parameter_handle("in.hosts");
	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(hhosts, &hhost))
		zbx_vector_ptr_append(&hosts, allocate_host_from_yaml(hhost, now));

	if (0 == strcmp(op, "close_single"))
	{
		zbx_ipmi_host_t		*h = (zbx_ipmi_host_t *)hosts.values[0];
		zbx_hashset_iter_t	iter;
		int			ret;

		/* 'h' must stay in 'hostset' for the duration of the call: zbx_domain_close_cb() (reached          */
		/* synchronously below when domain_found is "yes") resolves its cb_data back to a host via          */
		/* RETURN_IF_CB_DATA_INVALID(), which looks 'h' up in 'hostset' by domain_nr.                       */
		ret = zbx_close_inactive_host(h);	/* frees h on SUCCEED - see comment below */

		zbx_mock_assert_int_eq("zbx_close_inactive_host() return value",
				zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.close_ret")), ret);
		zbx_mock_assert_int_eq("ipmi_domain_pointer_cb() call count",
				zbx_mock_get_parameter_int("out.domain_pointer_cb_calls"), domain_pointer_cb_calls);
		zbx_mock_assert_int_eq("ipmi_domain_close() call count",
				zbx_mock_get_parameter_int("out.domain_close_calls"), domain_close_calls);

		if (SUCCEED != ret)
			zbx_free_ipmi_host(h);

		/* zbx_close_inactive_host() never removes 'h' from 'hostset' itself - that's the caller's job      */
		/* (zbx_delete_inactive_ipmi_hosts() does it via zbx_hashset_iter_remove()). By this point 'h' is   */
		/* freed either way (above, or internally on SUCCEED), so the removal below goes through the same   */
		/* iterator-based zbx_hashset_iter_remove(): unlike zbx_hashset_remove(), it never calls            */
		/* compare_func() - i.e. never dereferences the now-dangling stored copy of 'h' - since the         */
		/* iterator already knows the entry's slot directly.                                                */
		zbx_hashset_iter_reset(&hostset, &iter);
		if (NULL != zbx_hashset_iter_next(&iter))
			zbx_hashset_iter_remove(&iter);
	}
	else if (0 == strcmp(op, "sweep"))
	{
		zbx_mock_handle_t	hips, hip;
		zbx_vector_str_t	exp_ips;
		zbx_hashset_iter_t	iter;
		zbx_ipmi_host_t		**phost;

		zbx_vector_str_create(&exp_ips);
		hips = zbx_mock_get_parameter_handle("out.surviving_ips");
		while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(hips, &hip))
		{
			const char	*ip;

			zbx_mock_string(hip, &ip);
			zbx_vector_str_append(&exp_ips, zbx_strdup(NULL, ip));
		}

		zbx_delete_inactive_ipmi_hosts(now);

		zbx_mock_assert_int_eq("hosts remaining in hostset", exp_ips.values_num, (int)hostset.num_data);

		/* the sweep can close a stale host's domain too - assert the same call counts as 'close_single' */
		/* whenever the test case cares to specify them, so 'domain_found: yes' proves the domain was    */
		/* actually closed here as well, not just that the host left 'hostset' (which happens either way, */
		/* via the buggy "domain not found" fallback, even when the domain never gets closed for real)   */
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

		zbx_hashset_iter_reset(&hostset, &iter);
		while (NULL != (phost = (zbx_ipmi_host_t **)zbx_hashset_iter_next(&iter)))
		{
			if (FAIL == zbx_vector_str_search(&exp_ips, (*phost)->ip, ZBX_DEFAULT_STR_COMPARE_FUNC))
				fail_msg("host '%s' survived the sweep but was not expected to", (*phost)->ip);
		}

		for (int i = 0; i < exp_ips.values_num; i++)
			zbx_free(exp_ips.values[i]);
		zbx_vector_str_destroy(&exp_ips);

		/* free whatever the sweep left behind */
		zbx_hashset_iter_reset(&hostset, &iter);
		while (NULL != (phost = (zbx_ipmi_host_t **)zbx_hashset_iter_next(&iter)))
		{
			zbx_free_ipmi_host(*phost);
			zbx_hashset_iter_remove(&iter);
		}
	}
	else
		fail_msg("unknown 'in.op' value: %s", op);

	zbx_vector_ptr_destroy(&hosts);
	zbx_hashset_destroy(&hostset);
}
