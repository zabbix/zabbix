/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#include "zbxjson.h"
#include "log.h"
#include "comms.h"

typedef struct
{
	zbx_uint64_t ibytes;
	zbx_uint64_t ipackets;
	zbx_uint64_t ierr;
	zbx_uint64_t idrop;
	zbx_uint64_t ififo;
	zbx_uint64_t iframe;
	zbx_uint64_t icompressed;
	zbx_uint64_t imulticast;
	zbx_uint64_t obytes;
	zbx_uint64_t opackets;
	zbx_uint64_t oerr;
	zbx_uint64_t odrop;
	zbx_uint64_t ocolls;
	zbx_uint64_t ofifo;
	zbx_uint64_t ocarrier;
	zbx_uint64_t ocompressed;
}
net_stat_t;

typedef struct
{
	char		*laddr;
	unsigned int	lport;
	char		*raddr;
	unsigned int	rport;
	unsigned int	state;
}
net_count_info_t;

#define ZBX_ADDR_TYPE_IPV4	0
#define ZBX_ADDR_TYPE_IPV6	1

#define IPV4_MAX_CIDR_PREFIX	32	/* max number of bits in IPv4 CIDR prefix */
#define IPV6_MAX_CIDR_PREFIX	128	/* max number of bits in IPv6 CIDR prefix */

#if HAVE_INET_DIAG
#	include <sys/socket.h>
#	include <linux/netlink.h>
#	include <linux/inet_diag.h>

enum
{
	STATE_UNKNOWN = 0,
	STATE_ESTABLISHED,
	STATE_SYN_SENT,
	STATE_SYN_RECV,
	STATE_FIN_WAIT1,
	STATE_FIN_WAIT2,
	STATE_TIME_WAIT,
	STATE_CLOSE,
	STATE_CLOSE_WAIT,
	STATE_LAST_ACK,
	STATE_LISTEN,
	STATE_CLOSING,
	STATE_MAXSTATES
};

enum
{
	NLERR_OK = 0,
	NLERR_UNKNOWN,
	NLERR_SOCKCREAT,
	NLERR_BADSEND,
	NLERR_BADRECV,
	NLERR_RECVTIMEOUT,
	NLERR_RESPTRUNCAT,
	NLERR_OPNOTSUPPORTED,
	NLERR_UNKNOWNMSGTYPE
};

static int	nlerr;

