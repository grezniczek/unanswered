// Unanswered EM
// Dr. Günther Rezniczek, Marien Hospital Herne, Klinikum der Ruhr-Universität Bochum
// @ts-check
;(function() {

//#region Init global object and define local variables

const EM_NAME = 'Unanswered';
const NS_PREFIX = 'DE_RUB_';

// @ts-ignore
const EM = window[NS_PREFIX + EM_NAME] ?? {
    init: initialize
};
// @ts-ignore
window[NS_PREFIX + EM_NAME] = EM;

/** Configuration data supplied from the server */
let config = {};

let orig_setDataEntryFormValuesChanged = null;
let orig_doBranching = null;

let inserting = false;

//#endregion

/**
 * Implements the public init method.
 * @param {object} config_data 
 * @param {object} jsmo
 */
function initialize(config_data, jsmo = null) {
    config = config_data;
    config.JSMO = jsmo;
    log('Initialzing ...', config);
    
    orig_setDataEntryFormValuesChanged = window['setDataEntryFormValuesChanged'];
    window['setDataEntryFormValuesChanged'] = hooked_setDataEntryFormValuesChanged;
    orig_doBranching = window['doBranching'];
    window['doBranching'] = hooked_doBranching;
    count();
}

function count() {
    if (inserting) return;
    let count = 0;
    for (const field of config.fields) {
        if (config.excluded.includes(field)) continue;
        const $tr = $('tr[sq_id="' + field + '"]');
        // Skip some fields based on TR state
        if ($tr.hasClass('\@CALCTEXT') || $tr.hasClass('\@CALCDATE') || $tr.hasClass('\@HIDDEN') || $tr.css('display') == 'none') continue;
        if (config.isSurvey && $tr.hasClass('\@HIDDEN-SURVEY')) continue;
        if (!config.isSurvey && $tr.hasClass('\@HIDDEN-FORM')) continue;
        // Check value
        log('Checking field:', field);
        if (typeof document['form'][field] != 'undefined' && document['form'][field].value == '') {
            count++;
        }
        else {
            // Checkboxes - We only consider them unanswered if they are all unchecked but the field is marked as required

        }
        // Insert count
        inserting = true;
        for (const field of config.counters) {
            const val = '' + count;
            document['form'][field].value = val;
            $('input[name="' + field + '"]').val(val).trigger('blur');
            window['updatePipeReceivers'](field, window['event_id'], val);
        }
        inserting = false;
    }


    log('Unanswered count:', count);
}

//#region Hijack Hooks

function hooked_setDataEntryFormValuesChanged(field) {
    orig_setDataEntryFormValuesChanged(field);
    count();
}
function hooked_doBranching(field) {
    orig_doBranching(field);
    count();
}

//#endregion

//#region Debug Logging

/**
 * Logs a message to the console when in debug mode
 */
 function log() {
    if (!config.debug) return;
    var ln = '??';
    try {
        var line = ((new Error).stack ?? '').split('\n')[2];
        var parts = line.split(':');
        ln = parts[parts.length - 2];
    }
    catch(err) { }
    log_print(ln, 'log', arguments);
}
/**
 * Logs a warning to the console when in debug mode
 */
function warn() {
    if (!config.debug) return;
    var ln = '??';
    try {
        var line = ((new Error).stack ?? '').split('\n')[2];
        var parts = line.split(':');
        ln = parts[parts.length - 2];
    }
    catch(err) { }
    log_print(ln, 'warn', arguments);
}

/**
 * Logs an error to the console when in debug mode
 */
function error() {
    var ln = '??';
    try {
        var line = ((new Error).stack ?? '').split('\n')[2];
        var parts = line.split(':');
        ln = parts[parts.length - 2];
    }
    catch(err) { }
    log_print(ln, 'error', arguments);;
}

/**
 * Prints to the console
 * @param {string} ln Line number where log was called from
 * @param {'log'|'warn'|'error'} mode
 * @param {IArguments} args
 */
function log_print(ln, mode, args) {
    var prompt = EM_NAME + ' ' + config.version + ' [' + ln + ']';
    switch(args.length) {
        case 1:
            console[mode](prompt, args[0]);
            break;
        case 2:
            console[mode](prompt, args[0], args[1]);
            break;
        case 3:
            console[mode](prompt, args[0], args[1], args[2]);
            break;
        case 4:
            console[mode](prompt, args[0], args[1], args[2], args[3]);
            break;
        case 5:
            console[mode](prompt, args[0], args[1], args[2], args[3], args[4]);
            break;
        case 6:
            console[mode](prompt, args[0], args[1], args[2], args[3], args[4], args[5]);
            break;
        default:
            console[mode](prompt, args);
            break;
    }
}

//#endregion

})();