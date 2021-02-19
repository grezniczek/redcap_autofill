# Autofill

A REDCap external module providing action tags that allow filling of empty fields with default values.

**Feature and pull requests** (against _main_) are welcome!

## Requirements

- REDCAP 10.1.0 or newer.

## Installation

Automatic installation:

- Install this module from the REDCap External Module Repository and enable it.

Manual installation:

- Clone this repo into `<redcap-root>/modules/redcap_autofill_v<version-number>`.
- Go to _Control Center > Technical / Developer Tools > External Modules_ and enable 'External Module Management Tools'.

## Configuration and Effects

- Debug information can be output to the browser's console by enabling the JavaScript Debug option.
- Erros encountered during action tag parameter parsing can be indicated on data entry and survey pages.

Enable these **only** during testing!

All other effects of this module are controlled by action tags.

## Action Tags

### @AUTOFILL-VALUE

Defines the value to be filled. Use for each field that should be filled when the widget button is clicked. The value must correspond to the type of the field (e.g. a number for number-validated field, a date in the correct format for a date field, coded values for dropdowns, radios, checkboxes).
Some field types are not supported: Calculated Field, Signature, File Upload, Descriptive Text.

Usage:

```JS
// Simple syntax
@AUTOFILL-VALUE="5"
// Use special value syntax for checkbox fields
@AUTOFILL-VALUE="code,other"
// Extended syntax - needed to specify a group
@AUTOFILL-VALUE={
  'value': '5',
  'group': 'Fives',
  'overwrite': true|false
}
```

Extended parameters:

- `value`: The value* to be filled - **required**.
- `group`: A group name (widgets can control specific groups) - _optional_.
- `overwrite`: Indicates whether autofill should overwrite already set field values - _optional_ (default: `false`).

_*Special case:_ Checkboxes - here, autofill values need to be set in a special syntax: `coded_value[=0|1]`, i.e. specify the code and, optionally, whether it should be set to 0 or 1. Multiple values can be separated by commas. Note, `=0|1` can be omitted, in which case `1` (checked) is assumed.

### @AUTOFILL-FORM and @AUTOFILL-SURVEY

Defines the placement of the autofill widget (i.e. a control with buttons for the user to click in order to initiate the autofill process).

Usage:

```JS
// Simple syntax
@AUTOFILL-FORM/SURVEY
@AUTOFILL-FORM/SURVEY="group"
// Simple syntax, placement inside an element with id="id" (useful in combination with field embedding)
@AUTOFILL-FORM/SURVEY=":id"
@AUTOFILL-FORM/SURVEY="group1,group2:id" // control multiple groups
// Extended syntax
@AUTOFILL-FORM/SURVEY={
  'groups': [ 'group1', 'group2'],
  'target': 'id',
  'autofill': true|false,
  'autofillLabel': 'Autofill',
  'clear': true|false,
  'clearLabel': 'Reset',
  'clearStyle': 'background-color:red;',
  'delimiter': ' '
}
```

Extended parameters:

- `groups`: An array of group names (must be an array, even for a single group) - _optional_.
- `target`: When specified, the widget is rendered as last child of the element with the given id - _optional_ (default render position is at the end of the label portion of the field with the action tag).
- `autofill`: When set to `true`adds an autofill button that will execute the autofill - _optional_ (default: `true`).
- `autofillLabel`: The label of the button (supports HTML) - _optional_ (default: Autofill empty values).
- `autofillStyle`: CSS Style that is output in the button's style attribute - _optional_ (default: empty).
- `autofillClass`: Class names (space-delimited) to be added to the button - _optional_ (default: empty).
- `clear`: When set to `true` adds a clear button that will reset all affected fields - _optional_ (default: `false`).
- `clearLabel`: The label of the clear button (supports HTML) - _optional_ (default: Clear values).
- `clearStyle`: CSS Style that is output in the button's style attribute - _optional_ (default: empty).
- `clearClass`: Class names (space-delimited) to be added to the button - _optional_ (default: empty).
- `delimiter`: The delimiter between the autofill and clear buttons (supports HTML) - _optional_ (default: space).
- `before`: This is output before the autofill button (supports HTML) - _optional_ (default: empty).
- `after`: This is output after the autofill button (supports HTML) - _optional_ (default: empty).

### @AUTOFILL-FORM-ONSAVE and @AUTOFILL-SURVEY-ONSAVE

_Not implemented yet!_

Determines whether autofill should be performed after saving a form or survey. For multi-page surveys, this will only apply after the final submission, and not for each page. An additional event will be logged specifying which fields were set through autofill.

Usage:

```JS
// Place anywhere in the instrument
@AUTOFILL-FORM/SURVEY-ONSAVE
// Only apply to specific groups
@AUTOFILL-FORM/SURVEY-ONSAVE="group1,group2"
// Extended syntax
@AUTOFILL-FORM/SURVEY-ONSAVE={
  'groups': [ 'group' ]
}
```

## Changelog

Version | Description
------- | --------------------
v1.0.0  | Initial release.
