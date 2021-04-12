<?php namespace DE\RUB\AutofillExternalModule;

use \Project as REDCap_Project;
use \User as REDCap_User;
use \UserRights as REDCap_UserRights;
use \Exception;

use \ExternalModules\ExternalModules;
use \ExternalModules\Framework;

class Project
{
    #region -- Private Variables -----------------------------------------------------

    /** @var Framework The EM Framework instance */
    private $framework = null;

    /** @var int The project id */
    private $project_id;
    /** @var REDCap_Project Caches REDCap Core's Project object */
    private $proj = null;

    /**
     * Get the Project object.
     * @return REDCap_Project
     */
    private function getProject() {
        if ($this->proj === null) {
            // Get REDCap's Project instance.
            if (isset($GLOBALS["Proj"]) && $GLOBALS["Proj"]->project_id === $this->project_id) {
                $this->proj = $GLOBALS["Proj"];
            }
            else {
                $this->proj = new REDCap_Project($this->project_id);
            }
            $this->proj->getUniqueEventNames();
        }
        return $this->proj;
    }

    #endregion


    #region -- Constructor (via static get) ------------------------------------------

    /**
     * Instantiate a framework Project class.
     * @param Framework $framework The EM Framework instance
     * @param string|int|null $project_id (optional, current project assumed)
     * @return Project
     * @throws Exception Invalid project id, user, or when unable to determine
     */
    static function get($framework, $project_id = null) {
        return new Project($framework, $project_id);
    }

    /**
     * Constructor
     * @param Framework $framework The EM Framework instance
     * @param int $project_id
     */
    private function __construct($framework, $project_id) {
        if ($framework == null) {
            throw new \Exception("Must provide a Framework instance.");
        }
        $this->framework = $framework;
        $this->project_id = ExternalModules::requireInteger($project_id);
    }

    #endregion


    #region -- Project Properties ----------------------------------------------------
    
    /**
     * Gets the project id.
     * @return int
     */
    public function getProjectId() {
        return $this->project_id;
    }
    
    /**
     * Indicates whether the project is longitudinal.
     * @return boolean
     */
    public function isLongitudinal() {
        return $this->getProject()->longitudinal == true;
    }

    /**
     * Indicates whether surveys are enabled for this project.
     * @return boolean
     */
    public function surveysEnabled() {
        return $this->getProject()->project["surveys_enabled"] == 1;
    }

    /**
     * Gets the name of the record id field.
     * @return string
     */
    public function getRecordIdField() {
        return $this->getProject()->table_pk;
    }

    /**
     * Gets the number of arms in the project.
     * @return int
     */
    public function getNumArms() {
        return $this->getProject()->numArms;
    }

    public function getArmIdByEventId($event_id) {
        return $this->proj->eventInfo[$event_id]["arm_id"];
    }
    
    public function getArmNumByEventId($event_id) {
        return $this->proj->eventInfo[$event_id]["arm_num"];
    }
    

    /**
     * Indicates whether the project has multiple arms.
     * @return boolean
     */
    public function multipleArms() {
        return $this->getProject()->multiple_arms;
    }

    /**
     * Gets the number of events in the project.
     * @return int
     */
    public function getNumEvents() {
        return $this->getProject()->numEvents;
    }

    /**
     * Indicates whether the randomization module is enabled.
     * @return boolean
     */
    public function withRandomization() {
        return $this->getProject()->project["randomization"] == 1;
    }

    /**
     * Gets the status of the project.
     */
    public function getStatus() {
        $status = $this->getProject()->project["status"];
        return (int)$status;
    }

    /**
     * Indicates whether the project is set up to require a reason for data changes.
     * @return boolean
     */
    public function requiresChangeReason() {
        return $this->getProject()->project["require_change_reason"] == 1;
    }

    #endregion


    #region -- Records ---------------------------------------------------------------

    /**
     * Gets the record ids of all records in the project (optinally filtered).
     * @param string|null $filter Filter logic (optional)
     * @return array<int>
     */
    function getRecordIds($filter = null) {
        $pid = $this->getProjectId();
        $record_id_field = $this->getRecordIdField();
        $data = \REDCap::getData(
            $pid,              // project_id
            "array",           // return_format
            null,              // records
            $record_id_field,  // fields
            null,              // events
            null,              // groups
            false,             // combine_checkbox_values
            false,             // exportDataAccessGroups
            false,             // exportSurveyFields
            $filter,           // filterLogic
            false,             // exportAsLabels
            false              // exportCsvHeadersAsLabels
        );

        return array_keys($data);
    }

    #endregion


    #region -- Events ----------------------------------------------------------------

