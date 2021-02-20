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

    function getError(/** @type string */ msg) {
        return DE_RUB_AutofillEM.params.errors ?
            '<div style="background-color:red;color:white;padding:0.5em;float:left;">' + msg + '</div>' :
            '';
    }

    // Helper function to create widgets
    function create(
        /** @type AutofillWidget */ widget, 
        /** @type JQuery */ $t
    ) {
        DE_RUB_AutofillEM.log('Creating widget defined in ' + widget.field, widget);
        DE_RUB_AutofillEM.log('  in target', $t);
        var html = '';
        if (widget.error.length) {
            html = getError(widget.error);
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
        if (html.length) {
            var $widget = $.parseHTML(html);
            $t.append($widget);
            $t.find('[data-autofill-em=autofill').on('click', function(e) {
                DE_RUB_AutofillEM.autofill(widget.groups, 'fill');
                e.preventDefault();
                return false;
            });
            $t.find('[data-autofill-em=clear').on('click', function(e) {
                DE_RUB_AutofillEM.autofill(widget.groups, 'clear');
                e.preventDefault();
                return false;
            });
        }
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

    // Check autofills for errors
    Object.keys(DE_RUB_AutofillEM.params.fields).forEach(function(field) {
        var $t = $('td.labelrc').first();
        /** @type FieldInfo */
        var fi = DE_RUB_AutofillEM.params.fields[field];
        for (var i = 0; i < fi.autofills; i++) {
            /** @type AutofillValue */
            var afv = fi[i];
            if (afv.error.length) {
                $t.append($(getError(afv.error)));
            }
        }
    });
};

// Autofill fields
DE_RUB_AutofillEM.autofill = function(groups, mode) {
    DE_RUB_AutofillEM.log('Autofilling groups', groups)

    function isMDC(/** @type AutofillValue */ afv, /** @type FieldInfo */ fi) {
        var code = $('#' + afv.field + '_MDLabel').attr('code');
        return code != "";
    }

    function set(/** @type AutofillValue */ afv, /** @type FieldInfo */ fi) {
        DE_RUB_AutofillEM.log((afv.overwrite ? 'Overwriting' : 'Autofilling') + ' field ' + afv.field + ' with value ' + afv.value)
        /** @type JQuery */
        var $el;
        var current = '';
        switch (fi.type) {
            case 'checkbox':
                if (isMDC(afv, fi)) {
                    $('img[name=missingDataButton][fieldname=' + afv.field + ']').trigger('click');
                    $('div[name=MDSetButton][code=""]').trigger('click');
                }
                afv.value.split(',').forEach(function(code) {
                    $el = $('input[name=__chk__' + afv.field + '_RC_' + code);
                    if ($el.length == 1) {
                        current = $el.val().toString()
                        if (afv.overwrite || current.length == 0) {
                            $('input[type=checkbox][name=__chkn__' + afv.field + '][code="' + code + '"]').trigger('click');
                        }
                    }
                });
                break;
            case 'radio':
                current = $('input[name="' + afv.field + '"]').val().toString();
                if (afv.overwrite || current.length == 0) {
                    // @ts-ignore
                    radioResetVal(afv.field, 'form');
                    setTimeout(function() {
                        $el = $('input[type=radio][value="' + afv.value + '"]');
                        $el.trigger('click');
                    }, 10)
                }
                break;
            case 'select':
            case 'sql':
                $el = $('select[name=' + afv.field + ']');
                if (afv.overwrite || $el.val().toString().length == 0) {
                    $el.val(afv.value);
                }
                break;
            case 'textarea':
                $el = $('textarea[name=' + afv.field + ']');
                if (afv.overwrite || $el.val().toString().length == 0) {
                    $el.val(afv.value);
                }
                break;
            case 'slider':
                $el = $('input[name=' + afv.field + ']');
                if (afv.overwrite || $el.val().toString().length == 0) {
                    // @ts-ignore
                    setSlider(afv.field, afv.value);
                }
                break;
            default:
                $el = $('input[name=' + afv.field + ']');
                if (afv.overwrite || $el.val().toString().length == 0) {
                    $el.val(afv.value);
                }
                break;
        }
        // @ts-ignore
        doBranching(afv.field);
    }
    
    function clear(/** @type AutofillValue */ afv, /** @type FieldInfo */ fi) {
        DE_RUB_AutofillEM.log('Clearing field ' + afv.field)
        /** @type JQuery */
        var $el;
        // Clear missing data code
        if (isMDC(afv, fi)) {
            $('img[name=missingDataButton][fieldname=' + afv.field + ']').trigger('click');
            $('div[name=MDSetButton][code=""]').trigger('click');
        }
        switch (fi.type) {
            case 'checkbox':
                break;
            case 'radio':
                // @ts-ignore
                radioResetVal(afv.field, 'form');
                break;
            case 'sql':
            case 'select':
                $el = $('select[name=' + afv.field + ']');
                $el.val('');
                break;
            case 'textarea':
                $el = $('textarea[name=' + afv.field + ']');
                $el.val('');
                break;
            case 'slider':
                // @ts-ignore
                resetSlider(afv.field, false);
                break;
            default:
                $el = $('input[name=' + afv.field + ']');
                $el.val('');
                break;
        }
        // @ts-ignore
        doBranching(afv.field);
    }

    Object.keys(DE_RUB_AutofillEM.params.fields).forEach(function(field) {
        /** @type FieldInfo */
        var fi = DE_RUB_AutofillEM.params.fields[field];
        for (var i = 0; i < fi.autofills; i++) {
            /** @type AutofillValue */
            var afv = DE_RUB_AutofillEM.params.fields[field][i];
            if (groups.includes(afv.group)) {
                if (mode == 'clear') {
                    clear(afv, fi);
                }
                else {
                    set(afv, fi);
                }
            }
        }
    });
};

