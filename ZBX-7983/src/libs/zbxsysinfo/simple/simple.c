/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "common.h"
#include "sysinfo.h"
#include "comms.h"
#include "log.h"
#include "cfg.h"
#include "telnet.h"
#include "../common/net.h"
#include "ntp.h"
#include "simple.h"

ZBX_METRIC	parameters_simple[] =
/*      KEY                     FLAG		FUNCTION        	TEST PARAMETERS */
{
	{"net.tcp.service",	CF_HAVEPARAMS,	CHECK_SERVICE, 		"ssh,127.0.0.1,22"},
	{"net.tcp.service.perf",CF_HAVEPARAMS,	CHECK_SERVICE_PERF, 	"ssh,127.0.0.1,22"},
	{NULL}
};

#ifdef HAVE_LDAP
static int    check_ldap(const char *host, unsigned short port, int timeout, int *value_int)
{
	LDAP		*ldap	= NULL;
	LDAPMessage	*res	= NULL;
	LDAPMessage	*msg	= NULL;
	BerElement	*ber	= NULL;

	char	*attrs[2] = { "namingContexts", NULL };
	char	*attr	 = NULL;
	char	**valRes = NULL;
	int	ldapErr = 0;

	alarm(timeout);

	*value_int = 0;

	if (NULL == (ldap = ldap_init(host, port)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - initialization failed [%s:%hu]", host, port);
		goto lbl_ret;
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

	if (NULL == (attr = ldap_first_attribute(ldap, msg, &ber)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - empty first entry result. [%s] [%s]", host, ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	valRes = ldap_get_values(ldap, msg, attr);

	*value_int = 1;
lbl_ret:
	alarm(0);

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
#endif	/* HAVE_LDAP */

static int	check_ssh(const char *host, unsigned short port, int timeout, int *value_int)
{
	int		ret;
	zbx_sock_t	s;
	char		send_buf[MAX_STRING_LEN], *recv_buf, *ssh_server, *ssh_proto;

	*value_int = 0;

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, timeout)))
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
				zbx_snprintf(send_buf, sizeof(send_buf), "0\n");

			ret = zbx_tcp_send_raw(&s, send_buf);
		}

		zbx_tcp_close(&s);
	}

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "SSH check error: %s", zbx_tcp_strerror());

	return SYSINFO_RET_OK;
}

#ifdef HAVE_LIBCURL
static int	check_https(const char *host, unsigned short port, int timeout, int *value_int)
{
	const char	*__function_name = "check_https";
	int		err, opt;
	char		https_host[MAX_STRING_LEN];
	CURL            *easyhandle;

	*value_int = 0;

	if (NULL == (easyhandle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: could not init cURL library", __function_name);
		goto clean;
	}

	zbx_snprintf(https_host, sizeof(https_host), "%s%s", (0 == strncmp(host, "https://", 8) ? "" : "https://"), host);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_USERAGENT, "Zabbix " ZABBIX_VERSION)) ||
		CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, https_host)) ||
		CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_PORT, (long)port)) ||
		CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_NOBODY, 1L)) ||
		CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
		CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)) ||
		CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_TIMEOUT, (long)timeout)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: could not set cURL option [%d]: %s",
				__function_name, opt, curl_easy_strerror(err));
		goto clean;
	}

	if (CURLE_OK == (err = curl_easy_perform(easyhandle)))
		*value_int = 1;
	else
		zabbix_log(LOG_LEVEL_DEBUG, "%s: curl_easy_perform failed for [%s:%hu]: %s",
				__function_name, host, port, curl_easy_strerror(err));
clean:
	curl_easy_cleanup(easyhandle);

	return SYSINFO_RET_OK;
}
#endif	/* HAVE_LIBCURL */

