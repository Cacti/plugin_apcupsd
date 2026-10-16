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

/* UPS Dashboard: the per-UPS drilldown card grid. Mirrors the hmib/intropage
 * card interaction model (drag/keyboard reorder, per-card tools, an "Add card"
 * catalogue, a Cacti timespan selector, auto-refresh interval + manual refresh)
 * with the card order, expanded state and current UPS persisted server-side in
 * settings_user. */

var apcupsdDashDragging = null;

/** The endpoint/scope configuration for a grid, read from its data attributes so
 *  the per-UPS and fleet dashboards can share this engine. */
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

/** Build a POST payload for a grid, adding the CSRF token and the grid's scope
 *  parameter (the UPS id for the per-UPS dashboard; absent for the fleet one). */
function apcupsdDashPost(grid, extra) {
	var cfg = apcupsdDashCfg(grid);
	var data = {__csrf_magic: csrfMagicToken};
	for (var key in extra) {
		if (Object.prototype.hasOwnProperty.call(extra, key)) {
			data[key] = extra[key];
		}
	}
	if (cfg.scopeParam) {
		data[cfg.scopeParam] = cfg.scope;
	}
	return data;
}

/** Make a container's sortable headers focusable and screen-reader friendly. */
function apcupsdDashPrepSortHeaders(container) {
	if (!container) {
		return;
	}
	container.querySelectorAll('th.apcupsdDashSortable').forEach(function(th) {
		if (!th.hasAttribute('tabindex')) {
			th.setAttribute('tabindex', '0');
		}
		// Keep the native columnheader role: aria-sort is only valid there, and the
		// tabindex + Enter/Space keydown handler already makes it operable.
		if (!th.hasAttribute('aria-sort')) {
			th.setAttribute('aria-sort', 'none');
		}
	});
}

/** Sort a card table by the clicked column header, toggling asc/desc. Numeric
 *  columns (data-sort="num") sort by the cell's data-sort-value when present. */
function apcupsdDashSortTable(th) {
	var table = th.closest('table');
	if (!table) {
		return;
	}
	var tbody = table.tBodies[0];
	if (!tbody) {
		return;
	}
	var headers = Array.prototype.slice.call(th.parentNode.children);
	var index = headers.indexOf(th);
	var numeric = th.dataset.sort === 'num';
	var asc = !th.classList.contains('apcupsdDashSortAsc');

	headers.forEach(function(header) {
		header.classList.remove('apcupsdDashSortAsc', 'apcupsdDashSortDesc');
		if (header.classList.contains('apcupsdDashSortable')) {
			header.setAttribute('aria-sort', 'none');
		}
	});
	th.classList.add(asc ? 'apcupsdDashSortAsc' : 'apcupsdDashSortDesc');
	th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');

	var rows = Array.prototype.slice.call(tbody.rows).filter(function(row) {
		return !row.classList.contains('apcupsdDashEmptyRow');
	});

	var value = function(row) {
		var cell = row.cells[index];
		if (!cell) {
			return numeric ? 0 : '';
		}
		if (numeric) {
			var raw = cell.dataset.sortValue !== undefined ? cell.dataset.sortValue : cell.textContent;
			var num = parseFloat(String(raw).replace(/[^0-9.\-]/g, ''));
			return isNaN(num) ? 0 : num;
		}
		return cell.textContent.trim().toLowerCase();
	};

	rows.sort(function(a, b) {
		var va = value(a);
		var vb = value(b);
		if (va < vb) { return asc ? -1 : 1; }
		if (va > vb) { return asc ? 1 : -1; }
		return 0;
	});

	rows.forEach(function(row) {
		tbody.appendChild(row);
	});
}

/** Remember which header a card's table is sorted by, and in which direction. */
function apcupsdDashSortSnapshot(card) {
	var th = card.querySelector('th.apcupsdDashSortAsc, th.apcupsdDashSortDesc');
	if (!th) {
		return null;
	}
	return {index: Array.prototype.indexOf.call(th.parentNode.children, th), asc: th.classList.contains('apcupsdDashSortAsc')};
}

