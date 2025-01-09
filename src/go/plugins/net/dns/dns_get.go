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

package dns

import (
	"bytes"
	"encoding/json"
	"fmt"
	"regexp"
	"strings"
	"time"

	"github.com/miekg/dns"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	noErrorResponseCodeFinalJsonResult    = 0
	communicationErrorCodeFinalJsonResult = -1
	jsonParsingErrorCodeFinalJsonResult   = -2
)

var dnsTypesGet = map[string]uint16{
	"None":       dns.TypeNone,
	"A":          dns.TypeA,
	"NS":         dns.TypeNS,
	"MD":         dns.TypeMD,
	"MF":         dns.TypeMF,
	"CNAME":      dns.TypeCNAME,
	"SOA":        dns.TypeSOA,
	"MB":         dns.TypeMB,
	"MG":         dns.TypeMG,
	"MR":         dns.TypeMR,
	"NULL":       dns.TypeNULL,
	"PTR":        dns.TypePTR,
	"HINFO":      dns.TypeHINFO,
	"MINFO":      dns.TypeMINFO,
	"MX":         dns.TypeMX,
	"TXT":        dns.TypeTXT,
	"RP":         dns.TypeRP,
	"AFSDB":      dns.TypeAFSDB,
	"X25":        dns.TypeX25,
	"ISDN":       dns.TypeISDN,
	"RT":         dns.TypeRT,
	"NSAPPTR":    dns.TypeNSAPPTR,
	"SIG":        dns.TypeSIG,
	"KEY":        dns.TypeKEY,
	"PX":         dns.TypePX,
	"GPOS":       dns.TypeGPOS,
	"AAAA":       dns.TypeAAAA,
	"LOC":        dns.TypeLOC,
	"NXT":        dns.TypeNXT,
	"EID":        dns.TypeEID,
	"NIMLOC":     dns.TypeNIMLOC,
	"SRV":        dns.TypeSRV,
	"ATMA":       dns.TypeATMA,
	"NAPTR":      dns.TypeNAPTR,
	"KX":         dns.TypeKX,
	"CERT":       dns.TypeCERT,
	"DNAME":      dns.TypeDNAME,
	"OPT":        dns.TypeOPT,
	"APL":        dns.TypeAPL,
	"DS":         dns.TypeDS,
	"SSHFP":      dns.TypeSSHFP,
	"IPSECKEY":   dns.TypeIPSECKEY,
	"RRSIG":      dns.TypeRRSIG,
	"NSEC":       dns.TypeNSEC,
	"DNSKEY":     dns.TypeDNSKEY,
	"DHCID":      dns.TypeDHCID,
	"NSEC3":      dns.TypeNSEC3,
	"NSEC3PARAM": dns.TypeNSEC3PARAM,
	"TLSA":       dns.TypeTLSA,
	"SMIMEA":     dns.TypeSMIMEA,
	"HIP":        dns.TypeHIP,
	"NINFO":      dns.TypeNINFO,
	"RKEY":       dns.TypeRKEY,
	"TALINK":     dns.TypeTALINK,
	"CDS":        dns.TypeCDS,
	"CDNSKEY":    dns.TypeCDNSKEY,
	"OPENPGPKEY": dns.TypeOPENPGPKEY,
	"CSYNC":      dns.TypeCSYNC,
	"ZONEMD":     dns.TypeZONEMD,
	"SVCB":       dns.TypeSVCB,
	"HTTPS":      dns.TypeHTTPS,
	"SPF":        dns.TypeSPF,
	"UINFO":      dns.TypeUINFO,
	"UID":        dns.TypeUID,
	"GID":        dns.TypeGID,
	"UNSPEC":     dns.TypeUNSPEC,
	"NID":        dns.TypeNID,
	"L32":        dns.TypeL32,
	"L64":        dns.TypeL64,
	"LP":         dns.TypeLP,
	"EUI48":      dns.TypeEUI48,
	"EUI64":      dns.TypeEUI64,
	"URI":        dns.TypeURI,
	"CAA":        dns.TypeCAA,
	"AVC":        dns.TypeAVC,
	"AMTRELAY":   dns.TypeAMTRELAY,

	"TKEY": dns.TypeTKEY,
	"TSIG": dns.TypeTSIG,

	"IXFR":  dns.TypeIXFR,
	"AXFR":  dns.TypeAXFR,
	"MAILB": dns.TypeMAILB,
	"MAILA": dns.TypeMAILA,
	"ANY":   dns.TypeANY,

	"TA":       dns.TypeTA,
	"DLV":      dns.TypeDLV,
	"Reserved": dns.TypeReserved,
}

