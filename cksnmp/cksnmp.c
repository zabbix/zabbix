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

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>

#define SUCCEED		0
#define FAIL		(-1)
#define	NOTSUPPORTED	(-2)
#define	NETWORK_ERROR	(-3)
//#define	TIMEOUT_ERROR	(-4)
//#define	AGENT_ERROR	(-5)

#define ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV	0
#define ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV	1
#define ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV	2

#define ITEM_TYPE_SNMPv1	1
#define ITEM_TYPE_SNMPv2c	4
#define ITEM_TYPE_SNMPv3	6

#define MAX_STRING_LEN		2048

#define zbx_uint64_t uint64_t

typedef struct dc_host_s
{
	unsigned char	useip;
	const char	*ip;
	const char	*dns;
} DC_HOST;

typedef struct dc_item_s
{
	unsigned char	type;
	DC_HOST		host;
	unsigned short	snmp_port;
	const char	*snmp_oid;
	const char	*snmp_community;
	char		*snmpv3_securityname;
	unsigned char	snmpv3_securitylevel;
	const char	*snmpv3_authpassphrase;
	const char	*snmpv3_privpassphrase;
} DC_ITEM;

/******************************************************************************
 * Function: snmp_open_session                                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: -                                                            *
 ******************************************************************************/
static struct snmp_session	*snmp_open_session(DC_ITEM *item, char *err)
{
	const char		*__function_name = "snmp_open_session";
	struct snmp_session	session, *ss;
	char			addr[128];
	const char		*conn;
#ifdef HAVE_IPV6
	int			family;
#endif	/* HAVE_IPV6 */

	printf("In %s()\n", __function_name);

	snmp_sess_init(&session);

	if (item->type == ITEM_TYPE_SNMPv1)
		session.version = SNMP_VERSION_1;
	else if(item->type == ITEM_TYPE_SNMPv2c)
		session.version = SNMP_VERSION_2c;
	else if(item->type == ITEM_TYPE_SNMPv3)
		session.version = SNMP_VERSION_3;
	else
		/* this should never happen */;

	conn = item->host.useip == 1 ? item->host.ip : item->host.dns;

#ifdef HAVE_IPV6
	if (SUCCEED != get_address_family(conn, &family, err, MAX_STRING_LEN))
		return NULL;

	if (family == PF_INET)
		sprintf(addr, "%s:%d", conn, item->snmp_port);
	else
		sprintf(addr, "udp6:[%s]:%d", conn, item->snmp_port);
#else
	sprintf(addr, "%s:%d", conn, item->snmp_port);
#endif	/* HAVE_IPV6 */
	session.peername = addr;
	session.remote_port = item->snmp_port;

	if (session.version == SNMP_VERSION_1 || session.version == SNMP_VERSION_2c)
	{
		session.community = (u_char *)item->snmp_community;
		session.community_len = strlen((void *)session.community);
		printf("SNMP [%s@%s]\n",
				session.community,
				session.peername);
	}
	else if (session.version == SNMP_VERSION_3)
	{
		/* set the SNMPv3 user name */
		session.securityName = item->snmpv3_securityname;
		session.securityNameLen = strlen(session.securityName);

		/* set the security level to authenticated, but not encrypted */
		if (item->snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_NOAUTH;
		}
		else if (item->snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_AUTHNOPRIV;

			/* set the authentication method to MD5 */
			session.securityAuthProto = usmHMACMD5AuthProtocol;
			session.securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
			session.securityAuthKeyLen = USM_AUTH_KU_LEN;

			if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
					session.securityAuthProtoLen,
					(u_char *)item->snmpv3_authpassphrase,
					strlen(item->snmpv3_authpassphrase),
					session.securityAuthKey,
					&session.securityAuthKeyLen))
			{
				sprintf(err, "Error generating Ku from authentication pass phrase");
				return NULL;
			}
		}
		else if (item->snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_AUTHPRIV;

			/* set the authentication method to MD5 */
			session.securityAuthProto = usmHMACMD5AuthProtocol;
			session.securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
			session.securityAuthKeyLen = USM_AUTH_KU_LEN;

			if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
					session.securityAuthProtoLen,
					(u_char *)item->snmpv3_authpassphrase,
					strlen(item->snmpv3_authpassphrase),
					session.securityAuthKey,
					&session.securityAuthKeyLen))
			{
				sprintf(err, "Error generating Ku from authentication pass phrase");
				return NULL;
			}

			/* set the private method to DES */
			session.securityPrivProto = usmDESPrivProtocol;
			session.securityPrivProtoLen = USM_PRIV_PROTO_DES_LEN;
			session.securityPrivKeyLen = USM_PRIV_KU_LEN;

			if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
					session.securityAuthProtoLen,
					(u_char *)item->snmpv3_privpassphrase,
					strlen(item->snmpv3_privpassphrase),
					session.securityPrivKey,
					&session.securityPrivKeyLen))
			{
				sprintf(err, "Error generating Ku from priv pass phrase");
				return NULL;
			}
		}
		printf("SNMPv3 [%s@%s:%d]\n",
				session.securityName,
				session.peername,
				session.remote_port);
	}

