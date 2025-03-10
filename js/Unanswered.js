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

let counting = false;

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
    if (counting) return;
    counting = true;
    const counts = {};
    Object.keys(config.counters).forEach(key => counts[key] = 0);
    for (const field of config.fields) {
        if (config.excluded.includes(field)) continue;
        const $tr = $('tr[sq_id="' + field + '"]');
        // Skip some fields based on TR state
        if ($tr.hasClass('\@CALCTEXT') || 
            $tr.hasClass('\@CALCDATE') || 
            $tr.hasClass('\@HIDDEN') || 
            ($tr.css('display') == 'none' && !$tr.hasClass('row-field-embedded'))) {
                continue;
        }
        if (config.isSurvey && $tr.hasClass('\@HIDDEN-SURVEY')) continue;
        if (!config.isSurvey && $tr.hasClass('\@HIDDEN-FORM')) continue;
        // Check value
        if (typeof document['form'][field] != 'undefined') {
            log('Checking field:', field);
            const val = (document['form'][field].value == '') ? 1 : 0;
            Object.keys(counts).forEach(key => {
                if (config.counters[key].length == 0 || config.counters[key].includes(field)) {
                    counts[key] += val;
                }
            });
        }
        else {
            // Checkboxes - We only consider them unanswered if they are all unchecked but the field is marked as required
            log('Checking checkbox field:', field);
            const isRequired = $tr.attr('req') == '1';
            // Check if it is embedded and potentiall hidden, in which case we skip it
            const isHidden = $('.rc-field-embed[var="' + field + '"]').css('display') == 'none';
            if (isRequired && !isHidden) {
                let oneChecked = false;
                $('input[name="__chkn__' + field + '"]').each(function() {
                    if ($(this).is(':checked')) oneChecked = true;
                });
                const val = (oneChecked) ? 0 : 1;
                Object.keys(counts).forEach(key => {
                    if (config.counters[key].length == 0 || config.counters[key].includes(field)) {
                        counts[key] += val;
                    }
                });
            }
        }
    }
    // Insert count
    for (const field in counts) {
        const val = '' + counts[field];
        document['form'][field].value = val;
        $('input[name="' + field + '"]').val(val).trigger('blur');
        window['updatePipeReceivers'](field, window['event_id'], val);
    }
    counting = false;
    log('Unanswered count:', counts);
}

//#region Hijack Hooks

function hooked_setDataEntryFormValuesChanged(field) {
    orig_setDataEntryFormValuesChanged(field);
    log('Counting after setDataEntryFormValuesChanged for field:', field, counting);
    if (!counting) {
        count();
    }
}
function hooked_doBranching(field) {
    orig_doBranching(field);
    log('Counting after doBranching for field:', field, counting);
    if (!counting) {
        count();
    }
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