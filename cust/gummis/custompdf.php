<?php
include_once 'cust/default/pdfprinter.php';
include_once 'cust/default/CustomPDF.php';

class PDFCustomizer extends CustomPDF {

  function init() {
    parent::init();
    
    $this->pdf->setOrganization("Gummis");
    $this->pdf->setLogo("cust/gummis/gummi_footer.png");
      
    $this->pdf->setFooter($this->pdf->getOrganization(), null, utf8_decode(date('Y-m-d H:i:s P', time())));
  }

  function getRosterInstructions() {
    // don't use
    return "";
  }

  // FIXME
  function setHeaderStyle(FPDF $fpdf, $size = 2) {
    $this->pdf->setHeaderStyle($fpdf, $size);
    $fpdf->SetFillColor(0, 127, 127);
  }

  function setPreFilledStyle(FPDF $fpdf, $size = 3, $family = NULL, $style = NULL) {
    $this->pdf->setPreFilledStyle($fpdf, $size, $family, $style);
    $fpdf->SetFillColor(211, 211, 211);
  }
}
