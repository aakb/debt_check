<?php

check_payments();

function check_payments() {

  $do_print =  drush_get_option('print');
  $forced_email =  drush_get_option('always');

  $emails = array();
  foreach( explode(',', drush_get_option('email')) as $email){
    if (valid_email_address($email)) {
      $emails[] = $email;
    }
  }
  
  $day_to_check = drush_get_option('days', 2);
  if(!is_numeric($day_to_check)) {
    return;
  }

  $max_error_in_row = 4;

  $client = alma_client();
  if (!$client) {
    return;
  }
  // Construct sql-query. Note the sorting are desc so errorcounter are calculating correct
  // The reason are that the data from the librarysystem/alma are only keept for a certain time
  $sqlresult = db_query("SELECT payment_order_id, payment_price, payment_time, params FROM dibs_transactions 
where payment_status=1 AND UNIX_TIMESTAMP()-UNIX_TIMESTAMP(payment_time)<%d ORDER BY payment_time DESC;", $day_to_check * 86400 );

  $payments = array();
  while ($pay = db_fetch_array($sqlresult)) {
    $payments[] = $pay;
  }
  if($do_print) print 'Checking ' . count($payments) . " payments from last $day_to_check days - sending results to " . implode(',', $emails) . "\n\n";

  $errorcounter = 0;
  $errors = array();
  foreach ($payments as $pay) {

    if($do_print) print $pay['payment_order_id'] . ' ';

    $response = $client->request('patron/payment/confirmation', array('orderId' => $pay['payment_order_id']));

    $domlist = $response->getElementsByTagName('amount');
    $value = 0;
    if ( $domlist->length == 1 ) {
      $value = floatval(trim($domlist->item(0)->getAttribute('value')));
    }
    // compare the payed amount with the data from the library system
    if ( floatval($pay['payment_price']) != $value ) {
     
      $params = unserialize($pay['params']);
      sort($params['selected_debts']);

      $result  = $pay['payment_time'] . "\n";
      $result .= 'orderid: ' . $pay['payment_order_id'] . "\n"; 
      $result .= 'ddelibra: ' . implode(',', $params['selected_debts']) . "\n";
      $result .= 'dibs: ' . $pay['payment_price'] . "\n";
      $result .= 'alma: ' . $value . ( $value > 0 ? ' (possibly error in alma)' : '' ) . "\n";
      $result .= 'parturl: ' . 'patron/payments/add?' . http_build_query( array('debts' => implode(',', array_filter( $params['selected_debts']) ),  'orderId' => $pay['payment_order_id']), '', '&') . "\n";

      if($do_print) print "ERROR\n" . $result . "\n";

      $errors[] = $result;

      $errorcounter++;
    } else {
      if($do_print) print "OK " . $pay['payment_time'] . " $value\n";
      $errorcounter = 0;
    }

    if( $errorcounter >= $max_error_in_row ) {
      // drop the extra errors
      $errors = array_slice( $errors, 0, -$max_error_in_row);
      $errors[] = "NOTE: at least $max_error_in_row errors in a row - possibly no data in library system.\n";
      break;
    }
    usleep( 500000 );//dont misuse alma
  }
  if(!empty($emails) && (!empty($errors) || $forced_email)) {
    if($do_print) print "Sending mail\n";
    
    $subject = 'DIBS check of payments ';
    $body = count($payments) . ' payments from last ' . $day_to_check . " days are checked\n\n";
    if(empty($errors)){
      $subject .= 'OK';
      $body .= 'OK';
    } else {
      $subject .= 'ERROR';
      $body .= "The following payments needs to be checked manually\n\n" . implode("\n", $errors);
    }

    $from_address = variable_get('site_mail', ini_get('sendmail_from'));
    $message = array(
    'to' => implode(',', $emails),
    'from' => $from_address,
    'subject' => $subject,
    'body' => $body, 
    'headers' => array( ),
    );
    drupal_mail_send($message);
  }
}
?>