#ifdef HAVE_SNMP_SESSION_LOCALNAME
	if (NULL != CONFIG_SOURCE_IP)
		session.localname = CONFIG_SOURCE_IP;
#endif

	SOCK_STARTUP;
	ss = snmp_open(&session);

	if (ss == NULL)
	{
		SOCK_CLEANUP;

		sprintf(err, "Error doing snmp_open()");
		return NULL;
	}

	printf("End of %s():%p\n", __function_name, ss);

	return ss;
}

static void	snmp_close_session(struct snmp_session *session)
{
	const char *__function_name = "snmp_close_session";

	printf("In %s()\n", __function_name);

	snmp_close(session);
	SOCK_CLEANUP;

	printf("End of %s()\n", __function_name);
}

int	get_snmp(struct snmp_session *ss, DC_ITEM *item)
{
	const char		*__function_name = "get_snmp";
	struct snmp_pdu		*pdu, *response;
	char			temp[MAX_STRING_LEN], *ptemp;
	const char		*conn;
	oid			anOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN;
	struct variable_list	*vars;
	int			status, ret = SUCCEED;

	printf("In %s(oid:%s)\n", __function_name, item->snmp_oid);

	pdu = snmp_pdu_create(SNMP_MSG_GET);
	if (NULL == snmp_parse_oid(item->snmp_oid, anOID, &anOID_len))
	{
		snmp_perror(item->snmp_oid);
	}

	printf("%d\n", anOID_len);

	snmp_add_null_var(pdu, anOID, anOID_len);

	status = snmp_synch_response(ss, pdu, &response);
	printf("Status send [%d]\n", status);

	if (status == STAT_SUCCESS && response->errstat == SNMP_ERR_NOERROR)
	{
		for (vars = response->variables; vars; vars = vars->next_variable)
		{
			memset(temp, '\0', sizeof(temp));
			snprint_value(temp, sizeof(temp) - 1, vars->name, vars->name_length, vars);
			printf("AV loop OID [%s] Type [0x%02X] '%s'\n",
					item->snmp_oid, vars->type, temp);

			if (vars->type == ASN_OCTET_STR)
			{
				if (0 == strncmp(temp, "STRING: ", 8))
					ptemp = temp + 8;
				else if (0 == strncmp(temp, "Hex-STRING: ", 12))
					ptemp = temp + 12;
				else
					ptemp = temp;

				printf("ASN_OCTET_STR %x: %s\n", vars->type, ptemp);
			}
			else if (vars->type == ASN_UINTEGER)
			{
				printf("ASN_UINTEGER %x: %llu\n", vars->type, (unsigned long)*vars->val.integer);
			}
			else if (vars->type == ASN_COUNTER)
			{
				printf("ASN_COUNTER %x: %llu\n", vars->type, (unsigned long)*vars->val.integer);
			}
#ifdef OPAQUE_SPECIAL_TYPES
			else if (vars->type == ASN_UNSIGNED64)
			{
				printf("ASN_UNSIGNED64 %x: %llu\n", vars->type, (unsigned long)*vars->val.integer);
			}
#endif
			else if (vars->type == ASN_TIMETICKS)
			{
				printf("ASN_TIMETICKS %x: %llu\n", vars->type, (unsigned long)*vars->val.integer);
			}
			else if (vars->type == ASN_GAUGE)
			{
				printf("ASN_GAUGE %x: %llu\n", vars->type, (unsigned long)*vars->val.integer);
			}
			else if (vars->type == ASN_COUNTER64)
			{
				printf("ASN_COUNTER64 %x: %llu\n", vars->type,
						(((zbx_uint64_t)vars->val.counter64->high) << 32) +
						(zbx_uint64_t)vars->val.counter64->low);
			}
			else if (vars->type == ASN_INTEGER ||
#ifdef OPAQUE_SPECIAL_TYPES
					vars->type == ASN_INTEGER64
#endif
					)
			{
				printf("ASN_INTEGER(64) %x: %llu\n", vars->type, (zbx_uint64_t)*vars->val.integer);
			}
#ifdef OPAQUE_SPECIAL_TYPES
			else if (vars->type == ASN_FLOAT)
			{
				printf("ASN_FLOAT %x: %f\n", vars->type, *vars->val.floatVal);
			}
			else if (vars->type == ASN_DOUBLE)
			{
				printf("ASN_DOUBLE %x: %f\n", vars->type, *vars->val.doubleVal);
			}
#endif
			else if (vars->type == ASN_IPADDRESS)
			{
				printf("ASN_IPADDRESS %x: %d.%d.%d.%d\n", vars->type,
						vars->val.string[0],
						vars->val.string[1],
						vars->val.string[2],
						vars->val.string[3]);
			}
			else
			{
				printf("OID [%s] value has unknown type [0x%02X]\n",
						item->snmp_oid,
						vars->type);
				ret = NOTSUPPORTED;
			}

			if (SUCCEED == ret)
				break;
		}
	}
	else
	{
		if (status == STAT_SUCCESS)
		{
			printf("SNMP error [%s]\n", snmp_errstring(response->errstat));
			ret = NOTSUPPORTED;
		}
		else if(status == STAT_TIMEOUT)
		{
			conn = item->host.useip == 1 ? item->host.ip : item->host.dns;
			printf("Timeout while connecting to [%s:%d]\n", conn, item->snmp_port);
			ret = NETWORK_ERROR;
		}
		else
		{
			printf("SNMP error [%d]\n", status);
			ret = NOTSUPPORTED;
		}
	}

	if (response)
		snmp_free_pdu(response);

	printf("End of %s():%d\n", __function_name, ret);

	return ret;
}

