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

package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io/ioutil"
	"net"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"

	"github.com/chromedp/cdproto/emulation"
	"github.com/chromedp/cdproto/network"
	"github.com/chromedp/cdproto/page"
	"github.com/chromedp/chromedp"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	netErrCertCommonNameInvalid = "net::ERR_CERT_COMMON_NAME_INVALID"
	netErrCertAuthorityInvalid  = "net::ERR_CERT_AUTHORITY_INVALID"
	netErrCertDateInvalid       = "net::ERR_CERT_DATE_INVALID"
	netErrCertWeakSignatureAlg  = "net::ERR_CERT_WEAK_SIGNATURE_ALGORITHM"
)

type requestBody struct {
	URL        string            `json:"url"`
	Header     map[string]string `json:"headers"`
	Parameters map[string]string `json:"parameters"`
}

// Report size in pixels.
type reportSize struct {
	width  int64
	height int64
}

// PDF report generation request parameters.
type reportReqParams struct {
	cookieParams []*network.CookieParam
	size         reportSize
	url          string
}

// Report generation request parameters.
type chromedpResp struct {
	data []byte
	err  error
}

func newRequestBody() *requestBody {
	return &requestBody{"", make(map[string]string), make(map[string]string)}
}

// httpCookiesGet parses Cookie HTTP request header returns HTTP cookies
func (b *requestBody) httpCookiesGet() []*http.Cookie {
	r := http.Request{Header: http.Header{}}
	r.Header.Add("Cookie", b.Header["Cookie"])

	return r.Cookies()
}

func logAndWriteError(w http.ResponseWriter, errMsg string, code int) {
	log.Errf("%s", errMsg)
	w.Header().Set("Content-Type", "application/problem+json")
	w.Header().Set("X-Content-Type-Options", "nosniff")
	w.WriteHeader(code)
	encoder := json.NewEncoder(w)
	encoder.SetEscapeHTML(false)

	err := encoder.Encode(map[string]string{"detail": errMsg})
	if err != nil {
		log.Errf("Error '%s' happened while encoding error message: '%s'", err.Error(), errMsg)
	}
}

