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

ZBX_METRIC	parameters_simple[]=
/*      KEY                     FLAG    FUNCTION        ADD_PARAM       TEST_PARAM */
	{
	{"net.tcp.service",	CF_USEUPARAM,	CHECK_SERVICE, 		0,	"ssh,127.0.0.1,22"},
	{"net.tcp.service.perf",CF_USEUPARAM,	CHECK_SERVICE_PERF, 	0,	"ssh,127.0.0.1,22"},
	{0}
	};

#ifdef HAVE_LDAP

static int    check_ldap(char *hostname, short port, int *value_int)
{
	LDAP		*ldap	= NULL;
	LDAPMessage	*res	= NULL;
	LDAPMessage	*msg	= NULL;
	BerElement	*ber	= NULL;

	char	*attrs[2] = { "namingContexts", NULL };

	char	*attr	 = NULL;
	char	**valRes = NULL;

	int	ldapErr = 0;

        assert(value_int);

	*value_int = 0;

	if(NULL == (ldap = ldap_init(hostname, port)) )
	{
		zabbix_log( LOG_LEVEL_DEBUG, "LDAP - initialization failed [%s:%u]",hostname, port);
		return	SYSINFO_RET_OK;
	}

	if( LDAP_SUCCESS != (ldapErr = ldap_search_s(
		ldap,
		"",
		LDAP_SCOPE_BASE,
		"(objectClass=*)",
		attrs,
		0,
		&res)) )
	{
		zabbix_log( LOG_LEVEL_DEBUG, "LDAP - serching failed [%s] [%s]",hostname, ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	if(NULL == (msg = ldap_first_entry(ldap, res)) )
	{
		zabbix_log( LOG_LEVEL_DEBUG, " LDAP - empty sort result. [%s] [%s]", hostname, ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	attr	= ldap_first_attribute (ldap, msg, &ber);

	valRes	= ldap_get_values( ldap, msg, attr );

	*value_int = 1;

lbl_ret:
	if(valRes)	ldap_value_free(valRes);
	if(attr)	ldap_memfree(attr);
	if(ber) 	ber_free(ber, 0);
	if(res)		ldap_msgfree(res);
	if(ldap)	ldap_unbind(ldap);

	return	SYSINFO_RET_OK;
}
#endif


/*
 *  0 - NOT OK
 *  1 - OK
 * */
static int	check_ssh(const char *host, unsigned short port, int *value_int)
{
	int ret;

	zbx_sock_t	s;

	char
		send_buf[MAX_BUF_LEN],
		*recv_buf,
		*ssh_server,
		*ssh_proto;

	assert(value_int);

	*value_int = 0;
	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, 0))) {
		if( SUCCEED == (ret = zbx_tcp_recv(&s, &recv_buf)) )
		{
			if ( 0 == strncmp(recv_buf, "SSH", 3) )
			{
				ssh_server = ssh_proto = recv_buf + 4;
				ssh_server += strspn (ssh_proto, "0123456789-. ") ;
				ssh_server[-1] = '\0';

				zbx_snprintf(send_buf,sizeof(send_buf),"SSH-%s-%s\n", ssh_proto, "zabbix_agent");
				*value_int = 1;
			}
			else
			{
				zbx_snprintf(send_buf,sizeof(send_buf),"0\n");
			}
			ret = zbx_tcp_send_raw(&s, send_buf);
		}
	}
	zbx_tcp_close(&s);

	if( FAIL == ret )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "SSH check error: %s", zbx_tcp_strerror());
	}

	return	SYSINFO_RET_OK;
}

/* Example check_service[ssh], check_service[smtp,29],check_service[ssh,127.0.0.1,22]*/
/* check_service[ssh,127.0.0.1,ssh] */
int	CHECK_SERVICE_PERF(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	unsigned short	port=0;
	char	service[MAX_STRING_LEN];
	char	ip[MAX_STRING_LEN];
	char	str_port[MAX_STRING_LEN];

	double	start_time = 0;

	int	ret	= SYSINFO_RET_OK;
	int	value_int;

        assert(result);

	init_result(result);

	start_time = zbx_time();

        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 1, service, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 2, ip, MAX_STRING_LEN) != 0)
        {
                ip[0] = '\0';
        }

	if(ip[0] == '\0')
	{
		strscpy(ip, "127.0.0.1");
	}

	if(get_param(param, 3, str_port, MAX_STRING_LEN) != 0)
        {
                str_port[0] = '\0';
        }

	if(str_port[0] != '\0')
	{
		port = atoi(str_port);
	}
	else
	{
		port = 0;
	}

/*	printf("IP:[%s]",ip);
	printf("Service:[%s]",service);
	printf("Port:[%d]",port);*/

	if(strcmp(service,"ssh") == 0)
	{
		if(port == 0)	port=22;
		ret=check_ssh(ip,port,&value_int);
	}
