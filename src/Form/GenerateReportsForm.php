<?php

namespace Drupal\commerce_reports_retroactive\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_reports\ReportTypeManager;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Class GenerateReportsForm.
 */
class GenerateReportsForm extends FormBase {

  /**
   * Drupal\commerce_reports\ReportTypeManager definition.
   *
   * @var \Drupal\commerce_reports\ReportTypeManager
   */
  protected $pluginManagerCommerceReportType;

  /**
   * @var \Drupal\Core\Database\Database
   */
  protected $connection;

  /**
   * @var EntityTypeManager
   */
  protected $orderStorage;

  /**
   * Constructs a new GenerateReportsForm object.
   */
  public function __construct(
    ReportTypeManager $plugin_manager_commerce_report_type,
    Connection $database,
    EntityTypeManager $entityTypeManager
  ) {
    $this->pluginManagerCommerceReportType = $plugin_manager_commerce_report_type;
    $this->connection = $database;
    $this->orderStorage = $entityTypeManager->getStorage('commerce_order');
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.commerce_report_type'),
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'generate_reports_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $available = $this->getAvailableRetroactivePlugins();
    if (empty($available)) {
      $form['not_available'] = [
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => $this->t('There are no Commerce Report Type plugins available.  Please note that this does not work with plugins that already have existing reports.'),
      ];
    }
    else {
      $form['available'] = [
        '#type' => 'checkboxes',
        '#options' => $available,
        '#required' => TRUE,
        '#title' => $this->t('Plugins to generate reports for')
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Retroactive reports'),
    ];

    return $form;
  }

  /**
   * Get all plugins that don't currently have reports.
   */
  protected function getAvailableRetroactivePlugins() {
    $available = [];
    $reports_used = $this->connection->query("SELECT DISTINCT(type) FROM commerce_order_report")->fetchAllAssoc('type');
    $plugins = $this->pluginManagerCommerceReportType->getDefinitions();
    $available = array_diff_key($plugins, $reports_used);
    if (!empty($available)) {
      foreach ($available as $report_type => $plugin) {
        $available[$report_type] = $plugin['label'];
      }
    }

    return $available;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $plugins = array_filter($form_state->getValue('available'), function ($value, $key) {
      return $value === $key;
    }, ARRAY_FILTER_USE_BOTH);

    $query = "SELECT order_id FROM {commerce_order} WHERE state IN (:states[])";
    $params = [
      ':states[]' => [
        'fulfillment',
        'complete',
      ]
    ];
    $order_ids = $this->connection->query($query, $params)->fetchAll();

    // @TODO this isn't a real batch, need to figure out how to do it properly.
    if (!empty($order_ids)) {
      $per_op = 200;
      $num_operations = ceil(count($order_ids)/$per_op);
      $operations = [];
      for ($i = 0; $i < $num_operations; $i++) {
        $to_use = array_column(array_splice($order_ids, 0, $per_op), 'order_id');
        $operations[] = [
          '\Drupal\commerce_reports_retroactive\BackGenerateReports::generateReports',
          [$to_use, $plugins]
        ];
      }
      $batch = array(
        'title' => t('Generating Order Reports...'),
        'operations' => $operations,
        'finished' => '\Drupal\commerce_reports_retroactive\BackGenerateReports::generateReportsFinished',
        'progress_message' => $this->t('Creating reports ...')
      );

      batch_set($batch);
    }
    else {
      drupal_set_message(t('There are no orders to generate reports for.  This module does not currently generate retroactive reports for new plugins - only for orders that occurred before installing this module (PR\'s welcome :D))'));
    }
  }

};
