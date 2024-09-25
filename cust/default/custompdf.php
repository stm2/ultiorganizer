<?php
include_once 'cust/default/pdfprinter.php';
include_once 'cust/default/CustomPDF.php';

class PDFCustomizer extends CustomPDF {

  function init() {
    parent::init();
    // implement for adding your own customizations
  }
    
}
