/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* UPS Dashboard adapter for the shared card chrome (js/cards.js). The
 * CactiCards controller owns drag/keyboard reorder, the per-card tools and the
 * debounced layout save; this adapter maps those hooks to apcupsd's upses.php
 * endpoints and adds apcupsd's extras: a UPS picker, Cacti's timespan selector
 * (its graph and RRD-bullet cards follow the session timespan), an auto-refresh
 * interval and a manual refresh-all. Card markup uses the shared .cc* contract
 * and the card bodies the shared .cc* chart primitives. */

/* The endpoint/scope configuration for a grid, read from its data attributes. */
function apcupsdDashCfg(grid) {
	return {
		page:          grid.dataset.page          || 'upses.php',
		cardAction:    grid.dataset.cardAction    || 'dashboard_card',
		layoutAction:  grid.dataset.layoutAction  || 'dashboard_layout',
		refreshAction: grid.dataset.refreshAction || 'dashboard_refresh',
		scopeParam:    grid.dataset.scopeParam    || '',
		scope:         grid.dataset.scope         || '',
		reload:        grid.dataset.reload        || 'upses.php'
	};
}

/* Build a POST payload for a grid, adding the CSRF token and the grid's scope
 * parameter (the UPS id for the per-UPS dashboard). */
function apcupsdDashPost(grid, extra) {
	var data = {__csrf_magic: csrfMagicToken};

	for (var key in extra) {
		if (Object.prototype.hasOwnProperty.call(extra, key)) {
			data[key] = extra[key];
		}
	}

	var cfg = apcupsdDashCfg(grid);

	if (cfg.scopeParam) {
		data[cfg.scopeParam] = cfg.scope;
	}

	return data;
}

/* Make a container's sortable headers focusable and screen-reader friendly. */
function apcupsdDashPrepSortHeaders(container) {
	if (!container) {
		return;
	}

	container.querySelectorAll('th.apcupsdDashSortable').forEach(function(th) {
		if (!th.hasAttribute('tabindex')) {
			th.setAttribute('tabindex', '0');
		}
		if (!th.hasAttribute('aria-sort')) {
			th.setAttribute('aria-sort', 'none');
		}
	});
}

/* Show (or clear) a layout-save error in the toolbar's live-region status span so
 * a rejected save is not silently lost on the next reload. */
function apcupsdDashShowSaveStatus(message) {
	var el = document.querySelector('.apcupsdDashSaveStatus');

	if (!el) {
		return;
	}

	if (message) {
		el.textContent = message;
		el.removeAttribute('hidden');
	} else {
		el.textContent = '';
		el.setAttribute('hidden', 'hidden');
	}
}

/* Sort a card table by the clicked column header, toggling asc/desc. Numeric
 * columns (data-sort="num") sort by the cell's data-sort-value when present. */
function apcupsdDashSortTable(th) {
	var table = th.closest('table');

	if (!table) {
		return;
	}

	var tbody   = table.tBodies[0];
	var headers = Array.prototype.slice.call(th.parentNode.children);
	var index   = headers.indexOf(th);
	var numeric = th.getAttribute('data-sort') === 'num';
	var asc     = th.getAttribute('aria-sort') !== 'ascending';

	headers.forEach(function(header) {
		if (header !== th) {
			header.setAttribute('aria-sort', 'none');
		}
	});

	th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');

	var rows = Array.prototype.slice.call(tbody.rows).filter(function(row) {
		return !row.classList.contains('apcupsdDashEmptyRow');
	});

	rows.sort(function(a, b) {
		var x = apcupsdDashCellValue(a.cells[index], numeric);
		var y = apcupsdDashCellValue(b.cells[index], numeric);

		if (x < y) {
			return asc ? -1 : 1;
		}
		if (x > y) {
			return asc ? 1 : -1;
		}
		return 0;
	});

	rows.forEach(function(row) {
		tbody.appendChild(row);
	});
}

