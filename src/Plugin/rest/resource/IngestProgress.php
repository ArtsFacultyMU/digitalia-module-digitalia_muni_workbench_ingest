<?php

namespace Drupal\digitalia_muni_workbench_ingest\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "ingest_progress",
 *   label = @Translation("Ingest progress endpoint"),
 *   uri_paths = {
 *     "canonical" = "/digitalia_muni_workbench_ingest/ingest_progress",
 *     "create" = "/digitalia_muni_workbench_ingest/ingest_progress"
 *   }
 * )
 */
class IngestProgress extends ResourceBase
{
	protected $progress_filename;

	public function __construct(
				array $configuration,
				$plugin_id,
				$plugin_definition,
				array $serializer_formats,
				LoggerInterface $logger,
				)
	{
		parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

		$filesystem = \Drupal::service('file_system');
		$this->progress_filename = $filesystem->realpath("temporary://") . "/progress.txt";
		\Drupal::logger("DEBUG_CONSTRUCT")->debug($this->progress_filename);

		touch($this->progress_filename);
	}

	/**
	 *
	 */
	public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
	{
		$instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

		return $instance;
	}

	public function get()
	{
		\Drupal::logger("GET_TEST")->debug("GET endpoint used");
		$progress_file = fopen($this->progress_filename, 'r');
		$fsize = filesize($this->progress_filename);
		if ($fsize == 0) {
			$fsize = 1;
		}

    $raw_data = fread($progress_file, $fsize);
    $data = json_decode($raw_data, TRUE);

    \Drupal::logger("DEBUG_GET_REST")->debug($fsize);
    \Drupal::logger("DEBUG_GET_REST")->debug(print_r($raw_data, TRUE));
    \Drupal::logger("DEBUG_GET_REST")->debug(print_r($data, TRUE));
		fclose($progress_file);

    $result = [];

    if ($data) {
		  $result = [
		  		"percentage" => $data["percentage"],
		  		"status" => $data["status"],
		  ];
    }

		$response = new ResourceResponse($result);
		$response->addCacheableDependency($result);

		return $response;
	}

	public function post($data)
	{
		$percentage = $data["percentage"];
		$status = $data["status"];
    
    $data["percentage"] = floor((float) $data["percentage"]);

		$progress_file = fopen($this->progress_filename, 'w');
		fwrite($progress_file, json_encode($data));
		fclose($progress_file);

		//$progress_file = fopen($this->progress_filename, 'r');
		//$percentage = floor(fread($progress_file, filesize($this->progress_filename)));
		//fclose($progress_file);

		return new ModifiedResourceResponse([], 200);
	}
}
