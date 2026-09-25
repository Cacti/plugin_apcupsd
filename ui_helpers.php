<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 */

if (!function_exists('apcupsd_render_with_layout')) {
	/**
	 * Wraps a page-rendering callback with Cacti's standard top/bottom
	 * page chrome (top_header()/bottom_footer()). Called from upses.php's
	 * dispatcher for each of its list/edit page views.
	 *
	 * @param callable $renderer The callback that renders the page's
	 *                           main content.
	 *
	 * @return void
	 */
	function apcupsd_render_with_layout(callable $renderer): void {
		top_header();
		call_user_func($renderer);
		bottom_footer();
	}
}
