/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "log.h"

#include "zbxmedia.h"

#ifdef HAVE_JABBER

#include <iksemel.h>

static void	zbx_io_close(void *socket)
{
	int	*sock = (int *)socket;

	if (NULL == sock)
		return;

	close(*sock);
}

static int		zbx_j_sock = -1;
static const char	*__module_name = "JABBER";

static int	zbx_io_connect(iksparser *prs, void **socketptr, const char *server, int port)
{
	int		tmp;
#ifdef HAVE_GETADDRINFO
	struct addrinfo	hints, *addr_res, *addr_ptr;
	char		port_str[6];

	ZBX_UNUSED(prs);

	*socketptr = NULL;

	hints.ai_flags = AI_CANONNAME;
	hints.ai_family = PF_UNSPEC;
	hints.ai_socktype = SOCK_STREAM;
	hints.ai_protocol = 0;
	hints.ai_addrlen = 0;
	hints.ai_canonname = NULL;
	hints.ai_addr = NULL;
	hints.ai_next = NULL;

	zbx_snprintf(port_str, sizeof(port_str), "%d", port);

	if (0 != getaddrinfo(server, port_str, &hints, &addr_res))
		return IKS_NET_NODNS;

	addr_ptr = addr_res;

	while (NULL != addr_ptr)
	{
		if (-1 != (zbx_j_sock = socket(addr_ptr->ai_family, addr_ptr->ai_socktype, addr_ptr->ai_protocol)))
			break;

		addr_ptr = addr_ptr->ai_next;
	}

	if (-1 == zbx_j_sock)
	{
		freeaddrinfo(addr_res);
		return IKS_NET_NOSOCK;
	}

	tmp = connect(zbx_j_sock, addr_ptr->ai_addr, addr_ptr->ai_addrlen);

	freeaddrinfo(addr_res);
#else
	struct hostent		*host;
	struct sockaddr_in	sin;

	ZBX_UNUSED(prs);

	if (NULL == (host = gethostbyname(server)))
		return IKS_NET_NODNS;

	memcpy(&sin.sin_addr, host->h_addr, host->h_length);
	sin.sin_family = host->h_addrtype;
	sin.sin_port = htons(port);

	if (-1 == (zbx_j_sock = socket(host->h_addrtype, SOCK_STREAM, 0)))
		return IKS_NET_NOSOCK;

	tmp = connect(zbx_j_sock, (struct sockaddr *)&sin, sizeof(sin));
#endif
	if (0 != tmp)
	{
		zbx_io_close((void *)&zbx_j_sock);
		return IKS_NET_NOCONN;
	}

	*socketptr = (void *)&zbx_j_sock;

	return IKS_OK;
}

static int	zbx_io_send(void *socket, const char *data, size_t len)
{
	int	*sock = (int *)socket;

	if (NULL == sock)
		return IKS_NET_RWERR;

	if (write(*sock, data, len) < (ssize_t)len)
		return IKS_NET_RWERR;

	return IKS_OK;
}

static int	zbx_io_recv(void *socket, char *buffer, size_t buf_len, int timeout)
{
	int		*sock = (int *)socket, len;
	struct timeval	tv;
	fd_set		fds;

	if (NULL == sock)
		return -1;

	tv.tv_sec = timeout;
	tv.tv_usec = 0;

	FD_ZERO(&fds);
	FD_SET(*sock, &fds);

	if (0 < select(*sock + 1, &fds, NULL, NULL, -1 != timeout ? &tv : NULL))
	{
		len = recv(*sock, buffer, buf_len, 0);

		if (0 < len)
			return len;
		else if (0 >= len)
			return -1;
	}

	return 0;
}

static ikstransport	zbx_iks_transport =
{
	IKS_TRANSPORT_V1,
	zbx_io_connect,
	zbx_io_send,
	zbx_io_recv,
	zbx_io_close,
	NULL
};

#define JABBER_DISCONNECTED	0
#define JABBER_ERROR		1

#define JABBER_CONNECTING	2
#define JABBER_CONNECTED	3
#define JABBER_AUTHORIZED	4
#define JABBER_WORKING		5
#define JABBER_READY		10

typedef struct
{
	iksparser	*prs;
	iksid		*acc;
	char		*pass;
	int		features;
	iksfilter	*my_filter;
	int		opt_use_tls;
	int		opt_use_sasl;
	int		status;
}
jabber_session_t, *jabber_session_p;

static jabber_session_p jsess = NULL;
static char		*jabber_error = NULL;
static int		jabber_error_len = 0;