/** Re-apply a remembered sort to a freshly rendered card, so an in-place refresh
 *  does not throw away the order the user picked. */
function apcupsdDashSortRestore(card, snapshot) {
	if (!snapshot) {
		return;
	}
	var th = card.querySelector('th.apcupsdDashSortable:nth-child(' + (snapshot.index + 1) + ')');
	if (!th) {
		return;
	}
	// apcupsdDashSortTable() toggles the direction, so seed the opposite one.
	th.classList.toggle('apcupsdDashSortAsc', !snapshot.asc);
	apcupsdDashSortTable(th);
}

/** Wire click and Enter/Space sorting on the sortable headers under a root. The
 *  handlers are delegated, so cards that are refreshed or added later keep working. */
function apcupsdDashBindSort(root) {
	$(root).off('click.apcupsdsort').on('click.apcupsdsort', 'th.apcupsdDashSortable', function() {
		apcupsdDashSortTable(this);
	});
	$(root).off('keydown.apcupsdsort').on('keydown.apcupsdsort', 'th.apcupsdDashSortable', function(event) {
		if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
			event.preventDefault();
			apcupsdDashSortTable(this);
		}
	});
}

function initApcupsdDashboard() {
	$(function() {
		var grid = document.getElementById('apcupsd_dashboard');
		if (!grid) {
			return;
		}

		// Delegated so the sortable headers also work in cards that are refreshed or
		// added later.
		apcupsdDashBindSort(grid);

		grid.querySelectorAll('.apcupsdDashCard').forEach(function(card) {
			apcupsdDashBindCard(grid, card);
		});

		grid.addEventListener('dragover', function(event) {
			if (!apcupsdDashDragging) {
				return;
			}
			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';
			var before = apcupsdDashDragReference(grid, event.clientX, event.clientY);
			if (before == null) {
				grid.appendChild(apcupsdDashDragging);
			} else if (before !== apcupsdDashDragging) {
				grid.insertBefore(apcupsdDashDragging, before);
			}
		});

		// Per-card tool buttons bubble to the grid.
		$(grid).off('click.apcupsddash').on('click.apcupsddash', '.apcupsdDashCardTool', function() {
			var card = this.closest('.apcupsdDashCard');
			if (!card) {
				return;
			}
			switch (this.dataset.tool) {
				case 'expand':   card.classList.add('apcupsdDashCardExpanded'); apcupsdDashSaveLayout(grid); break;
				case 'collapse': card.classList.remove('apcupsdDashCardExpanded'); apcupsdDashSaveLayout(grid); break;
				case 'maximize': apcupsdDashMaximize(card); break;
				case 'refresh':  apcupsdDashRefreshCard(grid, card); break;
				case 'remove':   apcupsdDashRemoveCard(grid, card); break;
			}
		});

		var add = document.getElementById('apcupsd_dashboard_add');
		if (add) {
			$(add).off('change.apcupsddash').on('change.apcupsddash', function() {
				var key = this.value;
				this.value = '';
				if (key) {
					apcupsdDashAddCard(grid, key);
				}
			});
		}

		var ups = document.getElementById('apcupsd_dashboard_ups');
		if (ups) {
			$(ups).off('change.apcupsddash').on('change.apcupsddash', function() {
				if (this.value) {
					window.location = 'upses.php?action=view&id=' + encodeURIComponent(this.value);
				}
			});
		}

		// Changing the timespan reloads so Cacti's session timespan selector picks
		// up predefined_timespan, which drives both the graph cards and the RRD
		// bullet/gauge queries. The grid's reload target (per-UPS view or the fleet
		// dashboard) carries the id, so build the URL from it.
		var timespan = document.getElementById('apcupsd_dashboard_timespan');
		if (timespan) {
			$(timespan).off('change.apcupsddash').on('change.apcupsddash', function() {
				var reload = apcupsdDashCfg(grid).reload;
				var sep = reload.indexOf('?') === -1 ? '?' : '&';
				window.location = reload + sep + 'predefined_timespan=' + encodeURIComponent(this.value);
			});
		}

		// Changing the interval persists the preference through a CSRF-protected
		// POST (never a drive-by GET), then reloads so Cacti's page refresh applies.
		var interval = document.getElementById('apcupsd_dashboard_refresh');
		if (interval) {
			$(interval).off('change.apcupsddash').on('change.apcupsddash', function() {
				var cfg = apcupsdDashCfg(grid);
				$.post(cfg.page, apcupsdDashPost(grid, {action: cfg.refreshAction, refresh: this.value}), null, 'json').always(function() {
					window.location = cfg.reload;
				});
			});
		}

		var refreshNow = document.getElementById('apcupsd_dashboard_refresh_now');
		if (refreshNow) {
			$(refreshNow).off('click.apcupsddash').on('click.apcupsddash', function() {
				apcupsdDashRefreshAll(grid, this);
			});
		}
	});
}

