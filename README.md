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

This is the main action tag. All others are used in conjunction with this one (i.e., on the same field) to fine-tune the module's behavior or activate additional features.

`@N-UNANSWERED` counts the number of unanswered fields on a data entry form or survey page and writes the result into the field to which it is attached. This requires that the field is of type '**Text Box**' with validation set to '**Integer**'.

Optionally, a set of fields (provided as a comma-separated list of field names) can be specified as a parameter to limit counting to those fields. For example, `@N-UNANSWERED='field1,field2,field3'` will only consider the fields _field1_, _field2_, and _field3_. Additionally, when a field name in the parameter list is prepended with `__` (double underscore), all fields in the section that contains that field will be included. This is useful for creating consistent counting behavior across (parts of) an instrument, both in data entry mode and in survey mode (when showing one page per section).

Instead of a set of fields, you may use `*` (asterisk) as the parameter (i.e., `@N-UNANSWERED='*'`), in which case all fields marked as **required** will be counted. When counting required fields, modifiers such as `@N-UNANSWERED-EXCLUDED` will be ignored.




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
