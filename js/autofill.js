/**
 * Autofill - a REDCap External Module
 * https://github.com/grezniczek/redcap_autofill
 */

// @ts-check

/** @type AutofillEM */
var DE_RUB_AutofillEM = DE_RUB_AutofillEM || {};

// Debug logging
DE_RUB_AutofillEM.log = function() {
    if (DE_RUB_AutofillEM.params.debug) {
        switch(arguments.length) {
            case 1: 
                console.log(arguments[0]); 
                return;
            case 2: 
                console.log(arguments[0], arguments[1]); 
                return;
            case 3: 
                console.log(arguments[0], arguments[1], arguments[2]); 
                return;
            case 4:
                console.log(arguments[0], arguments[1], arguments[2], arguments[3]); 
                return;
            default:
                console.log(arguments);
        }
    }
};

// Initialization (set up widgets)
DE_RUB_AutofillEM.init = function() {

    DE_RUB_AutofillEM.log("Autofill EM - Initializing", DE_RUB_AutofillEM);

    // Helper function to create widgets
    function create(
        /** @type AutofillWidget */ widget, 
        /** @type JQuery */ $t
    ) {
        DE_RUB_AutofillEM.log('Creating widget defined in ' + widget.field, widget);
        DE_RUB_AutofillEM.log('  in target', $t);
        var html = '';
        if (widget.error.length) {
            html = '<div style="background-color:red;color:white;padding:0.5em;float:left;">' + widget.error + '</div>';
        }
        else {
            html += widget.before;
            if (widget.autofill) {
                html += '<button class="btn btn-primaryrc btn-xs ' + widget.autofillClass + '" data-autofill-em="autofill" style="' + widget.autofillStyle + '">' + widget.autofillLabel + '</button>';
            }
            if (widget.autofill && widget.clear) {
                html += widget.delimiter;
            }
            if (widget.clear) {
                html += '<button class="btn btn-defaultrc btn-xs ' + widget.clearClass + '" data-autofill-em="clear" style="' + widget.clearStyle + '">' + widget.clearLabel + '</button>';
            }
            html += widget.after;
        }
        var $widget = $.parseHTML(html);
        $t.append($widget);
        $t.find('[data-autofill-em=autofill').on('click', function(e) {
            DE_RUB_AutofillEM.clear(widget.groups);
            e.preventDefault();
            return false;
        });
        $t.find('[data-autofill-em=clear').on('click', function(e) {
            DE_RUB_AutofillEM.autofill(widget.groups);
            e.preventDefault();
            return false;
        });
    }

    // Add widgets
    Object.keys(DE_RUB_AutofillEM.params.widgets).forEach(function(field){
        /** @type FieldInfo */
        var widgets = DE_RUB_AutofillEM.params.widgets[field];
        for (var i = 0; i < widgets.autofills; i++) {
            /** @type AutofillWidget */
            var widget = widgets[i];
            var $target;
            // Is a target id defined?
            if (widget.target.length) {
                $target = $('#' + widget.target)
            }
            else {
                // Find the row
                var $tr = $('tr[sq_id=' + field + ']');
                // Is the field embedded elsewhere?
                var embedded = $tr.hasClass('row-field-embedded');
                if (embedded) {
                    $target = $('[class=rc-field-embed][var=' + field + ']')
                }
                else {
                    $target = $tr.find('td.labelrc')
                    if ($target.find('label#label-' + field).length) {
                        $target = $target.find('label#label-' + field + ' td').first();
                    }
                }
            }
            create(widget, $target);
        }
    });
};

// Autofill fields
DE_RUB_AutofillEM.autofill = function(groups) {
    DE_RUB_AutofillEM.log('Autofilling groups', groups)
};

// Clear autofill fields
DE_RUB_AutofillEM.clear = function(groups) {
    DE_RUB_AutofillEM.log('Clearing groups', groups)
};