    /**
     * Checks whether an event exists in the project.
     * @param mixed $event The unique event name or the (numerical) event id
     * @return boolean
     */
    public function hasEvent($event) {
        if (empty($event)) {
            return false;
        }
        else if (is_int($event * 1)) {
            return array_key_exists($event * 1, $this->getProject()->eventInfo);
        }
        else {
            return in_array($event, array_values($this->getProject()->uniqueEventNames), true);
        }
    }

    /**
     * Gets the event's display name.
     * Returns null if the event does not exist
     * @param string|int $event The unique form name
     * @return string|null
     */
    public function getEventDisplayName($event) {
        $event_id = $this->getEventId($event);
        return $event_id === null ? null : $this->getProject()->eventInfo[$event_id]["name"];
    }

    /**
     * Gets the event id.
     * If the event does not exist, null will be returned.
     * @param mixed $event The unique event name or the (numerical) event id (can be omitted in non-longitudinal projects)
     * @return integer|null 
     */
    public function getEventId($event = null) {
        if ($event === null) {
            return $this->getProject()->firstEventId;
        }
        if ($this->hasEvent($event)) {
            if (is_int($event * 1)) {
                return $event * 1;
            }
            else {
                return $this->getProject()->getEventIdUsingUniqueEventName($event);
            }
        }
        return null;
    }

    /**
     * Checks whether the event is a repeating event.
     * If the event does not exist, null will be returned.
     * @param string $event The unique name or the numerical id of the event 
     * @return boolean|null 
     */
    public function isEventRepeating($event) {
        $event_id = $this->getEventId($event);
        return $event_id === null ? 
            null : $this->getProject()->isRepeatingEvent($event_id);
    }

    /**
     * Gets a list of event ids the form is part of.
     * @param string $form The unique form name.
     * @return array<int>
     */
    public function getEventsByForm($form) {
        $events = array();
        foreach ($this->getProject()->eventsForms as $event_id => $forms) {
            if (in_array($form, $forms)) {
                array_push($events, $event_id);
            }
        }
        return $events;
    }

    /**
     * Gets a list of event ids of all events in the project.
     * @return array<int>
     */
    public function getEvents() {
        return array_keys($this->getProject()->eventInfo);
    }

    /**
     * Gets a data structure of events and the forms in them, optionally limited 
     * by reapeat state (events or forms).
     * 
     * The returned array is structured like so:
     * [
     *   event_id => [
     *      form_name => "form"|"event"|"" - indicating whether the form, event, or neither is repeating
     *      ...
     *   ],
     *   ...
     * ]
     * 
     * @param boolean|null $eventRepeating Event must repeat (TRUE), must not repeat (FALSE), or either (NULL)
     * @param boolean|null $formRepeating Form must repeat (TRUE), must not repeat (FALSE), or either (NULL)
     */
    public function getEventsForms($eventRepeating = null, $formRepeating = null) {
        if ($formRepeating && $eventRepeating) {
            // Impossible
            return array();
        }
        $ef = array();
        foreach ($this->getEvents() as $event_id) {
            foreach ($this->getFormsByEvent($event_id) as $form) {
                if ($this->isFormRepeating($form, $event_id)) {
                    $type = "form";
                }
                else if ($this->isEventRepeating($event_id)) {
                    $type = "event";
                }
                else {
                    $type = "";
                }
                // Add based on filter
                $passEvent = $eventRepeating === null ||
                    ($eventRepeating === false && $type != "event") ||
                    ($eventRepeating === true && $type == "event");
                $passForm = $formRepeating === null ||
                    ($formRepeating === false && $type != "form") ||
                    ($formRepeating === true && $type == "form");
                if ($passEvent && $passForm) {
                    $ef[$event_id][$form] = $type;
                }
            }
        }
        return $ef;
    }

    #endregion


    #region -- Forms -----------------------------------------------------------------

    /**
     * Indicates whether the form is enabled as a survey. Returns NULL if the form
     * does not exist.
     * @param string $form The unique form name
     * @return boolean|null
     */
    public function isSurvey($form) {
        if ($this->hasForm($form)) {
            return $this->surveysEnabled() && 
                isset($this->getProject()->forms[$form]["survey_id"]);
        }
        return null;
    }

   /**
     * Gets the survey id for the form. If the form is not a survey, NULL is returned.
     * @param string $form The unique form name
     * @return int|null
     */
    public function getSurveyId($form) {
        if ($this->isSurvey($form)) {
            return (int)$this->getProject()->forms[$form]["survey_id"];
        }
        return null;
    }

    /** 
     * Gets the form with the record id field (i.e. the first form).
     * @return string
     */
    public function getRecordIdForm() {
        $record_id_field = $this->getRecordIdField();
        return $this->getFormByField($record_id_field);
    }

