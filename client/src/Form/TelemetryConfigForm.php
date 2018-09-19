<?php

namespace Drupal\telemetry\Form;

use Drupal\Core\Form\ConfigFormBase;

class TelemetryConfigForm extends ConfigFormBase {
  /**
   * @inheritdoc
   */
  protected function getEditableConfigNames() {
    return ['telemetry.config'];
  }

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return 'telemetry.config';
  }
}
