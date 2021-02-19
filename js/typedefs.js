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
 * error: string
 * field: string
 * value: string
 * group: string
 * overwrite: boolean
 * }}
 */

/**
 * @typedef AutofillWidget
 * @type {{
 * error: string
 * field:string
 * autofill: boolean
 * autofillLabel: string
 * autofillStyle: string
 * autofillClass: string
 * clear: boolean
 * clearLabel: string
 * clearStyle: string
 * clearClass: string
 * groups: string[]
 * target: string
 * delimiter: string
 * before: string
 * after: string
 * }}
 */
