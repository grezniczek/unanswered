<?php

namespace DE\RUB\UnansweredExternalModule;

use Exception;
use RCView;

class UnansweredExternalModule extends \ExternalModules\AbstractExternalModule
{
    private $js_debug = false;

    /** @var \Project */
    private $proj = null;
    private $project_id = null;

    const AT_N_UNANSWERED = "@N-UNANSWERED";
    const AT_N_UNANSWERED_EXCLUDED = "@N-UNANSWERED-EXCLUDED";
    const AT_N_UNANSWERED_ALWAYS_INCLUDED = "@N-UNANSWERED-ALWAYS-INCLUDED";
    const AT_N_UNANSWERED_HIGHLIGHT = "@N-UNANSWERED-HIGHLIGHT";

    #region Hooks

    function redcap_data_entry_form($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1)
    {
        $this->inject_unanswered($project_id, $instrument, false);
    }

    function redcap_survey_page($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id = NULL, $repeat_instance = 1)
    {
        $this->inject_unanswered($project_id, $instrument, true);
    }

    #endregion

    #region Data Entry / Survey Injection

    /**
     * Injects the code necessary for counting unanswered questions on data entry or survey pages.
     * @param string $instrument
     * @param boolean $is_survey 
     */
    private function inject_unanswered($project_id, $instrument, $is_survey)
    {
        $this->init_proj($project_id);
        // Check for N-UNANSWERED action tag
        require_once "classes/ActionTagHelper.php";
        $page_fields = $this->get_page_fields($instrument, $is_survey);
        $tagged = ActionTagHelper::getActionTags(self::AT_N_UNANSWERED, array_keys($page_fields))[self::AT_N_UNANSWERED] ?? [];
        if (!count($tagged)) {
            return; // We are done
        }
        // Validate action tag use (must be on a field of type 'Text Box' with validation set to 'Integer')
        $valid_tagged_fields = [];
        foreach ($tagged as $field_name => $params) {
            $field = $page_fields[$field_name];
            if ($field["element_type"] == "text" && $field["element_validation_type"] == "int") {
                if (isset($params["params"])) {
                    $list = trim($params["params"], "'\"");
                    $list = array_filter(array_unique(array_map("trim", explode(",", $list))), "strlen");
                    $list = $this->add_section_fields($list, $page_fields);
                    $list = array_intersect($list, array_keys($page_fields));
                }
                $valid_tagged_fields[$field_name] = $list;
            }
        }
        if (!count($valid_tagged_fields)) {
            return; // No valid fields - we are done
        }
        // Check for N-UNANSWERED-EXCLUDED action tag
        $excluded = ActionTagHelper::getActionTags(self::AT_N_UNANSWERED_EXCLUDED, array_keys($page_fields))[self::AT_N_UNANSWERED_EXCLUDED] ?? [];
        // Check for N-UNANSWERED-EMBEDDED-INCLUDED action tag
        $always_included = ActionTagHelper::getActionTags(self::AT_N_UNANSWERED_ALWAYS_INCLUDED, array_keys($page_fields))[self::AT_N_UNANSWERED_ALWAYS_INCLUDED] ?? [];
        // Check for N-UNANSWERED-HIGHLIGHT action tag (must be applied with the N-UNANSWERED action tag)
        $highlight = ActionTagHelper::getActionTags(self::AT_N_UNANSWERED_HIGHLIGHT, array_keys($valid_tagged_fields))[self::AT_N_UNANSWERED_HIGHLIGHT] ?? [];
        foreach ($highlight as $field_name => $params) {
            $color = strip_tags(trim($params["params"], "'\""));
            if ($color == "") $color = "red";
            $highlight[$field_name] = $color;
        }
        // Prepare config
        $this->init_config();
        $config = array(
            "version" => $this->VERSION,
            "debug" => $this->js_debug,
            "counters" => $valid_tagged_fields,
            "excluded" => array_keys($excluded),
            "alwaysIncluded" => array_keys($always_included),
            "highlight" => $highlight,
            "fields" => array_values(array_filter(array_keys($page_fields), function ($field_name) use ($valid_tagged_fields, $page_fields, $instrument) { 
                // Filter out fields that are counters, descriptive fields, calc fields, the record id field, and the form_complete field
                // CALCTEXT and CALCDATE will be filtered out later (JavaScript)
                return !array_key_exists($field_name, $valid_tagged_fields) &&
                    $page_fields[$field_name]["element_type"] != "descriptive" && 
                    $page_fields[$field_name]["element_type"] != "calc" && 
                    $field_name != $this->proj->table_pk && 
                    $field_name != "{$instrument}_complete"; 
            })),
            "isSurvey" => $is_survey,
        );
        // Output JS and init code
        require_once "classes/InjectionHelper.php";
        $ih = InjectionHelper::init($this);
        $ih->js("js/Unanswered.js", $is_survey);
        $ih->css("css/Unanswered.css", $is_survey);
        $this->initializeJavascriptModuleObject();
        $jsmo_name = $this->getJavascriptModuleObjectName();
        print \RCView::script("DE_RUB_Unanswered.init(".json_encode($config).", $jsmo_name);");
    }

