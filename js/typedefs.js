/**
 * @typedef AutofillEM
 * @type {{
 *  params?: AutofillParams
 *  log: functiion()
 *  init: function()
 *  autofill: function(string[])
 *  clear: function(string[])
 * }}
 */

/**
 * @typedef AutofillParams
 * @type {{
 * debug: boolean
 * survey: boolean
 * fields: object
 * widgets: object
 * }}
 */

/**
 * @typedef FieldInfo
 * @type {{
 * autofills: integer
 * type: string
 * validation: string
 * }}
 */

/**
 * @typedef AutofillValue
 * @type {{
 * value: string
 * group: string
 * overwrite: boolean
 * }}
 */

/**
 * @typedef AutofillWidget
 * @type {{
 * autofill: boolean
 * autofillLabel: string
 * clear: boolean
 * clearLabel: string
 * groups: string[]
 * target: string
 * }}
 */