static int	check_telnet(const char *host, unsigned short port, int timeout, int *value_int)
{
	const char	*__function_name = "check_telnet";
	zbx_sock_t	s;
#ifdef _WINDOWS
	u_long		argp = 1;
#else
	int		flags;
#endif

	*value_int = 0;

	if (SUCCEED == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, timeout))
	{
#ifdef _WINDOWS
		ioctlsocket(s.socket, FIONBIO, &argp);	/* non-zero value sets the socket to non-blocking */
#else
		flags = fcntl(s.socket, F_GETFL);
		if (0 == (flags & O_NONBLOCK))
			fcntl(s.socket, F_SETFL, flags | O_NONBLOCK);
#endif

		if (SUCCEED == telnet_test_login(s.socket))
			*value_int = 1;
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Telnet check error: no login prompt");

		zbx_tcp_close(&s);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "%s error: %s", __function_name, zbx_tcp_strerror());

	return SYSINFO_RET_OK;
}

int	check_service(AGENT_REQUEST *request, const char *default_addr, AGENT_RESULT *result, int perf)
{
	unsigned short	port = 0;
	char		*service, *ip_str, ip[64], *port_str;
	int		value_int, ret = SYSINFO_RET_FAIL;
	double		check_time;

	check_time = zbx_time();

	if (3 < request->nparam)
		return ret;

	service = get_rparam(request, 0);
	ip_str = get_rparam(request, 1);
	port_str = get_rparam(request, 2);

	if (NULL == service || '\0' == *service)
		return ret;

	if (NULL == ip_str || '\0' == *ip_str)
		strscpy(ip, default_addr);
	else
		strscpy(ip, ip_str);

	if (NULL != port_str && SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid \"port\" parameter"));
		return ret;
	}

	if (0 == strcmp(service, "ssh"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_SSH_PORT;
		ret = check_ssh(ip, port, CONFIG_TIMEOUT, &value_int);
	}
	else if (0 == strcmp(service, "ntp") || 0 == strcmp(service, "service.ntp" /* deprecated */))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_NTP_PORT;
		ret = check_ntp(ip, port, CONFIG_TIMEOUT, &value_int);
	}
#ifdef HAVE_LDAP
	else if (0 == strcmp(service, "ldap"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_LDAP_PORT;
		ret = check_ldap(ip, port, CONFIG_TIMEOUT, &value_int);
	}
#endif
	else if (0 == strcmp(service, "smtp"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_SMTP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "220", "QUIT\r\n", &value_int);
	}
	else if (0 == strcmp(service, "ftp"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_FTP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "220", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "http"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_HTTP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, NULL, NULL, &value_int);
	}
	else if (0 == strcmp(service, "pop"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_POP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "+OK", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "nntp"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_NNTP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "200", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "imap"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_IMAP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "* OK", "a1 LOGOUT\n", &value_int);
	}
	else if (0 == strcmp(service, "tcp"))
	{
		if (NULL == port_str || '\0' == *port_str)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Required \"port\" parameter missing"));
			return ret;
		}
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, NULL, NULL, &value_int);
	}
#ifdef HAVE_LIBCURL
	else if (0 == strcmp(service, "https"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_HTTPS_PORT;
		ret = check_https(ip, port, CONFIG_TIMEOUT, &value_int);
	}
#endif
	else if (0 == strcmp(service, "telnet"))
	{
		if (NULL == port_str || '\0' == *port_str)
			port = ZBX_DEFAULT_TELNET_PORT;
		ret = check_telnet(ip, port, CONFIG_TIMEOUT, &value_int);
	}
	else
		return ret;

	if (SYSINFO_RET_OK == ret)
	{
		if (0 != perf)
		{
			if (0 != value_int)
			{
				check_time = zbx_time() - check_time;
				check_time = MAX(check_time, 0.0001);
				SET_DBL_RESULT(result, check_time);
			}
			else
				SET_DBL_RESULT(result, 0.0);
		}
		else
			SET_UI64_RESULT(result, value_int);
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

int	CHECK_SERVICE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return check_service(request, "127.0.0.1", result, 0);
}

int	CHECK_SERVICE_PERF(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return check_service(request, "127.0.0.1", result, 1);
}
