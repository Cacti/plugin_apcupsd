<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Cacti's __() formats at once whenever it gets more than the text: the text
 * domain counts as just another sprintf value. __('%s of %s rated', 'apcupsd')
 * therefore throws an ArgumentCountError (the Fleet Dashboard 500), and a
 * single placeholder silently prints the domain ('now apcupsd'). Placeholders
 * must be passed as __('%s of %s rated', $a, $b, 'apcupsd').
 */

/**
 * Find __()/__esc() calls whose literal text has a printf placeholder but
 * whose only other argument is the text domain.
 *
 * @return array<int, string> 'file:line text' for each offending call.
 */
function apcupsd_test_domain_only_placeholders(string $root): array {
	$found = [];
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', $file->getPathname());

		if (substr($path, -4) !== '.php' || preg_match('#/(tests|locales|vendor|\.git)/#', $path)) {
			continue;
		}

		$tokens = token_get_all((string) file_get_contents($path));

		foreach ($tokens as $i => $token) {
			if (!is_array($token) || $token[0] !== T_STRING || !in_array($token[1], ['__', '__esc'], true)) {
				continue;
			}

			$args  = [];
			$depth = 0;
			$cur   = '';

			for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
				$text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];

				if (in_array($text, ['(', '[', '{'], true) && ++$depth === 1) {
					continue;
				}

				if (in_array($text, [')', ']', '}'], true) && --$depth === 0) {
					$args[] = trim($cur);

					break;
				}

				if ($text === ',' && $depth === 1) {
					$args[] = trim($cur);
					$cur    = '';

					continue;
				}

				$cur .= $text;
			}

			if (count($args) === 2 && preg_match('/^([\'"])apcupsd\1$/', $args[1]) && preg_match('/%(?!%)[-+0-9.\']*[bcdeEfFgGosuxX]/', $args[0])) {
				$found[] = substr($path, strlen(str_replace('\\', '/', $root)) + 1) . ':' . $token[2] . ' ' . $args[0];
			}
		}
	}

	return $found;
}

describe('translated strings with placeholders', function () {
	it('pass their values before the text domain', function () {
		expect(apcupsd_test_domain_only_placeholders(dirname(__DIR__, 2)))->toBe([]);
	});

	it('is caught by the scanner when the broken form is used', function () {
		$dir = sys_get_temp_dir() . '/apcupsd_i18n_' . uniqid();
		mkdir($dir);
		file_put_contents($dir . '/bad.php', "<?php\n\$a = __('%s of %s rated', 'apcupsd');\n\$b = __('%s of %s rated', 1, 2, 'apcupsd');\n");

		$found = apcupsd_test_domain_only_placeholders($dir);

		unlink($dir . '/bad.php');
		rmdir($dir);

		expect($found)->toHaveCount(1);
		expect($found[0])->toContain('bad.php:2');
	});

	it('are formatted like Cacti: the text domain is a sprintf value, so too few values throw', function () {
		expect(__('%s of %s rated', '45%', '900 W', 'apcupsd'))->toBe('45% of 900 W rated');
		expect(__('now %s', '12 V', 'apcupsd'))->toBe('now 12 V');
		expect(__('Plain text', 'apcupsd'))->toBe('Plain text');
		expect(fn () => __('%s of %s rated', 'apcupsd'))->toThrow(ArgumentCountError::class);
	});
});

describe('NUT error messages', function () {
	it('name the UPS and error code they were given', function () {
		require_once __DIR__ . '/../../includes/nut.php';

		expect(apcupsd_nut_error('ERR UNKNOWN-UPS', 'ups1'))->toBe('The NUT server does not know a UPS named "ups1".');
		expect(apcupsd_nut_error('ERR SOMETHING-ELSE', 'ups1'))->toBe('The NUT server reported an error: SOMETHING-ELSE');
		expect(apcupsd_nut_error('ERR', 'ups1'))->toBe('The NUT server reported an error: ERR');
	});
});
