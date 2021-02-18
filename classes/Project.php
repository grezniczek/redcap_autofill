<?php namespace DE\RUB\AutofillExternalModule;

class Project
{
    /** @var \ExternalModules\Framework The EM framework */
    private $framework;
    /** @var int The project id */
    private $project_id;

    public static function load($framework, $project_id) {
        return new Project($framework, $project_id);
    }

    function __construct($framework, $project_id){
        $this->framework = $framework;
        $this->project_id = $framework->requireInteger($project_id * 1);
    }

    /**
     * Gets the project id.
     * @return int The project id.
     */
    function getProjectId() {
        return $this->project_id;
    }

    /**
     * Gets an instance of the Record class.
     * @return Record 
     */
    function getRecord($record_id) {
        if (!class_exists("\DE\RUB\AutofillExternalModule\Record")) include_once ("Record.php");
        return new Record($this->framework, $this, $record_id);
    }


    /**
     * Gets the name of the record id field.
     * 
     * @return string
     */
    function recordIdField() {
        $pds = $this->getProjectDataStructure();
        return $pds["record_id"];
    }

    /**
     * Checks whether an event exists in the project.
     * @param string $event The event name or numerical event id.
     * @return boolean
     */
    function hasEvent($event) {
        $event = "{$event}";
        $pds = $this->getProjectDataStructure();
        if (is_numeric($event) && is_int($event * 1)) {
            return isset($pds["events"][$event]);
        }
        else {
            foreach ($pds["events"] as $_ => $data) {
                if ($data["name"] == $event) return true;
            }
        }
        return false;
    }

    /**
     * Gets the event id.
     * If the event does not exist, null will be returned.
     * 
     * @param string $event The event name or (numerical) event id.
     * @return string|null 
     */
    function getEventId($event) {
        $event = "{$event}";
        $pds = $this->getProjectDataStructure();
        if (is_numeric($event) && is_int($event * 1) && isset($pds["events"][$event])) {
            return $event;
        }
        else {
            foreach ($pds["events"] as $_ => $data) {
                if ($data["name"] == $event) return $data["id"];
            }
        }
        return null;
    }

    /**
     * Checks whether the event is a repeating event.
     * If the event does not exist, null will be returned.
     * 
     * @param string $event The event name or (numerical) event id.
     * @return boolean|null 
     */
    function isEventRepeating($event) {
        $event_id = $this->getEventId($event);
        if ($event_id !== null) {
            $pds = $this->getProjectDataStructure();
            return $pds["events"][$event_id]["repeating"];
        }
        return null;
    }

    /**
     * Checks whether a form exists in the project.
     * @param string $form The form name.
     * @return boolean
     */
    function hasForm($form) {
        $pds = $this->getProjectDataStructure();
        return isset($pds["forms"][$form]);
    }

    /**
     * Checks whether a field exists in the project.
     * @param string $field The field name.
     * @return boolean
     */
    function hasField($field) {
        $pds = $this->getProjectDataStructure();
        return isset($pds["fields"][$field]);
    }

    /**
     * Gets the name of the form the field is on.
     * @param string $field The field name.
     * @return string The name of the form (or null).
     */
    function getFormByField($field) {
        $pds = $this->getProjectDataStructure();
        return @$pds["fields"][$field]["form"];
    }

    /**
     * Checks whether fields exist and are all fields are on the same form.
     * In case of an empty field list, false is returned.
     * 
     * @param array $fields List of field names.
     * @return string|boolean Form name or false.
     */
    function areFieldsOnSameForm($fields) {
        $ok = count($fields) > 0;
        $form = $this->getFormByField($fields[0]);
        $ok = $ok && !empty($form);
        foreach ($fields as $field) {
            $ok = $ok && $this->getFormByField($field) == $form;
        }
        return $ok ? $form : false;
    }

    /**
     * Checks whether a field is on a repeating form.
     * If the field or event does not exists, null is returned.
     * 
     * @param string $field The field name.
     * @param strign $event The event name or (numerical) event id.
     * @return boolean|null
     */
    function isFieldOnRepeatingForm($field, $event) {
        if ($this->hasField($field)) {
            $form = $this->getFormByField($field);
            return $this->isFormRepeating($form, $event);
        }
        return null;
    }

    /**
     * Checks whether a field is on a specific event.
     * If the field does not exists, false is returned.
     * 
     * @param string $field The field name.
     * @param strign $event The event name or (numerical) event id.
     * @return boolean|null
     */
    function isFieldOnEvent($field, $event) {
        $event_id = $this->getEventId($event);
        if ($event_id !== null && $this->hasField($field)) {
            $pds = $this->getProjectDataStructure();
            return array_key_exists($event_id, $pds["fields"][$field]["events"]);
        }
        return null;
    }

