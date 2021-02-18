<?php namespace DE\RUB\AutofillExternalModule;

use \REDCap as REDCap;
use \Files as Files;
use \Piping as Piping;
use \Event as Event;
use \Survey as Survey;

class AutofillExternalModule extends \ExternalModules\AbstractExternalModule {


    private $atValue = "@AUTOFILL-VALUE";
    private $atForm = "@AUTOFILL-FORM";
    private $atSurvey = "@AUTOFILL-SURVEY";
    private $atFormOnSave = "@AUTOFILL-FORM-ONSAVE";
    private $atSurveyOnSave = "@AUTOFILL-SURVEY-ONSAVE";

    private $actionTags;
    private $renderTags;

    function __construct() {
        $this->actionTags = array (
            $this->atValue,
            $this->atForm,
            $this->atSurvey,
            $this->atFormOnSave,
            $this->atSurveyOnSave,
        );
        $this->renderTags = array (
            $this->atValue,
            $this->atForm,
            $this->atSurvey,
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
                    $param = json_decode($raw_param, true);
                    // Convert non-json parameters to corresponding array
                    if (!is_array($param)) {
                        switch($tag) {
                            case $this->atValue:
                                $param = array (
                                    "value" => $raw_param,
                                );
                                break;
                            case $this->atForm:
                            case $this->atSurvey:
                                list($groups, $id) = explode(":", $raw_param, 2);
                                $param = array (
                                    "groups" => explode(",", $groups),
                                    "target" => $id,
                                );
                                break;
                            case $this->atFormOnSave:
                            case $this->atSurveyOnSave:
                                $param = array (
                                    "groups" => explode(",", $raw_param),
                                );
                                break;
                        }
                    }
                    // Complete parameters with defaults
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
                            break;
                        case $this->atForm:
                        case $this->atSurvey:
                            if (!isset($param["groups"])) {
                                $param["groups"] = array();
                            }
                            if (!isset($param["target"])) {
                                $param["target"] = "";
                            }
                            if (!isset($param["label"])) {
                                $param["label"] = $this->tt("widget_label");
                            }
                            if (!isset($param["clear"])) {
                                $param["clear"] = false;
                            }
                            if (!isset($param["clearLabel"])) {
                                $param["clearLabel"] = $this->tt("widget_clearlabel");
                            }
                            break;
                        case $this->atFormOnSave:
                        case $this->atSurveyOnSave:
                            if (!isset($param["groups"])) {
                                $param["groups"] = array();
                            }
                            break;
                    }
                    $params[] = $param;
                }
                $field_params[$tag][$field] = $params;
            }
        }
        return $field_params;
    }

    /**
     * Returns an array containing piped fields (@IMAGEPIPE action-tag). This needs context in order to find the correct 
     * source field.
     * @param $project_id
     * @param $instrument
     * @param $record
     * @param $event_id
     * @param $instance
     * @return array
     */
    function getPipedFields($project_id = null, $instrument = null, $record = null, $event_id = null, $instance = 1) {
        // Get from action tags (and only take if not specified in external module settings)
        if (!class_exists("ActionTagHelper")) include_once("classes/ActionTagHelper.php");

        if (!class_exists("\DE\RUB\AutofillExternalModule\Project")) include_once ("classes/Project.php");
        $project = new Project($this->framework, $project_id ?: $this->framework->getProjectId());

        $field_params = array();

        $action_tag_results = ActionTagHelper::getActionTags($this->imagePipeTag);
        if (isset($action_tag_results[$this->imagePipeTag])) {
            foreach ($action_tag_results[$this->imagePipeTag] as $field => $param_array) {
                $params = $param_array["params"];
                // Need to create correct context for the piping of special tags (instance, event smart variables)
                $raw_params = json_decode($params, true);
                if (is_string($raw_params)) {
                    $raw_params = json_decode("{\"field\":\"$raw_params\",\"event\":\"[event-name]\",\"instance\":\"[current-instance]\"}", true);
                }
                if (!isset($raw_params["event"])) $raw_params["event"] = "[event-name]";
                if (!isset($raw_params["instance"])) $raw_params["instance"] = "[current-instance]";
                $field_instrument = $project->getFormByField($raw_params["field"]);
                $raw_params["event"] = Piping::pipeSpecialTags($raw_params["event"] ?: "[event-name]", $project_id, $record, $event_id, $instance, null, false, null, $field_instrument, false, false);
                $ctx_event_id = is_numeric($raw_params["event"]) ? $raw_params["event"] * 1 : Event::getEventIdByName($project_id, $raw_params["event"]);
                $ctx_instance = $ctx_event_id == $event_id ? $instance : 1;
                $raw_params["instance"] = Piping::pipeSpecialTags($raw_params["instance"] ?: "[current-instance]", $project_id, $record, $ctx_event_id, $ctx_instance, null, false, null, $field_instrument, false, false);
                $field_params[$field] = $raw_params;
            }
        }
        return $field_params;
    }

    /**
     * Include JavaScript files and output basic JavaScript setup
     */
    function renderJavascriptSetup($project_id = null) {
        $field_params = $this->getFieldParams();


        // Make a list of all fields that may be downloaded
        $allowed = array_values(array_map(function($e) { 
            return $e->field; 
        }, $this->getPipedFields()));
        $allowed = array_unique(array_merge($allowed, array_keys($field_params)));
        $debug = $this->getProjectSetting("javascript-debug") == true;
        // Security token - needed to perform safe piping
        if ($project_id) {
            if (!class_exists("\DE\RUB\CryptoHelper")) include_once("classes/CryptoHelper.php");
            $crypto = \DE\RUB\CryptoHelper\Crypto::init();
            $payload = $crypto->encrypt(array( 
                "pid" => $project_id * 1,
                "allowed" => $allowed
            ));
        }
        else {
            $payload = "nop";
        }
        $payload = urlencode($payload);
        ?>
            <script src="<?php print $this->getUrl('js/pdfobject.min.js'); ?>"></script>
            <script src="<?php print $this->getUrl('js/imageViewer.js'); ?>"></script>
            <script>
                IVEM.valid_image_suffixes = <?php print json_encode($this->valid_image_suffixes) ?>;
                IVEM.valid_pdf_suffixes = <?php print json_encode($this->valid_pdf_suffixes) ?>;
                IVEM.field_params = <?php print json_encode($field_params) ?>;
                IVEM.payload = <?php print json_encode($payload) ?>;
                IVEM.debug = <?php print json_encode($debug) ?>;
                IVEM.log("Initialized IVEM", IVEM);
            </script>
        <?php
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

        // Filter the configured fields to only those on the current instrument / survey page
        $fields = array(); 
        // Survey
        if ($is_survey) {
            $question_by_section = $Proj->surveys[$Proj->forms[$instrument]['survey_id']]['question_by_section'];
            $current_page = $question_by_section == "1" ? $_GET["__page__"]  : 1;
            list ($pageFields, $totalPages) = Survey::getPageFields($instrument, $question_by_section);
            $fields = $pageFields[$current_page];
        }
        // Data entry
        else {
            $fields = REDCap::getFieldNames($instrument);
        }
        // Filter for active fields only and count
        $active_widgets = 0;
        $active_autofills = 0;
        $active_field_params = array();
        foreach ($this->renderTags as $tag) {
            foreach ($fields as $field_name) {
                if (isset($field_params[$tag][$field_name])) {
                    $active_fields[$tag][$field_name] = $field_params[$tag][$field_name];
                    if ($tag == $this->atForm || $tag == $this->atSurvey) {
                        $active_widgets++;
                    }
                    else if ($tag == $this->atValue) {
                        $active_autofills++;
                    }
                }
            }
        }
        // Anything to do? At least one widget and autofill must be present
        if (min($active_autofills, $active_widgets) == 0) {
            return;
        }

        // Assemble information needed to pass to JavaScript for rendering
        if (!class_exists("\DE\RUB\AutofillExternalModule\Project")) include_once ("classes/Project.php");
        $project = new Project($this->framework, $project_id);
        $debug = $this->getProjectSetting("javascript-debug") == true;

        return;

        // We need to know the filetype to validate when the file has been previously uploaded...
        // Get type of field
        global $Proj;
        $query_fields = array();
        foreach (array_keys($fields) as $field) {
            $query_fields[$field] = array(
                "field" => $field, 
                "event_id" => $event_id * 1, 
                "instance" => $instance * 1
            );
        }
        foreach ($piped_fields as $field => $source) {
            $source_event = $source["event"] === null ? $event_id : $source["event"];
            $query_fields[$field] = array (
                "field" => $source["field"], 
                "event_id" => is_numeric($source_event) ? $source_event * 1 : Event::getEventIdByName($project_id, $source_event),
                "instance" => $source["instance"] * 1 ?: 1
            );
        }
        // Get field data - how to get this depends on the data structure of the project (repeating forms/events)
        $field_data = array();
        foreach ($query_fields as $field => $source) {
            $sourceField = $source["field"];
            $sourceForm = $project->getFormByField($sourceField);
            $sourceEventId = $source["event_id"];
            $sourceInstance = $source["instance"];
            $data = REDCap::getData('array',$record, $sourceField);
            if ($project->isFieldOnRepeatingForm($sourceField, $sourceEventId)) {
                $result = $data[$record]["repeat_instances"][$sourceEventId][$sourceForm][$sourceInstance];
            }
            else if ($project->isEventRepeating($sourceEventId)) {
                $result = $data[$record]["repeat_instances"][$sourceEventId][null][$sourceInstance];
            }
            else {
                $result = $data[$record][$sourceEventId];
            }
            //Util::log($result);
            $field_meta = $Proj->metadata[$sourceField];
            $field_type = $field_meta['element_type'];
            if ($field_type == 'descriptive' && !empty($field_meta['edoc_id'])) {
                $doc_id = $field_meta['edoc_id'];
            } 
            elseif ($field_type == 'file') {
                $doc_id = $result[$sourceField];
            } 
            else {
                // invalid field type!
            }
            $field_data[$field] = array (
                'container_id' => "ivem-$field-$event_id-$instance",
                'params'       => $source_fields[$source["field"]],
                'page'         => $instrument,
                'field_name'   => $sourceField,
                'record'       => $record,
                'event_id'     => $sourceEventId,
                'instance'     => $sourceInstance,
                'survey_hash'  => $survey_hash,
                'pipe_source'  => "$sourceField-$sourceEventId-$sourceInstance",
            );
            if ($doc_id > 0) {
                list($mime_type, $doc_name) = Files::getEdocContentsAttributes($doc_id);
                $field_data[$field]["suffix"] = strtolower(pathinfo($doc_name, PATHINFO_EXTENSION));
                $field_data[$field]["mime_type"] = $mime_type;
                $field_data[$field]["doc_name"] = $doc_name;
                $field_data[$field]["doc_id"] = $doc_id;
                $field_data[$field]["hash"] = Files::docIdHash($doc_id);
            }
        }

        $preview_fields = array();
        foreach ($fields as $field => $_) {
            $preview_fields[$field] = $field_data[$field];
            $preview_fields[$field]["piped"] = false;
        }
        $pipe_sources = array();
        foreach ($piped_fields as $into => $from) {
            $pipe_sources[$from["field"]] = true;
            $preview_fields[$into] = $field_data[$into];
            $preview_fields[$into]["piped"] = true;
            $preview_fields[$into]["params"] = isset($active_field_params[$into]) ? $active_field_params[$into] : @$active_field_params[$from];
        }


        $this->renderJavascriptSetup($project_id);
        ?>
            <script>
                // Load the fields and parameters and start it up
                IVEM.preview_fields = <?php print json_encode($preview_fields) ?>;
                IVEM.pipe_sources = <?php print json_encode($pipe_sources) ?>;
                IVEM.init();
            </script>
        <?php
    }

    #endregion --------------------------------------------------------------------------------------------------------------

}