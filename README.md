# Unanswered (REDCap External Module)

[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.xxx.svg)](https://doi.org/10.5281/zenodo.xxx)

A REDCap external module that counts (and optionally highlights) the unanswered fields on a data entry or survey page.

## Requirements

- REDCap with EM Framework v16 support (REDCap 14.6.4+, 15.0.9+ LTS).

## Installation

- Clone this repo into `<redcap-root>/modules/unanswered_v<version-number>`, or
- Obtain this module from the Consortium [REDCap Repo](https://redcap.vumc.org/consortium/modules/index.php) via the Control Center.
- Go to _Control Center > Technical / Developer Tools > External Modules_ and enable _Unanswered_.

## Configuration

### System-level (Control Center)
There are no configuration options specific for this module.

### Project-level

- **Suppress dialogs for 'Save & Stay'**: When enabled, any dialogs that have been configured (via action tags) will not be triggered when the _Save & Stay_ button is pressed.

## Action Tags



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
