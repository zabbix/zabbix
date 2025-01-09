/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxcommon.h"

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#include "zbxcomms.h"
#include "zbxstr.h"

static zbx_get_program_type_f		zbx_get_program_type_cb = NULL;

#define ZBX_TLS_PARAMETER_CONFIG_FILE	0
#define ZBX_TLS_PARAMETER_COMMAND_LINE	1

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     return the name of a configuration file or command line parameter that *
 *     the value of the given parameter comes from                            *
 *                                                                            *
 * Parameters:                                                                *
 *     type           - [IN] type of parameter (file or command line)         *
 *     param          - [IN] address of the parameter variable                *
 *     config_tls     - [IN]                                                  *
 *                                                                            *
 ******************************************************************************/
static const char	*zbx_tls_parameter_name(int type, char * const *param, const zbx_config_tls_t *config_tls)
{
	if (&(config_tls->connect) == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSConnect" : "--tls-connect";

	if (&(config_tls->accept) == param)
		return "TLSAccept";

	if (&(config_tls->ca_file) == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSCAFile" : "--tls-ca-file";

	if (&(config_tls->crl_file) == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSCRLFile" : "--tls-crl-file";

	if (&(config_tls->server_cert_issuer) == param)
	{
		if (ZBX_TLS_PARAMETER_CONFIG_FILE == type)
			return "TLSServerCertIssuer";

		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_GET))
			return "--tls-agent-cert-issuer";
		else
			return "--tls-server-cert-issuer";
	}

	if (&(config_tls->server_cert_subject) == param)
	{
		if (ZBX_TLS_PARAMETER_CONFIG_FILE == type)
			return "TLSServerCertSubject";

		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_GET))
			return "--tls-agent-cert-subject";
		else
			return "--tls-server-cert-subject";
	}

	if (&(config_tls->cert_file) == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSCertFile" : "--tls-cert-file";

	if (&(config_tls->key_file) == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSKeyFile" : "--tls-key-file";

	if (&(config_tls->psk_identity) == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSPSKIdentity" : "--tls-psk-identity";

	if (&(config_tls->psk_file) == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSPSKFile" : "--tls-psk-file";

	if (&(config_tls->cipher_cert13) == param)
		return "TLSCipherCert13";

	if (&(config_tls->cipher_cert) == param)
		return "TLSCipherCert";

	if (&(config_tls->cipher_psk13) == param)
		return "TLSCipherPSK13";

	if (&(config_tls->cipher_psk) == param)
		return "TLSCipherPSK";

	if (&(config_tls->cipher_all13) == param)
		return "TLSCipherAll13";

	if (&(config_tls->cipher_all) == param)
		return "TLSCipherAll";

	if (&(config_tls->cipher_cmd13) == param)
		return "--tls-cipher13";

	if (&(config_tls->cipher_cmd) == param)
		return "--tls-cipher";

	THIS_SHOULD_NEVER_HAPPEN;

	zbx_tls_free();
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Helper function: check if a configuration parameter is defined it must *
 *     not be empty. Otherwise log error and exit.                            *
 *                                                                            *
 * Parameters:                                                                *
 *     param      - [IN] address of the parameter variable                    *
 *     config_tls - [IN]                                                      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tls_parameter_not_empty(char * const *param, const zbx_config_tls_t *config_tls)
{
	const char	*value = *param;

	if (NULL != value)
	{
		while ('\0' != *value)
		{
			if (0 == isspace(*value++))
				return;
		}

		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SENDER))
		{
			const char	*name1, *name2;

			name1 = zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param, config_tls);
			name2 = zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param, config_tls);

			if (0 != strcmp(name1, name2))
			{
				zabbix_log(LOG_LEVEL_CRIT, "configuration parameter \"%s\" or \"%s\" is defined but"
						" empty", name1, name2);
			}
			else
			{
				zabbix_log(LOG_LEVEL_CRIT, "configuration parameter \"%s\" is defined but empty",
						name1);
			}
		}
		else if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "configuration parameter \"%s\" is defined but empty",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param, config_tls));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "configuration parameter \"%s\" is defined but empty",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param, config_tls));
		}

		zbx_tls_free();
		exit(EXIT_FAILURE);
	}
}

