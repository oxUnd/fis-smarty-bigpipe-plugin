<?php

function smarty_compiler_cdn($params, $smarty) {
	$cdn = isset($params['domain']) ? $params['domain'] : '';
	$strCode = '';
	if (class_exists('FISPagelet')) {
		$strCode .= '<?php ';
		$strCode .= 'FISPagelet::setCdn('.$cdn.');';
		$strCode .= ' ?>';
	}
	return $strCode;
}