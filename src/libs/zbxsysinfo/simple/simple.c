/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "comms.h"
#include "log.h"
#include "cfg.h"

#include "../common/net.h"
#include "ntp.h"

#include "simple.h"

ZBX_METRIC	parameters_simple[] =
	/* KEY                   FLAG            FUNCTION             ADD_PARAM  TEST_PARAM      */
	{
	{"net.tcp.service",	CF_USEUPARAM,	CHECK_SERVICE, 		0,	"ssh,127.0.0.1,22"},
	{"net.tcp.service.perf",CF_USEUPARAM,	CHECK_SERVICE_PERF, 	0,	"ssh,127.0.0.1,22"},
	{0}
	};

#ifdef HAVE_LDAP

static int    check_ldap(const char *host, unsigned short port, int *value_int)
{
	LDAP		*ldap	= NULL;
	LDAPMessage	*res	= NULL;
	LDAPMessage	*msg	= NULL;
	BerElement	*ber	= NULL;

	char	*attrs[2] = { "namingContexts", NULL };

	char	*attr	 = NULL;
	char	**valRes = NULL;

	int	ldapErr = 0;

	assert(NULL != value_int);

	*value_int = 0;

	if (NULL == (ldap = ldap_init(host, port)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - initialization failed [%s:%hu]", host, port);
		return SYSINFO_RET_OK;
	}

	if (LDAP_SUCCESS != (ldapErr = ldap_search_s(ldap, "", LDAP_SCOPE_BASE, "(objectClass=*)", attrs, 0, &res)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - searching failed [%s] [%s]", host, ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	if (NULL == (msg = ldap_first_entry(ldap, res)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - empty sort result. [%s] [%s]", host, ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	attr = ldap_first_attribute(ldap, msg, &ber);
	valRes = ldap_get_values(ldap, msg, attr);

	*value_int = 1;

lbl_ret:
	if (NULL != valRes)
		ldap_value_free(valRes);
	if (NULL != attr)
		ldap_memfree(attr);
	if (NULL != ber)
		ber_free(ber, 0);
	if (NULL != res)
		ldap_msgfree(res);
	if (NULL != ldap)
		ldap_unbind(ldap);

	return SYSINFO_RET_OK;
}

#endif

static int	check_ssh(const char *host, unsigned short port, int *value_int)
{
	int		ret;
	zbx_sock_t	s;
	char		send_buf[MAX_BUFFER_LEN];
	char		*recv_buf;
	char		*ssh_server, *ssh_proto;

	assert(NULL != value_int);

	*value_int = 0;

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, 0)))
	{
		if (SUCCEED == (ret = zbx_tcp_recv(&s, &recv_buf)))
		{
			if (0 == strncmp(recv_buf, "SSH", 3))
			{
				ssh_server = ssh_proto = recv_buf + 4;
				ssh_server += strspn(ssh_proto, "0123456789-. ");
				ssh_server[-1] = '\0';

				zbx_snprintf(send_buf, sizeof(send_buf), "SSH-%s-%s\n", ssh_proto, "zabbix_agent");
				*value_int = 1;
			}
			else
			{
				zbx_snprintf(send_buf, sizeof(send_buf), "0\n");
			}

			ret = zbx_tcp_send_raw(&s, send_buf);
		}
	}

	zbx_tcp_close(&s);

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "SSH check error: %s", zbx_tcp_strerror());

	return SYSINFO_RET_OK;
}

