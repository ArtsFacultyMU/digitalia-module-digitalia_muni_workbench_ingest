<?php

namespace Drupal\digitalia_muni_workbench_ingest\Form;

use Drupal\file\Entity\File;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

class IngestForm extends FormBase
{
	protected $timestamp;

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
		//$form['actions']['check'] = [
		//	'#type' => 'button',
		//	'#value' => $this->t('Check config'),
		//	'#ajax' => [
		//		'callback' => '::workbenchCheckCallback',
		//		'wrapper' => 'edit-output',
		//	],
		//];
		if (is_null($this->timestamp)) {
			$this->timestamp = time();
		}
		$user_id = \Drupal::currentUser()->id();
		$media_id = \Drupal::routeMatch()->getRawParameter("media");
		\Drupal::logger("DEBUG_TIMESTAMP")->debug($this->timestamp);
		$form['actions']['ingest'] = [
			'#type' => 'button',
			'#value' => $this->t('Ingest'),
			'#ajax' => [
				'callback' => '::submitForm',
				'wrapper' => 'edit-output',
				'progress' => [
					'type' => 'bar',
					'message' => 'Importing...',
					'url' => "/digitalia_muni_workbench_ingest/ingest_progress/{$user_id}/{$media_id}",
					'interval' => '1000',
        			],
				//'progress' => [
				//  'type' => 'bar',
				//  'message' => 'Importing...',
				//  'url' => '/digitalia_muni_workbench_ingest/ingest_progress',
				//  'interval' => '1000',
        			//],
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
		$retval = $this->workbenchWrapper($form_state, false, $ret, $this->timestamp);

		if ($retval == 0) {
			\Drupal::messenger()->addStatus("Ingest successful! Reload the page to see results.");
		} else {
			\Drupal::messenger()->addError($ret);
		}

		$check_result = "<div id='edit-output'></div>";

		return ['#markup' => $check_result];
	}

	private function workbenchWrapper($form_state, $check_only, &$ret, $timestamp)
	{
		// Timestamp cannot be used, because there is no way to pass the timestamp from initial
		// form creation to callback function (when the callback is executed, the form class is
		// instantiated again)
		$config = $this->config('digitalia_muni_workbench_ingest.settings');

		$form_index = $form_state->getValue("config");

		$index = 0;
		if ($form_index) {
			$index = $form_index;
		}

		//$workbench_config = explode("\r\n", $config->get('config_files'))[$index];

		$user_id = \Drupal::currentUser()->id();
		$media_id = \Drupal::routeMatch()->getRawParameter("media");
		\Drupal::logger("DEBUG_WORKBENCH")->debug(print_r($media_id, TRUE));


		$client_factory = \Drupal::service('http_client_factory');
		$client = $client_factory->fromOptions(['verify' => FALSE]);
		//$timestamp = time();

		\Drupal::logger("DEBUG_WORKBENCH")->debug("{$timestamp}");
		//$client->get('http://workbench/index.php');
		$media = Media::load($media_id);
		$fid = $media->getSource()->getSourceFieldValue($media);
		$file = File::load($fid);
		$file_uri = $file->getFileUri();
		$external_file_uri = preg_replace('/^fedora:\/\/(.*)/', '/_flysystem/fedora/${1}', $file_uri);
		\Drupal::logger("DEBUG_WORKBENCH")->debug("{$external_file_uri}");
		try {
			$ret = $client->post('localhost:8080/api/execute.php', [
				'form_params' => [
					'user_id' => $user_id,
					'workbench_config' => 'workbench_base.yml',
					'media_url' => $external_file_uri,
					'media_id' => $media_id,
					'timestamp' => $timestamp,
					'check' => '0',
				],
				'timeout' => 0
			]);
		} catch (Exception $e) {
			\Drupal::logger("DEBUG_WORKBENCH")->debug("POST request execption!");
			\Drupal::logger("DEBUG_WORKBENCH")->debug($e->getMessage());
		}

		\Drupal::logger("DEBUG_WORKBENCH")->debug("POST POST request");
		//\Drupal::logger("DEBUG_WORKBENCH")->debug(print_r($ret->getBody()->getContents(), TRUE));
		\Drupal::logger("DEBUG_WORKBENCH")->debug(print_r($ret->getBody()->getContents(), TRUE));

		if ($ret->getStatusCode() == 200) {
			return 0;
		} else {
			return $ret->getStatusCode();
		}
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

	private function checkLineCount($file_handle, $delimiter)
	{
		rewind($file_handle);
		$line_count = 0;
		$last_line = [];

		$header = fgetcsv($file_handle, null, $delimiter);
		rewind($file_handle);

		while (!feof($file_handle)) {
			$tmp_last_line = fgetcsv($file_handle, null, $delimiter);

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