    /**
     * Gets a list of all forms in the project.
     * @return array<string>
     */
    public function getForms() {
        return array_keys($this->getProject()->forms);
    }

    /**
     * Gets a list of all forms in the event.
     * @param string|int|null $event The unique event name or the (numerical) event id (can be omitted in non-longitudinal projects)
     * @return array<string>
     */
    public function getFormsByEvent($event) {
        $event_id = $this->getEventId($event);
        return $event_id === null ? array() : array_values($this->getProject()->eventsForms[$event_id]);
    }

    /**
     * Gets a data structure of forms and the events they are on, optionally limited 
     * by reapeat state (forms or events).
     * 
     * The returned array is structured like so:
     * [
     *   form_name => [
     *      event_id => "form"|"event"|"" - indicating whether the form, event, or neither is repeating
     *      ...
     *   ],
     *   ...
     * ]
     * 
     * @param boolean|null $eventRepeating Event must repeat (TRUE), must not repeat (FALSE), or either (NULL)
     * @param boolean|null $formRepeating Form must repeat (TRUE), must not repeat (FALSE), or either (NULL)
     */
    public function getFormsEvents($formRepeating = null, $eventRepeating = null) {
        if ($formRepeating && $eventRepeating) {
            // Impossible
            return array();
        }
        $fe = array();
        foreach ($this->getForms() as $form) {
            foreach ($this->getEventsByForm($form) as $event_id) {
                if ($this->isFormRepeating($form, $event_id)) {
                    $type = "form";
                }
                else if ($this->isEventRepeating($event_id)) {
                    $type = "event";
                }
                else {
                    $type = "";
                }
                // Add based on filter
                $passEvent = $eventRepeating === null ||
                    ($eventRepeating === false && $type != "event") ||
                    ($eventRepeating === true && $type == "event");
                $passForm = $formRepeating === null ||
                    ($formRepeating === false && $type != "form") ||
                    ($formRepeating === true && $type == "form");
                if ($passEvent && $passForm) {
                    $fe[$form][$event_id] = $type;
                }
            }
        }
        return $fe;
    }

    /**
     * Checks whether a form exists in the project.
     * @param string $form The unique form name
     * @return boolean
     */
    public function hasForm($form) {
        return array_key_exists($form, $this->getProject()->forms);
    }

    /**
     * Gets the forms's display name.
     * @param string $form The unique form name
     * @return string
     */
    public function getFormDisplayName($form) {
        return $this->getProject()->forms[$form]["menu"];
    }

    /**
     * Gets the name of the form the field is on.
     * Returns null if the field does not exist.
     * @param string $field The field name
     * @return string
     */
    public function getFormByField($field) {
        $metadata = @$this->getProject()->metadata[$field];
        return empty($metadata) ? null : $metadata["form_name"];
    }

    /**
     * Checks whether a form is on a specific event.
     * If the form or event does not exist, null is returned.
     * @param string $form The unique form name
     * @param string $event The unique event name or the (numerical) event id (can be omitted on non-longitudinal projects)
     * @return boolean|null
     */
    public function isFormOnEvent($form, $event = null) {
        $event_id = $this->getEventId($event);
        if ($event_id !== null && $this->hasForm($form)) {
            return in_array($form, $this->getProject()->eventsForms[$event_id]);
        }
        return null;
    }

    /**
     * Checks whether a form is repeating.
     * If the form or event does not exist, null is returned.
     * @param string $form The unique form name
     * @param string $event The unique event name or (numerical) event id (can be omitted in non-longitudianl projects)
     * @return boolean|null
     */
    public function isFormRepeating($form, $event = null) {
        $event_id = $this->getEventId($event);
        if ($event_id !== null && $this->isFormOnEvent($form, $event_id)) {
            return $this->getProject()->isRepeatingForm($event_id, $form);
        }
        return null;
    }

    /**
     * Checks whether the give form includes a file upload or signature field.
     * @param string $form The unique form name
     * @return boolean
     */
    public function hasFormFileUploadOrSignatureFields($form) {
        $fields = $this->getFieldsByForm($form);
        foreach ($fields as $field) {
            if ($this->getProject()->metadata[$field]["element_type"] == "file") {
                return true;
            }
        }
        return false;
    }

    /**
     * Gets a list of file upload and signature fields on the form.
     * @param string $form The unique form name
     * @return array<string>
     */
    public function getFormFileUploadAndSignatureFields($form) {
        $fields = $this->getFieldsByForm($form);
        $fileOrSig = array();
        foreach ($fields as $field) {
            if ($this->getProject()->metadata[$field]["element_type"] == "file") {
                array_push($fileOrSig, $field);
            }
        }
        return $fileOrSig;
    }