/* The sort key of a cell: its data-sort-value when set, else its text; numeric
 * columns parse to a number so 9 sorts below 10. */
function apcupsdDashCellValue(cell, numeric) {
	if (!cell) {
		return numeric ? 0 : '';
	}

	var raw = cell.hasAttribute('data-sort-value') ? cell.getAttribute('data-sort-value') : cell.textContent;

	if (numeric) {
		var value = Number.parseFloat(raw);
		return Number.isNaN(value) ? 0 : value;
	}

	return (raw || '').trim().toLowerCase();
}

/* Delegate clicks and keyboard activation on a container's sortable headers. */
function apcupsdDashBindSort(container, namespace) {
	if (!container) {
		return;
	}

	apcupsdDashPrepSortHeaders(container);

	$(container).off('click.' + namespace).on('click.' + namespace, 'th.apcupsdDashSortable', function() {
		apcupsdDashSortTable(this);
	});

	$(container).off('keydown.' + namespace).on('keydown.' + namespace, 'th.apcupsdDashSortable', function(event) {
		if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
			event.preventDefault();
			apcupsdDashSortTable(this);
		}
	});
}

/* Wire the per-UPS dashboard grid to the shared card chrome plus apcupsd's
 * extras: the UPS picker, Cacti's timespan selector, the auto-refresh interval
 * and the manual refresh-all. */