static int	on_result(jabber_session_p sess, ikspak *pak)
{
	const char	*__function_name = "on_result";

	ZBX_UNUSED(pak);

	zabbix_log(LOG_LEVEL_DEBUG, "%s: In %s()", __module_name, __function_name);

	sess->status = JABBER_READY;

	zabbix_log(LOG_LEVEL_DEBUG, "%s: End of %s()", __module_name, __function_name);

	return IKS_FILTER_EAT;
}

/******************************************************************************
 *                                                                            *
 * Function: lookup_jabber                                                    *
 *                                                                            *
 * Purpose: lookup Jabber SRV record                                          *
 *                                                                            *
 * Author: Aleksandrs Saveljevs, based on code by Edward Rudd                 *
 *                                                                            *
 ******************************************************************************/
static void	lookup_jabber(const char *server, int port, char *real_server, size_t real_server_len, int *real_port)
{
	const char	*__function_name = "lookup_jabber";
	char		buffer[MAX_STRING_LEN], command[MAX_STRING_LEN];
	AGENT_RESULT	result;
	int		ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "%s: In %s() server:'%s' port:%d", __module_name, __function_name, server, port);

	init_result(&result);

	zbx_snprintf(command, sizeof(command), "net.dns.record[,_xmpp-client._tcp.%s,SRV]", server);

	if (SUCCEED == process(command, 0, &result))
	{
		int		max_priority = 65536, max_weight = -1;
		int		cur_priority, cur_weight, cur_port;
		const char	*p = result.text;

		zabbix_log(LOG_LEVEL_DEBUG, "response to DNS query: [%s]", result.text);

		/* let us now choose the server with the highest priority and maximum weight */

		zbx_snprintf(command, sizeof(command), "_xmpp-client._tcp.%s SRV %%d %%d %%d %%" ZBX_FS_SIZE_T "s",
				server, (zbx_fs_size_t)sizeof(buffer));

		while (NULL != p)
		{
			if (4 == sscanf(p, command, &cur_priority, &cur_weight, &cur_port, buffer))
			{
				if (cur_priority < max_priority || (cur_priority == max_priority && cur_weight > max_weight))
				{
					ret = SYSINFO_RET_OK;

					max_priority = cur_priority;
					max_weight = cur_weight;

					zbx_strlcpy(real_server, buffer, real_server_len);
					*real_port = cur_port;
				}
			}

			if (NULL != (p = strchr(p, '\n')))
				p++;
		}
	}

	free_result(&result);

	if (SYSINFO_RET_OK != ret)
	{
		zbx_strlcpy(real_server, server, real_server_len);
		*real_port = port;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s: End of %s() real_server:'%s' real_port:%d",
			__module_name, __function_name, real_server, *real_port);
}

