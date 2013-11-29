<?php

function smarty_compiler_cdn($params, $smarty) {
	$cdn = isset($params['domain']) ? $params['domain'] : '';
	$strCode = '';
	$strCode .= '<?php ';
	$strCode .= 'if (class_exists("FISPagelet", false)) {';
	$strCode .= 'FISPagelet::setCdn('.$cdn.');';
	$strCode .= '}';
	$strCode .= ' ?>';
	return $strCode;
}