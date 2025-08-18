(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define(['exports', 'echarts'], factory);
    } else if (typeof exports === 'object' && typeof exports.nodeName !== 'string') {
        // CommonJS
        factory(exports, require('echarts'));
    } else {
        // Browser globals
        factory({}, root.echarts);
    }
}(this, function (exports, echarts) {
    var log = function (msg) {
        if (typeof console !== 'undefined') {
            console && console.error && console.error(msg);
        }
    };
    if (!echarts) {
        log('ECharts is not Loaded');
        return;
    }
    echarts.registerTheme('white', {
        "backgroundColor": "rgba(220, 230, 255, .7)",
        "grid": {
           "backgroundColor": "rgba(255, 255, 255, 1)" ,
        }
   });
    echarts.registerTheme('green', {
        "backgroundColor": "rgba(200, 255, 200, .7)",
        "grid": {
           "backgroundColor": "rgba(230, 255, 230, 1)" ,
        }
   });
    echarts.registerTheme('blue', {
        "backgroundColor": "rgba(180, 210, 255, .7)",
        "grid": {
           "backgroundColor": "rgba(210, 220, 255, 1)" ,
        }
   });
}));