/******************************************************************************
 *                                                                            *
 * Function: disconnect_jabber                                                *
 *                                                                            *
 * Purpose: disconnect from Jabber server                                     *
 *                                                                            *
 * Return value: always return SUCCEED                                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	disconnect_jabber()
{
	const char	*__function_name = "disconnect_jabber";

	zabbix_log(LOG_LEVEL_DEBUG, "%s: In %s()", __module_name, __function_name);

	if (JABBER_DISCONNECTED != jsess->status)
		iks_disconnect(jsess->prs);

	if (NULL != jsess->my_filter)
	{
		iks_filter_delete(jsess->my_filter);
		jsess->my_filter = NULL;
	}

	if (NULL != jsess->prs)
	{
		iks_parser_delete(jsess->prs);
		jsess->prs = NULL;
	}

	zbx_free(jsess->pass);

	jsess->acc = NULL;

	jsess->status = JABBER_DISCONNECTED;

	zabbix_log(LOG_LEVEL_DEBUG, "%s: End of %s()", __module_name, __function_name);

	return SUCCEED;
}

static int	on_stream(jabber_session_p sess, int type, iks *node)
{
	const char	*__function_name = "on_stream";
	iks		*x = NULL;
	ikspak		*pak = NULL;
	int		ret = IKS_OK;

	zabbix_log(LOG_LEVEL_DEBUG, "%s: In %s()", __module_name, __function_name);

	switch (type)
	{
		case IKS_NODE_START:
			break;
		case IKS_NODE_NORMAL:
			if (0 == strcmp("stream:features", iks_name(node)))
			{
				sess->features = iks_stream_features(node);

				if (IKS_STREAM_STARTTLS == (sess->features & IKS_STREAM_STARTTLS))
				{
					iks_start_tls(sess->prs);
				}
				else
				{
					if (JABBER_AUTHORIZED == sess->status)
					{
						if (IKS_STREAM_BIND == (sess->features & IKS_STREAM_BIND))
						{
							x = iks_make_resource_bind(sess->acc);
							iks_send(sess->prs, x);
							iks_delete(x);
						}
						if (IKS_STREAM_SESSION == (sess->features & IKS_STREAM_SESSION))
						{
							x = iks_make_session();
							iks_insert_attrib(x, "id", "auth");
							iks_send(sess->prs, x);
							iks_delete(x);
						}
					}
					else
					{
						if (IKS_STREAM_SASL_MD5 == (sess->features & IKS_STREAM_SASL_MD5))
							iks_start_sasl(sess->prs, IKS_SASL_DIGEST_MD5, sess->acc->user, sess->pass);
						else if (IKS_STREAM_SASL_PLAIN == (sess->features & IKS_STREAM_SASL_PLAIN))
							iks_start_sasl(sess->prs, IKS_SASL_PLAIN, sess->acc->user, sess->pass);
					}
				}
			}
			else if (0 == strcmp("failure", iks_name(node)))
			{
				zbx_snprintf(jabber_error, jabber_error_len, "sasl authentication failed");
				jsess->status = JABBER_ERROR;
				ret = IKS_HOOK;
			}
			else if (0 == strcmp("success", iks_name(node)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s: authorized", __module_name);
				sess->status = JABBER_AUTHORIZED;
				iks_send_header(sess->prs, sess->acc->server);
			}
			else
			{
				pak = iks_packet(node);
				iks_filter_packet(sess->my_filter, pak);
				if (JABBER_READY == jsess->status)
					ret = IKS_HOOK;
			}
			break;
		case IKS_NODE_STOP:
			zbx_snprintf(jabber_error, jabber_error_len, "server disconnected");
			jsess->status = JABBER_ERROR;
			ret = IKS_HOOK;
			break;
		case IKS_NODE_ERROR:
			zbx_snprintf(jabber_error, jabber_error_len, "stream error");
			jsess->status = JABBER_ERROR;
			ret = IKS_HOOK;
	}

	if (NULL != node)
		iks_delete(node);

	zabbix_log(LOG_LEVEL_DEBUG, "%s: End of %s()", __module_name, __function_name);

	return ret;
}

static int	on_error(void *user_data, ikspak *pak)
{
	ZBX_UNUSED(user_data);
	ZBX_UNUSED(pak);

	zbx_snprintf(jabber_error, jabber_error_len, "authorization failed");

	jsess->status = JABBER_ERROR;

	return IKS_FILTER_EAT;
}

#ifdef DEBUG
static void	on_log(jabber_session_p sess, const char *data, size_t size, int is_incoming)
{
	zabbix_log(LOG_LEVEL_DEBUG, "%s: %s%s: %s",
			__module_name, iks_is_secure(sess->prs) ? "Sec" : "", is_incoming ? "RECV" : "SEND", data);
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: connect_jabber                                                   *
 *                                                                            *
 * Purpose: connect to Jabber server                                          *
 *                                                                            *
 * Return value: SUCCEED on successful connection                             *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	connect_jabber(const char *jabber_id, const char *password, int use_sasl, int port)
{
	const char	*__function_name = "connect_jabber";
	char		*buf = NULL;
	char		real_server[MAX_STRING_LEN];
	int		real_port = 0, iks_error, timeout, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "%s: In %s() jabber_id:'%s'", __module_name, __function_name, jabber_id);

	if (NULL == jsess)
	{
		jsess = zbx_malloc(jsess, sizeof(jabber_session_t));
		memset(jsess, 0, sizeof(jabber_session_t));
	}
	else if (JABBER_DISCONNECTED != jsess->status)
	{
		disconnect_jabber();
	}

	if (NULL == (jsess->prs = iks_stream_new(IKS_NS_CLIENT, jsess, (iksStreamHook *)on_stream)))
	{
		zbx_snprintf(jabber_error, jabber_error_len, "cannot create iksemel parser: %s", zbx_strerror(errno));
		goto lbl_fail;
	}

#ifdef DEBUG
	iks_set_log_hook(jsess->prs, (iksLogHook *)on_log);
#endif

	jsess->acc = iks_id_new(iks_parser_stack(jsess->prs), jabber_id);

	if (NULL == jsess->acc->resource)
	{
		/* user gave no resource name, use the default */
		buf = zbx_dsprintf(buf, "%s@%s/%s", jsess->acc->user, jsess->acc->server, "ZABBIX");
		jsess->acc = iks_id_new(iks_parser_stack(jsess->prs), buf);
		zbx_free(buf);
	}

	jsess->pass = zbx_strdup(jsess->pass, password);
	jsess->opt_use_sasl = use_sasl;

	if (NULL == (jsess->my_filter = iks_filter_new()))
	{
		zbx_snprintf(jabber_error, jabber_error_len, "cannot create filter: %s", zbx_strerror(errno));
		goto lbl_fail;
	}

	iks_filter_add_rule(jsess->my_filter, (iksFilterHook *)on_result, jsess,
		IKS_RULE_TYPE, IKS_PAK_IQ,
		IKS_RULE_SUBTYPE, IKS_TYPE_RESULT,
		IKS_RULE_ID, "auth",
		IKS_RULE_DONE);

	iks_filter_add_rule(jsess->my_filter, on_error, jsess,
		IKS_RULE_TYPE, IKS_PAK_IQ,
		IKS_RULE_SUBTYPE, IKS_TYPE_ERROR,
		IKS_RULE_ID, "auth",
		IKS_RULE_DONE);

	lookup_jabber(jsess->acc->server, port, real_server, sizeof(real_server), &real_port);

	switch (iks_connect_with(jsess->prs, real_server, real_port, jsess->acc->server, &zbx_iks_transport))
	{
		case IKS_OK:
			break;
		case IKS_NET_NODNS:
			zbx_snprintf(jabber_error, jabber_error_len, "hostname lookup failed");
			goto lbl_fail;
		case IKS_NET_NOCONN:
			zbx_snprintf(jabber_error, jabber_error_len, "connection failed: %s",
					strerror_from_system(errno));
			goto lbl_fail;
		default:
			zbx_snprintf(jabber_error, jabber_error_len, "connection error: %s",
					strerror_from_system(errno));
			goto lbl_fail;
	}

	timeout = 30;

	while (JABBER_READY != jsess->status && JABBER_ERROR != jsess->status)
	{
		iks_error = iks_recv(jsess->prs, 1);

		if (IKS_HOOK == iks_error)
			break;

		if (IKS_NET_TLSFAIL == iks_error)
		{
			zbx_snprintf(jabber_error, jabber_error_len, "tls handshake failed");
			break;
		}

		if (IKS_OK != iks_error)
		{
			zbx_snprintf(jabber_error, jabber_error_len, "received error [%d]: %s",
					iks_error, zbx_strerror(errno));
			break;
		}

		if (0 == --timeout)
			break;
	}

	if (JABBER_READY == jsess->status)
		ret = SUCCEED;
