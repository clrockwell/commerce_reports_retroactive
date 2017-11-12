<?php


namespace Drupal\commerce_reports_retroactive;


class BackGenerateReports {

  public static function generateReports($order_ids, &$context) {
    $message = 'Generating Report...';
    $results = array();
    $orders = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->loadMultiple(array_keys($order_ids));
    $plugin_manager = \Drupal::service('plugin.manager.commerce_report_type');
    /** @var OrderReportTypeInterface[] $plugin_types */
    $plugin_types = $plugin_manager->getDefinitions();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    foreach ($orders as $order) {
      foreach ($plugin_types as $plugin_type) {
        $instance = $plugin_manager->createInstance($plugin_type['id'], []);
        $instance->generateReport($order);
        $results[$order->id()][] = $instance->getLabel();
      }
    }

    $context['message'] = $message;
    $context['results'] = $results;
  }

  public static function generateReportsFinished($success, $results, $operations) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One report processed.', '@count reports processed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

}