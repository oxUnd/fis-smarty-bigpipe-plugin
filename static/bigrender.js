var BigRender = function() {
    function getHtml(container) {
        if (!container) {
            return "";
        }
        var dom = container.getElementsByClassName('g_bigrender')[0];
        if (!dom) {
            return "";
        }
        var html = dom.firstChild.nodeValue;
        if (!html) {
            return "";
        }
        html = html.replace(/\\([\s\S]|$)/g,'$1');
        container.removeChild(container.firstChild);
        return html;
    }

    function render(id) {
        var container = document.getElementById(id);
        var html = getHtml(container);
        if (html.length > 0) {
            container.innerHTML = html;
            var scripts = container.getElementsByTagName('script');
            if (scripts.length > 0) {
                for (var i = 0, len = scripts.length;  i < len; i++) {
                    eval(scripts[i].innerHTML);
                }
            }
        }
    }

    return {
        render: render
    };
}();