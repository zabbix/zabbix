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
	"golang.zabbix.com/sdk/tlsconfig"
)

func getTLSConfig(ck *connKey) (*tls.Config, error) {
	tlsConnect := ck.tlsConnect

	tlsConnectionType, err := tlsconfig.NewTLSConnectionType(tlsConnect)
	if err != nil {
		return nil, errs.Wrap(err, "invalid TLS connection type")
	}

	details := tlsconfig.NewDetails(
		"",
		ck.uri.String(),
		tlsconfig.WithTLSConnect(tlsConnectionType),
		tlsconfig.WithTLSCaFile(ck.tlsCA),
		tlsconfig.WithTLSCertFile(ck.tlsCert),
		tlsconfig.WithTLSKeyFile(ck.tlsKey),
		tlsconfig.WithAllowedConnections(
			string(tlsconfig.Disabled),
			string(tlsconfig.Required),
			string(tlsconfig.VerifyCA),
			string(tlsconfig.VerifyFull),
		),
	)

	tlsConfig, err := details.GetTLSConfig()
	if err != nil {
		return nil, errs.Wrap(err, "failed to get tls config")
	}

	return tlsConfig, nil
}