    /**
     * Gets the form status field name(s) of the given form(s) (or all forms).
     * Will return NULL in case $froms is empty.
     * @param array<string>|string|null $forms The unique instrument name(s). When omitted (or NULL), all forms in the project are assumed.
     * @return array<string>|string|null
     */
    public function getFormStatusFieldNames($forms = null) {
        if ($forms === null) {
            $forms = $this->getForms();
        }
        if (empty($forms)) return null;
        $is_array = is_array($forms);
        if (!$is_array) $forms = array($forms);
        $add_complete = function($form_name) { 
            return $form_name . "_complete"; 
        };
        $status_fields = array_map($add_complete, $forms);
        return $is_array ? $status_fields : $status_fields[0];
    }

    /**
     * Gets form names from form status (_complete) fields.
     * Only valid forms for this project are returned. In case no form is found, NULL is returned.
     * @param array<string>|string $field_names The field names
     * @return array<string>|string|null
     */
    public function getFormsFromStatusFieldNames($field_names) {
        if (empty($field_names)) return null;
        $is_array = is_array($field_names);
        if (!$is_array) $field_names = array($field_names);
        $forms = array();
        foreach ($field_names as $field_name) {
            if (substr($field_name, -9) == "_complete") {
                $form = substr($field_name, 0, strlen($field_name) - 9);
                if ($this->hasForm($form)) {
                    array_push($forms, $form);
                }
            }
        }
        $forms = array_unique($forms);
        if (empty($forms)) return null;
        return $is_array ? $forms : $forms[0];
    }

    #endregion


    #region -- Fields ----------------------------------------------------------------

    /**
     * Checks whether a field exists in the project.
     * @param string $field The unique field name
     * @return boolean
     */
    public function hasField($field) {
        return array_key_exists($field, $this->getProject()->metadata);
    }


    /**
     * Gets a list of field names of a form. Returns an empty array if the form does not exist.
     * Note: The record id field is NEVER returned from this. Use the getRecordIdForm() method.
     * @param string $form The unique form name
     * @return array<string>
     */
    public function getFieldsByForm($form) {
        if ($this->hasForm($form)) {
            $record_id_field = $this->getRecordIdField();
            $filter = function($field) use ($record_id_field) {
                return $field != $record_id_field;
            };
            return array_filter(array_keys($this->getProject()->forms[$form]["fields"]), $filter);
        }
        return array();
    }

    /**
     * Checks whether the project contains any file upload fields.
     */
    public function hasFileUploadFields() {
        return $this->getProject()->hasFileUploadFields;
    }

    /**
     * Checks whether fields exist and are all on the same form.
     * If so, the name of the form is returned, otherwise false.
     * In case of an empty field list, false is returned.
     * @param array $fields List of field names.
     * @return string|boolean Form name or false.
     */
    public function areFieldsOnSameForm($fields) {
        $forms = array();
        foreach ($fields as $field) {
            $forms[$this->getFormByField($field)] = null;
        }
        return count($forms) == 1 ? array_key_first($forms) : false;
    }

    /**
     * Checks whether a field is on a repeating form.
     * If the field or event does not exists, null is returned.
     * @param string $field The field name
     * @param strign $event The unique event name or (numerical) event id (can be omitted in non-longitudinal projects)
     * @return boolean|null
     */
    public function isFieldOnRepeatingForm($field, $event = null) {
        if ($this->hasField($field)) {
            $form = $this->getFormByField($field);
            return $this->isFormRepeating($form, $event);
        }
        return null;
    }

    /**
     * Checks whether a field is on a specific event.
     * If the field or event does not exists, null is returned.
     * @param string $field The field name.
     * @param strign $event The unique event name or the (numerical) event id (can be omitted on non-longitudinal projects)
     * @return boolean|null
     */
    public function isFieldOnEvent($field, $event = null) {
        $event_id = $this->getEventId($event);
        if ($event_id !== null && $this->hasField($field)) {
            return $this->isFormOnEvent($this->getFormByField($field), $event_id);
        }
        return null;
    }

    /**
     * Checks whether a field is of type checkbox.
     * If the field does not exists, null is returned.
     * @param string $field The field name
     * @return boolean|null
     */
    public function isFieldOfTypeCheckbox($field) {
        if ($this->hasField($field)) {
            return $this->getProject()->metadata[$field]["element_type"] == "checkbox";
        }
        return null;
    }

    /**
     * Checks whether a field is of type radio.
     * If the field does not exists, null is returned.
     * @param string $field The field name
     * @return boolean|null
     */
    public function isFieldOfTypeRadio($field) {
        if ($this->hasField($field)) {
            return $this->getProject()->metadata[$field]["element_type"] == "radio";
        }
        return null;
    }

