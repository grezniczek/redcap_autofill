<?php namespace DE\RUB\AutofillExternalModule;

use \REDCap as REDCap;

/**
 * Class ActionTagHelper
 *
 * A class for helping to parse custom action tags
 * This is based on Andy Martin's ActionTagHelper but supports multiple action tags of the same
 * type per field.
 */
class ActionTagHelper
{
    // Cache the search results to speed complex queries or queries with many hooks
    static $cache = array();

    /**
     * This is a utility function to return action tags, fields, and parameters for specific action-tag scripts
     *
     * @param null $tags        a single or array of action_tags that you are looking for
     * @param null $fields      a single or array of fields that you want to look in
     * @param null $instruments a single or array of instruments to look in
     * @throws \Exception
     * @return array            [ "tag1": [
     *                              "field_name": [
     *                                  any parameters next to tag (supports string, list, or json); if multiple identical
     *                                  action tags are present in a field, they are all added to an array.
     */
    static function getActionTags($tags = NULL, $fields = NULL, $instruments = NULL) {

        // Check to see if this search has been cached
        $arg_key = md5(json_encode(func_get_args()));
        if (isset(self::$cache[$arg_key])) {
            return self::$cache[$arg_key];
        }

        // Convert tag_filter into uppercase array
        if (!empty($tags)) {
            if (!is_array($tags)) $tags = array($tags);
            $tags = array_map('strtoupper', $tags);
        }

        // Get the metadata with applied filters
        $q = REDCap::getDataDictionary('json', false, $fields, $instruments);
        $metadata = json_decode($q, true);

        // Build action_tag array
        $action_tags = array();
        foreach ($metadata as $field) {
            $field_name = $field['field_name'];
            $field_annotation = $field['field_annotation'];
            $parsed_tags = self::parseActionTags($field_annotation);
            foreach ($parsed_tags as $tag) {
                // All action-tags should be parsed as uppercase
                $action_tag = strtoupper($tag['actiontag']);

                // If we are filtering, skip non-specified tags
                if ($tags AND !in_array($action_tag, $tags)) continue;

                // Initialize the action_tag node
                if (!isset($action_tags[$action_tag])) $action_tags[$action_tag] = array();

                $action_tags[$action_tag][$field_name][] = isset($tag['params']) ? $tag['params'] : "";
            }
        }
        // Cache this search
        self::$cache[$arg_key] = $action_tags;

        return $action_tags;
    }


    /**
     * Parses a string for arrays of actiontags (optionally filtering by the supplied tag)
     * Examples of valid action tags are:
     * @TAG1
     * @TAG2=1,2,3
     * @TAG3={
     *   "key":"value",
     *   "key2","value2"
     * }
     *
     * The results are an array where each match is an actiontag with a key of
     * [0] => [
     *   "actiontag" => "@TAG1"
     * ],
     * [1] => [
     *   "actiontag" => "@TAG2",
     *   "params"    => "1,2,3"
     * ],
     * [2] => [
     *   "actiontag" => "@TAG3",
     *   "params"    => "{"key":"value","key2","value2"}
     *
     * https://regex101.com/r/fL2rM8/5
     *
     * @param $string           The string to be parsed for actiontags (in the format of <code>@FOO=BAR or @FOO={"param":"bar"}</code>
     * @param null $tag_only    If you wish to select a single tag
     * @return array|bool       returns the match array with the key equal to the tag and an array containing keys of 'params, params_json and params_text'
     */
    static function parseActionTags($string, $tag_only = null) {
        $re = "/(?(DEFINE)
          (?<number>    -?  (?= [1-9]|0(?!\d) ) \d+ (\.\d+)? ([eE] [+-]? \d+)? )
          (?<boolean>   true | false | null )
          (?<string>    \" ([^\"\\\\]* | \\\\ [\"\\\\bfnrt\/] | \\\\ u [0-9a-f]{4} )* \" )
          (?<array>     \[  (?:  (?&json)  (?: , (?&json)  )*  )?  \s* \] )
          (?<pair>      \s* (?&string) \s* : (?&json)  )
          (?<object>    \{  (?:  (?&pair)  (?: , (?&pair)  )*  )?  \s* \} )
          (?<fieldname> [a-zA-Z0-9\_\-]+ )
          (?<json>      \s* (?: (?&number) | (?&boolean) | (?&string) | (?&array) | (?&object) )  )
          (?<fieldlist> (?: (?&fieldname) (?: , (?&fieldname) )+ )+ )  
        )
        (?'actiontag'
          \@(?&fieldname)
        )
        (?:\=
          (?'params'
            (?:
              (?'match_list'(?&fieldlist))
              |
              (?'match_json'(?&json))
              |
              (?'match_string'(?:[[:alnum:]\_\-]+))
            )
          )
        )?/ixm";

        preg_match_all($re, $string, $matches);

        // Return false if none are found
        if (count($matches['actiontag']) == 0) return false;

        $results = array();

        foreach ($matches['actiontag'] as $i => $tag) {
            $tag = strtoupper($tag);
            if ($tag_only && ($tag != strtoupper($tag_only))) continue;
            $results[] = array(
                'actiontag' => $tag,
                'params' => $matches['params'][$i]
            );
        }
        return $results;
    }
}
