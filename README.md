# Unanswered (REDCap External Module)

[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.15530714.svg)](https://doi.org/10.5281/zenodo.15530714)

A REDCap external module that supports data completeness by identifying and responding to unanswered fields in real time.

## Synopsis

**Unanswered** is a REDCap external module that enhances data quality oversight by dynamically tracking unanswered fields on forms and survey pages. It provides project designers with flexible tools to count missing data, highlight incomplete entries, and prompt users before saving incomplete records. The module supports both generic and targeted counting of required fields, with options to fine-tune behavior based on context (e.g., exclude fields, include conditionally embedded fields, or highlight progressively).

Dialog popups can be configured to inform users of missing fields before submission, and the visual highlighting features improve usability and data completeness without requiring custom coding. The module is compatible with both data entry and survey modes and supports dynamic page behaviors across multi-section instruments.

The functionality is highly customizable and integrates seamlessly into existing REDCap workflows, promoting completeness and consistency throughout the data collection process.

### Use cases

- **Prevent incomplete submissions**  
  Display a dialog warning when users attempt to submit a form or survey with unanswered required questions.

- **Visual feedback for data entry personnel**  
  Highlight missing responses during or after form completion to guide data collectors in real time.

- **Conditional completeness logic**  
  Count only a specific set of fields or sections when calculating unanswered items, allowing form-specific behavior.

- **Flexible survey flow**  
  Support one-section-per-page surveys by including entire sections based on a single field reference.

- **Audit-readiness**  
  Ensure that critical fields are not unintentionally skipped, improving compliance with data quality protocols.

This external module fully supports MLM integration.

## Requirements

- REDCap with EM Framework v16 support (REDCap 14.6.4+, 15.0.9+ LTS).

## Installation

- Clone this repo into `<redcap-root>/modules/unanswered_v<version-number>`, or
- Obtain this module from the Consortium [REDCap Repo](https://redcap.vumc.org/consortium/modules/index.php) via the Control Center (_External Modules_ > _Manage_ > _View modules available in the REDCap Repo_), and then
- Go to _Control Center > External Modules_ > _Manage_ > _Enable a module_ and enable **Unanswered**.
- Enable the module in any project where its functionality is required (_External Modules_ > _Manage_ > _Enable a module_).

## Configuration

### System-level (Control Center)

There are no system-level configuration options specific for this module.

### Project-level

- **Output debug information to the browser console**: When enabled, detailed information about the module's processing on a data entry or survey page is output to the browser's console.  
  This option should be enabled only temporarily in order to help troubleshootung any issues.
- All further setup and configuration is done using **action tags** (see below).

## Action Tags

### @N-UNANSWERED

This is the primary action tag. All others are used in conjunction with it, on the same field or on other fields, to fine-tune the module’s behavior or enable additional features.

`@N-UNANSWERED` counts the number of unanswered fields on a data entry form or survey page and writes the result into the field where it is applied. This field must be of type '**Text Box**' with validation set to '**Integer**'.

Optionally, a comma-separated list of field names can be provided as a parameter to restrict the count to specific fields. For example, `@N-UNANSWERED='field1,field2,field3'` limits the count to _field1_, _field2_, and _field3_. If a field name in this list is prefixed with `__` (double underscore), all fields within the same section as that field will be included. This allows for consistent behavior across instruments in both data entry and survey modes (e.g., when surveys are displayed one section per page).

Alternatively, the asterisk `*` may be used as the parameter (e.g., `@N-UNANSWERED='*'`) to count all fields marked as **required**. When using this option, modifiers such as `@N-UNANSWERED-EXCLUDED` will be ignored.

### @N-UNANSWERED-EXCLUDED

Attach this action tag to any field that should be excluded from the `@N-UNANSWERED` count. 

To exclude a field from a specific `@N-UNANSWERED` instance, supply the field name of that counter as a string parameter, e.g., `@N-UNANSWERED-EXCLUDED='counter'`, where _counter_ is the field containing the `@N-UNANSWERED` tag.

Alternatively, add this tag to the same field as the `@N-UNANSWERED` tag and provide a comma-separated list of field names in the string parameter to exclude multiple fields.

### @N-UNANSWERED-ALWAYS-INCLUDED

Use this tag for any field that is embedded in the label of a radio button or checkbox and should always be counted by `@N-UNANSWERED`, even when the radio/checkbox is not selected. Normally, such embedded fields are only counted when the associated radio or checkbox is checked.

To link this tag to a specific `@N-UNANSWERED` counter, supply that counter’s field name as a parameter. Alternatively, if this tag is added to the same field as `@N-UNANSWERED`, provide a comma-separated list of field names to always include.

This action tag can be used to mark a checkbox field to be considered unanswered when none of its options are checked. Otherwise, checkbox fields that are not marked as required are not considered unanswered when none of their options are selected.

### @N-UNANSWERED-DIALOG

Use this tag only in combination with `@N-UNANSWERED` (on the same field). When active, it displays a dialog if the user or survey respondent tries to save a page that still has unanswered fields (i.e., when the count is greater than 0).

Optional parameters include:
1. A field name (e.g., a descriptive field) whose label should be shown as the dialog’s content.
2. A threshold value (integer) that must be met or exceeded for the dialog to appear. Default is 1.
3. The flag `NSS` (Not for Save & Stay). When used, the dialog will not appear if the user clicks _Save & Stay_.
4. The flag `NPP` (Not for Previous Page). When used, the dialog will not appear when going to a previous survey page.

Parameters should be comma-separated and can be in any order. Examples:  
`@N-UNANSWERED-DIALOG='3'`,  
`@N-UNANSWERED-DIALOG='desc_dialog,3,NSS'`
`@N-UNANSWERED-DIALOG='desc_dialog,NPP`

The dialog can be customized using special elements in the label:
- Wrap content in double curly braces, e.g., `{{title:Missing Data}}`.
- Supported types: `title`, `cancel`, and `continue`:
  - `title` content will be used as the dialog's title (default: _"NOTICE"_).
  - `cancel` content will be used as the cancel button's label (default: _"Cancel"_); when clicked, submission of the form or survey page will be cancelled.
  - `continue` content will be used as the continue (i.e., submit) button. The default label reflects the label of the submit button (e.g., _"Save & Exit Form"_ or _"Submit"_).
- Avoid formatting that interferes with the tag structure (starting with `{{label:`and ending with `}}`), especially when using the Rich Text Editor. Basic formatting within the content itself is supported.
- The remaining text will be shown in the dialog's body. Note that piping is fully supported, as in every label.

**Note:**  
The field providing the dialog content will be hidden and must not be embedded in other elements. If multiple dialogs are active, only the one linked to the highest number of unanswered fields will be shown.

### @N-UNANSWERED-HIGHLIGHT-WITH-DIALOG

Use this tag only together with `@N-UNANSWERED` (on the same field). When enabled, unanswered fields will be highlighted when a dialog has been triggered upon saving the page (not when navigating back on a survey).

The default highlight color is red. To use a different color, provide it as a parameter, e.g.,  
`@N-UNANSWERED-HIGHLIGHT-WITH-DIALOG='orange'`

Any valid CSS color value is supported, such as `red`, `#ff0000`, or `rgb(255, 0, 0)`.

### @N-UNANSWERED-HIGHLIGHT-PROGRESSIVE

Use this tag only together with `@N-UNANSWERED` (on the same field). When active, unanswered fields will be progressively highlighted in red, or in a specified color.

To set a custom color, pass it as a parameter, e.g.,  
`@N-UNANSWERED-HIGHLIGHT-PROGRESSIVE='orange'`

Any valid CSS color value is allowed, such as `red`, `#ff0000`, or `rgb(255, 0, 0)`.

With progressive highlighting, only unanswered fields **above** the most recently edited field will be highlighted.

## Changlog

Please see [CHANGLOG.md](CHANGELOG.md) for a full version history.

## How to cite this work

If you use this external module for a project that generates a research output, please cite this software in addition to [citing REDCap](https://projectredcap.org/resources/citations/). You can do so using the APA referencing style as below:

> Rezniczek, G. A. (2025). Unanswered (REDCap External Module) [Computer software]. https://doi.org/10.5281/zenodo.15530714

Or by adding this reference to your BibTeX database:

```bibtex
@software{Rezniczek_Unanswered_REDCap_EM_2025,
author = {Rezniczek, Günther A.},
doi = {10.5281/zenodo.15530714},
title = {{Unanswered (REDCap External Module)}},
url = {https://github.com/grezniczek/unanswered},
version = {1.0.0},
year = {2025}
}
```

These instructions are also available in [GitHub](https://github.com/grezniczek/unanswered) under 'Cite This Repository'.

## Support this work

If you find this software useful, you can [buy me a coffee or a beer](https://www.paypal.com/donate/?hosted_button_id=6VRC2JFRCBGRN). Your support is purely voluntary and helps me continue improving this project. Of course, you are not entitled to any special benefits—except my silent appreciation while enjoying the drink! 🍻☕  

You can use the link or the QR code below to make a donation via PayPal.

![PayPal QR Code](/images/qr-paypal.png)

_Please note that donations are purely voluntary and not tax-deductible._


---

**Disclaimer**  
This module description and documentation were developed with the assistance of AI (ChatGPT by OpenAI) to support clarity, consistency, and ease of use for REDCap project designers. Final content has been reviewed and adapted to reflect the specific functionality and standards of the *Unanswered* external module.