/** Wire a single card's drag handle (mouse + keyboard) and drag events. */
function apcupsdDashBindCard(grid, card) {
	apcupsdDashPrepSortHeaders(card);

	var handle = card.querySelector('.apcupsdDashCardDrag');
	if (!handle) {
		return;
	}

	// Arm HTML5 drag only from the handle, and disarm on release when no drag
	// began so selecting content elsewhere can't drag the card.
	var disarm = function() { card.draggable = false; };
	handle.addEventListener('mousedown', function() {
		card.draggable = true;
		document.addEventListener('mouseup', disarm, {once: true});
	});
	handle.addEventListener('touchstart', function() {
		card.draggable = true;
		document.addEventListener('touchend', disarm, {once: true});
		document.addEventListener('touchcancel', disarm, {once: true});
	}, {passive: true});

	// Keyboard-accessible reordering: move the card with the arrow keys.
	handle.addEventListener('keydown', function(event) {
		var back = event.key === 'ArrowLeft' || event.key === 'ArrowUp';
		var fwd  = event.key === 'ArrowRight' || event.key === 'ArrowDown';
		if (!back && !fwd) {
			return;
		}
		event.preventDefault();
		if (back && card.previousElementSibling) {
			grid.insertBefore(card, card.previousElementSibling);
		} else if (fwd && card.nextElementSibling) {
			grid.insertBefore(card.nextElementSibling, card);
		} else {
			return;
		}
		handle.focus();
		apcupsdDashSaveLayout(grid);
	});

	card.addEventListener('dragstart', function(event) {
		apcupsdDashDragging = card;
		card.classList.add('apcupsdDashCardDragging');
		event.dataTransfer.effectAllowed = 'move';
		try { event.dataTransfer.setData('text/plain', card.dataset.card || ''); } catch (error) { /* IE guard */ }
	});

	card.addEventListener('dragend', function() {
		card.draggable = false;
		card.classList.remove('apcupsdDashCardDragging');
		if (apcupsdDashDragging) {
			apcupsdDashDragging = null;
			apcupsdDashSaveLayout(grid);
		}
	});
}

/** The card the dragged card should be inserted before for the current pointer
 *  position, or null to append at the end. */
function apcupsdDashDragReference(grid, x, y) {
	var cards = Array.prototype.slice.call(grid.querySelectorAll('.apcupsdDashCard:not(.apcupsdDashCardDragging)'));
	for (var i = 0; i < cards.length; i++) {
		var rect = cards[i].getBoundingClientRect();
		if (y < rect.top - 1) {
			return cards[i];
		}
		if (y <= rect.bottom && x < rect.left + rect.width / 2) {
			return cards[i];
		}
	}
	return null;
}

/** Add a card from the catalogue: fetch its HTML, append it, persist and refresh
 *  the "Add" options. */