/**************************************************************************************
 *                                                                                    *
 * Purpose:                                                                           *
 *     Helper function: log error message depending on program type and exit.         *
 *                                                                                    *
 * Parameters:                                                                        *
 *     type       - [IN] type of TLS validation error                                 *
 *     param1     - [IN] first configuration parameter                                *
 *     param2     - [IN] second configuration parameter (if there is any)             *
 *     config_tls - [IN]                                                              *
 *                                                                                    *
 **************************************************************************************/
#define ZBX_TLS_VALIDATION_INVALID	0
#define ZBX_TLS_VALIDATION_DEPENDENCY	1
#define ZBX_TLS_VALIDATION_REQUIREMENT	2
#define ZBX_TLS_VALIDATION_UTF8		3
#define ZBX_TLS_VALIDATION_NO_PSK	4
static void	zbx_tls_validation_error(int type, char **param1, char **param2, const zbx_config_tls_t *config_tls)
{
	if (ZBX_TLS_VALIDATION_INVALID == type)
	{
		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "invalid value of \"%s\" or \"%s\" parameter",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls));
		}
		else if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "invalid value of \"%s\" parameter",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "invalid value of \"%s\" parameter",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls));
		}
	}
	else if (ZBX_TLS_VALIDATION_DEPENDENCY == type)
	{
		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" or \"%s\" is defined,"
					" but neither \"%s\" nor \"%s\" is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2, config_tls));
		}
		else if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined, but \"%s\" is not defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2, config_tls));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined, but \"%s\" is not defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1,  config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2,  config_tls));
		}
	}
	else if (ZBX_TLS_VALIDATION_REQUIREMENT == type)
	{
		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" or \"%s\" value requires \"%s\" or \"%s\","
					" but neither of them is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2, config_tls));
		}
		else if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" value requires \"%s\", but it is not defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2, config_tls));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" value requires \"%s\", but it is not defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2, config_tls));
		}
	}
	else if (ZBX_TLS_VALIDATION_UTF8 == type)
	{
		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" or \"%s\" value is not a valid UTF-8 string",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls));
		}
		else if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" value is not a valid UTF-8 string",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" value is not a valid UTF-8 string",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls));
		}
	}
	else if (ZBX_TLS_VALIDATION_NO_PSK == type)
	{
		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "value of parameter \"%s\" or \"%s\" requires support of encrypted"
					" connection with PSK but support for PSK was not compiled in",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls));
		}
		else if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "value of parameter \"%s\" requires support of encrypted"
					" connection with PSK but support for PSK was not compiled in",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "value of parameter \"%s\" requires support of encrypted"
					" connection with PSK but support for PSK was not compiled in",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls));
		}
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_tls_free();
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Helper function: log error message depending on program type and exit  *
 *                                                                            *
 * Parameters:                                                                *
 *     type       - [IN] type of TLS validation error                         *
 *     param1     - [IN] first configuration parameter                        *
 *     param2     - [IN] second configuration parameter                       *
 *     param3     - [IN] third configuration parameter                        *
 *     config_tls - [IN]                                                      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tls_validation_error2(int type, char **param1, char **param2, char **param3,
		const zbx_config_tls_t *config_tls)
{
	if (ZBX_TLS_VALIDATION_DEPENDENCY == type)
	{
		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_AGENTD))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined,"
					" but neither \"%s\" nor \"%s\" is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param3, config_tls));
		}
		else if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined,"
					" but neither \"%s\" nor \"%s\" is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param3, config_tls));
		}
		else if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined,"
					" but neither \"%s\", nor \"%s\", nor \"%s\", nor \"%s\" is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param3, config_tls),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param3, config_tls));
		}
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_tls_free();
	exit(EXIT_FAILURE);
}
#undef ZBX_TLS_PARAMETER_CONFIG_FILE
#undef ZBX_TLS_PARAMETER_COMMAND_LINE

