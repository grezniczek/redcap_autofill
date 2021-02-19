/**
 * @typedef AutofillEM
 * @type {{
 *  data?: AutofillData
 *  log: functiion()
 *  init: function()
 *  autofill: function(string[])
 *  clear: function(string[])
 * }}
 */

/**
 * @typedef AutofillData
 * @type {{
 * debug: boolean
 * atValue: string
 * atWidget: string
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
