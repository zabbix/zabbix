<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';
require_once dirname(__FILE__).'/../../include/classes/helpers/CCepRuleHelper.php';

/**
 * Test suite to check if trigger CEP (Correlation Event Processing) works properly
 * when item state toggles between normal and unsupported.
 *
 * All item values are delivered to the server impersonating an active proxy (PROXY_NAME) that the host
 * (and, by inheritance, the discovered host) is assigned to, rather than as direct sender/trapper data, so
 * the whole suite exercises the proxy-delivery path. See dispatchSenderValues()/dispatchValues().
 *
 * @required-components server
 * @suite-components-reuse true
 * @onAfter clearData
 * @hosts test
 */
class testTriggerCEP extends CIntegrationTest {
	// Scale knobs for how much load each scenario generates. They are intentionally tiny here so the suite
	// runs quickly in CI, and small values are also handy while debugging to reach a failure fast; when
	// running locally to actually stress CEP, raise them to the recommended values noted below (or higher).
	// Increasing them makes the tests slower but far more thorough.
	const LLD_DISCOVERY_COUNT = 10;	// discovered items/triggers per rule; use at least 4000 to stress CEP
	const LOG_EVENT_COUNT = 10;		// log values pushed at the single-trigger stream; use at least 10000
	const RECOVERY_CYCLES_COUNT = 10;	// PROBLEM/recovery cycles in the rapid burst; use at least 1000
	const MAINTENANCE_COUNT = 40;		// number of maintenances to create; change to any number
	const MAINTENANCE_COUNT_EXTRA = 10;
	// How many ids the close window scenarios drive (see runEventAssessmentTestCepWindowCloseWindow()). Every
	// id gets a window of its own and the scenario treats them all alike, so this is a scale knob like the ones
	// above: the windows of a rule are spread over the CEP worker processes, so raising it is what puts more
	// than a handful of them to work at once. Use at least 100 to stress CEP; the lowest value the discarding
	// flavours still say anything with is 2 - one id whose values are dropped and one whose are kept.
	const CEP_CLOSE_WINDOW_SERVICE_COUNT = 3;
	// How many "down" values each of those ids is sent, and therefore how many events every window of the
	// scenario holds before the "up" value ends it. Raising this is the other half of the same scale knob: the
	// service count spreads the rule over more windows at once, this one fills each of those windows deeper,
	// and an ending window then has that many held problems to close instead of a single one. The eviction
	// flavours size their window capacity from it, so the "up" value is still the one event that does not fit,
	// see getCloseWindowCapacity(). Any value from 1 up says something; 1 leaves the scenario with one event
	// per window.
	const CEP_CLOSE_WINDOW_EVENT_COUNT = 2;
	// In which order those values go out, which only matters once the count above is more than one (see
	// getCloseWindowFillOrder()):
	//   - false, round by round ("down_0, down_1, down_0, down_1, ..."): every window of the rule is being
	//     filled while the others are, so the windows are all open and all growing at the same time;
	//   - true, id by id ("down_0, down_0, down_1, down_1, ..."): a window is filled to the end before the next
	//     one is opened, so the windows opened first sit untouched while the later ones are filled - which is
	//     what makes a window hold what it was given for the whole scenario rather than only for the last round
	//     of it.
	// Neither order changes what the scenario asserts: an id has as many open problems as it has been sent
	// values whichever way round they went out.
	const CEP_CLOSE_WINDOW_FILL_PER_SERVICE = true;
	// Whether those values go out in one sender batch instead of one at a time:
	//   - true, all of them in a single batch (in the order above), after which the totals are checked once -
	//     every id having all of its problems open and no more problems being open than the ids opened. The
	//     values are as distinct as they are when sent one by one, dispatchSenderValues() giving every value a
	//     strictly increasing (clock, ns) of its own, but the server receives them all at once and every window
	//     of the rule fills as fast as it can. This is what makes a large id count practical: nothing is polled
	//     for between values, so the scenario costs one wait instead of one per value;
	//   - false, one value at a time, with the counts waited for after each of them before the next goes out.
	//     Nothing more is asserted this way - the end state is the same - but a value that is not processed as
	//     it must be is caught as it happens rather than as a total that never comes out, so this is the one to
	//     use when a failure has to be pinned to a value.
	const CEP_CLOSE_WINDOW_BATCH_FILL = true;
	// Everything the suite does with a server that was stopped and started hangs on this one switch, and it costs
	// enough to be off by default - a restart is seconds of waiting for every scenario that takes one:
	//   - the *Restart tests of the trigger and correlation scenarios, which are their non-restart siblings driven
	//     against a freshly started server, are skipped entirely (see skipIfRestartTestsDisabled());
	//   - the CEP window scenarios stop and start the server in the middle of themselves instead, at the point where
	//     their windows are holding what the rest of the scenario acts on: everything after that point is driven
	//     against the windows the server loaded back from the database rather than the ones it had open all along, and
	//     not one assertion of those scenarios changes for it - a window that came back without the events it held,
	//     without the room it had left, or without knowing what it had ranked, fails the very steps that pass without
	//     a restart. See maybeRestartServerMidScenario() for where they take it and which ones do not.
	const SKIP_RESTART_TESTS = true;
	// How much room a scenario leaves for that restart inside the lifetime of the windows it lands among: the window
	// durations of the scenarios that take one are counted in seconds, and a window whose duration ran out while the
	// server was down would be gone for a reason the scenario is not about. Only added when the restarts are turned
	// on, so the durations are what they always were without them - see getRestartWindowAllowance().
	const CEP_RESTART_WINDOW_ALLOWANCE = 20;
	// The windowless CEP scenario suppresses its events for a while and can then wait for that suppression to
	// run out again (see waitForCepWindowNoneUnsuppressed()). That wait is the slowest part of the scenario by
	// far - the suppression period plus the once-a-minute timer pass that clears expired suppressions - so it
	// is skipped by default; set to false to check the suppression is lifted as well. Skipping it costs
	// nothing else: the events are still asserted to be suppressed while the suppression holds, and deleting
	// the CEP rules in the teardown takes their suppressions with them.
	const SKIP_UNSUPPRESS_WAIT = true;

	// The flavours whose operation condition compares a tag VALUE (CONDITION_TAG_VALUE) rather than a tag name are
	// skipped while the API cannot store one: CCepRule declares the operation condition field as 'value' but the
	// column behind it is cep_operation_condition.tag_value, and nothing maps the one to the other, so
	// DB::checkValueTypes() drops the unknown key and the condition is written with an empty tag_value. The server
	// then evaluates it as "tag = ''", which matches no event, and the operation never runs. Everything those
	// flavours assert is also asserted by their tag-name siblings, which is why skipping them leaves no window
	// behaviour uncovered - what they add is coverage of the condition type itself. Set to false once the API stores
	// the value, see skipIfOperationTagValueTestsDisabled().
	const SKIP_OPERATION_TAG_VALUE_TESTS = true;

	// Leave null to decide randomly based on the current time; set to true or false to force a path.
	const SKIP_SERVICES_TESTS = null;

	// Set to true to run the CEP window scenarios alone, so a debugging run starts at the windows instead of at
	// the hundred correlation and trigger scenarios that come before them: every test with "Cep" in its name is
	// one of them and no other test is, and the only test kept besides is testPrepareTriggerCEP_LLDDiscovery,
	// which every one of them @depends on (and nothing they need depends on anything else, so nothing is lost
	// as a dependency of a skipped test).
	const SKIP_NON_WINDOW_TESTS = false;

	const HOST_NAME = 'test';
	const TEMPLATE_NAME = 'template_trigger_cep';
	const LLD_RULE_KEY = 'lld.cep.trapper';
	const HOST_LLD_RULE_KEY = 'host.lld.trapper';
	const HOST_DISC_VALUE = 'discovered_host1';
	const LLD_MACRO = '{#COMPONENT}';
	// LLD macro carrying each discovered component's parity ('1' for odd index, '0' for even); used
	// by the parity-based global correlation scenario to tag every problem with an 'odd' tag.
	const PARITY_MACRO = '{#PARITY}';
	const ITEM_PROTO_KEY = 'cep.trap';
	const ITEM_PROTO_KEY2 = 'cep.trap2';
	const COMPONENT_VALUE = 'sensor1';
	// Stable per-trigger tag (fixed name, value resolved from the LLD macro to the component, e.g.
	// 'sensor1') used to map each discovered trigger to its own service. Unlike 'component_{ITEM.VALUE}'
	// the tag name does not contain {ITEM.VALUE}, so it is not rewritten at event time and the service
	// problem-tag match is stable across all scenarios.
	const SERVICE_TAG = 'cep_service';
	// Extra tag added to problem events by a webhook (see createExtraTagWebhookAction), used to verify that
	// tags returned by a media type are applied to the events they were generated for.
	const WEB_SERVICE_TAG = 'web_service';
	// Tag added by the second, independent webhook created alongside the first one (see
	// createExtraTagWebhookAction): a different tag name whose value is the same trailing number prefixed
	// with 'second_', verifying that tags returned by two separate media types both land on the same event.
	const WEB_SERVICE_TAG2 = 'web_service2';
	// Second webhook-applied tag carrying the event's component (copied from the 'component' event tag by
	// the same webhook). The web-tag services (see createWebTagServices) match problems only on this tag,
	// so they can go into problem state only via tags applied by the webhook, never via a trigger tag.
	const WEB_COMPONENT_TAG = 'web_component';

	// Name prefix shared by every CEP rule (ceprule API) these scenarios create. deleteCepRules() removes
	// every rule whose name starts with it, so a rule left behind by an aborted test cannot keep closing
	// the problems of the scenarios that run afterwards.
	const CEP_RULE_NAME_PREFIX = 'CEP rule ';
	// The single CEP rule used by the close-on-up complex event processing scenario, see
	// buildCloseOnUpCepRuleParams().
	const CEP_RULE_CLOSE_ON_UP = self::CEP_RULE_NAME_PREFIX.'tag correlation close on up';
	// The very same scenario driven by a cause and symptom grouping window instead, see
	// prepareDataCepWindowCauseSymptomCloseOnUp(). The window pairs the events on the same 'service' tag, so
	// what it closes and when is exactly what the tag correlation rule above closes; the difference is what its
	// grouping leaves on the events - the "down_N" problem is the cause of its window and the "up_N" problem
	// that ends it a symptom of that cause.
	const CEP_RULE_CLOSE_ON_UP_CAUSE = self::CEP_RULE_NAME_PREFIX.'cause symptom close on up';
	// Extra trigger tag used by the tag-exists flavour of that scenario, whose NAME (not value) carries the
	// state: the tag name contains {ITEM.VALUE}, which is resolved at event time, so a "down_N" event gets a
	// 'state_down' tag and an "up_N" event a 'state_up' tag. That lets the close-window operation single out
	// the "up" events with a plain tag-exists condition on CEP_STATE_TAG_UP, without comparing tag values.
	const CEP_STATE_TAG = 'state_{{ITEM.VALUE}.regsub("^([a-z]+)", "\\1")}';
	const CEP_STATE_TAG_UP = 'state_up';
	// Its counterpart, the tag name a "down" event gets instead - what the discarding close window flavours single
	// out, since there the "up" events are the ones that must be kept, see
	// prepareDataCepWindowCloseWindowOperations().
	const CEP_STATE_TAG_DOWN = 'state_down';

	// The thirty operator coverage rules of the windowless scenario, see getWindowNoneRules(): none of them has a window
	// (WINDOW_NONE) and each one applies a different operator, so which rules match an event is fully
	// determined by the event itself. Each rule is named after - and tags the events it matched with - the
	// operator it applies, so a tagged event names the rules that matched it.
	//
	// Twelve rules test the 'service' id of the event (eight through the event tags, four through the event
	// name) with operators coming in opposite pairs, so every event is matched by exactly one rule of every
	// pair. The other fourteen do not tell the events apart - they test the severity, the same DISASTER for
	// every event of these prototypes, the host, the same discovered host for all of them, its host group, and
	// the all-the-time period - so each one either holds for every event or for none: six of them tag all
	// three events and eight ("severity_not_equals", the non-Equals host and host group rules and
	// "time_period_not_in") must tag nothing, which is what pins down that a rule whose filter does not match
	// never tags anything.
	const CEP_TAG_SERVICE_EQUALS = 'service_equals';
	const CEP_TAG_SERVICE_NOT_EQUALS = 'service_not_equals';
	const CEP_TAG_SERVICE_CONTAINS = 'service_contains';
	const CEP_TAG_SERVICE_NOT_CONTAINS = 'service_not_contains';
	const CEP_TAG_SERVICE_MORE_EQUAL = 'service_more_equal';
	const CEP_TAG_SERVICE_LESS_EQUAL = 'service_less_equal';
	const CEP_TAG_SERVICE_EXISTS = 'service_exists';
	const CEP_TAG_SERVICE_NOT_EXISTS = 'service_not_exists';
	const CEP_TAG_EVENT_NAME_EQUALS = 'event_name_equals';
	const CEP_TAG_EVENT_NAME_NOT_EQUALS = 'event_name_not_equals';
	const CEP_TAG_EVENT_NAME_CONTAINS = 'event_name_contains';
	const CEP_TAG_EVENT_NAME_NOT_CONTAINS = 'event_name_not_contains';
	const CEP_TAG_SEVERITY_EQUALS = 'severity_equals';
	const CEP_TAG_SEVERITY_NOT_EQUALS = 'severity_not_equals';
	const CEP_TAG_SEVERITY_MORE_EQUAL = 'severity_more_equal';
	const CEP_TAG_SEVERITY_LESS_EQUAL = 'severity_less_equal';
	const CEP_TAG_HOST_EQUALS = 'host_equals';
	const CEP_TAG_HOST_NOT_EQUALS = 'host_not_equals';
	const CEP_TAG_HOST_CONTAINS = 'host_contains';
	const CEP_TAG_HOST_NOT_CONTAINS = 'host_not_contains';
	const CEP_TAG_HOST_GROUP_EQUALS = 'host_group_equals';
	const CEP_TAG_HOST_GROUP_NOT_EQUALS = 'host_group_not_equals';
	const CEP_TAG_HOST_GROUP_CONTAINS = 'host_group_contains';
	const CEP_TAG_HOST_GROUP_NOT_CONTAINS = 'host_group_not_contains';
	const CEP_TAG_TIME_PERIOD_IN = 'time_period_in';
	const CEP_TAG_TIME_PERIOD_NOT_IN = 'time_period_not_in';
	// The last four rules are the only ones whose filter combines several conditions of its own, one per
	// filter evaltype, so every way of evaluating a condition set is covered as well: everything AND-ed
	// (CONDITION_EVAL_TYPE_AND), everything OR-ed (CONDITION_EVAL_TYPE_OR), same type OR-ed / distinct types
	// AND-ed (CONDITION_EVAL_TYPE_AND_OR) and a custom expression (CONDITION_EVAL_TYPE_EXPRESSION). Each one
	// selects a set of ids the same conditions under another evaltype would not, so an evaltype evaluated as
	// another one shows up as the wrong events being tagged.
	const CEP_TAG_SERVICE_AND = 'service_and';
	const CEP_TAG_SERVICE_OR = 'service_or';
	const CEP_TAG_SERVICE_AND_OR = 'service_and_or';
	const CEP_TAG_SERVICE_EXPRESSION = 'service_expression';
	// Two more rules beside those, both matching every problem event of the scenario: one running through
	// every tag operation a windowless rule can perform (see getWindowNoneTagOperationCases()), the other
	// through the operations changing the event itself (see getWindowNoneEventOperationCase()).
	// The name the event operations rule gives every event it processes. Only its middle is written into the
	// operation as it stands: what leads it comes from a user macro (CEP_OP_EVENT_NAME_MACRO) and what ends it from
	// an expression macro (CEP_OP_EXPRESSION_MACRO), so the name below is what the event ends up with only if the
	// server resolved both of them. That is also why it is kept as the parts it is assembled from rather than as
	// one string: the operation and the expectation are built from the same pieces and cannot drift apart.
	const CEP_RULE_WINDOW_NONE_OP_EVENT_NAME_PREFIX = 'CEP window none';
	const CEP_RULE_WINDOW_NONE_OP_EVENT_NAME_MIDDLE = ' event operations ';
	const CEP_RULE_WINDOW_NONE_OP_EVENT_NAME = self::CEP_RULE_WINDOW_NONE_OP_EVENT_NAME_PREFIX
			.self::CEP_RULE_WINDOW_NONE_OP_EVENT_NAME_MIDDLE.self::CEP_OP_EXPRESSION_VALUE;
	// The macros the operations of those two rules are given instead of plain strings, and the values they must
	// resolve to. Unlike the limits of a window (CEP_WINDOW_DURATION_MACRO), an operation acts on an event, so its
	// user macros are resolved for the host of that event and its templates first and only fall back to the global
	// ones - which is why these are put on the template the discovered host is linked to (see
	// prepareWindowOperationMacros()) and not created globally.
	// Every form an operation may resolve is covered, because each of them is a token of its own to the server and
	// takes a path of its own through it:
	//   - a plain user macro, in a tag value, in a tag name and mixed into surrounding text;
	//   - a user macro with a context, which must win over the plain macro of the same name;
	//   - a macro function over a user macro ({{$MACRO}.uppercase()});
	//   - a macro of the event itself, plain ({HOST.HOST}), in its indexed form ({HOST.HOST1}) and under a macro
	//     function ({{HOST.HOST}.uppercase()});
	//   - an expression macro ({?...}), which only the event name accepts - the tag operations are resolved without
	//     the expression macro search enabled, so a tag may not hold one.
	// The event macros a case may use are limited to those that are the same for every event of the scenario: one
	// tag state is asserted for all of them, so a value carrying macro like {ITEM.VALUE} could not be expected -
	// the trigger tags cover that one (see prepareCloseOnUpTriggerPrototypes(), which builds the 'state' and
	// 'service' tags from it).
	const CEP_OP_TAG_NAME_MACRO = '{$CEP_OP_TAG_NAME}';
	const CEP_OP_TAG_NAME = 'op_macro_name';
	const CEP_OP_TAG_VALUE_MACRO = '{$CEP_OP_TAG_VALUE}';
	const CEP_OP_TAG_VALUE = 'from_macro';
	// The same macro name with a context, and therefore a different value: a context macro is looked up by name
	// and context first, so as long as this one exists it - and not the plain macro above - is what an operation
	// asking for the context gets.
	const CEP_OP_TAG_VALUE_CONTEXT_MACRO = '{$CEP_OP_TAG_VALUE:"cep"}';
	const CEP_OP_TAG_VALUE_CONTEXT = 'from_context';
	// A macro function applied to the user macro above. uppercase() needs no parameters and no escaping, so what
	// the case expects is the value of the macro in capitals and nothing about the function itself.
	const CEP_OP_TAG_VALUE_FUNC_MACRO = '{{$CEP_OP_TAG_VALUE}.uppercase()}';
	const CEP_OP_EVENT_NAME_MACRO = '{$CEP_OP_EVENT_NAME}';
	// The macros of the event itself, which an operation resolves as well: the host of the event, the same host
	// through the indexed form of the macro (the index selects the item of the trigger expression the host is taken
	// from, and these triggers have one), and the host under a macro function. Every event of the scenario comes
	// from the one discovered host, so all three are the same for all of them.
	const CEP_OP_HOST_MACRO = '{HOST.HOST}';
	const CEP_OP_HOST_INDEXED_MACRO = '{HOST.HOST1}';
	const CEP_OP_HOST_FUNC_MACRO = '{{HOST.HOST}.uppercase()}';
	// The expression macro of the event name and what it must evaluate to. Its expression is a constant one on
	// purpose: the name is asserted as a whole, so an expression reading the history of the item would resolve to
	// something different for every event of the scenario and could not be expected at all. What is covered by it
	// is that the name is resolved with the expression macro search enabled - the expression itself is the
	// evaluator's business, not CEP's.
	const CEP_OP_EXPRESSION_MACRO = '{?2*3}';
	const CEP_OP_EXPRESSION_VALUE = '6';
	// The windowed flavours of the scenario (see prepareDataCepWindowOperations()) run the very same
	// operations from a rule that has a window instead of none, grouped by the 'service' tag, so every id gets
	// a window of its own. They cannot reuse the operator coverage rules above, because how many windowed
	// rules may process one event depends on the window type - which is what the second rule of each flavour,
	// identical to the first except that it only adds a tag, is there to show:
	//   - a simple window is not exclusive, so both rules are processed and the tag is added;
	//   - a tag correlation window is, so only the first rule is processed and the tag is never added.
	// The rule of the discard scenario (see prepareDataCepDiscardUp()): it drops the "up" events before they
	// are ever stored, so they leave no trace at all - not a closed problem, not an event.
	const CEP_RULE_DISCARD_UP = self::CEP_RULE_NAME_PREFIX.'window none discard up';
	const CEP_TAG_WINDOW_SECOND = 'window_second';
	const CEP_TAG_WINDOW_SECOND_VALUE = 'second';
	// How long the windows of those flavours stay open. The evicted flavours wait for it to run out before
	// their operations run, so it is kept short.
	const CEP_RULE_WINDOW_OPS_DURATION = '3s';
	// The event pattern match flavour (see prepareDataCepWindowPattern()) is driven by a script instead: the
	// window runs it over its events once a second and, when it reports a match, the operations of the rule
	// are applied to the events the window holds. The operations that execution point allows are the two that
	// copy an event, discarding one and closing the window, so the match is observed through the copies
	// appearing (this flavour) or through the window ending (see prepareDataCepWindowPatternCloseWindow()).
	// That flavour ends its window itself, on the "up" value the scenario finishes with, so the window does not
	// outlive the run - see CEP_TAG_WINDOW_PATTERN_CLOSED.
	// The service scenario (see prepareDataCepServiceTag()): a service that is in problem for as long as an
	// event carries CEP_SERVICE_TAG_NAME, a tag no trigger produces and only the CEP rule adds and takes away
	// again. The service status therefore follows the tag rather than the problem.
	const CEP_SERVICE_TAG_NAME = 'cep_service_status';
	const CEP_SERVICE_TAG_VALUE = 'problem';
	const CEP_SERVICE_NAME = 'CEP tag driven service';
	const CEP_RULE_SERVICE_TAG = self::CEP_RULE_NAME_PREFIX.'window service tag';
	// The copy scenario (see prepareDataCepWindowCopy()): a simple window that copies an event when it is
	// evicted. A copy is a full event of its own, so it matches the same rule, lands in the same window and is
	// evicted in turn - which would copy it again, and again. The "event copied" operation condition
	// (ZBX_CONDITION_TYPE_EVENT_COPIED) is what tells a copy apart from what the trigger sent, and conditioning
	// the operation on it is the only thing that ends the chain.
	const CEP_RULE_WINDOW_COPY = self::CEP_RULE_NAME_PREFIX.'window simple copy';
	// The runaway copy scenario (see prepareDataCepWindowPatternCopyAlways()): a pattern whose script always
	// reports a match. Its window is examined once a second and nothing ever leaves it, so the copy operation
	// runs again on every examination - the rule keeps producing events for as long as it exists. The scenario
	// waits for this many problem events of one id before stopping it, which is more than the single copy a
	// pattern that matches once would leave behind.
	const CEP_RULE_WINDOW_COPY_ALWAYS = self::CEP_RULE_NAME_PREFIX.'window pattern copy always';
	const CEP_RULE_WINDOW_COPY_ALWAYS_MIN = 4;
	const CEP_RULE_WINDOW_PATTERN = self::CEP_RULE_NAME_PREFIX.'window pattern match';
	const CEP_RULE_WINDOW_PATTERN_EVENTS = 3;
	// The window of that flavour is ended by the "up" value the scenario finishes with, and what the closing runs
	// is an operation that tags every event the window held with this tag - the events the values opened and the
	// copies the match made alike. A window that was never closed leaves the tag on nothing, so the tag is how the
	// scenario sees that its window ended and what was in it when it did.
	const CEP_TAG_WINDOW_PATTERN_CLOSED = 'window_pattern_closed';
	const CEP_TAG_WINDOW_PATTERN_CLOSED_VALUE = 'closed';
	// The close window scenario (see prepareDataCepWindowCloseWindowOperations()) performs the same close window
	// operation from every execution point that may decide an id has recovered: on a pattern match, where a script
	// reports one and the window therefore ends at the examination that follows the "up" value rather than at the
	// value itself; when the "up" event is added to the window, where it is closed by an event it holds; and when
	// the "up"
	// event is evicted, where it is closed by an event that did not fit into it. Every window type is run from
	// every execution point it has - both event driven ones for a simple, a tag correlation and a cause and
	// symptom window, all three for a pattern match window - so that no combination of the two is left untried.
	// The cause and symptom flavour additionally singles out the event that ends the window by the rank its window
	// gave it (the "event symptom" operation condition, ZBX_CONDITION_TYPE_EVENT_SYMPTOM) and not only by a tag of
	// the event itself, which is what only that window type can do.
	const CEP_RULE_WINDOW_PATTERN_CLOSE = self::CEP_RULE_NAME_PREFIX.'window pattern close window';
	const CEP_RULE_WINDOW_PATTERN_CLOSE_EVENT = self::CEP_RULE_NAME_PREFIX.'window pattern close window on event';
	const CEP_RULE_WINDOW_PATTERN_CLOSE_EVICTED = self::CEP_RULE_NAME_PREFIX.'window pattern close window evicted';
	const CEP_RULE_WINDOW_SIMPLE_CLOSE = self::CEP_RULE_NAME_PREFIX.'window simple close window';
	const CEP_RULE_WINDOW_SIMPLE_CLOSE_EVICTED = self::CEP_RULE_NAME_PREFIX.'window simple close window evicted';
	const CEP_RULE_WINDOW_TAG_CLOSE = self::CEP_RULE_NAME_PREFIX.'window tag close window';
	const CEP_RULE_WINDOW_TAG_CLOSE_EVICTED = self::CEP_RULE_NAME_PREFIX.'window tag close window evicted';
	const CEP_RULE_WINDOW_CAUSE_CLOSE = self::CEP_RULE_NAME_PREFIX.'window cause close window';
	const CEP_RULE_WINDOW_CAUSE_CLOSE_EVICTED = self::CEP_RULE_NAME_PREFIX.'window cause close window evicted';
	// One flavour per window type is additionally run with the "down" values of a single id discarded as they occur,
	// while the "up" values still end the windows of the ids around it: a discarded event never takes a place in a
	// window, so the window of that id has nothing to hold and nothing to be closed with, which is the one thing a
	// discard can do to a window that closes, see prepareDataCepWindowCloseWindowOperations(). The id is the last of
	// the ones the scenario sends, so every id before it is kept and behaves exactly as it does without a discard.
	const CEP_RULE_WINDOW_SIMPLE_CLOSE_DISCARD = self::CEP_RULE_NAME_PREFIX.'window simple close window discard';
	const CEP_RULE_WINDOW_TAG_CLOSE_DISCARD = self::CEP_RULE_NAME_PREFIX.'window tag close window discard';
	const CEP_RULE_WINDOW_CAUSE_CLOSE_DISCARD = self::CEP_RULE_NAME_PREFIX.'window cause close window discard';
	const CEP_RULE_WINDOW_PATTERN_CLOSE_DISCARD = self::CEP_RULE_NAME_PREFIX.'window pattern close window discard';
	// Which id that is follows from how many the scenario sends, so it is getCloseWindowDiscardService() rather
	// than a constant: a constant expression cannot read a knob through static:: and would therefore ignore the
	// count a child class overrides.
	// The pattern match one of those flavours once more, with the discarded id named by the value of the plain
	// 'service' tag instead of by the name of the per-id one: the same discard, written with the one operation
	// condition type that compares a tag value rather than a tag name, see
	// testTriggerCEP_CepWindowPatternCloseWindowDiscardOnDownTagValue() and SKIP_OPERATION_TAG_VALUE_TESTS.
	const CEP_RULE_WINDOW_PATTERN_CLOSE_DISCARD_TAG_VALUE = self::CEP_RULE_NAME_PREFIX
		.'window pattern close window discard tag value';
	// Every one of those flavours except the discarding ones is additionally run over a single id, the first of the
	// ones the scenario has (getCloseWindowServices()), with every value it sends being the "down" value of that one
	// id: the rule then keeps one window instead of one per id and everything the closing window has to close was
	// opened by the same value - one value per discovered trigger, so that one window holds an event of every trigger
	// of the host while grouping by the 'service' tag alone. Every window type and every execution point a window may
	// be closed from is run that way, so a window grouped out of that many triggers is ended by all of them alike. The
	// discarding flavours are the ones this cannot be done to: they need
	// an id whose values are dropped and another whose are kept, and with a single id there is nothing to compare
	// the dropped one against. The rule of a single id variant is named after the flavour it varies with this suffix
	// appended, see buildSingleServiceRuleName().
	const CEP_RULE_WINDOW_CLOSE_SINGLE_SUFFIX = ' single id';
	// The flavours of the window types that are not exclusive are additionally run with a second rule of their own
	// kind beside the first, so two rules keep a window of the same events at once and both of them close it: only
	// a simple and a pattern match window may be doubled that way, a tag correlation and a cause and symptom window
	// being the types of which only the first matching rule is ever processed for an event (CEP_WINDOW_UNIQ in
	// cep_event_process_rules()), so a second rule of those types could not act on the same event at all. Both rules
	// of a doubled flavour add a tag of their own as an event occurs - the first one CEP_TAG_WINDOW_FIRST, the second
	// CEP_TAG_WINDOW_SECOND - so which of them was processed for an event is read from the event rather than assumed:
	// every event of such a run must carry both, one rule missing from an event being exactly what an exclusive
	// window type would leave behind, see prepareDataCepWindowCloseWindowOperations(). The second rule is named after
	// the flavour it doubles with this suffix appended, see buildSecondRuleName().
	const CEP_RULE_WINDOW_CLOSE_SECOND_SUFFIX = ' second rule';
	const CEP_TAG_WINDOW_FIRST = 'window_first';
	const CEP_TAG_WINDOW_FIRST_VALUE = 'first';
	// Every flavour above is ended by an operation of its rule, and their windows are given a duration long enough
	// that nothing else can end them (getCloseWindowDuration()). The duration of a window is the other way one can
	// end, and what it comes to differs by window type, so it is a scenario of its own:
	//   - a cause and symptom window is closed by its duration running out (cep_window_causal_process()), which is
	//     the one window type whose duration ends it rather than evicting what it holds. No operation takes part in
	//     it: the rule of that flavour has nothing but a "close" at the window closed execution point, and the
	//     window closing on its own is what has to reach it - and it closes once per duration rather than once, the
	//     period starting again with the events that arrive after it, see
	//     runEventAssessmentTestCepWindowCauseSymptomCloseOnDuration();
	//   - a simple and a tag correlation window are the sliding types: their duration evicts the events that have
	//     been in them too long, so what ends such a window is a "close window" operation at the eviction execution
	//     point, reached this time by an event that was evicted for its age rather than for not fitting. The window
	//     then closes with the younger events still in it, which is what the "close" of the closing window reaches -
	//     the evicted event itself is out of the window before it closes and is not closed with it, see
	//     runEventAssessmentTestCepWindowCloseOnDuration().
	// A pattern match window is left out of the sliding pair on purpose: cep_window_pattern_process() drops the
	// operation mask of the eviction it performs, so a "close window" operation cannot be reached that way at all -
	// only the eviction of an event that does not fit reaches it there, which the flavours above already cover.
	const CEP_RULE_WINDOW_CAUSE_CLOSE_DURATION = self::CEP_RULE_NAME_PREFIX.'window cause close on duration';
	const CEP_RULE_WINDOW_SIMPLE_CLOSE_DURATION = self::CEP_RULE_NAME_PREFIX.'window simple close on duration';
	const CEP_RULE_WINDOW_TAG_CLOSE_DURATION = self::CEP_RULE_NAME_PREFIX.'window tag close on duration';
	// How long those windows last, in seconds. The two flavours are given different periods because they are ended
	// differently:
	//   - a sliding window is examined exactly when its oldest event ages out (cep_window_get_nextcheck()), so its
	//     period only has to leave room for the second value of the scenario to be sent and its problem seen open
	//     before that happens - and, when the restarts are turned on, for the stop and start between the two values as
	//     well, which is what getCloseOnDurationPeriod() adds to it;
	//   - a cause and symptom window closes at the end of the period it belongs to, and the periods follow a grid the
	//     rule keeps for itself (cep_rule_get_window_start_time()), so a window is closed at most one period after it
	//     was opened. The scenario waits out two of them in a row and a shorter period is what keeps that from being
	//     the slowest thing in the family. A restart needs nothing added here: the window comes back with a period of
	//     its own starting at the startup (time_created is not among the columns of cep_window), so what follows a
	//     restart is one more period and no more.
	const CEP_RULE_WINDOW_CLOSE_DURATION_PERIOD = 15;
	const CEP_RULE_WINDOW_CLOSE_DURATION_CAUSE_PERIOD = 10;
	// How long the sliding flavour waits between the two values it sends, so their ages differ by more than the
	// second or so a window may be examined late by: the older value is the one that has to be evicted alone, with the
	// younger one still in the window when the eviction closes it. It leaves the rest of the period above for the
	// second value to be sent and its problem to be seen open before the first one ages out.
	const CEP_RULE_WINDOW_CLOSE_DURATION_GAP = 7;
	// What both flavours wait for is due at a time they know - the period of a window they opened themselves - so the
	// waits are given that period and this much on top, rather than the patience the waits for a value being processed
	// run with. The window pool examines a window within a second of when it is due (cep_window_get_nextcheck()), and
	// the rest is for the closing to reach the database and the API: a closing that does not happen is then reported
	// about when it was due instead of at the end of a patience meant for something else.
	const CEP_RULE_WINDOW_CLOSE_DURATION_SLACK = 11;
	// The reset scenario (see prepareDataCepWindowHeldProblemsOperations()) is run once per window type that has a
	// window at all: resetting a rule throws away the windows it has open, and what a window holds is the one thing
	// every window type keeps, so each of them must lose it the same way.
	const CEP_RULE_WINDOW_SIMPLE_RESET = self::CEP_RULE_NAME_PREFIX.'window simple reset';
	const CEP_RULE_WINDOW_TAG_RESET = self::CEP_RULE_NAME_PREFIX.'window tag reset';
	const CEP_RULE_WINDOW_CAUSE_RESET = self::CEP_RULE_NAME_PREFIX.'window cause reset';
	const CEP_RULE_WINDOW_PATTERN_RESET = self::CEP_RULE_NAME_PREFIX.'window pattern reset';
	// The delete scenario is run over the same rule of every window type, differing only in how the rule is taken
	// away: deleting it must do to its windows what resetting it does, and it additionally takes the rule itself, so
	// nothing opens a window again and what those windows held is the last thing the rule ever holds - see
	// runEventAssessmentTestCepWindowDelete().
	const CEP_RULE_WINDOW_SIMPLE_DELETE = self::CEP_RULE_NAME_PREFIX.'window simple delete';
	const CEP_RULE_WINDOW_TAG_DELETE = self::CEP_RULE_NAME_PREFIX.'window tag delete';
	const CEP_RULE_WINDOW_CAUSE_DELETE = self::CEP_RULE_NAME_PREFIX.'window cause delete';
	const CEP_RULE_WINDOW_PATTERN_DELETE = self::CEP_RULE_NAME_PREFIX.'window pattern delete';
	// The delete is additionally run against a pattern match window that is being examined while it happens: the
	// script of this rule sleeps, so the delete lands in the middle of a script that goes on running for seconds
	// after the rule it belongs to is gone - and the window it was examining has to clean itself up once it returns,
	// see runEventAssessmentTestCepWindowDeleteDuringScript().
	const CEP_RULE_WINDOW_PATTERN_DELETE_SLEEP = self::CEP_RULE_NAME_PREFIX.'window pattern delete sleeping script';
	// How long that script sleeps on every examination of the window. It has to outlast the delete by enough for the
	// script to still be running when the rule is gone, and stay below the JS execution timeout (ZBX_ES_TIMEOUT, ten
	// seconds) - Zabbix.sleep() throws instead of sleeping when it is asked for longer than that.
	const CEP_RULE_WINDOW_SLEEP_SCRIPT_MS = 3000;
	// How long the scenario waits before deleting the rule, counted from the moment the event it sent is known to be
	// in the window. A pattern match window is examined once a second and every examination holds it for the whole
	// sleep above, so the window is being examined nearly all the time - waiting a second only makes it near certain
	// that the delete lands inside a script rather than between two of them, and leaves two seconds of the script to
	// run without a rule behind it.
	const CEP_RULE_WINDOW_SLEEP_DELETE_DELAY = 1;
	// The unresolved limits scenario is run over the same rule of every window type as well, this time with the
	// limits of its window given as user macros that do not exist: a limit that does not resolve is not a limit at
	// all, so no window is opened for the event that would have needed one and the rule reports what it could not
	// make sense of - see prepareDataCepWindowUnresolvedLimitsOperations() and
	// runEventAssessmentTestCepWindowUnresolvedLimits().
	const CEP_RULE_WINDOW_SIMPLE_LIMITS = self::CEP_RULE_NAME_PREFIX.'window simple unresolved limits';
	const CEP_RULE_WINDOW_TAG_LIMITS = self::CEP_RULE_NAME_PREFIX.'window tag unresolved limits';
	const CEP_RULE_WINDOW_CAUSE_LIMITS = self::CEP_RULE_NAME_PREFIX.'window cause unresolved limits';
	// The pattern match window is driven one limit at a time instead. The scenario above leaves both macros
	// uncreated and therefore only ever reads the limit the server parses first, so neither of them is seen
	// failing by itself: each of these rules is given the other limit as a macro that does exist, which makes the
	// limit it is named after the only one the server can stumble on and the only one whose creation can bring the
	// rule back - see runEventAssessmentTestCepWindowSingleUnresolvedLimit().
	const CEP_RULE_WINDOW_PATTERN_DURATION_LIMIT = self::CEP_RULE_NAME_PREFIX.'window pattern unresolved duration';
	const CEP_RULE_WINDOW_PATTERN_CAPACITY_LIMIT = self::CEP_RULE_NAME_PREFIX.'window pattern unresolved capacity';
	// That the rule reports something is what the scenario reads, not what it says: the macros are the very ones the
	// working flavours give their windows (CEP_WINDOW_DURATION_MACRO and CEP_WINDOW_CAPACITY_MACRO) and this scenario
	// leaves them uncreated, so an unknown user macro is replaced by nothing at all and neither a duration nor a
	// capacity can be read out of what is left. Which of the two the server names is its own wording and is left to
	// it - what the scenario is about is a rule that cannot open a window saying so, and clearing that once the
	// macros exist, see waitForCepRuleError() and waitForCepRuleNoError().
	// What the macros are set to once the scenario has seen the error each of them causes while missing: a duration
	// outlasting the rest of the run, so the window that is finally opened keeps what it is given until an
	// operation ends it, and no capacity limit at all, so nothing is evicted for not fitting.
	const CEP_RULE_WINDOW_LIMITS_DURATION = '2m';
	const CEP_RULE_WINDOW_LIMITS_CAPACITY = 0;
	// The cause and symptom flavour (see prepareDataCepWindowCauseSymptom()) needs neither for its grouping: its
	// window does that work as the events arrive, ranking the first event of a group as the cause of the ones that
	// follow. The count of those is kept in a tag of the cause event, which only this window type has.
	const CEP_TAG_SYMPTOM_COUNT = 'symptom_count';
	// The capacity flavours (see prepareDataCepWindowCapacityOperations()) let the window overflow instead: it
	// holds a single event and outlasts the whole test, so every event after the first one is evicted for not
	// fitting rather than for having been in the window too long.
	const CEP_RULE_WINDOW_CAPACITY_DURATION = '2m';
	const CEP_RULE_WINDOW_CAPACITY = 1;
	// The discarding flavour of that family is the one exception: it drops the very events that would have ended
	// its windows, so nothing arrives to end them and they have to run out instead. Its window is therefore given
	// a duration short enough to be waited out within the patience of a single wait (WAIT_ITERATIONS seconds) and
	// long enough for the problems the windows hold to be seen open before it runs out - the scenario asserts
	// them open first and only then waits for the eviction, see runEventAssessmentTestCepWindowCapacityDiscard().
	const CEP_RULE_WINDOW_CAPACITY_DISCARD_DURATION = '5s';
	// The close window flavours compute both limits of their windows instead, from the number of ids they drive and
	// the number of values each of them is sent, see getCloseWindowDuration() and getCloseWindowCapacity().
	// They, like the windowed flavours of the operations scenario, also hand those limits to the window as user
	// macros rather than as the values themselves: the duration and the capacity are the only window parameters
	// that may hold one (the API allows a user macro in no other window field), and the server resolves them anew
	// whenever it works on a window - limits that do not resolve are not applied at all, leaving the window with
	// the zero duration it starts out with and therefore with nothing it holds for any length of time. Between
	// them those flavours cover every window type, so the resolving is tried from all of them, while the
	// remaining window scenarios keep plain values - both forms of the same limits are therefore covered.
	// One name for each is enough for all of the flavours: the macros are global and macroizeWindowLimits() points
	// them at the limits of the window the flavour about to run builds, so a flavour never inherits the ones of
	// the flavour before it.
	const CEP_WINDOW_DURATION_MACRO = '{$CEP_WINDOW_DURATION}';
	const CEP_WINDOW_CAPACITY_MACRO = '{$CEP_WINDOW_CAPACITY}';
	// How long the suppress operation of that rule suppresses an event for. The operation stores a duration
	// rather than a deadline, so the period is counted from the moment it runs on that event and every event
	// of the scenario gets the full period of its own - it only has to outlast the verification of the wave
	// the event belongs to, not the whole run. Everything after it is waited for, so it is kept as short as
	// it can safely be.
	const CEP_RULE_WINDOW_NONE_SUPPRESS_PERIOD = '60s';
	// Expired suppressions are removed by the timer, which does that pass once a minute, so clearing them
	// takes up to a minute longer than the suppression itself.
	const CEP_RULE_WINDOW_NONE_UNSUPPRESS_ITERATIONS = 180;
	// The custom expression of the fourth combining rule (CONDITION_EVAL_TYPE_EXPRESSION), grouping its
	// conditions in a way none of the other three evaltypes can express.
	const CEP_RULE_WINDOW_NONE_FORMULA = 'A and (B or C)';
	// The time period the last rule pair tests against: all the time, so whenever the scenario happens to run
	// the "In" rule matches every event and the "Not in" rule none of them.
	const CEP_RULE_WINDOW_NONE_TIME_PERIOD = '1-7,00:00-24:00';
	// Suffix making a host or host group name that cannot match: appended to the real name it is no longer a
	// substring of it, so the "contains" flavours - the only ones a real name would satisfy - come out false
	// too and "equals" stays the single matching rule of the four (see getWindowNoneRules()).
	const CEP_RULE_WINDOW_NONE_ABSENT_SUFFIX = '_absent';
	const CEP_RULE_WINDOW_NONE_HOST_ABSENT = self::HOST_DISC_VALUE.self::CEP_RULE_WINDOW_NONE_ABSENT_SUFFIX;
	// The 'service' ids (the trailing number of the item value, see prepareCloseOnUpTriggerPrototypes) the
	// rules test against. The Equals, Contains and Exists pairs all work on "0"; the scenario also sends an id
	// that contains it without being equal to it ("10"), which is what tells the Equals pair from the Contains
	// pair. The numeric pair splits the same ids from either side - "is less than or equal 0" and "is more
	// than or equal 1" - so it needs the id right above CEP_RULE_WINDOW_NONE_SERVICE as its second value.
	const CEP_RULE_WINDOW_NONE_SERVICE = '0';
	const CEP_RULE_WINDOW_NONE_SERVICE_NEXT = '1';
	// The third id the scenario sends: it contains CEP_RULE_WINDOW_NONE_SERVICE without being equal to it and
	// starts with CEP_RULE_WINDOW_NONE_SERVICE_NEXT, which is what tells the Equals pairs from the Contains
	// ones, and it is numerically above both.
	const CEP_RULE_WINDOW_NONE_SERVICE_LAST = '10';
	// Extra trigger tag the windowless scenario adds to the prototypes, whose NAME (not value) carries the
	// service id: like CEP_STATE_TAG the name contains {ITEM.VALUE} and is resolved at event time, so a
	// "down_0" event gets a 'service_0' tag and a "down_10" event a 'service_10' one. Existence is a property
	// of the tag name, so this is what the Exists / Does not exist pair tests for (CEP_SERVICE_TAG_PREFIX
	// concatenated with the id gives the name a rule looks for).
	const CEP_SERVICE_TAG_PREFIX = 'service_';
	const CEP_SERVICE_TAG = self::CEP_SERVICE_TAG_PREFIX.'{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}';
	// What the event name rules test against. The prototypes name every event "CEP trigger <component>
	// <item value>" (see prepareCloseOnUpTriggerPrototypes), and the scenario drives the first discovered
	// component, so the full name of the "down_1" event is known up front - that is what the Equals pair
	// compares with, while the Contains pair only looks for the item value inside the name. The two pairs
	// therefore disagree on "down_10", whose name contains "down_1" without being equal to that name.
	const CEP_RULE_WINDOW_NONE_VALUE_NEXT = 'down_'.self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT;
	const CEP_RULE_WINDOW_NONE_VALUE_LAST = 'down_'.self::CEP_RULE_WINDOW_NONE_SERVICE_LAST;
	const CEP_RULE_WINDOW_NONE_EVENT_NAME = 'CEP trigger '.self::COMPONENT_VALUE.' '
		.self::CEP_RULE_WINDOW_NONE_VALUE_NEXT;

	// Separate template used to stress single-trigger event generation. The template (linked directly to
	// the HOST_NAME host) carries a master log item plus an LLD rule with a dependent log item prototype.
	// The trigger prototype references the dependent discovered item and has multiple problem event
	// generation enabled, so every log value pushed to the master item is propagated to the dependent
	// item and opens a new problem on the one discovered trigger.
	const LOG_TEMPLATE_NAME = 'template_trigger_cep_log';
	const LOG_LLD_RULE_KEY = 'lld.cep.log.trapper';
	const LOG_LLD_MACRO = '{#LOGNUM}';
	const LOG_MASTER_ITEM_KEY = 'cep.log.master';
	const LOG_ITEM_PROTO_KEY = 'cep.log.proto';
	const LOG_COMPONENT_VALUE = 'logsensor1';
	const WAIT_ITERATIONS = 30;
	const WAIT_ITERATION_DELAY = 1;
	const WAIT_ITERATIONS_LONGER = 30;

	// change iterations to fail faster when debugging
	const STATE_CHANGE_WAIT_ITERATIONS = 30;

	// When true, prepareData() disables every internal-source action instead of enabling the built-in
	// "Report not supported items" / "Report unknown triggers" actions for the whole suite. The *Unknown
	// tests then enable those two actions for their own run only and disable them again afterwards, so the
	// rest of the suite runs without the server generating internal item-not-supported / trigger-unknown
	// events. When false the historic behaviour (enabled for the whole suite) is used.
	const SCOPED_INTERNAL_ACTIONS = true;

	// Active proxy whose name is spoofed when delivering item values. The host (and, by inheritance, the
	// discovered host) is assigned to this proxy in prepareData(), and every value is sent to the server
	// as a 'proxy data' request impersonating this proxy instead of as direct sender/trapper data, so the
	// whole suite exercises the proxy-delivery path. See dispatchValues()/dispatchSenderValues().
	const PROXY_NAME = 'test_trigger_cep_proxy';


	private static $hostid;
	private static $proxyid = null;

	// host name -> hostid and "host\0key" -> itemid lookup caches used by the proxy dispatch helpers to
	// translate the host/key based test values into the itemid based entries the proxy data protocol
	// requires. Populated lazily and reset in clearData().
	private static $hostid_cache = [];
	private static $itemid_cache = [];
	private static $disc_hostid;
	private static $templateid;
	private static $log_templateid;
	private static $log_lld_ruleid;
	private static $log_master_itemid;
	private static $log_item_prototypeid;
	private static $log_trigger_prototypeid;
	private static $discovered_log_triggerid;
	private static $lld_ruleid;
	private static $item_prototypeid;
	private static $dep_item_prototypeid;
	private static $trigger_prototypeid;
	private static $dep_trigger_prototypeid;
	private static $discovered_triggerid;
	private static $discovered_dep_triggerid;
	private static $discovered_triggerids = [];
	private static $discovered_dep_triggerids = [];
	private static $correlationid;
	private static $correlationid2;
	private static $cep_ruleid;
	private static $disc_maintenanceids = [];
	private static $serviceids = [];
	private static $service_actionid;
	private static $trigger_actionid;
	private static $mediatypeid;
	private static $tag_mediatypeid;
	private static $tag_actionid;
	private static $tag_mediatypeid2;
	private static $tag_actionid2;
	private static $web_tag_serviceids = [];
	// The service of the tag driven service scenario, see prepareDataCepServiceTag().
	private static $cep_tag_serviceid = null;
	private static $sessionid = null;

	// Highest internal-source eventid that exists before the current *Unknown cycle starts generating its
	// own events; captured by runOpenUnknownTest() so runCloseUnknownTest() can restrict the alert wait to
	// exactly this cycle's notifications (the alerts of events with a larger eventid) before disabling the
	// internal actions. @see waitForInternalAlertsCompleted().
	private static $internal_event_baseline_id = 0;

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 0,
				'DebugLevel' => 3,
				'CacheSize' => '128M',
				'HistoryCacheSize' => '32M',
				'HistoryIndexCacheSize' => '32M',
				'ValueCacheSize' => '128M',
				'LogSlowQueries' => 10000,
				'StartEscalators' => 8,
				'MaxHousekeeperDelete' => 0,
				'StartTrappers' => 32,
				'StartAlerters' => 10,
				'StartTimers' => 2
			]
		];
	}

	/**
	 * Lower bound (max eventid captured at the start of the current scenario) used to limit
	 * every event.get to only the events generated during the scenario. Without this bound the
	 * queries would re-fetch the entire, ever-growing event history of all discovered triggers
	 * on every poll iteration, which does not scale with LLD_DISCOVERY_COUNT.
	 */
	private $event_baseline_id = 0;

	// Name of the host group the discovered host belongs to, resolved on first use by
	// getDiscHostGroupName() for the host group rules of the windowless CEP scenario.
	private $disc_hostgroup_name = null;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Disable audit log so the bulk of API operations below do not flood it.
		$this->call('settings.update', ['auditlog_enabled' => 0, 'auditlog_mode' => 0]);

		// Disable every pre-existing monitored host so they don't interfere with the suite; this
		// suite's own hosts are (re-)set to monitored after prepareData() by onBeforeTestSuite().
		$response = $this->call('host.get', [
			'filter' => ['status' => HOST_STATUS_MONITORED],
			'output' => ['hostid']
		]);
		foreach ($response['result'] as $h) {
			$this->call('host.update', [
				'hostid' => $h['hostid'],
				'status' => HOST_STATUS_NOT_MONITORED
			]);
		}

		// Retrieve template group ID.
		$response = $this->call('templategroup.get', [
			'filter' => ['name' => 'Templates']
		]);
		$this->assertCount(1, $response['result']);
		$templategroupid = $response['result'][0]['groupid'];

		// Create template.
		$response = $this->call('template.create', [
			'host' => self::TEMPLATE_NAME,
			'groups' => [
				['groupid' => $templategroupid]
			]
		]);
		$this->assertArrayHasKey('templateids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['templateids']);
		self::$templateid = $response['result']['templateids'][0];

		// Create LLD rule on the template (trapper type so tests can push data directly).
		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$templateid,
			'name' => 'CEP LLD Discovery Rule',
			'key_' => self::LLD_RULE_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'lifetime_type' => 2
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$lld_ruleid = $response['result']['itemids'][0];

		// Create item prototype on the LLD rule.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$templateid,
			'ruleid' => self::$lld_ruleid,
			'name' => 'CEP sensor ['.self::LLD_MACRO.']',
			'key_' => self::ITEM_PROTO_KEY.'['.self::LLD_MACRO.']',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'preprocessing' => [
				[
					'type' => ZBX_PREPROC_TRIM,
					'params' => ' ',
					'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
					'error_handler_params' => ''
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$item_prototypeid = $response['result']['itemids'][0];

		// Create trigger prototype referencing the item prototype.
		$response = $this->call('triggerprototype.create', [
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'last(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'])<>0',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'priority' => TRIGGER_SEVERITY_DISASTER,
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}']
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$trigger_prototypeid = $response['result']['triggerids'][0];

		// Create second item prototype on the LLD rule.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$templateid,
			'ruleid' => self::$lld_ruleid,
			'name' => 'CEP sensor2 ['.self::LLD_MACRO.']',
			'key_' => self::ITEM_PROTO_KEY2.'['.self::LLD_MACRO.']',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'preprocessing' => [
				[
					'type' => ZBX_PREPROC_TRIM,
					'params' => ' ',
					'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
					'error_handler_params' => ''
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$dep_item_prototypeid = $response['result']['itemids'][0];

		// Create second trigger prototype with a dependency on the first trigger prototype.
		$response = $this->call('triggerprototype.create', [
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'last(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'])<>0',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'priority' => TRIGGER_SEVERITY_DISASTER,
			'dependencies' => [
				['triggerid' => self::$trigger_prototypeid]
			],
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep-dep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO]
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$dep_trigger_prototypeid = $response['result']['triggerids'][0];

		// Create the separate log template with an LLD rule, a master log item, a dependent log item
		// prototype and a trigger prototype with multiple problem event generation enabled. The value
		// burst is pushed to the master item and propagated to the dependent discovered item, on which the
		// trigger prototype fires. This template is linked directly to the host below so that a single
		// discovered trigger can be exercised with a large value burst.
		$response = $this->call('template.create', [
			'host' => self::LOG_TEMPLATE_NAME,
			'groups' => [
				['groupid' => $templategroupid]
			]
		]);
		$this->assertArrayHasKey('templateids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['templateids']);
		self::$log_templateid = $response['result']['templateids'][0];

		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$log_templateid,
			'name' => 'CEP Log LLD Discovery Rule',
			'key_' => self::LOG_LLD_RULE_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'lifetime_type' => 2
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$log_lld_ruleid = $response['result']['itemids'][0];

		// Master (normal) log item that receives the value burst via trapper.
		$response = $this->call('item.create', [
			'hostid' => self::$log_templateid,
			'name' => 'CEP log master item',
			'key_' => self::LOG_MASTER_ITEM_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_LOG
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$log_master_itemid = $response['result']['itemids'][0];

		// Dependent log item prototype: each value pushed to the master item is propagated here and drives
		// the trigger prototype on the discovered item.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$log_templateid,
			'ruleid' => self::$log_lld_ruleid,
			'name' => 'CEP log item ['.self::LOG_LLD_MACRO.']',
			'key_' => self::LOG_ITEM_PROTO_KEY.'['.self::LOG_LLD_MACRO.']',
			'type' => ITEM_TYPE_DEPENDENT,
			'master_itemid' => self::$log_master_itemid,
			'value_type' => ITEM_VALUE_TYPE_LOG
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$log_item_prototypeid = $response['result']['itemids'][0];

		// Multiple problem event generation: every log value matching the pattern opens a new problem,
		// so a burst of N values produces N problem events on the single discovered trigger.
		$response = $this->call('triggerprototype.create', [
			'description' => 'CEP log trigger for '.self::LOG_LLD_MACRO,
			'expression' => 'find(/'.self::LOG_TEMPLATE_NAME.'/'.self::LOG_ITEM_PROTO_KEY
				.'['.self::LOG_LLD_MACRO.'],,"like","problem")=1',
			'event_name' => 'CEP log trigger '.self::LOG_LLD_MACRO.' {ITEM.VALUE}',
			'priority' => TRIGGER_SEVERITY_DISASTER,
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'tags' => [
				['tag' => 'type', 'value' => 'cep-log']
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$log_trigger_prototypeid = $response['result']['triggerids'][0];

		// Create host — template will be linked via host prototype discovery, not directly.
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				['groupid' => 4]
			],
			'templates' => [
				['templateid' => self::$log_templateid]
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Create the active proxy and assign the host to it. The host prototype discovers its host with the
		// parent host's proxy inherited, so the discovered host is monitored by this proxy too; every value
		// is then delivered to the server impersonating this proxy (see dispatchValues()).
		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'hosts' => [
				['hostid' => self::$hostid]
			]
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['proxyids']);
		self::$proxyid = $response['result']['proxyids'][0];

		// Create LLD rule on the host for host prototype discovery.
		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$hostid,
			'name' => 'Host LLD Discovery Rule',
			'key_' => self::HOST_LLD_RULE_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'lifetime_type' => 2
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		$host_disc_ruleid = $response['result']['itemids'][0];

		// Create host prototype linked to the template created above.
		$response = $this->call('hostprototype.create', [
			'ruleid' => $host_disc_ruleid,
			'host' => '{#HOST}',
			'groupLinks' => [
				['groupid' => 4]
			],
			'templates' => [
				['templateid' => self::$templateid]
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);

		// Create the webhook media type used by all action operations and attach it to the Admin user
		// before enabling the internal actions below, so their notifications route through it too.
		$this->prepareWebhookMediaType();

		// Make the server generate internal item-not-supported and trigger-unknown events (verified by the
		// *Unknown tests). With SCOPED_INTERNAL_ACTIONS the internal actions are disabled here and re-enabled
		// only for the *Unknown tests, so the rest of the suite runs without them; otherwise the built-in
		// actions are enabled for the whole suite. Either way clearData() cleans up and the configuration
		// cache is reloaded by the first test (or by enableInternalActions()).
		if (self::SCOPED_INTERNAL_ACTIONS) {
			$this->disableInternalActions();
		}
		else {
			$this->setInternalActionStatus('Report unknown triggers', ACTION_STATUS_ENABLED);
			$this->setInternalActionStatus('Report not supported items', ACTION_STATUS_ENABLED);
		}

		return true;
	}

	/**
	 * Create a simple webhook media type whose script just returns 1, so the escalator exercises the
	 * alerter end-to-end without contacting anything external, and attach it to the Admin user (a member
	 * of user group 7) so the action operations actually generate alerts. As it is the user's only media,
	 * the built-in internal actions (which send to group 7 with the default "all media types") also route
	 * their notifications through it. Removed in clearData().
	 */
	private function prepareWebhookMediaType(): void {
		$response = $this->call('mediatype.create', [
			'name' => 'CEP webhook',
			'type' => MEDIA_TYPE_WEBHOOK,
			'script' => 'return 1;',
			'status' => MEDIA_TYPE_STATUS_ACTIVE
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['mediatypeids']);
		self::$mediatypeid = $response['result']['mediatypeids'][0];

		$this->call('user.update', [
			'userid' => 1,
			'medias' => [
				['mediatypeid' => self::$mediatypeid, 'sendto' => 'cep']
			]
		]);
	}

	/**
	 * Create one service per discovered primary trigger (mapped to its trigger via the stable SERVICE_TAG
	 * problem tag, value '<component>'), a service action firing on those services (service name like
	 * 'CEP service') and a trigger action firing on the discovered triggers (event tag type=cep). Both
	 * actions route through the CEP webhook media type, so the CEP event stream drives the service manager
	 * and the action/escalator pipeline. The configuration cache is reloaded so the server picks up the
	 * new actions. Everything is removed in clearData().
	 */
	private function createServicesAndActions(): void {
		// Re-read the discovered primary triggers with their tags so each service can be mapped to its
		// trigger via the trigger's own SERVICE_TAG value.
		$response = $this->call('trigger.get', [
			'triggerids' => self::$discovered_triggerids,
			'output' => ['triggerid'],
			'selectTags' => 'extend'
		]);
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'],
			'Not all discovered triggers were found.');

		$services = [];
		foreach ($response['result'] as $trigger) {
			$service_tag = current(array_filter($trigger['tags'],
				fn($t) => $t['tag'] === self::SERVICE_TAG
			));
			$this->assertNotFalse($service_tag,
				'Discovered trigger '.$trigger['triggerid'].' has no '.self::SERVICE_TAG.' tag.');

			$services[] = [
				'name' => 'CEP service '.$trigger['triggerid'],
				'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL,
				'sortorder' => 0,
				'problem_tags' => [
					[
						'tag' => $service_tag['tag'],
						'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL,
						'value' => $service_tag['value']
					]
				]
			];
		}

		$response = $this->call('service.create', $services);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']['serviceids'],
			'Not all CEP services were created.');
		self::$serviceids = $response['result']['serviceids'];

		// Service action: problem, recovery and update message operations targeting user group 7 via the
		// CEP webhook media type, so the escalator runs the service action through the alerter.
		$response = $this->call('action.create', [
			'name' => 'CEP service action',
			'eventsource' => EVENT_SOURCE_SERVICE,
			'status' => ACTION_STATUS_ENABLED,
			'esc_period' => '1h',
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_SERVICE_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => 'CEP service'
					]
				]
			],
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Problem', 'subject' => 'Problem'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Recovery', 'subject' => 'Recovery'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'update_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Update', 'subject' => 'Update'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		self::$service_actionid = $response['result']['actionids'][0];

		// Trigger action firing on the discovered triggers (event tag type=cep), with problem and
		// recovery message operations routed through the same CEP webhook media type.
		$response = $this->call('action.create', [
			'name' => 'CEP trigger action',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'esc_period' => '1h',
			'pause_suppressed' => 1,
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value2' => 'type',
						'value' => 'cep'
					]
				]
			],
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Problem', 'subject' => 'Problem'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$mediatypeid,
						'message' => 'Recovery', 'subject' => 'Recovery'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		self::$trigger_actionid = $response['result']['actionids'][0];

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Update all trigger prototypes on the LLD rule to use "None" ok event generation
	 * and resend discovery data so the server picks up the updated prototype configuration.
	 */
	public function prepareDataNoneOkEvent() {
		// Fetch all trigger prototypes belonging to the LLD rule.
		$response = $this->call('triggerprototype.get', [
			'discoveryids' => [self::$lld_ruleid],
			'output' => ['triggerid']
		]);
		$this->assertNotEmpty($response['result'], 'No trigger prototypes found on the LLD rule.');

		// Update each prototype: disable ok event generation.
		foreach ($response['result'] as $prototype) {
			$this->call('triggerprototype.update', [
				'triggerid' => $prototype['triggerid'],
				'recovery_mode' => ZBX_RECOVERY_MODE_NONE
			]);
		}

		// Resend LLD discovery data to re-instantiate discovered triggers with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'recovery_mode']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['recovery_mode'] !== ZBX_RECOVERY_MODE_NONE) {
					return false;
				}
			}
			return true;
		});

		// Reload configuration cache so the server is aware of the changed prototypes.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Restore all trigger prototypes to expression-based recovery mode and resend
	 * discovery data so the server picks up the restored configuration.
	 */
	public function prepareDataRestoreRecovery() {
		// Fetch all trigger prototypes belonging to the LLD rule.
		$response = $this->call('triggerprototype.get', [
			'discoveryids' => [self::$lld_ruleid],
			'output' => ['triggerid']
		]);
		$this->assertNotEmpty($response['result'], 'No trigger prototypes found on the LLD rule.');

		// Restore each prototype to expression-based recovery.
		foreach ($response['result'] as $prototype) {
			$this->call('triggerprototype.update', [
				'triggerid' => $prototype['triggerid'],
				'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION
			]);
		}

		// Resend LLD discovery data to re-instantiate discovered triggers with the restored config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'recovery_mode']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['recovery_mode'] !== ZBX_RECOVERY_MODE_EXPRESSION) {
					return false;
				}
			}
			return true;
		});

		// Reload configuration cache so the server is aware of the restored prototypes.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Switch both trigger prototypes to recovery-expression mode, setting the recovery
	 * expression identical to the problem expression, and resend discovery data so
	 * the server picks up the updated configuration.
	 */
	public function prepareDataRecoveryExpression() {
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'recovery_mode' => ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION,
			'recovery_expression' => 'min(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],#2)=0'
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'recovery_mode' => ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION,
			'recovery_expression' => 'min(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],#2)=0'
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Reload configuration cache so the server is aware of the changed prototypes.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// Verify the discovered triggers reflect the updated recovery mode.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'recovery_mode']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['recovery_mode'] !== ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION, $trigger['recovery_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to recovery-expression mode.');
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Switch both trigger prototypes to recovery-expression mode with multiple event generation
	 * enabled, and resend discovery data so the server picks up the updated configuration.
	 */
	public function prepareDataMultipleEventsRecoveryExpression() {
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'recovery_mode' => ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'recovery_mode' => ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify the discovered triggers reflect the updated mode.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'type', 'recovery_mode']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED
						|| (int) $trigger['recovery_mode'] !== ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
			$this->assertEquals(ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION, $trigger['recovery_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to recovery-expression mode.');
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Switch both trigger prototypes to tag-correlation mode: OK events close only
	 * PROBLEM events whose "component" tag value matches, then resend discovery data
	 * so the server picks up the updated configuration.
	 */
	public function prepareDataTagCorrelation() {
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_TAG,
			'correlation_tag' => 'type',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_TAG,
			'correlation_tag' => 'type',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify the discovered triggers reflect the updated correlation mode.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'correlation_tag', 'type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_TAG
						|| $trigger['correlation_tag'] !== 'type'
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_TRIGGER_CORRELATION_TAG, $trigger['correlation_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to tag-correlation mode.');
			$this->assertEquals('type', $trigger['correlation_tag'],
				'Discovered trigger '.$trigger['triggerid'].' has unexpected correlation tag.');
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Reconfigure both item prototypes to text value type and both trigger prototypes to use
	 * find(regexp,"down") as expression with tag-correlation on the 'service' tag, then verify
	 * that the already-discovered triggers and items reflect the updated configuration.
	 */
	public function prepareDataServiceCorrelation() {
		// Switch item prototypes to text so the find() function can be used in expressions.
		$this->call('itemprototype.update', [
			'itemid' => self::$item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('itemprototype.update', [
			'itemid' => self::$dep_item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		// Update trigger prototypes: find(regexp,"down") expression + service tag correlation.
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_TAG,
			'correlation_tag' => 'service',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}']
			]
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'dependencies' => [
				['triggerid' => self::$trigger_prototypeid]
			],
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_TAG,
			'correlation_tag' => 'service',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component_{ITEM.VALUE}', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep-dep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO]
			]
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers and items with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify the discovered items reflect the updated value type.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']);
		foreach ($response['result'] as $item) {
			$this->assertEquals(ITEM_VALUE_TYPE_TEXT, (int) $item['value_type'],
				'Discovered item '.$item['itemid'].' was not updated to text value type.');
		}

		// Verify the discovered triggers reflect the updated expression and correlation config.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'correlation_tag', 'manual_close', 'type', 'expression']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_TAG
						|| $trigger['correlation_tag'] !== 'service'
						|| (int) $trigger['manual_close'] !== ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_TRIGGER_CORRELATION_TAG, $trigger['correlation_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to tag-correlation mode.');
			$this->assertEquals('service', $trigger['correlation_tag'],
				'Discovered trigger '.$trigger['triggerid'].' has unexpected correlation tag.');
			$this->assertEquals(ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED, $trigger['manual_close'],
				'Discovered trigger '.$trigger['triggerid'].' has unexpected manual_close setting.');
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Reconfigure both item prototypes to text value type and both trigger prototypes to use
	 * find(regexp,"down") as expression with global event correlation:
	 *   - Trigger-level correlation mode = ZBX_TRIGGER_CORRELATION_NONE (trigger recovery
	 *     closes all remaining open problems at once).
	 *   - TRIGGER_MULT_EVENT_ENABLED so new PROBLEM events are generated even while the
	 *     trigger is already TRUE.
	 *   - A global event correlation rule (old event service="down", new event service="up",
	 *     operation = CLOSE_OLD) is created so that a RESOLVED event with service="up" closes
	 *     open problems whose service tag is "down", independently of trigger-level recovery.
	 */
	public function prepareDataGlobalCorrelation($evaltype = CONDITION_EVAL_TYPE_AND_OR) {
		// Switch item prototypes to text so the find() function can be used in expressions.
		$this->call('itemprototype.update', [
			'itemid' => self::$item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('itemprototype.update', [
			'itemid' => self::$dep_item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		// Update trigger prototypes: find(regexp,"down") expression + global correlation +
		// multiple event generation so multiple PROBLEM events accumulate before a single
		// recovery event closes them all at once.
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{ITEM.VALUE}']
			]
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'dependencies' => [],
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep-dep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{ITEM.VALUE}']
			]
		]);

		// Resend LLD discovery data to re-instantiate discovered triggers and items with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify the discovered items reflect the updated value type.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']);
		foreach ($response['result'] as $item) {
			$this->assertEquals(ITEM_VALUE_TYPE_TEXT, (int) $item['value_type'],
				'Discovered item '.$item['itemid'].' was not updated to text value type.');
		}

		// Verify the discovered triggers reflect global correlation mode and multiple event generation.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_NONE
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_TRIGGER_CORRELATION_NONE, $trigger['correlation_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to global correlation mode.');
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
		}

		// Start from a clean correlation slate so rules left over from other CEP scenarios (parity
		// "odd"/"even", "close on up", ...) cannot stay active during this test. The rule below is then
		// (re)created as the only CEP correlation rule.
		$this->deleteCepCorrelations();

		// Create a global event correlation rule: close old events whose 'service' tag value
		// is 'down' when a new event arrives with 'service' tag value 'up'.
		// This exercises the global-correlation path independently of trigger-level correlation.
		$conditions = [
			[
				'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				'tag' => 'service',
				'operator' => CONDITION_OPERATOR_EQUAL,
				'value' => 'down'
			],
			[
				'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
				'tag' => 'type',
				'operator' => CONDITION_OPERATOR_EQUAL,
				'value' => 'cep-dep'
			],
			[
				'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
				'oldtag' => 'component',
				'newtag' => 'component'
			]
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			// Assign a formula id to each condition and AND them together so the rule behaves
			// identically to the CONDITION_EVAL_TYPE_AND_OR variant while exercising the
			// custom expression evaluation path.
			$formulaids = ['A', 'B', 'C'];
			foreach ($conditions as $i => &$condition) {
				$condition['formulaid'] = $formulaids[$i];
			}
			unset($condition);

			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => implode(' and ', $formulaids),
				'conditions' => $conditions
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => $conditions
			];
		}

		$corr_params = [
			'name' => 'CEP global event correlation',
			'filter' => $filter,
			'operations' => [
				[
					'type' => ZBX_CORR_OPERATION_CLOSE_OLD
				],
				[
					'type' => ZBX_CORR_OPERATION_CLOSE_NEW
				]
			]
		];

		self::$correlationid = $this->upsertCorrelation($corr_params);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Reconfigure both trigger prototypes for the "close old down when new up" global correlation
	 * scenario. The key difference from prepareDataGlobalCorrelation is that the trigger expression
	 * matches both "down" and "up", so an "up_N" value is itself a PROBLEM event (not a recovery) that
	 * can drive global correlation as the new event. Each trigger carries:
	 *   - 'state'   = the leading letters of the value ("down"/"up"), the old/new discriminator;
	 *   - 'service' = the trailing number of the value ("0"/"1"), the pairing key so "up_1" correlates
	 *                 to "down_1".
	 * A single global correlation rule (old state="down" + new state="up" + service tag pair,
	 * CLOSE_OLD + CLOSE_NEW) is created; it stays silent while only "down" problems are opened and only
	 * fires once "up" problems arrive.
	 */
	public function prepareDataGlobalCorrelationCloseOnUp($evaltype = CONDITION_EVAL_TYPE_AND_OR,
			$extra_tag_via_webhook = false, $recreate_correlation = false) {
		$this->prepareCloseOnUpTriggerPrototypes();

		// Create (or update in place) the single "close old down when new up" rule. When
		// $recreate_correlation is set the existing CEP correlation rules are deleted first, so the rule is
		// built from scratch with this test's evaltype rather than updated on top of the one a previous
		// CloseOnUp variant left behind (which uses a different evaltype).
		if ($recreate_correlation) {
			$this->deleteCepCorrelations();
		}

		self::$correlationid = $this->upsertCorrelation(
			$this->buildCloseOnUpCorrelationParams('CEP global event correlation up', $evaltype)
		);

		// Optionally add an extra webhook-computed tag to every problem event.
		if ($extra_tag_via_webhook) {
			$this->createExtraTagWebhookAction();
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Reconfigure both trigger prototypes for the "close old down when new up" scenarios and resend LLD so
	 * the discovered items and triggers are re-instantiated with that configuration. Shared by the global
	 * event correlation variants (prepareDataGlobalCorrelationCloseOnUp) and by the complex event processing
	 * variant (prepareDataCepWindowTagCorrelationCloseOnUp), which only differ in which rule engine closes
	 * the problems afterwards. $extra_tags are appended to the tags of both prototypes, letting the
	 * tag-exists flavour of the CEP variant add the state-carrying tag name it matches on (CEP_STATE_TAG).
	 *
	 * Does nothing when the discovered triggers already carry this exact configuration, which is the common
	 * case: the CloseOnUp scenarios share it and every dependent test re-runs its prepareData* method, so
	 * re-sending LLD would only cost another full discovery cycle.
	 */
	private function prepareCloseOnUpTriggerPrototypes(array $extra_tags = []): void {
		// Both prototypes: find(regexp,"down|up") so "up" is a PROBLEM (not a recovery) + multiple event
		// generation + global correlation, plus a 'state' tag ("down"/"up") and a 'service' tag (the
		// trailing number) that pairs an "up_N" problem with its "down_N" problem.
		//
		// When $extra_tag_via_webhook is set, the correlation still uses the 'service' trigger tag as usual;
		// additionally a webhook media type (see createExtraTagWebhookAction) adds a separate WEB_SERVICE_TAG
		// tag to each problem event from JavaScript, so the test can verify that tags returned by a media
		// type are applied to the events they were generated for.
		$common_tags = array_merge([
			['tag' => 'component', 'value' => self::LLD_MACRO],
			['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
			['tag' => 'state', 'value' => '{{ITEM.VALUE}.regsub("^([a-z]+)", "\\1")}'],
			['tag' => 'service', 'value' => '{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}']
		], $extra_tags);

		// Nothing to re-discover when a discovered trigger already has this configuration. The tag names are
		// what tell the flavours apart, so they must match exactly: $extra_tags present when they are needed
		// and absent when they are not - a CEP_STATE_TAG left behind by the tag-exists flavour would
		// otherwise let the tag-value flavour reuse triggers carrying a tag its condition must not see.
		if ($this->hasCloseOnUpDiscoveredTrigger(array_merge(['type'], array_column($common_tags, 'tag')))) {
			return;
		}

		// Switch item prototypes to text so the find() function can be used in expressions.
		$this->call('itemprototype.update', [
			'itemid' => self::$item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('itemprototype.update', [
			'itemid' => self::$dep_item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],,"regexp","down|up")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => array_merge([['tag' => 'type', 'value' => 'cep']], $common_tags)
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],,"regexp","down|up")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'dependencies' => [],
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => array_merge([['tag' => 'type', 'value' => 'cep-dep']], $common_tags)
		]);

		// Resend LLD discovery data to re-instantiate the discovered triggers and items with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Wait for the discovered items to reflect the text value type and the discovered triggers to
		// reflect global correlation mode + multiple event generation.
		$this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});

		// Filter on the 'state' tag the updated prototype adds (the only genuinely new tag), so requiring
		// both ids back confirms the new config was applied rather than the pre-update defaults.
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'type'],
			'tags' => [['tag' => 'state', 'operator' => TAG_OPERATOR_EXISTS]]
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_NONE
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
			}
			return true;
		});

		// The wait above filters on the 'state' tag, which the prototypes already carried before this update,
		// so it cannot tell whether $extra_tags have landed on the discovered triggers yet. Wait for them
		// separately: trigger.get AND-s tag filters of distinct names, so a single trigger coming back means
		// every extra tag was applied (the tag names still hold the unresolved {ITEM.VALUE}, which is
		// substituted at event time, not at discovery time). One trigger is enough - the tags of every
		// discovered trigger come from the same two prototypes and are written by the same LLD pass.
		if ($extra_tags) {
			$this->callUntilDataIsPresent('trigger.get', [
				'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
				'output' => ['triggerid'],
				'tags' => array_map(
					fn(array $tag) => ['tag' => $tag['tag'], 'operator' => TAG_OPERATOR_EXISTS],
					$extra_tags
				)
			], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Whether at least one of the two primary discovered triggers is already configured the way
	 * prepareCloseOnUpTriggerPrototypes() would configure it: no trigger-level correlation, multiple event
	 * generation and exactly $tag_names as its tag names.
	 *
	 * One trigger is enough - the tags of every discovered trigger come from the same two prototypes and are
	 * written by the same LLD pass. The tag names alone identify the scenario: 'state' is written by no other
	 * prepareData* method, and the only call that writes it also switches the item prototypes to text value
	 * type, so a match implies the discovered items are text as well.
	 */
	private function hasCloseOnUpDiscoveredTrigger(array $tag_names): bool {
		$response = $this->call('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'type'],
			'selectTags' => 'extend'
		]);

		foreach ($response['result'] as $trigger) {
			if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_NONE
					|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
				continue;
			}

			$trigger_tag_names = array_column($trigger['tags'], 'tag');

			// No expected tag name missing and no unexpected one present.
			if (!array_diff($tag_names, $trigger_tag_names) && !array_diff($trigger_tag_names, $tag_names)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Prepare the "close old down when new up" scenario driven by the new complex event processing (CEP rule)
	 * functionality instead of a global event correlation rule. The trigger/item setup is exactly the one of
	 * prepareDataGlobalCorrelationCloseOnUp (an "up_N" value is a PROBLEM event carrying state="up" and the
	 * 'service' pairing tag); every global correlation rule is removed and the problems are closed by a single
	 * CEP rule with a tag correlation time window instead, see buildCloseOnUpCepRuleParams(). The observable
	 * behaviour is identical, so the scenario is driven by the very same runner
	 * (runEventAssessmentTestGlobalCorrelationCloseOnUp).
	 *
	 * $tag_exists_condition selects how the close-window operation singles out the "up" events: with a plain
	 * tag-exists condition, which additionally needs the state-carrying CEP_STATE_TAG tag name on both
	 * prototypes, or with a tag value comparison (state Equals "up").
	 *
	 * $extra_tag_via_webhook adds the same two tagging webhook media types the global correlation variant sets
	 * up (see createExtraTagWebhookAction), so the CEP flavours of the JS scenarios can assert that tags
	 * returned by a media type land on the events they were generated for.
	 */
	public function prepareDataCepWindowTagCorrelationCloseOnUp(bool $tag_exists_condition = true,
			bool $extra_tag_via_webhook = false) {
		return $this->prepareCloseOnUpCepRule(CCepRuleHelper::WINDOW_TAG_MATCH, self::CEP_RULE_CLOSE_ON_UP,
			$tag_exists_condition, $extra_tag_via_webhook
		);
	}

	/**
	 * Prepare the same "close old down when new up" scenario as
	 * prepareDataCepWindowTagCorrelationCloseOnUp(), driven by a cause and symptom grouping window
	 * (WINDOW_CAUSE_SYMPTOM) instead of a tag correlation one.
	 *
	 * Everything else is identical - the same trigger prototypes, the same 'service' grouping tag, the same
	 * "close window" on the "up" events plus "close" when the window closes - so a "down_N" problem still stays
	 * open in the window of its 'service' id until the matching "up_N" event closes that window, and every
	 * scenario of the tag correlation family produces exactly the same problem counts here. What the window type
	 * adds is the ranking: the "down_N" event opens the window of its id and is its cause, the "up_N" event
	 * joining that window becomes a symptom of it, and the cause counts its symptoms in the
	 * CEP_TAG_SYMPTOM_COUNT tag - see waitForCloseOnUpCauseSymptomRanking(), which asserts exactly that on the
	 * events the run generated.
	 *
	 * $tag_exists_condition and $extra_tag_via_webhook mean what they mean for the tag correlation flavour.
	 */
	public function prepareDataCepWindowCauseSymptomCloseOnUp(bool $tag_exists_condition = true,
			bool $extra_tag_via_webhook = false) {
		return $this->prepareCloseOnUpCepRule(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::CEP_RULE_CLOSE_ON_UP_CAUSE, $tag_exists_condition, $extra_tag_via_webhook
		);
	}

	/**
	 * The body both close-on-up CEP flavours share: the close-on-up trigger prototypes, a single rule of
	 * $window_type named $name closing the problems (buildCloseOnUpCepRuleParams()) and, optionally, the
	 * tagging webhook media types.
	 */
	private function prepareCloseOnUpCepRule(int $window_type, string $name, bool $tag_exists_condition,
			bool $extra_tag_via_webhook): bool {
		$this->prepareCloseOnUpTriggerPrototypes($tag_exists_condition
			? [['tag' => self::CEP_STATE_TAG, 'value' => '']]
			: []
		);

		// This rule must be the only thing closing problems in this scenario: drop the global correlation
		// rules a previous CloseOnUp variant left behind, otherwise they would close the same problems and
		// the run would pass regardless of what the CEP rule does. The rule of the other CEP flavour, which
		// matches the very same events, is gone already - every scenario removes its own rules in
		// cleanupCepRules() before the next one prepares.
		$this->deleteCepCorrelations();

		self::$cep_ruleid = $this->upsertCepRule(
			$this->buildCloseOnUpCepRuleParams($name, $tag_exists_condition, $window_type)
		);

		// Optionally add an extra webhook-computed tag to every problem event.
		if ($extra_tag_via_webhook) {
			$this->createExtraTagWebhookAction();
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the windowless complex event processing scenario. It reuses the close-on-up trigger prototypes
	 * (prepareCloseOnUpTriggerPrototypes(), so every problem event carries a 'service' tag holding the
	 * trailing number of the item value), but instead of the single tag correlation window rule of
	 * prepareDataCepWindowTagCorrelationCloseOnUp() it creates the rules of getWindowNoneRules(), which have no
	 * window at all (WINDOW_NONE) and only tag the events they match, one rule per operator. Every rule is
	 * named after the tag it adds, which in turn is named after its operator, so the rule set reads as:
	 *   - "service_equals": 'service' Equals "0";
	 *   - "service_not_equals": 'service' Does not equal "0";
	 *   - "service_contains": 'service' Contains "0";
	 *   - "service_not_contains": 'service' Does not contain "0";
	 *   - "service_more_equal": 'service' Is more than or equal "1";
	 *   - "service_less_equal": 'service' Is less than or equal "0";
	 *   - "service_exists": tag 'service_0' Exists;
	 *   - "service_not_exists": tag 'service_0' Does not exist;
	 *   - "event_name_equals": event name Equals "CEP trigger sensor1 down_1";
	 *   - "event_name_not_equals": event name Does not equal that name;
	 *   - "event_name_contains": event name Contains "down_1";
	 *   - "event_name_not_contains": event name Does not contain "down_1";
	 *   - "severity_equals": severity Equals Disaster;
	 *   - "severity_not_equals": severity Does not equal Disaster;
	 *   - "severity_more_equal": severity Is more than or equal High;
	 *   - "severity_less_equal": severity Is less than or equal Disaster;
	 *   - "host_equals": host Equals the discovered host;
	 *   - "host_not_equals": host Does not equal the discovered host;
	 *   - "host_contains": host Contains a name the discovered host does not contain;
	 *   - "host_not_contains": host Does not contain the discovered host name;
	 *   - "host_group_equals": host group Equals the discovered host's group;
	 *   - "host_group_not_equals": host group Does not equal that group;
	 *   - "host_group_contains": host group Contains a name that group does not contain;
	 *   - "host_group_not_contains": host group Does not contain that group's own name;
	 *   - "time_period_in": event time In "1-7,00:00-24:00";
	 *   - "time_period_not_in": event time Not in "1-7,00:00-24:00";
	 *   - "service_and": 'service' Contains "0" AND Does not equal "0" (CONDITION_EVAL_TYPE_AND);
	 *   - "service_or": 'service' Equals "0" OR event name Contains "down_10" (CONDITION_EVAL_TYPE_OR);
	 *   - "service_and_or": ('service' Equals "0" OR Equals "1") AND severity Equals Disaster
	 *     (CONDITION_EVAL_TYPE_AND_OR);
	 *   - "service_expression": severity Equals Disaster AND ('service' Equals "0" OR event name Contains
	 *     "down_10"), written as the custom expression "A and (B or C)" (CONDITION_EVAL_TYPE_EXPRESSION).
	 *
	 * The Exists pair tests a tag NAME rather than a value, so the prototypes additionally get the
	 * CEP_SERVICE_TAG tag whose name resolves to 'service_<id>' at event time; only the "down_0" event
	 * therefore carries a 'service_0' tag.
	 *
	 * The twelve id rules form six opposite pairs, so every problem event is tagged by exactly one rule of each
	 * pair: "down_0" by the Equals, the Contains, the Is less than or equal, the Exists, the name Does not
	 * equal and the name Does not contain rule; "down_1" by the Does not equal, the Does not contain, the Is
	 * more than or equal, the Does not exist, the name Equals and the name Contains rule; and "down_10" - an id
	 * that contains "0" without being equal to it, and whose name contains "down_1" without being equal to the
	 * "down_1" name - by the Does not equal, the Contains, the Is more than or equal, the Does not exist, the
	 * name Does not equal and the name Contains rule.
	 *
	 * The severity, host, host group and time period rules do not tell the ids apart - every event of these
	 * prototypes has the same DISASTER severity, comes from the same discovered host, which is in a single host
	 * group, and occurs inside a period covering all the time - so each of them holds either for all three
	 * events or for none, which checks the two ends of the range: "severity_equals", "severity_more_equal",
	 * "severity_less_equal", "host_equals", "host_group_equals" and "time_period_in" must tag everything,
	 * while "severity_not_equals", the non-Equals host and host group rules and "time_period_not_in" must tag
	 * nothing.
	 *
	 * The last four are the only rules whose filter holds more than one condition of its own, one per
	 * evaltype, and each of them picks a set of ids no other evaltype would produce from the same conditions:
	 * "service_and" tags "down_10" only, "service_or" tags "down_0" and "down_10", "service_and_or" tags
	 * "down_0" and "down_1", and "service_expression" tags "down_0" and "down_10" through a grouping - OR-ing
	 * two conditions of distinct types, then AND-ing a third - that no other evaltype can express.
	 *
	 * Two more rules are created beside those, neither with a condition of its own, so every problem event of
	 * the scenario goes through both:
	 *   - the "tag operations" rule runs every tag operation a windowless rule can perform - add, set, set
	 *     value, increase, decrease, rename and remove - in one operation list, see
	 *     getWindowNoneTagOperationCases(). Some of those operations work on tags the operation list adds
	 *     first, the others on tags the trigger prototypes carry for exactly that purpose;
	 *   - the "event operations" rule runs the operations changing the event itself - set name, set
	 *     severity, increase and decrease severity, suppress - see getWindowNoneEventOperationCase(). It is
	 *     the only rule with a non-zero sortorder, so it runs after all the others: it rewrites the event name
	 *     and severity the rules above have conditions on.
	 *
	 * None of the rules closes anything, which is the point of a windowless rule: the events keep flowing
	 * through untouched apart from the tags.
	 *
	 * $window_type gives every rule of the set a window of that type instead of none, and $name_infix names
	 * them after the flavour so they can exist side by side. The rule set behaves the same either way as long
	 * as the window type is not an exclusive one - see testTriggerCEP_CepWindowSimple() and
	 * testTriggerCEP_CepWindowPattern(), which run it with a simple and with a pattern match window and expect
	 * exactly what the windowless run produces. The exclusive types cannot be used for it at all: only the
	 * first matching rule of such a window type is processed for an event, so the rules after it would never
	 * tag anything.
	 */
	public function prepareDataCepWindowNoneTagOperations(?int $window_type = null,
			string $name_infix = 'none') {
		// The first extra tag carries the service id in its NAME, which is what the Exists / Does not exist
		// pair tests for; its value is irrelevant. The rest are the tags the tag operation rule modifies,
		// renames and removes, so those operations are exercised on tags the trigger itself generated and not
		// only on tags an earlier operation of the same rule added.
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// Some of the operations of the two rules below are given macros instead of the strings they act with.
		$this->prepareWindowOperationMacros();

		// Nothing except the trigger expression may close these problems - the scenario asserts they all stay
		// open - so drop the global correlation rules a previous CloseOnUp variant left behind; they would
		// close the problems this scenario opens.
		$this->deleteCepCorrelations();

		// The rules of a flavour are named after it, and the ones of a flavour that gives them a window all
		// get the same one, grouped by the 'service' tag, with its limits handed to it as user macros.
		$prefix = self::CEP_RULE_NAME_PREFIX.'window '.$name_infix.' ';
		$window = $window_type === null ? [] : $this->macroizeWindowLimits($this->buildWindowOperationsWindow());

		if ($window_type === CCepRuleHelper::WINDOW_PATTERN_MATCH) {
			// A pattern window is not allowed without a script. This one reports a match every time the
			// window is examined, which changes nothing at all: none of these rules has an operation at that
			// execution point, so there is nothing for a match to run.
			$window['script'] = "return 'true';";
		}

		foreach ($this->getWindowNoneRules() as $tag => $rule) {
			[$conditions, $operand] = $rule;
			// Only the rules combining conditions of their own carry an evaltype (and, for the custom
			// expression one, a formula); the rest are single conditions AND-ed with the guard
			// buildWindowNoneAddTagCepRuleParams() adds.
			$evaltype = isset($rule[2]) ? $rule[2] : CONDITION_EVAL_TYPE_AND;
			$formula = isset($rule[3]) ? $rule[3] : '';

			$this->upsertCepRule($this->buildWindowNoneAddTagCepRuleParams($prefix.$tag, $conditions, $tag,
				$operand, $evaltype, $formula, $window_type, $window
			));
		}

		// The two rules with more than a single operation: no condition of their own (so every problem event
		// of the scenario goes through them) and every tag operation a windowless rule can perform, then
		// every operation changing the event itself.
		$this->upsertCepRule($this->buildWindowNoneCepRuleParams($prefix.'tag operations', [],
			$this->getWindowNoneTagOperations(), CONDITION_EVAL_TYPE_AND, '', $window_type, $window
		));

		// This one changes the event name and severity, which the rules above have conditions on, so it must
		// be evaluated after all of them: rules run in sortorder and every other rule leaves it at 0.
		$event_operations = $this->getWindowNoneEventOperationCase()['operations'];
		$this->upsertCepRule(['sortorder' => 1] + $this->buildWindowNoneCepRuleParams($prefix.'event operations',
			[], $this->buildWindowNoneOperations($event_operations), CONDITION_EVAL_TYPE_AND, '', $window_type,
			$window
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the simple window flavour of the windowless scenario, see prepareDataCepWindowOperations().
	 */
	public function prepareDataCepWindowSimpleOperations() {
		return $this->prepareDataCepWindowOperations(CCepRuleHelper::WINDOW_SIMPLE, 'simple');
	}

	/**
	 * Prepare the tag correlation window flavour of the windowless scenario, see
	 * prepareDataCepWindowOperations().
	 */
	public function prepareDataCepWindowTagOperations() {
		return $this->prepareDataCepWindowOperations(CCepRuleHelper::WINDOW_TAG_MATCH, 'tag');
	}

	/**
	 * Prepare the simple window flavour that applies its operations when the event is evicted from the window
	 * rather than when it occurs, see prepareDataCepWindowOperations().
	 */
	public function prepareDataCepWindowSimpleEvictedOperations() {
		return $this->prepareDataCepWindowOperations(CCepRuleHelper::WINDOW_SIMPLE, 'simple evicted',
			CCepRuleHelper::WHEN_EVENT_EVICTED
		);
	}

	/**
	 * Prepare the tag correlation window flavour that applies its operations when the event is evicted from
	 * the window rather than when it occurs, see prepareDataCepWindowOperations().
	 */
	public function prepareDataCepWindowTagEvictedOperations() {
		return $this->prepareDataCepWindowOperations(CCepRuleHelper::WINDOW_TAG_MATCH, 'tag evicted',
			CCepRuleHelper::WHEN_EVENT_EVICTED
		);
	}

	/**
	 * Prepare the pattern match window flavour that applies its operations when the event is evicted from the
	 * window, the third and last window type whose events are evicted at all: a pattern match window evicts what
	 * its duration has outlived exactly as a simple one does, examining what is left against its script, so the
	 * operations must reach an evicted event here as well - see prepareDataCepWindowOperations().
	 */
	public function prepareDataCepWindowPatternEvictedOperations() {
		return $this->prepareDataCepWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH, 'pattern evicted',
			CCepRuleHelper::WHEN_EVENT_EVICTED
		);
	}

	/**
	 * Prepare the simple window flavour that applies its operations as the window closes rather than when the
	 * event occurs, see prepareDataCepWindowOperations().
	 */
	public function prepareDataCepWindowSimpleClosedOperations() {
		return $this->prepareDataCepWindowOperations(CCepRuleHelper::WINDOW_SIMPLE, 'simple closed',
			CCepRuleHelper::WHEN_WINDOW_CLOSED
		);
	}

	/**
	 * Prepare the tag correlation window flavour that applies its operations as the window closes, see
	 * prepareDataCepWindowOperations().
	 */
	public function prepareDataCepWindowTagClosedOperations() {
		return $this->prepareDataCepWindowOperations(CCepRuleHelper::WINDOW_TAG_MATCH, 'tag closed',
			CCepRuleHelper::WHEN_WINDOW_CLOSED
		);
	}

	/**
	 * Prepare the cause and symptom window flavour that applies its operations as the window closes, see
	 * prepareDataCepWindowOperations().
	 */
	public function prepareDataCepWindowCauseSymptomClosedOperations() {
		return $this->prepareDataCepWindowOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, 'cause closed',
			CCepRuleHelper::WHEN_WINDOW_CLOSED
		);
	}

	/**
	 * Prepare the pattern match window flavour that applies its operations as the window closes, see
	 * prepareDataCepWindowOperations().
	 */
	public function prepareDataCepWindowPatternClosedOperations() {
		return $this->prepareDataCepWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH, 'pattern closed',
			CCepRuleHelper::WHEN_WINDOW_CLOSED
		);
	}

	/**
	 * Prepare the discard scenario: one windowless rule whose only operation drops the events it matches, so
	 * they never reach the database.
	 *
	 * Discard is the one operation that has to be applied before anything is stored, and the server does
	 * exactly that - it is looked for while the rules are matched, before the event is added, and only among
	 * the operations that execute when the event occurs. An event it matches is therefore not closed or
	 * suppressed but simply gone: no problem, no event, and no trigger value change either.
	 *
	 * The operation only matches the "up" events, by a tag exists condition on CEP_STATE_TAG_UP, so the test
	 * can tell a discarded event from a kept one within the same scenario: the "down" values must still open
	 * their problems as usual.
	 */
	public function prepareDataCepDiscardUp() {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		$this->deleteCepCorrelations();

		$operations = $this->buildWindowNoneOperations([
			[CCepRuleHelper::OP_DISCARD, [
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [self::buildUpEventOperationCondition()]
				]
			]]
		]);

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams(self::CEP_RULE_DISCARD_UP, [], $operations));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the cause and symptom grouping flavour. This window type needs no operations for the grouping
	 * itself: it ranks the events of a group as they arrive - the first event of the group is the cause, and
	 * every event that arrives while it is still in the window becomes a symptom of it, pointing at it through
	 * its cause_eventid. The number of events the group has collected is written to the CEP_TAG_SYMPTOM_COUNT
	 * tag of the cause event, a tag only this window type maintains.
	 *
	 * The window groups by the 'component' tag, which every event of the driven trigger carries with the same
	 * value, so all of them form one group; it lasts longer than the test and has no capacity limit, so
	 * nothing is evicted and the group is never reset while the events are being sent.
	 *
	 * The only operations the rule has are the ones that recover the scenario, the same pair as the other
	 * windowed flavours (see buildCloseOnUpCepRuleParams()): "close window" when an event is added to the window,
	 * restricted to
	 * the "up" events by a tag exists condition on CEP_STATE_TAG_UP - a tag only an "up" event carries because
	 * its name is resolved from the item value - and "close" when the window closes, which closes every event
	 * that window held. Neither of them is reached while the "down" values are being sent, so the ranking they
	 * build is left untouched, and the "up" value at the end of the scenario closes the whole group at once: the
	 * ranked "down" problems and the "up" problem that ended their window alike, see
	 * runEventAssessmentTestCepWindowCauseSymptom().
	 *
	 * A second rule of the same window type is created beside it, matching the same events and doing nothing
	 * but adding a tag. A cause and symptom window is one of the exclusive window types, so only the first
	 * matching rule of that kind is processed for an event and that tag must never appear - the same property
	 * the tag correlation flavour checks.
	 */
	public function prepareDataCepWindowCauseSymptom() {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// Only the rule of the flavour may close these problems: the scenario asserts all of them stay open,
		// ranked but untouched, until its "up" value ends their window.
		$this->deleteCepCorrelations();

		$window = [
			'duration' => self::CEP_RULE_WINDOW_CAPACITY_DURATION,
			'capacity' => 0,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => ['component'],
			'event_count_tag' => self::CEP_TAG_SYMPTOM_COUNT
		];

		// The recovery of the scenario: the "up" event closes the window it has just entered, and the closing
		// window closes every event it held. Without the condition every event would close the window it just
		// entered, so nothing would ever be ranked.
		$operations = [
			[
				'sortorder' => 0,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_ADDED,
				'type' => CCepRuleHelper::OP_CLOSE_WINDOW,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [self::buildUpEventOperationCondition()]
				]
			],
			[
				'sortorder' => 1,
				'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED,
				'type' => CCepRuleHelper::OP_CLOSE_EVENT
			]
		];

		$name = self::CEP_RULE_NAME_PREFIX.'window cause ';

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams($name.'symptom', [], $operations,
			CONDITION_EVAL_TYPE_AND, '', CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, $window
		));

		$second_operations = $this->buildWindowNoneOperations([
			[CCepRuleHelper::OP_ADD_TAG, ['tag' => self::CEP_TAG_WINDOW_SECOND,
				'tag_value' => self::CEP_TAG_WINDOW_SECOND_VALUE
			]]
		]);

		$this->upsertCepRule(['sortorder' => 1] + $this->buildWindowNoneCepRuleParams($name.'second', [],
			$second_operations, CONDITION_EVAL_TYPE_AND, '', CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, $window
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the tag driven service scenario: a service that is in problem exactly while an event carries a
	 * tag, and a windowed rule that puts that tag on an event and takes it off again later.
	 *
	 * The service matches its problems on CEP_SERVICE_TAG_NAME, which no trigger of the suite produces - only
	 * the rule does, when the event occurs - so the service can only be brought into problem by the rule. The
	 * same rule removes the tag again when the window evicts the event, which happens once the window duration
	 * has run out, and the service has to follow that too. The problem itself stays open the whole time, so
	 * the service status can only be following the tag.
	 *
	 * The window groups by the 'service' tag, so the one event of the scenario has a window to itself.
	 */
	public function prepareDataCepServiceTag() {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// Only this rule may touch the problems and the tag the service watches.
		$this->deleteCepCorrelations();
		$this->deleteCepTagService();

		$response = $this->call('service.create', [
			'name' => self::CEP_SERVICE_NAME,
			'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL,
			'sortorder' => 0,
			'problem_tags' => [
				[
					'tag' => self::CEP_SERVICE_TAG_NAME,
					'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL,
					'value' => self::CEP_SERVICE_TAG_VALUE
				]
			]
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);
		self::$cep_tag_serviceid = $response['result']['serviceids'][0];

		$operations = [
			[
				'sortorder' => 0,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
				'type' => CCepRuleHelper::OP_ADD_TAG,
				'tag' => self::CEP_SERVICE_TAG_NAME,
				'tag_value' => self::CEP_SERVICE_TAG_VALUE
			],
			[
				'sortorder' => 1,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_EVICTED,
				'type' => CCepRuleHelper::OP_REMOVE_TAG,
				'tag' => self::CEP_SERVICE_TAG_NAME
			]
		];

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams(self::CEP_RULE_SERVICE_TAG, [], $operations,
			CONDITION_EVAL_TYPE_AND, '', CCepRuleHelper::WINDOW_SIMPLE, $this->buildWindowOperationsWindow()
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Delete the service of the tag driven service scenario so it does not react to the problems of the tests
	 * that run afterwards. Guarded, so it is safe in a finally block even if the scenario never got that far.
	 */
	private function deleteCepTagService(): void {
		if (self::$cep_tag_serviceid !== null) {
			$this->call('service.delete', [self::$cep_tag_serviceid]);
			self::$cep_tag_serviceid = null;
		}
	}

	/**
	 * Prepare the copy scenario: a simple window whose only operation copies an event when the window evicts
	 * it, which happens once the window duration has run out.
	 *
	 * The copy is a new event with the name, severity and tags of the one it was made from, so it matches the
	 * same rule and goes into the same window, where it will be evicted in its turn. Copying it again would
	 * produce another copy, and that one another - the rule would keep making events out of its own output for
	 * as long as the trigger has a problem. The operation is therefore conditioned on the event not being a copy
	 * (the "event copied" condition with the No operator), which only holds for events the trigger produced, so
	 * exactly one copy is made of each of them and nothing is made of the copies.
	 *
	 * The window groups by the 'service' tag, so each id is copied independently of the others.
	 */
	public function prepareDataCepWindowCopy() {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// Only this rule may add events; nothing may close a problem.
		$this->deleteCepCorrelations();

		// An event evicted because the window duration ran out is presented as the first event of the window,
		// so "copy first" is the operation that acts on it - "copy last" would never fire here.
		$operations = [
			[
				'sortorder' => 0,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_EVICTED,
				'type' => CCepRuleHelper::OP_CLONE_FIRST,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [[
						'type' => ZBX_CONDITION_TYPE_EVENT_COPIED,
						'operator' => CONDITION_OPERATOR_NO
					]]
				]
			]
		];

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams(self::CEP_RULE_WINDOW_COPY, [], $operations,
			CONDITION_EVAL_TYPE_AND, '', CCepRuleHelper::WINDOW_SIMPLE, $this->buildWindowOperationsWindow()
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the runaway copy scenario: a pattern window whose script matches whenever the window holds
	 * anything at all, with "copy first" as its operation.
	 *
	 * A pattern window is examined by the server once a second - never when an event arrives, only when the
	 * window comes due - and its events are not consumed by a match, so an always matching script runs the
	 * operations again at every examination. Each of those copies the oldest event of
	 * the window into a new event, which is itself matched by the rule and added to the same window - so the
	 * copies keep coming, one per examination, for as long as the rule exists. That is what
	 * runEventAssessmentTestCepWindowPatternCopyAlways() waits for and then puts a stop to by removing the
	 * rule; prepareDataCepWindowPattern() shows the other side of it, where the script refuses to match its
	 * own output and exactly one copy is made.
	 */
	public function prepareDataCepWindowPatternCopyAlways() {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// Only this rule may add events; nothing may close a problem.
		$this->deleteCepCorrelations();

		$window = [
			'duration' => self::CEP_RULE_WINDOW_CAPACITY_DURATION,
			'capacity' => 0,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => ['service'],
			'script' => "return cep_get_events().length > 0 ? 'true' : 'false';"
		];

		$operations = $this->buildWindowNoneOperations([[CCepRuleHelper::OP_CLONE_FIRST, []]],
			CCepRuleHelper::WHEN_PATTERN_MATCHED
		);

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams(self::CEP_RULE_WINDOW_COPY_ALWAYS, [],
			$operations, CONDITION_EVAL_TYPE_AND, '', CCepRuleHelper::WINDOW_PATTERN_MATCH, $window
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the event pattern match flavour: a window that hands its events to a script and acts when the
	 * script reports a match. The script is run when the window comes due, once a second, and not when an
	 * event arrives - so a match follows the value that completes the pattern, it does not accompany it.
	 *
	 * Besides deciding when to match, the script verifies what the window hands it: every event it is given
	 * must have an eventid, the severity of the trigger, a sane timestamp, the boolean lifecycle fields, a
	 * 'type' tag of "cep" and a name that agrees with its own 'service' tag. Anything else throws, which both
	 * fails the script and stops it from ever matching, and the server keeps that message on the rule where
	 * the scenario reports it from. Checking the fields inside the script is the only way to see them as the
	 * window sees them.
	 *
	 * The script matches as soon as the window holds CEP_RULE_WINDOW_PATTERN_EVENTS events that the rule did
	 * not create itself. That last part matters: the operations of this execution point copy an event, and a
	 * copy is an event of its own that the rule matches again and that lands in the same window - without the
	 * guard the pattern would match again, copy again, and never stop. The copies are recognised by the
	 * is_copied property the script sees on them.
	 *
	 * The operations are "copy first" and "copy last". They run for every event of the window but each one acts
	 * only on the event at its end of it, so a match leaves two new events behind: a copy of the oldest and a
	 * copy of the newest. The other operations a pattern match may perform are discarding an event and closing
	 * the window, which prepareDataCepWindowPatternCloseWindow() covers.
	 *
	 * The window is ended by the "up" value the scenario finishes with rather than left to outlive the test: an
	 * arriving event is taken into the window before the operations of its occurrence are performed, so the "up"
	 * event is one of the events its own close window operation ends the window with. What the closing does is
	 * add CEP_TAG_WINDOW_PATTERN_CLOSED to every event the window held - the three values that were sent, the two
	 * copies the match made and the "up" event itself - which is what shows the window ended and what was in it,
	 * closing being otherwise invisible in a flavour whose operations only copy.
	 *
	 * The window groups by the 'component' tag, so all the events of the driven trigger form one group, and it
	 * outlasts the test with no capacity limit so nothing is evicted while the pattern is being collected.
	 */
	public function prepareDataCepWindowPattern() {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// Only the copies may change what is open; nothing else may close a problem.
		$this->deleteCepCorrelations();

		$events_num = self::CEP_RULE_WINDOW_PATTERN_EVENTS;
		$name_prefix = 'CEP trigger '.self::COMPONENT_VALUE.' ';
		$severity = TRIGGER_SEVERITY_DISASTER;

		// Besides counting the events the script checks what the window handed it, so the fields an event is
		// exposed with are verified inside the server rather than through the API afterwards. A field that is
		// not what it should be throws, which fails the script, and the rule keeps that message - see
		// getCepRuleError(), which the scenario reports if the match never happens.
		//
		// The name is built from the 'state' and 'service' tags rather than from the "down" values alone: the
		// "up" value that ends the window is taken into it before the close window operation of its occurrence
		// runs, so an examination that lands in between is handed an "up" event too and must recognise it.
		//
		// The lifecycle fields are only checked for their type, not their value: the script keeps running
		// after the scenario has recovered the problems, and an event that is no longer open would then throw
		// for no good reason.
		$script = <<<HEREDOC
var events = cep_get_events(), originals = 0, copies = 0;

for (var i = 0; i < events.length; i++) {
	var event = events[i], service = null, state = null, type = null;

	if (event.is_copied) {
		copies++;
		continue;
	}

	originals++;

	if (!(event.eventid > 0)) {
		throw 'event at ' + i + ' has no eventid';
	}

	if (event.severity !== $severity) {
		throw 'event ' + event.eventid + ' severity ' + event.severity + ', expected $severity';
	}

	if (!(event.clock > 0) || !(event.ns >= 0)) {
		throw 'event ' + event.eventid + ' clock ' + event.clock + '.' + event.ns;
	}

	if (typeof event.is_open !== 'boolean' || event.is_suppressed !== false || event.is_symptom !== false) {
		throw 'event ' + event.eventid + ' is_open ' + event.is_open + ', is_suppressed ' +
				event.is_suppressed + ', is_symptom ' + event.is_symptom;
	}

	for (var j = 0; j < event.tags.length; j++) {
		if (event.tags[j].tag === 'service') {
			service = event.tags[j].value;
		}
		else if (event.tags[j].tag === 'state') {
			state = event.tags[j].value;
		}
		else if (event.tags[j].tag === 'type') {
			type = event.tags[j].value;
		}
	}

	if (type !== 'cep') {
		throw 'event ' + event.eventid + ' type tag ' + type + ', expected cep';
	}

	if (event.name !== '$name_prefix' + state + '_' + service) {
		throw 'event ' + event.eventid + ' name "' + event.name + '" does not match its state tag ' + state +
				' and service tag ' + service;
	}
}

return (originals >= $events_num && copies === 0) ? 'true' : 'false';
HEREDOC;

		$window = [
			'duration' => self::CEP_RULE_WINDOW_CAPACITY_DURATION,
			'capacity' => 0,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => ['component'],
			'script' => $script
		];

		$operations = $this->buildWindowNoneOperations([
			[CCepRuleHelper::OP_CLONE_FIRST, []],
			[CCepRuleHelper::OP_CLONE_LAST, []]
		], CCepRuleHelper::WHEN_PATTERN_MATCHED);

		// The "up" value ends the window. Every event that occurs reaches this execution point, so the condition
		// on CEP_STATE_TAG_UP is what singles that value out - without it the first "down" value would close the
		// window it just opened and the pattern would never be collected at all.
		$operations[] = [
			'sortorder' => count($operations),
			'execute_when' => CCepRuleHelper::WHEN_EVENT_ADDED,
			'type' => CCepRuleHelper::OP_CLOSE_WINDOW,
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [self::buildUpEventOperationCondition()]
			]
		];

		// Closing a window runs the operations of this execution point over every event it held, and tagging them
		// is what leaves a trace of it: the events the values opened and the copies the match made must all come
		// out carrying the tag.
		$operations[] = [
			'sortorder' => count($operations),
			'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED,
			'type' => CCepRuleHelper::OP_ADD_TAG,
			'tag' => self::CEP_TAG_WINDOW_PATTERN_CLOSED,
			'tag_value' => self::CEP_TAG_WINDOW_PATTERN_CLOSED_VALUE
		];

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams(self::CEP_RULE_WINDOW_PATTERN, [], $operations,
			CONDITION_EVAL_TYPE_AND, '', CCepRuleHelper::WINDOW_PATTERN_MATCH, $window
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * The name of the rule of the single id variant of the $name flavour, which drives one id - and therefore one
	 * window holding the events of every discovered trigger - instead of one window per id. The scenario needs the
	 * name in two places - the rule is created under it and the assessment reports the errors of that rule - so it is
	 * built here rather than spelled out twice, see prepareDataCepWindowCloseWindowOperations().
	 */
	private static function buildSingleServiceRuleName(string $name): string {
		return $name.self::CEP_RULE_WINDOW_CLOSE_SINGLE_SUFFIX;
	}

	/**
	 * The name of the second rule of the $name flavour, the one a doubled flavour puts beside the first so that two
	 * rules hold and close a window of the same events at once. The scenario needs it in two places as the name above
	 * is needed - the rule is created under it and the assessment reports its errors - see
	 * prepareDataCepWindowCloseWindowOperations().
	 */
	private static function buildSecondRuleName(string $name): string {
		return $name.self::CEP_RULE_WINDOW_CLOSE_SECOND_SUFFIX;
	}

	/**
	 * Prepare the pattern match flavour of the close window scenario, whose close window operation is performed
	 * on a pattern match, see prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowPatternCloseWindow() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_CLOSE, CCepRuleHelper::WHEN_PATTERN_MATCHED
		);
	}

	/**
	 * Prepare the flavour of the close window scenario that has a pattern match window but performs its close
	 * window operation when an "up" event is added to it, so the window of that window type is ended by an arriving
	 * event and not by its own script, see prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowPatternCloseWindowOnEvent() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVENT, CCepRuleHelper::WHEN_EVENT_ADDED
		);
	}

	/**
	 * Prepare the flavour of the close window scenario that has a pattern match window and performs its close
	 * window operation when an "up" event is evicted, so the window is ended by an event that never entered it,
	 * see prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowPatternCloseWindowOnEvicted() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVICTED, CCepRuleHelper::WHEN_EVENT_EVICTED
		);
	}

	/**
	 * Prepare the simple window flavour of the close window scenario, whose close window operation is performed
	 * when an "up" event is added to it. A simple window has no matching of its own, so this and the eviction
	 * flavour
	 * below are the only execution points it can be closed from, see
	 * prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowSimpleCloseWindow() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::CEP_RULE_WINDOW_SIMPLE_CLOSE, CCepRuleHelper::WHEN_EVENT_ADDED
		);
	}

	/**
	 * Prepare the simple window flavour of the close window scenario that performs its close window operation
	 * when an "up" event is evicted, so the window is ended by an event that never entered it, see
	 * prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowSimpleCloseWindowOnEvicted() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::CEP_RULE_WINDOW_SIMPLE_CLOSE_EVICTED, CCepRuleHelper::WHEN_EVENT_EVICTED
		);
	}

	/**
	 * Prepare the tag correlation flavour of the close window scenario, whose close window operation is
	 * performed when an "up" event is added to it, see prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowTagCloseWindow() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			self::CEP_RULE_WINDOW_TAG_CLOSE, CCepRuleHelper::WHEN_EVENT_ADDED
		);
	}

	/**
	 * Prepare the tag correlation flavour of the close window scenario that performs its close window operation
	 * when an "up" event is evicted, so the window is ended by an event that never entered it, see
	 * prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowTagCloseWindowOnEvicted() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			self::CEP_RULE_WINDOW_TAG_CLOSE_EVICTED, CCepRuleHelper::WHEN_EVENT_EVICTED
		);
	}

	/**
	 * Prepare the cause and symptom flavour of the close window scenario, whose close window operation is
	 * performed when an "up" event is added to it - and singles that event out by the rank its window has just
	 * given it
	 * (the "event symptom" operation condition) instead of by a tag the event carries, which is something only
	 * this window type can be asked, see prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowCauseSymptomCloseWindow() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::CEP_RULE_WINDOW_CAUSE_CLOSE, CCepRuleHelper::WHEN_EVENT_ADDED
		);
	}

	/**
	 * Prepare the cause and symptom flavour of the close window scenario that performs its close window operation
	 * when an "up" event is evicted. An evicted event was never taken into the window, so it was never ranked
	 * either and this flavour is back to the tag condition of the other window types, see
	 * prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowCauseSymptomCloseWindowOnEvicted() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::CEP_RULE_WINDOW_CAUSE_CLOSE_EVICTED, CCepRuleHelper::WHEN_EVENT_EVICTED
		);
	}

	/**
	 * Prepare the pattern match flavour of the close window scenario with the "down" values of one id discarded as
	 * they occur, so the window of that id is never given the event the script would have found beside the "up" one,
	 * see prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowPatternCloseWindowDiscardDown() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_CLOSE_DISCARD, CCepRuleHelper::WHEN_PATTERN_MATCHED, true
		);
	}

	/**
	 * The same pattern match flavour with the discarded id named by the value of the plain 'service' tag rather than
	 * by the name of the per-id one, which is the only operation condition of the whole scenario that compares a tag
	 * value instead of a tag name. Nothing else about it differs from
	 * prepareDataCepWindowPatternCloseWindowDiscardDown(), so it asserts what that one asserts, see
	 * prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowPatternCloseWindowDiscardDownTagValue() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_CLOSE_DISCARD_TAG_VALUE, CCepRuleHelper::WHEN_PATTERN_MATCHED, true,
			false, false, true
		);
	}

	/**
	 * Prepare the simple window flavour of the close window scenario with the "down" values of one id discarded as
	 * they occur, so the window of that id never has the problem that an ending window would have closed, see
	 * prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowSimpleCloseWindowDiscardDown() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::CEP_RULE_WINDOW_SIMPLE_CLOSE_DISCARD, CCepRuleHelper::WHEN_EVENT_ADDED, true
		);
	}

	/**
	 * Prepare the tag correlation flavour of the close window scenario with the "down" values of one id discarded as
	 * they occur, see prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowTagCloseWindowDiscardDown() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			self::CEP_RULE_WINDOW_TAG_CLOSE_DISCARD, CCepRuleHelper::WHEN_EVENT_ADDED, true
		);
	}

	/**
	 * Prepare the cause and symptom flavour of the close window scenario with the "down" values of one id discarded
	 * as they occur: the discarded event is not taken into the window, so it never becomes the cause the "up" event
	 * of that id would have been ranked a symptom of, see prepareDataCepWindowCloseWindowOperations().
	 */
	public function prepareDataCepWindowCauseSymptomCloseWindowDiscardDown() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::CEP_RULE_WINDOW_CAUSE_CLOSE_DISCARD, CCepRuleHelper::WHEN_EVENT_ADDED, true
		);
	}

	/**
	 * The single id variants of every close window flavour that has one: the rule of the flavour is built exactly as
	 * the flavour builds it, only over a single id - one window holding the events of every discovered trigger instead
	 * of one window per id, sized for that (getCloseWindowDuration() and getCloseWindowCapacity()) and named after the
	 * flavour it varies (buildSingleServiceRuleName()). What ends that one window is what the flavour it varies ends
	 * its windows with, so between them the depth of a window is tried from every window type and every execution
	 * point a window may be closed from, see prepareDataCepWindowCloseWindowOperations() and
	 * runEventAssessmentTestCepWindowCloseWindow().
	 *
	 * The discarding flavours have no single id variant: dropping the values of the only id there is leaves nothing
	 * for the ids around it to be compared against, which is the whole of what a discard says here.
	 */
	public function prepareDataCepWindowPatternCloseWindowSingleService() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_PATTERN_CLOSE),
			CCepRuleHelper::WHEN_PATTERN_MATCHED, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowSingleService()
	 */
	public function prepareDataCepWindowPatternCloseWindowOnEventSingleService() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVENT),
			CCepRuleHelper::WHEN_EVENT_ADDED, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowSingleService()
	 */
	public function prepareDataCepWindowPatternCloseWindowOnEvictedSingleService() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVICTED),
			CCepRuleHelper::WHEN_EVENT_EVICTED, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowSingleService()
	 */
	public function prepareDataCepWindowSimpleCloseWindowSingleService() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_SIMPLE_CLOSE),
			CCepRuleHelper::WHEN_EVENT_ADDED, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowSingleService()
	 */
	public function prepareDataCepWindowSimpleCloseWindowOnEvictedSingleService() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_SIMPLE_CLOSE_EVICTED),
			CCepRuleHelper::WHEN_EVENT_EVICTED, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowSingleService()
	 */
	public function prepareDataCepWindowTagCloseWindowSingleService() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_TAG_CLOSE),
			CCepRuleHelper::WHEN_EVENT_ADDED, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowSingleService()
	 */
	public function prepareDataCepWindowTagCloseWindowOnEvictedSingleService() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_TAG_CLOSE_EVICTED),
			CCepRuleHelper::WHEN_EVENT_EVICTED, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowSingleService()
	 */
	public function prepareDataCepWindowCauseSymptomCloseWindowSingleService() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_CAUSE_CLOSE),
			CCepRuleHelper::WHEN_EVENT_ADDED, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowSingleService()
	 */
	public function prepareDataCepWindowCauseSymptomCloseWindowOnEvictedSingleService() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_CAUSE_CLOSE_EVICTED),
			CCepRuleHelper::WHEN_EVENT_EVICTED, false, true
		);
	}

	/**
	 * The doubled variants of every close window flavour that may have one: the rule of the flavour is created twice -
	 * once under its own name and once under buildSecondRuleName() - so two rules of the same window type keep a window
	 * of the same events at once and both of them close it when it ends, see
	 * prepareDataCepWindowCloseWindowOperations() and runEventAssessmentTestCepWindowCloseWindow().
	 *
	 * Only the simple and the pattern match flavours are doubled. A tag correlation and a cause and symptom window are
	 * the exclusive window types - only the first matching rule of such a type is processed for an event (CEP_WINDOW_UNIQ
	 * in cep_event_process_rules()) - so their second rule would never be given the event the first one took, which is
	 * asserted where it belongs, in the operations scenario (see prepareDataCepWindowOperations()). The discarding
	 * flavours are left out as well: a discarded event is dropped while the rules are matched and reaches neither rule's
	 * window, so doubling the rule adds nothing to what a discard says.
	 */
	public function prepareDataCepWindowPatternCloseWindowDoubleRule() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_CLOSE, CCepRuleHelper::WHEN_PATTERN_MATCHED, false, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowDoubleRule()
	 */
	public function prepareDataCepWindowPatternCloseWindowOnEventDoubleRule() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVENT, CCepRuleHelper::WHEN_EVENT_ADDED, false, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowDoubleRule()
	 */
	public function prepareDataCepWindowPatternCloseWindowOnEvictedDoubleRule() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVICTED, CCepRuleHelper::WHEN_EVENT_EVICTED, false, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowDoubleRule()
	 */
	public function prepareDataCepWindowSimpleCloseWindowDoubleRule() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::CEP_RULE_WINDOW_SIMPLE_CLOSE, CCepRuleHelper::WHEN_EVENT_ADDED, false, false, true
		);
	}

	/**
	 * @see prepareDataCepWindowPatternCloseWindowDoubleRule()
	 */
	public function prepareDataCepWindowSimpleCloseWindowOnEvictedDoubleRule() {
		return $this->prepareDataCepWindowCloseWindowOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::CEP_RULE_WINDOW_SIMPLE_CLOSE_EVICTED, CCepRuleHelper::WHEN_EVENT_EVICTED, false, false, true
		);
	}

	/**
	 * Prepare the close window scenario: a window per id that is ended by the rule as soon as that id has
	 * recovered, which closes the events the window held. The window groups by the 'service' tag, so every id
	 * gets a window of its own and outlasts the whole scenario (getCloseWindowDuration()), so what a window
	 * holds is exactly the events of its id and only the operations may end it. How many ids the scenario drives
	 * - and therefore how many windows the rule keeps at once - is CEP_CLOSE_WINDOW_SERVICE_COUNT, see
	 * getCloseWindowServices(); how many "down" values each of them is sent, and therefore how many events a window
	 * holds when it ends, is CEP_CLOSE_WINDOW_EVENT_COUNT, see getCloseWindowEventCount().
	 *
	 * What differs between the flavours is $execute_when, the execution point the close window operation is
	 * performed at, and therefore what decides that the id has recovered:
	 *   - WHEN_PATTERN_MATCHED, which only a WINDOW_PATTERN_MATCH window has: the script of the window reports a
	 *     match as soon as the window it is given holds an "up" event, so the operation is not caused by an event
	 *     at all - a pattern window is examined once a second, so the window is closed at the first examination
	 *     after the "up" event landed in it;
	 *   - WHEN_EVENT_ADDED, which every window type has: the operation is performed by the "up" event itself,
	 *     restricted to those events by its condition. The new event is already in the window when the operation
	 *     runs, so it closes the window it has just entered - immediately, without waiting for the window to be
	 *     examined, which is what tells this flavour from the one above when both have the same window type;
	 *   - WHEN_EVENT_EVICTED, which is reached by an event that does not fit into its window. This flavour is the
	 *     one that needs a capacity limit, and it has room for exactly the "down" events of an id
	 *     (getCloseWindowCapacity()): they take every place of its window, so the "up" event of that id finds them
	 *     all taken and is evicted - without ever entering the window it ends. Being evicted rather than held is
	 *     also why this flavour needs the extra "close" operation below.
	 *
	 * $window_type decides which of them the rule may use: a WINDOW_SIMPLE window does nothing of its own, so the
	 * two event driven execution points are all it has; a WINDOW_TAG_MATCH window correlates the events of a group
	 * and a WINDOW_CAUSE_SYMPTOM window ranks them, without that giving either of them another execution point;
	 * and a WINDOW_PATTERN_MATCH window additionally has the pattern match execution point.
	 *
	 * However the operation is reached, an "up" event is recognised by the presence of a CEP_STATE_TAG_UP tag, a
	 * tag only an "up" event carries because its name is resolved from the item value - the pattern match
	 * execution point looks for it among the tags the window hands its script, the event driven ones with a
	 * tag-exists operation condition. The one flavour that recognises it differently is the arrival flavour of a
	 * cause and symptom window, which asks the window about the event instead of the event about itself, see
	 * $close_window_condition below.
	 *
	 * A pattern match window cannot be without a script, so the flavours that do not close the window on a match
	 * get one that never reports one: the events of such a window are only ever acted on by the operation of the
	 * arriving or evicted event, exactly as in the window types that have no script at all.
	 *
	 * The last operation of every flavour closes the events the window held, at WHEN_WINDOW_CLOSED - the execution
	 * point a closing window reaches for every event it held, and the only one it reaches: an event a window lets go
	 * of because it is closing is not evicted, so the eviction execution point is not reached by it (see
	 * cep_window_close(), which performs the window closed operations of the events it pops and nothing else). Which
	 * events those are is the one thing the flavours do not share: the arrival and pattern match flavours hold every
	 * event of the id, so this closes its "down" problems and the "up" problem that ended it, while the eviction
	 * flavours never held the "up" event, so it only closes the "down" problems - their "up" problem is closed by an
	 * extra "close" operation of the eviction execution point instead, which leaves every flavour with the same closed
	 * problems per id.
	 *
	 * The limits of the window - its duration and its capacity - are given to it as the user macros
	 * CEP_WINDOW_DURATION_MACRO and CEP_WINDOW_CAPACITY_MACRO rather than as the values themselves, whatever the
	 * window type is: they are the only window parameters that may hold a macro, and the server resolves them anew
	 * whenever it works on a window. A macro that does not resolve leaves the window with a zero duration, so it
	 * would hold nothing at all - and then not one of the assertions of this scenario could hold, which is what
	 * makes the resolving as much a part of it as the closing. What the macros are set to is what the flavour
	 * would have put into the window directly, see macroizeWindowLimits().
	 *
	 * The ids that have not been sent an "up" value are untouched by any of this, so their window is not closed
	 * and their problems stay open - see runEventAssessmentTestCepWindowCloseWindow().
	 *
	 * $discard_down adds one operation on top of all that: the "down" values of a single id
	 * (getCloseWindowDiscardService()) are discarded as they occur, all of them however many it is sent. Everything
	 * the flavour does is left in place - the other ids fill their windows and their "up" values still end them - so
	 * what the scenario compares is an id whose events were dropped against the ids around it: a dropped event never
	 * took a place in a window, so there is nothing for the ending window of that id to close, and it left no problem
	 * of its own either. That is the one thing a discard can do to a window that closes: not stop it, but empty it.
	 *
	 * $discard_by_tag_value only changes how that one operation names the id it drops - by the value of the plain
	 * 'service' tag rather than by the name of the per-id one - so it says exactly what $discard_down says and is a
	 * flavour of its own for the sake of the condition type alone, see
	 * prepareDataCepWindowPatternCloseWindowDiscardDownTagValue(). It means nothing without $discard_down.
	 *
	 * $single_service leaves the rule with a single window instead of one per id: the scenario then drives one id only
	 * and sends it a value per discovered trigger (LLD_DISCOVERY_COUNT) rather than CEP_CLOSE_WINDOW_EVENT_COUNT values,
	 * so both limits of the window are sized from those instead - the duration from the one id the flavour drives and
	 * the capacity, for an eviction flavour, from how deep that one window goes. Nothing else about the rule changes,
	 * which is what lets the same assessment drive it.
	 *
	 * $second_rule creates the whole rule a second time under buildSecondRuleName(), so two rules of the same window
	 * type keep a window of the same events at once and both of them close it when it ends. Only the window types that
	 * are not exclusive can be doubled - of a tag correlation and a cause and symptom window only the first matching
	 * rule is ever processed for an event (CEP_WINDOW_UNIQ in cep_event_process_rules()), so a second rule of those
	 * types would never see the event the first one took. The two rules differ in one operation only: each adds a tag
	 * of its own as an event occurs, CEP_TAG_WINDOW_FIRST and CEP_TAG_WINDOW_SECOND, which is what the assessment
	 * reads to know that both of them were processed for the very events their windows closed - an event carrying only
	 * one of the two would be an event one rule never got its turn for. Closing a problem twice may not do anything
	 * more than closing it once, so the doubled flavours assert what the single ones do.
	 */
	private function prepareDataCepWindowCloseWindowOperations(int $window_type, string $name, int $execute_when,
			bool $discard_down = false, bool $single_service = false, bool $second_rule = false,
			bool $discard_by_tag_value = false) {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// The rule of this flavour is the only thing that may close a problem.
		$this->deleteCepCorrelations();

		// Both limits reach the window as user macros rather than as the values written here, so what the rule
		// stores are the macro names and these are only what the macros are set to, see macroizeWindowLimits().
		$window = $this->macroizeWindowLimits([
			'duration' => static::getCloseWindowDuration($single_service),
			// Only the eviction flavour needs an event not to fit; the others must hold everything they are given.
			'capacity' => $execute_when == CCepRuleHelper::WHEN_EVENT_EVICTED
				? static::getCloseWindowCapacity($single_service)
				: 0,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => ['service']
		]);

		if ($window_type === CCepRuleHelper::WINDOW_CAUSE_SYMPTOM) {
			// A window may be asked to keep the number of events it has collected in a tag of the event that opened it,
			// and a cause and symptom window is the only type that may be (the API allows event_count_tag for no other,
			// and the server maintains it in no other - see cep_window_causal_process_event()), so the causal flavours
			// of this family are given it as well: what those counts have to be once the windows are filled is part of
			// what the scenario asserts, see runEventAssessmentTestCepWindowCloseWindow().
			$window['event_count_tag'] = self::CEP_TAG_SYMPTOM_COUNT;
		}

		if ($window_type === CCepRuleHelper::WINDOW_PATTERN_MATCH) {
			if ($execute_when == CCepRuleHelper::WHEN_PATTERN_MATCHED) {
				$state_tag_up = self::CEP_STATE_TAG_UP;

				// The window is handed the events of one id, so finding an "up" event among them means that id
				// has recovered. Only the tag name is looked at, not its value - see CEP_STATE_TAG.
				$window['script'] = <<<HEREDOC
var events = cep_get_events();

for (var i = 0; i < events.length; i++) {
	for (var j = 0; j < events[i].tags.length; j++) {
		if (events[i].tags[j].tag === '$state_tag_up') {
			return 'true';
		}
	}
}

return 'false';
HEREDOC;
			}
			else {
				// A pattern match window is required to have a script even when the match is not what ends it, so
				// this one reports no match however many events it is given.
				$window['script'] = "return 'false';";
			}
		}

		// Restricts an operation to the "up" events. The pattern match execution point is only reached once the
		// script has found such an event, but every event that occurs or is evicted reaches the other two, so
		// there the condition of the operation is what singles them out - without it every event would close the
		// window it just entered, and every problem would be closed the moment it opened.
		$up_condition = [
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [self::buildUpEventOperationCondition()]
			]
		];

		// A cause and symptom window ranks an event as it takes it in - the first event of a group is its cause
		// and every later one a symptom of it - so its arrival flavour can single out the event that ends the
		// window by that rank as well: the first "down" event of an id is the cause of its window and the "up"
		// event that follows it a symptom, and being a symptom is what the operation acts on. The ranking is done
		// before the operations of the arriving event are performed, so the condition sees the rank of the very
		// event that caused it.
		//
		// The rank alone only identifies the "up" event while a window holds a single "down" event: with more of
		// them (getCloseWindowEventCount()) every "down" event after the first is a symptom too, and the window
		// would end on the second value of its id instead of on its recovery. The tag condition of the other
		// flavours is then AND-ed to it, which leaves the rank just as much a part of what the operation needs -
		// an "up" event its window failed to rank a symptom still reaches nothing and closes no window.
		//
		// An evicted event was never taken into a window and therefore never ranked, so the eviction flavour of
		// this window type keeps the tag condition of the others alone.
		$close_window_condition = $window_type === CCepRuleHelper::WINDOW_CAUSE_SYMPTOM
				&& $execute_when == CCepRuleHelper::WHEN_EVENT_ADDED
			? [
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => array_merge(
						[[
							'type' => ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
							'operator' => CONDITION_OPERATOR_YES
						]],
						static::getCloseWindowEventCount($single_service) > 1
							? $up_condition['filter']['conditions']
							: []
					)
				]
			]
			: $up_condition;

		$operations = [];

		if ($execute_when == CCepRuleHelper::WHEN_EVENT_EVICTED) {
			// The evicted event is not in the window it ends, so the "close" operation below is not applied to it:
			// this is what closes it, leaving this flavour with the same two closed problems per id as the ones
			// whose window held both events.
			$operations[] = [
				'sortorder' => 0,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_EVICTED,
				'type' => CCepRuleHelper::OP_CLOSE_EVENT
			] + $up_condition;
		}

		$close_window = [
			'sortorder' => 1,
			'execute_when' => $execute_when,
			'type' => CCepRuleHelper::OP_CLOSE_WINDOW
		];

		$operations[] = $execute_when == CCepRuleHelper::WHEN_PATTERN_MATCHED
			? $close_window
			: $close_window + $close_window_condition;

		$operations[] = [
			'sortorder' => 2,
			'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED,
			'type' => CCepRuleHelper::OP_CLOSE_EVENT
		];

		if ($discard_down) {
			// Discarding is decided while the rules are matched, before the event is stored and before any window is
			// given it, so a discarded event never takes a place in a window, is never ranked by one and is never
			// closed with one - it leaves nothing at all behind. What is discarded here are the "down" values of a
			// single id (getCloseWindowDiscardService()), so the ids around it keep filling their windows and
			// the "up" values still end them: the operations above are left to do their work, and the one id whose
			// event was dropped is what shows the difference. Conditions on distinct tags are AND-ed, so this
			// matches an event that is both a "down" one and of that id.
			//
			// Both halves of that are tag NAME conditions by default: the state is carried by the name of the
			// CEP_STATE_TAG_DOWN tag and the id by the name of the per-id CEP_SERVICE_TAG one, exactly as the
			// windowless flavours single an id out (buildWindowNoneServiceCondition()). What the server groups
			// AND/OR conditions by is the type and the tag name together (cep_operation_condition_match_key()), so
			// two conditions of the same type on different tag names are AND-ed just as differing types would be.
			//
			// $discard_by_tag_value asks the id half to compare the value of the plain 'service' tag instead, which
			// is the same discard expressed the other way round - see
			// prepareDataCepWindowPatternCloseWindowDiscardDownTagValue() for why that flavour is kept apart.
			$discarded_service_condition = $discard_by_tag_value
				? [
					'type' => CCepRuleHelper::CONDITION_TAG_VALUE,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'tag' => 'service',
					'value' => static::getCloseWindowDiscardService()
				]
				: [
					'type' => CCepRuleHelper::CONDITION_TAG,
					'operator' => CONDITION_OPERATOR_EXISTS,
					'tag' => self::CEP_SERVICE_TAG_PREFIX.static::getCloseWindowDiscardService()
				];

			array_unshift($operations, [
				'sortorder' => -1,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
				'type' => CCepRuleHelper::OP_DISCARD,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [
						[
							'type' => CCepRuleHelper::CONDITION_TAG,
							'operator' => CONDITION_OPERATOR_EXISTS,
							'tag' => self::CEP_STATE_TAG_DOWN
						],
						$discarded_service_condition
					]
				]
			]);
		}

		$second_operations = null;

		if ($second_rule) {
			// A doubled flavour has both of its rules tag the events they are processed for, each with a tag of its
			// own, so which of them acted on an event is something the event itself says: an event carrying only one
			// of the two would be an event one of the rules never got its turn for, which is what an exclusive window
			// type does to the second rule of its kind and what these flavours must show is not happening. The tag is
			// added as the event occurs, before either window has done anything with it, so what the windows
			// themselves did is still read from the problems they closed.
			$build_tag_operation = fn(string $tag, string $tag_value) => [
				'sortorder' => 3,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
				'type' => CCepRuleHelper::OP_ADD_TAG,
				'tag' => $tag,
				'tag_value' => $tag_value
			];

			// The second rule is the first one operation for operation, its own tag apart, so both of them keep a
			// window of the same events and both close it when it ends.
			$second_operations = array_merge($operations, [
				$build_tag_operation(self::CEP_TAG_WINDOW_SECOND, self::CEP_TAG_WINDOW_SECOND_VALUE)
			]);

			$operations[] = $build_tag_operation(self::CEP_TAG_WINDOW_FIRST, self::CEP_TAG_WINDOW_FIRST_VALUE);
		}

		$cep_ruleid = $this->upsertCepRule($this->buildWindowNoneCepRuleParams($name, [], $operations,
			CONDITION_EVAL_TYPE_AND, '', $window_type, $window
		));

		// The limits must have been stored as the macros themselves: were they resolved before reaching the
		// database, the scenario would be running on plain values again and would say nothing about macros at all.
		$stored = $this->call('ceprule.get', [
			'cep_ruleids' => $cep_ruleid,
			'output' => ['cep_ruleid'],
			'selectWindow' => ['duration', 'capacity']
		]);
		$this->assertCount(1, $stored['result'], 'The rule of "'.$name.'" must exist after it was created.');
		$this->assertSame(self::CEP_WINDOW_DURATION_MACRO, $stored['result'][0]['window']['duration'],
			'The window of "'.$name.'" must keep its duration macro unresolved.'
		);
		$this->assertSame(self::CEP_WINDOW_CAPACITY_MACRO, $stored['result'][0]['window']['capacity'],
			'The window of "'.$name.'" must keep its capacity macro unresolved.'
		);

		if ($second_operations !== null) {
			// The second rule of a doubled flavour, built above: the rule of the flavour once more, window and
			// operations alike, so two windows of the same events exist at once and both of them are closed by what
			// ends them. A higher rule sortorder puts it second of the two, so which of them acts first is fixed
			// rather than left to the order the rules happen to be stored in.
			$this->upsertCepRule(['sortorder' => 1] + $this->buildWindowNoneCepRuleParams(
				self::buildSecondRuleName($name), [], $second_operations, CONDITION_EVAL_TYPE_AND, '', $window_type,
				$window
			));
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the simple window flavour of the reset scenario, see prepareDataCepWindowHeldProblemsOperations().
	 */
	public function prepareDataCepWindowSimpleReset() {
		return $this->prepareDataCepWindowHeldProblemsOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::CEP_RULE_WINDOW_SIMPLE_RESET
		);
	}

	/**
	 * Prepare the tag correlation flavour of the reset scenario, see
	 * prepareDataCepWindowHeldProblemsOperations().
	 */
	public function prepareDataCepWindowTagReset() {
		return $this->prepareDataCepWindowHeldProblemsOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			self::CEP_RULE_WINDOW_TAG_RESET
		);
	}

	/**
	 * Prepare the cause and symptom flavour of the reset scenario, see
	 * prepareDataCepWindowHeldProblemsOperations().
	 */
	public function prepareDataCepWindowCauseSymptomReset() {
		return $this->prepareDataCepWindowHeldProblemsOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::CEP_RULE_WINDOW_CAUSE_RESET
		);
	}

	/**
	 * Prepare the pattern match flavour of the reset scenario, see prepareDataCepWindowHeldProblemsOperations().
	 */
	public function prepareDataCepWindowPatternReset() {
		return $this->prepareDataCepWindowHeldProblemsOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_RESET
		);
	}

	/**
	 * Prepare the simple window flavour of the delete scenario, see prepareDataCepWindowHeldProblemsOperations().
	 */
	public function prepareDataCepWindowSimpleDelete() {
		return $this->prepareDataCepWindowHeldProblemsOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::CEP_RULE_WINDOW_SIMPLE_DELETE
		);
	}

	/**
	 * Prepare the tag correlation flavour of the delete scenario, see
	 * prepareDataCepWindowHeldProblemsOperations().
	 */
	public function prepareDataCepWindowTagDelete() {
		return $this->prepareDataCepWindowHeldProblemsOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			self::CEP_RULE_WINDOW_TAG_DELETE
		);
	}

	/**
	 * Prepare the cause and symptom flavour of the delete scenario, see
	 * prepareDataCepWindowHeldProblemsOperations().
	 */
	public function prepareDataCepWindowCauseSymptomDelete() {
		return $this->prepareDataCepWindowHeldProblemsOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::CEP_RULE_WINDOW_CAUSE_DELETE
		);
	}

	/**
	 * Prepare the pattern match flavour of the delete scenario, see prepareDataCepWindowHeldProblemsOperations().
	 */
	public function prepareDataCepWindowPatternDelete() {
		return $this->prepareDataCepWindowHeldProblemsOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_DELETE
		);
	}

	/**
	 * Prepare the pattern match flavour of the delete scenario whose script sleeps: the window is examined once a
	 * second and every examination sleeps CEP_RULE_WINDOW_SLEEP_SCRIPT_MS before reporting anything, so the window is
	 * being examined nearly all the time and the delete of the rule lands in the middle of a script - see
	 * runEventAssessmentTestCepWindowDeleteDuringScript().
	 *
	 * The script reports no match however long it took, so nothing it decides can close a problem: what the scenario
	 * reads is what the window does when the rule it belongs to is deleted while its script is running, not what a
	 * match would have done. Everything else is the rule of the other delete flavours, see
	 * prepareDataCepWindowHeldProblemsOperations() - except for the duration, which this flavour states itself: the
	 * window has to be holding an event while the delete lands in a script, so it must outlast both the sleep and the
	 * wait before the delete by a wide margin.
	 */
	public function prepareDataCepWindowPatternDeleteSleep() {
		$sleep_ms = self::CEP_RULE_WINDOW_SLEEP_SCRIPT_MS;

		$script = <<<HEREDOC
Zabbix.sleep($sleep_ms);

return 'false';
HEREDOC;

		return $this->prepareDataCepWindowHeldProblemsOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_DELETE_SLEEP, $script, self::CEP_RULE_WINDOW_CAPACITY_DURATION
		);
	}

	/**
	 * Prepare the rule of the two scenarios that take a rule away while its windows are holding problems - the
	 * reset one (runEventAssessmentTestCepWindowReset()) and the delete one
	 * (runEventAssessmentTestCepWindowDelete()): the same window every close window flavour uses - one per id,
	 * grouped by the 'service' tag and outlasting the whole scenario (CEP_RULE_WINDOW_CAPACITY_DURATION), so what a
	 * window holds is exactly the events of its id and only the operations may end it - given the two operations
	 * that make what a window holds visible from the outside:
	 *   - "close window" when an "up" event is added to the window, restricted to those events by a tag exists
	 *     condition on
	 *     CEP_STATE_TAG_UP, a tag only an "up" event carries because its name is resolved from the item value;
	 *   - "close" when the window closes, which reaches every event that window held.
	 *
	 * Together they close the problems of an id the moment it recovers, exactly as the arrival flavour of the close
	 * window scenario does (prepareDataCepWindowCloseWindowOperations()) - which is what taking the rule away is
	 * measured against: the recovery of an id closes what the window of that id holds, so once those windows are
	 * gone the problems they had been holding are left open for the trigger expression to recover, whether the rule
	 * was reset or deleted. Neither operation is tied to the window type, so every window type is given the same
	 * pair and must produce the same outcome.
	 *
	 * $window_type is the type under test. A pattern match window cannot be without a script and this scenario is
	 * not driven by a match, so that type gets one that never reports one - the events of its window are only ever
	 * acted on by the operations above, as in the window types that have no script at all. $script replaces that
	 * script with one of the caller's, which is how the flavour that deletes the rule while the window is being
	 * examined gets a script that sleeps (prepareDataCepWindowPatternDeleteSleep()); it is ignored by every window
	 * type that has no script. $duration replaces the duration of the window the same way, for the flavours that need
	 * one of their own rather than the one the reset and delete flavours share.
	 */
	private function prepareDataCepWindowHeldProblemsOperations(int $window_type, string $name,
			?string $script = null, $duration = null) {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// The rule of this scenario is the only thing that may close a problem.
		$this->deleteCepCorrelations();

		$window = [
			// The restarts of these scenarios land while the windows are supposed to be holding their problems (a
			// reset or a delete that arrives at an empty window says nothing about either), so the time a stop and a
			// start takes is added to the duration when they are turned on - and nothing changes when they are not,
			// see getRestartWindowAllowance().
			'duration' => $duration === null ? 3 + static::getRestartWindowAllowance() : $duration,
			// Every event must be held: both scenarios are about what a window has in it when it is taken away, so
			// nothing may be evicted for not fitting.
			'capacity' => 0,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => ['service']
		];

		if ($window_type === CCepRuleHelper::WINDOW_PATTERN_MATCH) {
			$window['script'] = $script === null ? "return 'false';" : $script;
		}

		$operations = [
			[
				'sortorder' => 0,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_ADDED,
				'type' => CCepRuleHelper::OP_CLOSE_WINDOW,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [self::buildUpEventOperationCondition()]
				]
			],
			[
				'sortorder' => 1,
				'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED,
				'type' => CCepRuleHelper::OP_CLOSE_EVENT
			]
		];

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams($name, [], $operations,
			CONDITION_EVAL_TYPE_AND, '', $window_type, $window
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the cause and symptom flavour of the scenarios whose window is ended by its duration, see
	 * prepareDataCepWindowCloseOnDurationOperations().
	 */
	public function prepareDataCepWindowCauseSymptomCloseOnDuration() {
		return $this->prepareDataCepWindowCloseOnDurationOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::CEP_RULE_WINDOW_CAUSE_CLOSE_DURATION
		);
	}

	/**
	 * Prepare the simple window flavour of the scenarios whose window is ended by its duration, see
	 * prepareDataCepWindowCloseOnDurationOperations().
	 */
	public function prepareDataCepWindowSimpleCloseOnDuration() {
		return $this->prepareDataCepWindowCloseOnDurationOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::CEP_RULE_WINDOW_SIMPLE_CLOSE_DURATION
		);
	}

	/**
	 * Prepare the tag correlation flavour of the scenarios whose window is ended by its duration, see
	 * prepareDataCepWindowCloseOnDurationOperations().
	 */
	public function prepareDataCepWindowTagCloseOnDuration() {
		return $this->prepareDataCepWindowCloseOnDurationOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			self::CEP_RULE_WINDOW_TAG_CLOSE_DURATION
		);
	}

	/**
	 * Prepare the rule of the scenarios whose window is ended by its duration running out instead of by the value that
	 * would have ended it: one window per id, grouped by the 'service' tag as everywhere else, with a duration short
	 * enough to be waited out within a single wait (CEP_RULE_WINDOW_CLOSE_DURATION_PERIOD and, for the window type
	 * examined on a grid, CEP_RULE_WINDOW_CLOSE_DURATION_CAUSE_PERIOD) and room for every event it is given, so nothing
	 * can leave it for not fitting - what leaves this window leaves it because it is old.
	 *
	 * What the operations are depends on what the duration of $window_type does when it runs out:
	 *   - a cause and symptom window is closed by it (cep_window_causal_process()), so the rule needs nothing but the
	 *     "close" of a closing window: no operation takes part in the ending itself, which is what tells this scenario
	 *     apart from every close window flavour;
	 *   - a simple and a tag correlation window evict what has been in them too long instead, so this rule closes the
	 *     window from the eviction execution point - unconditionally, unlike the close window flavours that restrict
	 *     that operation to the "up" events, because here the event that ends the window is whichever one is oldest.
	 *     The "close" of the closing window then reaches the events still in it, and not the evicted one that ended
	 *     it, see runEventAssessmentTestCepWindowCloseOnDuration().
	 *
	 * Nothing else may close a problem of these scenarios: the rule is the only thing that does, and it does it by
	 * the clock rather than by anything sent to it.
	 */
	private function prepareDataCepWindowCloseOnDurationOperations(int $window_type, string $name) {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// The rule of this scenario is the only thing that may close a problem.
		$this->deleteCepCorrelations();

		$window = [
			// The cause and symptom flavour waits out two periods in a row, so it gets the shorter of the two
			// durations - and the sliding one has to hold its younger event across the restart it takes, so its own
			// grows with that, see CEP_RULE_WINDOW_CLOSE_DURATION_PERIOD and getCloseOnDurationPeriod().
			'duration' => $window_type === CCepRuleHelper::WINDOW_CAUSE_SYMPTOM
				? self::CEP_RULE_WINDOW_CLOSE_DURATION_CAUSE_PERIOD
				: static::getCloseOnDurationPeriod(),
			'capacity' => 0,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => ['service']
		];

		$operations = [
			[
				'sortorder' => 0,
				'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED,
				'type' => CCepRuleHelper::OP_CLOSE_EVENT
			]
		];

		if ($window_type !== CCepRuleHelper::WINDOW_CAUSE_SYMPTOM) {
			// The duration of a sliding window evicts rather than closes, so the eviction of its oldest event is what
			// has to end it - the one execution point the duration of these window types reaches on its own.
			$operations[] = [
				'sortorder' => 1,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_EVICTED,
				'type' => CCepRuleHelper::OP_CLOSE_WINDOW
			];
		}

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams($name, [], $operations,
			CONDITION_EVAL_TYPE_AND, '', $window_type, $window
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the simple window flavour of the unresolved limits scenario, see
	 * prepareDataCepWindowUnresolvedLimitsOperations().
	 */
	public function prepareDataCepWindowSimpleUnresolvedLimits() {
		return $this->prepareDataCepWindowUnresolvedLimitsOperations(CCepRuleHelper::WINDOW_SIMPLE,
			self::CEP_RULE_WINDOW_SIMPLE_LIMITS
		);
	}

	/**
	 * Prepare the tag correlation window flavour of the unresolved limits scenario, see
	 * prepareDataCepWindowUnresolvedLimitsOperations().
	 */
	public function prepareDataCepWindowTagUnresolvedLimits() {
		return $this->prepareDataCepWindowUnresolvedLimitsOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			self::CEP_RULE_WINDOW_TAG_LIMITS
		);
	}

	/**
	 * Prepare the cause and symptom window flavour of the unresolved limits scenario, see
	 * prepareDataCepWindowUnresolvedLimitsOperations().
	 */
	public function prepareDataCepWindowCauseSymptomUnresolvedLimits() {
		return $this->prepareDataCepWindowUnresolvedLimitsOperations(CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
			self::CEP_RULE_WINDOW_CAUSE_LIMITS
		);
	}

	/**
	 * Prepare the pattern match window flavour whose duration is the only limit that cannot resolve: the capacity
	 * macro is created before the rule is, so the duration is both the limit the server parses first and the only
	 * one it can fail on, see prepareDataCepWindowUnresolvedLimitsOperations() and
	 * runEventAssessmentTestCepWindowSingleUnresolvedLimit().
	 */
	public function prepareDataCepWindowPatternDurationUnresolved() {
		return $this->prepareDataCepWindowUnresolvedLimitsOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_DURATION_LIMIT,
			[self::CEP_WINDOW_CAPACITY_MACRO => self::CEP_RULE_WINDOW_LIMITS_CAPACITY]
		);
	}

	/**
	 * Prepare the pattern match window flavour whose capacity is the only limit that cannot resolve: the duration
	 * macro is created before the rule is, so the limit parsed ahead of the capacity is out of the way, see
	 * prepareDataCepWindowUnresolvedLimitsOperations() and
	 * runEventAssessmentTestCepWindowSingleUnresolvedLimit().
	 */
	public function prepareDataCepWindowPatternCapacityUnresolved() {
		return $this->prepareDataCepWindowUnresolvedLimitsOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH,
			self::CEP_RULE_WINDOW_PATTERN_CAPACITY_LIMIT,
			[self::CEP_WINDOW_DURATION_MACRO => self::CEP_RULE_WINDOW_LIMITS_DURATION]
		);
	}

	/**
	 * Prepare the rule of the scenario that gives a window limits it cannot resolve
	 * (runEventAssessmentTestCepWindowUnresolvedLimits()): the very rule of the reset and delete scenarios - one
	 * window per id, grouped by the 'service' tag, "close window" when an "up" event is added to it and "close"
	 * when the
	 * window closes, see prepareDataCepWindowHeldProblemsOperations() - whose window limits are the user macros
	 * CEP_WINDOW_DURATION_MACRO and CEP_WINDOW_CAPACITY_MACRO with neither macro created.
	 *
	 * Those two are the only window parameters that may hold a macro and the server resolves them anew whenever it
	 * works on a window, so a rule is not rejected for holding one that does not exist - what happens instead is
	 * what this scenario reads: the server has no duration and no capacity to give the window it was about to open,
	 * so it opens none at all and records why on the rule. The operations are left as they are precisely because
	 * they are the ones that need a window: with none of them able to reach anything, what a window would have done
	 * is exactly what the events are missing.
	 *
	 * The macros are deleted here rather than assumed absent: they are global and outlive the rules of the flavours
	 * that create them (macroizeWindowLimits()), so a run following one of those would find both of them
	 * resolvable and the scenario would be driving a perfectly healthy rule. The assessment creates them itself,
	 * one at a time, once it has seen the error each of them causes while missing.
	 *
	 * $resolved_limits, macro to value, is what the flavour driving a single limit
	 * (runEventAssessmentTestCepWindowSingleUnresolvedLimit()) creates back afterwards, so the limit it is after is
	 * the only one left unresolvable. It changes nothing about the rule - the window is still given both of its
	 * limits as macros - only which of them the server can read.
	 *
	 * $window_type is the type under test, and every type has to behave the same way: the limits of a window are
	 * not what tells the types apart, so none of them may open a window without them. A pattern match window cannot
	 * be without a script and this scenario is not driven by a match, so that type gets one that never reports one.
	 */
	private function prepareDataCepWindowUnresolvedLimitsOperations(int $window_type, string $name,
			array $resolved_limits = []) {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// The rule of this scenario is the only thing that may close a problem.
		$this->deleteCepCorrelations();

		// Neither limit may resolve when the scenario starts, whatever the flavour that ran before it left behind.
		$this->deleteGlobalMacros([self::CEP_WINDOW_DURATION_MACRO, self::CEP_WINDOW_CAPACITY_MACRO]);

		// Only then are the limits this flavour wants resolvable given back, so what they are set to is this
		// scenario's doing and not something a previous flavour happened to leave.
		foreach ($resolved_limits as $macro => $value) {
			$this->upsertGlobalMacro($macro, (string) $value);
		}

		$window = [
			'duration' => self::CEP_WINDOW_DURATION_MACRO,
			'capacity' => self::CEP_WINDOW_CAPACITY_MACRO,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => ['service']
		];

		if ($window_type === CCepRuleHelper::WINDOW_PATTERN_MATCH) {
			$window['script'] = "return 'false';";
		}

		$operations = [
			[
				'sortorder' => 0,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_ADDED,
				'type' => CCepRuleHelper::OP_CLOSE_WINDOW,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [self::buildUpEventOperationCondition()]
				]
			],
			[
				'sortorder' => 1,
				'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED,
				'type' => CCepRuleHelper::OP_CLOSE_EVENT
			]
		];

		$cep_ruleid = $this->upsertCepRule($this->buildWindowNoneCepRuleParams($name, [], $operations,
			CONDITION_EVAL_TYPE_AND, '', $window_type, $window
		));

		// A rule holding macros no one can resolve must be accepted and stored as it was written: were the limits
		// rejected or resolved before reaching the database, there would be no rule to read an error from.
		$stored = $this->call('ceprule.get', [
			'cep_ruleids' => $cep_ruleid,
			'output' => ['cep_ruleid'],
			'selectWindow' => ['duration', 'capacity']
		]);
		$this->assertCount(1, $stored['result'], 'The rule of "'.$name.'" must exist after it was created.');
		$this->assertSame(self::CEP_WINDOW_DURATION_MACRO, $stored['result'][0]['window']['duration'],
			'The window of "'.$name.'" must keep its duration macro as it was written.'
		);
		$this->assertSame(self::CEP_WINDOW_CAPACITY_MACRO, $stored['result'][0]['window']['capacity'],
			'The window of "'.$name.'" must keep its capacity macro as it was written.'
		);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the simple window flavour of the capacity scenario, see
	 * prepareDataCepWindowCapacityOperations().
	 */
	public function prepareDataCepWindowSimpleCapacity() {
		return $this->prepareDataCepWindowCapacityOperations(CCepRuleHelper::WINDOW_SIMPLE, 'simple capacity');
	}

	/**
	 * Prepare the tag correlation window flavour of the capacity scenario, see
	 * prepareDataCepWindowCapacityOperations().
	 */
	public function prepareDataCepWindowTagCapacity() {
		return $this->prepareDataCepWindowCapacityOperations(CCepRuleHelper::WINDOW_TAG_MATCH, 'tag capacity');
	}

	/**
	 * Prepare the simple window flavour of the capacity scenario that groups by the 'service' tag, so every id
	 * gets a window of its own and fits into it, see prepareDataCepWindowCapacityOperations().
	 */
	public function prepareDataCepWindowSimpleCapacityPerService() {
		return $this->prepareDataCepWindowCapacityOperations(CCepRuleHelper::WINDOW_SIMPLE,
			'simple capacity service', true
		);
	}

	/**
	 * Prepare the tag correlation window flavour of the capacity scenario that groups by the 'service' tag, so
	 * every id gets a window of its own and fits into it, see prepareDataCepWindowCapacityOperations().
	 */
	public function prepareDataCepWindowTagCapacityPerService() {
		return $this->prepareDataCepWindowCapacityOperations(CCepRuleHelper::WINDOW_TAG_MATCH,
			'tag capacity service', true
		);
	}

	/**
	 * Prepare the per service capacity scenario whose rule additionally discards the "up" events, so the
	 * operation that would close the window and the operation that drops the event compete for the same
	 * event, see prepareDataCepWindowCapacityOperations().
	 */
	public function prepareDataCepWindowCapacityDiscardUp() {
		return $this->prepareDataCepWindowCapacityOperations(CCepRuleHelper::WINDOW_SIMPLE,
			'capacity discard', true, true
		);
	}

	/**
	 * Prepare a windowed flavour whose window overflows instead of expiring: it holds a single event
	 * (CEP_RULE_WINDOW_CAPACITY) and lasts longer than the whole test (CEP_RULE_WINDOW_CAPACITY_DURATION), and
	 * it groups by a tag all the events of the driven trigger share, so they all end up in that one window and
	 * compete for its single place.
	 *
	 * An event arriving at a window that has no place left is not added to it - it is evicted right away, and
	 * the operations of the rule decide what happens to it. The one rule this flavour creates does:
	 *   - "suppress" when an event is evicted, so an event that did not fit is marked as suppressed and not
	 *     only closed - the two operations are applied to the same event, in this order;
	 *   - "close" when an event is evicted: an event that did not fit is closed immediately;
	 *   - "close window" when an event is evicted, restricted to the "up" events by its condition - a tag
	 *     exists condition on CEP_STATE_TAG_UP, a tag only an "up" event carries because its name is resolved
	 *     from the item value: the event that did not fit also ends the window it could not enter;
	 *   - "close" when the window closes: the event the window did hold is closed with it.
	 *
	 * Sending "down" values therefore leaves exactly the first problem open (every later one is closed as it
	 * arrives), and the "up" value closes both itself and that first problem, see
	 * runEventAssessmentTestCepWindowCapacity(). Nothing here depends on the duration: the evictions are
	 * caused by the capacity alone, when the event arrives.
	 *
	 * $discard_up is the one flavour of which that is not true. It drops the "up" events as they occur, which
	 * are the events its windows would have been ended by, so its windows are left with nothing coming that
	 * could evict what they hold - and its window is given a short duration
	 * (CEP_RULE_WINDOW_CAPACITY_DISCARD_DURATION) so that running out is what evicts it instead, see
	 * runEventAssessmentTestCepWindowCapacityDiscard().
	 */
	private function prepareDataCepWindowCapacityOperations(int $window_type, string $name_infix,
			bool $group_by_service = false, bool $discard_up = false) {
		// The same prototypes as the other windowed flavours, so switching between them does not re-discover
		// the triggers; the one that matters here is CEP_STATE_TAG, whose resolved name tells the close window
		// operation which event is an "up" one.
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// The rule of this flavour is the only thing that may close a problem.
		$this->deleteCepCorrelations();

		// By default grouped by the 'component' tag, which every event of the driven trigger carries with the
		// same value: they all compete for the one place of a single window, and the grouping a tag
		// correlation window is there for is exercised rather than left switched off.
		//
		// $group_by_service groups by the 'service' tag instead, which differs per id, so every id gets a
		// window of its own and its "down" event fits into it - nothing is evicted until the "up" of that id
		// arrives and finds the place taken.
		// The duration is out of the way of every flavour but the discarding one, which needs its windows to run
		// out: the events that would have ended them are the very ones it drops, so nothing arrives to end them
		// and the duration is the only thing left that can - see CEP_RULE_WINDOW_CAPACITY_DISCARD_DURATION and
		// runEventAssessmentTestCepWindowCapacityDiscard(), which waits for exactly that.
		$window = [
			'duration' => $discard_up
				? self::CEP_RULE_WINDOW_CAPACITY_DISCARD_DURATION
				: self::CEP_RULE_WINDOW_CAPACITY_DURATION,
			'capacity' => self::CEP_RULE_WINDOW_CAPACITY,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => [$group_by_service ? 'service' : 'component']
		];

		$operations = [
			[
				'sortorder' => 0,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_EVICTED,
				'type' => CCepRuleHelper::OP_SUPPRESS,
				'suppress_duration' => self::CEP_RULE_WINDOW_NONE_SUPPRESS_PERIOD
			],
			[
				'sortorder' => 1,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_EVICTED,
				'type' => CCepRuleHelper::OP_CLOSE_EVENT
			],
			[
				'sortorder' => 2,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_EVICTED,
				'type' => CCepRuleHelper::OP_CLOSE_WINDOW,
				// The "up" events are singled out by the presence of a tag, not by a tag value: CEP_STATE_TAG
				// resolves its name at event time, so only an "up" event carries CEP_STATE_TAG_UP at all.
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [self::buildUpEventOperationCondition()]
				]
			],
			[
				'sortorder' => 3,
				'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED,
				'type' => CCepRuleHelper::OP_CLOSE_EVENT
			]
		];

		if ($discard_up) {
			// Discarding is decided while the rules are matched, before the event is stored and long before
			// any window sees it, so this operation short circuits everything the operations above would have
			// done to an "up" event: it is not evicted, it does not close the window, and it leaves no trace.
			array_unshift($operations, [
				'sortorder' => -1,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
				'type' => CCepRuleHelper::OP_DISCARD,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [self::buildUpEventOperationCondition()]
				]
			]);
		}

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams(
			self::CEP_RULE_NAME_PREFIX.'window '.$name_infix, [], $operations, CONDITION_EVAL_TYPE_AND, '',
			$window_type, $window
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare a windowed flavour of the scenario above: the very same operations, applied by a rule that has a
	 * $window_type window instead of none. The trigger prototypes are set up exactly as for the windowless
	 * flavour (so the events carry the same tags), and the operations are the same tag and event operations,
	 * this time all in one rule whose window groups the events by their 'service' tag - every id the scenario
	 * sends therefore lands in a window of its own. The rules of the flavour are named after $name_infix, and
	 * nothing closes a window or a problem, so as in the windowless flavour every problem stays open.
	 *
	 * The operator coverage rules of getWindowNoneRules() are deliberately not recreated with a window,
	 * because how many windowed rules may process one event depends on the window type. That difference is
	 * asserted instead: a second rule of the same window type matches the same events and does nothing but add
	 * the CEP_TAG_WINDOW_SECOND tag, and whether an event ends up carrying it tells the two apart.
	 *   - a simple window is not exclusive, so both rules are processed and every event gets the tag;
	 *   - a tag correlation window is one of the window types of which only the FIRST matching rule is
	 *     processed for an event, so the operations rule (the lower sortorder, hence the one that is
	 *     processed) uses up the slot and no event ever gets the tag. A matrix of tag correlation rules could
	 *     therefore never tag one event more than once, which is why the operator rules stay windowless.
	 *
	 * $execute_when is the execution point the operations are performed at, and the state they leave behind must be
	 * the same from all of them - an operation is what it does, not when it is asked to do it:
	 *   - WHEN_EVENT_OCCURRED, the moment the event enters the window. This is the only point that has the second
	 *     rule above, the window types differing in nothing else;
	 *   - WHEN_EVENT_EVICTED, once the window duration has run out and the event is evicted from it. A cause and
	 *     symptom window has no such point - the duration running out closes that window instead of evicting what
	 *     it holds (see cep_window_causal_process()) - so it is the one window type this flavour is not run for;
	 *   - WHEN_WINDOW_CLOSED, reached through a "close window" operation added below. The "set name" operation is
	 *     the one the server does not allow at this point, so this flavour runs every operation of the set except
	 *     that one and its events keep the name their trigger gave them.
	 *
	 * The macros of the operations are what make the later two points more than the same coverage twice: an
	 * operation resolves its event name and its tag names and values against the event it acts on, and an event
	 * leaving a window is no longer the event that was just assessed - the server has to build its context from the
	 * event the window was holding. Whether a macro can be resolved that late is therefore what these flavours read,
	 * event macros included, and the expression macro (which only the name accepts) as far as the name operation is
	 * performed at all, see getWindowNoneEventOperationCase() and getWindowNoneTagOperationCases().
	 */
	private function prepareDataCepWindowOperations(int $window_type, string $name_infix,
			int $execute_when = CCepRuleHelper::WHEN_EVENT_OCCURRED) {
		$this->prepareCloseOnUpTriggerPrototypes($this->getWindowOperationsTriggerTags());

		// The operations of the rule are the very ones of the windowless flavour, macros and all.
		$this->prepareWindowOperationMacros();

		// As in the windowless flavour, nothing except the trigger expression may close these problems.
		$this->deleteCepCorrelations();

		// The limits of the window are given to it as user macros, as in the windowless flavour's windowed runs.
		$window = $this->macroizeWindowLimits($this->buildWindowOperationsWindow());
		$name = self::CEP_RULE_NAME_PREFIX.'window '.$name_infix.' ';

		if ($window_type === CCepRuleHelper::WINDOW_PATTERN_MATCH) {
			// A pattern match window cannot be without a script and none of these flavours is driven by a match,
			// so this one reports none however many events it is handed: what acts on the events of the window are
			// the operations alone, exactly as in the window types that have no script at all.
			$window['script'] = "return 'false';";
		}

		// Both operation sets in one rule: with a window there is no second rule to run them from. When they run
		// is $execute_when - the moment the event occurs, once the window duration has run out and the event is
		// evicted from it, or as the window that holds the event closes.
		$event_operations = $this->getWindowNoneEventOperationCase()['operations'];

		if ($execute_when == CCepRuleHelper::WHEN_WINDOW_CLOSED) {
			// "set name" is the one operation of the set the server does not allow at the window closed
			// execution point (see CEP_OP_SET_NAME_MASK), so the flavour leaves it out and the events keep the
			// name their trigger gave them - see runEventAssessmentTestCepWindowLateOperations(), which expects
			// that name from this flavour and the rewritten one from every other.
			$event_operations = array_values(array_filter($event_operations,
				fn($operation) => $operation[0] != CCepRuleHelper::OP_SET_NAME
			));
		}

		$operations = $this->buildWindowNoneOperations(
			array_merge($this->getWindowNoneTagOperationOperations(), $event_operations),
			$execute_when
		);

		if ($execute_when == CCepRuleHelper::WHEN_WINDOW_CLOSED) {
			// Nothing but a "close window" operation ever closes a window - the duration running out evicts what
			// a simple, a tag correlation and a pattern match window hold rather than closing it - so the window
			// closed execution point is only reached by a rule that asks for it. This one asks for it
			// unconditionally and as the event is added to the window, so every event closes the window it has just
			// entered and
			// the operations above are performed for it right there: the shortest path from an event to that
			// execution point, and the same one for every window type.
			//
			// It is listed last only because its sortorder must not collide with the operations above; the order
			// within a rule is the order of one execution point, and this one is the only operation of its own.
			$operations[] = [
				'sortorder' => count($operations),
				'execute_when' => CCepRuleHelper::WHEN_EVENT_ADDED,
				'type' => CCepRuleHelper::OP_CLOSE_WINDOW
			];
		}

		$this->upsertCepRule($this->buildWindowNoneCepRuleParams($name.'operations', [], $operations,
			CONDITION_EVAL_TYPE_AND, '', $window_type, $window
		));

		if ($execute_when != CCepRuleHelper::WHEN_EVENT_OCCURRED) {
			$this->reloadConfigurationCacheAndWaitForLogLine();

			return true;
		}

		// The second rule of the same window type, matching the same events: whether it gets its turn is what
		// the flavours differ in. The higher sortorder makes it the second one either way.
		$second_operations = $this->buildWindowNoneOperations([
			[CCepRuleHelper::OP_ADD_TAG, ['tag' => self::CEP_TAG_WINDOW_SECOND,
				'tag_value' => self::CEP_TAG_WINDOW_SECOND_VALUE
			]]
		]);

		$this->upsertCepRule(['sortorder' => 1] + $this->buildWindowNoneCepRuleParams($name.'second', [],
			$second_operations, CONDITION_EVAL_TYPE_AND, '', $window_type, $window
		));

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Prepare the "close old down when new up" scenario with the correlation created under
	 * $baseline_evaltype and then updated in place to $target_evaltype, so the test exercises an evaltype
	 * transition on an existing rule rather than a freshly created one. The baseline rule is recreated from
	 * scratch first (so the starting evaltype is deterministic regardless of what a previous CloseOnUp
	 * variant left behind), then correlation.update switches it to the target evaltype.
	 */
	public function prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition($baseline_evaltype, $target_evaltype) {
		$this->prepareDataGlobalCorrelationCloseOnUp($baseline_evaltype, false, true);

		self::$correlationid = $this->upsertCorrelation(
			$this->buildCloseOnUpCorrelationParams('CEP global event correlation up', $target_evaltype)
		);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Create two independent webhook media types and two trigger actions that together add extra tags to
	 * every discovered CEP problem event using JavaScript. The first webhook adds a WEB_SERVICE_TAG tag (the
	 * trailing number of the item value, e.g. "0" for "down_0"); the second adds a WEB_SERVICE_TAG2 tag
	 * whose value is the same trailing number prefixed with 'second_'. This does not affect correlation
	 * (which still uses the 'service' trigger tag) — it lets the test verify that tags returned by media
	 * types are applied to the events they were generated for, including when two separate webhook actions
	 * tag the same event.
	 *
	 * Both media types have process_tags enabled; each script parses the item value passed as a parameter,
	 * extracts the trailing number and returns it in the {"tags": {...}} form the alerter applies to the
	 * event. The first script also copies the event's 'component' tag (passed via {EVENT.TAGS.component})
	 * into a WEB_COMPONENT_TAG tag, which the web-tag services (see createWebTagServices) match their
	 * problems on. Medias are attached to the Admin user for both media types, and two trigger actions
	 * firing on the discovered CEP triggers route their problem operations through the webhooks, so each
	 * PROBLEM event ("down_N" and, since the expression matches "up", "up_N") gets both a WEB_SERVICE_TAG
	 * and a WEB_SERVICE_TAG2 tag.
	 *
	 * Everything created here is removed in removeExtraTagWebhookAction() / clearData().
	 */
	private function createExtraTagWebhookAction(): void {
		$tag = self::WEB_SERVICE_TAG;
		$tag2 = self::WEB_SERVICE_TAG2;
		$component_tag = self::WEB_COMPONENT_TAG;
		$script_code = <<<HEREDOC
var params = JSON.parse(value),
	match = params.item_value.match(/([0-9]+)\$/),
	tags = {'$tag': match === null ? '' : match[1]};

tags['$component_tag'] = params.component;

return JSON.stringify({tags: tags});
HEREDOC;

		$script_code2 = <<<HEREDOC
var params = JSON.parse(value),
	match = params.item_value.match(/([0-9]+)\$/),
	tags = {'$tag2': match === null ? '' : 'second_' + match[1]};

return JSON.stringify({tags: tags});
HEREDOC;

		$response = $this->call('mediatype.create', [
			'name' => 'CEP extra tag webhook',
			'type' => MEDIA_TYPE_WEBHOOK,
			'script' => $script_code,
			'process_tags' => ZBX_MEDIA_TYPE_TAGS_ENABLED,
			'status' => MEDIA_TYPE_STATUS_ACTIVE,
			'parameters' => [
				['name' => 'item_value', 'value' => '{ITEM.VALUE}'],
				['name' => 'component', 'value' => '{EVENT.TAGS.component}']
			]
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['mediatypeids']);
		self::$tag_mediatypeid = $response['result']['mediatypeids'][0];

		$response = $this->call('mediatype.create', [
			'name' => 'CEP extra tag webhook 2',
			'type' => MEDIA_TYPE_WEBHOOK,
			'script' => $script_code2,
			'process_tags' => ZBX_MEDIA_TYPE_TAGS_ENABLED,
			'status' => MEDIA_TYPE_STATUS_ACTIVE,
			'parameters' => [
				['name' => 'item_value', 'value' => '{ITEM.VALUE}']
			]
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['mediatypeids']);
		self::$tag_mediatypeid2 = $response['result']['mediatypeids'][0];

		// Attach both tagging media types to the Admin user alongside the shared CEP webhook media, so the
		// actions' message operations actually generate alerts (and thus run the webhooks).
		$this->call('user.update', [
			'userid' => 1,
			'medias' => [
				['mediatypeid' => self::$mediatypeid, 'sendto' => 'cep'],
				['mediatypeid' => self::$tag_mediatypeid, 'sendto' => 'cep'],
				['mediatypeid' => self::$tag_mediatypeid2, 'sendto' => 'cep']
			]
		]);

		// Trigger action firing on every discovered CEP trigger event (both prototypes, type=cep and
		// type=cep-dep are OR'd together), routing the problem operation through the tagging webhook.
		$response = $this->call('action.create', [
			'name' => 'CEP extra tag action',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'esc_period' => '1h',
			'pause_suppressed' => 0,
			/*'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value2' => 'type',
						'value' => 'cep'
					],
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value2' => 'type',
						'value' => 'cep-dep'
					]
				]
			],*/
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$tag_mediatypeid,
						'message' => 'Problem', 'subject' => 'Problem'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		self::$tag_actionid = $response['result']['actionids'][0];

		// Second trigger action, identical except that it routes its problem operation through the second
		// tagging webhook, so every problem event is tagged by two independent webhook actions.
		$response = $this->call('action.create', [
			'name' => 'CEP extra tag action 2',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'esc_period' => '1h',
			'pause_suppressed' => 0,
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 0, 'mediatypeid' => self::$tag_mediatypeid2,
						'message' => 'Problem', 'subject' => 'Problem'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		self::$tag_actionid2 = $response['result']['actionids'][0];

		// The service/trigger actions are already disabled once the services-specific tests finish (see
		// disableServicesActions), so only the tagging webhooks fire in this scenario.
	}

	/**
	 * Tear down the webhook media types, their user medias and the trigger actions created by
	 * createExtraTagWebhookAction(), restoring the Admin user to only the shared CEP webhook media so the
	 * tagging webhooks do not leak into subsequent tests.
	 */
	private function removeExtraTagWebhookAction(): void {
		if (!empty(self::$tag_actionid)) {
			$this->call('action.delete', [self::$tag_actionid]);
			self::$tag_actionid = null;
		}

		if (!empty(self::$tag_actionid2)) {
			$this->call('action.delete', [self::$tag_actionid2]);
			self::$tag_actionid2 = null;
		}

		if (!empty(self::$tag_mediatypeid) || !empty(self::$tag_mediatypeid2)) {
			$this->call('user.update', [
				'userid' => 1,
				'medias' => [
					['mediatypeid' => self::$mediatypeid, 'sendto' => 'cep']
				]
			]);

			if (!empty(self::$tag_mediatypeid)) {
				$this->call('mediatype.delete', [self::$tag_mediatypeid]);
				self::$tag_mediatypeid = null;
			}

			if (!empty(self::$tag_mediatypeid2)) {
				$this->call('mediatype.delete', [self::$tag_mediatypeid2]);
				self::$tag_mediatypeid2 = null;
			}
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Create one service per discovered component whose only problem tag is the webhook-applied
	 * WEB_COMPONENT_TAG (see createExtraTagWebhookAction). Unlike the per-trigger CEP services (which match
	 * the SERVICE_TAG trigger tag), no trigger tag matches these services: each can enter problem state
	 * only after the tagging webhook runs for an open problem event and the tags it returns are applied to
	 * that event. Removed in removeWebTagServices() / clearData().
	 */
	private function createWebTagServices(): void {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');

		$services = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			$services[] = [
				'name' => 'CEP web tag service '.$base.$i,
				'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL,
				'sortorder' => 0,
				'problem_tags' => [
					[
						'tag' => self::WEB_COMPONENT_TAG,
						'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL,
						'value' => $base.$i
					]
				]
			];
		}

		$response = $this->call('service.create', $services);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']['serviceids'],
			'Not all web-tag services were created.');
		self::$web_tag_serviceids = $response['result']['serviceids'];

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Delete the services created by createWebTagServices() so they do not react to the events of later
	 * scenarios. Guarded, so it is safe in a finally block even if creation failed. The caller is expected
	 * to follow up with removeExtraTagWebhookAction(), which reloads the configuration cache.
	 */
	private function removeWebTagServices(): void {
		if (!empty(self::$web_tag_serviceids)) {
			$this->call('service.delete', self::$web_tag_serviceids);
			self::$web_tag_serviceids = [];
		}
	}

	/**
	 * Create the correlation rule described by $corr_params, or update it in place if one with the
	 * same name already exists (prepareData* methods are re-run by every dependent test). Returns
	 * the correlation id so the caller can track it for cleanup.
	 */
	private function upsertCorrelation(array $corr_params): string {
		$existing = $this->call('correlation.get',
			['filter' => ['name' => $corr_params['name']], 'output' => ['correlationid']]
		);
		if ($existing['result']) {
			$correlationid = $existing['result'][0]['correlationid'];
			$this->call('correlation.update', ['correlationid' => $correlationid] + $corr_params);
		}
		else {
			$response = $this->call('correlation.create', $corr_params);
			$this->assertArrayHasKey('correlationids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['correlationids']);
			$correlationid = $response['result']['correlationids'][0];
		}

		return $correlationid;
	}

	/**
	 * Create the CEP rule described by $rule_params, or update it in place if one with the same name already
	 * exists. Returns the CEP rule id so the caller can track it for cleanup.
	 *
	 * The usual case is a create: the scenario that ran before removed its rules in cleanupCepRules(), so a
	 * prepareData* method starts with none of its own in place. The update is what makes a rule left behind by
	 * a run that never reached its teardown - an aborted test, a killed server - harmless: the rule the
	 * scenario needs is brought to the parameters it asks for instead of the create failing on the duplicate
	 * name.
	 */
	private function upsertCepRule(array $rule_params): string {
		$existing = $this->call('ceprule.get',
			['filter' => ['name' => $rule_params['name']], 'output' => ['cep_ruleid']]
		);
		if ($existing['result']) {
			$cep_ruleid = $existing['result'][0]['cep_ruleid'];
			$this->call('ceprule.update', ['cep_ruleid' => $cep_ruleid] + $rule_params);
		}
		else {
			$response = $this->call('ceprule.create', $rule_params);
			$this->assertArrayHasKey('cep_ruleids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['cep_ruleids']);
			$cep_ruleid = $response['result']['cep_ruleids'][0];
		}

		return $cep_ruleid;
	}

	/**
	 * Turn the limits of $window into the user macros CEP_WINDOW_DURATION_MACRO and CEP_WINDOW_CAPACITY_MACRO: the
	 * macros are pointed at the duration and the capacity the window was built with and the window is returned with
	 * the macro names in their place, so its rule stores the macros and the server has to resolve them to arrive at
	 * the very limits the caller asked for.
	 *
	 * The macros are global, because that is the only kind a window can be given: a window belongs to a rule and
	 * not to a host, so the server resolves them without a host to resolve them for - unlike the macros of an
	 * operation, which acts on an event and therefore has one, see prepareWindowOperationMacros(). They outlive the
	 * rules of a flavour, which is why every flavour sets them rather than creating them once: the limits of the
	 * flavour that ran before must not be what the next one gets. clearData() takes them away with the rest of the
	 * suite.
	 *
	 * The configuration cache is not reloaded here: the caller does that after creating its rule, which is before
	 * any event of the scenario can open a window.
	 */
	private function macroizeWindowLimits(array $window): array {
		$this->upsertGlobalMacro(self::CEP_WINDOW_DURATION_MACRO, (string) $window['duration']);
		$this->upsertGlobalMacro(self::CEP_WINDOW_CAPACITY_MACRO, (string) $window['capacity']);

		$window['duration'] = self::CEP_WINDOW_DURATION_MACRO;
		$window['capacity'] = self::CEP_WINDOW_CAPACITY_MACRO;

		return $window;
	}

	/**
	 * Create the user macros the tag and event operations of the windowless scenario and of its windowed flavours
	 * are given instead of plain strings, see getWindowNoneTagOperationCases() and
	 * getWindowNoneEventOperationCase().
	 *
	 * They are put on the template the discovered host is linked to rather than created globally, because that is
	 * how an operation macro is resolved: an operation acts on an event, so the server looks the macro up on the
	 * host of that event and walks its templates, and only a macro found nowhere there falls back to the global
	 * ones. Coming from the template means the whole chain had to be walked for the operations to produce what the
	 * scenario expects - and the window limit macros (see macroizeWindowLimits()) cover the global kind, which is
	 * the only kind a window can be given.
	 *
	 * The macros outlive the rules of a flavour but not the suite: they are removed with the template itself in
	 * clearData().
	 */
	private function prepareWindowOperationMacros(): void {
		$this->upsertHostMacro(self::$templateid, self::CEP_OP_TAG_NAME_MACRO, self::CEP_OP_TAG_NAME);
		$this->upsertHostMacro(self::$templateid, self::CEP_OP_TAG_VALUE_MACRO, self::CEP_OP_TAG_VALUE);
		// The same name with a context, and a value of its own so the two cannot be confused: an operation asking
		// for the context must get this one and an operation asking for the plain name the one above.
		$this->upsertHostMacro(self::$templateid, self::CEP_OP_TAG_VALUE_CONTEXT_MACRO,
			self::CEP_OP_TAG_VALUE_CONTEXT
		);
		$this->upsertHostMacro(self::$templateid, self::CEP_OP_EVENT_NAME_MACRO,
			self::CEP_RULE_WINDOW_NONE_OP_EVENT_NAME_PREFIX
		);
		// The macro function and the event macros of the operations need nothing created for them: a function is
		// applied to what the macro it wraps resolved to, and an event macro comes from the event itself.
	}

	/**
	 * Set the user macro $macro of the host or template $hostid to $value, creating it if it does not exist yet.
	 * The macros are set one by one rather than in a single host.update, which would replace every macro the host
	 * has with the ones it is given.
	 */
	private function upsertHostMacro(string $hostid, string $macro, string $value): void {
		$existing = $this->call('usermacro.get', [
			'hostids' => $hostid,
			'filter' => ['macro' => $macro],
			'output' => ['hostmacroid', 'value']
		]);

		if (!$existing['result']) {
			$response = $this->call('usermacro.create', [
				'hostid' => $hostid,
				'macro' => $macro,
				'value' => $value
			]);
			$this->assertArrayHasKey('hostmacroids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['hostmacroids']);

			return;
		}

		if ($existing['result'][0]['value'] !== $value) {
			$this->call('usermacro.update', [
				'hostmacroid' => $existing['result'][0]['hostmacroid'],
				'value' => $value
			]);
		}
	}

	/**
	 * Set the global user macro $macro to $value, creating it if this suite has not created it yet.
	 */
	private function upsertGlobalMacro(string $macro, string $value): void {
		$existing = $this->call('usermacro.get', [
			'globalmacro' => true,
			'filter' => ['macro' => $macro],
			'output' => ['globalmacroid', 'value']
		]);

		if (!$existing['result']) {
			$response = $this->call('usermacro.createglobal', ['macro' => $macro, 'value' => $value]);
			$this->assertArrayHasKey('globalmacroids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['globalmacroids']);

			return;
		}

		if ($existing['result'][0]['value'] !== $value) {
			$this->call('usermacro.updateglobal', [
				'globalmacroid' => $existing['result'][0]['globalmacroid'],
				'value' => $value
			]);
		}
	}

	/**
	 * Remove the global user macros $macros, the ones of them that are there - the counterpart of
	 * upsertGlobalMacro() for the scenario that needs a macro not to resolve
	 * (prepareDataCepWindowUnresolvedLimitsOperations()). A macro that does not exist is not an error here: what
	 * this leaves behind is what the caller is after, whether it had to take anything away to get there.
	 *
	 * The configuration cache is not reloaded here, exactly as upsertGlobalMacro() does not: the caller does that
	 * once it has made every change of its own.
	 */
	private function deleteGlobalMacros(array $macros): void {
		$existing = $this->call('usermacro.get', [
			'globalmacro' => true,
			'filter' => ['macro' => $macros],
			'output' => ['globalmacroid']
		]);

		if ($existing['result']) {
			$this->call('usermacro.deleteglobal', array_column($existing['result'], 'globalmacroid'));
		}
	}

	/**
	 * Build a global event correlation rule that closes the problems of one parity. The new event must
	 * carry odd=$parity, and the rule matches old events with service="down" on the same component (tag
	 * pair), then closes both the old and the new problem.
	 *
	 * The condition set depends on $evaltype:
	 *   - CONDITION_EVAL_TYPE_AND_OR: service="down" (old) + odd=$parity (new) + component tag pair.
	 *     A 'type=cep-dep' new-event condition is intentionally omitted — combined with the 'odd'
	 *     new-event condition it would be OR'd (same type) and break parity selectivity. It is not
	 *     needed for correctness: only proto 2 (where "down" is re-sent) produces new events that find
	 *     a matching old problem, so service+parity+component already pinpoint the correlation.
	 *   - CONDITION_EVAL_TYPE_EXPRESSION: additionally AND-s a 'type=cep-dep' new-event condition,
	 *     exercising two same-type NEW_EVENT_TAG_VALUE conditions that only the expression evaltype can
	 *     AND together.
	 */
	private function buildParityCorrelationParams(string $name, string $parity, $evaltype): array {
		$service_down = [
			'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
			'tag' => 'service',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => 'down'
		];
		$new_type = [
			'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
			'tag' => 'type',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => 'cep-dep'
		];
		$new_odd = [
			'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
			'tag' => 'odd',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => $parity
		];
		$tag_pair = [
			'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
			'oldtag' => 'component',
			'newtag' => 'component'
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			$service_down['formulaid'] = 'A';
			$new_type['formulaid'] = 'B';
			$new_odd['formulaid'] = 'C';
			$tag_pair['formulaid'] = 'D';
			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'A and B and C and D',
				'conditions' => [$service_down, $new_type, $new_odd, $tag_pair]
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$service_down, $new_odd, $tag_pair]
			];
		}

		return [
			'name' => $name,
			'filter' => $filter,
			'operations' => [
				['type' => ZBX_CORR_OPERATION_CLOSE_OLD],
				['type' => ZBX_CORR_OPERATION_CLOSE_NEW]
			]
		];
	}

	/**
	 * Build a global event correlation rule whose only condition is an old-event odd=$parity match, with
	 * CLOSE_OLD + CLOSE_NEW operations. There is no component tag pair, so CLOSE_OLD is unrestricted: a
	 * single matching new event closes every open problem of that parity across all components at once
	 * (not just the same component's). Keeping the discriminator on the OLD event preserves parity
	 * selectivity — an odd rule never touches even problems and vice-versa.
	 */
	private function buildParityCloseAllCorrelationParams(string $name, string $parity, $evaltype): array {
		$old_odd = [
			'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
			'tag' => 'odd',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => $parity
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			$old_odd['formulaid'] = 'A';
			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'A',
				'conditions' => [$old_odd]
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$old_odd]
			];
		}

		return [
			'name' => $name,
			'filter' => $filter,
			'operations' => [
				['type' => ZBX_CORR_OPERATION_CLOSE_OLD],
				['type' => ZBX_CORR_OPERATION_CLOSE_NEW]
			]
		];
	}

	/**
	 * Build a global event correlation rule that closes an old "down" problem when a new "up" problem
	 * with the same sequence arrives. Unlike the parity/service rules that correlate a re-sent "down",
	 * here the closing event is itself a PROBLEM ("up_N", so the trigger expression must match "up" too):
	 *   - new event state="up"
	 *   - tag pair service=service (the trailing number, so "up_1" pairs with "down_1")
	 * CLOSE_OLD closes the paired "down" problem and CLOSE_NEW closes the "up" problem itself. No old-event
	 * state="down" condition is needed: correlation only matches open problems and every "up" closes itself
	 * via CLOSE_NEW, so the only open problem sharing a given service number is always its "down".
	 *
	 * CONDITION_EVAL_TYPE_OR is a special case: OR-ing the new state="up" condition with the tag pair would
	 * match every open problem (the state="up" condition alone is true for any "up" event), closing them all
	 * at once instead of the paired one. The OR variant therefore keeps only the service tag pair; with a
	 * single condition OR is equivalent to AND, so the 1:1 close-on-up pairing is preserved. The other
	 * evaltypes AND the two conditions (AND_OR because they are of distinct types), giving identical
	 * behaviour.
	 */
	private function buildCloseOnUpCorrelationParams(string $name, $evaltype): array {
		$new_up = [
			'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
			'tag' => 'state',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => 'up'
		];
		$tag_pair = [
			'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
			'oldtag' => 'service',
			'newtag' => 'service'
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			$new_up['formulaid'] = 'A';
			$tag_pair['formulaid'] = 'B';
			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'A and B',
				'conditions' => [$new_up, $tag_pair]
			];
		}
		elseif ($evaltype == CONDITION_EVAL_TYPE_OR) {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$tag_pair]
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$new_up, $tag_pair]
			];
		}

		return [
			'name' => $name,
			'filter' => $filter,
			'operations' => [
				['type' => ZBX_CORR_OPERATION_CLOSE_OLD],
				['type' => ZBX_CORR_OPERATION_CLOSE_NEW]
			]
		];
	}

	/**
	 * Build the complex event processing (CEP) rule that drives the close-on-up scenario through a tag
	 * correlation time window instead of a global event correlation rule (buildCloseOnUpCorrelationParams()).
	 *
	 * The rule matches the problem events of these scenarios only - its single filter condition requires the
	 * 'state' tag, which only the close-on-up trigger prototypes add - and puts every matched event into a
	 * "Tag correlation" (WINDOW_TAG_MATCH) window keyed on the 'service' tag, so a "down_N" event and the
	 * "up_N" event pairing with it (even when the "up" is raised on another trigger) belong to the same
	 * window. The window is one hour long with no capacity limit, so nothing is evicted during the run.
	 *
	 * The operations are:
	 *   - Execute when "Event added": "Close window", restricted to the "up" events by its condition;
	 *   - Execute when "Window closed": "Close".
	 *
	 * The new event is already in the window when the close-window operation runs, so an event closes the
	 * window it just entered and "Window closed" then closes every event that window held. Restricting the
	 * close-window operation to the "up" events is therefore what reproduces the close-on-up behaviour of the
	 * global correlation rule: a "down_N" event only accumulates in the window of its 'service' id, and the
	 * "up_N" event closes that window, closing both the paired "down_N" problem (like CLOSE_OLD) and itself
	 * (like CLOSE_NEW). Without the condition every event would close the window it just entered, so every
	 * problem would be closed the moment it opened.
	 *
	 * The condition comes in two flavours, so both ways of writing it are covered:
	 *   - $tag_exists_condition = false: a tag value comparison on the 'state' tag (state Equals "up");
	 *   - $tag_exists_condition = true: a tag-exists condition on CEP_STATE_TAG_UP, the tag the prototypes
	 *     only produce for "up" events because its name is built from {ITEM.VALUE}.
	 *
	 * $window_type gives the rule a cause and symptom grouping window (WINDOW_CAUSE_SYMPTOM) instead of the tag
	 * correlation one. Both group by the same 'service' tag and both close a window on an "up" event, so the
	 * scenario runs identically either way; a cause and symptom window additionally ranks what it holds - the
	 * "down_N" event that opened the window is its cause and the "up_N" event that ends it a symptom of that
	 * cause - and counts the symptoms of a group in the CEP_TAG_SYMPTOM_COUNT tag of its cause, which is what
	 * waitForCloseOnUpCauseSymptomRanking() asserts.
	 */
	private function buildCloseOnUpCepRuleParams(string $name, bool $tag_exists_condition = false,
			int $window_type = CCepRuleHelper::WINDOW_TAG_MATCH): array {
		$close_window_condition = $tag_exists_condition
			? self::buildUpEventOperationCondition()
			: [
				'type' => CCepRuleHelper::CONDITION_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'tag' => 'state',
				'value' => 'up'
			];

		$window = [
			'duration' => '1h',
			'capacity' => 0,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => ['service']
		];

		// Only a cause and symptom window ranks its events, so only it has a count of them to write.
		if ($window_type == CCepRuleHelper::WINDOW_CAUSE_SYMPTOM) {
			$window['event_count_tag'] = self::CEP_TAG_SYMPTOM_COUNT;
		}

		return [
			'name' => $name,
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'type' => CCepRuleHelper::CONDITION_TAG,
						'operator' => CONDITION_OPERATOR_EXISTS,
						'tag' => 'state',
						'tag_value' => ''
					]
				]
			],
			'window_type' => $window_type,
			'window' => $window,
			'operations' => [
				[
					'sortorder' => 0,
					'execute_when' => CCepRuleHelper::WHEN_EVENT_ADDED,
					'type' => CCepRuleHelper::OP_CLOSE_WINDOW,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [$close_window_condition]
					]
				],
				[
					'sortorder' => 1,
					'execute_when' => CCepRuleHelper::WHEN_WINDOW_CLOSED,
					'type' => CCepRuleHelper::OP_CLOSE_EVENT
				]
			]
		];
	}

	/**
	 * Build a complex event processing (CEP) rule without a window (WINDOW_NONE): $operations are applied to
	 * every event matching its filter, right when the event occurs (WHEN_EVENT_OCCURRED is the only execution
	 * point a windowless rule has). No window is opened and no problem is closed, so the matched events are
	 * left open and only modified by the operations.
	 *
	 * $match_conditions is either a single filter condition, a list of them or an empty list (matching every
	 * event of the scenario), singling out the events this rule acts on: the scenario builds one rule per
	 * operator with a condition from buildWindowNoneServiceCondition() (testing the event tags),
	 * buildWindowNoneEventNameCondition() (the event name), buildWindowNoneSeverityCondition(),
	 * buildWindowNoneHostCondition(), buildWindowNoneHostGroupCondition() or
	 * buildWindowNoneTimePeriodCondition(), plus four rules combining several of them under a specific
	 * $evaltype.
	 *
	 * With the default CONDITION_EVAL_TYPE_AND the filter additionally gets a 'type' Equals "cep" condition,
	 * restricting the rule to the events of the primary discovered trigger prototype - without it the negative
	 * flavours ("Does not equal", "Does not contain", "Does not exist") would also match every event that has
	 * no 'service' tag at all (a comparison against a missing tag is false, and the negation makes it true),
	 * i.e. the events of every other host and trigger in the suite. Under any other evaltype that guard would
	 * change what the rule means - it would be OR-ed with the conditions under CONDITION_EVAL_TYPE_OR, and
	 * joined with the same-type ones into their OR group under CONDITION_EVAL_TYPE_AND_OR - so it is left out
	 * and those rules restrict themselves instead, by only ever matching an event that carries the scenario's
	 * 'service' tag.
	 */
	private function buildWindowNoneCepRuleParams(string $name, array $match_conditions, array $operations,
			int $evaltype = CONDITION_EVAL_TYPE_AND, string $formula = '', ?int $window_type = null,
			array $window = []): array {
		// A single condition may be passed as is, without wrapping it in a list.
		$conditions = array_key_exists('type', $match_conditions) ? [$match_conditions] : $match_conditions;

		if ($evaltype == CONDITION_EVAL_TYPE_AND) {
			array_unshift($conditions, [
				'type' => CCepRuleHelper::CONDITION_TAG_VALUE,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'tag' => 'type',
				'tag_value' => 'cep'
			]);
		}

		return [
			'name' => $name,
			'filter' => [
				'evaltype' => $evaltype,
				// Only a custom expression has a formula; every other evaltype must leave it at its default.
				'formula' => $formula,
				'conditions' => $conditions
			],
			'window_type' => $window_type === null ? CCepRuleHelper::WINDOW_NONE : $window_type,
			// Every rule must be evaluated for every event, so none of them may stop the processing of the
			// rules after it.
			'stop' => CCepRuleHelper::EXECUTION_CONTINUE,
			'operations' => $operations
		] + ($window_type === null ? [] : ['window' => $window]);
	}

	/**
	 * The window the windowed flavours of the scenario give their rules, the same for both window types:
	 * grouped by the 'service' tag, so every id the scenario sends gets a window of its own.
	 */
	private function buildWindowOperationsWindow(): array {
		return [
			'duration' => self::CEP_RULE_WINDOW_OPS_DURATION,
			'capacity' => 0,
			'group_by_host_group' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_host' => CCepRuleHelper::GROUP_BY_NO,
			'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
			'tags' => ['service']
		];
	}

	/**
	 * Build a windowless rule whose single operation adds the $add_tag:$add_tag_value tag to every event its
	 * filter matches. This is the shape of every rule of the operator coverage set (see getWindowNoneRules()):
	 * the tag names the rule that matched, the value the operand it tested against.
	 */
	private function buildWindowNoneAddTagCepRuleParams(string $name, array $match_conditions, string $add_tag,
			string $add_tag_value, int $evaltype = CONDITION_EVAL_TYPE_AND, string $formula = '',
			?int $window_type = null, array $window = []): array {
		$operations = [
			[
				'sortorder' => 0,
				'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
				'type' => CCepRuleHelper::OP_ADD_TAG,
				'tag' => $add_tag,
				'tag_value' => $add_tag_value
			]
		];

		return $this->buildWindowNoneCepRuleParams($name, $match_conditions, $operations, $evaltype, $formula,
			$window_type, $window
		);
	}

	/**
	 * Build the filter condition a windowless rule uses to single out the events of one 'service' id through
	 * the event tags. $service is always a plain id; how it is tested depends on the operator, exactly like in
	 * the CEP rule form:
	 *   - CONDITION_OPERATOR_EXISTS / CONDITION_OPERATOR_NOT_EXISTS test a tag NAME, so they get a
	 *     CONDITION_TAG condition on the per-id 'service_<id>' tag (CEP_SERVICE_TAG resolves the id into the
	 *     tag name at event time);
	 *   - every other operator compares the value of the plain 'service' tag, so it gets a
	 *     CONDITION_TAG_VALUE condition. CONDITION_TAG would not do: it matches on the tag name only (the
	 *     server evaluates it as tag exists / does not exist and never looks at tag_value), which is why the
	 *     form converts a "Tag" condition with a value operator to CONDITION_TAG_VALUE before handing it to
	 *     the API.
	 */
	private function buildWindowNoneServiceCondition(int $operator, string $service): array {
		if (in_array($operator, [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS])) {
			return [
				'type' => CCepRuleHelper::CONDITION_TAG,
				'operator' => $operator,
				'tag' => self::CEP_SERVICE_TAG_PREFIX.$service,
				'tag_value' => ''
			];
		}

		return [
			'type' => CCepRuleHelper::CONDITION_TAG_VALUE,
			'operator' => $operator,
			'tag' => 'service',
			'tag_value' => $service
		];
	}

	/**
	 * Build the filter condition a windowless rule uses to single out events by their NAME rather than by
	 * their tags. The event name of these prototypes is "CEP trigger <component> <item value>", so it carries
	 * the same id the tag conditions test - $event_name is the whole name for the Equals flavours and just the
	 * item value for the Contains ones.
	 */
	private function buildWindowNoneEventNameCondition(int $operator, string $event_name): array {
		return [
			'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
			'operator' => $operator,
			'event_name' => $event_name
		];
	}

	/**
	 * Build the filter condition a windowless rule uses to test the event SEVERITY. Unlike the id conditions
	 * this one does not tell the events of the scenario apart - every trigger prototype of the suite has
	 * DISASTER priority - so it is either true for all of them or for none, which is exactly what the severity
	 * rules are there to check.
	 */
	private function buildWindowNoneSeverityCondition(int $operator, int $severity): array {
		return [
			'type' => CCepRuleHelper::CONDITION_SEVERITY,
			'operator' => $operator,
			'severity' => $severity
		];
	}

	/**
	 * Build the filter condition a windowless rule uses to test the HOST of the event. Like the severity one
	 * it cannot tell the events of the scenario apart - they all come from the one discovered host - so it is
	 * either true for all of them or for none.
	 */
	private function buildWindowNoneHostCondition(int $operator, string $host): array {
		return [
			'type' => CCepRuleHelper::CONDITION_HOST,
			'operator' => $operator,
			'host' => $host
		];
	}

	/**
	 * Build the filter condition a windowless rule uses to test the HOST GROUP of the event. The host group
	 * rules mirror the host ones - the discovered host is in a single group, so the condition holds either for
	 * every event of the scenario or for none.
	 */
	private function buildWindowNoneHostGroupCondition(int $operator, string $host_group): array {
		return [
			'type' => CCepRuleHelper::CONDITION_HOST_GROUP,
			'operator' => $operator,
			'host_group' => $host_group
		];
	}

	/**
	 * Build the filter condition a windowless rule uses to test when the event occurred. The scenario tests
	 * against the all-the-time period, so - like the severity, host and host group conditions - it holds
	 * either for every event or, negated, for none, no matter when the suite is run.
	 */
	private function buildWindowNoneTimePeriodCondition(int $operator, string $time_period): array {
		return [
			'type' => CCepRuleHelper::CONDITION_TIME_PERIOD,
			'operator' => $operator,
			'time_period' => $time_period
		];
	}

	/**
	 * The name of the host group the discovered host belongs to, resolved from the host itself rather than
	 * hardcoded so the host group rules always test against the group the host prototype actually put it in.
	 * The negative flavours require the host to be in that one group only (they must hold for none of its
	 * events), which is asserted here. Cached for the lifetime of the test.
	 */
	private function getDiscHostGroupName(): string {
		if ($this->disc_hostgroup_name === null) {
			$response = $this->call('hostgroup.get', [
				'hostids' => [self::$disc_hostid],
				'output' => ['name']
			]);
			$this->assertCount(1, $response['result'],
				'Expected the discovered host to be in exactly one host group: '.json_encode($response));

			$this->disc_hostgroup_name = $response['result'][0]['name'];
		}

		return $this->disc_hostgroup_name;
	}

	/**
	 * The rule set of the windowless scenario: one rule per operator, keyed by the tag it adds to the events
	 * it matched, holding that rule's filter condition and the operand it tests against. The operand doubles
	 * as the value of the added tag, so a tagged event states both which rule matched it and with which
	 * operand, and the rule is named after its tag (see prepareDataCepWindowNoneTagOperations()).
	 *
	 * This is the single definition of the rule set: prepareDataCepWindowNoneTagOperations() creates the rules
	 * from it and waitForCepWindowNoneTaggedEvents() takes both the expected tag values and the list of tags
	 * no non-matching rule may have added from the very same table.
	 *
	 * Twelve of the operators come in opposite pairs testing the 'service' id - four pairs through the event
	 * tags, two through the event name, which ends with the item value - so every event is matched by exactly
	 * one rule of every pair. The name conditions mirror the tag ones: Equals compares the whole event name of
	 * the "down_1" event, Contains only the "down_1" item value inside it. The next fourteen test the event
	 * severity, host, host group and time, none of which differs between the events of these prototypes, so
	 * they do not tell the ids apart - they hold for all of them or for none.
	 *
	 * The last four entries carry a third element, the evaltype their conditions are combined under, and the
	 * custom expression one a fourth, its formula; the rest are single conditions and default to
	 * CONDITION_EVAL_TYPE_AND.
	 */
	private function getWindowNoneRules(): array {
		$service = self::CEP_RULE_WINDOW_NONE_SERVICE;
		$service_next = self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT;
		$service_last = self::CEP_RULE_WINDOW_NONE_SERVICE_LAST;
		$event_name = self::CEP_RULE_WINDOW_NONE_EVENT_NAME;
		$value_next = self::CEP_RULE_WINDOW_NONE_VALUE_NEXT;
		$value_last = self::CEP_RULE_WINDOW_NONE_VALUE_LAST;
		$host_group = $this->getDiscHostGroupName();
		$host_group_absent = $host_group.self::CEP_RULE_WINDOW_NONE_ABSENT_SUFFIX;
		$time_period = self::CEP_RULE_WINDOW_NONE_TIME_PERIOD;

		return [
			self::CEP_TAG_SERVICE_EQUALS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_EQUAL, $service), $service
			],
			self::CEP_TAG_SERVICE_NOT_EQUALS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_NOT_EQUAL, $service), $service
			],
			self::CEP_TAG_SERVICE_CONTAINS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_LIKE, $service), $service
			],
			self::CEP_TAG_SERVICE_NOT_CONTAINS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_NOT_LIKE, $service), $service
			],
			self::CEP_TAG_SERVICE_MORE_EQUAL => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_MORE_EQUAL, $service_next),
				$service_next
			],
			self::CEP_TAG_SERVICE_LESS_EQUAL => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_LESS_EQUAL, $service), $service
			],
			self::CEP_TAG_SERVICE_EXISTS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_EXISTS, $service), $service
			],
			self::CEP_TAG_SERVICE_NOT_EXISTS => [
				$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_NOT_EXISTS, $service), $service
			],
			self::CEP_TAG_EVENT_NAME_EQUALS => [
				$this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_EQUAL, $event_name), $event_name
			],
			self::CEP_TAG_EVENT_NAME_NOT_EQUALS => [
				$this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_NOT_EQUAL, $event_name), $event_name
			],
			self::CEP_TAG_EVENT_NAME_CONTAINS => [
				$this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_LIKE, $value_next), $value_next
			],
			self::CEP_TAG_EVENT_NAME_NOT_CONTAINS => [
				$this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_NOT_LIKE, $value_next), $value_next
			],
			// Every event of these prototypes has DISASTER severity, so the first, third and fourth rule match
			// all of them and the second one - the negation of a condition that always holds - must match none.
			self::CEP_TAG_SEVERITY_EQUALS => [
				$this->buildWindowNoneSeverityCondition(CONDITION_OPERATOR_EQUAL, TRIGGER_SEVERITY_DISASTER),
				(string) TRIGGER_SEVERITY_DISASTER
			],
			self::CEP_TAG_SEVERITY_NOT_EQUALS => [
				$this->buildWindowNoneSeverityCondition(CONDITION_OPERATOR_NOT_EQUAL, TRIGGER_SEVERITY_DISASTER),
				(string) TRIGGER_SEVERITY_DISASTER
			],
			self::CEP_TAG_SEVERITY_MORE_EQUAL => [
				$this->buildWindowNoneSeverityCondition(CONDITION_OPERATOR_MORE_EQUAL, TRIGGER_SEVERITY_HIGH),
				(string) TRIGGER_SEVERITY_HIGH
			],
			self::CEP_TAG_SEVERITY_LESS_EQUAL => [
				$this->buildWindowNoneSeverityCondition(CONDITION_OPERATOR_LESS_EQUAL, TRIGGER_SEVERITY_DISASTER),
				(string) TRIGGER_SEVERITY_DISASTER
			],
			// Every event comes from the one discovered host, so only the first rule can match: the second is
			// the negation of a condition that always holds, the third looks for a name the host does not
			// contain and the fourth requires the host name not to contain itself.
			self::CEP_TAG_HOST_EQUALS => [
				$this->buildWindowNoneHostCondition(CONDITION_OPERATOR_EQUAL, self::HOST_DISC_VALUE),
				self::HOST_DISC_VALUE
			],
			self::CEP_TAG_HOST_NOT_EQUALS => [
				$this->buildWindowNoneHostCondition(CONDITION_OPERATOR_NOT_EQUAL, self::HOST_DISC_VALUE),
				self::HOST_DISC_VALUE
			],
			self::CEP_TAG_HOST_CONTAINS => [
				$this->buildWindowNoneHostCondition(CONDITION_OPERATOR_LIKE,
					self::CEP_RULE_WINDOW_NONE_HOST_ABSENT),
				self::CEP_RULE_WINDOW_NONE_HOST_ABSENT
			],
			self::CEP_TAG_HOST_NOT_CONTAINS => [
				$this->buildWindowNoneHostCondition(CONDITION_OPERATOR_NOT_LIKE, self::HOST_DISC_VALUE),
				self::HOST_DISC_VALUE
			],
			// The discovered host is in a single host group, so these four split exactly like the host ones:
			// only "equals" can match.
			self::CEP_TAG_HOST_GROUP_EQUALS => [
				$this->buildWindowNoneHostGroupCondition(CONDITION_OPERATOR_EQUAL, $host_group), $host_group
			],
			self::CEP_TAG_HOST_GROUP_NOT_EQUALS => [
				$this->buildWindowNoneHostGroupCondition(CONDITION_OPERATOR_NOT_EQUAL, $host_group), $host_group
			],
			self::CEP_TAG_HOST_GROUP_CONTAINS => [
				$this->buildWindowNoneHostGroupCondition(CONDITION_OPERATOR_LIKE, $host_group_absent),
				$host_group_absent
			],
			self::CEP_TAG_HOST_GROUP_NOT_CONTAINS => [
				$this->buildWindowNoneHostGroupCondition(CONDITION_OPERATOR_NOT_LIKE, $host_group), $host_group
			],
			// The period covers every moment, so the first rule matches whenever the scenario runs and the
			// second one - "not in all the time" - can never match.
			self::CEP_TAG_TIME_PERIOD_IN => [
				$this->buildWindowNoneTimePeriodCondition(CONDITION_OPERATOR_IN, $time_period), $time_period
			],
			self::CEP_TAG_TIME_PERIOD_NOT_IN => [
				$this->buildWindowNoneTimePeriodCondition(CONDITION_OPERATOR_NOT_IN, $time_period), $time_period
			],
			// The three rules combining conditions of their own, one per evaltype. Each one picks a different
			// set of ids, and picks it only because its conditions are combined the way its evaltype says:
			//   - AND: "contains 0" holds for "0" and "10", "does not equal 0" for "1" and "10", so only "10"
			//     satisfies both. Under AND_OR (both conditions are of the same type, hence OR-ed) all three
			//     ids would be tagged.
			//   - OR: neither half holds for "1" - its id is not "0" and its event name does not contain
			//     "down_10" - while "0" satisfies the first and "10" the second. Under AND or AND_OR the two
			//     conditions are of distinct types and would be AND-ed, tagging nothing.
			//   - AND_OR: the two same-type id conditions are OR-ed into one group ("0" or "1") and the
			//     severity condition, being of another type, is AND-ed with it. Under AND nothing would match
			//     ("0" and "1" cannot both hold), under OR every event would ("severity equals disaster"
			//     alone is enough).
			// The OR and AND_OR rules get no 'type' Equals "cep" guard - it would be OR-ed in and change what
			// they mean - and need none: every branch of theirs is a positive comparison against the 'service'
			// tag or the event name, which no event outside this scenario satisfies. The AND rule keeps the
			// guard like the single-condition rules, since AND-ing one more condition changes nothing.
			self::CEP_TAG_SERVICE_AND => [
				[
					$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_LIKE, $service),
					$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_NOT_EQUAL, $service)
				],
				$service,
				CONDITION_EVAL_TYPE_AND
			],
			self::CEP_TAG_SERVICE_OR => [
				[
					$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_EQUAL, $service),
					$this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_LIKE, $value_last)
				],
				$service.','.$service_last,
				CONDITION_EVAL_TYPE_OR
			],
			self::CEP_TAG_SERVICE_AND_OR => [
				[
					$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_EQUAL, $service),
					$this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_EQUAL, $service_next),
					$this->buildWindowNoneSeverityCondition(CONDITION_OPERATOR_EQUAL, TRIGGER_SEVERITY_DISASTER)
				],
				$service.','.$service_next,
				CONDITION_EVAL_TYPE_AND_OR
			],
			// The fourth combining rule groups its conditions with the custom expression "A and (B or C)",
			// which none of the other evaltypes can express: it OR-s two conditions of DISTINCT types (the id
			// "0" and the "down_10" event name) and AND-s the severity with the result, so it tags "0" and
			// "10". AND_OR would AND all three (three distinct types, so no OR group at all) and tag nothing,
			// plain AND the same, plain OR would tag every event through the severity condition alone.
			self::CEP_TAG_SERVICE_EXPRESSION => [
				[
					['formulaid' => 'A'] + $this->buildWindowNoneSeverityCondition(CONDITION_OPERATOR_EQUAL,
						TRIGGER_SEVERITY_DISASTER),
					['formulaid' => 'B'] + $this->buildWindowNoneServiceCondition(CONDITION_OPERATOR_EQUAL,
						$service),
					['formulaid' => 'C'] + $this->buildWindowNoneEventNameCondition(CONDITION_OPERATOR_LIKE,
						$value_last)
				],
				$service.','.$service_last,
				CONDITION_EVAL_TYPE_EXPRESSION,
				self::CEP_RULE_WINDOW_NONE_FORMULA
			]
		];
	}

	/**
	 * The tag operations of the windowless scenario, as a list of independent cases. Every case holds the
	 * operations it needs, the trigger prototype tags they work on (if any) and the tag state they must leave
	 * on the event, so what an operation does and what it is expected to produce stay side by side; each case
	 * works on tag names of its own, so the cases cannot influence one another. The 'trigger_tags' of the
	 * cases are collected by getWindowNoneTagOperationTriggerTags() and added to both trigger prototypes.
	 *
	 * All of them run in one CEP rule, the "tag operations" one: the operations of a rule execute in
	 * sortorder, so flattening the cases in order gives a deterministic sequence, and the rule's filter is
	 * just the 'type' Equals "cep" guard, so every problem event of the scenario goes through all of them.
	 *
	 * The cases cover every tag operation a windowless rule can perform, including the cases where the server
	 * must leave the event alone:
	 *   - "add tag" adds a tag;
	 *   - "set tag" adds one when the name is free and overwrites the value when it is taken;
	 *   - "set tag value" only updates an existing tag - on a name no tag has it must do nothing;
	 *   - "increase" / "decrease tag value" shift a numeric value by one (the operation's value is not an
	 *     increment, hence "10" turning into "11" and "9"), and leave a non-numeric value untouched;
	 *   - "rename tag" moves the value to another tag name, leaving no tag under the old one;
	 *   - "remove tag" drops a tag.
	 *
	 * The first cases build the tag they act on with an operation of their own; the next ones act on tags the
	 * trigger put on the event, which is what the operations are for in practice - overwriting, renaming and
	 * dropping the tags an event arrives with.
	 *
	 * The last cases give the operations macros where an operation may hold one - in its tag name as well as in its
	 * tag value, both of which the server resolves against the event the operation acts on - so what they expect is
	 * the resolved tag and never the macro text. Every form an operation resolves gets a case of its own, since each
	 * of them is a distinct token to the server: a plain user macro of the template the event's host is linked to
	 * (see prepareWindowOperationMacros()), the same macro inside a longer value, a tag named through a macro that a
	 * second operation then has to find under that same name, a user macro with a context (which must win over the
	 * plain macro of the same name), a macro function over a user macro, and the event's own macros - {HOST.HOST},
	 * its indexed form and a macro function over it. An expression macro is not among them on purpose: tags are
	 * resolved without the expression macro search, and the event name is where that form is covered instead (see
	 * getWindowNoneEventOperationCase()).
	 */
	private function getWindowNoneTagOperationCases(): array {
		return [
			[
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_add', 'tag_value' => 'added']]
				],
				'expected' => ['op_add' => 'added']
			],
			[
				// The tag name is free, so "set tag" adds it.
				'operations' => [
					[CCepRuleHelper::OP_SET_TAG, ['tag' => 'op_set', 'tag_value' => 'created']]
				],
				'expected' => ['op_set' => 'created']
			],
			[
				// The tag already exists, so "set tag" overwrites its value instead of adding a second one.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_set_existing', 'tag_value' => 'before']],
					[CCepRuleHelper::OP_SET_TAG, ['tag' => 'op_set_existing', 'tag_value' => 'after']]
				],
				'expected' => ['op_set_existing' => 'after']
			],
			[
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_set_value', 'tag_value' => 'before']],
					[CCepRuleHelper::OP_SET_TAG_VALUE, ['tag' => 'op_set_value', 'tag_value' => 'after']]
				],
				'expected' => ['op_set_value' => 'after']
			],
			[
				// "set tag value" updates an existing tag only, so with no tag of that name it must not add
				// one - unlike "set tag" above.
				'operations' => [
					[CCepRuleHelper::OP_SET_TAG_VALUE, ['tag' => 'op_set_value_missing', 'tag_value' => 'after']]
				],
				'expected' => ['op_set_value_missing' => null]
			],
			[
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_increase', 'tag_value' => '10']],
					[CCepRuleHelper::OP_INCREASE_TAG_VALUE, ['tag' => 'op_increase']]
				],
				'expected' => ['op_increase' => '11']
			],
			[
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_decrease', 'tag_value' => '10']],
					[CCepRuleHelper::OP_DECREASE_TAG_VALUE, ['tag' => 'op_decrease']]
				],
				'expected' => ['op_decrease' => '9']
			],
			[
				// A value that is not a number cannot be shifted, so the tag keeps it.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_increase_text', 'tag_value' => 'text']],
					[CCepRuleHelper::OP_INCREASE_TAG_VALUE, ['tag' => 'op_increase_text']]
				],
				'expected' => ['op_increase_text' => 'text']
			],
			[
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_rename', 'tag_value' => 'kept']],
					[CCepRuleHelper::OP_RENAME_TAG, ['tag' => 'op_rename', 'new_tag' => 'op_renamed']]
				],
				'expected' => ['op_rename' => null, 'op_renamed' => 'kept']
			],
			[
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_remove', 'tag_value' => 'gone']],
					[CCepRuleHelper::OP_REMOVE_TAG, ['tag' => 'op_remove']]
				],
				'expected' => ['op_remove' => null]
			],
			// The cases below work on tags the trigger itself put on the event rather than on tags an earlier
			// operation of this very rule added, so the operations are checked against tags that already exist
			// when the event reaches CEP. Their 'trigger_tags' are added to the trigger prototypes by
			// prepareDataCepWindowNoneTagOperations().
			[
				'trigger_tags' => [['tag' => 'op_trigger_set', 'value' => 'from_trigger']],
				'operations' => [
					[CCepRuleHelper::OP_SET_TAG, ['tag' => 'op_trigger_set', 'tag_value' => 'from_rule']]
				],
				'expected' => ['op_trigger_set' => 'from_rule']
			],
			[
				'trigger_tags' => [['tag' => 'op_trigger_value', 'value' => 'from_trigger']],
				'operations' => [
					[CCepRuleHelper::OP_SET_TAG_VALUE, ['tag' => 'op_trigger_value', 'tag_value' => 'from_rule']]
				],
				'expected' => ['op_trigger_value' => 'from_rule']
			],
			[
				'trigger_tags' => [['tag' => 'op_trigger_counter', 'value' => '10']],
				'operations' => [
					[CCepRuleHelper::OP_INCREASE_TAG_VALUE, ['tag' => 'op_trigger_counter']]
				],
				'expected' => ['op_trigger_counter' => '11']
			],
			[
				'trigger_tags' => [['tag' => 'op_trigger_rename', 'value' => 'kept']],
				'operations' => [
					[CCepRuleHelper::OP_RENAME_TAG, ['tag' => 'op_trigger_rename',
						'new_tag' => 'op_trigger_renamed'
					]]
				],
				'expected' => ['op_trigger_rename' => null, 'op_trigger_renamed' => 'kept']
			],
			[
				'trigger_tags' => [['tag' => 'op_trigger_remove', 'value' => 'gone']],
				'operations' => [
					[CCepRuleHelper::OP_REMOVE_TAG, ['tag' => 'op_trigger_remove']]
				],
				'expected' => ['op_trigger_remove' => null]
			],
			// The cases below give the operations macros instead of the plain strings the ones above use: an
			// operation resolves both its tag name and its tag value against the event it acts on, so the tag a
			// case is expected to leave behind is the resolved one - a macro the server did not resolve stays in
			// the event as the macro text and is caught as a wrong tag name or a wrong tag value. The user macros
			// they use are put on the template of the discovered host by prepareWindowOperationMacros().
			[
				// The value is a macro of its own, so the tag carries what the macro resolves to.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_macro_value',
						'tag_value' => self::CEP_OP_TAG_VALUE_MACRO
					]]
				],
				'expected' => ['op_macro_value' => self::CEP_OP_TAG_VALUE]
			],
			[
				// A macro in the middle of a value: only the macro is replaced and the text around it is kept, so
				// this fails both when the macro is left unresolved and when the whole value is thrown away.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_macro_in_text',
						'tag_value' => 'value '.self::CEP_OP_TAG_VALUE_MACRO.' end'
					]]
				],
				'expected' => ['op_macro_in_text' => 'value '.self::CEP_OP_TAG_VALUE.' end']
			],
			[
				// The tag NAME comes from a macro, so the tag the event ends up with is named after what the macro
				// resolved to and no tag named after the macro text itself may exist. The second operation names
				// the tag through the same macro, which is what a later operation has to do to find it again: it
				// only updates a tag that exists, so a name resolving to anything else would leave the value of
				// the first operation in place.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => self::CEP_OP_TAG_NAME_MACRO,
						'tag_value' => 'named_by_macro'
					]],
					[CCepRuleHelper::OP_SET_TAG_VALUE, ['tag' => self::CEP_OP_TAG_NAME_MACRO,
						'tag_value' => 'set_by_macro'
					]]
				],
				'expected' => [self::CEP_OP_TAG_NAME => 'set_by_macro', self::CEP_OP_TAG_NAME_MACRO => null]
			],
			[
				// The same macro name with a context: the context macro exists, so it is what the operation must
				// get - the plain macro of that name (asserted by the first case above) may not be used instead.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_macro_context',
						'tag_value' => self::CEP_OP_TAG_VALUE_CONTEXT_MACRO
					]]
				],
				'expected' => ['op_macro_context' => self::CEP_OP_TAG_VALUE_CONTEXT]
			],
			[
				// A macro function over that user macro: the macro is resolved first and the function applied to
				// what it resolved to, so the value only comes out in capitals when both steps happened.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_macro_function',
						'tag_value' => self::CEP_OP_TAG_VALUE_FUNC_MACRO
					]]
				],
				'expected' => ['op_macro_function' => strtoupper(self::CEP_OP_TAG_VALUE)]
			],
			[
				// Not a user macro but a macro of the event itself, which an operation resolves as well: every
				// event of the scenario is from the one discovered host, so all of them carry its name here.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_macro_host',
						'tag_value' => self::CEP_OP_HOST_MACRO
					]]
				],
				'expected' => ['op_macro_host' => self::HOST_DISC_VALUE]
			],
			[
				// The indexed form of that macro, which is a token of its own: the index picks the item of the
				// trigger expression the host is taken from, and these triggers are built on a single item, so the
				// first index is the host of the event again.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_macro_host_indexed',
						'tag_value' => self::CEP_OP_HOST_INDEXED_MACRO
					]]
				],
				'expected' => ['op_macro_host_indexed' => self::HOST_DISC_VALUE]
			],
			[
				// A macro function over an event macro rather than over a user macro - the same two steps, reached
				// through a different token.
				'operations' => [
					[CCepRuleHelper::OP_ADD_TAG, ['tag' => 'op_macro_host_function',
						'tag_value' => self::CEP_OP_HOST_FUNC_MACRO
					]]
				],
				'expected' => ['op_macro_host_function' => strtoupper(self::HOST_DISC_VALUE)]
			]
		];
	}

	/**
	 * The extra trigger prototype tags the tag operation cases need: the tags their operations modify, remove
	 * or rename on events that already carry them when CEP sees them, as opposed to the tags an operation of
	 * the rule itself adds first.
	 */
	/**
	 * Every extra tag the trigger prototypes of the windowless and windowed flavours carry, kept in one place
	 * so all of them discover the same triggers and switching between them does not re-discover anything:
	 *   - CEP_SERVICE_TAG, whose name carries the 'service' id, for the tag exists conditions;
	 *   - CEP_STATE_TAG, whose name carries the "down"/"up" state, so an "up" event can be singled out by a
	 *     plain tag exists condition on CEP_STATE_TAG_UP instead of by comparing the value of a 'state' tag;
	 *   - the tags the tag operations work on, see getWindowNoneTagOperationCases().
	 */
	private function getWindowOperationsTriggerTags(): array {
		return array_merge(
			[
				['tag' => self::CEP_SERVICE_TAG, 'value' => ''],
				['tag' => self::CEP_STATE_TAG, 'value' => '']
			],
			$this->getWindowNoneTagOperationTriggerTags()
		);
	}

	private function getWindowNoneTagOperationTriggerTags(): array {
		$tags = [];

		foreach ($this->getWindowNoneTagOperationCases() as $case) {
			if (array_key_exists('trigger_tags', $case)) {
				$tags = array_merge($tags, $case['trigger_tags']);
			}
		}

		return $tags;
	}

	/**
	 * The operations of every getWindowNoneTagOperationCases() case, flattened into the operation list of the
	 * single rule that performs them. They are numbered in the order the cases list them, which is the order
	 * the server executes them in.
	 */
	private function getWindowNoneTagOperations(): array {
		return $this->buildWindowNoneOperations($this->getWindowNoneTagOperationOperations());
	}

	/**
	 * The [operation type, operation parameters] pairs of every getWindowNoneTagOperationCases() case, in the
	 * order the cases list them and not yet numbered - the windowed flavour of the scenario appends the event
	 * operations to them before numbering, since it runs both sets from one rule.
	 */
	private function getWindowNoneTagOperationOperations(): array {
		$operations = [];

		foreach ($this->getWindowNoneTagOperationCases() as $case) {
			$operations = array_merge($operations, $case['operations']);
		}

		return $operations;
	}

	/**
	 * The operations changing the event itself rather than its tags, with the state they must leave on every
	 * problem event of the scenario. They all run in one rule, the "event operations" one, whose filter is
	 * just the 'type' Equals "cep" guard, in the order listed:
	 *   - "set name" replaces the event name. Its leading half is a user macro of the template the event's host is
	 *     linked to (see prepareWindowOperationMacros()) and the rest is written out, so the expected name is
	 *     reached only by resolving the one and keeping the other - the name is the only field of these operations
	 *     that may hold a macro at all: a severity is a number and a suppression duration is read straight from
	 *     the configuration, without a macro ever being resolved in it;
	 *   - "set severity" puts the event at Information, then two "increase severity" and one "decrease
	 *     severity" shift it by one step each, leaving Warning. The severity is asserted after the whole
	 *     chain, and since the three shifts do not cancel out, an operation that did nothing would leave a
	 *     different severity behind;
	 *   - "suppress" suppresses the event for CEP_RULE_WINDOW_NONE_SUPPRESS_PERIOD.
	 *
	 * The rule runs last (see prepareDataCepWindowNoneTagOperations()): it changes the event name and severity,
	 * which the event name and severity rules of getWindowNoneRules() have conditions on, so it must not run
	 * before them.
	 *
	 * The two remaining operations a windowless rule could perform at this point are left out on purpose:
	 * "discard" would drop the event before it is ever stored, and "close" would close the problem this
	 * scenario needs to stay open (the CEP window scenarios cover closing).
	 *
	 * The suppression is not indefinite: a duration is given rather than the 0 that would suppress the event
	 * for good, so both halves of it can be checked - the events are suppressed first, and the suppression is
	 * gone once CEP_RULE_WINDOW_NONE_SUPPRESS_PERIOD has passed since the operation ran on them. Only the
	 * first half is checked by default; the wait for the second one is the slowest step of the scenario and
	 * SKIP_UNSUPPRESS_WAIT leaves it out (see waitForCepWindowNoneUnsuppressed()).
	 *
	 * Either way the suppressions cannot outlive the test: event_suppress.cep_ruleid is an ON DELETE CASCADE
	 * foreign key, so deleting the CEP rules in the teardown removes them - a manual unsuppress could not, it
	 * only clears rows with no cep_ruleid.
	 */
	private function getWindowNoneEventOperationCase(): array {
		return [
			'operations' => [
				// The name is a user macro (see prepareWindowOperationMacros()), then text, then an expression
				// macro - the one macro form only the name accepts, the tag operations being resolved without the
				// expression macro search. The event ends up with the expected name below only when both macros
				// were resolved and the text between them was left alone.
				[CCepRuleHelper::OP_SET_NAME, ['event_name' => self::CEP_OP_EVENT_NAME_MACRO
					.self::CEP_RULE_WINDOW_NONE_OP_EVENT_NAME_MIDDLE.self::CEP_OP_EXPRESSION_MACRO
				]],
				[CCepRuleHelper::OP_SET_SEVERITY, ['severity' => TRIGGER_SEVERITY_INFORMATION]],
				[CCepRuleHelper::OP_INCREASE_SEVERITY, []],
				[CCepRuleHelper::OP_INCREASE_SEVERITY, []],
				[CCepRuleHelper::OP_DECREASE_SEVERITY, []],
				[CCepRuleHelper::OP_SUPPRESS, ['suppress_duration' => self::CEP_RULE_WINDOW_NONE_SUPPRESS_PERIOD]]
			],
			'expected' => [
				'name' => self::CEP_RULE_WINDOW_NONE_OP_EVENT_NAME,
				// Information +1 +1 -1.
				'severity' => TRIGGER_SEVERITY_WARNING,
				'suppressed' => true
			]
		];
	}

	/**
	 * Turn a list of [operation type, operation parameters] pairs into the operation list of a rule: every
	 * operation executes at $execute_when and is numbered in the order it is listed, which is the order the
	 * server executes them in (operations of a rule run in sortorder).
	 */
	private function buildWindowNoneOperations(array $operations,
			int $execute_when = CCepRuleHelper::WHEN_EVENT_OCCURRED): array {
		$result = [];

		foreach ($operations as [$type, $params]) {
			$result[] = [
				'sortorder' => count($result),
				'execute_when' => $execute_when,
				'type' => $type
			] + $params;
		}

		return $result;
	}

	/**
	 * The operation filter condition satisfied by an "up" event and by no other event of the close-on-up
	 * scenarios: CEP_STATE_TAG_UP is the tag name the trigger prototypes build from {ITEM.VALUE} (see
	 * CEP_STATE_TAG), so only an "up" event carries that tag at all and a plain tag-exists condition singles
	 * them out without comparing any tag value.
	 */
	private static function buildUpEventOperationCondition(): array {
		return [
			'type' => CCepRuleHelper::CONDITION_TAG,
			'operator' => CONDITION_OPERATOR_EXISTS,
			'tag' => self::CEP_STATE_TAG_UP
		];
	}

	/**
	 * The tag state getWindowNoneTagOperationCases() must leave on every problem event of the scenario, as a
	 * tag => value map in which a null value means the tag must not be on the event at all.
	 */
	private function getWindowNoneTagOperationResults(): array {
		$expected = [];

		foreach ($this->getWindowNoneTagOperationCases() as $case) {
			$expected += $case['expected'];
		}

		return $expected;
	}

	/**
	 * Build a global event correlation rule that only closes new problems (CLOSE_NEW), not old problems.
	 * Useful for testing correlation update behavior where you want to verify that changing the
	 * operations changes the event lifecycle behavior.
	 */
	private function buildCloseNewOnlyCorrelationParams(string $name, $evaltype): array {
		$new_up = [
			'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
			'tag' => 'state',
			'operator' => CONDITION_OPERATOR_EQUAL,
			'value' => 'up'
		];
		$tag_pair = [
			'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
			'oldtag' => 'service',
			'newtag' => 'service'
		];

		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION) {
			$new_up['formulaid'] = 'A';
			$tag_pair['formulaid'] = 'B';
			$filter = [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'A and B',
				'conditions' => [$new_up, $tag_pair]
			];
		}
		else {
			$filter = [
				'evaltype' => $evaltype,
				'conditions' => [$new_up, $tag_pair]
			];
		}

		return [
			'name' => $name,
			'filter' => $filter,
			'operations' => [
				['type' => ZBX_CORR_OPERATION_CLOSE_NEW]
			]
		];
	}

	/**
	 * Reconfigure both trigger prototypes for the parity-based global correlation scenario: the same
	 * find(regexp,"down") + multiple-event + global-correlation setup as prepareDataGlobalCorrelation,
	 * but every discovered trigger additionally carries an 'odd' tag whose value is the component
	 * parity ('1'/'0', from the {#PARITY} LLD macro). No correlation rule is created here; the run
	 * method opens problems on all triggers first, then adds the "even" and "odd" rules one at a time.
	 */
	public function prepareDataGlobalCorrelationParity() {
		// Switch item prototypes to text so the find() function can be used in expressions.
		$this->call('itemprototype.update', [
			'itemid' => self::$item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->call('itemprototype.update', [
			'itemid' => self::$dep_item_prototypeid,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		// Both prototypes: find(regexp,"down") + multiple event generation + global correlation, plus
		// a 'service'={ITEM.VALUE} tag (so "down" sets service="down"), a stable 'component' tag for
		// the correlation tag pair and an 'odd' tag carrying the component parity.
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'description' => 'CEP trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{ITEM.VALUE}'],
				['tag' => 'odd', 'value' => self::PARITY_MACRO]
			]
		]);

		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'description' => 'CEP dependent trigger for '.self::LLD_MACRO,
			'expression' => 'find(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY2
				.'['.self::LLD_MACRO.'],,"regexp","down")=1',
			'event_name' => 'CEP trigger '.self::LLD_MACRO.' {ITEM.VALUE}',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'recovery_expression' => '',
			'dependencies' => [],
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'type' => TRIGGER_MULT_EVENT_ENABLED,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'tags' => [
				['tag' => 'component', 'value' => self::LLD_MACRO],
				['tag' => 'type', 'value' => 'cep-dep'],
				['tag' => self::SERVICE_TAG, 'value' => self::LLD_MACRO],
				['tag' => 'service', 'value' => '{ITEM.VALUE}'],
				['tag' => 'odd', 'value' => self::PARITY_MACRO]
			]
		]);

		// Resend LLD discovery data (now including the parity macro) to re-instantiate the discovered
		// triggers and items with the new config.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData(true)
			]
		]);

		// Verify the discovered items reflect the updated value type.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'value_type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $item) {
				if ((int) $item['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']);

		// Verify the two primary discovered triggers reflect global correlation mode, multiple event
		// generation and that the parity 'odd' tag resolved (component sensor1 → odd index → '1');
		// poll until LLD has re-applied the new prototype config.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$discovered_triggerid, self::$discovered_dep_triggerid],
			'output' => ['triggerid', 'correlation_mode', 'type'],
			'selectTags' => 'extend'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			if (count($response['result']) !== 2) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['correlation_mode'] !== ZBX_TRIGGER_CORRELATION_NONE
						|| (int) $trigger['type'] !== TRIGGER_MULT_EVENT_ENABLED) {
					return false;
				}
				$odd = current(array_filter($trigger['tags'], fn($t) => $t['tag'] === 'odd'));
				if ($odd === false || $odd['value'] !== '1') {
					return false;
				}
			}
			return true;
		});
		$this->assertCount(2, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(ZBX_TRIGGER_CORRELATION_NONE, $trigger['correlation_mode'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to global correlation mode.');
			$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, $trigger['type'],
				'Discovered trigger '.$trigger['triggerid'].' was not updated to multiple-event mode.');
			$odd = current(array_filter($trigger['tags'], fn($t) => $t['tag'] === 'odd'));
			$this->assertNotFalse($odd,
				'Discovered trigger '.$trigger['triggerid'].' is missing the parity "odd" tag.');
			$this->assertEquals('1', $odd['value'],
				'Discovered trigger '.$trigger['triggerid'].' has unexpected parity tag value.');
		}

		// Start from a clean correlation slate: remove any CEP correlation rules left over from earlier
		// scenarios so that no rule is active while the run method opens the initial problems. The
		// "even" and "odd" rules are then created one at a time during the run.
		$this->deleteCepCorrelations();

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return true;
	}

	/**
	 * Delete every global correlation rule created by these CEP scenarios (their names all start with
	 * "CEP global event correlation") and reset the tracked correlation ids.
	 */
	private function deleteCepCorrelations(): void {
		$response = $this->call('correlation.get', [
			'output' => ['correlationid'],
			'search' => ['name' => 'CEP global event correlation']
		]);
		$ids = array_column($response['result'], 'correlationid');
		if ($ids) {
			$this->call('correlation.delete', $ids);
		}
		self::$correlationid = null;
		self::$correlationid2 = null;
	}

	/**
	 * Delete every CEP rule (ceprule API) created by these scenarios - their names all start with
	 * CEP_RULE_NAME_PREFIX - and reset the tracked CEP rule id. A leftover rule would keep closing the
	 * problems of the scenarios that run afterwards, which is why every CEP scenario calls this through
	 * cleanupCepRules() from a finally block: the rules of a scenario must not outlive it even when it failed.
	 * The prepareData* methods therefore find no rules of their own and none of another flavour, and create
	 * theirs with upsertCepRule() without clearing anything first.
	 */
	private function deleteCepRules(): void {
		$response = $this->call('ceprule.get', [
			'output' => ['cep_ruleid'],
			'search' => ['name' => self::CEP_RULE_NAME_PREFIX]
		]);
		$ids = array_column($response['result'], 'cep_ruleid');
		if ($ids) {
			$this->call('ceprule.delete', $ids);
		}
		self::$cep_ruleid = null;
	}

	/**
	 * Teardown every CEP window scenario must run, even when it failed: the CEP rule closes every problem
	 * carrying a 'state' tag, so it must not survive the test - the global correlation variants that run
	 * afterwards use the same trigger prototypes and would have their problems closed by this rule instead of
	 * by their correlation rule. The configuration cache is reloaded so the server drops the rule right away.
	 */
	private function cleanupCepRules(): void {
		$this->deleteCepRules();
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Send LLD data via the proxy dispatch helper and verify that the item and trigger
	 * prototypes are instantiated for the discovered component.
	 *
	 * @configurationDataProvider configurationProvider
	 */
	public function testPrepareTriggerCEP_LLDDiscovery() {
		// Reload configuration cache before sending discovery data.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// Send host LLD discovery data so the host prototype creates the discovered host
		// with the template linked.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::HOST_LLD_RULE_KEY,
				'value' => json_encode(['data' => [
					['{#HOST}' => self::HOST_DISC_VALUE]
				]])
			]
		]);

		// Wait for the discovered host to be created by the server.
		$response = $this->callUntilDataIsPresent('host.get', [
			'filter' => ['host' => self::HOST_DISC_VALUE],
			'output' => ['hostid']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		$this->assertCount(1, $response['result'], 'Discovered host was not created by host prototype.');
		self::$disc_hostid = $response['result'][0]['hostid'];

		// Wait for the inherited LLD rule to be created on the discovered host.
		$this->callUntilDataIsPresent('discoveryrule.get', [
			'hostids' => [self::$disc_hostid],
			'filter' => ['key_' => self::LLD_RULE_KEY],
			'output' => ['itemid']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Reload config so the server is aware of the discovered host's inherited LLD rule.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// Send item LLD discovery data to the discovered host's LLD rule (inherited from template).
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Verify all LLD_DISCOVERY_COUNT items from proto1 were created.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'['],
			'output' => ['itemid', 'name', 'key_']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === static::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'], 'Not all discovered items were created.');

		// Verify all LLD_DISCOVERY_COUNT triggers were created and store the primary one.
		$expected_description = 'CEP trigger for '.self::COMPONENT_VALUE;

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['description' => 'CEP trigger for '],
			'output' => ['triggerid', 'description', 'value', 'state'],
			'selectTags' => 'extend'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === static::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'], 'Not all discovered triggers were created.');

		$primary_trigger = current(array_filter($response['result'],
			fn($t) => $t['description'] === $expected_description
		));
		$this->assertNotFalse($primary_trigger, 'Primary discovered trigger not found.');
		self::$discovered_triggerid = $primary_trigger['triggerid'];
		self::$discovered_triggerids = array_column($response['result'], 'triggerid');

		$tags = $primary_trigger['tags'];
		$tags_json = json_encode($tags);
		$tags_tv = array_map(fn($t) => ['tag' => $t['tag'], 'value' => $t['value']], $tags);
		$this->assertCount(4, $tags_tv, 'Discovered trigger must have 4 tags, got: '.$tags_json);
		$this->assertContains(['tag' => 'component_{ITEM.VALUE}', 'value' => self::COMPONENT_VALUE], $tags_tv,
			'Tag component='.self::COMPONENT_VALUE.' not found in: '.$tags_json);
		$this->assertContains(['tag' => 'type', 'value' => 'cep'], $tags_tv,
			'Tag type=cep not found in: '.$tags_json);
		$this->assertContains(['tag' => self::SERVICE_TAG, 'value' => self::COMPONENT_VALUE], $tags_tv,
			'Tag '.self::SERVICE_TAG.'='.self::COMPONENT_VALUE.' not found in: '.$tags_json);
		$this->assertContains(['tag' => 'service', 'value' => '{{ITEM.VALUE}.regsub("([0-9]+)$", "\\1")}'], $tags_tv,
			'Tag service=regsub not found in: '.$tags_json);

		// Verify all LLD_DISCOVERY_COUNT items from proto2 were created.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY2.'['],
			'output' => ['itemid', 'name', 'key_']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === static::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'], 'Not all second discovered items were created.');

		// Verify all LLD_DISCOVERY_COUNT dependent triggers were created and store the primary one.
		$expected_dep_description = 'CEP dependent trigger for '.self::COMPONENT_VALUE;

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$disc_hostid],
			'search' => ['description' => 'CEP dependent trigger for '],
			'output' => ['triggerid', 'description', 'value', 'state'],
			'selectTags' => 'extend'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === static::LLD_DISCOVERY_COUNT;
		});
		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result'], 'Not all discovered dependent triggers were created.');

		$primary_dep_trigger = current(array_filter($response['result'],
			fn($t) => $t['description'] === $expected_dep_description
		));
		$this->assertNotFalse($primary_dep_trigger, 'Primary discovered dependent trigger not found.');
		self::$discovered_dep_triggerid = $primary_dep_trigger['triggerid'];
		self::$discovered_dep_triggerids = array_column($response['result'], 'triggerid');

		$dep_tags = $primary_dep_trigger['tags'];
		$dep_tags_json = json_encode($dep_tags);
		$dep_tags_tv = array_map(fn($t) => ['tag' => $t['tag'], 'value' => $t['value']], $dep_tags);
		$this->assertCount(3, $dep_tags_tv, 'Discovered dependent trigger must have 3 tags, got: '.$dep_tags_json);
		$this->assertContains(['tag' => 'component_{ITEM.VALUE}', 'value' => self::COMPONENT_VALUE], $dep_tags_tv,
			'Tag component='.self::COMPONENT_VALUE.' not found in: '.$dep_tags_json);
		$this->assertContains(['tag' => 'type', 'value' => 'cep-dep'], $dep_tags_tv,
			'Tag type=cep-dep not found in: '.$dep_tags_json);
		$this->assertContains(['tag' => self::SERVICE_TAG, 'value' => self::COMPONENT_VALUE], $dep_tags_tv,
			'Tag '.self::SERVICE_TAG.'='.self::COMPONENT_VALUE.' not found in: '.$dep_tags_json);

		// Discover the single log trigger up front (reloads the configuration cache before and after) so
		// the server is aware of all newly discovered items and the log event tests can drive it directly.
		$this->discoverLogTrigger();
	}

	/**
	 * Sanity check: send a numeric value of 0 to all discovered items and verify the values were
	 * written to the history cache (zabbix[vps,written] advanced), without asserting any trigger
	 * state or event changes.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_VpsWritten() {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		$vps_written = $this->getVpsWritten();
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'], $keys)
		);
		$this->assertVpsWrittenIncreasedBy($vps_written, count($keys));
	}

	/**
	 * Smoke test (part 1/2): a discovered trigger opens a problem on OK→PROBLEM.
	 * The problem is left open and closed by testTriggerCEP_CloseProblem.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenProblem() {
		$this->runOpenProblemTest(false);
	}

	/**
	 * Smoke test (part 1.5/2): re-send the problem value while the single-event triggers are already in
	 * PROBLEM. No new event is generated so the trigger state does not change, and CEP does not process
	 * the already-open problem events. Runs between open and close so the problem is still open.
	 *
	 * @depends testTriggerCEP_OpenProblem
	 */
	public function testTriggerCEP_OpenAlreadyOpenedProblem() {
		$this->runOpenAlreadyOpenedProblemTest(false);
	}

	/**
	 * Smoke test (part 2/2): the problem opened by testTriggerCEP_OpenProblem closes on PROBLEM→OK.
	 * Runs as a separate test so the open problem persists across the test boundary before recovery.
	 *
	 * @depends testTriggerCEP_OpenAlreadyOpenedProblem
	 */
	public function testTriggerCEP_CloseProblem() {
		$this->runCloseProblemTest(false);
	}

	/**
	 * Same scenario as testTriggerCEP_OpenProblem but the server component is stopped and restarted first,
	 * to verify the problem opens and is cached correctly after a fresh restart. Depends on the non-restart
	 * close so it starts from the recovered state.
	 *
	 * @depends testTriggerCEP_CloseProblem
	 */
	public function testTriggerCEP_OpenProblemRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runOpenProblemTest(true);
	}

	/**
	 * Same scenario as testTriggerCEP_OpenAlreadyOpenedProblem but the server component is stopped and
	 * restarted first, to verify a re-sent problem value generates no new event after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenProblemRestart
	 */
	public function testTriggerCEP_OpenAlreadyOpenedProblemRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runOpenAlreadyOpenedProblemTest(true);
	}

	/**
	 * Same scenario as testTriggerCEP_CloseProblem but the server component is stopped and restarted first,
	 * to verify recovery and cache draining after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenAlreadyOpenedProblemRestart
	 */
	public function testTriggerCEP_CloseProblemRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runCloseProblemTest(true);
	}

	/**
	 * Open a problem on OK→PROBLEM (one PROBLEM event per trigger) and verify CEP processed and cached it.
	 * When $restart is true, the server is restarted first.
	 */
	private function runOpenProblemTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// OK→PROBLEM: one PROBLEM event per trigger; trigger value goes TRUE.
		$cep_processed = $this->getCepStat('events', 'processed');
		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, 1);

		// CEP processed the opened problem events (one per trigger).
		$this->assertCepStatIncreasedBy('events', 'processed', $cep_processed, count($keys));

		// CEP cached one event per opened problem (one per trigger).
		$this->assertCepStatEquals('cache', 'events', count($keys));
		$this->assertCepStatEquals('cache', 'objects', count($keys));
	}

	/**
	 * Re-send the problem value while the single-event triggers are already in PROBLEM and verify no new
	 * event is generated. When $restart is true, the server is restarted first.
	 */
	private function runOpenAlreadyOpenedProblemTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// PROBLEM→PROBLEM: no new event (single-event triggers), trigger value/lastchange unchanged.
		// assertNoStateChangeForAll also verifies CEP did not process the already-open problem events.
		$this->captureEventBaseline($triggerids);
		$this->assertNoStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, 0);
	}

	/**
	 * Close the open problem on PROBLEM→OK (one RESOLVED event per trigger) and verify CEP processed it and
	 * drained its cache. When $restart is true, the server is restarted first.
	 */
	private function runCloseProblemTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// PROBLEM→OK: one RESOLVED event per trigger. The baseline is per-test-instance, so it is
		// recaptured here (now past the open event) and the close adds exactly one more event.
		$cep_processed = $this->getCepStat('events', 'processed');
		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, 1);
		$this->waitForNoOpenProblems($triggerids, 'close problem');

		// CEP processed the recovered events (one per trigger).
		$this->assertCepStatIncreasedBy('events', 'processed', $cep_processed, count($keys));
		$this->assertCepStatEquals('cache', 'events', 0);
		$this->assertCepStatEquals('cache', 'objects', 0);
		$this->assertCepNoWindows();
	}

	/**
	 * Create one service per discovered trigger (each mapped to its trigger via the stable SERVICE_TAG
	 * problem tag), a service action and a trigger action, all routed through the CEP webhook media type.
	 * Runs after the minimal open/close smoke tests so the same OK→PROBLEM→OK scenario can be repeated
	 * with the services and actions in place. The services and actions are removed in clearData().
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_AddServices() {
		$this->skipIfServicesTestsDisabled();
		$this->createServicesAndActions();
	}

	/**
	 * Like testTriggerCEP_OpenAndImmediateRecoverySingleItem but also verifies the per-trigger service that
	 * createServicesAndActions() already created for the driven trigger (matched to it only by that trigger's
	 * own SERVICE_TAG tag). After the long rapid PROBLEM/recovery burst the service must have tracked every
	 * cycle by the trigger tag and ended OK with no open service problem, and a final explicit open then
	 * verifies the service manager matches the problem to the service purely by the trigger tag - it reaches
	 * the trigger's DISASTER priority with exactly one open service problem (no duplicate cached during the
	 * burst) - before following the close back to OK. Skipped entirely when the per-trigger services do not
	 * exist (service tests skipped), as there would be nothing to verify by trigger tag.
	 * (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_OpenAndImmediateRecoverySingleItemWithService)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoverySingleItemWithService() {
		if (empty(self::$serviceids)) {
			$this->markTestSkipped('No CEP services created (service tests skipped); nothing to match by trigger tag.');
		}

		$this->runOpenAndImmediateRecoverySingleItemTest(false, true);
	}

	/**
	 * Like testTriggerCEP_OpenAndImmediateRecoveryValueWaves but also verifies the per-trigger services that
	 * createServicesAndActions() already created for the driven triggers (each matched to its trigger only by
	 * that trigger's own SERVICE_TAG tag). The batch ends on a 1 wave with every trigger in PROBLEM, so every
	 * service must have followed the interleaved cross-item waves and reached the trigger's DISASTER priority
	 * with exactly one open service problem (no duplicate cached during the waves - a regression guard for the
	 * service manager matching the same event to a service more than once), and the closing 0 wave must then
	 * follow every service back to OK with no open service problem. Skipped entirely when the per-trigger
	 * services do not exist (service tests skipped), as there would be nothing to verify by trigger tag.
	 * (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_OpenAndImmediateRecoveryValueWavesWithService)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryValueWavesWithService() {
		if (empty(self::$serviceids)) {
			$this->markTestSkipped('No CEP services created (service tests skipped); nothing to match by trigger tag.');
		}

		$this->runOpenAndImmediateRecoveryValueWavesTest(false, true);
	}

	/**
	 * Repeat of testTriggerCEP_OpenProblem with the services and actions in place: the trigger opens a
	 * problem and every per-trigger service follows it to PROBLEM (disaster) with one open service problem.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenProblemWithServices() {
		$this->runOpenProblemWithServicesTest(false);
	}

	/**
	 * Repeat of testTriggerCEP_CloseProblem with the services and actions in place: the problem closes
	 * and every per-trigger service recovers to OK with no open service problems.
	 *
	 * @depends testTriggerCEP_OpenProblemWithServices
	 */
	public function testTriggerCEP_CloseProblemWithServices() {
		$this->runCloseProblemWithServicesTest(false);
	}

	/**
	 * Open a problem and send the recovery immediately afterwards, without waiting for the problem to be
	 * confirmed first, and verify CEP still generates both events per trigger: one PROBLEM event followed
	 * by one RESOLVED event. Guards against the open and close collapsing into a single event (or the
	 * problem being dropped) when they arrive back-to-back.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecovery() {
		$this->runOpenAndImmediateRecoveryTest(false);
	}

	/**
	 * Same scenario as testTriggerCEP_OpenProblemWithServices but the server component is stopped and
	 * restarted first, to verify the problem opens and the per-trigger services follow it to PROBLEM
	 * after a fresh restart. Depends on the non-restart close so it starts from the recovered state.
	 *
	 * @depends testTriggerCEP_CloseProblemWithServices
	 */
	public function testTriggerCEP_OpenProblemWithServicesRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runOpenProblemWithServicesTest(true);
	}

	/**
	 * Same scenario as testTriggerCEP_CloseProblemWithServices but the server component is stopped and
	 * restarted first, to verify recovery and service status after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenProblemWithServicesRestart
	 */
	public function testTriggerCEP_CloseProblemWithServicesRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runCloseProblemWithServicesTest(true);
	}

	/**
	 * Same scenario as testTriggerCEP_OpenAndImmediateRecovery but the server component is stopped and
	 * restarted first, to verify CEP still emits one event per transition after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenAndImmediateRecovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runOpenAndImmediateRecoveryTest(true);
	}

	/**
	 * Like testTriggerCEP_OpenAndImmediateRecovery but the rapid burst also flips the item to an
	 * unsupported state mid-sequence in several combinations (problem→unsupported→recover,
	 * unsupported→problem→recover, problem→unsupported→problem→recover, and unsupported while already OK).
	 * The unsupported value sends the trigger to UNKNOWN without changing its value, so it must emit no
	 * trigger event; CEP must still emit exactly one event per real value transition without collapsing or
	 * dropping any when the transitions and the unsupported state arrive back-to-back.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryUnsupported() {
		$this->runOpenAndImmediateRecoveryUnsupportedTest(false);
	}

	/**
	 * Like testTriggerCEP_OpenAndImmediateRecovery but the whole burst lands on a single discovered item
	 * (and its one trigger) instead of being spread across every discovered item, cycling PROBLEM → recover
	 * (1, 0) a large number of times. Verifies CEP emits exactly one event per transition on that single
	 * event stream under a long rapid burst, without collapsing or dropping any.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoverySingleItem() {
		$this->runOpenAndImmediateRecoverySingleItemTest(false);
	}


	/**
	 * Like testTriggerCEP_OpenAndImmediateRecovery but the single batch is grouped by value ("waves")
	 * instead of by key: every discovered item first gets 0 (the triggers are already OK, so this wave
	 * must emit no events), then every item gets 1 (every trigger opens) and finally every item gets 0
	 * again (every trigger recovers). Verifies CEP handles the cross-item interleaved ordering, emitting
	 * exactly one PROBLEM and one RESOLVED event per trigger and leaving no open problems.
	 * (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_OpenAndImmediateRecoveryValueWaves)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryValueWaves() {
		$this->runOpenAndImmediateRecoveryValueWavesTest(false);
	}


	/**
	 * Like testTriggerCEP_OpenAndImmediateRecoveryValueWaves but with an even number of waves (1, 0, 1, 0),
	 * so the single batch itself ends on a recovery wave: every trigger opens twice and recovers twice
	 * within the batch and no separate closing wave is needed. Verifies CEP handles the cross-item
	 * interleaved ordering when the batch ends on a recovery, emitting exactly one PROBLEM and one
	 * RESOLVED event per trigger per cycle and leaving no open problems.
	 * (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_OpenAndImmediateRecoveryValueWavesEndOk)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenAndImmediateRecoveryValueWavesEndOk() {
		$this->runOpenAndImmediateRecoveryValueWavesEndOkTest(false);
	}

	/**
	 * Open the problem and let every per-trigger service follow it to PROBLEM (disaster). When $restart is
	 * true, the server is restarted first.
	 */
	private function runOpenProblemWithServicesTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, 1);

		// Each per-trigger service goes to PROBLEM (disaster) with one open service problem.
		$this->assertServicesStatus(TRIGGER_SEVERITY_DISASTER, count(self::$serviceids));

		// And each service holds exactly one open service problem — no duplicates (regression guard for the
		// service manager matching the same event to a service more than once).
		$this->assertOneServiceProblemPerService();
	}

	/**
	 * Close the problem and let every per-trigger service recover to OK with no open service problems.
	 * When $restart is true, the server is restarted first.
	 */
	private function runCloseProblemWithServicesTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);
		$this->assertStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, 1);
		$this->waitForNoOpenProblems($triggerids, 'close problem with services');

		// All services recover to OK with no open service problems.
		$this->assertServicesStatus(ZBX_SEVERITY_OK, 0);

		// This is the last services-specific test (the restart sibling is either the true last run or is
		// skipped), so disable the service/trigger actions: they must not fire on the events generated by the
		// later, non-services scenarios. The services and the actions are removed entirely in clearData().
		if ($restart || static::SKIP_RESTART_TESTS) {
			$this->disableServicesActions();
		}
	}

	/**
	 * Disable the service action created by createServicesAndActions() so it no longer fires on the events
	 * of later (non-services) scenarios. The trigger action is left enabled so it keeps firing on the
	 * discovered triggers in the later scenarios. Idempotent and guarded, so it is safe if the action was
	 * never created. Both actions are deleted in clearData().
	 */
	private function disableServicesActions(): void {
		if (!empty(self::$service_actionid)) {
			$this->call('action.update', [
				'actionid' => self::$service_actionid,
				'status' => ACTION_STATUS_DISABLED
			]);
		}

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Open a problem and send the recovery immediately afterwards, verifying CEP emits one PROBLEM event
	 * followed by one RESOLVED event per trigger. When $restart is true, the server is restarted first.
	 */
	private function runOpenAndImmediateRecoveryTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);

		// Build a long alternating PROBLEM/recovery burst (1,0,1,0,...) for every discovered item and send
		// it in a single batch. Every value gets a strictly increasing (clock, ns) so CEP must process the
		// whole rapid burst in order and emit one event per transition without collapsing or dropping any.
		// The sequence ends on 0 so the triggers finish OK.
		$values = [];
		for ($i = 0; $i < 3; $i++) {
			$values[] = '1';
			$values[] = '0';
		}

		$data = [];
		foreach ($keys as $key) {
			foreach ($values as $value) {
				$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
			}
		}
		$this->dispatchSenderValues($data);

		$expected_events = count($values);

		// Each trigger must produce one event per transition: PROBLEM, RESOLVED, PROBLEM, RESOLVED, ...
		$this->waitForAllTriggerEventCounts($triggerids, $expected_events);

		// Events are newest-first, so they alternate RESOLVED, PROBLEM, RESOLVED, PROBLEM, ... (the burst
		// ends on a recovery, so the newest event is RESOLVED).
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$events = $events_by_trigger[$triggerid];
			$info = 'trigger #'.$idx.': '.count($events).' events';
			$this->assertCount($expected_events, $events, $info);
			foreach ($events as $pos => $event) {
				$expected_value = ($pos % 2 === 0) ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$this->assertEquals($expected_value, (int) $event['value'], $info.' at pos '.$pos);
			}
		}

		$this->waitForNoOpenProblems($triggerids, 'open and immediate recovery');
	}

	/**
	 * Open/recover burst that also flips the item to an unsupported state mid-cycle (1, unsupported, 0),
	 * verifying CEP emits exactly one event per real value transition: the unsupported value changes only
	 * the item state (trigger goes UNKNOWN), not the trigger value, so it emits no event. When $restart is
	 * true, the server is restarted first.
	 */
	private function runOpenAndImmediateRecoveryUnsupportedTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);

		$unsupported = 'not_a_number';
		$values = [];
		for ($i = 0; $i < 3; $i++) {
			$values[] = $unsupported;
			$values[] = '0';
		}

		$data = [];
		foreach ($keys as $key) {
			foreach ($values as $value) {
				$entry = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
				// The server skips preprocessing for proxy-delivered values, so the unsupported transition
				// must be reported explicitly rather than relying on the non-numeric value failing.
				if ($value === $unsupported) {
					$entry['state'] = ITEM_STATE_NOTSUPPORTED;
				}
				$data[] = $entry;
			}
		}
		$this->dispatchSenderValues($data);

		$expected_values = [];
		$current = TRIGGER_VALUE_FALSE;
		foreach ($values as $value) {
			if ($value === $unsupported) {
				continue;
			}
			$new = ($value === '0') ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
			if ($new !== $current) {
				$expected_values[] = $new;
				$current = $new;
			}
		}
		$this->assertEquals(TRIGGER_VALUE_FALSE, $current, 'burst must leave the triggers OK');

		$expected_events = count($expected_values);

		// Each trigger must produce one event per value transition.
		$this->waitForAllTriggerEventCounts($triggerids, $expected_events);

		// Events are returned newest-first, so compare against the reversed expected sequence.
		$expected_newest_first = array_reverse($expected_values);
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$events = $events_by_trigger[$triggerid];
			$info = 'trigger #'.$idx.': '.count($events).' events';
			$this->assertCount($expected_events, $events, $info);
			foreach ($events as $pos => $event) {
				$this->assertEquals($expected_newest_first[$pos], (int) $event['value'], $info.' at pos '.$pos);
			}
		}

		$this->waitForNoOpenProblems($triggerids, 'open and immediate recovery unsupported');
	}

	private function getDiscoveredItemid(string $host, string $key): int {
		$this->ensureItemidsResolved([['host' => $host, 'key' => $key]]);
		return self::$itemid_cache[$host."\0".$key];
	}

	private function getTriggeridForKey(string $host, string $key): string {
		$itemid = $this->getDiscoveredItemid($host, $key);
		$response = $this->call('trigger.get', [
			'itemids' => [$itemid],
			'output' => ['triggerid']
		]);
		$this->assertCount(1, $response['result'],
			'Expected exactly one trigger for item on key '.$key.', got: '.json_encode($response['result']));
		return $response['result'][0]['triggerid'];
	}

	/**
	 * The triggers of the given item keys as a key => triggerid map, in the order the keys were given. This is
	 * getTriggeridForKey() for many keys at once and costs one request for all of them instead of one each, which is
	 * what makes the flavours that send a value through every discovered trigger practical - there are
	 * LLD_DISCOVERY_COUNT of those, see runEventAssessmentTestCepWindowCloseWindow().
	 */
	private function getTriggeridsForKeys(string $host, array $keys): array {
		$this->ensureItemidsResolved(array_map(fn(string $key) => ['host' => $host, 'key' => $key], $keys));

		$itemids = [];

		foreach ($keys as $key) {
			$itemids[$key] = self::$itemid_cache[$host."\0".$key];
		}

		$response = $this->call('trigger.get', [
			'itemids' => array_values($itemids),
			'output' => ['triggerid'],
			'selectItems' => ['itemid']
		]);

		// One trigger per item, as getTriggeridForKey() asserts for the single key it resolves: an item with a second
		// trigger on it would leave the map naming only one of them and the scenario reading the state of a trigger
		// its values are not the only cause of.
		$this->assertCount(count($itemids), $response['result'], 'Expected exactly one trigger per item of the '
			.count($itemids).' keys resolved, got: '.count($response['result']).'.'
		);

		$by_itemid = [];

		foreach ($response['result'] as $trigger) {
			foreach ($trigger['items'] as $item) {
				$by_itemid[(int) $item['itemid']] = $trigger['triggerid'];
			}
		}

		$triggerids = [];

		foreach ($itemids as $key => $itemid) {
			$this->assertArrayHasKey($itemid, $by_itemid, 'No trigger found for the item on key '.$key.'.');

			$triggerids[$key] = $by_itemid[$itemid];
		}

		return $triggerids;
	}

	/**
	 * Same as runOpenAndImmediateRecoveryTest but the whole burst lands on a single discovered item (and
	 * its one trigger), cycling PROBLEM → recover (1, 0) a large number of times, to stress CEP with a long
	 * rapid back-to-back burst on one event stream. When $restart is true, the server is restarted first.
	 *
	 * When $with_service is true the per-trigger service that createServicesAndActions() already created for
	 * this trigger (matched to it only by the trigger's own SERVICE_TAG tag) is verified as well: after the
	 * burst the service must have tracked every cycle by the trigger tag and ended OK with no open service
	 * problem, and a final explicit open then verifies the service manager matches the problem to the service
	 * purely by that trigger tag - it reaches the trigger's DISASTER priority with exactly one open service
	 * problem (no duplicate cached during the burst) - before following the close back to OK. The service
	 * checks are skipped when the per-trigger services do not exist (service tests skipped).
	 */
	private function runOpenAndImmediateRecoverySingleItemTest(bool $restart, bool $with_service = false): void {
		$this->maybeRestartServer($restart);

		// Drive a single discovered item (and its one trigger) so the whole burst lands on one event
		// stream rather than being spread across every discovered item.
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);

		// When requested, reuse the per-trigger service createServicesAndActions() already created for this
		// trigger (matched to it only by the trigger's own SERVICE_TAG tag). Null when those services do not
		// exist (service tests skipped), in which case the service checks below are skipped.
		$serviceid = $with_service ? $this->getServiceidForTrigger($triggerid) : null;

		// Baseline the service's own events (source SERVICE) before the burst, so the post-burst count is a
		// delta: the shared per-trigger service has accumulated events from earlier scenarios.
		$service_event_baseline = ($serviceid !== null) ? $this->captureServiceEventBaseline($serviceid) : 0;

		$this->captureEventBaseline([$triggerid]);

		// Build a long alternating PROBLEM/recovery burst (1,0,1,0,...) and send it in a single batch. Every
		// value gets a strictly increasing (clock, ns) so CEP must process the whole rapid burst in order
		// and emit one event per transition without collapsing or dropping any. The sequence ends on 0 so
		// the trigger finishes OK.
		$cycles = static::RECOVERY_CYCLES_COUNT;
		$values = [];
		for ($i = 0; $i < $cycles; $i++) {
			$values[] = '1';
			$values[] = '0';
		}

		$data = [];
		foreach ($values as $value) {
			$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
		}
		$vps_written = $this->getVpsWritten();
		$sent = $this->dispatchSenderValues($data);

		// Confirm the whole burst was ingested (written to the history cache) before asserting on events,
		// so a dropped or not-yet-processed value surfaces here rather than as a confusing event mismatch.
		$this->assertVpsWrittenIncreasedBy($vps_written, count($values));

		$expected_events = count($values);

		// The trigger must produce one event per transition: PROBLEM, RESOLVED, PROBLEM, RESOLVED, ... . Every
		// value flips the trigger, so each sent (clock, ns) must appear as exactly one event. If the count is
		// still off on the final wait iteration, the info callback diagnoses which sent offset/timestamp never
		// produced an event and appends it to the failure message.
		$this->waitForAllTriggerEventCounts([$triggerid], $expected_events,
			function () use ($triggerid, $sent) {
				return $this->diagnoseMissingBurstEvents($triggerid, $sent);
			}
		);

		// Events are newest-first, so they alternate RESOLVED, PROBLEM, RESOLVED, PROBLEM, ... (the burst
		// ends on a recovery, so the newest event is RESOLVED).
		$events = $this->getScenarioEventsByTrigger([$triggerid])[$triggerid];
		$info = 'trigger '.$triggerid.': '.count($events).' events';
		$this->assertCount($expected_events, $events, $info);
		foreach ($events as $pos => $event) {
			$expected_value = ($pos % 2 === 0) ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
			$this->assertEquals($expected_value, (int) $event['value'], $info.' at pos '.$pos);
		}

		$this->waitForNoOpenProblems([$triggerid], 'open and immediate recovery single item');

		if ($serviceid !== null) {
			// The burst ended on a recovery, so the trigger is OK and the service - matched only by the
			// trigger tag - must have followed every cycle and be OK too, with no open service problem left
			// over from the long rapid burst.
			$this->assertSingleServiceStatus($serviceid, ZBX_SEVERITY_OK, 0);

			// The service is matched to every trigger problem by the trigger tag, so the burst must have
			// driven exactly as many service events (one service PROBLEM per trigger PROBLEM, one service
			// RESOLVED per trigger RESOLVED) as the trigger did - the same expected_events. More means the
			// service manager cached a duplicated service problem; fewer means one was dropped or collapsed.
			$this->waitForServiceEventCount($serviceid, $service_event_baseline, $expected_events);

			// Now open one final problem and verify the service manager matches it to the service purely by
			// the trigger tag: the service reaches the trigger's DISASTER priority with exactly one open
			// service problem (a regression guard against a duplicated service problem being cached by the
			// service manager during the long burst).
			$this->dispatchSenderValues([['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '1']]);
			$this->waitForOpenProblemCount([$triggerid], 1);
			$this->assertSingleServiceStatus($serviceid, TRIGGER_SEVERITY_DISASTER, 1);

			// Close it again: the service follows the recovery back to OK with no open service problem.
			$this->dispatchSenderValues([['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0']]);
			$this->assertSingleServiceStatus($serviceid, ZBX_SEVERITY_OK, 0);
			$this->waitForNoOpenProblems([$triggerid],
				'open and immediate recovery single item with service (final close)');
		}
	}

	/**
	 * Return the CEP service that createServicesAndActions() created for $triggerid (matched to it by the
	 * trigger's own SERVICE_TAG tag), or null when the per-trigger services do not exist (service tests
	 * skipped). The service is looked up by the trigger's SERVICE_TAG value, which is the same component
	 * value the service's problem tag matches on (see getServiceIdsByComponent).
	 */
	private function getServiceidForTrigger(string $triggerid): ?string {
		$service_by_component = $this->getServiceIdsByComponent();
		if (empty($service_by_component)) {
			return null;
		}

		$response = $this->call('trigger.get', [
			'triggerids' => [$triggerid],
			'output' => ['triggerid'],
			'selectTags' => 'extend'
		]);
		$this->assertCount(1, $response['result'], 'Expected exactly one trigger '.$triggerid.'.');

		$service_tag = current(array_filter($response['result'][0]['tags'],
			fn($t) => $t['tag'] === self::SERVICE_TAG
		));
		$this->assertNotFalse($service_tag,
			'Trigger '.$triggerid.' has no '.self::SERVICE_TAG.' tag.');

		$this->assertArrayHasKey($service_tag['value'], $service_by_component,
			'No CEP service matches trigger '.$triggerid.' by its '.self::SERVICE_TAG.' value '
				.$service_tag['value'].'.');

		return $service_by_component[$service_tag['value']];
	}

	/**
	 * Capture the highest eventid currently recorded for the service $serviceid's own events (source
	 * SERVICE), so a later count is a delta relative to this point. The per-trigger services are shared
	 * across the suite, so a service has accumulated events from earlier scenarios.
	 */
	private function captureServiceEventBaseline(string $serviceid): int {
		$response = $this->call('event.get', [
			'objectids' => [$serviceid],
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'limit' => 1,
			'output' => ['eventid']
		]);

		return empty($response['result']) ? 0 : (int) $response['result'][0]['eventid'];
	}

	/**
	 * Wait until exactly $expected service events (source SERVICE) have been recorded for $serviceid since
	 * $baseline_id. The service is matched to every trigger problem by the trigger tag, so a burst that
	 * flips the trigger $expected times must drive exactly $expected service events; more means the service
	 * manager cached a duplicated service problem, fewer means one was dropped or collapsed.
	 */
	private function waitForServiceEventCount(string $serviceid, int $baseline_id, int $expected): void {
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => [$serviceid],
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE,
			'eventid_from' => $baseline_id + 1
		], $expected, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Poll until the single service $serviceid reaches $expected_status (a ZBX_SEVERITY_* value, or
	 * ZBX_SEVERITY_OK once recovered) and holds exactly $expected_open_problems open service problems. The
	 * per-service problem count is a regression guard against the service manager adding the same event to a
	 * service more than once. Mirrors assertServicesStatus() but scoped to one service.
	 */
	private function assertSingleServiceStatus(string $serviceid, int $expected_status,
			int $expected_open_problems): void {
		$this->callUntilCountIsPresent('service.get', [
			'serviceids' => [$serviceid],
			'filter' => ['status' => $expected_status]
		], 1, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => [$serviceid],
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE
		], $expected_open_problems, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Same as runOpenAndImmediateRecoveryTest but the batch is grouped by value instead of by key:
	 * alternating waves of 1, 0, 1, each wave sent to every discovered item, all in one batch with
	 * strictly increasing (clock, ns). Each 1 wave opens one problem per trigger and the 0 wave
	 * recovers it, so the batch ends with every trigger in PROBLEM. Once the batch is fully processed,
	 * a separate closing 0 wave is sent to recover the open problems. So unlike the per-key bursts,
	 * each trigger's transitions are separated by values for every other item, and CEP must still emit
	 * exactly one event per transition and leave no open problems. When $restart is true, the server
	 * is restarted first.
	 *
	 * When $with_service is true the per-trigger services createServicesAndActions() already created (each
	 * matched to its trigger only by the trigger's own SERVICE_TAG tag) are verified as well: the batch ends
	 * on a 1 wave with every trigger in PROBLEM, so every service must have followed the interleaved waves and
	 * reached the trigger's DISASTER priority with exactly one open service problem (no duplicate cached during
	 * the waves), and after the closing 0 wave every service must be back to OK with no open service problem.
	 * One representative service is additionally checked to have recorded exactly one service event per trigger
	 * transition (no service event collapsed or duplicated during the interleaved waves).
	 */
	private function runOpenAndImmediateRecoveryValueWavesTest(bool $restart, bool $with_service = false): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);

		// When verifying services, pick one representative discovered trigger and baseline its service's own
		// events (source SERVICE) before the waves, so the post-wave count is a delta: the shared per-trigger
		// services have accumulated events from earlier scenarios.
		$serviceid = $with_service ? $this->getServiceidForTrigger($triggerids[0]) : null;
		$service_event_baseline = ($serviceid !== null) ? $this->captureServiceEventBaseline($serviceid) : 0;

		$data = [];
		foreach (['1', '0', '1'] as $value) {
			foreach ($keys as $key) {
				$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
			}
		}
		$vps_written = $this->getVpsWritten();
		$this->dispatchSenderValues($data);

		// Confirm the whole batch was ingested (written to the history cache) before asserting on
		// events, so a dropped or not-yet-processed value surfaces here rather than as a confusing
		// event mismatch.
		$this->assertVpsWrittenIncreasedBy($vps_written, count($data));

		// Every wave flips each trigger, so the batch produces exactly three events per trigger:
		// PROBLEM, RESOLVED, PROBLEM. Waiting for the exact count also ensures the whole batch is
		// processed before the closing 0 wave is sent.
		$this->waitForAllTriggerEventCounts($triggerids, 3);

		// The batch ends on a 1 wave, so every trigger must be left in PROBLEM.
		$this->assertAllTriggerValues($triggerids, TRIGGER_VALUE_TRUE, 'must be PROBLEM after the batch');

		if ($with_service) {
			// Every trigger is in PROBLEM, so every per-trigger service - matched to its trigger only by the
			// trigger tag - must have followed the interleaved cross-item waves and reached the trigger's
			// DISASTER priority with exactly one open service problem. More means the service manager cached a
			// duplicated service problem during the waves; fewer means one was dropped or collapsed.
			$this->assertServicesStatus(TRIGGER_SEVERITY_DISASTER, count(self::$serviceids));
			$this->assertOneServiceProblemPerService();
		}

		// Send the closing 0 wave separately, after the batch has been fully processed, to recover
		// the problems left open by the batch's final 1 wave.
		$data = [];
		foreach ($keys as $key) {
			$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'];
		}
		$vps_written = $this->getVpsWritten();
		$this->dispatchSenderValues($data);
		$this->assertVpsWrittenIncreasedBy($vps_written, count($data));

		// The closing wave adds one RESOLVED event per trigger: PROBLEM, RESOLVED, PROBLEM, RESOLVED
		// in total. The wait requires an exact total, so a collapsed or extra event fails it too.
		$expected_events = 4;
		$this->waitForAllTriggerEventCounts($triggerids, $expected_events);

		// The closing 0 wave must have recovered every trigger back to OK.
		$this->assertAllTriggerValues($triggerids, TRIGGER_VALUE_FALSE, 'must be OK after the closing 0 wave');

		// Events are newest-first, so they alternate RESOLVED, PROBLEM, RESOLVED, PROBLEM (the batch
		// ends on a 0 wave, so the newest event is RESOLVED).
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$events = $events_by_trigger[$triggerid];
			$info = 'trigger #'.$idx.': '.count($events).' events';
			$this->assertCount($expected_events, $events, $info);
			foreach ($events as $pos => $event) {
				$expected_value = ($pos % 2 === 0) ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$this->assertEquals($expected_value, (int) $event['value'], $info.' at pos '.$pos);
			}
		}

		$this->waitForNoOpenProblems($triggerids, 'open and immediate recovery value waves');

		if ($with_service) {
			// The closing 0 wave recovered every trigger, so every per-trigger service must have followed the
			// recovery back to OK with no open service problem left over from the waves.
			$this->assertServicesStatus(ZBX_SEVERITY_OK, 0);

			// The representative service is matched to its one trigger by the trigger tag, so the four trigger
			// events (PROBLEM, RESOLVED, PROBLEM, RESOLVED) must have driven exactly four service events on it -
			// one service PROBLEM per trigger PROBLEM and one service RESOLVED per trigger RESOLVED. More means
			// the service manager cached a duplicated service problem during the interleaved cross-item waves;
			// fewer means one was dropped or collapsed.
			$this->waitForServiceEventCount($serviceid, $service_event_baseline, $expected_events);
		}
	}

	/**
	 * Same as runOpenAndImmediateRecoveryValueWavesTest but with an even number of waves (1, 0, 1, 0),
	 * all in one batch with strictly increasing (clock, ns). The batch itself ends on a 0 wave, so it
	 * leaves every trigger OK and no separate closing wave is needed. When $restart is true, the server
	 * is restarted first.
	 */
	private function runOpenAndImmediateRecoveryValueWavesEndOkTest(bool $restart): void {
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$this->captureEventBaseline($triggerids);

		$data = [];
		foreach (['1', '0', '1', '0'] as $value) {
			foreach ($keys as $key) {
				$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
			}
		}
		$vps_written = $this->getVpsWritten();
		$this->dispatchSenderValues($data);

		// Confirm the whole batch was ingested (written to the history cache) before asserting on
		// events, so a dropped or not-yet-processed value surfaces here rather than as a confusing
		// event mismatch.
		$this->assertVpsWrittenIncreasedBy($vps_written, count($data));

		// Every wave flips each trigger, so the batch produces exactly four events per trigger:
		// PROBLEM, RESOLVED, PROBLEM, RESOLVED. The wait requires an exact total, so a collapsed or
		// extra event fails it too.
		$expected_events = 4;
		$this->waitForAllTriggerEventCounts($triggerids, $expected_events);

		// The batch ends on a 0 wave, so every trigger must be left OK.
		$this->assertAllTriggerValues($triggerids, TRIGGER_VALUE_FALSE, 'must be OK after the batch');

		// Events are newest-first, so they alternate RESOLVED, PROBLEM, RESOLVED, PROBLEM (the batch
		// ends on a 0 wave, so the newest event is RESOLVED).
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$events = $events_by_trigger[$triggerid];
			$info = 'trigger #'.$idx.': '.count($events).' events';
			$this->assertCount($expected_events, $events, $info);
			foreach ($events as $pos => $event) {
				$expected_value = ($pos % 2 === 0) ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$this->assertEquals($expected_value, (int) $event['value'], $info.' at pos '.$pos);
			}
		}

		$this->waitForNoOpenProblems($triggerids, 'open and immediate recovery value waves end OK');
	}

	/**
	 * Smoke test (part 1/2): a discovered item becomes unsupported and its trigger enters the UNKNOWN
	 * state. The internal "Report unknown triggers" and "Report not supported items" actions are active for
	 * this test (enabled by runOpenUnknownTest() when SCOPED_INTERNAL_ACTIONS is set, otherwise enabled for
	 * the whole suite in prepareData()), so the server opens an internal problem for every unsupported item
	 * and every unknown trigger. The trigger value stays OK (it was not in a problem) while the state
	 * becomes UNKNOWN; the UNKNOWN state and the open internal problems are left in place and cleared by
	 * testTriggerCEP_CloseUnknown.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenUnknown() {
		$this->runOpenUnknownTest();
	}

	/**
	 * Smoke test (part 2/2): the UNKNOWN state entered by testTriggerCEP_OpenUnknown clears when a
	 * numeric value is sent again. The triggers return to NORMAL/OK, the items become supported, and
	 * all internal problems (item-not-supported and trigger-unknown) are resolved. Runs as a separate
	 * test so the UNKNOWN state persists across the test boundary before recovery.
	 *
	 * @depends testTriggerCEP_OpenUnknown
	 */
	public function testTriggerCEP_CloseUnknown() {
		$this->runCloseUnknownTest();
	}

	/**
	 * Same scenario as testTriggerCEP_OpenUnknown but the server component is stopped and restarted
	 * before the test runs, to verify the UNKNOWN state and internal problems are produced correctly
	 * after a fresh restart.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_OpenUnknownRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$this->runOpenUnknownTest();
	}

	/**
	 * Same scenario as testTriggerCEP_CloseUnknown but the server component is stopped and restarted
	 * before the test runs, to verify recovery and internal-problem resolution after a fresh restart.
	 *
	 * @depends testTriggerCEP_OpenUnknownRestart
	 */
	public function testTriggerCEP_CloseUnknownRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$this->runCloseUnknownTest();
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Verify CEP behaviour on the discovered trigger:
	 *
	 *   1. Send value 1        → trigger fires   (NORMAL / PROBLEM)
	 *   2. Send non-numeric    → item unsupported (UNKNOWN / PROBLEM)
	 *   3. Send value 0        → trigger recovers (NORMAL / OK)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 *
	 */
	public function testTriggerCEP_TriggerStateTransitions() {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		// Fire all triggers by sending a numeric value of 1 to all discovered items.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '1'], $keys)
		);
		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_TRUE);

		// Push a non-numeric value to flip all items into unsupported state.
		// CEP must keep all trigger values as PROBLEM while state becomes UNKNOWN.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'not_a_number',
					'state' => ITEM_STATE_NOTSUPPORTED], $keys)
		);
		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_TRUE);

		// Recover all triggers by sending a numeric value of 0 to all discovered items.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'], $keys)
		);
		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_FALSE);
	}

	/**
	 * Assess trigger event generation for all five state transitions:
	 *
	 *   1. Trigger A: OK→OK              – no new event, lastchange not updated
	 *   2. Trigger A: OK→PROBLEM         – PROBLEM event generated
	 *   3. Trigger A: item unsupported   – CEP keeps trigger PROBLEM, state becomes UNKNOWN
	 *   4. Trigger A: PROBLEM→PROBLEM    – item supported again; no new event, lastchange not updated
	 *   5. Trigger A: PROBLEM→OK         – RESOLVED event generated
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessment() {
		$this->runEventAssessmentTest(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessment but the server component is
	 * stopped and restarted between each step to verify CEP state survives a restart.
	 *
	 * @depends testTriggerCEP_EventAssessment
	 */
	public function testTriggerCEP_EventAssessmentRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runEventAssessmentTest(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Verify all meaningful parent→dependent state-transition combinations:
	 *
	 *   1. Parent OK→PROBLEM              – parent fires; PROBLEM event.
	 *   2. Dep condition met while parent PROBLEM – dep suppressed; no event, stays OK.
	 *   3. Parent PROBLEM→OK              – parent resolves; dep (condition still met) fires.
	 *   4. Dep PROBLEM→OK                 – dep recovers.
	 *
	 *   5. Parent OK→PROBLEM              – dep condition never becomes true; dep stays OK.
	 *   6. Parent PROBLEM→OK              – dep condition still false; dep produced no events.
	 *
	 *   7. Dep OK→PROBLEM                 – dep fires normally while parent is OK.
	 *   8. Parent OK→PROBLEM              – parent fires; dep already PROBLEM.
	 *   9. Dep PROBLEM→OK while parent PROBLEM – recovery suppressed; dep stays PROBLEM.
	 *  10. Parent PROBLEM→OK              – parent resolves; dep already OK.
	 *  Filter example: (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_DependentTrigger)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_DependentTrigger() {
		$this->runDependentTriggerTest(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_DependentTrigger but the server component is
	 * stopped and restarted between each step to verify CEP state survives a restart.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_DependentTriggerRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Assess trigger event generation for all five state transitions with "None" recovery mode:
	 *
	 *   1. Trigger A: OK→OK              – no new event, lastchange not updated
	 *   2. Trigger A: OK→PROBLEM         – PROBLEM event generated
	 *   3. Trigger A: item unsupported   – CEP keeps trigger PROBLEM, state becomes UNKNOWN
	 *   4. Trigger A: PROBLEM→PROBLEM    – item supported again; no new event, lastchange not updated
	 *   5. Trigger A: PROBLEM→OK         – trigger stays PROBLEM (None recovery), no event
	 *
	 * @depends testTriggerCEP_TriggerStateTransitions
	 */
	public function testTriggerCEP_EventAssessmentNone() {
		$this->prepareDataNoneOkEvent();
		$this->runEventAssessmentTest(false);
		$this->prepareDataRestoreRecovery();
		$this->assertRecoveryAfterRestore(false);
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentNone but the server component is
	 * stopped and restarted between each step to verify CEP state survives a restart.
	 *
	 * @depends testTriggerCEP_EventAssessmentNone
	 */
	public function testTriggerCEP_EventAssessmentNoneRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataNoneOkEvent();
		$this->runEventAssessmentTest(true);
		$this->prepareDataRestoreRecovery();
		$this->assertRecoveryAfterRestore(true);
	}

	/**
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentRecoveryExpression() {
		$this->clearDiscoveredItemHistory();
		$this->prepareDataRecoveryExpression();
		$this->runEventAssessmentTest(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentRecoveryExpression
	 */
	public function testTriggerCEP_EventAssessmentRecoveryExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runEventAssessmentTest(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentRecoveryExpression
	 */
	public function testTriggerCEP_DependentTriggerRecoveryExpression() {
		$this->runDependentTriggerTest(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerRecoveryExpression
	 */
	public function testTriggerCEP_DependentTriggerRecoveryExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerRecoveryExpression
	 */
	public function testTriggerCEP_EventAssessmentMultipleEvent() {
		$this->prepareDataMultipleEventsRecoveryExpression();
		$this->runEventAssessmentTest(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentMultipleEvent
	 */
	public function testTriggerCEP_EventAssessmentMultipleEventRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runEventAssessmentTest(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentMultipleEvent
	 */
	public function testTriggerCEP_DependentTriggerMultipleEvent() {
		$this->runDependentTriggerTest(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerMultipleEvent
	 */
	public function testTriggerCEP_DependentTriggerMultipleEventRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * prepareDataTagCorrelation() switches the prototypes to tag-correlation mode and resends LLD;
	 * the post-LLD defaults (numeric items, last()<>0 expression) are exactly what this scenario
	 * needs, so it only depends on the LLD step and can run in isolation together with the other
	 * tag-correlation variants and the service-correlation tests (see the regexp below).
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentTagCorrelation() {
		$this->prepareDataTagCorrelation();
		$this->runEventAssessmentTest(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentTagCorrelation
	 */
	public function testTriggerCEP_EventAssessmentTagCorrelationRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataTagCorrelation();
		$this->runEventAssessmentTest(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentTagCorrelation
	 */
	public function testTriggerCEP_DependentTriggerTagCorrelation() {
		$this->runDependentTriggerTest(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * @depends testTriggerCEP_DependentTriggerTagCorrelation
	 */
	public function testTriggerCEP_DependentTriggerTagCorrelationRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runDependentTriggerTest(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * prepareDataServiceCorrelation() fully reconfigures the prototypes and resends LLD, so this
	 * test is self-contained and only needs the discovered host/triggers from the LLD step.
	 * Run in isolation as
	 * (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_.*TagCorrelation.*|testTriggerCEP_EventAssessmentServiceCorrelation.*)
	 * (the .* options also pull in the Restart, dependent-trigger and ManualClose variants that chain to these tests)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentServiceCorrelation() {
		$this->prepareDataServiceCorrelation();
		$this->runEventAssessmentTestCorrelation(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * @depends testTriggerCEP_EventAssessmentServiceCorrelation
	 */
	public function testTriggerCEP_EventAssessmentServiceCorrelationRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataServiceCorrelation();
		$this->runEventAssessmentTestCorrelation(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentServiceCorrelation but the remaining
	 * "down_1" problem is closed via manual close rather than an automatic recovery event.
	 *
	 * @depends testTriggerCEP_EventAssessmentServiceCorrelation
	 */
	public function testTriggerCEP_EventAssessmentServiceCorrelationManualClose() {
		$this->prepareDataServiceCorrelation();
		$this->runEventAssessmentTestCorrelationManualClose(false);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentServiceCorrelationManualClose but the
	 * server component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentServiceCorrelationManualClose
	 */
	public function testTriggerCEP_EventAssessmentServiceCorrelationManualCloseRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataServiceCorrelation();
		$this->runEventAssessmentTestCorrelationManualClose(true);
		$this->waitForNoOpenProblems(self::$discovered_triggerids);
	}

	/**
	 * Verify cross-trigger-prototype global event correlation: proto 1 and proto 2 both fire
	 * PROBLEM events with service="down"; sending "up" to proto 1 generates a RESOLVED event
	 * with service="up" which triggers the global correlation rule (old service="down",
	 * new service="up", CLOSE_OLD) to close proto 2's open problems without any explicit
	 * recovery sent to proto 2.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger() {
		/*$this->triggerCEP_Cleanup();
		$this->testTriggerCEP_LLDDiscovery();*/
		$this->prepareDataGlobalCorrelation();
		$this->runEventAssessmentTestGlobalCorrelationCrossTrigger(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger but the
	 * server component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCrossTriggerRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelation();
		$this->runEventAssessmentTestGlobalCorrelationCrossTrigger(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same cross-trigger-prototype global event correlation scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger, but the correlation rule uses
	 * CONDITION_EVAL_TYPE_EXPRESSION with a custom formula ("A and B and C") instead of
	 * CONDITION_EVAL_TYPE_AND_OR, exercising the custom expression evaluation path.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCrossTriggerExpression$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCrossTriggerExpression() {
		$this->prepareDataGlobalCorrelation(CONDITION_EVAL_TYPE_EXPRESSION);
		$this->runEventAssessmentTestGlobalCorrelationCrossTrigger(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Verify parity-selective global event correlation (CONDITION_EVAL_TYPE_AND_OR). Every discovered
	 * trigger carries an 'odd' tag ('1' for odd components, '0' for even). First a problem is opened on
	 * every trigger; then an "even" correlation rule closes the even problems (the odd ones stay open),
	 * and finally an "odd" correlation rule closes the odd problems, so no open problem remains.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationParity)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationParity() {
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(false, CONDITION_EVAL_TYPE_AND_OR);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationParity but the server component
	 * is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationParity
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationParityRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(true, CONDITION_EVAL_TYPE_AND_OR);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same parity-selective global event correlation scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationParity (open all, then close even, then odd), but
	 * both correlation rules use CONDITION_EVAL_TYPE_EXPRESSION with a custom formula
	 * ("A and B and C and D"). The expression variant additionally AND-s a 'type=cep-dep' new-event
	 * condition alongside the 'odd' new-event condition — two same-type conditions that only the
	 * expression evaltype can AND together.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationParityExpression$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationParityExpression() {
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(false, CONDITION_EVAL_TYPE_EXPRESSION);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationParityExpression but the server
	 * component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationParityExpression
	 */
	/*public function testTriggerCEP_EventAssessmentGlobalCorrelationParityExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(true, CONDITION_EVAL_TYPE_EXPRESSION);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}*/

	/**
	 * Same assessment as testTriggerCEP_EventAssessmentGlobalCorrelationParity, but the correlation rules
	 * have a single old-event odd=$parity condition (no component tag pair), so each rule closes every
	 * problem of its parity at once, and the order is flipped: odd problems are closed first, then even.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAll$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAll() {
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(false, CONDITION_EVAL_TYPE_AND_OR, true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAll but the server
	 * component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAll
	 */
	/*public function testTriggerCEP_EventAssessmentGlobalCorrelationParityCloseAllRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationParity();
		$this->runEventAssessmentTestGlobalCorrelationParity(true, CONDITION_EVAL_TYPE_AND_OR, true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}*/

	/**
	 * Verify "close old down when new up" global event correlation. Each discovered trigger opens two
	 * "down" problems, each with a globally unique 'service' id; then the matching "up" values — themselves
	 * PROBLEM events, since the trigger expression matches "up" too — close exactly the corresponding
	 * "down" problem (and themselves) via a rule keyed on old state="down" + new state="up" + a service
	 * tag pair. Unique ids make the closing strictly 1:1 rather than closing every problem at once. No
	 * open problem remains.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}


	/**
	 * Same "close old down when new up" scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp, but the whole flow lands on a single
	 * discovered item (and its one trigger) instead of every discovered item, exercising the correlation
	 * close-on-up path on one event stream.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up (see testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem) with
	 * the correlation rule created from scratch under CONDITION_EVAL_TYPE_AND. Both conditions (new
	 * state="up" + service tag pair) are AND'd, giving the same 1:1 close-on-up as AND_OR.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAnd$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAnd() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created under CONDITION_EVAL_TYPE_AND_OR and then
	 * updated in place to CONDITION_EVAL_TYPE_AND, exercising an evaltype transition on an existing rule.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndUpdate$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndUpdate() {
		$this->prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition(CONDITION_EVAL_TYPE_AND_OR,
			CONDITION_EVAL_TYPE_AND);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created from scratch under
	 * CONDITION_EVAL_TYPE_AND_OR (the two distinct-type conditions are AND'd), the recreate-from-scratch
	 * counterpart of the default in-place testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndOr$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndOr() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND_OR, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created under CONDITION_EVAL_TYPE_AND and then
	 * updated in place to CONDITION_EVAL_TYPE_AND_OR, exercising an evaltype transition on an existing rule.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndOrUpdate$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemAndOrUpdate() {
		$this->prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition(CONDITION_EVAL_TYPE_AND,
			CONDITION_EVAL_TYPE_AND_OR);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created from scratch under
	 * CONDITION_EVAL_TYPE_EXPRESSION (custom formula "A and B"), exercising the custom expression evaluation
	 * path on a single event stream.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemExpression$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemExpression() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_EXPRESSION, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created under CONDITION_EVAL_TYPE_AND_OR and then
	 * updated in place to CONDITION_EVAL_TYPE_EXPRESSION (custom formula "A and B"), exercising a transition
	 * from a basic evaltype to a custom expression on an existing rule.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemExpressionUpdate$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemExpressionUpdate() {
		$this->prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition(CONDITION_EVAL_TYPE_AND_OR,
			CONDITION_EVAL_TYPE_EXPRESSION);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created from scratch under CONDITION_EVAL_TYPE_OR.
	 * OR-ing the new state="up" condition with the tag pair would match every open problem at once, so the
	 * OR rule keeps only the service tag pair (see buildCloseOnUpCorrelationParams); with one condition OR
	 * is equivalent to AND, preserving the 1:1 close-on-up pairing.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemOr$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemOr() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_OR, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * Single-item close-on-up with the correlation rule created under CONDITION_EVAL_TYPE_AND_OR and then
	 * updated in place to CONDITION_EVAL_TYPE_OR (single service tag pair condition), exercising an evaltype
	 * transition that also drops a condition on an existing rule.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemOrUpdate$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItemOrUpdate() {
		$this->prepareDataGlobalCorrelationCloseOnUpEvaltypeTransition(CONDITION_EVAL_TYPE_AND_OR,
			CONDITION_EVAL_TYPE_OR);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
		$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
	}

	/**
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpFromSameTrigger$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpFromSameTrigger() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, false, false, false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Test correlation rule update behavior: verify that changing from CLOSE_OLD+CLOSE_NEW to CLOSE_NEW only
	 * leaves old problems open, and changing back to CLOSE_OLD+CLOSE_NEW restores the closing behavior.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpUpdateBehavior$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpUpdateBehavior() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpUpdateBehavior(false);
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp
	 * (correlation still pairs on the 'service' trigger tag), but two independent webhook media types with
	 * process_tags enabled additionally add a WEB_SERVICE_TAG and a WEB_SERVICE_TAG2 tag to every problem
	 * event from JavaScript, each driven by its own trigger action on the discovered CEP triggers. After the
	 * scenario the test asserts that every problem event carries both tags (WEB_SERVICE_TAG matching the
	 * trailing number of the event name, WEB_SERVICE_TAG2 the same number prefixed with 'second_'),
	 * verifying that tags returned by separate media types are all applied to the events they were
	 * generated for.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND_OR, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		// The scenario opens two "down" problems per trigger (waves 1 and 2), which stay open long enough to
		// escalate and run the tagging webhook. The two "up" waves are PROBLEM events too, but each is closed
		// by correlation (CLOSE_NEW) on creation, so its escalation is cancelled and the webhook never fires —
		// only the down problems get tagged. Hence 2 tagged problem events per trigger (key).
		$m = count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY))
			+ count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2));

		try {
			// Bound the event.get verification below to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);

			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, 2 * $m);
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, 2 * $m);
		}
		finally {
			$this->removeExtraTagWebhookAction();
		}
	}

	/**
	 * Same tag-application scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS, but the
	 * WEB_SERVICE_TAG assertion runs after every wave instead of only in the final state, verifying that the
	 * tags returned by the media type are applied to the down problem events as they open and are not
	 * disturbed by the "up" waves. Each down problem escalates and runs the tagging webhook, so the tag count
	 * grows to $m after wave 1 and 2 * $m after wave 2. The two "up" waves are PROBLEM events too, but each is
	 * closed by correlation (CLOSE_NEW) on creation, so its escalation is cancelled and it is never tagged —
	 * the count stays 2 * $m after waves 3 and 4.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSAfterEachWave$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSAfterEachWave() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND_OR, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			// Pass $check_tags = true so the run asserts the WEB_SERVICE_TAG tag count after every wave.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeExtraTagWebhookAction();
		}
	}

	/**
	 * Same tag-application scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS, but the
	 * webhook-applied tags drive services into problem state instead of only being asserted on the events.
	 * The webhook additionally returns a WEB_COMPONENT_TAG tag (the event's 'component' tag value), and one
	 * service per discovered component is created whose only problem tag matches that webhook-applied tag.
	 * Unlike the per-trigger CEP services (matched via the SERVICE_TAG trigger tag), no trigger tag matches
	 * these services, so each can go into problem state only after the escalation runs the tagging webhook
	 * and the tags it returns are applied to the open problem event. The run asserts the services start OK,
	 * turn DISASTER once the webhook tags the open problems (and stay DISASTER through waves 2 and 3), drop
	 * to WARNING once the still-open problems are manually downgraded after wave 3 and recover to OK once
	 * global correlation closes every problem in wave 4.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSServices$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSServices() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND_OR, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->createWebTagServices();

			// $check_tags sequences each wave on the webhook having tagged the events; $check_web_services
			// asserts the service state transitions driven by those webhook-applied tags.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true, false, true, true);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeWebTagServices();
			$this->removeExtraTagWebhookAction();
		}
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp but the server component
	 * is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp,
	 * but once every problem is open (at the trigger's DISASTER priority, so each per-trigger service is at
	 * DISASTER too) the open problems are manually downgraded to WARNING via event.acknowledge. When services
	 * exist the test also verifies the service manager follows the manual severity change: every service drops
	 * from DISASTER to WARNING, then recovers to OK once the "up" values close the problems. When services are
	 * disabled the service assertions are skipped and the close-on-up flow runs as usual.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSeverity$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSeverity() {
		$this->prepareDataGlobalCorrelationCloseOnUp();
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSeverity(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp,
	 * but the discovered host only enters data-collection maintenance after the first wave of problems is
	 * already open: the maintenance is created mid-run, so the already-open problems must be suppressed
	 * retroactively, and every problem opened afterwards (while maintenance is active) suppressed at
	 * creation time too, while global correlation still closes them all, leaving nothing open.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirst$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirst() {
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// Moving the maintenance out of its active window only changes the configuration; the timer process
			// still has to take the host out of maintenance and clear the suppression of the events opened during
			// the run (their event_suppress rows persist even after the problems were closed by correlation). Wait
			// until nothing on the discovered host is suppressed any more, so the next test starts with the
			// host fully out of maintenance and cannot observe stale suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp,
	 * but the discovered host only enters data-collection maintenance after the first wave of problems is
	 * already open: the maintenance is created mid-run, so the already-open problems must be suppressed
	 * retroactively, and every problem opened afterwards (while maintenance is active) suppressed at
	 * creation time too, while global correlation still closes them all, leaving nothing open.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirstRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirstRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// Moving the maintenance out of its active window only changes the configuration; the timer process
			// still has to take the host out of maintenance and clear the suppression of the events opened during
			// the run (their event_suppress rows persist even after the problems were closed by correlation). Wait
			// until nothing on the discovered host is suppressed any more, so the next test starts with the
			// host fully out of maintenance and cannot observe stale suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirst,
	 * but verifies that problems and services are suppressed during maintenance and no longer suppressed after
	 * the maintenance is stopped. The stopped maintenances are then resumed one at a time out of creation
	 * order (middle, first, last) - suppression must return after the first resume and survive the
	 * overlapping ones - and finally stopped again, after which suppression must clear once more.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_SuppressUnsuppressProblems$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_SuppressUnsuppressProblems() {
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true);
		}
		finally {
			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_SuppressUnsuppressProblems,
	 * but the server component is stopped and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_SuppressUnsuppressProblemsRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_SuppressUnsuppressProblemsRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true);
		}
		finally {
			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "close old down when new up" scenario as testTriggerCEP_SuppressUnsuppressProblems, but instead
	 * of host-wide maintenances that suppress every problem, one maintenance is created per discovered
	 * component, each scoped to that component through a 'component' problem-tag filter. Every open problem
	 * must then be suppressed by exactly the single maintenance whose tag matches it (and by no other),
	 * verifying tag-scoped maintenance suppression in CEP. Stopping the maintenances clears the
	 * suppression, resuming brings it back per matching tag, and global correlation still closes the
	 * problems normally.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_SuppressUnsuppressProblemsPerTag$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_SuppressUnsuppressProblemsPerTag() {
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			// maintenance_after_first=true, stop_maintenance_and_verify_suppression=true,
			// maintenance_by_tag=true
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true, true, false, true);
		}
		finally {
			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "per problem tag" scenario as testTriggerCEP_SuppressUnsuppressProblemsPerTag, but the server
	 * component is stopped and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_SuppressUnsuppressProblemsPerTagRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_SuppressUnsuppressProblemsPerTagRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataGlobalCorrelationCloseOnUp();
		try {
			// Same as testTriggerCEP_SuppressUnsuppressProblemsPerTag but with restart=true.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true, true, false, true);
		}
		finally {
			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "close old down when new up" scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp, but the correlation rule uses
	 * CONDITION_EVAL_TYPE_AND (every condition AND'd regardless of type) instead of
	 * CONDITION_EVAL_TYPE_AND_OR. The rule's two conditions (new state="up" + service tag pair) are of
	 * distinct types, so AND evaluates them identically to AND_OR while exercising the
	 * CONDITION_EVAL_TYPE_AND formula-generation path on the close-on-up scenario. The correlation is
	 * recreated from scratch (rather than updated in place) so its evaltype does not depend on whichever
	 * AND_OR CloseOnUp variant ran before it.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpAnd$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpAnd() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_AND, false, true);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}

	/**
	 * The complex event processing (CEP rule) counterpart of
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp: the exact same scenario - it runs the very same
	 * four waves through the very same runner and asserts the same problem counts after each of them - but no
	 * global event correlation rule is involved. The problems are closed by a single CEP rule with a "Tag
	 * correlation" time window keyed on the 'service' tag and the operations
	 *   - Execute when "Event occurred" -> "Close window" (on the "up" events only)
	 *   - Execute when "Window closed"  -> "Close"
	 * so a "down_N" problem stays open in the window of its 'service' id until the matching "up_N" event
	 * closes that window, closing both of them - the CEP equivalent of CLOSE_OLD + CLOSE_NEW. The "up" events
	 * are singled out by a tag value comparison (state Equals "up") on the close-window operation; the
	 * tag-exists flavour of the same condition is covered by
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpTagExists. See
	 * buildCloseOnUpCepRuleParams().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp() {

		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * Same CEP tag correlation close-on-up scenario as
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp - identical waves, identical runner and
	 * identical assertions - but the close-window operation singles out the "up" events with a tag-exists
	 * condition instead of a tag value comparison: both prototypes additionally carry the CEP_STATE_TAG tag,
	 * whose name is built from {ITEM.VALUE} and therefore resolves to 'state_up' only for the "up" events, and
	 * the operation requires that tag to exist. See buildCloseOnUpCepRuleParams().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpTagExists$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpTagExists() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp(true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The complex event processing (CEP rule) counterpart of
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSingleItem: the same "close old down when new
	 * up" flow landing on a single discovered item (and its one trigger) instead of every discovered item,
	 * but with no global event correlation rule involved - the problems are closed by the same single CEP
	 * rule with a "Tag correlation" time window keyed on the 'service' tag as in
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp, exercising the CEP window close path
	 * on one event stream. See buildCloseOnUpCepRuleParams().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSingleItem$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSingleItem() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
			$this->waitForNoOpenProblems([self::$discovered_triggerids[0]]);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpFromSameTrigger: the same
	 * CEP tag correlation close-on-up scenario, but every "up_N" value is sent to the item whose trigger opened
	 * "down_N" instead of the next one, so the closing "up" PROBLEM event is raised on the same trigger. The
	 * window is keyed purely on the 'service' tag value, so the pairing must hold either way.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpFromSameTrigger$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpFromSameTrigger() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, false, false, false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJS: the same CEP tag
	 * correlation close-on-up scenario (the window still pairs on the 'service' trigger tag), but two
	 * independent webhook media types with process_tags enabled additionally add a WEB_SERVICE_TAG and a
	 * WEB_SERVICE_TAG2 tag to every problem event from JavaScript, each driven by its own trigger action on
	 * the discovered CEP triggers. After the scenario the test asserts that every problem event carries both
	 * tags, verifying that tags returned by separate media types are all applied to the events they were
	 * generated for.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp(true, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		// Every one of the four waves opens a problem event per trigger and all of them are tagged, so 4 tagged
		// problem events per trigger (key). Unlike the global correlation flavour - where CLOSE_NEW disables the
		// actions of the problem it closes, so the two "up" waves never escalate and only the "down" problems
		// end up tagged - a CEP rule closing an event leaves its actions enabled (only the correlation paths
		// set CEP_ACTION_DISABLED, see cep_worker.c), so the "up" problems escalate and get tagged as well.
		$m = count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY))
			+ count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2));

		try {
			// Bound the event.get verification below to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);

			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, 4 * $m);
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, 4 * $m);
		}
		finally {
			$this->removeExtraTagWebhookAction();
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSAfterEachWave: same
	 * tag-application scenario as testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS, but the
	 * WEB_SERVICE_TAG assertion runs after every wave instead of only in the final state, verifying that the
	 * tags returned by the media type are applied to the problem events as they open and are not disturbed by
	 * the later waves. A CEP rule closing an event leaves its actions enabled, so the "up" problems escalate
	 * and get tagged too and the count grows by $m per wave: $m, 2 * $m, 3 * $m, 4 * $m
	 * ($up_events_tagged = true).
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSAfterEachWave$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSAfterEachWave() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp(true, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			// Pass $check_tags = true so the run asserts the WEB_SERVICE_TAG tag count after every wave, and
			// $up_events_tagged = true so those counts expect the "up" problems to be tagged as well.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true, false, true, false, false,
				true
			);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeExtraTagWebhookAction();
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpJSServices: same
	 * tag-application scenario as testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS, but the
	 * webhook-applied tags drive services into problem state instead of only being asserted on the events. One
	 * service per discovered component is created whose only problem tag matches the webhook-applied
	 * WEB_COMPONENT_TAG tag, so a service can go into problem state only after the escalation ran the tagging
	 * webhook. The run asserts the services start OK, turn DISASTER once the webhook tags the open problems
	 * (and stay DISASTER through waves 2 and 3), drop to WARNING once the still-open problems are manually
	 * downgraded after wave 3 and recover to OK once the CEP rule closes every problem in wave 4.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSServices$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSServices() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp(true, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->createWebTagServices();

			// $check_tags sequences each wave on the webhook having tagged the events ($up_events_tagged = true:
			// the CEP-closed "up" problems are tagged too); $check_web_services asserts the service state
			// transitions driven by those webhook-applied tags.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true, false, true, true, false,
				true
			);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeWebTagServices();
			$this->removeExtraTagWebhookAction();
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpRestart: same scenario as
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp but the server component is stopped and
	 * restarted between each step, so the CEP windows must be restored from the database with their collected
	 * events and still close the paired problems afterwards.
	 *
	 * @depends testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpSeverity: same CEP tag
	 * correlation close-on-up scenario, but once every problem is open (at the trigger's DISASTER priority, so
	 * each per-trigger service is at DISASTER too) the open problems are manually downgraded to WARNING via
	 * event.acknowledge. When services exist the test also verifies the service manager follows the manual
	 * severity change: every service drops from DISASTER to WARNING, then recovers to OK once the "up" values
	 * close the problems through the CEP window.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSeverity$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSeverity() {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSeverity(false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpMaintenanceAfterFirst: the
	 * discovered host only enters data-collection maintenance after the first wave of problems is already open,
	 * so the already-open problems must be suppressed retroactively and every problem opened afterwards (while
	 * maintenance is active) suppressed at creation time too, while the CEP rule still closes them all, leaving
	 * nothing open.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst() {
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->cleanupCepRules();
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// Moving the maintenance out of its active window only changes the configuration; the timer process
			// still has to take the host out of maintenance and clear the suppression of the events opened during
			// the run (their event_suppress rows persist even after the problems were closed). Wait until nothing
			// on the discovered host is suppressed any more, so the next test starts with the host fully out of
			// maintenance and cannot observe stale suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst but
	 * the server component is stopped and restarted between each step.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirstRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirstRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->cleanupCepRules();
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// See testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst: wait
			// until the timer has taken the host out of maintenance and cleared every suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_SuppressUnsuppressProblems: verifies that problems and services are
	 * suppressed during maintenance and no longer suppressed after the maintenance is stopped. The stopped
	 * maintenances are then resumed one at a time out of creation order (middle, first, last) - suppression must
	 * return after the first resume and survive the overlapping ones - and finally stopped again, after which
	 * suppression must clear once more. The problems are closed by the CEP rule rather than by global
	 * correlation.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagSuppressUnsuppressProblems$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagSuppressUnsuppressProblems() {
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same scenario as testTriggerCEP_CepWindowTagSuppressUnsuppressProblems, but the server component is
	 * stopped and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * The CEP counterpart of testTriggerCEP_SuppressUnsuppressProblemsPerTag: same scenario as
	 * testTriggerCEP_CepWindowTagSuppressUnsuppressProblems, but instead of host-wide maintenances that
	 * suppress every problem, one maintenance is created per discovered component, each scoped to that
	 * component through a 'component' problem-tag filter. Every open problem must then be suppressed by exactly
	 * the single maintenance whose tag matches it (and by no other). Stopping the maintenances clears the
	 * suppression, resuming brings it back per matching tag, and the CEP rule still closes the problems
	 * normally.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTag$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTag() {
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			// maintenance_after_first=true, stop_maintenance_and_verify_suppression=true,
			// maintenance_by_tag=true
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "per problem tag" scenario as testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTag, but the
	 * server component is stopped and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTagRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTagRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
		try {
			// Same as testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTag but with restart=true.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUp: the exact same scenario - the same four
	 * waves through the same runner with the same problem counts after each of them - but the problems are
	 * closed by a rule whose window is a cause and symptom grouping one instead of a tag correlation one. It
	 * groups by the same 'service' tag and closes on the same "up" events, so what it closes and when may not
	 * differ; what it adds is the ranking of what it holds, which the run asserts afterwards: every id ends up
	 * with its "down_N" event as the cause of its window and the "up_N" event that ended that window as the one
	 * symptom of it. See prepareDataCepWindowCauseSymptomCloseOnUp().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUp$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUp() {
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		// Every wave sends one value per discovered item, the two "down" waves with an id set of their own and
		// the two "up" waves closing those same ids again, so the run opens a window per id and every one of
		// them holds a "down" and an "up" event.
		$m = count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY))
			+ count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2));

		try {
			// Bound the ranking verification below to the events generated by this run only.
			$this->captureEventBaseline($all);

			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);

			$this->waitForCloseOnUpCauseSymptomRanking($all, 2 * $m);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * Same cause and symptom close-on-up scenario as
	 * testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUp, with the close-window operation singling out
	 * the "up" events by a tag-exists condition on the CEP_STATE_TAG tag both prototypes then carry - the
	 * counterpart of testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpTagExists. See
	 * buildCloseOnUpCepRuleParams().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpTagExists$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpTagExists() {
		$this->prepareDataCepWindowCauseSymptomCloseOnUp(true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSingleItem: the same close-on-up flow
	 * landing on a single discovered item (and its one trigger), so the two windows the run opens are filled
	 * from one event stream. Both are ranked all the same - each holds the "down" event of its id and the "up"
	 * event that closed it.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpSingleItem$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpSingleItem() {
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();

		// The one trigger the run drives, derived the way the runner derives it.
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE,
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0]
		);

		try {
			// Bound the ranking verification below to the events generated by this run only.
			$this->captureEventBaseline([$triggerid]);

			$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(false);
			$this->waitForNoOpenProblems([$triggerid]);

			// The single-item run sends two ids, so the one trigger fills two windows.
			$this->waitForCloseOnUpCauseSymptomRanking([$triggerid], 2);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpFromSameTrigger: every "up_N" value is sent
	 * to the item whose trigger opened "down_N" instead of the next one, so both events of a window are raised
	 * on the same trigger. The window is keyed purely on the 'service' tag value, so the pairing - and with it
	 * the ranking of the pair - must hold either way.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpFromSameTrigger$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpFromSameTrigger() {
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);
		$m = count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY))
			+ count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2));

		try {
			// Bound the ranking verification below to the events generated by this run only.
			$this->captureEventBaseline($all);

			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, false, false, false);
			$this->waitForNoOpenProblems($all);

			$this->waitForCloseOnUpCauseSymptomRanking($all, 2 * $m);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS: the same close-on-up scenario, but two
	 * independent webhook media types with process_tags enabled additionally add a WEB_SERVICE_TAG and a
	 * WEB_SERVICE_TAG2 tag to every problem event from JavaScript. After the scenario the test asserts that
	 * every problem event carries both tags - the ranked ones included: a symptom escalates like any other
	 * problem, so the tags a media type returns must land on it too.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpJS$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpJS() {
		$this->prepareDataCepWindowCauseSymptomCloseOnUp(true, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		// Every one of the four waves opens a problem event per trigger and all of them are tagged, so 4 tagged
		// problem events per trigger (key) - a CEP rule closing an event leaves its actions enabled, so the
		// "up" problems escalate and get tagged as well, see
		// testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJS.
		$m = count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY))
			+ count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2));

		try {
			// Bound the event.get verification below to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
			$this->waitForNoOpenProblems($all);

			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, 4 * $m);
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, 4 * $m);
		}
		finally {
			$this->removeExtraTagWebhookAction();
			$this->cleanupCepRules();
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSAfterEachWave: same tag-application
	 * scenario as testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpJS, but the WEB_SERVICE_TAG
	 * assertion runs after every wave instead of only in the final state, verifying that the tags returned by
	 * the media type are applied to the problem events as they open and are not disturbed by the later waves.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpJSAfterEachWave$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpJSAfterEachWave() {
		$this->prepareDataCepWindowCauseSymptomCloseOnUp(true, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			// Pass $check_tags = true so the run asserts the WEB_SERVICE_TAG tag count after every wave, and
			// $up_events_tagged = true so those counts expect the "up" problems to be tagged as well.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true, false, true, false, false,
				true
			);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeExtraTagWebhookAction();
			$this->cleanupCepRules();
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpJSServices: the webhook-applied tags drive
	 * services into problem state instead of only being asserted on the events. One service per discovered
	 * component is created whose only problem tag matches the webhook-applied WEB_COMPONENT_TAG tag, so a
	 * service can go into problem state only after the escalation ran the tagging webhook. The run asserts the
	 * services start OK, turn DISASTER once the webhook tags the open problems, drop to WARNING once the
	 * still-open problems are manually downgraded after wave 3 and recover to OK once the rule closes every
	 * problem in wave 4 - the ranking the window gives those problems changes none of it.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpJSServices$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpJSServices() {
		$this->prepareDataCepWindowCauseSymptomCloseOnUp(true, true);

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			// Bound the event.get verification inside the run to events generated by this run only.
			$this->captureEventBaseline($all);

			$this->createWebTagServices();

			// $check_tags sequences each wave on the webhook having tagged the events ($up_events_tagged = true:
			// the CEP-closed "up" problems are tagged too); $check_web_services asserts the service state
			// transitions driven by those webhook-applied tags.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, false, true, false, true, true, false,
				true
			);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->removeWebTagServices();
			$this->removeExtraTagWebhookAction();
			$this->cleanupCepRules();
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpRestart: same scenario as
	 * testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUp but the server component is stopped and
	 * restarted between each step, so the windows must be restored from the database with their collected
	 * events - and with the ranking they built - and still close the paired problems afterwards.
	 *
	 * @depends testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUp
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);
		$m = count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY))
			+ count($this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2));

		try {
			// Bound the ranking verification below to the events generated by this run only.
			$this->captureEventBaseline($all);

			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true);
			$this->waitForNoOpenProblems($all);

			// A restarted window has to keep ranking where it left off: the "up" event of an id arrives after
			// the restart that followed its "down" event, so it may only become a symptom of it.
			$this->waitForCloseOnUpCauseSymptomRanking($all, 2 * $m);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpSeverity: once every problem is open (at the
	 * trigger's DISASTER priority, so each per-trigger service is at DISASTER too) the open problems are
	 * manually downgraded to WARNING via event.acknowledge. When services exist the test also verifies the
	 * service manager follows the manual severity change: every service drops from DISASTER to WARNING, then
	 * recovers to OK once the "up" values close the problems through the window.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpSeverity$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpSeverity() {
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUpSeverity(false);
			$this->waitForNoOpenProblems($all);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst: the discovered host
	 * only enters data-collection maintenance after the first wave of problems is already open, so the
	 * already-open problems must be suppressed retroactively and every problem opened afterwards (while
	 * maintenance is active) suppressed at creation time too, while the rule still closes them all, leaving
	 * nothing open.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpMaintenanceAfterFirst$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpMaintenanceAfterFirst() {
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->cleanupCepRules();
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// See testTriggerCEP_EventAssessmentCepWindowTagCorrelationCloseOnUpMaintenanceAfterFirst: wait
			// until the timer has taken the host out of maintenance and cleared every suppression, so the next
			// test cannot observe stale suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpMaintenanceAfterFirst but the
	 * server component is stopped and restarted between each step.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpMaintenanceAfterFirstRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpMaintenanceAfterFirstRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true);
			$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
		}
		finally {
			$this->cleanupCepRules();
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
			$this->reloadConfigurationCacheAndWaitForLogLine();

			// See testTriggerCEP_EventAssessmentCepWindowCauseSymptomCloseOnUpMaintenanceAfterFirst: wait until
			// the timer has taken the host out of maintenance and cleared every suppression.
			$this->callUntilCountIsPresent('event.get', [
				'hostids' => [self::$disc_hostid],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => true
			], 0, 120, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_CepWindowTagSuppressUnsuppressProblems: verifies that problems and services are suppressed
	 * during maintenance and no longer suppressed after the maintenance is stopped. The stopped maintenances are
	 * then resumed one at a time out of creation order (middle, first, last) - suppression must return after the
	 * first resume and survive the overlapping ones - and finally stopped again, after which suppression must
	 * clear once more. The problems are closed by the cause and symptom window rule.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblems$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblems() {
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same scenario as testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblems, but the server component
	 * is stopped and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblemsRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblemsRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();
		try {
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * The cause and symptom grouping counterpart of
	 * testTriggerCEP_CepWindowTagSuppressUnsuppressProblemsPerTag: instead of host-wide maintenances that
	 * suppress every problem, one maintenance is created per discovered component, each scoped to that component
	 * through a 'component' problem-tag filter. Every open problem must then be suppressed by exactly the single
	 * maintenance whose tag matches it (and by no other). Stopping the maintenances clears the suppression,
	 * resuming brings it back per matching tag, and the rule still closes the problems normally.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblemsPerTag$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblemsPerTag() {
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();
		try {
			// maintenance_after_first=true, stop_maintenance_and_verify_suppression=true,
			// maintenance_by_tag=true
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false, true, false, true, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * Same "per problem tag" scenario as
	 * testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblemsPerTag, but the server component is stopped
	 * and restarted between each step.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblemsPerTagRestart$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblemsPerTagRestart() {
		$this->skipIfRestartTestsDisabled();
		self::$disc_maintenanceids = [];
		$this->prepareDataCepWindowCauseSymptomCloseOnUp();
		try {
			// Same as testTriggerCEP_CepWindowCauseSymptomSuppressUnsuppressProblemsPerTag but with
			// restart=true.
			$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true, true, false, true, true, false, true);
		}
		finally {
			$this->cleanupCepRules();

			// Clean up: ensure maintenance is stopped
			$this->stopDiscHostMaintenances(self::$disc_maintenanceids);
		}
	}

	/**
	 * The counterpart of testTriggerCEP_OpenProblem for triggers that generate an event per value and
	 * problems that are closed by a CEP window instead of by the trigger recovering (part 1/3).
	 *
	 * The prototypes are the close-on-up ones - multiple event generation, a 'state' tag saying "down" or
	 * "up" and a 'service' tag pairing the two - and the only rule in place is the tag correlation window of
	 * prepareDataCepWindowTagCorrelationCloseOnUp(), which closes a window, and with it every problem the
	 * window holds, as soon as an "up" event of that 'service' arrives. The trigger expression never turns
	 * false in these tests: whatever closes a problem here is the rule.
	 *
	 * This part sends the first "down" value to every discovered trigger, so each of them opens one problem.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_MultEventOpenProblem$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_MultEventOpenProblem() {
		$this->runMultEventOpenProblemTest(false);
	}

	/**
	 * Part 2/3, and the place where multiple event generation shows: re-sending a problem value opens another
	 * problem instead of doing nothing, which is what testTriggerCEP_OpenAlreadyOpenedProblem asserts for the
	 * single event triggers. The second value carries a different 'service' id, so it also gives the window
	 * rule two groups to keep apart.
	 *
	 * @depends testTriggerCEP_MultEventOpenProblem
	 */
	public function testTriggerCEP_MultEventOpenSecondProblem() {
		$this->runMultEventOpenSecondProblemTest(false);
	}

	/**
	 * Part 3/3: the counterpart of testTriggerCEP_CloseProblem. The two problems of every trigger are closed
	 * by the window rule, one 'service' id at a time, while the trigger expression stays true throughout - so
	 * the triggers end up back in OK state without ever having recovered on their own.
	 *
	 * @depends testTriggerCEP_MultEventOpenSecondProblem
	 */
	public function testTriggerCEP_MultEventCloseByWindow() {
		try {
			$this->runMultEventCloseByWindowTest(false);
		}
		finally {
			// The sequence closed every problem itself; only the rule must not survive it.
			$this->cleanupCepRules();
		}
	}

	/**
	 * Same as testTriggerCEP_MultEventOpenProblem but the server component is stopped and restarted first.
	 *
	 * @depends testTriggerCEP_MultEventCloseByWindow
	 */
	public function testTriggerCEP_MultEventOpenProblemRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runMultEventOpenProblemTest(true);
	}

	/**
	 * Same as testTriggerCEP_MultEventOpenSecondProblem but the server component is stopped and restarted
	 * first, so the second problem is opened on a trigger whose first one was cached before the restart.
	 *
	 * @depends testTriggerCEP_MultEventOpenProblemRestart
	 */
	public function testTriggerCEP_MultEventOpenSecondProblemRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->runMultEventOpenSecondProblemTest(true);
	}

	/**
	 * Same as testTriggerCEP_MultEventCloseByWindow but the server component is stopped and restarted first,
	 * so the window has to close problems it only knows from what it restored at startup.
	 *
	 * @depends testTriggerCEP_MultEventOpenSecondProblemRestart
	 */
	public function testTriggerCEP_MultEventCloseByWindowRestart() {
		$this->skipIfRestartTestsDisabled();

		try {
			$this->runMultEventCloseByWindowTest(true);
		}
		finally {
			// The sequence closed every problem itself; only the rule must not survive it.
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same open and close by window sequence in one test, with the per-trigger services in place: every
	 * service must follow its trigger into problem while the two problems are open and back to OK once the
	 * window rule has closed them - a recovery the services see without the trigger ever recovering.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_MultEventCloseByWindowWithServices$)
	 * @depends testTriggerCEP_AddServices
	 */
	public function testTriggerCEP_MultEventCloseByWindowWithServices() {
		try {
			$this->runMultEventOpenProblemTest(false);
			$this->runMultEventOpenSecondProblemTest(false);
			$this->runMultEventCloseByWindowTest(false, true);
		}
		finally {
			// The sequence closed every problem itself; only the rule must not survive it.
			$this->cleanupCepRules();
		}
	}

	/**
	 * The counterpart of testTriggerCEP_OpenAndImmediateRecoverySingleItem: the same kind of rapid burst sent
	 * as one batch to a single item, but made of "down"/"up" pairs and closed by the window rule instead of
	 * by the trigger recovering.
	 *
	 * Every pair carries an id of its own, so each of them is a window of its own: a "down" opens a problem,
	 * and the "up" of the same id closes that window with both its events in it. The burst therefore has to be
	 * paired up correctly while it is being processed, and by the end every problem it opened must be closed
	 * again - with the trigger expression still true throughout, so only the rule can have done it.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_MultEventWindowBurstSingleItem$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_MultEventWindowBurstSingleItem() {
		$this->prepareMultEventWindow();

		try {
			// Drive a single discovered item, so the whole burst lands on one event stream.
			$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
			$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
			$all = [$triggerid];

			$this->captureEventBaseline($all);

			// One "down"/"up" pair per cycle, each pair with an id no other pair uses, sent in a single
			// batch: every value gets a strictly increasing (clock, ns), so CEP has to work through the
			// whole burst in order.
			$cycles = static::RECOVERY_CYCLES_COUNT;
			$data = [];

			for ($i = 0; $i < $cycles; $i++) {
				$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'down_'.$i];
				$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'up_'.$i];
			}

			$vps_written = $this->getVpsWritten();
			$this->dispatchSenderValues($data);
			$this->assertVpsWrittenIncreasedBy($vps_written, count($data));

			// Four events per cycle: the "down" and the "up" are a problem event each, and closing the window
			// they share closes both of them, which is a recovery event each as well. Waiting for the exact
			// number fails on a dropped or a duplicated one alike.
			$this->waitForAllTriggerEventCounts($all, 4 * $cycles);

			// Each "up" closed the window of its own id, so nothing is left open and the trigger is back to
			// OK although its expression never turned false.
			$this->waitForNoOpenProblems($all, 'after the paired burst');
			$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
			$this->assertCepStatEquals('cache', 'events', 0);
			$this->assertCepNoWindows();
		}
		finally {
			// The sequence closed every problem itself; only the rule must not survive it.
			$this->cleanupCepRules();
		}
	}

	/**
	 * The counterpart of testTriggerCEP_OpenAndImmediateRecoveryValueWaves: one batch grouped by value rather
	 * than by key, so the values of all discovered items are interleaved, with the window rule doing the
	 * closing.
	 *
	 * The batch is a "down_0" wave, an "up_0" wave and a "down_1" wave. The middle wave closes the window of
	 * the first id on every trigger, so when the batch has been worked through each trigger is left with the
	 * one problem of the second id; a closing "up_1" wave then takes that one too.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_MultEventWindowValueWaves$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_MultEventWindowValueWaves() {
		$this->prepareMultEventWindow();

		try {
			$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
			$triggerids = self::$discovered_triggerids;

			$this->captureEventBaseline($triggerids);

			$first = self::CEP_RULE_WINDOW_NONE_SERVICE;
			$second = self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT;
			$data = [];

			foreach (['down_'.$first, 'up_'.$first, 'down_'.$second] as $value) {
				foreach ($keys as $key) {
					$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value];
				}
			}

			$vps_written = $this->getVpsWritten();
			$this->dispatchSenderValues($data);
			$this->assertVpsWrittenIncreasedBy($vps_written, count($data));

			// Five events per trigger: the three waves are a problem event each, and the "up" wave closed the
			// window of the first id with two events in it, which recovered both of them.
			$this->waitForAllTriggerEventCounts($triggerids, 5);

			// The "up" wave closed the first id's window on every trigger - its own event and the "down" it
			// paired with - so exactly the problem of the second id is left open on each of them.
			$this->waitForOpenProblemCount($triggerids, count($keys));
			$this->assertAllTriggerValues($triggerids, TRIGGER_VALUE_TRUE,
				'must be PROBLEM after the batch, with the second id still open'
			);

			// The closing wave, sent once the batch has been worked through, takes the rest.
			$data = [];

			foreach ($keys as $key) {
				$data[] = ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'up_'.$second];
			}

			$vps_written = $this->getVpsWritten();
			$this->dispatchSenderValues($data);
			$this->assertVpsWrittenIncreasedBy($vps_written, count($data));

			// Three more: the closing wave itself, and the recovery of the two events its window held.
			$this->waitForAllTriggerEventCounts($triggerids, 8);
			$this->waitForNoOpenProblems($triggerids, 'after the closing up wave');
			$this->waitForParentsValue($triggerids, TRIGGER_VALUE_FALSE);
			$this->assertCepStatEquals('cache', 'events', 0);
			$this->assertCepNoWindows();
		}
		finally {
			// The sequence closed every problem itself; only the rule must not survive it.
			$this->cleanupCepRules();
		}
	}

	/**
	 * Put the close-on-up prototypes and the tag correlation window rule in place for the multiple event
	 * scenarios above. Every part calls it, so each of them can also be run on its own; it changes nothing
	 * when it is already in place, and in particular it neither closes the problems a previous part opened
	 * nor replaces the rule that is to close them.
	 */
	private function prepareMultEventWindow(): void {
		$this->prepareDataCepWindowTagCorrelationCloseOnUp();
	}

	/**
	 * Send $value to every discovered item of the primary prototype, the way the smoke tests drive all
	 * discovered triggers at once.
	 */
	private function sendDiscoveredValues(array $keys, string $value): void {
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value], $keys)
		);
	}

	/**
	 * Open the first problem of every discovered trigger with a "down" value and verify CEP processed and
	 * cached it. When $restart is true, the server is restarted first.
	 */
	private function runMultEventOpenProblemTest(bool $restart): void {
		$this->prepareMultEventWindow();
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$cep_processed = $this->getCepStat('events', 'processed');
		$this->captureEventBaseline($triggerids);

		// OK→PROBLEM: one PROBLEM event per trigger, and the trigger value follows.
		$this->sendDiscoveredValues($keys, 'down_'.self::CEP_RULE_WINDOW_NONE_SERVICE);
		$this->waitForAllTriggerEventCounts($triggerids, 1);
		$this->waitForParentsValue($triggerids, TRIGGER_VALUE_TRUE);
		$this->waitForOpenProblemCount($triggerids, count($keys));

		// CEP processed the opened problem events and is holding one per trigger.
		$this->assertCepStatIncreasedBy('events', 'processed', $cep_processed, count($keys));
		$this->assertCepStatEquals('cache', 'events', count($keys));
		$this->assertCepStatEquals('cache', 'objects', count($keys));
	}

	/**
	 * Send a second "down" value, with an id of its own, to triggers that are already in problem state. With
	 * multiple event generation this opens a second problem on every one of them rather than being ignored.
	 * When $restart is true, the server is restarted first.
	 */
	private function runMultEventOpenSecondProblemTest(bool $restart): void {
		$this->prepareMultEventWindow();
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		$cep_processed = $this->getCepStat('events', 'processed');
		$this->captureEventBaseline($triggerids);

		// PROBLEM→PROBLEM: one more event per trigger, and the trigger value stays where it is.
		$this->sendDiscoveredValues($keys, 'down_'.self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT);
		$this->waitForAllTriggerEventCounts($triggerids, 1);
		$this->waitForParentsValue($triggerids, TRIGGER_VALUE_TRUE);
		$this->waitForOpenProblemCount($triggerids, 2 * count($keys));

		// Two cached events per trigger now, but still one object per trigger.
		$this->assertCepStatIncreasedBy('events', 'processed', $cep_processed, count($keys));
		$this->assertCepStatEquals('cache', 'events', 2 * count($keys));
		$this->assertCepStatEquals('cache', 'objects', count($keys));
	}

	/**
	 * Close the problems of every discovered trigger with the window rule, one 'service' id at a time: the
	 * "up" value of an id is itself a problem event, and the rule closes the window of that id with both of
	 * them in it. The trigger expression stays true the whole time, so the triggers can only return to OK
	 * because their last problem was closed.
	 *
	 * When $restart is true, the server is restarted first. With $check_services the per-trigger services are
	 * expected to follow: in problem while the problems are open, OK once the rule has closed them.
	 */
	private function runMultEventCloseByWindowTest(bool $restart, bool $check_services = false): void {
		$this->prepareMultEventWindow();
		$this->maybeRestartServer($restart);

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		if ($check_services) {
			$this->waitForServicesStatus(TRIGGER_SEVERITY_DISASTER);
		}

		// The "up" of the first id closes that id's window: its own event and the "down" it pairs with are
		// both closed, so one problem per trigger is left - the one of the other id.
		$this->sendDiscoveredValues($keys, 'up_'.self::CEP_RULE_WINDOW_NONE_SERVICE);
		$this->waitForOpenProblemCount($triggerids, count($keys));
		$this->waitForParentsValue($triggerids, TRIGGER_VALUE_TRUE);

		// The "up" of the other id closes the rest. Nothing recovered the triggers - closing their last
		// problem is what puts them back to OK.
		$this->sendDiscoveredValues($keys, 'up_'.self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT);
		$this->waitForNoOpenProblems($triggerids, 'close by window correlation');
		$this->waitForParentsValue($triggerids, TRIGGER_VALUE_FALSE);

		// Nothing is left cached once every problem of every trigger is closed, and the windows the rule
		// closed are gone with the events they held.
		$this->assertCepStatEquals('cache', 'events', 0);
		$this->assertCepStatEquals('cache', 'objects', 0);
		$this->assertCepNoWindows();

		if ($check_services) {
			$this->waitForServicesStatus(ZBX_SEVERITY_OK);
		}
	}

	/**
	 * Complex event processing without a window (WINDOW_NONE): thirty windowless rules tag the problem events
	 * of one discovered trigger the moment they occur, each with a tag named after the operator it applies
	 * ("service_equals", "event_name_contains", ...), and one more rule runs every tag operation over those
	 * same events.
	 *
	 * Twelve of them form six opposite pairs of conditions on the 'service' id of the event - four on the
	 * event tags (Equals "0" / Does not equal "0", Contains "0" / Does not contain "0", Is less than or equal
	 * "0" / Is more than or equal "1" and, on the per-id tag name, 'service_0' Exists / Does not exist) and two
	 * on the event name, which ends with the item value (Equals / Does not equal the whole "down_1" event name,
	 * Contains / Does not contain "down_1"). Exactly one rule of every pair may match an event, so the tagging
	 * follows the ids: "down_0" gets service_equals + service_contains + service_less_equal + service_exists +
	 * event_name_not_equals + event_name_not_contains, "down_1" gets the opposite half of every pair, and
	 * "down_10" - the id that contains the others' values without being equal to them - gets
	 * service_not_equals + service_contains + service_more_equal + service_not_exists + event_name_not_equals +
	 * event_name_contains.
	 *
	 * The remaining fourteen test the event severity (DISASTER for every event here), the event host (the one
	 * discovered host), its host group and the time the event occurred, so they cannot tell the events apart:
	 * severity_equals (Equals Disaster), severity_more_equal (Is more than or equal High), severity_less_equal
	 * (Is less than or equal Disaster), host_equals, host_group_equals and time_period_in (In a period
	 * covering all the time) must therefore tag all three events, while severity_not_equals, the non-Equals
	 * host and host group rules (Does not equal the name, Contains a name it does not contain, Does not
	 * contain its own name) and time_period_not_in must tag none - no event may carry the tag of any rule that
	 * does not match it.
	 *
	 * The last four rules are the ones whose filter combines several conditions, one rule per evaltype, so the
	 * way a condition set is evaluated is covered too: service_and (everything AND-ed) tags "down_10" only,
	 * service_or (everything OR-ed) tags "down_0" and "down_10", service_and_or (same type OR-ed, distinct
	 * types AND-ed) tags "down_0" and "down_1", and service_expression (the custom expression "A and (B or
	 * C)") tags "down_0" and "down_10" - every one of them a set the same conditions under another evaltype
	 * would not produce.
	 *
	 * The last two rules match every event of the scenario. Instead of adding one tag, one of them runs the
	 * whole tag operation set on it: "add tag", "set tag" on a free and on a taken name, "set tag value" on an existing
	 * tag and on a name no tag has, "increase" and "decrease tag value" on a numeric and on a non-numeric
	 * value, "rename tag" and "remove tag". Half of them work on tags the rule adds itself, the other half on
	 * tags the trigger generated - the discovered triggers carry a few extra tags for that. Every event must
	 * end up with exactly the tag state those operations produce, down to the tags they must have left behind
	 * renamed or removed.
	 *
	 * The other one runs the operations that change the event itself: "set name", then "set severity" to
	 * Information followed by two "increase severity" and one "decrease severity" (so the shifts cannot cancel
	 * out and every event must end up at Warning), then "suppress" for a short period. Every event must be
	 * suppressed while it holds, and - when the scenario is run with SKIP_UNSUPPRESS_WAIT turned off -
	 * unsuppressed again once it has passed. The two operations that would contradict the scenario are left
	 * out: "discard" would drop the event and "close" would close the problem this test needs to stay open. None of the rules
	 * closes anything, so all three problems stay open until the trigger expression recovers them.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowNone$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowNone() {
		$this->prepareDataCepWindowNoneTagOperations();

		try {
			$this->runEventAssessmentTestCepWindowNone();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The whole rule set of testTriggerCEP_CepWindowNone once more, with every one of its rules given a simple
	 * window: the same operator coverage, the same tag and event operations, the same three values and the
	 * same expected outcome - only now each rule collects its events into a window of its own while it works.
	 *
	 * That the outcome may not change is the point. A simple window is not one of the exclusive window types,
	 * so every rule still gets to process every event, and none of these rules acts on its window - they have
	 * no operation at eviction or at window close - so the windows fill up and are never heard from. If the
	 * events came out tagged differently from the windowless run, a window would be doing something to the
	 * events it holds that it should not.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimple$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimple() {
		$this->prepareDataCepWindowNoneTagOperations(CCepRuleHelper::WINDOW_SIMPLE, 'simple all');

		try {
			$this->runEventAssessmentTestCepWindowNone();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The rule set of testTriggerCEP_CepWindowNone once more, this time with a pattern match window on every
	 * rule and a script that reports a match every time a window is examined.
	 *
	 * The outcome must again be the one the windowless run produces. A pattern window is not exclusive, so
	 * every rule still processes every event; the script decides nothing here beyond being run, because none
	 * of these rules has an operation at the pattern matched execution point - so a match has nothing to
	 * execute. What the flavour does show is that having a window, examining it once a second and matching in
	 * it leaves the events themselves alone.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPattern$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPattern() {
		$this->prepareDataCepWindowNoneTagOperations(CCepRuleHelper::WINDOW_PATTERN_MATCH, 'pattern all');

		try {
			$this->runEventAssessmentTestCepWindowNone();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same operations as testTriggerCEP_CepWindowNone, applied by a rule that has a simple window instead
	 * of none: the events are grouped into a window per 'service' id and must come out with exactly the same
	 * tags, event name, severity and suppression the windowless rule produces, showing the operations behave
	 * the same with a window in front of them. Nothing closes a window or a problem, so all three problems
	 * stay open until the trigger expression recovers them.
	 *
	 * A simple window is not exclusive, so a second rule with one is processed for the same event as well: the
	 * second rule of this flavour must have added its tag to every event - the opposite of what
	 * testTriggerCEP_CepWindowTagOperations asserts for a tag correlation window.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleOperations$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleOperations() {
		$this->prepareDataCepWindowSimpleOperations();

		try {
			$this->runEventAssessmentTestCepWindowOperations(true);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same as testTriggerCEP_CepWindowSimpleOperations, with a tag correlation window instead of a simple
	 * one: the operations must again leave exactly the state the windowless flavour produces.
	 *
	 * The window type is what the two differ in: only the first matching rule with a tag correlation window is
	 * processed for an event, so here the second rule never gets its turn and no event may carry its tag. That
	 * is also why the operator coverage rules are not recreated with a window - a matrix of tag correlation
	 * rules could never tag one event more than once.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagOperations$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagOperations() {
		$this->prepareDataCepWindowTagOperations();

		try {
			$this->runEventAssessmentTestCepWindowOperations(false);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same operations as testTriggerCEP_CepWindowSimpleOperations, but applied when the event is evicted
	 * from the window instead of when it occurs: every id gets a window of its own, and once the window
	 * duration has run out its event is evicted and the operations run on it. The events must end up in
	 * exactly the state the event time flavours leave behind, only later - which is what tells an operation
	 * that ran at the wrong execution point apart from one that ran at the right one.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleEvictedOperations$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleEvictedOperations() {
		$this->prepareDataCepWindowSimpleEvictedOperations();

		try {
			$this->runEventAssessmentTestCepWindowEvictedOperations();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same as testTriggerCEP_CepWindowSimpleEvictedOperations with a tag correlation window: its events
	 * must end up in the same state, showing the eviction operations do not depend on the window type.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagEvictedOperations$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagEvictedOperations() {
		$this->prepareDataCepWindowTagEvictedOperations();

		try {
			$this->runEventAssessmentTestCepWindowEvictedOperations();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same as testTriggerCEP_CepWindowSimpleEvictedOperations with a pattern match window, the third and last
	 * window type whose events are evicted at all: what the duration has outlived leaves a pattern match window
	 * exactly as it leaves a simple one, so its evicted events must come out with the same name, tags, severity and
	 * suppression - resolved macros and all. A cause and symptom window has no counterpart here: its duration
	 * running out closes the window instead of evicting what it holds, which is what
	 * testTriggerCEP_CepWindowCauseSymptomClosedOperations covers instead.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternEvictedOperations$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternEvictedOperations() {
		$this->prepareDataCepWindowPatternEvictedOperations();

		try {
			$this->runEventAssessmentTestCepWindowEvictedOperations();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same operations as testTriggerCEP_CepWindowSimpleOperations, applied at the window closed execution point
	 * of a simple window instead of at the arriving event: the event closes the window it has just entered and the
	 * operations are performed for it as that window closes.
	 *
	 * The event they act on is therefore the one the window was holding rather than the one being assessed, and the
	 * server has to build its context from that - so the event name and the tags, macros and all, must come out
	 * exactly as they do when the very same operations run the moment the event occurs: the user macro and the
	 * expression macro of the name resolved, the macro named tags resolved and named after what they resolved to.
	 * See runEventAssessmentTestCepWindowClosedOperations().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleClosedOperations$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleClosedOperations() {
		$this->prepareDataCepWindowSimpleClosedOperations();

		try {
			$this->runEventAssessmentTestCepWindowClosedOperations();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same as testTriggerCEP_CepWindowSimpleClosedOperations with a tag correlation window: correlating the
	 * events of a group is not what an operation acts on, so the closing window of this type must leave its event in
	 * the same state - see runEventAssessmentTestCepWindowClosedOperations().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagClosedOperations$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagClosedOperations() {
		$this->prepareDataCepWindowTagClosedOperations();

		try {
			$this->runEventAssessmentTestCepWindowClosedOperations();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same as testTriggerCEP_CepWindowSimpleClosedOperations with a cause and symptom window, the one window
	 * type that ranks what it is given and the one whose events reach this execution point on their own: its
	 * duration running out closes the window rather than evicting what it holds. Here the window is closed by the
	 * operation of the rule as in every other flavour, so what is compared is the operations and not the two ways
	 * that window type may end - see runEventAssessmentTestCepWindowClosedOperations().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomClosedOperations$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomClosedOperations() {
		$this->prepareDataCepWindowCauseSymptomClosedOperations();

		try {
			$this->runEventAssessmentTestCepWindowClosedOperations();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same as testTriggerCEP_CepWindowSimpleClosedOperations with a pattern match window, whose script reports
	 * no match: what closes the window is the operation of the rule, so the operations of the window closed
	 * execution point are reached by this window type without a match having anything to do with it - see
	 * runEventAssessmentTestCepWindowClosedOperations().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternClosedOperations$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternClosedOperations() {
		$this->prepareDataCepWindowPatternClosedOperations();

		try {
			$this->runEventAssessmentTestCepWindowClosedOperations();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * Discarding: a windowless rule whose only operation drops the "up" events as they occur, so they leave no
	 * trace at all - unlike a close, which leaves a closed problem behind, and unlike a suppress, which leaves
	 * a suppressed one. The "down" values around it must still open their problems, so the rule is shown to
	 * drop exactly what its condition selects.
	 *
	 * No window is involved here: discarding is decided while the rules are matched, before the event is stored or
	 * handed to any window, so a window could not change the outcome. What a window can be robbed of is asserted
	 * where the rules that close a window are - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepDiscardOnUp$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepDiscardOnUp() {
		$this->prepareDataCepDiscardUp();

		try {
			$this->runEventAssessmentTestCepDiscard();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * Cause and symptom grouping: the window ranks the events of a group itself, without an operation taking
	 * part in it. All three values go into one group, so the first problem becomes the cause and the two after it
	 * become its symptoms, each pointing at it through its cause_eventid, while the cause counts them in a tag of
	 * its own. Nothing closes anything while that is being built, so all three problems stay open - the "up"
	 * value at the end closes their window and the window closes them all with it.
	 *
	 * As with a tag correlation window, only the first matching rule of this window type is processed for an
	 * event: a second rule that would only add a tag must leave no trace.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptom$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptom() {
		$this->prepareDataCepWindowCauseSymptom();

		try {
			$this->runEventAssessmentTestCepWindowCauseSymptom();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * A service driven by a tag a CEP rule maintains: the service matches its problems on a tag no trigger
	 * produces, the rule adds that tag as the event occurs and removes it again when the window evicts the
	 * event, and the service has to follow both.
	 *
	 * The problem stays open from beginning to end, so the service going into problem and back to OK can only
	 * be the tag being added and taken away - which is also what makes this different from every other service
	 * scenario in the suite, where the services follow the problems themselves.
	 * run as (testTriggerCEP_AddServices|testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepServiceTag$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepServiceTag() {
		$this->prepareDataCepServiceTag();

		try {
			$this->runEventAssessmentTestCepServiceTag();
		}
		finally {
			$this->cleanupCepRules();
			$this->deleteCepTagService();
		}
	}

	/**
	 * Copying an event: a simple window whose only operation copies an event when it is evicted, so a single
	 * value ends up with two problems - the one it opened and the copy the window made of it when its duration
	 * ran out.
	 *
	 * A copy is an event like any other, so it matches the same rule and enters the same window: copying it
	 * again would produce a copy of the copy, and so on without end. The operation is conditioned on the built
	 * in "$IS.COPIED" tag for that reason, and the test checks the chain stops - each id keeps exactly two
	 * events even after its copy has been through the window itself.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCopy$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCopy() {
		$this->prepareDataCepWindowCopy();

		try {
			$this->runEventAssessmentTestCepWindowCopy();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same copy operation as testTriggerCEP_CepWindowPatternMatch, from a pattern whose script always
	 * reports a match: the window is examined once a second and a match does not consume it, so the rule
	 * copies its oldest event again at every examination and the copies never stop coming.
	 *
	 * This is the behaviour the other pattern test avoids by refusing to match its own output, and the reason
	 * such a script needs to. The test waits for the copies to pile up well past the single one a
	 * match-once script produces, then deletes the rule - recovering the trigger would not help, since the
	 * copies are problem events and would simply reopen it.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCopyAlways$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCopyAlways() {
		$this->prepareDataCepWindowPatternCopyAlways();

		try {
			$this->runEventAssessmentTestCepWindowPatternCopyAlways();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * Event pattern match: the window runs a script over its events once a second, and when the script reports
	 * a match the rule copies the oldest and the newest event of the window. Three values are sent into one
	 * group, so the match produces two more problems - copies carrying the tags of the events they were made
	 * from - which is how the match is observed, since copying is one of only two things this execution point
	 * can do.
	 *
	 * The copies enter the same window, so the script counts them apart from the events it was sent: without
	 * that the pattern would match its own output over and over. The totals staying at five is what shows it
	 * does not.
	 *
	 * The script doubles as an assertion on the event fields the window exposes to it - name, severity,
	 * timestamp, tags and the lifecycle flags - by refusing to match anything it does not recognise. A match
	 * therefore means both that the pattern was found and that every event it was found in looked right.
	 *
	 * The "up" value at the end closes the window, which tags the five events it held along with the "up" event
	 * that ended it: closing does nothing else in a flavour whose operations only copy, so the tag is how the
	 * window is seen ending and how what it held is read off the events.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternMatch$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternMatch() {
		$this->prepareDataCepWindowPattern();

		try {
			$this->runEventAssessmentTestCepWindowPattern();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * A simple window that overflows instead of expiring: it has room for one event and lasts longer than the
	 * test, so every event after the first one is evicted the moment it arrives, and the rule closes what it
	 * evicts. The "up" value at the end also closes the window, which closes the one problem the window was
	 * holding, so nothing is left open - see runEventAssessmentTestCepWindowCapacity().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCapacity$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCapacity() {
		$this->prepareDataCepWindowSimpleCapacity();

		try {
			$this->runEventAssessmentTestCepWindowCapacity();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same as testTriggerCEP_CepWindowSimpleCapacity with a tag correlation window: overflowing a window
	 * and closing it from an evicted event must work the same for both window types. Unlike the evicted
	 * flavours this one does not depend on the window duration at all - an event that does not fit is evicted
	 * as it arrives, not by the timer.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagCapacity$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagCapacity() {
		$this->prepareDataCepWindowTagCapacity();

		try {
			$this->runEventAssessmentTestCepWindowCapacity();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same capacity rule as testTriggerCEP_CepWindowSimpleCapacity, but its simple window groups by the
	 * 'service' tag: every id gets a window of its own, so this time every "down" fits and all three problems
	 * stay open. The "up" of an id then finds that id's window occupied, so it is evicted, closed, and closes
	 * the window along with the "down" problem it held - see runEventAssessmentTestCepWindowCapacityPerService().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCapacityPerService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCapacityPerService() {
		$this->prepareDataCepWindowSimpleCapacityPerService();

		try {
			$this->runEventAssessmentTestCepWindowCapacityPerService();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same as testTriggerCEP_CepWindowSimpleCapacityPerService with a tag correlation window: grouping by
	 * a tag that differs per event must give every id a window of its own for both window types.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagCapacityPerService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagCapacityPerService() {
		$this->prepareDataCepWindowTagCapacityPerService();

		try {
			$this->runEventAssessmentTestCepWindowCapacityPerService();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same per service capacity rule as testTriggerCEP_CepWindowSimpleCapacityPerService, with one
	 * operation added: the "up" events are discarded as they occur. The same event is therefore both the one
	 * that would be evicted for not fitting into its window - closing that window and the problem it holds -
	 * and the one that is dropped.
	 *
	 * Dropping it wins, because it is decided before the event is stored and before any window sees it: no
	 * "up" event exists afterwards, nothing was evicted or suppressed on its account, no window was closed and
	 * all three "down" problems are left open by it.
	 *
	 * What closes them is the window duration, kept short for this flavour alone: the windows the discard left
	 * with nothing to end them run out instead, and an event evicted because its window ran out is suppressed
	 * and closed by the same operations as one evicted for not fitting. So the rest of the rule is shown to
	 * keep working on the very events the discard emptied its windows of, and the trigger comes back to OK
	 * without a recovery value.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCapacityDiscardOnUp$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCapacityDiscardOnUp() {
		return;
		$this->prepareDataCepWindowCapacityDiscardUp();

		try {
			$this->runEventAssessmentTestCepWindowCapacityDiscard();
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/* Close window operation (event pattern match) - test abbility to close window for each type of window */

	/**
	 * The close window operation driven by an event pattern match instead of by an arriving event: the script of
	 * the window reports a match once the window holds the "up" event of its id, and the match closes the window
	 * along with both events it held - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindow$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindow() {
		$this->prepareDataCepWindowPatternCloseWindow();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_PATTERN_CLOSE);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same close window scenario with the same pattern match window, whose close window operation is this
	 * time performed when an event is added to it, restricted to the "up" events, while its script never reports
	 * a match:
	 * a pattern match window must honour the arrival execution point as well, so the "up" event closes the window
	 * it has just entered without the window ever being matched against - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowOnEvent$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowOnEvent() {
		$this->prepareDataCepWindowPatternCloseWindowOnEvent();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVENT);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same close window scenario with the same pattern match window, whose close window operation is this
	 * time performed when an event is evicted: the window has room for one event, so the "down" event of an id
	 * takes its place and the "up" event of that id does not fit. The window is therefore ended by an event that
	 * never entered it, which is also why the rule closes the evicted event itself - the "close" operation of the
	 * window only reaches the "down" event it held - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowOnEvicted$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowOnEvicted() {
		$this->prepareDataCepWindowPatternCloseWindowOnEvicted();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVICTED);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The pattern match close window rule with one operation added: the "down" values of one id are discarded as they
	 * occur, while its "up" values are kept and still end a window. The ids that were kept behave exactly as they do
	 * without the discard - their "up" event completes what the script looks for and the match closes the window with
	 * both events in it - and the discarded id has nothing at all: its "down" value was dropped while the rules were
	 * matched, before the event was stored and before the window was given it, so no problem of it exists to be held
	 * or closed - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowDiscardOnDown$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowDiscardOnDown() {
		$this->prepareDataCepWindowPatternCloseWindowDiscardDown();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_PATTERN_CLOSE_DISCARD, true);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same discarding flavour with the discarded id named by the value of the plain 'service' tag rather than by
	 * the name of the per-id one, so the operation is singled out by the one condition type that compares a tag value
	 * (CONDITION_TAG_VALUE) instead of a tag name. Everything it asserts is what its sibling above asserts - the
	 * condition type is the whole of the difference - so this is the only place in the scenario where an operation
	 * condition carries a value at all.
	 *
	 * Skipped while the API cannot store that value, see SKIP_OPERATION_TAG_VALUE_TESTS for what is wrong with it.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowDiscardOnDownTagValue$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowDiscardOnDownTagValue() {
		$this->skipIfOperationTagValueTestsDisabled();

		$this->prepareDataCepWindowPatternCloseWindowDiscardDownTagValue();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::CEP_RULE_WINDOW_PATTERN_CLOSE_DISCARD_TAG_VALUE, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same pattern match close window rule driven over a single id instead of one per id: every value sent is the
	 * "down" value of that one id and every one of them goes through a discovered trigger of its own, one value per
	 * trigger (LLD_DISCOVERY_COUNT), so the rule keeps one window and everything it holds was opened by the very same
	 * value on that many different triggers - a deep window grouped by the 'service' tag across the triggers of the
	 * host, rather than many shallow ones. The "up" value of that id then completes what the script looks for, and the
	 * match closes the window with every one of those problems in it in a single step, leaving nothing open at all and
	 * every one of those triggers back in OK - see runEventAssessmentTestCepWindowCloseWindow().
	 *
	 * Every close window flavour that is not a discarding one is run this way, this being the first of them: the
	 * window type and the execution point that ends the window are what the rest of them vary, exactly as they do
	 * without a single id, so between them a window grouped out of that many triggers is ended from everywhere a
	 * window can be ended from, see prepareDataCepWindowPatternCloseWindowSingleService().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowSingleService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowSingleService() {
		$this->prepareDataCepWindowPatternCloseWindowSingleService();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_PATTERN_CLOSE), false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The single id variant of testTriggerCEP_CepWindowPatternCloseWindowOnEvent: the one window is ended by the "up"
	 * event arriving instead of by the script of the window reporting a match, so the window that holds the events of
	 * every discovered trigger is closed by the very event that has just entered it - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowOnEventSingleService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowOnEventSingleService() {
		$this->prepareDataCepWindowPatternCloseWindowOnEventSingleService();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVENT), false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The single id variant of testTriggerCEP_CepWindowPatternCloseWindowOnEvicted: the capacity of the window is
	 * exactly the "down" values of the one id, one per discovered trigger, so they take every place it has and the
	 * "up" value that follows them is the one event that does not fit. The window holding the events of that many
	 * triggers is therefore ended by an event that never entered it - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowOnEvictedSingleService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowOnEvictedSingleService() {
		$this->prepareDataCepWindowPatternCloseWindowOnEvictedSingleService();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVICTED), false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The pattern match close window flavour with a second rule of its own kind beside the first: the same window and
	 * the same operations twice over, so both rules keep a window of the same events and the script of each of them
	 * finds the "up" event of an id in the window it was handed. Both windows are therefore closed and every problem
	 * of that id is closed by two rules rather than one, which may leave it no different from being closed once - the
	 * counts this asserts are the ones of testTriggerCEP_CepWindowPatternCloseWindow. A pattern match window is not
	 * one of the exclusive window types, so the second rule really is processed for those events, and the tag it adds
	 * as an event occurs is what says so - see runEventAssessmentTestCepWindowCloseWindow().
	 *
	 * This is the first of the doubled flavours: the rest of them vary the execution point that ends the window and
	 * where the events it held are closed, exactly as they do with one rule, see
	 * prepareDataCepWindowPatternCloseWindowDoubleRule().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowDoubleRule$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowDoubleRule() {
		$this->prepareDataCepWindowPatternCloseWindowDoubleRule();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_PATTERN_CLOSE, false, false, true);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The doubled variant of testTriggerCEP_CepWindowPatternCloseWindowOnEvent: the "up" event closes both windows it
	 * has just entered, one per rule, and the problems of its id are closed with both of them - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowOnEventDoubleRule$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowOnEventDoubleRule() {
		$this->prepareDataCepWindowPatternCloseWindowOnEventDoubleRule();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVENT, false, false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The doubled variant of testTriggerCEP_CepWindowPatternCloseWindowOnEvicted: each rule has a window with room for
	 * exactly the "down" events of an id, so the "up" event of that id fits into neither of them and is evicted from
	 * both - one event ending two windows - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCloseWindowOnEvictedDoubleRule$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCloseWindowOnEvictedDoubleRule() {
		$this->prepareDataCepWindowPatternCloseWindowOnEvictedDoubleRule();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::CEP_RULE_WINDOW_PATTERN_CLOSE_EVICTED, false, false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/* Close window operation - test abbility to close window for each type of window */

	/**
	 * The same close window scenario with a simple window, whose close window operation is performed when an
	 * event occurs, restricted to the "up" events: the operation is performed by the event itself, which is
	 * already in the window of its id when it runs, so it closes the window it has just entered and both events
	 * that window held. A simple window has no matching of its own, so this is the arrival flavour of
	 * testTriggerCEP_CepWindowPatternCloseWindowOnEvent with the window type that does the least of all - closing
	 * a window from an arriving event must not depend on it, so the outcome must be the same - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCloseWindow$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCloseWindow() {
		$this->prepareDataCepWindowSimpleCloseWindow();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_SIMPLE_CLOSE);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same close window scenario with the same simple window, whose close window operation is this time
	 * performed when an event is evicted: the window has room for one event, so the "down" event of an id takes
	 * its place and the "up" event of that id does not fit. The window is therefore ended by an event that never
	 * entered it, which is also why the rule closes the evicted event itself - the "close" operation of the window
	 * only reaches the "down" event it held. The two execution points a simple window has are thereby both
	 * covered - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCloseWindowOnEvicted$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCloseWindowOnEvicted() {
		$this->prepareDataCepWindowSimpleCloseWindowOnEvicted();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_SIMPLE_CLOSE_EVICTED);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The simple window close window rule with the "down" values of one id discarded as they occur: the "up" values
	 * of the other ids still close the window they enter and the problem it holds, while the discarded id never
	 * opened a problem for a window to hold in the first place - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCloseWindowDiscardOnDown$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCloseWindowDiscardOnDown() {
		$this->prepareDataCepWindowSimpleCloseWindowDiscardDown();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_SIMPLE_CLOSE_DISCARD, true);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The simple window close window rule driven over a single id instead of one per id, as
	 * testTriggerCEP_CepWindowPatternCloseWindowSingleService drives the pattern match one: every value sent is the
	 * "down" value of that one id and every one of them goes through a discovered trigger of its own, one value per
	 * trigger (LLD_DISCOVERY_COUNT), so the rule keeps one window holding an event of that many triggers, all opened by
	 * the very same value. The "up" value of that id is what ends the window here - the operation is performed by the
	 * event itself, so the window is closed the moment that event enters it - and every one of those problems is closed
	 * with it, leaving nothing open at all. What the window type changes is only what ends the window, so the outcome
	 * must be the one of the pattern match flavour - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCloseWindowSingleService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCloseWindowSingleService() {
		$this->prepareDataCepWindowSimpleCloseWindowSingleService();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_SIMPLE_CLOSE), false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The single id variant of testTriggerCEP_CepWindowSimpleCloseWindowOnEvicted: the window has room for exactly the
	 * "down" values of the one id, one per discovered trigger, so the "up" value that follows them is evicted and ends
	 * the window without ever entering it - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCloseWindowOnEvictedSingleService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCloseWindowOnEvictedSingleService() {
		$this->prepareDataCepWindowSimpleCloseWindowOnEvictedSingleService();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_SIMPLE_CLOSE_EVICTED), false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The simple window close window flavour with a second rule of its own kind beside the first: a simple window is
	 * not exclusive either, so both rules take every event into a window of their own and the arriving "up" event
	 * closes both of those windows and the problems each of them held. Being closed by two rules may leave a problem
	 * no different from being closed by one, so the counts are those of testTriggerCEP_CepWindowSimpleCloseWindow -
	 * see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCloseWindowDoubleRule$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCloseWindowDoubleRule() {
		$this->prepareDataCepWindowSimpleCloseWindowDoubleRule();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_SIMPLE_CLOSE, false, false, true);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The doubled variant of testTriggerCEP_CepWindowSimpleCloseWindowOnEvicted: each rule has a window with room for
	 * exactly the "down" events of an id, so the "up" event of that id is evicted from both and ends two windows
	 * without having entered either - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCloseWindowOnEvictedDoubleRule$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCloseWindowOnEvictedDoubleRule() {
		$this->prepareDataCepWindowSimpleCloseWindowOnEvictedDoubleRule();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::CEP_RULE_WINDOW_SIMPLE_CLOSE_EVICTED, false, false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same close window scenario with a tag correlation window, whose close window operation is performed
	 * when an event is added to it, restricted to the "up" events: the operation is performed by the event itself,
	 * which
	 * is already in the window of its id when it runs, so it closes the window it has just entered and both
	 * events that window held. The window type is all that differs from
	 * testTriggerCEP_CepWindowPatternCloseWindowOnEvent, and closing a window from an arriving event must not
	 * depend on it, so the outcome must be the same - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagCloseWindow$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagCloseWindow() {
		$this->prepareDataCepWindowTagCloseWindow();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_TAG_CLOSE);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same close window scenario with the same tag correlation window, whose close window operation is this
	 * time performed when an event is evicted: the window has room for one event, so the "down" event of an id
	 * takes its place and the "up" event of that id does not fit. The window is therefore ended by an event that
	 * never entered it, which is also why the rule closes the evicted event itself - the "close" operation of the
	 * window only reaches the "down" event it held. With this the operation is covered from every execution point
	 * of every window type that has it - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagCloseWindowOnEvicted$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagCloseWindowOnEvicted() {
		$this->prepareDataCepWindowTagCloseWindowOnEvicted();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_TAG_CLOSE_EVICTED);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The tag correlation close window rule with the "down" values of one id discarded as they occur: the discard is
	 * decided before the event is handed to a window of any type, so it must keep an event out of a tag correlation
	 * window exactly as it does out of the others, while the ids it does not match still correlate and close as
	 * usual - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagCloseWindowDiscardOnDown$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagCloseWindowDiscardOnDown() {
		$this->prepareDataCepWindowTagCloseWindowDiscardDown();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_TAG_CLOSE_DISCARD, true);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The single id variant of testTriggerCEP_CepWindowTagCloseWindow: one id sent a "down" value per discovered
	 * trigger, so the tag correlation window correlates the events of that many triggers into one group of its own and
	 * the arriving "up" event closes the window it has just entered along with every one of them - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagCloseWindowSingleService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagCloseWindowSingleService() {
		$this->prepareDataCepWindowTagCloseWindowSingleService();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_TAG_CLOSE), false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The single id variant of testTriggerCEP_CepWindowTagCloseWindowOnEvicted: the window has room for exactly the
	 * "down" values of the one id, one per discovered trigger, so the "up" value that follows them is evicted and ends
	 * the correlated window without ever entering it - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagCloseWindowOnEvictedSingleService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagCloseWindowOnEvictedSingleService() {
		$this->prepareDataCepWindowTagCloseWindowOnEvictedSingleService();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_TAG_CLOSE_EVICTED), false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same close window scenario with a cause and symptom window, whose close window operation is performed
	 * when an event is added to it - restricted this time not by a tag of the event but by the rank the window
	 * itself gave
	 * it: the "down" event of an id becomes the cause of its window and the "up" event that follows it becomes a
	 * symptom, and it is being a symptom that the operation acts on. A window is therefore ended by what its own
	 * ranking says about the event that entered it, and closing it closes the cause and the symptom together - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomCloseWindow$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomCloseWindow() {
		$this->prepareDataCepWindowCauseSymptomCloseWindow();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_CAUSE_CLOSE);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same close window scenario with the same cause and symptom window, whose close window operation is this
	 * time performed when an event is evicted: the window has room for one event, so the "down" event of an id
	 * takes its place and the "up" event of that id does not fit. An evicted event is never taken into the window
	 * and is therefore never ranked, so this flavour singles it out by its tag as the other window types do - and
	 * closes it itself, the "close" operation of the window only reaching the "down" event it held - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomCloseWindowOnEvicted$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomCloseWindowOnEvicted() {
		$this->prepareDataCepWindowCauseSymptomCloseWindowOnEvicted();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_CAUSE_CLOSE_EVICTED);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The cause and symptom close window rule with the "down" values of one id discarded as they occur, which takes
	 * away the cause of that id: a discarded event is never taken into the window, so it never becomes the cause its
	 * "up" event would have been ranked a symptom of - and being a symptom is what this flavour ends its window on.
	 * The ids that were kept are ranked and closed as usual, so the ranking is shown to follow what the window was
	 * given rather than what was sent - see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomCloseWindowDiscardOnDown$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomCloseWindowDiscardOnDown() {
		$this->prepareDataCepWindowCauseSymptomCloseWindowDiscardDown();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(self::CEP_RULE_WINDOW_CAUSE_CLOSE_DISCARD, true);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The single id variant of testTriggerCEP_CepWindowCauseSymptomCloseWindow: one id sent a "down" value per
	 * discovered trigger, so the first of them is the cause of the window and every event after it a symptom - the
	 * events of that many triggers ranked into one cause and its symptoms. The operation still singles the "up" event
	 * out by the rank its window has just given it AND by the tag of the event, the rank alone naming every event
	 * after the first, and closes the window with every one of those problems - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomCloseWindowSingleService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomCloseWindowSingleService() {
		$this->prepareDataCepWindowCauseSymptomCloseWindowSingleService();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_CAUSE_CLOSE), false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The single id variant of testTriggerCEP_CepWindowCauseSymptomCloseWindowOnEvicted: the window has room for
	 * exactly the "down" values of the one id, one per discovered trigger, so the "up" value that follows them is
	 * evicted - never taken in and therefore never ranked, which is why this flavour recognises it by its tag alone -
	 * and ends the window it never entered, see runEventAssessmentTestCepWindowCloseWindow().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomCloseWindowOnEvictedSingleService$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomCloseWindowOnEvictedSingleService() {
		$this->prepareDataCepWindowCauseSymptomCloseWindowOnEvictedSingleService();

		try {
			$this->runEventAssessmentTestCepWindowCloseWindow(
				self::buildSingleServiceRuleName(self::CEP_RULE_WINDOW_CAUSE_CLOSE_EVICTED), false, true
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/* The window ended by its duration instead of by an operation of the rule */

	/**
	 * A cause and symptom window left to its own duration: that is the one window type whose duration closes it rather
	 * than evicting what it holds, so the problems of the events it was given are closed without a value of any kind
	 * having been sent for them - and the next period closes the next window in turn. The rule has no operation that
	 * could end anything, only the "close" of a closing window, see
	 * runEventAssessmentTestCepWindowCauseSymptomCloseOnDuration().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomCloseOnDuration$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomCloseOnDuration() {
		$this->prepareDataCepWindowCauseSymptomCloseOnDuration();

		try {
			$this->runEventAssessmentTestCepWindowCauseSymptomCloseOnDuration(
				self::CEP_RULE_WINDOW_CAUSE_CLOSE_DURATION
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * A simple window ended by its duration the only way a sliding window can be: its duration evicts the event that
	 * has been in it too long, and the rule closes the window over that eviction rather than over the "up" value every
	 * close window flavour uses. The window then closes with the younger event still in it, so that one is closed and
	 * the evicted one is not - see runEventAssessmentTestCepWindowCloseOnDuration().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleCloseOnDuration$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleCloseOnDuration() {
		$this->prepareDataCepWindowSimpleCloseOnDuration();

		try {
			$this->runEventAssessmentTestCepWindowCloseOnDuration(self::CEP_RULE_WINDOW_SIMPLE_CLOSE_DURATION);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same as testTriggerCEP_CepWindowSimpleCloseOnDuration with a tag correlation window: correlating the events
	 * of a group is not what ages them out of it, so the eviction that ends the window has to happen for this window
	 * type exactly as it does for a simple one - see runEventAssessmentTestCepWindowCloseOnDuration().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagCloseOnDuration$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagCloseOnDuration() {
		$this->prepareDataCepWindowTagCloseOnDuration();

		try {
			$this->runEventAssessmentTestCepWindowCloseOnDuration(self::CEP_RULE_WINDOW_TAG_CLOSE_DURATION);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/* Reset of a rule - test that the windows of each window type are thrown away with it */

	/**
	 * Resetting a rule whose simple windows are holding the problems of three ids: the windows are thrown away
	 * with everything in them, so the problems they were holding are left to the trigger expression - see
	 * runEventAssessmentTestCepWindowReset().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleReset$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleReset() {
		$this->prepareDataCepWindowSimpleReset();

		try {
			$this->runEventAssessmentTestCepWindowReset(self::CEP_RULE_WINDOW_SIMPLE_RESET);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same reset scenario with tag correlation windows: correlating the events of a group is not what keeps
	 * them, so a reset must empty this window type exactly as it empties a simple one - see
	 * runEventAssessmentTestCepWindowReset().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagReset$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagReset() {
		$this->prepareDataCepWindowTagReset();

		try {
			$this->runEventAssessmentTestCepWindowReset(self::CEP_RULE_WINDOW_TAG_RESET);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same reset scenario with cause and symptom windows, the one window type that ranks what it is given: the
	 * reset takes the cause of every id with the window that ranked it, so the "down" value that follows the reset
	 * opens a window of its own and is the cause of it rather than a symptom of the event before it - see
	 * runEventAssessmentTestCepWindowReset().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomReset$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomReset() {
		$this->prepareDataCepWindowCauseSymptomReset();

		try {
			$this->runEventAssessmentTestCepWindowReset(self::CEP_RULE_WINDOW_CAUSE_RESET);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same reset scenario with pattern match windows, the one window type that is examined on its own: a reset
	 * window is gone rather than closed, so nothing examines it again and the events it held are not acted on by
	 * anything - see runEventAssessmentTestCepWindowReset().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternReset$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternReset() {
		$this->prepareDataCepWindowPatternReset();

		try {
			$this->runEventAssessmentTestCepWindowReset(self::CEP_RULE_WINDOW_PATTERN_RESET);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/* Deletion of a rule - test that the windows of each window type are thrown away with it, and stay away */

	/**
	 * Deleting a rule whose simple windows are holding the problems of three ids: the windows are thrown away with
	 * everything in them, and unlike a reset the delete takes the rule with them - so the values that used to close
	 * those problems close nothing and everything is left to the trigger expression, see
	 * runEventAssessmentTestCepWindowDelete().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleDelete$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleDelete() {
		$this->prepareDataCepWindowSimpleDelete();

		try {
			$this->runEventAssessmentTestCepWindowDelete(self::CEP_RULE_WINDOW_SIMPLE_DELETE);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same delete scenario with tag correlation windows: correlating the events of a group is not what keeps
	 * them, so deleting the rule must empty this window type exactly as it empties a simple one - see
	 * runEventAssessmentTestCepWindowDelete().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagDelete$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagDelete() {
		$this->prepareDataCepWindowTagDelete();

		try {
			$this->runEventAssessmentTestCepWindowDelete(self::CEP_RULE_WINDOW_TAG_DELETE);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same delete scenario with cause and symptom windows, the one window type that ranks what it is given: the
	 * delete takes the cause of every id with the window that ranked it, and no window ranks anything afterwards, so
	 * every event that follows the delete is a cause of nothing and a symptom of nothing - see
	 * runEventAssessmentTestCepWindowDelete().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomDelete$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomDelete() {
		$this->prepareDataCepWindowCauseSymptomDelete();

		try {
			$this->runEventAssessmentTestCepWindowDelete(self::CEP_RULE_WINDOW_CAUSE_DELETE);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same delete scenario with pattern match windows, the one window type that is examined on its own: a deleted
	 * window is gone rather than closed and there is no rule left to examine one, so neither the events it held nor
	 * the ones sent afterwards are acted on by anything - see runEventAssessmentTestCepWindowDelete().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternDelete$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternDelete() {
		$this->prepareDataCepWindowPatternDelete();

		try {
			$this->runEventAssessmentTestCepWindowDelete(self::CEP_RULE_WINDOW_PATTERN_DELETE);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The pattern match delete scenario driven by two events and a script that sleeps three seconds on every
	 * examination of the window: the rule is deleted one second into a script, so the script runs on for two seconds
	 * with no rule behind it and the window it is examining has to be discarded once it returns rather than leaking
	 * with the events it holds - see runEventAssessmentTestCepWindowDeleteDuringScript().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternDeleteDuringScript$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternDeleteDuringScript() {
		$this->prepareDataCepWindowPatternDeleteSleep();

		try {
			$this->runEventAssessmentTestCepWindowDeleteDuringScript(self::CEP_RULE_WINDOW_PATTERN_DELETE_SLEEP);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/* Unresolved window limits - test that each window type reports them and recovers once they resolve */

	/**
	 * A simple window whose duration and capacity are user macros that do not exist: without limits no window is
	 * opened at all, so the operations that need one reach nothing and the rule reports the limit it could not parse
	 * - and it opens a window again the moment the macros are created, see
	 * runEventAssessmentTestCepWindowUnresolvedLimits().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowSimpleUnresolvedLimits$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowSimpleUnresolvedLimits() {
		$this->prepareDataCepWindowSimpleUnresolvedLimits();

		try {
			$this->runEventAssessmentTestCepWindowUnresolvedLimits(self::CEP_RULE_WINDOW_SIMPLE_LIMITS);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same unresolved limits scenario with a tag correlation window: correlating the events of a group is not
	 * what a window needs its limits for, so this type must fail on missing ones and recover from them exactly as a
	 * simple window does - see runEventAssessmentTestCepWindowUnresolvedLimits().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowTagUnresolvedLimits$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowTagUnresolvedLimits() {
		$this->prepareDataCepWindowTagUnresolvedLimits();

		try {
			$this->runEventAssessmentTestCepWindowUnresolvedLimits(self::CEP_RULE_WINDOW_TAG_LIMITS);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same unresolved limits scenario with a cause and symptom window, the one window type that ranks what it is
	 * given: a window that was never opened ranks nothing either, so the events sent while the limits do not resolve
	 * are causes of nothing and symptoms of nothing on top of being held by nothing - see
	 * runEventAssessmentTestCepWindowUnresolvedLimits().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowCauseSymptomUnresolvedLimits$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowCauseSymptomUnresolvedLimits() {
		$this->prepareDataCepWindowCauseSymptomUnresolvedLimits();

		try {
			$this->runEventAssessmentTestCepWindowUnresolvedLimits(self::CEP_RULE_WINDOW_CAUSE_LIMITS);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The unresolved limits scenario with a pattern match window - the one window type that is examined on its own,
	 * so there is no window to hand to the script of the rule and the limits are what the rule reports rather than
	 * anything the script decided - driven with the duration as the only limit that cannot resolve: the capacity
	 * macro exists from the start, so the duration failing is the whole of what the rule has to report and creating
	 * that one macro is the whole of what has to bring it back - see
	 * runEventAssessmentTestCepWindowSingleUnresolvedLimit().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternDurationUnresolved$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternDurationUnresolved() {
		$this->prepareDataCepWindowPatternDurationUnresolved();

		try {
			$this->runEventAssessmentTestCepWindowSingleUnresolvedLimit(
				self::CEP_RULE_WINDOW_PATTERN_DURATION_LIMIT, self::CEP_WINDOW_DURATION_MACRO,
				self::CEP_RULE_WINDOW_LIMITS_DURATION
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * The same pattern match window with the capacity as the only limit that cannot resolve: the duration macro
	 * exists from the start, which is what puts the capacity in front of the server instead of behind a duration it
	 * cannot read - the message the rule reports could come from nowhere else - see
	 * runEventAssessmentTestCepWindowSingleUnresolvedLimit().
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_CepWindowPatternCapacityUnresolved$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_CepWindowPatternCapacityUnresolved() {
		$this->prepareDataCepWindowPatternCapacityUnresolved();

		try {
			$this->runEventAssessmentTestCepWindowSingleUnresolvedLimit(
				self::CEP_RULE_WINDOW_PATTERN_CAPACITY_LIMIT, self::CEP_WINDOW_CAPACITY_MACRO,
				(string) self::CEP_RULE_WINDOW_LIMITS_CAPACITY
			);
		}
		finally {
			$this->cleanupCepRules();
		}
	}

	/**
	 * Same "close old down when new up" scenario as
	 * testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp, but the correlation rule uses
	 * CONDITION_EVAL_TYPE_EXPRESSION with a custom formula ("A and B and C") instead of
	 * CONDITION_EVAL_TYPE_AND_OR, exercising the custom expression evaluation path.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpression$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	/*public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpression() {
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_EXPRESSION);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(false);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}*/

	/**
	 * Same scenario as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpression but the server
	 * component is stopped and restarted between each step.
	 *
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpression
	 */
	/*public function testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUpExpressionRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->prepareDataGlobalCorrelationCloseOnUp(CONDITION_EVAL_TYPE_EXPRESSION);
		$this->runEventAssessmentTestGlobalCorrelationCloseOnUp(true);
		$this->waitForNoOpenProblems(array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids));
	}*/

	/**
	 * Discover a single log trigger from the dedicated log template (linked directly to the host) and
	 * verify that a burst of LOG_EVENT_COUNT log values, all matching the trigger pattern, produces
	 * exactly LOG_EVENT_COUNT problem events. The trigger prototype has multiple problem event
	 * generation enabled, so every matching value opens a new problem on the same trigger.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_LogMultipleEvents() {
		$this->openLogProblemBurst(false);
	}

	/**
	 * Recover the single log trigger: a single value that does not match the "problem" pattern turns
	 * the expression false, so the trigger goes back to OK and every problem opened by
	 * testTriggerCEP_LogMultipleEvents is resolved.
	 *
	 * @depends testTriggerCEP_LogMultipleEvents
	 */
	public function testTriggerCEP_LogRecovery() {
		$this->recoverLogTrigger(false);
	}

	/**
	 * Same as testTriggerCEP_LogMultipleEvents but the server is restarted before the burst is opened.
	 *
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_LogMultipleEventsRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->openLogProblemBurst(true);
	}

	/**
	 * Same as testTriggerCEP_LogRecovery but the server is restarted before recovery. The restart forces
	 * the LOG_EVENT_COUNT problems opened by testTriggerCEP_LogMultipleEventsRestart to be reloaded into
	 * the event cache (exercising the batched initial event cache loading) before they are resolved.
	 *
	 * @depends testTriggerCEP_LogMultipleEventsRestart
	 */
	public function testTriggerCEP_LogRecoveryRestart() {
		$this->skipIfRestartTestsDisabled();
		$this->recoverLogTrigger(true);
	}

	/**
	 * Push a burst of LOG_EVENT_COUNT log values to the discovered item in a single request and wait until
	 * every value has opened a problem event on the log trigger. Each value matches the "problem" pattern
	 * and carries a distinct timestamp so none collapse; with multiple problem event generation enabled
	 * every value opens a new problem on the discovered trigger. When $restart is true, the server is
	 * restarted before the burst is sent.
	 */
	private function openLogProblemBurst(bool $restart): void {
		$this->maybeRestartServer($restart);

		$triggerids = [self::$discovered_log_triggerid];
		$this->captureEventBaseline($triggerids);

		// The trigger fires on the dependent discovered log item. A real proxy resolves dependent items
		// itself and delivers their values directly, and the server does not propagate proxy-delivered
		// master values to dependents, so the burst is pushed straight to the dependent item here rather
		// than to the master.
		$item_key = self::LOG_ITEM_PROTO_KEY.'['.self::LOG_COMPONENT_VALUE.']';
		$values = [];
		for ($i = 0; $i < static::LOG_EVENT_COUNT; $i++) {
			$values[] = [
				'host' => self::HOST_NAME,
				'key' => $item_key,
				'value' => 'problem '.$i
			];
		}
		$this->dispatchSenderValues($values);

		// Every one of the LOG_EVENT_COUNT values must have generated a problem event on the trigger.
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1
		], static::LOG_EVENT_COUNT, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Send a single non-matching log value so the trigger expression turns false, then wait until all open
	 * problems on the log trigger are resolved and the trigger is back to OK. When $restart is true, the
	 * server is restarted before the recovery value is sent.
	 */
	private function recoverLogTrigger(bool $restart): void {
		$this->maybeRestartServer($restart);

		$triggerids = [self::$discovered_log_triggerid];

		// Sent directly to the dependent discovered log item (see openLogProblemBurst): the server does not
		// propagate proxy-delivered master values to dependents. A burst of non-matching values is sent (as in
		// openLogProblemBurst) to stress parallel recovery, even though a single value is enough to turn the
		// trigger expression false and recover all open problems.
		$item_key = self::LOG_ITEM_PROTO_KEY.'['.self::LOG_COMPONENT_VALUE.']';
		$values = [];
		for ($i = 0; $i < static::LOG_EVENT_COUNT; $i++) {
			$values[] = [
				'host' => self::HOST_NAME,
				'key' => $item_key,
				'value' => 'recovered '.$i
			];
		}
		$this->dispatchSenderValues($values);

		$this->waitForNoOpenProblems($triggerids, 'log recovery');
	}

	/**
	 * Discover exactly one log trigger from the dedicated log template by sending LLD data with a single
	 * entry. Asserts the trigger has multiple problem event generation enabled and stores the discovered
	 * trigger id.
	 */
	private function discoverLogTrigger(): void {
		$this->reloadConfigurationCacheAndWaitForLogLine();

		$this->dispatchSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::LOG_LLD_RULE_KEY,
				'value' => json_encode(['data' => [
					[self::LOG_LLD_MACRO => self::LOG_COMPONENT_VALUE]
				]])
			]
		]);

		$item_key = self::LOG_ITEM_PROTO_KEY.'['.self::LOG_COMPONENT_VALUE.']';

		// Wait for the discovered log item.
		$this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'filter' => ['key_' => $item_key],
			'output' => ['itemid']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === 1;
		});

		// Wait for the single discovered log trigger.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'search' => ['description' => 'CEP log trigger for '],
			'output' => ['triggerid', 'type']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === 1;
		});
		$this->assertCount(1, $response['result'], 'Discovered log trigger was not created.');
		$this->assertEquals(TRIGGER_MULT_EVENT_ENABLED, (int) $response['result'][0]['type'],
			'Discovered log trigger must have multiple problem event generation enabled.');
		self::$discovered_log_triggerid = $response['result'][0]['triggerid'];

		// Reload config so the server is aware of the newly discovered item and trigger.
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Same "close old down when new up" setup as testTriggerCEP_EventAssessmentGlobalCorrelationCloseOnUp,
	 * but the scenario only OPENS problems: both "down" waves are sent (two open problems per trigger) and
	 * the "up" values that would close them are deliberately never sent. The discovered triggers are then
	 * deleted via empty LLD (triggerCEP_Cleanup()) and the discovered host via testTriggerCEP_CleanupDiscoveredHost();
	 * deleting them must resolve every open problem and drop their events from the CEP cache, so no open
	 * problem remains afterwards.
	 *
	 * This is the terminal test that uses the discovered host, so it is declared last among the host-using
	 * tests: the cleanup chain (testTriggerCEP_Cleanup, testTriggerCEP_CleanupDiscoveredHost) runs after it and
	 * tolerates the already-removed host.
	 * run as (testPrepareTriggerCEP_LLDDiscovery|testTriggerCEP_EventAssessmentGlobalCorrelationOpenThenRemoveHost$)
	 * @depends testPrepareTriggerCEP_LLDDiscovery
	 */
	public function testTriggerCEP_EventAssessmentGlobalCorrelationOpenThenRemoveHost() {
		$this->prepareDataGlobalCorrelationCloseOnUp();

		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);

		// Only open problems: send both "down" waves (no "up" values), so two problems stay open per trigger.
		$this->openProblemsGlobalCorrelationCloseOnUp();

		// Delete the discovered triggers via empty LLD while their problems are still open: this must resolve
		// every open problem and drop their events from the CEP cache, exercising the trigger-deletion path
		// before the host itself is removed.
		$this->triggerCEP_Cleanup();

		// Removing the discovered host must delete any remaining resources and resolve every open problem.
		$this->testTriggerCEP_CleanupDiscoveredHost();

		// Deleting the host queues its triggers' problem/event records for removal; force both the general and
		// the trigger housekeeper so those records are actually deleted before verifying that nothing remains.
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'housekeeper_execute');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'forced execution of the housekeeper', true, 20, 3);
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'trigger_housekeeper_execute');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'forced execution of the trigger housekeeper',
				true, 20, 3);

		// The triggers were deleted (by empty LLD and then with the host), so only the problem count can be
		// checked here: waitForNoOpenProblems() additionally asserts the triggers are still present in OK
		// state, which no longer holds. No open problem may remain on the now-deleted triggers.
		$this->waitForOpenProblemCount($all, 0);

		// Removing the host must also drop the discovered items' problem events from the CEP cache: no other
		// problem is open in the system at this point, so cache.events must drain back to zero.
		$this->assertCepStatEquals('cache', 'events', 0);
		$this->assertCepStatEquals('cache', 'objects', 0);
		$this->assertCepNoWindows();
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'diaginfo=cep');
	}

	/**
	 * Send empty LLD data to delete all resources that were discovered during the test run
	 * and verify the discovered triggers are actually removed.
	 *
	 * @depends testTriggerCEP_DependentTrigger
	 * @depends testTriggerCEP_EventAssessmentNone
	 * @depends testTriggerCEP_EventAssessmentServiceCorrelationManualClose
	 * @depends testTriggerCEP_EventAssessmentGlobalCorrelationCrossTrigger
	 * @depends testTriggerCEP_LogRecovery
	 */
	public function testTriggerCEP_Cleanup() {
		self::triggerCEP_Cleanup();
	}

	/**
	 * Send empty host LLD data to remove the discovered host and verify it is actually deleted.
	 *
	 * @depends testTriggerCEP_Cleanup
	 */
	public function testTriggerCEP_CleanupDiscoveredHost() {
		// The open-then-remove-host scenario already removes the discovered host mid-suite, so this may run
		// with the host already gone. Nothing left to delete in that case.
		if (self::$disc_hostid === null) {
			return;
		}

		$this->dispatchSenderValues([
			[
				'host' => self::HOST_NAME,
				'key' => self::HOST_LLD_RULE_KEY,
				'value' => json_encode(['data' => []])
			]
		]);

		$this->callUntilCountIsPresent('host.get', [
			'hostids' => [self::$disc_hostid]
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		self::$disc_hostid = null;
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Delete the template created during setup and verify it is gone.
	 *
	 * @depends testTriggerCEP_CleanupDiscoveredHost
	 */
	public function testTriggerCEP_CleanupTemplate() {
		$this->call('template.delete', [self::$templateid]);

		$response = $this->call('template.get', [
			'templateids' => [self::$templateid],
			'countOutput' => true
		]);
		$this->assertEquals(0, $response['result'], 'Template was not deleted.');
		self::$templateid = null;
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * @depends testTriggerCEP_CleanupTemplate
	 */
	public function testTriggerCEP_ClearData(): void {
		self::clearData();
	}

	/**
	 * Drive all discovered items into the unsupported state (triggers become UNKNOWN) and verify that
	 * an internal problem is opened for every unsupported item and every unknown trigger.
	 */
	private function runOpenUnknownTest(): void {
		// With SCOPED_INTERNAL_ACTIONS the internal actions were disabled in prepareData(); enable them here
		// so the server starts generating internal item-not-supported / trigger-unknown events just for the
		// *Unknown tests. They are disabled again by runCloseUnknownTest().
		if (self::SCOPED_INTERNAL_ACTIONS) {
			$this->enableInternalActions();
		}

		// Record the highest internal-source eventid that already exists (the previous cycle, if any, was
		// fully drained by runCloseUnknownTest()) so this cycle's notifications can be awaited by restricting
		// the alert wait to the events generated after this baseline.
		$this->captureInternalEventBaseline();

		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		// Push a non-numeric value to flip all items into unsupported state; CEP keeps the trigger
		// value unchanged (OK) while the state becomes UNKNOWN.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'not_a_number',
					'state' => ITEM_STATE_NOTSUPPORTED], $keys)
		);

		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_FALSE);

		// An internal problem must be opened for every unknown trigger.
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => self::$discovered_triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_INTERNAL
		], static::LLD_DISCOVERY_COUNT, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// An internal problem must be opened for every unsupported item on the discovered host.
		$this->callUntilCountIsPresent('problem.get', [
			'hostids' => [self::$disc_hostid],
			'object' => EVENT_OBJECT_ITEM,
			'source' => EVENT_SOURCE_INTERNAL
		], static::LLD_DISCOVERY_COUNT, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Restore all discovered items to the supported state (triggers return to NORMAL/OK) and verify that
	 * every internal problem opened by runOpenUnknownTest is resolved.
	 */
	private function runCloseUnknownTest(): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);

		// Send a numeric value of 0 to restore all items to supported state; the trigger returns to
		// the NORMAL state and stays OK.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0'], $keys)
		);

		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_FALSE);

		// Every internal trigger-unknown problem must be resolved once the triggers leave UNKNOWN.
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => self::$discovered_triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_INTERNAL
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Every internal item-not-supported problem must be resolved once the items become supported.
		$this->callUntilCountIsPresent('problem.get', [
			'hostids' => [self::$disc_hostid],
			'object' => EVENT_OBJECT_ITEM,
			'source' => EVENT_SOURCE_INTERNAL
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Every notification generated by this cycle must finish before the internal actions are disabled:
		// disabling an action cancels its still-running escalations, so a recovery notification that is
		// still queued when disableInternalActions() reloads the cache would be dropped by the escalator.
		$this->waitForInternalAlertsCompleted();

		// Disable the internal actions enabled by runOpenUnknownTest() so the rest of the suite runs without
		// the server generating internal events again.
		if (self::SCOPED_INTERNAL_ACTIONS) {
			$this->disableInternalActions();
			$this->reloadConfigurationCacheAndWaitForLogLine();
		}
	}

	private function runEventAssessmentTest(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// Use the primary trigger for recovery/correlation mode detection.
		$triggers = $this->getTriggers($triggerids);
		$trigger = $triggers[self::$discovered_triggerid];
		$this->assertEquals(TRIGGER_VALUE_FALSE, $trigger['value'],
			'Trigger must start in OK state for assessment.');
		$none_recovery = ((int) $trigger['recovery_mode'] === ZBX_RECOVERY_MODE_NONE);
		$tag_correlation = ((int) $trigger['correlation_mode'] === ZBX_TRIGGER_CORRELATION_TAG);
		$mult_event = ((int) $trigger['type'] === TRIGGER_MULT_EVENT_ENABLED);

		$this->captureEventBaseline($triggerids);
		$expected_events = 0;

		// 1. OK→OK: no new event, no lastchange update.
		$this->assertNoStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, $expected_events);
		$this->maybeRestartServer($restart);

		// 2. OK→PROBLEM: PROBLEM event generated.
		$expected_events++;
		$this->assertStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, $expected_events);
		$this->maybeRestartServer($restart);

		// 3. All items unsupported while PROBLEM: CEP keeps trigger values as PROBLEM, state becomes UNKNOWN.
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'not_a_number',
					'state' => ITEM_STATE_NOTSUPPORTED], $keys)
		);
		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_TRUE);
		$this->maybeRestartServer($restart);

		// 4. PROBLEM→PROBLEM: items supported again.
		//    With multiple event generation a new PROBLEM event is produced;
		//    without it no new event and no lastchange update.
		if ($mult_event) {
			$expected_events++;
			$this->assertNoStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, $expected_events);
		}
		else {
			$this->assertNoStateChangeForAll($triggerids, $keys, '1', TRIGGER_VALUE_TRUE, $expected_events);
		}
		$this->maybeRestartServer($restart);

		// 5. PROBLEM→OK: with None recovery mode the triggers stay PROBLEM and no event is generated;
		//    otherwise a RESOLVED event is generated and triggers return to OK.
		if ($none_recovery) {
			$this->assertNoStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_TRUE, $expected_events);
		}
		else {
			$expected_events++;
			$this->assertStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, $expected_events);
		}
	}

	/**
	 * Run the service-correlation event-assessment scenario:
	 *
	 *   1. "down_0" → find(regexp,"down") = true, service tag = "0" → first PROBLEM event.
	 *   2. "down_1" → expression still true, service tag = "1" → new PROBLEM event alongside
	 *                 the already-open "down_0" problem; trigger value unchanged (stays TRUE).
	 *   3. "up_0"   → expression false, service tag = "0" → RESOLVED closes "down_0"; the
	 *                 "down_1" problem (service = "1") is still open → trigger stays TRUE.
	 *   4. "up_1"   → expression false, service tag = "1" → RESOLVED closes "down_1"; all
	 *                 problems resolved → trigger returns to OK.
	 */
	private function runEventAssessmentTestCorrelation(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// All triggers must start in OK state.
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggers as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for service correlation assessment.');
		}

		$this->captureEventBaseline($triggerids);
		$expected_events = 0;

		// 1. "down_0": expression true, service tag = "0" → PROBLEM event; trigger goes TRUE.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'down_0', TRIGGER_VALUE_TRUE, $expected_events
		);

		$this->maybeRestartServer($restart);

		// 2. "down_1": expression still true, service tag = "1" → new PROBLEM event with a different
		//    service tag value. Trigger value unchanged (stays TRUE); lastchange not updated.
		$expected_events++;
		$this->assertNoStateChangeForAll(
			$triggerids, $keys, 'down_1', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 3. "up_0": expression false, service tag = "0" → RESOLVED event closes the "down_0" problem.
		//    The "down_1" problem (service tag "1") is still open → trigger stays TRUE.
		$expected_events++;
		$this->assertPartialRecoveryForAll($triggerids, $keys, 'up_0', $expected_events);
		$this->maybeRestartServer($restart);

		// 4. "up_1": expression false, service tag = "1" → RESOLVED event closes the last open problem.
		//    All problems resolved → trigger returns to OK.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'up_1', TRIGGER_VALUE_FALSE, $expected_events
		);
	}

	/**
	 * Same scenario as runEventAssessmentTestCorrelation but the "down_1" problem is closed
	 * via manual close instead of an automatic recovery:
	 *
	 *   1. "down_0" → find(regexp,"down") = true, service tag = "0" → first PROBLEM event.
	 *   2. "down_1" → expression still true, service tag = "1" → new PROBLEM event alongside
	 *                 the already-open "down_0" problem; trigger value unchanged (stays TRUE).
	 *   3. "up_0"   → expression false, service tag = "0" → RESOLVED closes "down_0"; the
	 *                 "down_1" problem (service = "1") is still open → trigger stays TRUE.
	 *   4. Manual close → closeTagCorrelationProblems closes the remaining "down_1" problem;
	 *                 all problems resolved → trigger returns to OK.
	 */
	private function runEventAssessmentTestCorrelationManualClose(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// All triggers must start in OK state.
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggers as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for service correlation manual-close assessment.');
		}

		$this->captureEventBaseline($triggerids);
		$expected_events = 0;

		// 1. "down_0": expression true, service tag = "0" → PROBLEM event; trigger goes TRUE.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'down_0', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 2. "down_1": expression still true, service tag = "1" → new PROBLEM event with a different
		//    service tag value. Trigger value unchanged (stays TRUE); lastchange not updated.
		$expected_events++;
		$this->assertNoStateChangeForAll(
			$triggerids, $keys, 'down_1', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 3. "up_0": expression false, service tag = "0" → RESOLVED event closes the "down_0" problem.
		//    The "down_1" problem (service tag "1") is still open → trigger stays TRUE.
		$expected_events++;
		$this->assertPartialRecoveryForAll($triggerids, $keys, 'up_0', $expected_events);
		$this->maybeRestartServer($restart);

		// 4. Manual close: exactly one problem per trigger remains open (the "down_1" / service="1"
		//    problem). closeTagCorrelationProblems verifies that manual close is rejected while
		//    manual_close=false, then enables it, closes all remaining problems and waits for all
		//    triggers to return to OK.
		$this->closeTagCorrelationProblems($triggerids);
	}

	/**
	 * Run the global event correlation scenario:
	 *
	 *   1. "down_0" → find(regexp,"down") = true, service="0" → first PROBLEM event; trigger TRUE.
	 *   2. "down_1" → expression still true, service="1" → second PROBLEM event (mult_event);
	 *                 trigger stays TRUE; no old event with service="1" yet, correlation silent.
	 *   3. "down_0" → expression still true, service="0" → third PROBLEM event (mult_event);
	 *                 global correlation matches oldtag/newtag 'service'="0" pair → closes old
	 *                 event #1 (RESOLVED generated); trigger stays TRUE (events #2 and #3 open).
	 *   4. "up"     → expression false → RESOLVED closes all remaining open problems at once
	 *                 (trigger-level correlation = NONE); trigger returns to OK.
	 */
	private function runEventAssessmentTestGlobalCorrelation(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// All triggers must start in OK state.
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggers as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for service correlation assessment.');
		}

		$this->captureEventBaseline($triggerids);
		$expected_events = 0;

		// 1. "down_0": expression true, service tag = "0" → PROBLEM event; trigger goes TRUE.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'down_0', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 2. "down_1": expression still true, service tag = "1" → new PROBLEM event with a different
		//    service tag value. Trigger value unchanged (stays TRUE); lastchange not updated.
		$expected_events++;
		$this->assertNoStateChangeForAll(
			$triggerids, $keys, 'down_1', TRIGGER_VALUE_TRUE, $expected_events
		);
		$this->maybeRestartServer($restart);

		// 3. "up_0": expression false, service tag = "0" → RESOLVED event closes the "down_0" problem.
		//    The "down_1" problem (service tag "1") is still open → trigger stays TRUE.
		$expected_events++;
		$this->assertPartialRecoveryForAll($triggerids, $keys, 'up_0', $expected_events);
		$this->maybeRestartServer($restart);

		// 4. "up_1": expression false, service tag = "1" → RESOLVED event closes the last open problem.
		//    All problems resolved → trigger returns to OK.
		$expected_events++;
		$this->assertStateChangeForAll(
			$triggerids, $keys, 'up_1', TRIGGER_VALUE_FALSE, $expected_events
		);
	}

	/**
	 * Run the "close old down when new up" global event correlation scenario. Unlike
	 * runEventAssessmentTestGlobalCorrelation (where "up" is a recovery that resolves the trigger), the
	 * trigger expression here matches "up" too, so the "up_<id>" values are PROBLEM events that drive
	 * the correlation.
	 *
	 * Every problem carries a globally unique 'service' id (the trailing number of the value), so the
	 * service tag pair correlates an "up" event to exactly one "down" problem (1:1). Broadcasting a
	 * reused id would instead let a single "up" close every problem sharing that id; unique ids make the
	 * closing strictly corresponding. Both prototypes participate; with $m = total triggers, key index
	 * $i opens problem id $i and, in a second wave, id $i+$m — so each trigger holds two problems:
	 *
	 *   1. "down_<i>"    → PROBLEM, state="down", service="<i>"; trigger goes TRUE.
	 *   2. "down_<i+m>"  → PROBLEM, state="down", service="<i+m>" (mult_event); trigger stays TRUE.
	 *                      Two problems are now open per trigger; the rule is silent (no "up" event yet).
	 *   3. "up_<i>"      → PROBLEM, state="up", service="<i>"; global correlation (old state="down" + new
	 *                      state="up" + service tag pair) closes exactly the paired "down_<i>" (CLOSE_OLD)
	 *                      and the "up_<i>" itself (CLOSE_NEW). Each trigger's "down_<i+m>" stays open, so
	 *                      triggers stay TRUE and exactly $m problems remain.
	 *   4. "up_<i+m>"    → closes each trigger's remaining "down_<i+m>" and itself. Nothing stays open.
	 */
	/**
	 * Open (but never close) the "close old down when new up" problems: send both "down" waves — a unique id
	 * per trigger, then a second unique id per trigger (mult_event) — so two problems stay open on every
	 * discovered trigger. The "up" values that would close them via global correlation are deliberately not
	 * sent. Returns the per-wave problem count $m (so 2 * $m problems are open on return).
	 */
	private function openProblemsGlobalCorrelationCloseOnUp(): int {
		$keys = array_merge(
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY),
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2)
		);
		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);
		$m = count($keys);

		// Build one sender value per key with a unique id: value "<prefix>_<offset + key index>".
		$values = fn(string $prefix, int $offset) => array_map(
			fn($key, $i) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $prefix.'_'.($offset + $i)],
			$keys, array_keys($keys)
		);

		// All triggers must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for open-then-remove-host global correlation test.');
		}

		// 1. Open the first problem on every trigger (unique id per trigger); triggers go TRUE.
		$this->dispatchSenderValues($values('down', 0));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// 2. Open a second problem on every trigger (a different unique id, mult_event); still TRUE.
		$this->dispatchSenderValues($values('down', $m));
		$this->waitForOpenProblemCount($all, 2 * $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		return $m;
	}

	/**
	 * Drive the "close old down when new up" scenario across four waves (down, down, up, up). When
	 * $check_tags is true, after each wave the test asserts how many problem events carry WEB_SERVICE_TAG
	 * and WEB_SERVICE_TAG2 (each applied by its own webhook action, see createExtraTagWebhookAction):
	 * every problem is tagged by the webhooks as it opens and the tags are never removed, and all problems
	 * open (both "down" waves) before any closes (the "up" waves), so the tagged count is the high-water
	 * mark of the open-problem count — $m after wave 1, then 2 * $m from wave 2 onwards (unchanged as the
	 * "up" waves close problems back down). Requires captureEventBaseline() and the tag webhook action to
	 * be set up by the caller.
	 *
	 * When $up_from_other_trigger is true, each "up_N" value is sent to the next discovered item instead
	 * of the one whose trigger opened "down_N", so the closing "up" PROBLEM event is raised on a different
	 * trigger and the correlation service tag pair must close the relevant "down" problem by its id across
	 * triggers rather than each trigger receiving its own "up".
	 *
	 * When $check_web_services is true, the run additionally asserts the per-component web-tag services
	 * (see createWebTagServices, to be created by the caller) follow the webhook-applied WEB_COMPONENT_TAG
	 * tag: OK before the first wave, DISASTER once the webhook has tagged the open problems (waves 1-3, the
	 * triggers have DISASTER priority), WARNING after wave 3 once the still-open problems are manually
	 * downgraded via event.acknowledge (the services must follow the severity down, not only up) and OK
	 * again once wave 4 closes everything.
	 */
	private function runEventAssessmentTestGlobalCorrelationCloseOnUp(bool $restart,
			bool $maintenance_after_first = false, bool $check_tags = false,
			bool $stop_maintenance_and_verify_suppression = false, bool $up_from_other_trigger = true,
			bool $check_web_services = false, bool $maintenance_by_tag = false,
			bool $up_events_tagged = false): void {
		$keys = array_merge(
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY),
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2)
		);
		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);
		$m = count($keys);

		// How many problem events are expected to carry the webhook-applied tags once $wave waves have been
		// sent. With $up_events_tagged every problem event is tagged by the escalation that runs the tagging
		// webhook, so the count is simply the number of problems opened so far ($wave * $m). Otherwise only the
		// two "down" waves are ever tagged: a global correlation CLOSE_NEW disables the actions of the problem
		// it closes (see the CEP_ACTION_DISABLED handling in cep_worker.c), so those "up" problems never
		// escalate - unlike the ones a CEP rule closes, whose actions stay enabled.
		$tagged_after_wave = fn(int $wave) => ($up_events_tagged ? $wave : min($wave, 2)) * $m;

		// Build one sender value per key with a unique id: value "<prefix>_<offset + key index>". A
		// non-zero $shift sends the value carrying id N at the item $shift positions over, so the event
		// with service=N originates from a different trigger than the one that opened "down_N".
		$values = fn(string $prefix, int $offset, int $shift = 0) => array_map(
			fn($i) => [
				'host' => self::HOST_DISC_VALUE,
				'key' => $keys[($i + $shift) % $m],
				'value' => $prefix.'_'.($offset + $i)
			],
			array_keys($keys)
		);
		$up_shift = $up_from_other_trigger ? 1 : 0;

		// All triggers must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for close-on-up global correlation test.');
		}

		// The web-tag services (matched only by the webhook-applied WEB_COMPONENT_TAG tag) must start OK:
		// no problem has been tagged for them yet.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(ZBX_SEVERITY_OK);
		}

		// 1. Open the first problem on every trigger (unique id per trigger); triggers go TRUE.
		$this->dispatchSenderValues($values('down', 0));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// In $maintenance_by_tag mode the host is placed under one maintenance per discovered component,
		// each scoped to that component via a 'component' tag filter, so a problem is suppressed only by
		// the single maintenance whose tag matches it; the map component value => maintenanceid drives the
		// per-component suppression assertions below.
		$maintenance_by_component = [];

		// The host only enters maintenance after the first problems are already open: creating the
		// maintenance now must retroactively suppress those $m open problems (and every problem opened
		// later), while global correlation still closes them normally below.
		if ($maintenance_after_first) {
			if ($maintenance_by_tag) {
				$maintenance_by_component = $this->startDiscHostTagMaintenances();
				$this->waitForOpenProblemsSuppressedPerComponent($all, $m, $maintenance_by_component);
			}
			else {
				$this->startDiscHostMaintenances(static::MAINTENANCE_COUNT);
				$this->waitForOpenProblemsSuppressedByMaintenances($all, $m, self::$disc_maintenanceids);
			}
			$this->waitForServicesSuppressed();

			if (!$maintenance_by_tag) {
				// Start additional maintenances on the already-suppressed host: the extra overlapping
				// maintenances must not disturb the existing suppression, and every open problem must end
				// up suppressed by every active maintenance. (Tag-scoped maintenances are one-per-component,
				// so there is nothing to overlap.)
				$this->startDiscHostMaintenances(static::MAINTENANCE_COUNT_EXTRA);
				$this->waitForOpenProblemsSuppressedByMaintenances($all, $m, self::$disc_maintenanceids);
			}
		}

		// Wave 1 is fully open ($m problems), so $m problem events are tagged by both webhooks.
		if ($check_tags) {
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, $tagged_after_wave(1));
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, $tagged_after_wave(1));
		}

		// Wave 1 covered every key, so the webhook has tagged an open problem of every component with
		// WEB_COMPONENT_TAG and every web-tag service goes to PROBLEM (DISASTER trigger priority) purely
		// via the webhook-applied tag - no trigger tag matches these services.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(TRIGGER_SEVERITY_DISASTER);
		}

		$this->maybeRestartServer($restart);

		if ($maintenance_after_first) {
			$this->waitForServicesSuppressed();
		}

		// 2. Open a second problem on every trigger (a different unique id, mult_event); still TRUE.
		$this->dispatchSenderValues($values('down', $m));
		$this->waitForOpenProblemCount($all, 2 * $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// The host is in maintenance by now, so this second wave (opened while maintenance is active) must
		// be suppressed at creation time as well: all 2 * $m open problems suppressed - each by every
		// active maintenance in host-wide mode, or by its single matching component maintenance in
		// $maintenance_by_tag mode.
		if ($maintenance_after_first) {
			if ($maintenance_by_tag) {
				$this->waitForOpenProblemsSuppressedPerComponent($all, 2 * $m, $maintenance_by_component);
			}
			else {
				$this->waitForOpenProblemsSuppressedByMaintenances($all, 2 * $m, self::$disc_maintenanceids);
			}
			$this->waitForServicesSuppressed();

			// If requested, verify services are suppressed while problems are still open,
			// then stop maintenance and verify suppression is cleared.
			if ($stop_maintenance_and_verify_suppression && $maintenance_by_tag) {
				// Stop all tag maintenances at once: suppression of the still-open problems and services
				// must be cleared.
				$this->stopDiscHostMaintenances(self::$disc_maintenanceids);

				$this->maybeRestartServer($restart);

				$this->reloadConfigurationCacheAndWaitForLogLine();

				$this->waitForSuppressionCleared();

				// Each service is matched to one component via its SERVICE_TAG problem tag, so a service is
				// suppressed exactly while its component's maintenance is active; this map drives the
				// per-component service assertions in the resume/stop steps below.
				$service_by_component = $this->getServiceIdsByComponent();

				$per_component = intdiv(2 * $m, count($maintenance_by_component));

				// Process the per-component maintenances out of creation order in the same
				// [last, middle bulk, last-1] grouping as the host-wide branch: highest index (id) first,
				// then everything but the last two at once, then the gap. The middle group is skipped when
				// there are fewer than three components.
				$components = array_keys($maintenance_by_component);
				$last = count($components) - 1;
				$groups = [[$last]];
				if ($last >= 2) {
					$groups[] = range(0, $last - 2);
				}
				if ($last >= 1) {
					$groups[] = [$last - 1];
				}

				// Resume the maintenances group by group: after each group exactly the resumed components'
				// problems are suppressed (each only by its own maintenance) and exactly their services are
				// suppressed, while the not-yet-resumed components' problems and services stay in problem.
				$resumed_by_component = [];
				foreach ($groups as $indexes) {
					$ids = [];
					foreach ($indexes as $index) {
						$component = $components[$index];
						$ids[] = $maintenance_by_component[$component];
						$resumed_by_component[$component] = $maintenance_by_component[$component];
					}
					$this->resumeDiscHostMaintenances($ids);

					$suppressed = $per_component * count($resumed_by_component);
					$this->waitForOpenProblemsSuppressedPerComponent($all, $suppressed, $resumed_by_component);
					$this->waitForServicesSuppressedForComponents($service_by_component,
						array_keys($resumed_by_component));
				}

				// Every component maintenance is active again, so every problem is suppressed and every
				// service is suppressed once more.
				$this->waitForServicesSuppressed();

				$this->maybeRestartServer($restart);

				// Now stop the maintenances again group by group, but split the bulk group so its last
				// (highest-id) component comes out of maintenance on its own first, then the remaining bulk
				// components: this exercises the suppression-data merge with the bulk's highest id removed
				// ahead of the lower ones. Stopping a group must unsuppress exactly its components' problems
				// while the others stay suppressed by their own still-active maintenance, so the suppressed
				// count shrinks by that group's worth per step and only stopping the final group clears the
				// suppression entirely. Each stopped group's services return to problem at the same step
				// while the still-maintained ones stay suppressed.
				$stop_groups = [[$last]];
				if ($last >= 2) {
					// Take the last of the bulk out first on its own, then the rest of the bulk.
					$stop_groups[] = [$last - 2];
					if ($last >= 3) {
						$stop_groups[] = range(0, $last - 3);
					}
				}
				if ($last >= 1) {
					$stop_groups[] = [$last - 1];
				}

				$remaining_by_component = $resumed_by_component;
				foreach ($stop_groups as $indexes) {
					$ids = [];
					foreach ($indexes as $index) {
						$component = $components[$index];
						$ids[] = $maintenance_by_component[$component];
						unset($remaining_by_component[$component]);
					}
					$this->stopDiscHostMaintenances($ids);
					$this->reloadConfigurationCacheAndWaitForLogLine();

					if (!empty($remaining_by_component)) {
						$suppressed = $per_component * count($remaining_by_component);
						$this->waitForOpenProblemsSuppressedPerComponent($all, $suppressed,
							$remaining_by_component);
						$this->waitForServicesSuppressedForComponents($service_by_component,
							array_keys($remaining_by_component));
					}
					else {
						// Last group stopped: nothing stays suppressed and every service returns to problem
						// (waitForSuppressionCleared() also asserts services are no longer suppressed).
						$this->waitForSuppressionCleared();
					}
				}
			}
			elseif ($stop_maintenance_and_verify_suppression) {
				// Stop all maintenances at once: suppression of the still-open problems and services
				// must be cleared.
				$this->stopDiscHostMaintenances(self::$disc_maintenanceids);

				$this->maybeRestartServer($restart);

				$this->reloadConfigurationCacheAndWaitForLogLine();

				$this->waitForSuppressionCleared();

				// Resume the stopped maintenances out of creation order, highest maintenanceid
				// first: the suppression data in the DB then holds only the highest id, and every
				// later resume adds maintenances with lower ids, which sort before the existing
				// entries (both sides are compared sorted by maintenanceid, not by start time).
				// The second step resumes all remaining lower-id maintenances but one at once, so a
				// single timer pass sees far more new cache-side maintenances than there are
				// DB-side suppression rows. After every step each problem must be suppressed by
				// exactly the maintenances resumed so far - the stopped ones must not linger in
				// the suppression data.
				$last = count(self::$disc_maintenanceids) - 1;
				$resumed = [];
				foreach ([[$last], range(0, $last - 2), [$last - 1]] as $indexes) {
					$ids = array_map(fn($index) => self::$disc_maintenanceids[$index], $indexes);
					$resumed = array_merge($resumed, $ids);
					$this->resumeDiscHostMaintenances($ids);
					$this->waitForOpenProblemsSuppressedByMaintenances($all, 2 * $m, $resumed);
					$this->waitForServicesSuppressed();
				}

				// Stop the resumed maintenances one by one in the same order: while at least one of
				// them is still active every problem must stay suppressed - by exactly the remaining
				// maintenances - and only stopping the last one may clear the suppression.
				//foreach ($resumed as $i => $maintenanceid) {
				//	$this->stopDiscHostMaintenances([$maintenanceid]);
				//
				//	$this->reloadConfigurationCacheAndWaitForLogLine();
				//
				//	$remaining = array_slice($resumed, $i + 1);
				//	if (!empty($remaining)) {
				//		$this->waitForOpenProblemsSuppressedByMaintenances($all, 2 * $m, $remaining);
				//		$this->waitForServicesSuppressed();
				//	}
				//}

				// Stop all resumed maintenances in bulk.
				$this->stopDiscHostMaintenances($resumed);

				$this->reloadConfigurationCacheAndWaitForLogLine();

				$this->maybeRestartServer($restart);

				$this->waitForSuppressionCleared();
			}
		}

		// Both "down" waves are now open (2 * $m problems), so 2 * $m problem events are tagged by both
		// webhooks.
		if ($check_tags) {
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, $tagged_after_wave(2));
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, $tagged_after_wave(2));
		}

		// Wave 2 keeps every component with open webhook-tagged problems, so the services stay DISASTER.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(TRIGGER_SEVERITY_DISASTER);
		}

		$this->maybeRestartServer($restart);

		// 3. "up" for the first id set: each is a PROBLEM that closes only its corresponding "down"
		//    (CLOSE_OLD) and itself (CLOSE_NEW) — matched by the service id even when the "up" was
		//    raised on another trigger ($up_shift). Each trigger's second problem stays open, so
		//    triggers stay TRUE and exactly $m problems remain.
		$this->dispatchSenderValues($values('up', 0, $up_shift));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// Wave 3 closed $m problems and the down problems keep their tags, so the tagged count only grows by
		// this wave's own "up" problem events - by $m when they are tagged too, by nothing when correlation
		// closed them with their actions disabled.
		if ($check_tags) {
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, $tagged_after_wave(3));
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, $tagged_after_wave(3));
		}

		// Wave 2's webhook-tagged problems are still open on every component, so the services stay DISASTER.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(TRIGGER_SEVERITY_DISASTER);

			// Manually downgrade the still-open problems to WARNING: the service manager must recompute
			// the web-tag service status from the new lower severity, so every service drops
			// DISASTER -> WARNING without any problem closing.
			$this->updateOpenProblemsSeverity($all, TRIGGER_SEVERITY_WARNING);
			$this->waitForWebTagServicesStatus(TRIGGER_SEVERITY_WARNING);
		}

		$this->maybeRestartServer($restart);

		// 4. "up" for the second id set closes each trigger's remaining problem; nothing stays open.
		$this->dispatchSenderValues($values('up', $m, $up_shift));
		$this->waitForNoOpenProblems($all);

		// Wave 4 closed the rest; nothing stays open, but the tagged count still reflects every problem event
		// that was ever tagged: every "down" problem, plus the "up" problems when they escalate too.
		if ($check_tags) {
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG, $tagged_after_wave(4));
			$this->waitForProblemEventsTagged($all, self::WEB_SERVICE_TAG2, $tagged_after_wave(4));
		}

		// No webhook-tagged problem stays open, so every web-tag service recovers to OK.
		if ($check_web_services) {
			$this->waitForWebTagServicesStatus(ZBX_SEVERITY_OK);
		}
	}

	/**
	 * Same "close old down when new up" scenario as runEventAssessmentTestGlobalCorrelationCloseOnUp, but
	 * the whole flow lands on a single discovered item (and its one trigger) rather than being spread
	 * across every discovered item. The one trigger opens two "down" problems (each with a unique 'service'
	 * id), then the matching "up" values — themselves PROBLEM events — close each corresponding "down"
	 * (and themselves) strictly 1:1, leaving no open problem. When $restart is true, the server is
	 * restarted between steps.
	 */
	private function runEventAssessmentTestGlobalCorrelationCloseOnUpSingleItem(bool $restart): void {
		// Drive a single discovered item (and its one trigger) so the whole scenario lands on one event
		// stream rather than being spread across every discovered item.
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for single-item close-on-up global correlation test.');
		}

		// Send one value with a unique id: value "<prefix>_<id>".
		$send = fn(string $prefix, int $id) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $prefix.'_'.$id]
		]);

		// 1. Open the first problem on the trigger (unique id); trigger goes TRUE.
		$send('down', 0);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 2. Open a second problem on the trigger (a different unique id, mult_event); still TRUE.
		$send('down', 1);
		$this->waitForOpenProblemCount($all, 2);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 3. "up" for the first id: a PROBLEM that closes only its corresponding "down" (CLOSE_OLD) and
		//    itself (CLOSE_NEW). The second problem stays open, so the trigger stays TRUE and one remains.
		$send('up', 0);

		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 4. "up" for the second id closes the trigger's remaining problem; nothing stays open.
		$send('up', 1);
		$this->waitForNoOpenProblems($all);
	}

	/**
	 * Drive the windowless (WINDOW_NONE) CEP scenario on a single discovered item, so the whole flow lands on
	 * one event stream. Three problems are opened on its one trigger, each with its own 'service' id, and each
	 * id is matched by exactly one rule of every id pair, so it ends up carrying the tags naming those rules
	 * plus the six tags of the rules that hold for every event of this DISASTER trigger on the discovered
	 * host:
	 *   - "down_0":  service "0"  -> service_equals, service_contains, service_less_equal, service_exists,
	 *                                event_name_not_equals, event_name_not_contains;
	 *   - "down_1":  service "1"  -> service_not_equals, service_not_contains, service_more_equal,
	 *                                service_not_exists, event_name_equals, event_name_contains;
	 *   - "down_10": service "10" -> service_not_equals, service_contains, service_more_equal,
	 *                                service_not_exists, event_name_not_equals, event_name_contains;
	 *   - all three              -> severity_equals, severity_more_equal, severity_less_equal, host_equals,
	 *                                host_group_equals, time_period_in, and never severity_not_equals,
	 *                                time_period_not_in nor any of the non-Equals host / host group rules;
	 *   - the evaltype rules     -> service_and on "down_10", service_or on "down_0" and "down_10",
	 *                                service_and_or on "down_0" and "down_1", service_expression on "down_0"
	 *                                and "down_10";
	 *   - all three              -> the tag state the tag operation rule leaves behind, and the name,
	 *                                severity and suppression the event operation rule leaves behind, the same
	 *                                on every event (see getWindowNoneTagOperationCases() and
	 *                                getWindowNoneEventOperationCase()). The suppression is temporary, so
	 *                                unless SKIP_UNSUPPRESS_WAIT says otherwise it is then waited out and the
	 *                                timer must clear it from every event again.
	 *
	 * "down_10" is what makes the Contains pairs more than slower Equals pairs: its id contains "0" without
	 * being equal to it and its event name contains the "down_1" item value without being equal to the
	 * "down_1" event name, so both Equals/Contains pairs must disagree on it (and, being numerically above the
	 * "1" threshold, it also keeps the numeric comparison from degrading into a string one). No rule closes
	 * anything, so every problem stays open, and the tags of all events so far are re-checked after each
	 * value, so a rule tagging an event it must not match is caught on the value that produced it. A value
	 * matching neither "down" nor "up" finally turns the trigger expression false and closes all three
	 * problems at once.
	 */
	private function runEventAssessmentTestCepWindowNone(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the windowless CEP test.');
		}

		// Only the events generated from here on are inspected for the CEP tags.
		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		// The severity, host, host group and time period rules cannot tell the events apart - all three have
		// DISASTER severity, come from the same discovered host in its one host group and occur inside the
		// all-the-time period - so the six conditions that hold must tag every event. The other eight
		// (severity_not_equals, the non-Equals host and host group rules and time_period_not_in) hold for no
		// event at all and must therefore appear nowhere: a rule tag that is not listed as expected fails the
		// check.
		$common_rule_tags = [
			self::CEP_TAG_SEVERITY_EQUALS,
			self::CEP_TAG_SEVERITY_MORE_EQUAL,
			self::CEP_TAG_SEVERITY_LESS_EQUAL,
			self::CEP_TAG_HOST_EQUALS,
			self::CEP_TAG_HOST_GROUP_EQUALS,
			self::CEP_TAG_TIME_PERIOD_IN
		];

		// The 'service' id of every problem the scenario opens, in the order they are sent, and the rules that
		// must have tagged its event (every rule tags with its own name): one rule of every opposite id pair,
		// the six rules that match every event, and whichever of the four evaltype rules selects this id. No
		// other rule tag may be on the event. The tag operation rule is not listed here - it leaves the same
		// state on every event, which waitForCepWindowNoneTaggedEvents() checks from its own definition.
		$expected_tags = [
			// The only id carrying a 'service_0' tag, so the only one the Exists rule may tag. Its event name
			// is neither equal to nor contains the "down_1" one, so both negative name rules match it.
			self::CEP_RULE_WINDOW_NONE_SERVICE => array_merge([
				self::CEP_TAG_SERVICE_EQUALS,
				self::CEP_TAG_SERVICE_CONTAINS,
				self::CEP_TAG_SERVICE_LESS_EQUAL,
				self::CEP_TAG_SERVICE_EXISTS,
				self::CEP_TAG_EVENT_NAME_NOT_EQUALS,
				self::CEP_TAG_EVENT_NAME_NOT_CONTAINS,
				// Its id is the first branch of the OR rule, the first half of the AND_OR rule's OR group and
				// the "B" of the custom expression, but it does not satisfy both halves of the AND rule.
				self::CEP_TAG_SERVICE_OR,
				self::CEP_TAG_SERVICE_AND_OR,
				self::CEP_TAG_SERVICE_EXPRESSION
			], $common_rule_tags),
			// The id whose full event name the name Equals rule was built from, so it is the only one matched
			// by both positive name rules.
			self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT => array_merge([
				self::CEP_TAG_SERVICE_NOT_EQUALS,
				self::CEP_TAG_SERVICE_NOT_CONTAINS,
				self::CEP_TAG_SERVICE_MORE_EQUAL,
				self::CEP_TAG_SERVICE_NOT_EXISTS,
				self::CEP_TAG_EVENT_NAME_EQUALS,
				self::CEP_TAG_EVENT_NAME_CONTAINS,
				// The second half of the AND_OR rule's OR group. It is in neither branch of the OR rule nor of
				// the custom expression's "B or C": its id is not "0" and its event name does not contain
				// "down_10".
				self::CEP_TAG_SERVICE_AND_OR
			], $common_rule_tags),
			// Contains "0" without being equal to it, so the Contains rule matches it but the Equals rule does
			// not - the case that tells the two string pairs apart. Its own tag is 'service_10', so the Exists
			// rule (which looks for 'service_0') must not match it either, and its event name ends with
			// "down_10", which contains the "down_1" value without being equal to the "down_1" event name -
			// the same split, on the name side.
			self::CEP_RULE_WINDOW_NONE_SERVICE_LAST => array_merge([
				self::CEP_TAG_SERVICE_NOT_EQUALS,
				self::CEP_TAG_SERVICE_CONTAINS,
				self::CEP_TAG_SERVICE_MORE_EQUAL,
				self::CEP_TAG_SERVICE_NOT_EXISTS,
				self::CEP_TAG_EVENT_NAME_NOT_EQUALS,
				self::CEP_TAG_EVENT_NAME_CONTAINS,
				// The only id satisfying both conditions of the AND rule; the second branch of the OR rule and
				// the "C" of the custom expression match it through its event name. Its id is in neither half
				// of the AND_OR rule's OR group.
				self::CEP_TAG_SERVICE_AND,
				self::CEP_TAG_SERVICE_OR,
				self::CEP_TAG_SERVICE_EXPRESSION
			], $common_rule_tags)
		];

		// Each value opens one more problem (multiple event generation) that no rule ever closes, so the open
		// problem count only grows. The events of the ids sent so far are all re-verified after every value.
		$expected_so_far = [];
		$problem_count = 0;

		foreach ($expected_tags as $service => $tags) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$problem_count);
			$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

			$expected_so_far[$service] = $tags;
			$this->waitForCepWindowNoneTaggedEvents($triggerid, $expected_so_far);
		}

		// Every event checked above was suppressed by the event operations. That suppression is time limited,
		// so once its deadline has passed the timer must take it off all of them again - unless the wait for
		// that is skipped, which it is by default (SKIP_UNSUPPRESS_WAIT).
		$this->waitForCepWindowNoneUnsuppressed($triggerid);

		// A value matching neither "down" nor "up" turns the trigger expression false, which recovers the
		// trigger and closes every problem this scenario left open.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the windowless CEP scenario recovery value');
	}

	/**
	 * Drive a windowed flavour of the scenario on the same single discovered item and the same three 'service'
	 * ids, so every id opens one problem that lands in a window of its own (the rule groups by that tag). No
	 * rule closes a window or a problem, so all three stay open, and every event must come out with exactly
	 * the tag, name, severity and suppression state the operations of the windowed rule produce - the same
	 * state the windowless flavour produces from the same operations.
	 *
	 * $second_rule_applies says what must have become of the second rule of the flavour, the one adding
	 * CEP_TAG_WINDOW_SECOND: with a window type that is not exclusive it is processed as well and every event
	 * carries that tag, with an exclusive one it never gets its turn and no event may carry it.
	 */
	private function runEventAssessmentTestCepWindowOperations(bool $second_rule_applies): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the windowed operations test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		// The tag list per id names the operator coverage rules that must have tagged the event, and this
		// flavour creates none of them - hence an empty list for every id, meaning none of those tags may be
		// on the event. The tags the operations themselves add are not listed here: they are the same on every
		// event and waitForCepWindowNoneTaggedEvents() checks them from getWindowNoneTagOperationResults(),
		// exactly as in the windowless flavour. The second window rule's tag is the one thing the flavours
		// disagree on: it must be on every event, or on none of them.
		$second_result = [
			self::CEP_TAG_WINDOW_SECOND => $second_rule_applies ? self::CEP_TAG_WINDOW_SECOND_VALUE : null
		];

		$expected_tags = [];
		$problem_count = 0;

		foreach ([self::CEP_RULE_WINDOW_NONE_SERVICE, self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT,
				self::CEP_RULE_WINDOW_NONE_SERVICE_LAST] as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$problem_count);
			$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

			$expected_tags[$service] = [];
			$this->waitForCepWindowNoneTaggedEvents($triggerid, $expected_tags, $second_result);
		}

		$this->waitForCepWindowNoneUnsuppressed($triggerid);

		// As in the windowless flavour, a value matching neither "down" nor "up" recovers the trigger and
		// closes every problem left open.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the windowed operations recovery value');
	}

	/**
	 * Drive a windowed flavour whose operations run when the event is evicted from the window. Every id gets a
	 * window of its own holding its one event, and once the window duration has run out that event is evicted
	 * - which is when the operations are applied to it.
	 *
	 * The events must therefore end up in exactly the state the flavours acting at event time leave behind,
	 * only later: nothing may have been applied while the problems were being opened, and everything must have
	 * been applied once the windows expired. No rule closes a problem, so all three stay open.
	 */
	private function runEventAssessmentTestCepWindowEvictedOperations(): void {
		$this->runEventAssessmentTestCepWindowLateOperations('evicted');
	}

	/**
	 * Drive a windowed flavour whose operations run as the window closes: every id gets a window of its own, and
	 * the event that enters it closes it right away (the "close window" operation of the rule, see
	 * prepareDataCepWindowOperations()), so the operations are performed for it at the window closed execution
	 * point instead of at the one of the arriving event.
	 *
	 * What that has to leave behind is the state of every other flavour, the resolved macros above all: the event
	 * the operations act on is the one the closing window was holding rather than the one being assessed, so a tag
	 * the server could not resolve that late shows up as macro text where the resolved value is expected.
	 * No rule closes a problem, so all three stay open.
	 *
	 * The one difference to the flavours above is the event name: "set name" is not allowed at this execution point,
	 * so the rule of this flavour does not carry that operation (see prepareDataCepWindowOperations()) and the events
	 * must keep the name their trigger gave them instead of the rewritten one - which is what $set_name_skipped
	 * below asks for.
	 */
	private function runEventAssessmentTestCepWindowClosedOperations(): void {
		$this->runEventAssessmentTestCepWindowLateOperations('closed', true);
	}

	/**
	 * The body of the two flavours above, which differ in the execution point their rule applies its operations at
	 * ($what naming it for the failure messages) and in whether that point allows the "set name" operation at all
	 * ($set_name_skipped): the events are driven the same way and the state they must end up in is the same, an
	 * operation being what it does and not when it was asked to do it.
	 */
	private function runEventAssessmentTestCepWindowLateOperations(string $what,
			bool $set_name_skipped = false): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the window '.$what.' operations test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$expected_tags = [];
		$problem_count = 0;

		foreach ([self::CEP_RULE_WINDOW_NONE_SERVICE, self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT,
				self::CEP_RULE_WINDOW_NONE_SERVICE_LAST] as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$problem_count);
			$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

			$expected_tags[$service] = [];
		}

		// The operations run only once the events have left their windows - evicted because the duration ran out,
		// or closed with the window that held them - which the wait below covers. These flavours create no second
		// rule, so its tag may not be on any event either.
		$this->waitForCepWindowNoneTaggedEvents($triggerid, $expected_tags,
			[self::CEP_TAG_WINDOW_SECOND => null], $set_name_skipped
		);

		$this->waitForCepWindowNoneUnsuppressed($triggerid);

		// As in the other flavours, a value matching neither "down" nor "up" recovers the trigger and closes
		// every problem left open.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the window '.$what.' operations recovery value');
	}

	/**
	 * Drive the capacity flavour: one window with room for a single event, so every event after the first one
	 * is evicted the moment it arrives, and the rule closes what it evicts.
	 *
	 *   1. "down_0" finds the window empty and takes its one place; its problem stays open;
	 *   2. "down_1" does not fit, so it is suppressed and closed straight away - the only problem still open
	 *      is the one of "down_0", which never left the window;
	 *   3. "down_10" is handled the same way;
	 *   4. "up_0" does not fit either, so it is suppressed and closed as well, and being an "up" event it
	 *      additionally closes the window it could not enter - which closes the problem of "down_0" that the
	 *      window held, without suppressing it.
	 *
	 * Nothing is open afterwards. The trigger itself is still in problem state (its expression is unchanged),
	 * so a value matching neither "down" nor "up" is sent at the end to bring it back to OK for the tests
	 * that follow.
	 *
	 * The server is stopped and started between step 1 and step 2 unless the restarts are turned off: everything
	 * evicted below is then evicted from a window the server loaded back from the database, which has to have come
	 * back with the event it was holding and with the one place it has - see maybeRestartServerMidScenario().
	 */
	private function runEventAssessmentTestCepWindowCapacity(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the window capacity test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$first = self::CEP_RULE_WINDOW_NONE_SERVICE;

		// 1. The window is empty, so this event takes its place and its problem stays open.
		$send('down_'.$first);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
		$this->waitForOpenProblemCountByTag($all, 'service', $first, 1);

		// 1a. Stop and start the server with the one place of the window taken, unless the restarts are turned off:
		//     everything below is then evicted from a window the server loaded back from the database, so it has to
		//     have come back both with the event it was holding and with the one place it has - a window short of
		//     either would take the values of step 2 instead of evicting them.
		$this->maybeRestartServerMidScenario();

		$this->waitForOpenProblemCount($all, 1);

		// 2. Every further "down" opens a problem that does not fit into the window and is suppressed and
		//    closed as it is evicted, so the count returns to the one problem the window holds.
		$evicted = 0;

		foreach ([self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT, self::CEP_RULE_WINDOW_NONE_SERVICE_LAST] as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCountByTag($all, 'service', $service, 0);
			$this->waitForOpenProblemCount($all, 1);
			$this->waitForOpenProblemCountByTag($all, 'service', $first, 1);
			$this->waitForSuppressedEventCount($triggerid, ++$evicted);
		}

		// 3. The "up" event does not fit either, so it is suppressed and closed too, and it closes the window
		//    - and with it the problem the window was holding all along. Nothing is left open, and closing the
		//    last problem of a trigger is what puts the trigger itself back to OK, so no recovery value is
		//    needed here: the rule alone has to bring both the problems and the trigger back.
		$send('up_'.$first);
		$this->waitForNoOpenProblems($all, 'After the window capacity close on up');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		++$evicted;

		// Only the evicted events were suppressed; the one the window held was closed with the window, which
		// suppresses nothing.
		$this->waitForSuppressedEventCount($triggerid, $evicted);
	}

	/**
	 * Drive the capacity flavour that groups by the 'service' tag: every id gets a window of its own, so this
	 * time every "down" event fits and no problem is closed while they are being opened.
	 *
	 *   1. "down_0", "down_1" and "down_10" each find their own window empty and take its one place, so all
	 *      three problems stay open;
	 *   2. a second "down_0" goes to the window of that id, which now has no place left, so it does not fit
	 *      and is suppressed and closed as it is evicted - the capacity limits inside a group as well, and it
	 *      is one;
	 *   3. the "up" of an id finds that id's window occupied by its "down", so it does not fit: it is
	 *      suppressed and closed, and being an "up" event it closes the window too, which closes the "down"
	 *      problem the window held. Both problems of that id are gone, the ids not sent an "up" yet are
	 *      untouched.
	 *
	 * Only the events that did not fit are suppressed - the ones the windows held are closed with their window
	 * and stay unsuppressed - so the number of suppressed events counts the evictions.
	 *
	 * After the "up" of every id nothing is left open, and closing the last problem of a trigger is what puts
	 * the trigger itself back to OK, so no recovery value is needed.
	 *
	 * The server is stopped and started between step 1 and step 2 unless the restarts are turned off: the window the
	 * second value of that id does not fit into is then the one the server loaded back from the database, so both what
	 * it holds and the room left in it have to have come back with it - see maybeRestartServerMidScenario().
	 */
	private function runEventAssessmentTestCepWindowCapacityPerService(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the per service window capacity test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$first = self::CEP_RULE_WINDOW_NONE_SERVICE;
		$services = [$first, self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT, self::CEP_RULE_WINDOW_NONE_SERVICE_LAST];

		// 1. The first id finds its window empty and takes its one place.
		$send('down_'.$first);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
		$this->waitForOpenProblemCountByTag($all, 'service', $first, 1);

		// 1a. Stop and start the server with that one place taken, unless the restarts are turned off: the window the
		//     second value of the id runs into is then the one the server loaded back from the database, and it has to
		//     have come back with the event it was holding as well as with the one place it has - a window short of
		//     either would take that value instead of evicting it, and both problems of the id would stay open.
		$this->maybeRestartServerMidScenario();

		$this->waitForOpenProblemCount($all, 1);

		// 2. A second value with the same id goes to that same window, which has no place left, so this one
		//    does not fit: the id ends up with two problem events of which only the first one - the one the
		//    window holds - is still open. The capacity limits inside a group as well, and it is one.
		$send('down_'.$first);
		$this->waitForProblemEventCountByTag($all, 'service', $first, 2);
		$this->waitForOpenProblemCountByTag($all, 'service', $first, 1);
		$this->waitForOpenProblemCount($all, 1);

		// The evicted problem is the one that was suppressed, the one in the window is not.
		$evicted = 1;
		$this->waitForSuppressedEventCount($triggerid, $evicted);

		// 3. The other ids have windows of their own, so their "down" fits too and every problem stays open.
		$open = 1;

		foreach ([self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT, self::CEP_RULE_WINDOW_NONE_SERVICE_LAST] as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$open);
			$this->waitForOpenProblemCountByTag($all, 'service', $service, 1);
		}

		// 4. The "up" of an id does not fit into that id's window: it is closed as it is evicted and closes
		//    the window, which closes the "down" problem the window was holding. Only that id is affected.
		foreach ($services as $service) {
			$send('up_'.$service);
			++$evicted;
			$this->waitForOpenProblemCountByTag($all, 'service', $service, 0);
			$this->waitForOpenProblemCount($all, --$open);
			$this->waitForSuppressedEventCount($triggerid, $evicted);
		}

		// Nothing is left open, and the rule alone has to bring the trigger back to OK as well.
		$this->waitForNoOpenProblems($all, 'After the per service window capacity close on up');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
	}

	/**
	 * Drive the discard scenario. Discarding is the absence of everything, so the check is built around a
	 * later event: an event that is going to be stored appears in order after the discarded one, so once the
	 * event of the second "down" is there, the "up" in between would have shown up too if it had been kept.
	 *
	 *   1. "down_0" is not matched by the discard condition and opens its problem as usual;
	 *   2. "up_0" is matched, so it must leave nothing behind - no problem of its own, and no problem closed
	 *      either, which is what tells a discard apart from a close;
	 *   3. "down_1" is kept again and opens the second problem, which is the point the counts are checked at:
	 *      exactly two events exist since the baseline and none of them came from an "up" value.
	 */
	private function runEventAssessmentTestCepDiscard(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the discard test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		// 1. Kept: the problem opens and the trigger goes to problem state.
		$send('down_'.self::CEP_RULE_WINDOW_NONE_SERVICE);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// 2. Discarded: nothing may come of it. It is a problem value like any other, so without the rule it
		//    would open a second problem.
		$send('up_'.self::CEP_RULE_WINDOW_NONE_SERVICE);

		// 3. Kept again: waiting for this one to be counted is what makes the check above safe, since the
		//    discarded event would have been stored before it.
		$send('down_'.self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT);
		$this->waitForOpenProblemCount($all, 2);

		// Two values were kept and one was discarded, so only two events exist - and none of them is an "up"
		// one, which only a discarded event can achieve: a closed or suppressed event would still be there.
		$this->waitForAllTriggerEventCounts($all, 2);
		$this->waitForProblemEventsTagged($all, self::CEP_STATE_TAG_UP, 0);

		// The problems the rule did not touch recover the usual way.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the discard scenario recovery value');
	}

	/**
	 * Drive the per service capacity scenario whose rule also discards the "up" events, so that the same event
	 * is the one the window would evict and close on, and the one the discard drops.
	 *
	 * The discard settles it: it is looked for while the rules are matched, before the event is stored and
	 * before it is handed to any window, so an "up" event never gets as far as the window it does not fit
	 * into. Nothing is evicted on its account, nothing is suppressed, the window is not closed - and the "down"
	 * problem it holds stays open, unlike in runEventAssessmentTestCepWindowCapacityPerService() where the same
	 * rule without the discard closes it there and then.
	 *
	 * What ends those windows instead is their duration, which this flavour keeps short for exactly that reason
	 * (CEP_RULE_WINDOW_CAPACITY_DISCARD_DURATION): an event is evicted from a window as much by the window
	 * running out as by another event taking its place, so the "down" event of every id is suppressed and closed
	 * in the end after all - by the clock rather than by the "up" value that should have done it. The scenario
	 * needs no recovery value of its own, which is what shows the evictions did the closing.
	 */
	private function runEventAssessmentTestCepWindowCapacityDiscard(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the capacity discard test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$services = [self::CEP_RULE_WINDOW_NONE_SERVICE, self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT,
			self::CEP_RULE_WINDOW_NONE_SERVICE_LAST
		];

		// 1. As without the discard: every id has a window of its own, so every "down" fits and stays open. The
		//    values are sent one after the other and only then waited for: the window of this flavour is a short
		//    lived one (CEP_RULE_WINDOW_CAPACITY_DISCARD_DURATION), so waiting between the sends would spend the
		//    life of the first window before the last value was even given one.
		foreach ($services as $service) {
			$send('down_'.$service);
		}

		$open = count($services);

		$this->waitForOpenProblemCount($all, $open);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		foreach ($services as $service) {
			$this->waitForOpenProblemCountByTag($all, 'service', $service, 1);
		}

		// 2. Every "up" is dropped before it reaches its window, so none of them evicts anything, closes a
		//    window or closes the "down" problem that window holds.
		foreach ($services as $service) {
			$send('up_'.$service);
		}

		// 3. That leaves every window with a "down" event in it and nothing coming that could end it - the
		//    events that would have are the ones that were dropped - so the duration is what ends them: as it
		//    runs out for a window, the event it holds is evicted, which suppresses it and closes it. Every id
		//    is left with its one "down" event, suppressed and closed, and nothing open at all.
		$this->waitForNoOpenProblems($all, 'After the capacity discard window durations ran out');
		$this->waitForSuppressedEventCount($triggerid, $open);

		// Closing the last problem of a trigger is what puts the trigger back to OK, so the scenario needs no
		// recovery value: the discarded "up" values could not have brought it back, and the evictions did.
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);

		// Waiting for the evictions is what makes the discard check safe: they happened long after the "up"
		// values were sent, so a stored "up" would be there by now. What the trigger has is two events per id
		// and nothing else - the "down" problem and the recovery event closing it generated - and not one of
		// them came from an "up" value, which only a discarded event can achieve: a closed or suppressed one
		// would still be there.
		$this->waitForAllTriggerEventCounts($all, $open * 2);
		$this->waitForProblemEventsTagged($all, self::CEP_STATE_TAG_UP, 0);
	}

	/**
	 * The 'service' ids the close window scenarios drive, CEP_CLOSE_WINDOW_SERVICE_COUNT of them counted from zero.
	 * An id is the trailing number of the item value (see prepareCloseOnUpTriggerPrototypes()), so counting up
	 * gives as many distinct ids - and, the windows being grouped by that tag, as many windows - as the knob asks
	 * for. Which ids they are does not matter to these scenarios, unlike in the windowless one where the ids
	 * (CEP_RULE_WINDOW_NONE_SERVICE and its two companions) are chosen to tell the operators apart.
	 *
	 * The knob is read through static:: so a child class raising it (see testTriggerCEPAtScale) redirects every
	 * flavour of the scenario at once, which is also why the ids are computed here instead of being listed.
	 *
	 * $single_service is the single id flavours, which drive one id however many the knob asks for - the first of
	 * them, so it is an id of the same space as the others and needs no knob of its own. Their window then holds
	 * nothing but the values of that one id, all of them the same value, see
	 * prepareDataCepWindowPatternCloseWindowSingleService().
	 */
	private static function getCloseWindowServices(bool $single_service = false): array {
		$services = array_map('strval', range(0, static::CEP_CLOSE_WINDOW_SERVICE_COUNT - 1));

		return $single_service ? [reset($services)] : $services;
	}

	/**
	 * The id whose "down" values the discarding close window flavours drop: the last of the ones the scenario
	 * sends, so every id before it is kept and behaves exactly as it does without a discard. That there is an id
	 * before it is what the flavour compares against, which is why the smallest count still saying anything is 2.
	 */
	private static function getCloseWindowDiscardService(): string {
		$services = static::getCloseWindowServices();

		return end($services);
	}

	/**
	 * How many "down" values every id of the close window scenarios is sent, and therefore how many events each of
	 * its windows holds when the "up" value ends it, CEP_CLOSE_WINDOW_EVENT_COUNT of them. Like the id count it is
	 * read through static:: so a child class raising it redirects every flavour of the scenario at once.
	 *
	 * The single id flavours send one value per discovered trigger instead, LLD_DISCOVERY_COUNT of them: they drive one
	 * id, so all of those values go into the same window and it holds as many events as the host has triggers to send
	 * them through - one window filled as deep as the discovery goes, and no knob of its own to keep in step with that,
	 * see prepareDataCepWindowPatternCloseWindowSingleService().
	 */
	private static function getCloseWindowEventCount(bool $single_service = false): int {
		return $single_service ? static::LLD_DISCOVERY_COUNT : static::CEP_CLOSE_WINDOW_EVENT_COUNT;
	}

	/**
	 * The "down" values the close window scenarios send, as [id, how many values that id has been sent including
	 * this one] pairs in the order they go out. Which order that is follows CEP_CLOSE_WINDOW_FILL_PER_SERVICE:
	 * round by round, one value per id and then the next round, or id by id, every value of an id before the next
	 * id is touched at all.
	 *
	 * The pairs carry the per-id count rather than the round number, because that is what the scenario waits for
	 * after every value and it is the same number in both orders - the count of values that id has been sent so
	 * far. That is what lets one loop drive either order, see runEventAssessmentTestCepWindowCloseWindow().
	 */
	private static function getCloseWindowFillOrder(array $services, int $events): array {
		$order = [];

		if (static::CEP_CLOSE_WINDOW_FILL_PER_SERVICE) {
			foreach ($services as $service) {
				for ($count = 1; $count <= $events; $count++) {
					$order[] = [$service, $count];
				}
			}
		}
		else {
			for ($count = 1; $count <= $events; $count++) {
				foreach ($services as $service) {
					$order[] = [$service, $count];
				}
			}
		}

		return $order;
	}

	/**
	 * How much the windows of the eviction close window flavours hold: exactly the "down" values of one id, so all
	 * of them fit and the "up" value that follows them is the one event that does not - which is what gets it
	 * evicted rather than held, and an evicted event is what those flavours end the window from. A capacity of one
	 * per held event (CEP_RULE_WINDOW_CAPACITY) is what that comes to, so the limit follows the number of values
	 * sent instead of being a constant.
	 */
	private static function getCloseWindowCapacity(bool $single_service = false): int {
		return self::CEP_RULE_WINDOW_CAPACITY * static::getCloseWindowEventCount($single_service);
	}

	/**
	 * How long the windows of the close window scenarios last. They must outlast the whole scenario - an expiring
	 * window would evict what it holds and close the problems the scenario expects to find open - and how long
	 * that takes grows with the number of ids driven and with the number of values each of them is sent, every
	 * value being verified before the next one goes out, so the duration grows with both. Nothing is waited for on
	 * it, so it is generous: the rules are deleted once the flavour is done, taking their windows with them, and a
	 * window that lasts longer than needed costs nothing.
	 *
	 * The scenario stops and starts the server in the middle of itself when the restarts are turned on, and the
	 * windows have to be there when it comes back, so the time that takes is added to the duration as well - see
	 * getRestartWindowAllowance().
	 */
	private static function getCloseWindowDuration(bool $single_service = false): string {
		return (120 + 30 * count(static::getCloseWindowServices($single_service))
				* static::getCloseWindowEventCount($single_service) + static::getRestartWindowAllowance()).'s';
	}

	/**
	 * Drive every flavour of the close window scenario, whose $rule_name rule ends the window of an id once that
	 * id has recovered - on a pattern match, on the arrival of the "up" event or on its eviction, see
	 * prepareDataCepWindowCloseWindowOperations(). What ends the window is the only difference between the
	 * flavours, so the outcome this asserts is the same for all of them:
	 *
	 *   1. every id has a window of its own and no window expires, so every "down" event takes a place in the
	 *      window of its id and its problem stays open. No window has seen an "up" event yet, so none is closed;
	 *   2. the "up" of an id ends that id's window, and every problem of that id is closed with it - the "down"
	 *      problems the window held and the "up" problem that ended it;
	 *   3. the ids whose "up" value has not been sent are untouched: their windows have seen no "up" event, so they
	 *      are not closed and their problems are still open.
	 *
	 * With the windows full, and before anything is closed, what a window records about itself is read as well: the
	 * flavours whose window type may be asked to keep the number of events it has collected were asked to
	 * (event_count_tag, which only a cause and symptom window allows - see
	 * prepareDataCepWindowCloseWindowOperations()), so the oldest event of every id has to carry the number of values
	 * that joined it in that tag and no other event of the id may carry it at all. Whether a flavour keeps such a count
	 * is read from its rule rather than passed in, so the flavours of every other window type simply have nothing to
	 * look for - see getCepRuleEventCountTag() and waitForCepWindowEventCounts(). What becomes of the count once the
	 * "up" value arrives is not asserted: that value joins the window in the flavours ended by an arriving event and is
	 * evicted without joining in the ones ended by an eviction, so the two would have to expect different counts for
	 * something neither of them is about - and the events are closed a moment later either way.
	 *
	 * Between step 1 and step 2 the server is stopped and started, unless the restarts are turned off: the windows
	 * every "up" value below ends are then the ones the server loaded back from the database, holding the problems
	 * they were given before the shutdown, and steps 2 and 3 have to come out the same either way - a window that came
	 * back without them would close nothing but its "up" event and leave every "down" problem of its id open. See
	 * maybeRestartServerMidScenario().
	 *
	 * The pattern match flavour is not tied to the value that ends the window: its window is examined once a
	 * second, so the closing follows the "up" value rather than accompanying it - which is why every step of this
	 * scenario is waited for rather than asserted right away.
	 *
	 * Every id is driven the same way and none of the steps depends on how many there are, so the number of ids is
	 * a scale knob instead (CEP_CLOSE_WINDOW_SERVICE_COUNT, see getCloseWindowServices()): raising it makes the
	 * rule keep that many windows open at once, which is what spreads the scenario over the CEP worker processes.
	 *
	 * How deep those windows go is the second knob (CEP_CLOSE_WINDOW_EVENT_COUNT, see getCloseWindowEventCount()):
	 * every id is sent that many "down" values, so a window holds that many events and the "up" value of its id has
	 * to close all of them at once rather than a single one. Nothing about the steps changes with it: the counts they
	 * wait for are what the knob is multiplied into, so a count of one leaves the scenario with one event per
	 * window.
	 *
	 * A third knob says in which order those values go out (CEP_CLOSE_WINDOW_FILL_PER_SERVICE, see
	 * getCloseWindowFillOrder()): round by round, which keeps every window of the rule growing at the same time, or
	 * id by id, which fills one window to the end before opening the next and therefore leaves the windows opened
	 * first holding what they were given while the rest of the scenario runs. What is asserted is the same for both,
	 * an id having as many open problems as it has been sent values, so the order is free to be either.
	 *
	 * A fourth says whether they go out in one sender batch or one at a time (CEP_CLOSE_WINDOW_BATCH_FILL). Batched,
	 * step 1 hands the server everything at once and then waits for the state all of it must leave - every id with
	 * all of its problems open and no more open problems than the ids opened - which is what the per-value waits of
	 * the other mode add up to; one at a time, that state is waited for after every value instead, which costs a
	 * wait per value but pins a value that is not processed as it must be to that value.
	 *
	 * After the "up" of every id nothing is left open, and closing the last problem of a trigger is what puts the
	 * trigger itself back to OK, so no recovery value is needed.
	 *
	 * $discarded drives the flavours whose rule additionally discards the "down" values of one id
	 * (getCloseWindowDiscardService()). The "up" values still end the windows of the ids around it, so what
	 * changes is only what those windows have to give: the discarded id opens no problem in step 1, however many
	 * "down" values it is sent, and is left out of step 2, so nothing is ever sent for it that a window could hold
	 * or close. That it left nothing behind is asserted once every kept value has been processed - by then an event
	 * that had been stored would be there.
	 *
	 * $single_service drives the flavours whose rule keeps a single window instead of one per id - one ended by a
	 * pattern match and one by the arriving event (prepareDataCepWindowPatternCloseWindowSingleService() and
	 * prepareDataCepWindowSimpleCloseWindowSingleService()): one id is sent one "down" value per discovered trigger,
	 * LLD_DISCOVERY_COUNT of them, all of them the same value, and no other id is sent anything at all. The steps are
	 * the ones above with a single id in them - the depth of the one window is where the scenario goes instead of the
	 * number of them, so step 1 leaves that window holding a stack of problems all opened by the same value and step 2
	 * has its "up" value close every one of them in the single step of the window ending. Step 3 has nothing left to
	 * compare against, there being no other id whose window must stay open, so what it comes to is that nothing at all
	 * is open once that one window is gone.
	 *
	 * Those values do not come from one trigger either: every one of them is sent through a discovered trigger of its
	 * own while carrying the same id, which is what ties their number to the discovery. The window is therefore grouped
	 * together out of the events of every trigger the host has, and what it groups by is the 'service' tag alone - so
	 * the trigger behind an event may neither keep it out of the group nor split the group into one window per trigger,
	 * and when the window closes, the problems it closes are spread over that many triggers, each of which must return
	 * to OK once the last of its own is gone.
	 *
	 * $doubled drives the flavours whose rule was created twice (prepareDataCepWindowCloseWindowOperations()), which
	 * only the window types that are not exclusive can be: two rules then keep a window of the same events at once and
	 * both windows are closed by what ends them, so every problem is closed by two rules instead of one. Closing a
	 * problem that is already closed may not do anything, so what this asserts is the outcome of the single rule
	 * flavours unchanged - no problem closed twice over into something else, none left open, and neither rule
	 * reporting an error. That both rules really were processed for those events is read from the tags they add as an
	 * event occurs, one of its own per rule (CEP_TAG_WINDOW_FIRST and CEP_TAG_WINDOW_SECOND): every problem event of
	 * the run must carry both of them, so neither rule can have been left out of the events the other one closed.
	 */
	private function runEventAssessmentTestCepWindowCloseWindow(string $rule_name, bool $discarded = false,
			bool $single_service = false, bool $doubled = false): void {
		// The rules whose errors are reported when an assertion of the scenario fails: a doubled flavour has two, and
		// either of them failing is what would leave the problems never closing.
		$rule_names = $doubled ? [$rule_name, self::buildSecondRuleName($rule_name)] : [$rule_name];
		$services = static::getCloseWindowServices($single_service);

		// The discarding flavours drop the "down" values of this one id, so it opens no problem and gets no window.
		// It is left out of the "up" values as well: with nothing of its own stored, an "up" of that id would be
		// given a window of its own and closed with it, which says nothing about the event that was dropped.
		$discarded_service = $discarded ? static::getCloseWindowDiscardService() : null;

		// How many "down" values every id is sent, and therefore how many events the window of an id holds when its
		// "up" value ends it. The eviction flavours have room for exactly that many, so the "up" value is the one
		// that does not fit however deep the windows go, see getCloseWindowCapacity(). The single id flavours send one
		// value per discovered trigger instead, all of them going to the one id they drive.
		$events = static::getCloseWindowEventCount($single_service);

		// Which discovered triggers the values go through. The flavours that drive an id per window send everything
		// through a single trigger - what they vary is the id, and one trigger produces the events of every id alike.
		// The single id flavours vary the trigger instead: they drive one id, so every event of the run carries the
		// same 'service' tag whatever trigger it came from, and giving each value a trigger of its own is what leaves
		// that one window holding the events of every discovered trigger there is. That is also where their value
		// count comes from (getCloseWindowEventCount()), so the values and the triggers are the same number by
		// construction. The window groups by that tag alone, so the trigger an event came from must neither keep it
		// out of the group nor split the group into one window per trigger - and the problems the closing window then
		// closes belong to that many triggers instead of all to one, each of which has to return to OK once the last
		// of its own problems is gone.
		$discovered = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$keys = $single_service ? $discovered : [$discovered[0]];

		$triggerids = $this->getTriggeridsForKeys(self::HOST_DISC_VALUE, $keys);
		$all = array_values($triggerids);

		// Every trigger the values will go through must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the close window test of "'.$rule_name.'".');
		}

		$this->captureEventBaseline($all);

		// The value goes through the first of those triggers unless one of them is named, which is what the values
		// filling the windows do - the "up" values that end them are sent through it as one of the triggers whose
		// events the window it ends is holding.
		$send = fn(string $value, ?string $key = null) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key === null ? $keys[0] : $key, 'value' => $value]
		]);

		// 1. Every id takes the places its own window has for it, and a window that has seen no "up" event is not
		//    closed. In which order the values go out - one per id and round by round, or every value of an id in a
		//    row - is CEP_CLOSE_WINDOW_FILL_PER_SERVICE, see getCloseWindowFillOrder(); whether they go out
		//    together or one at a time is CEP_CLOSE_WINDOW_BATCH_FILL. Either way the state they leave behind is
		//    the same, so the same counts are waited for - all at once after the batch, or after every value. The
		//    discarded id takes no place anywhere - there is nothing to wait for after its values, and that nothing
		//    is what the assertions at the end are about.
		$order = static::getCloseWindowFillOrder($services, $events);
		$open = 0;

		if (static::CEP_CLOSE_WINDOW_BATCH_FILL) {
			$this->dispatchSenderValues(array_map(fn(array $step, int $index) => [
				'host' => self::HOST_DISC_VALUE,
				'key' => $keys[$index % count($keys)],
				'value' => 'down_'.$step[0]
			], $order, array_keys($order)));

			// Every id but the discarded one has all of its values open at once.
			$open = ($discarded_service === null ? count($services) : count($services) - 1) * $events;

			$this->waitForOpenProblemCount($all, $open);
			$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

			// The total alone would also be reached by windows that took more of one id and less of another, so
			// every id is asked for its own count as well: as many open problems as it was sent values, which is
			// what the window of that id held on to instead of replacing what it had.
			foreach ($services as $service) {
				if ($service !== $discarded_service) {
					$this->waitForOpenProblemCountByTag($all, 'service', $service, $events);
				}
			}
		}
		else {
			foreach ($order as $index => [$service, $count]) {
				$step_key = $keys[$index % count($keys)];

				$send('down_'.$service, $step_key);

				if ($service === $discarded_service) {
					continue;
				}

				$this->waitForOpenProblemCount($all, ++$open);

				// The trigger this value went through is the one that must be in PROBLEM by now: the flavours that
				// send everything through one trigger have no other, and the ones spreading their values over a
				// trigger each have not reached the later ones yet - the triggers already sent stay in PROBLEM
				// until the window that holds their problems is closed.
				$this->waitForParentsValue([$triggerids[$step_key]], TRIGGER_VALUE_TRUE);

				// One open problem per value the id has been sent: the window keeps the earlier ones as well, so
				// its problems accumulate instead of replacing each other.
				$this->waitForOpenProblemCountByTag($all, 'service', $service, $count);
			}
		}

		// Every window of the rule is full now, which is where what it keeps about itself besides the events is read: a
		// window may be asked to record how many events it has collected in a tag of the event that opened it, and the
		// flavours whose window type can be asked (the cause and symptom ones, the only ones the field is allowed for)
		// were - so the oldest event of every id has to carry the number of values that joined it, and no other event
		// of the id may carry that tag at all. The rule says whether it keeps such a count, so the flavours whose
		// window type does not are given nothing to look for.
		$counted_services = $discarded_service === null
			? $services
			: array_values(array_diff($services, [$discarded_service]));
		$event_count_tag = $this->getCepRuleEventCountTag($rule_name);

		if ($event_count_tag !== '') {
			// Which event of an id opened its window is only known when every event of the id went through the same
			// trigger, which is what the flavours driving an id per window do - the single id ones give each value a
			// trigger of its own, see $keys above.
			$this->waitForCepWindowEventCounts($all, $event_count_tag, $counted_services, $events, !$single_service);
		}

		// None of the windows has closed anything yet either, which is where the restarts stop and start the server:
		// the "up" values below then end windows the server loaded back from the database, and every problem those
		// windows have been holding since before the shutdown has to be closed with them just the same. The restart is
		// left out when the restarts are turned off, and the windows are given room for it when they are not - see
		// maybeRestartServerMidScenario() and getCloseWindowDuration().
		$this->maybeRestartServerMidScenario();

		// The restart closes nothing on its way down or up, so what is open going into step 2 is what step 1 left.
		$this->waitForOpenProblemCount($all, $open);

		// 2. The "up" of an id ends that id's window, and every problem of the id is closed with it. A pattern
		//    window whose script rejected what it was handed would throw instead of reporting a match, and the
		//    server keeps that message on the rule, so it is reported here rather than leaving the problems simply
		//    never closing.
		foreach ($services as $service) {
			if ($service === $discarded_service) {
				continue;
			}

			$send('up_'.$service);

			try {
				// Every "down" event of the id plus its "up" one, none of them open: the "up" event is a problem of
				// its own, because "up" is not a recovery value for this trigger - the rule is what closes them
				// all, and the ones the window held it closes in the single step of the window ending.
				$this->waitForProblemEventCountByTag($all, 'service', $service, $events + 1);
				$this->waitForOpenProblemCountByTag($all, 'service', $service, 0);
			}
			catch (Throwable $e) {
				foreach ($rule_names as $name) {
					$error = $this->getCepRuleError($name);

					$this->assertSame('', $error, 'The rule "'.$name.'" reported an error: '.$error);
				}

				throw $e;
			}

			// 3. Only that id was affected - the windows of the ids without an "up" value are still open, and so
			//    are the problems they hold. The id is down as many open problems as its window held, not one more:
			//    its "up" problem was opened and closed within this step, so what the count loses is the "down"
			//    problems that had been holding the places in its window.
			$open -= $events;

			$this->waitForOpenProblemCount($all, $open);
		}

		if ($discarded_service !== null) {
			// Every kept value has been processed by now, so a discarded "down" that had been stored would be here
			// too - and there is nothing of that id at all: not one problem event of its own however many values it
			// was sent, and therefore nothing its window could have held or been closed with. Only the ids that
			// were kept have "down" events, all of theirs.
			$this->waitForProblemEventCountByTag($all, 'service', $discarded_service, 0);
			$this->waitForProblemEventsTagged($all, self::CEP_STATE_TAG_DOWN, (count($services) - 1) * $events);
		}

		if ($doubled) {
			// Both rules of the flavour were processed for every event of the run, which is what their tags are for:
			// each rule adds one of its own as an event occurs, so an event carrying only one of the two would be an
			// event one of the rules never got its turn for - what a window type that is exclusive would have done to
			// the second rule of its kind. Every "down" value and every "up" value of the ids driven must therefore
			// carry both tags, and since either count reaching that total means every event has that tag, the two
			// counts together mean every event has both. The counts above have already shown that being closed by two
			// rules instead of one left the problems exactly as one rule leaves them.
			$tagged = ($discarded_service === null ? count($services) : count($services) - 1) * ($events + 1);

			$this->waitForProblemEventCountByTag($all, self::CEP_TAG_WINDOW_FIRST, self::CEP_TAG_WINDOW_FIRST_VALUE,
				$tagged
			);
			$this->waitForProblemEventCountByTag($all, self::CEP_TAG_WINDOW_SECOND, self::CEP_TAG_WINDOW_SECOND_VALUE,
				$tagged
			);
		}

		// The last window took the problems it held with it, which is what returns every trigger whose problems those
		// were to OK as well.
		$this->waitForNoOpenProblems($all, 'After the close window scenario of "'.$rule_name.'"');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
	}

	/**
	 * Drive the cause and symptom flavour whose window is ended by its duration: the one window type the duration
	 * closes instead of evicting what it holds (cep_window_causal_process()), so the rule of the scenario has no
	 * operation that could end anything - nothing but the "close" of a closing window, see
	 * prepareDataCepWindowCloseOnDurationOperations().
	 *
	 *   1. three values of one id, which is one window holding all three of their problems. Nothing is sent that
	 *      would end that window and no other rule may touch it, so the only thing left that can close those problems
	 *      is the duration;
	 *   2. and it does: once the period is up the window closes, the "close" operation of the closing window reaches
	 *      every event it was holding, and the problems are gone without a value of any kind having been sent for
	 *      them. Closing the last problem of a trigger is what puts the trigger itself back to OK, so the clock alone
	 *      brings both the problems and the trigger back - the trigger expression is still true, its last value being
	 *      a "down" one;
	 *   3. a fourth value, sent once the first window is gone. A closed window is not the end of the rule: the period
	 *      starts again, this value opens a window of the id anew, and the next duration to run out closes that one
	 *      with the problem it holds - so the closing is what the rule does once per period rather than once.
	 *
	 * Between step 1 and step 2 the server is stopped and started, unless the restarts are turned off: the window whose
	 * duration then runs out is the one the server loaded back from the database, and the problems it closes are the
	 * ones it came back holding - see maybeRestartServerMidScenario().
	 *
	 * What the problems are while a window still holds them is not asserted here, and cannot be: the periods of a
	 * cause and symptom window follow a grid the rule keeps for itself (cep_rule_get_window_start_time()), so a value
	 * may land anywhere in a period, even just before it ends, and a problem that is closed a moment after it opened is
	 * the scenario working rather than failing - as is a group that the end of a period split in two. What every step
	 * therefore waits for is the problem events existing - a count that only grows - and then nothing being open. That
	 * the events are held and ranked while they are in the window is asserted where the window is ended by a value
	 * instead, see runEventAssessmentTestCepWindowCauseSymptom().
	 */
	private function runEventAssessmentTestCepWindowCauseSymptomCloseOnDuration(string $rule_name): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the duration close test of "'.$rule_name.'".');
		}

		$this->captureEventBaseline($all);

		$service = self::CEP_RULE_WINDOW_NONE_SERVICE;
		$send = fn() => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'down_'.$service]
		]);

		// 1. Three values of the one id the scenario drives, so one window is given all three of their problems.
		$events = 0;

		for ($i = 0; $i < 3; $i++) {
			$send();
			$this->waitForProblemEventCountByTag($all, 'service', $service, ++$events);
		}

		// 1a. Stop and start the server with the window holding all three, unless the restarts are turned off: what
		//     closes them is then a window the server loaded back from the database, and it has to close the events it
		//     came back with. The period of such a window starts again at the startup - the time it was created is not
		//     among the columns of cep_window - so what the wait below waits out is one period from here either way.
		$this->maybeRestartServerMidScenario();

		// Both waits below are due at a time this scenario knows: a window is closed at most one period after it was
		// opened, so that period plus the little the closing takes to reach the API is all the patience they get - a
		// window that does not close is reported about when it was due to.
		$patience = self::CEP_RULE_WINDOW_CLOSE_DURATION_CAUSE_PERIOD + self::CEP_RULE_WINDOW_CLOSE_DURATION_SLACK;

		try {
			// 2. Nothing else is sent and nothing else may close them: the window closing when its period is up is
			//    what closes every problem it was holding, and the trigger returns to OK with the last of them.
			$this->waitForNoOpenProblems($all,
				'After the window of "'.$rule_name.'" was left to be closed by its duration', true, $patience
			);

			// 3. The rule closes a window every period, so the value after the first close is held by a new window of
			//    the same id and closed when that period is up in turn.
			$send();
			$this->waitForProblemEventCountByTag($all, 'service', $service, ++$events);
			$this->waitForNoOpenProblems($all,
				'After the second window of "'.$rule_name.'" was left to be closed by its duration', true, $patience
			);
		}
		catch (Throwable $e) {
			$error = $this->getCepRuleError($rule_name);

			$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error: '.$error);

			throw $e;
		}

		// The rule closed both windows by the clock without failing on any of it.
		$error = $this->getCepRuleError($rule_name);

		$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error: '.$error);
	}

	/**
	 * Drive a sliding flavour whose window is ended by its duration, the $rule_name rule of
	 * prepareDataCepWindowCloseOnDurationOperations(): the duration of a simple or a tag correlation window evicts the
	 * events that have been in it too long instead of closing it, so what ends the window here is the "close window"
	 * operation of an event evicted for its age - and unlike every close window flavour, no value of the run asks for
	 * it.
	 *
	 * Two values of one id go into the one window of that id, far enough apart (CEP_RULE_WINDOW_CLOSE_DURATION_GAP)
	 * that only the older one is old enough to be evicted when the window is examined:
	 *
	 *   1. the first value opens a problem the window holds. Nothing may close it - the duration is what it has to be
	 *      closed by, and it has not run out yet, which the wait for both problems below shows;
	 *   2. the second value, sent while the first is still well inside the duration, is held by the same window, so
	 *      two problems are open and the window has the older event first;
	 *   3. the duration of the older event runs out and it is evicted, which the rule turns into the end of the
	 *      window. The window closes with the younger event still in it, so that one is closed by the "close"
	 *      operation of the closing window - while the evicted event is not: it was out of the window before it
	 *      closed, and the rule has no operation for an evicted event other than ending the window. Exactly one
	 *      problem is therefore left open and it is the one of the first value, asserted by eventid;
	 *   4. nothing of the rule can reach that problem any more - the window that held it is gone - so the trigger
	 *      expression has to close it, which is what the recovery value at the end is for.
	 *
	 * The server is stopped and started between step 1 and step 2, unless the restarts are turned off: the window that
	 * ages its oldest event out and ends over it is then one the server loaded back from the database, and that event
	 * came back with it. The period covers the stop and start (getCloseOnDurationPeriod()), so the eviction still
	 * happens for the age of the event rather than because the server was away - see maybeRestartServerMidScenario().
	 */
	private function runEventAssessmentTestCepWindowCloseOnDuration(string $rule_name): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the duration close test of "'.$rule_name.'".');
		}

		$this->captureEventBaseline($all);

		$service = self::CEP_RULE_WINDOW_NONE_SERVICE;
		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		// 1. The window of the id is empty, so this event takes a place in it and its problem stays open.
		$send('down_'.$service);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$first = $this->getOpenProblemEventids($all);

		$this->assertCount(1, $first, 'Expected the one problem the window of "'.$rule_name.'" holds, got: '
			.implode(', ', $first)
		);

		// 1a. Stop and start the server with that one event in the window, unless the restarts are turned off: the
		//     window that ages it out and ends over it is then the one the server loaded back from the database, and
		//     the event whose age ends it is one that came back with it. The period of the window covers the stop and
		//     start (getCloseOnDurationPeriod()), so what ends it is still the age of that event and not the restart.
		$this->maybeRestartServerMidScenario();

		$this->waitForOpenProblemCount($all, 1);

		// 2. The second value goes out once the first event has aged by this much and no more: both are inside the
		//    duration of the window, so both problems are open, but only the first one is close to running out of it.
		sleep(self::CEP_RULE_WINDOW_CLOSE_DURATION_GAP);

		$send('down_'.$service);
		$this->waitForOpenProblemCount($all, 2);

		try {
			// 3. The duration of the first event runs out, it is evicted, and the rule ends the window over it. What
			//    the closing window still holds is the second event, so that is the problem the "close" operation of
			//    the closing window closes - the evicted one had left the window before it closed and nothing closes
			//    that. This wait is due at a time the scenario knows: the first event ages out one period after it
			//    was sent, and the wait started within that period, so the period and the little the closing takes
			//    to reach the API is all the patience it gets.
			$this->waitForOpenProblemCount($all, 1, '', static::getCloseOnDurationPeriod()
				+ self::CEP_RULE_WINDOW_CLOSE_DURATION_SLACK
			);

			$left = $this->getOpenProblemEventids($all);

			$this->assertSame($first, $left, 'The problem left open by "'.$rule_name.'" is not the one its window '
				.'evicted to end itself: expected '.implode(', ', $first).', got '.implode(', ', $left).'.'
			);
		}
		catch (Throwable $e) {
			$error = $this->getCepRuleError($rule_name);

			$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error: '.$error);

			throw $e;
		}

		// 4. The window that could have closed the evicted problem is gone with the events it held, so the trigger
		//    expression is what closes it.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the duration close scenario of "'.$rule_name.'"');

		$error = $this->getCepRuleError($rule_name);

		$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error: '.$error);
	}

	/**
	 * Drive the reset scenario of the $rule_name rule, whose windows hold the problems of the ids that opened them
	 * and close them once that id recovers, see prepareDataCepWindowHeldProblemsOperations(). The rule is reset
	 * while its windows are full, and what that must do is asserted from the outside, by what the rule can and
	 * cannot close afterwards:
	 *
	 *   1. every id gets a window of its own and no window expires, so the three "down" values leave three open
	 *      problems, one held by each window. Their eventids are what the reset is measured on;
	 *   2. the rule is reset. A reset window is thrown away rather than closed - the operations of a closing window
	 *      are not performed for the events it held - so nothing of the three problems changes;
	 *   3. a second "down" value per id, to show that a reset rule is an empty one and not a broken one: every id
	 *      gets a new window and the problem of its second "down" is held by it, so six problems are open;
	 *   4. the "up" value of an id ends the window of that id and everything that window holds is closed with it.
	 *      The window it ends is the one opened after the reset, holding the second "down" event and the "up" event
	 *      itself - a problem of its own, since "up" is not a recovery value for this trigger - and both of those
	 *      are closed. What is not closed is the problem of the first "down": the window that had been holding it is
	 *      gone, so the recovery of its id no longer reaches it. Three problems are therefore left open and they are
	 *      the very three of step 1, asserted by eventid. Had the reset left those windows in place, the "up" values
	 *      would have closed the first "down" problems as well and nothing would be left;
	 *   5. only the trigger expression can close what is left - the recovery value closes all three and returns the
	 *      trigger to OK, and the rule that was reset reports no error through any of this.
	 *
	 * The scenario is the same for every window type: what a window holds is not what tells the types apart, so a
	 * reset must take it away from all of them alike.
	 *
	 * Between step 2 and step 3 the server is stopped and started, unless the restarts are turned off - and here what
	 * has to come back is nothing: a window and the events in it are rows of cep_window and cep_window_event that the
	 * server loads back at startup, so a reset that had emptied the window pool without emptying those tables would be
	 * undone by the restart, and step 4 would find the first "down" problems in a window again. That is exactly what
	 * the eventids of step 1 catch. The rest of the scenario is unchanged either way, so passing it with the restarts
	 * on also means the rule went on working across a stop and start: it opened its windows again and closed what they
	 * held - see maybeRestartServerMidScenario().
	 */
	private function runEventAssessmentTestCepWindowReset(string $rule_name): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the reset test of "'.$rule_name.'".');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$services = [self::CEP_RULE_WINDOW_NONE_SERVICE, self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT,
			self::CEP_RULE_WINDOW_NONE_SERVICE_LAST
		];

		// 1. Every id takes the place its own window has for it, and a window that has seen no "up" event is not
		//    closed, so every problem stays open.
		$open = 0;

		foreach ($services as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$open);
			$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
			$this->waitForOpenProblemCountByTag($all, 'service', $service, 1);
		}

		// The problems the windows are holding when the reset arrives. They are what the reset is measured on: no
		// operation of the rule may reach them afterwards, so these exact problems have to be the ones still open
		// once the second round of values has closed everything the rule can still close.
		$held = $this->getOpenProblemEventids($all);

		$this->assertCount(count($services), $held,
			'Expected one held problem per id before resetting "'.$rule_name.'", got: '.implode(', ', $held)
		);

		// 2. Reset the rule while all three windows are holding a problem. The request only queues the reset, so
		//    the queue is waited out before anything is sent that its outcome is read from.
		$this->resetCepRule($rule_name);
		$this->waitForCepTasksDrained();

		// 2a. Stop and start the server with the reset behind it: the windows of a rule are stored, and the server
		//     loads them back with the events they held, so a reset that took them out of the window pool without
		//     taking them out of the database is a reset the restart undoes. Everything the reset is read from
		//     happens after this point, so an undone reset shows up exactly as no reset at all would - in step 4.
		$this->maybeRestartServerMidScenario();

		// A window is discarded by a reset, not closed: the "close" operation of a closing window is not performed
		// for the events it held, so the problems are exactly as they were - and neither stopping nor starting the
		// server closes anything either, whether a window came back holding them or nothing came back at all.
		$this->waitForOpenProblemCount($all, $open);

		// 3. A second "down" value per id. A reset rule is an empty one, not a broken one, so every id gets a new
		//    window and the problem of its second "down" is the one that window holds.
		foreach ($services as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$open);
			$this->waitForOpenProblemCountByTag($all, 'service', $service, 2);
		}

		try {
			// 4. The "up" value of an id ends the window of that id and everything that window holds is closed with
			//    it. That window is the one opened after the reset: it holds the second "down" event and the "up"
			//    event itself - a problem of its own, because "up" is not a recovery value for this trigger - so
			//    the id is left with the three problem events it was sent and only the first "down" still open. Had
			//    the reset left the first window in place, that problem would have been closed with it too.
			foreach ($services as $service) {
				$send('up_'.$service);
				$this->waitForProblemEventCountByTag($all, 'service', $service, 3);
				$this->waitForOpenProblemCountByTag($all, 'service', $service, 1);
			}

			// One problem per id survived, and it is the very problem its window had been holding when the rule was
			// reset - the reset is what put it out of reach of the recovery that closed everything else of that id.
			$this->waitForOpenProblemCount($all, count($services));

			$left = $this->getOpenProblemEventids($all);

			$this->assertSame($held, $left, 'The problems left open by "'.$rule_name.'" are not the ones its '
				.'windows were holding when it was reset: expected '.implode(', ', $held).', got '
				.implode(', ', $left).'.'
			);
		}
		catch (Throwable $e) {
			$error = $this->getCepRuleError($rule_name);

			$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error: '.$error);

			throw $e;
		}

		// 5. Nothing of the rule can close them any more, so the trigger expression has to.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the window reset scenario of "'.$rule_name.'"');

		// The rule that was reset is still there and still working - a reset empties a rule, it does not break it.
		$error = $this->getCepRuleError($rule_name);

		$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error: '.$error);
	}

	/**
	 * Drive the delete scenario of the $rule_name rule, the same rule the reset scenario drives - its windows hold
	 * the problems of the ids that opened them and close them once that id recovers, see
	 * prepareDataCepWindowHeldProblemsOperations(). This time the rule is deleted while its windows are full instead
	 * of being reset, so it is taken away with them:
	 *
	 *   1. every id gets a window of its own and no window expires, so the three "down" values leave three open
	 *      problems, one held by each window. Their eventids are what the delete is measured on;
	 *   2. the rule is deleted. The windows of a deleted rule are thrown away rather than closed - the operations of
	 *      a closing window are not performed for the events it held, and a deleted rule has no operations left to
	 *      perform at all - so nothing of the three problems changes;
	 *   3. a second "down" value per id. This is where a delete parts with a reset: a reset rule opens a window
	 *      again, a deleted one is not there to assess anything, so the problems of these values are held by nothing
	 *      and six problems are open;
	 *   4. the "up" value of an id, the value that had been ending the window of that id and closing everything it
	 *      held. There is no rule to act on it, so it closes nothing and is left open as a problem of its own -
	 *      "up" is not a recovery value for this trigger. All nine problems the trigger was given are therefore
	 *      open, and among them, asserted by eventid, are the very three of step 1: the windows that had been
	 *      holding them went away with the rule instead of closing them;
	 *   5. only the trigger expression can close any of them - the recovery value closes all nine and returns the
	 *      trigger to OK.
	 *
	 * The scenario is the same for every window type: what a window holds is not what tells the types apart, so
	 * deleting a rule must take it away from all of them alike.
	 *
	 * Between step 2 and step 3 the server is stopped and started, unless the restarts are turned off, and this is
	 * where "thrown away" becomes "and stay away": the windows of a rule are rows the server loads back at startup, so
	 * one that was only taken out of the window pool would be there again afterwards - holding the problems of step 1,
	 * with no rule left to belong to - and step 4 would find them closed after all. See
	 * maybeRestartServerMidScenario().
	 */
	private function runEventAssessmentTestCepWindowDelete(string $rule_name): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the delete test of "'.$rule_name.'".');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$services = [self::CEP_RULE_WINDOW_NONE_SERVICE, self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT,
			self::CEP_RULE_WINDOW_NONE_SERVICE_LAST
		];

		// 1. Every id takes the place its own window has for it, and a window that has seen no "up" event is not
		//    closed, so every problem stays open.
		$open = 0;

		foreach ($services as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$open);
			$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
			$this->waitForOpenProblemCountByTag($all, 'service', $service, 1);
		}

		// The problems the windows are holding when the rule is deleted. They are what the delete is measured on:
		// these exact problems have to be among the ones still open once the rule is gone and the values that used
		// to close them have been sent.
		$held = $this->getOpenProblemEventids($all);

		$this->assertCount(count($services), $held,
			'Expected one held problem per id before deleting "'.$rule_name.'", got: '.implode(', ', $held)
		);

		// The rule held those problems without failing on any of them, so whatever the delete leads to is the
		// delete and not a rule that had already stopped working.
		$error = $this->getCepRuleError($rule_name);

		$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error: '.$error);

		// 2. Delete the rule while all three windows are holding a problem, and wait for the server to pick up the
		//    configuration the rule is no longer in - the rule is gone from the database right away, but it is the
		//    configuration cache the event assessment reads it from.
		$this->deleteCepRule($rule_name);
		$this->reloadConfigurationCacheAndWaitForLogLine();
		$this->waitForCepTasksDrained();

		// 2a. Stop and start the server with the delete behind it, unless the restarts are turned off. The windows of
		//     a rule are rows of cep_window and cep_window_event that the server loads back at startup, so this is
		//     what "and stay away" comes to: a window that was only taken out of the window pool would be there again
		//     afterwards - holding the problems of step 1 - and there is no rule left for it to belong to.
		$this->maybeRestartServerMidScenario();

		// The windows went away with the rule rather than being closed: the "close" operation of a closing window is
		// not performed for the events they held, so the problems are exactly as they were - and nothing about
		// stopping and starting the server closes a problem either.
		$this->waitForOpenProblemCount($all, $open);

		// 3. A second "down" value per id. There is no rule left to assess them, so no window takes them and their
		//    problems are held by nothing.
		foreach ($services as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$open);
			$this->waitForOpenProblemCountByTag($all, 'service', $service, 2);
		}

		// 4. The "up" value of an id, the one value the deleted rule used to close everything of that id on. Nothing
		//    acts on it any more, so all three problem events of the id are open - the two "down" ones and the "up"
		//    one, which is a problem of its own because "up" is not a recovery value for this trigger.
		foreach ($services as $service) {
			$send('up_'.$service);
			$this->waitForProblemEventCountByTag($all, 'service', $service, 3);
			$this->waitForOpenProblemCountByTag($all, 'service', $service, 3);
		}

		$this->waitForOpenProblemCount($all, 3 * count($services));

		// Nothing was closed, so the problems the windows had been holding when the rule was deleted are still among
		// the open ones - had the delete closed those windows instead of discarding them, they would be gone.
		$left = $this->getOpenProblemEventids($all);

		$this->assertSame($held, array_values(array_intersect($left, $held)),
			'The problems the windows of "'.$rule_name.'" were holding when it was deleted are no longer open: '
				.'expected '.implode(', ', $held).' among '.implode(', ', $left).'.'
		);

		// 5. There is no rule to close any of them, so the trigger expression has to.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the window delete scenario of "'.$rule_name.'"');
	}

	/**
	 * Drive the delete scenario of the $rule_name rule against a window that is being examined while the delete
	 * happens: its script sleeps CEP_RULE_WINDOW_SLEEP_SCRIPT_MS on every examination, so it is still running - with
	 * the window and the events it holds in its hands - for seconds after the rule it belongs to is gone, see
	 * prepareDataCepWindowPatternDeleteSleep(). Two events are enough for that, so this drives a single id:
	 *
	 *   1. one "down" value. Its problem is held by the window of its id, and the window is handed to the script once
	 *      a second, every examination holding it for the whole sleep;
	 *   2. a second (CEP_RULE_WINDOW_SLEEP_DELETE_DELAY) is waited out, which puts the delete inside a script rather
	 *      than between two of them, and the rule is deleted with the script of its window still running. The window
	 *      is left behind by the rule it belonged to, so the script that returns to it has to end it and let go of
	 *      the event it held;
	 *   3. nothing of the rule reaches the problem the window was holding - it is left open, exactly as it is when
	 *      the rule is deleted between two examinations (runEventAssessmentTestCepWindowDelete());
	 *   4. the "up" value that used to end the window of the id and close everything it held. There is no rule to act
	 *      on it, so it closes nothing and is left open as a problem of its own - and it shows the CEP service kept
	 *      working through all this: the event is processed, so the worker that had been sleeping in a script of a
	 *      deleted rule came back for it;
	 *   5. only the trigger expression can close the two problems, and once it has, nothing of the CEP service may be
	 *      holding their events any more - which is what says the window cleaned itself up. Its events are the ones
	 *      it holds handles of, so a window that outlived its rule without ever being discarded would keep them in
	 *      the event cache and waitForNoOpenProblems() would never see it empty.
	 */
	private function runEventAssessmentTestCepWindowDeleteDuringScript(string $rule_name): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the delete during script test of "'.$rule_name.'".');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$service = self::CEP_RULE_WINDOW_NONE_SERVICE;

		// 1. The one "down" value of the scenario. Its problem being open means the event was assessed and is in the
		//    window of its id, which is what the script of the rule is handed from then on.
		$send('down_'.$service);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$held = $this->getOpenProblemEventids($all);

		$this->assertCount(1, $held,
			'Expected the one held problem before deleting "'.$rule_name.'", got: '.implode(', ', $held)
		);

		// A sleeping script is a working script: the rule must not have failed on it, or the sleep this scenario is
		// built on never happened.
		$error = $this->getCepRuleError($rule_name);

		$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error: '.$error);

		// 2. Delete the rule from under the script that is examining its window.
		sleep(self::CEP_RULE_WINDOW_SLEEP_DELETE_DELAY);

		$this->deleteCepRule($rule_name);
		$this->reloadConfigurationCacheAndWaitForLogLine();
		$this->waitForCepTasksDrained();

		// 3. The window went away with the rule instead of being closed, whether the script was in the middle of it
		//    or not: the problem it was holding is untouched.
		$this->waitForOpenProblemCount($all, 1);

		// 4. The value that used to end the window of the id and close everything it held, sent while the script of
		//    that window may still be running. Nothing acts on it any more, so both problem events of the id are
		//    open - the "down" one the window had been holding and the "up" one, a problem of its own because "up" is
		//    not a recovery value for this trigger.
		$cep_processed = $this->getCepStat('events', 'processed');

		$send('up_'.$service);
		$this->waitForProblemEventCountByTag($all, 'service', $service, 2);
		$this->waitForOpenProblemCountByTag($all, 'service', $service, 2);

		// The event was taken in by the CEP service, so it is still assessing events after a script of a deleted rule
		// ran on without one.
		$this->assertCepStatIncreasedBy('events', 'processed', $cep_processed, 1);

		$left = $this->getOpenProblemEventids($all);

		$this->assertSame($held, array_values(array_intersect($left, $held)),
			'The problem the window of "'.$rule_name.'" was holding when it was deleted mid script is no longer '
				.'open: expected '.implode(', ', $held).' among '.implode(', ', $left).'.'
		);

		// 5. Only the trigger expression can close them, and with them closed the CEP event cache must be empty -
		//    the window that was being examined when its rule went away has to have let go of the event it held.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the sleeping script delete scenario of "'.$rule_name.'"');
	}

	/**
	 * Drive the unresolved limits scenario of the $rule_name rule, whose window is given both of its limits as user
	 * macros that do not exist, see prepareDataCepWindowUnresolvedLimitsOperations(). The rule is the one of the
	 * reset and delete scenarios - the "up" value of an id ends the window of that id and closes every problem the
	 * window held - so what a window would have done is known, and what the scenario reads is a rule that cannot
	 * open one. Each of the three ids it drives is sent through a different state of the macros:
	 *
	 *   1. neither macro exists. The "down" value of the first id opens its problem, and no window is opened for it:
	 *      the rule reports an error, an unknown user macro being replaced by nothing at all and neither limit being
	 *      readable out of what is left. The "up" value of that id, the value that ends a window and closes
	 *      everything in it, then reaches no window and closes nothing: both problems of the id are open, and the
	 *      second of them shows what the missing window costs rather than only that the rule complained;
	 *   2. the duration macro is created, so the capacity is the limit that is left unresolvable. The "down" value of
	 *      the second id gets the same treatment - no window, nothing closed by its "up" value, and the rule still
	 *      reporting - which is what says both limits are resolved and checked rather than the duration alone;
	 *   3. the capacity macro is created as well. The rule itself was never touched through any of this, so the
	 *      "down" value of the third id is what shows it is not broken but was only ever missing its limits: a window
	 *      is opened, the error the rule had been reporting is cleared, and the "up" value of that id ends the window
	 *      and closes both problems of the id exactly as it does for a rule whose limits were there from the start;
	 *   4. what the two broken phases left is untouched by that recovery. Their events were never in a window, so a
	 *      working rule has nothing of theirs to close and the trigger expression is what has to - four problems, the
	 *      two of every id that was driven without a window.
	 *
	 * The scenario is the same for every window type: limits are not what tells the types apart, so none of them may
	 * open a window without them, and each of them must be able to open one the moment they resolve. Walking the
	 * limits in the order the server parses them is what it cannot say anything about - the capacity is only ever
	 * reached through a duration that resolves, so neither limit is seen failing by itself here - and that is what
	 * runEventAssessmentTestCepWindowSingleUnresolvedLimit() drives, one limit at a time, over the pattern match
	 * window type.
	 */
	private function runEventAssessmentTestCepWindowUnresolvedLimits(string $rule_name): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the unresolved limits test of "'.$rule_name.'".');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		// One id per state of the macros: neither of them created, only the duration created, both of them created.
		$unresolved_service = self::CEP_RULE_WINDOW_NONE_SERVICE;
		$capacity_service = self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT;
		$resolved_service = self::CEP_RULE_WINDOW_NONE_SERVICE_LAST;

		// A rule that has not been given an event yet has nothing to report: whatever it says later is the doing of
		// the values below and not of something it was created with.
		$error = $this->getCepRuleError($rule_name);

		$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error before any event: '.$error);

		// 1. Neither limit resolves. The problem of the "down" value is opened by the trigger as always - the rule
		//    does not stand between an event and its problem - and the rule reports the limit it could not parse.
		$send('down_'.$unresolved_service);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
		$this->waitForCepRuleError($rule_name);

		// The "up" value of that id, the one that ends the window of its id and closes every problem the window
		// held. There is no window for it to end, so it closes nothing and is left open as a problem of its own -
		// "up" is not a recovery value for this trigger.
		$send('up_'.$unresolved_service);
		$this->waitForProblemEventCountByTag($all, 'service', $unresolved_service, 2);
		$this->waitForOpenProblemCountByTag($all, 'service', $unresolved_service, 2);
		$this->waitForOpenProblemCount($all, 2);

		// 2. The duration macro is created, which leaves the capacity as the only limit that does not resolve.
		$this->upsertGlobalMacro(self::CEP_WINDOW_DURATION_MACRO, self::CEP_RULE_WINDOW_LIMITS_DURATION);
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// A window needs both of its limits, so the second id fares exactly as the first one did - only the message
		// the rule reports moves on to the limit that is now the unresolvable one.
		$send('down_'.$capacity_service);
		$this->waitForOpenProblemCount($all, 3);
		$this->waitForCepRuleError($rule_name);

		$send('up_'.$capacity_service);
		$this->waitForProblemEventCountByTag($all, 'service', $capacity_service, 2);
		$this->waitForOpenProblemCountByTag($all, 'service', $capacity_service, 2);
		$this->waitForOpenProblemCount($all, 4);

		// 3. Both macros exist now, and nothing about the rule was changed to get there.
		$this->upsertGlobalMacro(self::CEP_WINDOW_CAPACITY_MACRO, (string) self::CEP_RULE_WINDOW_LIMITS_CAPACITY);
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// The "down" value of the third id is the first one the rule can open a window for, and opening it is what
		// takes the error away: a rule that had been failing on every event reports none again without having been
		// touched itself.
		$send('down_'.$resolved_service);
		$this->waitForOpenProblemCount($all, 5);
		$this->waitForCepRuleNoError($rule_name);

		// And the window it opened is a window like any other: the "up" value of the id ends it and both problems of
		// that id are closed with it - the "down" one the window held and the "up" one that ended it.
		$send('up_'.$resolved_service);
		$this->waitForProblemEventCountByTag($all, 'service', $resolved_service, 2);
		$this->waitForOpenProblemCountByTag($all, 'service', $resolved_service, 0);

		// 4. Only the id that had a window was affected. The events of the two ids sent while the limits did not
		//    resolve were never in one, so there is nothing of theirs for the working rule to close - all four of
		//    their problems are still open.
		$this->waitForOpenProblemCount($all, 4);
		$this->waitForOpenProblemCountByTag($all, 'service', $unresolved_service, 2);
		$this->waitForOpenProblemCountByTag($all, 'service', $capacity_service, 2);

		// The rule closed what it could without failing on any of it, so nothing of the recovery was a rule that had
		// started reporting something else.
		$error = $this->getCepRuleError($rule_name);

		$this->assertSame('', $error, 'The rule "'.$rule_name.'" reported an error once its limits resolved: '
			.$error
		);

		// Nothing of the rule can reach the four problems left, so the trigger expression has to.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the unresolved limits scenario of "'.$rule_name.'"');
	}

	/**
	 * Drive the single unresolved limit scenario of the $rule_name rule: its window is given both of its limits as
	 * user macros and only $macro is missing, the other limit having been created before the rule was
	 * (prepareDataCepWindowUnresolvedLimitsOperations()), so $error - the message of the limit $macro stands for -
	 * is the one thing the rule can fail with, and creating $macro with $value is the one thing that can take that
	 * failure away.
	 *
	 * That split is what this scenario adds to runEventAssessmentTestCepWindowUnresolvedLimits(), which leaves both
	 * macros uncreated and therefore walks the limits in the order the server parses them: the duration is what a
	 * window with neither macro reports, and the capacity is only ever reached through a duration that already
	 * resolves. Neither limit is seen failing on its own there. Here whichever limit is under test is the first one
	 * the server cannot make sense of, so what the rule reports is unmistakably its message and not the message of
	 * a limit that happens to be parsed earlier - and the recovery is the creation of that one macro rather than of
	 * the last one still missing.
	 *
	 * The window type driven is the pattern match one, the type that is examined on its own: its window is looked
	 * at once a second whether or not an event arrives, so the limits are resolved again on every examination and
	 * there is no script decision anywhere near what the rule reports - the script of the rule is never handed a
	 * window it does not have.
	 *
	 * Two ids are enough. The first is driven while $macro is missing: its "down" value opens a problem and no
	 * window, and its "up" value - the value that ends the window of an id and closes every problem the window held
	 * - reaches no window, so it closes nothing and both problems of that id stay open. Then $macro is created and
	 * the second id is driven through a rule nothing else was done to: a window is opened for its "down" value, the
	 * error is cleared, and its "up" value ends that window and closes both of its problems. What the first id left
	 * was never in a window, so the recovered rule has nothing of its to close and the trigger expression is what
	 * has to.
	 */
	private function runEventAssessmentTestCepWindowSingleUnresolvedLimit(string $rule_name, string $macro,
			string $value): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the single unresolved limit test of "'.$rule_name.'".');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $item_value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value]
		]);

		// One id driven while the limit under test does not resolve, one driven once it does.
		$unresolved_service = self::CEP_RULE_WINDOW_NONE_SERVICE;
		$resolved_service = self::CEP_RULE_WINDOW_NONE_SERVICE_LAST;

		// A rule that has not been given an event yet has nothing to report: whatever it says later is the doing of
		// the values below and not of something it was created with.
		$reported = $this->getCepRuleError($rule_name);

		$this->assertSame('', $reported, 'The rule "'.$rule_name.'" reported an error before any event: '.$reported);

		// 1. The limit under test does not resolve. The problem of the "down" value is opened by the trigger as
		//    always - the rule does not stand between an event and its problem - and the rule reports a failure it
		//    can only have from that limit: the one parsed before it, if there is one, resolves.
		$send('down_'.$unresolved_service);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
		$this->waitForCepRuleError($rule_name);

		// The "up" value of that id, the one that ends the window of its id and closes every problem the window
		// held. There is no window for it to end, so it closes nothing and is left open as a problem of its own -
		// "up" is not a recovery value for this trigger.
		$send('up_'.$unresolved_service);
		$this->waitForProblemEventCountByTag($all, 'service', $unresolved_service, 2);
		$this->waitForOpenProblemCountByTag($all, 'service', $unresolved_service, 2);
		$this->waitForOpenProblemCount($all, 2);

		// 2. The one macro that was missing is created, so both limits resolve. Nothing about the rule was changed
		//    to get there.
		$this->upsertGlobalMacro($macro, $value);
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// The "down" value of the second id is the first one the rule can open a window for, and opening it is what
		// takes the error away: a rule that had been failing on every event reports none again without having been
		// touched itself.
		$send('down_'.$resolved_service);
		$this->waitForOpenProblemCount($all, 3);
		$this->waitForCepRuleNoError($rule_name);

		// And the window it opened is a window like any other: the "up" value of the id ends it and both problems of
		// that id are closed with it - the "down" one the window held and the "up" one that ended it.
		$send('up_'.$resolved_service);
		$this->waitForProblemEventCountByTag($all, 'service', $resolved_service, 2);
		$this->waitForOpenProblemCountByTag($all, 'service', $resolved_service, 0);

		// 3. Only the id that had a window was affected. The events sent while the limit did not resolve were never
		//    in one, so there is nothing of theirs for the working rule to close - both problems are still open.
		$this->waitForOpenProblemCount($all, 2);
		$this->waitForOpenProblemCountByTag($all, 'service', $unresolved_service, 2);

		// The rule closed what it could without failing on any of it, so nothing of the recovery was a rule that had
		// started reporting something else.
		$reported = $this->getCepRuleError($rule_name);

		$this->assertSame('', $reported, 'The rule "'.$rule_name.'" reported an error once its limit resolved: '
			.$reported
		);

		// Nothing of the rule can reach the two problems left, so the trigger expression has to.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the single unresolved limit scenario of "'.$rule_name.'"');
	}

	/**
	 * Drive the cause and symptom grouping flavour: every value goes into the same group, so the problem of
	 * the first one becomes the cause and each of the following ones becomes a symptom of it as it arrives.
	 * Nothing closes anything while the "down" values are being sent, so all three problems stay open, ranked
	 * but otherwise untouched, and the ranking is re-checked after every value - a symptom is expected to point
	 * at the cause the moment its problem exists, not only at the end.
	 *
	 * The scenario then recovers the way the other windowed flavours do: the "up" value closes the window it
	 * enters and the window closes every event it held, which is every problem the scenario opened.
	 *
	 * The server is stopped and started once the first value has opened the window and become the cause of the group,
	 * unless the restarts are turned off: the two values after it are then ranked by a window the server loaded back
	 * from the database, and they have to become symptoms of that very cause with the count in its tag carrying on -
	 * a window that came back knowing nothing of its group would start one of its own, which the re-check after every
	 * value catches - see maybeRestartServerMidScenario().
	 */
	private function runEventAssessmentTestCepWindowCauseSymptom(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the cause and symptom test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$open = 0;

		foreach ([self::CEP_RULE_WINDOW_NONE_SERVICE, self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT,
				self::CEP_RULE_WINDOW_NONE_SERVICE_LAST] as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$open);
			$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

			// The first value only opens the cause; every one after it adds a symptom to it.
			$this->waitForCepCauseSymptomEvents($triggerid, $open - 1);

			if ($open == 1) {
				// Stop and start the server with nothing in the window but the cause of the group, unless the
				// restarts are turned off: everything ranked after this is ranked by a window the server loaded back
				// from the database, so it has to have come back knowing which event it had made the cause - the
				// values below must join that group rather than start one of their own, and the count of them the
				// cause carries in its tag must go on where it left off. The re-check right here is what says the
				// restart itself ranked and closed nothing.
				$this->maybeRestartServerMidScenario();

				$this->waitForOpenProblemCount($all, $open);
				$this->waitForCepCauseSymptomEvents($triggerid, 0);
			}
		}

		// The "up" value joins the very same window as one more event and closes it, and the closing window
		// closes every event it held: the ranked problems above and the "up" problem itself. Nothing else can
		// touch them, so the rule alone is what brings the trigger back to OK - the trigger expression recovers
		// nothing here, "up" being a problem value like any other.
		$send('up_'.self::CEP_RULE_WINDOW_NONE_SERVICE);
		$this->waitForNoOpenProblems($all, 'After the cause and symptom close on up value');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
	}

	/**
	 * Wait until the events generated since the scenario baseline are ranked as one cause with $symptom_count
	 * symptoms: the oldest of them must be the cause - no cause of its own - every other one must point at it
	 * through its cause_eventid, and the cause must carry the CEP_TAG_SYMPTOM_COUNT tag stating how many
	 * events the group has collected beside it.
	 *
	 * No event may carry the tag of the second rule: a cause and symptom window is exclusive, so that rule
	 * never gets its turn.
	 */
	private function waitForCepCauseSymptomEvents(int $triggerid, int $symptom_count): void {
		$this->callUntilDataIsPresent('event.get', [
			'objectids' => [$triggerid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'output' => ['eventid', 'name', 'cause_eventid'],
			'selectTags' => 'extend',
			'sortfield' => 'eventid',
			'sortorder' => 'ASC'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($symptom_count) {
			if (count($response['result']) !== $symptom_count + 1) {
				return 'expected '.($symptom_count + 1).' problem event(s), got '.count($response['result']);
			}

			$cause = $response['result'][0];
			$tags = array_column($cause['tags'], 'value', 'tag');
			$info = 'cause event '.$cause['eventid'].' ('.$cause['name'].')';

			if ((int) $cause['cause_eventid'] !== 0) {
				return $info.': has cause '.$cause['cause_eventid'].', the oldest event of the group is'
					.' the cause and must have none';
			}

			// The tag is only written once the group has more than the cause in it.
			if ($symptom_count != 0) {
				if (!array_key_exists(self::CEP_TAG_SYMPTOM_COUNT, $tags)) {
					return $info.': missing "'.self::CEP_TAG_SYMPTOM_COUNT.'" tag';
				}

				if ((int) $tags[self::CEP_TAG_SYMPTOM_COUNT] !== $symptom_count) {
					return $info.': "'.self::CEP_TAG_SYMPTOM_COUNT.'" tag value "'
						.$tags[self::CEP_TAG_SYMPTOM_COUNT].'", expected '.$symptom_count;
				}
			}

			foreach ($response['result'] as $event) {
				$event_tags = array_column($event['tags'], 'value', 'tag');

				if (array_key_exists(self::CEP_TAG_WINDOW_SECOND, $event_tags)) {
					return 'event '.$event['eventid'].': unexpected "'.self::CEP_TAG_WINDOW_SECOND
						.'" tag, the second rule of an exclusive window type must not be processed';
				}

				if ($event['eventid'] === $cause['eventid']) {
					continue;
				}

				if ($event['cause_eventid'] !== $cause['eventid']) {
					return 'event '.$event['eventid'].' ('.$event['name'].'): cause '
						.$event['cause_eventid'].', expected the cause of the group '.$cause['eventid'];
				}
			}

			return true;
		});
	}

	/**
	 * Wait until every window the cause and symptom flavour of the close-on-up scenario opened has ranked the
	 * pair of events it held, see prepareDataCepWindowCauseSymptomCloseOnUp().
	 *
	 * The window groups by the 'service' tag, so the events generated since the scenario baseline are grouped
	 * by that very tag here: each group must hold exactly the two events of one id - the "down_N" event that
	 * opened its window and the "up_N" event that closed it. The older of the two is the cause of its group,
	 * with no cause of its own and the CEP_TAG_SYMPTOM_COUNT tag stating the single symptom it collected, and
	 * the younger one must point at it through its cause_eventid. That the "up" event is ranked at all is the
	 * point: it is taken into the window and ranked before the operation it triggers closes that window, so the
	 * ranking has to survive the close - the problems are gone by the time this runs, the events are not.
	 *
	 * $groups is how many distinct 'service' ids the run sent, i.e. how many such pairs must exist.
	 */
	private function waitForCloseOnUpCauseSymptomRanking(array $triggerids, int $groups): void {
		$this->callUntilDataIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'output' => ['eventid', 'name', 'cause_eventid'],
			'selectTags' => 'extend',
			'sortfield' => 'eventid',
			'sortorder' => 'ASC'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($groups) {
			// Group the events the way the window grouped them: by the value of their 'service' tag. The
			// events come back oldest first, so the first event of a group is the one that opened its window.
			$by_service = [];
			foreach ($response['result'] as $event) {
				$tags = array_column($event['tags'], 'value', 'tag');

				if (!array_key_exists('service', $tags)) {
					return 'event '.$event['eventid'].' ('.$event['name'].'): missing the "service" tag the'
						.' window groups by';
				}

				$by_service[$tags['service']][] = $event + ['tag_values' => $tags];
			}

			if (count($by_service) !== $groups) {
				return 'expected '.$groups.' "service" group(s), got '.count($by_service);
			}

			foreach ($by_service as $service => $events) {
				$info = '"service" group '.$service;

				if (count($events) !== 2) {
					return $info.': expected the 2 events of the id ("down" and "up"), got '.count($events);
				}

				[$cause, $symptom] = $events;

				if ((int) $cause['cause_eventid'] !== 0) {
					return $info.': cause event '.$cause['eventid'].' ('.$cause['name'].') has cause '
						.$cause['cause_eventid'].', the event that opened the window must have none';
				}

				if (!array_key_exists(self::CEP_TAG_SYMPTOM_COUNT, $cause['tag_values'])) {
					return $info.': cause event '.$cause['eventid'].' ('.$cause['name'].') is missing the "'
						.self::CEP_TAG_SYMPTOM_COUNT.'" tag';
				}

				if ((int) $cause['tag_values'][self::CEP_TAG_SYMPTOM_COUNT] !== 1) {
					return $info.': cause event '.$cause['eventid'].' ('.$cause['name'].') "'
						.self::CEP_TAG_SYMPTOM_COUNT.'" tag value "'
						.$cause['tag_values'][self::CEP_TAG_SYMPTOM_COUNT].'", expected 1';
				}

				if ($symptom['cause_eventid'] !== $cause['eventid']) {
					return $info.': event '.$symptom['eventid'].' ('.$symptom['name'].'): cause '
						.$symptom['cause_eventid'].', expected the cause of the group '.$cause['eventid'];
				}
			}

			return true;
		});
	}

	/**
	 * Wait until the window of every id in $services has recorded how many events it collected in the $tag tag of the
	 * event that opened it: every one of those windows was given $events values, so exactly one event of an id has to
	 * carry that number less itself - the events that joined it - and no other event of the id may carry the tag at
	 * all, the count being kept on the event that opened the window and on no other
	 * (cep_window_set_event_set_tag_value(), called with the first event of the window).
	 *
	 * With a single value per window there is nothing that ever joined one, so the tag is written nowhere and no event
	 * may have it.
	 *
	 * $ordered additionally says the oldest event of the id is the one that must carry it. That holds only while every
	 * event of an id went through the same trigger: the CEP queue keeps one task at a time per event origin - source,
	 * object and objectid, so per trigger (cep_queue_task_limit_by_origin()) - and lets different origins run at once
	 * over CEP_WORKERS_DEFAULT worker threads. Events of one trigger therefore reach a window in the order their
	 * eventids were handed out, and events of different triggers in no order at all, so a group fed a trigger per
	 * value can only be asked that exactly one of its events carries the count, not which one.
	 *
	 * The count is a tag the server maintains as the events arrive, so it lags the problems it belongs to and is
	 * polled for; the callback names the first id that does not match, which callUntilDataIsPresent() surfaces in the
	 * failure message.
	 */
	private function waitForCepWindowEventCounts(array $triggerids, string $tag, array $services, int $events,
			bool $ordered = true): void {
		$this->callUntilDataIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'output' => ['eventid', 'name'],
			'selectTags' => 'extend',
			'sortfield' => 'eventid',
			'sortorder' => 'ASC'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($tag, $services, $events, $ordered) {
				// Group the events the way the windows grouped them, by the value of their 'service' tag. They come
				// back oldest first, so the first event of a group is the one that opened its window.
				$by_service = [];

				foreach ($response['result'] as $event) {
					$tags = array_column($event['tags'], 'value', 'tag');

					if (!array_key_exists('service', $tags)) {
						return 'event '.$event['eventid'].' ('.$event['name'].'): missing the "service" tag the'
							.' window groups by';
					}

					$by_service[$tags['service']][] = $event + ['tag_values' => $tags];
				}

				foreach ($services as $service) {
					if (!array_key_exists($service, $by_service)) {
						return '"service" group '.$service.': no problem events yet';
					}

					$group = $by_service[$service];

					if (count($group) !== $events) {
						return '"service" group '.$service.': expected the '.$events.' event(s) of the id, got '
							.count($group);
					}

					// Only the event that opened the window counts the ones that joined it, and only if any did.
					$counted = [];

					foreach ($group as $index => $event) {
						if (array_key_exists($tag, $event['tag_values'])) {
							$counted[$index] = $event;
						}
					}

					$info = '"service" group '.$service;

					if ($events == 1) {
						if ($counted) {
							$event = reset($counted);

							return $info.': event '.$event['eventid'].' ('.$event['name'].') carries a "'.$tag
								.'" tag, but nothing ever joined a window holding a single event';
						}

						continue;
					}

					if (count($counted) !== 1) {
						return $info.': expected exactly one event carrying the "'.$tag.'" tag - the one that opened'
							.' the window - got '.count($counted).' of the '.count($group).' events of the id';
					}

					$index = array_key_first($counted);
					$event = $counted[$index];
					$info .= ': event '.$event['eventid'].' ('.$event['name'].')';

					if ($ordered && $index !== 0) {
						return $info.' carries the "'.$tag.'" tag, but it is not the oldest event of the id - every'
							.' value of this flavour went through one trigger, so the window was opened by the'
							.' oldest';
					}

					if ((int) $event['tag_values'][$tag] !== $events - 1) {
						return $info.': "'.$tag.'" tag value "'.$event['tag_values'][$tag].'", expected '
							.($events - 1).' - the events that joined it';
					}
				}

				return true;
			}
		);
	}

	/**
	 * Drive the event pattern match flavour. The three values fill one window; as soon as the script sees that
	 * many events it reports a match and the rule copies the oldest and the newest of them, so two more
	 * problems appear without any value having been sent for them.
	 *
	 * A copy carries the tags of the event it was made from, which is what identifies it: the id of the first
	 * value and the id of the last one end up with two problem events each, the id in between with one. The
	 * copies land in the same window, and the script counts them separately for exactly that reason - the
	 * pattern must not match a second time, so the totals have to stay where they are.
	 *
	 * The "up" value that follows ends the window, and the closing tags every event the window held with
	 * CEP_TAG_WINDOW_PATTERN_CLOSED - the "up" event among them, since an arriving event is taken into the
	 * window before the operations of its occurrence run. Six tagged events is therefore what shows the window
	 * closed, and that the copies were in it as the script counting them assumed.
	 */
	private function runEventAssessmentTestCepWindowPattern(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the event pattern match test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$first = self::CEP_RULE_WINDOW_NONE_SERVICE;
		$middle = self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT;
		$last = self::CEP_RULE_WINDOW_NONE_SERVICE_LAST;

		// The pattern needs this many events, so nothing may be copied before the last of them arrives.
		$this->assertEquals(3, self::CEP_RULE_WINDOW_PATTERN_EVENTS,
			'The pattern script and this scenario must agree on how many events make a match.');

		$open = 0;

		foreach ([$first, $middle, $last] as $service) {
			$send('down_'.$service);
			$this->waitForOpenProblemCount($all, ++$open);
			$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
		}

		// The match copies the oldest and the newest event of the window, so those two ids have two problem
		// events each while the one in between still has the single one it was sent for. The script rejects an
		// event whose fields are not what it expects by throwing, which also stops it from ever reporting a
		// match, so that message is what has to be reported here rather than the copies simply never showing
		// up.
		try {
			$this->waitForProblemEventCountByTag($all, 'service', $first, 2);
			$this->waitForProblemEventCountByTag($all, 'service', $last, 2);
			$this->waitForProblemEventCountByTag($all, 'service', $middle, 1);
		}
		catch (Throwable $e) {
			$error = $this->getCepRuleError(self::CEP_RULE_WINDOW_PATTERN);

			$this->assertSame('', $error, 'The pattern match script rejected what the window handed it: '
				.$error
			);

			throw $e;
		}

		// Five problems in total, and they stay five: the copies are in the window too, and the script must
		// not take them for a new pattern.
		$this->waitForOpenProblemCount($all, $open + 2);
		$this->waitForAllTriggerEventCounts($all, $open + 2);

		// The "up" value ends the window. It is a problem of its own - the trigger reports "up" as a problem
		// too - and it is taken into the window before the close window operation of its occurrence runs, so
		// the window is closed with six events in it: the three values, the two copies and the "up" event.
		$send('up_'.$first);
		$this->waitForOpenProblemCount($all, $open + 3);
		$this->waitForAllTriggerEventCounts($all, $open + 3);

		// Closing runs the tag operation over every one of them, so all six come out tagged. Nothing else in
		// this flavour adds that tag, which makes it the whole of what the closed window left behind.
		$this->waitForProblemEventsTagged($all, self::CEP_TAG_WINDOW_PATTERN_CLOSED, $open + 3);

		// Nothing in this flavour closes a problem, so the trigger expression has to.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the event pattern match recovery value');
	}

	/**
	 * Drive the tag driven service scenario. One value opens one problem, and everything that happens to the
	 * service after that is the doing of the rule:
	 *
	 *   1. the service starts in OK - no event carries the tag it watches;
	 *   2. the problem opens and the rule tags it as the event occurs, so the service goes into problem at the
	 *      severity of that problem;
	 *   3. the window duration runs out, the event is evicted and the rule takes the tag away again, so the
	 *      service returns to OK - while the problem is still open, which is what shows the service was
	 *      following the tag and not the problem.
	 */
	private function runEventAssessmentTestCepServiceTag(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the tag driven service test.');
		}

		$this->captureEventBaseline($all);

		// 1. Nothing carries the tag yet.
		$this->waitForCepTagServiceStatus(ZBX_SEVERITY_OK);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		// 2. The problem opens and is tagged as it occurs, which is what the service matches on.
		$send('down_'.self::CEP_RULE_WINDOW_NONE_SERVICE);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
		$this->waitForProblemEventsTagged($all, self::CEP_SERVICE_TAG_NAME, 1);
		$this->waitForCepTagServiceStatus(TRIGGER_SEVERITY_DISASTER);

		// 3. Once the window duration has run out the event is evicted and the tag is removed with it.
		$this->waitForProblemEventsTagged($all, self::CEP_SERVICE_TAG_NAME, 0);
		$this->waitForCepTagServiceStatus(ZBX_SEVERITY_OK);

		// The problem was never closed - only the tag went away, and the service followed it.
		$this->waitForOpenProblemCount($all, 1);

		// Nothing in this scenario closes a problem, so the trigger expression has to.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the tag driven service recovery value');
	}

	/**
	 * Wait until the service of the tag driven service scenario reaches $expected_status, reporting the status
	 * it actually has if it does not.
	 */
	private function waitForCepTagServiceStatus(int $expected_status): void {
		$this->assertNotEmpty(self::$cep_tag_serviceid,
			'The tag driven service must be created before waiting for its status.'
		);

		try {
			$this->callUntilCountIsPresent('service.get', [
				'serviceids' => [self::$cep_tag_serviceid],
				'filter' => ['status' => $expected_status]
			], 1, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		}
		catch (Exception $e) {
			$response = $this->call('service.get', [
				'serviceids' => [self::$cep_tag_serviceid],
				'output' => ['serviceid', 'status']
			]);

			throw new Exception('Expected the tag driven service to have status '.$expected_status.', got '
				.json_encode($response['result']).'. '.$e->getMessage()
			);
		}
	}

	/**
	 * Drive the copy scenario. One value opens one problem; once the window duration has run out the event is
	 * evicted and copied, so that id ends up with two problem events although only one value was ever sent for
	 * it. The copy then goes through the same window, and the point of the scenario is that it comes out the
	 * other side unchanged - it is not copied again.
	 *
	 * The second id is what makes that check safe: waiting for its copy takes another window duration, by
	 * which time the copy of the first id has been evicted as well, so if copies were being copied the first
	 * id would have more than two events by then.
	 */
	private function runEventAssessmentTestCepWindowCopy(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the copy test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$first = self::CEP_RULE_WINDOW_NONE_SERVICE;
		$second = self::CEP_RULE_WINDOW_NONE_SERVICE_NEXT;

		// 1. One value, one problem - and then a second problem for the same id that no value was sent for,
		//    the copy made when the window evicted the first one.
		$send('down_'.$first);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
		$this->waitForProblemEventCountByTag($all, 'service', $first, 2);

		// 2. The same for another id, which also gives the copy of the first one time to be evicted.
		$send('down_'.$second);
		$this->waitForProblemEventCountByTag($all, 'service', $second, 2);

		// 3. Two events per id and no more: the copies went through the window without being copied again.
		$this->waitForProblemEventCountByTag($all, 'service', $first, 2);
		$this->waitForOpenProblemCount($all, 4);
		$this->waitForAllTriggerEventCounts($all, 4);

		// Nothing in this scenario closes a problem, so the trigger expression has to.
		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the copy scenario recovery value');
	}// burst

	/**
	 * Drive the runaway copy scenario: one value, and then a copy of it every time the pattern window is
	 * examined, because the script always reports a match and a match does not consume the window.
	 *
	 * The scenario waits until the one id has clearly more problem events than the single copy a pattern that
	 * matches once would leave behind, then removes the rule - which is the only thing that stops the copying,
	 * since a recovery would only be answered by more copies. Only after that can the problems be recovered
	 * for good.
	 */
	private function runEventAssessmentTestCepWindowPatternCopyAlways(): void {
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for the runaway copy test.');
		}

		$this->captureEventBaseline($all);

		$send = fn(string $value) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $value]
		]);

		$first = self::CEP_RULE_WINDOW_NONE_SERVICE;

		// One value, one problem - and from then on a copy of it at every examination of the window.
		$send('down_'.$first);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// The count is only ever checked for being large enough: it grows while it is being read, so pinning
		// it to an exact number would be a race.
		$this->waitForProblemEventCountByTagAtLeast($all, 'service', $first,
			self::CEP_RULE_WINDOW_COPY_ALWAYS_MIN
		);

		// Removing the rule is what ends it. Recovering the trigger first would not: the copies are problem
		// events, so they would reopen the trigger as fast as it was recovered.
		$this->cleanupCepRules();

		$send('0');
		$this->waitForParentsValue($all, TRIGGER_VALUE_FALSE);
		$this->waitForNoOpenProblems($all, 'After the runaway copy scenario recovery value');
	}

	/**
	 * Poll event.get until at least $expected problem events since the scenario baseline carry the $tag tag
	 * with the $value value. Unlike waitForProblemEventCountByTag() this does not pin the count down, which a
	 * scenario still producing events cannot do.
	 */
	private function waitForProblemEventCountByTagAtLeast(array $triggerids, string $tag, string $value,
			int $expected): void {
		$this->callUntilDataIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'tags' => [['tag' => $tag, 'value' => $value, 'operator' => TAG_OPERATOR_EQUAL]],
			'output' => ['eventid']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($expected) {
			if (count($response['result']) < $expected) {
				return 'only '.count($response['result']).' problem event(s) so far, expected at least '
					.$expected;
			}

			return true;
		});
	}

	/**
	 * Wait until none of the events the windowless scenario generated is suppressed any more. The suppress
	 * operation suppressed every one of them for CEP_RULE_WINDOW_NONE_SUPPRESS_PERIOD, which the assertions of
	 * waitForCepWindowNoneTaggedEvents() confirmed; here the other half is checked - the suppression is
	 * temporary and must be gone once that period has passed.
	 *
	 * The wait is a long one: it has to cover the rest of the suppression period plus the timer pass that
	 * removes expired event_suppress records, which happens once a minute. That is why SKIP_UNSUPPRESS_WAIT
	 * leaves it out by default - the suppressions of a skipped wait are removed with the CEP rules in the
	 * teardown instead.
	 */
	private function waitForCepWindowNoneUnsuppressed(int $triggerid): void {
		if (static::SKIP_UNSUPPRESS_WAIT) {
			return;
		}

		$this->callUntilCountIsPresent('event.get', [
			'objectids' => [$triggerid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'suppressed' => true
		], 0, self::CEP_RULE_WINDOW_NONE_UNSUPPRESS_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Wait until the problem events generated on $triggerid since the scenario baseline are exactly the ones
	 * described by $expected_by_service - a 'service' tag value => list of tags map naming, for every event,
	 * the rules that must have tagged it (each rule tags with its own name, see getWindowNoneRules()). The tag
	 * value expected with each of them is the operand of that rule, taken from the same rule table, and every
	 * other rule tag - the tag of a rule whose condition the event does not satisfy - fails the check, so
	 * "tagged by service_equals" always means "tagged by service_equals only".
	 *
	 * The windowed flavours of the scenario reuse this too: they create none of the operator rules, so they
	 * pass an empty tag list for every id (none of those tags may be on the event) and state what must have
	 * become of the second window rule's tag in $extra_results, which extends the operation expectations with
	 * the same tag => value or null (must be absent) meaning.
	 *
	 * Every event is additionally checked against the tag state the operations of the tag operation rule must
	 * have left on it (getWindowNoneTagOperationResults(), the same expectations for every event, including
	 * the tags that must not be there at all) and against the name, severity and suppression the event
	 * operation rule must have given it (getWindowNoneEventOperationCase()). $set_name_skipped is for the one
	 * flavour whose execution point does not allow the "set name" operation and therefore does not carry it: the
	 * name expected of its events is the one the trigger prototype gave them, built from the value that opened
	 * the event, and the rewritten name of the operation would mean the operation ran after all.
	 *
	 * The tags are applied asynchronously after the event is created, hence the polling; the callback returns
	 * a description of the first event that does not match, which callUntilDataIsPresent() surfaces in the
	 * failure message.
	 */
	private function waitForCepWindowNoneTaggedEvents(int $triggerid, array $expected_by_service,
			array $extra_results = [], bool $set_name_skipped = false): void {
		// tag => the value the rule adds it with, for every rule of the scenario.
		$rule_values = array_map(fn($rule) => $rule[1], $this->getWindowNoneRules());
		// tag => the value the tag operations must have left on every event, or null if the tag must be gone.
		$operation_results = array_merge($this->getWindowNoneTagOperationResults(), $extra_results);
		// The name, severity and suppression the event operations must have left on every event.
		$event_results = $this->getWindowNoneEventOperationCase()['expected'];

		$this->callUntilDataIsPresent('event.get', [
			'objectids' => [$triggerid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'output' => ['eventid', 'name', 'severity', 'suppressed'],
			'selectTags' => 'extend'
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($expected_by_service, $rule_values, $operation_results, $event_results,
					$set_name_skipped) {
				if (count($response['result']) !== count($expected_by_service)) {
					return 'expected '.count($expected_by_service).' problem event(s), got '
						.count($response['result']);
				}

				foreach ($response['result'] as $event) {
					$tags = array_column($event['tags'], 'value', 'tag');
					$info = 'event '.$event['eventid'].' ('.$event['name'].') tags '.json_encode($event['tags']);

					if (!array_key_exists('service', $tags)) {
						return $info.': no "service" tag';
					}

					$service = $tags['service'];

					if (!array_key_exists($service, $expected_by_service)) {
						return $info.': unexpected event with service "'.$service.'"';
					}

					$expected_tags = $expected_by_service[$service];

					foreach ($expected_tags as $expected_tag) {
						if (!array_key_exists($expected_tag, $tags)) {
							return $info.': missing "'.$expected_tag.'" tag';
						}

						if ($tags[$expected_tag] !== $rule_values[$expected_tag]) {
							return $info.': "'.$expected_tag.'" tag value "'.$tags[$expected_tag]
								.'", expected "'.$rule_values[$expected_tag].'"';
						}
					}

					// Only the rules whose conditions the event satisfies may have tagged it.
					foreach (array_keys($rule_values) as $rule_tag) {
						if (!in_array($rule_tag, $expected_tags) && array_key_exists($rule_tag, $tags)) {
							return $info.': unexpected "'.$rule_tag.'" tag added by a non-matching rule';
						}
					}

					// The event operations leave the same severity and suppression on every event, and the same
					// name too unless the flavour has no "set name" operation - then every event keeps the name
					// its trigger prototype built from the value that opened it.
					if ($set_name_skipped) {
						$expected_name = 'CEP trigger '.self::COMPONENT_VALUE.' down_'.$service;
						$name_source = 'the trigger prototype, the flavour having no "set name" operation';
					}
					else {
						$expected_name = $event_results['name'];
						$name_source = 'the event operations';
					}

					if ($event['name'] !== $expected_name) {
						return 'event '.$event['eventid'].': name "'.$event['name'].'", expected "'
							.$expected_name.'" from '.$name_source;
					}

					if ((int) $event['severity'] !== $event_results['severity']) {
						return $info.': severity '.$event['severity'].', expected '
							.$event_results['severity'].' from the event operations';
					}

					if (((int) $event['suppressed'] === 1) !== $event_results['suppressed']) {
						return $info.': suppressed '.$event['suppressed'].', expected '
							.($event_results['suppressed'] ? 1 : 0).' from the event operations';
					}

					// The tag operations leave the same state on every event of the scenario.
					foreach ($operation_results as $tag => $value) {
						if ($value === null) {
							if (array_key_exists($tag, $tags)) {
								return $info.': "'.$tag.'" tag still present, the operations must have left'
									.' no tag of that name';
							}
						}
						elseif (!array_key_exists($tag, $tags)) {
							return $info.': missing "'.$tag.'" tag left by the tag operations';
						}
						elseif ($tags[$tag] !== $value) {
							return $info.': "'.$tag.'" tag value "'.$tags[$tag].'" left by the tag operations,'
								.' expected "'.$value.'"';
						}
					}
				}

				return true;
			}
		);
	}

	/**
	 * Test correlation update behavior: start with CLOSE_OLD+CLOSE_NEW, then update to CLOSE_NEW only,
	 * verify old problems stay open, then update back to CLOSE_OLD+CLOSE_NEW and verify closing works.
	 * This validates that correlation rule changes take effect on subsequent events.
	 */
	private function runEventAssessmentTestGlobalCorrelationCloseOnUpUpdateBehavior(bool $restart): void {
		// Drive a single discovered item (and its one trigger) so the whole scenario lands on one event stream.
		$key = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY)[0];
		$triggerid = self::getTriggeridForKey(self::HOST_DISC_VALUE, $key);
		$all = [$triggerid];

		// The one trigger must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Trigger must start in OK state for correlation update test.');
		}

		// Send one value with a unique id: value "<prefix>_<id>".
		$send = fn(string $prefix, int $id) => $this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $prefix.'_'.$id]
		]);

		// 1. Open the first problem on the trigger (unique id); trigger goes TRUE.
		$send('down', 0);
		$this->waitForOpenProblemCount($all, 1);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 2. Open a second problem on the trigger (a different unique id, mult_event); still TRUE.
		$send('down', 1);
		$this->waitForOpenProblemCount($all, 2);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 3. Update correlation to only close new problems (not old). This will be applied to the next event.
		self::$correlationid = $this->upsertCorrelation(
			$this->buildCloseNewOnlyCorrelationParams('CEP global event correlation up', CONDITION_EVAL_TYPE_AND_OR)
		);
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// 4. "up" for the first id: with updated correlation (CLOSE_NEW only), this closes only itself,
		//    not the corresponding "down_0" problem. So we still have 2 problems open (down_0 and down_1).
		$send('up', 0);
		$this->waitForOpenProblemCount($all, 2, 'After up_0 with CLOSE_NEW only, down_0 should remain open');
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		$this->maybeRestartServer($restart);

		// 5. Update correlation back to close both old and new problems.
		self::$correlationid = $this->upsertCorrelation(
			$this->buildCloseOnUpCorrelationParams('CEP global event correlation up', CONDITION_EVAL_TYPE_AND_OR)
		);
		$this->reloadConfigurationCacheAndWaitForLogLine();

		// 6. "up" for the second id: with restored correlation (CLOSE_OLD+CLOSE_NEW), this closes down_1
		//    and itself, but down_0 was already created before the correlation was restored, so it needs
		//    to be closed manually or by another mechanism. We check that down_1 is closed.
		$send('up', 1);
		$this->waitForOpenProblemCount($all, 1, 'After up_1 with CLOSE_OLD+CLOSE_NEW, only down_0 remains open');
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// 7. Send "down_0" recovery to close the last remaining problem.
		// For now, we'll send a different value to trigger recovery, or manually acknowledge the problem.
		// Since the trigger is based on find(regexp,"down|up"), we need to send a value that doesn't match.
		// Let's send a recovery for down_0 by changing the item value to something that doesn't match the pattern.
		$this->dispatchSenderValues([
			['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => '0']
		]);
		$this->waitForNoOpenProblems($all, 'After final recovery, all problems should be closed');
	}

	/**
	 * Same close-on-up flow as runEventAssessmentTestGlobalCorrelationCloseOnUp, but once all problems are
	 * open it downgrades every open problem's severity to WARNING via event.acknowledge and asserts the
	 * per-trigger services follow the manual severity change from DISASTER to WARNING, then closes the
	 * problems with "up" and asserts the services recover to OK.
	 */
	private function runEventAssessmentTestGlobalCorrelationCloseOnUpSeverity(bool $restart): void {
		$keys = array_merge(
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY),
			$this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2)
		);
		$all = array_merge(self::$discovered_triggerids, self::$discovered_dep_triggerids);
		$m = count($keys);

		// Build one sender value per key with a unique id: value "<prefix>_<offset + key index>".
		$values = fn(string $prefix, int $offset) => array_map(
			fn($key, $i) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $prefix.'_'.($offset + $i)],
			$keys, array_keys($keys)
		);

		// All triggers must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for close-on-up severity global correlation test.');
		}

		// 1. Open the first problem on every trigger (unique id per trigger); triggers go TRUE.
		$this->dispatchSenderValues($values('down', 0));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// 2. Open a second problem on every trigger (a different unique id, mult_event); still TRUE.
		$this->dispatchSenderValues($values('down', $m));
		$this->waitForOpenProblemCount($all, 2 * $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);

		// The trigger prototypes have DISASTER priority, so with problems open every per-trigger service
		// (matched via the SERVICE_TAG problem tag) is at DISASTER.
		$this->waitForServicesStatus(TRIGGER_SEVERITY_DISASTER);

		$this->maybeRestartServer($restart);

		// 3. Manually downgrade every open problem to WARNING. The service manager must recompute each
		//    service's status from the new problem severity, so all services drop DISASTER -> WARNING.
		$this->updateOpenProblemsSeverity($all, TRIGGER_SEVERITY_WARNING);
		$this->waitForServicesStatus(TRIGGER_SEVERITY_WARNING);

		$this->maybeRestartServer($restart);

		// 4. "up" for the first id set closes each corresponding "down" (and itself); each trigger's second
		//    problem stays open, so the services stay in problem state (still WARNING).
		$this->dispatchSenderValues($values('up', 0));
		$this->waitForOpenProblemCount($all, $m);
		$this->waitForParentsValue($all, TRIGGER_VALUE_TRUE);
		$this->waitForServicesStatus(TRIGGER_SEVERITY_WARNING);

		$this->maybeRestartServer($restart);

		// 5. "up" for the second id set closes each trigger's remaining problem; every service recovers to OK.
		$this->dispatchSenderValues($values('up', $m));
		$this->waitForNoOpenProblems($all);
		$this->waitForServicesStatus(ZBX_SEVERITY_OK);
	}

	/**
	 * Run the cross-trigger-prototype global event correlation scenario:
	 *
	 *   1. "down" → proto 1 items → find(regexp,"down") = true, service="down"
	 *                 → PROBLEM event on proto 1 triggers; proto 1 triggers go TRUE.
	 *   2. "down" → proto 2 items → find(regexp,"down") = true, service="down"
	 *                 → PROBLEM event on proto 2 triggers;
	 *                 global correlation matches new type "cep-dep" and old service="down"
	 *                 closes matched (old and new) problems
	 */
	private function runEventAssessmentTestGlobalCorrelationCrossTrigger(bool $restart): void {
		$keys1 = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$keys2 = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2);
		$triggerids1 = self::$discovered_triggerids;
		$triggerids2 = self::$discovered_dep_triggerids;

		// All triggers must start in OK state.
		$triggers1 = $this->getTriggers($triggerids1);
		$triggers2 = $this->getTriggers($triggerids2);
		foreach ($triggers1 as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Proto 1 triggers must start in OK state for cross-trigger global correlation test.');
		}
		foreach ($triggers2 as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'Proto 2 triggers must start in OK state for cross-trigger global correlation test.');
		}

		$this->captureEventBaseline(array_merge($triggerids1, $triggerids2));
		$expected_events1 = 0;

		// 1. "down" → proto 1: PROBLEM, service="down"; proto 1 triggers go TRUE.
		$expected_events1++;
		$this->assertStateChangeForAll(
			$triggerids1, $keys1, 'down', TRIGGER_VALUE_TRUE, $expected_events1
		);
		$this->maybeRestartServer($restart);

		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'down'], $keys2)
		);

		$this->waitForNoOpenProblems(array_merge($triggerids1, $triggerids2));
	}

	/**
	 * Run the parity-based global event correlation scenario: open a problem on every discovered trigger,
	 * then close them in two parity-selective waves (even first, then odd — the order does not matter).
	 * The one differing knob is $close_all: false uses the per-component rules (old service="down" + new
	 * odd=parity + component tag pair, closing each component 1:1); true uses the single old-event
	 * odd=parity condition with no tag pair, so each event's unrestricted CLOSE_OLD closes the whole
	 * parity at once. Everything else — opening all problems, the parity count checks between waves, the
	 * final "nothing open" — is identical, which is the point of sharing one method.
	 */
	private function runEventAssessmentTestGlobalCorrelationParity(bool $restart,
			$evaltype = CONDITION_EVAL_TYPE_AND_OR, bool $close_all = false): void {
		$keys1 = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$keys2 = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2);
		$triggerids1 = self::$discovered_triggerids;
		$triggerids2 = self::$discovered_dep_triggerids;
		$all = array_merge($triggerids1, $triggerids2);

		// Per prototype: this many components carry odd="1" (odd index) and odd="0" (even index). With
		// problems open on both prototypes, twice each count is open before the parity waves run.
		$open_count = [
			'1' => 2 * intdiv(static::LLD_DISCOVERY_COUNT + 1, 2),
			'0' => 2 * intdiv(static::LLD_DISCOVERY_COUNT, 2)
		];
		$keys2_by_parity = [
			'1' => $this->buildDiscoveredKeysByParity(self::ITEM_PROTO_KEY2, '1'),
			'0' => $this->buildDiscoveredKeysByParity(self::ITEM_PROTO_KEY2, '0')
		];

		if ($close_all) {
			$keys2_by_parity['1'] = array_slice($keys2_by_parity['1'], 0, 256);
			$keys2_by_parity['0'] = array_slice($keys2_by_parity['0'], 0, 1);
		}
		$rule_name = ['1' => 'CEP global event correlation odd', '0' => 'CEP global event correlation even'];
		$build = fn(string $parity) => $close_all
			? $this->buildParityCloseAllCorrelationParams($rule_name[$parity], $parity, $evaltype)
			: $this->buildParityCorrelationParams($rule_name[$parity], $parity, $evaltype);

		// The two parities are closed in separate waves; the order between them is irrelevant.
		$first_parity = '0';
		$second_parity = '1';

		// All triggers must start in OK state.
		foreach ($this->getTriggers($all) as $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state for parity global correlation test.');
		}

		$this->captureEventBaseline($all);

		// 1. Open a problem on every discovered trigger (both prototypes). No rule is active yet, so all
		//    problems stay open.
		$this->assertStateChangeForAll($triggerids1, $keys1, 'down', TRIGGER_VALUE_TRUE, 1);
		$this->maybeRestartServer($restart);
		$this->assertStateChangeForAll($triggerids2, $keys2, 'down', TRIGGER_VALUE_TRUE, 1);
		$this->maybeRestartServer($restart);

		$this->waitForOpenProblemCountByTag($all, 'odd', '1', $open_count['1']);
		$this->waitForOpenProblemCountByTag($all, 'odd', '0', $open_count['0']);

		// 2. First wave: add the $first_parity rule and re-send "down" to that parity's proto 2 keys.
		//    Those problems close; the other parity stays open.
		self::$correlationid = $this->upsertCorrelation($build($first_parity));
		$this->reloadConfigurationCacheAndWaitForLogLine();
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'down'],
				$keys2_by_parity[$first_parity])
		);
		$this->waitForOpenProblemCountByTag($all, 'odd', $first_parity, 0);
		$this->waitForOpenProblemCountByTag($all, 'odd', $second_parity, $open_count[$second_parity]);
		$this->maybeRestartServer($restart);

		// 3. Second wave: add the $second_parity rule and re-send "down" to its proto 2 keys. Nothing
		//    remains open.
		self::$correlationid2 = $this->upsertCorrelation($build($second_parity));
		$this->reloadConfigurationCacheAndWaitForLogLine();
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => 'down'],
				$keys2_by_parity[$second_parity])
		);

		$this->waitForNoOpenProblems($all);
	}

	/**
	 * Poll problem.get until the number of open problems on $triggerids carrying the exact tag
	 * $tag=$value equals $expected.
	 */
	private function waitForOpenProblemCountByTag(array $triggerids, string $tag, string $value, int $expected): void {
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'tags' => [['tag' => $tag, 'value' => $value, 'operator' => TAG_OPERATOR_EQUAL]]
		], $expected, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * The error a CEP rule last failed with, as the server recorded it - an empty string while the rule is
	 * healthy. A window script that throws ends up here, which is the only place its message can be read from.
	 */
	private function getCepRuleError(string $name): string {
		$response = $this->call('ceprule.get', [
			'filter' => ['name' => $name],
			'output' => ['error']
		]);

		return $response['result'] ? $response['result'][0]['error'] : '';
	}

	/**
	 * The name of the tag the window of the $name rule keeps the number of events it has collected in, or an empty
	 * string when its window keeps no such count - which is every window type but the cause and symptom one, the API
	 * allowing event_count_tag for no other and the server maintaining it for no other.
	 *
	 * The rule is asked rather than the caller told: the families whose flavours differ in window type run one
	 * assessment for all of them, so what a flavour has to be checked for is read from the rule it was given - see
	 * runEventAssessmentTestCepWindowCloseWindow().
	 */
	private function getCepRuleEventCountTag(string $name): string {
		$response = $this->call('ceprule.get', [
			'filter' => ['name' => $name],
			'output' => ['cep_ruleid'],
			'selectWindow' => ['event_count_tag']
		]);

		$this->assertNotEmpty($response['result'], 'There is no CEP rule named "'.$name.'".');

		$window = $response['result'][0]['window'];

		// A rule without a window is given an empty window by the API, so the field is not there to read at all.
		return array_key_exists('event_count_tag', $window) ? $window['event_count_tag'] : '';
	}

	/**
	 * Wait until the CEP rule named $name reports an error - any error. What the server writes there is its own
	 * wording and changing it is not something these scenarios should fail on: that a rule which cannot do what it
	 * was configured to do says so is the whole of what they read, see
	 * runEventAssessmentTestCepWindowUnresolvedLimits().
	 *
	 * The error is recorded by the server rather than by whatever asked it to do the work, so it arrives after the
	 * event that caused it has been assessed and is polled for instead of being read once.
	 */
	private function waitForCepRuleError(string $name): void {
		$this->callUntilDataIsPresent('ceprule.get', [
			'filter' => ['name' => $name],
			'output' => ['cep_ruleid', 'error']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($name) {
			if ($response['result'][0]['error'] !== '') {
				return true;
			}

			return 'the rule "'.$name.'" reports no error, expected one';
		});
	}

	/**
	 * The other direction: wait until the CEP rule named $name reports no error at all, which is what a rule whose
	 * error was cleared has to come back to. Unlike the error itself there is only one thing this can be, so it is
	 * asserted exactly, see waitForCepRuleError().
	 */
	private function waitForCepRuleNoError(string $name): void {
		$this->callUntilDataIsPresent('ceprule.get', [
			'filter' => ['name' => $name],
			'output' => ['cep_ruleid', 'error']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($name) {
			$error = $response['result'][0]['error'];

			if ($error === '') {
				return true;
			}

			return 'the rule "'.$name.'" reports "'.$error.'", expected none';
		});
	}

	/**
	 * Ask the server to reset the CEP rule named $name, the request the frontend sends when a rule is reset from
	 * the user interface (see CZabbixServer::resetCepRule()). There is no API method for it, so the rule is looked
	 * up by name and the server is asked directly, over the same trapper request and with the same session the
	 * frontend would use.
	 *
	 * The server only queues the reset before answering, so a successful response means the request was accepted
	 * and not that the windows of the rule are gone yet - see waitForCepTasksDrained().
	 */
	private function resetCepRule(string $name): void {
		$response = $this->call('ceprule.get', [
			'filter' => ['name' => $name],
			'output' => ['cep_ruleid']
		]);

		$this->assertNotEmpty($response['result'], 'There is no CEP rule named "'.$name.'" to reset.');

		$client = $this->getClient(self::COMPONENT_SERVER);
		$result = $client->resetCepRule(['cep_ruleid' => $response['result'][0]['cep_ruleid']],
			$this->getApiSessionId()
		);

		$this->assertNotFalse($result,
			'The server refused to reset the CEP rule "'.$name.'": '.$client->getError()
		);
	}

	/**
	 * Delete the single CEP rule named $name over the API, the counterpart of resetCepRule() for the scenarios that
	 * take a rule away for good. Unlike deleteCepRules() this names the rule under test rather than every rule of
	 * the suite, so it also asserts that the rule was there to be deleted and is gone afterwards.
	 *
	 * The rule is removed from the database by this, not from the running server - the configuration cache has to be
	 * reloaded before the server stops assessing events against it.
	 */
	private function deleteCepRule(string $name): void {
		$response = $this->call('ceprule.get', [
			'filter' => ['name' => $name],
			'output' => ['cep_ruleid']
		]);

		$this->assertNotEmpty($response['result'], 'There is no CEP rule named "'.$name.'" to delete.');

		$this->call('ceprule.delete', [$response['result'][0]['cep_ruleid']]);

		$response = $this->call('ceprule.get', [
			'filter' => ['name' => $name],
			'output' => ['cep_ruleid']
		]);

		$this->assertEmpty($response['result'], 'The CEP rule "'.$name.'" is still there after it was deleted.');
	}

	/**
	 * Wait until the CEP service has nothing left to do: both of its task queues are empty, so everything that had
	 * been queued - the reset of a rule among it - has been carried out. Only the queued work is waited for, not
	 * what it leads to elsewhere, so this is a starting point for the assertions that follow rather than one of
	 * them.
	 */
	private function waitForCepTasksDrained(): void {
		$this->assertCepStatEquals('tasks', 'remote', 0);
		$this->assertCepStatEquals('tasks', 'internal', 0);
	}

	/**
	 * Poll event.get until exactly $expected of the events generated since the scenario baseline are
	 * suppressed. The capacity flavours suppress every event they evict, so this counts the evictions that
	 * happened so far.
	 */
	private function waitForSuppressedEventCount(int $triggerid, int $expected): void {
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => [$triggerid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'suppressed' => true
		], $expected, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Poll event.get until exactly $expected problem events since the scenario baseline carry the $tag tag
	 * with the $value value. Unlike waitForOpenProblemCountByTag() this counts the problems that were opened,
	 * whether they are still open or have been closed since.
	 */
	private function waitForProblemEventCountByTag(array $triggerids, string $tag, string $value,
			int $expected): void {
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'tags' => [['tag' => $tag, 'value' => $value, 'operator' => TAG_OPERATOR_EQUAL]]
		], $expected, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Poll problem.get until the total number of open problems on $triggerids equals $expected. $message, when given,
	 * is added to the failure so the report names the step that expected this count. $iterations replaces the patience
	 * of the wait for the scenarios that have to outlast a window duration rather than the processing of a value, see
	 * runEventAssessmentTestCepWindowCloseOnDuration().
	 */
	private function waitForOpenProblemCount(array $triggerids, int $expected, string $message = '',
			?int $iterations = null): void {
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS
		], $expected, $iterations === null ? static::WAIT_ITERATIONS_LONGER : $iterations,
			self::WAIT_ITERATION_DELAY, null, $message === '' ? null : function () use ($message) {
				return $message;
			}
		);
	}

	/**
	 * The eventids of the problems currently open on $triggerids, sorted, so two sets taken at different points of
	 * a scenario can be compared as they are: which problems are open, not only how many.
	 */
	private function getOpenProblemEventids(array $triggerids): array {
		$response = $this->call('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'output' => ['eventid']
		]);

		$eventids = array_column($response['result'], 'eventid');

		sort($eventids, SORT_NUMERIC);

		return $eventids;
	}

	/**
	 * Wait until exactly $expected open problems on the given triggers are suppressed. The 'suppressed'
	 * filter returns only suppressed problems, so a matching count means every open problem is suppressed
	 * (as expected while the host is under maintenance).
	 */
	private function waitForOpenProblemsSuppressed(array $triggerids, int $expected): void {
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'suppressed' => true
		], $expected, static::WAIT_ITERATIONS_LONGER, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Wait until exactly $expected open problems on the given triggers are suppressed and every one of
	 * them is suppressed by exactly the given maintenances: each problem's suppression data must list
	 * every maintenanceid from $maintenanceids and nothing else, so rows of stopped maintenances must
	 * be gone and rows of every active maintenance must be present.
	 */
	private function waitForOpenProblemsSuppressedByMaintenances(array $triggerids, int $expected,
			array $maintenanceids): void {
		$expected_ids = array_values($maintenanceids);
		sort($expected_ids);

		$this->callUntilDataIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'suppressed' => true,
			'selectSuppressionData' => ['maintenanceid']
		], static::WAIT_ITERATIONS_LONGER, self::WAIT_ITERATION_DELAY, function (array $response) use ($expected, $expected_ids) {
			if (count($response['result']) != $expected) {
				return 'expected '.$expected.' suppressed problems, got '.count($response['result']);
			}

			foreach ($response['result'] as $problem) {
				$ids = array_column($problem['suppression_data'], 'maintenanceid');
				sort($ids);

				if ($ids !== $expected_ids) {
					return 'problem '.$problem['eventid'].' is suppressed by maintenances ['.
							implode(', ', $ids).'], expected ['.implode(', ', $expected_ids).']';
				}
			}

			return true;
		});
	}

	/**
	 * Wait until exactly $expected open problems on the given triggers are suppressed and each one is
	 * suppressed by exactly the single tag-scoped maintenance matching its 'component' tag: a problem
	 * carrying component=X must be suppressed by $maintenance_by_component[X] and by nothing else. This
	 * proves the tag filter on a maintenance suppresses only the problems whose tag it matches, unlike a
	 * host-wide maintenance which suppresses every problem.
	 */
	private function waitForOpenProblemsSuppressedPerComponent(array $triggerids, int $expected,
			array $maintenance_by_component): void {
		$this->callUntilDataIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'suppressed' => true,
			'selectTags' => ['tag', 'value'],
			'selectSuppressionData' => ['maintenanceid']
		], static::WAIT_ITERATIONS_LONGER, self::WAIT_ITERATION_DELAY, function (array $response) use ($expected, $maintenance_by_component) {
			if (count($response['result']) != $expected) {
				return 'expected '.$expected.' suppressed problems, got '.count($response['result']);
			}

			foreach ($response['result'] as $problem) {
				$component_tag = current(array_filter($problem['tags'],
					fn($t) => $t['tag'] === 'component'
				));

				if ($component_tag === false) {
					return 'problem '.$problem['eventid'].' has no component tag';
				}

				$component = $component_tag['value'];

				if (!isset($maintenance_by_component[$component])) {
					return 'problem '.$problem['eventid'].' has unexpected component "'.$component.'"';
				}

				$ids = array_column($problem['suppression_data'], 'maintenanceid');
				$expected_ids = [$maintenance_by_component[$component]];

				sort($ids);
				sort($expected_ids);

				if ($ids !== $expected_ids) {
					return 'problem '.$problem['eventid'].' (component "'.$component.'") is suppressed by ['.
							implode(', ', $ids).'], expected ['.implode(', ', $expected_ids).']';
				}
			}

			return true;
		});
	}

	/**
	 * Wait until no suppressed trigger events remain on the discovered host and its services are no
	 * longer suppressed - the state expected once every maintenance is out of its active window.
	 */
	private function waitForSuppressionCleared(): void {
		$this->callUntilCountIsPresent('event.get', [
			'hostids' => [self::$disc_hostid],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'suppressed' => true
		], 0, static::WAIT_ITERATIONS_LONGER, self::WAIT_ITERATION_DELAY);

		$this->waitForServicesNoLongerSuppressed();
	}

	private function runDependentTriggerTest(bool $restart): void {
		$parent_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$dep_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2);
		$parent_ids = self::$discovered_triggerids;
		$dep_ids = self::$discovered_dep_triggerids;

		// All triggers must start in OK state.
		$triggers = $this->getTriggers(array_merge($parent_ids, $dep_ids));
		foreach ($triggers as $id => $t) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $t['value'],
				'All triggers must start in OK state.');
		}

		$this->captureEventBaseline(array_merge($parent_ids, $dep_ids));
		$parent_event_count = 0;
		$dep_event_count = 0;

		// 1. Parent OK→PROBLEM.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '1', TRIGGER_VALUE_TRUE, $parent_event_count + 1);
		$this->maybeRestartServer($restart);

		// 2. Dependents suppressed while parents are PROBLEM.
		$this->assertNoStateChangeForAll($dep_ids, $dep_keys, '2', TRIGGER_VALUE_FALSE, $dep_event_count);
		$this->maybeRestartServer($restart);

		// 3. Parent PROBLEM→OK: dependents fire.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '0', TRIGGER_VALUE_FALSE, $parent_event_count + 2);

		$this->assertStateChangeForAll($dep_ids, $dep_keys, '1', TRIGGER_VALUE_TRUE, $dep_event_count + 1, false);
		$this->maybeRestartServer($restart);

		// 4. Dependent PROBLEM→OK: dependents recover.
		$this->assertStateChangeForAll($dep_ids, $dep_keys, '0', TRIGGER_VALUE_FALSE, $dep_event_count + 2);
		$this->maybeRestartServer($restart);

		$parent_event_count += 2;
		$dep_event_count += 2;
		$dep_triggers = $this->getTriggers($dep_ids);
		$dep_lastchanges = array_map(fn($tid) => $dep_triggers[$tid]['lastchange'], $dep_ids);

		// 5. Parent OK→PROBLEM; dep condition was never true → deps must stay OK.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '1', TRIGGER_VALUE_TRUE, $parent_event_count + 1);
		$dep_triggers = $this->getTriggers($dep_ids);
		foreach ($dep_ids as $dep_id) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, $dep_triggers[$dep_id]['value'],
				'Dependent must stay OK while parent is PROBLEM and dep condition is not met.');
		}
		$this->maybeRestartServer($restart);

		// 6. Parent PROBLEM→OK; dep condition still false → deps stay OK throughout.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '0', TRIGGER_VALUE_FALSE, $parent_event_count + 2);
		$this->maybeRestartServer($restart);

		$parent_event_count += 2;

		// 7. Deps fire normally while parents are OK.
		$this->assertStateChangeForAll($dep_ids, $dep_keys, '1', TRIGGER_VALUE_TRUE, $dep_event_count + 1);
		$this->maybeRestartServer($restart);

		// 8. Parents fire; deps are already PROBLEM.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '1', TRIGGER_VALUE_TRUE, $parent_event_count + 1);
		$this->maybeRestartServer($restart);

		// 9. Dep recovery suppressed while parents are PROBLEM; deps stay PROBLEM.
		$this->assertNoStateChangeForAll($dep_ids, $dep_keys, '0', TRIGGER_VALUE_TRUE, $dep_event_count + 1);
		$this->maybeRestartServer($restart);

		// 10. Parents recover; deps recover as well.
		$this->assertStateChangeForAll($parent_ids, $parent_keys, '0', TRIGGER_VALUE_FALSE, $parent_event_count + 2);
		$this->assertStateChangeForAll($dep_ids, $dep_keys, '0', TRIGGER_VALUE_FALSE, $dep_event_count + 2);

		/*$this->runIntermingledDependentTriggerBatch($restart, $parent_event_count + 2);*/
	}

	private function runIntermingledDependentTriggerBatch(bool $restart, int $parent_event_count): void {
		$parent_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$dep_keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY2);
		$parent_ids = self::$discovered_triggerids;

		// 1. Intermingled batch: parent PROBLEM + dep PROBLEM values arrive in the same
		//    sender packet (parent_key, dep_key, parent_key, dep_key, ...); only the parent
		//    triggers are asserted.
		$intermingled = [];
		foreach ($parent_keys as $idx => $pkey) {
			$intermingled[] = ['host' => self::HOST_DISC_VALUE, 'key' => $pkey, 'value' => '1'];
			$intermingled[] = ['host' => self::HOST_DISC_VALUE, 'key' => $dep_keys[$idx], 'value' => '1'];
		}
		$this->dispatchSenderValues($intermingled);

		$this->waitForParentsValue($parent_ids, TRIGGER_VALUE_TRUE);
		$this->waitForAllTriggerEventCounts($parent_ids, $parent_event_count + 1);

		$this->maybeRestartServer($restart);

		// 2. Intermingled recovery: parent OK + dep OK values in one packet
		//    (parent_key, dep_key, parent_key, dep_key, ...); only parents are asserted.
		$intermingled_recovery = [];
		foreach ($parent_keys as $idx => $pkey) {
			$intermingled_recovery[] = ['host' => self::HOST_DISC_VALUE, 'key' => $pkey, 'value' => '0'];
			$intermingled_recovery[] = ['host' => self::HOST_DISC_VALUE, 'key' => $dep_keys[$idx], 'value' => '0'];
		}
		$this->dispatchSenderValues($intermingled_recovery);

		$this->waitForParentsValue($parent_ids, TRIGGER_VALUE_FALSE);
		$this->waitForAllTriggerEventCounts($parent_ids, $parent_event_count + 2);

		// When the parent recovered in the intermingled batch above, dependency suppression lifted while a
		// dependent's last value could still be '1' (processed before its own '0' in the same packet), so a
		// dependent problem may have opened. Send a final dep '0' to deterministically clear any such
		// leftover before asserting no open problems.
		$dep_recovery = [];
		foreach ($dep_keys as $dkey) {
			$dep_recovery[] = ['host' => self::HOST_DISC_VALUE, 'key' => $dkey, 'value' => '0'];
		}
		$this->dispatchSenderValues($dep_recovery);

		// After recovery no problems must remain open on either the parent or the dependent triggers.
		$this->waitForNoOpenProblems(array_merge($parent_ids, self::$discovered_dep_triggerids),
			'intermingled batch recovery', false);
	}

	/**
	 * Wait until every parent trigger reached $expected_value in NORMAL state. The callback returns a
	 * descriptive string on mismatch (surfaced in the callUntilDataIsPresent failure message) rather
	 * than a bare false.
	 */
	private function waitForParentsValue(array $parent_ids, int $expected_value): void {
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $parent_ids,
			'output' => ['triggerid', 'value', 'state']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($parent_ids, $expected_value) {
				$by_id = array_column($response['result'], null, 'triggerid');
				foreach ($parent_ids as $tid) {
					if (!isset($by_id[$tid])) {
						return 'trigger '.$tid.' missing from response';
					}
					$t = $by_id[$tid];
					if ((int) $t['value'] !== $expected_value) {
						return 'trigger '.$tid.' value '.$t['value'].', expected '.$expected_value;
					}
					if ((int) $t['state'] !== TRIGGER_STATE_NORMAL) {
						return 'trigger '.$tid.' state '.$t['state'].', expected NORMAL';
					}
				}
				return true;
			}
		);
	}

	/**
	 * Verify that after restoring expression-based recovery the discovered trigger
	 * (which is stuck PROBLEM from the None-mode run) can now recover via assertStateChange.
	 */
	private function assertRecoveryAfterRestore(bool $restart): void {
		$keys = $this->buildDiscoveredKeys(self::ITEM_PROTO_KEY);
		$triggerids = self::$discovered_triggerids;

		// All triggers must still be PROBLEM from the None-mode run.
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$this->assertEquals(TRIGGER_VALUE_TRUE, $triggers[$triggerid]['value'],
				'trigger #'.$idx.' must still be PROBLEM before recovery-after-restore check.');
		}
		$this->captureEventBaseline($triggerids);
		$event_count = 0;

		$this->maybeRestartServer($restart);

		// Send recovery value – now that recovery mode is expression, a RESOLVED event must fire.
		$this->assertStateChangeForAll($triggerids, $keys, '0', TRIGGER_VALUE_FALSE, $event_count + 1);

		$this->waitForNoOpenProblems($triggerids, 'After recovery-after-restore');
	}

	/**
	 * Collect open problem event IDs for all given triggers, verify that manual close is
	 * rejected while the flag is off, then enable manual_close on the trigger prototypes,
	 * resend LLD discovery data so the change propagates to the discovered triggers, reload
	 * the configuration cache, close all problems, and wait for every trigger to return to OK.
	 */
	private function closeTagCorrelationProblems(array $triggerids): void {
		// Collect the event ID of the open problem for every trigger.
		$response = $this->call('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'output' => ['eventid']
		]);
		$this->assertCount(count($triggerids), $response['result'], 'Expected exactly one open problem per trigger: '.json_encode($response));
		$problem_eventids = array_column($response['result'], 'eventid');

		// Manual close must be rejected because the manual_close flag is off.
		$ack_response = CAPIHelper::call('event.acknowledge', [
			'eventids' => [$problem_eventids[0]],
			'action' => ZBX_PROBLEM_UPDATE_CLOSE,
			'message' => 'Manual close for tag-correlation mode test'
		]);
		$this->assertArrayHasKey('error', $ack_response,
			'Expected manual close to be rejected, but it succeeded: '.json_encode($ack_response));

		// Enable manual close on the trigger prototypes so the setting propagates to all
		// discovered triggers after LLD re-discovery.
		$this->call('triggerprototype.update', [
			'triggerid' => self::$trigger_prototypeid,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
		]);
		$this->callUntilDataIsPresent('triggerprototype.get', [
			'triggerids' => [self::$trigger_prototypeid],
			'output' => ['manual_close']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			return (int) $response['result'][0]['manual_close'] === ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED;
		});
		$this->call('triggerprototype.update', [
			'triggerid' => self::$dep_trigger_prototypeid,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
		]);
		$this->callUntilDataIsPresent('triggerprototype.get', [
			'triggerids' => [self::$dep_trigger_prototypeid],
			'output' => ['manual_close']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
			return (int) $response['result'][0]['manual_close'] === ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED;
		});

		// Resend LLD discovery data so the server re-instantiates discovered triggers
		// with the updated prototype configuration.
		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => $this->buildItemLLDData()
			]
		]);

		// Wait for the discovered triggers to reflect the updated manual_close setting.
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $triggerids,
			'output' => ['manual_close']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($triggerids) {
			if (count($response['result']) !== count($triggerids)) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['manual_close'] !== ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED) {
					return false;
				}
			}
			return true;
		});

		$this->reloadConfigurationCacheAndWaitForLogLine();

		$this->call('event.acknowledge', [
			'eventids' => $problem_eventids,
			'action' => ZBX_PROBLEM_UPDATE_CLOSE,
			'message' => 'Manual close for tag-correlation mode test'
		]);

		$this->waitForNoOpenProblems($triggerids);
	}

	public function triggerCEP_Cleanup() {
		// Send empty log LLD data to remove the discovered log item and trigger.
		if (!empty(self::$discovered_log_triggerid)) {
			$this->dispatchSenderValues([
				[
					'host' => self::HOST_NAME,
					'key' => self::LOG_LLD_RULE_KEY,
					'value' => json_encode(['data' => []])
				]
			]);

			$this->callUntilCountIsPresent('trigger.get', [
				'triggerids' => [self::$discovered_log_triggerid]
			], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

			self::$discovered_log_triggerid = null;
			$this->reloadConfigurationCacheAndWaitForLogLine();
		}

		$triggerids = array_filter([self::$discovered_triggerid, self::$discovered_dep_triggerid]);

		if (!$triggerids) {
			return;
		}

		$this->dispatchSenderValues([
			[
				'host' => self::HOST_DISC_VALUE,
				'key' => self::LLD_RULE_KEY,
				'value' => json_encode(['data' => []])
			]
		]);

		$this->callUntilCountIsPresent('trigger.get', [
			'triggerids' => $triggerids
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		self::$discovered_triggerid = null;
		self::$discovered_dep_triggerid = null;
		self::$discovered_triggerids = [];
		self::$discovered_dep_triggerids = [];
		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Delete all history and trend data for items on the discovered host.
	 * Must be called before test phases that use history-sensitive functions (e.g. change()).
	 */
	private function clearDiscoveredItemHistory(): void {
		$response = $this->call('item.get', [
			'hostids' => [self::$disc_hostid],
			'output' => ['itemid']
		]);

		if (empty($response['result'])) {
			return;
		}

		CDataHelper::removeItemData(array_column($response['result'], 'itemid'));
	}

	private function maybeRestartServer(bool $restart): void {
		if (!$restart) {
			return;
		}
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
	}

	/**
	 * Stop and start the server in the middle of a CEP window scenario, or do nothing at all when the restarts are
	 * turned off (SKIP_RESTART_TESTS). The scenarios call this where their windows are holding what the rest of them
	 * acts on, so everything after the call is driven against the windows the server loaded back from the database:
	 * a window is a row of cep_window and every event in it a row of cep_window_event, and what a scenario asserts
	 * afterwards is exactly what it asserts without a restart - the events the window still holds close with it, the
	 * room it has left still limits what fits into it, and the group it had ranked is still the group. A window that
	 * came back short of any of that fails those steps.
	 *
	 * The scenarios that take one and where:
	 *   - the close window family, between filling every window and the "up" values that end them
	 *     (runEventAssessmentTestCepWindowCloseWindow());
	 *   - the capacity family, between filling every window and the values that must no longer fit into one
	 *     (runEventAssessmentTestCepWindowCapacity() and runEventAssessmentTestCepWindowCapacityPerService());
	 *   - the cause and symptom flavour, once the cause of the group has been ranked and before the events that must
	 *     join it (runEventAssessmentTestCepWindowCauseSymptom());
	 *   - the reset and delete flavours, after the rule was reset or deleted, where what must come back is nothing
	 *     (runEventAssessmentTestCepWindowReset() and runEventAssessmentTestCepWindowDelete());
	 *   - the two flavours that leave a window to be ended by its own duration, with the events whose age ends it
	 *     already in the window (runEventAssessmentTestCepWindowCloseOnDuration() and
	 *     runEventAssessmentTestCepWindowCauseSymptomCloseOnDuration()) - so the window that runs out is one loaded
	 *     back from the database and the events it closes are the ones it came back with.
	 *
	 * The scenarios that do not, all for the same reason - what they read is a matter of seconds and a restart is
	 * seconds long, and their durations have to stay short enough for the wait that follows them: the operations
	 * flavours that wait for an eviction, the capacity discarding one that waits for a window to run out, the delete
	 * during a sleeping script, and the pattern flavours counting the copies of an examination that repeats once a
	 * second. The windowless scenarios have no window to bring back at all.
	 */
	private function maybeRestartServerMidScenario(): void {
		if (static::SKIP_RESTART_TESTS) {
			return;
		}

		// What the windows hold has to be in the database before the server is stopped: the rows of a window are
		// written by the sync tasks of that window, and an event that had not been stored yet would be missing from
		// the window that comes back for a reason that is not the restart.
		$this->waitForCepTasksDrained();
		$this->maybeRestartServer(true);
	}

	/**
	 * The seconds a scenario adds to the duration of its windows to leave room for the restart it takes in the middle
	 * of itself, or none when the restarts are turned off - see CEP_RESTART_WINDOW_ALLOWANCE and
	 * maybeRestartServerMidScenario().
	 */
	private static function getRestartWindowAllowance(): int {
		return static::SKIP_RESTART_TESTS ? 0 : static::CEP_RESTART_WINDOW_ALLOWANCE;
	}

	/**
	 * How long the sliding windows of the duration close scenario last, in seconds: the age at which the oldest event
	 * of such a window is evicted, which is what ends the window there - see
	 * runEventAssessmentTestCepWindowCloseOnDuration(). The stop and start that scenario takes between its two values
	 * happens inside that lifetime, so the time it costs is part of the period as well: the younger event has to still
	 * be in the window when the older one ages out, restart or no restart.
	 */
	private static function getCloseOnDurationPeriod(): int {
		return static::CEP_RULE_WINDOW_CLOSE_DURATION_PERIOD + static::getRestartWindowAllowance();
	}

	/**
	 * Put the discovered host into data-collection maintenance and wait for the server to start it, so any
	 * problem opened afterwards is suppressed. Returns the maintenance id for stopDiscHostMaintenance().
	 */
	private function startDiscHostMaintenance(string $name): string {
		$maintenanceid = $this->upsertDiscHostMaintenance($name);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		return $maintenanceid;
	}

	/**
	 * Create a data-collection maintenance for the discovered host with an active period covering now, or,
	 * if a maintenance with the given name already exists (left over from a previous run), update it back
	 * into an active window. Returns the maintenance id.
	 */
	private function upsertDiscHostMaintenance(string $name): string {
		$maintenanceids = $this->upsertDiscHostMaintenances([$name]);

		return $maintenanceids[0];
	}

	/**
	 * Bulk variant of upsertDiscHostMaintenance(): one maintenance.get to find leftovers by name, then a
	 * single maintenance.update for the existing ones and a single maintenance.create for the rest.
	 * Returns the maintenance ids in the same order as the given names.
	 *
	 * $extra_by_name optionally maps a name to extra maintenance fields (e.g. a tag filter) merged on top
	 * of the host-wide defaults, so a caller can scope individual maintenances without duplicating the
	 * leftover-safe upsert logic.
	 */
	private function upsertDiscHostMaintenances(array $names, array $extra_by_name = []): array {
		if (empty($names)) {
			return [];
		}

		$now = time();
		$defaults = [
			'hosts' => ['hostid' => self::$disc_hostid],
			'active_since' => $now - 60,
			'active_till' => $now + 3600,
			'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
			'tags_evaltype' => MAINTENANCE_TAG_EVAL_TYPE_AND_OR,
			'timeperiods' => [
				'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
				'period' => 3600,
				'start_date' => $now - 60
			]
		];

		$response = $this->call('maintenance.get', [
			'output' => ['maintenanceid', 'name'],
			'filter' => ['name' => $names]
		]);

		$ids_by_name = [];
		foreach ($response['result'] as $maintenance) {
			$ids_by_name[$maintenance['name']] = $maintenance['maintenanceid'];
		}

		$updates = [];
		$creates = [];
		foreach ($names as $name) {
			$fields = isset($extra_by_name[$name]) ? array_merge($defaults, $extra_by_name[$name]) : $defaults;

			if (isset($ids_by_name[$name])) {
				$updates[] = array_merge(['maintenanceid' => $ids_by_name[$name]], $fields);
			}
			else {
				$creates[] = array_merge(['name' => $name], $fields);
			}
		}

		if (!empty($updates)) {
			$this->call('maintenance.update', $updates);
		}

		if (!empty($creates)) {
			$response = $this->call('maintenance.create', $creates);
			$this->assertArrayHasKey('maintenanceids', $response['result']);
			$this->assertCount(count($creates), $response['result']['maintenanceids']);

			foreach ($creates as $index => $maintenance) {
				$ids_by_name[$maintenance['name']] = $response['result']['maintenanceids'][$index];
			}
		}

		$maintenanceids = [];
		foreach ($names as $name) {
			$maintenanceids[] = $ids_by_name[$name];
		}

		return $maintenanceids;
	}

	/**
	 * End a maintenance created by startDiscHostMaintenance() without deleting it: push its active period
	 * far into the future so it is no longer active now, then reload the configuration cache so the host
	 * leaves maintenance before the rest of the suite runs.
	 */
	private function stopDiscHostMaintenance(string $maintenanceid): void {
		$this->stopDiscHostMaintenances([$maintenanceid]);
	}

	/**
	 * Bulk variant of stopDiscHostMaintenance(): push the active period of all given maintenances out of
	 * the current window with a single maintenance.update call.
	 */
	private function stopDiscHostMaintenances(array $maintenanceids): void {
		if (empty($maintenanceids)) {
			return;
		}

		// Ten years ahead - a start that will never come within the test run, so the maintenances stay
		// defined but idle and the host is taken out of maintenance.
		$future = time() + 10 * 365 * 24 * 3600;

		$maintenances = [];
		foreach ($maintenanceids as $maintenanceid) {
			$maintenances[] = [
				'maintenanceid' => $maintenanceid,
				'active_since' => $future,
				'active_till' => $future + 3600,
				'timeperiods' => [
					'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
					'period' => 3600,
					'start_date' => $future
				]
			];
		}

		$this->call('maintenance.update', $maintenances);
	}

	/**
	 * Bring maintenances ended by stopDiscHostMaintenances() back into an active window covering now
	 * with a single maintenance.update call, then reload the configuration cache so the host re-enters
	 * maintenance.
	 */
	private function resumeDiscHostMaintenances(array $maintenanceids): void {
		if (empty($maintenanceids)) {
			return;
		}

		$now = time();

		$maintenances = [];
		foreach ($maintenanceids as $maintenanceid) {
			$maintenances[] = [
				'maintenanceid' => $maintenanceid,
				'active_since' => $now - 60,
				'active_till' => $now + 3600,
				'timeperiods' => [
					'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
					'period' => 3600,
					'start_date' => $now - 60
				]
			];
		}

		$this->call('maintenance.update', $maintenances);

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Create multiple maintenances for the discovered host and reload configuration cache once after all are created.
	 */
	private function startDiscHostMaintenances(int $count): void {
		$start = count(self::$disc_maintenanceids) + 1;

		$names = [];
		for ($i = $start; $i < $start + $count; $i++) {
			$names[] = 'CEP close-on-up maintenance'.$i;
		}

		self::$disc_maintenanceids = array_merge(self::$disc_maintenanceids, $this->upsertDiscHostMaintenances($names));

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Create one maintenance per discovered component, each scoped to that component via a 'component'
	 * problem-tag filter (host + tag), so a maintenance suppresses only the problems carrying its own
	 * component tag rather than every problem on the host. Appends the created ids to
	 * self::$disc_maintenanceids and reloads the configuration cache once. Returns a map of
	 * component value => maintenanceid so callers can assert which maintenance must suppress each problem.
	 */
	private function startDiscHostTagMaintenances(): array {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');

		$names = [];
		$extra_by_name = [];
		$component_by_name = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			$component = $base.$i;
			$name = 'CEP per-tag maintenance '.$component;

			$names[] = $name;
			$component_by_name[$name] = $component;
			$extra_by_name[$name] = [
				'tags' => [
					['tag' => 'component', 'operator' => MAINTENANCE_TAG_OPERATOR_EQUAL, 'value' => $component]
				]
			];
		}

		$ids = $this->upsertDiscHostMaintenances($names, $extra_by_name);
		self::$disc_maintenanceids = array_merge(self::$disc_maintenanceids, $ids);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		$maintenance_by_component = [];
		foreach ($names as $index => $name) {
			$maintenance_by_component[$component_by_name[$name]] = $ids[$index];
		}

		return $maintenance_by_component;
	}

	/**
	 * Skip every test that is not a CEP window scenario when SKIP_NON_WINDOW_TESTS is enabled, see it for what
	 * counts as one. Unlike the skips below, this is a @before hook rather than a call from the body of the
	 * tests it skips: those are the tests that say nothing about windows, and there are a hundred of them.
	 *
	 * The components of the suite are started by the time this runs (CIntegrationTest::onBeforeTestCase() is a
	 * @before of the parent class and those run first), which costs a skipped test nothing here: the suite is
	 * declared @suite-components-reuse, so its server is started once for all of them rather than per test.
	 *
	 * @before
	 */
	public function skipNonWindowTests(): void {
		$name = $this->getName(false);

		if (static::SKIP_NON_WINDOW_TESTS && strpos($name, 'Cep') === false
				&& strpos($name, 'testPrepare') !== 0) {
			$this->markTestSkipped('Only the CEP window tests run, see SKIP_NON_WINDOW_TESTS.');
		}
	}

	/**
	 * Skip the calling *Restart test when SKIP_RESTART_TESTS is enabled. The non-restart sibling
	 * leaves the system in the same asserted state, so dependents can rely on it instead.
	 */
	private function skipIfRestartTestsDisabled(): void {
		if (static::SKIP_RESTART_TESTS) {
			$this->markTestSkipped('Restart test variants disabled via SKIP_RESTART_TESTS.');
		}
	}

	/**
	 * Skip the calling flavour whose operation condition compares a tag value when SKIP_OPERATION_TAG_VALUE_TESTS is
	 * enabled. Its tag-name sibling asserts the same thing, so nothing about windows goes uncovered while it is off.
	 */
	private function skipIfOperationTagValueTestsDisabled(): void {
		if (static::SKIP_OPERATION_TAG_VALUE_TESTS) {
			$this->markTestSkipped(
				'Operation conditions comparing a tag value disabled via SKIP_OPERATION_TAG_VALUE_TESTS.'
			);
		}
	}

	/**
	 * Skip the calling service-specific test when SKIP_SERVICES_TESTS is enabled. Skipping the root
	 * testTriggerCEP_AddServices cascades to its dependents via @depends, so the suite runs without the
	 * per-trigger services and their actions.
	 */
	private function skipIfServicesTestsDisabled(): void {
		$skip_services_tests = static::SKIP_SERVICES_TESTS;

		if ($skip_services_tests === null) {
			$skip_services_tests = (time() % 2 === 0);
		}

		if ($skip_services_tests) {
			$this->markTestSkipped('Service test variants disabled via SKIP_SERVICES_TESTS.');
		}
	}

	private function buildItemLLDData(bool $with_parity = false): string {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');
		$data = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			$entry = [self::LLD_MACRO => $base.$i];
			if ($with_parity) {
				// Odd component index → '1', even → '0'. Consumed by the trigger prototype 'odd'
				// tag so each discovered problem carries its parity for parity-based correlation.
				$entry[self::PARITY_MACRO] = ($i % 2 === 1) ? '1' : '0';
			}
			$data[] = $entry;
		}
		return json_encode(['data' => $data]);
	}

	private function buildDiscoveredKeys(string $proto_key): array {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');
		$keys = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			$keys[] = $proto_key.'['.$base.$i.']';
		}
		return $keys;
	}

	/**
	 * Like buildDiscoveredKeys() but only the keys of components matching the given parity
	 * ('1' = odd index, '0' = even index), matching the {#PARITY} macro emitted by buildItemLLDData().
	 */
	private function buildDiscoveredKeysByParity(string $proto_key, string $parity): array {
		$base = rtrim(self::COMPONENT_VALUE, '0123456789');
		$keys = [];
		for ($i = 1; $i <= static::LLD_DISCOVERY_COUNT; $i++) {
			if ((($i % 2 === 1) ? '1' : '0') === $parity) {
				$keys[] = $proto_key.'['.$base.$i.']';
			}
		}
		return $keys;
	}

	/**
	 * Drop-in replacement for sendSenderValues() that delivers the values to the server as if they came
	 * from the active proxy (PROXY_NAME) instead of as direct sender/trapper data. Each value is given as
	 * ['host' => ..., 'key' => ..., 'value' => ...] (plus optional 'clock', 'ns' and 'state'); the host/key
	 * pair is translated to the item id the proxy data protocol requires and the batch is handed to
	 * dispatchValues(). The $component argument is accepted for call-site compatibility with
	 * sendSenderValues() but ignored — values always target the server through the proxy.
	 *
	 * Because the server skips preprocessing for proxy-delivered values, an item can only be reported as
	 * unsupported by setting 'state' => ITEM_STATE_NOTSUPPORTED explicitly (the value is then taken as the
	 * error text); a non-numeric value alone would be dropped rather than turning the item unsupported.
	 */
	protected function dispatchSenderValues($values, $component = null, $delayOverride = 0): array {
		$this->ensureItemidsResolved($values);

		$data = [];
		foreach (array_values($values) as $value) {
			// Fall back to a strictly increasing (clock, ns) so any value without an explicit timestamp is
			// still globally unique and ordered, even across batches.
			$cn = (!isset($value['clock']) || !isset($value['ns'])) ? $this->currentClockNs() : null;
			$entry = [
				'itemid' => self::$itemid_cache[$value['host']."\0".$value['key']],
				'value' => $value['value'],
				'clock' => isset($value['clock']) ? $value['clock'] : $cn['clock'],
				'ns' => isset($value['ns']) ? $value['ns'] : $cn['ns']
			];
			if (isset($value['state'])) {
				$entry['state'] = $value['state'];
			}
			$data[] = $entry;
		}

		// Trace what goes to the server, so a test log shows the exact values and their (clock, ns).
		/*foreach (array_values($values) as $i => $value) {
			fwrite(STDOUT, sprintf("send: %s:%s = %s (itemid %s, clock %d.%09d)%s", $value['host'],
				$value['key'], var_export($value['value'], true), $data[$i]['itemid'], $data[$i]['clock'],
				$data[$i]['ns'], PHP_EOL
			));
		}*/

		$this->dispatchValues($data, $delayOverride);

		// Return the enriched entries (with resolved itemid and the assigned clock/ns) so callers can keep a
		// reference of exactly what was sent and, on an event mismatch, pinpoint which (clock, ns) is missing.
		return $data;
	}

	/**
	 * Deliver item id based history values to the server impersonating the active proxy. Subclasses that
	 * run a real proxy daemon can override this to route the values through the proxy instead.
	 */
	protected function dispatchValues(array $values, $delayOverride = 0): void {
		$this->sendAgentDataValues($values, self::HOST_NAME, self::COMPONENT_SERVER, $delayOverride,
			self::PROXY_NAME);
	}

	/**
	 * Populate self::$itemid_cache for every host/key pair in $values that is not cached yet, then return.
	 * Idempotent and cheap to call on every dispatch: when all pairs are already cached it makes no API
	 * call at all. Discovered items and the master/log items are resolved via item.get; LLD rules (which
	 * item.get does not return) fall back to discoveryrule.get. Missing pairs are gathered first and
	 * resolved in bulk per host, so a batch of thousands of discovered keys costs a couple of API calls on
	 * first use and none afterwards.
	 */
	private function ensureItemidsResolved(array $values): void {
		$need = [];
		foreach ($values as $value) {
			$ck = $value['host']."\0".$value['key'];
			if (!isset(self::$itemid_cache[$ck])) {
				$need[$value['host']][$value['key']] = true;
			}
		}

		foreach ($need as $host => $keymap) {
			$hostid = $this->hostidByName($host);

			$response = $this->call('item.get', [
				'hostids' => [$hostid],
				'filter' => ['key_' => array_keys($keymap)],
				'output' => ['itemid', 'key_'],
				'webitems' => true
			]);
			foreach ($response['result'] as $item) {
				self::$itemid_cache[$host."\0".$item['key_']] = (int) $item['itemid'];
				unset($keymap[$item['key_']]);
			}

			if ($keymap) {
				$response = $this->call('discoveryrule.get', [
					'hostids' => [$hostid],
					'filter' => ['key_' => array_keys($keymap)],
					'output' => ['itemid', 'key_']
				]);
				foreach ($response['result'] as $rule) {
					self::$itemid_cache[$host."\0".$rule['key_']] = (int) $rule['itemid'];
					unset($keymap[$rule['key_']]);
				}
			}

			$this->assertEmpty($keymap,
				'Could not resolve item id(s) on host "'.$host.'" for proxy dispatch: '
					.implode(', ', array_keys($keymap)));
		}
	}

	private function hostidByName(string $host): int {
		if (!isset(self::$hostid_cache[$host])) {
			$response = $this->call('host.get', [
				'filter' => ['host' => $host],
				'output' => ['hostid']
			]);
			$this->assertCount(1, $response['result'], 'Host "'.$host.'" not found for proxy dispatch.');
			self::$hostid_cache[$host] = (int) $response['result'][0]['hostid'];
		}

		return self::$hostid_cache[$host];
	}

	/**
	 * Enable or disable a built-in action by name (used for the internal "Report unknown triggers"
	 * and "Report not supported items" actions).
	 */
	private function setInternalActionStatus(string $name, int $status): void {
		$response = $this->call('action.get', [
			'output' => ['actionid'],
			'filter' => ['name' => $name]
		]);
		$this->assertNotEmpty($response['result'], 'Action "'.$name.'" not found.');
		$this->call('action.update', [
			'actionid' => $response['result'][0]['actionid'],
			'status' => $status
		]);
	}

	/**
	 * Re-enable the built-in internal "Report not supported items" and "Report unknown triggers" actions
	 * and reload the configuration cache so the server starts generating internal item-not-supported /
	 * trigger-unknown events. Used by the *Unknown tests when SCOPED_INTERNAL_ACTIONS disables the built-in
	 * internal actions in prepareData().
	 */
	private function enableInternalActions(): void {
		$this->setInternalActionStatus('Report not supported items', ACTION_STATUS_ENABLED);
		$this->setInternalActionStatus('Report unknown triggers', ACTION_STATUS_ENABLED);

		$this->reloadConfigurationCacheAndWaitForLogLine();
	}

	/**
	 * Disable every internal-source action so the server generates no internal item-not-supported /
	 * trigger-unknown events. Called from prepareData() (to clear the built-in actions for the whole
	 * suite) and after the *Unknown tests to disable the actions they enabled via enableInternalActions().
	 */
	private function disableInternalActions(): void {
		$response = $this->call('action.get', [
			'output' => ['actionid'],
			'filter' => ['eventsource' => EVENT_SOURCE_INTERNAL]
		]);
		if (!empty($response['result'])) {
			$this->call('action.update', array_map(
				fn($actionid) => ['actionid' => $actionid, 'status' => ACTION_STATUS_DISABLED],
				array_column($response['result'], 'actionid')
			));
		}
	}

	/**
	 * Capture the highest internal-source eventid currently recorded and store it as the *Unknown cycle
	 * baseline. waitForInternalAlertsCompleted() then restricts its alert wait to the events generated after
	 * this point (eventid greater than the baseline), so the wait counts only this cycle's notifications
	 * regardless of how many internal-source alerts already accumulated in the database.
	 */
	private function captureInternalEventBaseline(): int {
		$response = $this->call('event.get', [
			'source' => EVENT_SOURCE_INTERNAL,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'limit' => 1,
			'output' => ['eventid']
		]);

		self::$internal_event_baseline_id = empty($response['result'])
			? 0 : (int) $response['result'][0]['eventid'];

		return self::$internal_event_baseline_id;
	}

	/**
	 * Wait until every notification generated by the current *Unknown cycle has been delivered, so that the
	 * caller can safely disable the internal actions without the escalator dropping a queued alert.
	 *
	 * runOpenUnknownTest() opens one internal problem per unsupported item and per unknown trigger
	 * (LLD_DISCOVERY_COUNT each) and runCloseUnknownTest() recovers all of them, so the internal actions add
	 * 2 * LLD_DISCOVERY_COUNT notifications for this cycle. Rather than trusting a total-count delta against
	 * a pre-cycle baseline, the alerts are anchored to the events generated after captureInternalEventBaseline():
	 * every internal event (problem and recovery) already exists by now (runCloseUnknownTest() waited for all
	 * problems to resolve), so their eventids are resolved here and the alert wait is restricted to them.
	 * alert.get has no eventid range filter, hence the explicit eventid list. We first wait for all of this
	 * cycle's alerts to be created (they lag the problem.get resolution the caller already checked, since the
	 * escalator produces them only after processing the events), then wait for none to be left queued.
	 */
	private function waitForInternalAlertsCompleted(): void {
		// Resolve the events generated by this cycle, i.e. those with an eventid past the baseline captured
		// in runOpenUnknownTest(). Their alerts are the only ones this wait may count.
		$response = $this->call('event.get', [
			'source' => EVENT_SOURCE_INTERNAL,
			'eventid_from' => self::$internal_event_baseline_id + 1,
			'output' => ['eventid']
		]);
		$eventids = array_column($response['result'], 'eventid');

		$expected_alerts = 2 * static::LLD_DISCOVERY_COUNT;

		// All notifications of this cycle must have been created ...
		$this->callUntilCountIsPresent('alert.get', [
			'eventsource' => EVENT_SOURCE_INTERNAL,
			'eventids' => $eventids
		], $expected_alerts, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// ... and none may still be queued for delivery (NEW or NOT_SENT).
		$this->callUntilCountIsPresent('alert.get', [
			'eventsource' => EVENT_SOURCE_INTERNAL,
			'eventids' => $eventids,
			'filter' => ['status' => [ALERT_STATUS_NEW, ALERT_STATUS_NOT_SENT]]
		], 0, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	private function validateTriggerParams($expected_state, $expected_value) {
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => self::$discovered_triggerids,
			'output' => ['triggerid', 'value', 'state']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($expected_state, $expected_value) {
			if (count($response['result']) !== static::LLD_DISCOVERY_COUNT) {
				return false;
			}
			foreach ($response['result'] as $trigger) {
				if ($trigger['state'] != $expected_state || $trigger['value'] != $expected_value) {
					return false;
				}
			}
			return true;
		});

		$this->assertCount(static::LLD_DISCOVERY_COUNT, $response['result']);
		foreach ($response['result'] as $trigger) {
			$this->assertEquals($expected_state, $trigger['state'], 'Unexpected trigger state.');
			$this->assertEquals($expected_value, $trigger['value'], 'Unexpected trigger value.');
		}
	}

	/**
	 * Capture the highest eventid currently recorded for the given triggers. The returned value
	 * is stored as the scenario baseline so that subsequent event.get queries only retrieve events
	 * generated after this point (see $event_baseline_id and waitForAllTriggerEventCounts), keeping
	 * the queries bounded regardless of how much event history has accumulated. Per-trigger event
	 * counts are then expressed as deltas relative to this baseline (so they start at 0).
	 */
	private function captureEventBaseline(array $triggerids): int {
		$response = $this->call('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'limit' => 1,
			'output' => ['eventid']
		]);

		$this->event_baseline_id = empty($response['result']) ? 0 : (int) $response['result'][0]['eventid'];

		return $this->event_baseline_id;
	}

	private function waitForTriggerEventCount(int $triggerid, int $expected_count): array {
		$response = $this->callUntilDataIsPresent('event.get', [
			'objectids' => [$triggerid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'output' => ['eventid', 'name', 'value', 'clock']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($expected_count) {
			return count($response['result']) === $expected_count;
		});
		return $response['result'];
	}

	/**
	 * Wait until exactly $expected_count events per trigger have been generated since the scenario
	 * baseline. All triggers are fed identical values, so their per-trigger counts move together;
	 * waiting on the total lets the server aggregate (countOutput) instead of fetching and counting
	 * every event row on each poll iteration. callUntilCountIsPresent requires exact equality, so a
	 * missing or extra event on any trigger keeps the total off-target and fails the wait.
	 */
	private function waitForAllTriggerEventCounts(array $triggerids, int $expected_count,
			?callable $info_callback = null): void {
		// eventid_from is inclusive, so +1 excludes the baseline event itself. A target of 0
		// (no state change expected) is handled too: the count returns 0 immediately, and any
		// spurious event keeps it off-target and fails the wait. $info_callback (if given) is invoked
		// only on the final failed iteration to append a diagnostic to the failure message.
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1
		], count($triggerids) * $expected_count,
			self::STATE_CHANGE_WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, null, $info_callback
		);
	}

	/**
	 * Fetch the events generated since the scenario baseline, grouped by trigger (newest first).
	 * Only needed where the event values themselves are asserted; most callers just wait on the
	 * count via waitForAllTriggerEventCounts().
	 */
	private function getScenarioEventsByTrigger(array $triggerids): array {
		$response = $this->call('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'sortfield' => 'eventid',
			'sortorder' => 'DESC',
			'output' => ['value', 'objectid']
		]);

		$events_by_trigger = array_fill_keys($triggerids, []);
		foreach ($response['result'] as $event) {
			$events_by_trigger[(int) $event['objectid']][] = $event;
		}
		return $events_by_trigger;
	}

	/**
	 * Build a human-readable diagnostic for a burst that produced the wrong number of events. Each sent
	 * value in $sent (as returned by dispatchSenderValues(), carrying the assigned clock/ns) is expected to
	 * flip the trigger and thus produce exactly one event with the same (clock, ns). This fetches the
	 * events generated since the baseline and reports, per sent offset, which (clock, ns) never produced an
	 * event and which events were emitted with no matching sent value, so a dropped or duplicated value is
	 * pinned to its exact offset and timestamp instead of surfacing only as a count mismatch.
	 */
	private function diagnoseMissingBurstEvents(int $triggerid, array $sent): string {
		$response = $this->call('event.get', [
			'objectids' => [$triggerid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'sortfield' => ['clock', 'eventid'],
			'sortorder' => 'ASC',
			'output' => ['eventid', 'value', 'clock', 'ns']
		]);

		// Index the emitted events by their (clock, ns) so each sent value can be looked up directly.
		$events_by_key = [];
		foreach ($response['result'] as $event) {
			$events_by_key[$event['clock']."\0".$event['ns']][] = $event;
		}

		$lines = ['event diagnostic for trigger '.$triggerid.': sent '.count($sent).' value(s), got '
			.count($response['result']).' event(s)'];

		foreach ($sent as $offset => $entry) {
			$key = $entry['clock']."\0".$entry['ns'];
			if (isset($events_by_key[$key]) && $events_by_key[$key] !== []) {
				array_shift($events_by_key[$key]);
			}
			else {
				$lines[] = 'MISSING event for offset '.$offset.' value='.$entry['value']
					.' clock='.$entry['clock'].' ns='.$entry['ns'];
			}
		}

		// Any events left over matched no sent value (e.g. a duplicate emitted for one value).
		foreach ($events_by_key as $key => $leftover) {
			foreach ($leftover as $event) {
				$lines[] = 'UNEXPECTED event eventid='.$event['eventid'].' value='.$event['value']
					.' clock='.$event['clock'].' ns='.$event['ns'].' (no matching sent value)';
			}
		}

		return implode("\n", $lines);
	}

	/**
	 * Wait until exactly $expected PROBLEM events since the scenario baseline carry the $tag tag on
	 * $triggerids. Used to verify that tags returned by a webhook media type are applied to the events they
	 * were generated for. Uses a server-side count (countOutput + a tag-exists filter) instead of fetching
	 * every event and its tags, so the query cost stays flat regardless of how many events were generated.
	 */
	private function waitForProblemEventsTagged(array $triggerids, string $tag, int $expected): void {
		$this->callUntilCountIsPresent('event.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventid_from' => $this->event_baseline_id + 1,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'tags' => [['tag' => $tag, 'operator' => TAG_OPERATOR_EXISTS]]
		], $expected, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	private function getTriggers(array $triggerids): array {
		$response = $this->call('trigger.get', [
			'triggerids' => $triggerids,
			'output' => ['triggerid', 'value', 'lastchange', 'state', 'recovery_mode', 'type', 'correlation_mode']
		]);
		return array_column($response['result'], null, 'triggerid');
	}

	/**
	 * Assert that every trigger currently has the expected value. Instead of failing on the first
	 * mismatch, all triggers are checked and the failure message reports how many were correct, how
	 * many were wrong and the full data of every wrong one.
	 */
	private function assertAllTriggerValues(array $triggerids, int $expected_value, string $info): void {
		$triggers = $this->getTriggers($triggerids);
		$wrong = [];
		foreach ($triggerids as $idx => $triggerid) {
			if (!isset($triggers[$triggerid]) || (int) $triggers[$triggerid]['value'] !== $expected_value) {
				$wrong[] = 'trigger #'.$idx.': '
					.(isset($triggers[$triggerid]) ? json_encode($triggers[$triggerid]) : 'missing from trigger.get');
			}
		}
		$this->assertCount(0, $wrong, $info.': expected value '.$expected_value.' on all '.count($triggerids)
			.' triggers, '.(count($triggerids) - count($wrong)).' correct, '.count($wrong).' wrong: '
			.implode('; ', $wrong));
	}

	/**
	 * Assert that sending $item_value produces a new event but trigger stays PROBLEM.
	 * Used for partial tag-correlation recoveries where one tagged problem closes while
	 * another remains open, so the trigger value stays TRUE and lastchange is not updated.
	 */
	private function assertPartialRecoveryForAll(array $triggerids, array $keys, string $item_value,
			int $expected_event_count): void {
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value], $keys)
		);
		$this->waitForAllTriggerEventCounts($triggerids, $expected_event_count);
		$events_by_trigger = $this->getScenarioEventsByTrigger($triggerids);
		$triggers = $this->getTriggers($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$trigger = $triggers[$triggerid];
			$info = 'trigger #'.$idx.' after '.$item_value.': '.json_encode($trigger);
			$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value'], $info);
			$this->assertEquals(TRIGGER_STATE_NORMAL, $trigger['state'], $info);
			$this->assertEquals(TRIGGER_VALUE_FALSE, $events_by_trigger[$triggerid][0]['value'], $info);
		}
	}

	private function assertStateChangeForAll(array $triggerids, array $keys, string $item_value,
			int $expected_trigger_value, int $expected_event_count, bool $check_lastchange = true): void {
		$now = time();
		$prev_triggers = $this->getTriggers($triggerids);
		$prev_lastchanges = array_map(fn($tid) => $prev_triggers[$tid]['lastchange'], $triggerids);
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value], $keys)
		);

		$trigger_params = [
			'triggerids' => $triggerids,
			'output' => ['triggerid', 'value', 'lastchange', 'state', 'recovery_mode', 'type', 'correlation_mode']
		];
		$this->callUntilDataIsPresent('trigger.get', $trigger_params,
			self::STATE_CHANGE_WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($triggerids, $expected_trigger_value, $prev_lastchanges, $now, $check_lastchange) {
				$by_id = array_column($response['result'], null, 'triggerid');
				$missing = 0;
				$wrong_value = 0;
				$wrong_state = 0;
				$wrong_lastchange = 0;
				$failing_triggerid = null;
				foreach ($triggerids as $idx => $triggerid) {
					if (!isset($by_id[$triggerid])) {
						$missing++;
						if ($failing_triggerid === null) {
							$failing_triggerid = $triggerid;
						}
						continue;
					}
					$t = $by_id[$triggerid];
					$wrong = false;
					if ((int) $t['value'] !== $expected_trigger_value) {
						$wrong_value++;
						$wrong = true;
					}
					if ((int) $t['state'] !== TRIGGER_STATE_NORMAL) {
						$wrong_state++;
						$wrong = true;
					}
					if ($check_lastchange && $now > $prev_lastchanges[$idx]
							&& (int) $t['lastchange'] <= $prev_lastchanges[$idx]) {
						$wrong_lastchange++;
						$wrong = true;
					}
					if ($wrong && $failing_triggerid === null) {
						$failing_triggerid = $triggerid;
					}
				}
				if ($missing > 0 || $wrong_value > 0 || $wrong_state > 0 || $wrong_lastchange > 0) {
					$last_event_name = '<none>';
					$events = $this->call('event.get', [
						'objectids' => [$failing_triggerid],
						'source' => EVENT_SOURCE_TRIGGERS,
						'object' => EVENT_OBJECT_TRIGGER,
						'output' => ['name'],
						'sortfield' => ['clock', 'eventid'],
						'sortorder' => ZBX_SORT_DOWN,
						'limit' => 1
					]);
					if (!empty($events['result'])) {
						$last_event_name = $events['result'][0]['name'];
					}
					return 'of '.count($triggerids).' triggers: '.$missing.' missing, '.$wrong_value.
							' wrong value (expected '.$expected_trigger_value.'), '.$wrong_state.
							' wrong state (expected NORMAL), '.$wrong_lastchange.' lastchange not updated now:'.$now.
							'; last event of failing trigger '.$failing_triggerid.': "'.$last_event_name.'"';
				}
				return true;
			}
		);

		$this->waitForAllTriggerEventCounts($triggerids, $expected_event_count);
	}

	private function assertNoStateChangeForAll(array $triggerids, array $keys, string $item_value,
			int $expected_trigger_value, int $expected_event_count): void {
		$current_triggers = $this->getTriggers($triggerids);

		$vps_written = $this->getVpsWritten();
		//$cep_processed = $this->getCepStat('events', 'assessed');
		$expected_lastchanges = array_map(fn($tid) => $current_triggers[$tid]['lastchange'], $triggerids);
		$this->dispatchSenderValues(
			array_map(fn($key) => ['host' => self::HOST_DISC_VALUE, 'key' => $key, 'value' => $item_value], $keys)
		);

		$this->assertVpsWrittenIncreasedBy($vps_written, count($keys));
		//$this->assertCepStatIncreasedBy('events', 'assessed', $cep_processed, count($keys));

		// The count is enforced by the wait itself (exact total match); no per-trigger fetch needed.
		$this->waitForAllTriggerEventCounts($triggerids, $expected_event_count);
		$triggers_by_id = $this->getTriggers($triggerids);

		foreach ($triggerids as $idx => $triggerid) {
			$trigger = $triggers_by_id[$triggerid];
			$info = 'trigger #'.$idx.' '.json_encode($trigger);
			$this->assertEquals($expected_trigger_value, $trigger['value'], $info);
			$this->assertEquals($expected_lastchanges[$idx], $trigger['lastchange'], $info);
		}
	}

	private function waitForNoOpenProblems(array $triggerids, string $message = '', bool $wait_cep_drained = true,
			?int $iterations = null): void {
		// Wait for all problems to have a recovery event.
		// Wait until no unresolved problems remain. Using countOutput avoids fetching/decoding any
		// problem rows: the server returns just a count, and we poll until it reaches zero. (Default
		// problem.get without 'recent' returns only open problems, so count 0 means all recovered.)
		//
		// $iterations replaces the patience of this one wait, for the scenarios whose closing is due at a time they
		// know rather than as soon as a value has been processed: what they pass is that time and no more, so a
		// closing that does not happen is reported when it was due instead of at the end of a generic patience - see
		// runEventAssessmentTestCepWindowCauseSymptomCloseOnDuration().
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS
		], 0, $iterations === null ? static::WAIT_ITERATIONS : $iterations, self::WAIT_ITERATION_DELAY, null,
			$message === '' ? null : function () use ($message) {
				return $message;
			}
		);

		// Wait for all triggers to return to OK.
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => $triggerids,
			'output' => ['value', 'state']
		], static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) use ($triggerids) {
			$expected = count($triggerids);

			if (count($response['result']) !== $expected) {
				return 'expected '.$expected.' triggers, got '.count($response['result']);
			}

			// A trigger is only OK when its value is FALSE and its state is NORMAL: a trigger left in
			// UNKNOWN (e.g. after an unsupported item) is not yet recovered even with value FALSE.
			$ok = 0;
			foreach ($response['result'] as $trigger) {
				if ((int) $trigger['value'] === TRIGGER_VALUE_FALSE
						&& (int) $trigger['state'] === TRIGGER_STATE_NORMAL) {
					$ok++;
				}
			}

			if ($ok !== $expected) {
				return 'expected '.$expected.' triggers in OK state, got '.$ok;
			}

			return true;
		});

		$this->assertTriggersValueAndState($triggerids, TRIGGER_VALUE_FALSE, 'trigger after recovery');

		// With no open problems left, CEP should have drained its cached events back to zero.
		// Skip when other problems may still be open elsewhere in the system (cache.events is global).
		if ($wait_cep_drained) {
			$this->assertCepStatEquals('cache', 'events', 0);
			$this->assertCepStatEquals('cache', 'objects', 0);
			$this->assertCepNoWindows();
		}
	}

	private function assertTriggersValueAndState(array $triggerids, int $expected_value, string $label): void {
		$triggers = $this->getTriggers($triggerids);

		// Count how many triggers are off so the failure message reports the scale of the mismatch, not
		// just the first offending trigger.
		$wrong_value = 0;
		$wrong_state = 0;
		foreach ($triggerids as $triggerid) {
			if ((int) $triggers[$triggerid]['value'] !== $expected_value) {
				$wrong_value++;
			}
			if ((int) $triggers[$triggerid]['state'] !== TRIGGER_STATE_NORMAL) {
				$wrong_state++;
			}
		}

		$total = count($triggerids);
		foreach ($triggerids as $idx => $triggerid) {
			$trigger = $triggers[$triggerid];
			$info = $label.' #'.$idx.' ('.$wrong_value.'/'.$total.' wrong value, '.$wrong_state.'/'.$total
					.' wrong state): '.json_encode($trigger);
			$this->assertEquals($expected_value, $trigger['value'], $info);
			$this->assertEquals(TRIGGER_STATE_NORMAL, $trigger['state'], $info);
		}
	}

	/**
	 * Poll the per-trigger CEP services until every one reports the expected status, then assert the
	 * status and the number of open service problems. Each service has a single problem tag
	 * (SERVICE_TAG=<component>) matching its trigger's events, so the service status and its service
	 * problem events follow the trigger's problem state through the service manager:
	 * TRIGGER_SEVERITY_DISASTER with one open problem per service while the trigger problem is open,
	 * and ZBX_SEVERITY_OK with no open problems once it recovers.
	 *
	 * @param int    $expected_status        ZBX_SEVERITY_* expected for every service
	 * @param int    $expected_open_problems open service problems expected across all services
	 */
	private function assertServicesStatus(int $expected_status, int $expected_open_problems): void {
		$serviceids = self::$serviceids;

		// Service status reflects the matched trigger problem severity (or OK after recovery). The poll
		// fails if all services do not reach $expected_status in time.
		$this->callUntilCountIsPresent('service.get', [
			'serviceids' => $serviceids,
			'filter' => ['status' => $expected_status]
		], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// One open service problem per service while in problem state; none after recovery. The poll
		// fails if the open service problem count does not reach $expected_open_problems in time.
		$this->callUntilCountIsPresent('problem.get', [
			'objectids' => $serviceids,
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE
		], $expected_open_problems, static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Assert that every per-trigger service has exactly one open service problem, i.e. the service manager
	 * created a single service problem per matched service and did not add the same event to a service more
	 * than once. This is a regression guard for duplicated service problems: the aggregate count checked by
	 * assertServicesStatus() can be satisfied by an uneven distribution (one service with two problems and
	 * another with none), so the per-service breakdown is verified explicitly here.
	 */
	private function assertOneServiceProblemPerService(): void {
		$serviceids = self::$serviceids;

		if (empty($serviceids)) {
			return;
		}

		$response = $this->call('problem.get', [
			'objectids' => $serviceids,
			'object' => EVENT_OBJECT_SERVICE,
			'source' => EVENT_SOURCE_SERVICE,
			'output' => ['eventid', 'objectid']
		]);

		$counts = [];
		foreach ($response['result'] as $problem) {
			$objectid = $problem['objectid'];
			$counts[$objectid] = isset($counts[$objectid]) ? $counts[$objectid] + 1 : 1;
		}

		foreach ($serviceids as $serviceid) {
			$count = isset($counts[$serviceid]) ? $counts[$serviceid] : 0;
			$this->assertSame(1, $count, 'Service '.$serviceid.' must have exactly one open service problem, '.
				'got '.$count.'. Open service problems: '.json_encode($response['result']));
		}
	}

	/**
	 * Poll the per-trigger CEP services until every one reports $expected_status (a ZBX_SEVERITY_* value,
	 * or ZBX_SEVERITY_OK once recovered). Unlike assertServicesStatus() this only checks the status, so it
	 * can be used after a manual problem-severity change where the open service problem count is irrelevant.
	 * A no-op when no services exist (service tests disabled), so the caller runs as usual either way.
	 */
	private function waitForServicesStatus(int $expected_status): void {
		$serviceids = self::$serviceids;

		if (empty($serviceids)) {
			return;
		}

		$this->callUntilCountIsPresent('service.get', [
			'serviceids' => $serviceids,
			'filter' => ['status' => $expected_status]
		], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Poll the web-tag services (created by createWebTagServices) until every one reports $expected_status.
	 * Unlike the per-trigger CEP services these are matched to problems only via the webhook-applied
	 * WEB_COMPONENT_TAG tag, so reaching a problem status here proves the tags returned by the media type
	 * were applied to the open problem events and picked up by the service manager.
	 */
	private function waitForWebTagServicesStatus(int $expected_status): void {
		$serviceids = self::$web_tag_serviceids;

		$this->assertNotEmpty($serviceids, 'Web-tag services must be created before waiting for their status.');

		try {
			$this->callUntilCountIsPresent('service.get', [
				'serviceids' => $serviceids,
				'filter' => ['status' => $expected_status]
			], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		} catch (Exception $e) {
			$response = $this->call('service.get', [
				'serviceids' => $serviceids,
				'output' => ['serviceid', 'status']
			]);
			$wrong = array_values(array_filter($response['result'],
				fn($service) => (int) $service['status'] !== $expected_status
			));
			throw new Exception('Expected all '.count($serviceids).' web-tag services to have status '
				.$expected_status.', but '.count($wrong).' differ, first (max 5): '
				.json_encode(array_slice($wrong, 0, 5)).'. '.$e->getMessage());
		}
	}

	private function waitForServicesSuppressed(): void {
		$serviceids = self::$serviceids;

		if (empty($serviceids)) {
			return;
		}

		try {
			$this->callUntilCountIsPresent('service.get', [
				'serviceids' => $serviceids,
				'filter' => ['status' => -1]
			], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		} catch (Exception $e) {
			$response = $this->call('service.get', [
				'serviceids' => $serviceids,
				'output' => ['serviceid', 'status']
			]);
			if (!empty($response['result'])) {
				$status = $response['result'][0]['status'];
				throw new Exception('Expected all services to have status -1 (OK), but got status '.$status.' for service '.$response['result'][0]['serviceid'].'. '.$e->getMessage());
			}
			throw $e;
		}
	}

	private function waitForServicesNoLongerSuppressed(): void {
		$serviceids = self::$serviceids;

		if (empty($serviceids)) {
			return;
		}

		$this->callUntilCountIsPresent('service.get', [
			'serviceids' => $serviceids,
			'filter' => ['status' => [
				TRIGGER_SEVERITY_NOT_CLASSIFIED,
				TRIGGER_SEVERITY_INFORMATION,
				TRIGGER_SEVERITY_WARNING,
				TRIGGER_SEVERITY_AVERAGE,
				TRIGGER_SEVERITY_HIGH,
				TRIGGER_SEVERITY_DISASTER
			]]
		], count($serviceids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Build a map of component value => serviceid for the CEP services, read from each service's
	 * SERVICE_TAG problem-tag value (the tag by which a service is matched to its component's trigger).
	 * Returns an empty array when no services exist (service tests skipped).
	 */
	private function getServiceIdsByComponent(): array {
		if (empty(self::$serviceids)) {
			return [];
		}

		$response = $this->call('service.get', [
			'serviceids' => self::$serviceids,
			'output' => ['serviceid'],
			'selectProblemTags' => ['tag', 'value']
		]);

		$service_by_component = [];
		foreach ($response['result'] as $service) {
			$service_tag = current(array_filter($service['problem_tags'],
				fn($t) => $t['tag'] === self::SERVICE_TAG
			));

			if ($service_tag !== false) {
				$service_by_component[$service_tag['value']] = $service['serviceid'];
			}
		}

		return $service_by_component;
	}

	/**
	 * Wait until exactly the services of $suppressed_components are suppressed (status -1, i.e. OK because
	 * all their problems are suppressed) while every other CEP service shows a problem severity (its
	 * problems are no longer suppressed). $service_by_component maps a component value to its serviceid.
	 * No-op when there are no services (service tests skipped).
	 */
	private function waitForServicesSuppressedForComponents(array $service_by_component,
			array $suppressed_components): void {
		if (empty($service_by_component)) {
			return;
		}

		$suppressed_ids = [];
		$unsuppressed_ids = [];
		foreach ($service_by_component as $component => $serviceid) {
			if (in_array($component, $suppressed_components, true)) {
				$suppressed_ids[] = $serviceid;
			}
			else {
				$unsuppressed_ids[] = $serviceid;
			}
		}

		if (!empty($suppressed_ids)) {
			$this->callUntilCountIsPresent('service.get', [
				'serviceids' => $suppressed_ids,
				'filter' => ['status' => -1]
			], count($suppressed_ids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		}

		if (!empty($unsuppressed_ids)) {
			$this->callUntilCountIsPresent('service.get', [
				'serviceids' => $unsuppressed_ids,
				'filter' => ['status' => [
					TRIGGER_SEVERITY_NOT_CLASSIFIED,
					TRIGGER_SEVERITY_INFORMATION,
					TRIGGER_SEVERITY_WARNING,
					TRIGGER_SEVERITY_AVERAGE,
					TRIGGER_SEVERITY_HIGH,
					TRIGGER_SEVERITY_DISASTER
				]]
			], count($unsuppressed_ids), static::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);
		}
	}

	/**
	 * Collect every open problem on $triggerids and manually change its severity to $severity via
	 * event.acknowledge (ZBX_PROBLEM_UPDATE_SEVERITY), so the service manager recomputes the status of the
	 * services matched to those problems.
	 */
	private function updateOpenProblemsSeverity(array $triggerids, int $severity): void {
		$response = $this->call('problem.get', [
			'objectids' => $triggerids,
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'output' => ['eventid']
		]);
		$eventids = array_column($response['result'], 'eventid');
		$this->assertNotEmpty($eventids, 'Expected open problems to update severity for, found none.');

		$this->call('event.acknowledge', [
			'eventids' => $eventids,
			'action' => ZBX_PROBLEM_UPDATE_SEVERITY,
			'severity' => $severity
		]);
	}

	private function currentClockNs(): array {
		static $ns = 0;

		// Use a monotonically increasing ns starting from 0, so no two returned values ever collide.
		return ['clock' => time(), 'ns' => $ns++];
	}

	private function getApiSessionId(): string {
		if (self::$sessionid === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
			self::$sessionid = CAPIHelper::getSessionId();
		}
		else {
			CAPIHelper::setSessionId(self::$sessionid);
		}

		return self::$sessionid;
	}

	private function testItemOnServer(string $hostid, string $sid, array $item,
			array $options = ['single' => false, 'state' => 0]): array|false {
		$response = $this->call('host.get', [
			'hostids' => [$hostid],
			'output' => ['maintenance_status', 'maintenance_type']
		]);
		$this->assertCount(1, $response['result']);
		$host = $response['result'][0];

		$data = [
			'options' => $options,
			'item' => $item,
			'host' => [
				'hostid' => $hostid,
				'maintenance_status' => $host['maintenance_status'],
				'maintenance_type' => $host['maintenance_type']
			]
		];

		return $this->getClient(self::COMPONENT_SERVER)->testItem($data, $sid);
	}

	private function getVpsWritten(): int {
		$result = $this->testItemOnServer((string) self::$hostid, $this->getApiSessionId(),
			['value_type' => '3', 'type' => '5', 'key' => 'zabbix[vps,written]']
		);
		$this->assertNotFalse($result);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertIsNumeric($result['item']['result']);

		return (int) $result['item']['result'];
	}

	private function assertVpsWrittenIncreasedBy(int $baseline, int $min_increase): void {
		$expected = $baseline + $min_increase;

		$this->callTestItemUntilCallback(
			['value_type' => '3', 'type' => '5', 'key' => 'zabbix[vps,written]'],
			function ($result) use ($expected) {
				return $result !== false && isset($result['item']['result'])
						&& is_numeric($result['item']['result']) && (int) $result['item']['result'] >= $expected;
			}
		);
	}

	/**
	 * Query the server's internal zabbix["cep"] item and return the decoded CEP statistics:
	 *   ['events' => ['assessed' => N, 'processed' => N, 'discarded' => N],
	 *    'tasks'  => ['remote' => N, 'internal' => N, 'completed' => N]]
	 */
	private function getCepStats(): array {
		$result = $this->testItemOnServer((string) self::$hostid, $this->getApiSessionId(),
			['value_type' => '4', 'type' => '5', 'key' => 'zabbix["cep"]']
		);
		$this->assertNotFalse($result);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);

		$stats = json_decode($result['item']['result'], true);
		$this->assertIsArray($stats, 'zabbix["cep"] did not return valid JSON: '.json_encode($result['item']));

		return $stats;
	}

	/**
	 * Read a single numeric CEP statistic, e.g. getCepStat('events', 'processed').
	 */
	private function getCepStat(string $group, string $name): int {
		$stats = $this->getCepStats();
		$this->assertArrayHasKey($group, $stats, 'zabbix["cep"] stats missing group: '.json_encode($stats));
		$this->assertArrayHasKey($name, $stats[$group], 'zabbix["cep"] stats missing '.$group.'.'.$name);
		$this->assertIsNumeric($stats[$group][$name]);

		return (int) $stats[$group][$name];
	}

	/**
	 * Extract a single numeric CEP statistic from a testItem() response, or null if it is not available.
	 */
	private function cepStatFromResult($result, string $group, string $name): ?int {
		if ($result === false || !isset($result['item']['result'])) {
			return null;
		}

		$stats = json_decode($result['item']['result'], true);

		return (isset($stats[$group][$name]) && is_numeric($stats[$group][$name]))
				? (int) $stats[$group][$name] : null;
	}

	/**
	 * Poll the zabbix["cep"] statistics until the given counter reaches $baseline + $min_increase, then
	 * assert it. Mirrors assertVpsWrittenIncreasedBy.
	 */
	private function assertCepStatIncreasedBy(string $group, string $name, int $baseline, int $min_increase): void {
		$expected = $baseline + $min_increase;

		$this->callTestItemUntilCallback(
			['value_type' => '4', 'type' => '5', 'key' => 'zabbix["cep"]'],
			function ($result) use ($group, $name, $expected) {
				$value = $this->cepStatFromResult($result, $group, $name);

				return $value !== null && $value >= $expected;
			}
		);
	}

	/**
	 * Poll the zabbix["cep"] statistics until the given counter equals $expected, then assert it.
	 */
	private function assertCepStatEquals(string $group, string $name, int $expected): void {
		$this->callTestItemUntilCallback(
			['value_type' => '4', 'type' => '5', 'key' => 'zabbix["cep"]'],
			function ($result) use ($group, $name, $expected) {
				return $this->cepStatFromResult($result, $group, $name) === $expected;
			},
			['single' => false, 'state' => 0],
			null,
			// Surface up to 10 still-open problems to help diagnose why the counter did not settle: an open
			// problem is what keeps an event cached, and a cached event what keeps its window alive.
			function () use ($group, $name, $expected) {
				$response = $this->call('problem.get', [
					'object' => EVENT_OBJECT_TRIGGER,
					'source' => EVENT_SOURCE_TRIGGERS,
					'output' => ['eventid', 'objectid', 'name', 'clock'],
					'limit' => 10
				]);

				// If no trigger-source problem is open, fall back to any open problem (e.g. internal-source) so
				// the diagnostics are not empty when the cache is held open by a non-trigger problem.
				if (empty($response['result'])) {
					$response = $this->call('problem.get', [
						'output' => ['eventid', 'source', 'object', 'objectid', 'name', 'clock'],
						'limit' => 10
					]);
				}

				return ' CEP '.$group.'.'.$name.' did not reach '.$expected.
						'. Open problems (max 10): '.json_encode($response['result']);
			}
		);
	}

	/**
	 * Poll the zabbix["cep"] statistics until no CEP window is left in the window pool.
	 *
	 * A window exists only as long as it holds events, so this belongs beside the assertions that the cache
	 * drained: with nothing cached no window can be holding anything, and an emptied window is dropped from
	 * the pool at its next examination (see cep_window_pool_remove_window()). That examination is what the
	 * polling waits out - a window that survives it is one the scenario left behind.
	 */
	private function assertCepNoWindows(): void {
		$this->assertCepStatEquals('windows', 'total', 0);
	}

	public static function clearData(): void {
		if (!empty(self::$service_actionid)) {
			CDataHelper::call('action.delete', [self::$service_actionid]);
			self::$service_actionid = null;
		}

		if (!empty(self::$trigger_actionid)) {
			CDataHelper::call('action.delete', [self::$trigger_actionid]);
			self::$trigger_actionid = null;
		}

		// Remove the extra-tag webhook actions (created by createExtraTagWebhookAction) in case a test
		// aborted before its own teardown ran.
		if (!empty(self::$tag_actionid)) {
			CDataHelper::call('action.delete', [self::$tag_actionid]);
			self::$tag_actionid = null;
		}

		if (!empty(self::$tag_actionid2)) {
			CDataHelper::call('action.delete', [self::$tag_actionid2]);
			self::$tag_actionid2 = null;
		}

		// Detach the media from the Admin user before deleting the media types they reference.
		if (!empty(self::$mediatypeid) || !empty(self::$tag_mediatypeid) || !empty(self::$tag_mediatypeid2)) {
			CDataHelper::call('user.update', ['userid' => 1, 'medias' => []]);

			if (!empty(self::$tag_mediatypeid)) {
				CDataHelper::call('mediatype.delete', [self::$tag_mediatypeid]);
				self::$tag_mediatypeid = null;
			}

			if (!empty(self::$tag_mediatypeid2)) {
				CDataHelper::call('mediatype.delete', [self::$tag_mediatypeid2]);
				self::$tag_mediatypeid2 = null;
			}

			if (!empty(self::$mediatypeid)) {
				CDataHelper::call('mediatype.delete', [self::$mediatypeid]);
				self::$mediatypeid = null;
			}
		}

		if (!empty(self::$serviceids)) {
			CDataHelper::call('service.delete', self::$serviceids);
			self::$serviceids = [];
		}

		// Remove the web-tag services (created by createWebTagServices) in case a test aborted before its
		// own teardown ran.
		if (!empty(self::$web_tag_serviceids)) {
			CDataHelper::call('service.delete', self::$web_tag_serviceids);
			self::$web_tag_serviceids = [];
		}

		// The same for the service of the tag driven service scenario.
		if (self::$cep_tag_serviceid !== null) {
			CDataHelper::call('service.delete', [self::$cep_tag_serviceid]);
			self::$cep_tag_serviceid = null;
		}

		if (!empty(self::$correlationid)) {
			CDataHelper::call('correlation.delete', [self::$correlationid]);
			self::$correlationid = null;
		}

		if (!empty(self::$correlationid2)) {
			CDataHelper::call('correlation.delete', [self::$correlationid2]);
			self::$correlationid2 = null;
		}

		// Remove the CEP rules (created by prepareDataCepWindowTagCorrelationCloseOnUp) in case a test aborted
		// before its own teardown ran; a rule left behind would keep closing problems of later suites.
		$cep_rules = CDataHelper::call('ceprule.get', [
			'output' => ['cep_ruleid'],
			'search' => ['name' => self::CEP_RULE_NAME_PREFIX]
		]);
		if (!empty($cep_rules)) {
			CDataHelper::call('ceprule.delete', array_column($cep_rules, 'cep_ruleid'));
		}
		self::$cep_ruleid = null;

		// Remove the window limit macros the close window and operations flavours give their windows (created by
		// macroizeWindowLimits()): they belong to no host and are not deleted with the rules that used them, so
		// nothing else would take them away. The macros of the operations themselves need no such cleanup - they
		// are on the template and go with it.
		$macros = CDataHelper::call('usermacro.get', [
			'globalmacro' => true,
			'filter' => ['macro' => [self::CEP_WINDOW_DURATION_MACRO, self::CEP_WINDOW_CAPACITY_MACRO]],
			'output' => ['globalmacroid']
		]);
		if (!empty($macros)) {
			CDataHelper::call('usermacro.deleteglobal', array_column($macros, 'globalmacroid'));
		}

		// stopDiscHostMaintenance() only pushes the maintenance out of its active window; delete it for real
		// here (before its host) so it does not leak into later suites.
		if (!empty(self::$disc_maintenanceids)) {
			CDataHelper::call('maintenance.delete', self::$disc_maintenanceids);
			self::$disc_maintenanceids = [];
		}

		if (!empty(self::$disc_hostid)) {
			CDataHelper::call('host.delete', [self::$disc_hostid]);
			self::$disc_hostid = null;
		}

		if (!empty(self::$hostid)) {
			CDataHelper::call('host.delete', [self::$hostid]);
			self::$hostid = null;
		}

		// Deleted after the hosts it monitors (self::$hostid and the discovered host) have been removed,
		// since a proxy with assigned hosts cannot be deleted.
		if (!empty(self::$proxyid)) {
			CDataHelper::call('proxy.delete', [self::$proxyid]);
			self::$proxyid = null;
		}

		self::$hostid_cache = [];
		self::$itemid_cache = [];

		// Deleted after the host it is linked to (self::$hostid) has been removed.
		if (!empty(self::$log_templateid)) {
			CDataHelper::call('template.delete', [self::$log_templateid]);
			self::$log_templateid = null;
		}

		if (!empty(self::$templateid)) {
			CDataHelper::call('template.delete', [self::$templateid]);
			self::$templateid = null;
		}

		if (self::SCOPED_INTERNAL_ACTIONS) {
			// Disable any internal-source actions the *Unknown tests enabled (in case one aborted before
			// restoring them), returning to the all-internal-actions-disabled state prepareData() set up.
			// The built-in actions must not be deleted here: enableInternalActions() re-enables them by name,
			// so deleting them would make the next *Unknown run fail to find them.
			$result = CDataHelper::call('action.get', [
				'output' => ['actionid'],
				'filter' => ['eventsource' => EVENT_SOURCE_INTERNAL]
			]);
			if (!empty($result)) {
				CDataHelper::call('action.update', array_map(
					fn($actionid) => ['actionid' => $actionid, 'status' => ACTION_STATUS_DISABLED],
					array_column($result, 'actionid')
				));
			}
		}
		else {
			// Disable the internal actions again in case a test enabled them and aborted before restoring.
			foreach (['Report unknown triggers', 'Report not supported items'] as $action_name) {
				$result = CDataHelper::call('action.get', [
					'output' => ['actionid'],
					'filter' => ['name' => $action_name]
				]);
				if (!empty($result)) {
					CDataHelper::call('action.update', [
						'actionid' => $result[0]['actionid'],
						'status' => ACTION_STATUS_DISABLED
					]);
				}
			}
		}

		// Re-enable audit log disabled in prepareData().
		CDataHelper::call('settings.update', ['auditlog_enabled' => 1, 'auditlog_mode' => 1]);
	}
}
