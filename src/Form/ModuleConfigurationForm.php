<?php

namespace Drupal\digitalia_muni_workbench_ingest\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines configuration form for universal form module
 */
class ModuleConfigurationForm extends ConfigFormBase
{
	/**
	 * {@inheritdoc}
	 */
	public function getFormId()
	{
		return "digitalia_muni_workbench_ingest";
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getEditableConfigNames()
	{
		return [
			"digitalia_muni_workbench_ingest.settings",
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state)
	{
		$config = $this->config("digitalia_muni_workbench_ingest.settings");

		$form["system_user"] = [
			"#type" => "textfield",
			"#title" => $this->t("Workbench system user"),
			"#description" => $this->t("The user under which command is executed (e.g. www-data) MUST be able to sudo to specified system user without password."),
			"#default_value" => $config->get("system_user"),
		];

		$form["drupal_user"] = [
			"#type" => "textfield",
			"#title" => $this->t("Workbench Drupal user"),
			//"#description" => $this->t("The user for"),
			"#default_value" => $config->get("drupal_user"),
		];

		$form["drupal_password"] = [
			"#type" => "password",
			"#title" => $this->t("Workbench Drupal user password"),
			//"#description" => $this->t("The user under which command is executed (e.g. www-data) MUST be able to sudo to specified system user without password."),
			"#default_value" => $config->get("drupal_password"),
		];

		$form["workbench_executable"] = [
			"#type" => "textfield",
			"#title" => $this->t("Path to Workbench executable."),
			"#description" => $this->t("Absolute path."),
			"#default_value" => $config->get("workbench_executable"),
		];

		$form["config_files"] = [
			"#type" => "textarea",
			"#title" => $this->t("Paths to Workbench configuration files."),
			"#description" => $this->t("One file per line. First is considered default."),
			"#default_value" => $config->get("config_files"),
		];


		return parent::buildForm($form, $form_state);
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state)
	{
		foreach(array_keys($form) as $key) {
			//$this->config("digitalia_muni_workbench_ingest.settings")->set($key, $form_state->getValue($key));
			$this->config("digitalia_muni_workbench_ingest.settings")->set($key, $form_state->getValue($key))->save();
		}

		//$this->config("digitalia_muni_workbench_ingest.settings")->save();

		parent::submitForm($form, $form_state);
	}
}