    /**
     * Checks whether a field is of type checkbox.
     * If the field does not exists, null is returned.
     * @param string $field The field name
     * @return boolean|null
     */
    public function isFieldOfTypeDropdown($field) {
        if ($this->hasField($field)) {
            return $this->getProject()->metadata[$field]["element_type"] == "select";
        }
        return null;
    }

    /**
     * Gets the enum of a checkbox, radio, or dropdown field.
     * @param string $field The field name
     */
    public function getFieldEnum($field) {
        if (in_array($this->getFieldType($field), ["dropdown", "radio", "checkbox"])) {
            return parseEnum($this->getProject()->metadata[$field]["element_enum"]);
        }
    }

    /**
     * Gets the type of a field. If the field does not exist, NULL is returned.
     * Field types are:
     *  - text = Text Box without validation
     *  - notes = Notes Box [textarea]
     *  - calc = Calculated Field
     *  - dropdown = Multiple Choice - Drop-down List (Single Answer) [select]
     *  - radio = Multiple Choice - Radio Buttons (Single Answer)
     *  - checkbox = Checkboxes (Multiple Answers)
     *  - yesno = Yes / No
     *  - truefalse = True / False
     *  - file = File Upload (for users to upload files) [file]
     *  - signature = Signature (draw signature with mouse or finger) [file]
     *  - slider = Slider / Visual Analog Scale
     *  - descriptive = Descriptive Text (with optional Image/Video/Audio/File Attachment)
     *  - date = Text Box with date validation
     *  - datetime = Text Box with datetime validation, with or without seconds
     *  - email = Text Box with email validation
     *  - int = Text Box with integer validation
     *  - float = Text Bow with number validation (or number with comma, any number of digits)
     *  - time_hm = Time (HH:MM)
     *  - time_ms = Time (MM:SS)
     *  - other = Textbox with custom/other validation - use getFieldValidation()
     * 
     * @param string $field The field name
     * @return string|null
     */
    public function getFieldType($field) {
        if (!$this->hasField($field)) return null;

        $type = $this->getProject()->metadata[$field]["element_type"];
        switch ($type) {
            case "text":
                switch ($this->getProject()->metadata[$field]["element_validation_type"]) {
                    case "date_ymd":
                    case "date_dmy":
                    case "date_mdy": 
                        return "date";
                    case "datetime_ymd":
                    case "datetime_dmy":
                    case "datetime_mdy": 
                        return "date";
                    case "datetime_seconds_ymd":
                    case "datetime_seconds_dmy":
                    case "datetime_seconds_mdy": 
                        return "date";
                    case "email":
                        return "email";
                    case "int":
                        return "int";
                    case "float":
                    case "number_1dp":
                    case "number_2dp":
                    case "number_3dp":
                    case "number_4dp":
                    case "number_comma_decimal":
                    case "number_1dp_comma_decimal":
                    case "number_2dp_comma_decimal":
                    case "number_3dp_comma_decimal":
                    case "number_4dp_comma_decimal":
                        return "float";
                    case "time":
                        return "time_hm";
                    case "time_mm_ss":
                        return "time_ms";
                }
                return "other";
            case "textarea":
                return "notes";
            case "select":
                return "dropdown";
            case "file": {
                    $validation = $this->getProject()->metadata[$field]["element_validation_type"] == "signature";
                    return $validation == "signature" ? "signature" : "file";
                }
            case "calc":
            case "radio":
            case "checkbox":
            case "yesno":
            case "truefalse":
            case "slider":
            case "descriptive":
                return $type;
        }
        return "other";
    }

    /**
     * Gets the field validation of a text box field.
     * If the field does not exist, null is returned.
     * @param string $field The field name
     * @return string|null
     */
    function getFieldValidation($field) {
        if ($this->hasField($field) && $this->getProject()->metadata[$field]["element_type"] == "text") {
            return $this->getProject()->metadata[$field]["element_validation_type"];
        }
        return null;
    }

    #endregion


    #region -- Field Metadata --------------------------------------------------------






    #endregion


    #region -- Users and Permissions -------------------------------------------------

    /**
     * Gets a list of the project users.
     * @return array<User>
     */
    function getUsers(){
        $results = $this->framework->query(
            "SELECT username FROM redcap_user_rights WHERE project_id = ? ORDER BY username",
            $this->project_id);
        $users = [];
        while($row = $results->fetch_assoc()){
            $users[] = new \ExternalModules\User($this->framework, $row['username']);
        }
        return $users;
    }


