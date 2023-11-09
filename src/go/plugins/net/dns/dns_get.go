package dns

import (
	"reflect"
	"encoding/json"
	"flag"
	"time"
	"fmt"
	"strings"
	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/zbxerr"
	"github.com/miekg/dns"
)

var dnsTypesGet = map[string]uint16{
	"None": dns.TypeNone,
	"A": dns.TypeA,
	"NS": dns.TypeNS,
	"MD": dns.TypeMD,
	"MF": dns.TypeMF,
	"CNAME": dns.TypeCNAME,
	"SOA": dns.TypeSOA,
	"MB": dns.TypeMB,
	"MG": dns.TypeMG,
	"MR": dns.TypeMR,
	"NULL": dns.TypeNULL,
	"PTR": dns.TypePTR,
	"HINFO": dns.TypeHINFO,
	"MINFO": dns.TypeMINFO,
	"MX": dns.TypeMX,
	"TXT": dns.TypeTXT,
	"RP": dns.TypeRP,
	"AFSDB": dns.TypeAFSDB,
	"X25": dns.TypeX25,
	"ISDN": dns.TypeISDN,
	"RT": dns.TypeRT,
	"NSAPPTR": dns.TypeNSAPPTR,
	"SIG": dns.TypeSIG,
	"KEY": dns.TypeKEY,
	"PX": dns.TypePX,
	"GPOS": dns.TypeGPOS,
	"AAAA": dns.TypeAAAA,
	"LOC": dns.TypeLOC,
	"NXT": dns.TypeNXT,
	"EID": dns.TypeEID,
	"NIMLOC": dns.TypeNIMLOC,
	"SRV": dns.TypeSRV,
	"ATMA": dns.TypeATMA,
	"NAPTR": dns.TypeNAPTR,
	"KX": dns.TypeKX,
	"CERT": dns.TypeCERT,
	"DNAME": dns.TypeDNAME,
	"OPT": dns.TypeOPT,
	"APL": dns.TypeAPL,
	"DS": dns.TypeDS,
	"SSHFP": dns.TypeSSHFP,
	"RRSIG": dns.TypeRRSIG,
	"NSEC": dns.TypeNSEC,
	"DNSKEY": dns.TypeDNSKEY,
	"DHCID": dns.TypeDHCID,
	"NSEC3": dns.TypeNSEC3,
	"NSEC3PARAM": dns.TypeNSEC3PARAM,
	"TLSA": dns.TypeTLSA,
	"SMIMEA": dns.TypeSMIMEA,
	"HIP": dns.TypeHIP,
	"NINFO": dns.TypeNINFO,
	"RKEY": dns.TypeRKEY,
	"TALINK": dns.TypeTALINK,
	"CDS": dns.TypeCDS,
	"CDNSKEY": dns.TypeCDNSKEY,
	"OPENPGPKEY": dns.TypeOPENPGPKEY,
	"CSYNC": dns.TypeCSYNC,
	"ZONEMD": dns.TypeZONEMD,
	"SVCB": dns.TypeSVCB,
	"HTTPS": dns.TypeHTTPS,
	"SPF": dns.TypeSPF,
	"UINFO": dns.TypeUINFO,
	"UID": dns.TypeUID,
	"GID": dns.TypeGID,
	"UNSPEC": dns.TypeUNSPEC,
	"NID": dns.TypeNID,
	"L32": dns.TypeL32,
	"L64": dns.TypeL64,
	"LP": dns.TypeLP,
	"EUI48": dns.TypeEUI48,
	"EUI64": dns.TypeEUI64,
	"URI": dns.TypeURI,
	"CAA": dns.TypeCAA,
	"AVC": dns.TypeAVC,

	"TKEY": dns.TypeTKEY,
	"TSIG": dns.TypeTSIG,
	//
	"IXFR": dns.TypeIXFR,
	"AXFR": dns.TypeAXFR,
	"MAILB": dns.TypeMAILB,
	"MAILA": dns.TypeMAILA,
	"ANY": dns.TypeANY,

	"TA": dns.TypeTA,
	"DLV": dns.TypeDLV,
	"Reserved": dns.TypeReserved,
}

var (
	//dnskey       *dns.DNSKEY
	short        = flag.Bool("short", false, "abbreviate long DNSSEC records")
	dnssec       = flag.Bool("dnssec", false, "request DNSSEC records")
	//query        = flag.Bool("question", false, "show question")
	//check        = flag.Bool("check", false, "check internal DNSSEC consistency")
	six          = flag.Bool("6", false, "use IPv6 only")
	four         = flag.Bool("4", false, "use IPv4 only")
	//anchor       = flag.String("anchor", "", "use the DNSKEY in this file as trust anchor")
	//tsig         = flag.String("tsig", "", "request tsig with key: [hmac:]name:key")
	//port         = flag.Int("port", 53, "port number to use")
	//laddr        = flag.String("laddr", "", "local address to use")
	aa           = flag.Bool("aa", false, "set AA flag in query")
	ad           = flag.Bool("ad", false, "set AD flag in query")
	cd           = flag.Bool("cd", false, "set CD flag in query")
	rd           = flag.Bool("rd", true, "set RD flag in query")
	//fallback     = flag.Bool("fallback", false, "fallback to 4096 bytes bufsize and after that TCP")
	//tcp          = flag.Bool("tcp", false, "TCP mode, multiple queries are asked over the same connection")
	//timeoutDial  = flag.Duration("timeout-dial", 2*time.Second, "Dial timeout")
	//timeoutRead  = flag.Duration("timeout-read", 2*time.Second, "Read timeout")
	//timeoutWrite = flag.Duration("timeout-write", 2*time.Second, "Write timeout")
	nsid         = flag.Bool("nsid", false, "set edns nsid option")
	//	client       = flag.String("client", "", "set edns client-subnet option")
	//opcode       = flag.String("opcode", "query", "set opcode to query|update|notify")
	//rcode        = flag.String("rcode", "success", "set rcode to noerror|formerr|nxdomain|servfail|...")
)