func (h *handler) report(w http.ResponseWriter, r *http.Request) {
	log.Infof("received report request from %s", r.RemoteAddr)

	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		logAndWriteError(w, fmt.Sprintf("Cannot remove port from host for incoming ip %s.", err.Error()), http.StatusInternalServerError)

		return
	}

	if !h.allowedPeers.CheckPeer(net.ParseIP(host)) {
		logAndWriteError(w, fmt.Sprintf("Cannot accept incoming connection for peer: %s.", r.RemoteAddr), http.StatusInternalServerError)

		return
	}

	if r.Method != http.MethodPost {
		logAndWriteError(w, "Method is not supported.", http.StatusMethodNotAllowed)

		return
	}

	if r.Header.Get("Content-Type") != "application/json" {
		logAndWriteError(w, "Content Type is not application/json.", http.StatusMethodNotAllowed)

		return
	}

	b, err := ioutil.ReadAll(r.Body)
	if err != nil {
		logAndWriteError(w, "Can not read body data.", http.StatusInternalServerError)

		return
	}

	req := newRequestBody()
	if err = json.Unmarshal(b, &req); err != nil {
		logAndWriteError(w, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err).Error(), http.StatusInternalServerError)

		return
	}

	opts := chromedp.DefaultExecAllocatorOptions[:]

	if options.IgnoreURLCertErrors == 1 {
		opts = append(opts, chromedp.Flag("ignore-certificate-errors", "1"))
	}

	allocCtx, cancel := chromedp.NewExecAllocator(r.Context(), opts...)
	defer cancel()

	ctx, cancel := chromedp.NewContext(allocCtx)
	defer cancel()

	width, err := strconv.ParseInt(req.Parameters["width"], 10, 64)
	if err != nil {
		logAndWriteError(w, fmt.Sprintf("Incorrect parameter width: %s", err.Error()), http.StatusBadRequest)

		return
	}

	height, err := strconv.ParseInt(req.Parameters["height"], 10, 64)
	if err != nil {
		logAndWriteError(w, fmt.Sprintf("Incorrect parameter height: %s", err.Error()), http.StatusBadRequest)

		return
	}

	u, err := parseUrl(req.URL)
	if err != nil {
		logAndWriteError(w, fmt.Sprintf("Incorrect request url: %s", err.Error()), http.StatusBadRequest)

		return
	}

	if u.Scheme != "http" && u.Scheme != "https" {
		logAndWriteError(w, fmt.Sprintf("Unexpected URL scheme: \"%s\"", u.Scheme), http.StatusBadRequest)

		return
	}

	if !strings.HasSuffix(u.Path, "/zabbix.php") {
		logAndWriteError(w, fmt.Sprintf("Unexpected URL path: \"%s\"", u.Path), http.StatusBadRequest)

		return
	}

	queryParams := u.Query()

	if queryParams.Get("action") != "dashboard.print" {
		logAndWriteError(w, fmt.Sprintf("Unexpected URL action: \"%s\"", queryParams.Get("action")), http.StatusBadRequest)

		return
	}

	log.Tracef(
		"making chrome headless request with parameters url: %s, width: %s, height: %s for report request from %s",
		u.String(), req.Parameters["width"], req.Parameters["height"], r.RemoteAddr)

	var cookieParams []*network.CookieParam

	for _, cookie := range req.httpCookiesGet() {
		cookieParam := network.CookieParam{
			Name:     cookie.Name,
			Value:    cookie.Value,
			URL:      req.URL,
			Domain:   u.Hostname(),
			SameSite: network.CookieSameSiteStrict,
			HTTPOnly: true,
		}

		cookieParams = append(cookieParams, &cookieParam)
	}

	cdpReqParams := reportReqParams{
		cookieParams: cookieParams,
		size: reportSize{
			height: height,
			width:  width,
		},
		url: u.String(),
	}

	respChan := make(chan chromedpResp)
	defer close(respChan)

	go runCDP(ctx, cancel, cdpReqParams, respChan)

	// should never deadlock as chromedp.Run has it's own timeout and we should always get a response
	resp := <-respChan

	if resp.err != nil {
		logAndWriteError(
			w,
			errs.WrapConst(resp.err, zbxerr.ErrorCannotFetchData).Error(),
			http.StatusInternalServerError,
		)

		return
	}

	log.Infof("writing response to report request from %s", r.RemoteAddr)

	w.Header().Set("Content-type", "application/pdf")

	_, err = w.Write(resp.data)
	if err != nil {
		log.Errf("failed to write response to report request from %s: %s", r.RemoteAddr, err.Error())
	}
}

func runCDP(
	ctx context.Context,
	cancel context.CancelFunc,
	req reportReqParams,
	resp chan<- chromedpResp,
) {
	var (
		out         []byte
		listenerErr error
	)

	requests := make(map[network.RequestID]string)

	chromedp.ListenTarget(
		ctx,
		func(ev any) {
			switch evt := ev.(type) {
			case *network.EventRequestWillBeSent:
				requests[evt.RequestID] = evt.Request.URL
			case *network.EventLoadingFailed:
				reqURL := "unknown"

				evtReqSentURL, ok := requests[evt.RequestID]
				if ok {
					if evtReqSentURL == req.url {
						listenerErr = handleCriticalNetErr(evt.ErrorText)

						cancel()

						return
					}

					reqURL = evtReqSentURL
				}

				log.Warningf("network.EventLoadingFailed with error %q was received "+
					"while loading external widget content, "+
					"continuing to generate a report, URL: %s",
					evt.ErrorText, reqURL)
			}
		},
	)

	err := chromedp.Run(ctx, chromedp.Tasks{
		network.SetCookies(req.cookieParams),
		emulation.SetDeviceMetricsOverride(req.size.width, req.size.height, 1, false),
		prepareDashboard(req.url),
		chromedp.ActionFunc(func(ctx context.Context) error {
			timeoutContext, cancel := context.WithTimeout(ctx, time.Duration(options.Timeout)*time.Second)
			defer cancel()
			var err error
			out, _, err = page.PrintToPDF().
				WithPrintBackground(true).
				WithPreferCSSPageSize(true).
				WithPaperWidth(pixels2inches(req.size.width)).
				WithPaperHeight(pixels2inches(req.size.height)).
				Do(timeoutContext)
			if err != nil {
				if errors.Is(err, context.DeadlineExceeded) {
					return errs.Wrapf(
						err,
						"timeout (%ds) reached while formatting dashboard as PDF, "+
							"it is configurable in Zabbix web service config file "+
							"('Timeout' option)",
						options.Timeout,
					)
				}

				return errs.Wrap(err, "failed to format dashboard as PDF")
			}

			return nil
		}),
	})

	if listenerErr != nil {
		// error is logged since in case of listenerErr chromedp error might be nil or some other error,
		// and it is good for debugging.
		log.Tracef("chromedp.Run exited, with err: %v", err)
		resp <- chromedpResp{err: listenerErr}

		return
	}

	if err != nil {
		resp <- chromedpResp{err: err}

		return
	}

	resp <- chromedpResp{data: out}
}