    // Certain actions will require permissions, e.g. to delete form instances or 
    // records, or to lock/unlock forms and/or records, etc.
    
    /**
     * Grants the permissions of the specified user.
     * @param string $user_id A user id
     */
    public function grantUserPermissions($user_id = null) {
        // First, revoke all
        $this->revokeAllPermissions();
        // Then, get the user
        $user_id = empty($user_id) ? USERID : $user_id;
        // And add their privileges (grant all for super users)
        if (REDCap_User::isSuperUser($user_id)) {
            $this->grantAllPermissions();
        }
        else {
            $user_rights = REDCap_UserRights::getPrivileges($this->project_id, $user_id);
            foreach (array_keys($this->permissions) as $permission) {
                $this->permissions[$permission] = $user_rights[$permission] != 0;
            }
        }
        $this->permissions_user = empty($user_id) ? null : $user_id;
    }

    /**
     * Checks if a permission is granted.
     * @param string $permission
     * @return boolean
     */
    public function hasPermission($permission) {
        return $this->permissions[$permission] === true;
    }

    /**
     * Ensures that the specified permission is granted.
     * @param string $permission
     * @throws Exception
     */
    public function requirePermission($permission) {
        if (!$this->hasPermission($permission)) {
            if (array_key_exists($permission, $this->permissions)) {
                throw new Exception("Permission '{$permission}' not granted.");
            }
            else {
                throw new Exception("Invalid permission '{$permission}'.");
            }
        }
    }

    /**
     * Revokes all permissions.
     */
    public function revokeAllPermissions() {
        foreach ($this->permissions as $_ => &$granted) {
            $granted = false;
        }
        $this->permissions_user = null;
    }

    /**
     * Revoke a permission.
     * @param string $permission
     */
    public function revokePermission($permission) {
        $this->permissions[$permission] = false;
        $this->permissions_user = null;
    }

    /**
     * Grants all permissions.
     */
    public function grantAllPermissions() {
        foreach ($this->permissions as $_ => &$granted) {
            $granted = true;
        }
        $this->permissions_user = null;
    }

    /**
     * Grants a permission.
     * @param string $permission
     */
    public function grantPermissions($permission) {
        $this->permissions[$permission] = true;
        $this->permissions_user = null;
    }

    /**
     * Gets the user id of the user used to set permissions.
     * Returns NULL in case the permissions have been set otherwise or altered.
     * @return string|null
     */
    public function getPermissionsUser() {
        return $this->permissions_user;
    }

    /**
     * @var array<string,boolean> Permissions
     */
    private $permissions = array(
        "design" => false,
        "record_delete" => false,
        "lock_record" => false,
        "lock_record_multiform" => false,
        "data_access_groups" => false,
    );

    /**
     *  @var string The user id last used to set permissions. This will be invalidated
     *  whenever permissions are set with any other method. */
    private $permissions_user = null;

    #endregion


    #region -- Public Helpers --------------------------------------------------------

