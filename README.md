# Unanswered (REDCap External Module)

[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.xxx.svg)](https://doi.org/10.5281/zenodo.xxx)

A REDCap external module that counts (and optionally highlights) the unanswered fields on a data entry or survey page.

## Requirements

- REDCap with EM Framework v16 support (REDCap 14.6.4+, 15.0.9+ LTS).

## Installation

- Clone this repo into `<redcap-root>/modules/unanswered_v<version-number>`, or
- Obtain this module from the Consortium [REDCap Repo](https://redcap.vumc.org/consortium/modules/index.php) via the Control Center (_External Modules_ > _Manage_ > _View modules available in the REDCap Repo_), and then
- Go to _Control Center > External Modules_ > _Manage_ > _Enable a module_ and enable _Unanswered_.
- Enable the module in any project where its functionality is required (_External Modules_ > _Manage_ > _Enable a module_).

## Configuration

### System-level (Control Center)

There are no system-level configuration options specific for this module.

### Project-level

- **Output debug information to the browser console**: When enabled, detailed information about the module's processing on a data entry or survey page is output to the browser's console.  
  This option should be enabled only temporarily in order to help troubleshootung any issues.
- All further setup and configuration is done using **action tags** (see below).

## Action Tags

### `@N-UNANSWERED`

This is the primary action tag. All others are used in conjunction with it (i.e., on the same field) to fine-tune the module’s behavior or enable additional features.

`@N-UNANSWERED` counts the number of unanswered fields on a data entry form or survey page and writes the result into the field where it is applied. This field must be of type '**Text Box**' with validation set to '**Integer**'.

Optionally, a comma-separated list of field names can be provided as a parameter to restrict the count to specific fields. For example, `@N-UNANSWERED='field1,field2,field3'` limits the count to _field1_, _field2_, and _field3_. If a field name in this list is prefixed with `__` (double underscore), all fields within the same section as that field will be included. This allows for consistent behavior across instruments in both data entry and survey modes (e.g., when surveys are displayed one section per page).

Alternatively, the asterisk `*` may be used as the parameter (e.g., `@N-UNANSWERED='*'`) to count all fields marked as **required**. When using this option, modifiers such as `@N-UNANSWERED-EXCLUDED` will be ignored.

### `@N-UNANSWERED-EXCLUDED`

Attach this action to any field you do not want to be counted by the `@N-UNANSWERED` action tag. If there are multiple fields on a form or survey page and you want to exclude a field from a specific instance of `@N-UNANSWERED`, then supply the corresponding field name as a string parameter to the action tag (e.g., `@N-UNANSWERED-EXCLUDED='counter'`, where _counter_ is the field with the `@N-UNANSWERED` action tag). Alternatively, this action tag may be added to the same field as the `@N-UNANSWERED` action tag, in which case the list of fields to be excluded should be supplied as a comma-separated list in the string parameter of this action tag.

### `@N-UNANSWERED-ALWAYS-INCLUDED`

Attach this action to any field that is embedded in the label of a radio or checkbox field to included in the count even when the radio or checkbox it is embedded in is not selected/checked. Such fields are otherwise only counted by the `@N-UNANSWERED` action tag when the embedding radio/checkbox is selected/checked. To associated this action tag with a specific instance of `@N-UNANSWERED`, supply the corresponding field name as a string parameter to the action tag. Alternatively, this action tag may be added to the same field as the `@N-UNANSWERED` action tag, in which case the list of fields to be excluded should be supplied as a comma-separated list in the string parameter of this action tag.

### `@N-UNANSWERED-DIALOG`

Attach this action together with the `@N-UNANSWERED` action tag only, i.e., to the same field. When set, the label of the current field will be shown as a dialog when the user or survey respondent tries to save the page with unanswered questions (i.e., the count being > 0). The following optional parameters can be added: (1) The name of another field (e.g., a descriptive field) can be given, in which case that field's label will serve as the dialog's content. (2) A threshold value for the unanswered count that must be reached for the dialog to show. This must be an integer value. The default threshold is 1. (3) The flag `NSS` (Not for Save & Stay, all caps), that, when set, will not show the a data entry user uses the _Save & Stay_ button. The parameters must be separated by commas and can be in any order. Examples: `@N-UNANSWERED-DIALOG='3'`, `@N-UNANSWERED-DIALOG='desc_dialog,3,NSS'`.<br><br>The dialog shown can be customized to some extent. By default, the title will be 'NOTICE' and the buttons will be labeled 'Cancel' and what the original save/continue button was labeled. To customize the dialog title and the button labels, these elements can be added to the label, wrapped in double curly braces, i.e., `{{type:label}}`, where _type_ is `title`, `cancel`, or `continue`, and _label_ is the desired content. Care must be taken to not have any formatting obscure the start (`{{type:`) and end (`}}`) of these elements as else they may not be picked up correctly. It is best to not use the Rich Text Editor or to turn it off to inspect the underlying HTML. Some limited formatting of the contents itself is supported.  
Note: The field used as the dialog's source will be hidden and it must not be embedded elsewhere. When several dialogs are configured simulataneously, the first one representing the most unanswered fields will be shown.

### `@N-UNANSWERED-HIGHLIGHT-AFTER-DIALOG`

Attach this action together with the `@N-UNANSWERED` action tag only, i.e., to the same field. When set, unanswered fields will be highlighted, but only after a dialog has been opened in response to the user or survey respondent saving the current page (but not when going back on a survey). The default highlight color is red, but can be specified as an optional parameter to this actiontag (e.g., `@N-UNANSWERED-HIGHLIGHT-AFTER-DIALOG='orange'`). The color value can be any valid CSS color value, e.g., `red`, `#ff0000`, or `rgb(255, 0, 0)`.

### `@N-UNANSWERED-HIGHLIGHT-PROGRESSIVE`

Attach this action together with the `@N-UNANSWERED` action tag only, i.e., to the same field. When set, unanswered fields will be progressively highlighted in red or the color specified as an optional parameter to this actiontag (e.g., `@N-UNANSWERED-HIGHLIGHT-PROGRESSIVE='orange'`). The color value can be any valid CSS color value, e.g., `red`, `#ff0000`, or `rgb(255, 0, 0)`. Progressive highlighting means that any unanswered fields _above_ the last edited field will be hightlighted.




When enabled, any dialogs that have been configured (via action tags) will not be triggered when the _Save & Stay_ button is pressed.




## Changelog

Version | Description
------- | ----------------
1.0.0   | Initial release.

## How to cite this work

If you use this external module for a project that generates a research output, please cite this software in addition to [citing REDCap](https://projectredcap.org/resources/citations/). You can do so using the APA referencing style as below:

> Rezniczek, G. A. (2025). Unanswered (REDCap External Module) [Computer software]. https://doi.org/10.5281/zenodo.xxx

Or by adding this reference to your BibTeX database:

```bibtex
@software{Rezniczek_Unanswerd_REDCap_EM_2025,
  author = {Rezniczek, Günther A.},
  title = {{Unanswered (REDCap External Module)}},
  version = {1.0.0},
  year = {2025}
  month = {5},
  doi = {10.5281/zenodo.xxx},
  url = {https://github.com/grezniczek/unanswered},
}
```

These instructions are also available in [GitHub](https://github.com/grezniczek/unanswered) under 'Cite This Repository'.

## Support this work

If you find this software useful, you can [buy me a coffee or a beer](https://www.paypal.com/donate/?hosted_button_id=6VRC2JFRCBGRN). Your support is purely voluntary and helps me continue improving this project. Of course, you are not entitled to any special benefits—except my silent appreciation while enjoying the drink! 🍻☕  

You can use the link or the QR code below to make a donation via PayPal.

![PayPal QR Code](/images/qr-paypal.png)

_Please note that donations are purely voluntary and not tax-deductible._