    /**
     * Checks whether a form is repeating. 
     * If the form or event does not exist, null is returned.
     * 
     * @param string $form The form name.
     * @param string $event The event name or (numerical) event id.
     * @return boolean|null
     */
    function isFormRepeating($form, $event) {
        $event_id = $this->getEventId($event);
        if ($event_id !== null && $this->isFormOnEvent($form, $event)) {
            $pds = $this->getProjectDataStructure();
            return $pds["events"][$event_id]["forms"][$form]["repeating"];
        }
        return null;
    }

    /**
     * Checks whether a form is on a specific event. 
     * If the form or event does not exist, false is returned.
     * @param string $form The form name.
     * @param string $event The event name or (numerical) event id.
     * @return boolean 
     */
    function isFormOnEvent($form, $event) {
        $pds = $this->getProjectDataStructure();
        if ($this->hasForm($form) && $this->hasEvent($event)) {
            if ($pds["longitudinal"]) {
                $event_id = $this->getEventId($event);
                return array_key_exists($event_id, $pds["forms"][$form]["events"]);
            }
            // In case of non-longitudinal projects, return true at this point
            return true;
        }
        return false;
    }

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
     * Gets the field type.
     * If the field does not exist, null is returned.
     * 
     * @param string $field The field name.
     * @return string 
     */
    function getFieldType($field) {
        $metadata = $this->getFieldMetadata($field);
        if ($metadata) {
            return $metadata["element_type"];
        }
        return null;
    }

    /**
     * Gets the field validation.
     * If the field does not exist, null is returned.
     * 
     * @param string $field The field name.
     * @return string 
     */
    function getFieldValidation($field) {
        $metadata = $this->getFieldMetadata($field);
        if ($metadata) {
            return $metadata["element_validation_type"];
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

        // Use REDCap's Project class to get some of the data. Specifically, unique event names are not in the backend database.
        $proj = new \Project($pid);
        $proj->getUniqueEventNames();
        $proj->getRepeatingFormsEvents();

        // Prepare return data structure.
        $ps = array(
            "pid" => $pid,
            "longitudinal" => $proj->longitudinal,
            "record_id" => $this->framework->getRecordIdField($pid),
            "forms" => array(),
            "events" => array(),
            "arms" => array(),
            "fields" => array(),
        );

        // Gather data - arms, events, forms.
        foreach ($proj->events as $this_arm) {
            $this_arm_id = $this_arm["id"];
            $ps["arms"][$this_arm_id]["id"] = $this_arm_id;
            $ps["arms"][$this_arm_id]["events"] = array();
            $ps["arms"][$this_arm_id]["forms"] = array();
            foreach ($this_arm["events"] as $this_event_id => $this_event) {
                $ps["arms"][$this_arm_id]["events"][$this_event_id] = array (
                    "id" => $this_event_id,
                    "name" => $proj->uniqueEventNames[$this_event_id]
                );
                $ps["events"][$this_event_id]["id"] = $this_event_id;
                $ps["events"][$this_event_id]["name"] = $proj->uniqueEventNames[$this_event_id];
                $ps["events"][$this_event_id]["repeating"] = false;
                $ps["events"][$this_event_id]["arm"] = $this_arm_id;
                foreach ($proj->eventsForms[$this_event_id] as $this_form) {
                    $ps["arms"][$this_arm_id]["forms"][$this_form] = array (
                        "name" => $this_form
                    );
                    $ps["events"][$this_event_id]["forms"][$this_form] = array (
                        "name" => $this_form,
                        "repeating" => false
                    );
                    $ps["forms"][$this_form]["name"] = $this_form;
                    $ps["forms"][$this_form]["repeating"] = false;
                    $ps["forms"][$this_form]["repeating_event"] = false;
                    $ps["forms"][$this_form]["arms"][$this_arm_id] = array(
                        "id" => $this_arm_id
                    );
                    $ps["forms"][$this_form]["events"][$this_event_id] = array(
                        "id" => $this_event_id,
                        "name" => $proj->uniqueEventNames[$this_event_id],
                        "repeating" => false
                    );
                }
            }
        }

        // Gather data - fields.
        foreach ($proj->metadata as $this_field_name => $this_field_info) {
            $this_form = $this_field_info["form_name"];
            $ps["fields"][$this_field_name] = array (
                "name" => $this_field_name,
                "form" => $this_form,
                "repeating_form" => false,
                "repeating_event" => false
            );
            $ps["forms"][$this_form]["fields"][] = $this_field_name;
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
            $field_data["metadata"] = $proj->metadata[$field];
        }

        // Add to cache.
        self::$ProjectDataStructureCache[$pid] = $ps;

        return $ps;
    }

    private static $ProjectDataStructureCache = array();

}