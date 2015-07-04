<?php
if (!class_exists('FISResource', false)) require_once(dirname(__FILE__) . '/FISResource.class.php');

/**
 * Checks a string for UTF-8 encoding.
 *
 * @param  string $string
 * @return boolean
 */
function isUtf8($string) {
    $length = strlen($string);

    for ($i = 0; $i < $length; $i++) {
        if (ord($string[$i]) < 0x80) {
            $n = 0;
        }

        else if ((ord($string[$i]) & 0xE0) == 0xC0) {
            $n = 1;
        }

        else if ((ord($string[$i]) & 0xF0) == 0xE0) {
            $n = 2;
        }

        else if ((ord($string[$i]) & 0xF0) == 0xF0) {
            $n = 3;
        }

        else {
            return FALSE;
        }

        for ($j = 0; $j < $n; $j++) {
            if ((++$i == $length) || ((ord($string[$i]) & 0xC0) != 0x80)) {
                return FALSE;
            }
        }
    }

    return TRUE;
}

/**
 * Converts a string to UTF-8 encoding.
 *
 * @param  string $string
 * @return string
 */
function convertToUtf8($string) {

    if (!is_string($string)) {
        return '';
    }

    if (!isUtf8($string)) {
        if (function_exists('mb_convert_encoding')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'GBK');
        } else {
            $string = iconv('GBK','UTF-8//IGNORE', $string);
        }
    }

    return $string;
}

/**
 * Class FISPagelet
 * DISC:
 * 构造pagelet的html以及所需要的静态资源json
 */
class FISPagelet {

    const CSS_LINKS_HOOK = '<!--[FIS_CSS_LINKS_HOOK]-->';
    const JS_SCRIPT_HOOK = '<!--[FIS_JS_SCRIPT_HOOK]-->';

    const MODE_NOSCRIPT = 0;
    const MODE_QUICKLING = 1;
    const MODE_BIGPIPE = 2;
    const MODE_BIGRENDER = 3;

    /**
     * 收集widget内部使用的静态资源
     * array(
     *  0: array(), 1: array(), 2: array()
     * )
     * @var array
     */
    static protected $inner_widget = array(
        array(),
        array(),
        array()
    );

    /**
     * 记录pagelet_id
     * @var string
     */
    static private $_pagelet_id = null;

    static private $_session_id = 0;
    static private $_context = array();
    static private $_contextMap = array();
    static private $_pagelets = array();
    static private $_title = '';
    static private $_pagelet_group = array();
    /**
     * 解析模式
     * @var number
     */
    static protected $mode = null;

    static protected $default_mode = null;

    static protected $bigrender = false;

    /**
     * 某一个widget使用那种模式渲染
     * @var number
     */
    static protected  $widget_mode;

    static protected  $filter;

    static public $cp;
    static public $arrEmbeded = array();

    static public $cdn;

    /**
     * 设置渲染模式及其需要渲染的widget
     * @param $default_mode string 设置默认渲染模式
     */
    static public function init($default_mode) {
        if (is_string($default_mode)
            && in_array(
                self::_parseMode($default_mode),
                array(self::MODE_BIGPIPE, self::MODE_NOSCRIPT))
        ) {
            self::$default_mode = self::_parseMode($default_mode);
        } else {
            self::$default_mode = self::MODE_NOSCRIPT;
        }

        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($is_ajax) {
            self::setMode(self::MODE_QUICKLING);
        } else {
            self::setMode(self::$default_mode);
        }
        self::setFilter($_GET['pagelets']);
    }

    static public function setMode($mode){
        if (self::$mode === null) {
            self::$mode = isset($mode) ? intval($mode) : 1;
        }
    }