func parseAnswersGet(answers []dns.RR) string {
	var out string
	answersNum := len(answers)

	log.Infof("AGS 111")
	//	fmt.Println("AGS answersNum: %d", answersNum)
	//fmt.Println("AGS 222")

	for i, a := range answers {
		// fmt.Println("STRATA: i: %d", i)
		// fmt.Println("STRATA: a: %s", a)
		// fmt.Println("STRATA: T: %T", a)

		out += fmt.Sprintf("%-20s", strings.TrimSuffix(a.Header().Name, "."))
		out += fmt.Sprintf(" %-8s ", dns.Type(a.Header().Rrtype).String())

		// switch rr := a.(type) {
		// 	out += 
		// }

		// fmt.Println("OMEGA X222: %s",a.String())

		s := fmt.Sprintf("OMEGA X999: %s", reflect.TypeOf(a))
		log.Infof(s)
		
		out += a.String()

		if i != answersNum-1 {
			out += "\n"
		}
	}

	return out
}

func exportDnsGet(params []string) (result interface{}, err error) {
	answer, err := getDNSAnswersGet(params)
	if err != nil {
		return
	}

	if len(answer) < 1 {
		return nil, zbxerr.New("Cannot perform DNS query.")
	}

	
	return parseAnswersGet(answer), nil
}	



func parseParamasGet(params []string) (o options, err error) {
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
		err = o.setDNSTypeGet(params[thirdParam-1])
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



func getDNSAnswersGet(params []string) ([]dns.RR, error) {
	fmt.Printf("OMEGA PARAMTS: %s"+strings.Join(params, ", "))
	options, err := parseParamasGet(params)
	if err != nil {
		return nil, err
	}

	var resp *dns.Msg
	for i := 1; i <= options.count; i++ {
		resp, err = runQueryGet(options.ip, options.name, options.protocol, options.dnsType, options.timeout)
		if err != nil {
			continue
		}

		break
	}

	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	log.Infof("AGS HEADER: %s", resp.MsgHdr)
	log.Infof("AGS Question: %s", resp.Question)
	resp_x, _ := json.Marshal(resp.Answer)
	log.Infof("AGS Answer: %s", resp_x)
	log.Infof("AGS Ns: %s", resp.Ns)
	log.Infof("AGS Extra: %s", resp.Extra)

	log.Infof("AGS RCODE: %d", resp.Rcode)
	
	return resp.Answer, nil
}


func (o *options) setDNSTypeGet(dnsType string) error {
	if dnsType == "" {
		return nil
	}

	t, ok := dnsTypesGet[strings.ToUpper(dnsType)]
	if !ok {
		return zbxerr.New(fmt.Sprintf("invalid third parameter, unknown dns type %s", dnsType))
	}

	o.dnsType = t

	return nil
}

func runQueryGet(resolver, domain, net string, record uint16, timeout time.Duration) (*dns.Msg, error) {
	c := new(dns.Client)
	c.Net = net
	c.DialTimeout = timeout
	c.ReadTimeout = timeout
	c.WriteTimeout = timeout

	if *four {
		c.Net = "udp4"
	}

	if *six {
		c.Net = "udp6"
	}
	
	m := &dns.Msg{
		MsgHdr: dns.MsgHdr{
			Authoritative:     *aa,
			AuthenticatedData: *ad,
			CheckingDisabled:  *cd,
			RecursionDesired:  *rd,
			Opcode:           dns.OpcodeQuery,
			Rcode:            dns.RcodeSuccess,
		},
		Question: make([]dns.Question, 1),
	}

	m.Question[0] = dns.Question{Name: dns.Fqdn(domain), Qtype: record, Qclass: dns.ClassINET}


	///
	if *dnssec || *nsid /*|| *client != ""*/ {
		o := &dns.OPT{
			Hdr: dns.RR_Header{
				Name:   ".",
				Rrtype: dns.TypeOPT,
			},
		}
		if *dnssec {
			o.SetDo()
			o.SetUDPSize(dns.DefaultMsgSize)
		}
		if *nsid {
			e := &dns.EDNS0_NSID{
				Code: dns.EDNS0NSID,
			}
			o.Option = append(o.Option, e)
			// NSD will not return nsid when the udp message size is too small
			o.SetUDPSize(dns.DefaultMsgSize)
		}

		m.Extra = append(m.Extra, o)
	}

	if *short {
		shortenMsg(m)
	}

	///


	r, _, err := c.Exchange(m, resolver)

	return r, err
}

// shortenMsg walks trough message and shortens Key data and Sig data.
func shortenMsg(in *dns.Msg) {
	for i, answer := range in.Answer {
		in.Answer[i] = shortRR(answer)
	}
	for i, ns := range in.Ns {
		in.Ns[i] = shortRR(ns)
	}
	for i, extra := range in.Extra {
		in.Extra[i] = shortRR(extra)
	}
}

func shortRR(r dns.RR) dns.RR {
	switch t := r.(type) {
	case *dns.DS:
		t.Digest = "..."
	case *dns.DNSKEY:
		t.PublicKey = "..."
	case *dns.RRSIG:
		t.Signature = "..."
	case *dns.NSEC3:
		t.Salt = "." // Nobody cares
		if len(t.TypeBitMap) > 5 {
			t.TypeBitMap = t.TypeBitMap[1:5]
		}
	}
	return r
}

