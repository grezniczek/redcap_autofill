<?php namespace DE\RUB\AutofillExternalModule;

use \REDCap as REDCap;
use \Survey as Survey;

class AutofillExternalModule extends \ExternalModules\AbstractExternalModule {


    private $atValue = "@AUTOFILL-VALUE";
    private $atForm = "@AUTOFILL-FORM";
    private $atSurvey = "@AUTOFILL-SURVEY";
    private $atFormOnSave = "@AUTOFILL-FORM-ONSAVE";
    private $atSurveyOnSave = "@AUTOFILL-SURVEY-ONSAVE";
    private $atTab = "@AUTOTAB";
    private $atNextFocus = "@NEXTFOCUS";

    private $actionTags;

    function __construct() {
        $this->actionTags = array (
            $this->atValue,
            $this->atForm,
            $this->atSurvey,
            $this->atFormOnSave,
            $this->atSurveyOnSave,
            $this->atTab,
            $this->atNextFocus,
        );
        parent::__construct();
    }

    #region Hooks

    function redcap_data_entry_form ($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
        $this->renderAutofill($project_id, $instrument, $record, $event_id, $repeat_instance, NULL);
    }

    function redcap_survey_page ($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id = NULL, $repeat_instance = 1) {
        $this->renderAutofill($project_id, $instrument, $record, $event_id, $repeat_instance, $survey_hash);
    }

    function redcap_save_record ($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
        if ($survey_hash === NULL) {
            // Only for data entry!
            $this->applyAutofillOnSave($project_id, $instrument, $record, $event_id, $repeat_instance, NULL);
        }
    }

    function redcap_survey_complete ($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id = NULL, $repeat_instance = 1) {
        $this->applyAutofillOnSave($project_id, $instrument, $record, $event_id, $repeat_instance, $survey_hash);
    }

    #endregion


    #region Setup and Rendering 

    /**
     * Returns an array containing active fields and parameters for each field
     * @return array
     */
    function getFieldParams() {

        if (!class_exists("ActionTagHelper")) include_once("classes/ActionTagHelper.php");

        $field_params = array();
        $action_tag_results = ActionTagHelper::getActionTags($this->actionTags);
        foreach ($action_tag_results as $tag => $tag_data) {
            foreach ($tag_data as $field => $param_array) {
                $params = array ();
                foreach ($param_array as $raw_param) {
                    $param = empty($raw_param) ? "" : json_decode($raw_param, true);
                    if ($param === NULL) {
                        $raw_param = str_replace("\r", "", $raw_param);
                        $raw_param = str_replace("\n", "\\n", $raw_param);
                        $param = json_decode($raw_param);
                    }
                    if ($param === NULL) {
                        $param = array(
                            "error" => $this->tt("invalid_parameters", $field),
                            "target" => "",
                        );
                    }
                    else {
                        // Convert non-json parameters to corresponding array
                        if (!is_array($param)) {
                            switch($tag) {
                                case $this->atValue:
                                    $param = array (
                                        "value" => $param,
                                    );
                                    break;
                                case $this->atForm:
                                case $this->atSurvey:
                                    list($groups, $id) = explode(":", $param, 2);
                                    $param = array (
                                        "groups" => explode(",", $groups),
                                        "target" => $id,
                                    );
                                    break;
                                case $this->atFormOnSave:
                                case $this->atSurveyOnSave:
                                    $param = array (
                                        "groups" => explode(",", $param),
                                    );
                                    break;
                                case $this->atTab:
                                case $this->atNextFocus:
                                    $param = array (
                                        "target" => $param,
                                    );
                                    break;
                            }
                        }
                        // Complete parameters with defaults
                        $param["field"] = $field;
                        $param["error"] = "";
                        switch($tag) {
                            case $this->atValue:
                                if (!isset($param["value"])) {
                                    $param["value"] = NULL;
                                }
                                if (!isset($param["group"])) {
                                    $param["group"] = "";
                                }
                                if (!isset($param["overwrite"])) {
                                    $param["overwrite"] = false;
                                }
                                if (!isset($param["clearCheckbox"])) {
                                    $param["clearCheckbox"] = false;
                                }
                                break;
                            case $this->atForm:
                            case $this->atSurvey:
                                if (!isset($param["groups"])) {
                                    $param["groups"] = array();
                                }
                                if (!isset($param["target"])) {
                                    $param["target"] = "";
                                }
                                if (!isset($param["autofill"])) {
                                    $param["autofill"] = true;
                                }
                                if (!isset($param["autofillLabel"])) {
                                    $param["autofillLabel"] = $this->tt("widget_autofilllabel");
                                }
                                if (!isset($param["autofillStyle"])) {
                                    $param["autofillStyle"] = "";
                                }
                                if (!isset($param["autofillClass"])) {
                                    $param["autofillClass"] = "";
                                }
                                if (!isset($param["clear"])) {
                                    $param["clear"] = false;
                                }
                                if (!isset($param["clearLabel"])) {
                                    $param["clearLabel"] = $this->tt("widget_clearlabel");
                                }
                                if (!isset($param["clearStyle"])) {
                                    $param["clearStyle"] = "";
                                }
                                if (!isset($param["clearClass"])) {
                                    $param["clearClass"] = "";
                                }
                                if (!isset($param["delimiter"])) {
                                    $param["delimiter"] = " ";
                                }
                                if (!isset($param["before"])) {
                                    $param["before"] = " ";
                                }
                                if (!isset($param["after"])) {
                                    $param["after"] = " ";
                                }
                                break;
                            case $this->atFormOnSave:
                            case $this->atSurveyOnSave:
                                if (!isset($param["groups"])) {
                                    $param["groups"] = array();
                                }
                                break;
                            case $this->atNextFocus:
                            case $this->atTab:
                                if (!isset($param["target"])) {
                                    $param["target"] = null;
                                }
                                break;
                        }
                    }
                    $params[] = $param;
                }
                $field_params[$tag][$field] = $params;
            }
        }
        return $field_params;
    }


