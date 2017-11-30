<?php


namespace Drupal\commerce_reports_retroactive;


class BackGenerateReports {

  public static function generateReports(array $order_ids, array $plugins, &$context) {
    $message = 'Generating Report...';
    if (empty($context['sandbox'])) {
      $context['sandbox']['max'] = 50;
      $context['sandbox']['progress'] = 0;
    }

    $sandbox =& $context['sandbox'];
    $max = (int) $sandbox['max'];
    $progress =& $sandbox['progress'];
    $remaining = $max - $progress;


    $plugin_manager = \Drupal::service('plugin.manager.commerce_report_type');
    /** @var OrderReportTypeInterface[] $plugin_types */
    $plugin_types = $plugin_manager->getDefinitions();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */

    $context['message'] = t('Creating order report @n of @max', array('@n' => $progress, '@max' => $max));

    $orders = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->loadMultiple($order_ids);

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    foreach ($orders as $order) {
      foreach ($plugin_types as $plugin_type) {
        if (!in_array($plugin_type['id'], $plugins)) {
          continue;
        }
        $instance = $plugin_manager->createInstance($plugin_type['id'], []);
        $instance->generateReport($order);
        $results[$order->id()][] = $instance->getLabel();
      }
    }

    // Update progress.
    if ($progress != $max) {
      $context['finished'] = $progress / $max;
    }

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