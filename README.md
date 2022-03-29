# Autofill

A REDCap external module providing action tags that allow filling of empty fields with default values, as well as for controlling navigation between fields.

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
  "value": "5", 
  "clear": "2", // only for checkbox fields!
  "group": "Fives",
  "overwrite": true|false
}
```

Extended parameters:

- `value`: The value* to be filled - **required**.
- `clear`: **Checkbox fields only**. The list of checkbox options* that should be unchecked - _optional_.
- `group`: A group name (widgets can control specific groups) - _optional_.
- `overwrite`: Indicates whether autofill should overwrite already set field values - _optional_ (default: `false`).

_*Special case:_ Checkboxes - here, autofill values need to be set in a special syntax: `code,other,...`, i.e. specify the code values for each checkbox that should be set to a checked state (separate multiple values with commas). If instead of checking, unchecking is the desired action, then the extended syntax must be used with the `clear` option instead of `value`, giving the codes of the checkboxes to be cleared. When `clear` is used, it will take precedence over `value` and `clear` will always overwrite saved values (i.e. as if `overwrite` was set to `true`). `value` and `clear` can be used at the same time. The `overwrite` option has no function in case of checkbox fields.

_Note:_ Values for date and datetime fields **must always** be written in the format specified for the field type! Furthermore, dates must always be written with exactly four digits for the year and exactly two digits for month and day. Year, month, and day must be separated by dashes.

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
  "groups": [ "group1", "group2" ],
  "target": "id",
  "autofill": true|false,
  "autofillLabel": "Autofill",
  "clear": true|false,
  "clearLabel": "Reset",
  "clearStyle": "background-color:red;",
  "delimiter": " "
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

Determines whether autofill should be performed **after** saving a form or survey. For multi-page surveys, this will only apply after the final submission, and not for each page. An additional event will be logged specifying which fields were set through autofill.

_Note:_ Autofill gets applied **before** alerts are triggered!

Usage:

```JS
// Place anywhere in the instrument
@AUTOFILL-FORM/SURVEY-ONSAVE
// Only apply to specific groups
@AUTOFILL-FORM/SURVEY-ONSAVE="group1,group2"
// Extended syntax
@AUTOFILL-FORM/SURVEY-ONSAVE={
  "groups": [ "group1", "group2" ]
}
```

### @AUTOFILL-FORM-ONLOAD and @AUTOFILL-SURVEY-ONLOAD

Determines whether autofill should be performed **before** rendering a form or survey. For multi-page surveys, this will be peformed at the initial rendering of the first survey page only. An additional event will be logged specifying which fields were set through autofill.

_Note:_ This type of autofill will only ever be applied to when the form status is not gray (i.e. when the instrument has been saved before) and the form or record is not locked. Furthermore, when unlocking a locked form, on-load autofill will not apply without re-loading the form. If you want to set a value for a freshly loaded form, use `@DEFAULT`.

Usage:

```JS
// Place anywhere in the instrument
@AUTOFILL-FORM/SURVEY-ONLOAD
// Only apply to specific groups
@AUTOFILL-FORM/SURVEY-ONLOAD="group1,group2"
// Extended syntax
@AUTOFILL-FORM/SURVEY-ONLOAD={
  "groups": [ "group1", "group2" ]
}
```

### @AUTOTAB

This action tag works for fields of type _dropdown_ only. When set for a dropdown field, the focus will move to the next field (or a specified target) as soon as the value of the field changes. This may be useful when large amounts of fields have to be filled where each field has only a limited number of options, each accessible directly with a separate keystroke.

Usage:

```JS
// Behaves like pressing the tab key
@AUTOTAB
// Moves focus to the named field
@AUTOTAB="field"
// Moves focus to any element on the page that is matched by 'selector'. The colon is not part of the selector.
@AUTOTAB=":selector"
```

### @NEXTFOCUS

This action tag can be used on any field. When this field loses focus, the specified field (or any element by way of a CSS selector) gets the focus. Use with care!

Usage:

```JS
// Moves focus to the named field
@NEXTFOCUS="field"
// Moves focus to any element on the page that is matched by 'selector'. The colon is not part of the selector.
@NEXTFOCUS=":selector"
```

## Changelog

Version | Description
------- | --------------------
v1.2.1  | Bugfix: Autotab failed in newer REDCap versions
v1.2.0  | Added option to clear checkboxes and implemented @AUTOFILL-FORM/SURVEY-ONSAVE and added @AUTOFILL-FORM/SURVEY-ONLOAD.<br> Fixed a bug that prevented Autofill to work without setting a group name.
v1.1.0  | Added @AUTOTAB and @NEXTFOCUS
v1.0.0  | Initial release.