function initApcupsdDashboard() {
	$(function() {
		var grid = document.getElementById('apcupsd_dashboard');

		if (!grid || !window.CactiCards) {
			return;
		}

		var cfg = apcupsdDashCfg(grid);

		function catalogue() {
			return typeof apcupsdDashCatalog !== 'undefined' ? apcupsdDashCatalog : null;
		}

		function syncAddOptions() {
			var add = document.getElementById('apcupsd_dashboard_add');
			var cat = catalogue();

			if (!add || !cat) {
				return;
			}

			var present = {};

			grid.querySelectorAll('.ccCard').forEach(function(card) {
				if (card.dataset.card) {
					present[card.dataset.card] = true;
				}
			});

			while (add.options.length > 1) {
				add.remove(1);
			}

			Object.keys(cat).forEach(function(key) {
				if (!present[key]) {
					var option = document.createElement('option');
					option.value = key;
					option.textContent = cat[key];
					add.appendChild(option);
				}
			});
		}

		function fetchCard(id, expanded) {
			return $.post(cfg.page, apcupsdDashPost(grid, {action: cfg.cardAction, card: id, expanded: expanded ? '1' : '0'}), null, 'json').then(function(d) {
				return d && d.html ? d.html : null;
			});
		}

		function maximize(card) {
			var dialog = document.getElementById('apcupsd_dashboard_dialog');

			if (!dialog) {
				return;
			}

			var title = card.querySelector('.ccTitle');
			var body  = card.querySelector('.ccCardBody');

			dialog.innerHTML = '<div class="ccDialogBody">' + (body ? body.innerHTML : '') + '</div>';

			apcupsdDashBindSort(dialog, 'apcupsddlgsort');

			$(dialog).dialog({
				modal: true,
				appendTo: 'body',
				width: Math.min(900, $(window).width() - 40),
				title: title ? title.textContent : ''
			});
		}

		var adapter = {
			addControl: 'apcupsd_dashboard_add',

			// The shared controller calls applyTheme after every swap (refresh,
			// resize, add and manual refresh-all). apcupsd has no per-card palette
			// to re-apply, but this is the hook that lets it re-enhance the sort
			// headers of freshly inserted, server-rendered markup so keyboard
			// users keep access to sorting after a refresh or re-add.
			applyTheme: function(g) {
				apcupsdDashPrepSortHeaders(g);
			},

			fetchCard: function(id, state) {
				return fetchCard(id, state && state.expanded);
			},

			onAddCard: function(id) {
				return fetchCard(id, false);
			},

			onLayoutChange: function(state) {
				var expanded = {};

				state.order.forEach(function(id) {
					if (state.cards[id] && state.cards[id].expanded) {
						expanded[id] = true;
					}
				});

				$.post(cfg.page, apcupsdDashPost(grid, {
					action: cfg.layoutAction,
					layout: JSON.stringify({order: state.order, expanded: expanded})
				}), null, 'json').done(function(d) {
					// Surface a server-side rejection (e.g. the 4096-char layout
					// cap) instead of letting the change silently revert on reload.
					apcupsdDashShowSaveStatus(d && d.error ? d.error : '');
				});

				syncAddOptions();
			},

			maximize: function(id, mode, card) {
				maximize(card);
			}
		};

		CactiCards.init(grid, adapter);
		syncAddOptions();
		apcupsdDashBindSort(grid, 'apcupsdsort');

		// Cacti 1.2.x upgrades the Add card <select> to Select2, which emits a
		// jQuery 'change' but not the native event the shared controller listens
		// for. Forward the jQuery change to a native one (guarding re-entry and
		// the controller's own value reset) so choosing a card restores it on
		// both the bundled and the core controllers.
		var addSelect = document.getElementById('apcupsd_dashboard_add');
		if (addSelect && window.jQuery) {
			$(addSelect).on('change', function() {
				if (addSelect.__ccBridging || !addSelect.value) {
					return;
				}
				addSelect.__ccBridging = true;
				addSelect.dispatchEvent(new Event('change', {bubbles: true}));
				addSelect.__ccBridging = false;
			});
		}

		var ups = document.getElementById('apcupsd_dashboard_ups');
		if (ups) {
			$(ups).off('change.apcupsd').on('change.apcupsd', function() {
				if (this.value) {
					window.location = 'upses.php?action=view&id=' + encodeURIComponent(this.value);
				}
			});
		}

		// Changing the timespan reloads the dashboard so Cacti's session timespan
		// selector picks up predefined_timespan, which drives the graph cards and
		// the RRD bullet queries.
		var timespan = document.getElementById('apcupsd_dashboard_timespan');
		if (timespan) {
			$(timespan).off('change.apcupsd').on('change.apcupsd', function() {
				var reload = cfg.reload;
				var sep = reload.indexOf('?') === -1 ? '?' : '&';
				window.location = reload + sep + 'predefined_timespan=' + encodeURIComponent(this.value);
			});
		}

		// Changing the interval persists the preference through a CSRF-protected
		// POST (never a drive-by GET), then reloads so Cacti's page refresh arms
		// the new interval. If the save fails the dropdown rolls back.
		var interval = document.getElementById('apcupsd_dashboard_refresh');
		if (interval) {
			$(interval).off('change.apcupsd').on('change.apcupsd', function() {
				$.post(cfg.page, apcupsdDashPost(grid, {action: cfg.refreshAction, refresh: this.value}), null, 'json').always(function() {
					window.location = cfg.reload;
				});
			});
		}

		var refreshNow = document.getElementById('apcupsd_dashboard_refresh_now');
		if (refreshNow) {
			$(refreshNow).off('click.apcupsd').on('click.apcupsd', function() {
				var icon = this.querySelector('.fa, .fas');
				var cards = Array.prototype.slice.call(grid.querySelectorAll('.ccCard'));
				var pending = cards.length;

				if (!pending) {
					return;
				}

				if (icon) {
					icon.classList.add('fa-spin');
				}

				cards.forEach(function(card) {
					fetchCard(card.dataset.card, card.classList.contains('ccCardExpanded')).then(function(html) {
						if (html && card.parentNode && window.CactiCards) {
							CactiCards.replaceCard(grid, adapter, card, html);
						}
					}).always(function() {
						pending--;
						if (pending <= 0 && icon) {
							icon.classList.remove('fa-spin');
						}
					});
				});
			});
		}
	});
}