var dnsTypesGetReverse = reverseMap(dnsTypesGet)

var dnsRespCodesMappingGet = map[int]string{
	0:  "NOERROR",
	1:  "FORMERR",
	2:  "SERVFAIL",
	3:  "NXDOMAIN",
	4:  "NOTIMP",
	5:  "REFUSED",
	6:  "YXDOMAIN",
	7:  "YXRRSET",
	8:  "NXRRSET",
	9:  "NOTAUTH",
	10: "NOTZONE",
	16: "BADSIG/BADVERS",
	17: "BADKEY",
	18: "BADTIME",
	19: "BADMODE",
	20: "BADNAME",
	21: "BADALG",
	22: "BADTRUNC",
	23: "BADCOOKIE",
}

var dnsClassesGet = map[uint16]string{
	1:   "IN",
	3:   "CH",
	4:   "HS",
	254: "NONE",
	255: "ANY",
}

// QTYPES are a superset of TYPEs https://datatracker.ietf.org/doc/html/rfc1035
var dnsExtraQuestionTypesGet = map[uint16]string{
	251: "IXFR",
	252: "AXFR",
	253: "MAILB",
	254: "MAILA",
	255: "ANY",
}

type dnsGetOptions struct {
	options
	flags map[string]bool
}

func exportDnsGet(params []string) (result any, err error) {
	options, err := parseParamsGet(params)
	if err != nil {
		return "", err
	}

	timeBeforeQuery := time.Now()

	var resp *dns.Msg
	for i := 1; i <= options.count; i++ {
		resp, err = runQueryGet(options)
		if err != nil {
			continue
		}

		break
	}

	if err != nil {
		resultJson, err := json.Marshal(
			map[string]any{
				"zbx_error_code": communicationErrorCodeFinalJsonResult,
				"zbx_error_msg":  "Communication error: " + err.Error(),
			},
		)
		if err != nil {
			// There is communication error, however we failed to parse it
			// return the original communication error as regular error.
			return nil, err
		}

		return string(resultJson), nil
	}

	timeDNSResponseReceived := time.Since(timeBeforeQuery).Seconds()
	queryTimeSection := map[string]any{
		"query_time": fmt.Sprintf("%.2f", timeDNSResponseReceived),
	}

	// Now, resp from the DNS library is ready to be processed:
	//    type Msg struct {
	//    MsgHdr
	//    Compress bool       `json:"-"`
	//    Question []Question // Holds the RR(s) of the question section.
	//    Answer   []RR       // Holds the RR(s) of the answer section.
	//    Ns       []RR       // Holds the RR(s) of the authority section.
	//    Extra    []RR       // Holds the RR(s) of the additional section.
	//    }
	//
	// This gets parsed, with some new data attached. At the end, large JSON response,
	// consisting of several sections is returned:
	// 1) Meta-data: zbx_error_code and query_time - internally generated,
	//               not coming from the DNS library
	// 2) MsgHdr data: response_code and flags
	// 3) Question, Answer section, Ns and Extra sections data, mostly untouched,
	//    but formatted to make it more consistent with other Zabbix JSON returning items.

	parsedFlagsSection := parseRespFlags(resp.MsgHdr)
	parsedResponseCode := parseRespCode(resp.MsgHdr)
	parsedQuestionSection := parseRespQuestion(resp.Question)

	parsedAnswerSection, err := parseRRs(resp.Answer, "answer_section")
	if err != nil {
		failedResultMessage, err := prepareJsonErrorResponse(err)
		if err != nil {
			return "", err
		}

		return failedResultMessage, nil
	}

	parsedAuthoritySection, err := parseRRs(resp.Ns, "authority_section")
	if err != nil {
		failedResultMessage, err := prepareJsonErrorResponse(err)
		if err != nil {
			return "", err
		}

		return failedResultMessage, nil
	}

	parsedAdditionalSection, err := parseRRs(resp.Extra, "additional_section")
	if err != nil {
		failedResultMessage, err := prepareJsonErrorResponse(err)
		if err != nil {
			return "", err
		}

		return failedResultMessage, nil
	}

	almostCompleteResultBlock := prepareAlmostCompleteResultBlock(
		parsedAnswerSection,
		parsedAuthoritySection,
		parsedAdditionalSection,
		parsedFlagsSection,
		parsedResponseCode,
		queryTimeSection,
		parsedQuestionSection,
	)

	finalResultJson, errFinalResultParse := json.Marshal(almostCompleteResultBlock)
	if errFinalResultParse != nil {
		finalResultJson, err := prepareJsonErrorResponse(errFinalResultParse)
		if err != nil {
			return "", err
		}

		return finalResultJson, nil
	}

	return string(finalResultJson), nil
}

