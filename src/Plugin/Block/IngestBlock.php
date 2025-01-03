<?php

namespace Drupal\digitalia_muni_workbench_ingest\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/** 
 * Provides test block with action button
 *
 * @Block(
 *   id = "digitalia_muni_ingest_items",
 *   admin_label = @Translation("Ingest items.")
 *   )
 */
class IngestBlock extends BlockBase
{
	/**
	 * {@inheritdoc}
	 */
	public function build() {
		return \Drupal::formBuilder()->getForm('Drupal\digitalia_muni_workbench_ingest\Form\IngestForm');	 
	}

}
