<?php

namespace DE\RUB\UnansweredExternalModule;

use Exception;
use Project;
use RCView;

class UnansweredExternalModule extends \ExternalModules\AbstractExternalModule
{
	private $js_debug = false;

	/** @var Project The current project */
	private $proj = null;
	/** @var int|null Project ID */
	private $project_id = null;

	const AT_N_UNANSWERED = "@N-UNANSWERED";
	const AT_N_UNANSWERED_EXCLUDED = "@N-UNANSWERED-EXCLUDED";
	const AT_N_UNANSWERED_ALWAYS_INCLUDED = "@N-UNANSWERED-ALWAYS-INCLUDED";
	const AT_N_UNANSWERED_HIGHLIGHT_PROGRESSIVE = "@N-UNANSWERED-HIGHLIGHT-PROGRESSIVE";
	const AT_N_UNANSWERED_HIGHLIGHT_WITH_DIALOG = "@N-UNANSWERED-HIGHLIGHT-WITH-DIALOG";
	const AT_N_UNANSWERED_DIALOG = "@N-UNANSWERED-DIALOG";

	#region Hooks

	function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
	{
		$this->init_proj($project_id);
		$context = [
			"project_id" => $project_id,
			"record" => $record,
			"instrument" => $instrument,
			"event_id" => $event_id,
			"repeat_instance" => $repeat_instance,
			"is_survey" => false,
		];
		$this->inject_unanswered($context);
	}

	function redcap_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
	{
		$this->init_proj($project_id);
		$context = [
			"project_id" => $project_id,
			"record" => $record,
			"instrument" => $instrument,
			"event_id" => $event_id,
			"repeat_instance" => $repeat_instance,
			"is_survey" => true,
		];
		$this->inject_unanswered($context);
	}

	#endregion

	#region Data Entry / Survey Injection