static int	check_service(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result, int perf)
{
	unsigned short	port = 0;
	char		service[MAX_STRING_LEN];
	char		ip[MAX_STRING_LEN];
	char		str_port[MAX_STRING_LEN];

	int		ret = SYSINFO_RET_FAIL;
	int		value_int = 0;
	double		check_time;

	assert(NULL != result);

	init_result(result);

	check_time = zbx_time();

	if (num_param(param) > 3)
	{
		return SYSINFO_RET_FAIL;
	}

	if (0 != get_param(param, 1, service, MAX_STRING_LEN))
	{
		return SYSINFO_RET_FAIL;
	}

	if (0 != get_param(param, 2, ip, MAX_STRING_LEN) || '\0' == *ip)
	{
		strscpy(ip, "127.0.0.1");
	}

	if (0 != get_param(param, 3, str_port, MAX_STRING_LEN) || '\0' == *str_port)
	{
		port = 0;
	}
	else if (FAIL == is_uint(str_port))
	{
		return SYSINFO_RET_FAIL;
	}
	else
		port = (unsigned short)atoi(str_port);

	if (0 == strcmp(service, "ssh"))
	{
		if (0 == port) port = ZBX_DEFAULT_SSH_PORT;
		ret = check_ssh(ip, port, &value_int);
	}
	else if (0 == strcmp(service, "ntp") || 0 == strcmp(service, "service.ntp" /* obsolete */))
	{
		if (0 == port) port = ZBX_DEFAULT_NTP_PORT;
		ret = check_ntp(ip, port, &value_int);
	}
#ifdef HAVE_LDAP
	else if (0 == strcmp(service, "ldap"))
	{
		if (0 == port) port = ZBX_DEFAULT_LDAP_PORT;
		ret = check_ldap(ip, port, &value_int);
	}
#endif
	else if (0 == strcmp(service, "smtp"))
	{
		if (0 == port) port = ZBX_DEFAULT_SMTP_PORT;
		ret = tcp_expect(ip, port, NULL, "220", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "ftp"))
	{
		if (0 == port) port = ZBX_DEFAULT_FTP_PORT;
		ret = tcp_expect(ip, port, NULL, "220", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "http"))
	{
		if (0 == port) port = ZBX_DEFAULT_HTTP_PORT;
		ret = tcp_expect(ip, port, NULL, NULL, NULL, &value_int);
	}
	else if (0 == strcmp(service, "pop"))
	{
		if (0 == port) port = ZBX_DEFAULT_POP_PORT;
		ret = tcp_expect(ip, port, NULL, "+OK", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "nntp"))
	{
		if (0 == port) port = ZBX_DEFAULT_NNTP_PORT;
		ret = tcp_expect(ip, port, NULL, "200", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "imap"))
	{
		if (0 == port) port = ZBX_DEFAULT_IMAP_PORT;
		ret = tcp_expect(ip, port, NULL, "* OK", "a1 LOGOUT\n", &value_int);
	}
	else if (0 == strcmp(service, "tcp"))
	{
		if (0 == port) return SYSINFO_RET_FAIL;
		ret = tcp_expect(ip, port, NULL, NULL, NULL, &value_int);
	}
	else
		return SYSINFO_RET_FAIL;

	if (!perf)
	{
		if (SYSINFO_RET_OK == ret)
		{
			SET_UI64_RESULT(result, value_int);
		}
	}
	else
	{
		if (SYSINFO_RET_OK == ret)
		{
			if (value_int)
			{
				check_time = zbx_time() - check_time;
				check_time = MAX(check_time, 0.0001);
				SET_DBL_RESULT(result, check_time);
			}
			else
				SET_DBL_RESULT(result, 0.0);
		}
	}

	return ret;
}

/* Examples:
 *
 *   net.tcp.service[ssh]
 *   net.tcp.service[smtp,127.0.0.1]
 *   net.tcp.service[ssh,127.0.0.1,22]
 *
 *   net.tcp.service.perf[ssh]
 *   net.tcp.service.perf[smtp,127.0.0.1]
 *   net.tcp.service.perf[ssh,127.0.0.1,22]
 *
 * The old name for these checks is check_service[*].
 */

int	CHECK_SERVICE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return check_service(cmd, param, flags, result, 0);
}

int	CHECK_SERVICE_PERF(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return check_service(cmd, param, flags, result, 1);
}
