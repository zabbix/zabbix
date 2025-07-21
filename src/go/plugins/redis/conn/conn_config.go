/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
** documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
** rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
** permit persons to whom the Software is furnished to do so, subject to the following conditions:
**
** The above copyright notice and this permission notice shall be included in all copies or substantial portions
** of the Software.
**
** THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
** WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
** COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
** TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
** SOFTWARE.
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
	//nolint:errcheck //ignoring error because all wrong options were eliminated at the validation.
	tlsConnectionType, _ := comms.NewTLSConnectionType(tlsConnect)

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

	switch tlsConnectionType {
	case comms.Disabled:
		return nil, errTLSDisabled
	case comms.Required:
		return &tls.Config{InsecureSkipVerify: true}, nil //nolint:gosec //intended behavior
	case comms.VerifyCA:
		// in case if default values are set
		details.Apply(tlsconfig.WithTLSCertFile(""), tlsconfig.WithTLSKeyFile(""))

		tlsConfig, err := details.GetTLSConfig()
		if err != nil {
			return nil, errs.Wrap(err, "failed to get tls config")
		}

		return tlsConfig, nil
	case comms.VerifyFull:
		tlsConfig, err := details.GetTLSConfig()
		if err != nil {
			return nil, errs.Wrap(err, "failed to get tls config")
		}

		return tlsConfig, nil
	default:
		return nil, errs.New("unsupported TLS connection type: " + string(tlsConnectionType))
	}
}
