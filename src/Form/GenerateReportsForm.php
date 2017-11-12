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
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Retroactive reports'),
    ];

    return $form;
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
    $earliest_report_order_id = $this->connection->query("SELECT MIN(order_id) FROM {commerce_order_report}")->fetchField();
    if ($earliest_report_order_id) {
      $query = "SELECT order_id FROM {commerce_order} WHERE order_id < :report_order_id AND state IN (:states[])";
      $params = [
        ':report_order_id' => $earliest_report_order_id,
        ':states[]' => [
          'fulfillment',
          'completed',
        ]
      ];
    }
    else {
      $query = "SELECT order_id FROM {commerce_order} WHERE state IN (:states[])";
      $params = [
        ':states[]' => [
          'fulfillment',
          'completed',
        ]
      ];
    }
    $order_ids = $this->connection->query($query, $params)->fetchAllAssoc('order_id');
    
    if (!empty($order_ids)) {
      $batch = array(
        'title' => t('Generating Order Reports...'),
        'operations' => array(
          array(
            '\Drupal\commerce_reports_retroactive\BackGenerateReports::generateReports',
            array($order_ids)
          ),
        ),
        'finished' => '\Drupal\commerce_reports_retroactive\BackGenerateReports::generateReportsFinished',
      );

      batch_set($batch);
    }
    else {
      drupal_set_message(t('There are no orders to generate reports for.  This module does not currently generate retroactive reports for new plugins - only for orders that occurred before installing this module (PR\'s welcome :D))'));
    }
  }

};
