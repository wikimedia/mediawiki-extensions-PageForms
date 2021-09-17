<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'specials/',
		'languages/',
		'../../extensions/AdminLinks',
		'../../extensions/Cargo',
		'../../extensions/PageSchemas',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/AdminLinks',
		'../../extensions/Cargo',
		'../../extensions/PageSchemas',
	]
);

// TODO Enable
$cfg['null_casts_as_any_type'] = true;
$cfg['scalar_implicit_cast'] = true;

$cfg['suppress_issue_types'] = array_merge(
	$cfg['suppress_issue_types'],
	// NOTE: New issues should NOT be added here, but suppressed inline instead
	// (unless there's a valid reason not to)
	[
		// PHP < 7 support...
		'PhanPluginDuplicateConditionalNullCoalescing',

		// Cannot properly install SMW in CI, so suppress possibly-related issues. This should also help
		// with method that were added/removed in different MW versions, but may hide some genuine issues.
		'PhanUndeclaredClassReference',
		'PhanUndeclaredClassMethod',
		'PhanUndeclaredClassInstanceof',
		'PhanUndeclaredClassProperty',
		'PhanUndeclaredClassConstant',
		'PhanUndeclaredFunction',
		'PhanUndeclaredTypeReturnType',
		'PhanUndeclaredConstant',

		// Issues with BC code
		'PhanUndeclaredMethod',
		'PhanUndeclaredStaticMethod',

		// Many instances due to a phan bug, TODO enable when upgrading to mw-phan-config > 0.11.0
		'PhanPossiblyUndeclaredVariable',

		// TODO Enable
		'SecurityCheck-DoubleEscaped',
		'SecurityCheck-XSS',
		'SecurityCheck-ReDoS',
	]
);

$cfg['globals_type_map'] = array_merge(
	$cfg['globals_type_map'],
	[
		'edgValues' => 'array[]'
	]
);

return $cfg;