#ifdef HAVE_LDAP
	else if(strcmp(service,"ldap") == 0)
	{
		if(port == 0)   port=389;
		ret=check_ldap(ip,port,&value_int);
	}
#endif
	else if(strcmp(service,"smtp") == 0)
	{
		if(port == 0)	port=25;
		ret=tcp_expect(ip,port,NULL,"220","QUIT\n",&value_int);
	}
	else if(strcmp(service,"ftp") == 0)
	{
		if(port == 0)	port=21;
		ret=tcp_expect(ip,port,NULL,"220","",&value_int);
	}
	else if(strcmp(service,"http") == 0)
	{
		if(port == 0)	port=80;
		ret=tcp_expect(ip,port,NULL,NULL,"",&value_int);
	}
	else if(strcmp(service,"pop") == 0)
	{
		if(port == 0)	port=110;
		ret=tcp_expect(ip,port,NULL,"+OK","",&value_int);
	}
	else if(strcmp(service,"nntp") == 0)
	{
		if(port == 0)	port=119;
/* 220 is incorrect */
/*		ret=tcp_expect(ip,port,"220","");*/
		ret=tcp_expect(ip,port,NULL,"200","",&value_int);
	}
	else if(strcmp(service,"imap") == 0)
	{
		if(port == 0)	port=143;
		ret=tcp_expect(ip,port,NULL,"* OK","a1 LOGOUT\n",&value_int);
	}
	else if(strcmp(service,"tcp") == 0)
	{
		if(port == 0)	port=80;
		ret=tcp_expect(ip,port,NULL,NULL,"",&value_int);
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	if(SYSINFO_RET_OK == ret)
	{
		if(value_int)
		{
			SET_DBL_RESULT(result, zbx_time() - start_time);
		}
		else
		{
			SET_DBL_RESULT(result, 0.0);
		}
	}


	return ret;
}

/* Example check_service[ssh], check_service[smtp,29],check_service[ssh,127.0.0.1,22]*/
/* check_service[ssh,127.0.0.1,ssh] */
int	CHECK_SERVICE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	unsigned short	port=0;
	char	service[MAX_STRING_LEN];
	char	ip[MAX_STRING_LEN];
	char	str_port[MAX_STRING_LEN];

	int	ret;
	int	value_int = 0;

        assert(result);

        init_result(result);

        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 1, service, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 2, ip, MAX_STRING_LEN) != 0)
        {
                ip[0] = '\0';
        }

	if(ip[0] == '\0')
	{
		strscpy(ip, "127.0.0.1");
	}

	if(get_param(param, 3, str_port, MAX_STRING_LEN) != 0)
        {
                str_port[0] = '\0';
        }

	if(str_port[0] != '\0')
	{
		port = atoi(str_port);
	}
	else
	{
		port = 0;
	}

/*	printf("IP:[%s]",ip);
	printf("Service:[%s]",service);
	printf("Port:[%d]",port);*/

	if(strcmp(service,"ssh") == 0)
	{
		if(port == 0)	port=22;
		ret=check_ssh(ip,port,&value_int);
	}
	else if(strcmp(service,"service.ntp") == 0)
	{
		if(port == 0)	port=123;
		ret=check_ntp(ip,port,&value_int);
	}
#ifdef HAVE_LDAP
	else if(strcmp(service,"ldap") == 0)
	{
		if(port == 0)   port=389;
		ret=check_ldap(ip,port,&value_int);
	}
#endif
	else if(strcmp(service,"smtp") == 0)
	{
		if(port == 0)	port=25;
		ret=tcp_expect(ip,port,NULL,"220","QUIT\n",&value_int);
	}
	else if(strcmp(service,"ftp") == 0)
	{
		if(port == 0)	port=21;
		ret=tcp_expect(ip,port,NULL,"220","",&value_int);
	}
	else if(strcmp(service,"http") == 0)
	{
		if(port == 0)	port=80;
		ret=tcp_expect(ip,port,NULL,NULL,"",&value_int);
	}
	else if(strcmp(service,"pop") == 0)
	{
		if(port == 0)	port=110;
		ret=tcp_expect(ip,port,NULL,"+OK","",&value_int);
	}
	else if(strcmp(service,"nntp") == 0)
	{
		if(port == 0)	port=119;
/* 220 is incorrect */
/*		ret=tcp_expect(ip,port,"220","");*/
		ret=tcp_expect(ip,port,NULL,"200","",&value_int);
	}
	else if(strcmp(service,"imap") == 0)
	{
		if(port == 0)	port=143;
		ret=tcp_expect(ip,port,NULL,"* OK","a1 LOGOUT\n",&value_int);
	}
	else if(strcmp(service,"tcp") == 0)
	{
		if(port == 0)	port=80;
		ret=tcp_expect(ip,port,NULL,NULL,"",&value_int);
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	if(SYSINFO_RET_OK == ret)
	{
		SET_UI64_RESULT(result, value_int);
	}

	return ret;
}
