<?php

function smarty_compiler_widget_block($arrParams,  $smarty){
    $pageletId = $arrParams['pagelet_id'];
    $strCode = '<?php ';
    $strCode .= 'if (class_exists(\'FISPagelet\', false)) {';
    $strCode .=      'FISPagelet::start('.$pageletId.');';
    $strCode .= '}';
    $strCode .= '?>';
    return $strCode;
}

function smarty_compiler_widget_blockclose($arrParams,  $smarty){
    $pageletId = $arrParams['pagelet_id'];
    $strCode = '';
    $strCode .= '<?php ';
    $strCode .= 'if(class_exists(\'FISPagelet\', false)){';
    $strCode .= '   FISPagelet::end('.$pageletId.');';
    $strCode .= '}';
    $strCode .= '?>';
    return $strCode;
}