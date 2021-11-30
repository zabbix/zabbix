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

package dns

import (
	"fmt"
	"net"
	"strconv"
	"time"

	"github.com/miekg/dns"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxerr"
)

const (
	tcpProtocol = "tcp"
	udpProtocol = "udp"

	sixthParam  = 5
	fifthParam  = 4
	fourthParam = 3
	thirdParam  = 2
	secondParam = 1
	firstParam  = 0
)

type options struct {
	ip       string
	name     string
	protocol string
	dnsType  uint16
	count    int
	timeout  time.Duration
}

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

var dnsTypes = map[string]uint16{
	"ANY":   dns.TypeANY,
	"A":     dns.TypeA,
	"NS":    dns.TypeNS,
	"CNAME": dns.TypeCNAME,
	"MB":    dns.TypeMB,
	"MG":    dns.TypeMG,
	"MR":    dns.TypeMR,
	"PTR":   dns.TypePTR,
	"MD":    dns.TypeMD,
	"MF":    dns.TypeMF,
	"MX":    dns.TypeMX,
	"SOA":   dns.TypeSOA,
	"NULL":  dns.TypeNULL,
	"HINFO": dns.TypeHINFO,
	"MINFO": dns.TypeMINFO,
	"TXT":   dns.TypeTXT,
	"AAAA":  dns.TypeAAAA,
	"SRV":   dns.TypeSRV,
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var full bool
	switch key {
	case "net.dns":
	case "net.dns.record":
		full = true
	default:
		err = zbxerr.ErrorUnsupportedMetric

		return
	}

	options, err := parseParamas(params)
	if err != nil {
		return
	}

	resp, err := runQuery(options.ip, options.name, options.protocol, options.dnsType, options.timeout)
	if err != nil {
		p.Debugf("failed to run dns query for ip: %s", options.ip)

		return 0, nil
	}

	if len(resp.Answer) < 1 {
		return 0, nil
	}

	if full {
		fmt.Println("Class", resp.Answer[0].Header().Class)
		fmt.Println("Name", resp.Answer[0].Header().Name)
		fmt.Println("Rrtype", resp.Answer[0].Header().Rrtype)
		fmt.Println("Ttl", resp.Answer[0].Header().Ttl)
		fmt.Println("Rdlength", resp.Answer[0].Header().Rdlength)
		return resp.Answer[0].String(), nil
	}

	return 1, nil
}

func parseParamas(params []string) (o options, err error) {
	switch len(params) {
	case 6:
		err = o.setProtocol(params[sixthParam])
		if err != nil {
			return
		}

		fallthrough
	case 5:
		err = o.setCount(params[fifthParam])
		if err != nil {
			return
		}

		fallthrough
	case 4:
		err = o.setTimeout(params[fourthParam])
		if err != nil {
			return
		}

		fallthrough
	case 3:
		err = o.setDNSType(params[thirdParam])
		if err != nil {
			return
		}

		fallthrough
	case 2:
		o.name = params[secondParam]
		fallthrough
	case 1:
		if params[firstParam] != "" {
			o.ip = net.JoinHostPort(params[firstParam], "53")
		}

		fallthrough
	case 0:
		o.setDefaults()
	default:
		err = zbxerr.ErrorTooManyParameters

		return
	}

	return
}

func (o *options) setProtocol(protocol string) error {
	switch protocol {
	case tcpProtocol:
		o.protocol = tcpProtocol
	case udpProtocol, "":
		o.protocol = udpProtocol
	default:
		return zbxerr.New("invalid sixth parameter")
	}

	return nil
}

func (o *options) setCount(c string) error {
	count, err := strconv.Atoi(c)
	if err != nil {
		return zbxerr.New(fmt.Sprintf("invalid fifth parameter %s", err.Error()))
	}

	if count < 0 {
		return zbxerr.New("invalid fourth parameter")
	}

	o.count = count

	return nil
}

func (o *options) setTimeout(timeout string) error {
	t, err := strconv.Atoi(timeout)
	if err != nil {
		return zbxerr.New(fmt.Sprintf("invalid fourth parameter %s", err.Error()))
	}

	if t < 0 {
		return zbxerr.New("invalid fourth parameter")
	}

	o.timeout = time.Duration(t) * time.Second

	return nil
}

func (o *options) setDNSType(dnsType string) error {
	t, ok := dnsTypes[dnsType]
	if !ok {
		return zbxerr.New(fmt.Sprintf("unknown dns type %d", t))

	}

	o.dnsType = t

	return nil
}

func (o *options) setDefaults() {
	if o.ip == "" {
		o.setDefaultIP()
	}

	if o.dnsType == dns.TypeNone {
		o.dnsType = dns.TypeSOA
	}

	if o.timeout < 1 {
		o.timeout = 1 * time.Second
	}

	if o.count < 1 {
		o.count = 2
	}
}

func runQuery(resolver, domain, net string, record uint16, timeout time.Duration) (*dns.Msg, error) {
	c := new(dns.Client)
	c.Net = net
	c.DialTimeout = timeout
	c.ReadTimeout = timeout
	c.WriteTimeout = timeout

	m := &dns.Msg{
		MsgHdr: dns.MsgHdr{
			// CheckingDisabled: options.CheckingDisabled,
			// RecursionDesired: options.RecursionDesired,
			Opcode: dns.OpcodeQuery,
			Rcode:  dns.RcodeSuccess,
		},
		Question: make([]dns.Question, 1),
	}

	m.SetEdns0(4096, false)
	m.Question[0] = dns.Question{Name: dns.Fqdn(domain), Qtype: record, Qclass: dns.ClassINET}
	m.Id = dns.Id()

	r, _, err := c.Exchange(m, resolver)

	return r, err
}

func init() {
	plugin.RegisterMetrics(&impl, "DNS",
		"net.dns", "Checks if DNS service is up.",
		"net.dns.record", "	Performs a DNS query.",
	)
}