static int	find_tcp_port_by_state_nl(unsigned short port, int state, int *found)
{
	struct
	{
		struct nlmsghdr		nlhdr;
		struct inet_diag_req	r;
	}
	request;

	int			ret = FAIL, fd, status, i;
	int			families[] = {AF_INET, AF_INET6, AF_UNSPEC};
	unsigned int		sequence = 0x58425A;
	struct timeval		timeout = { 1, 500 * 1000 };

	struct sockaddr_nl	s_sa = { AF_NETLINK, 0, 0, 0 };
	struct iovec		s_io[1] = { { &request, sizeof(request) } };
	struct msghdr		s_msg = { (void *)&s_sa, sizeof(struct sockaddr_nl), s_io, 1, NULL, 0, 0};

	char			buffer[BUFSIZ] = { 0 };

	struct sockaddr_nl	r_sa = { AF_NETLINK, 0, 0, 0 };
	struct iovec		r_io[1] = { { buffer, BUFSIZ } };
	struct msghdr		r_msg = { (void *)&r_sa, sizeof(struct sockaddr_nl), r_io, 1, NULL, 0, 0};

	struct nlmsghdr		*r_hdr;

	*found = 0;

	request.nlhdr.nlmsg_len = sizeof(request);
	request.nlhdr.nlmsg_flags = NLM_F_REQUEST | NLM_F_ROOT | NLM_F_MATCH;
	request.nlhdr.nlmsg_pid = 0;
	request.nlhdr.nlmsg_seq = sequence;
	request.nlhdr.nlmsg_type = TCPDIAG_GETSOCK;

	memset(&request.r, 0, sizeof(request.r));
	request.r.idiag_states = (1 << state);

	if (-1 == (fd = socket(AF_NETLINK, SOCK_DGRAM, NETLINK_INET_DIAG)) ||
			0 != setsockopt(fd, SOL_SOCKET, SO_RCVTIMEO, (char *)&timeout, sizeof(struct timeval)))
	{
		nlerr = NLERR_SOCKCREAT;
		goto out;
	}

	nlerr = NLERR_OK;

	for (i = 0; AF_UNSPEC != families[i]; i++)
	{
		request.r.idiag_family = families[i];

		if (-1 == sendmsg(fd, &s_msg, 0))
		{
			nlerr = NLERR_BADSEND;
			goto out;
		}

		while (NLERR_OK == nlerr)
		{
			status = recvmsg(fd, &r_msg, 0);

			if (0 > status)
			{
				if (EAGAIN == errno || EWOULDBLOCK == errno)
					nlerr = NLERR_RECVTIMEOUT;
				else if (EINTR != errno)
					nlerr = NLERR_BADRECV;

				continue;
			}

			if (0 == status)
				break;

			for (r_hdr = (struct nlmsghdr *)buffer; NLMSG_OK(r_hdr, (unsigned)status);
					r_hdr = NLMSG_NEXT(r_hdr, status))
			{
				struct inet_diag_msg	*r = (struct inet_diag_msg *)NLMSG_DATA(r_hdr);

				if (sequence != r_hdr->nlmsg_seq)
					continue;

				switch (r_hdr->nlmsg_type)
				{
					case NLMSG_DONE:
						goto out;
					case NLMSG_ERROR:
					{
						struct nlmsgerr	*err = (struct nlmsgerr *)NLMSG_DATA(r_hdr);

						if (NLMSG_LENGTH(sizeof(struct nlmsgerr)) > r_hdr->nlmsg_len)
						{
							nlerr = NLERR_RESPTRUNCAT;
						}
						else
						{
							nlerr = (EOPNOTSUPP == -err->error ? NLERR_OPNOTSUPPORTED :
								NLERR_UNKNOWN);
						}

						goto out;
					}
					case 0x12:
						if (state == r->idiag_state && port == ntohs(r->id.idiag_sport))
						{
							*found = 1;
							goto out;
						}
						break;
					default:
						nlerr = NLERR_UNKNOWNMSGTYPE;
						break;
				}
			}
		}
	}
out:
	if (-1 != fd)
		close(fd);

	if (NLERR_OK == nlerr)
		ret = SUCCEED;

	return ret;
}
#endif

