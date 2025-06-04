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
 *     "canonical" = "/digitalia_muni_workbench_ingest/ingest_progress/{user_id}/{media_id}",
 *   }
 * )
 */
class IngestProgress extends ResourceBase
{
	public function __construct(
				array $configuration,
				$plugin_id,
				$plugin_definition,
				array $serializer_formats,
				LoggerInterface $logger,
				)
	{
		parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
	}

	public function get($user_id, $media_id)
	{
		// dumb way to work around mixed http(s) on single page
		$client_factory = \Drupal::service('http_client_factory');
		$client = $client_factory->fromOptions(['verify' => FALSE]);
		//\Drupal::logger("DEBUG_REST")->debug("media_id: {$media_id}\nuser_id: {$user_id}");

		$result = [
			"percentage" => "0",
		];

		$ret;
		try {
			$ret = $client->get("localhost:8080/api/status.php?media_id={$media_id}&user_id={$user_id}");
			$result = [
				"percentage" => $ret->getBody()->getContents(),
			];
		} catch (Exception $e) {
			\Drupal::logger("DEBUG_WORKBENCH")->debug($e->getMessage());
		}


		//Drupal::logger("DEBUG_REST")->debug(print_r($result, TRUE));

		$response = new ResourceResponse($result);
		$response->addCacheableDependency($result);

		return $response;
	}
}
