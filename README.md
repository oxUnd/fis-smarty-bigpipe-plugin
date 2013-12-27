#### Quickling

基于fis支持quickling的smarty插件

#### Overview

插件支持两种渲染模式，

+ 正常渲染模式 `noscript`，不需要前端库进行控制
+ Pipeline渲染模式 `bigpipe`，需要前端库控制渲染

-----

#####`noscript` Render#####

######before######

```html
{%html%}
    {%head%}
        <title>This is a test</title>
    {%/head%}
    {%body%}
        {%style%}
            body {
                background-color: #EEEEEE;
            }
        {%/style%}
        {%script%}
            console.log("foo, foo, foo");
        {%/script%}
        {%widget_block pagelet_id="pager"%}
            {%widget name="widget/a.tpl"%}
            <div>
                //balabala
            </div>
        {%/widget_block%}
    {%/body%}
{%/html%}
```

#####after#####

```html
<html>
    <head>
        <title>This is a test</title>
        <style type="text/css">
            body {
                background-color: #EEEEEE;
            }
        </style>
    </head>
    <body>
        <div id="pager">
            <div>
                I'm `widget/a.tpl`
            </div>
            <div>
                //balabala....
            </div>
        </div>
        <script type="text/javascript">
        !function() {
            console.log("foo, foo, foo");
        }();
        </script>
    </body>
</html>
```

-------

####`bigpipe` render == `pipeline`####

#####before#####

```html
{%html mode="bigpipe"%}
    {%head%}
        <title>This is a test</title>
    {%/head%}
    {%body%}
        {%style%}
            body {
                background-color: #EEEEEE;
            }
        {%/style%}
        {%script%}
            console.log("foo, foo, foo");
        {%/script%}
        {%widget_block pagelet_id="pager"%}
            {%widget name="widget/a.tpl"%}
            <div>
                //balabala
            </div>
        {%/widget_block%}
    {%/body%}
{%/html%}
```

#####after#####

```html
<html>
    <head>
        <title>This is a test</title>
        <style type="text/css">
            body {
                background-color: #EEEEEE;
            }
        </style>
    </head>
    <body>
        <div id="pager">
        </div>
    </body>
</html>
<script type="text/javascript">
BigPipe.onPageReady(function() {
    console.log("foo, foo, foo");
});
</script>
<code style="display:none;" id="__cnt_0"><!--
<div>
    I'm `widget/a.tpl`
</div>
--></code>
<script type="text/javascript">
BigPipe.onPageletArrived({"id":"__elm_0",
"parent_id":"pager","html_id":"__cnt_0"});
</script>
<code style="display:none;" id="__cnt_1">
<!--
<div id="__elm_0"></div>
<div>
    //balabala....
</div>
--></code>
<script type="text/javascript">
BigPipe.onPageletArrived({"id":"pager", 
"html_id":"__cnt_1"});
</script>
<script type="text/javascript">
BigPipe.register({});
</script>
```

如上源码(before)和运行后的代码(after)；

#### Detail

在smarty里面为了控制输出，使用插件代替几个html标签。

|   标签         |  插件                        |                                          |
|:--------------:|:-----------------------------|:-----------------------------------------|
|   body         |      compiler.body.php       | 确定JS的位置                             |
|   html         |      compiler.html.php       | 初始化数据                               |
|   head         |      compiler.head.php       | 确定css安放位置                          |
|   script       |      compiler.script.php     | 收集内联脚本                             |
|   style        |      compiler.style.php      | 收集内联样式                             |
|   title        |      compiler.title.php      | 获取title，以便异步请求切换页面          |
|   cdn          |      compiler.cdn.php        | 动态cdn                                  |

在`正常模式`渲染下，js和css加载的位置，css在head关闭标签前；js在body关闭标签前。

在`pipeline`渲染下，js和css的链接给前端加载器，前端负责加载。包括html内容，前端负责渲染。

+ lib/FISResource.class.php  收集静态资源，<a href="http://pythontutor.com/visualize.html#code=collection+%3D+%5B%5D%0Adict+%3D+%7B%0A++++%22res%22%3A+%7B%0A++++++++%22a.js%22%3A+%7B%0A++++++++++++'uri'%3A+'/static/widget/a.js'%0A++++++++%7D,%0A++++++++%22c.js%22%3A+%7B%0A++++++++++++'uri'%3A+'/static/widget/b.js'%0A++++++++%7D%0A++++%7D%0A%7D%0A%0Adef+load(id)%3A%0A++++res+%3D+dict%5B'res'%5D%0A++++if+res.has_key(id)%3A%0A++++++++info+%3D+res%5Bid%5D%0A++++++++collection.append(info%5B'uri'%5D)%0A++++++++return+info%5B'uri'%5D%0A++++return+False%0A%0Aload('a.js')%0Aload('b.js')%0Aload('c.js')&mode=display&cumulative=false&heapPrimitives=false&drawParentPointers=false&textReferences=false&showOnlyOutputs=false&py=2&curInstr=0">算法演示</a>
+ lib/FISPagelet.class.php   初始化系统，输出模式控制

##### compiler.widget.php

``` smarty
{%widget name="/widget/a.tpl"%}

{%widget name="/widget/a.tpl" pagelet_id="test_id" mode="bigrender"%}  //支持bigrender

{%widget name="/widget/a.tpl" pagelet_id="test_id" mode="quickling"%}  //支持wdiget异步化

{%widget name="/widget/a.tpl" pagelet_id="test_id" mode="quickling" group="test_group"%} // quickling + group
```

##### 动态cdn使用原则

+ 动态cdn只对js，css资源起效，静态cdn和动态cdn不能同时在同一个资源引用上使用
+ 异步组件是否添加cdn，取决于是否使用`mod-store.js`，如果使用了则不加，如果没有使用则加。@TODO
+ 图片的cdn可以使用静态cdn [domain.image](https://github.com/fis-dev/fis/wiki/%E9%85%8D%E7%BD%AEAPI#roadmapdomainimage)
+ 其他一些静态资源也使用静态cdn [domain](https://github.com/fis-dev/fis/wiki/%E9%85%8D%E7%BD%AEAPI#roadmapdomain)


#### 关于部署迁移

OK, 各个插件的功能都已经说过了。现在说一下部署的问题

-----

**纯粹的FIS 2.0项目**

把所有插件放到smarty的 plugin_dir下。

-----

**和fis 1.0共存？**

把所有插件放到smarty的 plugin_dir下，并删除`compiler.widget_block.php`

**2.0里面有一个插件和1.0有冲突，那就是`compiler.widget_block.php`，请删除2.0里面的.**