func pixels2inches(value int64) float64 {
	return float64(value) * 0.0104166667
}

func prepareDashboard(url string) chromedp.ActionFunc {
	return func(ctx context.Context) error {
		_, _, _, err := page.Navigate(url).Do(ctx)
		if err != nil {
			return errs.Wrapf(err, "failed to navigate to dashboard, url: %q", url)
		}

		return waitForDashboardReady(ctx, url)
	}
}

func waitForDashboardReady(ctx context.Context, url string) error {
	var isReady bool

	err := chromedp.Run(
		ctx,
		chromedp.Poll(
			"document.querySelector('.wrapper.is-ready') !== null",
			&isReady,
			chromedp.WithPollingTimeout(time.Second*45),
		),
	)
	if err != nil {
		return errs.Wrapf(err, "dashboard failed to get ready, url: '%s'", url)
	}

	if !isReady {
		/* Should never happen: */
		/* it is expected that either dashboard gets ready or chromedp.ErrPollingTimeout happens. */
		return errs.Errorf("dashboard failed to get ready with no error, url: '%s'", url)
	}

	return nil
}

func parseUrl(u string) (*url.URL, error) {
	if u == "" {
		return nil, errors.New("url is empty")
	}

	parsed, err := url.Parse(u)
	if err != nil {
		return nil, err
	}

	if parsed.Scheme == "" {
		return nil, errors.New("url is missing scheme")
	}

	return parsed, nil
}

// handleCriticalNetErr returns a user friendly error message for network.EventLoadingFailed errors.
func handleCriticalNetErr(errStr string) error {
	switch errStr {
	case netErrCertCommonNameInvalid:
		return errs.New(
			"Invalid certificate common name detected while loading dashboard. Fix TLS configuration or " +
				"configure Zabbix web service to ignore TLS certificate errors when accessing " +
				"frontend URL.",
		)
	case netErrCertDateInvalid:
		return errs.New(
			"Invalid certificate date detected while loading dashboard " +
				"(certificate's validity period has expired or is not yet valid). " +
				"configure Zabbix web service to ignore TLS certificate errors when accessing " +
				"frontend URL.",
		)
	case netErrCertWeakSignatureAlg:
		return errs.New(
			"Weak certificate signature algorithm detected while loading dashboard. " +
				"Fix TLS configuration or configure Zabbix web service to ignore " +
				"TLS certificate errors when accessing frontend URL.",
		)

	case netErrCertAuthorityInvalid:
		return errs.New(
			"Invalid certificate authority detected while loading dashboard. Fix TLS configuration or " +
				"configure Zabbix web service to ignore TLS certificate errors when accessing " +
				"frontend URL.",
		)
	case "":
		return errs.New(
			"network.EventLoadingFailed event with empty ErrorText was received while loading dashboard.",
		)
	default:
		return errs.Errorf(
			"network.EventLoadingFailed event with ErrorText = '%s' was received while loading dashboard.",
			errStr,
		)
	}
}