/**********************************************************************************************
 *                                                                                            *
 * Purpose: check for allowed combinations of TLS configuration parameters                    *
 *          and also initialize the program_type callback                                     *
 *                                                                                            *
 * Comments:                                                                                  *
 *     Valid combinations:                                                                    *
 *         - either all 3 certificate parameters - config_tls->config_tls_cert_file,          *
 *           config_tls->config_tls_key_file, config_tls->config_tls_ca_file  -               *
 *           are defined and not empty or none of them. Parameter                             *
 *           config_tls->config_tls_crl_file is optional but may be defined only together     *
 *           with the 3 certificate parameters,                                               *
 *         - either both PSK parameters - config_tls->config_tls_psk_identity and             *
 *           config_tls->config_tls_psk_file - are defined and not empty or none of them,     *
 *           (if config_tls->config_tls_psk_identity is defined it must be a valid UTF-8      *
 *           string),                                                                         *
 *         - in active agent, active proxy, zabbix_get, and zabbix_sender the                 *
 *           certificate and PSK parameters must match the value of                           *
 *           config_tls->config_tls_connect parameter,                                        *
 *         - in passive agent and passive proxy the certificate and PSK                       *
 *           parameters must match the value of config_tls->config_tls_accept parameter.      *
 *                                                                                            *
 *********************************************************************************************/