func (o *dnsGetOptions) setFlags(flags string) error {
	o.flags = map[string]bool{
		"cdflag": false,
		"rdflag": true,
		"dnssec": false,
		"nsid":   false,
		"edns0":  true,
		"aaflag": false,
		"adflag": false,
	}

	found := map[string]string{}

	flagsSplit := strings.Split(flags, ",")
	if len(flagsSplit) > len(o.flags) {
		return zbxerr.New(fmt.Sprintf("Too many flags supplied: %d", len(flagsSplit)))
	}

	if len(flagsSplit) == 1 && flagsSplit[0] == "" {
		return nil
	}

	flags = "," + flags

	for key, _ := range o.flags {
		if strings.Contains(flags, ",no"+key) && strings.Contains(flags, ","+key) {
			return zbxerr.New("Invalid flags combination, cannot use no" +
				key + " and " + key + " together")
		}
	}

	for _, flag := range flagsSplit {
		key := strings.TrimPrefix(flag, "no")
		value := !strings.HasPrefix(flag, "no")

		_, ok := o.flags[key]
		if !ok {
			return zbxerr.New("Invalid flag supplied:" + flag)
		}

		_, ok = found[key]
		if ok {
			return zbxerr.New("Duplicate flag supplied: " + flag)
		}

		found[key] = flag
		o.flags[key] = value
	}

	if o.flags["nsid"] && !o.flags["edns0"] {
		return zbxerr.New("Invalid flags combination, cannot use noedns0 and nsid together")
	}

	return nil
}

func parseParamsGet(params []string) (*dnsGetOptions, error) {
	var o dnsGetOptions

	switch len(params) {
	case seventhParam:
		err := o.setFlags(params[seventhParam-1])
		if err != nil {
			return nil, err
		}

		fallthrough
	case sixthParam:
		err := o.setProtocol(params[sixthParam-1])
		if err != nil {
			return nil, err
		}

		fallthrough
	case fifthParam:
		err := o.setCount(params[fifthParam-1])
		if err != nil {
			return nil, err
		}

		fallthrough
	case fourthParam:
		err := o.setTimeout(params[fourthParam-1])
		if err != nil {
			return nil, err
		}

		fallthrough
	case thirdParam:
		err := o.setDNSTypeGet(params[thirdParam-1])
		if err != nil {
			return nil, err
		}

		fallthrough
	case secondParam:
		o.name = params[secondParam-1]

		fallthrough
	case firstParam:
		err := o.setIP(params[firstParam-1])
		if err != nil {
			return &o, zbxerr.New(fmt.Sprintf("invalid first parameter, %s", err.Error()))
		}

		fallthrough
	case noneParam:
		err := o.setDefaults()
		if err != nil {
			return nil, err
		}
	default:
		err := zbxerr.ErrorTooManyParameters

		return nil, err
	}

	return &o, nil
}

func reverseMap(m map[string]uint16) map[uint16]string {
	n := make(map[uint16]string, len(m))
	for k, v := range m {
		n[v] = k
	}

	return n
}

func insertAtEveryNthPosition(s string, n int, r rune) string {
	var buffer bytes.Buffer

	if n <= 0 {
		return s
	}

	var n1 = n - 1
	var l1 = len(s) - 1
	for i, rune := range s {
		buffer.WriteRune(rune)
		if i%n == n1 && i != l1 {
			buffer.WriteRune(r)
		}
	}

	return buffer.String()
}

func parseRRs(rrs []dns.RR, source string) (map[string][]any, error) {
	parsedSection := make(map[string][]any)
	parsedRRs := make([]any, 0, len(rrs))

	for _, rr := range rrs {
		switch rr := rr.(type) {
		case *dns.OPT:
			parsedRR, err := parseOptRR(rr)
			if err != nil {
				return nil, err
			}

			parsedRRs = append(parsedRRs, parsedRR)
		default:
			parsedRR, err := parseDefaultRR(rr)
			if err != nil {
				return nil, err
			}

			parsedRRs = append(parsedRRs, parsedRR)
		}
	}

	if len(parsedRRs) != 0 {
		parsedSection[source] = parsedRRs
	}

	return parsedSection, nil
}