	/**
	 * Injects the code necessary for counting unanswered questions on data entry or survey pages.
	 * @param string $instrument
	 * @param boolean $is_survey 
	 */
	private function inject_unanswered($context)
	{
		// Check for N-UNANSWERED action tag
		require_once "classes/ActionTagHelper.php";
		$page_fields = $this->get_page_fields($context["instrument"], $context["is_survey"]);
		$tagged = ActionTagHelper::getActionTags(self::AT_N_UNANSWERED, array_keys($page_fields), null, $context)[self::AT_N_UNANSWERED] ?? [];
		if (!count($tagged)) {
			return; // We are done
		}
		// Validate action tag use (must be on a field of type 'Text Box' with validation set to 'Integer', 
		// or on a Calc field)
		$counters = [];
		foreach ($tagged as $field_name => $params) {
			$field = $page_fields[$field_name];
			$field_type = "";
			if ($field["element_type"] == "calc") {
				$field_type = "calc";
			}
			else if ($field["element_type"] == "text" && $field["element_validation_type"] == "int") {
				$field_type = "int";
			}
			if (in_array($field_type, ["calc", "int"])) {
				if (isset($params["params"])) {
					$list = trim($params["params"], "'\"");
					if ($list == "*") {
						// Required fields
						$list = [];
						foreach ($page_fields as $req_field_name => $req_field) {
							if ($req_field["field_req"] == "1") {
								$list[] = $req_field_name;
							}
						}
					}
					else {
						$list = array_filter(array_unique(array_map("trim", explode(",", $list))), "strlen");
						$list = $this->add_section_fields($list, $page_fields);
						$list = array_intersect($list, array_keys($page_fields));
					}
				}
				$counters[$field_name] = [
					"fields" => $list,
					"excluded" => [],
					"alwaysIncluded" => [],
					"highlightProgressive" => "",
					"highlightWithDialog" => "",
					"dialog" => null,
					"fieldType" => $field_type,
				];
			}
		}
		if (!count($counters)) {
			return; // No valid fields - we are done
		}
		$errors = [];
		// Get all other action tags
		$action_tags = ActionTagHelper::getActionTags([
				self::AT_N_UNANSWERED_EXCLUDED,
				self::AT_N_UNANSWERED_ALWAYS_INCLUDED,
				self::AT_N_UNANSWERED_HIGHLIGHT_PROGRESSIVE,
				self::AT_N_UNANSWERED_HIGHLIGHT_WITH_DIALOG,
				self::AT_N_UNANSWERED_DIALOG
			], array_keys($page_fields), null, $context);
		// Check for N-UNANSWERED-EXCLUDED action tag
		$excluded = $action_tags[self::AT_N_UNANSWERED_EXCLUDED] ?? [];
		foreach ($excluded as $field_name => $params) {
			$list = trim($params["params"], "'\"");
			$list = array_filter(array_unique(array_map("trim", explode(",", $list))), "strlen");
			if (count($list) == 0) $list = array_keys($counters);
			foreach ($list as $counter_field) {
				if (isset($counters[$counter_field]) && (count($counters[$counter_field]["fields"]) == 0 || in_array($field_name, $counters[$counter_field]["fields"]))) {
					$counters[$counter_field]["excluded"][] = $field_name;
				}
			}
		}
		// Check for N-UNANSWERED-EMBEDDED-INCLUDED action tag
		$always_included = $action_tags[self::AT_N_UNANSWERED_ALWAYS_INCLUDED] ?? [];
		foreach ($always_included as $field_name => $params) {
			$list = trim($params["params"], "'\"");
			$list = array_filter(array_unique(array_map("trim", explode(",", $list))), "strlen");
			if (count($list) == 0) $list = array_keys($counters);
			foreach ($list as $counter_field) {
				if (isset($counters[$counter_field]) && (count($counters[$counter_field]["fields"]) == 0 || in_array($field_name, $counters[$counter_field]["fields"]))) {
					$counters[$counter_field]["alwaysIncluded"][] = $field_name;
				}
			}
		}
		// Check for N-UNANSWERED-HIGHLIGHT-PROGRESSIVE action tag (must be applied with the N-UNANSWERED action tag)
		$highlight_progressive = $action_tags[self::AT_N_UNANSWERED_HIGHLIGHT_PROGRESSIVE] ?? [];
		foreach ($highlight_progressive as $counter_field => $params) {
			if (isset($counters[$counter_field])) {
				$color = strip_tags(trim($params["params"], "'\""));
				if ($color == "") $color = "red";
				$counters[$counter_field]["highlightProgressive"] = $color;
			}
		}
		// Check for N-UNANSWERED-HIGHLIGHT-PROGRESSIVE action tag (must be applied with the N-UNANSWERED action tag)
		$highlight_with_dialog = $action_tags[self::AT_N_UNANSWERED_HIGHLIGHT_WITH_DIALOG] ?? [];
		foreach ($highlight_with_dialog as $counter_field => $params) {
			if (isset($counters[$counter_field])) {
				$color = strip_tags(trim($params["params"], "'\""));
				if ($color == "") $color = "red";
				$counters[$counter_field]["highlightWithDialog"] = $color;
			}
		}
		// Check for N-UNANSWERED-DIALOG action tag
		$dialog = $action_tags[self::AT_N_UNANSWERED_DIALOG] ?? [];
		foreach ($dialog as $counter_field => $params) {
			if (isset($counters[$counter_field])) {
				$dialog_params = explode(",", trim($params["params"], "'\""));
				$dialog_field = $counter_field;
				$dialog_threshold = 1;
				$dialog_nss = false;
				$dialog_npp = false;
				foreach ($dialog_params as $dialog_param) {
					$dialog_param = trim($dialog_param);
					if ($dialog_param == "NSS") {
						$dialog_nss = true;
					}
					elseif ($dialog_param == "NPP") {
						$dialog_npp = true;
					}
					elseif (isinteger($dialog_param)) {
						if (intval($dialog_param) > 0) {
							$dialog_threshold = intval($dialog_param);
						}
					}
					elseif (array_key_exists($dialog_param, $page_fields)) {
						$dialog_field = $dialog_param;
					}
					else {
						$errors[] = "Invalid ". self::AT_N_UNANSWERED_DIALOG . " parameters: " . $params["params"];
					}
				}
				$counters[$counter_field]["dialog"] = [
					"field" => $dialog_field,
					"threshold" => $dialog_threshold,
					"nss" => $dialog_nss,
					"npp" => $dialog_npp,
				];
			}
		}
		// Ensure uniqueness in all arrays
		foreach ($counters as $_ => &$counter) {
			$counter["alwaysIncluded"] = array_unique($counter["alwaysIncluded"]);
			$counter["excluded"] = array_unique($counter["excluded"]);
			$counter["fields"] = array_unique($counter["fields"]);
		}
		// Prepare config
		$this->init_config();
		$config = array(
			"version" => $this->VERSION,
			"debug" => $this->js_debug,
			"counters" => $counters,
			"fields" => array_values(array_filter(array_keys($page_fields), function ($field_name) use ($counters, $page_fields, $instrument) { 
				// Filter out fields that are counters, descriptive fields, calc fields, the record id field, and the form_complete field
				// CALCTEXT and CALCDATE will be filtered out later (JavaScript)
				return !array_key_exists($field_name, $counters) &&
					$page_fields[$field_name]["element_type"] != "descriptive" && 
					$page_fields[$field_name]["element_type"] != "calc" && 
					$field_name != $this->proj->table_pk && 
					$field_name != "{$instrument}_complete"; 
			})),
			"isSurvey" => $context["is_survey"],
			"defaultTitle" => RCView::tt("global_03"),
		);
		// Output JS and init code
		require_once "classes/InjectionHelper.php";
		$ih = InjectionHelper::init($this);
		$ih->js("js/Unanswered.js", $context["is_survey"]);
		$ih->css("css/Unanswered.css", $context["is_survey"]);
		// $this->initializeJavascriptModuleObject();
		// $jsmo_name = $this->getJavascriptModuleObjectName();
		$jsmo_name = "null";
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
		$this->js_debug  = $this->getProjectSetting("javascript-debug") == true;
	}

	#endregion

}
