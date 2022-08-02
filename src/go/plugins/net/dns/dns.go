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
	"strings"
	"time"

	"github.com/miekg/dns"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxerr"
)

const (
	base10 = 10

	tcpProtocol = "tcp"
	udpProtocol = "udp"
)

const (
	noneParam = iota
	firstParam
	secondParam
	thirdParam
	fourthParam
	fifthParam
	sixthParam
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
	switch key {
	case "net.dns":
		return exportDns(params)
	case "net.dns.record":
		return exportDnsRecord(params)
	default:
		err = zbxerr.ErrorUnsupportedMetric

		return
	}
}

func exportDns(params []string) (result interface{}, err error) {
	answer, err := getDNSAnswers(params)
	if err != nil {
		return
	}

	if len(answer) < 1 {
		return 0, nil
	}

	return 1, nil
}

func exportDnsRecord(params []string) (result interface{}, err error) {
	answer, err := getDNSAnswers(params)
	if err != nil {
		return
	}

	if len(answer) < 1 {
		return nil, zbxerr.New("Cannot perform DNS query.")
	}

	return parseAnswers(answer), nil
}

func parseAnswers(answers []dns.RR) string {
	var out string
	answersNum := len(answers)
	for i, a := range answers {
		out += fmt.Sprintf("%-20s", strings.TrimSuffix(a.Header().Name, "."))
		out += fmt.Sprintf(" %-8s ", dns.Type(a.Header().Rrtype).String())

		switch rr := a.(type) {
		case *dns.A:
			out += getAString(rr)
		case *dns.NS:
			out += getNSString(rr)
		case *dns.CNAME:
			out += getCNAMEString(rr)
		case *dns.MB:
			out += getMBString(rr)
		case *dns.MG:
			out += getMGString(rr)
		case *dns.PTR:
			out += getPTRString(rr)
		case *dns.MD:
			out += getMDString(rr)
		case *dns.MF:
			out += getMFString(rr)
		case *dns.MX:
			out += getMXString(rr)
		case *dns.SOA:
			out += getSOAString(rr)
		case *dns.NULL:
			out += getNULLString(rr)
		case *dns.HINFO:
			out += getHINFOString(rr)
		case *dns.MINFO:
			out += getMINFOString(rr)
		case *dns.TXT:
			out += getTXTString(rr)
		case *dns.AAAA:
			out += getAAAAString(rr)
		case *dns.SRV:
			out += getSRVString(rr)
		}

		if i != answersNum-1 {
			out += "\n"
		}
	}

	return out
}

func getDNSAnswers(params []string) ([]dns.RR, error) {
	options, err := parseParamas(params)
	if err != nil {
		return nil, err
	}

	var resp *dns.Msg
	for i := 1; i <= options.count; i++ {
		resp, err = runQuery(options.ip, options.name, options.protocol, options.dnsType, options.timeout)
		if err != nil {
			continue
		}

		break
	}

	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return resp.Answer, nil
}

func getSOAString(in *dns.SOA) string {
	return strings.TrimSuffix(in.Ns, ".") +
		" " + strings.TrimSuffix(in.Mbox, ".") +
		" " + strconv.FormatInt(int64(in.Serial), base10) +
		" " + strconv.FormatInt(int64(in.Refresh), base10) +
		" " + strconv.FormatInt(int64(in.Retry), base10) +
		" " + strconv.FormatInt(int64(in.Expire), base10) +
		" " + strconv.FormatInt(int64(in.Minttl), base10)
}

func getAString(in *dns.A) string {
	if in.A == nil {
		return "\n"
	}

	return in.A.String()
}

func getNSString(in *dns.NS) string {
	return strings.TrimSuffix(in.Ns, ".")
}

func getCNAMEString(in *dns.CNAME) string {
	return strings.TrimSuffix(in.Target, ".")
}

func getMBString(in *dns.MB) string {
	return strings.TrimSuffix(in.Mb, ".")
}

func getMGString(in *dns.MG) string {
	return strings.TrimSuffix(in.Mg, ".")
}

func getPTRString(in *dns.PTR) string {
	return strings.TrimSuffix(in.Ptr, ".")
}

func getMDString(in *dns.MD) string {
	return strings.TrimSuffix(in.Md, ".")
}

func getMFString(in *dns.MF) string {
	return strings.TrimSuffix(in.Mf, ".")
}

func getMXString(in *dns.MX) string {
	return strconv.Itoa(int(in.Preference)) +
		" " + strings.TrimSuffix(in.Mx, ".")
}

func getNULLString(in *dns.NULL) string {
	return strings.TrimSuffix(in.Data, ".")
}

func getHINFOString(in *dns.HINFO) string {
	return parseTXT(in.Cpu, in.Os)

}

