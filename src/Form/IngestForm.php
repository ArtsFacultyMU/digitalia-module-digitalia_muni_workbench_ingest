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
	/**
	 * {@inheritdoc}
	 */
	public function getFormId()
	{
		return 'digitalia_muni_workbench_ingest_form';
	}

	// TODO: check ingest status and disable button, if not in state "not yet ingested"
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state)
	{
		$config = $this->config('digitalia_muni_workbench_ingest.settings');

		$form['actions']['#type'] = 'actions';

		$user_id = \Drupal::currentUser()->id();
		$media_id = \Drupal::routeMatch()->getRawParameter("media");

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

	// TODO: set ingest status to "in progress"
	//  
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

	private function workbenchWrapper($form_state, $check_only, &$ret)
	{
		// Timestamp cannot be used, because there is no way to pass the timestamp from initial
		// form creation to callback function (when the callback is executed, the form class is
		// instantiated again)
		$config = $this->config('digitalia_muni_workbench_ingest.settings');
		$form_index = $form_state->getValue("config");
		$timestamp = time();

		$user_id = \Drupal::currentUser()->id();
		$media_id = \Drupal::routeMatch()->getRawParameter("media");

		$client_factory = \Drupal::service('http_client_factory');
		$client = $client_factory->fromOptions(['verify' => FALSE]);

		$media = Media::load($media_id);
		$fid = $media->getSource()->getSourceFieldValue($media);
		$file = File::load($fid);
		$file_uri = $file->getFileUri();
		$external_file_uri = preg_replace('/^fedora:\/\/(.*)/', '/_flysystem/fedora/${1}', $file_uri);

		try {
			$ret = $client->post('workbench:8080/api/execute.php', [
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
			\Drupal::logger("workbench")->error($e->getMessage());
		}

		$status_code = -1;

		if ($ret) {
			$status_code = $ret->getStatusCode();
		}


		if ($status_code == 200) {
			return 0;
		} else {
			return $status_code;
		}
	}

//	private function workbenchStart($user, $executable, $config, &$ret, $check_only)
//	{
//		$output = array();
//		$retval = null;
//		$check = "";
//		if ($check_only) {
//			$check = "--check";
//		}
//
//		$command = "sudo -u {$user} {$executable} --config {$config} {$check} 2>&1";
//		$ret = exec($command, $output, $retval);
//
//		return $retval;
//	}
//
//	private function checkLineCount($file_handle, $delimiter)
//	{
//		rewind($file_handle);
//		$line_count = 0;
//		$last_line = [];
//
//		$header = fgetcsv($file_handle, null, $delimiter);
//		rewind($file_handle);
//
//		while (!feof($file_handle)) {
//			$tmp_last_line = fgetcsv($file_handle, null, $delimiter);
//
//			if ($tmp_last_line) {
//				$last_line = $tmp_last_line;
//				$line_count += 1;
//			}
//
//			if ($tmp_last_line && sizeof($tmp_last_line) != sizeof($header)) {
//				\Drupal::messenger()->addWarning("Empty or incomplete lines detected in 'import.csv' (line {$line_count}).");
//				\Drupal::logger("Digitalia workbench")->warning("Empty or incomplete lines detected in 'import.csv' (line {$line_count}).");
//			}
//		}
//
//
//		// Take header into consideration
//		return $line_count == ((int) $last_line[0] + 1);
//	}
}