    /**
     * Prettifies an SQL statement.
     * Can optionally contain comments, but these need to be on separate lines!
     * When multiple SQL queries are present, it's the callers responsibility to insert
     * semicolons!
     * @param string $sql The (multiline) SQL statement
     * @return string A less multiline SQL statement (one line if there are no comments)
     */
    public function oneLineSQL($sql) {
        $lines = explode("\n", $sql);
        $result = "";
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (substr($line, 0, 2) == "--") {
                // Comments get their own lines
                $result .= strlen($result) ? "\n" : "";
                $result .= $line . "\n";
            }
            else {
                $result .= " $line";
                if (substr($line, -1) == ";") {
                    // Newline after semicolons!
                    $result .= "\n";
                }
            }
        }
        $result = str_replace("\n ", "\n", $result);
        do {
            $result = str_replace("\n\n", "\n", $result, $count);
        } while ($count > 0);
        return trim($result);
    }

    #endregion


    // -- Below: Needs work, some will be obsolete --

    /**
     * Gets the field metadata.
     * If the field does not exist, null is returned.
     * 
     * @param string $field The field name.
     * @return array Field metadata (as in global $Proj).
     */
    function getFieldMetadata($field) {
        $pds = $this->getProjectDataStructure();
        if ($this->hasField($field)) {
            return $pds["fields"][$field]["metadata"];
        }
        return null;
    }



    /**
     * Gets the repeating forms and events in the current or specified project.
     * 
     * The returned array is structured like so:
     * [
     *   "forms" => [
     *      event_id => [
     *         "form name", "form name", ...
     *      ],
     *      ...
     *   ],
     *   "events" => [
     *      event_id => [
     *        "form name", "form name", ...
     *      ],
     *      ...
     *   ] 
     * ]
     * 
     * @param int|string|null $pid The project id (optional).
     * @return array An associative array listing the repeating forms and events.
     * @throws Exception From requireProjectId if no project id can be found.
     */
    function getRepeatingFormsEvents($pid = null) {

        $pid = $pid === null ? $this->getProjectId() : $this->framework->requireProjectId($pid);
        
        $result = $this->framework->query('
            select event_id, form_name 
            from redcap_events_repeat 
            where event_id in (
                select m.event_id 
                from redcap_events_arms a
                join redcap_events_metadata m
                on a.arm_id = m.arm_id and a.project_id = ?
            )', $pid);

        $forms = array(
            "forms" => array(),
            "events" => array()
        );
        while ($row = $result->fetch_assoc()) {
            $event_id = $row["event_id"];
            $form_name = $row["form_name"];
            if ($form_name === null) {
                // Entire repeating event. Add all forms in it.
                $forms["events"][$event_id] = $this->getEventForms($event_id);
            }
            else {
                $forms["forms"][$event_id][] = $form_name;
            }
        }
        return $forms;
    }

    /**
     * Gets the names of the forms in the current or specified event.
     * 
     * @param int|null $event_id The event id (optional)
     * @return array An array of form names.
     * @throws Exception From requireProjectId or ExternalModules::getEventId if event_id, project_id cannot be deduced or multiple event ids are in a project.
     */
    function getEventForms($event_id = null) {
        if($event_id === null){
            $event_id = $this->framework->getEventId();
        }
        $forms = array();
        $result = $this->framework->query('
            select form_name
            from redcap_events_forms
            where event_id = ?
        ', $event_id);
        while ($row = $result->fetch_assoc()) {
            $forms[] = $row["form_name"];
        }
        return $forms;
    }

    function getMissingDataCodes() {
        return parseEnum($this->proj->project["missing_data_codes"]);
    }


    /**
     * Gets the project structure (arms, events, forms, fields) of the current or specified project.
     * 
     * The returned array is structured like so:
     * [
     *   "pid" => "project_id", 
     *   "record_id" => "record_id_field_name",
     *   "longitudinal" => true|false,
     *   "forms" => [
     *      "form name" => [
     *          "name" => "form name",
     *          "repeating" => true|false,
     *          "repeating_event" => true|false,
     *          "arms" => [
     *              arm_id => [ 
     *                  "id" => arm_id 
     *              ], ...
     *          ],
     *          "events" => [
     *              event_id => [
     *                  "id" => event_id,
     *                  "name" => "event name",
     *                  "repeating" => true|false
     *              ], ...
     *          ],
     *          "fields" => [
     *              "field name", "field name", ...
     *          ]
     *      ], ...
     *   ],
     *   "events" => [
     *      event_id => [
     *          "id" => event_id,
     *          "name" => "event name",
     *          "repeating" => true|false,
     *          "arm" => arm_id,
     *          "forms" => [
     *              "form_name" => [
     *                  "name" => "form_name",
     *                  "repeating" => true|false
     *              ], ...
     *          ]
     *      ], ...
     *   ],
     *   "arms" => [
     *      arm_id => [
     *          "id" => arm_id
     *          "events" => [
     *              event_id => [
     *                  "id" => event_id,
     *                  "name" => "event name"
     *              ], ...
     *          ],
     *          "forms" => [
     *              "form name" => [
     *                  "name" => "form name"
     *              ], ...
     *          ]
     *      ], ...
     *   ],
     *   "fields" => [
     *      "field name" => [
     *          "name" => "field name",
     *          "form" => "form name",
     *          "repeating_form" => true|false,
     *          "repeating_event" => true|false,
     *          "events" => [
     *              event_id => [ 
     *                  (same as "events" => event_id -- see above)
     *              ], ...
     *          ],
     *          "metadata" => [
     *              (same as in $Proj)
     *          ]
     *      ], ...
     *   ]
     * ] 
     * @param int|string|null $pid The project id (optional).
     * @return array An array containing information about the project's data structure.
     */
    function getProjectDataStructure($pid = null) {

        $pid = $pid === null ? $this->getProjectId() : $this->framework->requireProjectId($pid);

        // Check cache.
        if (array_key_exists($pid, self::$ProjectDataStructureCache)) return self::$ProjectDataStructureCache[$pid];

        // Prepare return data structure.
        $ps = array(
            "pid" => $pid,
            "project" => $this->getProject(),
            "longitudinal" => $this->getProject()->longitudinal,
            "multiple_arms" => $this->getProject()->multiple_arms,
            // Events are ordered by day_offset in redcap_events_metadata
            "first_event_id" => array_key_first($this->getProject()->events[1]["events"]), 
            "record_id" => $this->framework->getRecordIdField($pid),
            "forms" => array(),
            "events" => array(),
            "arms" => array(),
            "fields" => array(),
        );

        // Gather data - arms, events, forms.
        // Some of this might be extractable from $proj, but this is just easier.
        $params = array($pid);
        $sql = "SELECT a.arm_id, m.event_id, f.form_name
                FROM redcap_events_arms a
                JOIN redcap_events_metadata m
                ON a.arm_id = m.arm_id AND a.project_id = ?
                JOIN redcap_events_forms f
                ON f.event_id = m.event_id";
        if (!$ps["longitudinal"]) {
            // Limit to the "first" event (i.e. the one in Project) - there may be more if the 
            // project has ever been longitudinal.
            $sql .= " AND m.event_id = ?";
            array_push($params, $ps["first_event_id"]);
        }
        $result = $this->framework->query($sql, $params);
        while ($row = $result->fetch_assoc()) {
            $event_id = $row["event_id"] * 1;
            $event_name = $this->getProject()->uniqueEventNames[$event_id];
            $arm_id = $row["arm_id"] * 1;
            $form_name = $row["form_name"];

            $ps["arms"][$arm_id]["id"] = $arm_id;
            $ps["arms"][$arm_id]["events"][$event_id] = array(
                "id" => $event_id,
                "name" => $event_name,
            );
            $ps["arms"][$arm_id]["forms"][$form_name] = array(
                "name" => $form_name
            );
            $ps["events"][$event_id]["id"] = $event_id;
            $ps["events"][$event_id]["name"] = $event_name;
            $ps["events"][$event_id]["repeating"] = false;
            $ps["events"][$event_id]["arm"] = $arm_id;
            $ps["events"][$event_id]["forms"][$form_name] = array(
                "name" => $form_name,
                "repeating" => false
            );
            $ps["forms"][$form_name]["name"] = $form_name;
            $ps["forms"][$form_name]["repeating"] = false;
            $ps["forms"][$form_name]["repeating_event"] = false;
            $ps["forms"][$form_name]["arms"][$arm_id] = array(
                "id" => $arm_id
            );
            $ps["forms"][$form_name]["events"][$event_id] = array(
                "id" => $event_id,
                "name" => $event_name,
                "repeating" => false
            );
        }
        // Gather data - fields. Again, this could be got from $proj, but this is more straightforward to process.
        // TODO: Do indeed get this from Project. This is more complicated than it seems.
        
        $result = $this->framework->query("
            SELECT field_name, form_name
            from redcap_metadata
            where project_id = ?
            order by field_order asc
        ", $pid);
        while ($row = $result->fetch_assoc()) {
            $ps["fields"][$row["field_name"]] = array(
                "name" => $row["field_name"],
                "form" => $row["form_name"],
                "repeating_form" => false,
                "repeating_event" => false,
            );
            $ps["forms"][$row["form_name"]]["fields"][] = $row["field_name"];
        }
        // Gather data - repeating forms, events.
        $repeating = $this->getRepeatingFormsEvents($pid);
        foreach ($repeating["forms"] as $eventId => $forms) {
            foreach ($forms as $form) {
                $ps["events"][$eventId]["forms"][$form]["repeating"]= true;
                $ps["forms"][$form]["repeating"] = true;
                // Augment fields.
                foreach ($ps["fields"] as $field => &$field_info) {
                    if ($field_info["form"] == $form) {
                        $field_info["repeating_form"] = true;
                    }
                }
            }
        }
        foreach ($repeating["events"] as $eventId => $forms) {
            $ps["events"][$eventId]["repeating"] = true;
            foreach ($forms as $form) {
                $ps["forms"][$form]["repeating_event"] = true;
                $ps["forms"][$form]["events"][$eventId]["repeating"] = true;
                // Augment fields.
                foreach ($ps["fields"] as $field => &$field_info) {
                    if ($field_info["form"] == $form) {
                        $field_info["repeating_event"] = true;
                    }
                }
            }
        }
        // Augment fields with events.
        foreach ($ps["forms"] as $formName => $formInfo) {
            foreach ($formInfo["fields"] as $field) {
                foreach ($formInfo["events"] as $eventId => $_) {
                    $ps["fields"][$field]["events"][$eventId] = $ps["events"][$eventId];
                }
            }
        }
        // Augment fields with field metadata.
        foreach ($ps["fields"] as $field => &$field_data) {
            $field_data["metadata"] = $this->getProject()->metadata[$field];
        }

        // Add to cache.
        self::$ProjectDataStructureCache[$pid] = $ps;

        return $ps;
    }

    private static $ProjectDataStructureCache = array();

}