<?php

namespace Drupal\digitalia_muni_workbench_ingest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileSystem;
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

		$form['output'] = [
			'#type' => 'markup',
			'#markup' => '<div id="edit-output">Config not checked</div>',
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
		$message = "Ingest successful. Refresh page to see results.";
		$message_type = MessengerInterface::TYPE_STATUS;
		if ($retval != 0) {
			$message = "Ingest failed. Have you checked config beforehand?";
			$message_type = MessengerInterface::TYPE_ERROR;
			\Drupal::logger("DEBUG_INGEST")->debug("INGEST FAILED");
		}
		\Drupal::messenger()->addMessage($message, $message_type);

		\Drupal::logger("DEBUG_INGEST")->debug("INGEST SUCCESSFUL");
		//$check_result = "<div id='edit-output'>Ingest completed</div>";
		$check_result = "<div id='edit-output'></div>";

		return ['#markup' => $check_result];
	}

	public function workbenchCheckCallback(array &$form, FormStateInterface $form_state)
	{
		\Drupal::logger("DEBUG_INGEST")->debug("CHECK");

		$ret = "";
		$retval = $this->workbenchWrapper($form_state, true, $ret);

		if ($retval == 0) {
			\Drupal::messenger()->addStatus($ret);
		} else {
			\Drupal::messenger()->addError($ret);
		}

		//$check_result = "<div id='edit-output'>{$ret}</div>";
		$check_result = "<div id='edit-output'>Config checked</div>";
		//if ($retval == 0) {
		//	$form['actions']['ingest'] = [
		//		'#disabled' => false,
		//	];
		//}

		//return $form;
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
		\Drupal::logger("DEBUG_INGEST")->debug(print_r($yaml_lines, TRUE));

		
		$start = -1;
		for ($i = 0; $i < count($yaml_lines); $i += 1) {
			if (str_starts_with($yaml_lines[$i], "csv_field_templates:")) {
				$start = $i;
				break;
			}
		}

		$node_id = null;
		$user_id = null;
		$node_id = \Drupal::routeMatch()->getParameter("node")->id();
		$user_id = \Drupal::currentUser()->id();
		\Drupal::logger("DEBUG_INGEST")->debug("NODE ID: {$node_id}");

		//$node_id = "";

		if (!$node_id) {
			\Drupal::logger("DEBUG_INGEST")->error("Invalid node id, aborting.");
			\Drupal::messenger->addError("Invalid node id, aborting. Please contact administrators.");
			return 1;
		}

		if ($start != -1) {
			array_splice($yaml_lines, $start + 1, 0, array(" - parent_id: {$node_id}\n"));
			array_splice($yaml_lines, $start + 1, 0, array(" - field_member_of: {$node_id}\n"));
			array_splice($yaml_lines, $start + 1, 0, array(" - uid: {$user_id}\n"));
			\Drupal::logger("DEBUG_INGEST")->debug(print_r($yaml_lines, TRUE));
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

		chmod($temp_filename, 0644);


		return $this->workbenchStart($user, $executable, $temp_filename, $ret, $check_only);
	}

	private function workbenchStart($user, $executable, $workbench_config, &$ret, $check_only)
	{
		$output = array();
		$retval = null;
		$check = "";
		if ($check_only) {
			$check = "--check";
		}

		$command = "sudo -u {$user} {$executable} --config {$workbench_config} {$check} 2>&1";
	
		$ret = exec($command, $output, $retval);
		\Drupal::logger("DEBUG_INGEST")->debug("{$ret}");
		\Drupal::logger("DEBUG_INGEST")->debug("{$command}: retval = {$retval}\n" . print_r($output, TRUE));

		return $retval;
	}
	
	private function addParent()
	{

	}
}