function apcupsdDashAddCard(grid, key) {
	if (apcupsdDashHasCard(grid, key)) {
		return;
	}
	var cfg = apcupsdDashCfg(grid);
	$.post(cfg.page, apcupsdDashPost(grid, {action: cfg.cardAction, card: key}), null, 'json').done(function(data) {
		if (!data || !data.html || apcupsdDashHasCard(grid, key)) {
			return;
		}
		var card = apcupsdDashParseCard(data.html);
		if (!card) {
			return;
		}
		grid.appendChild(card);
		apcupsdDashBindCard(grid, card);
		apcupsdDashSaveLayout(grid);
		apcupsdDashSyncAddOptions(grid);
	});
}

/** Whether a card with the given key is currently on the grid. */
function apcupsdDashHasCard(grid, key) {
	var present = false;
	grid.querySelectorAll('.apcupsdDashCard').forEach(function(card) {
		if (card.dataset.card === key) {
			present = true;
		}
	});
	return present;
}

/** Remove a card from the page and return it to the "Add" catalogue. */
function apcupsdDashRemoveCard(grid, card) {
	card.parentNode.removeChild(card);
	apcupsdDashSaveLayout(grid);
	apcupsdDashSyncAddOptions(grid);
}

/** Re-fetch a single card's HTML and swap it in place, keeping expanded state. */
function apcupsdDashRefreshCard(grid, card, done) {
	var icon = card.querySelector('.apcupsdDashCardTool[data-tool="refresh"] .fa, .apcupsdDashCardTool[data-tool="refresh"] .fas');
	if (icon) {
		icon.classList.add('fa-spin');
	}
	var settle = function() {
		if (icon) {
			icon.classList.remove('fa-spin');
		}
		if (done) {
			done();
		}
	};
	var expanded = card.classList.contains('apcupsdDashCardExpanded') ? '1' : '0';
	var sorted = apcupsdDashSortSnapshot(card);
	var cfg = apcupsdDashCfg(grid);
	$.post(cfg.page, apcupsdDashPost(grid, {action: cfg.cardAction, card: card.dataset.card, expanded: expanded}), null, 'json').done(function(data) {
		if (!data || !data.html || !card.parentNode) {
			settle();
			return;
		}
		var fresh = apcupsdDashParseCard(data.html);
		if (!fresh) {
			settle();
			return;
		}
		// Re-read the card's expanded state at swap time: the user may have toggled
		// it while this request was in flight, and that live state must win over the
		// state captured when the request was sent.
		if (card.classList.contains('apcupsdDashCardExpanded')) {
			fresh.classList.add('apcupsdDashCardExpanded');
		} else {
			fresh.classList.remove('apcupsdDashCardExpanded');
		}
		card.parentNode.replaceChild(fresh, card);
		apcupsdDashBindCard(grid, fresh);
		apcupsdDashSortRestore(fresh, sorted);
		if (done) {
			done();
		}
	}).fail(settle);
}

/** Refresh every card on the page, spinning the toolbar glyph until all land. A
 *  refresh that is still in flight is not stacked on, so a slow server is never
 *  hit by overlapping rounds. */
var apcupsdDashRefreshing = false;

function apcupsdDashRefreshAll(grid, button) {
	var icon = button ? button.querySelector('.fa, .fas') : null;
	var cards = Array.prototype.slice.call(grid.querySelectorAll('.apcupsdDashCard'));
	var pending = cards.length;
	if (!pending || apcupsdDashRefreshing) {
		return;
	}
	apcupsdDashRefreshing = true;
	if (icon) {
		icon.classList.add('fa-spin');
	}
	cards.forEach(function(card) {
		apcupsdDashRefreshCard(grid, card, function() {
			pending--;
			if (pending <= 0) {
				apcupsdDashRefreshing = false;
				if (icon) {
					icon.classList.remove('fa-spin');
				}
			}
		});
	});
}

/** Called by Cacti's page timer (the refreshFunction global, the hook the Graphs
 *  page uses too): refresh the cards in place instead of reloading the page. Cacti
 *  re-arms the timer after this returns. The timer outlives ajax page loads, so if
 *  the dashboard is no longer on screen the page that replaced it gets Cacti's
 *  normal full-page refresh back. */
