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
#include "zbxjson.h"
#include "log.h"

typedef struct
{
	zbx_uint64_t ibytes;
	zbx_uint64_t ipackets;
	zbx_uint64_t ierr;
	zbx_uint64_t idrop;
	zbx_uint64_t obytes;
	zbx_uint64_t opackets;
	zbx_uint64_t oerr;
	zbx_uint64_t odrop;
	zbx_uint64_t colls;
}
net_stat_t;

#if HAVE_INET_DIAG

int	nlerr;

static int	find_tcp_port_by_state_nl(unsigned short port, int state)
{
	int				ret = -1, fd = -1;
	int				family = -1, found = 0, done;
	int				status = -1, sequence = 0x5A4258;
	struct timeval			timeout = { 1, 500 * 1000 };

	struct {
		struct nlmsghdr		nlhdr;
		struct inet_diag_req	r;
	} request;

	size_t				siz_request = sizeof(request), siz_sa_nl = sizeof(struct sockaddr_nl);

	request.nlhdr.nlmsg_len		= siz_request;
	request.nlhdr.nlmsg_flags	= NLM_F_REQUEST | NLM_F_ROOT | NLM_F_MATCH;
	request.nlhdr.nlmsg_pid		= 0;
	request.nlhdr.nlmsg_seq		= sequence;
	request.nlhdr.nlmsg_type	= TCPDIAG_GETSOCK;

	request.r.idiag_states		= (1 << state);

	struct sockaddr_nl		s_sa = { AF_NETLINK, 0, 0, 0 };
	struct iovec			s_io[1] = { { &request, siz_request } };
	struct msghdr			s_msg = { (void *)&s_sa, siz_sa_nl, s_io, 1 };

	char				buffer[BUFSIZ] = { 0 };

	struct sockaddr_nl		r_sa = { AF_NETLINK, 0, 0, 0 };
	struct iovec			r_io[1] = { { buffer, BUFSIZ } };
	struct msghdr			r_msg = { (void *)&r_sa, siz_sa_nl, r_io, 1 };

	struct nlmsghdr			*r_hdr = NULL;

	do
	{
		if (-1 == (fd = socket(AF_NETLINK, SOCK_DGRAM, NETLINK_INET_DIAG)))
		{
			nlerr = NLERR_SOCKCREAT;
			break;
		}

		if (0 != setsockopt(fd, SOL_SOCKET, SO_RCVTIMEO, (char *)&timeout, sizeof(struct timeval)))
		{
			nlerr = NLERR_SOCKCREAT;
			close(fd);
			break;
		}

		nlerr = NLERR_OK;

		do
		{
			if (-1 == family)
				family = AF_INET;
			else if (AF_INET == family)
				family = AF_INET6;
			else
				break;

			request.r.idiag_family		= family;

			if (-1 == sendmsg(fd, &s_msg, 0))
			{
				nlerr = NLERR_BADSEND;
				break;
			}

			done = 0;

			while (NLERR_OK == nlerr && 1 != done)
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

				for (r_hdr = (struct nlmsghdr *)buffer;
					NLMSG_OK(r_hdr, status) && 1 != found && 1 != done;
					r_hdr = NLMSG_NEXT(r_hdr, status))
				{
					int			err;
					struct inet_diag_msg	*r = NLMSG_DATA(r_hdr);

					if (sequence != r_hdr->nlmsg_seq)
						continue;

					switch (r_hdr->nlmsg_type)
					{
						case NLMSG_DONE:
							done = 1;
							break;
						case NLMSG_ERROR:
						{
							struct nlmsgerr	*err = (struct nlmsgerr *)NLMSG_DATA(r_hdr);

							if (NLMSG_LENGTH(sizeof(struct nlmsgerr)) > r_hdr->nlmsg_len)
								nlerr = NLERR_RESPTRUNCAT;
							else
							{
								errno = -err->error;
								nlerr = (EOPNOTSUPP == errno) ?
									NLERR_OPNOTSUPPORTED :
									NLERR_UNKNOWN;
							}

							done = 1;

							break;
						}
						case 0x12:
							if (state == r->idiag_state &&
								port == ntohs(r->id.idiag_sport))
								(found = 1) && (done = 1);

							break;
						default:
							nlerr = NLERR_UNKNOWNMSGTYPE;
					}
				}
			}

		} while(NLERR_OK == nlerr && 1 != found);
	} while(0);

	if (NLERR_SOCKCREAT != nlerr)
		close(fd);

	if (NLERR_OK == nlerr)
		ret = found;

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

		if (10 == sscanf(line, "%s\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
				ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t%*s\t%*s\t%*s\t%*s\t"
				ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
				ZBX_FS_UI64 "\t%*s\t" ZBX_FS_UI64 "\t%*s\t%*s\n",
				name,
				&result->ibytes,	/* bytes */
				&result->ipackets,	/* packets */
				&result->ierr,		/* errs */
				&result->idrop,		/* drop */
				&result->obytes,	/* bytes */
				&result->opackets,	/* packets */
				&result->oerr,		/* errs */
				&result->odrop,		/* drop */
				&result->colls))	/* icolls */
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
 * Comments: When reading line by line the file might be changed between      *
 *           reads resulting in a possible information loss. To avoid it      *
 *           try reading/expanding the buffer until it fits the whole file.   *
 *                                                                            *
 ******************************************************************************/
