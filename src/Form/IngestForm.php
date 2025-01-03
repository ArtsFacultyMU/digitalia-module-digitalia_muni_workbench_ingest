<?php

namespace Drupal\digitalia_muni_workbench_ingest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;

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
		$form['actions']['#type'] = 'actions';
		$form['actions']['check'] = [
			'#type' => 'button',
			'#value' => $this->t('Check button'),
			'#ajax' => [
				'callback' => '::workbenchCheckCallback',
				'wrapper' => 'edit-output',
			],
		];
		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Ingest button'),
			'#button_type' => 'primary',
		];

		$form['output'] = [
			'#type' => 'markup',
			'#markup' => '<div id="edit-output">Config not checked</div>',
		];

		return $form;
	}

	public function validateForm(array &$form, FormStateInterface $form_state) {}

	public function submitForm(array &$form, FormStateInterface $form_state)
	{
	  	if ($this->workbenchCheck() != 0) {
			\Drupal::logger("Workbench Ingest")->error("Invalid input data.");
			return;
		}
		dpm("TEST");

		\Drupal::logger("DEBUG_INGEST")->debug("SUBMIT");
	}

	public function workbenchCheckCallback(array &$form, FormStateInterface $form_state)
	{
		\Drupal::logger("DEBUG_INGEST")->debug("CHECK");
		$output = array();
		$retval = null;
		$command = "sudo -u islandora /home/islandora/bin/islandora_workbench_test.sh";
	
		$ret = exec($command, $output, $retval);
		\Drupal::logger("DEBUG_INGEST")->debug("{$ret}");
		\Drupal::logger("DEBUG_INGEST")->debug("{$command}: retval = {$retval}\n" . print_r($output, TRUE));

		$check_result = "<div id='edit-output'>{$ret}</div>";
		if ($retval == 0) {
			$form['actions']['submit'] = [
				'#disabled' => false,
			];
		}

		return ['#markup' => $check_result];
	}

	private function workbenchCheck()
	{
		$output = array();
		$retval = null;
		$command = "sudo -u islandora /home/islandora/bin/islandora_workbench_check.sh";
	
		exec($command, $output, $retval);

		return $retval;
	}

	private function workbenchIngest()
	{
		$output = array();
		$retval = null;
		$command = "sudo -u islandora /home/islandora/bin/islandora_workbench_ingest.sh";
	
		exec($command, $output, $retval);

		return $retval;
	}

}
