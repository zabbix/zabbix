package dns

import (
	"reflect"
	"encoding/json"
	"flag"
	"fmt"
	"strings"
	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/zbxerr"
	"github.com/miekg/dns"
)

type dnsGetOptions struct {
	options
	//flags []f
	flags map[string]bool
}

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
	six          = flag.Bool("6", false, "use IPv6 only")
	four         = flag.Bool("4", false, "use IPv4 only")
)

func parseAnswersGet(answers []dns.RR) string {
	var out string
	answersNum := len(answers)

	log.Infof("AGS 111\n")
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

func (o *dnsGetOptions) setFlags(flags string) error {

	flags = "," + flags

	o.flags = map[string]bool {
		"cdflag": false,
			"rdflag": true,
			"dnssec": false,
			"nsid": false,
			"edns0": true,
			"aaflag": false,
			"adflag": false,
		}

	for key, val := range o.flags {

		noXflag := strings.Contains(flags, ",no" + key)
		Xflag := strings.Contains(flags, "," + key)

		if (noXflag && Xflag) {
			return zbxerr.New("Invalid flags combination, cannot use no" + key + " and " + key +
					" together")
		}

		if (!val) {
			o.flags[key] = Xflag
		} else {
			o.flags[key] = noXflag
		}
	}

	return nil
}

func parseParamasGet(params []string) (o dnsGetOptions, err error) {
	switch len(params) {
	case seventhParam:
		err = o.setFlags(params[seventhParam-1])
		if err != nil {
			return
		}

		fallthrough
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

func reverseMap(m map[string]uint16) map[interface{}]string {
	n := make(map[interface{}]string, len(m))
	for k, v := range m {
		n[v] = k
	}
	return n
}

var dnsTypesGetReverse = reverseMap(dnsTypesGet)

var dnsClassesGet = map[uint16]string{
	1:"IN",
	3:"CH",
	4:"HS",
	254:"NONE",
	255:"ANY",
}

func parseRespAnswer(respAnswer []dns.RR) {

	resultG := make(map[string][]interface{})

	for _, ii := range respAnswer {
		h := ii.Header() // RR_Header
		log.Infof("AGS H: ->%s<-\n", h)

		result := make(map[string]interface{})

		value := reflect.ValueOf(ii)
		typeOf := value.Type()
		// *dns.A

		log.Infof("RECORD TYPE: %s", typeOf)

		if value.Kind() == reflect.Ptr {
			value = value.Elem()
		}

		resFieldAggregatedValues := make(map[string]interface{})

		for j := 0; j < value.NumField(); j++ {

			fieldValue := value.Field(j)
			fieldType := value.Type().Field(j)
			fieldName := fieldType.Name

			log.Infof("FIELD_NAME Z: %s", fieldName)
			if fieldName == "Hdr" {

				valueH := fieldValue
				typeOfH := valueH.Type()
				// *dns.A
				log.Infof("RECORD TYPE: %s", typeOfH)
				if valueH.Kind() == reflect.Ptr {
					valueH = valueH.Elem()
				}

				for jH := 0; jH < valueH.NumField(); jH++ {
					fieldValueH := valueH.Field(jH).Interface()
					fieldTypeH := valueH.Type().Field(jH)
					fieldNameH := fieldTypeH.Name
					log.Infof("AGS fieldValueH: -%s<-", fieldValueH)
					log.Infof("AGS fieldTypeH: -%s<-", fieldTypeH)
					log.Infof("AGS fieldNameH: -%s<-", fieldNameH)
					n := strings.ToLower(fieldNameH)

					if (n == "rrtype") {
						n = "type"
						//log.Infof("SUBARU 1 in: %d", fieldValueH)
						fieldValueH = dnsTypesGetReverse[fieldValueH]
						//log.Infof("SUBARU 2 res: %s", fieldValueH)
					} else if (n == "class") {
						log.Infof("JEEP 1 in: %d", fieldValueH)

						zeta, _ := fieldValueH.(uint16)
						fieldValueH = dnsClassesGet[zeta]

					}
					result[n] = fieldValueH
				}
			} else {
				resFieldValue := fieldValue.Interface()
				//result[strings.ToLower(fieldName)] = resFieldValue
				n := strings.ToLower(fieldName)
				resFieldAggregatedValues[n] = resFieldValue
			}
		}

		result["rdata"] = resFieldAggregatedValues

		resultG["answer_section"] = append(resultG["answer_section"], result)
	}
	log.Infof("###################\n\n\n")

	aG, _ := json.Marshal(resultG)
	log.Infof("MARSHALL RES aG: ->%s<-", string(aG))
}

func getDNSAnswersGet(params []string) ([]dns.RR, error) {
	fmt.Printf("OMEGA PARAMTS: %s"+strings.Join(params, ", "))

	options, err := parseParamasGet(params)
	if err != nil {
		return nil, err
	}

	var resp *dns.Msg
	for i := 1; i <= options.count; i++ {
		resp, err = runQueryGet(&options)

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

	resp_answer, _ := json.Marshal(resp.Answer)
	log.Infof("AGS Answer: %s", resp_answer)

	log.Infof("#######################################\n\n\n\n")

	parseRespAnswer(resp.Answer)

	// log.Infof("AGS Ns: %s", resp.Ns)
	// log.Infof("AGS Extra: %s", resp.Extra)

	// log.Infof("AGS RCODE: %d", resp.Rcode)

	return resp.Answer, nil
}


func (o *dnsGetOptions) setDNSTypeGet(dnsType string) error {
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

func runQueryGet(o *dnsGetOptions) (*dns.Msg, error) {

	resolver := o.ip
	domain := o.name
	net := o.protocol
	record := o.dnsType
	timeout := o.timeout
	flags := o.flags

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
			Authoritative:      flags["aaflag"],
			AuthenticatedData: flags["adflag"],
			CheckingDisabled:  false,
			RecursionDesired:  flags["rdflag"],
			Opcode:           dns.OpcodeQuery,
			Rcode:            dns.RcodeSuccess,
		},
		Question: make([]dns.Question, 1),
	}

	m.Question[0] = dns.Question{Name: dns.Fqdn(domain), Qtype: record, Qclass: dns.ClassINET}


	///
	if flags["dnssec"] || flags["nsid"] /*|| *client != ""*/ {
		o := &dns.OPT{
			Hdr: dns.RR_Header{
				Name:   ".",
				Rrtype: dns.TypeOPT,
			},
		}
		if flags["dnssec"] {
			o.SetDo()
			o.SetUDPSize(dns.DefaultMsgSize)
		}
		if flags["nsid"] {
			e := &dns.EDNS0_NSID{
				Code: dns.EDNS0NSID,
			}
			o.Option = append(o.Option, e)
			// NSD will not return nsid when the udp message size is too small
			o.SetUDPSize(dns.DefaultMsgSize)
		}

		m.Extra = append(m.Extra, o)
	}


	r, _, err := c.Exchange(m, resolver)

	return r, err
}