    #endregion

    #region Private Helpers

    private function add_section_fields($orig_fields, $page_fields)
    {
        $fields = [];
        $ordered_page_fields = [];
        $min = PHP_INT_MAX;
        $max = 1;
        foreach ($page_fields as $field_name => $field) {
            $pos = $field["field_order"];
            $min = min($min, $pos);
            $max = max($max, $pos);
            $ordered_page_fields[$pos] = $field_name;
        }
        ksort($ordered_page_fields);
        foreach ($orig_fields as $field_name) {
            if (strpos($field_name, "__") === 0) {
                $field = substr($field_name, 2);
                $field_idx = array_search($field, $ordered_page_fields);
                if ($field_idx === false) continue;
                // Add all fields before the field, until a field with a section header is reached
                for ($i = $field_idx; $i >= $min; $i--) {
                    $this_field = $ordered_page_fields[$i];
                    $fields[] = $this_field;
                    if ($page_fields[$this_field]["element_preceding_header"] !== null) break;
                }
                $fields = array_reverse($fields);
                // Add all fields after the field, until a field with a section header is reached
                for ($i = $field_idx + 1; $i <= $max; $i++) {
                    $this_field = $ordered_page_fields[$i];
                    if ($page_fields[$this_field]["element_preceding_header"] !== null) break;
                    $fields[] = $this_field;
                }
            }
            else {
                $fields[] = $field_name;
            }
        }
        return $fields;
    }

    /**
     * Gets a list of field on the page
     * @param string $project_id 
     * @param string $form 
     * @param boolean $is_survey
     * @return array<string, array> 
     */
    private function get_page_fields($form, $is_survey = false)
    {
        $this->require_proj();
        $fields = [];
        if ($is_survey) {
            $page = $_GET["__page__"];
            foreach ($GLOBALS["pageFields"][$page] as $field_name) {
                $fields[$field_name] = $this->get_field_metadata($field_name);
            }
        }
        else {
            foreach($this->get_form_fields($form) as $field_name) {
                $fields[$field_name] = $this->get_field_metadata($field_name);
            }
        }
        return $fields;
    }


    private function get_project_forms()
    {
        $this->require_proj();
        return $this->is_draft_preview() ? $this->proj->forms_temp : $this->proj->getForms();
    }

    private function get_form_fields($form_name)
    {
        $this->require_proj();
        $forms = $this->get_project_forms();
        if (!isset($forms[$form_name])) {
            throw new Exception("Form '$form_name' does not exist!");
        }
        return array_keys($forms[$form_name]["fields"]);
    }

    private function get_project_metadata()
    {
        $this->require_proj();
        return $this->is_draft_preview() ? $this->proj->metadata_temp : $this->proj->getMetadata();
    }

    private function get_field_metadata($field_name)
    {
        $this->require_proj();
        $meta = $this->get_project_metadata();
        if (!array_key_exists($field_name, $meta)) {
            throw new Exception("Field '$field_name' does not exist!");
        }
        return $meta[$field_name];
    }

    private function is_draft_preview()
    {
        $this->require_proj();
        return intval($this->proj->project["status"] ?? 0) > 0 && intval($this->proj->project["draft_mode"]) > 0 && $GLOBALS["draft_preview_enabled"] == true;
    }

    private function get_salt()
    {
        $this->require_proj();
        return $this->proj->project["__SALT__"] ?? "--no-salt--";
    }


    private function init_proj($project_id)
    {
        if ($this->proj == null) {
            $this->proj = new \Project($project_id);
            $this->project_id = $project_id;
        }
    }

    private function require_proj()
    {
        if ($this->proj == null) {
            throw new Exception("Project not initialized");
        }
    }

    private function init_config()
    {
        $this->require_proj();
        $setting = $this->getProjectSetting("javascript-debug");
        $this->js_debug = $setting == true;
    }

    #endregion

}
