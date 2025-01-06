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
		
		return $form;
	}

	public function validateForm(array &$form, FormStateInterface $form_state) {}

	public function submitForm(array &$form, FormStateInterface $form_state)
	{
		$retval = $this->workbenchWrapper($form_state, false, $ret);

		if ($retval == 0) {
			\Drupal::messenger()->addStatus("Ingest successful!");
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
		$user = $config->get('user');
		$executable = $config->get('workbench_executable');

		$yaml_lines = file($workbench_config);
		$drupal_username = $config->get('drupal_user');
		$drupal_password = $config->get('drupal_password');
		
		$start = -1;
		for ($i = 0; $i < count($yaml_lines); $i += 1) {
			if (str_starts_with($yaml_lines[$i], "csv_field_templates:")) {
				$start = $i;
				break;
			}
			if (str_starts_with($yaml_lines[$i], "username:")) {
				$yaml_lines[$i] = "username: {$drupal_username}\n";	
			}
			if (str_starts_with($yaml_lines[$i], "password:")) {
				$yaml_lines[$i] = "password: {$drupal_password}\n";	
			}
		}

		$node_id = \Drupal::routeMatch()->getParameter("node")->id();
		$user_id = \Drupal::currentUser()->id();

		if (!$node_id) {
			\Drupal::logger("Digitalia workbench")->error("Invalid node id, aborting.");
			\Drupal::messenger->addError("Invalid node id, aborting. Please contact administrators.");
			return 1;
		}

		if ($start != -1) {
			array_splice($yaml_lines, $start + 1, 0, array(" - parent_id: {$node_id}\n"));
			array_splice($yaml_lines, $start + 1, 0, array(" - field_member_of: {$node_id}\n"));
			array_splice($yaml_lines, $start + 1, 0, array(" - uid: {$user_id}\n"));
		} else {
			array_push($yaml_lines, "csv_field_templates:\n");
			array_push($yaml_lines, " - parent_id: {$node_id}\n");
			array_push($yaml_lines, " - field_member_of: {$node_id}\n");
			array_push($yaml_lines, " - uid: {$user_id}\n");
		}

		$filesystem = \Drupal::service('file_system');
	
		$temp_filename = tempnam($filesystem->realpath("tmp://"), "WORKBENCH_TEST_");
		$temp_file = fopen($temp_filename, "w");

		foreach($yaml_lines as $line) {
			fwrite($temp_file, $line);
		}

		//\Drupal::logger("DEBUG_WORKBENCH")->debug(print_r($yaml_lines, TRUE));

		chmod($temp_filename, 0644);

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
	
	private function addParent()
	{

	}
}