function apcupsdDashAutoRefresh() {
	var grid = document.getElementById('apcupsd_dashboard');
	if (grid) {
		apcupsdDashRefreshAll(grid, document.getElementById('apcupsd_dashboard_refresh_now'));
		return;
	}

	refreshFunction = '';
	if (typeof loadUrl === 'function' && typeof refreshPage !== 'undefined' && refreshPage !== '') {
		loadUrl({url: refreshPage});
	}
}

/** Open a copy of a card's body in a large modal dialog. */
function apcupsdDashMaximize(card) {
	var dialog = document.getElementById('apcupsd_dashboard_dialog');
	if (!dialog) {
		return;
	}
	var title = card.querySelector('.apcupsdDashCardTitle');
	var body  = card.querySelector('.apcupsdDashCardBody');
	dialog.innerHTML = '<div class="apcupsdDashDialogBody">' + (body ? body.innerHTML : '') + '</div>';

	// The dialog is a clone outside the grid, so wire its own sort handlers.
	apcupsdDashPrepSortHeaders(dialog);
	apcupsdDashBindSort(dialog);

	$(dialog).dialog({
		modal: true,
		appendTo: 'body',
		width: Math.min(900, $(window).width() - 40),
		title: title ? title.textContent : ''
	});
}

/** Parse a card HTML string into its <section> element. */
function apcupsdDashParseCard(html) {
	var tmp = document.createElement('div');
	tmp.innerHTML = html;
	return tmp.querySelector('.apcupsdDashCard');
}

/** Rebuild the "Add" dropdown so it lists only cards not currently on the page. */
function apcupsdDashSyncAddOptions(grid) {
	var add = document.getElementById('apcupsd_dashboard_add');
	if (!add || typeof apcupsdDashCatalog === 'undefined') {
		return;
	}
	var present = {};
	grid.querySelectorAll('.apcupsdDashCard').forEach(function(card) {
		if (card.dataset.card) {
			present[card.dataset.card] = true;
		}
	});
	while (add.options.length > 1) {
		add.remove(1);
	}
	Object.keys(apcupsdDashCatalog).forEach(function(key) {
		if (!present[key]) {
			var option = document.createElement('option');
			option.value = key;
			option.textContent = apcupsdDashCatalog[key];
			add.appendChild(option);
		}
	});
}

/** Serialize layout POSTs so rapid actions can't persist out of order: one
 *  request is in flight at a time, and a save requested meanwhile is coalesced
 *  into a single follow-up that reads the latest DOM, so the final state wins. */
var apcupsdDashSaveInFlight = false;
var apcupsdDashSaveQueued = false;

function apcupsdDashSaveLayout(grid) {
	if (apcupsdDashSaveInFlight) {
		apcupsdDashSaveQueued = true;
		return;
	}
	apcupsdDashSaveInFlight = true;

	var order = [];
	var expanded = {};
	grid.querySelectorAll('.apcupsdDashCard').forEach(function(card) {
		if (!card.dataset.card) {
			return;
		}
		order.push(card.dataset.card);
		if (card.classList.contains('apcupsdDashCardExpanded')) {
			expanded[card.dataset.card] = true;
		}
	});

	var cfg = apcupsdDashCfg(grid);
	$.post(cfg.page, apcupsdDashPost(grid, {
		action: cfg.layoutAction,
		layout: JSON.stringify({order: order, expanded: expanded})
	}), null, 'json').done(function(data) {
		var status = grid.previousElementSibling && grid.previousElementSibling.querySelector('.apcupsdDashSaveStatus');
		if (status) {
			status.textContent = data && data.error ? data.error : '';
			status.hidden = !status.textContent;
		}
	}).always(function() {
		apcupsdDashSaveInFlight = false;
		if (apcupsdDashSaveQueued) {
			apcupsdDashSaveQueued = false;
			apcupsdDashSaveLayout(grid);
		}
	});
}