static int	proc_read_file(const char *filename, char **buffer, int *buffer_alloc)
{
	int	n, fd, ret = -1;
	size_t	offset = 0;

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
			*buffer = zbx_realloc(*buffer, *buffer_alloc);
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

	SET_UI64_RESULT(result, ns.colls);

	return SYSINFO_RET_OK;
}

int	NET_IF_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		line[MAX_STRING_LEN], *p;
	FILE		*f;
	struct zbx_json	j;

	if (NULL == (f = fopen("/proc/net/dev", "r")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc/net/dev: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

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
	int		prev_nlerr = NLERR_OK;
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
	n = find_tcp_port_by_state_nl(port, STATE_LISTEN);

	if (n >= 0)
	{
		ret = SYSINFO_RET_OK;
		listen = n;
	}
	else
	{
		if (prev_nlerr != nlerr)
		{
			switch(nlerr)
			{
				case NLERR_UNKNOWN:
					zabbix_log(LOG_LEVEL_DEBUG, "Unrecognized netlink error occurred.");
					break;
				case NLERR_SOCKCREAT:
					zabbix_log(LOG_LEVEL_DEBUG, "Cannot create netlink socket.");
					break;
				case NLERR_BADSEND:
					zabbix_log(LOG_LEVEL_DEBUG, "Cannot send netlink message to kernel.");
					break;
				case NLERR_BADRECV:
					zabbix_log(LOG_LEVEL_DEBUG, "Cannot receive netlink message from kernel.");
					break;
				case NLERR_RECVTIMEOUT:
					zabbix_log(LOG_LEVEL_DEBUG, "Receiving netlink response timed out.");
					break;
				case NLERR_RESPTRUNCAT:
					zabbix_log(LOG_LEVEL_DEBUG, "Received truncated netlink response from kernel.");
					break;
				case NLERR_OPNOTSUPPORTED:
					zabbix_log(LOG_LEVEL_DEBUG, "Netlink operation not supported.");
					break;
				case NLERR_UNKNOWNMSGTYPE:
					zabbix_log(LOG_LEVEL_DEBUG, "Received message of unrecognized type from kernel.");
					break;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "Falling back on reading /proc/net/tcp...");

			prev_nlerr = nlerr;
		}
#endif
		buffer = zbx_malloc(NULL, buffer_alloc);

		if (0 < (n = proc_read_file("/proc/net/tcp", &buffer, &buffer_alloc)))
		{
			ret = SYSINFO_RET_OK;

			zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000:0000 0A", (unsigned int)port);

			buffer[n] = '\0';

			if (NULL != strstr(buffer, pattern))
			{
				listen = 1;
				goto out;
			}
		}

		if (0 < (n = proc_read_file("/proc/net/tcp6", &buffer, &buffer_alloc)))
		{
			ret = SYSINFO_RET_OK;

			zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000000000000000000000000000:0000 0A",
					(unsigned int)port);

			buffer[n] = '\0';

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

	buffer = zbx_malloc(NULL, buffer_alloc);

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
