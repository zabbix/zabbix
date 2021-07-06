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
package webcertificate

import (
	"crypto/tls"
	"crypto/x509"
	"encoding/json"
	"fmt"
	"math/big"
	"time"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxerr"
)

type Output struct {
	X509   Cert
	Result ValidationResult
}

type CertTime struct {
	Value     string
	Timestamp int64
}

type Cert struct {
	Version            int
	Serial             *big.Int
	SignatureAlgorithm x509.SignatureAlgorithm
	Issuer             string
	NotBefore          CertTime
	NotAfter           CertTime
	Subject            string
	AlternativeNames   []string
	PublicKeyAlgorithm string
}

type ValidationResult struct {
	Value   string
	Message string
}

type Options struct {
	Timeout int `conf:"optional,range=1:30"`
}

type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (interface{}, error) {
	if key != "web.certificate.get" {
		return nil, plugin.UnsupportedMetricError
	}

	var hostname, port, ip, connection string

	switch len(params) {
	case 3:
		ip = params[2]
		port = params[1]
		hostname = params[0]
		connection = fmt.Sprintf("%s:%s", ip, port)
	case 2:
		port = params[1]
		hostname = params[0]
		connection = fmt.Sprintf("%s:%s", hostname, port)
	case 1:
		hostname = params[0]
		connection = fmt.Sprintf("%s:%s", hostname, "443")
	default:
		return nil, zbxerr.ErrorInvalidParams
	}

	certs, err := getCertificatesPEM(connection)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if len(certs) < 1 {
		return nil, zbxerr.ErrorCannotFetchData
	}

	var o Output
	cert := certs[0]

	o.X509 = Cert{
		cert.Version, cert.SerialNumber, cert.SignatureAlgorithm, cert.Issuer.ToRDNSequence().String(),
		CertTime{cert.NotBefore.Format(time.RFC822), cert.NotBefore.Unix()},
		CertTime{cert.NotAfter.Format(time.RFC822), cert.NotAfter.Unix()}, cert.Subject.ToRDNSequence().String(),
		cert.DNSNames, cert.PublicKeyAlgorithm.String(),
	}

	o.Result = ValidationResult{"valid", fmt.Sprintf("certificate for %s is valid", hostname)}

	b, err := json.Marshal(o)
	if err != nil {
		return nil, err
	}

	return string(b), nil
}

func getCertificatesPEM(address string) ([]*x509.Certificate, error) {
	conn, err := tls.Dial("tcp", address, &tls.Config{InsecureSkipVerify: true})
	if err != nil {
		return nil, err
	}

	defer conn.Close()

	return conn.ConnectionState().PeerCertificates, nil
}

func init() {
	plugin.RegisterMetrics(&impl, "WebCertificate", "web.certificate.get", "Get TLS/SSL website certificates.")
}
