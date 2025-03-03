<?php

namespace Drupal\digitalia_muni_workbench_ingest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\Core\Messenger\MessengerInterface;

class IngestForm extends FormBase
{
	/**
	 * {@inheritdoc}
	 */
	public function getFormId()
	{
		return 'digitalia_muni_workbench_ingest_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state)
	{
		$config = $this->config('digitalia_muni_workbench_ingest.settings');

		$form['actions']['#type'] = 'actions';
		$form['actions']['check'] = [
			'#type' => 'button',
			'#value' => $this->t('Check config'),
			'#ajax' => [
				'callback' => '::workbenchCheckCallback',
				'wrapper' => 'edit-output',
			],
		];
		$form['actions']['ingest'] = [
			'#type' => 'button',
			'#value' => $this->t('Ingest'),
			'#ajax' => [
				'callback' => '::submitForm',
				'wrapper' => 'edit-output',
				'progress' => [
				  'type' => 'bar',
				  'message' => 'Importing...',
				  'url' => '/digitalia_muni_workbench_ingest/ingest_progress',
				  'interval' => '1000',
        ],
			],
		];

		// Dummy output
		$form['output'] = [
			'#type' => 'markup',
			'#markup' => '<div id="edit-output"></div>',
		];


		$config_files = $config->get('config_files');
		$exploded = explode("\r\n", $config_files);

		if (count($exploded) > 1) {
			$form['config'] = [
				'#type' => 'select',
				'#title' => 'Workbench config',
				'#options' => $exploded,
			];
		}

		//$form['start_row'] = [
		//	'#type' => 'textfield',
		//	'#title' => 'Start row',
		//	'#description' => 'Leave empty to ignore',
		//];

		//$form['stop_row'] = [
		//	'#type' => 'textfield',
		//	'#title' => 'Stop row',
		//	'#description' => 'Leave empty to ignore',
		//];
		
		return $form;
	}

	public function validateForm(array &$form, FormStateInterface $form_state) {}

	public function submitForm(array &$form, FormStateInterface $form_state)
	{
		$retval = $this->workbenchWrapper($form_state, false, $ret);

		if ($retval == 0) {
			\Drupal::messenger()->addStatus("Ingest successful! Reload the page to see results.");
		} else {
			\Drupal::messenger()->addError($ret);
		}

		$check_result = "<div id='edit-output'></div>";

		return ['#markup' => $check_result];
	}

	public function workbenchCheckCallback(array &$form, FormStateInterface $form_state)
	{
		$retval = $this->workbenchWrapper($form_state, true, $ret);

		if ($retval == 0) {
			\Drupal::messenger()->addStatus($ret);
		} else {
			\Drupal::messenger()->addError($ret);
		}

		$check_result = "<div id='edit-output'></div>";

		return ['#markup' => $check_result];
	}

	private function workbenchWrapper($form_state, $check_only, &$ret)
	{
		$config = $this->config('digitalia_muni_workbench_ingest.settings');

		$form_index = $form_state->getValue("config");

		$index = 0;
		if ($form_index) {
			$index = $form_index;
		}

		$workbench_config = explode("\r\n", $config->get('config_files'))[$index];
		$user = $config->get('system_user');
		$executable = $config->get('workbench_executable');

		$config_yaml_parsed = yaml_parse_file($workbench_config);

		$yaml_lines = file($workbench_config);
		$drupal_username = $config->get('drupal_user');
		$drupal_password = $config->get('drupal_password');

		$node_id = \Drupal::routeMatch()->getParameter("node")->id();
		$user_id = \Drupal::currentUser()->id();

		if (!$node_id) {
			\Drupal::logger("Digitalia workbench")->error("Invalid node id, aborting.");
			\Drupal::messenger()->addError("Invalid node id, aborting. Please contact administrators.");
			return 1;
		}


		// Add credentials and node info to workbench config
		if (!$config_yaml_parsed["csv_field_templates"]) {
			$config_yaml_parsed["csv_field_templates"] = array();
		}

		array_push($config_yaml_parsed["csv_field_templates"], array("field_member_of" => $node_id));
		array_push($config_yaml_parsed["csv_field_templates"], array("uid" => $user_id));
		array_push($config_yaml_parsed["csv_field_templates"], array("field_model" => "Page"));
		$config_yaml_parsed["username"] = $drupal_username;
		$config_yaml_parsed["password"] = trim($drupal_password);


		// Show first few lines from import csv
		$import_csv = fopen("{$config_yaml_parsed['input_dir']}/{$config_yaml_parsed['input_csv']}", "r");

		if ($check_only) {
			\Drupal::messenger()->addStatus("Config excerpt:");
			for ($i = 0; $i < 5; $i += 1) {
				\Drupal::messenger()->addStatus(fgets($import_csv));
			}

			if (!$this->checkLineCount($import_csv)) {
				\Drupal::logger("Digitalia workbench")->warning("Line count mismatch in 'import.csv'");
				\Drupal::messenger()->addWarning("Line count mismatch, please check 'import.csv'");
			}
		}

		// Write modified config
		$filesystem = \Drupal::service('file_system');
		$temp_filename = tempnam($filesystem->realpath("tmp://"), "WORKBENCH_TMP_CONFIG_");
		yaml_emit_file($temp_filename, $config_yaml_parsed);
		chmod($temp_filename, 0640);

		return $this->workbenchStart($user, $executable, $temp_filename, $ret, $check_only);
	}

	private function workbenchStart($user, $executable, $config, &$ret, $check_only)
	{
		$output = array();
		$retval = null;
		$check = "";
		if ($check_only) {
			$check = "--check";
		}

		$command = "sudo -u {$user} {$executable} --config {$config} {$check} 2>&1";
		$ret = exec($command, $output, $retval);

		return $retval;
	}

	private function checkLineCount($file_handle)
	{
		rewind($file_handle);
		$line_count = 0;
		$last_line = [];

		$header = fgetcsv($file_handle, null, ";");
		rewind($file_handle);

		while (!feof($file_handle)) {
			$tmp_last_line = fgetcsv($file_handle, null, ";");

			if ($tmp_last_line) {
				$last_line = $tmp_last_line;
				$line_count += 1;
			}

			if ($tmp_last_line && sizeof($tmp_last_line) != sizeof($header)) {
				\Drupal::messenger()->addWarning("Empty or incomplete lines detected in 'import.csv' (line {$line_count}).");
				\Drupal::logger("Digitalia workbench")->warning("Empty or incomplete lines detected in 'import.csv' (line {$line_count}).");
			}
		}


		// Take header into consideration
		return $line_count == ((int) $last_line[0] + 1);
	}
}
