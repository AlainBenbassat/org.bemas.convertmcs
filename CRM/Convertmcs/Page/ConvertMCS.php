<?php
use CRM_Convertmcs_ExtensionUtil as E;

class CRM_Convertmcs_Page_ConvertMCS extends CRM_Core_Page {
  const DEFAULT_BATCH_LIMIT = 500;

  public function run() {
    CRM_Utils_System::setTitle('Convert (Primary) Member Contacts');

    $outputFromStats = '';
    $outputFromConversion = '<a href="convertmcs?reset=1&convert=1">Start conversie</a>';
    try {
      $action = $this->getAction();
      if ($action == 'convert') {
        $batchLimit = $this->getBatchLimit();
        $convertor = new CRM_Convertmcs_Convertor();
        $outputFromConversion = $convertor->start($batchLimit);
      }

      $inspector = new CRM_Convertmcs_Inspector();
      $outputFromStats = $inspector->getStats();
    }
    catch (Exception $e) {
      $output = '<p>ERROR: ' . $e->getMessage() . '</p>';
    }

    $this->assign('output', $outputFromStats . $outputFromConversion);

    parent::run();
  }

  private function getBatchLimit() {
    return CRM_Utils_Request::retrieveValue('limit', 'Integer', self::DEFAULT_BATCH_LIMIT);
  }

  private function getAction() {
    $convert = CRM_Utils_Request::retrieveValue('convert', 'Integer', 0, FALSE, 'GET');
    if ($convert == 1) {
      return 'convert';
    }
    else {
      return 'show_stats';
    }
  }

}
