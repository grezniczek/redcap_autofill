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

    // Add widgets


};

// Autofill fields
DE_RUB_AutofillEM.autofill = function(groups) {

};

// Clear autofill fields
DE_RUB_AutofillEM.clear = function(groups) {

};