func parseHeaderRRtype(header *dns.RR_Header) string {
	rrTypeNewValue := fmt.Sprintf("%d", header.Rrtype)
	mappedFieldValue, ok := dnsTypesGetReverse[header.Rrtype]
	if ok {
		rrTypeNewValue = mappedFieldValue
	}

	return rrTypeNewValue
}

func parseHeaderClass(header *dns.RR_Header) string {
	headerClassNewValue := fmt.Sprintf("%d", header.Class)
	mappedClass, ok := dnsClassesGet[header.Class]

	if ok {
		headerClassNewValue = mappedClass
	}

	return headerClassNewValue
}

var matchFirstCap = regexp.MustCompile("(.)([A-Z][a-z]+)")
var matchAllCap = regexp.MustCompile("([a-z0-9])([A-Z])")

func toSnake(in string) string {
	snakeCase := matchFirstCap.ReplaceAllString(in, "${1}_${2}")
	snakeCase = matchAllCap.ReplaceAllString(snakeCase, "${1}_${2}")

	return strings.ToLower(snakeCase)
}

func parseOptRR(optRR *dns.OPT) (map[string]any, error) {
	parsedOpts := make([]any, 0, len(optRR.Option))

	for _, o := range optRR.Option {
		oMap, err := structToMap(o)
		if err != nil {
			return nil, err
		}

		for k, v := range oMap {
			delete(oMap, k)
			oMap[toSnake(k)] = v
		}

		nsid, ok := oMap["nsid"]
		if ok {
			const numOfDigitsTogetherInNSID = 2
			nsidValue := insertAtEveryNthPosition(nsid.(string), numOfDigitsTogetherInNSID, ' ')

			oMap["nsid"] = nsidValue
		}

		parsedOpts = append(parsedOpts, oMap)
	}

	header := optRR.Header()

	rData2 := map[string]any{}
	rData2["options"] = parsedOpts

	return map[string]any{
		"udp_payload":    header.Class,
		"name":           header.Name,
		"rdata":          rData2,
		"rdlength":       header.Rdlength,
		"extended_rcode": header.Ttl,
		"type":           parseHeaderRRtype(header),
	}, nil
}

func parseDefaultRR(rr dns.RR) (map[string]any, error) {
	rData, err := structToMap(rr)
	if err != nil {
		return nil, err
	}

	delete(rData, "Hdr")

	header := rr.Header()

	rData2 := map[string]any{}
	for k, v := range rData {
		newKey := toSnake(k)
		if newKey == "type_covered" {
			// unmarshalling produces float64 instead of the original uint16
			// need to cast the value to float64 first and then to uint16
			if newValue, ok := v.(float64); ok {
				newValueS, ok := dnsTypesGetReverse[uint16(newValue)]
				if ok {
					v = newValueS
				}
			}
		}
		rData2[newKey] = v
	}

	return map[string]any{
		"class":    parseHeaderClass(header),
		"name":     header.Name,
		"rdata":    rData2,
		"rdlength": header.Rdlength,
		"ttl":      header.Ttl,
		"type":     parseHeaderRRtype(header),
	}, nil
}

func structToMap(s any) (map[string]any, error) {
	b, err := json.Marshal(s)
	if err != nil {
		return nil, err
	}

	rData := map[string]any{}

	err = json.Unmarshal(b, &rData)
	if err != nil {
		return nil, err
	}

	return rData, nil
}

func parseRespQuestion(respQuestion []dns.Question) map[string][]any {
	var (
		// RFC allows to have multiple questions, however DNS library describes
		// it almost never happens, so it says it will fail if there is more than 1,
		// so safe to assume there will be exactly 1 question.
		q          = respQuestion[0]
		resultPart = map[string]any{"qname": q.Name}
		ok         bool
	)

	resultPart["qtype"], ok = dnsTypesGetReverse[q.Qtype]
	if !ok {
		resultPart["qtype"], ok = dnsExtraQuestionTypesGet[q.Qtype]
		if !ok {
			resultPart["qtype"] = q.Qtype
		}
	}

	resultPart["qclass"], ok = dnsClassesGet[q.Qclass]
	if !ok {
		resultPart["qclass"] = q.Qclass
	}

	return map[string][]any{
		"question_section": {resultPart},
	}
}