    /**
     * This function passess along details about existing uploaded files so they can be previewed immediately after the
     * page is rendered or displayed when piped with the @IMAGEPIPE action-tag
     * @param $project_id
     * @param $instrument
     * @param $record
     * @param $event_id
     * @param $instance
     * @param @survey_hash
     * @throws \Exception
     */
    function applyAutofillOnSave($project_id, $instrument, $record, $event_id, $instance, $survey_hash = null) {
        $field_params = $this->getFieldParams();


    }

    /**
     * This function passess along details about existing uploaded files so they can be previewed immediately after the
     * page is rendered or displayed when piped with the @IMAGEPIPE action-tag
     * @param $project_id
     * @param $instrument
     * @param $record
     * @param $event_id
     * @param $instance
     * @param @survey_hash
     * @throws \Exception
     */
    function renderAutofill($project_id, $instrument, $record, $event_id, $instance, $survey_hash = null) {

        global $Proj;
        $is_survey = $survey_hash != NULL;

        $field_params = $this->getFieldParams();

        // Filter the tags and configured fields to only those on the current instrument / survey page
        $fields = array(); 
        $tags = array ( $this->atValue, $this->atNextFocus, $this->atTab );
        // Survey
        if ($is_survey) {
            $question_by_section = $Proj->surveys[$Proj->forms[$instrument]['survey_id']]['question_by_section'];
            $current_page = $question_by_section == "1" ? $_GET["__page__"]  : 1;
            $pageFields = Survey::getPageFields($instrument, $question_by_section)[0];
            $fields = $pageFields[$current_page];
            array_push($tags, $this->atSurvey);
        }
        // Data entry
        else {
            $fields = REDCap::getFieldNames($instrument);
            array_push($tags, $this->atForm);
        }
        // Filter for active fields only and count
        $active_widgets = 0;
        $active_autofills = 0;
        $active_other = 0;
        $active_fields = array();
        foreach ($tags as $tag) {
            foreach ($fields as $field_name) {
                if (isset($field_params[$tag][$field_name])) {
                    $active_fields[$tag][$field_name] = $field_params[$tag][$field_name];
                    if ($tag == $this->atForm || $tag == $this->atSurvey) {
                        $active_widgets++;
                    }
                    else if ($tag == $this->atValue) {
                        $active_autofills++;
                    }
                    else {
                        $active_other++;
                    }
                }
            }
        }
        // Anything to do? At least one widget and autofill must be present
        if (min($active_autofills, $active_widgets) + $active_other == 0) {
            return;
        }

        // Assemble information needed to pass to JavaScript for rendering
        if (!class_exists("\DE\RUB\AutofillExternalModule\Project")) include_once ("classes/Project.php");
        $project = new Project($this->framework, $project_id);
        $debug = $this->getProjectSetting("javascript-debug") == true;
        $show_errors = $this->getProjectSetting("show-errors") == true;

        // Augement autofill fields with some metadata (field type, ...)
        foreach ($active_fields as $tag => &$field_info) {
            foreach ($field_info as $field_name => &$data) {
                $fmd = $project->getFieldMetadata($field_name);
                $data["autofills"] = count($data);
                $data["type"] = $fmd["element_type"];
                $data["validation"] = $fmd["element_validation_type"];
            }
        }

        // @AUTOTAB only supports dropdown fields
        foreach ($active_fields[$this->atTab] as $field_name => &$data) {
            if ($data["type"] != "select") {
                for ($i = 0; $i < $data["autofills"]; $i++) {
                    $data[$i]["error"] = $this->tt("invalid_for_tab", $this->atTab, $field_name);
                }
            }
        }

        $js_params = array (
            "debug" => $debug,
            "errors" => $show_errors,
            "survey" => $is_survey,
            "fields" => $active_fields[$this->atValue] ?: array(),
            "widgets" => $active_fields[$is_survey ? $this->atSurvey : $this->atForm] ?: array(),
            "nextfocus" => $active_fields[$this->atNextFocus] ?: array(),
            "autotab" => $active_fields[$this->atTab] ?: array(),
        );

        $this->renderJavascript($js_params);
    }

    /**
     * Include JavaScript files and initialize the module
     */
    function renderJavascript($params) {
        ?>
            <script src="<?php print $this->getUrl('js/autofill.js'); ?>"></script>
            <script>
                DE_RUB_AutofillEM.params = <?php print json_encode($params) ?>;
                $(function() {
                    DE_RUB_AutofillEM.init();
                });
            </script>
        <?php
    }


    #endregion --------------------------------------------------------------------------------------------------------------

}