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

/* zbx_close_inactive_host() and zbx_delete_inactive_ipmi_hosts() are static/exported respectively, and   */
/* checks_ipmi.c is included directly to reach the static one and the 'hostset' they share.               */
/*                                                                                                          */
/* No IPMI domain is ever opened in this test process, so ipmi_domain_iterate_domains() (a real, unmocked  */
/* OpenIPMI call) always finds zero domains - the exact "domain not found" branch zbx_close_inactive_host() */
/* itself documents as a legitimate case ("Zabbix allocated an IPMI host during network problem and the    */
/* domain was closed... but the host is still in our list"). That branch frees the host directly without   */
/* touching any OpenIPMI sensor/control/domain object, so it's fully exercised here without a live BMC.    */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

/* Wraps for the "domain found by name" path, unreachable above without a live OpenIPMI domain. They let    */
/* zbx_get_domain_id_by_name_cb(), ipmi_domain_pointer_cb() and ipmi_domain_close() be driven end-to-end, in */
/* particular proving that a domain found by ipmi_domain_iterate_domains() is actually passed to             */
/* ipmi_domain_close() - the exact step that a RETURN_IF_CB_DATA_INVALID() misuse in                         */
/* zbx_get_domain_id_by_name_cb() used to skip, silently leaking the OpenIPMI domain instead of closing it.  */
static void	*wrap_iterate_cb_data;
static int	wrap_domain_pointer_cb_calls;
static int	wrap_domain_close_calls;

void	__wrap_ipmi_domain_iterate_domains(ipmi_domain_ptr_cb handler, void *cb_data)
{
	wrap_iterate_cb_data = cb_data;

	/* the domain pointer itself is never dereferenced by __wrap_ipmi_domain_get_name() below, so any */
	/* non-NULL value stands in for a real ipmi_domain_t* */
	handler((ipmi_domain_t *)0x1, cb_data);
}

int	__wrap_ipmi_domain_get_name(ipmi_domain_t *domain, char *name, int length)
{
	ZBX_UNUSED(domain);

	/* echo back the exact name zbx_close_inactive_host() is searching for, simulating "domain found" */
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

	wrap_domain_pointer_cb_calls++;
	handler((ipmi_domain_t *)0x1, cb_data);

	return 0;
}

int	__wrap_ipmi_domain_close(ipmi_domain_t *domain, ipmi_domain_close_done_cb close_done, void *cb_data)
{
	ZBX_UNUSED(domain);

	wrap_domain_close_calls++;

	/* invoke the completion callback synchronously so zbx_perform_openipmi_ops() sees h->done already set  */
	/* and never touches the (uninitialized, in this test process) os_hnd event loop */
	close_done(cb_data);

	return 0;
}

void	zbx_mock_test_entry(void **state)
{
	const char	*scenario = zbx_mock_get_parameter_string("in.scenario");

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	if (0 == strcmp(scenario, "close_directly"))
	{
		zbx_ipmi_host_t	*h = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");
		int		ret = zbx_close_inactive_host(h);	/* frees h on SUCCEED - domain not found */

		zbx_mock_assert_int_eq("zbx_close_inactive_host() return value", SUCCEED, ret);

		/* zbx_close_inactive_host() only frees the host, it never removes it from 'hostset' - that's   */
		/* the caller's job (zbx_delete_inactive_ipmi_hosts() does it via zbx_hashset_iter_remove()) */
		zbx_hashset_remove(&hostset, &h);
	}
	else if (0 == strcmp(scenario, "delete_inactive"))
	{
		time_t	now = time(NULL);
		zbx_ipmi_host_t	*stale = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");
		zbx_ipmi_host_t	*fresh = zbx_allocate_ipmi_host("127.0.0.2", 623, 2, 4, "user", "pass");

		stale->lastaccess = now - 4 * SEC_PER_HOUR;	/* older than the 3h INACTIVE_HOST_LIMIT */
		fresh->lastaccess = now;

		zbx_delete_inactive_ipmi_hosts(now);

		zbx_mock_assert_int_eq("hosts remaining in hostset", 1, hostset.num_data);

		if (NULL == zbx_hashset_search(&hostset, &fresh))
			fail_msg("the recently-active host should still be in 'hostset'");
		if (NULL != zbx_hashset_search(&hostset, &stale))
			fail_msg("the inactive host should have been removed from 'hostset'");

		zbx_free_ipmi_host(fresh);
		zbx_hashset_remove(&hostset, &fresh);
	}
	else if (0 == strcmp(scenario, "boundary_not_deleted"))
	{
		time_t	now = time(NULL);
		/* exactly at INACTIVE_HOST_LIMIT (3h) - the check is "last_check - lastaccess > LIMIT", so a */
		/* host exactly at the boundary must survive */
		zbx_ipmi_host_t	*h = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");

		h->lastaccess = now - 3 * SEC_PER_HOUR;

		zbx_delete_inactive_ipmi_hosts(now);

		zbx_mock_assert_int_eq("hosts remaining in hostset", 1, hostset.num_data);
		if (NULL == zbx_hashset_search(&hostset, &h))
			fail_msg("a host exactly at the inactivity boundary should not have been removed");

		zbx_free_ipmi_host(h);
		zbx_hashset_remove(&hostset, &h);
	}
	else if (0 == strcmp(scenario, "multiple_stale"))
	{
		time_t	now = time(NULL);
		zbx_ipmi_host_t	*stale1 = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");
		zbx_ipmi_host_t	*stale2 = zbx_allocate_ipmi_host("127.0.0.2", 623, 2, 4, "user", "pass");
		zbx_ipmi_host_t	*fresh = zbx_allocate_ipmi_host("127.0.0.3", 623, 2, 4, "user", "pass");

		stale1->lastaccess = now - 4 * SEC_PER_HOUR;
		stale2->lastaccess = now - 10 * SEC_PER_HOUR;
		fresh->lastaccess = now;

		zbx_delete_inactive_ipmi_hosts(now);

		zbx_mock_assert_int_eq("hosts remaining in hostset", 1, hostset.num_data);
		if (NULL == zbx_hashset_search(&hostset, &fresh))
			fail_msg("the recently-active host should still be in 'hostset'");

		zbx_free_ipmi_host(fresh);
		zbx_hashset_remove(&hostset, &fresh);
	}
	else if (0 == strcmp(scenario, "domain_found_and_closed"))
	{
		zbx_ipmi_host_t	*h = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");
		int		ret;

		wrap_domain_pointer_cb_calls = 0;
		wrap_domain_close_calls = 0;

		ret = zbx_close_inactive_host(h);

		zbx_mock_assert_int_eq("zbx_close_inactive_host() return value", SUCCEED, ret);

		/* the whole point of this scenario: a domain found by name must actually be looked up and closed, */
		/* not silently skipped as it was while zbx_get_domain_id_by_name_cb() misused                     */
		/* RETURN_IF_CB_DATA_INVALID() on a cb_data that was never a zbx_ipmi_host_t* */
		zbx_mock_assert_int_eq("ipmi_domain_pointer_cb() call count", 1, wrap_domain_pointer_cb_calls);
		zbx_mock_assert_int_eq("ipmi_domain_close() call count", 1, wrap_domain_close_calls);

		zbx_hashset_remove(&hostset, &h);
	}
	else
		fail_msg("unknown 'in.scenario' value: %s", scenario);

	zbx_hashset_destroy(&hostset);
}
