<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The Dashboard arrangement is shared across every UPS, but graph cards are
 * keyed by local_graph_id, which only exists on one UPS's device. The fixed
 * cards are therefore shared while each UPS keeps its own graph order, and a
 * layout from the earlier per-UPS storage is persisted to the shared row the
 * first time it is read.
 */

if (!function_exists('read_user_setting')) {
	function read_user_setting($name, $default = '', $force = false) {
		return $GLOBALS['__user_settings'][$name] ?? $default;
	}
}

if (!function_exists('set_user_setting')) {
	function set_user_setting($name, $value) {
		$GLOBALS['__user_settings'][$name] = $value;
	}
}

require_once __DIR__ . '/../../includes/dashboard.php';

describe('shared dashboard layout', function () {
	beforeEach(function () {
		$GLOBALS['__user_settings'] = [];
	});

	$fixed = ['identity', 'power', 'battery', 'events'];

	it('shares the fixed-card order and keeps graph cards per UPS', function () use ($fixed) {
		$a = array_merge($fixed, ['graph_10', 'graph_11']);
		$b = array_merge($fixed, ['graph_20', 'graph_21']);

		expect(apcupsd_dashboard_layout_store(1, ['battery', 'identity', 'graph_11', 'graph_10', 'power', 'events'], ['events' => true]))->toBeTrue();

		expect(apcupsd_dashboard_layout_read(1, $a)['order'])->toBe(['battery', 'identity', 'graph_11', 'graph_10', 'power', 'events']);

		// UPS B has no graph preference yet: its graphs take the shared slot in default order.
		$read_b = apcupsd_dashboard_layout_read(2, $b);

		expect($read_b['order'])->toBe(['battery', 'identity', 'graph_20', 'graph_21', 'power', 'events']);
		expect($read_b['expanded'])->toBe(['events' => true]);
	});

	it('preserves graphs interleaved with fixed cards', function () {
		$available = ['identity', 'power', 'battery', 'graph_10', 'graph_11'];
		$order     = ['identity', 'graph_10', 'battery', 'graph_11'];

		apcupsd_dashboard_layout_store(1, $order, []);

		expect(apcupsd_dashboard_layout_read(1, $available)['order'])->toBe($order);
	});

	it('does not overwrite another UPS\'s graph selection when saving', function () {
		apcupsd_dashboard_layout_store(1, ['identity', 'graph_10'], []);
		apcupsd_dashboard_layout_store(2, ['identity', 'graph_21'], []);

		expect(apcupsd_dashboard_layout_read(1, ['identity', 'graph_10', 'graph_11'])['order'])->toBe(['identity', 'graph_10']);
		expect(apcupsd_dashboard_layout_read(2, ['identity', 'graph_20', 'graph_21'])['order'])->toBe(['identity', 'graph_21']);
	});

	it('keeps the shared graph position when a UPS without graphs saves', function () {
		$a = ['identity', 'battery', 'graph_10'];

		apcupsd_dashboard_layout_store(1, ['identity', 'graph_10', 'battery'], []);

		// UPS B has no graphs: it reorders the fixed cards.
		apcupsd_dashboard_layout_store(2, ['battery', 'identity'], []);

		expect(apcupsd_dashboard_layout_read(1, $a)['order'])->toBe(['battery', 'identity', 'graph_10']);

		// ...and then removes the card the graphs were anchored to: they stay after the nearest surviving card.
		apcupsd_dashboard_layout_store(2, ['battery'], []);

		expect(apcupsd_dashboard_layout_read(1, $a)['order'])->toBe(['battery', 'graph_10']);

		// Removing every fixed card leaves the slot at the start.
		apcupsd_dashboard_layout_store(2, [], []);

		expect(apcupsd_dashboard_layout_read(1, $a)['order'])->toBe(['graph_10']);
	});

	it('migrates a second UPS\'s legacy graph selection without changing the shared layout', function () {
		apcupsd_dashboard_layout_store(1, ['power', 'identity', 'graph_10'], []);

		$shared = $GLOBALS['__user_settings']['apcupsd_dashboard_layout_shared'];

		$GLOBALS['__user_settings']['apcupsd_dashboard_layout_2'] = json_encode(['order' => ['battery', 'graph_21']]);

		$read_b = apcupsd_dashboard_layout_read(2, ['identity', 'power', 'battery', 'graph_20', 'graph_21']);

		expect($read_b['order'])->toBe(['power', 'identity', 'graph_21']);
		expect($GLOBALS['__user_settings']['apcupsd_dashboard_layout_shared'])->toBe($shared);
		expect($GLOBALS['__user_settings'])->toHaveKey('apcupsd_dashboard_layout_graphs_2');
	});

	it('migrates a second UPS\'s combined-map graph selection', function () {
		apcupsd_dashboard_layout_store(1, ['identity', 'graph_10'], []);

		$GLOBALS['__user_settings']['apcupsd_dashboard_layout'] = json_encode(['2' => ['order' => ['graph_21', 'identity']]]);

		expect(apcupsd_dashboard_layout_read(2, ['identity', 'graph_20', 'graph_21'])['order'])->toBe(['identity', 'graph_21']);
	});

	it('refuses to save a layout over the settings size cap without writing anything', function () {
		$order = [];

		for ($i = 0; $i < 600; $i++) {
			$order[] = 'graph_' . (100000 + $i);
		}

		expect(apcupsd_dashboard_layout_store(1, $order, []))->toBeFalse();
		expect($GLOBALS['__user_settings'])->toBe([]);
	});

	it('returns null when nothing is saved', function () use ($fixed) {
		expect(apcupsd_dashboard_layout_read(1, $fixed))->toBeNull();
	});

	it('persists a legacy per-UPS layout to the shared row on first read', function () use ($fixed) {
		$available = array_merge($fixed, ['graph_10']);

		$GLOBALS['__user_settings']['apcupsd_dashboard_layout_1'] = json_encode(['order' => ['power', 'graph_10', 'gauges', 'bogus'], 'expanded' => ['events' => true]]);

		$first = apcupsd_dashboard_layout_read(1, $available);

		expect($first['order'])->toBe(['power', 'graph_10']);
		expect($GLOBALS['__user_settings'])->toHaveKey('apcupsd_dashboard_layout_shared');
		expect($GLOBALS['__user_settings'])->toHaveKey('apcupsd_dashboard_layout_graphs_1');

		// A different UPS now inherits the migrated fixed-card arrangement.
		expect(apcupsd_dashboard_layout_read(2, ['identity', 'power', 'graph_20'])['order'])->toBe(['power', 'graph_20']);
	});

	it('persists the pre-release combined-map layout to the shared row on first read', function () use ($fixed) {
		$GLOBALS['__user_settings']['apcupsd_dashboard_layout'] = json_encode(['1' => ['order' => ['events', 'identity']]]);

		expect(apcupsd_dashboard_layout_read(1, $fixed)['order'])->toBe(['events', 'identity']);
		expect($GLOBALS['__user_settings'])->toHaveKey('apcupsd_dashboard_layout_shared');
	});

	it('normalizes to the available cards, dropping unknown and duplicate keys', function () use ($fixed) {
		$layout = apcupsd_dashboard_layout_normalize(['order' => ['power', 'power', 'nope', 'identity'], 'expanded' => ['events' => true, 'nope' => true]], $fixed);

		expect($layout['order'])->toBe(['power', 'identity']);
		expect($layout['expanded'])->toBe(['events' => true]);
	});
});
