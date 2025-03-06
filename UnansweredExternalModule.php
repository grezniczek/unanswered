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
        foreach ($tagged as $field_name => $_) {
            $field = $page_fields[$field_name];
            if ($field["element_type"] == "text" && $field["element_validation_type"] == "int") {
                $valid_tagged_fields[] = $field_name;
            }
        }
        if (!count($valid_tagged_fields)) {
            return; // No valid fields - we are done
        }
        // Check for N-UNANSWERED-EXCLUDED action tag
        $tagged = ActionTagHelper::getActionTags(self::AT_N_UNANSWERED_EXCLUDED, array_keys($page_fields))[self::AT_N_UNANSWERED_EXCLUDED] ?? [];
        // Prepare config
        $this->init_config();
        $config = array(
            "version" => $this->VERSION,
            "debug" => $this->js_debug,
            "counters" => $valid_tagged_fields,
            "excluded" => array_keys($tagged)
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
            foreach($this->proj->forms[$form]["fields"] as $field_name => $_) {
                $fields[$field_name] = $this->get_field_metadata($field_name);
            }
        }
        return $fields;
    }


    private function get_project_forms()
    {
        $this->require_proj();
        return $this->is_draft_mode() ? $this->proj->forms_temp : $this->proj->getForms();
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
        return $this->is_draft_mode() ? $this->proj->metadata_temp : $this->proj->getMetadata();
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

    private function is_draft_mode()
    {
        $this->require_proj();
        return intval($this->proj->project["status"] ?? 0) > 0;
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
