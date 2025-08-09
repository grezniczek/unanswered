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
let orig_dataEntrySubmit = null;

let counting = false;
let counts = {};
let missing = {};

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
	
	// Hijack Hooks
	orig_setDataEntryFormValuesChanged = window['setDataEntryFormValuesChanged'];
	window['setDataEntryFormValuesChanged'] = hooked_setDataEntryFormValuesChanged;
	orig_doBranching = window['doBranching'];
	window['doBranching'] = hooked_doBranching;
	orig_dataEntrySubmit = window['dataEntrySubmit'];
	window['dataEntrySubmit'] = hooked_dataEntrySubmit;

	// Additional triggers
	if (config.isSurvey) {
		// On surveys, we need to hook into some elements to get informed
		$('[data-kind="field-value"] input').on('change', (e) => count($(e.target).attr('name')));
		$('[data-kind="field-value"] textarea').on('change', (e) => count($(e.target).attr('name')));
		$('[data-kind="field-value"] select').on('change', (e) => count($(e.target).attr('name')));
	}

	// Hide all dialogs
	for (const counterName in config.counters) {
		if (config.counters[counterName].dialog != null) {
			$('tr[sq_id="' + config.counters[counterName].dialog + '"]').addClass('n-unanswered-dialog');
		}
	}

	// Translation support for default dialog title ()
	const $defaultTitle = $('<div></div>').html(config.defaultTitle).css('display', 'none');
	$defaultTitle.appendTo('#questiontable');
	config.$defaultTitle = $defaultTitle;

	// Initial count
	count();
}

/**
 * Counts unanswered fields
 * @param {string} initiator Field that triggered the count
 */