void	zbx_tls_validate_config(zbx_config_tls_t *config_tls, int config_active_forks,
		int config_passive_forks, zbx_get_program_type_f zbx_get_program_type_cb_arg)
{
	zbx_get_program_type_cb = zbx_get_program_type_cb_arg;

	zbx_tls_parameter_not_empty(&(config_tls->connect), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->accept), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->ca_file), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->crl_file), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->server_cert_issuer), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->server_cert_subject), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->cert_file), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->key_file), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->psk_identity), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->psk_file), config_tls);

	zbx_tls_parameter_not_empty(&(config_tls->cipher_cert13), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->cipher_psk13), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->cipher_all13), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->cipher_cmd13), config_tls);

	zbx_tls_parameter_not_empty(&(config_tls->cipher_cert), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->cipher_psk), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->cipher_all), config_tls);
	zbx_tls_parameter_not_empty(&(config_tls->cipher_cmd), config_tls);

	/* parse and validate 'TLSConnect' parameter (in zabbix_proxy.conf, zabbix_agentd.conf) and '--tls-connect' */
	/* parameter (in zabbix_get and zabbix_sender) */

	if (NULL != config_tls->connect)
	{
		if (0 == strcmp(config_tls->connect, ZBX_TCP_SEC_UNENCRYPTED_TXT))
		{
			config_tls->connect_mode = ZBX_TCP_SEC_UNENCRYPTED;
		}
		else if (0 == strcmp(config_tls->connect, ZBX_TCP_SEC_TLS_CERT_TXT))
		{
			config_tls->connect_mode = ZBX_TCP_SEC_TLS_CERT;
		}
		else if (0 == strcmp(config_tls->connect, ZBX_TCP_SEC_TLS_PSK_TXT))
		{
#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
			config_tls->connect_mode = ZBX_TCP_SEC_TLS_PSK;
#else
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_NO_PSK, &(config_tls->connect), NULL,
					config_tls);
#endif
		}
		else
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_INVALID, &(config_tls->connect), NULL,
					config_tls);
		}
	}

	/* parse and validate 'TLSAccept' parameter (in zabbix_proxy.conf, zabbix_agentd.conf) */

	if (NULL != config_tls->accept)
	{
		char		*s, *p, *delim;
		unsigned int	accept_modes_tmp = 0;	/* 'accept_modes' is shared between threads on */
							/* MS Windows. To avoid races make a local temporary */
							/* variable, modify it and write into */
							/* 'accept_modes' when done. */

		p = s = zbx_strdup(NULL, config_tls->accept);

		while (1)
		{
			delim = strchr(p, ',');

			if (NULL != delim)
				*delim = '\0';

			if (0 == strcmp(p, ZBX_TCP_SEC_UNENCRYPTED_TXT))
			{
				accept_modes_tmp |= ZBX_TCP_SEC_UNENCRYPTED;
			}
			else if (0 == strcmp(p, ZBX_TCP_SEC_TLS_CERT_TXT))
			{
				accept_modes_tmp |= ZBX_TCP_SEC_TLS_CERT;
			}
			else if (0 == strcmp(p, ZBX_TCP_SEC_TLS_PSK_TXT))
			{
#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
				accept_modes_tmp |= ZBX_TCP_SEC_TLS_PSK;
#else
				zbx_tls_validation_error(ZBX_TLS_VALIDATION_NO_PSK, &(config_tls->accept), NULL,
						config_tls);
#endif
			}
			else
			{
				zbx_free(s);
				zbx_tls_validation_error(ZBX_TLS_VALIDATION_INVALID, &(config_tls->accept), NULL,
						config_tls);
			}

			if (NULL == delim)
				break;

			p = delim + 1;
		}

		config_tls->accept_modes = accept_modes_tmp;

		zbx_free(s);
	}

	/* either both a certificate and a private key must be defined or none of them */

	if (NULL != config_tls->cert_file && NULL == config_tls->key_file)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cert_file),
				&(config_tls->key_file), config_tls);
	}

	if (NULL != (config_tls->key_file) && NULL == (config_tls->cert_file))
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->key_file),
				&(config_tls->cert_file), config_tls);
	}
	/* CA file must be defined only together with a certificate */

	if (NULL != (config_tls->cert_file) && NULL == (config_tls->ca_file))
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cert_file),
				&(config_tls->ca_file), config_tls);
	}

	if (NULL != (config_tls->ca_file) && NULL == (config_tls->cert_file))
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->ca_file),
				&(config_tls->cert_file), config_tls);
	}

	/* CRL file is optional but must be defined only together with a certificate */
	if (NULL == (config_tls->cert_file) && NULL != (config_tls->crl_file))
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->crl_file),
				&(config_tls->cert_file), config_tls);
	}

	/* Server certificate issuer is optional but must be defined only together with a certificate */
	if (NULL == config_tls->cert_file && NULL != config_tls->server_cert_issuer)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->server_cert_issuer),
				&(config_tls->cert_file), config_tls);
	}

	/* Server certificate subject is optional but must be defined only together with a certificate */

	if (NULL == config_tls->cert_file && NULL != config_tls->server_cert_subject)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->server_cert_subject),
				&(config_tls->cert_file), config_tls);
	}

	/* either both a PSK and a PSK identity must be defined or none of them */

	if (NULL != config_tls->psk_file && NULL == config_tls->psk_identity)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->psk_file),
				&(config_tls->psk_identity), config_tls);
	}

	if (NULL != config_tls->psk_identity && NULL == config_tls->psk_file)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->psk_identity),
				&(config_tls->psk_file), config_tls);
	}

	/* PSK identity must be a valid UTF-8 string (RFC 4279 says Unicode) */
	if (NULL != config_tls->psk_identity && SUCCEED != zbx_is_utf8(config_tls->psk_identity))
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_UTF8, &(config_tls->psk_identity), NULL, config_tls);
	}

	/* active agentd, active proxy, zabbix_get, and zabbix_sender specific validation */

	if ((0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_AGENTD) && 0 != config_active_forks) ||
			(0 != (zbx_get_program_type_cb() & (ZBX_PROGRAM_TYPE_PROXY_ACTIVE | ZBX_PROGRAM_TYPE_GET |
					ZBX_PROGRAM_TYPE_SENDER))))
	{
		/* 'TLSConnect' is the master parameter to be matched by certificate and PSK parameters. */

		if (NULL != config_tls->cert_file && NULL == config_tls->connect)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cert_file),
					&(config_tls->connect), config_tls);
		}

		if (NULL != config_tls->psk_file && NULL == config_tls->connect)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->psk_file),
					&(config_tls->connect), config_tls);
		}

		if (0 != (config_tls->connect_mode & ZBX_TCP_SEC_TLS_CERT) && NULL == config_tls->cert_file)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_REQUIREMENT, &(config_tls->connect),
					&(config_tls->cert_file), config_tls);
		}

		if (0 != (config_tls->connect_mode & ZBX_TCP_SEC_TLS_PSK) && NULL == config_tls->psk_file)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_REQUIREMENT, &(config_tls->connect),
					&(config_tls->psk_file), config_tls);
		}
	}

	/* passive agentd and passive proxy specific validation */

	if ((0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_AGENTD) && 0 != config_passive_forks) ||
			0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
	{
		/* 'TLSAccept' is the master parameter to be matched by certificate and PSK parameters */

		if (NULL != config_tls->cert_file && NULL == config_tls->accept)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cert_file),
					&(config_tls->accept), config_tls);
		}

		if (NULL != config_tls->psk_file && NULL == config_tls->accept)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->psk_file),
					&(config_tls->accept), config_tls);
		}

		if (0 != (config_tls->accept_modes & ZBX_TCP_SEC_TLS_CERT) && NULL == config_tls->cert_file)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_REQUIREMENT, &(config_tls->accept),
					&(config_tls->cert_file), config_tls);
		}

		if (0 != (config_tls->accept_modes & ZBX_TCP_SEC_TLS_PSK) && NULL == config_tls->psk_file)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_REQUIREMENT, &(config_tls->accept),
					&(config_tls->psk_file), config_tls);
		}
	}

	/* TLSCipher* and --tls-cipher* parameter validation */

	/* parameters 'TLSCipherCert13' and 'TLSCipherCert' can be used only with certificate */

	if (NULL != config_tls->cipher_cert13 && NULL == config_tls->cert_file)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cipher_cert13),
					&(config_tls->cert_file), config_tls);
	}

	if (NULL != config_tls->cipher_cert && NULL == config_tls->cert_file)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cipher_cert),
				&(config_tls->cert_file), config_tls);
	}
	/* For server and proxy 'TLSCipherPSK13' and 'TLSCipherPSK' are optional and do not depend on other */
	/* TLS parameters. Validate only in case of agent, zabbix_get and sender. */

	if (0 != (zbx_get_program_type_cb() & (ZBX_PROGRAM_TYPE_AGENTD | ZBX_PROGRAM_TYPE_GET |
			ZBX_PROGRAM_TYPE_SENDER)))
	{
		if (NULL !=  config_tls->cipher_psk13 && NULL == config_tls->psk_identity)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cipher_psk13),
					&(config_tls->psk_identity), config_tls);
		}

		if (NULL != config_tls->cipher_psk && NULL == config_tls->psk_identity)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cipher_psk),
					&(config_tls->psk_identity), config_tls);
		}
	}

	/* Parameters 'TLSCipherAll13' and 'TLSCipherAll' are used only for incoming connections if a combined list */
	/* of certificate- and PSK-based ciphersuites is used. They may be defined without other TLS parameters on */
	/* server and proxy (at least some hosts may be connecting with PSK). */
	/* 'zabbix_get' and sender do not use these parameters. Validate only in case of agent. */

	if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_AGENTD) && NULL ==
			config_tls->cert_file && NULL == config_tls->psk_identity)
	{
		if (NULL != config_tls->cipher_all13)
		{
			zbx_tls_validation_error2(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cipher_all13),
					&(config_tls->cert_file), &(config_tls->psk_identity), config_tls);
		}

		if (NULL != config_tls->cipher_all)
		{
			zbx_tls_validation_error2(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cipher_all),
					&(config_tls->cert_file), &(config_tls->psk_identity), config_tls);
		}
	}

	/* Parameters '--tls-cipher13' and '--tls-cipher' can be used only in zabbix_get and sender with */
	/* certificate or PSK. */

	if (0 != (zbx_get_program_type_cb() & (ZBX_PROGRAM_TYPE_GET | ZBX_PROGRAM_TYPE_SENDER)) &&
			NULL == config_tls->cert_file && NULL == config_tls->psk_identity)
	{
		if (NULL != config_tls->cipher_cmd13)
		{
			zbx_tls_validation_error2(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cipher_cmd13),
					&(config_tls->cert_file), &(config_tls->psk_identity), config_tls);
		}

		if (NULL != config_tls->cipher_cmd)
		{
			zbx_tls_validation_error2(ZBX_TLS_VALIDATION_DEPENDENCY, &(config_tls->cipher_cmd),
					&(config_tls->cert_file), &(config_tls->psk_identity), config_tls);
		}
	}
}
#endif