static int	get_net_stat(const char *if_name, net_stat_t *result, char **error)
{
	int	ret = SYSINFO_RET_FAIL;
	char	line[MAX_STRING_LEN], name[MAX_STRING_LEN], *p;
	FILE	*f;

	if (NULL == if_name || '\0' == *if_name)
	{
		*error = zbx_strdup(NULL, "Network interface name cannot be empty.");
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (f = fopen("/proc/net/dev", "r")))
	{
		*error = zbx_dsprintf(NULL, "Cannot open /proc/net/dev: %s", zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (NULL == (p = strstr(line, ":")))
			continue;

		*p = '\t';

		if (17 == sscanf(line, "%s\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
				ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
				ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
				ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
				ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
				ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
				ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
				ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\n",
				name,
				&result->ibytes,	/* bytes */
				&result->ipackets,	/* packets */
				&result->ierr,		/* errs */
				&result->idrop,		/* drop */
				&result->ififo,		/* fifo (overruns) */
				&result->iframe,	/* frame */
				&result->icompressed,	/* compressed */
				&result->imulticast,	/* multicast */
				&result->obytes,	/* bytes */
				&result->opackets,	/* packets */
				&result->oerr,		/* errs */
				&result->odrop,		/* drop */
				&result->ofifo,		/* fifo (overruns)*/
				&result->ocolls,	/* colls (collisions) */
				&result->ocarrier,	/* carrier */
				&result->ocompressed))	/* compressed */
		{
			if (0 == strcmp(name, if_name))
			{
				ret = SYSINFO_RET_OK;
				break;
			}
		}
	}

	zbx_fclose(f);

	if (SYSINFO_RET_FAIL == ret)
	{
		*error = zbx_strdup(NULL, "Cannot find information for this network interface in /proc/net/dev.");
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: proc_read_tcp_listen                                             *
 *                                                                            *
 * Purpose: reads /proc/net/tcp(6) file by chunks until the last line in      *
 *          in buffer has non-listening socket state                          *
 *                                                                            *
 * Parameters: filename     - [IN] the file to read                           *
 *             buffer       - [IN/OUT] the output buffer                      *
 *             buffer_alloc - [IN/OUT] the output buffer size                 *
 *                                                                            *
 * Return value: -1 error occurred during reading                             *
 *                0 empty file (shouldn't happen)                             *
 *               >0 the number of bytes read                                  *
 *                                                                            *
 ******************************************************************************/
static int    proc_read_tcp_listen(const char *filename, char **buffer, int *buffer_alloc)
{
	int     n, fd, ret = -1, offset = 0;
	char    *start, *end;

	if (-1 == (fd = open(filename, O_RDONLY)))
		return -1;

	while (0 != (n = read(fd, *buffer + offset, *buffer_alloc - offset)))
	{
		int    count = 0;

		if (-1 == n)
			goto out;

		offset += n;

		if (offset == *buffer_alloc)
		{
			*buffer_alloc *= 2;
			*buffer = (char *)zbx_realloc(*buffer, *buffer_alloc);
		}

		(*buffer)[offset] = '\0';

		/* find the last full line */
		for (start = *buffer + offset - 1; start > *buffer; start--)
		{
			if ('\n' == *start)
			{
				if (++count == 2)
					break;

				end = start;
			}
		}

		/* check if the socket is in listening state */
		if (2 == count)
		{
			start++;
			count = 0;

			while (' ' == *start++)
				;

			while (count < 3 && start < end)
			{
				while (' ' != *start)
					start++;

				while (' ' == *start)
					start++;

				count++;
			}

			if (3 == count && 0 != strncmp(start, "0A", 2) && 0 != strncmp(start, "03", 2))
				break;
		}
	}

	ret = offset;
out:
	close(fd);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proc_read_file                                                   *
 *                                                                            *
 * Purpose: reads whole file into a buffer in a single read operation         *
 *                                                                            *
 * Parameters: filename     - [IN] the file to read                           *
 *             buffer       - [IN/OUT] the output buffer                      *
 *             buffer_alloc - [IN/OUT] the output buffer size                 *
 *                                                                            *
 * Return value: -1 error occurred during reading                             *
 *                0 empty file (shouldn't happen)                             *
 *               >0 the number of bytes read                                  *
 *                                                                            *
 ******************************************************************************/
static int	proc_read_file(const char *filename, char **buffer, int *buffer_alloc)
{
	int	n, fd, ret = -1, offset = 0;

	if (-1 == (fd = open(filename, O_RDONLY)))
		return -1;

	while (0 != (n = read(fd, *buffer + offset, *buffer_alloc - offset)))
	{
		if (-1 == n)
			goto out;

		offset += n;

		if (offset == *buffer_alloc)
		{
			*buffer_alloc *= 2;
			*buffer = (char *)zbx_realloc(*buffer, *buffer_alloc);
		}
	}

	ret = offset;
out:
	close(fd);

	return ret;
}

int	NET_IF_IN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		*if_name, *mode, *error;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ns.ibytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.ipackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.ierr);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ns.idrop);
	else if (0 == strcmp(mode, "overruns"))
		SET_UI64_RESULT(result, ns.ififo);
	else if (0 == strcmp(mode, "frame"))
		SET_UI64_RESULT(result, ns.iframe);
	else if (0 == strcmp(mode, "compressed"))
		SET_UI64_RESULT(result, ns.icompressed);
	else if (0 == strcmp(mode, "multicast"))
		SET_UI64_RESULT(result, ns.imulticast);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_OUT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		*if_name, *mode, *error;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ns.obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.oerr);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ns.odrop);
	else if (0 == strcmp(mode, "overruns"))
		SET_UI64_RESULT(result, ns.ofifo);
	else if (0 == strcmp(mode, "collisions"))
		SET_UI64_RESULT(result, ns.ocolls);
	else if (0 == strcmp(mode, "carrier"))
		SET_UI64_RESULT(result, ns.ocarrier);
	else if (0 == strcmp(mode, "compressed"))
		SET_UI64_RESULT(result, ns.ocompressed);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		*if_name, *mode, *error;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ns.ibytes + ns.obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.ipackets + ns.opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.ierr + ns.oerr);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ns.idrop + ns.odrop);
	else if (0 == strcmp(mode, "overruns"))
		SET_UI64_RESULT(result, ns.ififo + ns.ofifo);
	else if (0 == strcmp(mode, "compressed"))
		SET_UI64_RESULT(result, ns.icompressed + ns.ocompressed);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_COLLISIONS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		*if_name, *error;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, ns.ocolls);

	return SYSINFO_RET_OK;
}

