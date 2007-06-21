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
#include "log.h"
#include "zlog.h"

#include "jabber.h"

#include <iksemel.h>

static void
zbx_io_close (void *socket)
{
        int *sock = (int*) socket;

	if( !sock ) return;

        close (*sock);
}

static int	zbx_j_sock = -1;

static int
zbx_io_connect (iksparser *prs, void **socketptr, const char *server, int port)
{
        int tmp;
#ifdef HAVE_GETADDRINFO
        struct addrinfo hints;
        struct addrinfo *addr_res, *addr_ptr;
        char port_str[6];

        *socketptr = (void *) NULL;

        hints.ai_flags = AI_CANONNAME;
        hints.ai_family = PF_UNSPEC;
        hints.ai_socktype = SOCK_STREAM;
        hints.ai_protocol = 0;
        hints.ai_addrlen = 0;
        hints.ai_canonname = NULL;
        hints.ai_addr = NULL;
        hints.ai_next = NULL;
        zbx_snprintf(port_str, sizeof(port_str), "%i", port);

        if (getaddrinfo (server, port_str, &hints, &addr_res) != 0)
                return IKS_NET_NODNS;

        addr_ptr = addr_res;
        while (addr_ptr) {
                zbx_j_sock = socket (addr_ptr->ai_family, addr_ptr->ai_socktype, addr_ptr->ai_protocol);
                if (zbx_j_sock != -1) break;
                addr_ptr = addr_ptr->ai_next;
        }
        if (zbx_j_sock == -1) return IKS_NET_NOSOCK;

        tmp = connect (zbx_j_sock, addr_ptr->ai_addr, addr_ptr->ai_addrlen);
        freeaddrinfo (addr_res);
#else
        struct hostent *host;
        struct sockaddr_in sin;

        host = gethostbyname (server);
        if (!host) return IKS_NET_NODNS;

        memcpy (&sin.sin_addr, host->h_addr, host->h_length);
        sin.sin_family = host->h_addrtype;
        sin.sin_port = htons (port);
        zbx_j_sock = socket (host->h_addrtype, SOCK_STREAM, 0);
        if (zbx_j_sock == -1) return IKS_NET_NOSOCK;

        tmp = connect (zbx_j_sock, (struct sockaddr *)&sin, sizeof (struct sockaddr_in));
#endif
        if (tmp != 0) {
                zbx_io_close ((void *) &zbx_j_sock);
                return IKS_NET_NOCONN;
        }

        *socketptr = (void *) &zbx_j_sock;

        return IKS_OK;
}

static int
zbx_io_send (void *socket, const char *data, size_t len)
{
	int *sock = (int*) socket;

	if ( !sock )	return IKS_NET_RWERR;

	if ( write(*sock, data, len) < len) return IKS_NET_RWERR;
	return IKS_OK;
}

static int
zbx_io_recv (void *socket, char *buffer, size_t buf_len, int timeout)
{
        int *sock = (int*) socket;
        fd_set fds;
        struct timeval tv, *tvptr;
        int len;

	if( !sock ) return -1;

        tv.tv_sec = 0;
        tv.tv_usec = 0;

        FD_ZERO (&fds);
        FD_SET (*sock, &fds);
        tv.tv_sec = timeout;
        if (timeout != -1) tvptr = &tv; else tvptr = NULL;
        if (select (*sock + 1, &fds, NULL, NULL, tvptr) > 0) {
                len = recv (*sock, buffer, buf_len, 0);
                if (len > 0) {
                        return len;
                } else if (len <= 0) {
                        return -1;
                }
        }
        return 0;
}

