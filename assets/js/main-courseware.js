define(['domReady!', 'scrollTo', 'backbone', 'assets/js/url', 'assets/js/block_types', 'assets/js/block_model', 'blocks/Courseware/js/Courseware'], function (domReady, scrollTo, Backbone, helper, block_types, BlockModel, Courseware) {

    function logError(error) {
        if (console) {
            console.log(error);
        }
    }

    window.onerror  = function (message, file, line) {
        logError(file + ':' + line + '\n\n' + message);
    };

    Backbone.history.start({
        push_state: true,
        silent: true,
        root: helper.courseware_url
    });

    var $el = jQuery("#courseware");
    var model = new BlockModel({
        id: $el.attr("data-blockid"),
        type: "Courseware"
    });
    Courseware.createView("student", {
        el: $el,
        model: model
    });
});
