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

package conn

import (
	"crypto/tls"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/uri"
)

var errTLSDisabled = errs.New("tls is disabled for this connection")

func getTLSConfig(redisURI *uri.URI, params map[string]string) (*tls.Config, error) {
	tlsConnect := params[string(comms.TLSConnect)]

	tlsConnectionType, err := comms.NewTLSConnectionType(tlsConnect)
	if err != nil {
		return nil, errs.Wrap(err, "invalid TLS connection type")
	}

	details := tlsconfig.NewDetails(
		"",
		redisURI.String(),
		tlsconfig.WithTLSServerName(redisURI.Host()),
		tlsconfig.WithTLSConnect(string(tlsConnectionType)),
		tlsconfig.WithTLSCaFile(params[string(comms.TLSCAFile)]),
		tlsconfig.WithTLSCertFile(params[string(comms.TLSCertFile)]),
		tlsconfig.WithTLSKeyFile(params[string(comms.TLSKeyFile)]),
		tlsconfig.WithAllowedConnections(
			string(comms.Disabled),
			string(comms.Required),
			string(comms.VerifyCA),
			string(comms.VerifyFull),
		),
	)

	// could move this to the tlsconfig.
	switch tlsConnectionType {
	case comms.Disabled:
		return nil, errTLSDisabled
	case comms.Required:
		details.Apply(tlsconfig.WithSkipDefaultTLSVerification(true))
	case comms.VerifyCA:
		details.Apply(
			tlsconfig.WithTLSServerName(""),
			// eliminate default config check to use custom which ignores SAN
			tlsconfig.WithSkipDefaultTLSVerification(true),
			tlsconfig.WithCustomTLSVerification(tlsconfig.VerifyPeerCertificateFunc("", nil)),
		)

	case comms.VerifyFull: // uses default configuration with all provided data.

	default:
		return nil, errs.New("unsupported TLS connection type: " + string(tlsConnectionType))
	}

	tlsConfig, err := details.GetTLSConfig()
	if err != nil {
		return nil, errs.Wrap(err, "failed to get tls config")
	}

	return tlsConfig, nil
}