lbl_fail:
	zabbix_log(LOG_LEVEL_DEBUG, "%s: End of %s():%s", __module_name, __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_jabber                                                      *
 *                                                                            *
 * Purpose: send Jabber message                                               *
 *                                                                            *
 * Return value: SUCCEED if message sent                                      *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int	send_jabber(const char *username, const char *password, const char *sendto,
		const char *subject, const char *message, char *error, int max_error_len)
{
	const char	*__function_name = "send_jabber";
	iks		*x;
	int		ret = FAIL, iks_error = IKS_OK;

	assert(error);

	zabbix_log(LOG_LEVEL_DEBUG, "%s: In %s()", __module_name, __function_name);

	*error = '\0';

	jabber_error = error;
	jabber_error_len = max_error_len;

	if (SUCCEED != connect_jabber(username, password, 1, IKS_JABBER_PORT))
		goto lbl_fail;

	zabbix_log(LOG_LEVEL_DEBUG, "%s: sending", __module_name);

	if (NULL != (x = iks_make_msg(IKS_TYPE_NONE, sendto, message)))
	{
		iks_insert_cdata(iks_insert(x, "subject"), subject, 0);
		iks_insert_attrib(x, "from", username);

		if (IKS_OK == (iks_error = iks_send(jsess->prs, x)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s: message sent", __module_name);
			ret = SUCCEED;
		}
		else
		{
			zbx_snprintf(error, max_error_len, "cannot send message: %s", strerror_from_system(errno));
			jsess->status = JABBER_ERROR;
		}

		iks_delete(x);
	}
	else
		zbx_snprintf(error, max_error_len, "cannot create message");
lbl_fail:
	if (NULL != jsess && JABBER_DISCONNECTED != jsess->status)
		disconnect_jabber();

	jabber_error = NULL;
	jabber_error_len = 0;

	if ('\0' != *error)
		zabbix_log(LOG_LEVEL_WARNING, "%s: [%s] %s", __module_name, username, error);

	zabbix_log(LOG_LEVEL_DEBUG, "%s: End of %s():%s", __module_name, __function_name, zbx_result_string(ret));

	return ret;
}

#endif	/* HAVE_JABBER */