    static public function setFilter($ids) {
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $id) {
            self::$filter[$id] = true;
        }
    }

    static public function setTitle($title) {
        self::$_title = $title;
    }

    static public function getUri($strName, $smarty) {
        return FISResource::getUri($strName, $smarty);
    }

    static public function addScript($code) {
        if(self::$_context['hit'] || self::$mode == self::$default_mode){
            FISResource::addScriptPool($code);
        }
    }

    static public function addStyle($code) {
        if(self::$_context['hit'] || self::$mode == self::$default_mode){
            FISResource::addStylePool($code);
        }
    }

    public static function cssHook() {
        return self::CSS_LINKS_HOOK;
    }

    public static function jsHook() {
        return self::JS_SCRIPT_HOOK;
    }

    static function load($str_name, $smarty, $async = false) {
        if(self::$_context['hit'] || self::$mode == self::$default_mode){
            FISResource::load($str_name, $smarty, $async);
        }
    }

    static private function _parseMode($str_mode) {
        $str_mode = strtoupper($str_mode);
        $mode = self::$mode;
        switch($str_mode) {
            case 'BIGPIPE':
                $mode = self::MODE_BIGPIPE;
                break;
            case 'QUICKLING':
                $mode = self::MODE_QUICKLING;
                break;
            case 'NOSCRIPT':
                $mode = self::MODE_NOSCRIPT;
                break;
            case 'BIGRENDER':
                $mode = self::MODE_BIGRENDER;
                break;
        }
        return $mode;
    }


    /**
     * WIDGET START
     * 解析参数，收集widget所用到的静态资源
     * @param $id
     * @param $mode
     * @param $group
     * @return bool
     */
    static public function start($id, $mode = null, $group = null) {
        $has_parent = !empty(self::$_context);

        if ($mode) {
            $widget_mode = self::_parseMode($mode);
        } else {
            $widget_mode = self::$mode;
        }

        self::$_pagelet_id = $id;

        $parent_id = $has_parent ? self::$_context['id'] : '';
        $qk_flag = self::$mode == self::MODE_QUICKLING ? '_qk_' : '';
        $id = empty($id) ? '__elm_' . $parent_id . '_' . $qk_flag . self::$_session_id ++ : $id;


        $parent = self::$_context;

        $has_parent = !empty($parent);

        //$hit
        $hit = true;

        $context = array(
            'id' => $id,            //widget id
            'mode' => $widget_mode, //当前widget的mode
            'hit' => $hit          // 是否命中
        );

        if ($has_parent) {
            $context['parent_id'] = $parent['id'];
            self::$_contextMap[$parent['id']] = $parent;
        }

        if ($widget_mode == self::MODE_NOSCRIPT) {
            //只有指定paglet_id的widget才嵌套一层div
            if (self::$_pagelet_id) {
                echo '<div id="' . $id . '">';
            }
        } else {

            if ($widget_mode == self::MODE_BIGRENDER) {
                //widget 为bigrender时，将内容渲染到html注释里面
                if (!$has_parent) {
                    echo '<div id="' . $id . '">';
                    echo '<code class="g_bigrender"><!--';
                }
            } else {
                echo '<div id="' . $id . '">';
            }

            if (self::$mode == self::MODE_QUICKLING) {
                $hit = self::$filter[$id];
                //如果父widget被命中，则子widget设置为命中
                if ($has_parent && $parent['hit']) {
                    $hit = true;
                } else if ($hit) {
                    //指定获取一个子widget时，需要单独处理这个widget
                    $context['parent_id'] = null;
                    $has_parent = false;
                }
            } else if ($widget_mode == self::MODE_QUICKLING) {
                //渲染模式不是quickling时，可以认为是首次渲染
                if (self::$_pagelet_id && self::$mode != self::MODE_QUICKLING) {
                    if (!$group) {
                        echo '<textarea class="g_fis_bigrender" style="display:none;">'
                            .'BigPipe.asyncLoad({id: "'.$id.'"});'
                            .'</textarea>';
                    } else {
                        if (isset(self::$_pagelet_group[$group])) {
                            self::$_pagelet_group[$group][] = $id;
                        } else {
                            self::$_pagelet_group[$group] = array($id);
                            echo "<!--" . $group . "-->";
                        }
                    }
                }
                // 不需要渲染这个widget
                $hit = false;
            }

            $context['hit'] = $hit;

            if ($hit) {
                if (!$has_parent) {
                    //获取widget内部的静态资源
                    FISResource::widgetStart();
                }
                //start a buffer
                ob_start();
            }
        }

        //设置当前处理context
        self::$_context = $context;

        return $hit;
    }

    /**
     * WIDGET END
     * 收集html，收集静态资源
     */
    static public function end() {
        $ret = true;

        $context = self::$_context;
        $widget_mode = $context['mode'];
        $has_parent = $context['parent_id'];

        if ($id) {
            self::$_pagelet_id = $id;
        }

        if ($widget_mode == self::MODE_NOSCRIPT) {
            if (self::$_pagelet_id) {
                echo '</div>';
            }
        } else {
            if ($context['hit']) {
                //close buffer
                $html = ob_get_clean();
                if (!$has_parent) {
                    $widget = FISResource::widgetEnd();
                    // end
                    if ($widget_mode == self::MODE_BIGRENDER) {
                        $widget_style = $widget['style'];
                        $widget_script = $widget['script'];
                        //内联css和script放到注释里面, 不需要收集
                        unset($widget['style']);
                        unset($widget['script']);

                        $out = '';
                        if ($widget_style) {
                            $out .= '<style type="text/css">'. implode('', $widget_style) . '</style>';
                        };

                        $out .= $html;
                        if ($widget_script) {
                            $out .= '<script type="text/javascript">' . implode('', $widget_script) . '</script>';
                        }
                        echo str_replace (
                            array('\\', '-->'),
                            array('\\\\', '--\\>'),
                            $out
                        );

                        $html = '';

                        echo '--></code></div>';

                        //收集外链的js和css
                        self::$inner_widget[self::$mode][] = $widget;

                    } else {
                        $context['html'] = $html;
                        //删除不需要的信息
                        unset($context['mode']);
                        unset($context['hit']);
                        //not parent
                        unset($context['parent_id']);
                        self::$_pagelets[] = $context;
                        self::$inner_widget[$widget_mode][] = $widget;
                    }
                } else {
                    // end
                    if ($widget_mode == self::MODE_BIGRENDER) {
                        echo $html;
                    } else {
                        $context['html'] = $html;
                        //删除不需要的信息
                        unset($context['mode']);
                        unset($context['hit']);
                        self::$_pagelets[] = $context;
                    }
                }
            }

            if ($widget_mode != self::MODE_BIGRENDER) {
                echo '</div>';
            }

        }

        //切换context
        self::$_context = self::$_contextMap[$context['parent_id']];
        unset(self::$_contextMap[$context['parent_id']]);
        if (!$has_parent) {
            self::$_context = null;
        }

        return $ret;
    }


    /**
     * 设置cdn
     */
    static public function setCdn($cdn) {
        $cdn = trim($cdn);
        self::$cdn = $cdn;
    }

    static public function getCdn() {
        return self::$cdn;
    }


    /**
     * 渲染静态资源
     * @param $html
     * @param $arr
     * @param bool $clean_hook
     * @return mixed
     */
    static public function renderStatic($html, $arr, $clean_hook = false) {
        if (!empty($arr)) {
            $code = '';
            $resource_map = $arr['async'];
            $loadModJs = (FISResource::getFramework() && ($arr['js'] || $resource_map));
            if ($loadModJs) {
                $code .= '<script type="text/javascript" src="'.self::getCdn() . FISResource::getFramework().'"></script>';
                if ($resource_map) {
                    $code .= '<script type="text/javascript">';
                    $code .= 'require.resourceMap('.json_encode($resource_map).');';
                    $code .= '</script>';
                }
                foreach ($arr['js'] as $js) {
                    if ($js == FISResource::getFramework()) {
                        continue;
                    }
                    $code .= '<script type="text/javascript" src="' . self::getCdn() . $js . '"></script>';
                }
            }

            if (!empty($arr['script'])) {
                $code .= '<script type="text/javascript">'. PHP_EOL;
                foreach ($arr['script'] as $inner_script) {
                    $code .= '!function(){'.$inner_script.'}();'. PHP_EOL;
                }
                $code .= '</script>';
            }
            $html = str_replace(self::JS_SCRIPT_HOOK, $code . self::JS_SCRIPT_HOOK, $html);
            $code = '';
            if (!empty($arr['css'])) {
                $code = '<link rel="stylesheet" type="text/css" href="' . self::getCdn()
                    . implode('" /><link rel="stylesheet" type="text/css" href="' . self::getCdn(), $arr['css'])
                    . '" />';
            }
            if (!empty($arr['style'])) {
                $code .= '<style type="text/css">';
                foreach ($arr['style'] as $inner_style) {
                    $code .= $inner_style;
                }
                $code .= '</style>';
            }
            //替换
            $html = str_replace(self::CSS_LINKS_HOOK, $code . self::CSS_LINKS_HOOK, $html);
        }
        if ($clean_hook) {
            $html = str_replace(array(self::CSS_LINKS_HOOK, self::JS_SCRIPT_HOOK), '', $html);
        }
        return $html;
    }

    /**
     * @param $html string html页面内容
     * @return mixed
     */
    static public function insertPageletGroup($html) {
        if (empty(self::$_pagelet_group)) {
            return $html;
        }
        $search = array();
        $replace = array();
        foreach (self::$_pagelet_group as $group => $ids) {
            $search[] = '<!--' . $group . '-->';
            $replace[] = '<textarea class="g_fis_bigrender g_fis_bigrender_'.$group.'" style="display: none">BigPipe.asyncLoad([{id: "'.
                implode('"},{id:"', $ids)
            .'"}])</textarea>';
        }
        return str_replace($search, $replace, $html);
    }

    static function merge_resource(array $array1, array $array2) {
        $res = array(
            'js' => array(),
            'css' => array(),
            'script' => array(),
            'style' => array(),
            'async' => array(
                'res' => array(),
                'pkg' => array()
            )
        );

        $merged = array();

        foreach ($res as $key => $val) {
            if (!is_array($array1[$key])) {
                $array1[$key] = $val;
            }

            if (!is_array($array2[$key])) {
                $array2[$key] = $val;
            }

            if ($key != 'async') {
                $merged = array_merge($array1[$key], $array2[$key]);
                $merged = array_merge(array_unique($merged));
            } else {
                $merged = array(
                    'res' => array_merge($array1['async']['res'], (array)$array2['async']['res']),
                    'pkg' => array_merge($array1['async']['pkg'], (array)$array2['async']['pkg'])
                );
            }
            //合并收集
            $array1[$key] = $merged;
        }
        return $array1;
    }

    static public function display($html) {
        $html = self::insertPageletGroup($html);
        $pagelets = self::$_pagelets;
        $mode = self::$mode;

        $res = array();

        //合并资源
        foreach (self::$inner_widget[$mode] as $item) {
            $res = self::merge_resource($res, $item);
        }

        if ($mode != self::MODE_NOSCRIPT) {
            //add cdn
            foreach ((array)$res['js'] as $key => $js) {
                $res['js'][$key] = self::getCdn() . $js;
            }

            foreach ((array)$res['css'] as $key => $css) {
                $res['css'][$key] = self::getCdn() . $css;
            }
        }

        //tpl信息没有必要打到页面
        switch($mode) {
            case self::MODE_NOSCRIPT:
                //渲染widget以外静态文件
                $all_static = FISResource::getArrStaticCollection();
                $all_static = self::merge_resource($all_static, $res);

                $html = self::renderStatic(
                    $html,
                    $all_static,
                    true
                );

                break;
            case self::MODE_QUICKLING:
                header('Content-Type: text/json; charset=utf-8');
                if (is_array($res['script'])) {
                    $res['script'] = convertToUtf8(implode("\n", $res['script']));
                }
                if (is_array($res['style'])) {
                    $res['style'] = convertToUtf8(implode("\n", $res['style']));
                }
                foreach ($pagelets as &$pagelet) {
                    $pagelet['html'] = convertToUtf8(self::insertPageletGroup($pagelet['html']));
                }
                unset($pagelet);
                $title = convertToUtf8(self::$_title);
                $html = json_encode(array(
                    'title' => $title,
                    'pagelets' => $pagelets,
                    'resource_map' => $res
                ));
                break;
            case self::MODE_BIGPIPE:
                $external = FISResource::getArrStaticCollection();
                $page_script = $external['script'];
                unset($external['script']);
                $html = self::renderStatic(
                    $html,
                    $external,
                    true
                );
                $html .= "\n";
                $html .= '<script type="text/javascript">';
                $html .= 'BigPipe.onPageReady(function() {';
                $html .= implode("\n", $page_script);
                $html .= '});';
                $html .= '</script>';
                $html .= "\n";

                if (is_array($res['script'])) {
                    $res['script'] = convertToUtf8(implode("\n", $res['script']));
                }
                if (is_array($res['style'])) {
                    $res['style'] = convertToUtf8(implode("\n", $res['style']));
                }
                $html .= "\n";
                foreach($pagelets as $index => $pagelet){
                    $id = '__cnt_' . $index;
                    $html .= '<code style="display:none" id="' . $id . '"><!-- ';
                    $html .= str_replace(
                        array('\\', '-->'),
                        array('\\\\', '--\\>'),
                        self::insertPageletGroup($pagelet['html'])
                    );
                    unset($pagelet['html']);
                    $pagelet['html_id'] = $id;
                    $html .= ' --></code>';
                    $html .= "\n";
                    $html .= '<script type="text/javascript">';
                    $html .= "\n";
                    $html .= 'BigPipe.onPageletArrived(';
                    $html .= json_encode($pagelet);
                    $html .= ');';
                    $html .= "\n";
                    $html .= '</script>';
                    $html .= "\n";
                }
                $html .= '<script type="text/javascript">';
                $html .= "\n";
                $html .= 'BigPipe.register(';
                if(empty($res)){
                    $html .= '{}';
                } else {
                    $html .= json_encode($res);
                }
                $html .= ');';
                $html .= "\n";
                $html .= '</script>';
                break;
        }

        return $html;
    }

    //smarty output filter
    static function renderResponse($content, $smarty) {
        return self::display($content);
    }
}