ikstransport zbx_iks_transport = {
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

typedef struct jabber_session {
	iksparser	*prs;
	iksid		*acc;
	char		*pass;
	int		features;
	iksfilter	*my_filter;
	int		opt_use_tls;
	int		opt_use_sasl;
	int		status;
} jabber_session_t, *jabber_session_p;

static jabber_session_p jsess = NULL;

static int on_result (jabber_session_p sess, ikspak *pak)
{
	zabbix_log (LOG_LEVEL_DEBUG, "JABBER: ready");
	sess->status = JABBER_READY;
	return IKS_FILTER_EAT;
}

/******************************************************************************
 *                                                                            *
 * Function: disconnect_jabber                                                *
 *                                                                            *
 * Purpose: Disconnect from jabber server                                     *
 *                                                                            *
 * Parameters: ... ... ...                                                    *
 *                                                                            *
 * Return value:  allways return SUCCEED                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int disconnect_jabber()
{
	if ( JABBER_DISCONNECTED != jsess->status)
		iks_disconnect(jsess->prs);
	
	zabbix_log(LOG_LEVEL_INFORMATION, "JABBER: disconnecting");
	
	if (jsess->my_filter)
	{
		iks_filter_delete (jsess->my_filter);
		jsess->my_filter = NULL;
	}

	if (jsess->prs)
	{
		iks_parser_delete (jsess->prs);
		jsess->prs = NULL;
	}

	zbx_free(jsess->pass);

	jsess->acc = NULL;

	jsess->status = JABBER_DISCONNECTED;

	return SUCCEED;
}

static int on_stream (jabber_session_p sess, int type, iks *node)
{
	iks *x = NULL;
	ikspak *pak = NULL;

	switch (type) {
		case IKS_NODE_START:
			if (sess->opt_use_tls && !iks_is_secure (sess->prs)) {
				iks_start_tls (sess->prs);
				break;
			}
			if (!sess->opt_use_sasl) {
				x = iks_make_auth (sess->acc, sess->pass, iks_find_attrib (node, "id"));
				iks_insert_attrib (x, "id", "auth");
				iks_send (sess->prs, x);
				iks_delete (x);
			}
			break;

		case IKS_NODE_NORMAL:
			if (strcmp ("stream:features", iks_name (node)) == 0) {
				sess->features = iks_stream_features (node);
				if (sess->opt_use_sasl) {
					if (sess->opt_use_tls && !iks_is_secure (sess->prs)) break;
					if (sess->status == JABBER_AUTHORIZED) {
						if (sess->features & IKS_STREAM_BIND) {
							x = iks_make_resource_bind (sess->acc);
							iks_send (sess->prs, x);
							iks_delete (x);
						}
						if (sess->features & IKS_STREAM_SESSION) {
							x = iks_make_session ();
							iks_insert_attrib (x, "id", "auth");
							iks_send (sess->prs, x);
							iks_delete (x);
						}
					} else {
						if (sess->features & IKS_STREAM_SASL_MD5)
							iks_start_sasl (sess->prs, IKS_SASL_DIGEST_MD5, sess->acc->user, sess->pass);
						else if (sess->features & IKS_STREAM_SASL_PLAIN)
							iks_start_sasl (sess->prs, IKS_SASL_PLAIN, sess->acc->user, sess->pass);
					}
				}
			} else if (strcmp ("failure", iks_name (node)) == 0) {
				zabbix_log (LOG_LEVEL_WARNING, "JABBER: sasl authentication failed");
			} else if (strcmp ("success", iks_name (node)) == 0) {
				zabbix_log (LOG_LEVEL_DEBUG, "JABBER: authorized");
				sess->status = JABBER_AUTHORIZED;
				iks_send_header (sess->prs, sess->acc->server);
			} else {
				pak = iks_packet (node);
				iks_filter_packet (sess->my_filter, pak);
			}
			break;

		case IKS_NODE_STOP:
			zabbix_log (LOG_LEVEL_WARNING, "JABBER: server disconnected");
			disconnect_jabber();
			break;
		case IKS_NODE_ERROR:
			zabbix_log (LOG_LEVEL_WARNING, "JABBER: stream error");
			jsess->status = JABBER_ERROR;
	}

	if (node) iks_delete (node);
	return IKS_OK;
}

static int on_error (void *user_data, ikspak *pak)
{
	zabbix_log (LOG_LEVEL_WARNING, "JABBER: authorization failed");

	jsess->status = JABBER_ERROR;
	return IKS_FILTER_EAT;
}

static void on_log (jabber_session_p sess, const char *data, size_t size, int is_incoming)
{
	zabbix_log(LOG_LEVEL_DEBUG, "%s%s [%s]\n", iks_is_secure (sess->prs) ? "Sec" : "", is_incoming ? "RECV" : "SEND", data);
}

/******************************************************************************
 *                                                                            *
 * Function: connect_jabber                                                   *
 *                                                                            *
 * Purpose: Connect to jabber server                                          *
 *                                                                            *
 * Parameters: ... ... ...                                                    *
 *                                                                            *
 * Return value:  SUCCEED on successfull connection                           *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int connect_jabber(const char *jabber_id, const char *password, int use_sasl, int port, char *error, int len)
{
	char *buf = NULL;
		
	zabbix_log(LOG_LEVEL_DEBUG, "JABBER: connecting as %s, pass %s", jabber_id, password);

	if(NULL == jsess)
	{
		jsess = zbx_malloc(jsess, sizeof (jabber_session_t));
		memset (jsess, 0, sizeof (jabber_session_t));
	}
	else
	{
		disconnect_jabber();
	}

	jsess->pass = strdup(password);
	jsess->opt_use_sasl = use_sasl;

	if ( !(jsess->prs = iks_stream_new (IKS_NS_CLIENT, jsess, (iksStreamHook *) on_stream)) )
	{
		zbx_snprintf(error, len, "Cannot create iksemel parser [%s]", strerror(errno));
		goto lbl_fail;
	}

#ifdef DEBUG
	iks_set_log_hook (jsess->prs, (iksLogHook *) on_log);
#endif /* DEBUG */

	jsess->acc = iks_id_new (iks_parser_stack (jsess->prs), jabber_id);

	if (NULL == jsess->acc->resource) {
		/* user gave no resource name, use the default */
		buf = zbx_dsprintf (buf, "%s@%s/%s", jsess->acc->user, jsess->acc->server, "ZABBIX");
		jsess->acc = iks_id_new (iks_parser_stack (jsess->prs), buf);
		zbx_free (buf);
	}

	if( !(jsess->my_filter = iks_filter_new ()) )
	{
		zbx_snprintf(error, len, "Cannot create filter [%s]", strerror(errno));
		goto lbl_fail;
	}

	iks_filter_add_rule (jsess->my_filter, (iksFilterHook *) on_result, jsess,
		IKS_RULE_TYPE, IKS_PAK_IQ,
		IKS_RULE_SUBTYPE, IKS_TYPE_RESULT,
		IKS_RULE_ID, "auth",
		IKS_RULE_DONE);

	iks_filter_add_rule (jsess->my_filter, on_error, jsess,
		IKS_RULE_TYPE, IKS_PAK_IQ,
		IKS_RULE_SUBTYPE, IKS_TYPE_ERROR,
		IKS_RULE_ID, "auth",
		IKS_RULE_DONE);

	switch (iks_connect_with(jsess->prs, jsess->acc->server, port, jsess->acc->server, &zbx_iks_transport) ) {
		case IKS_OK:
			break;
		case IKS_NET_NODNS:
			zbx_snprintf(error, len, "hostname lookup failed");
			goto lbl_fail;
		case IKS_NET_NOCONN:
			zbx_snprintf(error, len, "connection failed");
			goto lbl_fail;
		default:
			zbx_snprintf(error, len, "connection io error");
			goto lbl_fail;
	}

	while (jsess->status != JABBER_READY) {
		switch (iks_recv (jsess->prs, 5)) {
			case IKS_OK:
			case IKS_HOOK:
				break;
			case IKS_NET_TLSFAIL:
				zbx_snprintf(error, len, "tls handshake failed");
				goto lbl_fail;
			default:
				zbx_snprintf(error, len, "receiving io error");
				goto lbl_fail;
		}
	}

	return SUCCEED;

lbl_fail:
	disconnect_jabber();
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: send_jabber                                                      *
 *                                                                            *
 * Purpose: Send jabber message                                               *
 *                                                                            *
 * Parameters: ... ... ...                                                    *
 *                                                                            *
 * Return value:  SUCCEED if message sended                                   *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_jabber(char *username, char *passwd, char *sendto, char *message, char *error, int max_error_len)
{
	iks *x = NULL;
	int ret = FAIL;

	assert(error);

	zabbix_log( LOG_LEVEL_DEBUG, "JABBER: sending message");

	*error = '\0';

	if (NULL == jsess || jsess->status == JABBER_DISCONNECTED || jsess->status == JABBER_ERROR) {
		if (SUCCEED != connect_jabber(username, passwd, 1, IKS_JABBER_PORT,  error, max_error_len))
		{
			zabbix_log(LOG_LEVEL_WARNING, "JABBER: %s", error);
			return FAIL;
		}
	}

	zabbix_log( LOG_LEVEL_DEBUG, "JABBER: sending");
	if( (x = iks_make_msg(IKS_TYPE_NONE, sendto, message)) )
	{
		if ( IKS_OK == iks_send (jsess->prs, x) )
		{
			zabbix_log( LOG_LEVEL_DEBUG, "JABBER: message sent");
			ret = SUCCEED;
		}
		else
		{
			jsess->status = JABBER_ERROR;

			zbx_snprintf(error, max_error_len, "JABBER: Cannot send message [%s]", strerror_from_system(errno));
			zabbix_log(LOG_LEVEL_WARNING, error);
		}
		iks_delete (x);
	}
	else
	{
		zbx_snprintf(error, max_error_len, "JABBER: Cannot create message");
		zabbix_log(LOG_LEVEL_WARNING, error);
	}

	return ret;
}