function count(initiator = '') {
	if (counting) return;
	log('Counting (initiator: ' + initiator + ') ...');
	counting = true;
	counts = {};
	missing = {};
	Object.keys(config.counters).forEach(key => { counts[key] = 0; missing[key] = []; });
	for (const field of config.fields) {
		for (const counter_name in config.counters) {
			const counter = config.counters[counter_name];
			if (counter.fieldType == 'calc') {
				// Calc takes care of counting
				counts[counter_name] = $('input[name="' + counter_name + '"]').val();
				continue;
			}
			if (counter.fields.length > 0 && !counter.fields.includes(field)) continue;
			if (counter.excluded.includes(field)) continue;
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
			// Skip any embedded fields that are embedded within a radio or checkbox choice, unless they are marked with @N-UNANSWERED-ALWAYS-INCLUDED
			if (!counter.alwaysIncluded.includes(field) && $tr.hasClass('row-field-embedded')) {
				const $container = $('.rc-field-embed[var="' + field + '"]').parents('[data-mlm-type="enum"]').first();
				if ($container.length > 0) {
					const id = $container.is('.ec') ? ($container.parent().attr('for') ?? '') : ($container.attr('for') ?? '');
					const checked = $('input#' + id).prop('checked');
					if (!checked) {
						log('Skipping radio/checkbox-embedded field:', field, id);
						continue;
					}
				}
			}
			// Skip any fields that have a missing data code set
			const missingCode = '' + ($('#' + field + '_MDLabel:visible').attr('code') ?? '');
			if (missingCode != '') {
				log('Skipping field with missing data code:', field);
				continue;
			}
			// Check value
			if (typeof document['form'][field] != 'undefined') {
				const val = (document['form'][field].value == '') ? 1 : 0;
				counts[counter_name] += val;
				if (val == 1) missing[counter_name].push(field);
				log('Checking field "' + field + '":', val == 0 ? 'Answered' : 'Unanswered');
			}
			else {
				// Checkboxes - We only consider them unanswered if they are all unchecked and the field is marked as required or if the field is to be always included
				const isRequired = $tr.attr('req') == '1';
				const alwaysIncluded = counter.alwaysIncluded.includes(field);
				// Check if it is embedded and potentiall hidden, in which case we skip it
				const isHidden = $('.rc-field-embed[var="' + field + '"]').css('display') == 'none';
				if ((isRequired || alwaysIncluded) && !isHidden) {
					let oneChecked = false;
					$('input[name="__chkn__' + field + '"]').each(function() {
						if ($(this).is(':checked')) oneChecked = true;
					});
					const val = (oneChecked) ? 0 : 1;
					counts[counter_name] += val;
					if (val == 1) missing[counter_name].push(field);
					log('Checking checkbox field "' + field + '":', val == 0 ? 'Answered' : 'Unanswered');
				}
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
	toggleHighlight(initiator, missing);
}

//#region Highlighting

function toggleHighlight(initiator, missing, withDialog = false) {
	if (initiator == '') return;
	initiator = initiator.replace('___radio', '').replace('__chkn__', '');
	log('Checking to highlight missing fields after change to "' + initiator + '":', missing);
	let bottom = Number.MAX_VALUE;
	// Get y-coordinate of initiator field
	if (withDialog == false) {
		const $tr = $('tr[sq_id="' + initiator + '"]');
		const $container = $tr.hasClass('row-field-embedded') ? $('.rc-field-embed[var="' + initiator + '"]').parents('tr[sq_id]') : $tr;
		if ($container.length == 0) return;
		$container.removeClass('n-unanswered-highlight').find('.n-unanswered-highlight').removeClass('n-unanswered-highlight');
		bottom = ($container?.offset()?.top ?? 0) + ($container.find('td.data').height() ?? 0);
	}
	// Loop through missing fields
	for (const counterName in missing) {
		const fields = missing[counterName];
		for (const field of fields) {
			const $tr = $('tr[sq_id="' + field + '"]');
			const $container = $tr.hasClass('row-field-embedded') ? $('.rc-field-embed[var="' + field + '"]') : $tr;
			if ($container.length == 0) continue;
			let top = withDialog ? Number.MIN_VALUE : $container?.offset()?.top ?? 0;
			// Should we highlight?
			const color = withDialog ? config.counters[counterName].highlightWithDialog : config.counters[counterName].highlightProgressive;
			// log('Checking to highlight field "' + field + '":', bottom, '>', top, $container);
			if (color != '' && (bottom > top || initiator == field)) {
				$container.addClass('n-unanswered-highlight');
				$container.css('--n-unanswered-highlight-color', color);
				log('Highlighted field "' + field + '" for counter "' + counterName + '" (color: ' + color + ') in container:', $container);
			}
		}
	}
}

//#endregion

//#region Dialog

function showDialog(dialogField, n, ob) {
	const regex = /{{(\w+):\s*(?:<\/?\w+[^>]*>)*([\s\S]*?)(?:<[^>]*>)*}}/mg;
	const srcContent = $('tr[sq_id="' + dialogField + '"] div[data-kind=field-label] div[data-mlm-type=label]').html();
	let cancelBtnLabel = window['lang'].global_53; // Cancel
	const $btn = typeof ob == 'string' ? $('#' + ob) : $(ob);
	let continueBtnLabel = $btn.html();
	let content = srcContent;
	let title = config.$defaultTitle.text(); // NOTICE
	let match;
	while ((match = regex.exec(srcContent)) !== null) {
		// This is necessary to avoid infinite loops with zero-width matches
		if (match.index === regex.lastIndex) {
			regex.lastIndex++;
		}
		switch (match[1]) {
			case 'title':
				title = match[2];
				break;
			case 'continue':
				continueBtnLabel = match[2];
				break;
			case 'cancel':
				cancelBtnLabel = match[2];
				break;
			default:
				break;
		}
		content = content.replace(match[0], '');
	}
	// Setup and show the dialog
	let $dlg = $('#n-unanswered-dialog');
	if ($dlg.length > 0) $dlg.remove();

	toggleHighlight(dialogField, missing, true);
	$('<div id="n-unanswered-dialog"></div>').dialog({
		modal: true,
		title: title,
		width: 500,
		open: function() {
			$(this).html(content);
		},
		buttons: [
			{
				html: cancelBtnLabel,
				click: function() {
					$(this).dialog('close');
					// Scroll to the first highlighted field
					$('tr.n-unanswered-highlight').first().get(0)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
					// Need to re-enable the survey submit button
					if (config.isSurvey) {
						log($btn);
						$btn.button('enable');
					}
				}
			},
			{
				html: continueBtnLabel,
				click: function() {
					$(this).dialog('close');
					orig_dataEntrySubmit(ob);
				}
			}
		],
		autoOpen: true
	});
	log('Showing dialog:', dialogField, continueBtnLabel, cancelBtnLabel, title, content);
}


//#endregion

//#region Hijack Hooks

function hooked_setDataEntryFormValuesChanged(field) {
	orig_setDataEntryFormValuesChanged(field);
	log('Counting after setDataEntryFormValuesChanged for field:', field, counting);
	if (!counting) {
		count(field);
	}
}
function hooked_doBranching(field) {
	orig_doBranching(field);
	log('Counting after doBranching for field:', field, counting);
	if (!counting) {
		count(field);
	}
}

/**
 * Hooked function to handle data entry / survey submission.
 * It counts unanswered fields and shows a dialog if there are any unanswered fields.
 * @param {string|object} ob The object or string representing the submit button.
 * @returns {boolean} Returns false if a dialog is shown, otherwise calls the original data entry submit function.
 */
function hooked_dataEntrySubmit(ob) {
	const submitMode = (typeof ob == 'string' ? ob : $(ob).attr('id') ?? '');
	log('Submitting data entry ...', submitMode);
	count();
	let dialogField = '';
	let n = 0;
	// Which dialog to show? Last one with missing fields wins
	for (const counterName in counts) {
		if (config.counters[counterName].dialog != null && counts[counterName] > 0) {
			const dialog = config.counters[counterName].dialog;
			// Skip when threshold not reached
			if (counts[counterName] < dialog.threshold) continue;
			// Skip when set to not trigger on Save & Stay 
			if (dialog.nss && submitMode == 'submit-btn-savecontinue') continue;
			dialogField = dialog.field;
			// Set filter
			n = counts[counterName];
		}
	}
	if (dialogField != '') {
		// Show dialog
		showDialog(dialogField, n, ob);
		return false;
	}
	return orig_dataEntrySubmit(ob);
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