func getMINFOString(in *dns.MINFO) string {
	return strings.TrimSuffix(in.Rmail, ".") + " " +
		strings.TrimSuffix(in.Email, ".")
}

func getTXTString(in *dns.TXT) string {
	return parseTXT(in.Txt...)
}

func getAAAAString(in *dns.AAAA) string {
	if in.AAAA == nil {
		return "\n"
	}

	return in.AAAA.String()
}

func getSRVString(in *dns.SRV) string {
	return strconv.Itoa(int(in.Priority)) + " " +
		strconv.Itoa(int(in.Weight)) + " " +
		strconv.Itoa(int(in.Port)) + " " +
		strings.TrimSuffix(in.Target, ".")
}

func parseTXT(in ...string) string {
	var out string
	for _, s := range in {
		if s != "" {
			out += "\"" + s + "\"" + " "
		}
	}

	return strings.TrimSpace(out)
}

func parseParamas(params []string) (o options, err error) {
	switch len(params) {
	case sixthParam:
		err = o.setProtocol(params[sixthParam-1])
		if err != nil {
			return
		}

		fallthrough
	case fifthParam:
		err = o.setCount(params[fifthParam-1])
		if err != nil {
			return
		}

		fallthrough
	case fourthParam:
		err = o.setTimeout(params[fourthParam-1])
		if err != nil {
			return
		}

		fallthrough
	case thirdParam:
		err = o.setDNSType(params[thirdParam-1])
		if err != nil {
			return
		}

		fallthrough
	case secondParam:
		o.name = params[secondParam-1]

		fallthrough
	case firstParam:
		err = o.setIP(params[firstParam-1])
		if err != nil {
			return o, zbxerr.New(fmt.Sprintf("invalid fist parameter, %s", err.Error()))
		}

		fallthrough
	case noneParam:
		err = o.setDefaults()
		if err != nil {
			return
		}
	default:
		err = zbxerr.ErrorTooManyParameters

		return
	}

	return
}

func (o *options) setIP(ip string) error {
	if ip == "" {
		return nil
	}

	if !isValidIP(ip) {
		return fmt.Errorf("invalid IP address, %s", ip)
	}

	o.ip = net.JoinHostPort(ip, "53")

	return nil
}

func isValidIP(ip string) bool {
	if r := net.ParseIP(ip); r == nil {
		return false
	}

	return true
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
	if c == "" {
		return nil
	}

	count, err := strconv.Atoi(c)
	if err != nil {
		return zbxerr.New(fmt.Sprintf("invalid fifth parameter, %s", err.Error()))
	}

	if count <= 0 {
		return zbxerr.New("invalid fifth parameter")
	}

	o.count = count

	return nil
}

func (o *options) setTimeout(timeout string) error {
	if timeout == "" {
		return nil
	}

	t, err := strconv.Atoi(timeout)
	if err != nil {
		return zbxerr.New(fmt.Sprintf("invalid fourth parameter, %s", err.Error()))
	}

	if t <= 0 {
		return zbxerr.New("invalid fourth parameter")
	}

	o.timeout = time.Duration(t) * time.Second

	return nil
}

func (o *options) setDNSType(dnsType string) error {
	if dnsType == "" {
		return nil
	}

	t, ok := dnsTypes[strings.ToUpper(dnsType)]
	if !ok {
		return zbxerr.New(fmt.Sprintf("invalid third parameter, unknown dns type %s", dnsType))
	}

	o.dnsType = t

	return nil
}

func (o *options) setDefaults() error {
	if o.ip == "" {
		err := o.setDefaultIP()
		if err != nil {
			return zbxerr.New(err.Error())
		}
	}

	if o.name == "" {
		o.setDefaultName()
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

	if o.protocol == "" {
		o.protocol = udpProtocol
	}

	return nil
}

func (o *options) setDefaultName() {
	o.name = "zabbix.com"
}

func runQuery(resolver, domain, net string, record uint16, timeout time.Duration) (*dns.Msg, error) {
	c := new(dns.Client)
	c.Net = net
	c.DialTimeout = timeout
	c.ReadTimeout = timeout
	c.WriteTimeout = timeout

	m := &dns.Msg{
		MsgHdr: dns.MsgHdr{
			CheckingDisabled: false,
			RecursionDesired: true,
			Opcode:           dns.OpcodeQuery,
			Rcode:            dns.RcodeSuccess,
		},
		Question: make([]dns.Question, 1),
	}

	m.Question[0] = dns.Question{Name: dns.Fqdn(domain), Qtype: record, Qclass: dns.ClassINET}
	r, _, err := c.Exchange(m, resolver)

	return r, err
}

func init() {
	plugin.RegisterMetrics(&impl, "DNS",
		"net.dns", "Checks if DNS service is up.",
		"net.dns.record", "Performs a DNS query.",
	)
}