func parseRespFlags(rh dns.MsgHdr) map[string][]string {
	var answerFlags []string

	if rh.Authoritative {
		answerFlags = append(answerFlags, "AA")
	}

	if rh.Truncated {
		answerFlags = append(answerFlags, "TC")
	}

	if rh.RecursionDesired {
		answerFlags = append(answerFlags, "RD")
	}

	if rh.RecursionAvailable {
		answerFlags = append(answerFlags, "RA")
	}

	if rh.AuthenticatedData {
		answerFlags = append(answerFlags, "AD")
	}

	if rh.CheckingDisabled {
		answerFlags = append(answerFlags, "CD")
	}

	return map[string][]string{"flags": answerFlags}
}

func parseRespCode(rh dns.MsgHdr) map[string]any {
	rCodeMapped, ok := dnsRespCodesMappingGet[rh.Rcode]
	if !ok {
		return map[string]any{"response_code": rh.Rcode}
	}

	return map[string]any{"response_code": rCodeMapped}
}

func prepareJsonErrorResponse(e error) (string, error) {
	resultFailedParsing := map[string]any{
		"zbx_error_code": jsonParsingErrorCodeFinalJsonResult,
		"zbx_error_msg":  e.Error(),
	}

	resultJsonFailedParsing, errWhileProcessingFailedParsing := json.Marshal(resultFailedParsing)

	if errWhileProcessingFailedParsing != nil {
		return "", errWhileProcessingFailedParsing
	}

	return string(resultJsonFailedParsing), nil
}

func prepareAlmostCompleteResultBlock(
	parsedAnswerSection map[string][]any,
	parsedAuthoritySection map[string][]any,
	parsedAdditionalSection map[string][]any,
	parsedFlagsSection map[string][]string,
	parsedResponseCode map[string]any,
	queryTimeSection map[string]any,
	parsedQuestionSection map[string][]any,
) map[string]any {
	// Almost complete since it is not marshaled yet and without
	// zbx_error_code (and possibly zbx_error_msg).
	almostCompleteResultBlock := map[string]any{}

	for k, v := range parsedFlagsSection {
		almostCompleteResultBlock[k] = v
	}

	for k, v := range parsedResponseCode {
		almostCompleteResultBlock[k] = v
	}

	for k, v := range queryTimeSection {
		almostCompleteResultBlock[k] = v
	}

	for k, v := range parsedQuestionSection {
		almostCompleteResultBlock[k] = v
	}

	for k, v := range parsedAnswerSection {
		almostCompleteResultBlock[k] = v
	}

	for k, v := range parsedAuthoritySection {
		almostCompleteResultBlock[k] = v
	}

	for k, v := range parsedAdditionalSection {
		almostCompleteResultBlock[k] = v
	}

	almostCompleteResultBlock["zbx_error_code"] = noErrorResponseCodeFinalJsonResult

	return almostCompleteResultBlock
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

	c := &dns.Client{
		Net:          net,
		DialTimeout:  timeout,
		ReadTimeout:  timeout,
		WriteTimeout: timeout,
	}

	var err error
	if record == dns.TypePTR {
		domain, err = dns.ReverseAddr(domain)
		if err != nil {
			return nil, err
		}
	}

	m := &dns.Msg{
		MsgHdr: dns.MsgHdr{
			Authoritative:     flags["aaflag"],
			AuthenticatedData: flags["adflag"],
			CheckingDisabled:  flags["cdflag"],
			RecursionDesired:  flags["rdflag"],
			Opcode:            dns.OpcodeQuery,
			Rcode:             dns.RcodeSuccess,
		},
		Question: []dns.Question{
			{Name: dns.Fqdn(domain), Qtype: record, Qclass: dns.ClassINET},
		},
	}

	if flags["dnssec"] || flags["nsid"] {
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
			o.Option = append(o.Option,
				&dns.EDNS0_NSID{Code: dns.EDNS0NSID},
			)
			// NSD will not return nsid when the udp message size is too small
			o.SetUDPSize(dns.DefaultMsgSize)
		}

		m.Extra = append(m.Extra, o)
	}

	r, _, err := c.Exchange(m, resolver)
	if err != nil {
		return nil, err
	}

	return r, nil
}
