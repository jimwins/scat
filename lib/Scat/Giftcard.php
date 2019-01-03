<?php
namespace Scat;

include dirname(__FILE__).'/../php-barcode.php';

class Giftcard extends \Model implements \JsonSerializable {
  public function card() {
    return $self->id . $self->pin;
  }

  public function txns() {
    return $this->has_many('Giftcard_Txn', 'card_id');
  }

  public function jsonSerialize() {
    $history= array();
    $balance= 0.00;
    $latest= "";

    $txns= $this->txns()
             ->select('*')
             ->select_expr("IF(type = 'vendor' && YEAR(created) > 2013,
                               CONCAT(SUBSTRING(YEAR(created), 3, 2), number),
                               CONCAT(DATE_FORMAT(created, '%Y-'), number))",
                           'txn_name')
             ->left_outer_join('txn',
                               array('txn.id', '=', 'giftcard_txn.txn_id'))
             ->find_many();

    foreach ($txns as $txn) {
      $history[]= array( 'entered' => $txn->entered,
                         'amount' => $txn->amount,
                         'txn_id' => $txn->txn_id,
                         'txn_name' => $txn->txn_name );
      $balance= bcadd($balance, $txn->amount);
      $latest= $txn->entered;
    }

    return array(
      'id' => $this->id,
      'pin' => $this->pin,
      'card' => $this->id . $this->pin,
      'expires' => $this->expires,
      'history' => $history,
      'balance' => $balance,
      'latest' => $latest,
    );
  }

  public function getPDF() {
    $card= $this->id . $this->pin;

    $balance= 0.00;

    $txns= $this->txns()
             ->select('*')
             ->select_expr("IF(type = 'vendor' && YEAR(created) > 2013,
                               CONCAT(SUBSTRING(YEAR(created), 3, 2), number),
                               CONCAT(DATE_FORMAT(created, '%Y-'), number))",
                           'txn_name')
             ->left_outer_join('txn',
                               array('txn.id', '=', 'giftcard_txn.txn_id'))
             ->find_many();

    foreach ($txns as $txn) {
      $balance= bcadd($balance, $txn->amount);
    }

    $issued= (new \Datetime($this->issued))->format('l, F j, Y');

    /* Work around deprecations in FPDF code */
    $error_reporting= error_reporting(E_ALL ^ E_DEPRECATED);

    // initiate FPDI
    $pdf = new \FPDI('P', 'in', array(8.5, 11));
    // add a page
    $pdf->AddPage();
    // set the source file
    $pdf->setSourceFile("../print/blank-gift-card.pdf");
    // import page 1
    $tplIdx = $pdf->importPage(1);
    // use the imported page
    $pdf->useTemplate($tplIdx);

    // now write some text above the imported page
    $pdf->SetFont('Helvetica');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFontSize(($basefontsize= 18));

    if ($balance) {
      $width= $pdf->GetStringWidth('$' . $balance);
      $pdf->SetXY(4.25 - ($width / 2), 2.5);
      $pdf->Write(0, '$' . $balance);
    }

    $width= $pdf->GetStringWidth($issued);
    $pdf->SetXY(4.25 - ($width / 2), 3.25);
    $pdf->Write(0, $issued);

    $code= "RAW-$card";
    $type= "code39";

    \Barcode::fpdf($pdf, '000000',
                   4.25, 5,
                   0 /* angle */, $type,
                   $code, (1/72), $basefontsize/2/72);

    $pdf->SetFontSize(10);
    $width= $pdf->GetStringWidth($card);
    $pdf->SetXY(4.25 - ($width / 2), 5.2);
    $pdf->Write(0, $card);

    ob_start();
    $pdf->Output();
    $content= ob_get_contents();
    ob_end_clean();

    error_reporting($error_reporting);

    return $content;
  }
}

class Giftcard_Txn extends \Model {
  public function card() {
    return $this->belongs_to('Giftcard', 'card_id');
  }

  public function txn() {
    return $this->belongs_to('Txn');
  }
}
