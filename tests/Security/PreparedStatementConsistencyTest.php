<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('prepared statement consistency in apcupsd', function () {
	it('uses prepared DB helpers in all plugin files', function () {
		// setup.php is excluded: it is almost entirely schema-migration DDL
		// (CREATE/DROP/ALTER TABLE) with no user-supplied parameters to bind,
		// unlike the user-facing query code in upses.php.
		$targetFiles = array(
		'upses.php',
		);

		$rawPattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\s*\(/';
		$preparedPattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)_prepared\s*\(/';

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);
			if ($path === false) continue;
			$contents = file_get_contents($path);
			if ($contents === false) continue;

			$lines = explode("\n", $contents);
			$rawCalls = 0;

			foreach ($lines as $line) {
				$trimmed = ltrim($line);
				if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0 || strpos($trimmed, '#') === 0) continue;
				if (preg_match($rawPattern, $line) && !preg_match($preparedPattern, $line)) {
					$rawCalls++;
				}
			}

			expect($rawCalls)->toBe(0, "File {$relativeFile} contains raw DB calls");
		}
	});

	it('uses parameterized placeholders not string interpolation in SQL', function () {
		$targetFiles = array(
		'setup.php',
		'upses.php',
		);

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);
			if ($path === false) continue;
			$contents = file_get_contents($path);
			if ($contents === false) continue;

			$lines = explode("\n", $contents);
			$interpolatedSql = 0;

			foreach ($lines as $num => $line) {
				$trimmed = ltrim($line);
				if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) continue;

				// Detect _prepared calls with $ interpolation inside the SQL string
				// itself, not just a bound parameter appearing elsewhere on the line
				// (e.g. array($id)), which is the normal, safe form.
				if (preg_match('/_prepared\s*\(\s*[\'"]([^\'"]*)[\'"]/', $line, $sqlMatch)) {
					if (preg_match('/\$[a-zA-Z_]/', $sqlMatch[1])) {
						$interpolatedSql++;
					}
				}
			}

			// This is a heuristic; some false positives expected for complex queries
			expect($interpolatedSql)->toBeLessThanOrEqual(2,
				"File {$relativeFile} may have SQL interpolation in prepared calls"
			);
		}
	});
});