int	NET_IF_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		line[MAX_STRING_LEN], *p;
	FILE		*f;
	struct zbx_json	j;

	ZBX_UNUSED(request);

	if (NULL == (f = fopen("/proc/net/dev", "r")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc/net/dev: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (NULL == (p = strstr(line, ":")))
			continue;

		*p = '\0';

		/* trim left spaces */
		for (p = line; ' ' == *p && '\0' != *p; p++)
			;

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#IFNAME}", p, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_fclose(f);

	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	NET_TCP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		pattern[64], *port_str, *buffer = NULL;
	unsigned short	port;
	zbx_uint64_t	listen = 0;
	int		ret = SYSINFO_RET_FAIL, n, buffer_alloc = 64 * ZBX_KIBIBYTE;
#ifdef HAVE_INET_DIAG
	int		found;
#endif
	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	port_str = get_rparam(request, 0);

	if (NULL == port_str || SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

#ifdef HAVE_INET_DIAG
	if (SUCCEED == find_tcp_port_by_state_nl(port, STATE_LISTEN, &found))
	{
		ret = SYSINFO_RET_OK;
		listen = found;
	}
	else
	{
		const char	*error;

		switch (nlerr)
		{
			case NLERR_UNKNOWN:
				error = "unrecognized netlink error occurred";
				break;
			case NLERR_SOCKCREAT:
				error = "cannot create netlink socket";
				break;
			case NLERR_BADSEND:
				error = "cannot send netlink message to kernel";
				break;
			case NLERR_BADRECV:
				error = "cannot receive netlink message from kernel";
				break;
			case NLERR_RECVTIMEOUT:
				error = "receiving netlink response timed out";
				break;
			case NLERR_RESPTRUNCAT:
				error = "received truncated netlink response from kernel";
				break;
			case NLERR_OPNOTSUPPORTED:
				error = "netlink operation not supported";
				break;
			case NLERR_UNKNOWNMSGTYPE:
				error = "received message of unrecognized type from kernel";
				break;
			default:
				error = "unknown error";
		}

		zabbix_log(LOG_LEVEL_DEBUG, "netlink interface error: %s", error);
		zabbix_log(LOG_LEVEL_DEBUG, "falling back on reading /proc/net/tcp...");
#endif
		buffer = (char *)zbx_malloc(NULL, buffer_alloc);

		if (0 < (n = proc_read_tcp_listen("/proc/net/tcp", &buffer, &buffer_alloc)))
		{
			ret = SYSINFO_RET_OK;

			zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000:0000 0A", (unsigned int)port);

			if (NULL != strstr(buffer, pattern))
			{
				listen = 1;
				goto out;
			}
		}

		if (0 < (n = proc_read_tcp_listen("/proc/net/tcp6", &buffer, &buffer_alloc)))
		{
			ret = SYSINFO_RET_OK;

			zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000000000000000000000000000:0000 0A",
					(unsigned int)port);

			if (NULL != strstr(buffer, pattern))
				listen = 1;
		}
out:
		zbx_free(buffer);
#ifdef HAVE_INET_DIAG
	}
#endif
	SET_UI64_RESULT(result, listen);

	return ret;
}

int	NET_UDP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		pattern[64], *port_str, *buffer = NULL;
	unsigned short	port;
	zbx_uint64_t	listen = 0;
	int		ret = SYSINFO_RET_FAIL, n, buffer_alloc = 64 * ZBX_KIBIBYTE;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	port_str = get_rparam(request, 0);

	if (NULL == port_str || SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	buffer = (char *)zbx_malloc(NULL, buffer_alloc);

	if (0 < (n = proc_read_file("/proc/net/udp", &buffer, &buffer_alloc)))
	{
		ret = SYSINFO_RET_OK;

		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000:0000 07", (unsigned int)port);

		buffer[n] = '\0';

		if (NULL != strstr(buffer, pattern))
		{
			listen = 1;
			goto out;
		}
	}

	if (0 < (n = proc_read_file("/proc/net/udp6", &buffer, &buffer_alloc)))
	{
		ret = SYSINFO_RET_OK;

		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000000000000000000000000000:0000 07",
				(unsigned int)port);

		buffer[n] = '\0';

		if (NULL != strstr(buffer, pattern))
			listen = 1;
	}
out:
	zbx_free(buffer);

	SET_UI64_RESULT(result, listen);

	return ret;
}

