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
	"fmt"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/uri"
)

// RedisConfig encapsulates all parameters needed to create a connection.
type RedisConfig struct {
	URI               uri.URI
	TLSConnectionType comms.TLSConnectionType
	TLSCertFile       string
	TLSServerName     string
	TLSKeyFile        string
	TLSCAFile         string
}

// NewConfig creates a RedisConfig from a map of parameters. No param validation is made.
// It is responsible for parsing, validation, and URI creation.
func NewConfig(redisURI *uri.URI, params map[string]string) RedisConfig {
	var cfg RedisConfig

	cfg.URI = *redisURI

	tlsConnect := params[string(comms.TLSConnect)]
	tlsConnectionType, _ := comms.NewTLSConnectionType(tlsConnect) //ignoring error because all wrong
	// configurations were deleted at config validation moment

	cfg.TLSConnectionType = tlsConnectionType
	cfg.TLSCertFile = params[string(comms.TLSCertFile)]
	cfg.TLSServerName = params[string(comms.TLSServerName)]
	cfg.TLSKeyFile = params[string(comms.TLSKeyFile)]
	cfg.TLSCAFile = params[string(comms.TLSCAFile)]

	return cfg
}

func (cfg RedisConfig) GetTlsConfig() (*tls.Config, error) {
	details := tlsconfig.NewDetails(
		"",
		cfg.URI.String(),
		tlsconfig.WithTlsConnect(string(cfg.TLSConnectionType)),
		tlsconfig.WithTlsCaFile(cfg.TLSCAFile),
		//todo add servername
		tlsconfig.WithTlsCertFile(cfg.TLSCertFile),
		tlsconfig.WithTlsKeyFile(cfg.TLSKeyFile),
		tlsconfig.WithAllowedConnections(
			string(comms.NoTLS),
			string(comms.Insecure),
			string(comms.VerifyCA),
			string(comms.VerifyFull),
		),
	)

	switch cfg.TLSConnectionType {
	case comms.NoTLS:
		return nil, nil
	case comms.Insecure:
		return &tls.Config{InsecureSkipVerify: true}, nil
	case comms.VerifyCA:
		tlsConfig, err := details.GetTLSConfig(false)
		if err != nil {
			return nil, errs.Wrap(err, "failed to get tls config")
		}

		tlsConfig.VerifyPeerCertificate = tlsconfig.VerifyPeerCertificateFunc(tlsConfig.ServerName, tlsConfig.RootCAs)

		return tlsConfig, nil
	case comms.VerifyFull:
		tlsConfig, err := details.GetTLSConfig(false)
		if err != nil {
			return nil, errs.Wrap(err, "failed to get tls config")
		}

		return tlsConfig, nil
	default:
		return nil, fmt.Errorf("unsupported TLS connection type: %s", cfg.TLSConnectionType)
	}
}