int	get_value_snmp(DC_ITEM *item)
{
	const char		*__function_name = "get_value_snmp";
	struct snmp_session	*ss;
	int			idx, ret = SUCCEED;
	char		method[MAX_STRING_LEN];
	char		oid_index[MAX_STRING_LEN];
	char		oid_full[MAX_STRING_LEN];
	char		index_value[MAX_STRING_LEN];
	char		err[MAX_STRING_LEN], temp[MAX_STRING_LEN];
	char		*pl;

	printf("In %s() oid:'%s'\n",
			__function_name, item->snmp_oid);

	if (NULL == (ss = snmp_open_session(item, err)))
	{
		ret = NOTSUPPORTED;

		printf("%s\n", err);

		printf("End of %s():%d\n", __function_name, ret);

		return ret;
	}

	printf("Standard processing\n");
	ret = get_snmp(ss, item);

	snmp_close_session(ss);

	printf("End of %s():%d\n", __function_name, ret);

	return ret;
}

int	main(int argc, char **argv)
{
	init_snmp("zabbix_server");

	DC_ITEM	item;

	item.type		= ITEM_TYPE_SNMPv2c;
	item.snmp_port		= 161;
	item.snmp_oid		= argv[3];
	item.snmp_community	= argv[2];

	item.host.useip		= 1;
	item.host.ip		= argv[1];

	get_value_snmp(&item);
}