static int	check_ip_mask(char *addr, int addr_type, unsigned int ip_cmpr, const char *ip_cmpr6, char **error)
{
	char	*cidr_sep, ip[65];
	int	ret = FAIL, type;
	int	prefix_sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s'", __func__, addr);

	if (NULL != strchr(addr, ':'))
	{
		type = AF_INET6;
		prefix_sz = IPV6_MAX_CIDR_PREFIX;
	}
	else
	{
		type = AF_INET;
		prefix_sz = IPV4_MAX_CIDR_PREFIX;
	}


	if (NULL != (cidr_sep = strchr(addr, '/')))
	{
		*cidr_sep = '\0';

		if (FAIL == validate_cidr(addr, cidr_sep + 1, &prefix_sz))
		{
			*error = zbx_dsprintf(*error, "Cannot validate CIDR \"%s\"", addr);
			goto out;
		}
	}
	else if (FAIL == is_supported_ip(addr))
	{
		*error = zbx_dsprintf(*error, "IP is not supported \"%s\"", addr);
		goto out;
	}

	if (ZBX_ADDR_TYPE_IPV4 == addr_type)
	{
		ip_cmpr = htonl(ip_cmpr);

		if (NULL == inet_ntop(type, &ip_cmpr, ip, sizeof(ip)))
		{
			*error = zbx_dsprintf(*error, "Cannot convert IP address");
			goto out;
		}

		if (prefix_sz == IPV4_MAX_CIDR_PREFIX)
		{
			if (0 == strcmp(ip, addr))
				ret = SUCCEED;
		}
		else
			ret = subnet_match(type, prefix_sz, ip, addr);
	}
	else
	{
		if (prefix_sz == IPV6_MAX_CIDR_PREFIX)
		{
			if (0 == strcmp(ip_cmpr6, addr))
				ret = SUCCEED;
		}
		else
			ret = subnet_match(type, prefix_sz, addr, ip_cmpr6);
	}
out:
	if (NULL != cidr_sep)
		*cidr_sep = '/';

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	get_connection_state(const char *name)
{
	int	state;

	if (0 == strcmp(name, "established"))
		state = 1;
	else if (0 == strcmp(name, "syn_sent"))
		state = 2;
	else if (0 == strcmp(name, "syn_recv"))
		state = 3;
	else if (0 == strcmp(name, "fin_wait1"))
		state = 4;
	else if (0 == strcmp(name, "fin_wait2"))
		state = 5;
	else if (0 == strcmp(name, "time_wait"))
		state = 6;
	else if (0 == strcmp(name, "close"))
		state = 7;
	else if (0 == strcmp(name, "close_wait"))
		state = 8;
	else if (0 == strcmp(name, "last_ack"))
		state = 9;
	else if (0 == strcmp(name, "listen"))
		state = 10;
	else if (0 == strcmp(name, "closing"))
		state = 11;
	else
		state = 0;

	return state;
}

static unsigned short	get_service_port_udp(const char *name)
{
	unsigned short	port;

	if (0 == strcmp(name, "ntp"))
		port = ZBX_DEFAULT_NTP_PORT;
	else
		port = 0;

	return port;
}

static unsigned short	get_service_port_tcp(const char *name)
{
	unsigned short	port;

	if (0 == strcmp(name, "ssh"))
		port = ZBX_DEFAULT_LDAP_PORT;
	else if (0 == strcmp(name, "ldap"))
		port = ZBX_DEFAULT_LDAP_PORT;
	else if (0 == strcmp(name, "ftp"))
		port = ZBX_DEFAULT_FTP_PORT;
	else if (0 == strcmp(name, "http"))
		port = ZBX_DEFAULT_HTTP_PORT;
	else if (0 == strcmp(name, "pop"))
		port = ZBX_DEFAULT_POP_PORT;
	else if (0 == strcmp(name, "nntp"))
		port = ZBX_DEFAULT_NNTP_PORT;
	else if (0 == strcmp(name, "imap"))
		port = ZBX_DEFAULT_IMAP_PORT;
	else if (0 == strcmp(name, "https"))
		port = ZBX_DEFAULT_HTTPS_PORT;
	else if (0 == strcmp(name, "telnet"))
		port = ZBX_DEFAULT_TELNET_PORT;
	else
		port = 0;

	return port;
}

static int	get_proc_net_count(const char *filename, int addr_type, net_count_info_t *result_exp,
		zbx_uint64_t *count, char **error)
{
	char	line[MAX_STRING_LEN];
	FILE	*f;
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s'", __func__, filename);

	if (NULL == (f = fopen(filename, "r")))
	{
		*error = zbx_dsprintf(NULL, "Cannot open %s: %s", filename, zbx_strerror(errno));
		return FAIL;
	}

	while (NULL != fgets(line, sizeof(line), f))
	{
		unsigned int	laddr_n, raddr_n, lport, rport, state;
		char		laddr[MAX_STRING_LEN], raddr[MAX_STRING_LEN], *p;

		if (NULL == (p = strchr(line, ':')))
			continue;

		if (ZBX_ADDR_TYPE_IPV6 == addr_type)
		{
			if (5 == sscanf(p, ": %s:%x %s:%x %x", laddr, &lport, raddr, &rport, &state))
			{
				if (NULL != result_exp->laddr && '\0' != *result_exp->laddr &&
						SUCCEED != check_ip_mask(result_exp->laddr, ZBX_ADDR_TYPE_IPV6, 0,
						laddr, error))
				{
					if (NULL != *error)
					{
						*error = zbx_dsprintf(*error, "Invalid first argument: \"%s\"", *error);
						ret = FAIL;
						break;
					}

					continue;
				}

				if (NULL != result_exp->raddr && '\0' != *result_exp->raddr &&
						SUCCEED != check_ip_mask(result_exp->raddr, ZBX_ADDR_TYPE_IPV6, 0,
						raddr, error))
				{
					if (NULL != *error)
					{
						*error = zbx_dsprintf(*error, "Invalid third argument: \"%s\"", *error);
						ret = FAIL;
						break;
					}

					continue;
				}

				if ((0 != result_exp->lport && result_exp->lport != lport) ||
						(0 != result_exp->rport && result_exp->rport != rport) ||
						(0 != result_exp->state && result_exp->state != state))
				{
					continue;
				}

				(*count)++;
			}
		}
		else
		{
			if (5 == sscanf(p, ": %x:%x %x:%x %x", &laddr_n, &lport, &raddr_n, &rport, &state))
			{
				if (NULL != result_exp->laddr && '\0' != *result_exp->laddr &&
						SUCCEED != check_ip_mask(result_exp->laddr, ZBX_ADDR_TYPE_IPV4,
						htonl(laddr_n), NULL, error))
				{
					if (NULL != *error)
					{
						*error = zbx_dsprintf(*error, "Invalid first argument: \"%s\"", *error);
						ret = FAIL;
						break;
					}

					continue;
				}

				if (NULL != result_exp->raddr && '\0' != *result_exp->raddr &&
						SUCCEED != check_ip_mask(result_exp->raddr, ZBX_ADDR_TYPE_IPV4,
						htonl(raddr_n), NULL, error))
				{
					if (NULL != *error)
					{
						*error = zbx_dsprintf(*error, "Invalid third argument: \"%s\"", *error);
						ret = FAIL;
						break;
					}

					continue;
				}

				if ((0 != result_exp->lport && result_exp->lport != lport) ||
						(0 != result_exp->rport && result_exp->rport != rport) ||
						(0 != result_exp->state && result_exp->state != state))
				{
					continue;
				}

				(*count)++;
			}
		}
	}

	zbx_fclose(f);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	NET_TCP_COUNT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_count_info_t	res;
	char			*error = NULL, *lport, *rport, *state;
	zbx_uint64_t		count = 0;

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	memset(&res, 0, sizeof(res));

	res.laddr = get_rparam(request, 0);
	lport = get_rparam(request, 1);
	res.raddr = get_rparam(request, 2);
	rport = get_rparam(request, 3);
	state = get_rparam(request, 4);

	if (NULL != lport && '\0' != *lport && 0 == (res.lport = get_service_port_tcp(lport)) &&
			SUCCEED != is_ushort(lport, &res.lport))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != rport && '\0' != *rport && 0 == (res.rport = get_service_port_tcp(rport)) &&
			SUCCEED != is_ushort(rport, &res.rport))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != state && '\0' != *state && 0 == (res.state = get_connection_state(state)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED != get_proc_net_count("/proc/net/tcp", ZBX_ADDR_TYPE_IPV4, &res, &count, &error) ||
			SUCCEED != get_proc_net_count("/proc/net/tcp6", ZBX_ADDR_TYPE_IPV6, &res, &count, &error))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, count);

	return SYSINFO_RET_OK;
}

int	NET_UDP_COUNT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_count_info_t	res;
	char			*error = NULL, *lport, *rport, *state;
	zbx_uint64_t		count = 0;

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	memset(&res, 0, sizeof(res));

	res.laddr = get_rparam(request, 0);
	lport = get_rparam(request, 1);
	res.raddr = get_rparam(request, 2);
	rport = get_rparam(request, 3);
	state = get_rparam(request, 4);

	if (NULL != lport && '\0' != *lport && 0 == (res.lport = get_service_port_udp(lport)) &&
			SUCCEED != is_ushort(lport, &res.lport))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != rport && '\0' != *rport && 0 == (res.rport = get_service_port_udp(rport)) &&
			SUCCEED != is_ushort(rport, &res.rport))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != state && '\0' != *state && 0 == (res.state = get_connection_state(state)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED != get_proc_net_count("/proc/net/udp", ZBX_ADDR_TYPE_IPV4, &res, &count, &error) ||
			SUCCEED != get_proc_net_count("/proc/net/udp6", ZBX_ADDR_TYPE_IPV6, &res, &count, &error))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, count);

	return SYSINFO_RET_OK;
}
